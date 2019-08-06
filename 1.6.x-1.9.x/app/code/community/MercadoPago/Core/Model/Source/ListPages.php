<?php

/**
 * Class MercadoPago_Core_Model_Source_ListPages
 */
class MercadoPago_Core_Model_Source_ListPages extends Mage_Payment_Model_Method_Abstract
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $pages = array();
        $pages[] = array('value' => "product.info.calculator", 'label' => Mage::helper('mercadopago')->__("Product Detail Page"));
        $pages[] = array('value' => "checkout.cart.calculator", 'label' => Mage::helper('mercadopago')->__("Cart page"));

        ksort($pages);
        return $pages;
    }
}
