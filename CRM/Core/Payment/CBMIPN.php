<?php
/*
 * CBM Plugin Package v4 - Oct 10, 2012
 */


require_once 'CRM/Core/Payment/BaseIPN.php';

class CRM_Core_Payment_CBMIPN extends CRM_Core_Payment_BaseIPN {

    /**
     * We only need one instance of this object. So we use the singleton
     * pattern and cache the instance in this variable
     *
     * @var object
     */
    static private $_singleton = null;

    /**
     * mode of operation: live or test
     *
     * @var object
     */
    static protected $_mode = null;


    /** 
     * Constructor 
     * 
     * @param string $mode the mode of operation: live or test
     * @return void 
     */ 
    function __construct( $mode, &$paymentProcessor ) {
        parent::__construct( );
        
        $this->_mode = $mode;
        $this->_paymentProcessor = $paymentProcessor;
    }

    /**  
     * singleton function used to manage this object  
     *  
     * @param string $mode the mode of operation: live or test
     *  
     * @return object  
     */  
    static function &singleton( $mode, $component, &$paymentProcessor ) {
        if ( self::$_singleton === null ) {
            self::$_singleton = new CRM_Core_Payment_CBMIPN( $mode, $paymentProcessor );
        }
        return self::$_singleton;
    }

	/**
     * This function is not processor specific (taken from PaymentExpressIPN)
     * The function gets called when a new order takes place.
	 * 
	 * @param $success
	 * @param $privateData
	 * @param $component
	 * @param $amount
	 * @param $transactionReference
	 */
    function newOrderNotify( $success, $privateData, $component,$amount,$transactionReference ) {
        $ids = $input = $params = array( );
        
        $input['component'] = strtolower($component);

        $ids['contact']          = self::retrieve( 'contactID'     , 'Integer', $privateData, true );
        $ids['contribution']     = self::retrieve( 'contributionID', 'Integer', $privateData, true );

        if ( $input['component'] == "event" ) {
            $ids['event']       = self::retrieve( 'eventID'      , 'Integer', $privateData, true );
            $ids['participant'] = self::retrieve( 'participantID', 'Integer', $privateData, true );
            $ids['membership']  = null;
        } else {
            $ids['membership'] = self::retrieve( 'membershipID'  , 'Integer', $privateData, false );
        }
        $ids['contributionRecur'] = $ids['contributionPage'] = null;

        if ( ! $this->validateData( $input, $ids, $objects ) ) {
            return false;
        }

        // make sure the invoice is valid and matches what we have in the contribution record
        $input['invoice']    =  $privateData['invoiceID'];
        $input['newInvoice'] =  $transactionReference;
        $contribution        =& $objects['contribution'];
		$input['trxn_id']  =	$transactionReference;
		
        if ( $contribution->invoice_id != $input['invoice'] ) {
            CRM_Core_Error::debug_log_message( "Invoice values dont match between database and IPN request" );
            echo "Failure: Invoice values dont match between database and IPN request<p>";
            return;
        }

        // lets replace invoice-id with Payment Processor -number because thats what is common and unique 
        // in subsequent calls or notifications sent by google.
        $contribution->invoice_id = $input['newInvoice'];

        $input['amount'] = $amount;
        
        if ( $contribution->total_amount != $input['amount'] ) {
            CRM_Core_Error::debug_log_message( "Amount values dont match between database and IPN request" );
            echo "Failure: Amount values dont match between database and IPN request. ".$contribution->total_amount."/".$input['amount']."<p>";
            return;
        }

        require_once 'CRM/Core/Transaction.php';
        $transaction = new CRM_Core_Transaction( );

        // check if contribution is already completed, if so we ignore this ipn
        if ( $contribution->contribution_status_id == 1 ) {
            CRM_Core_Error::debug_log_message( "returning since contribution has already been handled" );
            echo "Success: Contribution has already been handled<p>";
            return true;
        } else {
            /* Since trxn_id hasn't got any use here, 
             * lets make use of it by passing the eventID/membershipTypeID to next level.
             * And change trxn_id to the payment processor reference before finishing db update */
            if ( $ids['event'] ) {
                $contribution->trxn_id =
                    $ids['event']       . CRM_Core_DAO::VALUE_SEPARATOR .
                    $ids['participant'] ;
            } else {
                $contribution->trxn_id = $ids['membership'];
            }
        }
        $this->completeTransaction ( $input, $ids, $objects, $transaction);
        return true;
    }

	/**
	 * Helper function for newOrderNotify method
	 *
	 * @param $name
	 * @param $type
	 * @param $object
	 * @param $abort
	 */
    static function retrieve( $name, $type, $object, $abort = true ) {
        $value = CRM_Utils_Array::value( $name, $object );
        if ( $abort && $value === null ) {
            CRM_Core_Error::debug_log_message( "Could not find an entry for $name" );
            echo "Failure: Missing Parameter - ".$name."<p>";
            exit( );
        }

        if ( $value ) {
            if ( ! CRM_Utils_Type::validate( $value, $type ) ) {
                CRM_Core_Error::debug_log_message( "Could not find a valid entry for $name" );
                echo "Failure: Invalid Parameter<p>";
                exit( );
            }
        }

        return $value;
    }
    
   /**  
	* This function is not processor specific (taken from PaymentExpressIPN)
	* The function returns the component(Event/Contribute..)and whether it is Test or not
	*  
	* @param array   $privateData    contains the name-value pairs of transaction related data
	*  
	* @return array context of this call (test, component, payment processor id)
	*/
	static function getContext($privateData)	{
        require_once 'CRM/Contribute/DAO/Contribution.php';

        $component = null;
        $isTest = null;

        $contributionID   = $privateData['contributionID'];
        $contribution     = new CRM_Contribute_DAO_Contribution( );
        $contribution->id = $contributionID;

        if ( ! $contribution->find( true ) ) {
            CRM_Core_Error::debug_log_message( "Could not find contribution record!: $contributionID" );
            echo "Failure: Could not find contribution record for $contributionID<p>";
            exit( );
        }
		
        if (stristr($contribution->source, 'Online Contribution')) {
            $component = 'contribute';
        } elseif (stristr($contribution->source, 'Online Event Registration')) {
            $component = 'event';
        }
        $isTest = $contribution->is_test;
       
		$duplicateTransaction = 0;
        if ($contribution->contribution_status_id == 1) {
            //contribution already handled. (some processors do two notifications so this could be valid)
			$duplicateTransaction = 1;
        }

        if ( $component == 'contribute' ) {
            if ( ! $contribution->contribution_page_id ) {
                CRM_Core_Error::debug_log_message( "Could not find contribution page for contribution record: $contributionID" );
                echo "Failure: Could not find contribution page for contribution record: $contributionID<p>";
                exit( );
            }

            // get the payment processor id from contribution page
            $paymentProcessorID = CRM_Core_DAO::getFieldValue( 'CRM_Contribute_DAO_ContributionPage',
                                                               $contribution->contribution_page_id,
                                                               'payment_processor_id' );
        } else {
			
            $eventID = $privateData['eventID'];
          
            if ( !$eventID ) {
                CRM_Core_Error::debug_log_message( "Could not find event ID" );
                echo "Failure: Could not find eventID<p>";
                exit( );
            }

            // we are in event mode
            // make sure event exists and is valid
            require_once 'CRM/Event/DAO/Event.php';
            $event = new CRM_Event_DAO_Event( );
            $event->id = $eventID;
            if ( ! $event->find( true ) ) {
                CRM_Core_Error::debug_log_message( "Could not find event: $eventID" );
                echo "Failure: Could not find event: $eventID<p>";
                exit( );
            }
            
            // get the payment processor id from contribution page
            $paymentProcessorID = $event->payment_processor;  //this variable name changed from 3.4 to 4.2
        }

        if ( ! $paymentProcessorID ) {
            CRM_Core_Error::debug_log_message( "Could not find payment processor for contribution record: $contributionID" );
            echo "Failure: Could not find payment processor for contribution record: $contributionID<p>";
            exit( );
        }

        return array( $isTest, $component, $paymentProcessorID, $duplicateTransaction );
    }

    /**
     * This is a processor specific implementation of this method
     * This function handles the notification request coming from the CBM Server
     * by first checking to see if the transaction is already being processed, then
     * validating the request for notification and finaly sending a notification
     * response back to the CBM Server.  
     * 
     * @param $sessionAuthTicket
     * @param $xmlFieldArray
     * @param $cbm_trxn_auth_url
     * @param $cbm_trxn_state 
     *
     * Note, a couple of these params are not needed anymore, but why mess with something that works?
     */
    static function processNotificationResponse($sessionAuthTicket, $xmlFieldArray, $cbm_trxn_auth_url, $cbm_trxn_auth_port, $cbm_trxn_state) {
    	
	  	// ensure that the transaction isn't already being processed
    	if($cbm_trxn_state != 'PYMT_REQUESTED') {
    		//already been processed, send success message
    		$reply = "<?xml version='1.0' encoding='UTF-8' standalone='yes' ?>"
	           			. "<credit_card_service_response>"
	           			. "<CBM_ID>" .$xmlFieldArray['CBM_ID']. "</CBM_ID>"
	           			. "<MERCHANT_ID>".$xmlFieldArray['MERCHANT_ID']."</MERCHANT_ID>"
	           			. "<STATUS_CODE>0</STATUS_CODE>"
	           			. "</credit_card_service_response>";
	    
	        header('Content-Type: text/xml');
	      	print $reply;
	      	exit;
	    }
	    
  		$newState = "COMPLETE";
  		$statusCode = "0"; //success
   		$payment_num = $xmlFieldArray['CBM_ID'];

		//update cbm_trxn row state and notification code
	    $query = "UPDATE civicrm_cbm_trxn SET state='{$newState}', 
       				payment_num='{$payment_num}', 
	    			notification_request_code='{$xmlFieldArray['STATUS_CODE']}' 
	    			WHERE cbm_unique_id = %1";
		$params = array(1 => array($xmlFieldArray['MERCHANT_ID'], 'String'));
	    $daoUpdate = & CRM_Core_DAO::executeQuery($query, $params);
	  		

		//update the successfull transaction	
    	//GET CONTEXT - retrieves information to complete transaction
    	$params = array('cbm_unique_id' => $xmlFieldArray['MERCHANT_ID']);
        $values = array();

        $Trxn_Obj = CRM_Core_DAO::commonRetrieve('CRM_Core_DAO_CbmTrxn', $params, $values);
        
        $privateData = array();
        $privateData['contactID'] = $Trxn_Obj->contact_id;
		$privateData['contributionID'] = $Trxn_Obj->contribution_id;
		$privateData['contributionTypeID'] = $Trxn_Obj->contributionType_id;
		$privateData['invoiceID'] = $Trxn_Obj->invoice_id;

		$component = $Trxn_Obj->component;
		if ($component == "event") {
			$privateData['participantID'] = $Trxn_Obj->participant_id;
			$privateData['eventID'] = $Trxn_Obj->event_id;
		} 
		elseif ($component == "contribute") {
	        $privateData["membershipID"] = $Trxn_Obj->membership_id;			
		}
		
        list($mode, $component, $paymentProcessorID, $duplicateTransaction) = self::getContext($privateData);
        $mode = ($mode)? 'test': 'live';

        $paymentProcessor = CRM_Financial_BAO_PaymentProcessor::getPayment($paymentProcessorID, $mode);
        $ipn=& self::singleton($mode, $component, $paymentProcessor);
	  		
		if ($duplicateTransaction == 0) {
			$ipn->newOrderNotify(1, $privateData, $component, $Trxn_Obj->amount, $values['auth_ticket']);
		}
	  		
	  			  		
	  	//Return validation response to CBM 
	    $reply = "<?xml version='1.0' encoding='UTF-8' standalone='yes' ?>"
	         			. "<credit_card_service_response>"
	          			. "<CBM_ID>" .$xmlFieldArray['CBM_ID']. "</CBM_ID>"
	          			. "<MERCHANT_ID>". $xmlFieldArray['MERCHANT_ID'] ."</MERCHANT_ID>"
	          			. "<STATUS_CODE>". $statusCode ."</STATUS_CODE>"
	           			. "</credit_card_service_response>";
	    print $reply;
	    exit;
	
    }
    
	/**
	 * Validate the session ticket **** NOT NEEDED ANY MORE ***
	 * 
	 * @param $auth_url
	 * @param $auth_port
	 * @param $cbmTicket
	 */
	static function validateCBMAuthTicket($auth_url, $auth_port, $cbmTicket) {
		require_once 'CRM/Core/Payment/CBM.php';
		
		// Create data string to send to CBM to get a session ticket
	 	$formdata = array( 	"function" => "verify",
			    			"authTicket" => "$cbmTicket",
			    			"service"   => "creditcard"); 

	 	$sessionValidation = CRM_Core_Payment_CBM::hlp_sendPost($auth_url, $auth_port, $formdata);
	 	$sessionResponseArray = CRM_Core_Payment_CBM::hlp_extract_params_from_XML($sessionValidation, array("response-code"));
	 	
		return $sessionResponseArray;	
	 }
    	
    	
	/**
	 * Processes the continuation script from the CBM
	 * and redirect to either the 'Thank you' or the 'cancel' civicrm screen
	 * 
	 * This function calls the processor non-specific function getContext()
	 * 
	 * @param arary $args
	 */
    static function processContinuationResponse($args) {
    	
    	//Update civicrm_cbm_trxn record
    	$merchant_id = $args['MERCHANT_ID'];
    	$transaction_status = $args['STATUS_CODE'];
    	
    	switch($transaction_status) {
    		case '0': 
    			$newState = 'COMPLETE';
				break;    			
    		case '5':
				$newState = 'DECLINED';
				break;    			
    		case '96':
				$newState = 'CANCELLED';
    			break;    			
    		case '99';	
    			$newState = 'ERROR';
    			break;    			
    	}
    
    	
    	$query = "UPDATE civicrm_cbm_trxn SET state='{$newState}',
	    			continue_status_code='{$transaction_status}' 
	    			WHERE cbm_unique_id = %1";
		$params = array(1 => array($merchant_id, 'String'));
    	$daoUpdate = & CRM_Core_DAO::executeQuery($query, $params);
    	
        //GET CONTEXT - retrieves information to complete transaction
    	$params = array('cbm_unique_id' => $merchant_id);
        $values = array();
        $Trxn_Obj = CRM_Core_DAO::commonRetrieve('CRM_Core_DAO_CbmTrxn', $params, $values);
        
		$component = $Trxn_Obj->component;

        //REDIRECT
        if ($transaction_status == '0') {
			if ($component == "event") {
                $finalURL = CRM_Utils_System::url( 'civicrm/event/register',
                                                   "_qf_ThankYou_display=1&qfKey=".$Trxn_Obj->qfkey, 
                                                   false, null, false );
			} 
			elseif ($component == "contribute") {
                $finalURL = CRM_Utils_System::url( 'civicrm/contribute/transact',
                                                   "_qf_ThankYou_display=1&qfKey=".$Trxn_Obj->qfkey,
                                                   false, null, false );
			}
				
            CRM_Utils_System::redirect($finalURL);	  	
        }
    	else {
		    if ($component == "event") {
                $finalURL = CRM_Utils_System::url( 'civicrm/event/confirm',
                                                   "reset=1&cc=fail&participantId=".$Trxn_Obj->participant_id,
                                                   false, null, false );
            } 
            elseif ($component == "contribute") {
                $finalURL = CRM_Utils_System::url( 'civicrm/contribute/transact',
                                                   "_qf_Main_display=1&cancel=1&qfKey=".$Trxn_Obj->qfkey,
                                                   false, null, false );
            }
								
            CRM_Utils_System::redirect($finalURL);			
        }    	
    }
   
}
