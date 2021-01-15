<?php
namespace Omnipay\FirstAtlanticCommerce\Support;

use \Omnipay\Common\CreditCard as OmniPayCreditCard;
use Omnipay\Common\Exception\InvalidCreditCardException;
use Omnipay\FirstAtlanticCommerce\Exception\InvalidEmailAddress;

class CreditCard extends OmniPayCreditCard
{
    public function validate()
    {
        parent::validate();

        return $this
                ->validateCard()
                ->validateCVV();
    }

    protected function validateCard()
    {
        $cardNumber = $this->getNumber();

        switch ($this->getBrand())
        {
            case self::BRAND_MASTERCARD:
                if(strlen($cardNumber) != 16) throw new InvalidCreditCardException('Card number should be 16 digits!');
                break;

            case self::BRAND_VISA:
                if(strlen($cardNumber) != 16) throw new InvalidCreditCardException('Card number should be 16 digits!');
                break;

            case self::BRAND_AMEX:
                if(strlen($cardNumber) != 15) throw new InvalidCreditCardException('Card number should be 15 digits!');
                break;
        }

        return $this;
    }

    protected function validateCVV()
    {
        $CVV = $this->getCvv();

        switch ($this->getBrand())
        {
            case self::BRAND_MASTERCARD:
                if(strlen($CVV) != 3) throw new InvalidCreditCardException('CVV should be 3 digits!');
                break;

            case self::BRAND_VISA:
                if(strlen($CVV) != 3) throw new InvalidCreditCardException('CVV should be 3 digits!');
                break;

            case self::BRAND_AMEX:
                if(strlen($CVV) != 3) throw new InvalidCreditCardException('CVV should be 4 digits!');
                break;
        }

        return $this;
    }

    public function getBillingEmail()
    {
        return $this->getParameter('billingEmail');
    }

    public function setBillingEmail($value)
    {
        if(!filter_var($value, FILTER_VALIDATE_EMAIL)) throw new InvalidEmailAddress($value);
        return $this->setParameter('billingEmail', $value);
    }

    public function setBillingCountry($value)
    {
        if (strlen($value) == 2 && ctype_alpha($value)) $country = (new \League\ISO3166\ISO3166)->alpha2(strtoupper($value));
        if (strlen($value) == 3 && ctype_alpha($value)) $country = (new \League\ISO3166\ISO3166)->alpha3(strtoupper($value));
        if (strlen($value) == 3 && ctype_digit($value)) $country = (new \League\ISO3166\ISO3166)->numeric(intval($value));
        $country = (new \League\ISO3166\ISO3166)->name($value);

        if (is_array($country) && array_key_exists('numeric', $country))
            return $this->setParameter('billingCountry', $country);

        return $this->setParameter('billingCountry', null);
    }
}