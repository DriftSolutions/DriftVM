<?php
/*
Part of DriftVM
License: GPLv3
Copyright 2023 Drift Solutions
*/

$type = my_intval(SanitizedRequestStr('nettype'));
$iface = SanitizedRequestStr('iface', GetSetting('default_listen_iface'));


if (count($_POST)) {
	if (csrf_verify('network-create')) {
		$devname = SanitizedRequestStr('device');
		$addr = SanitizedRequestStr('address');
		if (is_valid_network_type($type)) {
			if (!empty($devname)) {
				if (!array_key_exists($devname, net_get_interfaces())) {
					if (!empty($addr)) {
						$ip = '';
						$mask = 0;
						$iptmp = parse_ip_mask($addr, $ip, $mask);
						if ($iptmp !== FALSE) {
							if (get_network_device($iface) !== FALSE) {
								$insert = [
									'Device' => $devname,
									'IP' => $ip,
									'Netmask' => $mask,
									'Type' => $type,
									'Interface' => $iface,
								];
								if ($db->insert('Networks', $insert) === TRUE) {
									ShowMsgBox('Success', 'Network created!<br /><br />'.std_redirect('auth_networks','action=view&dev='.xssafe($devname)));
									return;
								} else {
									ShowMsgBox('Error', 'Error creating network! (Is the device name already in use?)');
								}
							} else {
								ShowMsgBox('Error', 'Invalid listening interface selected!');
							}
						} else {
							ShowMsgBox('Error', 'That is not a valid subnet IP!');
						}
					} else {
						ShowMsgBox('Error', 'The subnet IP cannot be empty!');
					}
				} else {
					ShowMsgBox('Error', 'The device name is already in use!');
				}
			} else {
				ShowMsgBox('Error', 'The device name cannot be empty!');
			}
		} else {
			ShowMsgBox('Error', 'Invalid network type selected!');
		}
	} else {
		ShowMsgBox('Error', 'CSRF check failed! Please try again...');
	}
}

print '<div class="container">';
OpenPanel('Create Network');
?>
<form action="network-create" method="POST">
	<?php echo csrf_get_html('network-create'); ?>
	<div class="mb-3 row">
		<label for="deviceName" class="col-sm-2 col-form-label">Device Name</label>
		<div class="col-sm-10">
			<input type="text" class="form-control" id="deviceName" name="device" value="<?php echo xssafe(SanitizedRequestStr('device')); ?>">
			Unique device name, for example: dvmbr0 or lxcbr0
		</div>
	</div>
	<div class="mb-3 row">
		<label for="address" class="col-sm-2 col-form-label">Subnet IP/Mask</label>
		<div class="col-sm-10">
			<input type="text" class="form-control" id="address" name="address" value="<?php echo xssafe(SanitizedRequestStr('address')); ?>">
			Example: 192.168.3.0/24 or 10.3.0.0/16<br />
			This cannot be an IP range you are already using elsewhere.
		</div>
	</div>
	<div class="mb-3 row">
		<label for="address" class="col-sm-2 col-form-label">Network Type</label>
		<div class="col-sm-10">
			<div class="form-check">
			  <input class="form-check-input" type="radio" name="nettype" value="0" id="nettype0"<?php echo iif($type == 0,' checked',''); ?>>
			  <label class="form-check-label" for="nettype0">
			    Routed (can access other networks and the internet)
			  </label>
			</div>
			<div class="form-check">
			  <input class="form-check-input" type="radio" name="nettype" value="1" id="nettype1"<?php echo iif($type == 1,' checked',''); ?>>
			  <label class="form-check-label" for="nettype1">
			    Isolated (can only access other VMs and containers on the same network)
			  </label>
			</div>
		</div>
	</div>
	<div class="mb-3 row">
		<label for="iface" class="col-sm-2 col-form-label">Listening Interface</label>
		<div class="col-sm-10">
			<select class="form-control" id="iface" name="iface">
			<?php
				$networks = get_network_interfaces();
				foreach ($networks as $dev => $arr) {
					print '<option value="'.xssafe($dev).'"'.iif($dev == $iface,' selected','').'>'.xssafe($dev.' ('.$arr['address'].')').'</option>';
				}
			?>
			</select>
			Interface to listen for port forwarding on.
		</div>
	</div>
	<div class="mb-3 text-center">
 		<button type="submit" class="btn btn-primary mb-3">Create Network</button>
	</div>
</form>
<?php

ClosePanel();
print '</div>';//container
