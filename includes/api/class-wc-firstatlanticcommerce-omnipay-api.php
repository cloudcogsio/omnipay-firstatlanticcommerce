<?php
use SkyVerge\WooCommerce\PluginFramework\v5_10_3 as Framework;
use Omnipay\FirstAtlanticCommerce\FACGateway;
use Omnipay\FirstAtlanticCommerce\Constants;

defined( 'ABSPATH' ) or exit;

class WC_FirstAtlanticCommerce_OmniPay_API extends Framework\SV_WC_API_Base implements Framework\SV_WC_Payment_Gateway_API {

    /** @var \WC_Gateway_FirstAtlanticCommerce class instance */
    protected $gateway;

    /** @var \WC_Order order associated with the request, if any */
    protected $order;

    public function __construct( $gateway ) {

        $this->gateway = $gateway;
    }

    /**
     * {@inheritDoc}
     * @see \SkyVerge\WooCommerce\PluginFramework\v5_10_3\SV_WC_API_Base::get_new_request()
     * @return \WC_FirstAtlanticCommerce_API_Transaction_Request
     */
    protected function get_new_request($args = array())
    {
        $this->order = isset( $args['order'] ) && $args['order'] instanceof WC_Order ? $args['order'] : null;

        $FACGW = new FACGateway();
        if($this->get_gateway()->get_environment() == WC_Gateway_FirstAtlanticCommerce::ENVIRONMENT_SANDBOX)
        {
            $FACGW->setTestMode(true);
        }
        else
        {
            $FACGW->setTestMode(false);
        }

        $FACGW->setFacId($this->get_gateway()->get_merchant_id());
        $FACGW->setFacPwd($this->get_gateway()->get_merchant_password());

        $FACIntegration = $this->get_gateway()->integration;
        switch ($FACIntegration)
        {
            case Constants::GATEWAY_INTEGRATION_DIRECT:
                $FACGW->setIntegrationOption(Constants::GATEWAY_INTEGRATION_DIRECT);
                break;
            case Constants::GATEWAY_INTEGRATION_HOSTED:
                $FACGW->setIntegrationOption(Constants::GATEWAY_INTEGRATION_HOSTED);
                $FACGW->setFacPageSet($this->get_gateway()->get_hosted_page_set());
                $FACGW->setFacPageName($this->get_gateway()->get_hosted_page_name());
                break;

            default:
                throw new Framework\SV_WC_API_Exception( 'Invalid FAC integration option' );
        }

        switch ( $args['type'] ) {

            case 'transaction':

                $this->set_response_handler( $this->get_gateway()->is_credit_card_gateway() ? 'WC_FirstAtlanticCommerce_API_Credit_Card_Transaction_Response' : '' );
                return new WC_FirstAtlanticCommerce_API_Transaction_Request( $this->order, $FACGW );

            default:
                throw new Framework\SV_WC_API_Exception( 'Invalid request type' );
        }
    }


    /**
     * {@inheritDoc}
     * @see \SkyVerge\WooCommerce\PluginFramework\v5_10_3\SV_WC_Payment_Gateway_API::credit_card_authorization()
     */
    public function credit_card_authorization(\WC_Order $order)
    {
        $request = $this->get_new_request( array(
            'type'  => 'transaction',
            'order' => $order,
        ) );

        $request->create_credit_card_auth();

        return $this->perform_request( $request );
    }

    /**
     * {@inheritDoc}
     * @see \SkyVerge\WooCommerce\PluginFramework\v5_10_3\SV_WC_Payment_Gateway_API::credit_card_charge()
     */
    public function credit_card_charge(\WC_Order $order)
    {
        $request = $this->get_new_request( array(
            'type'  => 'transaction',
            'order' => $order,
        ) );

        $request->create_credit_card_charge();

        return $this->perform_request( $request );
    }

    /**
     * {@inheritDoc}
     * @see \SkyVerge\WooCommerce\PluginFramework\v5_10_3\SV_WC_Payment_Gateway_API::credit_card_capture()
     */
    public function credit_card_capture(\WC_Order $order)
    {
        $request = $this->get_new_request( array(
            'type'  => 'transaction',
            'order' => $order,
        ) );

        $request->create_credit_card_capture();

        return $this->perform_request( $request );
    }

    /**
     * {@inheritDoc}
     * @see \SkyVerge\WooCommerce\PluginFramework\v5_10_3\SV_WC_Payment_Gateway_API::check_debit()
     */
    public function check_debit(\WC_Order $order)
    {
    }

    /**
     * {@inheritDoc}
     * @see \SkyVerge\WooCommerce\PluginFramework\v5_10_3\SV_WC_Payment_Gateway_API::refund()
     */
    public function refund(\WC_Order $order)
    {
        $request = $this->get_new_request( array(
            'type'  => 'transaction',
            'order' => $order,
        ) );

        $request->create_refund();

        return $this->perform_request( $request );
    }

    /**
     * {@inheritDoc}
     * @see \SkyVerge\WooCommerce\PluginFramework\v5_10_3\SV_WC_Payment_Gateway_API::void()
     */
    public function void(\WC_Order $order)
    {
        $request = $this->get_new_request( array(
            'type'  => 'transaction',
            'order' => $order,
        ) );

        $request->create_void();

        return $this->perform_request( $request );
    }

    /**
     * {@inheritDoc}
     * @see \SkyVerge\WooCommerce\PluginFramework\v5_10_3\SV_WC_Payment_Gateway_API::tokenize_payment_method()
     */
    public function tokenize_payment_method(\WC_Order $order)
    {
        // TODO Auto-generated method stub
    }

    /**
     * {@inheritDoc}
     * @see \SkyVerge\WooCommerce\PluginFramework\v5_10_3\SV_WC_Payment_Gateway_API::update_tokenized_payment_method()
     */
    public function update_tokenized_payment_method(\WC_Order $order)
    {
        // TODO Auto-generated method stub
    }

    /**
     * {@inheritDoc}
     * @see \SkyVerge\WooCommerce\PluginFramework\v5_10_3\SV_WC_Payment_Gateway_API::supports_update_tokenized_payment_method()
     */
    public function supports_update_tokenized_payment_method()
    {
        // TODO Auto-generated method stub
    }

    /**
     * {@inheritDoc}
     * @see \SkyVerge\WooCommerce\PluginFramework\v5_10_3\SV_WC_Payment_Gateway_API::remove_tokenized_payment_method()
     */
    public function remove_tokenized_payment_method($token, $customer_id)
    {
        // TODO Auto-generated method stub
    }

    /**
     * {@inheritDoc}
     * @see \SkyVerge\WooCommerce\PluginFramework\v5_10_3\SV_WC_Payment_Gateway_API::supports_remove_tokenized_payment_method()
     */
    public function supports_remove_tokenized_payment_method()
    {
        // TODO Auto-generated method stub
    }

    /**
     * {@inheritDoc}
     * @see \SkyVerge\WooCommerce\PluginFramework\v5_10_3\SV_WC_Payment_Gateway_API::get_tokenized_payment_methods()
     */
    public function get_tokenized_payment_methods($customer_id)
    {
        // TODO Auto-generated method stub
    }

    /**
     * {@inheritDoc}
     * @see \SkyVerge\WooCommerce\PluginFramework\v5_10_3\SV_WC_Payment_Gateway_API::supports_get_tokenized_payment_methods()
     */
    public function supports_get_tokenized_payment_methods()
    {
        // TODO Auto-generated method stub
    }

    /**
     * {@inheritDoc}
     * @see \SkyVerge\WooCommerce\PluginFramework\v5_10_3\SV_WC_Payment_Gateway_API::get_order()
     */
    public function get_order()
    {
        return $this->order;
    }

    protected function get_api_id() {

        return $this->get_gateway()->get_id();
    }

    public function get_plugin() {

        return $this->get_gateway()->get_plugin();
    }

    public function get_gateway() {

        return $this->gateway;
    }

    protected function do_remote_request( $callback, $callback_params ) {

        $request = $this->request;

        try {
            $response = $this->request->getFACRequest()->send();

        } catch ( Exception $e ) {

            $response = $e;
        }

        return $response;
    }

    protected function handle_response( $response ) {

        // check if response contains exception and convert to framework exception
        if ( $response instanceof Exception ) {
            throw new Framework\SV_WC_API_Exception( $response->getMessage(), $response->getCode(), $response );
        }

        if ($response instanceof Omnipay\FirstAtlanticCommerce\Message\Authorize3DSResponse)
        {
            if ($response->getCode() === "00" || $response->getCode() === "0")
            {
                $response->renderHTMLFormData();
            }
            else {
                throw new Framework\SV_WC_API_Exception($response->getMessage(), $response->getCode());
            }
        }

        if ($response instanceof Omnipay\FirstAtlanticCommerce\Message\HostedPagePreprocessResponse)
        {
            if ($response->isSuccessful())
            {
                $response->redirectToHostedPage();
            }
            else {
                throw new Framework\SV_WC_API_Exception($response->getMessage(), $response->getCode());
            }
        }

        $handler_class = $this->get_response_handler();

        // parse the response body and tie it to the request
        $this->response = new $handler_class( $response, $this->get_gateway()->is_credit_card_gateway() ? 'credit-card' : '' );

        // broadcast request
        $this->broadcast_request();

        return $this->response;
    }

    public function handle_3ds_response($response)
    {
        $this->response_handler = 'WC_FirstAtlanticCommerce_API_Three_DS_Transaction_Response';
        return $this->handle_response($response);
    }

    public function handle_hostedpage_response($response)
    {
        $this->response_handler = 'WC_FirstAtlanticCommerce_API_Hosted_Credit_Card_Transaction_Response';
        return $this->handle_response($response);
    }
}