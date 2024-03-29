<?php
/*
Part of DriftVM
License: GPLv3
Copyright 2023 Drift Solutions
*/
require_once('./include/sysinfo.inc.php');
require("header.inc.php");

// Do some defaults if needed
if (!HaveSettings()) {
	UpdateSetting('lxc_paths', '/var/lib/lxc/');
	UpdateSetting('lxc_templates', 'debian;ubuntu;centos');
	UpdateSetting('kvm_paths', '/var/lib/kvm/');
} else {
	CacheAllSettings();
}

$names = ['default_listen_iface','lxc_paths','lxc_templates','default_lxc_postinst','kvm_paths','default_kvm_os','bind_ip','bind_pattern'];
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

		if (get_network_device($settings['default_listen_iface']) === FALSE) {
			$error_msg = "Invalid listening interface selected!";
			$all_ok = false;
		}
		if (!in_array($settings['default_kvm_os'], array_keys(GetOSInfo()))) {
			$error_msg = "Invalid KVM OS type selected!";
			$all_ok = false;
		}
		if (!empty($settings['bind_ip']) && !is_ip($settings['bind_ip'])) {
			$error_msg = "Invalid IP for your Bind server!";
			$all_ok = false;
		}
		if (!empty($settings['bind_pattern']) && strstr($settings['bind_pattern'], '%name%') === FALSE) {
			$error_msg = "There is no %name% in your Bind hostname pattern!";
			$all_ok = false;
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
<ul class="nav nav-tabs mb-3" id="myTab" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="home-tab" data-bs-toggle="tab" data-bs-target="#home-tab-pane" type="button" role="tab" aria-controls="home-tab-pane" aria-selected="true">Main</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="lxc-tab" data-bs-toggle="tab" data-bs-target="#lxc-tab-pane" type="button" role="tab" aria-controls="lxc-tab-pane" aria-selected="false">LXC</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="kvm-tab" data-bs-toggle="tab" data-bs-target="#kvm-tab-pane" type="button" role="tab" aria-controls="kvm-tab-pane" aria-selected="false">KVM</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="bind-tab" data-bs-toggle="tab" data-bs-target="#bind-tab-pane" type="button" role="tab" aria-controls="bind-tab-pane" aria-selected="false">Bind</button>
  </li>
</ul>
<form action="settings" method="POST">
	<?php echo csrf_get_html('settings'); ?>
	<div class="tab-content" id="myTabContent">
  	<div class="tab-pane fade show active" id="home-tab-pane" role="tabpanel" aria-labelledby="home-tab" tabindex="0">
			<div class="mb-3 row">
				<label for="iface" class="col-sm-2 col-form-label">Listening Interface</label>
				<div class="col-sm-10">
					<select class="form-control" id="iface" name="default_listen_iface">
					<?php
						$networks = get_network_interfaces();
						foreach ($networks as $dev => $arr) {
							print '<option value="'.xssafe($dev).'"'.iif($dev == $settings['default_listen_iface'],' selected','').'>'.xssafe($dev.' ('.$arr['address'].')').'</option>';
						}
					?>
					</select>
					Default interface to listen for port forwarding on.
				</div>
			</div>
		</div>
  	<div class="tab-pane fade" id="lxc-tab-pane" role="tabpanel" aria-labelledby="lxc-tab" tabindex="0">
			<div class="mb-3 row">
				<label for="lxcPaths" class="col-sm-2 col-form-label">LXC Path(s)</label>
				<div class="col-sm-10">
					<input type="text" class="form-control" id="lxcPaths" name="lxc_paths" value="<?php echo xssafe($settings['lxc_paths']); ?>" required>
					The path(s) to store your LXC containers under. Separate with a semicolon (;) for multiple paths.
				</div>
			</div>
			<div class="mb-3 row">
				<label for="lxcTemplates" class="col-sm-2 col-form-label">LXC Template(s)</label>
				<div class="col-sm-10">
					<input type="text" class="form-control" id="lxcTemplates" name="lxc_templates" value="<?php echo xssafe($settings['lxc_templates']); ?>" required>
					The LXC templates to offer during container creation. Separate with a semicolon (;) for multiple options.
				</div>
			</div>
			<div class="mb-3 row">
				<label for="default_lxc_postinst" class="col-sm-2 col-form-label">Default LXC Post-Install Script</label>
				<div class="col-sm-10">
					<input type="text" class="form-control" id="default_lxc_postinst" name="default_lxc_postinst" value="<?php echo xssafe($settings['default_lxc_postinst']); ?>">
					Path to a default post-install script to use for LXC machines (optional.) It will be copied into the VM as /root/postinst and executed.
				</div>
			</div>
		</div>
  	<div class="tab-pane fade" id="kvm-tab-pane" role="tabpanel" aria-labelledby="kvm-tab" tabindex="0">
			<div class="mb-3 row">
				<label for="kvmPaths" class="col-sm-2 col-form-label">KVM Path(s)</label>
				<div class="col-sm-10">
					<input type="text" class="form-control" id="kvmPaths" name="kvm_paths" value="<?php echo xssafe($settings['kvm_paths']); ?>" required>
					The path(s) to store your KVM disk images under. Separate with a semicolon (;) for multiple paths.
				</div>
			</div>
			<div class="mb-3 row">
				<label for="kvm_os" class="col-sm-2 col-form-label">Default KVM OS</label>
				<div class="col-sm-10">
					<select class="form-control" id="kvm_os" name="default_kvm_os">
					<?php
						$types = GetOSInfo();
						foreach ($types as $dev => $disp) {
							print '<option value="'.xssafe($dev).'"'.iif($dev == $settings['default_kvm_os'],' selected','').'>'.xssafe($disp).'</option>';
						}
					?>
					</select>
				</div>
			</div>
		</div>
  	<div class="tab-pane fade" id="bind-tab-pane" role="tabpanel" aria-labelledby="bind-tab" tabindex="0">
			<div class="mb-3 row">
				<label for="bind_ip" class="col-sm-2 col-form-label">Bind Server IP</label>
				<div class="col-sm-10">
					<input type="text" class="form-control" id="bind_ip" name="bind_ip" value="<?php echo xssafe($settings['bind_ip']); ?>">
					The IP of your Bind zone master server.
				</div>
			</div>
			<div class="mb-3 row">
				<label for="bind_pattern" class="col-sm-2 col-form-label">Bind Hostname Pattern</label>
				<div class="col-sm-10">
					<input type="text" class="form-control" id="bind_pattern" name="bind_pattern" value="<?php echo xssafe($settings['bind_pattern']); ?>">
					The hostname pattern for your dynamic A records. %name% will be replaced with your machine names, for example: <b>%name%.yourdomain.com</b>
				</div>
			</div>
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
