<?php
/*
driftvm.inc.php - part of DriftVM
License: GPLv3
Copyright 2023 Drift Solutions
*/

function is_valid_network_type($type) {
	if ($type < 0 || $type > 1) {
		return false;
	}
	return true;
}

function is_ip($str) {
	if (empty($str)) { return false; }
	return filter_var($str, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? true:false;
}

function is_ip_with_mask($str) {
	$tmp = explode('/', $str);
	if (count($tmp) != 2) { return false; }
	if (!is_ip($tmp[0])) { return false; }
	if (strspn($tmp[1], '0123456789') != strlen($tmp[1])) { return false; }
	$mask = my_intval($tmp[1]);
	return ($tmp[1] === strval($mask) && $mask >= 8); // for our purposes a mask should be at least 8 bits
}

function parse_ip_mask($str, &$ip, &$mask) {
	if (!is_ip_with_mask($str)) {
		return false;
	}
	$tmp = explode('/', $str);
	$ip = $tmp[0];
	$mask = my_intval($tmp[1]);
	return true;
}

function get_network_device($devname) {
	$networks = net_get_interfaces();
	if (!isset($networks[$devname]) || !isset($networks[$devname]['unicast'])) {
		return false;
	}

	foreach ($networks[$devname]['unicast'] as $net) {
		if ($net['family'] == 2 && !empty($net['address']) && !empty($net['netmask'])) {
			$ret = $networks[$devname];
			return array_merge($ret, $net);
		}
	}

	return FALSE;
}