<?php
/*
 * CBM Plugin Package v4 - Oct 10, 2012
 */



/**
 *	Custom payment processor for UBC CBM
 *	to be used with CiviCRM module for Drupal
 *
 */

require_once 'CRM/Core/Payment.php';

class CRM_Core_Payment_CBM extends CRM_Core_Payment {
	
	const
		CHARSET  = 'iso-8859-1';
	
	const	
		TEST_AUTH_PORT = "8301",
		TEST_PAYM_PORT = "",
		LIVE_AUTH_PORT = "",
		LIVE_PAYM_PORT = "";
		
	const 		
		AUTH_DIR = "/authServer/authenticate",
		PAYM_DIR = "/creditcardservice/CreditCardPaymentForm";
		
	protected $_mode = null;
	
	private $_authport = null;
	private $_paymport = null;
	
	static private $_singleton = null; 

	/**
	 * Constructor
	 *
	 * @param string $mode the mode of operation: live or test
	 *
	 * @return void
	 */
	function __construct( $mode, &$paymentProcessor ) {
		ini_set('memory_limit', '512M');
		$this->_mode             = $mode;
		$this->_paymentProcessor = $paymentProcessor;
		$this->_processorName    = ts('UBC CBM');
			
		$this->_authport = ($mode == 'live')? self::LIVE_AUTH_PORT: self::TEST_AUTH_PORT;
		$this->_paymport = ($mode == 'live')? self::LIVE_PAYM_PORT: self::TEST_PAYM_PORT;
	}
	
	
	/** 
     * singleton function used to manage this object 
     * 
     * @param string $mode the mode of operation: live or test
     * 
     * @return object 
     * @static 
     * */
	static function &singleton($mode = 'test', &$paymentProcessor, &$paymentForm = NULL, $force = FALSE) {
		$processorName = $paymentProcessor['name'];
		if (self::$_singleton[$processorName] === NULL) {
		  self::$_singleton[$processorName] = new CRM_Core_Payment_CBM($mode, $paymentProcessor);
		}
		return self::$_singleton[$processorName];
	}

	/**
	 * Check for correct payment portal configuration
	 */	
	function checkConfig() {
       $config = CRM_Core_Config::singleton( );

        $error = array( );

        if (empty($this->_paymentProcessor['user_name'])) {
            $error[] = ts('Merchant ID is not set in the Administer CiviCRM &raquo; Payment Processor.');
        }
        if (empty($this->_paymentProcessor['password'])) {
            $error[] = ts('Password is not set in the Administer CiviCRM &raquo; Payment Processor.');
        }
        
        if (!empty($error)) {
            return implode('<p>', $error);
        }

        return null;
	}
	
	
	/**
	 * Creates a unique transaction ID for this purchase
	 * Requests Master Ticket from CBM Authentication Server
	 * Requests Session Ticket from CBM Authentication Server
	 * Posts transaction details to the CBM WS who takes over transaction
	 *
	 * @param  array $params assoc array of input parameters for this transaction
	 * @return array the result in an nice formatted array (or an error object)
	 *
	 */
	function doTransferCheckout(&$params, $component) {

		$component = strtolower($component);
        $config = CRM_Core_Config::singleton();
        
        if ($component != 'contribute' && $component != 'event') {
            CRM_Core_Error::fatal(ts('Component is invalid'));
        }
        
        //Acquire all required urls for response or cancellation from payment
        if ($component == 'event') {
        	$cancelURL = CRM_Utils_System::url( 'civicrm/event/register',
                                                "_qf_Confirm_display=true&qfKey={$params['qfKey']}", 
                                                false, null, false );
        } 
        else if ($component == 'contribute') {
            $cancelURL = CRM_Utils_System::url( 'civicrm/contribute/transact',
                                                "_qf_Confirm_display=true&qfKey={$params['qfKey']}", 
                                                false, null, false );
        }		

       	
        $notifyURL = $config->userFrameworkResourceURL."extern/cbmNotify.php";
        $continueURL = $config->userFrameworkResourceURL."extern/cbmContinue.php";
        $urls = array('cancel' => $cancelURL, 'notify' => $notifyURL, 'continue' => $continueURL);

        
		//get master and session tickets from CBM auth server
		$auth = $this->getCbmTicketsFromAuthServer();
		if (isset($auth['master']) && isset($auth['session'])) {

			//make payment request
			$result = $this->postPaymentRequest($auth, $params, $urls, $component);
		}	
		else {
			CRM_Core_Error::fatal(ts('Unable to obtain authentication from UBC CBM. ('.$auth['string'].')'), $auth['code']);
		}	
	}
	
	/**
	 * Acquire Master Ticket and Session Ticket from Auth Server
	 * 
	 * 
	 * @return  $array['master']
	 * 			$array['session']
	 * 			
	 * 			OR array containing error response
	 * 			$error['string']
	 * 			$error['code']
	 */
	private function getCbmTicketsFromAuthServer() {
		
		$errMsg = array();
		$auth_url = $this->_paymentProcessor['url_site'];
		$auth_port = $this->_authport;
		
		//Master
		$masterFormData = array("userID" => $this->_paymentProcessor['user_name'],
   		       				   	 "credential" => $this->_paymentProcessor['password'],
   		       				     "function"   => "authenticate" );
   		       		 
   		       				     
	    $masterTicketResponse = self::hlp_sendPost($auth_url, $auth_port, $masterFormData, $errMsg);
	    //parse response to get the response code and auth ticket from the xml portion
	    $masterTicketResponseFields = self::hlp_extract_params_from_XML($masterTicketResponse, array('response-code','auth-ticket'));
	    if ($masterTicketResponseFields['response-code'] == 100 && isset($masterTicketResponseFields['auth-ticket'])) {
		    	
		    //Session
		    $masterTicket = $masterTicketResponseFields['auth-ticket']; 
		    $sessionFormData = array("function" => "getSessionTicket",
	                    		   	  "authTicket" => "$masterTicket",
	   		           			      "creditcard" => "checked" );
		    $sessionTicketResponse = self::hlp_sendPost($auth_url, $auth_port, $sessionFormData, $errMsg);
			//CRM_Core_Error::debug("master ticket response", $sessionTicketResponse);
		    //parse response to get response code
		    $sessionTicketResponseFields = self::hlp_extract_params_from_XML($sessionTicketResponse, array('response-code'));
		    if ($sessionTicketResponseFields['response-code'] == 100) {

		    	//extract session ticket
		    	$sessionTicket = self::hlp_extract_session_ticket_from_response($sessionTicketResponse);
    			$result = array('master' => $masterTicket, 'session' => $sessionTicket);
				return $result;
		    }
	    }
	    
	    return $errMsg;
	}
	
	/**
	 * Helper function that sends post request to CBM
	 * authentication server to attempt to retrieve tickets
	 * 
	 * @param string $auth_url
	 * @param array $formdata
	 * @param array $errMsg
	 * @return the return value from auth server | false
	 * 				
	 */
	static function hlp_sendPost($auth_url, $auth_port, $formdata, &$errMsg="") {
		
		$result = "";
		$ssl = false;
		
		if (strstr($auth_url, 'https://')) {
			$auth_url = substr($auth_url, strlen('https://'));
			$ssl = true;
		}
		else if (strstr($auth_url, 'http://')) {
			$auth_url = substr($auth_url, strlen('http://'));
		}
		
		//create connection
		if($ssl) $fp = fsockopen("ssl://".$auth_url, 443, $errno, $errstr, 30);
		else $fp = fsockopen($auth_url, $auth_port, $errno, $errstr, 30);
			
			
		if($fp) {	
	    	//build the post string
			$poststring = '';
		    foreach($formdata as $key => $val) {
		    	$poststring .= urlencode($key) . "=" . urlencode($val) . "&";
		    }
		    
		    $out = "POST ". self::AUTH_DIR ." HTTP/1.1\r\n";
		    $out .= "Host: ". $_SERVER['HTTP_HOST']."\r\n";
		    $out .= "Content-Type: application/x-www-form-urlencoded\r\n";
		    $out .= "Content-Length: " . strlen($poststring) . "\r\n";
		    $out .= "Connection: Close\r\n\r\n";
		    
		    
		    //Send the headers and post data to CBM
		    fwrite($fp, $out.$poststring);
		    while (!feof($fp)) {
		    	$result .= fgets($fp, 1024);
		    }
		    fclose($fp);
		    return $result;
		}
 
		$errMsg['string'] = $errstr;
		$errMsg['code'] = $errno; 
		return false;
	}
	
	/**
	 * Helper function for extracting fields from xml string
	 * 
	 * @param String $data
	 * @param array $xmlFieldNames
	 */
	static function hlp_extract_params_from_XML($data, $xmlFieldNames) {
		
		$parsedXML = array();
		foreach ($xmlFieldNames as $xmlField) {

		  	if(strpos($data, $xmlField)!==false) {
		    	$parsedXML[$xmlField] = substr($data, strpos($data,"<$xmlField>")+strlen("<$xmlField>"),
		  	               strpos($data, "</$xmlField>") - strlen("<$xmlField>") - strpos($data,"<$xmlField>"));
		  	}
		}
		return $parsedXML;
	}
	
	/**
	 * Helper function for extracting the session ticket from the reponse data
	 * 
	 * Enter description here ...
	 * @param String $data
	 */
	static function hlp_extract_session_ticket_from_response($data) {
	
	    $parsedXML = substr($data, strpos($data, "<auth-ticket name=\"creditcard\">")
	    					+strlen("<auth-ticket name=\"creditcard\">"),
	    					strpos($data, "</auth-ticket>") - strlen("<auth-ticket name=\"creditcard\">") 
	    					- strpos($data,"<auth-ticket name=\"creditcard\">"));
	
	  	return $parsedXML;
	}	
	
	/**
	 * Sends a post request to CBM WS
	 * 
	 * @param array $tickets
	 * @param array $args
	 * @param array $urls
	 */
	private function postPaymentRequest(&$tickets, &$params, &$urls, $component) {
		
		//Create a unique Transaction ID
		$unique_str = $params['participantID'].$params['eventID'].time();
		$txn_id = md5($unique_str);
		
		$amount = str_replace(",","", number_format($params['amount'],2));
               
        if ($component == 'event') {
            $merchantRef = "Event Registration";   
            $membershipID = null;         
        } 
        elseif ($component == 'contribute') {
        	$merchantRef = "Charitable Contribution";
            $membershipID = CRM_Utils_Array::value('membershipID', $params);
        }		

        //Insert row for this transaction
        $query = "INSERT INTO civicrm_cbm_trxn (cbm_unique_id, auth_url, auth_port, state, amount, contact_id, 
        										contribution_id, contributionType_id, invoice_id, component, 
        										qfkey, participant_id, event_id, membership_id) 
        			VALUES ('{$txn_id}', '{$this->_paymentProcessor['url_site']}', 
        					'{$this->_authport}', 'PYMT_REQUESTED', '{$amount}', '{$params['contactID']}', 
        					'{$params['contributionID']}', '{$params['contributionTypeID']}', '{$params['invoiceID']}', '{$component}', 
        					'{$params['qfKey']}', '{$params['participantID']}', '{$params['eventID']}', '{$membershipID}')";
        
        $daoInsert = & CRM_Core_DAO::executeQuery($query);
        
		
		//payment request params
		$formdata = array(  'SRCE_REF_NO' => "1",
	         				'SRCE_TYP_CD' => $this->_paymentProcessor['signature'],
	         				'TRAN_AMOUNT' => $amount,
	              			'TICKET' => $tickets['session'],
	   						'MERCHANT_TRANS_ID' => $txn_id,
	   						'ITEM_DESCRIPTION' => $merchantRef,
	          				'NOTIFY_URL' => $urls['notify'],		
	        				'CONTINUE_URL' => $urls['continue'],
							'GL_ACCT_CD'=>$this->_paymentProcessor['subject']);	
		
		//build the post string
		$poststring = '';
		foreach($formdata as $key => $val) {
			$poststring .= urlencode($key)."=".urlencode($val)."&";
		}
		
		//Build CBM WS URL with LFS transaction data
		$paym_url = $this->_paymentProcessor['url_api'];
		$cbmurl = $paym_url.":".$this->_paymport.self::PAYM_DIR."?".$poststring;
		
		//redirect to CBM
		CRM_Utils_System::redirect($cbmurl);
	}
	
    function setExpressCheckOut( &$params ) {
        CRM_Core_Error::fatal( ts( 'This function is not implemented' ) ); 
    }
    function getExpressCheckoutDetails( $token ) {
        CRM_Core_Error::fatal( ts( 'This function is not implemented' ) ); 
    }
    function doExpressCheckout( &$params ) {
        CRM_Core_Error::fatal( ts( 'This function is not implemented' ) ); 
    }

    function doDirectPayment( &$params ) {
        CRM_Core_Error::fatal( ts( 'This function is not implemented' ) );
    }
	
	
	
	
	
	
	
	

}
