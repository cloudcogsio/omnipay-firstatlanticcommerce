<?php
use Omnipay\FirstAtlanticCommerce\Constants;
use Omnipay\FirstAtlanticCommerce\Message\AbstractRequest;

return [
    'testMode'                          => true,
    AbstractRequest::PARAM_CACHE_TRANSACTION => false,
    AbstractRequest::PARAM_CACHE_REQUEST => false,

    Constants::CONFIG_KEY_FACID         => '', // First Atlantic Commerce ID
    Constants::CONFIG_KEY_FACPWD        => '', // First Atlantic Commerce Processing Password
    Constants::CONFIG_KEY_FACAQID       => '464748', // First Atlantic Commerce Acquirer ID

    /**
     * List all authorized currencies.
     * First in list will be default and populated as 'currency' parameter
     */
    Constants::CONFIG_KEY_FACCUR        => ['USD'],
];