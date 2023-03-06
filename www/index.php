<?php
require("./core.inc.php");

if ($_SERVER['REQUEST_METHOD'] == "OPTIONS") {
	header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
	exit;
} else if ($_SERVER['REQUEST_METHOD'] != "POST" && $_SERVER['REQUEST_METHOD'] != "GET") {
	header('403 Access Forbidden');
	print __("We only accept POST and GET requests...");
	exit;
}

$mod = SanitizedRequestStr('mod');
if (empty($mod)) {
	// The default module if none is specified
	$mod = "login";
}

// The page name for the titlebar
$pagename = "";
// The page description for metadata
$pagedesc = "";
// The page canonical URL for metadata
$pagecan = "";

if ($mod == "logout") {
	if (is_user()) {
		$db->update("Users", array("ID" => $_SESSION['userinfo']['ID'], "LastLogout" => time()));
	}
	kill_session();
	$pagename = __("Logged out");
	require("header.inc.php");
	ShowMsgBox(__("Log Out"), __("You are now logged out."));
	require("footer.inc.php");
	exit;
}

$fn = "./modules/".$mod."/index.php";
/* Check if module name is only legal characters and exists. */
if (strspn($mod,"abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_23") == strlen($mod) && file_exists($fn)) {
	if (!strncasecmp($mod, "acct_", 5)) {
		/* If this is a user module, make sure they are logged in. */
		if (is_user()) {
			if (!headers_sent()) {
				header('Referrer-Policy: same-origin');
			}
			require($fn);
		} else {
			die(__("ERROR: You must be logged in to access this page!"));
		}
	} else if (!strncasecmp($mod, "admin_", 6)) {
		/* If this is an admin module, make sure they are logged in and an admin. */
		if (is_admin()) {
			if (!headers_sent()) {
				header('Referrer-Policy: same-origin');
			}
			require($fn);
		} else {
			/* Just so the user doesn't know if they found a real page. */
			http_response_code(404);
			die(__("ERROR: Invalid command!"));
		}
	} else {
		require($fn);
	}
} else {
	http_response_code(404);
	die(__("ERROR: Invalid command!"));
}
