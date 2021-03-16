<?php
namespace Omnipay\FirstAtlanticCommerce\Message;

class Authorize3DSResponse extends AbstractResponse
{
    public function isSuccessful()
    {
        if ($this->getCode() === "00") return true;

        return false;
    }

    public function getMessage()
    {
        return $this->queryData("ResponseCodeDescription");
    }

    public function getCode()
    {
        return $this->queryData("ResponseCode");
    }

    public function getHTMLFormData()
    {
        return $this->queryData("HTMLFormData");
    }

    public function getTokenizedPAN()
    {
        return $this->queryData("TokenizedPAN");
    }

    public function verifySignature()
    {
        return $this;
    }
}