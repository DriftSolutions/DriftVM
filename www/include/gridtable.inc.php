<?php
/*
gridtable.inc.php - part of DriftVM
License: GPLv3
Copyright 2023 Drift Solutions
This just provides a simple interface to build tables. See pretty much any page for examples :)
*/

class GridTable {
	var $class = "";

	function Open($extra="", $responsive=1) {
		if ($extra != "") { $extra = " ".$extra; }
		print "<table class=\"table table-bordered table-condensed\"".$extra.">";
		return 1;
	}
	function Close() {
		print "</table>";
		return 1;
	}

	function OpenRow($extra="") {
		if ($extra != "") { $extra = " ".$extra; }
		print "<tr".$extra.">";
	}
	function CloseRow() {
		print "</tr>";
	}

	function OpenHead() {
		print "<thead>";
	}
	function CloseHead() {
		print "</thead>";
	}
	function OpenBody() {
		print "<tbody>";
	}
	function CloseBody() {
		print "</tbody>";
	}
	function OpenFoot() {
		print "<tfoot>";
	}
	function CloseFoot() {
		print "</tfoot>";
	}

	function Header($content, $extra="") {
		if ($extra != "") { $extra = " ".$extra; }
		print "<th".$extra.">".$content."</th>";
		$this->class = "";
		return 1;
	}

	function TD($content, $extra="") {
		if ($extra != "") { $extra = " ".$extra; }
		if (!is_array($content)) {
			$content = array($content);
		}
		foreach($content as $str) {
			print "<td".$extra.">".$str."</td>";
		}
		return 1;
	}
};

$grid = new GridTable();

