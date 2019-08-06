<?php

/**
 * Class MercadoPago_Core_Model_Core
 */
class MercadoPago_Core_Model_Core extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = 'mercadopago';
    protected $_accessToken;
    protected $_clientId;
    protected $_clientSecret;

    protected $_isGateway = true;
    protected $_canOrder = true;
    protected $_canRefund = true;
    protected $_canVoid = true;
    protected $_canUseInternal = false;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = true;
    protected $_canFetchTransactionInfo = true;
    protected $_canCreateBillingAgreement = true;
    protected $_canReviewPayment = true;

    const XML_PATH_ACCESS_TOKEN = 'payment/mercadopago_custom_checkout/access_token';
    const LOG_FILE = 'mercadopago-custom.log';

    /**
     * @return Mage_Core_Model_Abstract
     */
    protected function _getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * @return Mage_Core_Model_Abstract
     */
    protected function _getAdminCheckout()
    {
        return Mage::getSingleton('adminhtml/session_quote');
    }

    /**
     * @param null $quoteId
     * @return Mage_Core_Model_Abstract
     * @throws Mage_Core_Model_Store_Exception
     */
    protected function _getQuote($quoteId = null)
    {
        if (!empty($quoteId)) {
            return Mage::getModel('sales/quote')->load($quoteId);
        } else {
            if (Mage::app()->getStore()->isAdmin()) {
                return $this->_getAdminCheckout()->getQuote();
            } else {
                return $this->_getCheckout()->getQuote();
            }
        }
    }

    /**
     * @param $orderId
     * @return array
     */
    public function getInfoPaymentByOrder($orderId)
    {
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
        $payment = $order->getPayment();
        $infoPayments = array();
        $fields = array(
            array("field" => "cardholderName", "title" => "Card Holder Name: %s"),
            array("field" => "trunc_card", "title" => "Card Number: %s"),
            array("field" => "payment_method", "title" => "Payment Method: %s"),
            array("field" => "expiration_date", "title" => "Expiration Date: %s"),
            array("field" => "installments", "title" => "Installments: %s"),
            array("field" => "statement_descriptor", "title" => "Statement Descriptor: %s"),
            array("field" => "payment_id", "title" => "Payment id (MercadoPago): %s"),
            array("field" => "status", "title" => "Payment Status: %s"),
            array("field" => "status_detail", "title" => "Payment Detail: %s"),
            array("field" => "activation_uri", "title" => "Generate Ticket"),
            array("field" => "payment_id_detail", "title" => "Mercado Pago Payment Id: %s")

        );

        foreach ($fields as $field) {
            if ($payment->getAdditionalInformation($field['field']) != "") {
                $text = Mage::helper('mercadopago')->__($field['title'], Mage::helper('mercadopago')->__($payment->getAdditionalInformation($field['field'])));
                $infoPayments[$field['field']] = array(
                    "text" => $text,
                    "value" => Mage::helper('mercadopago')->__($payment->getAdditionalInformation($field['field']))
                );
            }
        }

        if ($payment->getAdditionalInformation('payer_identification_type') != "") {
            $text = __($payment->getAdditionalInformation('payer_identification_type') . ': ' . $payment->getAdditionalInformation('payer_identification_number'));
            $infoPayments[$payment->getAdditionalInformation('payer_identification_type')] = array(
                "text" => $text,
                "value" => $payment->getAdditionalInformation('payer_identification_number')
            );
        }

        return $infoPayments;
    }

    /**
     * @param $status
     * @return string
     */
    protected function validStatusTwoPayments($status)
    {
        $arrayStatus = explode(" | ", $status);
        $statusVerif = true;
        $statusFinal = "";
        foreach ($arrayStatus as $status):

            if ($statusFinal == "") {
                $statusFinal = $status;
            } else {
                if ($statusFinal != $status) {
                    $statusVerif = false;
                }
            }
        endforeach;

        if ($statusVerif === false) {
            $statusFinal = "other";
        }

        return $statusFinal;
    }

    /**
     * @param $status
     * @param $statusDetail
     * @param $paymentMethod
     * @param $installment
     * @param $amount
     * @return array
     * TODO michel: Retirar variaveis nao utilizadas
     */
    public function getMessageByStatus($status, $statusDetail, $paymentMethod, $installment, $amount)
    {
        $order_id = Mage::getSingleton('checkout/session')->getLastRealOrderId();
        $order = Mage::getModel('sales/order')->loadByIncrementId($order_id);
        $payment = $order->getPayment();


        $status = $payment->getAdditionalInformation('status');
        $statusDetail = $payment->getAdditionalInformation('status_detail');
        $paymentMethod = $payment->getAdditionalInformation('payment_method');
        $installment = $payment->getAdditionalInformation('installments');
        $amount = $this->getTotalOrder($order);

        $status = $this->validStatusTwoPayments($status);
        $statusDetail = $this->validStatusTwoPayments($statusDetail);

        $message = array(
            "title" => "",
            "message" => ""
        );

        $rawMessage = Mage::helper('mercadopago/statusMessage')->getMessage($status);
        $message['title'] = Mage::helper('mercadopago')->__($rawMessage['title']);

        if ($status == 'rejected') {
            if ($statusDetail == 'cc_rejected_invalid_installments') {
                $message['message'] = Mage::helper('mercadopago')
                    ->__(Mage::helper('mercadopago/statusDetailMessage')->getMessage($statusDetail), strtoupper($paymentMethod), $installment);
            } elseif ($statusDetail == 'cc_rejected_call_for_authorize') {
                $message['message'] = Mage::helper('mercadopago')
                    ->__(Mage::helper('mercadopago/statusDetailMessage')->getMessage($statusDetail), strtoupper($paymentMethod), $amount);
            } else {
                $message['message'] = Mage::helper('mercadopago')
                    ->__(Mage::helper('mercadopago/statusDetailMessage')->getMessage($statusDetail), strtoupper($paymentMethod));
            }
        } else {
            $message['message'] = Mage::helper('mercadopago')->__($rawMessage['message']);
        }

        return $message;
    }

    /**
     * @param $customer
     * @param $order
     * @return array
     */
    protected function getCustomerInfo($customer, $order)
    {
        $email = htmlentities($customer->getEmail());
        if ($email == "") {
            $email = $order['customer_email'];
        }

        $firstName = htmlentities($customer->getFirstname());
        if ($firstName == "") {
            $firstName = $order->getBillingAddress()->getFirstname();
        }

        $lastName = htmlentities($customer->getLastname());
        if ($lastName == "") {
            $lastName = $order->getBillingAddress()->getLastname();
        }

        return array('email' => $email, 'first_name' => $firstName, 'last_name' => $lastName);
    }

    /**
     * @param $preference
     * @return mixed
     * @throws MercadoPago_Core_Model_Api_V1_Exception
     */
    public function postPaymentV1($preference)
    {
        if (!$this->_accessToken) {
            $this->_accessToken = Mage::getStoreConfig(self::XML_PATH_ACCESS_TOKEN);
        }
        Mage::helper('mercadopago/log')->log("Access Token for Post", self::LOG_FILE, $this->_accessToken);

        //set sdk php mercadopago
        $mp = Mage::helper('mercadopago')->getApiInstance($this->_accessToken);
        $response = $mp->post("/v1/payments", $preference);
        Mage::helper('mercadopago/log')->log("POST /v1/payments", self::LOG_FILE, $response);

        if ($response['status'] == 200 || $response['status'] == 201) {
            return $response;
        } else {
            $e = "";
            $exception = new MercadoPago_Core_Model_Api_V1_Exception();
            if (count($response['response']['cause']) > 0) {
                foreach ($response['response']['cause'] as $error) {
                    $e .= $exception->getUserMessage($error) . " ";
                }
            } else {
                $e = $exception->getUserMessage();
            }

            Mage::helper('mercadopago/log')->log("error post pago: " . $e, self::LOG_FILE);
            Mage::helper('mercadopago/log')->log("response post pago: ", self::LOG_FILE, $response);

            $exception->setMessage($e);
            throw $exception;
        }
    }

    /**
     * @param $payment_id
     * @return mixed
     */
    public function getPayment($payment_id)
    {
        if (!$this->_clientId || !$this->_clientSecret) {
            $this->_clientId = Mage::getStoreConfig(MercadoPago_Core_Helper_Data::XML_PATH_CLIENT_ID);
            $this->_clientSecret = Mage::getStoreConfig(MercadoPago_Core_Helper_Data::XML_PATH_CLIENT_SECRET);
        }
        $mp = Mage::helper('mercadopago')->getApiInstance($this->_clientId, $this->_clientSecret);

        return $mp->get("/v1/payments/" . $payment_id);
    }

    /**
     * @param $payment_id
     * @return mixed
     */
    public function getPaymentV1($payment_id)
    {
        if (!$this->_accessToken) {
            $this->_accessToken = Mage::getStoreConfig(self::XML_PATH_ACCESS_TOKEN);
        }
        $mp = Mage::helper('mercadopago')->getApiInstance($this->_accessToken);

        return $mp->get("/v1/payments/" . $payment_id);
    }

    public function getMerchantOrder($merchant_order_id)
    {
        if (!$this->_clientId || !$this->_clientSecret) {
            $this->_clientId = Mage::getStoreConfig(MercadoPago_Core_Helper_Data::XML_PATH_CLIENT_ID);
            $this->_clientSecret = Mage::getStoreConfig(MercadoPago_Core_Helper_Data::XML_PATH_CLIENT_SECRET);
        }
        $mp = Mage::helper('mercadopago')->getApiInstance($this->_clientId, $this->_clientSecret);

        return $mp->get("/merchant_orders/" . $merchant_order_id);
    }

    public function getPaymentMethods()
    {
        if (!$this->_accessToken) {
            $this->_accessToken = Mage::getStoreConfig(self::XML_PATH_ACCESS_TOKEN);
        }

        $mp = Mage::helper('mercadopago')->getApiInstance($this->_accessToken);

        $payment_methods = $mp->get("/v1/payment_methods");

        return $payment_methods;
    }


    public function getAmount()
    {
        $quote = $this->_getQuote();
        $total = $quote->getBaseSubtotalWithDiscount() + $quote->getShippingAddress()->getShippingAmount() + $quote->getShippingAddress()->getBaseTaxAmount();

        return (float)$total;
    }


    public function updateOrder($order = null, $data)
    {
        $helper = Mage::helper('mercadopago');
        $statusHelper = Mage::helper('mercadopago/statusUpdate');
        $helper->log('Update Order', 'mercadopago-notification.log');

        if (!isset($data['external_reference'])) {
            return;
        }

        if (!$order) {
            $order = Mage::getModel('sales/order')->loadByIncrementId($data['external_reference']);
        }

        $paymentOrder = $order->getPayment();
        $this->_saveTransaction($data, $paymentOrder);

        if ($statusHelper->isStatusUpdated()) {
            return;
        }
        try {
            $additionalFields = array(
                'status',
                'status_detail',
                'payment_id',
                'transaction_amount',
                'cardholderName',
                'installments',
                'statement_descriptor',
                'trunc_card',
                'id',
                'payer_identification_type',
                'payer_identification_number'
            );

            $infoPayments = $paymentOrder->getAdditionalInformation();

            if (!isset($infoPayments['first_payment_id'])) {
                $paymentOrder = $this->_addAdditionalInformationToPaymentOrder($data, $additionalFields, $paymentOrder);
            }

            if (isset($data['id'])) {
                $paymentOrder->setAdditionalInformation('payment_id_detail', $data['id']);
            }

            if (isset($data['payer_identification_type']) & isset($data['payer_identification_number'])) {
                $paymentOrder->setAdditionalInformation($data['payer_identification_type'], $data['payer_identification_number']);
            }

            $paymentStatus = $paymentOrder->save();
            $helper->log('Update Payment', 'mercadopago.log', $paymentStatus->getData());

            $statusSave = $order->save();
            $helper->log('Update order', 'mercadopago.log', $statusSave->getData());
        } catch (Exception $e) {
            $helper->log('Error in update order status: ' . $e, 'mercadopago.log');
            $this->getResponse()->setBody($e);

            $this->getResponse()->setHttpResponseCode(MercadoPago_Core_Helper_Response::HTTP_BAD_REQUEST);
        }
    }

    protected function _addAdditionalInformationToPaymentOrder($data, $additionalFields, $paymentOrder)
    {
        foreach ($additionalFields as $field) {
            if (isset($data[$field])) {
                $paymentOrder->setAdditionalInformation($field, $data[$field]);
            }
        }

        if (isset($data['payment_method_id'])) {
            $paymentOrder->setAdditionalInformation('payment_method', $data['payment_method_id']);
        }

        if (isset($data['merchant_order_id'])) {
            $paymentOrder->setAdditionalInformation('merchant_order_id', $data['merchant_order_id']);
        }
        return $paymentOrder;
    }

    protected function _saveTransaction($data, $paymentOrder)
    {
        try {
            $paymentOrder->setTransactionId($data['id']);
            $paymentOrder->setParentTransactionId($paymentOrder->getTransactionId());
            $transaction = $paymentOrder->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_PAYMENT, null, true, "");
            $transaction->setAdditionalInformation('raw_details_info', $data);
            $transaction->setIsClosed(true);
            $transaction->save();
        } catch (Exception $e) {
            Mage::helper('mercadopago/log')->log('error in update order status: ' . $e, 'mercadopago.log');
        }
    }

    public function getRecurringPayment($id)
    {
        if (!$this->_clientId || !$this->_clientSecret) {
            $this->_clientId = Mage::getStoreConfig(MercadoPago_Core_Helper_Data::XML_PATH_CLIENT_ID);
            $this->_clientSecret = Mage::getStoreConfig(MercadoPago_Core_Helper_Data::XML_PATH_CLIENT_SECRET);
        }
        $mp = Mage::helper('mercadopago')->getApiInstance($this->_clientId, $this->_clientSecret);

        return $mp->get_preapproval_payment($id);
    }

    public function getTotalOrder($order)
    {
        $total = $order->getBaseGrandTotal();

        if (!$total) {
            $total = $order->getBasePrice() + $order->getBaseShippingAmount();
        }

        $total = number_format($total, 2, '.', '');
        return $total;
    }

    // Identification Type

    public function getIdentificationType()
    {
        if (!$this->_accessToken) {
            $this->_accessToken = Mage::getStoreConfig(self::XML_PATH_ACCESS_TOKEN);
        }

        $mp = Mage::helper('mercadopago')->getApiInstance($this->_accessToken);

        $payment_methods = $mp->get("/v1/identification_types");

        return $payment_methods;
    }


    public function getBanks()
    {
        if (!$this->_accessToken) {
            $this->_accessToken = Mage::getStoreConfig(self::XML_PATH_ACCESS_TOKEN);
        }

        $mp = Mage::helper('mercadopago')->getApiInstance($this->_accessToken);

        $array = array(
            'payment_type_id' => 'bank_transfer',
            'marketplace' => 'NONE'
        );

        $payment_methods = $mp->get("/v1/payment_methods/search");

        return $payment_methods;
    }


}
