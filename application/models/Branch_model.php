<?php
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Branch_model extends MY_Model
{

    public function __construct()
    {
        parent::__construct();
    }

    public function save($data)
    {
        /*
         * Several installations of this project have strict SQL mode enabled.
         * The branch table contains legacy NOT NULL columns without defaults
         * (prefixes, timezone, registration settings, etc.).  The old save()
         * omitted those columns, so INSERT failed even though the form passed
         * validation.  Supply safe application defaults while preserving all
         * user-entered branch fields.
         */
        $arrayBranch = array(
            'name' => trim($data['branch_name']),
            'school_name' => trim($data['school_name']),
            'email' => trim($data['email']),
            'mobileno' => trim($data['mobileno']),
            'currency' => trim($data['currency']),
            'symbol' => trim($data['currency_symbol']),
            'city' => isset($data['city']) ? trim($data['city']) : '',
            'state' => isset($data['state']) ? trim($data['state']) : '',
            'address' => isset($data['address']) ? trim($data['address']) : '',

            // Legacy branch settings required by the current schema.
            'stu_generate' => isset($data['stu_generate']) ? (int)$data['stu_generate'] : 0,
            'stu_username_prefix' => isset($data['stu_username_prefix']) ? trim($data['stu_username_prefix']) : '',
            'stu_default_password' => isset($data['stu_default_password']) ? $data['stu_default_password'] : '',
            'grd_generate' => isset($data['grd_generate']) ? (int)$data['grd_generate'] : 0,
            'grd_username_prefix' => isset($data['grd_username_prefix']) ? trim($data['grd_username_prefix']) : '',
            'grd_default_password' => isset($data['grd_default_password']) ? $data['grd_default_password'] : '',
            'teacher_restricted' => isset($data['teacher_restricted']) ? (int)$data['teacher_restricted'] : 1,
            'due_days' => isset($data['due_days']) && $data['due_days'] !== '' ? (float)$data['due_days'] : 30,
            'due_with_fine' => isset($data['due_with_fine']) ? (int)$data['due_with_fine'] : 1,
            'translation' => isset($data['translation']) && $data['translation'] !== '' ? $data['translation'] : 'english',
            'timezone' => isset($data['timezone']) ? trim($data['timezone']) : '',
            'weekends' => isset($data['weekends']) && $data['weekends'] !== '' ? $data['weekends'] : '0',
            'reg_prefix_enable' => isset($data['reg_prefix_enable']) ? (int)$data['reg_prefix_enable'] : 0,
            'reg_start_from' => isset($data['reg_start_from']) && $data['reg_start_from'] !== '' ? (int)$data['reg_start_from'] : 1,
            'institution_code' => isset($data['institution_code']) && $data['institution_code'] !== '' ? trim($data['institution_code']) : null,
            'reg_prefix_digit' => isset($data['reg_prefix_digit']) && $data['reg_prefix_digit'] !== '' ? (int)$data['reg_prefix_digit'] : 0,
            'offline_payments' => isset($data['offline_payments']) ? (int)$data['offline_payments'] : 1,
            'status' => isset($data['status']) ? (int)$data['status'] : 1,
            'unique_roll' => isset($data['unique_roll']) ? (int)$data['unique_roll'] : 1,
        );
        if (!isset($data['branch_id'])) {
            $this->db->insert('branch', $arrayBranch);
            $id = $this->db->insert_id();
        } else {
            $id = $data['branch_id'];
            $this->db->where('id', $data['branch_id']);
            $this->db->update('branch', $arrayBranch);
        }

        $file_upload = false;
        if (isset($_FILES["logo_file"]) && !empty($_FILES['logo_file']['name'])) {
            $fileInfo = pathinfo($_FILES["logo_file"]["name"]);
            $img_name = $id . '.' . $fileInfo['extension'];
            move_uploaded_file($_FILES["logo_file"]["tmp_name"], "uploads/app_image/logo-" . $img_name);
            $file_upload = true;
        }
        if (isset($_FILES["text_logo"]) && !empty($_FILES['text_logo']['name'])) {
            $fileInfo = pathinfo($_FILES["text_logo"]["name"]);
            $img_name = $id . '.' . $fileInfo['extension'];
            move_uploaded_file($_FILES["text_logo"]["tmp_name"], "uploads/app_image/logo-small-" . $img_name);
            $file_upload = true;
        }

        if (isset($_FILES["print_file"]) && !empty($_FILES['print_file']['name'])) {
            $fileInfo = pathinfo($_FILES["print_file"]["name"]);
            $img_name = $id . '.' . $fileInfo['extension'];
            move_uploaded_file($_FILES["print_file"]["tmp_name"], "uploads/app_image/printing-logo-" . $img_name);
            $file_upload = true;
        }

        if (isset($_FILES["report_card"]) && !empty($_FILES['report_card']['name'])) {
            $fileInfo = pathinfo($_FILES["report_card"]["name"]);
            $img_name = $id . '.' . $fileInfo['extension'];
            move_uploaded_file($_FILES["report_card"]["tmp_name"], "uploads/app_image/report-card-logo-" . $img_name);
            $file_upload = true;
        }

        if ($this->db->affected_rows() > 0 || $file_upload == true) {
            return true;
        } else {
            return false;
        }
    }
}
