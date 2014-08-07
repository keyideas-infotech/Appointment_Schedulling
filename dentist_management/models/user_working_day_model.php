<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class User_working_day_model extends BF_Model {

    protected $table = "user_dent_working_days";
    protected $key = "id";
    protected $soft_deletes = false;
    protected $set_created = false;
    protected $set_modified = false;

    public function find_working_day_for_dent($user_id, $flag = 0) {
        
        if (!empty($user_id)) {
            //get sub by id...

            $days = $this->db->dbprefix("days_of_week");

            $this->db->select("{$this->table}.*, {$days}.*");
            $this->db->from($this->table);
            $this->db->join($days, "{$days}.id = {$this->table}.day_id");
            $this->db->where("user_id", $user_id);
            $query = $this->db->get();

            if ($query->num_rows() > 0) {
                if ($flag) {
                    return $query->result();
                } else {
                    return $query->result_array();
                }
            }
        }

        return FALSE;
    }

    public function combine_day_start_end_time_for_dent($working_days, $start_time, $end_time, $user_id) {

        if (is_array($working_days) && !empty($working_days) && !empty($user_id)) {

            $working_days_data = array();
            foreach ($working_days as $key => $value) {
                $tdd = array();
                $tdd['user_id'] = $user_id;
                $tdd['day_id'] = $value;
                if (isset($start_time[$key])) {
                    if (empty($start_time[$key])) {
                        $tdd['start_time'] = "00:00:00";
                    } else {
                        $tdd['start_time'] = $start_time[$key];
                    }
                }
                if (isset($end_time[$key])) {
                    if (empty($end_time[$key])) {
                        $tdd['end_time'] = "00:00:00";
                    } else {
                        $tdd['end_time'] = $end_time[$key];
                    }
                }
                $working_days_data[] = $tdd;
            }

            return $working_days_data;
        }

        return FALSE;
    }

    public function correct_index($working_days) {

        if (is_array($working_days) && !empty($working_days)) {
            $tmp_working_days = array();
            foreach ($working_days as $key => $value) {
                $key = $value - 1;
                $tmp_working_days[$key] = $value;
            }

            return $tmp_working_days;
        }
        return FALSE;
    }

    public function format_working_days_for_post($working_days, $start_time, $end_time) {

        if (is_array($working_days) && is_array($start_time) && is_array($end_time)) {
            $tmp_working_days = array();
            $working_days = $this->correct_index($working_days);

            foreach ($working_days as $key => $value) {
                $tmp = array();
                $tmp['start_time'] = (isset($start_time[$key]) && !empty($start_time[$key])) ? $start_time[$key] : "00:00:00";
                $tmp['end_time'] = (isset($end_time[$key]) && !empty($end_time[$key])) ? $end_time[$key] : "00:00:00";
                $tmp_working_days[$value] = $tmp;
            }

            return $tmp_working_days;
        }

        return FALSE;
    }

//end find_user_and_meta()
}
