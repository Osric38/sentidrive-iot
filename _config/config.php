<?php

	error_reporting(E_ALL | E_STRICT);
#	ini_set('display_errors', 1);

	session_start();
	if (!isset($_SESSION['initiated']))
	{
	    session_regenerate_id(true);
	    $_SESSION['initiated'] = true;
	}
	
	if(isset($_SESSION['last_session_request']) && $_SESSION['last_session_request'] > (time() - 1)){
		if(empty($_SESSION['last_request_count'])){
			$_SESSION['last_request_count'] = 1;
		}elseif($_SESSION['last_request_count'] < 10){
			$_SESSION['last_request_count'] = $_SESSION['last_request_count'] + 1;
		}elseif($_SESSION['last_request_count'] >= 10){
			//url page 404
			//header('location: http://lebonminecraft.fr/404.html');
			exit;
		}
	} else{
		$_SESSION['last_request_count'] = 1;
	}
	$_SESSION['last_session_request'] = time();
	
	//ini_set ('session.cookie_lifetime', 0);
	
	define('MYSQL_DEBUG', false);
	
	define('SITE_NAME', 'Sentidrive.fr');

    define('ACTIVE_LOG', false);
    // Parametres locaux
    if(isset($_SERVER['SERVER_ADDR']) && 
        ($_SERVER['SERVER_ADDR'] == '127.0.0.1' || $_SERVER['SERVER_NAME'] == 'localhost')) {
            define('BDD_HOST', 'localhost');
            define('BDD_USER', 'root');
            define('BDD_PASSWORD', '');
            define('BDD_PORT', 3307);
            define('BDD_NAME', 'sentidrive_datas');
           // define('BDD_HOST', 'ns342281.ip-188-165-211.eu');
           // define('BDD_USER', 'user_handball');
           // define('BDD_PASSWORD', 'ypX$5o13');
           // define('BDD_NAME', 'handballBDD');
                
            define('MOD_REWRITE_ENABLED', false);
            define('MAIL_SEND_ENABLED', true);
            
            define('BASE_URL', 'http://localhost:8080/Divers/ESGA/');
        } else {
            //define('BDD_HOST', 'db-mysql-ams3-52863-do-user-8999648-0.b.db.ondigitalocean.com'); //DO
            define('BDD_HOST', 'proxysql.pub.sentidrive.fr'); //DO Proxy
            //define('BDD_HOST', '10.110.0.7'); //DO Proxy
            //define('BDD_HOST', 'private-db-mysql-ams3-52863-do-user-8999648-0.b.db.ondigitalocean.com'); //OVH
            //define('BDD_HOST', 'private-db-mysql-ams3-17529-do-user-8999648-0.b.db.ondigitalocean.com'); //OVH
            define('BDD_PORT', 25060);
            define('BDD_USER', 'doadmin');
            define('BDD_PASSWORD', 'AVNS_9v3uzv3DdShR8t0q7Ez');
            //define('BDD_PASSWORD', 'ba6az83073gk9riv');
            define('BDD_NAME', 'sentidrive_datas');
            /*define('BDD_HOST', 'ns382851.ip-46-105-101.eu'); //OVH
            define('BDD_PORT', 3306);
            define('BDD_USER', 'user_sentidrive_datas_072023');
            define('BDD_PASSWORD', '~E5l91bc1O2vx0h0*7');
            define('BDD_NAME', 'sentidrive_datas_072023');*/
        define('MOD_REWRITE_ENABLED', true);
        define('MAIL_SEND_ENABLED', true);
        define('BASE_URL', 'https://sentidrive.fr/');
    }
    define('USER_SESSION_NAME', 'site_session');
    
    define('CONTACT_NAME', 'Mehdi');
    define('CONTACT_MAIL', 'mehdi.guenoune@gmail.com');
     
?>
