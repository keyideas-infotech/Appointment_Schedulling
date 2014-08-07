<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class User_dent_info_model extends BF_Model
{

    protected $table = "user_dent_info";
    protected $key = "id";
    protected $soft_deletes = false;
    protected $date_format = "datetime";
    protected $set_created = false;
    protected $set_modified = false;

    public function __construct()
    {
        parent::__construct();
    }

    public function set_null_for($id, array $data)
    {
        if (!empty($data) && is_array($data) && !empty($id)) {
            return $this->db->update($this->table, $data, array($this->key => $id));
        }

        return FALSE;
    }

    public function find_basic_info_by_user_id($user_id, $flag = 0)
    {

        if (!empty($user_id)) {

            $user_table = $this->db->dbprefix("users");
            $user_dent_info_table = $this->db->dbprefix($this->table);

            $this->db->select("*");
            $this->db->from($user_table);
            $this->db->join($user_dent_info_table, "{$user_dent_info_table}.user_id = {$user_table}.id", "left");
            $this->db->where("{$user_table}.id", $user_id);
            $query = $this->db->get();

            if ($query->num_rows() > 0) {
                if ($flag == 1) {
                    return $query->row_array();
                } else {
                    return $query->row();
                }
            }
        }

        return FALSE;
    }

    public function toggle_downgrade_status($user_id, $flag = 0)
    {
        return $this->update_where("user_id", $user_id, array("can_degrade_plan" => $flag));
    }

}
