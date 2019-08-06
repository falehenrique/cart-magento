<?php

/**
 * Class MercadoPago_Core_Model_Source_PaymentMethods
 */
class MercadoPago_Core_Model_Source_PaymentMethods extends Mage_Payment_Model_Method_Abstract
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
        $clientId = $website->getConfig(MercadoPago_Core_Helper_Data::XML_PATH_CLIENT_ID);
        $clientSecret = $website->getConfig(MercadoPago_Core_Helper_Data::XML_PATH_CLIENT_SECRET);
        if (empty($accessToken) && !$this->helper->isValidClientCredentials($clientId, $clientSecret)) {
            return $methods;
        }

        //if accessToken is empty uses clientId and clientSecret to obtain it
        if (empty($accessToken)) {
            $accessToken = $this->helper->getAccessToken();
        }

        $this->log->log('Get payment methods by country... ', 'mercadopago.log');
        $this->log->log('API payment methods: ' . '/v1/payment_methods?access_token=' . $accessToken, 'mercadopago.log');
        $response = MercadoPago_RestClient_MpRestClient::get('/sites/' . strtoupper($website->getConfig('payment/mercadopago/country')) . '/payment_methods?marketplace=NONE');

        $this->log->log("API payment methods", 'mercadopago.log', $response);

        if (isset($response['error']) || !isset($response['response'])) {
            return $methods;
        }

        $response = $response['response'];

        foreach ($response as $m) {
            if ($m['id'] != 'account_money') {
                $methods[] = array(
                    'value' => $m['id'],
                    'label' => $this->helper->__($m['name'])
                );
            }
        }

        return $methods;
    }
}
