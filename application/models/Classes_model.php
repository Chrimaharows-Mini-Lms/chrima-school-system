<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Classes_model extends MY_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getTeacherAllocation($branch_id = '')
    {
        $this->db->select('ta.*,st.name as teacher_name,st.staff_id as teacher_id,c.name as class_name,c.branch_id,s.name as section_name');
        $this->db->from('teacher_allocation as ta');
        $this->db->join('staff as st', 'st.id = ta.teacher_id', 'left');
        $this->db->join('class as c', 'c.id = ta.class_id', 'left');
        $this->db->join('section as s', 's.id = ta.section_id', 'left');
        $this->db->order_by('ta.id', 'ASC');
        $this->db->where('ta.session_id', get_session_id());
        if (!empty($branch_id)) {
            $this->db->where('c.branch_id', $branch_id);
        }
        return $this->db->get();
    }

    public function teacherAllocationSave($data)
    {
        $branchID = $this->application_model->get_branch_id();
        $sessionID = get_session_id();
        $classID = $data['class_id'];
        $sectionID = $data['section_id'];
        $teacherIDs = is_array($data['staff_id']) ? array_values(array_unique(array_filter($data['staff_id']))) : array();

        if (count($teacherIDs) < 1 || count($teacherIDs) > 2) {
            return false;
        }

        // Validate that every selected user is actually a teacher in this branch.
        $validTeachers = $this->db
            ->select('st.id')
            ->from('staff as st')
            ->join('login_credential as lc', 'lc.user_id = st.id', 'inner')
            ->where('st.branch_id', $branchID)
            ->where('lc.role', 3)
            ->where_in('st.id', $teacherIDs)
            ->get()->result_array();

        $validIDs = array_map(function ($row) { return (string)$row['id']; }, $validTeachers);
        foreach ($teacherIDs as $teacherID) {
            if (!in_array((string)$teacherID, $validIDs, true)) {
                return false;
            }
        }

        $where = array(
            'branch_id' => $branchID,
            'session_id' => $sessionID,
            'class_id' => $classID,
            'section_id' => $sectionID,
        );

        if (isset($data['allocation_id']) && !empty($data['allocation_id'])) {
            // Editing an allocation edits the complete teacher set for the class section.
            $old = $this->db->get_where('teacher_allocation', array('id' => $data['allocation_id']))->row_array();
            if (!empty($old)) {
                $this->db->where('branch_id', $branchID)
                    ->where('session_id', $sessionID)
                    ->where('class_id', $old['class_id'])
                    ->where('section_id', $old['section_id'])
                    ->delete('teacher_allocation');
            }
        } else {
            $existing = $this->db->select('teacher_id')
                ->where($where)->get('teacher_allocation')->result_array();

            $existingIDs = array_map(function ($row) { return (string)$row['teacher_id']; }, $existing);
            $newIDs = array();
            foreach ($teacherIDs as $teacherID) {
                if (!in_array((string)$teacherID, $existingIDs, true)) {
                    $newIDs[] = $teacherID;
                }
            }

            if (count($existingIDs) + count($newIDs) > 2) {
                return false;
            }

            // Existing assignments are already stored. Only insert genuinely new teachers.
            $teacherIDs = $newIDs;
            if (empty($teacherIDs)) {
                return true;
            }
        }

        foreach ($teacherIDs as $teacherID) {
            $this->db->insert('teacher_allocation', array(
                'branch_id' => $branchID,
                'session_id' => $sessionID,
                'class_id' => $classID,
                'section_id' => $sectionID,
                'teacher_id' => $teacherID,
            ));
        }

        set_alert('success', translate('information_has_been_saved_successfully'));
        return true;
    }

    public function getTeacherAllocationByClassSection($classID, $sectionID, $branchID = '')
    {
        $this->db->select('ta.*, st.name as teacher_name, st.staff_id as teacher_staff_id');
        $this->db->from('teacher_allocation as ta');
        $this->db->join('staff as st', 'st.id = ta.teacher_id', 'left');
        $this->db->where('ta.class_id', $classID);
        $this->db->where('ta.section_id', $sectionID);
        $this->db->where('ta.session_id', get_session_id());
        if (!empty($branchID)) {
            $this->db->where('ta.branch_id', $branchID);
        }
        $this->db->order_by('ta.id', 'ASC');
        return $this->db->get()->result_array();
    }
}