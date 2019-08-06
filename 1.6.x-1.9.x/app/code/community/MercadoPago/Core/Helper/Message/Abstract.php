<?php

/**
 * Class MercadoPago_Core_Helper_Message_Abstract
 */
abstract class MercadoPago_Core_Helper_Message_Abstract extends Mage_Core_Helper_Abstract
{
    /**
     * @return mixed
     */
    public abstract function getMessageMap();

    /**
     * @param $key
     * @return string
     */
    public function getMessage($key)
    {
        $messageMap = $this->getMessageMap();
        if (isset($messageMap[$key])) {
            return $messageMap[$key];
        }

        return '';
    }

}