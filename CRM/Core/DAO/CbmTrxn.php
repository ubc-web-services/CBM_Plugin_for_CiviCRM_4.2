<?php
/*
 * CBM Plugin Package v4 - Oct 10, 2012
 */

require_once 'CRM/Core/DAO.php';
require_once 'CRM/Utils/Type.php';
class CRM_Core_DAO_CbmTrxn extends CRM_Core_DAO {
	
	static $_tableName = 'civicrm_cbm_trxn';
    static $_fields = null;
    static $_links = null;
    static $_import = null;
    static $_export = null;
    static $_log = false;
    
    public $id;
    public $cbm_unique_id;
    public $payment_num;
    public $auth_url;
    public $auth_port;
    public $state;
    public $amount;
    public $contact_id;
    public $contribution_id;
    public $contributionType_id;
    public $invoice_id;
    public $component;
    public $qfkey;
    public $participant_id;
    public $event_id;
    public $membership_id;
    public $notification_request_code;
    public $continue_status_code;
    
    function __construct() {
        $this->__table = 'civicrm_cbm_trxn';
        parent::__construct();
    }
    
 	function links() {
        if (!(self::$_links)) {
            self::$_links = array(
                'contribution_id' => 'civicrm_contribution:id',
                'participant_id' => 'civicrm_participant:id',
            	'event_id' => 'civicrm_event:id',
            	'contact_id' => 'civicrm_contact:id',
            );
        }
        return self::$_links;
    }
    
    static function &fields() {
        if (!(self::$_fields)) {
            self::$_fields = array(
                'id' => array(
                    'name' => 'id',
                    'type' => CRM_Utils_Type::T_INT,
                    'required' => true,
                ) ,
                'cbm_unique_id' => array(
                    'name' => 'cbm_unique_id',
                    'type' => CRM_Utils_Type::T_STRING,
                    'maxlength' => 45,
                    //'where' => 'civicrm_civicrm_cbm_trxn.name',
                	'required' => true,
                ) ,
                'payment_num' => array(
                    'name' => 'payment_num',
                    'type' => CRM_Utils_Type::T_INT,
                    //'where' => 'civicrm_civicrm_cbm_trxn.payment_num',
                	'required' => false,
                ) ,
                'auth_url' => array(
                    'name' => 'auth_url',
                    'type' => CRM_Utils_Type::T_STRING,
                    'maxlength' => 64,
                    //'where' => 'civicrm_civicrm_cbm_trxn.auth_url',
                	'required' => true,
                ) ,
                'auth_port' => array(
                    'name' => 'auth_port',
                    'type' => CRM_Utils_Type::T_INT,
                    //'where' => 'civicrm_civicrm_cbm_trxn.auth_port',
                	'required' => true,
                ) ,
                'state' => array(
                    'name' => 'state',
                    'type' => CRM_Utils_Type::T_ENUM,
                    //'where' => 'civicrm_civicrm_cbm_trxn.state',
                	'required' => true,
                ) ,
                'amount' => array(
                    'name' => 'amount',
                    'type' => CRM_Utils_Type::T_FLOAT,
                    //'where' => 'civicrm_civicrm_cbm_trxn.amount',
                	'required' => true,
                ) ,
                'contact_id' => array(
                    'name' => 'contact_id',
                    'type' => CRM_Utils_Type::T_INT,
                    //'where' => 'civicrm_civicrm_cbm_trxn.contact_id',
                	'required' => true,
                ) ,
                'contribution_id' => array(
                    'name' => 'contribution_id',
                    'type' => CRM_Utils_Type::T_INT,
                    //'where' => 'civicrm_civicrm_cbm_trxn.contribution_id',
                	'required' => true,
                ) ,
                'contributionType_id' => array(
                    'name' => 'contributionType_id',
                    'type' => CRM_Utils_Type::T_INT,
                    //'where' => 'civicrm_civicrm_cbm_trxn.contributionType_id',
                	'required' => true,
                ) ,
                'invoice_id' => array(
                    'name' => 'invoice_id',
                    'type' => CRM_Utils_Type::T_STRING,
                	'maxlength' => 64,
                    //'where' => 'civicrm_civicrm_cbm_trxn.invoice_id',
                	'required' => true,
                ) ,
                'component' => array(
                    'name' => 'component',
                    'type' => CRM_Utils_Type::T_STRING,
                    'maxlength' => 32,
                	//'where' => 'civicrm_civicrm_cbm_trxn.component',
                	'required' => true,
                ) ,
                'qfkey' => array(
                    'name' => 'qfkey',
                    'type' => CRM_Utils_Type::T_STRING,
                    'maxlength' => 64,
                	//'where' => 'civicrm_civicrm_cbm_trxn.qfkey',
                	'required' => true,
                ) ,
                'participant_id' => array(
                    'name' => 'participant_id',
                    'type' => CRM_Utils_Type::T_INT,
                	//'where' => 'civicrm_civicrm_cbm_trxn.participant_id',
                	'required' => false,
                ) ,
                'event_id' => array(
                    'name' => 'event_id',
                    'type' => CRM_Utils_Type::T_INT,
                	//'where' => 'civicrm_civicrm_cbm_trxn.event_id',
                	'required' => false,
                ) ,
                'membership_id' => array(
                    'name' => 'membership_id',
                    'type' => CRM_Utils_Type::T_INT,
                	//'where' => 'civicrm_civicrm_cbm_trxn.membership_id',
                	'required' => false,
                ) ,
                'notification_request_code' => array(
                    'name' => 'notification_request_code',
                    'type' => CRM_Utils_Type::T_INT,
                	//'where' => 'civicrm_civicrm_cbm_trxn.notification_request_code',
                	'required' => false,
                ) ,
                'continue_status_code' => array(
                    'name' => 'continue_status_code',
                    'type' => CRM_Utils_Type::T_INT,
                	//'where' => 'civicrm_civicrm_cbm_trxn.continue_status_code',
                	'required' => false,
                ) ,
        	);
        }
        return self::$_fields;
    }	
	
    static function getTableName() {
        return self::$_tableName;
    }
    
    function getLog() {
        return self::$_log;
    }

  	function &import($prefix = false) {
        if (!(self::$_import)) {
            self::$_import = array();
            $fields = & self::fields();
            foreach($fields as $name => $field) {
               
            }
        }
        return self::$_import;
    }

 	function &export($prefix = false) {
        if (!(self::$_export)) {
            self::$_export = array();
            $fields = & self::fields();
            foreach($fields as $name => $field) {

            }
        }
        return self::$_export;
    }
    
    
    
}












