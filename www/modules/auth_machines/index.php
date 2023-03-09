<?php
/*
Part of DriftVM
License: GPLv3
Copyright 2023 Drift Solutions
*/

$action = SanitizedRequestStr('action');
if ($action == 'vnc') {
	$name = SanitizedRequestStr('name');
	if (empty($name)) {
		require("header.inc.php");
		ShowMsgBox('Error', 'No name specified!');
		require("footer.inc.php");
		exit;
	}

	$res = $db->query("SELECT * FROM `Machines` WHERE `Name`='".$db->escape($name)."'");
	if ($db->num_rows($res) == 0) {
		require("header.inc.php");
		ShowMsgBox('Error', 'Invalid machine specified!');
		require("footer.inc.php");
		exit;
	}
	$arr = $db->fetch_assoc($res);
	$db->free_result($res);

	if (empty($arr['Extra'])) {
		require("header.inc.php");
		ShowMsgBox('Error', 'Machine does not have VNC data!');
		require("footer.inc.php");
		exit;
	}

	$tmp = json_decode($arr['Extra'], TRUE);
	if (!is_array($tmp) || count($tmp) == 0) {
		require("header.inc.php");
		ShowMsgBox('Error', 'Machine does not have VNC data!');
		require("footer.inc.php");
		exit;
	}

	if (!isset($tmp['VNC Display']) || !isset($tmp['VNC Password'])) {
		require("header.inc.php");
		ShowMsgBox('Error', 'Machine does not have VNC data!');
		require("footer.inc.php");
		exit;
	}

	header('Content-Disposition: attachment; filename="'.xssafe($arr['Name']).'.vnc"');
	print "[connection]\n";
	$host = empty($_SERVER['HTTP_HOST']) ? $_SERVER['SERVER_ADDR'] : $_SERVER['HTTP_HOST'];
	print "host=".$host."\n";
	print "port=".(my_intval(substr($tmp['VNC Display'], 1)) + 5900)."\n";
	print "password=".encrypt_vnc_pass($tmp['VNC Password'])."\n";
	exit;
}

require("header.inc.php");
switch ($action) {
	case 'create':
	case 'view':
		require('./modules/auth_machines/'.$action.'.php');
		break;
	default:
		require('./modules/auth_machines/list.php');
		break;
}
require("footer.inc.php");
