<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Gradebook_model extends MY_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function teacherCanGrade($teacherID, $classID, $sectionID, $subjectID, $branchID)
    {
        $this->db->from('subject_assign');
        $this->db->where(array(
            'teacher_id' => $teacherID,
            'class_id' => $classID,
            'section_id' => $sectionID,
            'subject_id' => $subjectID,
            'branch_id' => $branchID,
            'session_id' => get_session_id(),
        ));
        return $this->db->count_all_results() > 0;
    }

    public function getTeacherSubjects($teacherID, $branchID)
    {
        $this->db->select('sa.class_id, sa.section_id, sa.subject_id, c.name as class_name, se.name as section_name, s.name as subject_name');
        $this->db->from('subject_assign as sa');
        $this->db->join('class as c', 'c.id = sa.class_id', 'inner');
        $this->db->join('section as se', 'se.id = sa.section_id', 'inner');
        $this->db->join('subject as s', 's.id = sa.subject_id', 'inner');
        $this->db->where('sa.teacher_id', $teacherID);
        $this->db->where('sa.branch_id', $branchID);
        $this->db->where('sa.session_id', get_session_id());
        $this->db->group_by(array('sa.class_id', 'sa.section_id', 'sa.subject_id'));
        $this->db->order_by('c.name', 'ASC');
        return $this->db->get()->result_array();
    }

    public function getTerms($sessionID = null, $activeOnly = true)
    {
        $sessionID = $sessionID ?: get_session_id();
        $this->db->where('session_id', $sessionID);
        $this->db->where('branch_id', 0);
        if ($activeOnly) {
            $this->db->where('is_active', 1);
        }
        return $this->db->order_by('term_order', 'ASC')->get('gradebook_term')->result_array();
    }

    public function getTerm($termID)
    {
        return $this->db->where(array('id' => $termID, 'session_id' => get_session_id(), 'branch_id' => 0))
            ->get('gradebook_term')->row_array();
    }

    public function saveTerms($names, $createdBy)
    {
        $clean = array();
        foreach ((array)$names as $name) {
            $name = trim((string)$name);
            if ($name !== '') {
                $clean[] = $name;
            }
        }
        if (empty($clean)) {
            return false;
        }

        $this->db->trans_start();
        $existing = $this->getTerms(get_session_id(), false);
        $existingByOrder = array();
        foreach ($existing as $row) {
            $existingByOrder[(int)$row['term_order']] = $row;
        }

        foreach ($clean as $index => $name) {
            $order = $index + 1;
            if (isset($existingByOrder[$order])) {
                $this->db->where('id', $existingByOrder[$order]['id'])->update('gradebook_term', array(
                    'name' => $name,
                    'is_active' => 1,
                ));
            } else {
                $this->db->insert('gradebook_term', array(
                    'session_id' => get_session_id(),
                    'branch_id' => 0,
                    'name' => $name,
                    'term_order' => $order,
                    'is_active' => 1,
                    'created_by' => $createdBy,
                ));
            }
        }

        if (count($existing) > count($clean)) {
            $keep = range(1, count($clean));
            $this->db->where('session_id', get_session_id())->where('branch_id', 0)
                ->where_not_in('term_order', $keep)->update('gradebook_term', array('is_active' => 0));
        }
        $this->db->trans_complete();
        return $this->db->trans_status();
    }

    public function getOrCreateScheme($branchID, $classID, $sectionID, $subjectID, $createdBy, $termID = null)
    {
        $termID = $termID ? (int)$termID : null;
        $where = array(
            'branch_id' => $branchID,
            'session_id' => get_session_id(),
            'term_id' => $termID,
            'class_id' => $classID,
            'section_id' => $sectionID,
            'subject_id' => $subjectID,
        );
        $scheme = $this->db->where($where)->get('gradebook_scheme')->row_array();
        if (!empty($scheme)) {
            return $scheme;
        }

        $this->db->insert('gradebook_scheme', $where + array(
            'total_mark' => 100,
            'created_by' => $createdBy,
        ));
        $id = $this->db->insert_id();
        return $this->db->get_where('gradebook_scheme', array('id' => $id))->row_array();
    }

    public function syncExamComponents($scheme)
    {
        $this->db->select('te.id as timetable_id, te.exam_id, te.mark_distribution, e.name as exam_name, e.gradebook_weight');
        $this->db->from('timetable_exam as te');
        $this->db->join('exam as e', 'e.id = te.exam_id', 'inner');
        $this->db->join('exam_term as et', 'et.id = e.term_id', 'left');
        $this->db->join('gradebook_term as gt', "gt.session_id = e.session_id AND gt.branch_id = 0 AND gt.name = et.name", 'left', false);
        $this->db->where('te.branch_id', $scheme['branch_id']);
        $this->db->where('te.session_id', $scheme['session_id']);
        $this->db->where('te.class_id', $scheme['class_id']);
        $this->db->where('te.section_id', $scheme['section_id']);
        $this->db->where('te.subject_id', $scheme['subject_id']);
        if (!empty($scheme['term_id'])) {
            $this->db->group_start();
            $this->db->where('e.grading_term_id', $scheme['term_id']);
            $this->db->or_group_start();
            $this->db->where('e.grading_term_id IS NULL', null, false);
            $this->db->where('gt.id', $scheme['term_id']);
            $this->db->group_end();
            $this->db->group_end();
        }
        $this->db->order_by('te.exam_date', 'ASC');
        $rows = $this->db->get()->result_array();

        $sort = 1;
        foreach ($rows as $row) {
            $distribution = json_decode($row['mark_distribution'], true);
            $maxMark = 0;
            if (is_array($distribution)) {
                foreach ($distribution as $assessment) {
                    if (is_array($assessment) && isset($assessment['full_mark']) && is_numeric($assessment['full_mark'])) {
                        $maxMark += (float)$assessment['full_mark'];
                    }
                }
            }
            // Use the exam's explicit percentage when configured. For older exams without
            // a weight, preserve the previous behavior by using the raw full-mark total.
            if (isset($row['gradebook_weight']) && $row['gradebook_weight'] !== null && (float)$row['gradebook_weight'] > 0) {
                $maxMark = (float)$row['gradebook_weight'];
            }
            if ($maxMark <= 0) {
                continue;
            }
            $existing = $this->db->where(array('scheme_id' => $scheme['id'], 'exam_id' => $row['exam_id']))
                ->get('gradebook_component')->row_array();
            $component = array(
                'scheme_id' => $scheme['id'],
                'name' => $row['exam_name'],
                'component_type' => 'exam',
                'exam_id' => $row['exam_id'],
                'max_mark' => $maxMark,
                'sort_order' => $sort++,
                'created_by' => $scheme['created_by'],
            );
            if ($existing) {
                $this->db->where('id', $existing['id'])->update('gradebook_component', $component);
            } else {
                $this->db->insert('gradebook_component', $component);
            }
        }
        return $this->getComponents($scheme['id']);
    }

    public function getComponents($schemeID)
    {
        return $this->db->where('scheme_id', $schemeID)
            ->order_by('component_type', 'ASC')->order_by('sort_order', 'ASC')->order_by('id', 'ASC')
            ->get('gradebook_component')->result_array();
    }

    public function getComponent($id, $schemeID)
    {
        return $this->db->where(array('id' => $id, 'scheme_id' => $schemeID))
            ->get('gradebook_component')->row_array();
    }

    public function getComponentTotal($schemeID)
    {
        $row = $this->db->select_sum('max_mark')->where('scheme_id', $schemeID)->get('gradebook_component')->row_array();
        return (float)($row['max_mark'] ?: 0);
    }

    public function addTeacherComponent($schemeID, $name, $maxMark, $createdBy)
    {
        return $this->db->insert('gradebook_component', array(
            'scheme_id' => $schemeID, 'name' => $name, 'component_type' => 'teacher',
            'exam_id' => null, 'max_mark' => $maxMark, 'sort_order' => 999, 'created_by' => $createdBy,
        ));
    }

    public function deleteTeacherComponent($componentID, $schemeID)
    {
        $component = $this->getComponent($componentID, $schemeID);
        if (empty($component) || $component['component_type'] !== 'teacher') return false;
        $this->db->where('component_id', $componentID)->delete('gradebook_mark');
        $this->db->where(array('id' => $componentID, 'scheme_id' => $schemeID))->delete('gradebook_component');
        return true;
    }

    public function getStudents($branchID, $classID, $sectionID)
    {
        return $this->db->select('e.student_id, e.roll, s.first_name, s.last_name, s.register_no')
            ->from('enroll as e')->join('student as s', 's.id = e.student_id', 'inner')
            ->where('e.branch_id', $branchID)->where('e.session_id', get_session_id())
            ->where('e.class_id', $classID)->where('e.section_id', $sectionID)
            ->order_by('e.roll', 'ASC')->get()->result_array();
    }

    public function getTeacherMarks($schemeID)
    {
        $rows = $this->db->select('gm.component_id, gm.student_id, gm.raw_mark, gm.is_absent')
            ->from('gradebook_mark as gm')->join('gradebook_component as gc', 'gc.id = gm.component_id', 'inner')
            ->where('gc.scheme_id', $schemeID)->get()->result_array();
        $map = array();
        foreach ($rows as $row) $map[$row['component_id']][$row['student_id']] = $row;
        return $map;
    }

    /** Sum the original full marks for a scheduled exam/subject. */
    private function getExamRawMaximum($examID, $classID, $sectionID, $subjectID)
    {
        $row = $this->db->select('mark_distribution')
            ->where(array(
                'exam_id' => $examID,
                'class_id' => $classID,
                'section_id' => $sectionID,
                'subject_id' => $subjectID,
                'session_id' => get_session_id(),
            ))
            ->get('timetable_exam')->row_array();
        if (empty($row)) return 0;
        $distribution = json_decode($row['mark_distribution'], true);
        $maximum = 0;
        if (is_array($distribution)) {
            foreach ($distribution as $item) {
                if (is_array($item) && isset($item['full_mark']) && is_numeric($item['full_mark'])) {
                    $maximum += max(0, (float)$item['full_mark']);
                }
            }
        }
        return $maximum;
    }

    public function getExamScores($students, $examComponents, $classID, $sectionID, $subjectID)
    {
        $result = array();
        foreach ($examComponents as $component) {
            $result[$component['id']] = array();
            // Resolve once per exam component, not once per student.
            $rawMaximum = $this->getExamRawMaximum($component['exam_id'], $classID, $sectionID, $subjectID);
            foreach ($students as $student) {
                $markRow = $this->db->where(array(
                    'student_id' => $student['student_id'], 'exam_id' => $component['exam_id'],
                    'class_id' => $classID, 'section_id' => $sectionID, 'subject_id' => $subjectID,
                    'session_id' => get_session_id(),
                ))->get('mark')->row_array();
                $score = 0; $absent = false;
                if (!empty($markRow)) {
                    $absent = !empty($markRow['absent']);
                    if (!$absent && !empty($markRow['mark'])) {
                        $values = json_decode($markRow['mark'], true);
                        if (is_array($values)) foreach ($values as $value) if (is_numeric($value)) $score += (float)$value;
                    }
                }
                // Exam marks are entered on their original full-mark scale (for example
                // 40/50). Convert them proportionally to this component's gradebook weight
                // (for example 16/20) instead of incorrectly clamping raw marks to 20.
                if ($rawMaximum > 0 && (float)$component['max_mark'] > 0) {
                    $score = ($score / $rawMaximum) * (float)$component['max_mark'];
                }
                $score = min($score, (float)$component['max_mark']);
                $result[$component['id']][$student['student_id']] = array('score' => $score, 'absent' => $absent, 'entered' => !empty($markRow));
            }
        }
        return $result;
    }

    public function saveTeacherMarks($schemeID, $marks)
    {
        $components = $this->getComponents($schemeID);
        $allowed = array();
        foreach ($components as $component) if ($component['component_type'] === 'teacher') $allowed[$component['id']] = (float)$component['max_mark'];
        $this->db->trans_start();
        foreach ($marks as $studentID => $studentMarks) {
            foreach ((array)$studentMarks as $componentID => $value) {
                if (!array_key_exists($componentID, $allowed)) continue;
                if ($value === '' || $value === null) {
                    $this->db->where(array('component_id' => $componentID, 'student_id' => $studentID))->delete('gradebook_mark');
                    continue;
                }
                $value = max(0, min((float)$value, $allowed[$componentID]));
                $exists = $this->db->where(array('component_id' => $componentID, 'student_id' => $studentID))->get('gradebook_mark')->row_array();
                $data = array('component_id' => $componentID, 'student_id' => $studentID, 'raw_mark' => $value, 'is_absent' => 0);
                if ($exists) $this->db->where('id', $exists['id'])->update('gradebook_mark', $data);
                else $this->db->insert('gradebook_mark', $data);
            }
        }
        $this->db->trans_complete();
        return $this->db->trans_status();
    }

    public function getSubjectsForClassSection($branchID, $classID, $sectionID)
    {
        $this->db->select('sa.subject_id, s.name as subject_name');
        $this->db->from('subject_assign as sa')->join('subject as s', 's.id = sa.subject_id', 'inner');
        $this->db->where(array('sa.branch_id' => $branchID, 'sa.session_id' => get_session_id(), 'sa.class_id' => $classID, 'sa.section_id' => $sectionID));
        $this->db->group_by(array('sa.subject_id', 's.name'))->order_by('s.name', 'ASC');
        return $this->db->get()->result_array();
    }

    public function getSubjectTermScores($branchID, $classID, $sectionID, $subjectID, $termID, $students = null)
    {
        $students = $students === null ? $this->getStudents($branchID, $classID, $sectionID) : $students;
        $scheme = $this->getOrCreateScheme($branchID, $classID, $sectionID, $subjectID, get_loggedin_user_id(), $termID);
        $components = $this->syncExamComponents($scheme);
        $teacherMarks = $this->getTeacherMarks($scheme['id']);
        $examComponents = array();
        foreach ($components as $component) if ($component['component_type'] === 'exam') $examComponents[] = $component;
        $examScores = $this->getExamScores($students, $examComponents, $classID, $sectionID, $subjectID);
        $scores = array();
        foreach ($students as $student) {
            $total = 0;
            foreach ($components as $component) {
                $sid = $student['student_id'];
                if ($component['component_type'] === 'exam') $total += isset($examScores[$component['id']][$sid]) ? (float)$examScores[$component['id']][$sid]['score'] : 0;
                else $total += isset($teacherMarks[$component['id']][$sid]) ? (float)$teacherMarks[$component['id']][$sid]['raw_mark'] : 0;
            }
            $scores[$sid] = min(100, max(0, $total));
        }
        return array('scheme' => $scheme, 'components' => $components, 'scores' => $scores, 'total' => $this->getComponentTotal($scheme['id']));
    }


    /** Read-only term grade breakdown for a single student. Does not create gradebook schemes. */
    public function getStudentTermGrade($branchID, $studentID, $termID)
    {
        $student = $this->db->select('e.student_id,e.class_id,e.section_id,e.branch_id,c.name as class_name,se.name as section_name,s.first_name,s.last_name,s.register_no')
            ->from('enroll as e')->join('student as s','s.id=e.student_id','inner')
            ->join('class as c','c.id=e.class_id','left')->join('section as se','se.id=e.section_id','left')
            ->where(array('e.student_id'=>$studentID,'e.branch_id'=>$branchID,'e.session_id'=>get_session_id()))->get()->row_array();
        if (empty($student)) return false;
        $term = $this->getTerm($termID); if (empty($term)) return false;
        $subjects = $this->getSubjectsForClassSection($branchID,$student['class_id'],$student['section_id']);
        $rows = array();
        foreach ($subjects as $subject) {
            $scheme = $this->db->where(array('branch_id'=>$branchID,'session_id'=>get_session_id(),'term_id'=>$termID,'class_id'=>$student['class_id'],'section_id'=>$student['section_id'],'subject_id'=>$subject['subject_id']))->get('gradebook_scheme')->row_array();
            if (empty($scheme)) {
                $rows[] = array('subject_id'=>$subject['subject_id'],'subject_name'=>$subject['subject_name'],'score'=>0,'components'=>array(),'has_scheme'=>false);
                continue;
            }
            $this->syncExamComponents($scheme);
            $components = $this->getComponents($scheme['id']);
            $teacherMarks = $this->getTeacherMarks($scheme['id']);
            $breakdown = array(); $total = 0;
            foreach ($components as $component) {
                $score = 0; $max = (float)$component['max_mark'];
                if ($component['component_type'] === 'exam') {
                    $markRow = $this->db->where(array('student_id'=>$studentID,'exam_id'=>$component['exam_id'],'class_id'=>$student['class_id'],'section_id'=>$student['section_id'],'subject_id'=>$subject['subject_id'],'session_id'=>get_session_id()))->get('mark')->row_array();
                    if (!empty($markRow) && empty($markRow['absent']) && !empty($markRow['mark'])) {
                        $values=json_decode($markRow['mark'],true); if(is_array($values)) foreach($values as $v) if(is_numeric($v)) $score += (float)$v;
                    }
                } else {
                    $score = isset($teacherMarks[$component['id']][$studentID]) ? (float)$teacherMarks[$component['id']][$studentID]['raw_mark'] : 0;
                }
                $score=min($score,$max); $total += $score;
                $breakdown[] = array('name'=>$component['name'],'type'=>$component['component_type'],'score'=>$score,'max_mark'=>$max);
            }
            $rows[] = array('subject_id'=>$subject['subject_id'],'subject_name'=>$subject['subject_name'],'score'=>min(100,max(0,$total)),'components'=>$breakdown,'has_scheme'=>true);
        }
        return array('student'=>$student,'term'=>$term,'subjects'=>$rows);
    }

    public function getClassTermReport($branchID, $classID, $sectionID, $termID)
    {
        $students = $this->getStudents($branchID, $classID, $sectionID);
        $subjects = $this->getSubjectsForClassSection($branchID, $classID, $sectionID);
        $subjectScores = array();
        foreach ($subjects as $subject) {
            $subjectScores[$subject['subject_id']] = $this->getSubjectTermScores($branchID, $classID, $sectionID, $subject['subject_id'], $termID, $students);
        }

        $rows = array();
        foreach ($students as $student) {
            $total = 0; $count = 0; $scores = array();
            foreach ($subjects as $subject) {
                $score = isset($subjectScores[$subject['subject_id']]['scores'][$student['student_id']]) ? $subjectScores[$subject['subject_id']]['scores'][$student['student_id']] : 0;
                $scores[$subject['subject_id']] = $score;
                $total += $score; $count++;
            }
            $average = $count > 0 ? $total / $count : 0;
            $rows[] = array('student' => $student, 'scores' => $scores, 'total' => $total, 'average' => $average, 'subjects_count' => $count);
        }
        usort($rows, function($a, $b) {
            if (abs($a['average'] - $b['average']) < 0.00001) return strcasecmp($a['student']['first_name'].' '.$a['student']['last_name'], $b['student']['first_name'].' '.$b['student']['last_name']);
            return $a['average'] < $b['average'] ? 1 : -1;
        });
        $rank = 0; $position = 0; $lastAverage = null;
        foreach ($rows as &$row) {
            $position++;
            if ($lastAverage === null || abs($lastAverage - $row['average']) > 0.00001) $rank = $position;
            $row['rank'] = $rank; $lastAverage = $row['average'];
        }
        unset($row);
        return array('students' => $rows, 'subjects' => $subjects, 'subject_data' => $subjectScores, 'term' => $this->getTerm($termID));
    }

    public function getAnnualReport($branchID, $classID, $sectionID, $terms)
    {
        $students = $this->getStudents($branchID, $classID, $sectionID);
        $subjects = $this->getSubjectsForClassSection($branchID, $classID, $sectionID);
        $termReports = array();
        foreach ($terms as $term) {
            $termReports[$term['id']] = $this->getClassTermReport($branchID, $classID, $sectionID, $term['id']);
        }

        $rows = array();
        foreach ($students as $student) {
            $termAverages = array();
            $termScores = array();
            $annualSum = 0;
            $termCount = 0;
            foreach ($terms as $term) {
                $found = null;
                foreach ($termReports[$term['id']]['students'] as $r) {
                    if ($r['student']['student_id'] == $student['student_id']) {
                        $found = $r;
                        break;
                    }
                }
                $avg = $found ? $found['average'] : 0;
                $termAverages[$term['id']] = $avg;
                $termScores[$term['id']] = $found ? $found['scores'] : array();
                $annualSum += $avg;
                $termCount++;
            }
            $rows[] = array(
                'student' => $student,
                'term_averages' => $termAverages,
                'term_scores' => $termScores,
                'annual_average' => $termCount ? $annualSum / $termCount : 0,
            );
        }
        usort($rows, function($a,$b){
            return abs($a['annual_average'] - $b['annual_average']) < 0.00001
                ? strcasecmp($a['student']['first_name'].' '.$a['student']['last_name'], $b['student']['first_name'].' '.$b['student']['last_name'])
                : ($a['annual_average'] < $b['annual_average'] ? 1 : -1);
        });
        $rank = 0; $pos = 0; $last = null;
        foreach ($rows as &$row) {
            $pos++;
            if ($last === null || abs($last - $row['annual_average']) > 0.00001) $rank = $pos;
            $row['rank'] = $rank;
            $last = $row['annual_average'];
        }
        unset($row);
        return array('students'=>$rows,'subjects'=>$subjects,'term_reports'=>$termReports,'terms'=>$terms);
    }

}
