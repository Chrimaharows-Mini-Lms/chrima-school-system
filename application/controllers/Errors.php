<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * @package : Zohar School Management System
 * @version : 2.0
 * @developed by : yosefsahle
 * @support : yosefsahle48@gmail.com
 * @author url : https://zohar.et
 * @filename : Errors.php
 * @copyright : Zohar Tech Development Team
 */

class Errors extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $this->load->view('errors/error_404_message.php');
    }
}
