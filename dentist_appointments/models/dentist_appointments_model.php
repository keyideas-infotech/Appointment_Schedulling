<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Dentist_appointments_model extends BF_Model {

    protected $table = "dentist_appointments";
    protected $key = "id";
    protected $soft_deletes = true;
    protected $date_format = "datetime";
    protected $set_created = true;
    protected $set_modified = true;
    protected $created_field = "created_on";
    protected $modified_field = "modified_on";
    protected $status_field = "status";
    protected $deleted_field = "deleted";
    public $config_core = array();

    public function __construct() {
        parent::__construct();
        $config = array(
            "table" => $this->table,
            "status_field" => $this->status_field,
            "deleted_field" => $this->deleted_field,
            "action" => array(
                "delete" => "_delete_softly",
                "deleteSelected" => "_delete_selected_softly",
                "restore" => "_restore",
                "purge" => "_delete",
                "purgeSelected" => "_delete_selected",
                "toggleStatus" => "_toggle_status"
            ),
            "order" => array(
                "sortby" => $this->table . ".created_on",
                "order" => "DESC",
            )
        );
        $this->load->library("EX_CH_Grid_generator", $config, "grid99");
        $this->load->model('settings/settings_model', 'settings_model');
        $this->config_core = $this->settings_model->select('name,value')->find_all_by('module', 'core');
    }

    /* public function read($req_data) {

      //var_dump($req_data);

      if (($req_data['action'] == 'toggleStatus') && !empty($req_data['id'])) {
      $appointment_id = $req_data['id'];
      $prefix = $this->db->dbprefix;
      $query = $this->db->query("Select den.appointment_date,us.display_name from " . $prefix . "dentist_appointments AS den INNER JOIN " . $prefix . "users AS us ON us.id=den.dentist_id WHERE (den.id='$appointment_id' AND den.status=0)");
      $dentist_resu = array_shift($query->result());

      $sql = $this->db->query("Select us.display_name,us.email from " . $prefix . "dentist_appointments AS den INNER JOIN " . $prefix . "users AS us ON us.id=den.patient_id WHERE (den.id='$appointment_id' AND den.status=0)");
      $patient_data = array_shift($sql->result());

      if (!empty($dentist_resu) && !empty($patient_data)) {

      $data['dentist_name'] = $dentist_resu->display_name;
      $data['patient_name'] = $patient_data->display_name;
      $data['patient_email'] = $patient_data->email;
      $data['appointment_date'] = $dentist_resu->appointment_date;
      $mode = "confirm";
      $this->send_appointment_mail($data, $dentist_id = '', $appointment_date = '', $mode);
      // var_dump($req_data);
      $this->grid99->initialize(array(
      "req_data" => $req_data
      ));
      // var_dump($this->grid->get_result());
      return $this->grid99->get_result();
      die;
      } else {
      $this->grid99->initialize(array(
      "req_data" => $req_data
      ));
      return $this->grid99->get_result();
      }
      } else {
      $this->grid99->initialize(array(
      "req_data" => $req_data
      ));
      return $this->grid99->get_result();
      }
      } */

    public function read($req_data) {
        $this->grid99->initialize(array(
            "req_data" => $req_data
        ));
        return $this->grid99->get_result();
    }

    public function changeStatusToCompleted() {
        $this->update_where('DATE(appointment_date)<', date('Y-m-d'), array($this->status_field => 2));
    }

    /*
     * For Calender listing : Appointments booked by particuler patient
     */

    public function get_appointments_for_patient($patient_id, $dent_id) {
        $r = $this->db->from($this->table)->where(array('patient_id' => $patient_id, 'dentist_id' => $dent_id, 'deleted' => '0'))->get();
        if ($r->num_rows()) {
            return $r->result();
        }
        return FALSE;
    }

    /*
     * Listing dentist appointments : future, curMonthPast, past
     */

    public function get_appointments_for_dentist($dent_id, $data, $limit = FALSE, $is_paging = FALSE) {
        $prefix = $this->db->dbprefix;
        $today = date('Y-m-d H:i:s');
        $sql = "SELECT da.appointment_date, da.patient_id, u.display_name AS patient_name FROM " . $prefix . "dentist_appointments da ";
        $sql .= "INNER JOIN " . $prefix . "users u ON u.id = da.patient_id";
        $sql .= " WHERE u.active = 1 AND u.deleted = 0 AND u.banned = 0 AND da.deleted = 0 AND da.status = 1 ";
        $sql .= " AND da.dentist_id = '" . $dent_id . "'";
        //  var_dump($data);
        switch ($data['type']) {
            case 'future':
                $sql .= " AND da.appointment_date > '" . $today . "' AND da.appointment_date <= '" . date('Y-m-d H:i:s', strtotime($data['value'][1])) . "' ";
                $sql .= " ORDER BY da.appointment_date ASC";
                break;
            case 'curMonthPast':
                $sql .= " AND da.appointment_date > '" . date('Y-m-d H:i:s', strtotime($data['value'][0])) . "' AND da.appointment_date <= '" . $today . "' ";
                $sql .= " ORDER BY da.appointment_date ASC";
                break;
            case 'past':
                $sql .= " AND da.appointment_date <='" . $today . "'";
                $sql .= " ORDER BY da.appointment_date DESC";

                break;
            default:
                break;
        }
        //    echo $sql;die;
        $sql2 = $sql;
        if ($limit !== FALSE) {
            $sql .= ' LIMIT ' . $limit;
        }
        //  echo $sql;die;
        $query = $this->db->query($sql);
//        echo $this->db->last_query().'<br/>';
        if ($query->num_rows()) {
            if ($is_paging !== FALSE) {
                /* for pagination */
                $per_page = 5;
                $count = $query->num_rows();
                $curent_page = ($this->uri->segment(3) > 0) ? $this->uri->segment(3) : 1;
                $offset = ($curent_page - 1) * $per_page;

                $this->load->library('pagination');

                $config['base_url'] = base_url('dentist_management/get_dentist_appointments_by_filter');
                $config['total_rows'] = $count;
                $config['per_page'] = $per_page;
                $config['use_page_numbers'] = TRUE;
                $config['uri_segment'] = 3;
                $config['next_link'] = '<img src="' . Template::theme_url('images/rightarrow-pagination.png') . '" alt="" />';
                $config['prev_link'] = '<img src="' . Template::theme_url('images/leftarrow-pagination.png') . '" alt="" />';
                $config['cur_tag_open'] = '<a class="number active" href="#">';
                $config['cur_tag_close'] = '</a>';
                $config['first_link'] = FALSE;
                $config['last_link'] = FALSE;

                $this->pagination->initialize($config);

                $pagination = $this->pagination->create_links();
                /* End for pagination */

                /*
                 * Handling last page
                 */
                $total_pages = ceil($count / $per_page);
                if ($total_pages > 0 && $curent_page == $total_pages) {
                    $per_page = $count + $per_page - $total_pages * $per_page;
                }

                $sql2 .= " LIMIT {$offset}, {$per_page}";

                $query = $this->db->query($sql2);

                $result = $query->result();
                if (!empty($result)) {
                    $this->load->model('users/user_model');
                    foreach ($result as &$value) {
                        $tele = $this->user_model->find_meta_for($value->patient_id, array('phone'));
                        $value->phone = isset($tele->phone) ? $tele->phone : '';
                    }
                }

                $data = array();
                $data['pagination'] = $pagination;
                $data['result'] = $result;
                $data['count'] = $count;
                return $data;
            } else {
                $result = $query->result();
                if (!empty($result)) {
                    $this->load->model('users/user_model');
                    foreach ($result as &$value) {
                        $tele = $this->user_model->find_meta_for($value->patient_id, array('phone'));
                        $value->phone = isset($tele->phone) ? $tele->phone : '';
                    }
                }
                return $result;
            }
        }
        return FALSE;
    }

    /*
     * Listing patients appointments future, past, all
     */

    public function get_appointments_for_patient_by_type($pat_id, $type) {
        $prefix = $this->db->dbprefix;
        $today = date('Y-m-d H:i:s');
        $sql = "SELECT da.appointment_date, da.dentist_id, u.display_name AS dentist_name,  CASE WHEN da.status = 0 THEN '" . lang('bf_common_pending') . "' WHEN da.appointment_date > '" . $today . "' AND da.status = 1 THEN '" . lang('bf_common_confirmed') . "' WHEN da.appointment_date <= '" . $today . "' AND da.status = 1 THEN '" . lang('bf_common_completed') . "' ELSE '' END AS status FROM " . $prefix . "dentist_appointments da ";
        $sql .= "INNER JOIN " . $prefix . "users u ON u.id = da.dentist_id";
        $sql .= " WHERE u.active = 1 AND u.deleted = 0 AND u.banned = 0 AND da.deleted = 0";
        $sql .= " AND da.patient_id = '" . $pat_id . "'";
        switch ($type) {
            case 'future':
                $sql .= " AND DATE(da.appointment_date) >'" . $today . "'";
                $sql .= " ORDER BY da.appointment_date ASC";
                break;
            case 'past':
                $sql .= " AND DATE(da.appointment_date) <='" . $today . "'";
                $sql .= " ORDER BY da.appointment_date DESC";
                break;
            case 'all':
                $sql .= " ORDER BY da.appointment_date DESC";
                break;
            default:
                break;
        }
        $query = $this->db->query($sql);
        if ($query->num_rows()) {
            /* for pagination */
            $per_page = 5;
            $count = $query->num_rows();
            $curent_page = ($this->uri->segment(3) > 0) ? $this->uri->segment(3) : 1;
            $offset = ($curent_page - 1) * $per_page;
            $this->load->library('pagination');

            $config['base_url'] = base_url('patient_management/appointments');
            $config['total_rows'] = $count;
            $config['per_page'] = $per_page;
            $config['use_page_numbers'] = TRUE;
            $config['uri_segment'] = 3;
            $config['next_link'] = '<img src="' . Template::theme_url('images/rightarrow-pagination.png') . '" alt="" />';
            $config['prev_link'] = '<img src="' . Template::theme_url('images/leftarrow-pagination.png') . '" alt="" />';
            $config['cur_tag_open'] = '<a class="number active" href="#">';
            $config['cur_tag_close'] = '</a>';
            $config['first_link'] = FALSE;
            $config['last_link'] = FALSE;

            $this->pagination->initialize($config);

            $pagination = $this->pagination->create_links();
            /* End for pagination */

            $sql .= " LIMIT {$offset}, {$per_page}";

            $query = $this->db->query($sql);

            $data = array();
            $data['pagination'] = $pagination;
            $data['result'] = $query->result();
            $data['count'] = $count;
            return $data;
        }
        return FALSE;
    }

    /*
     * Appointment counts for Statistics at topbar
     */

    public function get_counts_of_appointments_for_profile() {
        $data = array();

        /* Start for dentist_profileview_count */
        if ($this->session->userdata("user_id")) {
            $this->load->model("dentist_management/user_management_model");
            $total_dentist_profileview_count = $this->user_management_model->dent_profileview_count($this->session->userdata("user_id"));
        //    var_dump($total_dentist_profileview_count);
            $data['dentist_profileview_count'] = $total_dentist_profileview_count;
        }
        /* End for dentist_profileview_count */

        /* Start for dentist_this_months_appointmnets */
        $total_dentist_appointments_this_month = $this->get_dentists_appointmnets_by_filter(TRUE);
        $data['dentist_this_months_appointmnets'] = $total_dentist_appointments_this_month;
        /* End for dentist_this_months_appointmnets */

        /* Start for dentist_total_appointmnets */
        $total_dentist_appointments_till_current_date = $this->get_dentists_appointmnets_by_filter();
        $data['dentist_appointments_till_current_date'] = $total_dentist_appointments_till_current_date;
        /* End for dentist_total_appointmnets */

        /* Start for dental_networks_appointments */
        $total_network_appointments_this_month = $this->get_dental_networks_appointments_allOver();
        $data['dental_networks_appointments'] = $total_network_appointments_this_month;
        /* End for dental_networks_appointments */

        /* Start for Average Appointments of registered users */
        $total_network_appointments_last_month = $this->get_dental_networks_appointments_last_month();
        $total_network_registered_dents = $this->get_registered_dent_users_count_till_last_month();

        if ($this->config_core['site.lastmonth_average_appointments'] > 0) {
            $data['avg_dental_networks_appointments'] = $this->config_core['site.lastmonth_average_appointments'];
        } else {
            $data['avg_dental_networks_appointments'] = $total_network_registered_dents > 0 ? round(($total_network_appointments_last_month / $total_network_registered_dents), 0) : 0;
        }
        /* End for Average Appointments of registered users */
      // var_dump($data);
        return $data;
    }

    /*
     * 1) Dentist appointments counts : total, current month
     */

    private function get_dentists_appointmnets_by_filter($is_cur_month = FALSE) {
        $month = date('n');
        $year = date('Y');
        if ($this->session->userdata("user_id")) {
            $dent_id = $this->session->userdata("user_id");
            $this->db->select('COUNT(*) AS total');
            $this->db->from($this->table . ' AS da');
            $this->db->join('users u', 'u.id = da.patient_id', 'inner');
            $this->db->where(array(
                'da.dentist_id' => $dent_id,
                'da.deleted' => 0,
                'da.status' => 1,
                'u.active' => 1,
                'u.deleted' => 0,
                'u.banned' => 0,
            ));
            if ($is_cur_month) {
                $this->db->where('YEAR(da.appointment_date)', $year);
                $this->db->where('MONTH(da.appointment_date)', $month);
            }
            $result = $this->db->get();
//            echo $this->db->last_query();
            if ($result->num_rows()) {
                return $result->row()->total;
            } else {
                return 0;
            }
        }
        return 0;
    }

    /*
     * 2) Whole Dental network appointment count
     */

    private function get_dental_networks_appointments_allOver() {
        if (isset($this->config_core['site.dental_appointments']) && is_numeric($this->config_core['site.dental_appointments']) && $this->config_core['site.dental_appointments'] > 0) {
            return $this->config_core['site.dental_appointments'];
        }
        $result = $this->db->select('COUNT(*) AS total')
                        ->from($this->table . ' da')
                        ->join('users u1', 'u1.id = da.dentist_id', 'inner')
                        ->join('users u2', 'u2.id = da.patient_id', 'inner')
                        ->where(array(
                            'da.deleted' => 0,
                            'da.status' => 1,
                            'u1.deleted' => 0,
                            'u1.banned' => 0,
                            'u1.active' => 1,
                            'u2.deleted' => 0,
                            'u2.banned' => 0,
                            'u2.active' => 1,
                        ))->get();
        if ($result->num_rows()) {
            return $result->row()->total;
        } else {
            return 0;
        }
    }

    /*
     * 3) Dental network appointmnets : last month
     */

    private function get_dental_networks_appointments_last_month() {
        if (isset($this->config_core['site.dn_appointment_last_month_count']) && is_numeric($this->config_core['site.dn_appointment_last_month_count']) && $this->config_core['site.dn_appointment_last_month_count'] > 0) {
            return $this->config_core['site.dn_appointment_last_month_count'];
        }
        return 0;
    }

    /*
     * Registered dentist count : last month
     */

    private function get_registered_dent_users_count_till_last_month() {
        $date = date('Y-m-d', strtotime('-1 second', strtotime(date('m') . '/01/' . date('Y'))));
        $result = $this->db->select('COUNT(*) AS total')
                        ->from('users u')
                        ->where(array(
                            'u.deleted' => 0,
                            'u.banned' => 0,
                            'u.active' => 1,
                            'u.role_id' => 7,
                            'DATE(u.created_on) <=' => $date,
                        ))->get();
        if ($result->num_rows()) {
            return $result->row()->total;
        } else {
            return 0;
        }
    }

    /*
     * Dental network appointments By year month
     */

    private function get_dental_networks_appointments_by_year_month($year, $month) {
        $result = $this->db->select('COUNT(*) AS total')
                        ->from($this->table . ' da')
                        ->join('users u1', 'u1.id = da.dentist_id', 'inner')
                        ->join('users u2', 'u2.id = da.patient_id', 'inner')
                        ->where(array(
                            'MONTH(da.appointment_date)' => $month,
                            'YEAR(appointment_date)' => $year,
                            'da.deleted' => 0,
                            'da.status' => 1,
                            'u1.deleted' => 0,
                            'u1.banned' => 0,
                            'u1.active' => 1,
                            'u2.deleted' => 0,
                            'u2.banned' => 0,
                            'u2.active' => 1,
                        ))->get();
        if ($result->num_rows()) {
            return $result->row()->total;
        } else {
            return 0;
        }
    }

    /*
     * Set Dental network appointments - last mont
     */

    public function set_dental_networks_appointments_last_month() {
        $month = date('n');
        $year = date('Y');
        if ($month == 1) {
            $month = 12;
            $year = $year - 1;
        } else {
            $month = $month - 1;
        }

        $last_month = 0;
        if (isset($this->config_core['site.dn_appointment_last_month']) && is_numeric($this->config_core['site.dn_appointment_last_month']) && $this->config_core['site.dn_appointment_last_month'] > 0) {
            $last_month = $this->config_core['site.dn_appointment_last_month'];
        }

        if ($month != $last_month || $last_month == 0) {
            $count = $this->get_dental_networks_appointments_by_year_month($year, $month);
            $data = array(
                array('name' => 'site.dn_appointment_last_month_count', 'value' => $count),
                array('name' => 'site.dn_appointment_last_month', 'value' => $month),
            );
            $this->settings_model->update_batch($data, 'name');
        }
    }

    public function send_appointment_mail($user_info, $dentist_id, $appointment_date) {


        $this->load->library('emailer/emailer');
        $prefix = $this->db->dbprefix;
        $this->load->model('email_template/email_template_model');
        //Dentist Information:  
        $query = $this->db->query('select display_name from ' . $prefix . 'users where id=' . $dentist_id . '');
        $get_dentname = array_shift($query->result());

        //patient Full name:
        $user_fullname = $user_info->display_name;
        $fomat_appointment_date = date('l F d, Y \a\t h:i A', strtotime($appointment_date));
        $patient_management = lang('bf_dentist_name');
        $appointment_date1 = lang('bf_appointment_date');
        $appointment_info = "<p><span><b> $patient_management :</b> </span>" . $get_dentname->display_name . "</p>" .
                "<p><span><b>$appointment_date1:</b> </span>" . $fomat_appointment_date . "</p>";


        /* Start:Send mail Admin After Create Appointment:chiragPrajapati */
        $template_admin = $this->email_template_model->find_by('label', 'new_appointment_booked_info_admin');
        if ($template_admin) {
            $Patient_name = lang('bf_patient_name');
            $appointment_info1 = "<p><span><b>$Patient_name:</b> </span>" . $user_fullname . "</p>";
            $appointment_info_admin = $appointment_info . $appointment_info1;

            $var_replace = array("[ADMIN_NAME]" => 'ADMIN', "[USER_FULLNAME]" => $user_fullname, "[APPOINTMENT_INFO]" => $appointment_info_admin);

            $template_admin->content_admin = strtr($template_admin->content, $var_replace);

            $subject = $template_admin->title;
            $content = $template_admin->content_admin;
            $email_admin = $this->settings_lib->item('site.system_email');
            ;

            $data_admin = array(
                "subject" => $subject,
                "message" => $content,
                "to" => $email_admin
            );

            $is_send_admin = $this->emailer->send($data_admin);
        }


        /* End:Send mail Admin After Create Appointment:chiragPrajapati */

        /* Start:send mail for patient After Create Appointment:chiragprajapati */
        $template = $this->email_template_model->find_by('label', 'new_appointment_pending');
        $user_email = $user_info->email;

        if ($template) {
            $template->content2 = str_replace('[USER_FULLNAME]', ucfirst($user_fullname), $template->content);
            $template->content2 = str_replace('[APPOINTMENT_INFO]', $appointment_info, $template->content2);

            $subject = $template->title . ' on ' . $fomat_appointment_date;
            $content = $template->content2;

            $data = array(
                "subject" => $subject,
                "message" => $content,
                "to" => $user_email
            );
            //  var_dump($data);die;
            $is_send = $this->emailer->send($data);
        }
        /* End:send mail for patient After Create Appointment:chiragprajapati */
    }

}
