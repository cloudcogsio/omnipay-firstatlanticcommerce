<?php
/**
 * WooCommerce First Atlantic Commerce Gateway
 */

use SkyVerge\WooCommerce\PluginFramework\v5_10_3 as Framework;
use Omnipay\FirstAtlanticCommerce\Support\ThreeDSResponse;
use Omnipay\FirstAtlanticCommerce\FACGateway;
use Omnipay\FirstAtlanticCommerce\Constants;
use SkyVerge\WooCommerce\PluginFramework\v5_10_3\SV_WC_Helper;

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
					//self::FEATURE_PRODUCTS,
					//self::FEATURE_CARD_TYPES,
					self::FEATURE_PAYMENT_FORM,
					//self::FEATURE_TOKENIZATION,
					self::FEATURE_CREDIT_CARD_CHARGE,
					self::FEATURE_CREDIT_CARD_CHARGE_VIRTUAL,
					self::FEATURE_CREDIT_CARD_AUTHORIZATION,
					self::FEATURE_CREDIT_CARD_CAPTURE,
					//self::FEATURE_DETAILED_CUSTOMER_DECLINE_MESSAGES,
					self::FEATURE_REFUNDS,
					self::FEATURE_VOIDS,
					//self::FEATURE_CUSTOMER_ID,
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
	    if (isset($_GET['ID']) && isset($_GET['RespCode']) && isset($_GET['ReasonCode']))
	    {
	        try {
                $GW = new FACGateway();
                if($this->get_environment() == self::ENVIRONMENT_SANDBOX)
                {
                    $GW->setTestMode(true);
                }
                else {
                    $GW->setTestMode(false);
                }

                $HostedPageResultsResponse = $GW->hostedPageResults([
                    'securityToken'=>$_GET['ID'],
                    Constants::CONFIG_KEY_FACID => $this->get_merchant_id(),
                    Constants::CONFIG_KEY_FACPWD => $this->get_merchant_password(),
                ])->send();

                if($HostedPageResultsResponse->isSuccessful())
                {
                    $response = $this->get_api()->handle_hostedpage_response($HostedPageResultsResponse);
                    $this->handled_response = $response;

                    $FACOrderConfirm = explode("|", $HostedPageResultsResponse->getOrderNumber());

                    $WC_OrderID = $FACOrderConfirm[1];
                    $WC_OrderKey = $FACOrderConfirm[0];

                    $order = $this->get_order($WC_OrderID);

                    $this->do_transaction($order);

                    print("<script>window.opener.document.location.href = '".get_site_url()."/checkout/order-received/".$WC_OrderID."/?key=".$WC_OrderKey."';</script>");
                    print("<script>window.close();</script>");
                    exit;
                }
                else
                {
                        print "<h3>ERROR: ".$HostedPageResultsResponse->getCode()."</h3>";
                        print "<h4>".$HostedPageResultsResponse->getMessage()."</h4>";
                        print "<h5>Please contact us for assistance.</h5>";
                        print("<script>window.opener.document.location.href = '".get_site_url()."/checkout"."';</script>");
                        exit;
                }
	        } catch (Exception $e)
	        {
	            wp_safe_redirect( get_site_url()."/checkout");
	            exit;
	        }
	    }
	    else
	    {
    	    try {
        	    $ThreeDSResponse = new ThreeDSResponse($this->get_merchant_password(), $_POST);

        	    $response = $this->get_api()->handle_3ds_response($ThreeDSResponse);
        	    $this->handled_response = $response;

        	    $FACOrderConfirm = explode("|", $ThreeDSResponse->getOrderID());

        	    $WC_OrderID = $FACOrderConfirm[1];
        	    $WC_OrderKey = $FACOrderConfirm[0];

        	    $order = $this->get_order($WC_OrderID);

        	    $this->do_transaction($order);

        	    wp_safe_redirect( get_site_url()."/checkout/order-received/".$WC_OrderID."/?key=".$WC_OrderKey);
        	    exit;
    	    } catch (Exception $e)
    	    {
    	        wp_safe_redirect( get_site_url()."/checkout");
    	        exit;
    	    }

	    }


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
			//TODO enqueue kount js libraries
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
	 *
	 * + Fraud tool settings
	 *
	 * @return array
	 */
	protected function get_method_form_fields() {

		$fraud_tool_options = array(
		    'disabled' => __( 'Disabled', self::TEXT_DOMAIN),
			//'basic'    => __( 'Basic', self::TEXT_DOMAIN ),
			//'advanced' => __( 'Advanced', self::TEXT_DOMAIN ),
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
				'options'  => $fraud_tool_options,
			    'custom_attributes' => array('disabled' => 'disabled')
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
				'description' => __( '3D Secure benefits cardholders and merchants by providing an additional layer of verification using Verified by Visa and MasterCard SecureCode and is enabled by default. ', self::TEXT_DOMAIN ),
			),
			/*'threed_secure_card_types' => array(
				'title'       => __( 'Supported Card Types', self::TEXT_DOMAIN ),
				'type'        => 'multiselect',
				'class'       => 'wc-enhanced-select',
				'description' => __( '3D Secure validation will occur for these cards.', self::TEXT_DOMAIN ),
				'default'     => array_keys( $default_card_types ),
				'options'     => $card_types,
			    'custom_attributes' => array('disabled' => 'disabled')
			),*/
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
		    else
		    {
		        if ($response->gatewayApproved())
		        {
		            try {
		                $order->add_order_note( "VOID due to CSC Mismatch [".$response->get_csc_result()."]" );

    		            // Reverse at FAC
    		            $GW = new FACGateway();
    		            if($this->get_environment() == self::ENVIRONMENT_SANDBOX)
    		            {
    		                $GW->setTestMode(true);
    		            }
    		            else {
    		                $GW->setTestMode(false);
    		            }

    		            $FACOrder = $order->get_order_key()."|".$order->get_order_number();
    		            $TrxnData = [
    		                Constants::CONFIG_KEY_FACID => $this->get_merchant_id(),
    		                Constants::CONFIG_KEY_FACPWD => $this->get_merchant_password(),
    		                'transactionId' => $FACOrder,
    		                'amount' => $order->get_total()
    		            ];

    		            if (SV_WC_Helper::is_order_virtual( $order ))
    		            {
    		                $VoidRequest = $GW->refund($TrxnData);
    		            }
    		            else
    		            {
    		                $VoidRequest = $GW->void($TrxnData);
    		            }

    		            $VoidResponse = $VoidRequest->send();

    		            if ($VoidResponse->isSuccessful())
    		            {
    		                $order->add_order_note( "Successfully VOIDed at FAC: ([".$VoidResponse->getCode()."]".$VoidResponse->getMessage().")" );
    		            }
    		            else
    		            {
    		                $order->add_order_note( "Failed to VOID at FAC: ([".$VoidResponse->getCode()."]".$VoidResponse->getMessage().")" );
    		            }
		            } catch (Exception $e)
		            {
		                $order->add_order_note( "Exception while trying to VOID order at FAC: (".$e->getMessage().")" );
		            }
		        }
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
	 * Return the enabled fraud tool setting
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

	    if($this->integration == "direct")
	    {
		  $is_valid = parent::validate_fields();
	    }
	    else
	    {
	       $is_valid = true;
	    }

        //TODO Additional field validations

		return $is_valid;
	}


}

function facGatewayIcon()
{
    return get_site_url()."/wp-content/plugins/woocommerce-gateway-first-atlantic-commerce/assets/fac-visa-mc.png";
}
