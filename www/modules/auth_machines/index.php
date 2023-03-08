<?php
/*
Part of DriftVM
License: GPLv3
Copyright 2023 Drift Solutions
*/
require("header.inc.php");

$action = SanitizedRequestStr('action');
switch ($action) {
	case 'create':
	case 'edit':
	case 'view':
		require('./modules/auth_machines/'.$action.'.php');
		break;
	default:
		require('./modules/auth_machines/list.php');
		break;
}

require("footer.inc.php");
