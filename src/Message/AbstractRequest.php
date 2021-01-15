<?php
namespace Omnipay\FirstAtlanticCommerce\Message;


use Omnipay\FirstAtlanticCommerce\Constants;
use Omnipay\FirstAtlanticCommerce\Support\TransactionCode;
use Omnipay\FirstAtlanticCommerce\Exception\GatewayHTTPException;

abstract class AbstractRequest extends \Omnipay\Common\Message\AbstractRequest
implements \Omnipay\FirstAtlanticCommerce\Support\FACParametersInterface
{
    const SIGNATURE_METHOD_SHA1 = 'SHA1';

    protected $data = [];
    protected $XMLDoc;

    protected $FACServices = [
        "Authorize" => [
            "request"=>"AuthorizeRequest",
            "response"=>"AuthorizeResponse"
        ],
        "TransactionStatus" => [
            "request"=>"TransactionStatusRequest",
            "response"=>"TransactionStatusResponse"
        ],
        "TransactionModification" => [
            "request"=>"TransactionModificationRequest",
            "response"=>"TransactionModificationResponse"
        ]
    ];

    public function signTransaction()
    {
        $signature = null;

        switch ($this->getMessageClassName())
        {
            case "Authorize":
                $data = $this->getFacPwd().$this->getFacId().$this->getFacAcquirer().$this->getTransactionId().$this->getAmountForFAC().$this->getCurrencyNumeric();
                $hash = sha1($data, true);
                $signature = base64_encode($hash);

                break;
        }

        return $this->setSignature($signature);
    }

    public function sendData($data)
    {
        if ($this->getTestMode()) print "Sending to: ".$this->getEndpoint().$this->getMessageClassName()."...\n";

        $this->createNewXMLDoc($data);

        if($this->getTestMode()) $this->XMLDoc->asXML($this->getMessageClassName().'Request.xml');

        $httpResponse = $this->httpClient
            ->request("POST", $this->getEndpoint().$this->getMessageClassName(), [
            "Content-Type"=>"text/html"
        ], $this->XMLDoc->asXML());

        if ($this->getTestMode())
        {
            print "Response Headers: \n";
            foreach ($httpResponse->getHeaders() as $header=>$headerValues)
            {
                print "$header: ".implode(", ", $headerValues)."\n";
            }
        }

        switch ($httpResponse->getStatusCode())
        {
            case "200":
                $responseContent = $httpResponse->getBody()->getContents();
                $responseClassName = __NAMESPACE__."\\".$this->FACServices[$this->getMessageClassName()]["response"];

                $responseXML = new \SimpleXMLElement($responseContent);
                $responseXML->registerXPathNamespace("fac", Constants::PLATFORM_XML_NS);

                if($this->getTestMode()) $responseXML->asXML($this->getMessageClassName().'Response.xml');

                return $this->response = new $responseClassName($this, $responseXML);

                break;

            default:
                throw new GatewayHTTPException($httpResponse->getReasonPhrase(), $httpResponse->getStatusCode());
                break;
        }
    }

    protected function createNewXMLDoc($data)
    {
        $rootElement = $this->FACServices[$this->getMessageClassName()]["request"];
        $this->XMLDoc = new \SimpleXMLElement("<".$rootElement." xmlns=\"".Constants::PLATFORM_XML_NS."\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" />");

        $this->createXMLFromData($this->XMLDoc, $data);
    }

    protected function createXMLFromData(\SimpleXMLElement $parent, $data)
    {
        foreach ($data as $elementName=>$value)
        {
            if (is_array($value))
            {
                $element = $parent->addChild($elementName);
                $this->createXMLFromData($element, $value);
            }
            else
            {
                $parent->addChild($elementName, $value);
            }
        }
    }

    protected function getEndpoint()
    {
        return ($this->getTestMode()) ? Constants::PLATFORM_XML_UAT : Constants::PLATFORM_XML_PROD;
    }

    public function getMessageClassName()
    {
        $className = explode("\\",get_called_class());
        return array_pop($className);
    }

    public function setFacId($FACID)
    {
        return $this->setParameter(Constants::CONFIG_KEY_FACID, $FACID);
    }

    public function getFacId()
    {
        return $this->getParameter(Constants::CONFIG_KEY_FACID);
    }

    public function setFacPwd($PWD)
    {
        return $this->setParameter(Constants::CONFIG_KEY_FACPWD, $PWD);
    }

    public function getFacPwd()
    {
        return $this->getParameter(Constants::CONFIG_KEY_FACPWD);
    }

    public function setFacAcquirer($ACQ)
    {
        return $this->setParameter(Constants::CONFIG_KEY_FACAQID, $ACQ);
    }

    public function getFacAcquirer()
    {
        return $this->getParameter(Constants::CONFIG_KEY_FACAQID);
    }

    public function setFacCurrencyList($list)
    {
        return $this->setParameter(Constants::CONFIG_KEY_FACCUR, $list);
    }

    public function getFacCurrencyList()
    {
        return $this->getParameter(Constants::CONFIG_KEY_FACCUR);
    }

    public function setIPAddress($ip)
    {
        if (filter_var($ip, FILTER_VALIDATE_IP))
        {
            return $this->setClientIp($ip);
        }

        return $this;
    }

    public function getIPAddress()
    {
        return $this->getClientIp();
    }

    public function getCustomerReference()
    {
        return $this->getParameter(Authorize::PARAM_CUSTOMER_REF);
    }

    public function setCustomerReference($ref)
    {
        return $this->setParameter(Authorize::PARAM_CUSTOMER_REF, $ref);
    }

    public function getSignature()
    {
        return $this->getParameter(Authorize::PARAM_SIGNATURE);
    }

    public function setSignature($signature)
    {
        return $this->setParameter(Authorize::PARAM_SIGNATURE, $signature);
    }

    public function getSignatureMethod()
    {
        if (!$this->getParameter(Authorize::PARAM_SIGNATURE_METHOD))
        {
            $this->setSignatureMethod();
        }

        return $this->getParameter(Authorize::PARAM_SIGNATURE_METHOD);
    }

    public function setSignatureMethod($algo = self::SIGNATURE_METHOD_SHA1)
    {
        return $this->setParameter(Authorize::PARAM_SIGNATURE_METHOD, $algo);
    }

    public function getAmountForFAC()
    {
        $length = 12;
        $amount = $this->getAmountInteger();

        while (strlen($amount) < $length)
        {
            $amount = "0".$amount;
        }

        return $amount;
    }

    public function setTransactionCode(TransactionCode $transactionCode)
    {
        return $this->setParameter(Authorize::PARAM_TRANSACTIONCODE, $transactionCode);
    }

    public function getTransactionCode() : TransactionCode
    {
        return $this->getParameter(Authorize::PARAM_TRANSACTIONCODE);
    }
}