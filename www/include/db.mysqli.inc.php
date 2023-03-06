<?php
/*
db.mysql.inc.php - part of DriftVM
License: GPLv3
Copyright 2023 Drift Solutions
*/
if (!defined('DATABASE_INC_PHP')) {
	define('DATABASE_INC_PHP','INCLUDED');

	if (!function_exists('mysqli_connect_error')) {
		die("ERROR: Need mysqli extension installed!");
	}

	class DriftVM_DB {
		var $link = FALSE;
		var $queries = 0;
		var $host = '';
		var $user = '';
		var $pass = '';
		var $dbname = '';
		var $port = 0;

		function init($host, $user, $pass, $dbname, $port=0) {
			global $config;
			$this->link = FALSE;
			$this->queries = 0;
			if (!$port) {
				$port = 3306;
			}
			$this->host = $host;
			$this->user = $user;
			$this->pass = $pass;
			$this->dbname = $dbname;
			$this->port = $port;
			$this->link = new mysqli($host, $user, $pass, '', $port);
			$tries = 0;
			while (mysqli_connect_error() == 1040 && $tries++ < 10) {
				if ($config['Debug']) {
					print "Error connecting to MySQL, retrying in 1-2 seconds...<br />";
				}
				sleep(mt_rand(1,2));
				$this->link = null;
				$this->link = new mysqli($host, $user, $pass, '', $port);
			}
			if (mysqli_connect_error()) {
				$msg = "Error connecting to MySQL server! Error Number: ".mysqli_connect_errno()." -> ".mysqli_connect_error();
				if ($config['Debug']) {
					print $msg."<br />";
				}
				if (mysqli_connect_errno() != 1040) {
					mail($config['admin_email'], "Database Error", $msg."\nServer: $host:$port\nUser: $user\nPass: $pass\nRemote IP:".$_SERVER['REMOTE_ADDR']."\nRequest: ".print_r($_REQUEST, TRUE)."\nServer: ".print_r($_SERVER, TRUE));
				}
				$this->link = FALSE;
				return false;
			}
			if (!empty($dbname) && !mysqli_select_db($this->link, $dbname)) {
				$msg = "Error selecting database! Error Number: ".$this->errno()." -> ".$this->error();
				if ($config['Debug']) {
					print $msg."<br />";
				}
				mail($config['admin_email'], "Database Error", $msg);
				return false;
			}
			mysqli_set_charset($this->link, 'utf8');
			return true;
		}

		function close() {
			mysqli_close($this->link);
			$this->link = FALSE;
		}

		function select_db($dbname) {
			return mysqli_select_db($this->link, $dbname);
		}

		function query($query) {
			global $config;
			$this->queries++;
			$ret = mysqli_query($this->link, $query);
			$errno = (int)$this->errno();
			if ($ret === FALSE && $this->errno() == 2006) {
				$tries = 0;
				while (!$this->init($this->host, $this->user, $this->pass, $this->dbname, $this->port) && $tries++ < 5) { sleep(1); }
			}
			$tries = 0;
			$errors = array(1205,1213,2006);
			while ($ret === FALSE && in_array($errno, $errors) && $tries++ < 60) { // deadlock
				sleep(1);
				$ret = mysqli_query($this->link, $query);
				$errno = (int)$this->errno();
			}
			if ($ret === FALSE) {
				$msg = "Error executing query: $query => Error Number: ".$this->errno()." -> ".$this->error();
				if ($config['Debug']) {
					print $msg."<br />";
				}
				mail($config['admin_email'], "Database Error", MakeErrorReport("MySQL", $msg));
			}
			return $ret;
		}

		/* Array values passed in $arr are escaped for you, so you don't have to */
		function insert($table, $arr) {
			$query = "INSERT INTO `".$table."` (".
			$values = "";
			foreach($arr as $field => $val) {
				$query .= "`".$field."`,";
				$values .= "'".$this->escape($val)."',";
			}
			$query = substr($query,0,strlen($query)-1);
			$values = substr($values,0,strlen($values)-1);
			$query .= ") VALUES (".$values.")";
			return $this->query($query);
		}


		function insert_ignore($table, $arr) {
			$query = "INSERT IGNORE INTO `".$table."` (".
			$values = "";
			foreach($arr as $field => $val) {
				$query .= "`".$field."`,";
				$values .= "'".$this->escape($val)."',";
			}
			$query = substr($query,0,strlen($query)-1);
			$values = substr($values,0,strlen($values)-1);
			$query .= ") VALUES (".$values.")";
			return $this->query($query);
		}

		function insert_or_update($table, $arr) {
			$query = "INSERT INTO `".$table."` (".
			$values = "";
			foreach($arr as $field => $val) {
				$query .= "`".$field."`,";
				$values .= "'".$this->escape($val)."',";
			}
			$query = substr($query,0,strlen($query)-1);
			$values = substr($values,0,strlen($values)-1);
			$query .= ") VALUES (".$values.") ON DUPLICATE KEY UPDATE ";
			foreach($arr as $field => $val) {
				if ($field == 'ID') { continue; }
				$query .= "`".$field."`='".$this->escape($val)."',";
			}
			$query = substr($query,0,strlen($query)-1);
			return $this->query($query);
		}

		/* Array values passed in $arr are escaped for you, so you don't have to */
		function update($table, $arr) {
			$query = "UPDATE `".$table."` SET ";
			foreach($arr as $field => $val) {
				if ($field != "ID") {
					$query .= "`".$field."`='".$this->escape($val)."',";
				}
			}
			$query = substr($query,0,strlen($query)-1);
			$query .= " Where `ID`='".$this->escape($arr['ID'])."'";
			return $this->query($query);
		}

		function replace($table, $arr) {
			$query = "REPLACE INTO `".$table."` (".
			$values = "";
			foreach($arr as $field => $val) {
				$query .= "`".$field."`,";
				$values .= "'".$this->escape($val)."',";
			}
			$query = substr($query,0,strlen($query)-1);
			$values = substr($values,0,strlen($values)-1);
			$query .= ") VALUES (".$values.")";
			return $this->query($query);
		}

		function escape($val) {
			if ($this->link != FALSE) {
				return mysqli_real_escape_string($this->link, $val);
			}
			return addslashes($val);
		}

		function num_rows($res) {
			return mysqli_num_rows($res);
		}

		function affected_rows() {
			return mysqli_affected_rows($this->link);
		}

		function fetch_assoc($res) {
			if ($ret = mysqli_fetch_assoc($res)) {
				foreach ($ret as $key => $val) {
					$ret[$key] = $val;
				}
			}
			return $ret;
		}

		function fetch_array($res) {
			if ($ret = mysqli_fetch_array($res)) {
				foreach ($ret as $key => $val) {
					$ret[$key] = $val;
				}
			}
			return $ret;
		}

		function field_type($res, $ind) {
			return mysqli_field_type($res, $ind);
		}

		function insert_id() {
			return mysqli_insert_id($this->link);
		}
		function error() {
			return mysqli_error($this->link);
		}
		function errno() {
			return mysqli_errno($this->link);
		}
		function free_result($res) {
			return mysqli_free_result($res);
		}

		function GetQueriesCount() {
			return $this->queries;
		}

		function GetDBType() { return "mysql"; }

		function GetDriverInfo() {
			return array(	"Database Type" => "MariaDB/MySQL",
			"Client Version" => mysqli_get_client_info($this->link),
			"Server Version" => mysqli_get_server_info($this->link),
			"Connection Type" => mysqli_get_host_info($this->link),
			"Protocol Version" => mysqli_get_proto_info($this->link),
			"Encoding" => mysqli_client_encoding($this->link));
		}
	}
	$db = new DriftVM_DB();
}
