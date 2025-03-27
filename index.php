<?php

if ($_SERVER['SERVER_ADDR'] == '172.31.47.193') {
    define('APP_MODE', 'PROD');
    define('LOG_FILE', '/var/log/sentidrive-iot.log');
    //define('LOG_FILE', './sentidrive-iot.log');
    define('BASE_PATH', './');
} else {
    define('APP_MODE', 'LOCAL');
    define('LOG_FILE', './sentidrive-iot.log');
    define('BASE_PATH', '../');
}

define('TOKEN', 'a12341234123');
define('PHANTOM_USER', -9999);

require_once __DIR__ . '/vendor/autoload.php';
require_once BASE_PATH . '_classes/classes.php';
require_once BASE_PATH . '_config/config.php';

require_once BASE_PATH . 'class/Deveryware.class.php';
include_once BASE_PATH . 'class/Logger.class.php';

require_once BASE_PATH . 'class/CRC16.class.php';
require_once BASE_PATH . 'class/IEEE754.class.php';
require_once BASE_PATH . 'class/Notifier.class.php';

require_once BASE_PATH . 'class/FCM.class.php';
require_once BASE_PATH . 'class/IOS.class.php';

require_once './class/DaemonIOT.class.php';
require_once './class/InstructSenderIotT.class.php';

use Symfony\Component\HttpFoundation\Request;

$app = new Silex\Application();
$app['debug']=true;

// Création du Logger
Logger::setLogFile(LOG_FILE);

//Niveau de log, par défault DEBUG donc on surcharge par Info
Logger::setLogLevel(constant(Logger::class . '::LOG_LEVEL_DEBUG'));

function checkRequest($aRequest) {
    if ($aRequest->get('token') != TOKEN) {
        throw new Exception('invalid token', 1);
        //throw new Exception('invalid token ' . $aRequest->get('token'), 1);
    }
    $datalist = $aRequest->get('data_list');
    if (empty($datalist)) {
        throw new Exception('invalid parameters', 2);
    }
    $datas = json_decode($datalist, true);
    if (empty($datas)) {
        throw new Exception('invalid parameters', 3);
    }
    return true;
}

$app->error(function (\Exception $e) use ($app) {
    Logger::log('Debug: ' . ucfirst($e->getMessage()) . ' (' . $e->getCode() . ' - ' . $_SERVER['HTTP_USER_AGENT']?$_SERVER['HTTP_USER_AGENT']:'No User Agent' . ')', Logger::LOG_LEVEL_DEBUG);
    return $app->json(array('code' => $e->getCode(), 'msg' => $e->getMessage()));
});

$app->get('/', function (){
    exit;
});
$app->get('/test', function (){
    echo 'DAEMONS';exit;
});
    
//Login / Logout
$app->post('/pushevent', function(Request $request) use ($app) {
    checkRequest($request);
    
    $daemonIOT = new DaemonIOT();
    $daemonIOT->treatDatas('LoginLogout', $request->get('data_list'));

    return $app->json(array('code' => 0));
});

//Heartbeat
$app->post('/pushhb', function(Request $request) use ($app) {
    checkRequest($request);
   
    $daemonIOT = new DaemonIOT();
    $daemonIOT->treatDatas('Heartbeat', $request->get('data_list'));
    
    return $app->json(array('code' => 0));
});

//GPS
$app->post('/pushgps', function(Request $request) use ($app) {
    checkRequest($request);
    
    $daemonIOT = new DaemonIOT();
    $daemonIOT->treatDatas('Location', $request->get('data_list'));
    
    return $app->json(array('code' => 0));
});

$app->post('/pushalarm', function(Request $request) use ($app) {
    checkRequest($request);
    
    $daemonIOT = new DaemonIOT();
    $daemonIOT->treatDatas('Alarm', $request->get('data_list'));
    
    return $app->json(array('code' => 0));
});

$app->post('/rfid', function(Request $request) use ($app) {
    checkRequest($request);
    
    Logger::log('Debug: Received post ' . "rfid", Logger::LOG_LEVEL_DEBUG);
    return $app->json(array('code' => 1));
});

$app->post('/wgtc', function(Request $request) use ($app) {
    checkRequest($request);
    
    Logger::log('Debug: Received post ' . "wgtc", Logger::LOG_LEVEL_DEBUG);
    return $app->json(array('code' => 1));
});

$app->post('/pushoil', function(Request $request) use ($app) {
    checkRequest($request);
    
    Logger::log('Debug: Received post ' . "pushoil", Logger::LOG_LEVEL_DEBUG);
    return $app->json(array('code' => 1));
});
$app->post('/pushfileupload', function(Request $request) use ($app) {
    checkRequest($request);
    
    Logger::log('Debug: Received post ' . "pushfileupload", Logger::LOG_LEVEL_DEBUG);
    return $app->json(array('code' => 1));
});
$app->post('/pushtem', function(Request $request) use ($app) {
    checkRequest($request);
    
    Logger::log('Debug: Received post ' . "pushtem", Logger::LOG_LEVEL_DEBUG);
    return $app->json(array('code' => 1));
});
$app->post('/pushlbs', function(Request $request) use ($app) {
    checkRequest($request);
    
    Logger::log('Debug: Received post ' . "pushlbs", Logger::LOG_LEVEL_DEBUG);
    return $app->json(array('code' => 1));
});
$app->post('/pushresourcelist', function(Request $request) use ($app) {
    checkRequest($request);
    
    Logger::log('Debug: Received post ' . "pushresourcelist: " . $request->get('data_list'), Logger::LOG_LEVEL_DEBUG);
    return $app->json(array('code' => 1));
});    
$app->post('/pushftpfileupload', function(Request $request) use ($app) {
    checkRequest($request);
    
    Logger::log('Debug: Received post ' . "pushftpfileupload", Logger::LOG_LEVEL_DEBUG);
    return $app->json(array('code' => 1));
});
$app->post('/pushIothubEvent', function(Request $request) use ($app) {
    checkRequest($request);
    
    Logger::log('Debug: Received post ' . "pushIothubEvent", Logger::LOG_LEVEL_DEBUG);
    return $app->json(array('code' => 1));
});
$app->post('/pushPassThroughData', function(Request $request) use ($app) {
    checkRequest($request);
    
    Logger::log('Debug: Received post ' . "pushPassThroughData", Logger::LOG_LEVEL_DEBUG);
    return $app->json(array('code' => 1));
});
$app->post('/pushTerminalTransInfo', function(Request $request) use ($app) {
    checkRequest($request);
    
    Logger::log('Debug: Received post ' . "pushTerminalTransInfo", Logger::LOG_LEVEL_DEBUG);
    return $app->json(array('code' => 1));
});
$app->post('/pushobd', function(Request $request) use ($app) {
    checkRequest($request);
    
    Logger::log('Debug: Received post ' . "pushobd", Logger::LOG_LEVEL_DEBUG);
    return $app->json(array('code' => 1));
});
$app->post('/pushfaultinfo', function(Request $request) use ($app) {
    checkRequest($request);
    
    Logger::log('Debug: Received post ' . "pushfaultinfo", Logger::LOG_LEVEL_DEBUG);
    return $app->json(array('code' => 1));
});
$app->post('/pushtripreport', function(Request $request) use ($app) {
    checkRequest($request);
    
    Logger::log('Debug: Received post ' . "pushtripreport", Logger::LOG_LEVEL_DEBUG);
    return $app->json(array('code' => 1));
});
$app->post('/pushInstructResponse', function(Request $request) use ($app) {
    checkRequest($request);
    
    Logger::log('Debug: Received post ' . "pushInstructResponse", Logger::LOG_LEVEL_DEBUG);
    return $app->json(array('code' => 1));
});

/* Debug */ 
$app->get('/debugResourcesList', function(Request $request) use ($app) {
    $imei = $request->get('imei');
    if (empty($imei)) {
        return $app->json(array('code' => 1));
    }
    
    $result = InstructSenderIotT::send($imei, (int)microtime(true), 'getResourcesList', array(strtotime('-23 hours'), microtime(true)), false);
    if (!$result['success']) {
        $content = '<span style="color:#a90000; font-weight: bold">' . $result['message'] . '</span>';
    } else {
        $content = '<span style="color:#00a900; font-weight: bold">Content:' . $result['content'] . '</span><br/>'
                 . '<span style="color:#00a900; font-weight: bold">Message:' . $result['message'] . ' (' . $result['code'] . ')</span>';
    }
    return '<html>'
            . '<body>'
            . '<p>' . $content . '</p>'
            . '<p><a href="javascript:history.back();">Retour</a></p>'
            . '</body>'
            . '</html>';
});

$app->get('/debugStreamOn', function(Request $request) use ($app) {
    $imei = $request->get('imei');
    if (empty($imei)) {
        return $app->json(array('code' => 1));
    }
    $result = InstructSenderIotT::send($imei, (int)microtime(true), 'enableLiveStream', array(), false);
    if (!$result['success']) {
        $content = '<span style="color:#a90000; font-weight: bold">' . $result['message'] . '</span>';
    } else {
        $content = '<span style="color:#00a900; font-weight: bold">Content:' . $result['content'] . '</span><br/>'
                 . '<span style="color:#00a900; font-weight: bold">Message:' . $result['message'] . ' (' . $result['code'] . ')</span>';
    }
    return '<html>'
            . '<body>'
            . '<p>' . $content . '</p>'
            . '<p><a href="javascript:history.back();">Retour</a></p>'
            . '</body>'
            . '</html>';
});
    
    
$app->get('/debugStreamOff', function(Request $request) use ($app) {
    $imei = $request->get('imei');
    if (empty($imei)) {
        return $app->json(array('code' => 1));
    }
    $result = InstructSenderIotT::send($imei, (int)microtime(true), 'disableLiveStream', array(), false);
    if (!$result['success']) {
        $content = '<span style="color:#a90000; font-weight: bold">' . $result['message'] . '</span>';
    } else {
        $content = '<span style="color:#00a900; font-weight: bold">Content:' . $result['content'] . '</span><br/>'
            . '<span style="color:#00a900; font-weight: bold">Message:' . $result['message'] . ' (' . $result['code'] . ')</span>';
    }
    return '<html>'
            . '<body>'
            . '<p>' . $content . '</p>'
            . '<p><a href="javascript:history.back();">Retour</a></p>'
           . '</body>'
        . '</html>';
});

$app->get('/debugBenoitRequest', function(Request $request) use ($app) {
    if ($request->get('security') != sha1('SecuJimiDebugBenoit69!')) {
        return $app->json(array('code' => 1));
    }
    $_SESSION['security'] = $request->get('security');
    return '<html>'
            . '<body>'
                . '<form method="POST" action="debugBenoitRequest">'
                    . '<input type="hidden" name="imei" value="' . $request->get('imei') . '"/>'
                    . '<p><span>Commande:</span></p>'
                    . '<p><input type="text" name="command" /></p>'
                    . '<p><button>Envoyer</button></p>'
                . '</form>'
            . '</body>'
        . '</html>';
});
$app->post('/debugBenoitRequest', function(Request $request) use ($app) {
    $deviceImei = $request->get('imei');
    $command = $request->get('command');
    if (empty($_SESSION['security'])) {
        return $app->json(array('code' => 1));
    } elseif (empty($deviceImei)) {
        return $app->json(array('code' => 2));
    } elseif (empty($command)) {
        return $app->json(array('code' => 3));
    }
    
    $result = InstructSenderIotT::send($deviceImei, (int)microtime(true), 'debugQ:' . $command, array(), false);
    if (!$result['success']) {
        $content = '<span style="color:#a90000; font-weight: bold">' . $result['message'] . '</span>';        
    } else {
        $content = '<span style="color:#00a900; font-weight: bold">' . $result['content'] . '</span>';
    }
    return '<html>'
            . '<body>'
                . '<p>' . $content . '</p>'
                . '<p><a href="javascript:history.back();">Retour</a></p>'
            . '</body>'
           . '</html>';
});
        
$app->get('/debugBenoitLogs', function(Request $request) use ($app){
    if ($request->get('security') != sha1('SecuJimiDebugBenoit69!')) {
        return $app->json(array('code' => 1));
    }
    
    $imei = $request->get('imei');
    
    $string = shell_exec('tail -n 300 ' . LOG_FILE);

    $lines = explode("\n", $string);
    $lines = array_reverse($lines);

    $coloredLines = array( 'Error:' => 'font-weight: bold;color: #d40000',
                           'Debug: Start treatment' => 'margin-bottom: 20px',
                           //'Debug: Server' => 'color: #a900a9',
                            //'Debug:' => '#d3d7cf',
                            //'Info:' => 'font-weight: bold;'
                        );
    $finalLines = array();
    foreach ($lines as &$line) {
        if (!strpos($line, 'Start treatment')) {
            continue;
        }
        
        if (!empty($imei) && !strpos($line, $imei)) {
            continue;
        }
        
        $found = false;
        foreach ($coloredLines as $str => $style) {
            if (strpos($line, $str) !== false) {
                $line = '<div style="' . $style . '">' . $line . '</div>';
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $line = '<div>' . $line . '</div>';
        }
        $finalLines[] = $line;
    }
    
    $string = implode('', $finalLines);
    return '<html>'
            . '<body>'
                . '<button onclick="document.location.href = document.location.href">Actualiser</button>'
                . '<h1>Dernières lignes:</h1>'
                . '<div>' . $string . '</div>'
            . '</body>'
        . '</html>';
});
$app->run();
