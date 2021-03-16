<?php
/**
 * WooCommerce First Atlantic Commerce Gateway
 */

defined( 'ABSPATH' ) or exit;

class WC_FirstAtlanticCommerce_Direct_Payment_Form extends WC_FirstAtlanticCommerce_Payment_Form {

    const TEXT_DOMAIN = 'woocommerce-gateway-first-atlantic-commerce';


	/**
	 * Override the default form fields
	 *
	 * @return array credit card form fields
	 */
	protected function get_credit_card_fields() {

		$fields = parent::get_credit_card_fields();

		foreach ( array( 'card-number', 'card-expiry', 'card-csc' ) as $field_key ) {

			if ( isset( $fields[ $field_key ] ) ) {
                //TODO - Customize form
			}
		}

		// adjust expiry date label
		$fields['card-expiry']['label'] = esc_html__( 'Expiration (MMYY)', self::TEXT_DOMAIN );

		return $fields;
	}

	/**
	 * Gets the enabled card types
	 *
	 * @return array
	 */
	protected function get_enabled_card_types() {

		$types = array_map( '\\SkyVerge\\WooCommerce\\PluginFramework\\v5_10_3\\SV_WC_Payment_Gateway_Helper::normalize_card_type', $this->get_gateway()->get_card_types() );

		return $types;
	}

	/**
	 * Renders hidden inputs for the handling 3D Secure transactions.
	 */
	public function render_payment_fields() {
	    ?>
	    <script>
		(function(){
			jQuery("#place_order").click(function(e){
				jQuery("form[name='checkout']").off();
                jQuery("form[name='checkout']").attr('action','<?= get_site_url()."/checkout"; ?>');

                return true;
			});
		}());
	    </script>
	    <?php

		$fields = [
			'card-type',
		];

		foreach ( $fields as $field ) {
			echo '<input type="hidden" id="wc-' . $this->get_gateway()->get_id_dasherized() . '-' . esc_attr( $field ) . '" name="wc-' . $this->get_gateway()->get_id_dasherized() . '-' . esc_attr( $field ) . '" value="'.$value.'" />';
		}

		parent::render_payment_fields();
	}
}
