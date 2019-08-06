<?php

/**
 * Class MercadoPago_Core_Model_Source_TypeCheckout
 */
class MercadoPago_Core_Model_Source_TypeCheckout extends Mage_Payment_Model_Method_Abstract
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $arr = array(
            array("value" => "iframe", 'label' => Mage::helper('mercadopago')->__("Iframe")),
            array("value" => "redirect", 'label' => Mage::helper('mercadopago')->__("Redirect")),
            array("value" => "lightbox", 'label' => Mage::helper('mercadopago')->__("Lightbox"))
        );

        return $arr;
    }
}
