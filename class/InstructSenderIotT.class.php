<?php

require_once('InstructSender.class.php');
class InstructSenderIotT extends InstructSender {
    protected static function _getRequestParameters($aDeviceImei, $aIsn, $aType, $aParams = array(), $aSync = true) {
        $isDebugQuery = false;
        if (substr($aType, 0, 7) == 'debugQ:') {
            $isDebugQuery = true;
        }
        
        $proNo = 128;
        $cmdContent = '';
        try {
            switch ($aType) {
                case 'getICCID':
                case 'getIMSI':
                case 'getVersion':
                case 'getStatus':
                    /* Query the information */
                    $cmdContent = strtoupper(str_replace('get', '', $aCommand));
                    break;
                case 'getMemoryCardStatus':
                    $cmdContent = 'CAMERA,TF';
                    break;
                    
                    
                case 'formatMemoryCard':
                    $cmdContent = 'CAMERA,TF,FORMAT';
                    break;
                case 'reboot':
                    $cmdContent = 'RESET';
                    break;
                case 'resetToFactory':
                    $cmdContent = 'FACTORY';
                    break;
                case 'wakeUp':
                    $cmdContent = 'WAKEUP_QUERY';
                    break;
                case 'enableLiveStream':
                    $cmdContent = array( 'dataType' => '0',
                                         'codeStreamType' => '0',
                                         'channel' => '1',
                                         'videoIP' => 'iot.sentidrive.fr', //$_SERVER['SERVER_ADDR'],
                                         'videoTCPPort' => '10002',
                                         'videoUDPPort' => '0'
                                        );
                    $cmdContent = json_encode($cmdContent);
                    $proNo = 37121;
                    //'RTMP,ON,INOUT';
                    break;
                case 'disableLiveStream':
                    $cmdContent = array('dataType' => '0',
                                        'cmd' => '0',
                                        'codeStreamType' => '0',
                                        'channel' => '1',
                                        'videoIP' => 'iot.sentidrive.fr', //$_SERVER['SERVER_ADDR'],
                                        'videoTCPPort' => '10002',
                                        'videoUDPPort' => '0'
                                            );
                    $cmdContent = json_encode($cmdContent);
                    $proNo = 37122;
                    //'RTMP,OFF,A,B';
                    break;
                case 'getResourcesList':
                    $cmdContent = array("channel" => 0,
                                        "beginTime" => $aParams[0],
                                        "endTime" => $aParams[1],
                                        "alarmFlag" => 0,
                                        "resourceType" => 0,
                                        "codeType" => 0,
                                        "storageType" => 0,
                                        "instructionID" => $aIsn);
                    $cmdContent = json_encode($cmdContent);
                    $proNo = 37381;
                    break;
                    
                case 'setApn':
                    /* A=Address of the APN
                     B=Account, empty if no need
                     C=Password, empty if no need */
                    $cmdContent = 'APN,' . $aParams[0] . ' ' . $aParams[0] . ',' . $aParams[1];
                    break;
                case 'setVolume':
                    $cmdContent = 'VOLUME,' . $aParams[0];
                    break;
                case 'setFirmwareUpdateUrl':
                    $cmdContent = 'UPDATE,' . $aParams[0];
                    break;
                case 'setWorkingMode':
                    $cmdContent = 'PWRLIMIT,' . ($aParams[0]?'ON':'OFF');
                    break;
                case 'setServerAddress':
                    /* A=0/1. It refers to the address type, wherein ""0"" refers to ""IP"" and ""1"" ""domain name"".
                     B=Server address (IP or domain name)
                     C=Server port */
                    $cmdContent = 'SERVER,' . $aParams[0] . ' ' . $aParams[1] . ',' . $aParams[2];
                    break;
                case 'setTimezone':
                    /* A: E/W; E east time zone, W west time zone; default value: E
                     B: 0-12; time zone; default: 8
                     C: 0/15/30/45; half time zone; default: 0 */
                    $cmdContent = 'GMT,' . $aParams[0] . ' ' . $aParams[1] . ',' . $aParams[2];
                    break;
                case 'setTimezoneAuto':
                    $cmdContent = 'ASETGMT,' . ($aParams[0]?'ON':'OFF');
                    break;
                case 'setApnAuto':
                    $cmdContent = 'ASETAPN,' . ($aParams[0]?'ON':'OFF');
                    break;
                case 'setDeviceId':
                    /* A=0, device will report ID as ""front 14 digits IMEI in HEX""
                     {To show the whole IMEI to user, can study how to count the check digit (15th digit) on google""}
                     B=1, device will report ID as ""last 12 digits IMEI"" */
                    $cmdContent = 'BCD,' . $aParams[0];
                    break;
                case 'setHttpUploadType':
                    /* A=1 HTTPS
                     A=2 HTTP (For integgration) */
                    $cmdContent = 'URLTYPE,' . $aParams[0];
                    break;
                case 'setSOSNumbers':
                    /* A=1 HTTPS
                     A=2 HTTP (For integgration) */
                    $cmdContent = 'SOS,' . implode(',', $aParams);
                    break;
                case 'setWifiHotspotMode':
                    /* A=ON/OFF WiFi hot-spot switch.
                     B=Hot-spot name, default is IMEI number
                     C=password, default is last 8 digits of IMEI */
                    $cmdContent = 'WIFIAP,' . ($aParams[0]?'ON':'OFF') . ',' . $aParams[1] . ',' . $aParams[2];
                    break;
                case 'setWifiRouterMode':
                    /* "A=ON/OFF WiFi STD switch
                     B=Router's name
                     C=Router's password */
                    $cmdContent = 'SSID,' . ($aParams[0]?'ON':'OFF') . ',' . $aParams[1] . ',' . $aParams[2];
                    break;
                case 'videoRecordingInfos':
                    /*  A=1
                     Main camera
                     
                     B=480/720/1080
                     Video resolution
                     480 is 720*480, 720 is 1280*720, 1080 is 1920*1080
                     
                     C=30/25/15
                     Video frame rate
                     30 is 30fps, 25 is 25fps, 15 is 15fps
                     
                     D=1/2/3/4/5/6/7/8
                     Video bit rate
                     1 is 1M, 2 is 2M, 3 is 3M, 4 is 4M, 5 is 5M, 6 is 6M, 7 is 7M, 8 is 8M */
                    $cmdContent = 'VIDEO,PARAM,' . $aParams[0] . ',' . $aParams[1] . ',' . $aParams[2] . ',' . $aParams[3] . ',' . $aParams[4];
                    break;
                case 'setSpeedUnit':
                    /* A=0/1, 0=KMH, 1=MPH */
                    $cmdContent = 'MILE,' . $aParams[0];
                    break;
                case 'setLocationUploadInterval':
                    /* B=0–18000;  It defines the upload interval.
                     The unit is second (s) and the default value is 10 */
                    $cmdContent = 'TIMER,' . $aParams[0];
                    break;
                case 'setLocationUploadAngleInfos':
                    /* A=ON/OFF
                     B=angle,5~180, default is 30
                     C=Detect period, 2-5 second, default is 3 */
                    $cmdContent = 'ANGLEREP,' . ($aParams[0]?'ON':'OFF') . ',' . $aParams[1] . ',' . $aParams[2];
                    break;
                case 'setLocationAutoUpdateOnAccChange':
                    /* A=ON/OFF
                     It is a switch used to specify whether the device will auto upload a location packet to notify the user every time the ACC status changes. */
                    $cmdContent = 'ACCREP,' . ($aParams[0]?'ON':'OFF');
                    break;
                case 'setMileageFeature':
                    /* A=Function switch, ON/OFF
                     B=mileage setting, range 0~1000000000 (unit meter, 0~1 million kilometers) */
                    $cmdContent = 'MILEAGE,' . ($aParams[0]?'ON':'OFF') . ',' . $aParams[1];
                    break;
                case 'setHeartbeatUploadInterval':
                    /* A=1-300 minutes, ACC ON heartbeat package upload interval, default: 3 minutes;
                     B=1-300 minutes, ACC OFF heartbeat packet upload interval, default: 3 minutes; */
                    $cmdContent = 'HBT,' . $aParams[0] . ',' . $aParams[1];
                    break;
                case 'setAlarmLowBatteryInfos':
                    /* A=ON/OFF Switch to enable or discble the function
                     B=0
                     C=0 Alarm reporting method (0: GPRS)
                     
                     D=10-1000 Low electric alarm threshold
                     Default: 118 Unit: 0.1V
                     
                     E=10-1000 Disallow alarm threshold voltage
                     Default: 120 Unit: 0.1V
                     
                     F=1-300 Detection time Default: 300 Unit: Seconds */
                    $cmdContent = 'EXBATALM,' . ($aParams[0]?'ON':'OFF') . ',0,0,' . $aParams[1] . ',' . $aParams[2] . ',' . $aParams[3];
                    break;
                case 'setAlarmParkingInfos':
                    /*  A=0/1/2/3/4/5
                     Sensitivity, 0 means OFF
                     
                     B=1~20
                     Alarm times triggered by vibration interruption
                     Default: 5
                     
                     C=1-3000
                     Detection time
                     Default: 10 Unit: Seconds
                     
                     D=1-3000
                     Filter period or interval to trigger next time alarm.
                     Default: 15 Unit: Mins */
                    $cmdContent = 'SENALM,' . ($aParams[0]?'ON':'OFF') . ',' . $aParams[1] . ',' . $aParams[2] . ',' . $aParams[3];
                    break;
                case 'setAlarmSpeedingInfos':
                    /*  A=ON/OFF; ON: Enable; OFF: Disable.
                    
                    B=0
                    Report mode of speed alerts
                    0: GPRS,
                    
                    C=1–255;
                    Speed threshold valuet;
                    Default: 50 Unit: km/h
                    
                    D=5-600,
                    The duration during which the device detects the speed of the vehicle is always above the threshold value;
                    Default: 20 Unit: Second */
                    $cmdContent = 'SPEED,' . ($aParams[0]?'ON':'OFF') . ',0,' . $aParams[1] . ',' . $aParams[2];
                    break;
                case 'setAlarmAccelerationDecelerationInfos':
                    /*  A=ON/OFF; ON: Enable; OFF: Disable.
                    
                    B=0 Alarm reporting method
                    0: GPRS
                    
                    C=1-30 Detection time
                    Default: 4 Unit: Seconds
                    
                    D=10-300
                    Speed threshold value (Change) for harsh acceleration;
                    Default: 30 Unit: km/h
                    
                    E=10-300
                    Speed threshold value (Change)  for harsh deceleration;
                    Default: 50 Unit: km/h */
                    $cmdContent = 'SPEEDCHECK,' . ($aParams[0]?'ON':'OFF') . ',0,' . $aParams[1] . ',' . $aParams[2] . ',' . $aParams[3];
                    break;
                case 'setAlarmTurningInfos':
                    /*  A=ON/OFF Switch to enable or discble the function

                        B=0 Alarm reporting method 0: GPRS

                        C=10-180
                        Angle threshold value (Change);
                        Default: 30 Unit: km/h
                        
                        D=10-300
                        Speed threshold value for harsh turning; 
                        Default: 60 Unit: km/h
                        
                        E=1-30
                        Detection time
                        Default: 3 Unit: Seconds */
                    $cmdContent = 'SWERVE,' . ($aParams[0]?'ON':'OFF') . ',0,' . $aParams[1] . ',' . $aParams[2] . ',' . $aParams[3];
                    break;
                case 'setAlarmCollisionInfos':
                    /*  A=ON/OFF; ON: Enable; OFF: Disable.
                    
                    B=0 Alarm reporting method
                    0: GPRS
                    
                    C=0-1024 It refers to the trigger sensitivity;
                    Default: 520
                    
                    D=3-20 Detection time after receive the broadcast of the collision;
                    Default: 10 Unit: Seconds
                    
                    E=10-90 Detection time of the third stage
                    Default: 20 Unit: Seconds
                    
                    F=5-30
                    Speed threshold value;
                    Default: 5
                    Unit: km/h */
                    $cmdContent = 'COLLIDE,' . ($aParams[0]?'ON':'OFF') . ',0,' . $aParams[1] . ',' . $aParams[2] . ',' . $aParams[3] . ',' . $aParams[4];
                    break;
                case 'setAlarmSOS':
                    /* A=ON/OFF Switch to enable or discble the function
                    
                    B=0
                    
                    C=0 Alarm reporting method
                    0: GPRS */
                    $cmdContent = 'SOSALM,' . ($aParams[0]?'ON':'OFF') . ',0,0';
                    break;
                case 'setAlarmPowercutInfos':
                    /*  A=ON/OFF
                     B=0 is GPRS only, 1 is SMS+GPRS, 2 is GPRS+SMS+CALL; 3 is GPRS+CALL;
                     Reporting method
                     
                     C=1-3600
                     Detection time
                     Default: 1 Unit: Seconds
                     
                     D=1-3600
                     Charging time to detect exit from power-off state
                     Default: 1 Unit: Seconds
                     
                     E=0-3600
                     Filter time
                     Default: 0 Unit: Seconds */
                    $cmdContent = 'POWERALM,' . ($aParams[0]?'ON':'OFF') . ',' . $aParams[1] . ',' . $aParams[2] . ',' . $aParams[3] . ',' . $aParams[4];
                    break;
                case 'setFuelCutoff':
                    /* A=0/1;
                     Whether to cut off the fuel/power
                     0: Connect fuel/power,
                     1: Cut fuel/power */
                    $cmdContent = 'RELAY,' . ($aParams[0]?'ON':'OFF');
                    break;
                    
                    /* Unknown*/
                default:
                    if (!$isDebugQuery) {
                        throw new Exception('Invalid command to send');
                    }
                    unset($aParams[0]);
                    $cmdContent = str_replace('debugQ:', '', $aType) . (!empty($aParams)?','.implode(',', $aParams):'');
            }
        } catch (Exception $error) {
            return array('success' => false, 'message' => 'Error:' . $error->getmessage());
        }
        
        if ($cmdContent == null) {
            return array('success' => false, 'message' => 'Unknown command');   
        }
        return array('success' => true,
                     'parameters' => array('cmdContent' => $cmdContent,
                                           'serverFlagId' => $aIsn,
                                           'proNo' => $proNo,
                                           'platform' => 'web',
                                           'requestId' => $aIsn,
                                           'cmdType' => 'normallns',
                                           'offLineFlag' => (int)false,
                                           'timeOut' => 15,
                                           'language' => 'en',
                                           'sync' => (int)$aSync,
                                           'deviceImei' => $aDeviceImei,
                                           'token' => TOKEN ));
    }
}
