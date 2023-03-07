<?php
/*
Part of DriftVM
License: GPLv3
Copyright 2023 Drift Solutions
*/

print '<div class="container">';
OpenPanel('Create Network');
$type = my_intval(SanitizedRequestStr('address'));

?>
<form action="network-create" method="POST">
	<?php echo csrf_get_html('network-create'); ?>
	<div class="mb-3 row">
		<label for="deviceName" class="col-sm-2 col-form-label">Device Name</label>
		<div class="col-sm-10">
			<input type="text" class="form-control" id="deviceName" name="device" value="<?php echo xssafe(SanitizedRequestStr('device')); ?>">
			Unique device name, for example: dvmbr0 or lxcbr0
		</div>
	</div>
	<div class="mb-3 row">
		<label for="address" class="col-sm-2 col-form-label">Subnet IP/Mask</label>
		<div class="col-sm-10">
			<input type="text" class="form-control" id="address" name="address" value="<?php echo xssafe(SanitizedRequestStr('address')); ?>">
			Example: 192.168.3.0/24 or 10.3.0.0/16<br />
			This cannot be an IP range you are already using elsewhere.
		</div>
	</div>
	<div class="mb-3 row">
		<label for="address" class="col-sm-2 col-form-label">Network Type</label>
		<div class="col-sm-10">
			<div class="form-check">
			  <input class="form-check-input" type="radio" name="nettype" id="nettype0"<?php echo iif($type == 0,' checked',''); ?>>
			  <label class="form-check-label" for="nettype0">
			    Routed (can access other networks and the internet)
			  </label>
			</div>
			<div class="form-check">
			  <input class="form-check-input" type="radio" name="nettype" id="nettype1"<?php echo iif($type == 1,' checked',''); ?>>
			  <label class="form-check-label" for="nettype1">
			    Isolated (can only access other VMs and containers on the same network)
			  </label>
			</div>
		</div>
	</div>
</form>
<?php

ClosePanel();
print '</div>';//container

require("footer.inc.php");
