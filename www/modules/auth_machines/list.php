<?php
/*
Part of DriftVM
License: GPLv3
Copyright 2023 Drift Solutions
*/

print '<div class="container">';
OpenPanel('Machines');

$grid->Open();

$grid->OpenHead();
$grid->OpenRow();
$grid->Header('Machine');
$grid->Header('Status');
$grid->Header('Actions');
$grid->CloseRow();
$grid->CloseHead();

$grid->OpenBody();

$devices = array();
$net = SanitizedRequestStr('net');
if (!empty($net)) {
	$res = $db->query("SELECT `Name`,`Status` FROM `Machines` WHERE `Network`='".$db->escape($net)."'");
} else {
	$res = $db->query("SELECT `Name`,`Status` FROM `Machines`");
}
while ($arr = $db->fetch_assoc($res)) {
	$grid->OpenRow();
	$grid->TD('<b><a href="machine-view?name='.xssafe($arr['Name']).'">'.xssafe($arr['Name']).'</a></b>', 'class="text-center"');
	$grid->TD(GetMachineStatusString($arr));
	$str = '<a type="button" class="btn btn-primary" href="machine-view?name='.xssafe($arr['Name']).'">View Machine</a>';
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
}

if ($db->num_rows($res) == 0) {
	$grid->OpenRow();
	$grid->TD('- No Machines Found -', 'class="text-center" colspan=3');
	$grid->CloseRow();
}
$db->free_result($res);

$grid->CloseBody();
$grid->Close();

ClosePanel();
print '</div>';//container
