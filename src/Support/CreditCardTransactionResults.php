<?php
namespace Omnipay\FirstAtlanticCommerce\Support;

class CreditCardTransactionResults extends AbstractResults
{
    public function getAuthCode()
    {
        return $this->queryData("AuthCode");
    }

    public function getAVSResult()
    {
        return $this->queryData("AVSResult");
    }

    public function getCVV2Result()
    {
        return $this->queryData("CVV2Result");
    }

    public function getOriginalResponseCode()
    {
        return $this->queryData("OriginalResponseCode");
    }

    public function getPaddedCardNumber()
    {
        return $this->queryData("PaddedCardNumber");
    }

    public function getReasonCode()
    {
        return $this->queryData("ReasonCode");
    }

    public function getReasonCodeDescription()
    {
        return $this->queryData("ReasonCodeDescription");
    }

    public function getReferenceNumber()
    {
        return $this->queryData("ReferenceNumber");
    }

    public function getResponseCode()
    {
        return $this->queryData("ResponseCode");
    }

    public function getTokenizedPAN()
    {
        return $this->queryData("TokenizedPAN");
    }
}