<?php if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Communication_model extends MY_Model
{

    public function __construct()
    {
        parent::__construct();
    }

    // mailbox compose
    public function mailbox_compose($data)
    {
        $id = '';
        $branchID = $this->application_model->get_branch_id();
        $sender = loggedin_role_id() . '-' . get_loggedin_user_id();
        $reciever = $data['role_id'] . '-' . $data['receiver_id'];
        $arrayMsg = array(
            'body' => $data['message_body'],
            'subject' => $data['subject'],
            'sender' => $sender,
            'reciever' => $reciever,
            'created_at' => date('Y-m-d H:i:s'),
        );
        if($_FILES["attachment_file"]['name'] !="") {
            // uploading file using codeigniter upload library
            $config['upload_path'] = 'uploads/attachments/';
            $config['encrypt_name'] = true;
            $config['allowed_types'] = '*';
            $this->upload->initialize($config);
            if ($this->upload->do_upload("attachment_file")) {
                $arrayMsg['file_name'] = $this->upload->data('orig_name');
                $arrayMsg['enc_name'] = $this->upload->data('file_name');
            }
        }
        $this->db->insert('message', $arrayMsg);
        $id = $this->db->insert_id();

        // send new message received email
        $this->db->where(array('branch_id' => $branchID, 'template_id' => 4));
        $getTemplate = $this->db->get('email_templates_details')->row_array();
        if ($getTemplate['notified'] == 1) {
            $user = $this->application_model->getUserNameByRoleID($data['role_id'], $data['receiver_id']);
            $message = $getTemplate['template_body'];
            $message = str_replace("{institute_name}", get_global_setting('institute_name'), $message);
            $message = str_replace("{recipient}", $user['name'], $message);
            $message = str_replace("{message}", $data['message_body'], $message);
            $message = str_replace("{message_url}", base_url('communication/mailbox/read?type=inbox&id=' . $id), $message);
            $msg_data['recipient'] = $user['email'];
            $msg_data['subject'] = $getTemplate['subject'];
            $msg_data['message'] = $message;
            $this->load->model("email_model");
            $this->email_model->sendEmail($msg_data);
        }
        return $id;
    }

    public function mark_messages_read($message_id)
    {
        $activeUser = loggedin_role_id() . '-' . get_loggedin_user_id();
        $this->db->where('reciever', $activeUser);
        $this->db->where('id', $message_id);
        $this->db->update('message', array('read_status' => 1, 'updated_at' => date('Y-m-d H:i:s')));

        $this->db->where('sender', $activeUser);
        $this->db->where('id', $message_id);
        $this->db->update('message', array('reply_status' => 0, 'updated_at' => date('Y-m-d H:i:s')));
    }
    /**
     * Return class/section pairs a teacher is allowed to contact.
     * A teacher may be assigned as a class teacher or as the subject teacher.
     */
    public function getTeacherAssignedClassSections($teacherID, $branchID)
    {
        $result = array();
        $queries = array();

        $queries[] = $this->db->select('class_id, section_id')
            ->from('teacher_allocation')
            ->where('teacher_id', $teacherID)
            ->where('branch_id', $branchID)
            ->where('session_id', get_session_id())
            ->get()->result_array();

        $queries[] = $this->db->select('class_id, section_id')
            ->from('subject_assign')
            ->where('teacher_id', $teacherID)
            ->where('branch_id', $branchID)
            ->where('session_id', get_session_id())
            ->group_by(array('class_id', 'section_id'))
            ->get()->result_array();

        foreach ($queries as $rows) {
            foreach ($rows as $row) {
                $key = $row['class_id'] . '-' . $row['section_id'];
                $result[$key] = array(
                    'class_id' => $row['class_id'],
                    'section_id' => $row['section_id'],
                );
            }
        }

        return array_values($result);
    }

    public function teacherCanAccessClassSection($teacherID, $classID, $sectionID, $branchID)
    {
        $classTeacher = $this->db->where(array(
            'teacher_id' => $teacherID,
            'class_id' => $classID,
            'section_id' => $sectionID,
            'branch_id' => $branchID,
            'session_id' => get_session_id(),
        ))->count_all_results('teacher_allocation');

        if ($classTeacher > 0) {
            return true;
        }

        $subjectTeacher = $this->db->where(array(
            'teacher_id' => $teacherID,
            'class_id' => $classID,
            'section_id' => $sectionID,
            'branch_id' => $branchID,
            'session_id' => get_session_id(),
        ))->count_all_results('subject_assign');

        return $subjectTeacher > 0;
    }

    public function teacherCanMessageStudent($teacherID, $studentID, $branchID)
    {
        $this->db->select('e.class_id,e.section_id');
        $this->db->from('enroll as e');
        $this->db->where('e.student_id', $studentID);
        $this->db->where('e.branch_id', $branchID);
        $this->db->where('e.session_id', get_session_id());
        $enrollments = $this->db->get()->result_array();

        foreach ($enrollments as $enrollment) {
            if ($this->teacherCanAccessClassSection($teacherID, $enrollment['class_id'], $enrollment['section_id'], $branchID)) {
                return true;
            }
        }

        return false;
    }

    public function teacherCanMessageParent($teacherID, $parentID, $branchID)
    {
        $this->db->select('e.class_id,e.section_id');
        $this->db->from('enroll as e');
        $this->db->join('student as st', 'st.id = e.student_id', 'inner');
        $this->db->where('st.parent_id', $parentID);
        $this->db->where('e.branch_id', $branchID);
        $this->db->where('e.session_id', get_session_id());
        $enrollments = $this->db->get()->result_array();

        foreach ($enrollments as $enrollment) {
            if ($this->teacherCanAccessClassSection($teacherID, $enrollment['class_id'], $enrollment['section_id'], $branchID)) {
                return true;
            }
        }

        return false;
    }

    public function getStudentsForTeacher($teacherID, $classID, $sectionID, $branchID)
    {
        if (!$this->teacherCanAccessClassSection($teacherID, $classID, $sectionID, $branchID)) {
            return array();
        }

        return $this->db->select('e.student_id, s.register_no, CONCAT(s.first_name, " ", s.last_name) as fullname')
            ->from('enroll as e')
            ->join('student as s', 's.id = e.student_id', 'inner')
            ->join('login_credential as l', 'l.user_id = e.student_id AND l.role = 7', 'left')
            ->where('l.active', 1)
            ->where('e.session_id', get_session_id())
            ->where('e.class_id', $classID)
            ->where('e.section_id', $sectionID)
            ->where('e.branch_id', $branchID)
            ->order_by('s.first_name', 'asc')
            ->get()->result_array();
    }

    public function getParentsForTeacher($teacherID, $classID, $sectionID, $branchID)
    {
        if (!$this->teacherCanAccessClassSection($teacherID, $classID, $sectionID, $branchID)) {
            return array();
        }

        return $this->db->select('DISTINCT p.id, p.name', false)
            ->from('parent as p')
            ->join('student as s', 's.parent_id = p.id', 'inner')
            ->join('enroll as e', 'e.student_id = s.id', 'inner')
            ->where('e.class_id', $classID)
            ->where('e.section_id', $sectionID)
            ->where('e.branch_id', $branchID)
            ->where('e.session_id', get_session_id())
            ->order_by('p.name', 'asc')
            ->get()->result_array();
    }


    /** Parent can message only branch admin(s) and the active child's class teacher(s). */
    public function getParentMessageRecipients($parentID, $studentID, $branchID)
    {
        $recipients = array();
        if (empty($parentID) || empty($studentID) || empty($branchID)) return $recipients;

        $enroll = $this->db->select('class_id, section_id, branch_id')
            ->where(array('student_id' => $studentID, 'session_id' => get_session_id(), 'branch_id' => $branchID))
            ->get('enroll')->row_array();
        if (empty($enroll)) return $recipients;

        // Branch administrator(s) (role 2).
        $admins = $this->db->select('staff.id, staff.name, staff.staff_id')
            ->from('staff')->join('login_credential as lc', 'lc.user_id = staff.id AND lc.role = 2', 'inner')
            ->where('staff.branch_id', $branchID)->where('lc.active', 1)
            ->order_by('staff.name', 'ASC')->get()->result_array();
        foreach ($admins as $row) {
            $recipients[] = array('role_id' => 2, 'user_id' => $row['id'], 'name' => $row['name'], 'type' => 'Admin');
        }

        // Both class teachers are allowed (supports the new two-teacher assignment).
        $teachers = $this->db->select('staff.id, staff.name, staff.staff_id')
            ->from('teacher_allocation as ta')->join('staff', 'staff.id = ta.teacher_id', 'inner')
            ->join('login_credential as lc', 'lc.user_id = staff.id AND lc.role = 3', 'inner')
            ->where(array(
                'ta.class_id' => $enroll['class_id'], 'ta.section_id' => $enroll['section_id'],
                'ta.branch_id' => $branchID, 'ta.session_id' => get_session_id(), 'lc.active' => 1
            ))->group_by('staff.id')->order_by('staff.name', 'ASC')->get()->result_array();
        foreach ($teachers as $row) {
            $recipients[] = array('role_id' => 3, 'user_id' => $row['id'], 'name' => $row['name'], 'type' => 'Class Teacher');
        }
        return $recipients;
    }

    public function parentCanMessageRecipient($parentID, $studentID, $roleID, $receiverID, $branchID)
    {
        foreach ($this->getParentMessageRecipients($parentID, $studentID, $branchID) as $recipient) {
            if ((int)$recipient['role_id'] === (int)$roleID && (int)$recipient['user_id'] === (int)$receiverID) return true;
        }
        return false;
    }

}
