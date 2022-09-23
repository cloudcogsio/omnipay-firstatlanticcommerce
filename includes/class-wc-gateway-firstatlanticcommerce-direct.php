<?php
/**
 * WooCommerce First Atlantic Commerce Gateway
 */

use SkyVerge\WooCommerce\PluginFramework\v5_10_3 as Framework;

defined( 'ABSPATH' ) or exit;

class WC_Gateway_FirstAtlanticCommerce extends Framework\SV_WC_Payment_Gateway_Direct {

    const TEXT_DOMAIN = 'woocommerce-gateway-first-atlantic-commerce';

	/** sandbox environment ID */
	const ENVIRONMENT_SANDBOX = 'sandbox';

	/** @var string production merchant ID */
	protected $merchant_id;

	/** @var string production public key */
	protected $merchant_password;

	/** @var string sandbox merchant ID */
	protected $sandbox_merchant_id;

	/** @var string sandbox public key */
	protected $sandbox_merchant_password;

	/** @var WC_FirstAtlanticCommerce_OmniPay_API instance */
	protected $api;


	/**
	 * @param string $id the gateway id
	 * @param Framework\SV_WC_Payment_Gateway_Plugin $plugin the parent plugin class
	 * @param array $args gateway arguments
	 */
	public function __construct( $id, $plugin, $args ) {

		parent::__construct( $id, $plugin, $args );

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts'] );

		add_action( 'add_meta_boxes_shop_order', array( $this, 'add_box' ) );
	}

	public function add_box()
	{
		global $post;
		$order = $this->get_order($post->ID);

		if ($order->get_payment_method() == $this->id) {
			add_meta_box( 'woocommerce-gateway-first-atlantic-commerce-binlist-box', 'binlist.net Info', array(
				$this,
				'create_box_content_binlist'
			), 'shop_order', 'side', 'low' );
			add_meta_box( 'woocommerce-gateway-first-atlantic-commerce-ipgeolocation-box', 'IP Geolocation Info', array(
				$this,
				'create_box_content_ipgeolocation'
			), 'shop_order', 'side', 'low' );
		}
	}

	public function create_box_content_binlist()
	{
		global $post;
		$order = $this->get_order($post->ID);
		$binlistData = json_decode($order->get_meta('wc_firstatlanticcommerce_binlist_info'));
		if ($binlistData != null)
		{
			print "<ul>";
			print "<li><img style='width:30px' src='".get_site_url()."/wp-content/plugins/woocommerce-gateway-first-atlantic-commerce/vendor/skyverge/wc-plugin-framework/woocommerce/payment-gateway/assets/images/card-".$binlistData->scheme.".svg' />&nbsp;<img style='width: 30px' src='https://flags.fmcdn.net/data/flags/normal/".strtolower($binlistData->country->alpha2).".png' /></li>";
			print "<li><em>".$binlistData->brand."</em></li>";
			print "<li><em>".$binlistData->bank->name."</em></li>";
			print "<li><em>".$binlistData->country->name."</em></li>";
			print "</ul>";
		}
		else {
			print "<em>Not Available</em>";
		}
	}

	public function create_box_content_ipgeolocation()
	{
		global $post;
		$order = $this->get_order($post->ID);
		$geodata = json_decode($order->get_meta('wc_firstatlanticcommerce_ip_geolocation'));
		if ($geodata != null)
		{
			print "<ul>";
			print "<li><img style='width: 30px' src='https://flags.fmcdn.net/data/flags/normal/".strtolower($geodata->country_code2).".png' /></li>";
			print "<li><em>".$geodata->city."</em></li>";
			print "<li><em>".$geodata->country_name."</em></li>";
			print "</ul>";
		}
		else {
			print "<em>Not Available</em>";
		}
	}

	/**
	 * Enqueues admin scripts.
	 */
	public function enqueue_admin_scripts() {

		if ( $this->get_plugin()->is_plugin_settings() ) {

			wp_enqueue_script( 'wc-backbone-modal', null, [ 'backbone' ] );
		}
	}


	/**
	 * @see SV_WC_Payment_Gateway::enqueue_scripts()
	 */
	//TODO - Additional GW validation assets
	public function enqueue_gateway_assets() {

		if ( $this->is_available() ) {
			parent::enqueue_gateway_assets();
		}
	}

	/**
	 * @see SV_WC_Payment_Gateway_Direct::get_order()
	 * @param int $order order ID being processed
	 * @return \WC_Order object with payment and transaction information attached
	 */
	public function get_order( $order ) {

		$order = parent::get_order( $order );

		$order->payment->tokenize = $this->get_payment_tokens_handler()->should_tokenize();

		// fraud tool data as a JSON string, unslashed as WP slashes $_POST data which breaks the JSON
		$order->payment->device_data = wp_unslash( Framework\SV_WC_Helper::get_posted_value( 'device_data' ) );

		// test amount when in sandbox mode
		if ( $this->is_test_environment() && ( $test_amount = Framework\SV_WC_Helper::get_posted_value( 'wc-' . $this->get_id_dasherized() . '-test-amount' ) ) ) {
			$order->payment_total = Framework\SV_WC_Helper::number_format( $test_amount );
		}

		return $order;
	}


	/**
	 * Gets the order object with data added to process a refund.
	 *
	 * @see \SV_WC_Payment_Gateway::get_order_for_refund()
	 * @since 2.0.0
	 * @param \WC_Order $order the order object
	 * @param float $amount the refund amount
	 * @param string $reason the refund reason
	 * @return \WC_Order
	 */
	public function get_order_for_refund( $order, $amount, $reason ) {

		$order = parent::get_order_for_refund( $order, $amount, $reason );

		if ( empty( $order->refund->trans_id ) ) {

			$order->refund->trans_id = $order->get_transaction_id( 'edit' );
		}

		return $order;
	}


	/**
	 * Gets the capture handler.
	 *
	 * @return \WC_FirstAtlanticCommerce\Capture
	 */
	public function get_capture_handler() {

		require_once( $this->get_plugin()->get_plugin_path() . '/includes/class-wc-firstatlanticcommerce-capture.php' );

		return new \WC_FirstAtlanticCommerce\Capture( $this );
	}


	/** Admin settings methods ************************************************/


	/**
	 * Returns an array of form fields specific for this method
	 *
	 * @see SV_WC_Payment_Gateway::get_method_form_fields()
	 * @return array of form fields
	 */
	protected function get_method_form_fields() {

		return array(

			// production
			'merchant_id' => array(
				'title'    => __( 'FAC Merchant ID', self::TEXT_DOMAIN ),
				'type'     => 'text',
				'class'    => 'environment-field production-field',
			    'desc_tip' => __( 'The Merchant ID for your First Atlantic Commerce account.', self::TEXT_DOMAIN),
			),

			'merchant_password' => array(
			    'title'    => __( 'FAC Processing Password', self::TEXT_DOMAIN),
				'type'     => 'password',
				'class'    => 'environment-field production-field',
				'desc_tip' => __( 'The processing password for your First Atlantic Commerce account.', self::TEXT_DOMAIN ),
			),

			// sandbox
			'sandbox_merchant_id' => array(
				'title'    => __( 'Sandbox Merchant ID', self::TEXT_DOMAIN ),
				'type'     => 'text',
				'class'    => 'environment-field sandbox-field',
				'desc_tip' => __( 'The Merchant ID for your First Atlantic Commerce STAGING account.', self::TEXT_DOMAIN ),
			),

			'sandbox_merchant_password' => array(
				'title'    => __( 'Sandbox Processing Password', self::TEXT_DOMAIN ),
				'type'     => 'password',
				'class'    => 'environment-field sandbox-field',
				'desc_tip' => __( 'The processing password for your First Atlantic Commerce STAGING account.', self::TEXT_DOMAIN ),
			),

		    'hosted_page_title' => array(
		        'title'       => __( 'FAC Integration Option', self::TEXT_DOMAIN ),
		        'type'        => 'title',
		        'description' => esc_html__( 'Select the type of integration required. (*Hosted Page requires additional setup in your Merchant Account at FAC)', self::TEXT_DOMAIN ),
		    ),

		    'integration' => array(
		        'title'    => esc_html__( 'Integration', 'woocommerce-plugin-framework' ),
		        'type'     => 'select',
		        'default'  => 'direct',
		        'options'  => ['direct'=>'Direct API','hosted'=>'Hosted Page'],
		    ),

		    'page_set' => array(
		        'title'    => __( 'Hosted Page - Page Set', self::TEXT_DOMAIN ),
		        'type'     => 'text',
		        'class'    => 'environment-field production-field integration-field hosted-field',
		        'desc_tip' => __( 'The "Page Set" for your hosted checkout page', self::TEXT_DOMAIN),
		    ),

		    'page_name' => array(
		        'title'    => __( 'Hosted Page - Page Name', self::TEXT_DOMAIN),
		        'type'     => 'text',
		        'class'    => 'environment-field production-field integration-field hosted-field',
		        'desc_tip' => __( 'The "Page Name" for your hosted checkout page', self::TEXT_DOMAIN ),
		    ),

		    'sandbox_page_set' => array(
		        'title'    => __( 'ECM Hosted Page - Page Set', self::TEXT_DOMAIN ),
		        'type'     => 'text',
		        'class'    => 'environment-field sandbox-field integration-field hosted-field',
		        'desc_tip' => __( 'The "Page Set" for your hosted checkout page', self::TEXT_DOMAIN),
		    ),

		    'sandbox_page_name' => array(
		        'title'    => __( 'ECM Hosted Page - Page Name', self::TEXT_DOMAIN),
		        'type'     => 'text',
		        'class'    => 'environment-field sandbox-field integration-field hosted-field',
		        'desc_tip' => __( 'The "Page Name" for your hosted checkout page', self::TEXT_DOMAIN ),
		    ),

		);

	}


	/** Getters ***************************************************************/

	/**
	 * Returns true if the gateway is properly configured to perform transactions
	 *
	 * @see SV_WC_Payment_Gateway::is_configured()
	 * @return boolean true if the gateway is properly configured
	 */
	public function is_configured() {

		$is_configured = parent::is_configured();

		if ( ! $this->get_merchant_id() || ! $this->get_merchant_password() ) {
			$is_configured = false;
		}

		return $is_configured;
	}


	/**
	 * Returns true if the current page contains a payment form
	 *
	 * @return bool
	 */
	public function is_payment_form_page() {

		return ( is_checkout() && ! is_order_received_page() ) || is_checkout_pay_page() || is_add_payment_method_page();
	}


	/**
	 * Get the API object
	 *
	 * @see SV_WC_Payment_Gateway::get_api()
	 * @return \WC_FirstAtlanticCommerce_OmniPay_API instance
	 */
	public function get_api() {
	    if ( is_object( $this->api ) ) {
	        return $this->api;
	    }

	    $includes_path = $this->get_plugin()->get_plugin_path() . '/includes';

	    // main API class
	    require_once( $includes_path . '/api/class-wc-firstatlanticcommerce-omnipay-api.php' );

	    // requests
	    require_once( $includes_path . '/api/requests/abstract-wc-firstatlanticcommerce-api-request.php' );
	    require_once( $includes_path . '/api/requests/class-wc-firstatlanticcommerce-api-transaction-request.php' );

	    // responses
	    require_once( $includes_path . '/api/responses/abstract-wc-firstatlanticcommerce-api-response.php' );
	    require_once( $includes_path . '/api/responses/abstract-wc-firstatlanticcommerce-api-transaction-response.php' );
	    require_once( $includes_path . '/api/responses/class-wc-firstatlanticcommerce-api-credit-card-transaction-response.php' );
	    require_once( $includes_path . '/api/responses/class-wc-firstatlanticcommerce-api-hosted-credit-card-transaction-response.php' );
	    require_once( $includes_path . '/api/responses/class-wc-firstatlanticcommerce-api-3ds-transaction-response.php' );

	    return $this->api = new WC_FirstAtlanticCommerce_OmniPay_API( $this );
	}


	/**
	 * Returns true if the current gateway environment is configured to 'sandbox'
	 *
	 * @see SV_WC_Payment_Gateway::is_test_environment()
	 * @param string $environment_id optional environment id to check, otherwise defaults to the gateway current environment
	 * @return boolean true if $environment_id (if non-null) or otherwise the current environment is test
	 */
	public function is_test_environment( $environment_id = null ) {

		// if an environment is passed in, check that
		if ( ! is_null( $environment_id ) ) {
			return self::ENVIRONMENT_SANDBOX === $environment_id;
		}

		// otherwise default to checking the current environment
		return $this->is_environment( self::ENVIRONMENT_SANDBOX );
	}

	/**
	 * Determines if this is a gateway that supports charging virtual-only orders.
	 *
	 * @return bool
	 */
	public function supports_credit_card_charge_virtual() {
		return $this->supports( self::FEATURE_CREDIT_CARD_CHARGE_VIRTUAL );
	}

	/**
	 * Returns the merchant ID based on the current environment
	 *
	 * @param string $environment_id optional one of 'sandbox' or 'production', defaults to current configured environment
	 * @return string merchant ID
	 */
	public function get_merchant_id( $environment_id = null ) {

		if ( is_null( $environment_id ) ) {
			$environment_id = $this->get_environment();
		}

		return self::ENVIRONMENT_PRODUCTION === $environment_id ? $this->merchant_id : $this->sandbox_merchant_id;
	}


	/**
	 * Returns the merchant password based on the current environment
	 *
	 * @param string $environment_id optional one of 'sandbox' or 'production', defaults to current configured environment
	 * @return string merchant password
	 */
	public function get_merchant_password( $environment_id = null ) {

		if ( is_null( $environment_id ) ) {
			$environment_id = $this->get_environment();
		}

		return self::ENVIRONMENT_PRODUCTION === $environment_id ? $this->merchant_password : $this->sandbox_merchant_password;
	}

	public function get_hosted_page_set( $environment_id = null ) {

		if ( is_null( $environment_id ) ) {
			$environment_id = $this->get_environment();
		}

		return self::ENVIRONMENT_PRODUCTION === $environment_id ? $this->page_set : $this->sandbox_page_set;
	}

	public function get_hosted_page_name( $environment_id = null ) {

		if ( is_null( $environment_id ) ) {
			$environment_id = $this->get_environment();
		}

		return self::ENVIRONMENT_PRODUCTION === $environment_id ? $this->page_name : $this->sandbox_page_name;
	}


	/**
	 * Return an array of valid environments
	 *
	 * @return array
	 */
	public function get_environments() {

		return array( self::ENVIRONMENT_PRODUCTION => __( 'Production', self::TEXT_DOMAIN ), self::ENVIRONMENT_SANDBOX => __( 'Sandbox', self::TEXT_DOMAIN ) );
	}
}
