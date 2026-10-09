<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * @package : Zohar School Management System
 * @version : 2.0
 * @developed by : yosefsahle
 * @support : yosefsahle48@gmail.com
 * @author url : https://zohar.et
 * @filename : Classes.php
 * @copyright : Zohar Tech Development Team
 */

class Classes extends Admin_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model('classes_model');
    }

    /* class form validation rules */
    protected function class_validation()
    {
        if (is_superadmin_loggedin()) {
            $this->form_validation->set_rules('branch_id', translate('branch'), 'required');
        }
        $this->form_validation->set_rules('name', translate('name'), 'trim|required');
        $this->form_validation->set_rules('name_numeric', translate('name_numeric'), 'trim|numeric');
        $this->form_validation->set_rules('sections[]', translate('section'), 'trim|required');
    }

    public function index()
    {
        if (!get_permission('classes', 'is_view')) {
            access_denied();
        }
        if ($_POST) {
            if (get_permission('classes', 'is_add')) {
                $this->class_validation();
                if ($this->form_validation->run() !== false) {
                    $arrayClass = array(
                        'name' => $this->input->post('name'),
                        'name_numeric' => $this->input->post('name_numeric'),
                        'branch_id' => $this->application_model->get_branch_id(),
                    );
                    $this->db->insert('class', $arrayClass);
                    $class_id = $this->db->insert_id();
                    $sections = $this->input->post('sections');
                    foreach ($sections as $section) {
                        $arrayData = array(
                            'class_id' => $class_id,
                            'section_id' => $section,
                        );
                        $query = $this->db->get_where("sections_allocation", $arrayData);
                        if ($query->num_rows() == 0) {
                            $this->db->insert('sections_allocation', $arrayData);
                        }
                    }
                    set_alert('success', translate('information_has_been_saved_successfully'));
                    $url = base_url('classes');
                    $array = array('status' => 'success', 'url' => $url, 'error' => '');
                } else {
                    $error = $this->form_validation->error_array();
                    $array = array('status' => 'fail', 'url' => '', 'error' => $error);
                }
                echo json_encode($array);
                exit();
            }
        }
        $this->data['classlist'] = $this->app_lib->getTable('class');
        $this->data['query_classes'] = $this->db->get('class');
        $this->data['title'] = translate('control_classes');
        $this->data['sub_page'] = 'classes/index';
        $this->data['main_menu'] = 'classes';
        $this->load->view('layout/index', $this->data);

    }

    public function edit($id = '')
    {
        if (!get_permission('classes', 'is_edit')) {
            access_denied();
        }
        if ($_POST) {
            $this->class_validation();
            if ($this->form_validation->run() !== false) {
                $id = $this->input->post('class_id');
                $arrayClass = array(
                    'name' => $this->input->post('name'),
                    'name_numeric' => $this->input->post('name_numeric'),
                    'branch_id' => $this->application_model->get_branch_id(),
                );
                $this->db->where('id', $id);
                $this->db->update('class', $arrayClass);
                $sections = $this->input->post('sections');
                foreach ($sections as $section) {
                    $query = $this->db->get_where("sections_allocation", array('class_id' => $id, 'section_id' => $section));
                    if ($query->num_rows() == 0) {
                        $this->db->insert('sections_allocation', array('class_id' => $id, 'section_id' => $section));
                    }
                }
                $this->db->where_not_in('section_id', $sections);
                $this->db->where('class_id', $id);
                $this->db->delete('sections_allocation');
                set_alert('success', translate('information_has_been_updated_successfully'));
                $url = base_url('classes');
                $array = array('status' => 'success', 'url' => $url, 'error' => '');
            } else {
                $error = $this->form_validation->error_array();
                $array = array('status' => 'fail', 'url' => '', 'error' => $error);
            }
            echo json_encode($array);
            exit();
        }
        $this->data['class'] = $this->app_lib->getTable('class', array('t.id' => $id), true);
        $this->data['title'] = translate('control_classes');
        $this->data['sub_page'] = 'classes/edit';
        $this->data['main_menu'] = 'classes';
        $this->load->view('layout/index', $this->data);
    }

    public function delete($id = '')
    {
        if (get_permission('classes', 'is_delete')) {
            if (!is_superadmin_loggedin()) {
                $this->db->where('branch_id', get_loggedin_branch_id());
            }
            $this->db->where('id', $id);
            $this->db->delete('class');
            if ($this->db->affected_rows() > 0) {
                $this->db->where('class_id', $id);
                $this->db->delete('sections_allocation');
            }
        }
    }

    // class teacher allocation
    public function teacher_allocation()
    {
        if (!get_permission('assign_class_teacher', 'is_view')) {
            access_denied();
        }
        $branch_id = $this->application_model->get_branch_id();
        $this->data['branch_id'] = $branch_id;
        $this->data['query'] = $this->classes_model->getTeacherAllocation($branch_id);
        $this->data['title'] = translate('assign_class_teacher');
        $this->data['sub_page'] = 'classes/teacher_allocation';
        $this->data['main_menu'] = 'classes';
        $this->load->view('layout/index', $this->data);
    }

    public function getAllocationTeacher()
    {
        if (get_permission('assign_class_teacher', 'is_edit')) {
            $allocation_id = $this->input->post('id');
            $this->data['data'] = $this->app_lib->get_table('teacher_allocation', $allocation_id, true);
            if (!empty($this->data['data'])) {
                $this->data['teachers'] = $this->classes_model->getTeacherAllocationByClassSection(
                    $this->data['data']['class_id'],
                    $this->data['data']['section_id'],
                    $this->data['data']['branch_id']
                );
            } else {
                $this->data['teachers'] = array();
            }
            $this->load->view('classes/tallocation_modalEdit', $this->data);
        }
    }

    public function teacher_allocation_save()
    {
        if ($_POST) {
            if (is_superadmin_loggedin()) {
                $this->form_validation->set_rules('branch_id', translate('branch'), 'required');
            }
            $this->form_validation->set_rules('class_id', translate('class'), 'required');
            $this->form_validation->set_rules('section_id', translate('section'), 'required');
            $this->form_validation->set_rules('staff_id[]', translate('teacher'), 'required');

            $teacher_ids = $this->input->post('staff_id');
            $teacher_ids = is_array($teacher_ids) ? array_values(array_unique(array_filter($teacher_ids))) : array();

            if (count($teacher_ids) < 1 || count($teacher_ids) > 2) {
                $error = array('staff_id' => 'Please select one or two teachers. A class section can have a maximum of two class teachers.');
                $array = array('status' => 'fail', 'error' => $error);
                echo json_encode($array);
                return;
            }

            if ($this->form_validation->run() !== false) {
                $post = $this->input->post();
                $post['staff_id'] = $teacher_ids;

                // A teacher cannot be assigned twice to the same class section.
                if (count($teacher_ids) !== count(array_unique($teacher_ids))) {
                    $error = array('staff_id' => 'The same teacher cannot be assigned twice to the same class section.');
                    $array = array('status' => 'fail', 'error' => $error);
                    echo json_encode($array);
                    return;
                }

                $saved = $this->classes_model->teacherAllocationSave($post);
                if (!$saved) {
                    $array = array('status' => 'fail', 'error' => array('staff_id' => 'A class section can have a maximum of two different teachers, and all selected users must be teachers in this branch.'));
                } else {
                    $url = base_url('classes/teacher_allocation');
                    $array = array('status' => 'success', 'url' => $url);
                }
            } else {
                $error = $this->form_validation->error_array();
                $array = array('status' => 'fail', 'error' => $error);
            }
            echo json_encode($array);
        }
    }

    public function teacher_allocation_delete($id = '')
    {
        if (get_permission('assign_class_teacher', 'is_delete')) {
            if (!is_superadmin_loggedin()) {
                $this->db->where('branch_id', get_loggedin_branch_id());
            }
            $this->db->where('id', $id);
            $this->db->delete('teacher_allocation');
        }
    }


}
