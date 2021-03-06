<?php

if (!function_exists('apache_request_headers')) {

    /**
     * Implementation copy-pasted from https://www.php.net/manual/en/function.apache-request-headers.php
     * @author limalopex.eisfux.de <limalopex.eisfux.de>
     */
    function apache_request_headers()
    {
        $arh = array();
        $rx_http = '/\AHTTP_/';
        foreach ($_SERVER as $key => $val) {
            if (preg_match($rx_http, $key)) {
                $arh_key = preg_replace($rx_http, '', $key);
                $rx_matches = array();
                // do some nasty string manipulations to restore the original letter case
                // this should work in most cases
                $rx_matches = explode('_', $arh_key);
                if (count($rx_matches) > 0 and strlen($arh_key) > 2) {
                    foreach ($rx_matches as $ak_key => $ak_val) $rx_matches[$ak_key] = ucfirst($ak_val);
                    $arh_key = implode('-', $rx_matches);
                }
                $arh[$arh_key] = $val;
            }
        }
        return ($arh);
    }
}
header($_SERVER["SERVER_PROTOCOL"] . " 202 Verified");
echo json_encode(apache_request_headers());
