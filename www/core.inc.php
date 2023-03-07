<?php
/*
core.inc.php - part of DriftVM
License: GPLv3
Copyright 2023 Drift Solutions
*/
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
if (!defined('CORE_INC_PHP')) {
	define('CORE_INC_PHP','INCLUDED');
	define('DRIFTVM_VERSION','0.0.1');
	$config = array();
	require("config.inc.php");

	if (!headers_sent()) {
		header('X-Frame-Options: SAMEORIGIN'); // Prevent clickjacking
		if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on") {
			header('Strict-Transport-Security: max-age=31536000;'); // Tells browsers to only use HTTPS and never HTTP for our site
		}
		header('X-XSS-Protection: 1; mode=block');
	}

	if (isset($config['Debug']) && $config['Debug']) {
		error_reporting(E_ALL & ~E_STRICT & ~E_DEPRECATED);
		ini_set("display_errors", "on");
	}

	require('./include/functions.inc.php');
	require('./include/db.mysqli.inc.php');
	if (!$db->init($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_database'], $config['db_port'])) {
		http_response_code(503);
		die("FATAL: Error connecting to database!");
	}
	require('./include/csrf.inc.php');
	require('./include/gridtable.inc.php');
	require('./include/ratelimiter.inc.php');

	date_default_timezone_set("America/New_York");

	$ban_exempt = array(
		// Put any IPs here you want exempt from bans
	);
	if (php_sapi_name() != "cli" && !in_array($_SERVER['REMOTE_ADDR'], $ban_exempt)) {
		/*
			Check for SQL/Javascript injection attacks, vulnerability scanners (Acunetix), etc. and ban IP.
			It does this by checking for strings that should never appear in POST/GET data.
		*/
		function implode_r($glue,$arr){
			$ret_str = "";
			foreach($arr as $a){
				if (!empty($ret_str)) { $ret_str .= $glue; }
				$ret_str .= (is_array($a)) ? implode_r($glue,$a) : (string)$a;
			}
			return $ret_str;
		}

		$banned = false;
		$expires = 0;
		$reason = "Unknown";
		$query = "SELECT * FROM `BannedIPs` WHERE `IP`='".$db->escape($_SERVER['REMOTE_ADDR'])."' AND (`Expires`=0 OR `Expires`>".time().")";
		$res = $db->query($query);
		if ($db->num_rows($res)) {
			$arr = $db->fetch_assoc($res);
			$banned = true;
			$expires = $arr['Expires'];
			$reason = $arr['Reason'];
		}
		if (!$banned) {
			$banterms = array("acunetix","Mozilla/1.0","Mozilla/2.0","Mozilla/3.0","Indy Library");
			$match="";
			if (isset($_SERVER['HTTP_USER_AGENT']) && str_ireplace($banterms, '', $_SERVER['HTTP_USER_AGENT']) != $_SERVER['HTTP_USER_AGENT']) {
				$match = $_SERVER['HTTP_USER_AGENT'];
				$banned = true;
				$expires = time() + 86400;
				$reason = "Suspiciously Old Browser";
			}
			$banterms = array("sleep(","/**","**/","user(","jos_users","acunetix","/etc/passwd","concat(","prompt(","acUn3t1x","onmouseover=","count(*)","/proc/self/","w3af.sourceforge.net","w3af.org",'and 1=1','or 1=1','sysdate()','now()');
			foreach($_GET as $bkey => $bval) {
				if (is_array($bval)) {
					$bval = implode_r(',', $bval);
				}
				if (str_ireplace($banterms, '', $bval) != $bval) {
					$match = $bval;
					$banned = true;
					$expires = time() + (86400 * 7);
					$reason = "Injection Attack";
					break;
				}
			}
			foreach($_POST as $bkey => $bval) {
				if (is_array($bval)) {
					$bval = implode_r(',', $bval);
				}
				if (str_ireplace($banterms, '', $bval) != $bval) {
					$match = $bval;
					$banned = true;
					$expires = time() + (86400 * 7);
					$reason = "Injection Attack";
					break;
				}
			}
			unset($_SERVER['REQUEST_TIME_FLOAT']);
			foreach($_SERVER as $bkey => $bval) {
				if (is_array($bval)) {
					$bval = implode_r(',', $bval);
				}
				if (str_ireplace($banterms, '', $bval) != $bval) {
					$match = $bval;
					$banned = true;
					$expires = time() + (86400 * 7);
					$reason = "Injection Attack";
					break;
				}
			}
			if ($banned) {
				if ($expires) {
					$expires += mt_rand(3600,86400);
				}
				$query = "REPLACE INTO BannedIPs (`IP`,`TimeStamp`,`Expires`,`Reason`,`Data`) VALUES ('".$db->escape($_SERVER['REMOTE_ADDR'])."', ".time().", ".$expires.", '".$db->escape($reason)."', '".$db->escape($match)."')";
				$db->query($query);
			}
		}
		if ($banned) {
			print "Your IP (".xssafe($_SERVER['REMOTE_ADDR']).") has been banned from DriftVM ";
			if ($expires == 0) {
				print "permanently.".'<br /><br />';
			} else {
				print "until ".date("F j, Y, g:i a").'<br /><br />';
			}
			exit;
		}
	}

	/* Destroys the current user's session */
	function kill_session() {
		$_SESSION = array();
		if (ini_get("session.use_cookies") && !headers_sent()) {
		    $params = session_get_cookie_params();
		    setcookie(session_name(), '', time() - 86400, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
		}
		// Finally, destroy the session.
		if (session_status() == PHP_SESSION_ACTIVE) { session_destroy(); }
		session_start();
		init_session();
	}

	function get_cookie_domain() {
		if (ini_get("session.use_cookies")) {
		    $params = session_get_cookie_params();
	    	return $params["domain"];
		}
	}

	function init_session() {
		if (!isset($_SESSION['session_ip'])) {
			$_SESSION['session_ip'] = $_SERVER['REMOTE_ADDR'];
		}
	}

	if (!defined('__NO_SESSION__')) {
		session_start();
		init_session();

		/* If the users IP has changed destroy the session, this prevents session hijacking. */
		if ($_SESSION['session_ip'] != $_SERVER['REMOTE_ADDR']) {
			kill_session();
		}
		if (isset($_SESSION['last_seen']) && time() - $_SESSION['last_seen'] > mt_rand(900,1200)) {
			//hard 15-20 minute expiration
			kill_session();
		}
		$_SESSION['last_seen'] = time();

		/* Double-check the user has not been banned or deleted since they logged in and load user options like time zone. */
		if (isset($_SESSION['userinfo'])) {
			$res = $db->query("SELECT * FROM `Users` WHERE ID='".$db->escape($_SESSION['userinfo']['ID'])."' AND `Status`>0");
			if ($arr = $db->fetch_assoc($res)) {
				if ($arr['LastLogout'] <= $arr['LastLogin'] && !strcmp($arr['Password'], $_SESSION['userinfo']['Password']) && !strcmp($arr['Email'], $_SESSION['userinfo']['Email'])) {
					$arr['LastSeen'] = time();
					$_SESSION['userinfo'] = $arr;
					$db->update("Users", array("ID" => $arr['ID'], "LastSeen" => $arr['LastSeen'], "LastIP" => $_SERVER['REMOTE_ADDR']));
					if (!empty($arr['TimeZone']) && is_valid_timezone($arr['TimeZone'])) {
						if (!date_default_timezone_set($arr['TimeZone'])) {
							date_default_timezone_set("America/New_York");
						}
					}
				} else {
					/* User has clicked the Log Out button in some session or changed their email or password */
					kill_session();
				}
			} else {
				kill_session();
			}
		}
	} // if (!defined('__NO_SESSION__'))
}// !defined('CORE_INC_PHP')

