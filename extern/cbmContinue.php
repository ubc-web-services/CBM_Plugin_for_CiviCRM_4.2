<?php
/*
 * CBM Plugin Package v4 - Oct 10, 2012
 */

/**
 * Continuation Script
 * 
 * This script receives the continuation message and calls the 
 * processContinuationResponse function which handles the request
 * 
 * Example continuation message:
 * 
 * http://www.transcripts.com/continuerequest?CBM_ID=1503958&STATUS_CODE=0&
 * MERCHANT_ID=123456&MESSAGE=approved&AMOUNT=25.30
 */

#error_reporting(E_ALL);
#ini_set('display_errors', TRUE);
#ini_set('display_startup_errors', TRUE);

if ($_GET) {
	$args = $_GET;
}
elseif ($_POST) {
	$args = $_POST;
}

session_start();
	
require_once '../civicrm.config.php';
require_once '../CRM/Core/Config.php';
require_once '../CRM/Core/Payment/CBMIPN.php';
	
$config = CRM_Core_Config::singleton();

if (!($args)) {
	//Die if user navigated here somehow
  	CRM_Core_Error::fatal(ts('Unauthorized page access'));
}
else {
	CRM_Core_Payment_CBMIPN::processContinuationResponse($args);
}

