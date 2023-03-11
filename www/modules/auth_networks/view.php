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
if ($sub == 'activate') {
	$update = [
		'ID' => $arr['ID'],
		'NormalStatus' => 1,
	];
	if ($db->update('Networks', $update) === TRUE) {
		$cli = new jsonRPCClient($config['driftvmd_rpc'], $config['Debug']);
		try {
			$cli->network_activate(['device' => $arr['Device']]);
			ShowMsgBox('Success', 'Network activated!');
		} catch (Exception $e) {
			ShowMsgBox('Error', 'Error activating network: '.xssafe($e->getMessage()));
		}
	} else {
		ShowMsgBox('Error', 'Error activating network! (Is the device name already in use?)');
	}
} else if ($sub == 'delete') {
	$res = $db->query("SELECT COUNT(*) AS `Count` FROM `Machines` WHERE `Network`='".$db->escape($device)."' LIMIT 1");
	$tmp = $db->fetch_assoc($res);
	$db->free_result($res);
	if ($tmp['Count'] == 0) {
		$update = [
			'ID' => $arr['ID'],
			'NormalStatus' => 0,
		];
		if ($db->update('Networks', $update) === TRUE) {
			$cli = new jsonRPCClient($config['driftvmd_rpc'], $config['Debug']);
			try {
				$cli->network_destroy(['device' => $arr['Device']]);
				ShowMsgBox('Success', 'Network destroyed!');
				return;
			} catch (Exception $e) {
				ShowMsgBox('Error', 'Error destroying network: '.xssafe($e->getMessage()));
			}
		} else {
			ShowMsgBox('Error', 'Error destroying network!');
		}
	} else {
		ShowMsgBox('Error', 'You can\'t destroy a network that still has machines!');
	}
} else if ($sub == 'firewall_apply') {
	$cli = new jsonRPCClient($config['driftvmd_rpc'], $config['Debug']);
	try {
		$cli->network_firewall_apply(['device' => $arr['Device']]);
		ShowMsgBox('Success', 'Firewall rules applied!');
	} catch (Exception $e) {
		ShowMsgBox('Error', 'Error activating network changes: '.xssafe($e->getMessage()));
	}
} else if ($sub == 'firewall_flush') {
	$cli = new jsonRPCClient($config['driftvmd_rpc'], $config['Debug']);
	try {
		$cli->network_firewall_flush(['device' => $arr['Device']]);
		ShowMsgBox('Success', 'Firewall rules flushed!');
	} catch (Exception $e) {
		ShowMsgBox('Error', 'Error activating network changes: '.xssafe($e->getMessage()));
	}
}

$nd = get_network_device($arr['Device']);

print '<div class="container">';
OpenPanel('Network Information: '.xssafe($arr['Device']).' <a type="button" class="btn btn-sm btn-primary float-end" href="network-view?dev='.xssafe($arr['Device']).'">Refresh</a>');

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
$grid->TD('Network Type:', 'class="text-right"');
$grid->TD(xssafe(GetNetworkType($arr['Type'])));
$grid->CloseRow();

$grid->OpenRow();
$grid->TD('Actions:', 'class="text-right"');
$str = '<a type="button" class="btn btn-primary" href="network-edit?dev='.xssafe($arr['Device']).'">Edit Network</a>';
if (!$up) {
	$str .= ' <a type="button" class="btn btn-success" href="network-view?dev='.xssafe($arr['Device']).'&sub=activate">Activate</a>';
}
$str .= ' <a type="button" class="btn btn-success" href="network-view?dev='.xssafe($arr['Device']).'&sub=firewall_apply">Apply Firewall Rules</a>';
$str .= ' <a type="button" class="btn btn-danger" href="network-view?dev='.xssafe($arr['Device']).'&sub=firewall_flush">Flush Firewall Rules</a>';
$str .= ' <a type="button" class="btn btn-danger" href="network-view?dev='.xssafe($arr['Device']).'&sub=delete" onClick="return confirm(\'Are you sure?\');">Delete</a>';
$grid->TD($str);
$grid->CloseRow();

$grid->CloseBody();
$grid->Close();

ClosePanel();
print '</div>';//container
