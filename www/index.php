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
		<div class="d-flex flex-row h-100">
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
				<ul class="list-unstyled ps-0">
					<?php if (is_user()) { ?>
					<li class="mb-1">
						<button class="btn btn-toggle d-inline-flex align-items-center rounded border-0 collapsed" data-bs-toggle="collapse" data-bs-target="#system-collapse" aria-expanded="false">
							System
						</button>
						<div class="collapse" id="system-collapse" style="">
							<ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
								<li><a href="dashboard" target="module" class="link-light d-inline-flex text-decoration-none rounded">Dashboard</a></li>
							</ul>
						</div>
					</li>
					<li class="mb-1">
						<button class="btn btn-toggle d-inline-flex align-items-center rounded border-0 collapsed" data-bs-toggle="collapse" data-bs-target="#networks-collapse" aria-expanded="false">
							Networks
						</button>
						<div class="collapse" id="networks-collapse" style="">
							<ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
								<?php
									$res = $db->query("SELECT `Device` FROM `Networks`");
									while ($arr = $db->fetch_assoc($res)) {
										print '<li><a href="network-view?dev='.xssafe($arr['Device']).'" target="module" class="link-light d-inline-flex text-decoration-none rounded">'.xssafe($arr['Device']).'</a></li>';
									}
									$db->free_result($res);
								?>
								<li><a href="network-create" target="module" class="link-light d-inline-flex text-decoration-none rounded">Create New</a></li>
							</ul>
						</div>
					</li>
					<li class="mb-1">
						<button class="btn btn-toggle d-inline-flex align-items-center rounded border-0 collapsed" data-bs-toggle="collapse" data-bs-target="#dashboard-collapse" aria-expanded="false">
							VM/Containers
						</button>
						<div class="collapse" id="dashboard-collapse">
							<ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
								<li><a href="#" class="link-dark d-inline-flex text-decoration-none rounded">Overview</a></li>
								<li><a href="#" class="link-dark d-inline-flex text-decoration-none rounded">Weekly</a></li>
								<li><a href="#" class="link-dark d-inline-flex text-decoration-none rounded">Monthly</a></li>
								<li><a href="#" class="link-dark d-inline-flex text-decoration-none rounded">Annually</a></li>
							</ul>
						</div>
					</li>
					<li class="mb-1">
						<button class="btn btn-toggle d-inline-flex align-items-center rounded border-0 collapsed" data-bs-toggle="collapse" data-bs-target="#orders-collapse" aria-expanded="false">
							Orders
						</button>
						<div class="collapse" id="orders-collapse">
							<ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
								<li><a href="#" class="link-dark d-inline-flex text-decoration-none rounded">New</a></li>
								<li><a href="#" class="link-dark d-inline-flex text-decoration-none rounded">Processed</a></li>
								<li><a href="#" class="link-dark d-inline-flex text-decoration-none rounded">Shipped</a></li>
								<li><a href="#" class="link-dark d-inline-flex text-decoration-none rounded">Returned</a></li>
							</ul>
						</div>
					</li>
					<li class="border-top my-3"></li>
					<li class="mb-1">
						<button class="btn btn-toggle d-inline-flex align-items-center rounded border-0 collapsed" data-bs-toggle="collapse" data-bs-target="#account-collapse" aria-expanded="false">
							Account
						</button>
						<div class="collapse" id="account-collapse">
							<ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
								<li><a href="#" class="link-dark d-inline-flex text-decoration-none rounded">New...</a></li>
								<li><a href="#" class="link-dark d-inline-flex text-decoration-none rounded">Profile</a></li>
								<li><a href="#" class="link-dark d-inline-flex text-decoration-none rounded">Settings</a></li>
								<li><a href="#" class="link-dark d-inline-flex text-decoration-none rounded">Sign out</a></li>
							</ul>
						</div>
					</li>
					<li><a href="logout" target="module" class="text-decoration-none rounded" style="padding: 0.75rem 0.375rem;">Log Out</a></li>
					<?php } else { ?>
					<li><a href="login" class="text-decoration-none rounded" target="module">Log In</a></li>
					<?php } /* is_user() */ ?>
				</ul>
			</div>
			<iframe class="flex-grow-1 h-100" name="module" id="module"></iframe>
		</div><!-- /flex-row -->
		<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4" crossorigin="anonymous"></script>
<?php
	$url = 'module.php?mod='.xssafe($mod);
	print '<script type="text/javascript">document.getElementById(\'module\').src = '.json_encode($url).';</script>';
?>
  </body>
</html>