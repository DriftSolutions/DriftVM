<?php
/*
Part of DriftVM
License: GPLv3
Copyright 2023 Drift Solutions
*/

$name = SanitizedRequestStr('name');
if (empty($name)) {
	ShowMsgBox('Error', 'No name specified!');
	return;
}

$res = $db->query("SELECT * FROM `Machines` WHERE `Name`='".$db->escape($name)."'");
if ($db->num_rows($res) == 0) {
	ShowMsgBox('Error', 'Invalid machine specified!');
	return;
}
$arr = $db->fetch_assoc($res);
$db->free_result($res);

$sub = SanitizedRequestStr('sub');
if ($sub == 'stop') {
	if ($arr['Status'] == MS_RUNNING) {
		$cli = new jsonRPCClient($config['driftvmd_rpc'], $config['Debug']);
		try {
			$cli->machine_stop(['name' => $arr['Name']]);
			ShowMsgBox('Success', 'Machine stopping...');
			$arr['Status'] = MS_STOPPING;
		} catch (Exception $e) {
			ShowMsgBox('Error', 'Error sending command: '.xssafe($e->getMessage()));
		}
	} else {
		ShowMsgBox('Error', 'Machine is not running!');
	}
} elseif ($sub == 'start') {
	if ($arr['Status'] == MS_STOPPED) {
		$cli = new jsonRPCClient($config['driftvmd_rpc'], $config['Debug']);
		try {
			$cli->machine_start(['name' => $arr['Name']]);
			ShowMsgBox('Success', 'Machine starting...');
			$arr['Status'] = MS_STARTING;
		} catch (Exception $e) {
			ShowMsgBox('Error', 'Error sending command: '.xssafe($e->getMessage()));
		}
	} else {
		ShowMsgBox('Error', 'Machine is not stopped!');
	}
} elseif ($sub == 'delete') {
	if ($arr['Status'] == MS_STOPPED || $arr['Status'] == MS_ERROR_CREATING) {
		$cli = new jsonRPCClient($config['driftvmd_rpc'], $config['Debug']);
		try {
			$cli->machine_delete(['name' => $arr['Name']]);

			ShowMsgBox('Success', 'Deleting machine...');
			$db->query("DELETE FROM `PortForwards` WHERE `MachineID`='".$db->escape($arr['ID'])."'");
			$arr['Status'] = MS_DELETING;
		} catch (Exception $e) {
			ShowMsgBox('Error', 'Error sending command: '.xssafe($e->getMessage()));
		}
	} else {
		ShowMsgBox('Error', 'Machine is not stopped!');
	}
} else if ($sub == 'add_port_forward') {
	if (csrf_verify('machine-view-portforward')) {
		$intport = my_intval(SanitizedRequestStr('intport'));
		$extport = my_intval(SanitizedRequestStr('extport'));
		$type = my_intval(SanitizedRequestStr('type'));
		$comment = SanitizedRequestStr('comment');
		if ($intport >= 1 && $intport <= 65535) {
			if ($extport >= 1 && $extport <= 65535) {
				if ($type >= 0 && $type <= 2) {
					$insert = [
						'MachineID' => $arr['ID'],
						'Type' => $type,
						'InternalPort' => $intport,
						'ExternalPort' => $extport,
						'Comment' => $comment,
					];
					if ($db->insert('PortForwards', $insert) === TRUE) {
						ShowMsgBox('Success', 'Port forward added!');
					} else {
						ShowMsgBox('Error', 'Error creating network! (Is the device name already in use?)');
					}
				} else {
					ShowMsgBox('Error', 'Invalid protocol type!');
				}
			} else {
				ShowMsgBox('Error', 'Invalid external port!');
			}
		} else {
			ShowMsgBox('Error', 'Invalid internal port!');
		}
	} else {
		ShowMsgBox('Error', 'CSRF check failed! Please try again...');
	}
} else if ($sub == 'del_port_forward') {
	$id = my_intval(SanitizedRequestStr('id'));
	if ($id > 0) {
		if ($db->query("DELETE FROM `PortForwards` WHERE `ID`='".$db->escape($id)."'") === TRUE) {
			ShowMsgBox('Success', 'Port forward deleted!');
		}
	}
}

print '<div class="container">';
OpenPanel('Machine Information: '.xssafe($arr['Name']).' <a type="button" class="btn btn-sm btn-primary float-end" href="machine-view?name='.xssafe($arr['Name']).'">Refresh</a>');

$grid->Open();
$grid->OpenBody();

$grid->OpenRow();
$grid->TD('Status:', 'class="text-right"');
$grid->TD(GetMachineStatusString($arr));
$grid->CloseRow();

if (!empty($arr['LastError'])) {
	$grid->OpenRow();
	$grid->TD('Last Error:', 'class="text-right"');
	$grid->TD(xssafe($arr['LastError']));
	$grid->CloseRow();
}

$grid->OpenRow();
$grid->TD('IP Address:', 'class="text-right"');
$grid->TD(xssafe($arr['IP']).' on <a href="network-view?dev='.xssafe($arr['Network']).'">'.xssafe($arr['Network']).'</a>');
$grid->CloseRow();

$grid->OpenRow();
$grid->TD('Type:', 'class="text-right"');
$d = GetMachineDriver($arr['Type']);
if ($d !== FALSE) {
	$str = $d->GetMachineType(false);
} else {
	$str = $arr['Type'];
}
$grid->TD(xssafe($str));
$grid->CloseRow();

if (!empty($arr['Extra'])) {
	$tmp = json_decode($arr['Extra'], TRUE);
	if (is_array($tmp) && count($tmp)) {
		foreach ($tmp as $k => $v) {
			$grid->OpenRow();
			$grid->TD(xssafe($k.':'), 'class="text-right"');
			if ($k == 'VNC Display') {
				$host = empty($_SERVER['HTTP_HOST']) ? $_SERVER['SERVER_ADDR'] : $_SERVER['HTTP_HOST'];
				$grid->TD('<a href="machine-vnc?name='.xssafe($arr['Name']).'">'.xssafe($host.$v).'</a>');
			} else {
				$grid->TD(xssafe($v));
			}
			$grid->CloseRow();
		}
	}
}

if (!empty($arr['CreateOptions'])) {
	$tmp = json_decode($arr['CreateOptions'], TRUE);
	if (is_array($tmp) && count($tmp)) {
		$grid->OpenRow();
		$grid->TD('Creation Options:', 'class="text-right"');
		$str = '';
		foreach ($tmp as $k => $v) {
			$str .= xssafe($k.' = '.$v).'<br />';
		}
		$grid->TD($str);
		$grid->CloseRow();
	}
}

$grid->OpenRow();
$grid->TD('Actions:', 'class="text-right"');
$str = '';
if ($arr['Status'] == MS_RUNNING) {
	$str .= ' <a type="button" class="btn btn-danger" href="machine-view?name='.xssafe($arr['Name']).'&sub=stop">Stop</a>';
} elseif ($arr['Status'] == MS_STOPPED) {
	$str .= ' <a type="button" class="btn btn-success" href="machine-view?name='.xssafe($arr['Name']).'&sub=start">Start</a>';
}
if ($arr['Status'] == MS_STOPPED || $arr['Status'] == MS_ERROR_CREATING) {
	$str .= ' <a type="button" class="btn btn-danger" href="machine-view?name='.xssafe($arr['Name']).'&sub=delete" onClick="return confirm(\'Are you sure?\');">Delete</a>';
}
if (empty($str)) {
	$str = 'N/A';
}
$grid->TD($str);
$grid->CloseRow();

$grid->CloseBody();
$grid->Close();

if ($arr['Type'] == GUID_KVM) {
?>
<div class="alert alert-secondary" role="alert">
	Note for the Stop button to work you need the QEMU Guest Agent installed in your VM. On Debian/Ubuntu this can be accomplished with: apt install qemu-guest-agent
</div>
<?php
}

ClosePanel();
print '</div>';//container

$net = GetNetwork($arr['Network']);
if ($net !== FALSE && $net['Type'] == NT_ROUTED) {
	print '<div class="container mt-3">';
	OpenPanel('Port Forwards'.'<a type="button" class="btn btn-sm btn-success float-end" href="network-view?dev='.xssafe($arr['Network']).'&sub=firewall_apply">Apply Changes</a>');

	$grid->Open();

	$grid->OpenHead();
	$grid->OpenRow();
	$grid->Header('Internal (VM) Port');
	$grid->Header('External (Host) Port');
	$grid->Header('Protocol');
	$grid->Header('Comment');
	$grid->Header('Actions');
	$grid->CloseRow();
	$grid->CloseHead();

	$grid->OpenBody();

	$res = $db->query("SELECT * FROM `PortForwards` WHERE `MachineID`='".$db->escape($arr['ID'])."'");
	while ($port = $db->fetch_assoc($res)) {
		$grid->OpenRow();
		$grid->TD(xssafe($port['InternalPort']), 'class="text-center"');
		$grid->TD(xssafe($port['ExternalPort']), 'class="text-center"');
		switch (my_intval($port['Type'])) {
			case 0:
				$str = 'TCP';
				break;
			case 1:
				$str = 'UDP';
				break;
			case 2:
				$str = 'TCP &amp; UDP';
				break;
			default:
				$str = 'Unknown';
				break;
		}
		$grid->TD($str, 'class="text-center"');
		$grid->TD(xssafe($port['Comment']));

		$str = '<a type="button" class="btn btn-danger" href="machine-view?name='.xssafe($arr['Name']).'&sub=del_port_forward&id='.xssafe($port['ID']).'">Delete</a>';
		$grid->TD($str, 'class="text-center"');
		$grid->CloseRow();
	}
	$db->free_result($res);

	print '<form action="machine-view" method="POST">';
	print '<input type="hidden" name="name" value="'.xssafe($arr['Name']).'">';
	print '<input type="hidden" name="sub" value="add_port_forward">';
	print csrf_get_html('machine-view-portforward');
	$grid->OpenRow();
	$grid->TD('<input type="number" class="form-control" min=1 max=65535 step=1 name="intport" value="'.xssafe(my_intval(SanitizedRequestStr('intport','1024'))).'" required>', 'class="text-center"');
	$grid->TD('<input type="number" class="form-control" min=1 max=65535 step=1 name="extport" value="'.xssafe(my_intval(SanitizedRequestStr('extport','1024'))).'" required>', 'class="text-center"');
	$str  = '<select name="type" class="form-control">';
	$type = my_intval(SanitizedRequestStr('type'));
	$str .= '<option value=0'.iif($type == 0,' selected', '').'>TCP</option>';
	$str .= '<option value=1'.iif($type == 1,' selected', '').'>UDP</option>';
	$str .= '<option value=2'.iif($type == 2,' selected', '').'>Both</option>';
	$str .= '</select>';
	$grid->TD($str, 'class="text-center"');
	$grid->TD('<input type="text" class="form-control" name="comment" value="'.xssafe(SanitizedRequestStr('comment')).'">', 'class="text-center"');
	$grid->TD('<button type="submit" class="btn btn-primary">Add</button>', 'class="text-center"');
	$grid->CloseRow();
	print '</form>';

	$grid->CloseBody();
	$grid->Close();

	ClosePanel();
	print '</div>';//container
}