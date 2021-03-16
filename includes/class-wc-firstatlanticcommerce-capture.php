<?php
/**
 * WooCommerce First Atlantic Commerce Gateway
 *
 */

namespace WC_FirstAtlanticCommerce;

use SkyVerge\WooCommerce\PluginFramework\v5_10_3 as Framework;

defined( 'ABSPATH' ) or exit;

/**
 * The capture handler.
 */
class Capture extends Framework\Payment_Gateway\Handlers\Capture {


	/**
	 * Determines if an order's authorization has expired.
	 *
	 * @param \WC_Order $order
	 * @return bool
	 */
	public function has_order_authorization_expired( \WC_Order $order ) {

		if ( ! $this->get_gateway()->get_order_meta( $order, 'trans_id' ) ) {
			$this->get_gateway()->update_order_meta( $order, 'trans_id', $order->get_transaction_id( 'edit' ) );
		}

		$date_created = $order->get_date_created( 'edit' );

		if ( $date_created && ! $this->get_gateway()->get_order_meta( $order, 'trans_date' ) ) {
			$this->get_gateway()->update_order_meta( $order, 'trans_date', $date_created->date( 'Y-m-d H:i:s' ) );
		}

		return parent::has_order_authorization_expired( $order );
	}


	/**
	 * Determines if an order is eligible for capture.
	 *
	 * @param \WC_Order $order order object
	 * @return bool
	 */
	public function order_can_be_captured( \WC_Order $order ) {

		// if v1 never set the capture status, assume it has been captured
		if ( ! in_array( $this->get_gateway()->get_order_meta( $order, 'charge_captured' ), array( 'yes', 'no' ), true ) ) {
			$this->get_gateway()->update_order_meta( $order, 'charge_captured', 'yes' );
		}

		return parent::order_can_be_captured( $order );
	}

}
