<?php

/**
 * Class MP
 */
class MercadoPago_MP
{

    private $version = '4.0.0';
    private $client_id;
    private $client_secret;
    private $ll_access_token;
    private $sandbox = FALSE;
    private $accessTokenByClient;
    private $paymentClass;
    private $_platform = null;
    private $_so = null;
    private $_type = null;

    /**
     * MP constructor.
     * @throws Exception
     */
    public function __construct()
    {
        $includes_path = dirname(__FILE__);
        require_once($includes_path . '/RestClient/AbstractRestClient.php');
        require_once($includes_path . '/RestClient/MeliRestClient.php');
        require_once($includes_path . '/RestClient/MpRestClient.php');

        $params = func_num_args();

        if ($params > 2 || $params < 1) {
            Mage::throwException("Invalid arguments. Use CLIENT_ID and CLIENT SECRET, or ACCESS_TOKEN");
        }

        if ($params == 1) {
            $this->version = 'version';
            $this->ll_access_token = func_get_arg(0);
        }

        if ($params == 3) {
            $this->version = func_get_arg(0);
            $this->client_id = func_get_arg(1);
            $this->client_secret = func_get_arg(2);
        }
    }

    /**
     * @param $email
     */
    public function set_email($email)
    {
        MercadoPago_RestClient_MpRestClient::set_email($email);
        MercadoPago_RestClient_MeliRestClient::set_email($email);
    }

    /**
     * @param $country_code
     */
    public function set_locale($country_code)
    {
        MercadoPago_RestClient_MpRestClient::set_locale($country_code);
        MercadoPago_RestClient_MeliRestClient::set_locale($country_code);
    }

    /**
     * @param null $enable
     * @return bool
     */
    public function sandbox_mode($enable = NULL)
    {
        if (!is_null($enable)) {
            $this->sandbox = $enable === TRUE;
        }
        return $this->sandbox;
    }

    /**
     * @return mixed|null
     * @throws Exception
     */
    public function get_access_token()
    {

        if (isset($this->ll_access_token) && !is_null($this->ll_access_token)) {
            return $this->ll_access_token;
        }

        if (!empty($this->accessTokenByClient)) {
            return $this->accessTokenByClient;
        }

        $app_client_values = array(
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'grant_type' => 'client_credentials'
        );

        $access_data = MercadoPago_RestClient_MpRestClient::post(
            array(
                'uri' => '/oauth/token',
                'data' => $app_client_values,
                'headers' => array(
                    'content-type' => 'application/x-www-form-urlencoded'
                )
            ),
            $this->version
        );

        if ($access_data['status'] != 200) {
            return null;
        }

        $response = $access_data['response'];
        $this->accessTokenByClient = $response['access_token'];

        return $this->accessTokenByClient;
    }

    /**
     * @param $id
     * @return array|null
     * @throws Exception
     */
    public function search_paymentV1($id)
    {

        $request = array(
            'uri' => '/v1/payments/' . $id,
            'params' => array('access_token' => $this->get_access_token())
        );

        $payment = MercadoPago_RestClient_MpRestClient::get($request, $this->version);
        return $payment;
    }

    //=== CUSTOMER CARDS FUNCTIONS ===

    /**
     * @param $payer_email
     * @return array|mixed|null
     * @throws Exception
     */
    public function get_or_create_customer($payer_email)
    {

        $customer = $this->search_customer($payer_email);

        if ($customer['status'] == 200 && $customer['response']['paging']['total'] > 0) {
            $customer = $customer['response']['results'][0];
        } else {
            $resp = $this->create_customer($payer_email);
            $customer = $resp['response'];
        }

        return $customer;

    }

    /**
     * @param $email
     * @return array|null
     * @throws Exception
     */
    public function create_customer($email)
    {

        $request = array(
            'uri' => '/v1/customers',
            'params' => array(
                'access_token' => $this->get_access_token()
            ),
            'data' => array(
                'email' => $email
            )
        );

        $customer = MercadoPago_RestClient_MpRestClient::post($request, $this->version);
        return $customer;

    }

    /**
     * @param $email
     * @return array|null
     * @throws Exception
     */
    public function search_customer($email)
    {

        $request = array(
            'uri' => '/v1/customers/search',
            'params' => array(
                'access_token' => $this->get_access_token(),
                'email' => $email
            )
        );

        $customer = MercadoPago_RestClient_MpRestClient::get($request, $this->version);
        return $customer;

    }

    /**
     * @param $customer_id
     * @param $token
     * @param null $payment_method_id
     * @param null $issuer_id
     * @return array|null
     * @throws Exception
     */
    public function create_card_in_customer($customer_id, $token, $payment_method_id = null,
                                            $issuer_id = null)
    {

        $request = array(
            'uri' => '/v1/customers/' . $customer_id . '/cards',
            'params' => array(
                'access_token' => $this->get_access_token()
            ),
            'data' => array(
                'token' => $token,
                'issuer_id' => $issuer_id,
                'payment_method_id' => $payment_method_id
            )
        );

        $card = MercadoPago_RestClient_MpRestClient::post($request, $this->version);
        return $card;

    }

    /**
     * @param $customer_id
     * @param $token
     * @return array|null
     * @throws Exception
     */
    public function get_all_customer_cards($customer_id, $token)
    {

        $request = array(
            'uri' => '/v1/customers/' . $customer_id . '/cards',
            'params' => array(
                'access_token' => $this->get_access_token()
            )
        );

        $cards = MercadoPago_RestClient_MpRestClient::get($request, $this->version);
        return $cards;

    }

    //=== COUPOM AND DISCOUNTS FUNCTIONS ===

    /**
     * @param $transaction_amount
     * @param $payer_email
     * @param $coupon_code
     * @return array|null
     * @throws Exception
     */
    public function check_discount_campaigns($transaction_amount, $payer_email, $coupon_code)
    {

        $request = array(
            'uri' => '/discount_campaigns',
            'params' => array(
                'access_token' => $this->get_access_token(),
                'transaction_amount' => $transaction_amount,
                'payer_email' => $payer_email,
                'coupon_code' => $coupon_code
            )
        );

        $discount_info = MercadoPago_RestClient_MpRestClient::get($request, $this->version);
        return $discount_info;

    }

    //=== CHECKOUT AUXILIARY FUNCTIONS ===

    /**
     * @param $id
     * @return array|null
     * @throws Exception
     */
    public function get_authorized_payment($id)
    {

        $request = array(
            'uri' => '/authorized_payments/{$id}',
            'params' => array(
                'access_token' => $this->get_access_token()
            )
        );

        $authorized_payment_info = MercadoPago_RestClient_MpRestClient::get($request, $this->version);
        return $authorized_payment_info;

    }

    /**
     * @param $preference
     * @return array|null
     */
    public function create_preference($preference)
    {

        $request = array(
            'uri' => '/checkout/preferences',
            'params' => array(
                'access_token' => $this->get_access_token()
            ),
            'headers' => array(
                'user-agent' => 'platform:desktop,type:woocommerce,so:' . $this->version
            ),
            'data' => $preference
        );

        $preference_result = MercadoPago_RestClient_MpRestClient::post($request, $this->version);
        return $preference_result;

    }

    /**
     * @param $id
     * @param $preference
     * @return array|null
     */
    public function update_preference($id, $preference)
    {

        $request = array(
            'uri' => '/checkout/preferences/{$id}',
            'params' => array(
                'access_token' => $this->get_access_token()
            ),
            'data' => $preference
        );

        $preference_result = MercadoPago_RestClient_MpRestClient::put($request, $this->version);
        return $preference_result;

    }

    /**
     * @param $id
     * @return array|null
     * @throws Exception
     */
    public function get_preference($id)
    {

        $request = array(
            'uri' => '/checkout/preferences/{$id}',
            'params' => array(
                'access_token' => $this->get_access_token()
            )
        );

        $preference_result = MercadoPago_RestClient_MpRestClient::get($request, $this->version);
        return $preference_result;

    }

    /**
     * @param $preference
     * @return array|null
     * @throws Exception
     */
    public function create_payment($preference)
    {

        $request = array(
            'uri' => '/v1/payments',
            'params' => array(
                'access_token' => $this->get_access_token()
            ),
            'headers' => array(
                'X-Tracking-Id' => 'platform:v1-whitelabel,type:woocommerce,so:' . $this->version
            ),
            'data' => $preference
        );

        $payment = MercadoPago_RestClient_MpRestClient::post($request, $this->version);
        return $payment;
    }

    /**
     * @param $preapproval_payment
     * @return array|null
     * @throws Exception
     */
    public function create_preapproval_payment($preapproval_payment)
    {

        $request = array(
            'uri' => '/preapproval',
            'params' => array(
                'access_token' => $this->get_access_token()
            ),
            'data' => $preapproval_payment
        );

        $preapproval_payment_result = MercadoPago_RestClient_MpRestClient::post($request, $this->version);
        return $preapproval_payment_result;

    }

    /**
     * @param $id
     * @return array|null
     * @throws Exception
     */
    public function get_preapproval_payment($id)
    {

        $request = array(
            'uri' => '/preapproval/' . $id,
            'params' => array(
                'access_token' => $this->get_access_token()
            )
        );

        $preapproval_payment_result = MercadoPago_RestClient_MpRestClient::get($request, $this->version);
        return $preapproval_payment_result;

    }

    /**
     * @param $id
     * @param $preapproval_payment
     * @return array|null
     * @throws Exception
     */
    public function update_preapproval_payment($id, $preapproval_payment)
    {

        $request = array(
            'uri' => '/preapproval/' . $id,
            'params' => array(
                'access_token' => $this->get_access_token()
            ),
            'data' => $preapproval_payment
        );

        $preapproval_payment_result = MercadoPago_RestClient_MpRestClient::put($request, $this->version);
        return $preapproval_payment_result;

    }

    /**
     * @param $id
     * @return array|null
     * @throws Exception
     */
    public function cancel_preapproval_payment($id)
    {

        $request = array(
            'uri' => '/preapproval/' . $id,
            'params' => array(
                'access_token' => $this->get_access_token()
            ),
            'data' => array(
                'status' => 'cancelled'
            )
        );

        $response = MercadoPago_RestClient_MpRestClient::put($request, $this->version);
        return $response;

    }

    //=== REFUND AND CANCELING FLOW FUNCTIONS ===

    /**
     * @param $id
     * @return array|null
     * @throws Exception
     */
    public function refund_payment($id)
    {

        $request = array(
            'uri' => '/v1/payments/' . $id . '/refunds',
            'params' => array(
                'access_token' => $this->get_access_token()
            )
        );

        $response = MercadoPago_RestClient_MpRestClient::post($request, $this->version);
        return $response;

    }

    /**
     * @param $id
     * @param $amount
     * @param $reason
     * @param $external_reference
     * @return array|null
     * @throws Exception
     */
    public function partial_refund_payment($id, $amount, $reason, $external_reference)
    {

        $request = array(
            'uri' => '/v1/payments/' . $id . '/refunds?access_token=' . $this->get_access_token(),
            'data' => array(
                'amount' => $amount,
                'metadata' => array(
                    'metadata' => $reason,
                    'external_reference' => $external_reference
                )
            )
        );

        $response = MercadoPago_RestClient_MpRestClient::post($request, $this->version);
        return $response;

    }

    /**
     * @param $id
     * @return array|null
     * @throws Exception
     */
    public function cancel_payment($id)
    {

        $request = array(
            'uri' => '/v1/payments/' . $id,
            'params' => array(
                'access_token' => $this->get_access_token()
            ),
            'data' => '{"status":"cancelled"}'
        );

        $response = MercadoPago_RestClient_MpRestClient::put($request, $this->version);
        return $response;

    }

    /**
     * @return array|null
     * @throws Exception
     */
    public function get_payment_methods()
    {
        $request = array(
            'uri' => '/v1/payment_methods',
            'params' => array(
                'access_token' => $this->get_access_token()
            )
        );

        $response = MercadoPago_RestClient_MpRestClient::get($request, $this->version);

        return $response;
    }

    //=== GENERIC RESOURCE CALL METHODS ===

    /**
     * @param $request
     * @param null $params
     * @param bool $authenticate
     * @return array|null
     * @throws Exception
     */
    public function get($request, $params = null, $authenticate = true)
    {

        if (is_string($request)) {
            $request = array(
                'uri' => $request,
                'params' => $params,
                'authenticate' => $authenticate
            );
        }

        $request['params'] = isset($request['params']) && is_array($request['params']) ?
            $request['params'] :
            array();

        if (!isset($request['authenticate']) || $request['authenticate'] !== false) {
            $request['params']['access_token'] = $this->get_access_token();
        }

        $result = MercadoPago_RestClient_MpRestClient::get($request, $this->version);
        return $result;

    }

    /**
     * @param $request
     * @param null $data
     * @param null $params
     * @return array|null
     * @throws Exception
     */
    public function post($request, $data = null, $params = null)
    {

        if (is_string($request)) {
            $request = array(
                'uri' => $request,
                'data' => $data,
                'params' => $params
            );
        }

        $request['params'] = isset($request['params']) && is_array($request['params']) ?
            $request["params"] :
            array();

        if (!isset ($request['authenticate']) || $request['authenticate'] !== false) {
            $request['params']['access_token'] = $this->get_access_token();
        }

        $result = MercadoPago_RestClient_MpRestClient::post($request, $this->version);
        return $result;

    }

    /**
     * @param $request
     * @param null $data
     * @param null $params
     * @return array|null
     * @throws Exception
     */
    public function put($request, $data = null, $params = null)
    {

        if (is_string($request)) {
            $request = array(
                'uri' => $request,
                'data' => $data,
                'params' => $params
            );
        }

        $request['params'] = isset($request['params']) && is_array($request['params']) ?
            $request['params'] :
            array();

        if (!isset ($request['authenticate']) || $request['authenticate'] !== false) {
            $request['params']['access_token'] = $this->get_access_token();
        }

        $result = MercadoPago_RestClient_MpRestClient::put($request, $this->version);
        return $result;

    }

    /**
     * @param $request
     * @param null $params
     * @return array|null
     * @throws Exception
     */
    public function delete($request, $params = null)
    {

        if (is_string($request)) {
            $request = array(
                'uri' => $request,
                'params' => $params
            );
        }

        $request['params'] = isset($request['params']) && is_array($request['params']) ?
            $request['params'] :
            array();

        if (!isset($request['authenticate']) || $request['authenticate'] !== false) {
            $request['params']['access_token'] = $this->get_access_token();
        }

        $result = MercadoPago_RestClient_MpRestClient::delete($request, $this->version);
        return $result;

    }

    //=== MODULE ANALYTICS FUNCTIONS ===

    /**
     * @param $module_info
     * @return array|null
     * @throws Exception
     */
    public function analytics_save_settings($module_info)
    {

        $request = array(
            'uri' => '/modules/tracking/settings?access_token=' . $this->get_access_token(),
            'data' => $module_info
        );

        $result = MercadoPago_RestClient_MpRestClient::post($request, $this->version);
        return $result;

    }

    /**
     * @param null $payment
     */
    public function setPaymentClass($payment = null)
    {
        if (!empty($payment)) {
            $this->paymentClass = get_class($payment);
        }
    }

    /**
     * @return mixed
     */
    public function getPaymentClass()
    {
        return $this->paymentClass;
    }

    /**
     * @param null $platform
     */
    public function set_platform($platform)
    {
        $this->_platform = $platform;
    }

    /**
     * @param string $so
     */
    public function set_so($so = '')
    {
        $this->_so = $so;
    }

    /**
     * @param $type
     */
    public function set_type($type)
    {
        $this->_type = $type;
    }
}
