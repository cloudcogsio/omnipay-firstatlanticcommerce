<?php
namespace Omnipay\FirstAtlanticCommerce\Message;

class HostedPagePreprocess extends Authorize
{
    const MESSAGE_PART_CARDHOLDER_RESPONSE_URL = 'cardHolderResponseURL';
    const HOSTED_PAGE_PAGESET = "hostedPagePageSet";
    const HOSTED_PAGE_NAME = "hostedPageName";

    public function getData()
    {
        $this->TransactionDetails = array_merge($this->TransactionDetails, $this->setTransactionDetailsCommon());
        $this->setTransactionDetails();

        $this->applyCardHolderResponseURL();

        return $this->data;
    }

    public function setCardHolderResponseURL($url)
    {
        return $this->setParameter(self::MESSAGE_PART_CARDHOLDER_RESPONSE_URL, $url);
    }

    public function getCardHolderResponseURL()
    {
        return $this->getParameter(self::MESSAGE_PART_CARDHOLDER_RESPONSE_URL);
    }

    public function setHostedPagePageSet($pageSet)
    {
        return $this->setParameter(self::HOSTED_PAGE_PAGESET, $pageSet);
    }

    public function getHostedPagePageSet()
    {
        return $this->getParameter(self::HOSTED_PAGE_PAGESET);
    }

    public function setHostedPageName($pageName)
    {
        return $this->setParameter(self::HOSTED_PAGE_NAME, $pageName);
    }

    public function getHostedPageName()
    {
        return $this->getParameter(self::HOSTED_PAGE_NAME);
    }

    protected function applyCardHolderResponseURL()
    {
        $this->data[ucfirst(self::MESSAGE_PART_CARDHOLDER_RESPONSE_URL)] = $this->getCardHolderResponseURL();
    }
}