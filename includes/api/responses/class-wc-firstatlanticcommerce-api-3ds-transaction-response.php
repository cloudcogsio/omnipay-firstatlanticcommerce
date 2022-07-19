<?php

use SkyVerge\WooCommerce\PluginFramework\v5_10_3 as Framework;

defined( 'ABSPATH' ) or exit;

class WC_FirstAtlanticCommerce_API_Three_DS_Transaction_Response extends WC_FirstAtlanticCommerce_API_Credit_Card_Transaction_Response {

    public function get_csc_result() {
        return ! empty($this->response->getCVV2Result()) ? $this->response->getCVV2Result() : null;
    }

	public function get_authorization_code() {

	    return ! empty( $this->response->getAuthCode() ) ? $this->response->getAuthCode(): null;
	}

	public function get_payment_token() {

	    if ( empty( $this->response->getTokenizedPAN() ) ) {
			throw new Framework\SV_WC_Payment_Gateway_Exception( __( 'Required credit card token is missing or empty!' ) );
		}

		$data = array(
			'default'            => false, // tokens created as part of a transaction can't be set as default
			'type'               => WC_FirstAtlanticCommerce_Payment_Method::CREDIT_CARD_TYPE,
			'last_four'          => $this->get_last_four(),
			'card_type'          => $this->get_card_type(),
		    'exp_month'          => ($this->get_exp_month())?$this->get_exp_month():"XX",
			'exp_year'           => ($this->get_exp_year())?$this->get_exp_year():"XX",
		);

		return new WC_FirstAtlanticCommerce_Payment_Method( $this->response->getTokenizedPAN(), $data );
	}


	/**
	 * Get the card type used for this transaction
	 *
	 * @return string
	 */
	public function get_card_type() {

		// note that creditCardDetails->cardType is not used here as it is already prettified (e.g. American Express instead of amex)
		return Framework\SV_WC_Payment_Gateway_Helper::card_type_from_account_number( $this->get_bin() );
	}

	public function get_bin() {

	    return ! empty( $this->response->getTokenizedPAN() ) ? substr($this->response->getTokenizedPAN(), 0, 6): null;
	}


	/**
	 * Get the masked card number, which is the first 6 digits followed by
	 * 6 asterisks then the last 4 digits. This complies with PCI security standards.
	 *
	 * @return string
	 */
	public function get_masked_number() {

	    return ! empty( $this->response->getPaddedCardNo() ) ? $this->response->getPaddedCardNo() : null;
	}


	/**
	 * Get the last four digits of the card number used for this transaction
	 *
	 * @return string
	 */
	public function get_last_four() {

	    return ! empty( $this->response->getPaddedCardNo()) ? substr($this->response->getPaddedCardNo(),-4): null;
	}


	/**
	 * Get the expiration month (MM) of the card number used for this transaction
	 *
	 * @return string
	 */
	public function get_exp_month() {

	    return ! empty( $this->response->getExpiryMonth()) ? $this->response->getExpiryMonth(): null;
	}


	/**
	 * Get the expiration year (YY) of the card number used for this transaction
	 *
	 * @return string
	 */
	public function get_exp_year() {

	    return ! empty( $this->response->getExpiryYear()) ? $this->response->getExpiryYear(): null;
	}

    public function transaction_held()
    {
        return false;
    }

}
