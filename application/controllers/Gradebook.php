<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Gradebook extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('gradebook_model');
        $this->load->model('exam_model');
        $this->load->model('subject_model');
    }

    private function canManage() { return get_permission('exam_mark', 'is_view'); }

    private function currentBranch()
    {
        $branch = $this->input->post('branch_id') ?: $this->input->get('branch_id');
        return is_superadmin_loggedin() && $branch ? $branch : $this->application_model->get_branch_id();
    }

    private function teacherCanGrade($classID, $sectionID, $subjectID)
    {
        if (loggedin_role_id() != 3) return true;
        return $this->gradebook_model->teacherCanGrade(get_loggedin_user_id(), $classID, $sectionID, $subjectID, $this->currentBranch());
    }

    private function selectedTerm()
    {
        $termID = $this->input->post('term_id') ?: $this->input->get('term_id');
        if ($termID) return $this->gradebook_model->getTerm($termID);
        $terms = $this->gradebook_model->getTerms();
        return !empty($terms) ? $terms[0] : null;
    }

    public function index()
    {
        if (!$this->canManage()) access_denied();
        $branchID = $this->currentBranch();
        $classID = $this->input->post('class_id') ?: $this->input->get('class_id');
        $sectionID = $this->input->post('section_id') ?: $this->input->get('section_id');
        $subjectID = $this->input->post('subject_id') ?: $this->input->get('subject_id');
        $term = $this->selectedTerm();

        $this->data['branch_id']=$branchID; $this->data['class_id']=$classID; $this->data['section_id']=$sectionID;
        $this->data['subject_id']=$subjectID; $this->data['terms']=$this->gradebook_model->getTerms();
        $this->data['term_id']=$term ? $term['id'] : '';
        $this->data['term']=$term;

        if (loggedin_role_id()==3) $this->data['teacher_subjects']=$this->gradebook_model->getTeacherSubjects(get_loggedin_user_id(),$branchID); else $this->data['teacher_subjects']=array();

        if (!empty($classID)&&!empty($sectionID)&&!empty($subjectID)&&!empty($term)) {
            if (!$this->teacherCanGrade($classID,$sectionID,$subjectID)) access_denied();
            $scheme=$this->gradebook_model->getOrCreateScheme($branchID,$classID,$sectionID,$subjectID,get_loggedin_user_id(),$term['id']);
            $components=$this->gradebook_model->syncExamComponents($scheme);
            $students=$this->gradebook_model->getStudents($branchID,$classID,$sectionID);
            $teacherMarks=$this->gradebook_model->getTeacherMarks($scheme['id']);
            $examComponents=array(); foreach($components as $component) if($component['component_type']==='exam') $examComponents[]=$component;
            $examScores=$this->gradebook_model->getExamScores($students,$examComponents,$classID,$sectionID,$subjectID);
            $this->data['scheme']=$scheme; $this->data['components']=$components; $this->data['students']=$students;
            $this->data['teacher_marks']=$teacherMarks; $this->data['exam_scores']=$examScores;
            $this->data['component_total']=$this->gradebook_model->getComponentTotal($scheme['id']);
            $this->data['remaining_mark']=max(0,100-$this->data['component_total']);
            $this->data['class_name']=get_type_name_by_id('class',$classID); $this->data['section_name']=get_type_name_by_id('section',$sectionID);
            $this->data['subject_name']=get_type_name_by_id('subject',$subjectID);
            $this->data['grade_ranges']=$this->db->where('branch_id',$branchID)->order_by('lower_mark','DESC')->get('grade')->result_array();
        }
        $this->data['sub_page']='gradebook/index'; $this->data['main_menu']='mark'; $this->data['title']='Gradebook';
        $this->load->view('layout/index',$this->data);
    }

    public function save_component()
    {
        if (!$this->canManage()) ajax_access_denied();
        $classID=$this->input->post('class_id'); $sectionID=$this->input->post('section_id'); $subjectID=$this->input->post('subject_id'); $termID=$this->input->post('term_id');
        if (!$classID||!$sectionID||!$subjectID||!$termID||!$this->teacherCanGrade($classID,$sectionID,$subjectID)) ajax_access_denied();
        $term=$this->gradebook_model->getTerm($termID); if(empty($term)) ajax_access_denied();
        $name=trim($this->input->post('name')); $maxMark=$this->input->post('max_mark');
        if($name===''||!is_numeric($maxMark)||(float)$maxMark<=0){echo json_encode(array('status'=>'fail','error'=>array('max_mark'=>'Enter a valid component percentage.')));return;}
        $scheme=$this->gradebook_model->getOrCreateScheme($this->currentBranch(),$classID,$sectionID,$subjectID,get_loggedin_user_id(),$termID);
        $this->gradebook_model->syncExamComponents($scheme); $used=$this->gradebook_model->getComponentTotal($scheme['id']); $maxMark=(float)$maxMark;
        if($used+$maxMark>100.0001){echo json_encode(array('status'=>'fail','error'=>array('max_mark'=>'The term grading scheme cannot exceed 100%. Remaining: '.max(0,100-$used).'%')));return;}
        $this->gradebook_model->addTeacherComponent($scheme['id'],$name,$maxMark,get_loggedin_user_id());
        echo json_encode(array('status'=>'success','url'=>base_url('gradebook?class_id='.$classID.'&section_id='.$sectionID.'&subject_id='.$subjectID.'&term_id='.$termID.'&branch_id='.$this->currentBranch())));
    }

    public function delete_component($id='')
    {
        if(!$this->canManage()) access_denied();
        $component=$this->db->where('id',$id)->get('gradebook_component')->row_array(); if(empty($component)) return;
        $scheme=$this->db->where('id',$component['scheme_id'])->get('gradebook_scheme')->row_array();
        if(empty($scheme)||!$this->teacherCanGrade($scheme['class_id'],$scheme['section_id'],$scheme['subject_id'])) access_denied();
        if($this->gradebook_model->deleteTeacherComponent($id,$component['scheme_id'])) set_alert('success','Grade component deleted successfully.');
        redirect('gradebook?class_id='.$scheme['class_id'].'&section_id='.$scheme['section_id'].'&subject_id='.$scheme['subject_id'].'&term_id='.$scheme['term_id']);
    }

    public function save_marks()
    {
        if(!$this->canManage()) ajax_access_denied();
        $classID=$this->input->post('class_id'); $sectionID=$this->input->post('section_id'); $subjectID=$this->input->post('subject_id'); $termID=$this->input->post('term_id');
        if(!$classID||!$sectionID||!$subjectID||!$termID||!$this->teacherCanGrade($classID,$sectionID,$subjectID)) ajax_access_denied();
        if(empty($this->gradebook_model->getTerm($termID))) ajax_access_denied();
        $scheme=$this->gradebook_model->getOrCreateScheme($this->currentBranch(),$classID,$sectionID,$subjectID,get_loggedin_user_id(),$termID);
        $this->gradebook_model->syncExamComponents($scheme); $componentTotal=$this->gradebook_model->getComponentTotal($scheme['id']);
        if(abs($componentTotal-100)>0.0001){echo json_encode(array('status'=>'fail','error'=>array('marks'=>'Complete the '.$this->gradebook_model->getTerm($termID)['name'].' grading scheme to exactly 100%. Current total: '.$componentTotal.'%.')));return;}
        $students=$this->gradebook_model->getStudents($this->currentBranch(),$classID,$sectionID); $studentIDs=array(); foreach($students as $student)$studentIDs[(string)$student['student_id']]=true;
        $marks=$this->input->post('marks'); if(!is_array($marks))$marks=array();
        foreach($marks as $studentID=>$studentMarks) if(!isset($studentIDs[(string)$studentID])||!is_array($studentMarks)) unset($marks[$studentID]);
        echo json_encode(array('status'=>$this->gradebook_model->saveTeacherMarks($scheme['id'],$marks)?'success':'fail','message'=>'Student term grades have been saved successfully.','error'=>array('marks'=>'Unable to save the grades.')));
    }

    public function terms()
    {
        if(!is_superadmin_loggedin()) access_denied();
        if($_POST){
            $names=$this->input->post('term_names');
            $count=(int)$this->input->post('term_count');
            if($count<1){set_alert('error','Term count must be at least 1.');redirect('gradebook/terms');}
            $prepared=array();
            for($i=0;$i<$count;$i++) $prepared[] = isset($names[$i]) && trim($names[$i])!=='' ? trim($names[$i]) : 'Term '.($i+1);
            $normalized=array_map(function($v){ return strtolower(trim($v)); },$prepared);
            if(count($normalized)!==count(array_unique($normalized))){ set_alert('error','Term / semester names must be unique.'); redirect('gradebook/terms'); }
            if($this->gradebook_model->saveTerms($prepared,get_loggedin_user_id())) set_alert('success','Academic grading terms updated successfully.');
            else set_alert('error','Unable to update the academic grading terms.');
            redirect('gradebook/terms');
        }
        $this->data['terms']=$this->gradebook_model->getTerms(); $this->data['sub_page']='gradebook/terms'; $this->data['main_menu']='mark'; $this->data['title']='Academic Terms / Semesters';
        $this->load->view('layout/index',$this->data);
    }

    private function reportData()
    {
        $branchID=$this->currentBranch(); $classID=$this->input->get('class_id'); $sectionID=$this->input->get('section_id'); $termID=$this->input->get('term_id');
        if(!$branchID||!$classID||!$sectionID||!$termID) return false;
        $term=$this->gradebook_model->getTerm($termID); if(empty($term)) return false;
        return array('branch_id'=>$branchID,'class_id'=>$classID,'section_id'=>$sectionID,'term_id'=>$termID,'term'=>$term,'report'=>$this->gradebook_model->getClassTermReport($branchID,$classID,$sectionID,$termID));
    }

    public function report()
    {
        if(!$this->canManage()) access_denied();
        $this->data['terms']=$this->gradebook_model->getTerms(); $this->data['branch_id']=$this->currentBranch();
        $this->data['class_id']=$this->input->get('class_id'); $this->data['section_id']=$this->input->get('section_id'); $this->data['term_id']=$this->input->get('term_id') ?: (!empty($this->data['terms'])?$this->data['terms'][0]['id']:'');
        $this->data['reportData']=$this->reportData();
        $this->data['sub_page']='gradebook/report'; $this->data['main_menu']='mark'; $this->data['title']='Term Results & Ranking';
        $this->load->view('layout/index',$this->data);
    }

    public function export_term_csv()
    {
        if(!$this->canManage()) access_denied(); $data=$this->reportData(); if(!$data) show_error('Invalid report selection.',400);
        $r=$data['report']; $filename='term_'.$data['term_id'].'_'.date('Ymd_His').'.csv';
        header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="'.$filename.'"');
        $out=fopen('php://output','w'); $head=array('Rank','Student','Register No'); foreach($r['subjects'] as $s)$head[]=$s['subject_name'].' / 100'; $head[]='Total';$head[]='Average / 100'; fputcsv($out,$head);
        foreach($r['students'] as $row){$line=array($row['rank'],$row['student']['first_name'].' '.$row['student']['last_name'],$row['student']['register_no']);foreach($r['subjects'] as $s)$line[]=number_format((float)$row['scores'][$s['subject_id']],2,'.','');$line[]=number_format($row['total'],2,'.','');$line[]=number_format($row['average'],2,'.','');fputcsv($out,$line);} fclose($out); exit;
    }

    public function export_annual_csv()
    {
        if(!$this->canManage()) access_denied(); $branchID=$this->currentBranch(); $classID=$this->input->get('class_id');$sectionID=$this->input->get('section_id');$terms=$this->gradebook_model->getTerms();
        if(!$branchID||!$classID||!$sectionID||empty($terms))show_error('Invalid annual report selection.',400);
        $r=$this->gradebook_model->getAnnualReport($branchID,$classID,$sectionID,$terms); header('Content-Type:text/csv; charset=utf-8');header('Content-Disposition:attachment; filename="annual_results_'.date('Ymd_His').'.csv"');$out=fopen('php://output','w');$head=array('Rank','Student','Register No');foreach($terms as $t){foreach($r['subjects'] as $sub)$head[]=$t['name'].' - '.$sub['subject_name'].' / 100';$head[]=$t['name'].' Average / 100';}$head[]='Annual Average / 100';fputcsv($out,$head);foreach($r['students'] as $row){$line=array($row['rank'],$row['student']['first_name'].' '.$row['student']['last_name'],$row['student']['register_no']);foreach($terms as $t){foreach($r['subjects'] as $sub)$line[]=number_format(isset($row['term_scores'][$t['id']][$sub['subject_id']])?$row['term_scores'][$t['id']][$sub['subject_id']]:0,2,'.','');$line[]=number_format($row['term_averages'][$t['id']],2,'.','');}$line[]=number_format($row['annual_average'],2,'.','');fputcsv($out,$line);}fclose($out);exit;
    }

    public function export_term_pdf()
    {
        if(!$this->canManage()) access_denied();$data=$this->reportData();if(!$data)show_error('Invalid report selection.',400);$data['class_name']=get_type_name_by_id('class',$data['class_id']);$data['section_name']=get_type_name_by_id('section',$data['section_id']);$html=$this->load->view('gradebook/report_pdf',$data,true);$this->load->library('html2pdf');$this->html2pdf->mpdf->WriteHTML($html);$this->html2pdf->mpdf->Output('term_result_'.$data['term_id'].'.pdf','D');
    }

    public function export_annual_pdf()
    {
        if(!$this->canManage()) access_denied();$branchID=$this->currentBranch();$classID=$this->input->get('class_id');$sectionID=$this->input->get('section_id');$terms=$this->gradebook_model->getTerms();if(!$branchID||!$classID||!$sectionID||empty($terms))show_error('Invalid annual report selection.',400);$data=$this->gradebook_model->getAnnualReport($branchID,$classID,$sectionID,$terms);$data['class_name']=get_type_name_by_id('class',$classID);$data['section_name']=get_type_name_by_id('section',$sectionID);$html=$this->load->view('gradebook/annual_pdf',$data,true);$this->load->library('html2pdf');$this->html2pdf->mpdf->WriteHTML($html);$this->html2pdf->mpdf->Output('annual_results.pdf','D');
    }
}
