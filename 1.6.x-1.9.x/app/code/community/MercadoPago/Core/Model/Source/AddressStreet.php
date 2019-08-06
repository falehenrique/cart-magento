<?php

/**
 * Class MercadoPago_Core_Model_Source_AddressStreet
 */
class MercadoPago_Core_Model_Source_AddressStreet extends Mage_Payment_Model_Method_Abstract
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $arr = array(
            array("value" => "street_1", 'label' => "Street Address 1"),
            array("value" => "street_2", 'label' => "Street Address 2"),
            array("value" => "street_3", 'label' => "Street Address 3"),
            array("value" => "street_3", 'label' => "Street Address 4")
        );

        return $arr;
    }
}
