<?php
/*
Part of DriftVM
License: GPLv3
Copyright 2023 Drift Solutions
*/

$guid = SanitizedRequestStr('guid');
if (empty($guid)) {
	ShowMsgBox('Error', 'No machine driver specified!');
	return;
}

$d = GetMachineDriver($guid);
if ($d === FALSE) {
	ShowMsgBox('Error', 'Could not load that machine driver!');
	return;
}
$opts = $d->GetCreateOptions();

$charset = 'abcdefghijklmnopqrstuvwxyz0123456789';
function is_devname_allowed($str) {
	global $charset;
	return (strspn($str, $charset) == strlen($str));
}

if (count($_POST)) {
	if (csrf_verify('machine-create')) {
		$devname = SanitizedRequestStr('device');
		$net = SanitizedRequestStr('network');
		if (!empty($devname)) {
			if (is_devname_allowed($devname)) {
				if (IsOurNetwork($net)) {
					$optvals = [];
					foreach (array_keys($opts) as $opt) {
						$optvals[$opt] = SanitizedRequestStr($opt);
					}
					$tmp = $d->ValidateCreateOptions($optvals);
					if ($tmp === TRUE) {
						$insert = [
							'Name' => $devname,
							'Type' => $guid,
							'Network' => $net,
							'Status' => MS_CREATING,
							'CreateOptions' => json_encode($optvals),
						];
						if ($db->insert('Machines', $insert) === TRUE) {
							$cli = new jsonRPCClient($config['driftvmd_rpc'], $config['Debug']);
							try {
								$cli->machine_create(['name' => $devname]);
								ShowMsgBox('Success', 'Machine created!<br /><br />'.std_redirect('auth_machines','action=view&name='.xssafe($devname)));
								return;
							} catch (Exception $e) {
								ShowMsgBox('Error', 'Error creating machine: '.xssafe($e->getMessage()));
							}
						} else {
							ShowMsgBox('Error', 'Error creating machine! (Is the name already in use?)');
						}
					} else {
						ShowMsgBox('Error', xssafe($tmp));
					}
				} else {
					ShowMsgBox('Error', 'Invalid network selected!');
				}
			} else {
				ShowMsgBox('Error', 'The machine name has invalid characters!<br /><br />Allowed: '.$charset);
			}
		} else {
			ShowMsgBox('Error', 'The machine name cannot be empty!');
		}
	} else {
		ShowMsgBox('Error', 'CSRF check failed! Please try again...');
	}
}

print '<div class="container">';
OpenPanel('Create '.xssafe($d->GetMachineType(false)));
?>
<form action="machine-create" method="POST">
	<?php echo csrf_get_html('machine-create'); ?>
	<input type="hidden" name="guid" value="<?php echo xssafe($guid); ?>">

<ul class="nav nav-tabs mb-3" id="myTab" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="main-tab" data-bs-toggle="tab" data-bs-target="#main-tab-pane" type="button" role="tab" aria-controls="main-tab-pane" aria-selected="true">Main</button>
  </li>
  <?php if (count($opts)) { ?>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="opts-tab" data-bs-toggle="tab" data-bs-target="#opts-tab-pane" type="button" role="tab" aria-controls="opts-tab-pane" aria-selected="false">Driver-specific Options</button>
  </li>
  <?php } ?>
</ul>

<div class="tab-content" id="myTabContent">
  <div class="tab-pane fade show active" id="main-tab-pane" role="tabpanel" aria-labelledby="main-tab" tabindex="0">
		<div class="mb-3 row">
			<label for="deviceName" class="col-sm-2 col-form-label">Machine Name</label>
			<div class="col-sm-10">
				<input type="text" class="form-control" id="deviceName" name="device" value="<?php echo xssafe(SanitizedRequestStr('device')); ?>" required>
				Unique machine name, for example: mailserver or plex (for your home videos only of course.) No spaces or special characters.
			</div>
		</div>
		<div class="mb-3 row">
			<label for="network" class="col-sm-2 col-form-label">Network</label>
			<div class="col-sm-10">
				<select class="form-control" id="network" name="network">
					<?php
						$sel = SanitizedRequestStr('network');
						$res = $db->query("SELECT `Device`,`IP`,`Netmask` FROM `Networks`");
						while ($arr = $db->fetch_assoc($res)) {
							print '<option value="'.xssafe($arr['Device']).'"'.iif($arr['Device'] == $sel,' selected','').'>'.xssafe($arr['Device'].' ('.$arr['IP'].'/'.$arr['Netmask'].')').'</option>';
						}
						$db->free_result($res);
					?>
				</select>
			</div>
		</div>
	</div>

  <div class="tab-pane fade" id="opts-tab-pane" role="tabpanel" aria-labelledby="opts-tab" tabindex="0">
  	<?php echo RenderOptions($opts); ?>
	</div>
</div>

	<div class="mb-3 text-center">
 		<button type="submit" class="btn btn-primary mb-3">Create Machine</button>
	</div>
</form>
<?php

ClosePanel();
print '</div>';//container
