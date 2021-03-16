<?php

use SkyVerge\WooCommerce\PluginFramework\v5_10_3 as Framework;


defined( 'ABSPATH' ) or exit;

abstract class WC_FirstAtlanticCommerce_API_Response implements Framework\SV_WC_API_Response {

	/** @var mixed raw response */
	protected $response;

	protected $response_type;

	/** @var \Omnipay\FirstAtlanticCommerce\Support\CreditCardTransactionResults **/
	protected $CreditCardTransactionResults;

	public function __construct( $response, $response_type ) {

		$this->response = $response;
		$this->response_type = $response_type;

		if ($response instanceof Omnipay\FirstAtlanticCommerce\Message\AuthorizeResponse) {
		    $this->CreditCardTransactionResults = $this->response->getCreditCardTransactionResults();
		}
	}

	public function transaction_approved() {
        return $this->response->isSuccessful();

	}

	public function get_status_code() {

		return $this->transaction_approved() ? $this->get_success_status_info( 'code' ) : $this->get_failure_status_info( 'code' );
	}

	public function get_status_message() {

		return $this->transaction_approved() ? $this->get_success_status_info( 'message' ) : $this->get_failure_status_info( 'message' );
	}

	public function get_success_status_info( $type ) {

		$status = [
		    "code"=>$this->response->getCode(),
		    "message"=>$this->response->getMessage()
		];

		return isset( $status[ $type ] ) ? $status[ $type ] : null;
	}

	public function get_failure_status_info( $type ) {

		return $this->get_success_status_info($type);
	}

	public function get_user_message() {

		return $this->get_status_message();
	}

	public function to_string() {

		return print_r( $this->response, true );
	}

	public function to_string_safe() {

		// not implemented
		return $this->to_string();
	}

	protected function get_response_type() {

		return $this->response_type;
	}

	public function get_payment_type() {

		return $this->get_response_type();
	}

	protected function is_credit_card_response() {
		return 'credit-card' === $this->get_response_type();
	}

	/**
	 * {@inheritDoc}
	 * @see \SkyVerge\WooCommerce\PluginFramework\v5_10_3\SV_WC_Payment_Gateway_API_Response::transaction_held()
	 */
	public function transaction_held()
	{
	    return false;
	}

}
