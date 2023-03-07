<?php
/*
Part of DriftVM
License: GPLv3
Copyright 2023 Drift Solutions
*/
require("header.inc.php");
require_once("include/sysinfo.inc.php");

print '<div class="container">';
OpenPanel('System Information');
?>
<div class="row">
	<div class="col text-center">
		CPU Load<br />
		<?php
		$tmp = GetLoadAvg();
		print sprintf('%.1f%%', round($tmp[0] * 100, 1));
		//print_r($tmp);
		?>
	</div>
	<div class="col text-center">
		Memory Used<br />
		<?php
		$mi = GetMemoryInfo();
		print sprintf('%.1f%%', round(($mi['ram']['used']/$mi['ram']['total']) * 100, 1));
		?>
	</div>
	<div class="col text-center">
		Disk Space Used<br />
		<?php
		$tmp = GetDiskUsedPercent();
		print sprintf('%.1f%%', round($tmp * 100, 1));
		?>
	</div>
</div>
<?php

function AddInfoLine($s1,$s2,$s3,$s4) {
	global $grid;
	$grid->OpenRow();
	$grid->TD('<b>'.$s1.':</b>', 'class="text-right"');
	$grid->TD($s2);
	$grid->TD('<b>'.$s3.':</b>', 'class="text-right"');
	$grid->TD($s4);
	$grid->CloseRow();
}

$grid->Open();
$grid->OpenBody();

$host = gethostname();
if (empty($host)) {
	$host = 'Unknown';
}
AddInfoLine('Host (IP)', xssafe($host.' ('.gethostbyname($host).')'), 'DriftVM Version', DRIFTVM_VERSION);
AddInfoLine('Operating System', xssafe(GetOSVersion()), 'Kernel/Arch', xssafe(php_uname('s').' '.php_uname('r').' '.php_uname('m')));
$uptime = trim(shell_exec('uptime -p'));
AddInfoLine('System Uptime', xssafe($uptime), 'Memory', xssafe(bytes($mi['ram']['total']).' total, '.bytes($mi['ram']['free']).' free, '.bytes($mi['ram']['cached']).' cached'));

$grid->CloseBody();
$grid->Close();

ClosePanel();
print '</div>';//container

print '<div class="container mt-3">';
OpenPanel('Active Network Interfaces');

$grid->Open();
$grid->OpenBody();

$grid->OpenHead();
$grid->OpenRow();
$grid->Header('Device');
$grid->Header('Address(es)');
$grid->Header('Status');
$grid->CloseRow();
$grid->CloseHead();

$grid->OpenBody();

$devices = array();
$res = $db->query("SELECT `Device` FROM `Networks`");
while ($arr = $db->fetch_assoc($res)) {
	$devices[] = $arr['Device'];
}
$db->free_result($res);
$networks = net_get_interfaces();
$wanted_families = [2,10]; // 2 = IPv4, 10 = IPv6
foreach ($networks as $dev => $arr) {
	if (!isset($arr['unicast'])) { continue; }
	$addrs = [];
	foreach ($arr['unicast'] as $net) {
		if (in_array($net['family'], $wanted_families) && !empty($net['address']) && !empty($net['netmask'])) {
			$addrs[] = xssafe($net['address'].' / '.$net['netmask']);
		}
	}

	$grid->OpenRow();
	if (in_array($dev, $devices)) {
		$grid->TD('<b><a href="network-edit?dev='.xssafe($dev).'">'.xssafe($dev).'</a></b>', 'class="text-center"');
	} else {
		$grid->TD('<b>'.xssafe($dev).'</b>', 'class="text-center"');
	}
	$grid->TD(implode('<br />', $addrs));
	$grid->TD(iif($arr['up'], 'UP', 'DOWN'));
	$grid->CloseRow();
}

$grid->CloseBody();
$grid->Close();

ClosePanel();
print '</div>';//container

require("footer.inc.php");
