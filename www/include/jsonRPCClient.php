<?php
/*
jsonRPCClient.php - part of DriftVM
License: GPLv3
Copyright 2023 Drift Solutions
*/
/*
					COPYRIGHT

Copyright 2007 Sergio Vaccaro <sergio@inservibile.org>

This file is part of JSON-RPC PHP.

JSON-RPC PHP is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

JSON-RPC PHP is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with JSON-RPC PHP; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if (!function_exists('curl_init')) {
	die("ERROR: We need php-curl installed!\n");
}

/**
 * The object of this class are generic jsonRPC 1.0 clients
 * http://json-rpc.org/wiki/specification
 *
 * @author sergio <jsonrpcphp@inservibile.org>
 */
class jsonRPCClient {

	/**
	 * Debug state
	 *
	 * @var boolean
	 */
	private $debug;

	/**
	 * The server URL
	 *
	 * @var string
	 */
	private $url;
	/**
	 * The request id
	 *
	 * @var integer
	 */
	private $id;

	private $timeout = 300;
	private $custom_headers = array();

	public function getCustomHeaders() {
		$ret = array();
		foreach ($this->custom_headers as $name => $value) {
			$ret[] = $name.': '.$value;
		}
		return $ret;
	}

	public function addCustomHeader($name, $value) {
		$this->custom_headers[$name] = $value;
	}
	public function delCustomHeader($name) {
		if (isset($this->custom_headers[$name])) {
			unset($this->custom_headers[$name]);
		}
	}

	/**
	 * Takes the connection parameters
	 *
	 * @param string $url
	 * @param boolean $debug
	 * @param int $timeo - The amount of time a request can take before timing out.
	 */
	public function __construct($url,$debug = false, $timeo = 300) {
		// server URL
		$this->url = $url;
		// debug state
		$this->debug = $debug;
		// message id
		$this->id = 1;
		$this->timeout = max(30, (int)$timeo);
	}

	/**
	 * Performs a jsonRPC request and gets the results as an array
	 *
	 * @param string $method
	 * @param array $params
	 * @return array
	 */
	public function __call($method,$params) {

		// check
		if (!is_scalar($method)) {
			throw new Exception('Method name has no scalar value');
		}

		// check
		if (!is_array($params)) {
			throw new Exception('Params must be given as array');
		}
		$params = $params[0];
		if (!is_array($params)) {
			throw new Exception('Params must be given as array');
		}

		$currentId = $this->id++;
		// prepares the request
		$request = array(
						'jsonrpc' => '2.0',
						'method' => $method,
						'params' => $params,
						'id' => $currentId
		);
		$request = json_encode($request, JSON_UNESCAPED_SLASHES);
		if ($this->debug && php_sapi_name() == "cli") {
			print '***** Request *****'."\n".$request."\n".'***** End Of request *****'."\n\n";
		}

		$ch = curl_init();
		if ($ch === FALSE) {
			throw new Exception('Error creating cURL handle!');
		}
		$opts = array(
			CURLOPT_URL => $this->url,
			CURLOPT_TIMEOUT => $this->timeout,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_FAILONERROR => FALSE,
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_POST => TRUE,
			CURLOPT_HTTPHEADER => array_merge($this->getCustomHeaders(), array(
				'Content-type: application/json',
			)),
			CURLOPT_POSTFIELDS => $request,
		);
//		if ($this->debug) {
//			$opts[CURLOPT_VERBOSE] = TRUE;
//		}
		curl_setopt_array($ch, $opts);

		// performs the HTTP POST
		$raw = $response = curl_exec($ch);
		$info = curl_getinfo($ch);
		if ($response === FALSE) {
			if ($this->debug && php_sapi_name() == "cli") {
				print "cURL Error: ".curl_error($ch)."\n";
				print_r($info);
			}
			throw new Exception('Error executing cURL handle!');
		}
		curl_close($ch);

		$success = (intval($info['http_code']) >= 200 && intval($info['http_code']) < 300) ? true:false;
		if ($this->debug && php_sapi_name() == "cli" && !$success) {
			print '***** Request *****'."\n".$request."\n".'***** End Of request *****'."\n\n";
			print '***** Server response *****'."\n".$response.'***** End of server response *****'."\n";
		}
		if ($success) {
			$response = json_decode($response,true);
		} else {
			throw new Exception('RPC ERROR: '.$response);
		}

		// final checks and return
		if ($response['id'] != $currentId) {
			if ($this->debug && php_sapi_name() == "cli") {
				print '***** Request *****'."\n".$request."\n".'***** End Of request *****'."\n\n";
				print '***** Server response *****'."\n".$raw.'***** End of server response *****'."\n";
			}
			throw new Exception('Incorrect response id (request id: '.$currentId.', response id: '.$response['id'].')');
		}
		if (isset($response['error']) && !is_null($response['error'])) {
			if ($this->debug && php_sapi_name() == "cli") {
				print '***** Request *****'."\n".$request."\n".'***** End Of request *****'."\n\n";
			}
			throw new Exception('Request error: '.print_r($response['error'], TRUE));
		}

		return $response['result'];
	}
}
