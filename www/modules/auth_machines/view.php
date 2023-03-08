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
			$arr['Status'] = MS_DELETING;
		} catch (Exception $e) {
			ShowMsgBox('Error', 'Error sending command: '.xssafe($e->getMessage()));
		}
	} else {
		ShowMsgBox('Error', 'Machine is not stopped!');
	}
}

print '<div class="container">';
OpenPanel('Machine Information: '.xssafe($arr['Name']));

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
$grid->TD(xssafe($arr['IP'].' on '.$arr['Network']));
$grid->CloseRow();

$grid->OpenRow();
$grid->TD('Type:', 'class="text-right"');
$grid->TD(xssafe($arr['Type']));
$grid->CloseRow();

$grid->OpenRow();
$grid->TD('Actions:', 'class="text-right"');
$str = '<a type="button" class="btn btn-primary" href="machine-view?name='.xssafe($arr['Name']).'">Edit Machine</a>';
if ($arr['Status'] == MS_RUNNING) {
	$str .= ' <a type="button" class="btn btn-danger" href="machine-view?name='.xssafe($arr['Name']).'&sub=stop">Stop</a>';
} elseif ($arr['Status'] == MS_STOPPED) {
	$str .= ' <a type="button" class="btn btn-success" href="machine-view?name='.xssafe($arr['Name']).'&sub=start">Start</a>';
}
if ($arr['Status'] == MS_STOPPED || $arr['Status'] == MS_ERROR_CREATING) {
	$str .= ' <a type="button" class="btn btn-danger" href="machine-view?name='.xssafe($arr['Name']).'&sub=delete" onClick="return confirm(\'Are you sure?\');">Delete</a>';
}
$grid->TD($str);
$grid->CloseRow();

$grid->CloseBody();
$grid->Close();

ClosePanel();
print '</div>';//container
