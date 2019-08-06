<?php

/**
 * Class MercadoPago_Core_Model_Source_CategoryId
 */
class MercadoPago_Core_Model_Source_CategoryId extends Mage_Payment_Model_Method_Abstract
{

    /**
     * @return array
     * @throws Exception
     */
    public function toOptionArray()
    {
        try {
            Mage::helper('mercadopago/log')->log("Get Categories... ", 'mercadopago.log');
            $response = MercadoPago_RestClient_MpRestClient::get("/item_categories");
            Mage::helper('mercadopago/log')->log("API item_categories", 'mercadopago.log', $response);

            if (!isset($response['response'])) {
                return array();
            }

            $response = $response['response'];

            $cat = array();
            $count = 0;
            foreach ($response as $value) {
                if ($value['id'] == "others") {
                    $cat[0] = array('value' => $value['id'], 'label' => Mage::helper('mercadopago')->__($value['description']));
                } else {
                    $count++;
                    $cat[$count] = array('value' => $value['id'], 'label' => Mage::helper('mercadopago')->__($value['description']));
                }
            }
            ksort($cat);
            return $cat;
        } catch (Exception $e) {
            Mage::helper('mercadopago/log')->log("ERROR: CategoryId.php:". __FUNCTION__, 'mercadopago.log');
            return array();
        }
    }
}