<?php
/*
Part of DriftVM
License: GPLv3
Copyright 2023 Drift Solutions
*/
$pagename = "Log In";
$pagedesc = "Log in to the DriftVM Control Panel";
$pagecan = $config['site_url'].'login';
require("header.inc.php");
require_once('./include/passwd.inc.php');

$captcha_ok = false;
if ($config['use_hcaptcha'] && !empty($config['hcaptcha_secret']) && !empty($config['hcaptcha_sitekey'])) {
	if (isset($_POST["h-captcha-response"]) && !empty($_POST["h-captcha-response"])) {
		$captcha_ok = hcaptcha_verify_code($_POST["h-captcha-response"]);
	}
	$captcha_html = '<script src="https://www.hCaptcha.com/1/api.js" async defer></script><div class="h-captcha" data-sitekey="'.xssafe($config['hcaptcha_sitekey']).'"></div>';
} else {
	$captcha_ok = true;
	$captcha_html = '';
}

function complete_login($ui) {
	global $db;

	if ($ui['Status'] <= 0) {
		kill_session();
		exit;
	}

	$_SESSION['userinfo'] = $ui;
	$update = array(
		'ID' => $_SESSION['userinfo']['ID'],
		'LastLogin' => time(),
	);
	$db->update('Users', $update);
	/* Insert successful login into login history */
	$insert = array(
		'UserID' => $_SESSION['userinfo']['ID'],
		'TimeStamp' => time(),
		'IP' => $_SERVER['REMOTE_ADDR'],
	);
	$db->insert('LoginHistory', $insert);

	$_SESSION['keep_logged_in'] = (my_intval(SanitizedRequestStr('kli')) == 1);

	if (!HaveSettings()) {
		ShowMsgBox("Logged In", std_redirect_reload('auth_settings'));
	} else if (isset($_SESSION['return'])) {
		$return = $_SESSION['return']['mod'];
		unset($_SESSION['return']);
		ShowMsgBox("Logged In", std_redirect_reload($return));
	} else {
		ShowMsgBox("Success", sprintf("Hello %s, welcome back!", xssafe($_SESSION['userinfo']['Username']))."<br /><br />".std_redirect_reload("auth_dashboard"));
	}
}

if (isset($_REQUEST['return'])) {
	$return = SanitizedRequestStr('return');
	if (strspn($return,"abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_") != strlen($return)) {
		$return = "";
	}
	if (!empty($return)) {
		$_SESSION['return'] = array();
		$_SESSION['return']['mod'] = $return;
	} else {
		unset($_SESSION['return']);
	}
}

if (is_user()) {
	if (isset($_SESSION['return'])) {
		$return = $_SESSION['return']['mod'];
		unset($_SESSION['return']);
		ShowMsgBox("Already Logged In", std_redirect_reload($return));
	} else {
		ShowMsgBox("Already Logged In", "You are already logged in!");
	}
	require("footer.inc.php");
	exit;
}

$error_msg = "";
$info_msg = "";

if (isset($_REQUEST['user']) && isset($_REQUEST['pass'])) {
	$username = SanitizedRequestStr('user');
	$pass = TrimTo(GetRequestStr('pass'), 72);
	if (!empty($username) && !empty($pass)) {
		if (!csrf_verify('login')) {
			ShowMsgBox("Error", "Form does not pass CSRF checks!");
			require("footer.inc.php");
			exit;
		}

		/* Rate Limiting / Account Locking */
		$rl = new RateLimiter(3, 2, '', 'login');
		if (!$rl->Allow()) {
			$insert = array("IP" => $_SERVER['REMOTE_ADDR'], "TimeStamp" => time(), "Expires" => time() + 3600, "Reason" => "Too many login attempts");
			$db->insert_ignore("BannedIPs", $insert);
			ShowMsgBox("Error", 'Too many login attempts, you have been banned for 1 hour.');
			require("footer.inc.php");
			exit;
		}
		$rl->Hit();
		/* End Rate Limiting / Account Locking */

		if (!$captcha_ok) {
			$error_msg = "Invalid CAPTCHA response, please try again...";
		}

		if (empty($error_msg)) {
			$res = $db->query("SELECT COUNT(*) AS `Count` FROM `Users` LIMIT 1");
			if (($arr = $db->fetch_assoc($res)) !== FALSE && $arr['Count'] == 0) {
				if (strspn($username,"abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_0123456789") == strlen($username)) {
					$insert = array(
						'Username' => $username,
						'Status' => 1,
						'Joined' => time(),
					);
					if ($db->insert('Users', $insert) === TRUE && ($id = $db->insert_id()) > 0) {
						SetPasswordByID($id, $pass);
					} else {
						$error_msg = "Error creating first user!";
					}
				} else {
					$error_msg = "No existing users, but you have invalid characters in your username so we can't create it for you! Allowed characters: abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_0123456789";
				}
			}
			$db->free_result($res);
		}
		if (empty($error_msg)) {
			$res = $db->query("SELECT * FROM `Users` WHERE `Username`='".$db->escape($username)."' LIMIT 1");
			$arr = FALSE;
			if ($arr = $db->fetch_assoc($res)) {
				if ($arr['Status'] > 0 && CheckEncryptedPassword($pass, $arr['Password'])) {
					complete_login($arr);
					require("footer.inc.php");
					exit;
				}
			}
			$error_msg = "Invalid username and/or password!";
		}

		ShowMsgBox("Error", $error_msg);
		require("footer.inc.php");
		exit;
	}
}

if (!empty($error_msg)) {
	ShowMsgBox("Error", $error_msg);
}

print '<div class="container">';
print '<div class="row">';
print '<div class="col-12 col-sm-8 col-md-8 col-lg-4">';
OpenPanel('Log in to your account');
?>
<form action="login" method="POST">
	<input class="form-control" type="text" placeholder="<?php echo xssafe('Username'); ?>" name="user" value="<?php echo xssafe(SanitizedRequestStr('user')); ?>" required>
  <input class="form-control mb-3" type="password" placeholder="<?php echo xssafe('Password'); ?>" name="pass" required>
  <?php
  	echo csrf_get_html('login');
  	if (!empty($captcha_html)) {
			echo $captcha_html;
		}
  ?>
  <input type="submit" value="<?php echo 'Login'; ?>" class="btn btn-primary mb-3">
  <div class="form-check form-switch">
	  <input class="form-check-input" type="checkbox" role="switch" value="1" id="kli" name="kli">
	  <label class="form-check-label" for="kli">Keep me logged in</label>
	 </div>
</form>
<?php
ClosePanel();
print '</div>';//col
print '</div>';//row
print '</div>';//container

require("footer.inc.php");
