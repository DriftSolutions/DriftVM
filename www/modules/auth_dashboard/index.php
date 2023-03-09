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

$cli = new jsonRPCClient($config['driftvmd_rpc'], $config['Debug']);
try {
	$tmp = $cli->getinfo(array());
	$str = 'Connected, v'.xssafe($tmp['version']);
} catch (Exception $e) {
	$str= 'Error: '.xssafe($e->getMessage());
}
$uptime = trim(shell_exec('uptime -p'));
AddInfoLine('System Uptime', xssafe($uptime), 'driftvmd', $str);

AddInfoLine('Operating System', xssafe(GetOSVersion()), 'Kernel/Arch', xssafe(php_uname('s').' '.php_uname('r').' '.php_uname('m')));

$tmp = GetDiskInfo();
$str = '';
$nl = "\n";
foreach ($tmp as $d) {
	$str2  = 'Device: '.$d['device'].$nl;
	$str2 .= 'Available: '.bytes($d['avail']).$nl;
	$str2 .= 'FS: '.$d['fstype'];
	$str .= '<span title="'.xssafe($str2).'">'.xssafe($d['mountpoint'].': '.bytes($d['used']).' used of '.bytes($d['size'])).'</span><br />';
}
//$str .= nl2br(print_r($tmp, TRUE));
AddInfoLine('Memory', xssafe(bytes($mi['ram']['total']).' total, '.bytes($mi['ram']['free']).' free, '.bytes($mi['ram']['cached']).' cached'), 'Disk Space', $str);

$grid->CloseBody();
$grid->Close();

ClosePanel();
print '</div>';//container

$res = $db->query("SELECT `Status`,COUNT(*) AS `Count` FROM `Machines` GROUP BY `Status` ORDER BY `Status` DESC");
if ($db->num_rows($res) > 0) {

	print '<div class="container mt-3">';
	OpenPanel('Machines');

	$parts = [];
	$total = 0;
	while ($arr = $db->fetch_assoc($res)) {
		$parts[] = xssafe(number_format($arr['Count']).' '.GetMachineStatusString($arr));
		$total += $arr['Count'];
	}
	if ($total > 5) {
		$parts[] = xssafe(number_format($total).' in total.');
	}
	echo implode(', ', $parts);

	ClosePanel();
	print '</div>';//container
}
$db->free_result($res);

print '<div class="container mt-3">';
OpenPanel('Active Network Interfaces');

$grid->Open();

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
ksort($networks);
$wanted_families = [2,10]; // 2 = IPv4, 10 = IPv6
$count = 0;
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
		$grid->TD('<b><a href="network-view?dev='.xssafe($dev).'">'.xssafe($dev).'</a></b>', 'class="text-center"');
	} else {
		$grid->TD('<b>'.xssafe($dev).'</b>', 'class="text-center"');
	}
	$grid->TD(implode('<br />', $addrs));
	$grid->TD(iif($arr['up'], 'UP', 'DOWN'));
	$grid->CloseRow();
	$count++;
}

if ($count == 0) {
	$grid->OpenRow();
	$grid->TD('- No Interfaces Found -', 'class="text-center" colspan=3');
	$grid->CloseRow();
}

$grid->CloseBody();
$grid->Close();

ClosePanel();
print '</div>';//container

require("footer.inc.php");
