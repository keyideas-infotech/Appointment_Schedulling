<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Dentist_management extends Front_Controller {

//--------------------------------------------------------------------

    private $module_config = array();

    const PLAN_NOT_FOUND = 2;
    const USER_HASH_NOT_VALID = 3;
    const OPERATION_FAILED = 4;
    const TRIAL_PLAN_SELECTED = 5;
    const CREDIT_CARD_INFO_NOT_VALID = 6;
    const TOKEN_NOT_ISSUED = 7;
    const NO_PLAN_SUBSCRIBED = 8;
    const PLAN_CANCELLATION_FAILED = 9;
    const SUCCESSFULL = 10;
    const PROMO_CODE_NOT_FOUND = 11;
    const PROMO_CODE_ALREADY_USED = 12;

    public function __construct() {
        parent::__construct();
        $this->load->library("form_validation");
        $this->load->library("paypal");
//load language file
        $this->lang->load('dentist_management');
//load upload helper...
        $this->load->helper("upload_file");
//load upload library...
        $this->load->library('upload');

        $this->module_config = module_config("dentist_management");
        Template::set('module_config', $this->module_config);


        $this->load->model("dentist_management/user_dent_mulimages_model");
        /* Start for counting prev months dental network appointments */
        $this->load->model('dentist_appointments/dentist_appointments_model', 'dentist_appointments_model');
        $this->dentist_appointments_model->set_dental_networks_appointments_last_month();
        /* End for counting prev months dental network appointments */
    }

//--------------------------------------------------------------------

    public function enrollment() {

//Check for authentication...
        $user_id = $this->session->userdata("user_id");

        if (!empty($user_id)) {
            Template::redirect("/");
        }

        if ($this->input->post()) {

            $id = $this->submit();
            if ($id !== FALSE) {
                $msg = lang('dentist_management_account_activation_mail');
                $form_hide = lang('dentist_management_account_activation_mail');
                $this->session->set_userdata("gen_msg", array("type" => "success", "msg" => $msg));
                $this->session->set_userdata("form_hide", array("type" => "success", "form_hide" => $form_hide));
                Template::redirect("dentist_management/enrollment");
            }
        }

//module assignments...
        Assets::add_module_js("dentist_management", "dentist_management_front.js");
        //Assets::add_js("jquery.fancybox.js");
        Template::set("enrollment_banner", TRUE);

//get subscription plans...
        $this->load->model("plan/plan_model", "pm");
        Template::set("subscription_plans", $this->pm->order_by("position", "ASC")->find_all_by("status", 1));
//get insurance details...
        $this->load->model("insurance/insurance_model", "im");
        Template::set("insurance_accepted", $this->im->find_all_by("status", 1));
//get dental specialities...
        $this->load->model("dental_speciality/dental_speciality_model", "dsm");
        Template::set("dental_specialities", $this->dsm->find_all_by("status", 1));
        Template::render();
    }

    private function submit() {
//Post is set...

        $this->form_validation->set_rules('first_name', lang('dentist_management_first_name'), 'required|trim|xss_clean|max_length[255]');
        $this->form_validation->set_rules('last_name', lang('dentist_management_last_name'), 'required|trim|xss_clean|max_length[255]');
        $this->form_validation->set_rules('tel_no', lang('dentist_management_tel_no'), 'required|trim|xss_clean|max_length[15]');
        $this->form_validation->set_rules('insurance_id', lang('dentist_management_Insurance'), 'required');
        $this->form_validation->set_rules('education', lang('bf_common_education'), 'required|xss_clean|max_length[255]');
        $this->form_validation->set_rules('speciality_id', lang('dentist_management_speciality'), 'required');
        $this->form_validation->set_rules('address', lang('bf_address'), 'required|xss_clean|max_length[255]');
        $this->form_validation->set_rules('zipcode', lang('bf_common_zip_code'), 'required|xss_clean|max_length[6]');
        $this->form_validation->set_rules('email', lang('bf_email'), 'required|trim|unique[users.email]|valid_email|max_length[120]|xss_clean');
        $this->form_validation->set_rules('password', lang('bf_password'), 'required|trim|strip_tags|min_length[8]|max_length[120]|valid_password|xss_clean');
        $this->form_validation->set_rules('terms_condition', lang('dentist_management_terms_and_condition'), 'required|xss_clean');


        if ($this->form_validation->run($this) === FALSE) {
            return FALSE;
        }

// make sure we only pass in the fields we want
//save info to user table...
        $this->load->model("users/user_model");
        $data = array(
            'email' => $this->input->post('email'),
            'username' => "",
            'display_name' => $this->input->post('first_name') . " " . $this->input->post('last_name'),
            'password' => $this->input->post('password'),
            'role_id' => 7,
            'deleted' => 0,
            'banned' => 0,
            'active' => 0,
            'payment_status' => 0
        );


        // var_dump($data);die;exit;
        $return = $user_id = $this->user_model->insert($data);

        if (is_numeric($user_id)) {

//save data to user_dent_info...
            $this->load->model("dentist_management/user_dent_info_model", "udim");

            /* ------------Add verified_provide default value for dentist:chiragP--------- */
            $data2 = array(
                'user_id' => $user_id,
                'first_name' => $this->input->post('first_name'),
                'last_name' => $this->input->post('last_name'),
                'tel_no' => $this->input->post('tel_no'),
                'education' => $this->input->post('education'),
                'address' => $this->input->post('address'),
                'zipcode' => $this->input->post('zipcode'),
                'verified_provider' => '0',
            );

            /* For getting latLong by address -By Hitendra */
            $address = "";
            if ($this->input->post('address')) {
                $address .= $this->input->post('address');
            }
            if ($this->input->post('zipcode')) {
                $address .= "," . $this->input->post('zipcode');
            }

            $this->load->model('search/search_model');
            $location = $this->search_model->getLatLngByAddress($address);

            if ($location) {
                $data2['lat'] = $location['lat'];
                $data2['lng'] = $location['lng'];
            } else {
                $data2['lat'] = 0.0;
                $data2['lng'] = 0.0;
            }
            /* End for getting latLong by address -By Hitendra */
            //    var_dump($data2);die;
            $this->udim->insert($data2);

//save data to user_dent_insu...
            $this->load->model("dentist_management/user_dent_insu_model", "udism");
            $insurance_ids = $this->input->post("insurance_id");
            $tmp = array();
            if (!empty($insurance_ids) && is_array($insurance_ids)) {
                foreach ($insurance_ids as $insu_id) {
                    $tmp[] = array("user_id" => $user_id, "insurance_id" => $insu_id);
                }
                $this->udism->insert_batch($tmp);
            }


//save data to user_dent_spcl...
            $this->load->model("dentist_management/user_dent_spcl_model", "udsm");
            $speciality_ids = $this->input->post("speciality_id");
            $tmp2 = array();
            if (!empty($speciality_ids) && is_array($speciality_ids)) {
                foreach ($speciality_ids as $spcl_id) {
                    $tmp2[] = array("user_id" => $user_id, "spcl_id" => $spcl_id);
                }
                $this->udsm->insert_batch($tmp2);
            }
            //Send Activation Mail To User
            $this->send_activation_email($user_id);
        }

        return $return;
    }

    private function payment() {

        if ($this->session->userdata("proceed_payment")) {
            $this->session->unset_userdata("proceed_payment");
//Initialization...
            $this->load->model("plan/plan_model", "pm");
            $this->load->model("promocodes/promocodes_model", "pcm");

            $promo_code_id = $this->session->userdata("promo_code_id");
            $registering_user_id = $this->session->userdata("registering_user_id");
            $registering_plan_id = $this->session->userdata("registering_user_plan_id");
            $paypal = new Paypal();

            if (empty($registering_user_id) || empty($registering_plan_id)) {
                $this->reset_session_variables();
                return FALSE;
            }

            $plan = $this->pm->find_by("id", $registering_plan_id);
            if ($plan == FALSE) {
                $this->reset_session_variables();
                return self::PLAN_NOT_FOUND;
            }

            $price_with_discount = $plan->price;

            if ($promo_code_id != FALSE && !empty($promo_code_id)) {
                $price_with_discount = $this->pcm->get_price_with_promocode_discount($promo_code_id, $plan->price);
                //dump($price_with_discount);
            }

//You have authority to initiate payment...
            $requestParams = array(
//Payment related options...
                'PAYMENTREQUEST_0_PAYMENTACTION' => 'Sale',
                'PAYMENTREQUEST_0_AMT' => round($price_with_discount),
                'PAYMENTREQUEST_0_CURRENCYCODE' => 'USD',
                'PAYMENTREQUEST_0_ITEMAMT' => round($price_with_discount),
                'PAYMENTREQUEST_0_DESC' => $plan->description,
//Payment details item...
                'L_PAYMENTREQUEST_0_NAME0' => $plan->name,
                'L_PAYMENTREQUEST_0_DESC0' => $plan->description,
                'L_PAYMENTREQUEST_0_AMT0' => round($price_with_discount),
                'L_PAYMENTREQUEST_0_QTY0' => '1',
//Shipping details...
                'NOSHIPPING' => '1',
//Style details...
                /* 'HDRIMG' => base_url("assets/images/logo.png"),
                  'PAYFLOWCOLOR' => "#A4E4F8", */
                'PAGESTYLE' => 'DentalPayment',
//Landing Page...
                'LANDINGPAGE' => 'login',
                'L_BILLINGTYPE0' => 'RecurringPayments',
                'L_BILLINGAGREEMENTDESCRIPTION0' => $plan->description,
                'RETURNURL' => base_url("dentist_management/finalize_payment"),
                'CANCELURL' => base_url("dentist_management/cancel_payment")
            );

//Get paypal token...
            $response = $paypal->request('SetExpressCheckout', $requestParams);
            //dump($response); die;
            if (empty($response) || ($response['ACK'] != 'Success')) {
                $this->reset_session_variables();
                return self::TOKEN_NOT_ISSUED;
            }

//Request successful
            $token = $response['TOKEN'];
            $this->session->set_userdata("finalize_payment", TRUE);
            header('Location: https://www.sandbox.paypal.com/webscr?cmd=_express-checkout&token=' . urlencode($token));
            die;
        } else {
            redirect("/");
        }
    }

    public function direct_payment() {

        if ($this->session->userdata("proceed_payment")) {
            $this->session->unset_userdata("proceed_payment");

//Initialization...
            $this->load->helper("date");

            $this->load->model("plan/plan_model", "pm");
            $this->load->model("users/user_model", "um");
            $this->load->model("promocodes/promocodes_model", "pcm");
            $this->load->model("email_template/email_template_model", "em");
            $this->load->model("dentist_management/user_dent_info_model", "udim");
            $this->load->model("dentist_management/user_dent_subplan_model", "udspm");
            $this->load->model("dentist_management/user_dent_activation_model", "udam");
            $this->load->model("dentist_management/user_dent_sub_history_model", "udshm");

            $type = $this->session->userdata("type");
            $promo_code_id = $this->session->userdata("promo_code_id");
            $registering_user_id = $this->session->userdata("registering_user_id");
            $registering_plan_id = $this->session->userdata("registering_user_plan_id");
            $registering_user_sub_table_id = $this->session->userdata("registering_user_sub_table_id");

            $card_type = $this->input->post("card_type");
            $card_owner = $this->input->post("card_owner");
            $card_number = $this->input->post("card_number");
            $cvv = $this->input->post("cvv");
            $month = $this->input->post("date");
            $year = $this->input->post("year");
            $expdate = $month . $year;
            $zip = $this->input->post("zipcode");

            if (empty($registering_user_id) || empty($registering_plan_id)) {
                $this->reset_session_variables();
                return FALSE;
            }

            $plan = $this->pm->find_by("id", $registering_plan_id);
            if (empty($plan)) {
                $this->reset_session_variables();
                return FALSE;
            }
            $price_with_discount = $plan->price;

            if ($promo_code_id != FALSE && !empty($promo_code_id)) {
                $price_with_discount = $this->pcm->get_price_with_promocode_discount($promo_code_id, $plan->price);
            }

//You have authority to initiate payment...
            $paypal = new Paypal();

            $options = array(
                'PAYMENTACTION' => 'Sale',
                'AMT' => round($price_with_discount),
                'DESC' => $plan->description,
//Account related details...
                "ACCT" => $card_number,
                "CREDITCARDTYPE" => $card_type,
                "CVV2" => $cvv,
                "EXPDATE" => $expdate,
//Personal details...
                "FIRSTNAME" => $card_owner,
                "LASTNAME" => "",
                "STREET" => "1 Main St",
                "CITY" => "San Jose",
                "STATE" => "CA",
                "ZIP" => $zip,
                "CURRENCYCODE" => "USD",
                "COUNTRYCODE" => "US",
            );

            $one_time_payment = $paypal->request('DoDirectPayment', $options);
//                dump($one_time_payment); die;

            if (empty($one_time_payment) || ($one_time_payment['ACK'] == "Failure")) {
                $this->reset_session_variables();
                return self::CREDIT_CARD_INFO_NOT_VALID;
            }

//One time payment has done... Now create recurring profile...
            $profile_start_date = $this->pm->get_date_by($plan->duration, DATE_ATOM);
            $start_date = date("Y-m-d H:i:s", now());
            $end_date = $this->pm->get_date_by_interval_of($plan->duration, 'Y-m-d H:i:s', $start_date);
            $txn_id = $one_time_payment['TRANSACTIONID'];
            $txn_date = $one_time_payment['TIMESTAMP'];

            $update = array(
                "user_id" => $registering_user_id,
                "plan_id" => $registering_plan_id,
                'pay_mode' => "card",
                "start_date" => $start_date,
                "end_date" => $end_date,
                'txn_date' => date("Y-m-d H:i:s", strtotime($txn_date)),
                'txn_id' => $txn_id,
                "status" => 1,
            );

            $requestParams = array(
                "PROFILESTARTDATE" => $profile_start_date,
                "DESC" => $plan->description,
                "BILLINGPERIOD" => ucfirst($plan->duration),
                "BILLINGFREQUENCY" => "1",
                "RECURRING" => "ns:Y",
                "AMT" => $plan->price,
                "MAXFAILEDPAYMENTS" => "1",
                "CURRENCYCODE" => "USD",
                "COUNTRYCODE" => "US",
//account related details...
                "ACCT" => $card_number,
                "CREDITCARDTYPE" => $card_type,
                "CVV2" => $cvv,
//personal details...
                "FIRSTNAME" => $card_owner,
                "LASTNAME" => "",
                "STREET" => "1 Main St",
                "CITY" => "San Jose",
                "STATE" => "CA",
                "ZIP" => $zip,
                "EXPDATE" => $expdate,
            );

//Create recurring profile...
            $crpro = $paypal->request('CreateRecurringPaymentsProfile', $requestParams);

            if (isset($crpro['ACK']) && ($crpro['ACK'] == "Success")) {

//Recurring profile created...
                $sub_profile_id = $crpro['PROFILEID'];
                $sub_profile_status = $crpro['PROFILESTATUS'];
                $sub_profile_timestamp = $crpro['TIMESTAMP'];
                $sub_profile_ack = $crpro['ACK'];

                $update['sub_profile_id'] = $sub_profile_id;
                $update['sub_profile_status'] = $sub_profile_status;
                $update['sub_timestamp'] = $sub_profile_timestamp;
                $update['sub_ack'] = $sub_profile_ack;
            }

//User has done payment and recurring profile has been created at paypal...
//Finalizing steps...
//1) Remove activation hash from hash_table user has done final step...
//2) Change user payment_status and active status to active...
//3) Change user subscription to active and if not found insert subscription...
//4) Insert subscription data to history table...
//1) Remove activation hash from hash_table...
//Delete activation hash entry for this user...

            $this->udam->delete_where(array("dentist_id" => $registering_user_id));

//2) Change user payment_status and active status to active...
//Update payment status in users table...

            $this->um->update($registering_user_id, array("payment_status" => 1, "active" => 1));

//3) Change user subscription to active and if not found insert subscription...
//Get user_sub_table and update status...

            $this->udspm->update($registering_user_sub_table_id, $update);

//4) Insert subscription data to history table...

            $this->udshm->insert_sub_history_from($registering_user_sub_table_id);

//6) Insert promocode data to table...

            if ($promo_code_id != FALSE && !empty($promo_code_id)) {
                $this->upcm->insert(array("promo_code_id" => $promo_code_id, "user_id" => $registering_user_id));
            }

//5)Send mail to user...
            $user = $this->udim->find_basic_info_by_user_id($registering_user_id);
            $date = new DateTime("now");

            if ($user) {

                if ($type == "upgrade") {
                    $template_label = "dentist_plan_upgrade_success";
                } else {
                    $template_label = "welcome_to_dental_network_dentist_confirm";
                }

                $email_config = array("to" => $user->email);
                $array_to_Replace = array(
                    "[USER_FNAME]" => ucfirst($user->first_name),
                    "[USER_LNAME]" => ucfirst($user->last_name),
                    "[PLAN_NAME]" => ucfirst($plan->name),
                    "[PLAN_PRICE]" => "$" . $plan->price,
                    "[USER_FULLNAME]" => ucwords($user->first_name . " " . $user->last_name),
                    "[USER_PHONE_NO]" => $user->tel_no,
                    "[USER_EMAIL]" => $user->email,
                    "[SITE_NAME]" => $this->settings_lib->item('site.title'),
                    "[SITE_URL]" => base_url(),
                    "[SITE_MAIL]" => $this->settings_lib->item('site.system_email'),
                    "[DATE]" => $date->format("m-d-Y"),
                    "[TIME]" => $date->format("h:i A")
                );
                $this->em->send_mail($template_label, $array_to_Replace, $email_config);
            }

//Cancel previous plan for type upgrade...
            if ($type == "upgrade") {
                $previous_profile_id = $this->session->userdata("user_profile_id");
                $this->cancel_plan($previous_profile_id);
            }


//Unset all session...
            $this->reset_session_variables();

            return TRUE;
        } else {

            redirect("/");
        }
    }

    public function finalize_payment() {
        if ($this->session->userdata("finalize_payment")) {
            $this->session->unset_userdata("finalize_payment");

//Load helper...
            $this->load->helper("date");

            $this->load->model("plan/plan_model", "pm");
            $this->load->model("users/user_model", "um");
            $this->load->model("promocodes/promocodes_model", "pcm");
            $this->load->model("promocodes/used_promocodes_model", "upcm");
            $this->load->model("email_template/email_template_model", "em");
            $this->load->model("dentist_management/user_dent_info_model", "udim");
            $this->load->model("dentist_management/user_dent_subplan_model", "udspm");
            $this->load->model("dentist_management/user_dent_activation_model", "udam");
            $this->load->model("dentist_management/user_dent_sub_history_model", "udshm");

            $paypal = new Paypal();
            $token = $this->input->get("token");
            $type = $this->session->userdata("type");
            $redirect_url = $this->session->userdata("rurl");
            $fallback_url = $this->session->userdata("furl");
            $promo_code_id = $this->session->userdata("promo_code_id");
            $message = $this->session->userdata("success_message");
            $registering_user_id = $this->session->userdata("registering_user_id");
            $registering_plan_id = $this->session->userdata("registering_user_plan_id");
            $registering_user_sub_table_id = $this->session->userdata("registering_user_sub_table_id");

//Check token and do some security check to compare token...
            if (!$token) {
                $msg = lang('dentist_management_error_token_not_found');
                $this->session->set_userdata("gen_msg", array("type" => "danger", "msg" => $msg));
                redirect($fallback_url);
            }

//Get paypal user details...
            $checkoutDetails = $paypal->request('GetExpressCheckoutDetails', array('TOKEN' => $token));
            if (empty($checkoutDetails)) {
                $msg = lang('dentist_management_error_checkout_detail_error');
                $this->session->set_userdata("gen_msg", array("type" => "danger", "msg" => $msg));
                redirect($fallback_url);
            }

//Get plan information...
            $plan = $this->pm->find_by("id", $registering_plan_id);
            if ($plan == FALSE) {
                $msg = lang('dentist_management_error_plan_not_found');
                $this->session->set_userdata("gen_msg", array("type" => "danger", "msg" => $msg));
                redirect($fallback_url);
            }
            $price_with_discount = $plan->price;

            if ($promo_code_id != FALSE && !empty($promo_code_id)) {
                $price_with_discount = $this->pcm->get_price_with_promocode_discount($promo_code_id, $plan->price);
            }


//Do express checkout for one time charge...
            $options = array(
                'TOKEN' => $checkoutDetails['TOKEN'],
                "PAYERID" => $checkoutDetails['PAYERID'],
                'PAYMENTREQUEST_0_PAYMENTACTION' => 'Sale',
                'PAYMENTREQUEST_0_AMT' => round($price_with_discount),
                'PAYMENTREQUEST_0_CURRENCYCODE' => 'USD'
            );

            $expressCheckoutDetails = $paypal->request('DoExpressCheckoutPayment', $options);
            //dump($expressCheckoutDetails); die;
            if (empty($expressCheckoutDetails) || ($expressCheckoutDetails['ACK'] != "Success")) {
                $msg = lang('dentist_management_error_payment_error');
                $this->session->set_userdata("gen_msg", array("type" => "danger", "msg" => $msg));
                redirect($fallback_url);
            }

//Payment is done... Now create recurring profile...
            $profile_start_date = $this->pm->get_date_by($plan->duration, DATE_ATOM);
            $start_date = date("Y-m-d H:i:s", now());
            $end_date = $this->pm->get_date_by_interval_of($plan->duration, 'Y-m-d H:i:s', $start_date);
            $txn_id = $expressCheckoutDetails['PAYMENTINFO_0_TRANSACTIONID'];
            $txn_date = $expressCheckoutDetails['PAYMENTINFO_0_ORDERTIME'];

            $update = array(
                "user_id" => $registering_user_id,
                "plan_id" => $registering_plan_id,
                'pay_mode' => "paypal",
                "start_date" => $start_date,
                "end_date" => $end_date,
                'txn_date' => date("Y-m-d H:i:s", strtotime($txn_date)),
                'txn_id' => $txn_id,
                "status" => 1
            );

            $pr = array(
                'TOKEN' => $checkoutDetails['TOKEN'],
                "PAYERID" => $checkoutDetails['PAYERID'],
                "PROFILESTARTDATE" => $profile_start_date,
                "DESC" => $plan->description,
                "BILLINGPERIOD" => ucfirst($plan->duration),
                "BILLINGFREQUENCY" => "1",
                "AMT" => $plan->price,
                "CURRENCYCODE" => "USD",
                "COUNTRYCODE" => "US",
                "MAXFAILEDPAYMENTS" => "1",
            );


            $crpro = $paypal->request('CreateRecurringPaymentsProfile', $pr);
//                        dump($crpro);

            if (isset($crpro['ACK']) && ($crpro['ACK'] == "Success")) {

//Recurring profile successfully created...
                $sub_profile_id = $crpro['PROFILEID'];
                $sub_profile_status = $crpro['PROFILESTATUS'];
                $sub_profile_timestamp = $crpro['TIMESTAMP'];
                $sub_profile_ack = $crpro['ACK'];

//Temp for testing ipn request
                $this->load->model("dentist_management/user_dent_subplan_model", "udspm");
                $update['sub_profile_id'] = $sub_profile_id;
                $update['sub_profile_status'] = $sub_profile_status;
                $update['sub_timestamp'] = $sub_profile_timestamp;
                $update['sub_ack'] = $sub_profile_ack;
            }

//User has done payment and recurring profile has been created at paypal...
//1) Remove activation hash from hash_table user has done final step...
//2) Change user payment_status and active status to active...
//3) Change user subscription to active and if not found insert subscription...
//4) Insert subscription data to history table...
//1) Remove activation hash from hash_table...
//Delete activation hash entry for this user...

            $this->udam->delete_where(array("dentist_id" => $registering_user_id));

//2) Change user payment_status and active status to active...
//Update payment status in users table...

            $this->um->update($registering_user_id, array("payment_status" => 1, "active" => 1));

//3) Change user subscription to active and if not found insert subscription...
//Get user_sub_table and update status...

            $this->udspm->update($registering_user_sub_table_id, $update);

//4) Insert subscription data to history table...

            $this->udshm->insert_sub_history_from($registering_user_sub_table_id);

//6) Insert promo code in table...
            if ($promo_code_id != FALSE && !empty($promo_code_id)) {
                $this->upcm->insert(array("promo_code_id" => $promo_code_id, "user_id" => $registering_user_id));
            }

//7) Set downgrade flag to false....
            if ($type == "upgrade") {
                $this->udim->toggle_downgrade_status($registering_user_id);
            }

//5)Send mail to user...
            $user = $this->udim->find_basic_info_by_user_id($registering_user_id);
            $date = new DateTime("now");

            if ($user) {

                if ($type == "upgrade") {
                    $template_label = "dentist_plan_upgrade_success";
                } else {
                    $template_label = "welcome_to_dental_network_dentist_confirm";
                }


                $email_config = array("to" => $user->email);
                $array_to_Replace = array(
                    "[USER_FNAME]" => ucfirst($user->first_name),
                    "[USER_LNAME]" => ucfirst($user->last_name),
                    "[PLAN_NAME]" => ucfirst($plan->name),
                    "[PLAN_PRICE]" => "$" . $plan->price,
                    "[USER_FULLNAME]" => ucwords($user->first_name . " " . $user->last_name),
                    "[USER_PHONE_NO]" => $user->tel_no,
                    "[USER_EMAIL]" => $user->email,
                    "[SITE_NAME]" => $this->settings_lib->item('site.title'),
                    "[SITE_URL]" => base_url(),
                    "[SITE_MAIL]" => $this->settings_lib->item('site.system_email'),
                    "[DATE]" => $date->format("m-d-Y"),
                    "[TIME]" => $date->format("h:i A")
                );
                $this->em->send_mail($template_label, $array_to_Replace, $email_config);
            }

//cancel previous plan for type upgrade...
            if ($type == "upgrade") {
                $previous_profile_id = $this->session->userdata("user_profile_id");
                $this->cancel_plan($previous_profile_id);
            }

//Unset all session data...
            $this->reset_session_variables();
//Set session message...
            $this->session->set_userdata("gen_msg", array("type" => "success", "msg" => $message));

            redirect($redirect_url);
        }

        redirect("/");
    }

    public function cancel_payment() {
//Do some cleanup process here...
        $type = $this->session->userdata("type");
        $furl = $this->session->userdata("furl");
        if ($type == "register") {
            $msg = lang('dentist_management_error_registration_process');
        } else if ($type == "upgrade") {
            $msg = lang('dentist_management_error_upgradation_canceled');
        }
        $this->reset_session_variables();
        $this->session->set_userdata("gen_msg", array("type" => "danger", "msg" => $msg));
        redirect($furl);
    }

    private function reset_session_variables() {
        $this->session->unset_userdata("rurl");
        $this->session->unset_userdata("furl");
        $this->session->unset_userdata("type");
        $this->session->unset_userdata("gen_msg");
        $this->session->unset_userdata("promo_code_id");
        $this->session->unset_userdata("success_message");
        $this->session->unset_userdata("proceed_payment");
        $this->session->unset_userdata("finalize_payment");
        $this->session->unset_userdata("registering_user_id");
        $this->session->unset_userdata("registering_user_plan_id");
        $this->session->unset_userdata("registering_user_sub_table_id");
    }

    public function notify() {

//load date helper...
        $this->load->helper("date");

//process the post data...
        $raw_post_data = file_get_contents('php://input');
        $raw_post_array = explode('&', $raw_post_data);
        $myPost = array();

        foreach ($raw_post_array as $keyval) {
            $keyval = explode('=', $keyval);
            if (count($keyval) == 2) {
                $myPost[$keyval[0]] = urldecode($keyval[1]);
            }
        }

// read the IPN message sent from PayPal and prepend 'cmd=_notify-validate'
        $req = 'cmd=_notify-validate';
        if (function_exists('get_magic_quotes_gpc')) {
            $get_magic_quotes_exists = true;
        }

        foreach ($myPost as $key => $value) {
            if ($get_magic_quotes_exists == true && get_magic_quotes_gpc() == 1) {
                $value = urlencode(stripslashes($value));
            } else {
                $value = urlencode($value);
            }
            $req .= "&$key=$value";
        }

        $this->db->insert("paypal_ipn_notification_request", array('time' => date("Y-m-d H:i:s", now()), "response" => $req));


        $curlOptions = array(
            CURLOPT_URL => 'https://www.sandbox.paypal.com/cgi-bin/webscr',
            CURLOPT_VERBOSE => 1,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CAINFO => FCPATH . "bonfire" . DIRECTORY_SEPARATOR . "application" . DIRECTORY_SEPARATOR . "libraries" . DIRECTORY_SEPARATOR . "cacert.pem", //CA cert file
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => $req
        );

        $ch = curl_init();
        curl_setopt_array($ch, $curlOptions);

//Sending our request - $response will hold the API response
        $response = curl_exec($ch);


        if (strcmp($response, "VERIFIED") == 0) {
//The IPN is verified, process it
//Do some processing here...

            $txn_type = $_POST['txn_type'];
            $payer_email = $_POST['payer_email'];
            $receiver_email = $_POST['receiver_email'];
            $payer_id = $_POST['payer_id'];
            $recurring_payment_id = $_POST['recurring_payment_id'];
            $product_name = $_POST['product_name'];
            $ipn_track_id = $_POST['ipn_track_id'];
            $time_created = $_POST['time_created'];
            $profile_status = $_POST['profile_status'];


//Check whether this ipn is processed before...
            $this->db->select("*");
            $this->db->from("processed_ipn");
            $this->db->where(array("ipn_track_id" => $ipn_track_id));
            $query = $this->db->get();

            if ($query->num_rows() > 0) {
                die();
            }

//find user from unique profile id stored in the database...

            $this->load->model("dentist_management/user_dent_subplan_model", "udspm");
            $subscription = $this->udspm->find_by(array("sub_profile_id" => $recurring_payment_id));


            if ($subscription) {

//Find user from subscription...
                $this->load->library('emailer/emailer');
                $this->load->model("email_template/email_template_model", "em");

//Find email by user_id...
                $this->db->select("*");
                $this->db->from("users");
                $this->db->join("user_dent_info", "user_dent_info.user_id = users.id", "left");
                $this->db->where("users.id", $subscription->user_id);
                $query = $this->db->get();
                $user = $query->row();
                $date = new DateTime("now");


                switch ($txn_type) {
                    case "recurring_payment":

                        $business = $_POST['business'];
                        $payment_status = $_POST['payment_status'];
                        $next_payment_date = $_POST['next_payment_date'];
                        $payment_cycle = $_POST['payment_cycle'];
                        $payment_date = $_POST['payment_date'];
                        $amount_per_cycle = $_POST['amount_per_cycle'];
                        $txn_id = $_POST['txn_id'];
                        $receiver_id = $_POST['receiver_id'];
                        $payment_gross = $_POST['payment_gross'];

                        $data = array();
                        $data['start_date'] = date("Y-m-d H:i:s", strtotime($payment_date));
                        $data['end_date'] = date("Y-m-d H:i:s", strtotime($next_payment_date));
                        $data['txn_date'] = date("Y-m-d H:i:s", strtotime($payment_date));
                        $data['txn_id'] = $txn_id;
                        $data['sub_profile_status'] = $profile_status;
                        $data['status'] = 1;

                        $this->udspm->update($subscription->id, $data);

                        $this->load->model("users/user_model", "um");
                        $this->um->update($subscription->user_id, array("payment_status" => 1, "active" => 1));

//5) This ipn is processed insert into database for track...
                        $ipn_data = array(
                            "ipn_track_id" => $ipn_track_id,
                            "txn_type" => $txn_type,
                            "recurring_profile_id" => $recurring_payment_id,
                            "payer_id" => $payer_id,
                            "date" => date("Y-m-d H:i:s", now())
                        );
                        $this->db->insert("processed_ipn", $ipn_data);

                        break;

                    case "recurring_payment_expired":

                        break;

                    case "recurring_payment_suspended_due_to_max_failed_payment":

//User has cancelled recurring profile...
//Do plan status inactive and sub_status cancelled...
                        $data = array(
                            "status" => 0,
                            "sub_profile_status" => $profile_status
                        );

                        $this->udspm->update($subscription->id, $data);

//4) Insert subscription data to history table...
//now get that record and save it in user_history table...
                        $history_table = $this->db->dbprefix("user_dent_sub_history");
                        $subscription_table = $this->db->dbprefix("user_dent_subscription");

                        $sql = "INSERT INTO {$history_table} "
                                . "SELECT * FROM {$subscription_table} AS uds "
                                . "WHERE uds.id = {$subscription->id}";

                        $this->db->query($sql);

//Send Mail...
                        //Find email template for activation mail...
                        $template = $this->em->find_by(array("label" => "suspended_account", "status" => 1));

                        if ($user && $template) {
//Send activation mail...
                            $array_to_Replace = array(
                                "[USER_FNAME]" => ucfirst($user->first_name),
                                "[USER_LNAME]" => ucfirst($user->last_name),
                                "[USER_FULLNAME]" => ucwords($user->first_name . " " . $user->last_name),
                                "[USER_PHONE_NO]" => $user->tel_no,
                                "[USER_EMAIL]" => $user->email,
                                "[SITE_NAME]" => $this->settings_lib->item('site.title'),
                                "[SITE_URL]" => base_url(),
                                "[SITE_MAIL]" => $this->settings_lib->item('site.system_email'),
                                "[DATE]" => $date->format("m-d-Y"),
                                "[TIME]" => $date->format("h:i A")
                            );

                            $subject = $template->title;
                            $message = html_entity_decode($this->em->replacer($template->content, $array_to_Replace));

                            $data = array(
                                'to' => $user->email,
                                'subject' => $subject,
                                'message' => $message
                            );
                            $this->emailer->send($data);
                        }


//This ipn is processed insert into database for track...
                        $ipn_data = array(
                            "ipn_track_id" => $ipn_track_id,
                            "txn_type" => $txn_type,
                            "recurring_profile_id" => $recurring_payment_id,
                            "payer_id" => $payer_id,
                            "date" => date("Y-m-d H:i:s", now())
                        );
                        $this->db->insert("processed_ipn", $ipn_data);

                        break;

                    case "recurring_payment_profile_cancel":

//User has cancelled recurring profile...
//Do plan status inactive and sub_status cancelled...
                        $data = array(
                            "status" => 0,
                            "sub_profile_status" => $profile_status,
//                            "end_date" => date("Y-m-d H:i:s", now())
                        );

                        $this->udspm->update($subscription->id, $data);

//4) Insert subscription data to history table...
//now get that record and save it in user_history table...
                        $history_table = $this->db->dbprefix("user_dent_sub_history");
                        $subscription_table = $this->db->dbprefix("user_dent_subscription");

                        $sql = "INSERT INTO {$history_table} "
                                . "SELECT * FROM {$subscription_table} AS uds "
                                . "WHERE uds.id = {$subscription->id}";

                        $this->db->query($sql);

//This ipn is processed insert into database for track...
                        $ipn_data = array(
                            "ipn_track_id" => $ipn_track_id,
                            "txn_type" => $txn_type,
                            "recurring_profile_id" => $recurring_payment_id,
                            "payer_id" => $payer_id,
                            "date" => date("Y-m-d H:i:s", now())
                        );
                        $this->db->insert("processed_ipn", $ipn_data);

                        break;

                    default:
                        break;
                }
            } else {
//Sorry profile not matched...
                die;
            }


            $content = $response;
            $content .= $req;
            $content .= "\n" . $ipn_track_id;

            $file = FCPATH . "bonfire" . DIRECTORY_SEPARATOR . "application" . DIRECTORY_SEPARATOR . "cache" . DIRECTORY_SEPARATOR . "ipn_notification.txt";
            $f = fopen($file, "a");
            fwrite($f, $content . "\n");
            fclose($f);
        } else if (strcmp($response, "INVALID") == 0) {
            redirect("/");
        }

        if ($response == "VERIFIED") {

            $content = $response;
            $content .= $req;

            $file = FCPATH . "bonfire" . DIRECTORY_SEPARATOR . "application" . DIRECTORY_SEPARATOR . "cache" . DIRECTORY_SEPARATOR . "ipn_notification.txt";
            $f = fopen($file, "a");
            fwrite($f, $content . "\n");
            fclose($f);
        }

        curl_close($ch);
    }

    /*
     * Profile of current logged in user
     */
    public function profile() {
        Assets::add_module_js("dentist_management", "dentist_management_front.js");

//        Assets::add_module_css("dentist_management", "dentist_management.css");

        if ($this->session->userdata("user_id")) {
            if ($this->session->userdata("role_name") != "Dentist") {
//                show_404();
//                Template::set_message("You don't have permission to access that page", "danger");
                Template::redirect("/");
            }
        }

        $dentist_id = $this->session->userdata("user_id");
        if ($dentist_id) {


            $this->load->model("dentist_management/user_management_model", "dumm");

            $dentist_info = $this->dumm->find_info_for_dent($dentist_id);
//            dump($dentist_info);
            Template::set("dentist_info", $dentist_info);

//get subscription plans...
            $this->load->model("plan/plan_model", "pm");
            Template::set("subscription_plans", $this->pm->order_by("price", "ASC")->find_all_by("status", 1));

            Template::set("enrollment_banner", TRUE);
            if (isset($dentist_info->sub_info) && !empty($dentist_info->sub_info) && isset($dentist_info->sub_info['start_date']) && isset($dentist_info->sub_info['end_date']) && isset($dentist_info->sub_info['no_appointment'])) {

                /*
                 * =======Start For dentist appointments=======
                 */
                $start_date = $dentist_info->sub_info['start_date'];
                $end_date = $dentist_info->sub_info['end_date'];
                $no_of_appointments = $dentist_info->sub_info['no_appointment'];
                $this->load->model('dentist_appointments/dentist_appointments_model');
                /*
                 * For Future Appointments
                 */
                //Current month's past appointments
                $curMonthPast = $this->dentist_appointments_model->get_appointments_for_dentist($dentist_id, array(
                    'type' => 'curMonthPast',
                    'value' => array(
                        $start_date,
                        $end_date
                    )
                        ), $no_of_appointments);
                $remainig_count = $curMonthPast ? ($no_of_appointments - count($curMonthPast)) : $no_of_appointments;
                //Future appointments
                $future = $this->dentist_appointments_model->get_appointments_for_dentist($dentist_id, array(
                    'type' => 'future',
                    'value' => array(
                        $dentist_info->sub_info['start_date'],
                        $dentist_info->sub_info['end_date']
                    )
                        ), $remainig_count, TRUE);
                //All future appointments
                $futureAll = $this->dentist_appointments_model->get_appointments_for_dentist($dentist_id, array(
                    'type' => 'future',
                    'value' => array(
                        $dentist_info->sub_info['start_date'],
                        $dentist_info->sub_info['end_date']
                    )
                ));
                $futureAllCount = $futureAll ? count($futureAll) : 0;
                $futureAllCountForUpgrade = $futureAllCount > $remainig_count ? $futureAllCount - $remainig_count : 0;
                Template::set('futureApps', $future);
                Template::set('futureAllCountForUpgrade', $futureAllCountForUpgrade);
                /*
                 * For Past Appointments
                 */
                $pastAll = $this->dentist_appointments_model->get_appointments_for_dentist($dentist_id, array('type' => 'past'), FALSE, TRUE);
                Template::set('pastApps', $pastAll);
                /*
                 * =======End For dentist appointments=======
                 */
            }

            /*
             * ==Start For General Statistics At profiles top bar==
             */
            Template::set('appointments_counts', $this->dentist_appointments_model->get_counts_of_appointments_for_profile());
            /*
             * ==End For General Statistics At profiles top bar==
             */

            Template::render();
        } else {
            Template::redirect("/");
        }
    }

    /*
     * Edit profile of current logged in user
     */
    public function edit_profile() {

        if ($this->session->userdata("user_id")) {
            if ($this->session->userdata("role_name") != "Dentist") {
                Template::redirect("/");
            }
        }
        Assets::add_module_js("dentist_management", "dentist_management_front.js");
        $user_id = $this->session->userdata("user_id");

        $this->lang->load('dentist_management');

        if (empty($user_id) || ($this->current_user == NULL)) {
            Template::redirect("/");
        }

        $this->load->model('user_management_model', null, true);
        $this->load->model('roles/role_model');
        $this->load->config('address');
        $this->load->helper('address');
        $this->load->helper('date');

        if ($user_id != $this->current_user->id) {
            Template::set_message(lang('Sorry_your_session_has_expired'), "info");
            Template::redirect("/");
        }


        $this->load->config('user_meta');
        $meta_fields = config_item('user_meta_fields');
        Template::set('meta_fields', $meta_fields);

        $user = $user_data = $this->user_management_model->find_info_for_dent($user_id);

        if ($this->input->post('submit')) {

            if ($this->save_user('update', $user_id, $meta_fields, $user->role_name)) {
                $this->session->userdata["full_name"] = $this->input->post("first_name") . " " . $this->input->post("last_name");
                $meta_data = array();
                foreach ($meta_fields as $field) {
                    if (!isset($field['admin_only']) || $field['admin_only'] === FALSE || (isset($field['admin_only']) && $field['admin_only'] === TRUE && isset($this->current_user) && $this->current_user->role_id == 1)) {
                        $meta_data[$field['name']] = $this->input->post($field['name']);
                    }
                }

                // now add the meta is there is meta data
                $this->user_model->save_meta_for($user_id, $meta_data);

                $user = $this->user_model->find_user_and_meta($user_id);
                $log_name = (isset($user->display_name) && !empty($user->display_name)) ? $user->display_name : ($this->settings_lib->item('auth.use_usernames') ? $user->username : $user->email);
                $this->activity_model->log_activity($this->current_user->id, lang('us_log_edit') . ': ' . $log_name, 'users');

                Template::set_message(lang('us_user_update_success'), 'success');
// if($this->input->post('password')){
//                  redirect('/');
//              }else{
//                  redirect('dentist_management/profile');
//              }
                redirect('dentist_management/profile');
// redirect back to the edit page to make sure that a users password change
// forces a login check
                Template::redirect($this->uri->uri_string());
            }
        }


        if (isset($user)) {
            Template::set('roles', $this->role_model->select('role_id, role_name, default')->where('deleted', 0)->find_all());
            Template::set('user', $user);
            Template::set('languages', unserialize($this->settings_lib->item('site.languages')));
        } else {
            Template::set_message(sprintf(lang('us_unauthorized'), $user->role_name), 'error');
            redirect(SITE_AREA . "/settings/{$this->module_config['module_name']}");
        }

        $settings = $this->settings_lib->find_all();
        if ($settings['auth.password_show_labels'] == 1) {
            Assets::add_module_js('users', 'password_strength.js');
            Assets::add_module_js('users', 'jquery.strength.js');
            Assets::add_js($this->load->view('users_js', array('settings' => $settings), true), 'inline');
        }

        Template::set('toolbar_title', lang('us_edit_user_front'));

//get subscription plans...
        $this->load->model("plan/plan_model", "pm");
        Template::set("subscription_plans", $this->pm->order_by("price", "ASC")->find_all_by("status", 1));
//get insurance details...
        $this->load->model("insurance/insurance_model", "im");
        Template::set("insurance_accepted", $this->im->find_all_by("status", 1));
//get dental specialities...
        $this->load->model("dental_speciality/dental_speciality_model", "dsm");
        Template::set("dental_specialities", $this->dsm->find_all_by("status", 1));
//get payment type accepting...
        $this->load->model("payment_type/payment_type_model", "ptm");
        Template::set("payment_accepting", $this->ptm->find_all_by("status", 1));
//get working days...
        $this->load->model("dentist_management/days_of_week_model", "dow");
        Template::set("working_days", $this->dow->find_all());

        Template::set("enrollment_banner", TRUE);
//        Template::set('module_config', $this->module_config);
        //add  
        $query = $this->db->get_where('user_dentist_images', array("user_id" => $this->session->userdata("user_id")));
        $get_images[] = $query->result();

        Template::set_view('dentist_management/edit_profile');
        Template::set('get_images', $get_images);

        Template::render();
    }

    private function save_user($type = 'insert', $id = 0, $meta_fields = array(), $cur_role_name = '') {

        $this->form_validation->set_rules('first_name', lang('dentist_management_first_name'), 'required|trim|xss_clean|max_length[255]');
        $this->form_validation->set_rules('last_name', lang('dentist_management_last_name'), 'required|trim|xss_clean|max_length[255]');
        $this->form_validation->set_rules('tel_no', lang('dentist_management_tel_no'), 'required|trim|xss_clean|max_length[15]');
        $this->form_validation->set_rules('education', lang('bf_common_education'), 'required|trim|xss_clean|max_length[255]');
        $this->form_validation->set_rules('speciality_id', lang('dentist_management_speciality'), 'required|xss_clean');
        $this->form_validation->set_rules('insurance_id', lang('dentist_management_Insurance'), 'required|xss_clean');
        $this->form_validation->set_rules('payment_acceptine_id', lang('dentist_management_payment_type_accepted'), 'required|xss_clean');
        $this->form_validation->set_rules('address', lang('bf_address'), 'required|xss_clean|max_length[255]');
        $this->form_validation->set_rules('zipcode', lang('bf_common_zip_code'), 'required|numeric|xss_clean|max_length[6]');
        $this->form_validation->set_rules('accepting_new_patient', lang('bf_common_accepting_ew_patients'), 'required|numeric|xss_clean');
        $this->form_validation->set_rules('do_not_include_dates', lang('dentist_management_do_not_include'), 'xss_clean');

        if ($_FILES["profile_pic"]['error'] != 4) {
            $this->form_validation->set_rules('profile_pic', 'Image', 'callback_req_image');
        }

        if ($type == 'insert') {
            $this->form_validation->set_rules('email', lang('bf_email'), 'required|trim|unique[users.email]|valid_email|max_length[120]|xss_clean');
            $this->form_validation->set_rules('password', lang('bf_password'), 'required|trim|strip_tags|min_length[8]|max_length[120]|valid_password|xss_clean');
            $this->form_validation->set_rules('pass_confirm', lang('bf_password_confirm'), 'required|trim|strip_tags|matches[password]|xss_clean');
        } else {
            $_POST['id'] = $id;
            $this->form_validation->set_rules('email', lang('bf_email'), 'required|trim|unique[users.email,users.id]|valid_email|max_length[120]|xss_clean');

            $this->form_validation->set_message('required', lang('dentist_management_error_message_required'));
            $this->form_validation->set_message('minlength', lang('dentist_management_error_message_min_length'));
            $this->form_validation->set_message('maxlength', lang('dentist_management_error_message_max_length'));
            $this->form_validation->set_message('matches', lang('dentist_management_error_message_matches'));

            $this->form_validation->set_rules('password', lang('bf_password'), 'trim|strip_tags|min_length[8]|max_length[120]|valid_password|matches[pass_confirm]|xss_clean');

            $this->form_validation->set_rules('pass_confirm', lang('bf_password_confirm'), 'trim|strip_tags|xss_clean');
            if ($this->input->post('password') || $this->input->post('pass_confirm')) {
                $this->form_validation->set_rules('old_password', lang('bf_oldpassword'), 'required|trim|strip_tags|min_length[8]|max_length[120]|valid_password|xss_clean|callback_old_password_check');
            }
        }

        /* $use_usernames = $this->settings_lib->item('auth.use_usernames');

          if ($use_usernames) {
          $extra_unique_rule = $type == 'update' ? ',users.id' : '';

          $this->form_validation->set_rules('username', lang('bf_username'), 'required|trim|strip_tags|max_length[30]|unique[users.username' . $extra_unique_rule . ']|xss_clean');
          } */

//        $this->form_validation->set_rules('display_name', lang('bf_display_name'), 'trim|strip_tags|max_length[255]|xss_clean');
//        $this->form_validation->set_rules('language', lang('bf_language'), 'required|trim|strip_tags|xss_clean');
//        $this->form_validation->set_rules('timezones', lang('bf_timezone'), 'required|trim|strip_tags|max_length[4]|xss_clean');

        if (has_permission('Bonfire.Roles.Manage') && has_permission('Permissions.' . $cur_role_name . '.Manage')) {
            $this->form_validation->set_rules('role_id', lang('us_role'), 'required|trim|strip_tags|max_length[2]|is_numeric|xss_clean');
        }

        if ($this->form_validation->run($this) === FALSE) {
            return FALSE;
        }

        /* Start:Add Multiple image upload:chiragPrajapati */
        $multipleimagedata = $this->MultipleImage();
        if (!empty($multipleimagedata['error'])) {
            $multipleimage_message = $multipleimagedata['error'];
            Template::set('multipleimage_message', $multipleimage_message);
            return FALSE;
        }
        //   var_dump($multipleimagedata);die;
        if (!empty($multipleimagedata) && count($multipleimagedata) > 0) {
            $this->load->model("dentist_management/user_dent_mulimages_model", "user_dentist_images");
            $created_on = date("Y-m-d H:i:s");
            $data = $this->user_dent_mulimages_model->insert_dent_picture($multipleimagedata, $this->session->userdata("user_id"), $created_on);
            //redirect('dentist_management/edit_profile');
            // var_dump($data_images); die;
        }
        /* End:Add Multiple image upload:chiragPrajapati */


        /* $meta_data = array();

          foreach ($meta_fields as $field) {
          if (!isset($field['admin_only']) || $field['admin_only'] === FALSE || (isset($field['admin_only']) && $field['admin_only'] === TRUE && isset($this->current_user) && $this->current_user->role_id == 1)) {
          $this->form_validation->set_rules($field['name'], $field['label'], $field['rules']);

          $meta_data[$field['name']] = $this->input->post($field['name']);
          }
          } */


// Compile our core user elements to save.
        $data = array(
            'email' => $this->input->post('email'),
            'role_id' => 7,
            'display_name' => $this->input->post('first_name') . " " . $this->input->post('last_name'),
//            'language' => $this->input->post('language'),
//            'timezone' => $this->input->post('timezones'),
        );

        if ($this->input->post('password')) {
            $data['password'] = $this->input->post('password');
        }

        if ($this->input->post('pass_confirm')) {
            $data['pass_confirm'] = $this->input->post('pass_confirm');
        }

        if ($this->input->post('role_id')) {
            $data['role_id'] = $this->input->post('role_id');
        }

        if ($this->input->post('restore')) {
            $data['deleted'] = 0;
        }

        if ($this->input->post('unban')) {
            $data['banned'] = 0;
        }

        if ($this->input->post('display_name')) {
            $data['display_name'] = $this->input->post('display_name');
        }

// Activation
        if ($this->input->post('activate')) {
            $data['active'] = 1;
        } else if ($this->input->post('deactivate')) {
            $data['active'] = 0;
        }


//get data for user_dent_info...
        $this->load->model("dentist_management/user_dent_info_model", "udim");
        $d_n_dates = $this->input->post("do_not_include_dates");

        $data2 = array(
            'first_name' => $this->input->post('first_name'),
            'last_name' => $this->input->post('last_name'),
            'tel_no' => $this->input->post('tel_no'),
            'education' => $this->input->post('education'),
            'hospital_affiliations' => $this->input->post('hospital_affiliations'),
            'my_statement' => $this->input->post('my_statement'),
            'address' => $this->input->post('address'),
            'zipcode' => $this->input->post('zipcode'),
            'accepting_new_patient' => $this->input->post('accepting_new_patient'),
            'do_not_include_dates' => $d_n_dates
        );

        /* For getting latLong by address -By Hitendra */
        $address = "";
        if ($this->input->post('address')) {
            $address .= $this->input->post('address');
        }
        if ($this->input->post('zipcode')) {
            $address .= "," . $this->input->post('zipcode');
        }

//get data for user_dent_insu
        $this->load->model("dentist_management/user_dent_insu_model", "udism");
        $insurance_ids = $this->input->post("insurance_id");
        $tmp = array();

//get data for user_dent_spcl...
        $this->load->model("dentist_management/user_dent_spcl_model", "udsm");
        $speciality_ids = $this->input->post("speciality_id");
        $tmp2 = array();

//get data for user_dent_spcl...
        $this->load->model("dentist_management/user_dent_paymode_model", "udpmm");
        $payment_accepting_ids = $this->input->post("payment_acceptine_id");
        $tmp4 = array();

//get subscription data for user_dent_subscription...
        $this->load->model("dentist_management/user_dent_subplan_model", "udspm");
        $sub_plan_id = $this->input->post('sub_plan');
//get plan info...
        $this->load->model("plan/plan_model", "pm");
        $plan_info = $this->pm->find_by("id", $sub_plan_id);
        $subscription = FALSE;

        if ($plan_info) {
            $subscription = TRUE;
            $start_date = date('Y-m-d H:i:s');
            $end_date = $this->pm->get_date_by_interval_of($plan_info->duration, 'Y-m-d H:i:s', $start_date);

            $tmp3 = array(
                'plan_id' => $sub_plan_id,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'pay_mode' => 'other',
                'status' => 1
            );
        }

//get data for working days of dentist....
        $this->load->model("dentist_management/user_working_day_model", "uwdm");
        $working_days = $this->input->post("working_days");
        $working_days = $this->uwdm->correct_index($working_days);

//        dump($working_days);
//        die;

        $start_time = $this->input->post("start_time");
        $end_time = $this->input->post("end_time");

//save image file...
        $delete_image = FALSE;
        $image_config = $this->module_config['dentist_image_file_config'];
        if ($_FILES["profile_pic"]['error'] != 4) {

            $upload_data = uploadFile('profile_pic', $image_config);
            if ($upload_data === FALSE) {
                return FALSE;
            }
            $data2['profile_pic'] = $upload_data['file_name'];
            // $delete_image = TRUE;
        }

        $this->load->model('search/search_model');
        $location = $this->search_model->getLatLngByAddress($address);
        if ($location) {
            $data2['lat'] = $location['lat'];
            $data2['lng'] = $location['lng'];
        } else {
            $data2['lat'] = 0.0;
            $data2['lng'] = 0.0;
        }
        /* End for getting latLong by address -By Hitendra */


        if ($type == 'insert') {
            $activation_method = $this->settings_lib->item('auth.user_activation_method');

// No activation method
            if ($activation_method == 0) {
// Activate the user automatically
                $data['active'] = 1;
            }

            //var_dump($data);die;
            $return = $user_id = $this->user_model->insert($data);


            if (is_numeric($user_id)) {

                //save data to user_dent_info...
                $data2['user_id'] = $user_id;
                $this->udim->insert($data2);

//save data to user_dent_insu...
                if (!empty($insurance_ids) && is_array($insurance_ids)) {
                    foreach ($insurance_ids as $insu_id) {
                        $tmp[] = array("user_id" => $user_id, "insurance_id" => $insu_id);
                    }
                    $this->udism->insert_batch($tmp);
                }


//save data to user_dent_spcl...
                if (!empty($speciality_ids) && is_array($speciality_ids)) {
                    foreach ($speciality_ids as $spcl_id) {
                        $tmp2[] = array("user_id" => $user_id, "spcl_id" => $spcl_id);
                    }
                    $this->udsm->insert_batch($tmp2);
                }

//save data to user_dent_paymode...
                if (!empty($payment_accepting_ids) && is_array($payment_accepting_ids)) {
                    foreach ($payment_accepting_ids as $pm_id) {
                        $tmp4[] = array("user_id" => $user_id, "payment_id" => $pm_id);
                    }
                    $this->udpmm->insert_batch($tmp4);
                }

//save subscription plan details...
                if ($subscription) {
                    $tmp3['user_id'] = $user_id;
//set plan_id and user_id to session...
                    $this->udspm->insert($tmp3);
                }

//save working days...
                $tmp_wd = $this->uwdm->combine_day_start_end_time_for_dent($working_days, $start_time, $end_time, $user_id);
                if ($tmp_wd) {
                    $this->uwdm->insert_batch($tmp_wd);
                }
                /*
                 * Generating Meta Information
                 */
                $this->load->model('seo_dentist/seo_dentist_model', null, true);
                $this->seo_dentist_model->generateMetas($user_id);
                //----------------------------
            }
        } else { // Update
            $return = $this->user_model->update($id, $data);

//save data to user_dent_info...
            $data2['user_id'] = $id;
            $this->udim->update_where("user_id", $id, $data2);

//save data to user_dent_insu...
            if (!empty($insurance_ids) && is_array($insurance_ids)) {
//delete from user_dent_insu...
                $this->udism->delete_where(array("user_id" => $id));
                foreach ($insurance_ids as $insu_id) {
                    $tmp[] = array("user_id" => $id, "insurance_id" => $insu_id);
                }
                $this->udism->insert_batch($tmp);
            }


//save data to user_dent_spcl...
            if (!empty($speciality_ids) && is_array($speciality_ids)) {
//delete from user_dent_spcl...
                $this->udsm->delete_where(array("user_id" => $id));

                foreach ($speciality_ids as $spcl_id) {
                    $tmp2[] = array("user_id" => $id, "spcl_id" => $spcl_id);
                }
                $this->udsm->insert_batch($tmp2);
            }

//save data to user_dent_paymode...
            if (!empty($payment_accepting_ids) && is_array($payment_accepting_ids)) {
//delete from user_dent_spcl...
                $this->udpmm->delete_where(array("user_id" => $id));

                foreach ($payment_accepting_ids as $pm_id) {
                    $tmp4[] = array("user_id" => $id, "payment_id" => $pm_id);
                }
                $this->udpmm->insert_batch($tmp4);
            }

//save subscription plan details...
            if ($subscription) {

//change status...
                $tmp3['user_id'] = $id;
//                $tmp3['status'] = 1;
                $this->udspm->update_where("user_id", $id, $tmp3);

//now get that record and save it in user_history table...
                $history_table = $this->db->dbprefix("user_dent_sub_history");
                $subscription_table = $this->db->dbprefix("user_dent_subscription");

                $sql = "INSERT INTO {$history_table} "
                        . "SELECT * FROM {$subscription_table} AS uds "
                        . "WHERE uds.user_id = {$id}";

                $this->db->query($sql);

//                echo $this->db->last_query();die;
            }

//save working days...
            $tmp_wd = $this->uwdm->combine_day_start_end_time_for_dent($working_days, $start_time, $end_time, $id);
            $this->uwdm->delete_where(array("user_id" => $id));
            if ($tmp_wd) {
                $this->uwdm->insert_batch($tmp_wd);
            }

//delete old dentist file from filesystem...
            $image_to_delete = $this->input->post("image_to_delete");
            if (!empty($image_to_delete) && $delete_image) {
                delete_file($this->module_config['dentist_image_path'] . DIRECTORY_SEPARATOR . $image_to_delete);
            }

            if ($return && $this->input->post('password')) {
                /* Start for- Stay user logged in */
                $user = $this->user_model->find_by($this->settings_lib->item('auth.login_type'), $this->session->userdata('identity'));
                $this->session->set_userdata('user_token', do_hash($this->session->userdata('user_id') . $user->password_hash));
                /* End for- Stay user logged in */
            }

            /*
             * Generating Meta Information
             */
            $this->load->model('seo_dentist/seo_dentist_model', null, true);
            $this->seo_dentist_model->generateMetas($id);
            //----------------------------
        }

// Any modules needing to save data?
        Events::trigger('save_user', $this->input->post());

        return $return;
    }

    public function req_image($str) {

        if ($this->input->post("profile_pic")) {
            if (is_array($str)) {
                $image_config = $this->module_config['dentist_image_file_config'];

                $allowed_types = array("image/png", "image/jpg", "image/jpeg", "image/gif");
                $message = check_file($str, $allowed_types, $image_config['max_size']);
                if ($message === TRUE) {
                    return TRUE;
                } else {
                    $this->form_validation->set_message('req_image', $message);
                    return FALSE;
                }
            }
        }
        return FALSE;
    }

    public function delete() {
        if ($this->input->is_ajax_request()) {

//get data for user_dent_info...
            $this->load->model("dentist_management/user_dent_info_model", "udim");

            $id = $this->input->post("id");
            $data = array();
            $path = "";


//find category by id...
            $dentist = $this->udim->find_by("id", $id);
//            dump($dentist);
            $data["profile_pic"] = "";
            $path = $this->module_config['dentist_image_path'] . DIRECTORY_SEPARATOR . $dentist->profile_pic;
//            echo $path;
            if ($this->udim->set_null_for($id, $data)) {
                if (!empty($path)) {
                    if (delete_file($path)) {
                        $msg = lang('dentist_management_image_successfully_deleted');
                        $this->output->set_output(json_encode(array('status' => 'success', 'msg' => $msg, 'id' => $id)));
                    }
                }
            }
        } else {
            show_404();
        }
    }

    public function choose_plan() {

        $logged_in = $this->session->userdata("user_id");
        if ($logged_in !== FALSE) {
            redirect("/");
        }

        $activation_hash = $this->uri->segment(3);

        if ($activation_hash) {
//Find activation hash in database...

            $this->load->model("dentist_management/user_dent_activation_model", "udam");
            $this->load->model("promocodes/promocodes_model", "pcm");

            $where = array(
                "activation_hash" => $activation_hash,
                "verified" => 0
            );
            $user_hash = $this->udam->get_results_where($where);


            if ($user_hash) {
                $date = date("Y-m-d H:i:s", strtotime($user_hash->start_time));
                $new_date = strtotime(date("Y-m-d H:i:s", strtotime("+24 hour", strtotime($date))));
                $current_date = strtotime(date("Y-m-d H:i:s"));
                if ($new_date < $current_date) {
                    redirect("/");
                }


//Check for user payment status in users table...
                $this->load->model("users/user_model", "um");
                $user = $this->um->find_by("id", $user_hash->dentist_id);

                if ($user != FALSE && $user->payment_status == 0 && $user->active == 0) {

//Render the plan form...
                    if ($this->input->post()) {

                        $result = $this->submit_plan($activation_hash);

//                        dump($result);

                        switch ($result) {
                            case self::PLAN_NOT_FOUND :
                                $msg = lang('dentist_management_error_plan_not_found');
                                $this->session->set_userdata("gen_msg", array("type" => "danger", "msg" => $msg));
                                redirect(base_url("dentist_management/choose_plan/{$activation_hash}"));
                                break;

                            case self::PROMO_CODE_NOT_FOUND :
                                $msg = lang('dentist_management_error_promo_code_not_found');
                                $this->session->set_userdata("gen_msg", array("type" => "danger", "msg" => $msg));
                                redirect(base_url("dentist_management/choose_plan/{$activation_hash}"));
                                break;

                            case self::PROMO_CODE_ALREADY_USED :
                                $msg = lang('dentist_management_error_promo_code_already_used');
                                $this->session->set_userdata("gen_msg", array("type" => "danger", "msg" => $msg));
                                redirect(base_url("dentist_management/choose_plan/{$activation_hash}"));
                                break;

                            case self::USER_HASH_NOT_VALID :
                                $msg = lang('dentist_management_error_user_hash_not_valid');
                                $this->session->set_userdata("gen_msg", array("type" => "danger", "msg" => $msg));
                                redirect(base_url("dentist_management/choose_plan/{$activation_hash}"));
                                break;

                            case self::OPERATION_FAILED :
                                $msg = lang('dentist_management_error_operation_error');
                                $this->session->set_userdata("gen_msg", array("type" => "danger", "msg" => $msg));
                                redirect(base_url("dentist_management/choose_plan/{$activation_hash}"));
                                break;

                            case self::TRIAL_PLAN_SELECTED :

                                $form_hide = lang('dentist_management_account_reg_success');
                                $this->session->set_userdata("form_hide", array("type" => "success", "form_hide" => $form_hide));
                                redirect(base_url("enroll"));
                                break;

                            case self::SUCCESSFULL :

                                if ($this->input->post("payment_type") == "card") {

                                    $r = $this->direct_payment();

                                    if ($r === self::CREDIT_CARD_INFO_NOT_VALID) {
                                        $msg = lang('dentist_management_error_credit_card_invalid');
                                        $this->session->set_userdata("gen_msg", array("type" => "danger", "msg" => $msg));
                                        redirect(base_url("dentist_management/choose_plan/{$activation_hash}"));
                                    } else if ($r === TRUE) {
                                        $msg = lang('dentist_management_account_reg_success');
                                        $form_hide = lang('dentist_management_account_reg_success');
                                        $this->session->set_userdata("form_hide", array("type" => "success", "form_hide" => $form_hide));
                                        $this->session->set_userdata("gen_msg", array("type" => "success", "msg" => $msg));
                                    }
                                } else if ($this->input->post("payment_type") == "paypal") {

                                    $this->session->set_userdata("rurl", base_url("dentist_management/enrollment"));
                                    $this->session->set_userdata("furl", base_url("dentist_management/choose_plan/{$activation_hash}"));
                                    $this->session->set_userdata("success_message", lang("dentist_management_account_reg_success"));

                                    $form_hide = lang('dentist_management_account_reg_success');
                                    $this->session->set_userdata("form_hide", array("type" => "success", "form_hide" => $form_hide));


                                    $r = $this->payment();
                                    if ($r == self::TOKEN_NOT_ISSUED) {
                                        $msg = lang('dentist_management_error_token_not_issued');
                                        $this->session->set_userdata("gen_msg", array("type" => "danger", "msg" => $msg));
                                    } else if ($r == self::PLAN_NOT_FOUND) {
                                        $msg = lang('dentist_management_error_plan_not_found');
                                        $this->session->set_userdata("gen_msg", array("type" => "danger", "msg" => $msg));
                                    }
                                }

                                redirect(base_url("dentist_management/enrollment"));

                                break;
                        }

//                        die;
                    }

//Do setup...
                    Assets::add_module_js("dentist_management", "dentist_management_front.js");
                    Template::set("enrollment_banner", TRUE);

//Get subscription plans...
                    $this->load->model("plan/plan_model", "pm");
                    Template::set("subscription_plans", $this->pm->order_by("price", "ASC")->find_all_by("status", 1));
                    Template::set("promocodes", $this->pcm->find_all_by("status", 1));
                    Template::set("user_id", $user_hash->dentist_id);
                    Template::render();
                } else {
                    show_404();
                }
            } else {
                redirect("/");
            }
        } else {
            show_404();
        }
    }

    private function submit_plan($activation_hash = "") {

//Initialization...
        $this->load->helper("date");
        $this->load->model("plan/plan_model", "pm");
        $this->load->model("promocodes/promocodes_model", "pcm");
        $this->load->model("promocodes/used_promocodes_model", "upcm");
        $this->load->model("dentist_management/user_dent_activation_model", "udam");
        $this->load->model("dentist_management/user_dent_subplan_model", "udspm");

        $this->form_validation->set_rules('sub_plan', 'sub_plan', 'required|trim|xss_clean');
        $this->form_validation->set_rules('terms_condition', lang('dentist_management_terms_and_condition'), 'required|xss_clean');
        $this->form_validation->set_rules('user_id', lang('dentist_management_user_id'), 'required|xss_clean|numeric');
        $this->form_validation->set_rules('promo_code', lang('dentist_management_promo_code'), 'xss_clean');

//Get plan info...
        $sub_plan_id = $this->input->post('sub_plan');
        $plan_info = $this->pm->find_by("id", $sub_plan_id);

        if ($plan_info == FALSE) {
            return self::PLAN_NOT_FOUND;
        }

        if (!($plan_info->price == "0.00")) {
//User has not selected trial plan so need of paypal information...
            $this->form_validation->set_rules('payment_type', 'payment_type', 'required|trim|xss_clean');
            if ($this->input->post("payment_type") == "card") {
//User has selected credit card so needed credit card info...
                $this->form_validation->set_rules('card_type', lang('dentist_management_card_type'), 'required|xss_clean');
                $this->form_validation->set_rules('card_owner', lang('dentist_management_card_owner'), 'required|xss_clean');
                $this->form_validation->set_rules('card_number', lang('dentist_management_card_no'), 'required|xss_clean');
                $this->form_validation->set_rules('cvv', lang('dentist_management_cvv'), 'required|xss_clean');
                $this->form_validation->set_rules('date', lang('dentist_management_date'), 'required|xss_clean|callback_checkDate');
                $this->form_validation->set_rules('year', lang('dentist_management_year'), 'required|xss_clean|callback_checkYear');
            }
        }

        if ($this->form_validation->run($this) === FALSE) {
            return FALSE;
        }

//Get user by activation hash...
        $where = array(
            "activation_hash" => $activation_hash,
            "verified" => 0
        );
        $user_hash = $this->udam->get_results_where($where);
        if ($user_hash == FALSE) {
            return self::USER_HASH_NOT_VALID;
        }
        $user_id = $user_hash->dentist_id;

//Get promo code info...
        $promo_code = $this->input->post('promo_code');
        if (!empty($promo_code)) {
            $promo_code_info = $this->pcm->find_by(array("title" => $promo_code, "status" => 1));
            if ($promo_code_info == FALSE) {
                return self::PROMO_CODE_NOT_FOUND;
            }

            if (in_array($promo_code_info->id, $this->upcm->find_used_codes_for($user_id))) {
                return self::PROMO_CODE_ALREADY_USED;
            }

            $this->session->set_userdata("promo_code_id", $promo_code_info->id);
        }


//Save subscription plan details...



        if ($plan_info->price == "0.00") {
            $sub_status = 0;
            $start_date = '0000-00-00 00:00:00';
            $end_date = '0000-00-00 00:00:00';
        } else {
            $sub_status = 1;
            $start_date = date('Y-m-d H:i:s', now());
            $end_date = $this->pm->get_date_by_interval_of($plan_info->duration, 'Y-m-d H:i:s', $start_date);
        }

        $tmp3 = array(
            'user_id' => $user_id,
            'plan_id' => $sub_plan_id,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'pay_mode' => $this->input->post('payment_type'),
            'status' => $sub_status
        );

//Delete subscription entry if found for user...
        $this->udspm->delete_where(array('user_id' => $user_id));

//Insert plan to subscription table...
        $sub_id = $this->udspm->insert($tmp3);

        if (!is_numeric($sub_id)) {
            return self::OPERATION_FAILED;
        }


        if ($plan_info->price == "0.00") {

//Delete activation hash entry for this user...                    
            $this->udam->delete_where(array("activation_hash" => $activation_hash));

//Update payment status in users table...                    
            $this->load->model("users/user_model", "um");
            $this->um->update($user_hash->dentist_id, array("payment_status" => 1, "active" => 0));

//Insert subscription history into table..
            $this->load->model("dentist_management/user_dent_sub_history_model", "udshm");
            $history_table = $this->db->dbprefix("user_dent_sub_history");
            $subscription_table = $this->db->dbprefix("user_dent_subscription");

            $sql = "INSERT INTO {$history_table} "
                    . "SELECT * FROM {$subscription_table} AS uds "
                    . "WHERE uds.id = {$sub_id}";
            $this->db->query($sql);
            //    var_dump($this->db->last_query());die;
//Send mail...
//Load necessary models and libraries...
            $this->load->library('emailer/emailer');
            $this->load->model("email_template/email_template_model", "em");

//Find email by user_id...
            $this->db->select("*");
            $this->db->from("users");
            $this->db->join("user_dent_info", "user_dent_info.user_id = users.id", "left");
            $this->db->where("users.id", $user_id);
            $query = $this->db->get();

            $user = $query->row();
            $date = new DateTime("now");

//Find email template for activation mail...
            $template = $this->em->find_by(array("label" => "welcome_to_dental_network_dentist_aprroval", "status" => 1));

            if ($template) {
//Send activation mail...
                $array_to_Replace = array(
                    "[USER_FNAME]" => ucfirst($user->first_name),
                    "[USER_LNAME]" => ucfirst($user->last_name),
                    "[USER_FULLNAME]" => ucwords($user->first_name . " " . $user->last_name),
                    "[USER_PHONE_NO]" => $user->tel_no,
                    "[USER_EMAIL]" => $user->email,
                    "[SITE_NAME]" => $this->settings_lib->item('site.title'),
                    "[SITE_URL]" => base_url(),
                    "[SITE_MAIL]" => $this->settings_lib->item('site.system_email'),
                    "[DATE]" => $date->format("m-d-Y"),
                    "[TIME]" => $date->format("h:i A")
                );

                $subject = $template->title;
                $message = html_entity_decode($this->em->replacer($template->content, $array_to_Replace));

                $data = array(
                    'to' => $user->email,
                    'subject' => $subject,
                    'message' => $message
                );
                $this->emailer->send($data);
            }

            // $msg = strtr(lang('dentist_management_success_trial_plan_selected'), array("$1" => 1, "$2" => $plan_info->duration));
            $msg = lang('dentist_management_success_trial_plan');

            $this->session->set_userdata("gen_msg", array("type" => "success", "msg" => $msg));

            return self::TRIAL_PLAN_SELECTED;
        }

//setting data to session...
        $this->session->set_userdata("registering_user_id", $user_id);
        $this->session->set_userdata("registering_user_plan_id", $sub_plan_id);
        $this->session->set_userdata("proceed_payment", TRUE);
        $this->session->set_userdata("registering_user_sub_table_id", $sub_id);
        $this->session->set_userdata("type", "register");

        return self::SUCCESSFULL;
    }

    public function public_profile($id, $name = '') {
        Assets::add_module_js("dentist_management", "dentist_management_front.js");
        if ($id && is_numeric($id)) {
            $this->load->model("dentist_management/user_management_model");


            if ($this->session->userdata("user_id")) {
                if ($this->session->userdata("role_name") == "Patient") {
                    Template::set("show_review_form", TRUE);
                }
            }


            $dentist_info = $this->user_management_model->find_info_for_dent($id, TRUE);

            if (empty($dentist_info)) {
                Template::redirect();
            }

            $name = mb_convert_case(urldecode($name), MB_CASE_LOWER, 'UTF-8');
            $name_compare = mb_convert_case($dentist_info->first_name . ' ' . $dentist_info->last_name, MB_CASE_LOWER, 'UTF-8');
            if ($name != $name_compare) {
                //Template::redirect();
            }

            /* Original: if ($this->session->userdata("user_id") && $this->session->userdata("role_name") == "Patient") {
              $patient_id = $this->session->userdata("user_id");
              $this->user_management_model->add_dent_profileview_history($id, $patient_id);
              } */


            if ($this->session->userdata("role_name") !== "Dentist") {


                //      echo "yes";
                $remoteaddress = $_SERVER['REMOTE_ADDR'];
                $this->user_management_model->add_dent_profileview_history($id, $remoteaddress);
            }
            $timeArray = array();

            if (isset($dentist_info->working_days) && !empty($dentist_info->working_days)) {
                $t = array();
                foreach ($dentist_info->working_days as $value) {
                    $value = (array) $value;
                    $t[$value['day_id']] = $value;
                    $timeArray[$value['start_time']] = date('G', strtotime($value['start_time']));
                    $timeArray[$value['end_time']] = date('G', strtotime($value['end_time']));
                }
                $dentist_info->working_days = $t;
            }
            //chirag
            $this->load->model("dentist_management/user_dent_mulimages_model");
            $dentist_images = $this->user_dent_mulimages_model->find_image_for_dent($id);
            // var_dump($dentist_images);die;
            Template::set_view('dentist_management/public_profile');
            Template::set('dentist_images', $dentist_images);


            Template::set("dentist_info", $dentist_info);
            Template::set("dentist_id", $id);

            if (!empty($timeArray)) {
                $timeArray = array_unique($timeArray);
                Template::set('minTime', min($timeArray));
                Template::set('maxTime', max($timeArray));
            }

            //Check if user is logged in or not
            $is_patient_logged_in = FALSE;
            if ($this->session->userdata("user_id") && $this->session->userdata("role_name") == "Patient") {
                $is_patient_logged_in = TRUE;
            }
            Template::set('is_patient_logged_in', $is_patient_logged_in);

            $this->load->model("dentist_management/days_of_week_model", "dow");
            Template::set("working_days", $this->dow->find_all());

            Template::set("enrollment_banner", TRUE);

            /*
             * Start - for Meta Information
             */
            Template::set('meta_title', $dentist_info->metaTitle);
            Template::set('meta_keyword', $dentist_info->metaKeyword);
            Template::set('meta_description', $dentist_info->metaDescription);
            //-------------

            Template::render();
        } else {
            Template::redirect("/");
        }
    }

    /*
     * Get Appointments by week(Booked, Non booked)
     */

    public function get_next_prev_week($date, $id) {
        if ($this->input->is_ajax_request()) {
            $date = urldecode($date);
            $this->load->model("dentist_management/user_working_day_model");
            $dentWorkingDays = $this->user_working_day_model->find_working_day_for_dent($id);

            if (!empty($dentWorkingDays)) {
                $t = array();
                $timeArray = array();
                foreach ($dentWorkingDays as $value) {
                    $value = (array) $value;
                    $t[$value['day_id']] = $value;
                    $timeArray[$value['start_time']] = date('G', strtotime($value['start_time']));
                    $timeArray[$value['end_time']] = date('G', strtotime($value['end_time']));
                }
                Template::set('dentWorkingDays', $t);
                if (!empty($timeArray)) {
                    $timeArray = array_unique($timeArray);
                    Template::set('minTime', min($timeArray));
                    Template::set('maxTime', max($timeArray));
                }
            }

            //Check if user is logged in or not
            $is_patient_logged_in = FALSE;
            $is_dentist_logged_in = FALSE;
            $appointment_datetimes_arr = array();
            if ($this->session->userdata("user_id") && $this->session->userdata("role_name") == "Patient") {
                $is_patient_logged_in = TRUE;
                $this->load->model('dentist_appointments/dentist_appointments_model');
                $result = $this->dentist_appointments_model->get_appointments_for_patient($this->session->userdata("user_id"), $id);
                if ($result) {
                    foreach ($result as $r) {
                        $appointment_datetimes_arr[$r->appointment_date] = $r->appointment_date;
                    }
                }

//                var_dump($appointment_datetimes_arr);
            } elseif ($this->session->userdata("user_id") && $this->session->userdata("role_name") == "Dentist") {
                $is_dentist_logged_in = TRUE;
            }

            //Start For excluding dates
            $this->load->model("dentist_management/user_dent_info_model");
            $excludeDates = $this->user_dent_info_model->find_by('user_id', $id);
            if ($excludeDates) {
                Template::set('excludeThisDates', !empty($excludeDates->do_not_include_dates) ? explode(',', $excludeDates->do_not_include_dates) : array());
            } else {
                Template::set('excludeThisDates', array());
            }
            //End For excluding dates

            Template::set('is_patient_logged_in', $is_patient_logged_in);
            Template::set('is_dentist_logged_in', $is_dentist_logged_in);
            Template::set('patients_appointment_datetime', $appointment_datetimes_arr);

            Template::set('dateToFrom', $date);
            Template::set('dentId', $id);

            /* For returning */
            $date_from = date('jS ', strtotime($date)) . lang('bf_' . date('F', strtotime($date))) . date(', Y', strtotime($date));
            $to_date_ts = strtotime($date . ' + 6 days');
            $date_to = date('jS ', $to_date_ts) . lang('bf_' . date('F', $to_date_ts)) . date(', Y', $to_date_ts);

            $prep_date = $date_from . ' - ' . $date_to;
            /* For returning */

            $calander = $this->load->view('get_next_prev_week', Template::getData(), TRUE);

            $data = array(
                'prep_date' => $prep_date,
                'calender' => $calander
            );
            echo json_encode($data);
            exit();
        }
    }

    public function review_dentist() {

//        if ($this->input->is_ajax_request()) {

        $patient_id = $this->session->userdata("user_id");
        $dentist_id = $this->input->post("dentist_id");
        $role = $this->session->userdata("role_name");

        if (empty($patient_id) || ($role != "Patient")) {
            $msg = lang('dentist_management_you_do_not_have_permission');
            //$this->session->set_userdata("review_msg", array("msg" => $msg, "type" => "danger"));
            redirect("dentist_management/public_profile/$dentist_id");
        }

        $review = $this->input->post("review");
        $rating = $this->input->post("rating");
        if (empty($dentist_id) || empty($review) || empty($rating)) {
            $msg = lang('dentist_management_review_and_rating_fields');
            //$this->session->set_userdata("review_msg", array("msg" => $msg, "type" => "danger"));
            redirect("dentist_management/public_profile/$dentist_id");
        }

        //now insert review ratings to table...
        $this->load->model("review_and_rating/review_and_rating_model", "rrm");
        $review_data = array(
            "dentist_id" => $dentist_id,
            "patient_id" => $patient_id,
            "review" => $review,
            "rating" => $rating,
            "status" => 0
        );

        //check whether patient has given review before...
        $check_data = $review_data;
        unset($check_data['status']);
        unset($check_data['review']);
        unset($check_data['rating']);

        $user_review = $this->rrm->find_by($check_data);

        if ($user_review) {
//we found review update this review...
            $flag = $this->rrm->update_where("patient_id", $user_review->patient_id, $review_data);
            $id = ($flag == TRUE) ? $user_review->id : FALSE;
        } else {
//no review found please insert for this user...
            $id = $this->rrm->insert($review_data);
        }

        $msg = "";
        $type = "";

        if ($id && is_numeric($id)) {
            $msg = lang('dentist_management_review_successfully_posted_and_waiting');
            $type = "success";
        } else {
            $msg = lang('dentist_management_review_not_posted');
            $type = "danger";
        }

        $this->session->set_userdata("review_msg", array("msg" => $msg, "type" => $type));

        echo json_encode(array("msg" => $msg, "type" => $type));
        //redirect("dentist_management/public_profile/$dentist_id");
//        } else {
//            show_404();
//        }
    }

    public function old_password_check($password) {
        if (do_hash($this->current_user->salt . $password) != $this->current_user->password_hash) {
            $this->form_validation->set_message('old_password_check', lang('dentist_management_you_entered_wrong_password'));
            return FALSE;
        } else {
            return TRUE;
        }
    }

    private function send_activation_email($user_id) {

//Load necessary models and libraries...
        $this->load->model("users/user_model");
        $this->load->library('emailer/emailer');
        $this->load->model("email_template/email_template_model", "em");

//Find email by user_id...
        $this->db->select("*");
        $this->db->from("users");
        $this->db->join("user_dent_info", "user_dent_info.user_id = users.id", "left");
        $this->db->where("users.id", $user_id);
        $query = $this->db->get();
        $user = $query->row();

        $date = new DateTime("now");


//Find email template for activation mail...
        $template = $this->em->find_by(array("label" => "welcome_to_dental_network_dentist_activation", "status" => 1));

        if ($user && $template) {
            //Send activation mail...

            $activation_hash = $this->generate_activation_link($user);
            $activation_link = "<a href='" . base_url("dentist_management/choose_plan/{$activation_hash}") . "'>" . lang('dentist_management_activation_mail_has_been_sent') . "</a>";

            $array_to_Replace = array(
                "[USER_FNAME]" => ucfirst($user->first_name),
                "[USER_LNAME]" => ucfirst($user->last_name),
                "[USER_FULLNAME]" => ucwords($user->first_name . " " . $user->last_name),
                "[ACTIVATION_LINK]" => $activation_link,
                "[USER_PHONE_NO]" => $user->tel_no,
                "[USER_EMAIL]" => $user->email,
                "[SITE_NAME]" => $this->settings_lib->item('site.title'),
                "[SITE_URL]" => base_url(),
                "[SITE_MAIL]" => $this->settings_lib->item('site.system_email'),
                "[DATE]" => $date->format("m-d-Y"),
                "[TIME]" => $date->format("h:i A")
            );

            $subject = $template->title;
            $message = html_entity_decode($this->em->replacer($template->content, $array_to_Replace));

            $data = array(
                'to' => $user->email,
                'subject' => $subject,
                'message' => $message
            );

            if ($this->emailer->send($data)) {
                $message = lang('dentist_management_activation_mail_has_been_sent');

//insert activation info to database...
                $this->load->model('dentist_management/user_dent_activation_model', "udam");
                $this->load->helper("date");

//delete any previously settled hash...
                $this->udam->delete_where(array("dentist_id" => $user_id));
                $act_info = array(
                    "dentist_id" => $user_id,
                    "activation_hash" => $activation_hash,
                    "verified" => 0,
                    "start_time" => date("Y-m-d H:i:s", now()),
                    "period" => "24"
                );
                //  var_dump($act_info);die;
                $this->udam->insert($act_info);
            } else {
                $message = "Mail not sent";
            }
        }
    }

    private function generate_activation_link($user) {
        if ($user) {
            $str = "" . $user->email . $user->password_hash . $user->salt;
            $str .= time();
            return md5(md5($str));
        }

        return FALSE;
    }

    public function store_appointment_data_to_session($datetime, $dent_id) {
        if ($this->input->is_ajax_request()) {
            $this->session->set_userdata('app_datetime', urldecode($datetime));
            $this->session->set_userdata('app_dent_id', $dent_id);
            $this->session->set_userdata('display_diff_form', TRUE);
        }
    }

    public function upgrade() {
//Initialization...
        $this->load->model("promocodes/promocodes_model", "pcm");
        $this->load->model("dentist_management/user_dent_info_model", "udim");

        Assets::add_module_js("dentist_management", "dentist_management_front.js");
        $user_id = $this->session->userdata("user_id");
        $role_name = $this->session->userdata("role_name");
        $rurl = base_url("dentist_management/upgrade");

//Check for authentication...
        if (empty($user_id) || ($role_name != "Dentist")) {
            Template::redirect("/");
        }

        if ($this->input->post()) {
            $result = $this->upgrade_plan();
//            dump($result); die;

            $sub_plan_id = $this->input->post("sub_plan");
            $plan_info = $this->pm->find_by("id", $sub_plan_id);

            switch ($result) {
                case self::PLAN_NOT_FOUND :
                    $msg = lang('dentist_management_error_plan_not_found');
                    $this->session->set_userdata("gen_msg", array("type" => "danger", "msg" => $msg));
                    redirect($rurl);
                    break;

                case self::PROMO_CODE_NOT_FOUND :
                    $msg = lang('dentist_management_error_promo_code_not_found');
                    $this->session->set_userdata("gen_msg", array("type" => "danger", "msg" => $msg));
                    redirect($rurl);
                    break;

                case self::PROMO_CODE_ALREADY_USED :
                    $msg = lang('dentist_management_error_promo_code_already_used');
                    $this->session->set_userdata("gen_msg", array("type" => "danger", "msg" => $msg));
                    redirect($rurl);
                    break;

                case self::NO_PLAN_SUBSCRIBED :
                    $msg = lang('dentist_management_error_no_plan_subscribed');
                    $this->session->set_userdata("gen_msg", array("type" => "danger", "msg" => $msg));
                    redirect($rurl);
                    break;

                case self::OPERATION_FAILED :
                    $msg = lang('dentist_management_error_operation_error');
                    $this->session->set_userdata("gen_msg", array("type" => "danger", "msg" => $msg));
                    redirect($rurl);
                    break;

                case self::PLAN_CANCELLATION_FAILED :
                    $msg = lang('dentist_management_error_plan_cancellation_failed');
                    $this->session->set_userdata("gen_msg", array("type" => "danger", "msg" => $msg));
                    redirect($rurl);
                    break;

                case self::SUCCESSFULL :
                    if ($this->input->post("payment_type") == "card") {

                        $r = $this->direct_payment();
                        if ($r === self::CREDIT_CARD_INFO_NOT_VALID) {
                            $msg = lang('dentist_management_error_credit_card_invalid');
                            $this->session->set_userdata("gen_msg", array("type" => "danger", "msg" => $msg));
                        } else if ($r === TRUE) {
                            $msg = strtr(lang("dentist_management_success_plan_upgradation"), array("$1" => ucfirst($plan_info->name)));
                            $this->session->set_userdata("gen_msg", array("type" => "success", "msg" => $msg));
                            $this->udim->toggle_downgrade_status($user_id);
                        }
                    } else if ($this->input->post("payment_type") == "paypal") {

                        $this->session->set_userdata("rurl", $rurl);
                        $this->session->set_userdata("furl", $rurl);
                        $msg = strtr(lang("dentist_management_success_plan_upgradation"), array("$1" => ucfirst($plan_info->name)));
                        $this->session->set_userdata("success_message", $msg);

                        $r = $this->payment();
                        if ($r == self::TOKEN_NOT_ISSUED) {
                            $msg = lang('dentist_management_error_token_not_issued');
                            $this->session->set_userdata("gen_msg", array("type" => "danger", "msg" => $msg));
                        } else if ($r == self::PLAN_NOT_FOUND) {
                            $msg = lang('dentist_management_error_plan_not_found');
                            $this->session->set_userdata("gen_msg", array("type" => "danger", "msg" => $msg));
                        }
                    }

                    redirect($rurl);

                    break;
            }
        }


//Get current subscription plan...
        $sub_table = $this->db->dbprefix("user_dent_subscription");
        $plan_table = $this->db->dbprefix("plan");
        $price = 0;
        $flag = FALSE;
        $user = $this->udim->find_by("user_id", $user_id);
        //dump($user);

        if ($user && ($user->can_degrade_plan != 1)) {
            $flag = TRUE;
            $this->db->select("price");
            $this->db->from($sub_table);
            $this->db->join($plan_table, "{$plan_table}.id = {$sub_table}.plan_id", "left");
            $this->db->where("user_id", $user_id);
            $row = $this->db->get()->row();
            if ($row) {
                $price = $row->price;
            }
        }


        $this->load->model("plan/plan_model", "pm");
        Template::set("subscription_plans", $this->pm->where("price > {$price}")->order_by("price", "ASC")->find_all_by("status", 1));
        Template::set("promocodes", $this->pcm->find_all_by("status", 1));
        Template::set("down_grade_notice", $flag);
        Template::set("enrollment_banner", TRUE);

        Template::render();
    }

    private function upgrade_plan() {

//Initialization...
        $this->load->helper("date");
        $this->load->model("promocodes/promocodes_model", "pcm");
        $this->load->model("promocodes/used_promocodes_model", "upcm");
        $this->load->model("plan/plan_model", "pm");
        $this->load->model("dentist_management/user_dent_subplan_model", "udspm");

        $this->form_validation->set_rules('sub_plan', lang('dentist_management_subscription_plan'), 'required|trim|xss_clean');
        $this->form_validation->set_rules('terms_condition', lang('dentist_management_terms_and_condition'), 'required|xss_clean');
        $this->form_validation->set_rules('promo_code', lang('dentist_management_promo_code'), 'xss_clean');

//User has not selected trial plan so need of payment_type information...
        $this->form_validation->set_rules('payment_type', lang('dentist_management_payment_types'), 'required|trim|xss_clean');

        if ($this->input->post("payment_type") == "card") {
//User has selected credit card so needed credit card info...
            $this->form_validation->set_rules('card_type', lang('dentist_management_card_type'), 'required|xss_clean');
            $this->form_validation->set_rules('card_owner', lang('dentist_management_card_owner'), 'required|xss_clean');
            $this->form_validation->set_rules('card_number', lang('dentist_management_card_no'), 'required|xss_clean');
            $this->form_validation->set_rules('cvv', lang('dentist_management_cvv'), 'required|xss_clean');
            $this->form_validation->set_rules('date', lang('dentist_management_date'), 'required|xss_clean|callback_checkDate');
            $this->form_validation->set_rules('year', lang('dentist_management_year'), 'required|xss_clean|callback_checkYear');
        }

        if ($this->form_validation->run($this) === FALSE) {
            return FALSE;
        }


//Check for authentication...
        $user_id = $this->session->userdata("user_id");
        $role_name = $this->session->userdata("role_name");
        if (empty($user_id) || ($role_name != "Dentist")) {
            Template::redirect("/");
        }

//Get promo code info...
        $promo_code = $this->input->post('promo_code');
        if (!empty($promo_code)) {
            $promo_code_info = $this->pcm->find_by(array("title" => $promo_code, "status" => 1));
            if ($promo_code_info == FALSE) {
                return self::PROMO_CODE_NOT_FOUND;
            }

            if (in_array($promo_code_info->id, $this->upcm->find_used_codes_for($user_id))) {
                return self::PROMO_CODE_ALREADY_USED;
            }

            $this->session->set_userdata("promo_code_id", $promo_code_info->id);
        }

//Get plan info...
        $sub_plan_id = $this->input->post('sub_plan');
        $plan_info = $this->pm->find_by("id", $sub_plan_id);
        if ($plan_info == FALSE) {
            return self::PLAN_NOT_FOUND;
        }

//Get previous subscription plan...
        $prev_sub_plan = $this->udspm->find_by(array("user_id" => $user_id));
        if ($prev_sub_plan == FALSE) {
            return self::NO_PLAN_SUBSCRIBED;
        }

//Setting data to session...
        $this->session->set_userdata("registering_user_id", $user_id);
        $this->session->set_userdata("registering_user_plan_id", $sub_plan_id);
        $this->session->set_userdata("proceed_payment", TRUE);
        $this->session->set_userdata("registering_user_sub_table_id", $prev_sub_plan->id);
        $this->session->set_userdata("user_profile_id", $prev_sub_plan->sub_profile_id);
        $this->session->set_userdata("type", "upgrade");

        return self::SUCCESSFULL;
    }

    public function cancel_plan($profile_id) {

//Initialization
        $paypal = new Paypal();
        $data = array(
            "PROFILEID" => $profile_id,
            "ACTION" => "Cancel"
        );

//Check profile id...
        if (empty($profile_id)) {
            return "Profile id not found";
        }

//Make paypal api call of profile cancellation...
        $res = $paypal->request('ManageRecurringPaymentsProfileStatus', $data);
        if (isset($res['ACK']) && ($res['ACK'] == "Success")) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

//--------------------------------------------------------------------

    public function MultipleImage() {
        // echo "yes";
        $config = array();
        $uploadPath = FCPATH . 'assets/uploads/dentist';
        $config['upload_path'] = $uploadPath;
        $config['allowed_types'] = 'gif|jpg|png|jpeg';
        $config['max_size'] = '6000';

        $this->load->library('upload');

        $this->upload->initialize($config);
        //echo $_POST['image']['name']['0'];
        //var_dump($_POST);die;
        if ($_POST['image']['name']['0'] != '') {

            if (!$this->upload->do_multi_upload("image")) {
                $error = array('error' => $this->upload->display_errors());
                // var_dump($error);die;
                return $error;
                //return FALSE;
            } else {
                $upload_data = $this->upload->get_multi_upload_data();
                foreach ($upload_data as $value) {
                    $images[] = $value['file_name'];
                }
                return $images;
            }
        }
        //  }
    }

    public function deleteimage() {
        if ($this->input->is_ajax_request()) {
            $id = $this->input->post('id');
            $query1 = $this->db->get_where('user_dentist_images', array('id' => $id));
            //     echo $this->db->last_query();exit;
            $get_dentimage = array_shift($query1->result());
            $mulimage_Path = FCPATH . "assets/uploads/dentist/" . $get_dentimage->images;
            array_map("unlink", glob($mulimage_Path));
            $query = $this->db->delete('user_dentist_images', array('id' => $id));
            //   redirect('dentist_management/edit_profile');
            Template::set_view('dentist_management/edit_profile');
        }
    }

    public function get_dentist_appointments_by_filter() {
        if ($this->input->is_ajax_request() && $this->session->userdata("user_id") && $this->session->userdata("role_name") == "Dentist") {
            $dentist_id = $this->session->userdata("user_id");
            $app_type = $this->input->get('app_type');
            Template::set("app_type", $app_type);
            if ($dentist_id) {
                $this->load->model("dentist_management/user_management_model", "dumm");

                $dentist_info = $this->dumm->find_info_for_dent($dentist_id);
                Template::set("dentist_info", $dentist_info);

                if (isset($dentist_info->sub_info) && !empty($dentist_info->sub_info) && isset($dentist_info->sub_info['start_date']) && isset($dentist_info->sub_info['end_date']) && isset($dentist_info->sub_info['no_appointment'])) {
                    /* Start For dentist appointments */
                    $start_date = $dentist_info->sub_info['start_date'];
                    $end_date = $dentist_info->sub_info['end_date'];
                    $no_of_appointments = $dentist_info->sub_info['no_appointment'];
                    $this->load->model('dentist_appointments/dentist_appointments_model');
                    switch ($app_type) {
                        case 'future':
                            //Current month's past appointments
                            $curMonthPast = $this->dentist_appointments_model->get_appointments_for_dentist($dentist_id, array(
                                'type' => 'curMonthPast',
                                'value' => array(
                                    $start_date,
                                    $end_date
                                )
                                    ), $no_of_appointments);
                            $remainig_count = $curMonthPast ? ($no_of_appointments - count($curMonthPast)) : $no_of_appointments;
                            //Future appointments
                            $future = $this->dentist_appointments_model->get_appointments_for_dentist($dentist_id, array(
                                'type' => 'future',
                                'value' => array(
                                    $dentist_info->sub_info['start_date'],
                                    $dentist_info->sub_info['end_date']
                                )
                                    ), $remainig_count, TRUE);
                            //All future appointments
                            $futureAll = $this->dentist_appointments_model->get_appointments_for_dentist($dentist_id, array(
                                'type' => 'future',
                                'value' => array(
                                    $dentist_info->sub_info['start_date'],
                                    $dentist_info->sub_info['end_date']
                                )
                            ));
                            $futureAllCount = $futureAll ? count($futureAll) : 0;
                            $futureAllCountForUpgrade = $futureAllCount > $remainig_count ? $futureAllCount - $remainig_count : 0;
                            Template::set('futureApps', $future);
                            Template::set('futureAllCountForUpgrade', $futureAllCountForUpgrade);
                            break;
                        case 'past':
                            $pastAll = $this->dentist_appointments_model->get_appointments_for_dentist($dentist_id, array('type' => 'past'), FALSE, TRUE);
                            Template::set('pastApps', $pastAll);
                            break;
                        default:
                            break;
                    }
                    /* End For dentist appointments */
                    Template::render();
                } else {
                    echo 'fail';
                    die();
                }
            } else {
                echo 'fail';
                die();
            }
        }
    }

    public function get_discounted_price() {

//Initialization...
        $this->load->model("plan/plan_model", "pm");
        $this->load->model("promocodes/promocodes_model", "pcm");
        $this->load->model("promocodes/used_promocodes_model", "upcm");
        $this->load->model("dentist_management/user_dent_activation_model", "udam");
        $this->load->model("promocodes/promocodes_model", "pcm");


        if ($this->input->is_ajax_request()) {

            /* dump($_POST); */
            $sub_plan_id = $this->input->post("sub_plan");
            $promo_code_title = $this->input->post("promo_code");
            $activation_hash = $this->input->post("user_hash");
            $data = array();
            $where = array(
                "activation_hash" => $activation_hash,
                "verified" => 0
            );
            $user_hash = $this->udam->get_results_where($where);
            if ($user_hash == FALSE) {
                $data["status"] = "fail";
                $data["msg"] = "Sorry! Invalid activation link.";
                echo json_encode($data);
                die;
            }
            $user_id = $user_hash->dentist_id;


//Get plan info...
            $plan_info = $this->pm->find_by(array("id" => $sub_plan_id, "status" => 1));
            //dump($plan_info);
            if ($plan_info == FALSE) {
                $data["status"] = "fail";
                $data["msg"] = lang('dentist_management_sorry_found_in_system');
                echo json_encode($data);
                die;
            }

//Get promocode info...
            $promo_code = $this->pcm->find_by(array("title" => $promo_code_title, "status" => 1));
            if ($promo_code == FALSE) {
                $data["status"] = "fail";
                $data["msg"] = lang('bf_common_Sorry_promocode_system');
                echo json_encode($data);
                die;
            }
            if (in_array($promo_code->id, $this->upcm->find_used_codes_for($user_id))) {
                $data["status"] = "fail";
                $data["msg"] = lang('bf_common_sorry_promocode_is_already_used');
                echo json_encode($data);
                die;
            }


//Get discounted price...
            if (!empty($promo_code->plan_id) && ($plan_info->id == $promo_code->plan_id)) {

                $dis_price = $this->pcm->get_price_with_promocode_discount($promo_code->id, $plan_info->price);
                $data["status"] = "success";
                $data["plan_name"] = $plan_info->name;
                $data["plan_price"] = $plan_info->price;
                $data["discounted_price"] = $dis_price;
                $data["saved_price"] = $data["plan_price"] - $data["discounted_price"];
                $data["msg"] = sprintf(lang('bf_common_promocodes_front_after_plan_price'), $data['plan_name'], "$" . round($data['discounted_price'], 2));
                echo json_encode($data);
                die;
            } else {
                $data["status"] = "fail";
                $data["msg"] = lang('bf_common_sorry_promocode_is_not_used');
                echo json_encode($data);
                die;
            }
        } else {
            redirect("/");
        }
    }

    public function get_profile_info() {
        $this->load->model("dentist_management/Recurring_payment_profile_model");

        $recurring_profile_obj = new Recurring_payment_profile_model();
        $recurring_profile_obj->initialize(new Paypal(), "I-228CLE7FS49L");
        dump($recurring_profile_obj->get_profile());
        //dump($recurring_profile_obj->manage_recurring_profile("Reactivate"));
        dump($recurring_profile_obj->getErrors());
    }

    public function checkDate($str) {
        $return = TRUE;
        $date = $this->input->post("date");
        if (empty($date)) {
            $this->form_validation->set_message("checkDate", lang("dentist_management_date_error"));
            $return = FALSE;
        }

        return $return;
    }

    public function checkYear($str) {
        $return = TRUE;
        $year = $this->input->post("year");
        if (empty($year)) {
            $this->form_validation->set_message("checkYear", lang("dentist_management_year_error"));
            $return = FALSE;
        }

        return $return;
    }

}
