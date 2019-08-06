<?php

/**
 * Class MercadoPago_Core_Model_Preference_Custom
 */
class MercadoPago_Core_Model_Preference_Custom extends MercadoPago_Core_Model_Preference_Abstract
{
    const LOG_FILE = 'mercadopago-custom.log';

    public function __construct()
    {
        $this->logFile = self::LOG_FILE;
        parent::__construct();
    }

    /**
     * @param null $paymentInfo
     * @return array
     * @throws Mage_Core_Exception
     */
    public function createPreference($paymentInfo = null)
    {
        $preference = array();
        $preference['notification_url'] = $this->getNotificationUrl();
        $preference['description'] = $this->getDescription();
        $preference['transaction_amount'] = $this->getTransactionAmount($paymentInfo);
        $preference['external_reference'] = $this->getExternalReference();
        $preference['payer']['email'] = $this->getPayerEmail();

        if (!empty($paymentInfo['identification_type'])) {
            $preference['payer']['identification']['type'] = $this->getPayerIdentificationType($paymentInfo);
            $preference['payer']['identification']['number'] = $this->getPayerIdentificationNumber($paymentInfo);
        }

        $preference['additional_info']['items'] = $this->getItemsInfo($this->order);
        $preference['additional_info']['payer']['first_name'] = $this->getAdditionalInfoPayerFirstName();
        $preference['additional_info']['payer']['last_name'] = $this->getAdditionalInfoPayerLastName();
        $preference['additional_info']['payer']['address']["zip_code"] = $this->getAdditionalInfoPayerAddressZipCode();
        $preference['additional_info']['payer']['address']["street_name"] = $this->getAdditionalInfoPayerAddressStreetName();
        $preference['additional_info']['payer']['address']["street_number"] = $this->getAdditionalInfoPayerAddressStreetNumber();
        $preference['additional_info']['payer']['registration_date'] = $this->getAdditionalInfoPayerRegistrationDate();

        if ($this->order->canShip()) {
            $shipping = $this->order->getShippingAddress()->getData();
            $preference['additional_info']['shipments']['receiver_address']["zip_code"] = $this->getAdditionalInfoShipmentsReceiverZip($shipping);
            $preference['additional_info']['shipments']['receiver_address']["street_name"] = $this->getAdditionalInfoShipmentsReceiverStreetName($shipping);
            $preference['additional_info']['shipments']['receiver_address']["street_number"] = $this->getAdditionalInfoShipmentsReceiverStreetNumber($shipping);
            $preference['additional_info']['shipments']['receiver_address']["floor"] = $this->getAdditionalInfoShipmentsReceiverFloor($shipping);
            $preference['additional_info']['shipments']['receiver_address']["apartment"] = $this->getAdditionalInfoShipmentsReceiverApartment($shipping);
        }

        $preference['additional_info']['payer']['phone']["area_code"] = $this->getAdditionalInfoPayerPhoneAreaCode();
        $preference['additional_info']['payer']['phone']["number"] = $this->getAdditionalInfoPayerPhoneNumber();

        if($couponInfo = $this->isValidCoupon($paymentInfo)){
            $preference['coupon_amount'] = $couponInfo['coupon_amount'];
            $preference['coupon_code'] = strtoupper($couponInfo['coupon_code']);
            $preference['campaign_id'] = $couponInfo['campaign_id'];
        }

        if ($sponsorId = $this->getSponsorId()) {
            $preference['sponsor_id'] = $sponsorId;
        }

        return $preference;
    }

}