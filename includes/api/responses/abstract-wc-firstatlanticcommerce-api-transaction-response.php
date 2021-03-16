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

	    return ( ! empty( $this->CreditCardTransactionResults->getCVV2Result()->getResponseCode() ) ) ? $this->CreditCardTransactionResults->getCVV2Result()->getResponseCode(): null;
	}

	public function csc_match() {

		return $this->get_csc_result() === "M";
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
