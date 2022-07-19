<?php

use SkyVerge\WooCommerce\PluginFramework\v5_10_3 as Framework;
use Omnipay\FirstAtlanticCommerce\FACGateway;
use Omnipay\FirstAtlanticCommerce\Message\AbstractRequest;
use Omnipay\FirstAtlanticCommerce\Support\CreditCard;
use Omnipay\FirstAtlanticCommerce\Constants;

defined( 'ABSPATH' ) or exit;

abstract class WC_FirstAtlanticCommerce_API_Request implements Framework\SV_WC_Payment_Gateway_API_Request {


	protected $resource;

	protected $callback;

	protected $request_data = array();

	protected $order;

	/** @var Omnipay\FirstAtlanticCommerce\FACGateway **/
	protected $FACAPI;

	/** @var Omnipay\FirstAtlanticCommerce\Message\AbstractRequest **/
	protected $FACRequest;

	protected $FACOrderNumber;
	protected $FACCreditCard;
	protected $MerchantResponseURL;
	protected $CardHolderResponseURL;

    /** @var \Omnipay\FirstAtlanticCommerce\Support\TransactionCode */
    protected $TransactionCodes;

	public function __construct( $order = null, \Omnipay\FirstAtlanticCommerce\FACGateway $FACAPI, array $DefaultTransactionCodes) {

		$this->order = $order;
		$this->FACAPI = $FACAPI;
		$this->MerchantResponseURL = get_site_url()."/wc-api/".WC_FirstAtlanticCommerce::CREDIT_CARD_GATEWAY_ID;
		$this->CardHolderResponseURL = $this->MerchantResponseURL;
        $this->TransactionCodes = new \Omnipay\FirstAtlanticCommerce\Support\TransactionCode($DefaultTransactionCodes);


		$this->FACOrderNumber = $this->order->get_order_key()."|".$this->order->get_order_number();

		if ($FACAPI->getIntegrationOption() == Constants::GATEWAY_INTEGRATION_DIRECT)
		{
    		$this->FACCreditCard = new CreditCard([
    		    'number'=>$this->get_order()->payment->account_number,
    		    'cvv'=>$this->get_order()->payment->csc,
    		    'expiryMonth'=>$this->get_order()->payment->exp_month,
    		    'expiryYear'=>$this->get_order()->payment->exp_year
    		]);

    		$this->FACCreditCard->setBillingCountry($this->get_order_prop( 'billing_country' ));
		}
	}

	public function getFACAPI()
	{
	    return $this->FACAPI;
	}

	public function getFACRequest()
	{
	    return $this->FACRequest;
	}

	public function getFACOrderNumber()
	{
	    return $this->FACOrderNumber;
	}

	public function getFACCreditCard()
	{
	    return $this->FACCreditCard;
	}

	public function getMerchantResponseURL()
	{
	    return $this->MerchantResponseURL;
	}

	protected function set_resource( $resource ) {

		$this->resource = $resource;
	}

	public function get_resource() {

		return $this->resource;
	}

	protected function set_callback( $callback ) {

		$this->callback = $callback;
	}

	public function get_callback() {

		return $this->callback;
	}

	public function get_callback_params() {

		switch ( $this->get_callback() ) {

			// these API calls use 2 callback parameters
			case 'submitForSettlement':
			case 'refund':
			case 'update':
				return $this->get_data();

			// all others use a single callback param
			default:
				return array( $this->get_data() );
		}
	}

	public function to_string() {

		return print_r( $this->get_data(), true );
	}

	public function to_string_safe() {

		return $this->to_string();
	}

	public function get_data() {

		$this->request_data = apply_filters( 'wc_firstatlanticcommerce_api_request_data', $this->request_data, $this );

		$this->remove_empty_data();

		return $this->request_data;
	}


	/**
	 * Remove null or blank string values from the request data (up to 2 levels deep)
	 */
	protected function remove_empty_data() {

		foreach ( (array) $this->request_data as $key => $value ) {

			if ( is_array( $value ) ) {

				if ( empty( $value ) ) {

					unset( $this->request_data[ $key ] );

				} else {

					foreach ( $value as $inner_key => $inner_value ) {

						if ( is_null( $inner_value ) || '' === $inner_value ) {
							unset( $this->request_data[ $key ][ $inner_key ] );
						}
					}
				}

			} else {

				if ( is_null( $value ) || '' === $value ) {
					unset( $this->request_data[ $key ] );
				}
			}
		}
	}

	public function get_order_prop( $prop ) {

		$order  = $this->get_order();
		$method = "get_{$prop}";

		return $order instanceof \WC_Order && is_callable( [ $order, $method ] ) ? $order->$method( 'edit' ) : '';
	}

	public function get_order() {

		return $this->order;
	}

	public function get_method() { }

	public function get_path() { }

	public function get_params() {

		return array();
	}


}
