<?php
/**
 * WooCommerce First Atlantic Commerce Gateway
 *
 */

use SkyVerge\WooCommerce\PluginFramework\v5_10_3 as Framework;

defined( 'ABSPATH' ) or exit;

/**
 * Abstract Payment Form
 */
abstract class WC_FirstAtlanticCommerce_Payment_Form extends Framework\SV_WC_Payment_Gateway_Payment_Form {

    const TEXT_DOMAIN = 'woocommerce-gateway-first-atlantic-commerce';

	public function __construct( $gateway ) {

		$this->gateway = $gateway;

		$this->add_hooks();
	}

	/**
	 * Render a test amount input field that can be used to override the order total
	 * when using the gateway in sandbox mode. The order total can then be set to
	 * various amounts to simulate various authorization/settlement responses
	 */
	public function render_payment_form_description() {

		parent::render_payment_form_description();

		if ( $this->get_gateway()->is_test_environment() && ! is_add_payment_method_page() ) {

			$id = 'wc-' . $this->get_gateway()->get_id_dasherized() . '-test-amount';

			?>
			<p class="form-row">
				<label for="<?php echo esc_attr( $id ); ?>">Test Amount <span style="font-size: 10px;" class="description">- Enter a test amount or leave blank to use the order total.</span></label>
				<input type="text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $id ); ?>" />
			</p>
			<?php
		}
	}

}
