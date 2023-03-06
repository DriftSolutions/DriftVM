<?php
/*
csrf.inc.php - part of DriftVM
License: GPLv3
Copyright 2023 Drift Solutions
*/

function csrf_get_or_create($page, $force_new) {
	if (!isset($_SESSION['csrf'])) {
		$_SESSION['csrf'] = array();
	}
	if (!isset($_SESSION['csrf'][$page]) || $force_new) {
		$_SESSION['csrf'][$page] = array(
			'code' => get_verification_code(32),
			'field' => get_verification_code(32),
		);
	}
}

function csrf_get_html($page) {
	csrf_get_or_create($page, FALSE);
	return '<input type="hidden" name="'.xssafe($_SESSION['csrf'][$page]['field']).'" value="'.xssafe($_SESSION['csrf'][$page]['code']).'">';
}

function csrf_verify($page) {
	if ($_SERVER['REQUEST_METHOD'] != "POST") { return FALSE; }
	csrf_get_or_create($page, FALSE);
	$tmp = $_SESSION['csrf'][$page];
	unset($_SESSION['csrf'][$page]);

	$code = SanitizedRequestStr($tmp['field']);
	return hash_equals($tmp['code'], $code);
}
