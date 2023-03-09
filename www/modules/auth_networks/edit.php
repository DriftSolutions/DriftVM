<?php
/*
Part of DriftVM
License: GPLv3
Copyright 2023 Drift Solutions
*/

$device = SanitizedRequestStr('dev');
if (empty($device)) {
	ShowMsgBox('Error', 'No device specified!');
	return;
}

$res = $db->query("SELECT * FROM `Networks` WHERE `Device`='".$db->escape($device)."'");
if ($db->num_rows($res) == 0) {
	ShowMsgBox('Error', 'Invalid device specified!');
	return;
}
$arr = $db->fetch_assoc($res);
$db->free_result($res);

$type = my_intval(SanitizedRequestStr('nettype', $arr['Type']));
$addr = SanitizedRequestStr('address', $arr['IP'].'/'.$arr['Netmask']);
$iface = SanitizedRequestStr('iface', $arr['Interface']);

if (count($_POST)) {
	if (csrf_verify('network-edit')) {
		if (is_valid_network_type($type)) {
			if (get_network_device($iface) !== FALSE) {
				$update = [
					'ID' => $arr['ID'],
					'Type' => $type,
					'Interface' => $iface,
				];
				if ($db->update('Networks', $update) === TRUE) {
					$cli = new jsonRPCClient($config['driftvmd_rpc'], $config['Debug']);
					try {
						if ($arr['NormalStatus'] > 0) {
							$cli->network_firewall_apply(['device' => $arr['Device']]);
						}
						ShowMsgBox('Success', 'Network updated!<br /><br />'.std_redirect('auth_networks','action=view&dev='.xssafe($arr['Device'])));
						return;
					} catch (Exception $e) {
						ShowMsgBox('Error', 'Error activating network changes: '.xssafe($e->getMessage()));
					}
				} else {
					ShowMsgBox('Error', 'Error updating network! (Is the device name already in use?)');
				}
			} else {
				ShowMsgBox('Error', 'Invalid listening interface selected!');
			}
		} else {
			ShowMsgBox('Error', 'Invalid network type selected!');
		}
	} else {
		ShowMsgBox('Error', 'CSRF check failed! Please try again...');
	}
}

print '<div class="container">';
OpenPanel('Update Network');
?>
<form action="network-edit" method="POST">
	<?php echo csrf_get_html('network-edit'); ?>
	<input type="hidden" name="dev" value="<?php echo xssafe($arr['Device']); ?>">
	<div class="mb-3 row">
		<label for="deviceName" class="col-sm-2 col-form-label">Device Name</label>
		<div class="col-sm-10">
			<input type="text" class="form-control-plaintext" id="deviceName" readonly value="<?php echo xssafe($arr['Device']); ?>">
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
 		<button type="submit" class="btn btn-primary mb-3">Update Network</button>
	</div>
</form>
<?php

ClosePanel();
print '</div>';//container
