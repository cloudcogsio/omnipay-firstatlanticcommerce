<?php
namespace Omnipay\FirstAtlanticCommerce;

class Constants
{
    const DRIVER_NAME = 'First Atlantic Commerce - Payment Gateway';

    const PLATFORM_XML_UAT = 'https://ecm.firstatlanticcommerce.com/PGServiceXML/';
    const PLATFORM_XML_PROD = 'https://marlin.firstatlanticcommerce.com/PGServiceXML/';

    const PLATFORM_XML_NS = "http://schemas.firstatlanticcommerce.com/gateway/data";

    const CONFIG_KEY_FACID = 'facId';
    const CONFIG_KEY_FACPWD = 'facPwd';
    const CONFIG_KEY_FACAQID = 'facAcquirer';
    const CONFIG_KEY_FACCUR = 'facCurrencyList';

    const AUTHORIZE_OPTION_3DS = '3DS';

}