<?php
/*
functions.inc.php - part of DriftVM
License: GPLv3
Copyright 2023 Drift Solutions
*/
if (PHP_INT_SIZE != 8) {
	die("ERROR: We need 64-bit PHP!\n");
}
if (!function_exists('apcu_add')) {
	die("The PHP APCu extension is required!\n");
}

function is_user() {
	if (isset($_SESSION['userinfo']) && $_SESSION['userinfo']['Status'] >= 1) { return true;	}
	return false;
}
function is_admin() {
	if (isset($_SESSION['userinfo']) && $_SESSION['userinfo']['Status'] >= 2) { return true;	}
	return false;
}

//xss mitigation
function xssafe($data,$encoding='UTF-8') {
	if (defined('ENT_HTML401')) {
		return htmlspecialchars($data,ENT_QUOTES|ENT_HTML401,$encoding);
	} else {
		return htmlspecialchars($data,ENT_QUOTES,$encoding);
	}
}

// Strips any characters that shouldn't be in data such as tags. May break Unicode too, don't know.
function sanitize($str) {
	return trim(filter_var($str, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW|FILTER_FLAG_NO_ENCODE_QUOTES));
}
// Strips any characters that shouldn't be in an email address.
function sanitize_email($str) {
	return trim(filter_var($str, FILTER_SANITIZE_EMAIL));
}
function is_email($str) {
	if (strpos($str, '@') === FALSE) { return false; }
	return filter_var($str, FILTER_VALIDATE_EMAIL) ? true:false;
}
function is_http($str) {
	if (strncasecmp($str, 'https://', 8) == 0 || strncasecmp($str, 'http://', 7) == 0) {
		return true;
	}
	return false;
}
function is_url($str, $with_path=false) {
	if (empty($str)) { return false; }
	if (!is_http($str)) { return false; }
	if (stristr($str, 'javascript:') !== FALSE) { return false; }
	return filter_var($str, FILTER_VALIDATE_URL, $with_path ? FILTER_FLAG_PATH_REQUIRED:0) ? true:false;
}

function is_url_local($url) {
	if (!empty($url)) {
		$host = parse_url($url, PHP_URL_HOST);
		if (substr($host, 0, 4) == '127.' || stristr($host, "localhost") !== FALSE || stristr($host, $_SERVER['SERVER_ADDR']) !== FALSE || stristr($host, '.') == FALSE) {
			return true;
		}
	}
	return false;
}

function FinishRequestStr($str) {
	if (stristr($str, 'javascript:') !== FALSE) { return ""; }
	return trim($str);
}

// GetRequestStr and SanitizedRequestStr are used to get user POST/GET variables, return is always trim()ed and if it starts with javascript: will return ''.
// GetRequestStr returns the string as-is, SanitizedRequestStr runs it through sanitize() from above.
function GetRequestStr($name, $defstr="") {
	if (isset($_REQUEST[$name]) && !is_array($_REQUEST[$name])) {
		return FinishRequestStr($_REQUEST[$name]);
	}
	return FinishRequestStr($defstr);
}

function SanitizedRequestStr($name, $defstr="") {
	if (isset($_REQUEST[$name]) && !is_array($_REQUEST[$name])) {
		return FinishRequestStr(sanitize($_REQUEST[$name]));
	}
	return FinishRequestStr(sanitize($defstr));
}

// Trim a string to len characters.
function TrimTo($str, $len) {
	return trim(substr($str,0,$len));
}

// intval() returns odd values on arrays and objects, this fixes that.
function my_intval($val) {
	if (is_object($val) || is_array($val)) {
		return 0;
	}
	$val = str_replace(',','',$val);
	return (int)$val;
}
// floatval() returns odd values on arrays and objects, this fixes that.
function my_floatval($val) {
	if (is_object($val) || is_array($val)) {
		return 0.00;
	}
	$val = str_replace(',','',$val);
	return (float)$val;
}

// Returns $val clamped to the range of $min - $max (inclusive)
function my_clamp($val, $min, $max) {
	return max($min, min($val, $max));
}

$date_formats = array();
$date_long_formats = array(0 => 'F j, Y', 1 => 'j F Y');
$date_short_formats = array(0 => 'n/j/Y', 1 => 'j/n/Y', 2 => 'Y-m-d', 3 => 'd.m.Y');
$date_time_formats = array(0 => 'h:i:sa', 1 => 'H:i:s');
function GetDateFormat($format) {
	global $date_formats,$date_long_formats,$date_short_formats,$date_time_formats;
	if (count($date_formats) == 0) {
		$date_formats = array (
		'{date_long}' => $date_long_formats[0],
		'{date_short}' => $date_short_formats[0],
		'{time}' => $date_time_formats[0],
		);
		if (is_user() && !empty($_SESSION['userinfo']['DateFormat'])) {
			if (isset($_SESSION['dateformats']) && count($_SESSION['dateformats'])) {
				$date_formats = $_SESSION['dateformats'];
			} else {
				$tmp = my_intval(substr($_SESSION['userinfo']['DateFormat'], 0, 1));
				if (array_key_exists($tmp, $date_short_formats)) {
					$date_formats['{date_short}'] = $date_short_formats[$tmp];
				}
				$tmp = my_intval(substr($_SESSION['userinfo']['DateFormat'], 1, 1));
				if (array_key_exists($tmp, $date_long_formats)) {
					$date_formats['{date_long}'] = $date_long_formats[$tmp];
				}
				$tmp = my_intval(substr($_SESSION['userinfo']['DateFormat'], 2, 1));
				if (array_key_exists($tmp, $date_time_formats)) {
					$date_formats['{time}'] = $date_time_formats[$tmp];
				}
				$_SESSION['dateformats'] = $date_formats;
			}
		}
	}
	return str_replace(array_keys($date_formats), array_values($date_formats), $format);
}

$usercache = array();
function GetUserInfoByID($id, $timeout=0) {
	global $usercache,$db;
	$id = my_intval($id);
	$timeout = my_intval($timeout);
	if ($id <= 0) {
		return array();
	}
	if (isset($usercache[$id])) {
		return $usercache[$id];
	}
	if ($timeout > 0) {
		$ret = cache1_get("user_by_id_".$timeout."_".$id);
		if ($ret !== FALSE && is_array($ret) && count($ret)) {
			$usercache[$id] = $ret;
			return $ret;
		}
	}
	$query = "SELECT * FROM `Users` WHERE `ID`='".$db->escape($id)."'";
	$res = $db->query($query);
	if ($arr = $db->fetch_assoc($res)) {
		$usercache[$id] = $arr;
		if ($timeout > 0) {
			cache1_store("user_by_id_".$timeout."_".$id, $arr, $timeout);
		}
		return $arr;
	}
	return array();
}

function GenTimeZoneDrop($cur="", $name="timezone", $extra="") {
	if ($cur == "") { $cur = "America/New_York"; }

	$ret = "\n<select name=\"".$name."\" ".$extra.">\n";

	$zones = timezone_identifiers_list();
	$arr = array();
	foreach ($zones as $zone) {
		$x = explode("/", $zone);
		if (count($x) == 3) {
			$arr[$x[0]][$x[1]][$zone] = $x[2];
		} else if (count($x) == 2) {
			$arr[$x[0]][$zone] = $x[1];
		} else if (count($x) == 1){
			$arr[$x[0]] = $zone;
		}
	}

	ksort($arr);

	foreach ($arr as $top => $zone) {
		if (is_array($zone)) {
			$ret .= "\n<optgroup label=\"".str_replace("_"," ",$top)."\">\n";
			foreach ($zone as $sub => $val) {
				if (is_array($val)) {
					$ret .= "<option DISABLED><b>".str_replace("_"," ",$sub)."</option>\n";
					foreach ($val as $sub2 => $val2) {
						//print_r($val);
						$ret .= "<option value=\"".$sub2."\"".iif(($sub2 == $cur)," SELECTED","").">&middot; ".str_replace("_"," ",$val2)."</option>\n";
					}
					//$ret .= "</optgroup>\n";
				} else {
					$ret .= "<option value=\"".$sub."\"".iif(($sub == $cur)," SELECTED","").">".str_replace("_"," ",$val)."</option>\n";
				}
			}
			$ret .= "</optgroup>\n";
		} else {
			$ret .= "<option value=\"".$zone."\"".iif(($zone == $cur)," SELECTED","").">".str_replace("_"," ",$top)."</option>\n";
		}
	}
	$ret .= "</select>\n";
	return $ret;
}

// Just checks with PHP to make sure the timezone is a valid one.
function is_valid_timezone($cur) {
	$zones = timezone_identifiers_list();
	return in_array($zone, $zones);
}

// Returns a pretty string for a number of seconds. ie. 60 = 1 minute or 900 = 15 minutes
function duration2($secs, $include_seconds = TRUE) {
	$vDay = 0;
	$vHour = 0;
	$vMin = 0;

	while ($secs >= 86400) {
		$secs = $secs - 86400;
		$vDay++;
	}

	while ($secs >= 3600) {
		$secs = $secs - 3600;
		$vHour++;
	}

	while ($secs >= 60) {
		$secs = $secs - 60;
		$vMin++;
	}

	$sout = "";
	if ($vDay > 0) { $sout = $vDay." day".iif($vDay != 1,"s",'').", "; }
	if ($vHour > 0) { $sout = $sout.$vHour." hour".iif($vHour != 1,"s",'').", "; }
	if ($vMin > 0) { $sout = $sout.$vMin." minute".iif($vMin != 1,"s",'').", "; }
	if (empty($sout)  || ($secs > 0 && $include_seconds)) { $sout = $sout.$secs." second".iif($secs != 1,"s",''); }

	return rtrim(rtrim($sout), ',');
}

function bytes($num) {
	$amounts = array(
		'TiB' => 1024*1024*1024*1024,
		'GiB' => 1024*1024*1024,
		'MiB' => 1024*1024,
		'KiB' => 1024,
	);
	foreach ($amounts as $disp => $amount) {
		if ($num >= $amount) {
			return sprintf('%.02f', $num / $amount).' '.$disp;
		}
	}
	return sprintf('%.02f B', $num);
}

function get_cache_string($key) {
	return "driftvm_".$key;
}

// The cache1_* functions use APCu.
function cache1_get($key) {
	$suc = false;
	$x = apcu_fetch(get_cache_string($key), $suc);
	if ($suc) {
		return $x;
	}
	return FALSE;
}
function cache1_store($key, $content, $ttl=0) {
	apcu_store(get_cache_string($key), $content, $ttl);
}
function cache1_delete($key) {
	apcu_delete(get_cache_string($key));
}

// This should only be used for queries that return (relatively) small amounts of data.
function cached_query_exec($query) {
	global $db;

	$ret = array();
	$res = $db->query($query);
	while ($arr = $db->fetch_assoc($res)) {
		$ret[] = $arr;
	}
	return $ret;
}

function cache1_query($query, $ttl=0) {
	// Hash is just for collision avoidance, SHA-1 should be plenty good enough
	$key = 'cached_query_'.sha1($query.'_'.$ttl);
	$ret = cache1_get($key);
	if ($ret === FALSE || !is_array($ret)) {
		$ret = cached_query_exec($query);
		cache1_store($key, $ret, $ttl);
	}
	return $ret;
}

$templatecache = array();
// Gets a template from the pages folder.
function gettemplate($name) {
	global $db,$config,$templatecache;

	if (strspn($name,"abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_0123456789") != strlen($name)) {
		return "Invalid Template: ".$name;
	}

	$key = 'template_'.$name;
	if (array_key_exists($key, $templatecache)) { return $templatecache[$key]; }

	$fn = $config['site_fsroot']."templates/".$name.".tpl";
	$ret = cache1_get($key);
	if ($ret !== FALSE) {
		$templatecache[$key] = $ret;
		return $ret;
	} else if (is_readable($fn)) {
		$ret = file_get_contents($fn);
		if ($ret !== FALSE) {
			$templatecache[$key] = $ret;
			cache1_store($key, $ret, 1800);
			return $ret;
		}
	}
	return "Invalid Template: ".$name;
}

function std_redirect_reload($cmd, $text="") {
	global $config;
	if (empty($text)) { $text = 'You are being redirected to a new page, if you are not automatically forwarded in 5 seconds click here.'; }
	$url = $config['site_url']."index.php?mod=".urlencode($cmd);
	$ret = "<center><a href=\"".xssafe($url)."\" target=\"_top\">".xssafe($text)."</a><br /><img src=\"images/redirect.gif\" border=0></center>";
	$ret .= "<script type=\"text/javascript\">setTimeout('window.open(".json_encode($url).", \"_top\");', 1000);</script>";
	return $ret;
}

function std_redirect($cmd, $extra="", $text="") {
	global $config;
	if (empty($text)) { $text = 'You are being redirected to a new page, if you are not automatically forwarded in 5 seconds click here.'; }
	$url = $config['site_url']."module.php?mod=".urlencode($cmd).iif(($extra != ""),"&".$extra,"");
	$ret = "<center><a href=\"".xssafe($url)."\">".xssafe($text)."</a><br /><img src=\"images/redirect.gif\" border=0></center>";
	$ret .= "<script type=\"text/javascript\">setTimeout('window.open(".json_encode($url).", \"_self\");', 1000);</script>";
	return $ret;
}

function std_redirect_exturl($url, $text="") {
	if (empty($text)) { $text = 'You are being redirected to a new page, if you are not automatically forwarded in 5 seconds click here.'; }
	$ret = "<center><a href=\"".xssafe($url)."\">".xssafe($text)."</a><br /><img src=\"images/redirect.gif\" border=0></center>";
	$ret .= "<script type=\"text/javascript\">setTimeout('window.open(".json_encode($url).", \"_self\");', 1000);</script>";
	return $ret;
}

// if $blah is true return $ret1, otherwise return $ret2
function iif($blah,$ret1,$ret2="") {
	if ($blah) { return $ret1; }
	return $ret2;
}

// Generates secure random data for keys, etc. If somehow all else fails falls back to mt_rand.
function get_random_bytes($size) {
	for ($i=0; $i < 5; $i++) {
		if (function_exists('random_bytes')) {
			try {
				$iv = random_bytes($size);
			} catch (Exception $e) {
				$iv = FALSE;
			}
		} else if (function_exists('openssl_random_pseudo_bytes')) {
			$iv = openssl_random_pseudo_bytes($size);
		} else if (function_exists('mcrypt_create_iv')) {
			$iv = mcrypt_create_iv($size, MCRYPT_DEV_URANDOM);
		} else {
			die("Could not find secure RNG");
		}
		if ($iv !== FALSE) { return $iv; }
	}
	$iv = '';
	for($i = 0; $i < $size; $i++) {
		$iv .= chr(mt_rand(0,255));
	}
	return $iv;
}

function get_verification_charset() {
	return 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
}

function get_verification_code($length = 8) {
	$charset = get_verification_charset();
	$max = strlen($charset) - 1;
	$ret = '';
	for ($i = 0; $i < $length; $i++) {
		$ret .= $charset[random_int(0, $max)];
	}
	return $ret;
}

function EncryptData($secret, $data, $raw = FALSE) {
	global $config;
	if (empty($data)) { return ''; }

	$cipher = 'AES-256-CTR';
	$iv_size = openssl_cipher_iv_length($cipher);
	$secret = hash('sha256', $secret, true);
	$iv = get_random_bytes($iv_size);

	$hmac = hash_hmac('sha256', $data, $config['encrypt_data_hmac_secret'], TRUE);
	$encrypted = $iv.openssl_encrypt($hmac.$data, $cipher, $secret, OPENSSL_RAW_DATA, $iv);
	if ($raw) {
		return $encrypted;
	}
	return base64_encode($encrypted);
}

function DecryptData($secret, $data, $from_raw = FALSE) {
	global $config;
	if (empty($data)) { return ''; }

	$cipher = 'AES-256-CTR';
	$iv_size = openssl_cipher_iv_length($cipher);
	$secret = hash('sha256', $secret, true);

	$data = $from_raw ? $data : base64_decode($data);
	if (strlen($data) <= $iv_size) {
		return '';
	}

	$iv = substr($data, 0, $iv_size);
	$data = substr($data, $iv_size);

	$data = openssl_decrypt($data, $cipher, $secret, OPENSSL_RAW_DATA, $iv);
	if (strlen($data) <= 32) { /* Data length should be 32 bytes of HMAC + at least 1 byte of decrypted data */
		return '';
	}

	$stored_hmac = substr($data, 0, 32);
	$data = substr($data, 32);
	$hmac = hash_hmac('sha256', $data, $config['encrypt_data_hmac_secret'], TRUE);

	if (hash_equals($hmac, $stored_hmac)) {
		return $data;
	}

	return '';
}

function MakeErrorReport($cat,$str) {
	global $userinfo;

	$ret = "Error Report\n\n";
	$ret .= "Error category: $cat\n";
	$ret .= "Error Description: $str\n\n";
	if (isset($_SESSION['userinfo'])) {
		$ret .= "User Info\n";
		foreach($_SESSION['userinfo'] as $key => $val) {
			$ret .= "$key = $val\n";
		}
		$ret .= "\n";
	}

	$ret .= "Globals Dump\n\n";
	foreach($_SERVER as $key => $val) {
		$ret .= "\$_SERVER[$key] => $val\n";
	}
	foreach($_GET as $key => $val) {
		$ret .= "\$_GET[$key] => $val\n";
	}
	foreach($_POST as $key => $val) {
		$ret .= "\$_POST[$key] => $val\n";
	}
	$ret .= "\n";

	$ret .= "End of report...\n";
	return $ret;
}

function OpenPanel($title='', $class='') {
	/*
  <div class="card-header">
    Featured
  </div>
  <div class="card-body">
	*/
	print '<div class="card">';
	if (!empty($title)) {
		print '<h5 class="card-header '.$class.'">'.$title.'</h5>';
	}
	print '<div class="card-body">';
}
function ClosePanel($footer='') {
	print '</div>';
	if (!empty($footer)) {
		print '<div class="card-footer text-muted">'.$footer.'</div>';
	}
	print '</div>';
}

function ShowMsgBox($title, $content, $extra="") {
	print '<div class="container">';
	if (stristr($title, 'Error') !== FALSE || stristr($title, 'Gas Notice') !== FALSE) {
		$class = 'text-bg-danger';
	} elseif (stristr($title, 'Warning') !== FALSE) {
		$class = 'text-bg-warning';
	} else {
		$class = 'text-bg-primary';
	}
	OpenPanel($title, $class);
	print $content;
	ClosePanel();
	print '</div>';
}

//danger, success or primary
function ShowAlert($content, $extra="danger") {
	print '<div class="container mb-5">
	<div class="alert alert-'.$extra.'" role="alert">';
	print $content;
	print '</div>
	</div>';
}

function hcaptcha_verify_code($code) {
	global $config;
	$data = array(
		'secret' => $config['hcaptcha_secret'],
		'response' => $code,
	);
	$verify = curl_init();
	curl_setopt($verify, CURLOPT_URL, "https://hcaptcha.com/siteverify");
	curl_setopt($verify, CURLOPT_POST, true);
	curl_setopt($verify, CURLOPT_POSTFIELDS, http_build_query($data));
	curl_setopt($verify, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($verify, CURLOPT_TIMEOUT, 30);
	curl_setopt($verify, CURLOPT_CONNECTTIMEOUT, 10);
	$ret = curl_exec($verify);
	curl_close($verify);
	if ($ret !== FALSE) {
		$tmp = json_decode($ret, TRUE);
		if (is_array($tmp) && count($tmp) && $tmp['success']) {
			return true;
		}
	}
	return false;
}
