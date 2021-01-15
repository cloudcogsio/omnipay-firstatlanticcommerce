<?php
namespace Omnipay\FirstAtlanticCommerce\Message;

use Omnipay\Common\Message\RequestInterface;
use Omnipay\Common\Message\AbstractResponse as OmnipayAbstractResponse;
use Omnipay\FirstAtlanticCommerce\Exception\InvalidResponseData;

abstract class AbstractResponse extends OmnipayAbstractResponse
{
    public function __construct(RequestInterface $request, $data)
    {
        if ($data instanceof \SimpleXMLElement)
        {
            $this->request = $request;
            $this->data = $data;

            parent::__construct($request, $data);

            if(intval($this->queryData("ResponseCode")) === 1)
                $this->verifySignature();
        }
        else
        {
            throw new InvalidResponseData("Response data is not valid XML");
        }
    }

    public function getRequest() : AbstractRequest
    {
        return $this->request;
    }

    public function getData() : \SimpleXMLElement
    {
        return $this->data;
    }

    protected function queryData($element)
    {
        $result = $this->getData()->xpath("//fac:$element");
        if (is_array($result) && count($result) > 0)
            return (string) $result[0];

        return null;
    }

    abstract public function verifySignature();
}