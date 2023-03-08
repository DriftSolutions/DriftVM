<?php
/*
Part of DriftVM
License: GPLv3
Copyright 2023 Drift Solutions
*/
require("header.inc.php");

// Do some defaults if needed
if (!HaveSettings()) {
	UpdateSetting('lxc_paths', '/var/lib/lxc/');
	UpdateSetting('lxc_templates', 'debian;ubuntu;centos;fedora');
	UpdateSetting('kvm_paths', '/var/lib/kvm/');
} else {
	CacheAllSettings();
}

$names = ['lxc_paths','lxc_templates','kvm_paths'];
$settings = [];
foreach ($names as $n) {
	$settings[$n] = SanitizedRequestStr($n, GetSetting($n));
}

function clean_multi_options($str, $is_dir = FALSE) {
		$tmp = explode(';', $str);
		$tmp2 = [];
		foreach ($tmp as $p) {
			$p = trim($p);
			if (empty($p)) { continue; }
			if ($is_dir && substr($p, -1) != '/') {
				$p .= '/';
			}
			$tmp2[] = $p;
		}
		return implode(';', $tmp2);
}

$error_msg = '';

if (count($_POST)) {
	if (csrf_verify('settings')) {
		$settings['lxc_paths'] = clean_multi_options($settings['lxc_paths'], TRUE);
		$settings['lxc_templates'] = clean_multi_options($settings['lxc_templates'], FALSE);
		$settings['kvm_paths'] = clean_multi_options($settings['kvm_paths'], TRUE);

		$all_ok = true;
		foreach ($names as $n) {
			if (empty($n)) {
				$error_msg = 'Setting "'.xssafe($n).'" cannot be blank!';
				$all_ok = false;
				break;
			}
		}

		if ($all_ok) {
			$db->query("BEGIN");
			foreach ($settings as $k => $v) {
				UpdateSetting($k, $v);
			}
			$db->query("COMMIT");
			ShowMsgBox('Success', 'Settings updated!');
		}
	} else {
		ShowMsgBox('Error', 'CSRF check failed! Please try again...');
	}
}

if (!empty($error_msg)) {
	ShowMsgBox('Error', $error_msg);
}

print '<div class="container">';
OpenPanel('Edit Settings');
?>
<form action="settings" method="POST">
	<?php echo csrf_get_html('settings'); ?>
	<div class="mb-3 row">
		<label for="lxcPaths" class="col-sm-2 col-form-label">LXC Path(s)</label>
		<div class="col-sm-10">
			<input type="text" class="form-control" id="lxcPaths" name="lxc_paths" value="<?php echo xssafe($settings['lxc_paths']); ?>">
			The path(s) to store your LXC containers under. Separate with a semicolon (;) for multiple paths.
		</div>
	</div>
	<div class="mb-3 row">
		<label for="lxcTemplates" class="col-sm-2 col-form-label">LXC Template(s)</label>
		<div class="col-sm-10">
			<input type="text" class="form-control" id="lxcTemplates" name="lxc_templates" value="<?php echo xssafe($settings['lxc_templates']); ?>">
			The LXC templates to offer during container creation. Separate with a semicolon (;) for multiple options.
		</div>
	</div>
	<div class="mb-3 row">
		<label for="kvmPaths" class="col-sm-2 col-form-label">KVM Path(s)</label>
		<div class="col-sm-10">
			<input type="text" class="form-control" id="kvmPaths" name="kvm_paths" value="<?php echo xssafe($settings['kvm_paths']); ?>">
			The path(s) to store your KVM machines under. Separate with a semicolon (;) for multiple paths.
		</div>
	</div>
	<div class="mb-3 text-center">
 		<button type="submit" class="btn btn-primary mb-3">Update Settings</button>
	</div>
</form>
<?php

ClosePanel();
print '</div>';//container

require("footer.inc.php");
