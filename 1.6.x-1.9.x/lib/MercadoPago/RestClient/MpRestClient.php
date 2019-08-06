<?php

$GLOBALS["LIB_LOCATION"] = Mage::getBaseDir() . '/lib/MercadoPago/RestClient';
/**
 * Class MPRestClient
 */
class MercadoPago_RestClient_MpRestClient extends MercadoPago_RestClient_AbstractRestClient
{
    const API_MP_BASE_URL = 'https://api.mercadopago.com';

    /**
     * @param $request
     * @param $version
     * @return array|null
     * @throws Exception
     */
    public static function get($request, $version = null)
    {
        if(empty($version)){
            $version = MercadoPago_RestClient_AbstractRestClient::$module_version;
        }

        if(is_string($request)){
            $request = array('uri' => $request);
        }

        $request['method'] = 'GET';
        return self::execAbs($request, $version, self::API_MP_BASE_URL);
    }

    /**
     * @param $request
     * @param $version
     * @return array|null
     * @throws Exception
     */
    public static function post($request, $version)
    {
        $request['method'] = 'POST';
        return self::execAbs($request, $version, self::API_MP_BASE_URL);
    }

    /**
     * @param $request
     * @param $version
     * @return array|null
     * @throws Exception
     */
    public static function put($request, $version)
    {
        $request['method'] = 'PUT';
        return self::execAbs($request, $version, self::API_MP_BASE_URL);
    }

    /**
     * @param $request
     * @param $version
     * @return array|null
     * @throws Exception
     */
    public static function delete($request, $version)
    {
        $request['method'] = 'DELETE';
        return self::execAbs($request, $version, self::API_MP_BASE_URL);
    }

}
