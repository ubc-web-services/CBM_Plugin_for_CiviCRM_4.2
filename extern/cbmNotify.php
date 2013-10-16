<?php
/*
 * CBM Plugin Package v4 - Oct 10, 2012
 */


/**
 * Notification Request Script
 * 
 * CBM WS sends Notification Request message to Merchant WS with the Transaction ID
 * Parse and validate this request and send Notification Response for the
 * payment transaction to be finalized.
 *  
 * Example Request Message:
 * 
 * http://www.transcripts.com/notifyrequest?TICKET=Õsf474e0d21314145862Õ
 * xml_details=<?xml version=Õ1.0Õ encoding=ÕUTF-8Õ standalone=ÕyesÕ?>
 * <creditcard_service_result><CBM_ID>1234</CBM_ID>
 * <MERCHANT_ID>5678</MERCHANT_ID>
 * <STATUS_CODE>0</STATUS_CODE>
 * <MESSAGE>approved</MESSAGE>
 * <AMOUNT>24.30</AMOUNT> 
 * </creditcard_service_result>
 *  
 */
session_start();
	
require_once '../civicrm.config.php';
require_once '../CRM/Core/Config.php';	
$config = CRM_Core_Config::singleton();

if (!isset($_POST)) {
	//Die if user navigated here somehow
  	CRM_Core_Error::fatal(ts('Unauthorized page access'));
	
}else {
 	require_once '../CRM/Core/Payment/CBM.php';
	require_once '../CRM/Core/Payment/CBMIPN.php';
	
	$sessionAuthTicket = $_POST["TICKET"];
  	$cbmXml = $_POST["xml_details"];
  	//parse xml portion
  	$xmlFieldNames = array("CBM_ID", "MERCHANT_ID", "STATUS_CODE", "MESSAGE", "AMOUNT");
  	$xmlFieldArray = CRM_Core_Payment_CBM::hlp_extract_params_from_XML($cbmXml, $xmlFieldNames);


    //Get the cbm trxn row
	$query = "SELECT state FROM civicrm_cbm_trxn WHERE cbm_unique_id = %1";
	$params = array(1 => array($xmlFieldArray['MERCHANT_ID'], 'String'));
	
	$cbm_trxn_row = CRM_Core_DAO::executeQuery($query, $params);
	while ($cbm_trxn_row->fetch()) {
		$cbm_trxn_auth_url = $cbm_trxn_row->auth_url;
		$cbm_trxn_auth_port = $cbm_trxn_row->auth_port;
		$cbm_trxn_state = $cbm_trxn_row->state;
	}
	
	CRM_Core_Payment_CBMIPN::processNotificationResponse($sessionAuthTicket, $xmlFieldArray, 
												$cbm_trxn_auth_url, $cbm_trxn_auth_port, $cbm_trxn_state);

}	
	
	
	
