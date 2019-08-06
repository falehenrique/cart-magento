<?php

/**
 * Class MercadoPago_Core_Model_Source_PaymentMethodsTicket
 */

class MercadoPago_Core_Model_Source_PaymentMethodsTicket extends Mage_Payment_Model_Method_Abstract
{
    public $log;
    public $helper;

    const XML_PATH_ACCESS_TOKEN = 'payment/mercadopago_custom_checkout/access_token';

    public function __construct()
    {
        parent::__construct();
        $this->log = Mage::helper('mercadopago/log');
        $this->helper = Mage::helper('mercadopago');
    }

    /**
     * @return array
     * @throws Exception
     */
    public function toOptionArray()
    {
        $methods = array();
        $methods[] = array('value' => '', 'label' => '');
        $website = $this->helper->getAdminSelectedWebsite();
        $accessToken = $website->getConfig(self::XML_PATH_ACCESS_TOKEN);

        if (empty($accessToken)) {
            return $methods;
        }

        $this->log->log('Get payment methods by country... ', 'mercadopago.log');
        $this->log->log('API payment methods: ' . '/v1/payment_methods?access_token=' . $accessToken, 'mercadopago.log');
        $response = MercadoPago_RestClient_MpRestClient::get('/v1/payment_methods?access_token=' . $accessToken);
        $this->log->log("API payment methods", 'mercadopago.log', $response);

        if (isset($response['error']) || !isset($response['response'])) {
            return $methods;
        }

        $response = $response['response'];

        foreach ($response as $m) {
            if ($m['payment_type_id'] == 'ticket' || $m['payment_type_id'] == 'atm') {
                $methods[] = array(
                    'value' => $m['id'],
                    'label' => $this->helper->__($m['name'])
                );
            }
        }

        return $methods;
    }
}
