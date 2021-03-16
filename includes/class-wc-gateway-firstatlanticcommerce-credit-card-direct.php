<?php
/**
 * WooCommerce First Atlantic Commerce Gateway
 */

use SkyVerge\WooCommerce\PluginFramework\v5_10_3 as Framework;
use OmniPay\FirstAtlanticCommerce\Support\ThreeDSResponse;

defined( 'ABSPATH' ) or exit;

class WC_Gateway_FirstAtlanticCommerce_Credit_Card_Direct extends WC_Gateway_FirstAtlanticCommerce {


	const TEXT_DOMAIN = 'woocommerce-gateway-first-atlantic-commerce';

	/** @var string require CSC field */
	protected $require_csc;

	/** @var string fraud tool to use */
	protected $fraud_tool;

	/** @var string kount merchant ID */
	protected $kount_merchant_id;

	/** @var string 3D Secure enabled */
	protected $threed_secure_enabled;

	/** @var array 3D Secure card types */
	protected $threed_secure_card_types = array();

	/** @var bool 3D Secure available */
	protected $threed_secure_available;

	protected $handled_response;


	/**
	 * Initialize the gateway
	 *
	 */
	public function __construct() {

	    add_filter( 'wc_'.WC_FirstAtlanticCommerce::CREDIT_CARD_GATEWAY_ID.'_icon', 'facGatewayIcon' );

		parent::__construct(
		    WC_FirstAtlanticCommerce::CREDIT_CARD_GATEWAY_ID,
			wc_firstatlanticcommerce(),
			array(
				'method_title'       => __( 'First Atlantic Commerce', self::TEXT_DOMAIN ),
				'method_description' => __( 'Allow customers to securely pay using their credit card via First Atlantic Commerce.', self::TEXT_DOMAIN ),
				'supports'           => array(
					self::FEATURE_PRODUCTS,
					self::FEATURE_CARD_TYPES,
					self::FEATURE_PAYMENT_FORM,
					//self::FEATURE_TOKENIZATION,
					self::FEATURE_CREDIT_CARD_CHARGE,
					self::FEATURE_CREDIT_CARD_CHARGE_VIRTUAL,
					self::FEATURE_CREDIT_CARD_AUTHORIZATION,
					self::FEATURE_CREDIT_CARD_CAPTURE,
					self::FEATURE_DETAILED_CUSTOMER_DECLINE_MESSAGES,
					self::FEATURE_REFUNDS,
					self::FEATURE_VOIDS,
					self::FEATURE_CUSTOMER_ID,
				),
				'payment_type'       => self::PAYMENT_TYPE_CREDIT_CARD,
				'environments'       => $this->get_environments(),
				'card_types' => array(
					'VISA'    => 'Visa',
					'MC'      => 'MasterCard',
				),
			)
		);

		// callback for 3DS
		add_action( 'woocommerce_api_'.WC_FirstAtlanticCommerce::CREDIT_CARD_GATEWAY_ID, array( $this, 'threeds_authorize_callback'));
	}

	public function threeds_authorize_callback()
	{
	    try {
    	    require_once $this->get_plugin()->get_plugin_path() . '/src/Support/ThreeDSResponse.php';

    	    $ThreeDSResponse = new ThreeDSResponse($this->get_merchant_password(), $_POST);

    	    $response = $this->get_api()->handle_3ds_response($ThreeDSResponse);
    	    $this->handled_response = $response;

    	    $FACOrderConfirm = explode("|", $ThreeDSResponse->getOrderID());
    	    $WC_OrderID = $FACOrderConfirm[1];
    	    $WC_OrderKey = $FACOrderConfirm[0];

            $order = $this->get_order($WC_OrderID);

    	    $this->do_transaction($order);

    	    wp_safe_redirect( get_site_url()."/checkout/order-received/".$WC_OrderID."/?key=".$WC_OrderKey);
	    } catch (Exception $e)
	    {
	        wp_safe_redirect( get_site_url()."/checkout");
	    }

	    exit;
	}


	/**
	 * Enqueue credit card method specific scripts, currently:
	 *
	 * + Fraud tool library
	 *
	 * @see SV_WC_Payment_Gateway::enqueue_gateway_assets()
	 */
	public function enqueue_gateway_assets() {

		// advanced/kount fraud tool
		if ( $this->is_advanced_fraud_tool_enabled() ) {

			// enqueue braintree-data.js library
			//wp_enqueue_script( 'braintree-data', 'https://js.braintreegateway.com/v1/braintree-data.js', array( 'braintree-js-client' ), WC_Braintree::VERSION, true );

			// adjust the script tag to add async attribute
			//add_filter( 'clean_url', array( $this, 'adjust_fraud_script_tag' ) );

			// this script must be rendered to the page before the braintree-data.js library, hence priority 1
			//add_action( 'wp_print_footer_scripts', [ $this, 'render_fraud_js' ], 1 );
		}

		if ( $this->is_available() && $this->is_payment_form_page() ) {

			parent::enqueue_gateway_assets();

		}
	}

	/**
	 * Initializes the payment form handler.
	 *
	 * @return \WC_FirstAtlanticCommerce_Direct_Payment_Form
	 */
	protected function init_payment_form_instance() {

		return new \WC_FirstAtlanticCommerce_Direct_Payment_Form( $this );
	}


	/**
	 * Add credit card method specific form fields
	 *
	 * + Fraud tool settings
	 *
	 * @return array
	 */
	protected function get_method_form_fields() {

		$fraud_tool_options = array(
			'basic'    => __( 'Basic', self::TEXT_DOMAIN ),
			'advanced' => __( 'Advanced', self::TEXT_DOMAIN ),
		);

		if ( $this->is_kount_supported() ) {
			$fraud_tool_options['kount_direct'] = __( 'Kount Direct', self::TEXT_DOMAIN );
		}

		$fields = array(

			// fraud tools
			'fraud_settings_title' => array(
				'title' => __( 'Fraud Settings', self::TEXT_DOMAIN ),
				'type'  => 'title',
			),
			'fraud_tool'           => array(
				'title'    => __( 'Fraud Tool', self::TEXT_DOMAIN ),
				'type'     => 'select',
				'class'    => 'js-fraud-tool',
				'desc_tip' => __( 'Select the fraud tool you want to use. Basic is enabled by default and requires no additional configuration. Advanced requires additional configuration.', self::TEXT_DOMAIN ),
				'options'  => $fraud_tool_options,
			),
			'kount_merchant_id'    => array(
				'title'    => __( 'Kount merchant ID', self::TEXT_DOMAIN ),
				'type'     => 'text',
				'class'    => 'js-kount-merchant-id',
				'desc_tip' => __( 'Contact your account management team to get this.', self::TEXT_DOMAIN ),
			),
		);

		$fields = array_merge( $fields, $this->get_3d_secure_fields() );

		return array_merge( parent::get_method_form_fields(), $fields );
	}


	/**
	 * Gets the 3D Secure settings fields.
	 *
	 * @return array
	 */
	protected function get_3d_secure_fields() {

		$card_types = $default_card_types = array(
			Framework\SV_WC_Payment_Gateway_Helper::CARD_TYPE_MASTERCARD => Framework\SV_WC_Payment_Gateway_Helper::payment_type_to_name( Framework\SV_WC_Payment_Gateway_Helper::CARD_TYPE_MASTERCARD ),
			Framework\SV_WC_Payment_Gateway_Helper::CARD_TYPE_VISA       => Framework\SV_WC_Payment_Gateway_Helper::payment_type_to_name( Framework\SV_WC_Payment_Gateway_Helper::CARD_TYPE_VISA ),
		);

		unset( $default_card_types[ Framework\SV_WC_Payment_Gateway_Helper::CARD_TYPE_AMEX ] );

		$fields = array(
			'threed_secure_title' => array(
				'title'       => __( '3D Secure', self::TEXT_DOMAIN ),
				'type'        => 'title',
				'description' => sprintf( __( '3D Secure benefits cardholders and merchants by providing an additional layer of verification using Verified by Visa and MasterCard SecureCode. %1$sLearn more about 3D Secure%2$s.', self::TEXT_DOMAIN ), '<a href="' . esc_url( $this->get_plugin()->get_documentation_url() ) . '#3d-secure' . '">', '</a>' ),
			),
			'threed_secure_card_types' => array(
				'title'       => __( 'Supported Card Types', self::TEXT_DOMAIN ),
				'type'        => 'multiselect',
				'class'       => 'wc-enhanced-select',
				'description' => __( '3D Secure validation will only occur for these cards.', self::TEXT_DOMAIN ),
				'default'     => array_keys( $default_card_types ),
				'options'     => $card_types,
			),
		);

		return $fields;
	}

	protected function add_csc_form_fields( $form_fields ) {

		$form_fields['require_csc'] = array(
			'title'   => __( 'Card Verification (CSC)', self::TEXT_DOMAIN ),
			'label'   => __( 'Display and Require the Card Security Code (CVV/CID) field on checkout', self::TEXT_DOMAIN ),
			'type'    => 'checkbox',
			'default' => 'yes',
		);

		return $form_fields;
	}


	/**
	 * Returns true if the CSC field should be displayed and required at checkout
	 *
	 */
	public function is_csc_required() {

		return 'yes' === $this->require_csc;
	}


	/**
	 * Override the standard CSC enabled method to return the value of the csc_required()
	 * @return bool
	 */
	public function csc_enabled() {

		return $this->is_csc_required();
	}


	/**
	 * Render credit card method specific JS to the settings page, currently:
	 *
	 * + Hide/show Fraud tool kount merchant ID setting
	 *
	 * @see WC_Gateway_FirstAtlanticCommerce::admin_options()
	 */
	public function admin_options() {

		parent::admin_options();

		ob_start();
		?>
		// show/hide the kount merchant ID field based on the fraud tools selection
		$( 'select.js-fraud-tool' ).change( function() {

			var $kount_id_row = $( '.js-kount-merchant-id' ).closest( 'tr' );

			if ( 'kount_direct' === $( this ).val() ) {
				$kount_id_row.show();
			} else {
				$kount_id_row.hide();
			}
		} ).change();
		<?php

		wc_enqueue_js( ob_get_clean() );

		// 3D Secure setting handler
		ob_start();
		?>

		if ( ! <?php echo (int) $this->is_3d_secure_available(); ?> ) {
			$( '#woocommerce_firstatlanticcommerce_credit_card_threed_secure_title' ).hide().next( 'p' ).hide().next( 'table' ).hide();
		}

		<?php

		wc_enqueue_js( ob_get_clean() );
	}


	/**
	 * Add credit card specific data to the order
	 *
	 * @param \WC_Order|int $order order
	 * @return \WC_Order
	 */
	public function get_order( $order ) {

		$order = parent::get_order( $order );

		if ( empty( $order->payment->card_type ) ) {
			$order->payment->card_type = Framework\SV_WC_Payment_Gateway_Helper::normalize_card_type( Framework\SV_WC_Helper::get_posted_value( 'wc-' . $this->get_id_dasherized() . '-card-type' ) );
		}

		return $order;
	}

	protected function do_credit_card_transaction( $order, $response = null ) {

		if ( is_null( $response ) ) {

		    if (is_null($this->handled_response)){
    			$response = $this->perform_credit_card_charge( $order ) ? $this->get_api()->credit_card_charge( $order ) : $this->get_api()->credit_card_authorization( $order );
		    }
		    else {
		        $response = $this->handled_response;
		    }

		    if ( $response->transaction_approved() ) {
		        $order->payment->account_number = $response->get_masked_number();
		        $order->payment->last_four      = $response->get_last_four();
		        $order->payment->card_type      = Framework\SV_WC_Payment_Gateway_Helper::card_type_from_account_number( $response->get_masked_number() );
		        $order->payment->exp_month      = $response->get_exp_month();
		        $order->payment->exp_year       = $response->get_exp_year();
		    }
		}

		return parent::do_credit_card_transaction( $order, $response );
	}


	/**
	 * Adds any gateway-specific transaction data to the order, for credit cards
	 * this is:
	 *
	 * + risk data (if available)
	 *
	 * @see SV_WC_Payment_Gateway_Direct::add_transaction_data()
	 * @param \WC_Order $order the order object
	 * @param \WC_FirstAtlanticCommerce_API_Credit_Card_Transaction_Response $response transaction response
	 */
	public function add_payment_gateway_transaction_data( $order, $response ) {

		// add risk data
		if ( $this->is_advanced_fraud_tool_enabled() && $response->has_risk_data() ) {
			$this->update_order_meta( $order, 'risk_id', $response->get_risk_id() );
			$this->update_order_meta( $order, 'risk_decision', $response->get_risk_decision() );
		}
	}


	/** Refund/Void feature ***************************************************/

	protected function maybe_void_instead_of_refund( $order, $response ) {
        return false;
	}


	/** Fraud Tool feature ****************************************************/


	/**
	 * Renders the fraud tool script.
	 *
	 * Note this is hooked to load at high priority (1) so that it's rendered prior to the braintree.js/braintree-data.js scripts being loaded
	 * @link https://developers.braintreepayments.com/guides/advanced-fraud-tools/overview
	 *
	 * @internal
	 *
	 * @since 3.0.0
	 */
	public function render_fraud_js() {

		$environment = 'BraintreeData.environments.' . ( $this->is_test_environment() ? 'sandbox' : 'production' );

		if ( $this->is_kount_direct_enabled() && $this->get_kount_merchant_id() ) {
			$environment .= '.withId( kount_id )'; // kount_id will be defined before this is output
		}

		// TODO: consider moving this to it's own file

		?>
		<script>
			( function( $ ) {

				var form_id;
				var kount_id = '<?php echo esc_js( $this->get_kount_merchant_id() ); ?>';

				if ( $( 'form.checkout' ).length ) {

					// checkout page
					// WC does not set a form ID, use an existing one if available
					form_id = $( 'form.checkout' ).attr( 'id' ) || 'checkout';

					// otherwise set it ourselves
					if ( 'checkout' === form_id ) {
						$( 'form.checkout' ).attr( 'id', form_id );
					}

				} else if ( $( 'form#order_review' ).length ) {

					// checkout > pay page
					form_id = 'order_review'

				} else if ( $( 'form#add_payment_method' ).length ) {

					// add payment method page
					form_id = 'add_payment_method'
				}

				if ( ! form_id ) {
					return;
				}

				window.onBraintreeDataLoad = function () {
					BraintreeData.setup( '<?php echo esc_js( $this->get_merchant_id() ); ?>', form_id, <?php echo esc_js( $environment ); ?> );
				}

			} ) ( jQuery );
		</script>
		<?php
	}


	/**
	 * Add an async attribute to the braintree-data.js script tag, there's no
	 * way to do this when enqueing so it must be done manually here
	 *
	 * @since 3.0.0
	 * @param string $url cleaned URL from esc_url()
	 * @return string
	 */
	public function adjust_fraud_script_tag( $url ) {

		if ( Framework\SV_WC_Helper::str_exists( $url, 'braintree-data.js' ) ) {

			$url = "{$url}' async='true";
		}

		return $url;
	}


	/**
	 * Return the enabled fraud tool setting, either 'basic', 'advanced', or
	 * 'kount_direct'
	 *
	 * @return string
	 */
	public function get_fraud_tool() {

		return $this->fraud_tool;
	}


	/**
	 * Return true if advanced fraud tools are enabled (either advanced or
	 * kount direct)
	 *
	 * @return bool
	 */
	public function is_advanced_fraud_tool_enabled() {

		return 'advanced' === $this->get_fraud_tool() || 'kount_direct' === $this->get_fraud_tool();
	}


	/**
	 * Return true if the Kount Direct fraud tool is enabled
	 *
	 * @return bool
	 */
	public function is_kount_direct_enabled() {

		return $this->is_kount_supported() && 'kount_direct' === $this->get_fraud_tool();
	}


	/**
	 * Get the Kount merchant ID, only used when the Kount Direct fraud tool
	 * is enabled
	 *
	 * @return string
	 */
	public function get_kount_merchant_id() {

		return $this->kount_merchant_id;
	}


	/**
	 * Determines if Kount is supported.
	 *
	 * @return bool
	 */
	public function is_kount_supported() {

		return false;
	}


	/** 3D Secure feature *****************************************************/

	/**
	 * Determines if 3D Secure is available for the merchant account.
	 *
	 * @return bool
	 */
	public function is_3d_secure_available() {
	    return true;
	}

	public function is_3d_secure_enabled() {
	    return apply_filters( 'wc_' . $this->get_id() . '_enable_3d_secure', true );
	}

	/**
	 * Determines if the passed card type supports 3D Secure.
	 *
	 * This checks the card types configured in the settings.
	 *
	 * @param string $card_type card type
	 * @return bool
	 */
	public function card_type_supports_3d_secure( $card_type ) {

		return in_array( Framework\SV_WC_Payment_Gateway_Helper::normalize_card_type( $card_type ), $this->get_3d_secure_card_types(), true );
	}


	/**
	 * Gets the card types to validate with 3D Secure
	 *
	 * @return array
	 */
	public function get_3d_secure_card_types() {

		return (array) $this->get_option( 'threed_secure_card_types' );
	}


	/**
	 * @see SV_WC_Payment_Gateway_Direct::validate_fields()
	 * @return bool
	 */
	public function validate_fields() {

		$is_valid = parent::validate_fields();

        //TODO Additional field validations

		return $is_valid;
	}


}

function facGatewayIcon()
{
    return get_site_url()."/wp-content/plugins/woocommerce-gateway-first-atlantic-commerce/assets/fac-visa-mc.png";
}
