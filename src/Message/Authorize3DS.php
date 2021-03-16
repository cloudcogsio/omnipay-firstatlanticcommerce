<?php
namespace Omnipay\FirstAtlanticCommerce\Message;

class Authorize3DS extends Authorize
{
    const MESSAGE_PART_MERCHANT_RESPONSE_URL = "merchantResponseURL";

    public function getData()
    {
        parent::getData();
        $this->applyMerchantResponseURL();

        return $this->data;
    }

    public function setMerchantResponseURL($url)
    {
        return $this->setParameter(self::MESSAGE_PART_MERCHANT_RESPONSE_URL, $url);
    }

    public function getMerchantResponseURL()
    {
        return $this->getParameter(self::MESSAGE_PART_MERCHANT_RESPONSE_URL);
    }

    protected function applyMerchantResponseURL()
    {
        $this->data[ucfirst(self::MESSAGE_PART_MERCHANT_RESPONSE_URL)] = $this->getMerchantResponseURL();
    }
}