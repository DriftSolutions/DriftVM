<?php
/*
driftvm.inc.php - part of DriftVM
License: GPLv3
Copyright 2023 Drift Solutions
*/

class MachineDriverLXC extends MachineDriver {
	public function __construct() {
		$this->guid = '{46634A47-CB89-4318-B98D-A691138C256B}';
		$this->driver_name = 'LXC Containers';
	}

	public function GetMachineType($short = true) {
		if ($short) {
			return 'LXC';
		}
		return 'LXC Container';
	}

	public function GetCreateOptions() {
		$paths = explode(';', GetSetting('lxc_paths'));
		$path_opts = [];
		foreach ($paths as $path) {
			$path_opts[$path] = $path;
		}
		$templates = explode(';', GetSetting('lxc_templates'));
		$tpl_opts = [];
		foreach ($templates as $tpl) {
			$tpl_opts[$tpl] = $tpl;
		}
		return [
			'path' => [
				'type' => 'select',
				'options' => $path_opts,
				'default' => '',
				'label' => 'Container Directory',
				'desc' => '',
			],
			'lxc_template' => [
				'type' => 'select',
				'options' => $tpl_opts,
				'default' => '',
				'label' => 'LXC Template',
				'desc' => '',
			],
			'use_image' => [
				'type' => 'checkbox',
				'value' => 1,
				'default' => 0,
				'label' => 'Use Disk Image',
				'desc' => 'Check to use a disk image of the below size, otherwise it will use a directory in the container\'s directory',
			],
			'image_size' => [
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
		$paths = explode(';', GetSetting('lxc_paths'));
		if (!in_array($opts['path'], $paths)) {
			return "Invalid container path selected!";
		}
		$templates = explode(';', GetSetting('lxc_templates'));
		if (!in_array($opts['lxc_template'], $templates)) {
			return "Invalid LXC template selected!";
		}

		$opts['use_image'] = my_intval($opts['use_image']);
		$opts['image_size'] = my_intval($opts['image_size']);

		if ($opts['use_image'] > 0) {
			if ($opts['image_size'] <= 0) {
				return "Invalid disk image size!";
			}
		}

		return TRUE;
	}

	public function Create($options) { return false;	}
	public function Destroy($machine) {	return false; }

	public function Start($machine) { return false;	}
	public function Stop($machine) { return false;	}
};

RegisterMachineDriver(new MachineDriverLXC());
