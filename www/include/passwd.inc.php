<?php
/*
passwd.inc.php - part of DriftVM
License: GPLv3
Copyright 2023 Drift Solutions
*/

function SetPasswordByID($id, $pass) {
	global $config,$db;
	$pass = TrimTo($pass, 72);
	$hash = password_hash($pass, PASSWORD_DEFAULT);
	$db->query("UPDATE `Users` SET `Password`='".$db->escape(EncryptData($config['pass_enc_key'], $hash))."' WHERE ID='".$db->escape($id)."'");
}

function CheckEncryptedPassword($pass, $hash) {
	global $config;
	$pass = TrimTo($pass, 72);
	return password_verify($pass, DecryptData($config['pass_enc_key'], $hash));
}

function CheckPassword($pass, $hash) {
	$pass = TrimTo($pass, 72);
	return password_verify($pass, $hash);
}
