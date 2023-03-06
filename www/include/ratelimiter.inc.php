<?php
/*
ratelimiter.inc.php - part of DriftVM
License: GPLv3
Copyright 2023 Drift Solutions
*/
class RateLimiter {
	private $prefix, $calls, $minutes;

	public function __construct($calls = 3, $minutes = 2, $ip = '', $prefix = "ratelimit") {
		if (empty($ip)) {
			$ip = $_SERVER['REMOTE_ADDR'];
		}
		$this->prefix = $prefix.'_'.$ip.'_';
		$this->calls = $calls;
		$this->minutes = $minutes;
	}

	public function Hit() {
		$curkey = $this->prefix.date('Y_m_d_H_i');
		apcu_add($curkey, 0, 120);
		apcu_inc($curkey);
	}

	public function Allow() {
		$ts = time();

		$count = 0;
		$keys = array();
		for ($i = 0; $i < $this->minutes; $i++) {
			$key = $this->prefix.date('Y_m_d_H_i', $ts - ($i * 60));
			$tmp = apcu_fetch($key);
			if ($tmp !== FALSE) {
				$count += $tmp;
			}
		}

		if ($count > $this->calls) {
			return FALSE;
		}

		return TRUE;
	}
};
