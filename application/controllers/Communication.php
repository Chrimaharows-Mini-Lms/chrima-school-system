<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * @package : Zohar School Management System
 * @version : 2.0
 * @developed by : yosefsahle
 * @support : yosefsahle48@gmail.com
 * @author url : https://zohar.et
 * @filename : Communication.php
 * @copyright : Zohar Tech Development Team
 */

class Communication extends Admin_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model('communication_model');
    }

    public function index()
    {
        if (is_loggedin()) {
            redirect(base_url('communication/mailbox/inbox'));
        } else {
            redirect(base_url(), 'refresh');
        }
    }

    public function mailbox($action = 'inbox')
    {
        if ($action == 'compose') {
            $this->data['inside_subview'] = is_parent_loggedin() ? 'message_parent_compose' : 'message_compose';
        } elseif ($action == 'inbox') {
            $this->data['inside_subview'] = is_parent_loggedin() ? 'message_parent_inbox' : 'message_inbox';
        } elseif ($action == 'sent') {
            $this->data['inside_subview'] = is_parent_loggedin() ? 'message_parent_sent' : 'message_sent';
        } elseif ($action == 'important') {
            $this->data['inside_subview'] = 'message_important';
        } elseif ($action == 'trash') {
            $this->data['inside_subview'] = 'message_trash';
        } elseif ($action == 'read') {
            $id = urldecode($this->input->get('id'));
            if (preg_match('/^[^.][-a-z0-9_.]+[a-z]$/i', $id) || is_numeric($id) == false) {
                redirect(base_url('dashboard'));
                exit;
            }
            $message = $this->db->where('id', $id)->get('message')->row_array();
            $activeUser = loggedin_role_id() . '-' . get_loggedin_user_id();
            if (empty($message) || ($message['sender'] !== $activeUser && $message['reciever'] !== $activeUser)) {
                access_denied();
            }
            if (is_parent_loggedin()) {
                $parts = explode('-', $message['sender'] === $activeUser ? $message['reciever'] : $message['sender']);
                $allowed = $this->communication_model->parentCanMessageRecipient(
                    get_loggedin_user_id(), get_activeChildren_id(), $parts[0], $parts[1], $this->application_model->get_branch_id()
                );
                if (!$allowed) access_denied();
            }
            $this->communication_model->mark_messages_read($id);
            $this->data['message_id'] = $id;
            $this->data['inside_subview'] = is_parent_loggedin() ? 'message_parent_read' : 'message_read';
        }
        $this->data['active_user'] = loggedin_role_id() . '-' . get_loggedin_user_id();
        $this->data['branch_id'] = $this->application_model->get_branch_id();
        $this->data['is_teacher_sender'] = (loggedin_role_id() == 3);
        $this->data['is_parent_sender'] = is_parent_loggedin();
        $this->data['teacher_class_sections'] = $this->communication_model->getTeacherAssignedClassSections(
            get_loggedin_user_id(),
            $this->data['branch_id']
        );
        $this->data['parent_message_recipients'] = array();
        if ($this->data['is_parent_sender'] && !empty(get_activeChildren_id())) {
            $this->data['parent_message_recipients'] = $this->communication_model->getParentMessageRecipients(
                get_loggedin_user_id(), get_activeChildren_id(), $this->data['branch_id']
            );
        }
        $this->data['title'] = translate('mailbox');
        $this->data['sub_page'] = 'communication/message';
        $this->data['main_menu'] = 'message';
        $this->data['headerelements'] = array(
            'css' => array(
                'vendor/summernote/summernote.css',
                'vendor/bootstrap-fileupload/bootstrap-fileupload.min.css',
            ),
            'js' => array(
                'vendor/summernote/summernote.js',
                'vendor/bootstrap-fileupload/bootstrap-fileupload.min.js',
            ),
        );
        $this->load->view('layout/index', $this->data);
    }

    public function message_send() {
        if ($_POST) {
            if (is_superadmin_loggedin()) {
                $this->form_validation->set_rules('branch_id', translate('branch'), 'required');
            }
            $this->form_validation->set_rules('role_id', translate('role'), 'trim|required');
            $this->form_validation->set_rules('receiver_id', translate('receiver'), 'trim|required');
            $this->form_validation->set_rules('subject', translate('subject'), 'trim|required');
            $this->form_validation->set_rules('message_body', translate('message'), 'trim|required');
            $this->form_validation->set_rules('attachment_file', translate('attachment'), 'callback_handle_upload');
            if ($this->form_validation->run() !== false) {
                $post = $this->input->post();

                // Teachers may contact only students/parents belonging to a class section
                // to which they are currently assigned. This is enforced server-side.
                if (loggedin_role_id() == 3 && in_array((int)$post['role_id'], array(6, 7), true)) {
                    $branchID = $this->application_model->get_branch_id();
                    $allowed = false;
                    if ((int)$post['role_id'] === 7) {
                        $allowed = $this->communication_model->teacherCanMessageStudent(
                            get_loggedin_user_id(), $post['receiver_id'], $branchID
                        );
                    } else {
                        $allowed = $this->communication_model->teacherCanMessageParent(
                            get_loggedin_user_id(), $post['receiver_id'], $branchID
                        );
                    }

                    if (!$allowed) {
                        $array = array(
                            'status' => 'fail',
                            'url' => '',
                            'error' => array('receiver_id' => 'You can only send messages to students or parents from your assigned class sections.')
                        );
                        echo json_encode($array);
                        return;
                    }
                }

                // Parents may message only branch admins or the active child's class teacher(s).
                if (is_parent_loggedin()) {
                    $activeChild = get_activeChildren_id();
                    $branchID = $this->application_model->get_branch_id();
                    if (empty($activeChild) || !$this->communication_model->parentCanMessageRecipient(
                        get_loggedin_user_id(), $activeChild, $post['role_id'], $post['receiver_id'], $branchID
                    )) {
                        echo json_encode(array(
                            'status' => 'fail', 'url' => '',
                            "error" => array("receiver_id" => "You can only message your child\'s school administrator or assigned class teacher.")
                        ));
                        return;
                    }
                }

                $message_id = $this->communication_model->mailbox_compose($post);
                set_alert('success', translate('message_sent_successfully'));
                $array = array('status' => 'success');
            } else {
                $error = $this->form_validation->error_array();
                $array = array('status' => 'fail', 'url' => '', 'error' => $error);
            }
            echo json_encode($array);
        }
    }

    public function message_reply()
    {
        if ($_POST) {
            $this->form_validation->set_rules('attachment_file', translate('attachment'), 'callback_handle_upload');
            $this->form_validation->set_rules('message', 'Message', 'trim|required');
            if ($this->form_validation->run() == true) {
                $message_id = $this->input->post('message_id');
                if (is_parent_loggedin()) {
                    $message = $this->db->where('id', $message_id)->get('message')->row_array();
                    $activeUser = loggedin_role_id() . '-' . get_loggedin_user_id();
                    if (empty($message) || ($message['sender'] !== $activeUser && $message['reciever'] !== $activeUser)) {
                        access_denied();
                    }
                    $parts = explode('-', $message['sender'] === $activeUser ? $message['reciever'] : $message['sender']);
                    if (!$this->communication_model->parentCanMessageRecipient(get_loggedin_user_id(), get_activeChildren_id(), $parts[0], $parts[1], $this->application_model->get_branch_id())) {
                        access_denied();
                    }
                }
                if ($this->input->post('user_identity') == 'sender') {
                    $arrayMsg['identity'] = 1;
                    $this->db->where('id', $message_id);
                    $this->db->update('message', array('read_status' => 0));
                } else {
                    $arrayMsg['identity'] = 0;
                    $this->db->where('id', $message_id);
                    $this->db->update('message', array('reply_status' => 1));
                }

                $arrayMsg['created_at'] = date('Y-m-d H:i:s');
                $arrayMsg['message_id'] = $message_id;
                $arrayMsg['body'] = $this->input->post('message');
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
                $this->db->insert('message_reply', $arrayMsg);
                $array = array('status' => 'success');
            } else {
                $error = $this->form_validation->error_array();
                $array = array('status' => 'fail', 'error' => $error);
            }
            echo json_encode($array);
        }
    }

    // file downloader
    public function download()
    {
        $encrypt_name = urldecode($this->input->get('file'));
        $type = urldecode($this->input->get('type'));
        if (!preg_match('/^[^.][-a-z0-9_.]+[a-z]$/i', $type)) {
            redirect(base_url('dashboard'));
            exit;
        }
        if ($type == 'reply') {
            $table = 'message_reply';
        } else {
            $table = 'message';
        }
        if(preg_match('/^[^.][-a-z0-9_.]+[a-z]$/i', $encrypt_name)) {
            $file_name = $this->db->select('file_name')->where('enc_name', $encrypt_name)->get($table)->row()->file_name;
            if (!empty($file_name)) {
                $this->load->helper('download');
                force_download($file_name, file_get_contents('uploads/attachments/' . $encrypt_name));
            }
        }
    }

    // upload file form validation
    public function handle_upload()
    {
        if (isset($_FILES["attachment_file"]) && !empty($_FILES['attachment_file']['name'])) {
            $allowedExts = array_map('trim', array_map('strtolower', explode(',', $this->data['global_config']['file_extension'])));
            $allowedSizeKB = $this->data['global_config']['file_size'];
            $allowedSize = floatval(1024 * $allowedSizeKB);
            $file_size = $_FILES["attachment_file"]["size"];
            $file_name = $_FILES["attachment_file"]["name"];
            $extension = pathinfo($file_name, PATHINFO_EXTENSION);
            if ($files = filesize($_FILES["attachment_file"]['tmp_name'])) {
                if (!in_array(strtolower($extension), $allowedExts)) {
                    $this->form_validation->set_message('handle_upload', translate('this_file_type_is_not_allowed'));
                    return false;
                }
                if ($file_size > $allowedSize) {
                    $this->form_validation->set_message('handle_upload', translate('file_size_shoud_be_less_than') . " $allowedSizeKB KB.");
                    return false;
                }
            } else {
                $this->form_validation->set_message('handle_upload', translate('error_reading_the_file'));
                return false;
            }
            return true;
        }
    }

    /* message delete */
    public function delete_mail()
    {
        $arrayID = $this->input->post('arrayID');
        $mode = $this->input->post('mode');
        if (count($arrayID)) {
            foreach ($arrayID as $value) {
                $this->db->where('id', $value);
                $this->db->update('message', array('trash_' . $mode => 1));
            }
            set_alert('success', translate('message_has_been_deleted'));
        } else {
            set_alert('error', 'Please Select a Message to Delete');
        }
    }

    public function set_fvourite_status()
    {
        $messageID = $this->input->post('messageID');
        $status = $this->input->post('status');
        $active_user = loggedin_role_id() . '-' . get_loggedin_user_id();
        $query = $this->db->select('sender,reciever')->where('id', $messageID)->get('message')->row();
        if ($active_user == $query->sender) {
            $data['fav_sent'] = ($status == 'false' ? 0 : 1);
        } elseif ($active_user == $query->reciever) {
            $data['fav_inbox'] = ($status == 'false' ? 0 : 1);
        }

        $this->db->where('id', $messageID);
        $this->db->update('message', $data);
        $return = array('msg' => translate('information_has_been_updated_successfully'), 'status' => true);
        echo json_encode($return);
    }

    /* mailbox trash observe */
    public function trash_observe()
    {
        $activeUser = loggedin_role_id() . '-' . get_loggedin_user_id();
        $arrayID = $this->input->post('array_id');
        $mode = $this->input->post('mode');
        if ($mode == 'restore') {
            $status = 0;
        } elseif ($mode == 'delete') {
            $status = 1;
        } elseif ($mode == 'forever') {
            $status = 2;
        }
        if (count($arrayID)) {
            $array = array();
            foreach ($arrayID as $id) {
                $get_user = $this->db->select('sender,reciever')->where(array('id' => $id))->get('message')->row();
                if ($get_user->sender == $activeUser) {
                    $array['trash_sent'] = $status;
                } elseif ($get_user->reciever == $activeUser) {
                    $array['trash_inbox'] = $status;
                }
                $this->db->where('id', $id);
                $this->db->update('message', $array);
            }
            if ($option == 'restore') {
                set_alert('success', translate('message_has_been_restored'));
            } elseif ($option == 'delete') {
                set_alert('success', translate('message_has_been_deleted'));
            }
        } else {
            set_alert('error', 'Please Select a Message to Delete');
        }
    }

    public function getStafflistRole()
    {
        $html = "";
        $branch_id = $this->application_model->get_branch_id();
        if (!empty($branch_id)) {
            $role_id = $this->input->post('role_id');
            $selected_id = (isset($_POST['staff_id']) ? $_POST['staff_id'] : 0);
            $this->db->select('staff.id,staff.name,staff.staff_id,lc.role');
            $this->db->from('staff');
            $this->db->join('login_credential as lc', 'lc.user_id = staff.id AND lc.role != 6 AND lc.role != 7', 'inner');
            $this->db->where('lc.role', $role_id);
            $this->db->where('staff.branch_id', $branch_id);
            $this->db->order_by('staff.id', 'asc');
            $result = $this->db->get()->result_array();
            if (count($result)) {
                $html .= "<option value=''>" . translate('select') . "</option>";
                foreach ($result as $staff) {
                    if ($staff['id'] == get_loggedin_user_id()) {
                        continue;
                    }
                    $selected = ($staff['id'] == $selected_id ? 'selected' : '');
                    $html .= "<option value='" . $staff['id'] . "' " . $selected . ">" . $staff['name'] . " (" . $staff['staff_id'] . ")</option>";
                }
            } else {
                $html .= '<option value="">' . translate('no_information_available') . '</option>';
            }
        } else {
            $html .= '<option value="">' . translate('select_branch_first') . '</option>';
        }
        echo $html;
    }

    public function getStudentByClass()
    {
        $html = "";
        $classID = $this->input->post('class_id');
        $sectionID = $this->input->post('section_id');
        $branchID = $this->application_model->get_branch_id();

        if (!empty($classID) && (loggedin_role_id() != 3 || !empty($sectionID))) {
            if (loggedin_role_id() == 3) {
                $result = $this->communication_model->getStudentsForTeacher(
                    get_loggedin_user_id(), $classID, $sectionID, $branchID
                );
            } else {
                $this->db->select('e.student_id,s.register_no,CONCAT(s.first_name, " ", s.last_name) as fullname')
                    ->from('enroll as e')
                    ->join('student as s', 's.id = e.student_id', 'inner')
                    ->join('login_credential as l', 'l.user_id = e.student_id AND l.role = 7', 'left')
                    ->where('l.active', 1)
                    ->where('e.session_id', get_session_id())
                    ->where('e.branch_id', $branchID);

                // Admins are not restricted by class assignment. If a class is
                // selected, show that class; otherwise show every student in
                // the current branch/session.
                if (!empty($classID)) {
                    $this->db->where('e.class_id', $classID);
                }

                $result = $this->db->order_by('s.first_name', 'asc')
                    ->get()->result_array();
            }

            if (count($result)) {
                $html .= "<option value=''>" . translate('select') . "</option>";
                foreach ($result as $row) {
                    $html .= '<option value="' . $row['student_id'] . '">' . html_escape($row['fullname']) . ' (Register No : ' . html_escape($row['register_no']) . ')</option>';
                }
            } else {
                $html .= '<option value="">' . translate('no_information_available') . '</option>';
            }
        } else {
            $html .= '<option value="">' . translate('select_class_first') . '</option>';
        }
        echo $html;
    }

    public function getParentListBranch()
    {
        $html = "";
        $classID = $this->input->post('class_id');
        $sectionID = $this->input->post('section_id');
        $branchID = $this->application_model->get_branch_id();

        if (loggedin_role_id() == 3 && (!empty($classID) && !empty($sectionID))) {
            $result = $this->communication_model->getParentsForTeacher(
                get_loggedin_user_id(), $classID, $sectionID, $branchID
            );
        } elseif (loggedin_role_id() == 3) {
            $result = array();
        } else {
            // Admins are unrestricted. With a class selected, return parents
            // of students in that class; with no class selected, return all
            // parents in the current branch.
            if (!empty($classID)) {
                $this->db->select('DISTINCT p.id, p.name', false)
                    ->from('parent as p')
                    ->join('student as s', 's.parent_id = p.id', 'inner')
                    ->join('enroll as e', 'e.student_id = s.id', 'inner')
                    ->where('e.class_id', $classID)
                    ->where('e.branch_id', $branchID)
                    ->where('e.session_id', get_session_id())
                    ->order_by('p.name', 'asc');
                $result = $this->db->get()->result_array();
            } else {
                $result = $this->db->select('id,name')
                    ->where('branch_id', $branchID)
                    ->order_by('name', 'asc')
                    ->get('parent')->result_array();
            }
        }

        if (count($result)) {
            $html .= "<option value=''>" . translate('select') . "</option>";
            foreach ($result as $parent) {
                $html .= '<option value="' . $parent['id'] . '">' . html_escape($parent['name']) . '</option>';
            }
        } else {
            $html .= '<option value="">' . translate('no_information_available') . '</option>';
        }
        echo $html;
    }

    public function getTeacherMessageClasses()
    {
        if (loggedin_role_id() != 3) {
            echo json_encode(array());
            return;
        }

        $branchID = $this->application_model->get_branch_id();
        $items = $this->communication_model->getTeacherAssignedClassSections(
            get_loggedin_user_id(), $branchID
        );
        echo json_encode($items);
    }

}
