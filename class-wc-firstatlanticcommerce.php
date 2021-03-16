<?php
/**
 * WooCommerce First Atlantic Commerce Gateway
 */

use SkyVerge\WooCommerce\PluginFramework\v5_10_3 as Framework;

defined( 'ABSPATH' ) or exit;

class WC_FirstAtlanticCommerce extends Framework\SV_WC_Payment_Gateway_Plugin {

    /** plugin version number */
    const VERSION = '1.0.0';

    const TEXT_DOMAIN = 'woocommerce-gateway-first-atlantic-commerce';

    protected static $instance;

    /** plugin id */
    const PLUGIN_ID = 'firstatlanticcommerce';

    /** credit card gateway class name */
    const CREDIT_CARD_GATEWAY_CLASS_NAME = 'WC_Gateway_FirstAtlanticCommerce_Credit_Card_Direct';

    /** credit card gateway ID */
    const CREDIT_CARD_GATEWAY_ID = 'firstatlanticcommerce_credit_card';


    /**
     * Initializes the plugin
     */
    public function __construct() {

        parent::__construct(
            self::PLUGIN_ID,
            self::VERSION,
            array(
                'text_domain' => self::TEXT_DOMAIN,
                'gateways'    => array(
                    self::CREDIT_CARD_GATEWAY_ID => self::CREDIT_CARD_GATEWAY_CLASS_NAME,
                ),
                'require_ssl' => true,
                'supports'    => array(
                    self::FEATURE_CAPTURE_CHARGE,
                    //self::FEATURE_MY_PAYMENT_METHODS,
                    //self::FEATURE_CUSTOMER_ID,
                ),
                'dependencies' => [
                    'php_extensions' => [ 'curl', 'dom', 'hash', 'openssl', 'SimpleXML', 'xmlwriter' ],
                ],
            )
            );

        // include required files
        $this->includes();


    }

    /**
     * Include required files
     */
    public function includes() {

        // gateways
        require_once( $this->get_plugin_path() . '/includes/class-wc-gateway-firstatlanticcommerce-direct.php' );
        require_once( $this->get_plugin_path() . '/includes/class-wc-gateway-firstatlanticcommerce-credit-card-direct.php' );

        // payment forms
        require_once( $this->get_plugin_path() . '/includes/payment-forms/abstract-wc-firstatlanticcommerce-payment-form.php' );
        require_once( $this->get_plugin_path() . '/includes/payment-forms/class-wc-firstatlanticcommerce-direct-payment-form.php' );

    }

    /** Admin methods ******************************************************/
    /**
     * @see SV_WC_Plugin::add_admin_notices()
     */
    public function add_admin_notices() {

        // show any dependency notices
        parent::add_admin_notices();

        /** @var \WC_Gateway_FirstAtlanticCommerce_Credit_Card $credit_card_gateway */
        $credit_card_gateway = $this->get_gateway( self::CREDIT_CARD_GATEWAY_ID );

        if ( $credit_card_gateway->is_advanced_fraud_tool_enabled() && ! $this->get_admin_notice_handler()->is_notice_dismissed( 'fraud-tool-notice' ) ) {

            $this->get_admin_notice_handler()->add_admin_notice(
                sprintf( __( 'Heads up! You\'ve enabled advanced fraud tools.', self::TEXT_DOMAIN ),
                    '<a target="_blank" href="' . $this->get_documentation_url() . '">',
                    '</a>'
                    ), 'fraud-tool-notice', array( 'always_show_on_settings' => false, 'dismissible' => true, 'notice_class' => 'updated' )
                );
        }

        $credit_card_settings = get_option( 'woocommerce_firstatlanticcommerce_credit_card_settings' );

        // install notice
        if ( ! $this->is_plugin_settings() ) {

            if ( empty( $credit_card_settings ) && ! $this->get_admin_notice_handler()->is_notice_dismissed( 'install-notice' ) ) {

                $this->get_admin_notice_handler()->add_admin_notice(
                    sprintf(
                        /** translators: Placeholders: %1$s - <a> tag, %2$s - </a> tag */
                        __( 'First Atlantic Commerce for WooCommerce is almost ready. To get started, configure your account%2$s.', self::TEXT_DOMAIN ),
                        '<a href="' . esc_url( $this->get_settings_url() ) . '">', '</a>'
                        ), 'install-notice', array( 'notice_class' => 'updated' )
                    );

            }
        }

        // SSL check

                if ( ! wc_checkout_is_https() && ! $this->get_admin_notice_handler()->is_notice_dismissed( 'ssl-recommended-notice' ) ) {

                    $this->get_admin_notice_handler()->add_admin_notice( __( 'WooCommerce is not being forced over SSL -- Using First Atlantic Commerce requires that checkout to be forced over SSL.', self::TEXT_DOMAIN ), 'ssl-recommended-notice' );
                }
    }


    /** Helper methods ******************************************************/

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get_plugin_name() {
        return __( WC_FirstAtlanticCommerce_Loader::PLUGIN_NAME, self::TEXT_DOMAIN );
    }

    protected function get_file() {
        return __FILE__;
    }

    public function get_documentation_url() {
        return 'https://github.com/cloudcogsio/omnipay-firstatlanticcommerce';
    }

    public function get_support_url() {
        return 'https://github.com/cloudcogsio/omnipay-firstatlanticcommerce';
    }


    /**
     * Returns the "Configure Credit Card" plugin action
     * links that go directly to the gateway settings page
     *
     * @see SV_WC_Payment_Gateway_Plugin::get_settings_url()
     * @param string $gateway_id the gateway identifier
     * @return string plugin configure link
     */
    public function get_settings_link( $gateway_id = null ) {

        return sprintf( '<a href="%s">%s</a>',
            $this->get_settings_url( $gateway_id ),
            self::CREDIT_CARD_GATEWAY_ID === $gateway_id ? __( 'Configure Credit Card', self::TEXT_DOMAIN ) : "Configure"
            );
    }


    /**
     * Determines if WooCommerce is active.
     *
     * @return bool
     */
    public static function is_woocommerce_active() {

        $active_plugins = (array) get_option( 'active_plugins', array() );

        if ( is_multisite() ) {
            $active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
        }

        return in_array( 'woocommerce/woocommerce.php', $active_plugins ) || array_key_exists( 'woocommerce/woocommerce.php', $active_plugins );
    }


}

function wc_firstatlanticcommerce() {
    return WC_FirstAtlanticCommerce::instance();
}
