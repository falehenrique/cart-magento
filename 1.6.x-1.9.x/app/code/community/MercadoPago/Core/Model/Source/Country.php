<?php

/**
 * Class MercadoPago_Core_Model_Source_Country
 */
class MercadoPago_Core_Model_Source_Country extends Mage_Payment_Model_Method_Abstract
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $country = array();
        $country[] = array('value' => "mla", 'label' => Mage::helper('mercadopago')->__("Argentina"), 'code' => 'AR');
        $country[] = array('value' => "mlb", 'label' => Mage::helper('mercadopago')->__("Brasil"), 'code' => 'BR');
        $country[] = array('value' => "mco", 'label' => Mage::helper('mercadopago')->__("Colombia"), 'code' => 'CO');
        $country[] = array('value' => "mlm", 'label' => Mage::helper('mercadopago')->__("Mexico"), 'code' => 'MX');
        $country[] = array('value' => "mlc", 'label' => Mage::helper('mercadopago')->__("Chile"), 'code' => 'CL');
        $country[] = array('value' => "mlv", 'label' => Mage::helper('mercadopago')->__("Venezuela"), 'code' => 'VE');
        $country[] = array('value' => "mpe", 'label' => Mage::helper('mercadopago')->__("Perú"), 'code' => 'PE');
        $country[] = array('value' => "mlu", 'label' => Mage::helper('mercadopago')->__("Uruguay"), 'code' => 'UY');

        ksort($country);
        return $country;
    }

    /**
     * @param $value
     * @return string
     */
    public function getCodeByValue($value)
    {
        $countries = $this->toOptionArray();
        foreach ($countries as $country) {
            if ($value == $country['value']) {
                return $country['code'];
            }
        }
        return '';
    }
}
