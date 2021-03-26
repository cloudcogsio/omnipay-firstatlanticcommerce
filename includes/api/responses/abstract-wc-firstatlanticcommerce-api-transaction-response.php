<?php

use SkyVerge\WooCommerce\PluginFramework\v5_10_3 as Framework;
use Omnipay\FirstAtlanticCommerce\Support\AVSCheckResponse;
use Omnipay\FirstAtlanticCommerce\Support\FraudDetails;

defined( 'ABSPATH' ) or exit;

abstract class WC_FirstAtlanticCommerce_API_Transaction_Response extends WC_FirstAtlanticCommerce_API_Response
implements Framework\SV_WC_Payment_Gateway_API_response, Framework\SV_WC_Payment_Gateway_API_Authorization_Response, Framework\SV_WC_Payment_Gateway_API_Create_Payment_Token_Response, Framework\SV_WC_Payment_Gateway_API_Customer_Response {

	public function get_transaction_id() {

	    return ! empty( $this->response->getTransactionReference() ) ? $this->response->getTransactionReference(): null;
	}

	public function get_avs_result() {

	    if ( ! empty( $this->CreditCardTransactionResults->getAVSResult() ) &&  $this->CreditCardTransactionResults->getAVSResult() != "M" ) {

	        $AVSResult = new AVSCheckResponse($this->CreditCardTransactionResults->getRequest()->getCard()->getBrand(), $this->CreditCardTransactionResults->getAVSResult());
	        if (is_object($AVSResult->getResponseCode()))
	        {
	           return '[' . $this->CreditCardTransactionResults->getAVSResult().'] - '.$AVSResult->getResponseCode()->Definition;
	        }

		}

		return $this->CreditCardTransactionResults->getAVSResult();
	}

	public function get_csc_result() {
        try {
	       return ( ! empty( $this->CreditCardTransactionResults->getCVV2Result()->getResponseCode() ) ) ? $this->CreditCardTransactionResults->getCVV2Result()->getResponseCode(): null;
        } catch (Exception $e)
        {
            return null;
        }
    }

	public function csc_match() {

	    // No results from gateway for CVV
	    if($this->get_csc_result() == null || !$this->get_csc_result()) return true;

		return $this->get_csc_result() === "M";
	}

	public function gatewayApproved()
	{
	    return parent::transaction_approved();
	}

	public function transaction_approved()
	{
	    if ($this->gatewayApproved())
	    {
	        if($this->check_csc)
	        {
    	        if ($this->csc_match())
    	        {
    	            return true;
    	        }
    	        else
    	        {
    	            return false;
    	        }
	        }
	        else
	        {
	            return true;
	        }
	    }

	    return false;
	}


	/** Risk Data feature *****************************************************/

	public function has_risk_data() {

		return ($this->CreditCardTransactionResults->getFraudScore())?true:false;
	}


	public function get_risk_id() {
        return ($this->has_risk_data())?$this->CreditCardTransactionResults->getFraudControlId():null;
	}


	/**
	 * Get the risk decision for this transaction, one of: 'not evaulated',
	 * 'approve', 'review', 'decline'
	 */
	public function get_risk_decision() {
        $FraudResponseCode = $this->CreditCardTransactionResults->getFraudResponseCode();

        switch ($FraudResponseCode)
        {
            case FraudDetails::AUTH_RESPONSE_CODE_AUTH:
            case "1":
            return "approve";

            case FraudDetails::AUTH_RESPONSE_CODE_DECLINED:
            case "B":
            case "2":
            return "decline";

            case "R":
            return "review";

            case "3":
            case "91":
            case "12":
            case "99":
            return "error";
        }

		return null;
	}


}
