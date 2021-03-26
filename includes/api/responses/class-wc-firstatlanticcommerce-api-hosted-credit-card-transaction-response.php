<?php

use SkyVerge\WooCommerce\PluginFramework\v5_10_3 as Framework;

defined( 'ABSPATH' ) or exit;

class WC_FirstAtlanticCommerce_API_Hosted_Credit_Card_Transaction_Response extends WC_FirstAtlanticCommerce_API_Credit_Card_Transaction_Response{


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

	    return null;
	}


	/**
	 * Get the masked card number, which is the first 6 digits followed by
	 * 6 asterisks then the last 4 digits. This complies with PCI security standards.
	 *
	 * @return string
	 */
	public function get_masked_number() {

	    return ! empty( $this->CreditCardTransactionResults->getPaddedCardNumber() ) ? $this->CreditCardTransactionResults->getPaddedCardNumber() : null;
	}


	/**
	 * Get the last four digits of the card number used for this transaction
	 *
	 * @return string
	 */
	public function get_last_four() {

	    return ! empty( $this->get_masked_number()) ? substr($this->get_masked_number(),-4): null;
	}


	/**
	 * Get the expiration month (MM) of the card number used for this transaction
	 *
	 * @return string
	 */
	public function get_exp_month() {

	    return null;
	}


	/**
	 * Get the expiration year (YYYY) of the card number used for this transaction
	 *
	 * @return string
	 */
	public function get_exp_year() {

	    return null;
	}

	/** 3D Secure feature *****************************************************/

    /**
     * {@inheritDoc}
     * @see \SkyVerge\WooCommerce\PluginFramework\v5_10_3\SV_WC_Payment_Gateway_API_Response::transaction_held()
     */
    public function transaction_held()
    {
        return false;
    }
    /**
     * {@inheritDoc}
     * @see \SkyVerge\WooCommerce\PluginFramework\v5_10_3\SV_WC_Payment_Gateway_API_Customer_Response::get_customer_id()
     */
    public function get_customer_id()
    {
        return null;
    }

}
