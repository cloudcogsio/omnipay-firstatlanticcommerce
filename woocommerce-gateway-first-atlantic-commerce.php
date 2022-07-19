<?php
/**
 * Plugin Name: First Atlantic Commerce - WooCommerce Payment Gateway
 * Plugin URI: https://github.com/cloudcogsio/omnipay-firstatlanticcommerce
 * Description: WooCommerce Payment Gateway for First Atlantic Commerce (https://firstatlanticcommerce.com)
 * Author: cloudcogs.io
 * Author URI: https://www.cloudcogs.io/
 * Version: 1.0.1
 * Text Domain: woocommerce-gateway-first-atlantic-commerce
 * Domain Path: /i18n/languages/
 *
 * Copyright: (c) 2020, Tsiana, Inc. (info@tsiana.ca)
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * WC requires at least: 4.9.2
 * WC tested up to: 4.9.2
 */

defined( 'ABSPATH' ) or exit;


class WC_FirstAtlanticCommerce_Loader {


    /** minimum PHP version required by this plugin */
    const MINIMUM_PHP_VERSION = '7.0';

    /** minimum WordPress version required by this plugin */
    const MINIMUM_WP_VERSION = '5.6';

    /** minimum WooCommerce version required by this plugin */
    const MINIMUM_WC_VERSION = '3.5';

    /** SkyVerge plugin framework version used by this plugin */
    const FRAMEWORK_VERSION = '5.10.3';

    /** the plugin name, for displaying notices */
    const PLUGIN_NAME = 'First Atlantic Commerce - WooCommerce Payment Gateway';

    const TEXT_DOMAIN = 'woocommerce-gateway-first-atlantic-commerce';


    /** @var WC_FirstAtlanticCommerce_Loader single instance of this class */
    private static $instance;

    /** @var array the admin notices to add */
    private $notices = [];


    /**
     * Constructs the class.
     *
     * @since 1.0.0
     */
    protected function __construct() {

        register_activation_hook( __FILE__, array( $this, 'activation_check' ) );

        add_action( 'admin_init', array( $this, 'check_environment' ) );
        add_action( 'admin_init', array( $this, 'add_plugin_notices' ) );

        add_action( 'admin_notices', array( $this, 'admin_notices' ), 15 );

        add_filter( 'extra_plugin_headers', array( $this, 'add_documentation_header') );

        // if the environment check fails, initialize the plugin
        if ( $this->is_environment_compatible() ) {
            add_action( 'plugins_loaded', array( $this, 'init_plugin' ) );
        }
    }


    /**
     * Cloning instances is forbidden due to singleton pattern.
     *
     * @since 1.0.0
     */
    public function __clone() {

        _doing_it_wrong( __FUNCTION__, sprintf( 'You cannot clone instances of %s.', get_class( $this ) ), '1.0.0' );
    }


    /**
     * Unserializing instances is forbidden due to singleton pattern.
     *
     * @since 1.0.0
     */
    public function __wakeup() {

        _doing_it_wrong( __FUNCTION__, sprintf( 'You cannot unserialize instances of %s.', get_class( $this ) ), '1.0.0' );
    }


    /**
     * Initializes the plugin.
     *
     * @since 1.0.0
     */
    public function init_plugin() {

        if ( ! $this->plugins_compatible() ) {
            return;
        }

        $this->load_framework();

        // autoload plugin and vendor files
        $loader = require_once( plugin_dir_path( __FILE__ ) . 'vendor/autoload.php' );

        // load main plugin file
        require_once( plugin_dir_path( __FILE__ ) . 'class-wc-firstatlanticcommerce.php' );

        // if WooCommerce is inactive, render a notice and bail
        if ( ! WC_FirstAtlanticCommerce::is_woocommerce_active() ) {

            add_action( 'admin_notices', static function() {

                echo '<div class="error"><p>';
                esc_html_e( self::PLUGIN_NAME.' is inactive because WooCommerce is not installed.', self::TEXT_DOMAIN );
                echo '</p></div>';

            } );

                return;
        }

        wc_firstatlanticcommerce();
    }


    /**
     * Loads the base framework classes.
     *
     * @since 1.0.0
     */
    private function load_framework() {

        if ( ! class_exists( '\\SkyVerge\\WooCommerce\\PluginFramework\\' . $this->get_framework_version_namespace() . '\\SV_WC_Plugin' ) ) {
            require_once( plugin_dir_path( __FILE__ ) . 'vendor/skyverge/wc-plugin-framework/woocommerce/class-sv-wc-plugin.php' );
        }

        if ( ! class_exists( '\\SkyVerge\\WooCommerce\\PluginFramework\\' . $this->get_framework_version_namespace() . '\\SV_WC_Payment_Gateway_Plugin' ) ) {
            require_once( plugin_dir_path( __FILE__ ) . 'vendor/skyverge/wc-plugin-framework/woocommerce/payment-gateway/class-sv-wc-payment-gateway-plugin.php' );
        }
    }


    /**
     * Gets the framework version in namespace form.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function get_framework_version_namespace() {

        return 'v' . str_replace( '.', '_', $this->get_framework_version() );
    }


    /**
     * Gets the framework version used by this plugin.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function get_framework_version() {

        return self::FRAMEWORK_VERSION;
    }


    /**
     * Checks the server environment and other factors and deactivates plugins as necessary.
     *
     * Based on http://wptavern.com/how-to-prevent-wordpress-plugins-from-activating-on-sites-with-incompatible-hosting-environments
     *
     * @internal
     *
     * @since 1.0.0
     */
    public function activation_check() {

        if ( ! $this->is_environment_compatible() ) {

            $this->deactivate_plugin();

            wp_die( self::PLUGIN_NAME . ' could not be activated. ' . $this->get_environment_message() );
        }
    }


    /**
     * Checks the environment on loading WordPress, just in case the environment changes after activation.
     *
     * @internal
     *
     * @since 1.0.0
     */
    public function check_environment() {

        if ( ! $this->is_environment_compatible() && is_plugin_active( plugin_basename( __FILE__ ) ) ) {

            $this->deactivate_plugin();

            $this->add_admin_notice( 'bad_environment', 'error', self::PLUGIN_NAME . ' has been deactivated. ' . $this->get_environment_message() );
        }
    }

    /**
     * Checks the environment for compatibility problems.
     *
     * @since 1.0.0
     * @param bool $during_activation whether this check is during plugin activation
     * @return string|bool the error message if one exists, or false if everything's okay
     */
    public static function get_environment_warning( $during_activation = false ) {

        $message = false;

        // check the PHP version
        if ( version_compare( PHP_VERSION, self::MINIMUM_PHP_VERSION, '<' ) ) {

            $message = sprintf( __( 'The minimum PHP version required for this plugin is %1$s. You are running %2$s.', self::TEXT_DOMAIN ), self::MINIMUM_PHP_VERSION, phpversion() );

            $prefix = ( $during_activation ) ? 'The plugin could not be activated. ' : self::PLUGIN_NAME.' has been deactivated. ';

            $message = $prefix . $message;
        }

        return $message;
    }

    /**
     * Adds notices for out-of-date WordPress and/or WooCommerce versions.
     *
     * @internal
     *
     * @since 1.0.0
     */
    public function add_plugin_notices() {

        if ( ! $this->is_wp_compatible() ) {

            $this->add_admin_notice( 'update_wordpress', 'error', sprintf(
                '%s requires WordPress version %s or higher. Please %supdate WordPress &raquo;%s',
                '<strong>' . self::PLUGIN_NAME . '</strong>',
                self::MINIMUM_WP_VERSION,
                '<a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">', '</a>'
                ) );
        }

        if ( ! $this->is_wc_compatible() ) {

            $this->add_admin_notice( 'update_woocommerce', 'error', sprintf(
                '%1$s requires WooCommerce version %2$s or higher. Please %3$supdate WooCommerce%4$s to the latest version, or %5$sdownload the minimum required version &raquo;%6$s',
                '<strong>' . self::PLUGIN_NAME . '</strong>',
                self::MINIMUM_WC_VERSION,
                '<a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">', '</a>',
                '<a href="' . esc_url( 'https://downloads.wordpress.org/plugin/woocommerce.' . self::MINIMUM_WC_VERSION . '.zip' ) . '">', '</a>'
                ) );
        }

        if ( ! extension_loaded( 'curl' ) ) {

            $this->add_admin_notice( 'install_curl', 'error', sprintf(
                '%1$s requires the cURL PHP extension to function. Contact your host or server administrator to install and configure cURL.',
                '<strong>' . self::PLUGIN_NAME . '</strong>'
                ) );
        }

    }


    /**
     * Determines if the required plugins are compatible.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    private function plugins_compatible() {

        return $this->is_wp_compatible() && $this->is_wc_compatible() && extension_loaded( 'curl' );
    }


    /**
     * Determines if the WordPress compatible.
     *
     * @return bool
     */
    private function is_wp_compatible() {

        if ( ! self::MINIMUM_WP_VERSION ) {
            return true;
        }

        return version_compare( get_bloginfo( 'version' ), self::MINIMUM_WP_VERSION, '>=' );
    }


    /**
     * Determines if the WooCommerce compatible.
     *
     * @return bool
     */
    private function is_wc_compatible() {

        if ( ! self::MINIMUM_WC_VERSION ) {
            return true;
        }

        return defined( 'WC_VERSION' ) && version_compare( WC_VERSION, self::MINIMUM_WC_VERSION, '>=' );
    }


    /**
     * Deactivates the plugin.
     *
     */
    protected function deactivate_plugin() {

        deactivate_plugins( plugin_basename( __FILE__ ) );

        if ( isset( $_GET['activate'] ) ) {
            unset( $_GET['activate'] );
        }
    }


    /**
     * Adds an admin notice to be displayed.
     *
     * @param string $slug the slug for the notice
     * @param string $class the css class for the notice
     * @param string $message the notice message
     */
    private function add_admin_notice( $slug, $class, $message ) {

        $this->notices[ $slug ] = array(
            'class'   => $class,
            'message' => $message
        );
    }


    /**
     * Displays any admin notices added with \SV_WC_Framework_Plugin_Loader::add_admin_notice()
     */
    public function admin_notices() {

        foreach ( (array) $this->notices as $notice_key => $notice ) {

            ?>
			<div class="<?php echo esc_attr( $notice['class'] ); ?>">
				<p><?php echo wp_kses( $notice['message'], array( 'a' => array( 'href' => array() ) ) ); ?></p>
			</div>
			<?php
		}
	}


	/**
	 * Adds the Documentation URI header.
	 *
	 * @param string[] $headers original headers
	 * @return string[]
	 */
	public function add_documentation_header( $headers ) {

		$headers[] = 'Documentation URI';

		return $headers;
	}


	/**
	 * Determines if the server environment is compatible with this plugin.
	 *
	 * @return bool
	 */
	private function is_environment_compatible() {

		return version_compare( PHP_VERSION, self::MINIMUM_PHP_VERSION, '>=' );
	}


	/**
	 * Gets the message for display when the environment is incompatible with this plugin.
	 *
	 * @return string
	 */
	private function get_environment_message() {

		return sprintf( 'The minimum PHP version required for this plugin is %1$s. You are running %2$s.', self::MINIMUM_PHP_VERSION, PHP_VERSION );
	}


	/**
	 * Gets the main \SV_WC_Framework_Plugin_Loader instance.
	 *
	 * @return \SV_WC_Framework_Plugin_Loader
	 */
	public static function instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}


}

WC_FirstAtlanticCommerce_Loader::instance();