<?php

class DaemonIOT {
    protected $_dbMysql;
    protected $_dbHnd = array();
    protected $_fcm;
    protected $_ios;
    
    private function _initDbHnd($aType) {
        
        $type = ucfirst(strtolower($aType));
        if (!array_key_exists($type, $this->_dbHnd)) {
            $this->_dbHnd[$type] = new $type($this->_dbMysql);
        }
    }
    
    public function __construct() {
        $this->_dbMysql = new MySQL(BDD_HOST, BDD_USER, BDD_PASSWORD, BDD_PORT, BDD_NAME);;
        $this->_dbHnd = array();
        
        $this->_fcm = new FCM(FCM::CONTENT_JSON, 60*60*24*5, null);
        $this->_ios = new IOS(BASE_PATH . '_config/pushs-prod/SentidrivePushStoreAppCertificateKey.pem', 'sentidrive');
        
        if (!file_exists(__DIR__ . '/_datas/')) {
            mkdir(__DIR__ . '/_datas/', 0755);
        }
        
    }
    
    public function treatDatas($aMessageType, $aDatas) {
        $classFunction = '_treat' . $aMessageType . 'Datas';
        $datasArray = json_decode($aDatas, true);
        
        $this->_initDbHnd('Box');
        $this->_initDbHnd('Boxinformation');
        $this->_initDbHnd('Log');
        
        $this->_dbMysql->mysql_connect();
            $errors = array();
            foreach ($datasArray as $i => $datas) {
                if (empty($datas) || !array_key_exists('deviceImei', $datas)) {
                    $errors[] = 'Invalid parameters for item ' . $i;
                    continue;
                }
                
                try {
                    $box = $this->_dbHnd['Box']->getTerminalByIMEI($datas['deviceImei']);
                    if (empty($box) || !array_key_exists('id', $box)) {
                        Logger::log('Debug: Imei not found in DB : ' . $datas['deviceImei'] , Logger::LOG_LEVEL_DEBUG);
                        throw new Exception('box ' . $datas['deviceImei'] . ' not found', 6);
                    }
                    
                    file_put_contents('_datas/times/' . $datas['deviceImei'] . '.time', (int)microtime(true));
                    if (method_exists($this, $classFunction)) {
            		    Logger::log('Debug: Start treatment for ' . $aMessageType . ' - params: ' . json_encode($datas), Logger::LOG_LEVEL_DEBUG);
                		    $logDatas = array('hexa' => json_encode($datas));
                		    $logDatas['packet_type'] = $aMessageType;
                		    $logDatas['box_id'] = $box['id'];
            		    
            		        $this->$classFunction($box, $datas);
            		        $this->_dbHnd['Log']->insert($logDatas);
        		        Logger::log('Debug: Treatment finished', Logger::LOG_LEVEL_DEBUG);
                    } else {
                        Logger::log('Debug: No treatment for ' . $aMessageType, Logger::LOG_LEVEL_DEBUG);
                    }
        		    
                } catch (Exception $e) {
                    $errors[] = 'Item ' . $i . ' : ' . $e->getMessage() . ' (' . $e->getCode() . ')';
                    $logDatas['error'] = 'Item ' . $i . ' : ' . $e->getMessage() . ' (' . $e->getCode() . ')';
                    $this->_dbHnd['Log']->insert($logDatas);
                }
            }
        $this->_dbMysql->mysql_close();
        
        if (!empty($errors)) {
            throw new Exception(implode(", ", $errors), 5);
        }
        return true;
    }
    
    private function _treatLoginLogoutDatas($aBox, $aDatas) {
        $this->_initDbHnd('User');
        $this->_initDbHnd('Boxactivity');
        $this->_initDbHnd('Boxmessage');
        $this->_initDbHnd('Parameter');
        
        if ($aDatas['type'] == 'login') {
            //Force redisplay to get imei in log
            Logger::log('Info: Received Msg Type: Login (UserId: ' . $aBox['user_id'] . ')', Logger::LOG_LEVEL_INFO);
            
            if ($aBox['user_id'] != 0 && $aBox['user_id'] != PHANTOM_USER) {
                //$this->_dbHnd['User']->update($aBox['user_id'], array('last_connexion' => gmdate('Y-m-d H:i:s'), 'last_box_id' => $aBox['id']));
            }
            
            $aBoxDatas = array('last_connexion' => gmdate('Y-m-d H:i:s'),
                'timezone' => $aDatas['timezone'],
                'type_identification_code' => '',
                'connexion_lost' => 0, 'reachable' => 1);
            if ($aBox['sim_status'] == 'pre-active') {
                $aBoxDatas['sim_status'] = 'active';
            }
            if ($aBox['status'] == 'registered' && (int)$aBox['has_subscription'] == 1) {
                $aBoxDatas['status'] = 'active';
            }
            $this->_dbHnd['Box']->update($aBox['id'], $aBoxDatas);
            
            //Si on a déjà les paramètres de la box en base, on récupère les infos
            if (!empty($_SESSION['sentidriveInfos']['parId'])) {
                $lastGetConfTime = strtotime('-3days');
                if (file_exists(__DIR__ . '/_datas/' . $aBox['imei'] . '-getConf.time')) {
                    $lastGetConfTime = file_get_contents(__DIR__ . '/_datas/' . $aBox['imei'] . '-getConf.time');
                }
                
                if ($lastGetConfTime < strtotime(date('Y-m-d 00:00:00'))) {
                    $datas = array('box_id' => $aBox['id'],
                        'initial_date' => gmdate('Y-m-d H:i:s'),
                        'date' => gmdate('Y-m-d H:i:s'),
                        'type' => 'getConf',
                        'sent' => 0,
                        'params' => '{}',
                        'revert' => null,
                        'key' => null);
                    $this->_dbHnd['Boxmessage']->insert($datas);
                } else {
                    Logger::log('Debug: Get Conf already sent');
                }
            }
            
            //Si la box a perdu sa connexion
            if ($aBox['connexion_lost'] && $aBox['user_id'] != 0 && $aBox['user_id'] != PHANTOM_USER) {
                if ($this->_dbHnd['Box']->isRequisitionned($aBox['id'])) {
                    $dwMessage = Deveryware::createMessage($aBox['imei'], 'connexion_found');
                    $result = Deveryware::sendToAPI($dwMessage);
                    Logger::log('Debug: Send connexion found to Deveryware, result : ' . (int)$result, Logger::LOG_LEVEL_DEBUG);
                }
                
                $datas = array('box_id' => $aBox['id'], 'user_id' => $aBox['user_id'],
                               'date' => gmdate('Y-m-d H:i:s'), 'type' => 'box_reconnected',
                               'value' => $aBox['serial_number']);
                $this->_dbHnd['Boxactivity']->insert($datas);
                
                $isNotificationEnabled = $this->_dbHnd['Parameter']->isRecoNotificationEnabled($aBox['id']);
                if (!empty($aBox['user_notif_token']) && $isNotificationEnabled) {
                    //MGE Notification connexion retrouvée
                    $infos = array('box_id' => $aBox['id'], 'box_imei' => $aBox['imei'],
                                   'box_serial_number' => $aBox['serial_number'], 'notif_type' => 'info',
                                   'title' => $gTranslations[$aBox['user_language']]['box_reconnected']['title'],
                                   'label' => str_replace('###LABEL###', $aBox['label'], $gTranslations[$aBox['user_language']]['box_reconnected']['desc']),
                                   'time' => microtime(),
                                   'angel' => false);
                    if ($aBox['user_os'] == 'ios') {
                        $this->_ios->notification($aBox['user_notif_token'], $infos['title'], $infos['label'], $infos);
                    } else {
                        $this->_fcm->notification($aBox['user_notif_token'], $infos['title'], $infos['label'], $infos);
                    }
                }
            }
        } else if ($aDatas['type'] == 'logout') {
            $this->_dbHnd['Box']->update($aBox['id'], array('reachable' => 0, 'connexion_lost' => 1));
            
            if ($this->_dbHnd['Box']->isRequisitionned($aBox['id'])) {
                $dwMessage = Deveryware::createMessage($aBox['imei'], 'connexion_lost');
                $result = Deveryware::sendToAPI($dwMessage);
                Logger::log('Debug: Send connexion lost to Deveryware, result : ' . (int)$result, Logger::LOG_LEVEL_DEBUG);
            }
            
            if (!empty($aBox['user_id']) && $aBox['user_id'] != PHANTOM_USER) {
                $isNotificationEnabled = $this->_dbHnd['Parameter']->isRecoNotificationEnabled($aBox['id']);
                $datas = array('box_id' => $aBox['id'],
                                'user_id' => $aBox['user_id'],
                                'date' => gmdate('Y-m-d H:i:s'),
                                'type' => 'box_lost',
                                'value' => $aBox['serial_number']);
                $this->_dbHnd['Boxactivity']->insert($datas);
                
                $user = $this->_dbHnd['User']->get($aBox['user_id']);
                //Si notification active et token utilisateur connu
                if (!empty($user['notif_token']) && $isNotificationEnabled) {
                    //Envoi de la notification de connexion perdue
                    $infos = array('box_id' => $aBox['id'],
                                   'box_imei' => $aBox['imei'],
                                   'box_serial_number' => $aBox['serial_number'],
                                   'notif_type' => 'info', 'type' => 'box_lost',
                                   'title' => $gTranslations[$user['language']]['box_lost']['title'],
                                   'label' => str_replace('###LABEL###', $aBox['label'], $gTranslations[$user['language']]['box_lost']['desc']),
                                   'time' => microtime(),
                                   'angel' => false);
                    if ($user['os'] == 'ios') {
                        $this->_ios->notification($user['notif_token'], $infos['title'], $infos['label'], $infos);
                    } else {
                        $this->_fcm->notification($user['notif_token'], $infos['title'], $infos['label'], $infos);
                    }
                }
            }
        }
        
        return true;
    }
    
    private function _treatHeartbeatDatas($aBox, $aDatas) {
        function getVoltageLevel($aValue) {
            if ($aValue >= 75) return 'very-high';
            if ($aValue >= 45) return 'high';
            if ($aValue >= 15) return 'medium';
            if ($aValue >= 10) return 'low';
            if ($aValue >= 6) return 'very-low';
            if ($aValue >= 3) return 'extremely-low';
            return 'no-power';
        }
        $this->_initDbHnd('LastHeartbeat');
        $this->_dbHnd['Lastheartbeat']->insertForBox($aBox['id'], array('box_id' => $aBox['id'],
                                                                        'voltage_level' => getVoltageLevel($aDatas['powerLevel']),
                                                                        'gsm_signal_strength' => $aDatas['gsmSign'],
                                                                        'port_status' => '', //$aDatas['LanguagePortStatus']['PortStatus'],
                                                                        'language' => '', //$aDatas['LanguagePortStatus']['Language'],
                                                                        'oil_electricity' => $aDatas['oilEle']?'On':'Off',
                                                                        'gps_tracking' => $aDatas['gpsPos']?'On':'Off',
                                                                        'charge' => $aDatas['powerStatus']?'On':'Off',
                                                                        'acc' => $aDatas['acc'],
                                                                        'armed' => $aDatas['fortify'],
                                                                        'date' => gmdate('Y-m-d H:i:s')));
        $this->_dbHnd['Boxinformation']->insertForBox($aBox['id'], 'acc', $aDatas['acc']);
        if ($this->_dbHnd['Box']->isRequisitionned($aBox['id'])) {
            $dwMessage = Deveryware::createMessage($aBox['imei'], 'heartbeat', $aDatas);
            $result = Deveryware::sendToAPI($dwMessage);
            Logger::log('Debug: Send heartbeat to Deveryware, result : ' . (int)$result, Logger::LOG_LEVEL_DEBUG);
        }
        
        return true;
    }
    
    private function _treatLocationDatas($aBox, $aDatas) {
        $postMethod = array('0x00' => 'Upload by time interval',
                            '0x01' => 'Upload by distance',
                            '0x02' => 'Inflection point upload',
                            '0x03' => 'Upload by ACC status change',
                            '0x04' => 'Re-upload the last GPS point when back to static',
                            '0x05' => 'Upload the last effective point when network recovers',
                            '0x06' => 'Update ephemeris and upload GPS data compulsorily',
                            '0x07' => 'Upload location when side key triggered',
                            '0x08' => 'Upload location after power on',
                            '0x09' => 'Upload by command "GPSON"',
                            '0x0A' => 'Upload the last longitude and latitude when device is static',
                            '0x0B' => 'Upload after WIFI data query',
                            '0x0C' => 'Upload by command LJDW',
                            '0x0D' => 'Upload the last longitude and latitude when device is static',
                            '0x0E' => 'Gpsdup upload (Upload regularly in a static state.)',
                            '0x0F' => 'Upload after exit tracking mode');
        
        $aDatas['postMethod'] = dechex($aDatas['postMethod']);
        if (strlen($aDatas['postMethod']) == 1) {
            $aDatas['postMethod'] = '0' . $aDatas['postMethod'];
        }
        $aDatas['postMethod'] = '0x' . $aDatas['postMethod'];
        
        $status = strrev(decbin($aDatas['status']));
        $datas = array('latitude' => $aDatas['lat'],
                       'longitude' => $aDatas['lng'],
                       'date' => $aDatas['gpsTime'],
                       'gps_info_length' => 0,
                       'gps_info_sat_count' => $aDatas['satelliteNum'],
                       'angle' => 0,
                       'speed' => $aDatas['gpsSpeed'],
                       'mobile_country_code' => 0,
                       'location_area_code' => 0,
                       'mobile_network_code' => 0,
                       'cell_id' => 0,
                       'acc' => $aDatas['acc']?'High':'Low',
                       'data_upload_mode' => @$postMethod[$aDatas['postMethod']],
                       'gps_data_reupload' => $aDatas['gpsMode']?'Re-upload':'Real-time',
                       'course_status_gps_type' => '',
                       'course_status_gps_positionned' => $status[1],
                       'course_status_longitude' => $status[2]=='1'?'South':'North',
                       'course_status_latitude' => $status[3]=='1'?'West':'East',
            'course_status_course' => $aDatas['direction']);
        
        if ($datas['course_status_latitude'] == 'South') {
            $datas['latitude'] = '-' . $datas['latitude'];
        }
        if ($datas['course_status_longitude'] == 'West') {
            $datas['longitude'] = '-' . $datas['longitude'];
        }
        
        $datas['box_id'] = $aBox['id'];
        $datas['timezone'] = $aBox['timezone'];
        if ((strtotime($datas['date']) - strtotime(gmdate('Y-m-d H:i:s'))) >= 7200 ) { //if 2hours in future
            Logger::log('Debug: Position ignored for date ' . $datas['date'], Logger::LOG_LEVEL_DEBUG);
            $datas['ignored'] = 1;
        } else {
            $datas['ignored'] = 0;
        }
        
        $this->_initDbHnd('LastPosition');
        $this->_initDbHnd('Position');
        
        $posId = $this->_dbHnd['Position']->insert($datas);
        
        if (!$datas['ignored'] && ($datas['gps_data_reupload'] == 'Real-time'
            || empty($aBox['lp_date'])
            || $aBox['lp_date'] < $datas['date'])) {
            if ($datas['gps_data_reupload'] == 'Real-time') {
                $this->_dbHnd['Boxinformation']->insertForBox($aBox['id'], 'acc', $datas['acc']);
            }
            
            if ((int)$datas['gps_info_sat_count'] > $aBox['min_sat_count']) {
                $this->_dbHnd['Lastposition']->insertForBox($aBox['id'], array('box_id' => $aBox['id'],
                    'pos_id' => $posId,
                    'date' => $datas['date']));
            }
        }
        
        if ($this->_dbHnd['Box']->isRequisitionned($aBox['id'])) {
            $dwMessage = Deveryware::createMessage($aBox['imei'], 'location', $datas);
            $result = Deveryware::sendToAPI($dwMessage);
            Logger::log('Debug: Send Location to Deveryware, result : ' . (int)$result, Logger::LOG_LEVEL_DEBUG);
        }
            
        return true;
    }
    
    private function _treatAlarmDatas($aBox, $aDatas) {
        $this->_initDbHnd('Alarm');
        $this->_initDbHnd('Boxactivity');
        $alarms = array();
        if ($aDatas['type'] == 'DEVICE') {
            $alarmDatas = array('date' => $aDatas['msg']['alarmTime'],
                                'latitude' => $aDatas['msg']['lat'],
                                'longitude' => $aDatas['msg']['lng'],
                                'user_id' => $aBox['user_id'],
                                'box_id' => $aBox['id'],
                                'timezone' => $aBox['timezone'],
                                'value' => 'other-event'
                               );
            
            if ($aDatas['msgClass'] == 0) {
                $alarmDatas['speed'] = (int)@$aDatas['msg']['gpsSpeed'];
                $alarmTypes = array('0' => 'normal', '1' => 'sos', '2' => 'power-off',
                                    '3' => 'vibration', '4' => 'entered-fence-alert',
                                    '5' => 'left-fence-alert', '6' => 'speed', '9' => 'displacement',
                                    '10' => 'entered-gnss-dead-zone', '11' => 'left-gnss-dead-zone',
                                    '12' => 'power-on', '13' => 'device-got-first-fix',
                                    '14' => 'low-external-battery', '15' => 'low-battery-protect',
                                    '16' => 'sim-changed', '17' => 'powered-off', '18' => 'airplane-protect',
                                    '19' => 'tamper', '20' => 'abnormal-door-status',
                                    '21' => 'power-off-battery', '22' => 'ambient-sound-too-loud',
                                    '23' => 'station-base-detected', '24' => 'cover-removed',
                                    '25' => 'low-internal-battery', '26' => 'transport-mode-exited',
                                    '27' => 'designated-herd-leaving-suspected', '28' => 'door-was-opened',
                                    '29' => 'door-was-closed', '30' => 'airbag-deployed',
                                    '32' => 'entered-deep-sleep-mode', '35' => 'fall',
                                    '36' => 'charger-connected', '37' => 'light-detected',
                                    '38' => 'moving-away-from-beacon', '39' => 'cover-removed',
                                    '40' => 'enter-sleep-mode', '41' => 'harsh-acceleration',
                                    '42' => 'sharp-left-cornering', '43' => 'sharp-right-cornering',
                                    '44' => 'collision', '45' => 'tipped-over-onto-side',
                                    '48' => 'harsh-braking', '49' => 'designated-herd-already-left',
                                    '50' => 'powered-off', '51' => 'device-locked',
                                    '52' => 'device-unlocked', '53' => 'device-unlocked-unexpectedly',
                                    '54' => 'device-unlock-failed', '55' => 'device-hit-violently',
                                    '56' => 'out-of-preset-range', '57' => 'out-of-preset-range',
                                    '58' => 'cover-removed', '59' => 'device-stationary-too-long',
                                    '60' => 'vehicle-may-have-been-stolen', '61' => 'vehicle-started-unexpectedly',
                                    '62' => 'sos', '63' => 'defense-mode-exited', '64' => 'defense-mode-entered',
                                    '65' => 'device-muted', '66' => 'vehicle-finding-alert', '67' => 'truck-opened',
                                    '68' => 'rsv1', '69' => 'rsv2', '70' => 'rsv3', '71' => 'fatigue-detected',
                                    '72' => 'pet-has-been-lost-detected', '73' => 'internal-battery-charged',
                                    '74' => 'internal-battery-error', '75' => 'device-tilted-unexpectedly',
                                    '76' => 'abruptly-turn', '77' => 'abruptly-changed-lane',
                                    '78' => 'vehicle-stability-exception', '79' => 'vehicle-attitude-exception',
                                    '80' => 'door-was-closed', '80' => 'door-opening', '81' => 'door-was-opened',
                                    '81' => 'door-closing', '82' => 'body-temperature-abnormal',
                                    '83' => 'fuel-may-be-stolen', '84' => 'gnss-antenna-disconnected',
                                    '85' => 'internal-battery-high-temperature', '86' => 'internal-battery-charging-started',
                                    '87' => 'internal-battery-charging-stopped', '88' => 'internal-battery-will-be-charged',
                                    '89' => 'internal-battery-charged', '90' => 'low-external-battery',
                                    '91' => 'high-temperature', '92' => 'low-temperature',
                                    '93' => 'rfid-sensor', '94' => 'pulse-exception', '95' => 'vehicle-speeding-inside-geofence',
                                    '96' => 'live-wire-exception', '97' => 'temperature-sensor', '98' => 'external-battery-voltage-too-high',
                                    '99' => 'close-to-bluetooth-beacon', '100' => 'temperature-recovered-normal',
                                    '101' => 'device-hit-violently', '102' => 'voltage-value-exception',
                                    '103' => 'device-already-signed-in', '104' => 'device-already-signed-out',
                                    '105' => 'file-completely-uploaded', '106' => 'vehicle-tipped-over-its-side',
                                    '112' => 'sd-card-already-mounted', '113' => 'tank-needs-refill',
                                    '114' => 'device-already-installed', '115' => 'abnormal-fuel-level',
                                    '116' => 'vehicle-speed-resumed-normal', '117' => 'fatigue-detected',
                                    '118' => 'temperature-connection-timeout', '119' => 'adc1-high-voltage',
                                    '120' => 'adc1-low-voltage', '121' => 'adc1-high-voltage', '122' => 'adc1-low-voltage',
                                    '123' => 'temperature-rising-abnormally', '124' => 'temperature-dropping-abnormally',
                                    '126' => 'humidity', '128' => 'rear-mirror-vibration', '129' => 'mobile-data-usage-exception',
                                    '130' => 'device-already-restarted', '131' => 'collision', '131' => 'seatbelt-fastened',
                                    '132' => 'seatbelt-unfastened', '132' => 'camera1-exception', '133' => 'camera2-exception',
                                    '134' => 'sd-card-not-found', '135' => 'speed', '136' => 'powered-off',
                                    '137' => 'usb-camera-not-found', '138' => 'fuel-power-reconnected', 
                                    '139' => 'fuel-power-disconnected', '140' => 'driver-blinking-frequently',
                                    '141' => 'land-transport-mode', '142' => 'abnormal-ambient-environment',
                                    '143' => 'driver-distracted', '144' => 'harsh-acceleration', '145' => 'harsh-braking',
                                    '146' => 'abruptly-turn', '147' => 'collision', '148' => 'no-driver-face-detected',
                                    '149' => 'waterborne-transport-mode', '150' => 'stationery-mode', '151' => 'driver-using-phone',
                                    '152' => 'capture-already-completed', '153' => 'driver-info-changed',
                                    '154' => 'driver-smoking', '160' => 'driver-yawning', '161' => 'camera-lens-blocked',
                                    '162' => 'face-alignment-error', '163' => 'fatigue-detected', '164' => 'sd-card-low-capacity',
                                    '165' => 'card-swipe-detected-by-rfid', '166' => 'seat-belt-already-fasten',
                                    '167' => 'seat-belt-unfasten', '168' => 'engine-failed', '169' => 'low-vehicle-battery',
                                    '170' => 'driver-drinking', '171' => 'package-opened-unexpectedly',
                                    '172' => 'bluetooth-mac-address-found', '173' => 'bluetooth-mac-address-not-found',
                                    '177' => 'fuel-increased-unexpectedly', '178' => 'fuel-dropped-unexpectedly',
                                    '179' => 'fuel-sensor-communication-error', '180' => 'fuel-sensor-communication-resumed',
                                    '181' => 'temperature-sensor-communication-error', '182' => 'vehicle-towing-away-unexpectedly',
                                    '183' => 'vehicle-tipped-over-its-side', '184' => 'position-fix-too-long',
                                    '185' => 'vehicle-idling-too-long', '186' => '3d-acceleration-sensor-error',
                                    '187' => 'gnss-module-error', '188' => 'ubi-sensor-chip-error', '189' => 'ubi-encrypted-ic-error',
                                    '190' => 'ubi-gnss-chip-error', '191' => 'powered-off', '197' => 'acc-on',
                                    '198' => 'acc-off', '199' => 'driver-extendedly-driving', '200' => 'driver-extended-driving-known',
                                    '201' => 'input1-activated', '202' => 'speed', '203' => 'vehicle-parked-too-long',
                                    '204' => 'forward-colision-warning', '205' => 'lane-departure-warning',
                                    '206' => 'headway-monitor-warning', '207' => 'pedestrian-collision-warning',
                                    '224' => 'powered-on', '225' => 'flash-error-detected', '226' => 'can-module-error-detected',
                                    '227' => 'water-temperature-too-high', '228' => 'vehicle-current-lane-departed',
                                    '229' => 'collision', '254' => 'acc-on', '255' => 'acc-off');
                $alarmDatas['type'] = @$alarmTypes[$aDatas['msg']['alertType']];
                
                if (empty($alarmDatas['type'])) {
                    throw new Exception('invalid-alarm-type#' . $aDatas['msg']['alertType'], 401);
                }
                
                $alarms[] = $alarmDatas;
            } else {
                $alertTypes = array(262 => 'driving-behavior-abnormal', 15 => 'camera-fault',
                                    1024 => 'harsh-acceleration', 1025 => 'harsh-braking',
                                    1026 => 'sharp-turn', 1027 => 'speed',
                                    1028 => 'fatigue-detected', 1029 => 'collision',
                                    1030 => 'vibration', 1031 => 'displacement',
                                    1032 => 'entered-fence-alert', 1033 => 'left-fence-alert',
                                    1040 => 'sleep-mode', 1041 => 'working-mode',
                                    3073 => 'sos', 3074 => 'low-battery', 3075 => 'acc-on', 3076 => 'acc-off',
                                    3077 => 'anti-theft', 3078 => 'calibration-abnormal', 3079 => 'identification',
                                    3080 => 'door', 3081 => 'oil-steal', 3082 => 'temperature-humidity-abnormal',
                                    3083 => 'dlt-card-login', 3084 => 'dlt-card-logout', 3085 => 'dlt-non-registered-card',
                                    3086 => 'power-off', 3087 => 'internal-low-battery', 3088 => 'power-off-battery',
                                    3089 => 'voice-controlled', 3090 => 'tamper', 3091 => 'offline',
                                    3092 => 'sd-card-insertion', 3093 => 'sd-card-not-found', 3094 => 'sd-card-readonly',
                                    3095 => 'sd-card-full', 3096 => 'oil-electricity-restore', 3097 => 'oil-electricity-disconnect' );
                if (array_key_exists($aDatas['msg']['alertType'], $alertTypes)) {
                    $alarmDatas['type'] = @$alarmTypes[$aDatas['msg']['alertType']];
                    $alarms[] = $alarmDatas;
                } else if ($aDatas['msg']['alertType'] == 256 && array_key_exists('standardAlarmValue', $aDatas['msg'])) {                    
                    $bin = strrev(decbin($aDatas['msg']['standardAlarmValue']));
                    $bitValues = array(0 => 'sos', 1 => 'speed', 2 => 'fatigue-detected',
                                        3 => 'danger', 4 => 'gnss-module-error', 5 => 'gnss-antenna-disconnected',
                                        6 => 'gnss-antenna-short-circuit', 7 => 'low-battery',
                                        8 => 'power-off', 9 => 'display-error', 10 => 'tts-module-error',
                                        11 => 'camera-failure', 12 => 'ic-card-module-error', 13 => 'speed',
                                        14 => 'fatigue-detected', 15 => 'reserved', 16 => 'reserved',
                                        17 => 'reserved', 18 => 'cumulative-driving-timeout',
                                        19 => 'parking-overtime', 20 => 'area-in-out', 21 => 'route-in-out',
                                        22 => 'road-driving-too-long-insufficient', 23 => 'route-deviation',
                                        24 => 'vss-error', 25 => 'abnormal-fuel-level', 26 => 'vehicle-stolen',
                                        27 => 'vehicle-illegal-ignition', 28 => 'displacement',
                                        29 => 'collision', 30 => 'rollover-warning', 31 => 'door-opened');
                    for ($i = 0; $i < strlen($bin); $i++) {
                        if ($bin[$i] == 1) {
                            $alarmDatas['type'] = $bitValues[$i];
                            $alarms[] = $alarmDatas;
                        }
                    }
                }
            }
            if (!empty($alarms)) {
                foreach ($alarms as $alarm) {
                    $alarmId = $this->_dbHnd['Alarm']->insert($alarm);
                    
                    $datas = array('box_id' => $aBox['id'],
                                   'user_id' => $aBox['user_id'],
                                   'date' => gmdate('Y-m-d H:i:s'),
                                   'type' => 'alarm_' . str_replace('-', '_', $alarm['type']),
                                   'value' => $alarmId);
                    $this->_dbHnd['Boxactivity']->insert($datas);
                    
                    if (in_array($alarm['type'], array('low-battery', 'power-off', 'vibration'))) {
                        if ($this->_dbHnd['Box']->isRequisitionned($aBox['id'])) {
                            $dwMessage = Deveryware::createMessage($aBox['imei'], str_replace('-', '_', $alarm['type']), $alarm);
                            $result = Deveryware::sendToAPI($dwMessage);
                            Logger::log('Debug: Send Alarm to Deveryware, result : ' . (int)$result, Logger::LOG_LEVEL_DEBUG);
                        }
                    }
                    
                    if (!empty($datas['file'])) {
                        
                    }
                }
            }
            return true;
        }
        return false;
    }
}
