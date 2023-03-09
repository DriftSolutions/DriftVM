<?php
/*
passwd.inc.php - part of DriftVM
License: GPLv3
Copyright 2023 Drift Solutions
*/

function GetCPUInfo() {
	$ret = cache1_get('sysinfo_cpu_info');
	if ($ret !== FALSE) {
		return $ret;
	}
	$ret = [
		'name' => 'Unknown CPU',
		'cores' => 1,
		'has_virt' => false,
	];

	if(is_file('/proc/cpuinfo')) {
		$tmp = file_get_contents('/proc/cpuinfo');
		$lines = explode("\n", $tmp);
		print_r($lines);
		$id = $rel = $code = '';
		$ret['cores'] = 0;
		foreach ($lines as $line) {
			$tmp = explode(':', $line);
			if (count($tmp) == 2) {
				print_r($tmp);
				$k = trim($tmp[0]);
				$v = trim($tmp[1]);
				if ($k == 'model name') {
					$ret['name'] = $v;
				} elseif ($k == 'processor') {
					$ret['cores']++;
				} else if ($k == 'flags') {
					$flags = explode(' ', $v);
					$ret['has_virt'] = (in_array('svm', $flags) || in_array('vmx', $flags));
				}
			}
		}
	}
	return $ret;
}

function GetCPUCount() {
	$cpu_count = cache1_get('sysinfo_cpu_count');
	if ($cpu_count !== FALSE && $cpu_count > 0) {
		return $cpu_count;
	}
	$cpu_count = 1;
	if(is_file('/proc/cpuinfo')) {
		$cpuinfo = file_get_contents('/proc/cpuinfo');
		preg_match_all('/^processor/m', $cpuinfo, $matches);
		$cpu_count = count($matches[0]);
		cache1_store('sysinfo_cpu_count', $cpu_count, 3600);
	}
	return $cpu_count;
}

function GetLoadAvg() {
	$cpu_count = GetCPUCount();

	$sys_getloadavg = sys_getloadavg();
	$sys_getloadavg[0] = $sys_getloadavg[0] / $cpu_count;
	$sys_getloadavg[1] = $sys_getloadavg[1] / $cpu_count;
	$sys_getloadavg[2] = $sys_getloadavg[2] / $cpu_count;

	return $sys_getloadavg;
}

function GetMemoryInfo() {
	$tmp = explode("\n", trim(shell_exec('free -b')));
	$arr = preg_split("/[\s]+/", $tmp[1]);
	$arr2 = preg_split("/[\s]+/", $tmp[2]);
	$ret = [
		'ram' => [
			'total' => $arr[1],
			'used' => $arr[2],
			'free' => $arr[3],
			'shared' => $arr[4],
			'cached' => $arr[5],
			'available' => $arr[6],
		],
		'swap' => [
			'total' => $arr2[1],
			'used' => $arr2[2],
			'free' => $arr2[3],
		],
	];
	return $ret;;
}

function GetDiskUsedPercent() {
	$total = $used = 0;
	foreach (GetDiskInfo() as $arr) {
		$total += $arr['size'];
		$used += $arr['used'];
	}
	if ($total <= 0) {
		return 0;
	}
	return round($used / $total, 2);
}

function GetDiskInfo() {
	$tmp = explode("\n", trim(shell_exec('df -B 1 -T')));
	unset($tmp[0]);// headers

	$ignore = ['tmpfs','devtmpfs'];

	$ret = [];
	foreach ($tmp as $arr) {
		$arr = preg_split("/[\s]+/", $arr);
		if (count($arr) < 7) { continue; }
		if (in_array($arr[1], $ignore)) { continue; }

		$ret[] = [
			'device' => $arr[0],
			'fstype' => $arr[1],
			'size' => $arr[2],
			'used' => $arr[3],
			'avail' => $arr[4],
			'used_percent' => round(rtrim($arr[5],'%') / 100, 2),
			'mountpoint' => $arr[6],
		];
	}
	return $ret;
}

function GetOSVersion() {
	$ret = cache1_get('sysinfo_os_version');
	if ($ret !== FALSE) {
		return $ret;
	}

	if (file_exists('/etc/lsb-release')) {
		$tmp = file_get_contents('/etc/lsb-release');
		$lines = preg_split("/[\s]+/", $tmp);
		$id = $rel = $code = '';
		foreach ($lines as $line) {
			$tmp = explode('=', $line);
			if (count($tmp) == 2) {
				$k = trim($tmp[0]);
				$v = trim($tmp[1]);
				if ($k == 'DISTRIB_ID') {
					$id = $v;
				} else if ($k == 'DISTRIB_RELEASE') {
					$rel= $v;
				} else if ($k == 'DISTRIB_CODENAME') {
					$code= $v;
				}
			}
		}
		if (!empty($id) && !empty($rel)) {
			$ret = $id.' '.$rel;
			if (!empty($code)) {
				$ret .= ' ('.$code.')';
			}
			cache1_store('sysinfo_os_version', $ret, 300);
			return $ret;
		}
	}
	if (file_exists('/etc/debian_version')) {
		$ret = 'Debian '.trim(file_get_contents('/etc/debian_version'));
		cache1_store('sysinfo_os_version', $ret, 300);
		return $ret;
	}
	return php_uname('s').' '.php_uname('r').' '.php_uname('v');
}

function GetOSInfo() {
	$ret = cache1_get('sysinfo_os_info');
	if ($ret !== FALSE) {
		return $ret;
	}

	$tmp = explode("\n", shell_exec('osinfo-query os'));
	unset($tmp[0]);// headers
	unset($tmp[1]);// headers

	$ret = [];
	foreach ($tmp as $line) {
		$arr = explode('|', $line);
		if (count($arr) != 4) { continue; }
		$key = trim($arr[0]);
		$disp = trim($arr[1]);
		if (!empty($key) && !empty($disp)) {
			$ret[$key] = $disp;
		}
	}

	if (count($ret)) {
		cache1_store('sysinfo_os_info', $ret, 3600);
	}
	return $ret;
}
