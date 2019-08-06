<?php

abstract class MercadoPago_Core_Model_Preference_Abstract extends Mage_Core_Model_Abstract
{

    public $helper;
    public $customer;
    public $customerInfo;
    public $quote;
    public $billingAddress;
    public $order;
    public $orderIncrementId;
    public $accessToken;
    public $logFile;

    const XML_PATH_ACCESS_TOKEN = 'payment/mercadopago_custom_checkout/access_token';

    /**
     * MercadoPago_Core_Model_Preference_Abstract constructor.
     * @throws Mage_Core_Model_Store_Exception
     */
    public function __construct()
    {
        $this->helper = Mage::helper('mercadopago');
        $this->customer = Mage::getSingleton('customer/session')->getCustomer();
        $this->quote = $this->_getQuote();
        $this->billingAddress = $this->quote->getBillingAddress()->getData();
        $this->order = $this->_getOrder($this->_getQuote()->getReservedOrderId());
        $this->orderIncrementId = $this->_getQuote()->getReservedOrderId();
        $this->accessToken = Mage::getStoreConfig(self::XML_PATH_ACCESS_TOKEN);
        $this->customerInfo = $this->getCustomerInfo($this->customer, $this->order);
    }


    /**
     * @param $incrementId
     * @return Mage_Sales_Model_Order
     */
    public function _getOrder($incrementId)
    {
        return Mage::getModel('sales/order')->loadByIncrementId($incrementId);
    }

    /**
     * @return Mage_Checkout_Model_Session|Mage_Core_Model_Abstract
     */
    public function _getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * @return Mage_Adminhtml_Model_Session_Quote|Mage_Core_Model_Abstract
     */
    public function _getAdminCheckout()
    {
        return Mage::getSingleton('adminhtml/session_quote');
    }

    /**
     * @param null $quoteId
     * @return Mage_Core_Model_Abstract|Mage_Sales_Model_Quote
     * @throws Mage_Core_Model_Store_Exception
     */
    public function _getQuote($quoteId = null)
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
     * @param $customer
     * @param $order
     * @return array
     */
    public function getCustomerInfo($customer, $order)
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
     * @return float
     */
    public function getAmount()
    {
        $quote = $this->quote;
        $total = $quote->getBaseSubtotalWithDiscount() + $quote->getShippingAddress()->getShippingAmount()
            + $quote->getShippingAddress()->getBaseTaxAmount();

        return (float)$total;
    }

    /**
     * @param $order
     * @return array
     */
    public function getItemsInfo($order)
    {
        $dataItems = array();
        foreach ($order->getAllVisibleItems() as $item) {
            $product = $item->getProduct();
            $image = (string)Mage::helper('catalog/image')->init($product, 'image');

            $dataItems[] = array(
                "id" => $item->getSku(),
                "title" => $product->getName(),
                "description" => $product->getName(),
                "picture_url" => $image,
                "category_id" => Mage::getStoreConfig('payment/mercadopago/category_id'),
                "quantity" => (int)number_format($item->getQtyOrdered(), 0, '.', ''),
                "unit_price" => (float)number_format($product->getPrice(), 2, '.', '')
            );
        }

        /* verify discount and add it like an item */
        $discount = $this->getDiscount(); //TODO ?
        if ($discount != 0) {
            $dataItems[] = array(
                "title" => "Discount by the Store",
                "description" => "Discount by the Store",
                "quantity" => 1,
                "unit_price" => (float)number_format($discount, 2, '.', '')
            );
        }

        return $dataItems;
    }

    /**
     * @param $coupon
     * @return mixed
     */
    public function getCouponApi($coupon)
    {
        $mp = Mage::helper('mercadopago')->getApiInstance($this->accessToken);
        $params = array(
            "transaction_amount" => $this->getAmount(),
            "payer_email" => $this->getEmailCustomer(),
            "coupon_code" => $coupon
        );

        $details_discount = $mp->get("/discount_campaigns", $params);
        $details_discount['response']['transaction_amount'] = $params['transaction_amount'];
        $details_discount['response']['params'] = $params;

        if ($details_discount['status'] >= 400 && $details_discount['status'] < 500) {
            $details_discount['response']['message'] = Mage::helper('mercadopago')->__($details_discount['response']['message']);
        }

        return $details_discount;
    }

    /**
     * @return string
     */
    public function getEmailCustomer()
    {
        $email = $this->customer->getEmail();
        if (empty($email)) {
            $email = $this->quote->getBillingAddress()->getEmail();
        }

        return $email;
    }

    /**
     * @param $paymentInfo
     * @return array|null
     * @throws Mage_Core_Exception
     */
    public function isValidCoupon($paymentInfo)
    {
        if (!empty($paymentInfo['coupon_code'])) {
            $couponCode = $paymentInfo['coupon_code'];
            Mage::helper('mercadopago/log')->log("Validating coupon_code: " . $couponCode, $this->logFile);
            $coupon = $this->getCouponApi($couponCode);
            Mage::helper('mercadopago/log')->log("Response API Coupon: ", $this->logFile, $coupon);
            if (isset($coupon['status']) && $coupon['status'] < 300) {
                $couponInfo = $this->getCouponInfo($coupon, $couponCode);
                return $couponInfo;
            }
        }
        return null;
    }

    /**
     * @param $coupon
     * @param $couponCode
     * @return array
     */
    public function getCouponInfo($coupon, $couponCode)
    {
        $infoCoupon = array();
        $infoCoupon['coupon_amount'] = (float)$coupon['response']['coupon_amount'];
        $infoCoupon['coupon_code'] = $couponCode;
        $infoCoupon['campaign_id'] = $coupon['response']['id'];
        if ($coupon['status'] == 200) {
            Mage::helper('mercadopago/log')->log("Coupon applied. API response 200.", $this->logFile);
        } else {
            Mage::helper('mercadopago/log')->log("Coupon invalid, not applied.", $this->logFile);
        }

        return $infoCoupon;
    }

    /**
     * @return int|mixed
     */
    public function getSponsorId()
    {
        $sponsorId = Mage::getStoreConfig('payment/mercadopago/sponsor_id');
        Mage::helper('mercadopago/log')->log("Sponsor_id", 'mercadopago-standard.log', $sponsorId);
        if (!empty($sponsorId)) {
            $sponsorId = (int)$sponsorId;
            Mage::helper('mercadopago/log')->log("Sponsor_id identificado", $this->logFile, $sponsorId);
        }
        return $sponsorId;
    }

    /**
     * @return string
     */
    public function getNotificationUrl()
    {
        return Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK) . "mercadopago/notifications/custom";
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->helper->__("Order # %s in store %s", $this->orderIncrementId, Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK, true));
    }

    /**
     * @param null $paymentInfo
     * @return float
     */
    public function getTransactionAmount($paymentInfo = null)
    {
        if (isset($paymentInfo['transaction_amount'])) {
            return (float)$paymentInfo['transaction_amount'];
        }

        return $preference['transaction_amount'] = (float)$this->getAmount();
    }

    /**
     * @return string
     */
    public function getExternalReference()
    {
        return $this->orderIncrementId;
    }

    /**
     * @return mixed
     */
    public function getPayerEmail()
    {
        return $this->customerInfo['email'];
    }

    /**
     * @param $paymentInfo
     * @return mixed
     */
    public function getPayerIdentificationType($paymentInfo)
    {
        return $paymentInfo['identification_type'];
    }

    /**
     * @param $paymentInfo
     * @return mixed
     */
    public function getPayerIdentificationNumber($paymentInfo)
    {
        return $paymentInfo['identification_number'];

    }

    /**
     * @return mixed
     */
    public function getAdditionalInfoPayerFirstName()
    {
        return $this->customerInfo['first_name'];

    }

    /**
     * @return mixed
     */
    public function getAdditionalInfoPayerLastName()
    {
        return $this->customerInfo['last_name'];

    }

    /**
     * @return mixed
     */
    public function getAdditionalInfoPayerAddressZipCode()
    {
        return $this->billingAddress['postcode'];
    }

    /**
     * @return string
     */
    public function getAdditionalInfoPayerAddressStreetName()
    {
        return $this->billingAddress['street'] . " - " . $this->billingAddress['city'] . " - " . $this->billingAddress['country_id'];
    }

    /**
     * @return string
     */
    public function getAdditionalInfoPayerAddressStreetNumber()
    {
        return '';
    }

    /**
     * @return string
     */
    public function getAdditionalInfoPayerRegistrationDate()
    {
        return date('Y-m-d', $this->customer->getCreatedAtTimestamp()) . "T" . date('H:i:s', $this->customer->getCreatedAtTimestamp());
    }

    /**
     * @param $shipping
     * @return mixed
     */
    public function getAdditionalInfoShipmentsReceiverZip($shipping)
    {
        return $shipping['postcode'];
    }

    /**
     * @param $shipping
     * @return string
     */
    public function getAdditionalInfoShipmentsReceiverStreetName($shipping)
    {
        return $shipping['street'] . " - " . $shipping['city'] . " - " . $shipping['country_id'];
    }

    /**
     * @param $shipping
     * @return string
     */
    public function getAdditionalInfoShipmentsReceiverStreetNumber($shipping = null)
    {
        return '';
    }

    /**
     * @param $shipping
     * @return string
     */
    public function getAdditionalInfoShipmentsReceiverFloor($shipping = null)
    {
        return '-';
    }

    /**
     * @param $shipping
     * @return string
     */
    public function getAdditionalInfoShipmentsReceiverApartment($shipping = null)
    {
        return '-';
    }

    /**
     * @return string
     */
    public function getAdditionalInfoPayerPhoneAreaCode()
    {
        return '0';

    }

    /**
     * @return mixed
     */
    public function getAdditionalInfoPayerPhoneNumber()
    {
        return $this->billingAddress['telephone'];
    }

    /**
     * @return string
     */
    public function getCouponAmount()
    {
        return '-';
    }

    /**
     * @return string
     */
    public function getCouponCode()
    {
        return '-';
    }

    /**
     * @return string
     */
    public function getCampaignId()
    {
        return '-';
    }


}