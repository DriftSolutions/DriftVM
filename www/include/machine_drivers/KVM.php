<?php
/*
driftvm.inc.php - part of DriftVM
License: GPLv3
Copyright 2023 Drift Solutions
*/

require_once('./include/sysinfo.inc.php');

class MachineDriverKVM extends MachineDriver {
	public function __construct() {
		$this->guid = '{2E9F6C5A-34AE-4456-8CDF-EA5F0805A4B7}';
		$this->driver_name = 'KVM Virtual Machines';
	}

	public function GetMachineType($short = true) {
		if ($short) {
			return 'KVM';
		}
		return 'KVM Machine';
	}

	public function GetCreateOptions() {
		$paths = explode(';', GetSetting('kvm_paths'));
		$path_opts = [];
		foreach ($paths as $path) {
			$path_opts[$path] = $path;
		}
		return [
			'path' => [
				'type' => 'select',
				'options' => $path_opts,
				'default' => '',
				'label' => 'Disk Image Directory',
				'desc' => '',
			],
			'cdrom' => [
				'type' => 'text',
				'value' => '',
				'default' => GetSetting('last_kvm_cdrom'),
				'label' => 'CD-ROM ISO Path',
				'desc' => '',
				'required' => true,
			],
			'os_type' => [
				'type' => 'select',
				'options' => GetOSInfo(),
				'default' => GetSetting('default_kvm_os'),
				'label' => 'Guest OS',
				'desc' => 'If there isn\'t an exact match, do the best you can.',
			],
			'memory' => [
				'type' => 'number',
				'min' => 512,
				'max' => 99999999,
				'step' => 1,
				'default' => 1024,
				'label' => 'RAM Size (MB)',
				'desc' => '',
			],
			'cpu_count' => [
				'type' => 'number',
				'min' => 1,
				'max' => GetCPUCount(),
				'step' => 1,
				'default' => 1,
				'label' => 'CPU Count',
				'desc' => '',
			],
			'disk_size' => [
				'type' => 'number',
				'min' => 1,
				'max' => 99999999,
				'step' => 1,
				'default' => 25,
				'label' => 'Disk Image Size (GB)',
				'desc' => '',
			],
		];
	}

	public function ValidateCreateOptions(&$opts) {
		$paths = explode(';', GetSetting('kvm_paths'));
		if (!in_array($opts['path'], $paths)) {
			return "Invalid container path selected!";
		}
		if (empty($opts['cdrom']) || !file_exists($opts['cdrom'])) {
			return "CD-ROM file does not exist!";
		}
		UpdateSetting('last_kvm_cdrom', $opts['cdrom']);
		if (!in_array($opts['os_type'], array_keys(GetOSInfo()))) {
			return "Invalid KVM OS type selected!";
		}

		$opts['memory'] = my_intval($opts['memory']);
		$opts['cpu_count'] = my_intval($opts['cpu_count']);
		$opts['disk_size'] = my_intval($opts['disk_size']);

		if ($opts['memory'] < 512) {
			return "Invalid memory size!";
		}
		if ($opts['cpu_count'] < 1 || $opts['cpu_count'] > GetCPUCount()) {
			return "Invalid number of CPUs!";
		}
		if ($opts['disk_size'] < 1) {
			return "Invalid disk image size!";
		}

		return TRUE;
	}
};

if (HaveExecutable('virt-install')) {
	RegisterMachineDriver(new MachineDriverKVM());
}
