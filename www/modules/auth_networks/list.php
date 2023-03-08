<?php
/*
Part of DriftVM
License: GPLv3
Copyright 2023 Drift Solutions
*/

print '<div class="container">';
OpenPanel('Networks');

$grid->Open();

$grid->OpenHead();
$grid->OpenRow();
$grid->Header('Network');
$grid->Header('Subnet');
$grid->Header('Machines');
$grid->CloseRow();
$grid->CloseHead();

$grid->OpenBody();

$counts = [];
$res = $db->query("SELECT `Network`,COUNT(*) AS `Count` FROM `Machines` GROUP BY `Network`");
while ($arr = $db->fetch_assoc($res)) {
	$counts[$arr['Network']] = $arr['Count'];
}
$db->free_result($res);

$res = $db->query("SELECT `Device`,`IP`,`Netmask` FROM `Networks`");
while ($arr = $db->fetch_assoc($res)) {
	$grid->OpenRow();
	$grid->TD('<b><a href="network-view?dev='.xssafe($arr['Device']).'">'.xssafe($arr['Device']).'</a></b>', 'class="text-center"');
	$grid->TD(xssafe($arr['IP'].'/'.$arr['Netmask']));
	$count = isset($counts[$arr['Device']]) ? $counts[$arr['Device']] : 0;
	$grid->TD('<b><a href="machines?net='.xssafe($arr['Device']).'">'.xssafe(number_format($count)).'</a></b>', 'class="text-center"');
	$grid->CloseRow();
}

if ($db->num_rows($res) == 0) {
	$grid->OpenRow();
	$grid->TD('- No Networks Found -', 'class="text-center" colspan=3');
	$grid->CloseRow();
}
$db->free_result($res);

$grid->CloseBody();
$grid->Close();

ClosePanel();
print '</div>';//container
