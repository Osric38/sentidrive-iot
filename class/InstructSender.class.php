<?php

class InstructSender {
    public static function send($aDeviceImei, $aIsn, $aType, $aParams = array(), $aSync = true) {
        $requestParameters = self::_getRequestParameters($aDeviceImei, $aIsn, $aType, $aParams, $aSync);
        print_r($requestParameters);
        
        if (!$requestParameters['success']) {
            return $requestParameters;
        }
        
        $result = self::_sendRequest('POSTUE', $requestParameters['parameters']);
        if (!$result['success']) {
            return array('success' => false, 'message' => 'Request failed');
        }
        print_r($result['response']);
        if (array_key_exists('error', $result['response'])) {
            return array('success' => false, 'message' => $result['response']['error'] . ' (' . $result['response']['status'] . ')');
        }
        
        if (array_key_exists('code', $result['response']) && $result['response']['code'] != 0) {
            return array('success' => false, 'message' => $result['response']['msg'] . ' (' . $result['response']['code'] . ')');
        }
        
        if ($result['response']['msg'] != 'success') {
            return array('success' => false, 'message' => $result['response']['msg']);
        }
        return array('success' => true, 'content' => $result['response']['data']['_content'],
                                        'code' => $result['response']['data']['_code'],
                                        'message' => $result['response']['data']['_msg']);
    }
    
    protected static function _sendRequest($aType='GET', $aParameters = array()) {
        $uri = 'http://iot.priv.sentidrive.fr:10088/api/device/sendInstruct';
        
        $cURLConnection = curl_init();
        $headers = array();
        $requestType = $aType;
        if ($aType == 'POST') {
            curl_setopt($cURLConnection, CURLOPT_POSTFIELDS, json_encode($aParameters));
        } elseif ($aType == 'POSTUE') {
            $requestType = 'POST';
            $headers = array( "Content-Type: application/x-www-form-urlencoded");
            $args = array();
            foreach ($aParameters as $key => $value) {
                $args[] = $key . '=' . urlencode(utf8_decode($value));
            }
            $headers = array( "Content-Type: application/x-www-form-urlencoded");
            curl_setopt($cURLConnection, CURLOPT_POSTFIELDS, implode('&', $args));
        } elseif ($aType == 'PUT') {
            $headers = array( "Content-Type: application/json; charset=UTF-8");
            curl_setopt($cURLConnection, CURLOPT_POSTFIELDS, $aParameters);
        } elseif ($aType == 'GET') {
            $args = array();
            foreach ($aParameters as $key => $value) {
                $args[] = $key . '=' . urlencode(utf8_decode($value));
            }
            if (!empty($args)) {
                $uri .= '?' . implode('&', $args);
            }
        }
        
        $params = array(CURLOPT_URL => $uri,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => "",
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_SSL_VERIFYHOST => 0,
                        CURLOPT_SSL_VERIFYPEER => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => $requestType,
                        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                        //CURLOPT_USERPWD => $username . ':' . $password
                    );
        if (!empty($headers)) {
            curl_setopt($cURLConnection, CURLOPT_HTTPHEADER, $headers);
        }
        curl_setopt_array($cURLConnection, $params);
        
        $apiResponse = curl_exec($cURLConnection);
        $err     = curl_errno($cURLConnection);
        $errmsg  = curl_error($cURLConnection);
        curl_close($cURLConnection);

        if ($err != 0 || $errmsg != '') {
            return array('success' => false, 'message' => 'Error:' . $errmsg);
        }
        
        return array('success' => true, 'response' => json_decode($apiResponse, true));
    }
    
    
    protected static function _getRequestParameters($aDeviceImei, $aIsn, $aType, $aParams = array(), $aSync = true) {
        return static::_getRequestParameters($aDeviceImei, $aIsn, $aType, $aParams, $aSync);
    }
}
