<?php

/**
 * Class MercadoPago_Core_Helper_Log
 */
class MercadoPago_Core_Helper_Log extends Mage_Payment_Helper_Data
{
    /**
     * @param $message
     * @param string $file
     * @param null $array
     */
    public function log($message, $file = "mercadopago.log", $array = null)
    {
        $actionLog = Mage::getStoreConfig('payment/mercadopago/logs');
        if ($actionLog) {
            if (!is_null($array)) {
                $message .= " - " . json_encode($array);
            }
            Mage::log($message, null, $file, $actionLog);
        }
    }

}