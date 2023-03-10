<?php
require("./core.inc.php");

if ($_SERVER['REQUEST_METHOD'] == "OPTIONS") {
	header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
	exit;
} else if ($_SERVER['REQUEST_METHOD'] != "POST" && $_SERVER['REQUEST_METHOD'] != "GET") {
	header('403 Access Forbidden');
	print "We only accept POST and GET requests...";
	exit;
}

$mod = SanitizedRequestStr('mod');
if (empty($mod)) {
	// The default module if none is specified
	$mod = "login";
}

$fn = "./modules/".$mod."/index.php";
/* Check if module name is only legal characters and exists. */
if (strspn($mod,"abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_") != strlen($mod) || !file_exists($fn)) {
	http_response_code(404);
	die("ERROR: Invalid module!");
}
?>
<!doctype html>
<html lang="en" class="h-100">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title>DriftVM <?php echo DRIFTVM_VERSION; ?></title>
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootswatch/5.2.3/darkly/bootstrap.min.css" integrity="sha512-YRcmztDXzJQCCBk2YUiEAY+r74gu/c9UULMPTeLsAp/Tw5eXiGkYMPC4tc4Kp1jx/V9xjEOCVpBe4r6Lx6n5dA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
	</head>
	<body class="h-100">
		<div class="d-flex flex-column h-100">
			<div class="flex-grow-1 d-flex flex-row h-100">
				<div class="flex-shrink-0 p-3" style="width: 280px;">
					<a href="/" class="d-flex align-items-center pb-3 mb-3 text-decoration-none border-bottom">
						<div class="d-flex flex-row">
							<img src="images/logo.png" class="flex-shrink-0 img-responsive pe-2" style="max-height: 50px;" />
							<div class="flex-grow-1">
								<span class="fs-5 fw-semibold">DriftVM</span><br />
								<small>Management System</small>
							</div>
						</div>
					</a>

					<?php if (is_user()) { ?>

					<h5>System</h5>
					<ul class="list-unstyled ps-0">
						<li><a href="dashboard" target="module" class="link-light d-inline-flex text-decoration-none rounded">Dashboard</a></li>
						<li><a href="settings" target="module" class="link-light d-inline-flex text-decoration-none rounded">Settings</a></li>
					</ul>
					<h5>Networks</h5>
					<ul class="list-unstyled ps-0">
						<li><a href="networks" target="module" class="link-light d-inline-flex text-decoration-none rounded">Networks</a></li>
						<li><a href="network-create" target="module" class="link-light d-inline-flex text-decoration-none rounded">Create New</a></li>
					</ul>
					<h5>Machines</h5>
					<ul class="list-unstyled ps-0">
						<li><a href="machines" target="module" class="link-light d-inline-flex text-decoration-none rounded">Machines</a></li>
						<?php
							foreach (GetMachineDrivers() as $d) {
									print '<li><a href="machine-create?guid='.xssafe($d->guid).'" target="module" class="link-light d-inline-flex text-decoration-none rounded">'.xssafe('Create '.$d->GetMachineType(false)).'</a></li>';
							}
						?>
					</ul>
					<hr />
					<ul class="list-unstyled ps-0">
						<li><a href="logout" target="module" class="text-decoration-none rounded" style="padding: 0.75rem 0.375rem;">Log Out</a></li>
					</ul>

					<?php } else { ?>
					<ul class="list-unstyled ps-0">
						<li><a href="login" class="text-decoration-none rounded" target="module">Log In</a></li>
					</ul>
					<?php } /* is_user() */ ?>

				</div>
				<iframe class="flex-grow-1 h-100" name="module" id="module"></iframe>
			</div><!-- /flex-row -->
			<div class="flex-shrink-0 text-bg-dark p-3">
				<div class="container text-center">
					Copyright 2023 Drift Solutions. All Rights Reserved.<br />
					DriftVM is licensed under the <a href="https://github.com/DriftSolutions/DriftVM/blob/main/LICENSE" target="_blank">GNU General Public License v3.0</a> or any later version.
				</div>
			</div>
		</div>
		<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4" crossorigin="anonymous"></script>
<?php
	$url = 'module.php?mod='.xssafe($mod);
	print '<script type="text/javascript">document.getElementById(\'module\').src = '.json_encode($url).';</script>';
?>
  </body>
</html>
