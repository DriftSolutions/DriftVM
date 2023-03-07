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

$sub = SanitizedRequestStr('sub');
if ($sub == 'deactivate' || $sub == 'activate') {
	$ns = ($sub == 'activate') ? 1 : 0;
	$update = [
		'ID' => $arr['ID'],
		'NormalStatus' => $ns,
	];
	if ($db->update('Networks', $update) === TRUE) {
		$cli = new jsonRPCClient($config['driftvmd_rpc'], $config['Debug']);
		try {
			if ($ns) {
				$cli->network_activate(['device' => $arr['Device']]);
			} else {
				$cli->network_deactivate(['device' => $arr['Device']]);
			}
			ShowMsgBox('Success', 'Network updated!');
		} catch (Exception $e) {
			ShowMsgBox('Error', 'Error activating network changes: '.xssafe($e->getMessage()));
		}
	} else {
		ShowMsgBox('Error', 'Error updating network! (Is the device name already in use?)');
	}
}

$nd = get_network_device($arr['Device']);
print_r($nd);

print '<div class="container">';
OpenPanel('Network Information: '.xssafe($arr['Device']));

$grid->Open();
$grid->OpenBody();

$grid->OpenRow();
$grid->TD('Network Status:', 'class="text-right"');
$up = false;
if ($nd !== FALSE) {
	$up = $nd['up'];
	$str = iif($up, 'UP', 'DOWN');
} else {
	$str = 'DOWN';
}
$grid->TD($str);
$grid->CloseRow();

$grid->OpenRow();
$grid->TD('Subnet IP/Mask:', 'class="text-right"');
$grid->TD(xssafe($arr['IP'].'/'.$arr['Netmask']));
$grid->CloseRow();

$grid->OpenRow();
$grid->TD('Actions:', 'class="text-right"');
$str = '<a type="button" class="btn btn-primary" href="network-edit?dev='.xssafe($arr['Device']).'">Edit Network</a>';
if ($up) {
	$str .= ' <a type="button" class="btn btn-danger" href="network-view?dev='.xssafe($arr['Device']).'&sub=deactivate">Deactivate</a>';
} else {
	$str .= ' <a type="button" class="btn btn-success" href="network-view?dev='.xssafe($arr['Device']).'&sub=activate">Activate</a>';
}
$grid->TD($str);
$grid->CloseRow();

$grid->CloseBody();
$grid->Close();

ClosePanel();
print '</div>';//container
