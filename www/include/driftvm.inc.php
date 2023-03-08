<?php
/*
driftvm.inc.php - part of DriftVM
License: GPLv3
Copyright 2023 Drift Solutions
*/

/* Network Types */
define('NT_ROUTED', 0);
define('NT_ISOLATED', 1);

/* Machine Statuses */
define('MS_STOPPED', 0);
define('MS_CREATING', 1);
define('MS_ERROR_CREATING', 2);
define('MS_UPDATING', 3);
define('MS_STARTING', 4);
define('MS_STOPPING', 5);
define('MS_DELETING', 6);
define('MS_RUNNING', 100);

function GetMachineStatusString($arr) {
	switch ($arr['Status']) {
		case MS_STOPPED:
			return 'Stopped';
		case MS_CREATING:
			return 'Creating';
		case MS_ERROR_CREATING:
			return 'Creation Error';
		case MS_UPDATING:
			return 'Updating';
		case MS_STARTING:
			return 'Starting';
		case MS_STOPPING:
			return 'Stopping';
		case MS_DELETING:
			return "Deleting";
		case MS_RUNNING:
			return 'Running';
		default:
			return 'Unknown ('.xssafe($arr['Status']).')';
	}
}

function is_valid_network_type($type) {
	if ($type < 0 || $type > 1) {
		return false;
	}
	return true;
}

function IsOurNetwork($device) {
	global $db;
	if (empty($device)) { return false; }
	$res = $db->query("SELECT COUNT(*) AS `Count` FROM `Networks` WHERE `Device`='".$db->escape($device)."'");
	$arr = $db->fetch_assoc($res);
	$db->free_result($res);
	return ($arr['Count'] > 0);
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

function get_network_interfaces() {
	$ret = [];
	$networks = net_get_interfaces();
	foreach ($networks as $dev => $arr) {
		if (!isset($arr['unicast'])) { continue; }
		foreach ($arr['unicast'] as $net) {
			if ($net['family'] == 2 && !empty($net['address']) && !empty($net['netmask'])) {
				unset($arr['unicast']);
				$ret[$dev] = array_merge($arr, $net);
				break;
			}
		}
	}
	return $ret;
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

function HaveSettings() {
	global $db;
	$res = $db->query("SELECT COUNT(*) AS `Count` FROM `Settings` LIMIT 1");
	$arr = $db->fetch_assoc($res);
	$db->free_result($res);
	return ($arr['Count'] > 0);
}

$settings_cache = [];
function CacheAllSettings() {
	global $db,$settings_cache;
	$res = $db->query("SELECT * FROM `Settings`");
	if ($arr = $db->fetch_assoc($res)) {
		$settings_cache[$arr['Name']] = $arr['Value'];
	}
	$db->free_result($res);
}

function GetSetting($name, $def = '', $use_cache = TRUE) {
	global $db,$settings_cache;
	if ($use_cache && isset($settings_cache[$name])) {
		return $settings_cache[$name];
	}
	$res = $db->query("SELECT * FROM `Settings` WHERE `Name`='".$db->escape($name)."'");
	if ($arr = $db->fetch_assoc($res)) {
		$db->free_result($res);
		$settings_cache[$name] = $arr['Value'];
		return $arr['Value'];
	}
	$db->free_result($res);
	$settings_cache[$name] = $def;
	return $def;
}

function GetSettingJSON($name, $def = FALSE, $use_cache = TRUE) {
	$tmp = GetSetting($name, FALSE, $use_cache);
	if ($tmp !== FALSE) {
		return json_decode($tmp, TRUE);
	}
	return $def;
}

function UpdateSetting($name, $value) {
	global $db,$settings_cache;
	$settings_cache[$name] = $value;
	$update = ['Name' => $name, 'Value' => $value];
	return $db->insert_or_update('Settings', $update);
}

function UpdateSettingJSON($name, $value) {
	return UpdateSetting($name, json_encode($value));
}

class MachineDriver {
	public $guid = '';
	public $driver_name = '';

	public function __construct() {
	}

	public function GetMachineType($short = true) {
		if ($short) {
			return 'LXC';
		}
		return 'LXC Container';
	}

	public function GetCreateOptions() { return []; }
	public function ValidateCreateOptions(&$opts) { return true; }

	public function Create($options) { return false;	}
	public function Destroy($machine) {	return false; }

	public function Start($machine) { return false;	}
	public function Stop($machine) { return false;	}
};

$machine_drivers = [];
function LoadMachineDrivers() {
	global $machine_drivers;
	if (count($machine_drivers) == 0) {
		$machine_drivers = [];
		$files = glob('./include/machine_drivers/*.php');
		foreach ($files as $fn) {
			require($fn);
		}
	}
}

function RegisterMachineDriver($d) {
	global $machine_drivers;
	$machine_drivers[$d->guid] = $d;
}

function GetMachineDrivers() {
	global $machine_drivers;
	LoadMachineDrivers();
	return $machine_drivers;
}

function GetMachineDriver($guid) {
	global $machine_drivers;
	LoadMachineDrivers();
	if (isset($machine_drivers[$guid])) {
		return $machine_drivers[$guid];
	}
	return FALSE;
}

function RenderOptions($opts) {
	/*
		return [
			'use_image' => [
				'type' => 'checkbox',
				'value' => true,
				'default' => false,
				'label' => 'Use Disk Image',
				'desc' => '',
			],
			'image_size' => [
				'type' => 'number',
				'min' => 1,
				'max' => 99999999,
				'default' => 25,
				'label' => 'Disk Image Size',
				'desc' => '',
			],
		];
		*/
	$ret = '';
	foreach ($opts as $name => $arr) {
		$ret .= '<div class="mb-3 row"><label for="'.xssafe($name).'" class="col-sm-2 col-form-label">'.xssafe($arr['label']).'</label><div class="col-sm-10">';
		if ($arr['type'] == 'checkbox') {
			$ret .= '<div class="form-check form-switch">
  <input class="form-check-input" type="checkbox" role="switch" value="'.xssafe($arr['value']).'" id="'.xssafe($name).'" name="'.xssafe($name).'"'.iif($arr['value'] == SanitizedRequestStr($name, $arr['default']),' checked','').'>
  <label class="form-check-label" for="'.xssafe($name).'">'.xssafe($arr['desc']).'</label>
 </div>';
		} else if ($arr['type'] == 'select') {
			$cur = SanitizedRequestStr($name, $arr['default']);
			$ret .= '<select class="form-control" id="'.xssafe($name).'" name="'.xssafe($name).'">';
			foreach ($arr['options'] as $val => $disp) {
				$ret .= '<option value="'.xssafe($val).'"'.iif($val == $cur,' selected','').'>'.xssafe($disp).'</option>';
			}
			$ret .= '</select>';
		} elseif ($arr['type'] == 'number') {
			$ret .= '<input type="number" class="form-control" id="'.xssafe($name).'" name="'.xssafe($name).'" value="'.xssafe(SanitizedRequestStr($name, $arr['default'])).'"'.iif(isset($arr['min']),' min='.xssafe($arr['min']), '').iif(isset($arr['max']),' max='.xssafe($arr['max']), '').iif(isset($arr['step']),' step='.xssafe($arr['step']), '').'>';
			if (!empty($arr['desc'])) {
				$ret .= '<br />'.$arr['desc'];
			}
		} else {
			$ret .= '<input type="'.xssafe($arr['type']).'" class="form-control" id="'.xssafe($name).'" name="'.xssafe($name).'" value="'.xssafe(SanitizedRequestStr($name, $arr['default'])).'">';
			if (!empty($arr['desc'])) {
				$ret .= '<br />'.$arr['desc'];
			}
		}
		$ret .= '</div></div>';
	}
	return $ret;
}