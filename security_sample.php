<?php

/*
 * Various security methods.
 */

require_once "mis_base_classes/MISBase.class.inc";
require_once "mis_DB.inc";

class fieldreplink_base extends MISBase {
    protected $db_rw;
    protected $db_ro;

    public function __construct()
    {
        parent::__construct();

        $this->db_rw = $this->central_pdb;
        $this->db_ro = $this->query_pdb;
    }

    public function __destruct()
    {
        parent::__destruct();
    }

    public function defineVariableNames()
    {

    }

    public function defineVariables($variable_list = "")
    {

    }

    public function log($message, $level = PEAR_LOG_INFO, $keys = array())
    {
        $class = __CLASS__;

        parent::log($message, $level, $class, $keys);
    }

    public static function check_ip_address($session_id, $ip){
        if (isset($_SESSION['company_user']['last_activity']) && (time() - $_SESSION['company_user']['last_activity'] > 1800)) {
            // last request was more than 30 minutes ago
            header('location: /logout.php');
            exit;
        }
        $_SESSION['company_user']['last_activity'] = time(); // update last activity time stamp

        $query = "Select company_user_idnum, ip_address from fieldreplink.portal_sessions where session_id = ? and ip_address = ? and active = ?";
        $params = array($session_id, $ip, 'True');

        $results = mis_DB::pdb_central()->getRow($query, $params);
        if($results['ip_address'] == $ip){
            return true;
        }
        mis::log("LOGGED OUT line 55", PEAR_LOG_DEBUG, 'kstest');
        header('location: /logout.php');
        exit();
    }

    public static function create_page_action_token($session_company_user, $session_id, $page_action){
        return md5(XXXXXXXXXXXXXXXXXXXXXX); //OMITTED FROM SAMPLE
    }

    public static function create_page_action_record($session_id, $session_ip, $page_action, $token){
        $lookup_query = "Select idnum, company_user_idnum from fieldreplink.portal_sessions where session_id = ? and ip_address = ? and active = ?";
        $lookup_params = array($session_id, $session_ip, 'True');
        $results = mis_DB::pdb_central()->getRow($lookup_query, $lookup_params);

        if(!empty($results)){
            $query = "INSERT INTO fieldreplink.portal_users_tokens (portal_session_idnum, company_user_idnum, page_action, token, active) values(?,?,?,?,?)
                        ON DUPLICATE KEY UPDATE token = ?, active = 'True', portal_session_idnum = ?";
            $params = array($results['idnum'], $results['company_user_idnum'], $page_action, $token, 'True', $token, $results['idnum']);
            $inserted = mis_DB::pdb_central()->query($query, $params);
            if($inserted){
                return true;
            }
            else{
                fieldreplink_base::log("Unable to write token record to DB", PEAR_LOG_CRIT, $params);
                return false;
            }
        }
        else{
            mis::log("LOGGED OUT line 83", PEAR_LOG_DEBUG, 'kstest');
            header("location: /logout.php");
            exit();
        }
    }

    public static function check_page_action_token($page_action, $token){
        $query = "Select token from fieldreplink.portal_users_tokens where token = ? and page_action = ? and active = ?";
        $params = array($token, $page_action, 'True');

        $results = mis_DB::pdb_central()->getRow($query, $params);
        if($results['token'] != $token){
            mis::log("LOGGED OUT line 95", PEAR_LOG_DEBUG, 'kstest');
            header('location: /logout.php');
            exit();
        }
        else{
            $query = "UPDATE fieldreplink.portal_users_tokens SET active = ? where token = ? and page_action = ?";
            mis_DB::pdb_central()->query($query, array('False', $token, $page_action));
        }
    }

    public static function invalidate_tokens($session_id){
        $query = "Select idnum from fieldreplink.portal_sessions where session_id = ? and active = ?";
        $params = array($session_id, 'True');
        $results = mis_DB::pdb_central()->getRow($query, $params);

        $query = "UPDATE fieldreplink.portal_sessions SET active=? where session_id = ? and active = ?";
        $params = array('False', $session_id, 'True');

        mis_DB::pdb_central()->query($query, $params);

        $query = "UPDATE fieldreplink.portal_users_tokens SET active=? where portal_session_idnum = ? and active = ?";
        $params = array('False', $results['idnum'], 'True');

        mis_DB::pdb_central()->query($query, $params);
    }

    public static function scrub_data(&$data_item){

        //if the variable is an array, recurse into it
        if(is_array($data_item)){
            //for each element in the array...
            foreach($data_item as $key => $val){
                //...clean the content of each variable in the array
                fieldreplink_base::scrub_data($val);
                $data_item[$key] = $val;
            }
        }
        else{
            //Tag stripping algorithm should first decode all HTML entities
            $data_item = htmlentities($data_item);

            $data = html_entity_decode($data_item);

            //then run strip_tags
            $data = strip_tags($data);

            //then replace any of the following characters with the empty string XXXXXXXXXXXXXXXXXXXXXXX
            $to_replace = array(XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX); //OMITTED FROM SAMPLE
            $data = str_replace($to_replace, "", $data);
        }
    }
}

require_once "fieldreplink/fieldreplink.class.inc";
?>