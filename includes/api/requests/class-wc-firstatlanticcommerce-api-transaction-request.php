<?php

use Omnipay\FirstAtlanticCommerce\Constants;
use Omnipay\FirstAtlanticCommerce\Support\TransactionCode;

use SkyVerge\WooCommerce\PluginFramework\v5_10_3 as Framework;

defined( 'ABSPATH' ) or exit;

class WC_FirstAtlanticCommerce_API_Transaction_Request extends WC_FirstAtlanticCommerce_API_Request {


	/** auth and capture transaction type */
	const AUTHORIZE_AND_CAPTURE = true;

	/** authorize-only transaction type */
	const AUTHORIZE_ONLY = false;

	/**
	 * Creates a credit card charge request
	 */
	public function create_credit_card_charge() {
	    $FACData = [
	        'card'=>$this->getFACCreditCard(),
	        'amount' => $this->get_order()->payment_total,
	        'currency' => $this->get_order_prop('currency'),
	        'transactionId' => $this->getFACOrderNumber(),
	    ];

	    // All transactions should be 3DS Auth
	    $FACData['merchantResponseURL'] = $this->MerchantResponseURL;
	    $FACData[Constants::AUTHORIZE_OPTION_3DS] = true;

	    $this->FACRequest = $this->FACAPI->purchase($FACData);
	}

	/**
	 * Creates a credit card auth request
	 */
	public function create_credit_card_auth() {

		$FACData = [
		    'card'=>$this->getFACCreditCard(),
		    'amount' => $this->get_order()->payment_total,
		    'currency' => $this->get_order_prop('currency'),
		    'transactionId' => $this->getFACOrderNumber(),
		];

		// All transactions should be 3DS Auth
		$FACData['merchantResponseURL'] = $this->MerchantResponseURL;
		$FACData[Constants::AUTHORIZE_OPTION_3DS] = true;

		$this->FACRequest = $this->FACAPI->authorize($FACData);
	}


	/**
	 * Capture funds for a previous credit card authorization
	 */
	public function create_credit_card_capture() {

		$this->set_resource( 'transaction' );
		$this->set_callback( 'submitForSettlement' );

		$FACData = [
		    'amount' => $this->get_order()->capture->amount,
		    'transactionId' => $this->getFACOrderNumber(),
		];

		$this->FACRequest = $this->FACAPI->capture($FACData);
	}


	/**
	 * Refund funds from a previous transaction
	 */
	public function create_refund() {
	    $this->set_resource( 'transaction' );
	    $this->set_callback( 'refund' );

	    $FACData = [
	        'amount' => $this->get_order()->refund->amount,
	        'transactionId' => $this->getFACOrderNumber(),
	    ];

	    $this->FACRequest = $this->FACAPI->refund($FACData);
	}


	/**
	 * Void a previous transaction
	 */
	public function create_void() {

		$this->set_resource( 'transaction' );
		$this->set_callback( 'void' );

		$FACData = [
		    'amount' => $this->get_order()->refund->amount,
		    'transactionId' => $this->getFACOrderNumber(),
		];

		$this->FACRequest = $this->FACAPI->void($FACData);
	}
}
