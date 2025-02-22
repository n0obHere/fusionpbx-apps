<?php
/*
	FusionPBX
	Version: MPL 1.1

	The contents of this file are subject to the Mozilla Public License Version
	1.1 (the "License"); you may not use this file except in compliance with
	the License. You may obtain a copy of the License at
	http://www.mozilla.org/MPL/

	Software distributed under the License is distributed on an "AS IS" basis,
	WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
	for the specific language governing rights and limitations under the
	License.

	The Original Code is FusionPBX

	The Initial Developer of the Original Code is
	Mark J Crane <markjcrane@fusionpbx.com>
	Portions created by the Initial Developer are Copyright (C) 2008-2025
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	KonradSC <konrd@yahoo.com>
	Mark J Crane <markjcrane@fusionpbx.com>
*/

//includes
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/pdo.php";
	require_once "resources/check_auth.php";
	require_once "resources/paging.php";

//check permissions
	if (permission_exists('mobile_twinning_view')) {
		//access granted
	}
	else {
		echo "access denied";
		exit;
	}

//initialize the database object
	$database = new database;

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//get order and order by
	$order_by = $_GET["order_by"] ?? 'extension';
	$order = $_GET["order"] ?? 'asc';
	$sort = $order_by == 'extension' ? 'natural' : null;

//get total extension count for domain
	if (isset($_SESSION['limit']['extensions']['numeric'])) {
		$sql = "select count(*) from v_extensions ";
		$sql .= "where domain_uuid = :domain_uuid ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
		$total_extensions = $database->select($sql, $parameters, 'column');
		unset($sql, $parameters);
	}

//add the search term
	$search = strtolower($_GET["search"] ?? '');

//get total extension count
	$sql = "select count(*) from v_extensions ";
	$sql .= "where true ";
	if (!(!empty($_GET['show']) && $_GET['show'] == "all" && permission_exists('extension_all'))) {
		$sql .= "and domain_uuid = :domain_uuid ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	}
	if (!empty($search)) {
		$sql .= "and ( ";
		$sql .= " lower(extension) like :search ";
		$sql .= " or lower(mobile_twinning_number) like :search ";
		$sql .= " or lower(description) like :search ";
		$sql .= ") ";
		$parameters['search'] = '%'.$search.'%';
	}
	$num_rows = $database->select($sql, $parameters ?? null, 'column');

//prepare to page the results
	$rows_per_page = ($_SESSION['domain']['paging']['numeric'] != '') ? $_SESSION['domain']['paging']['numeric'] : 50;
	$param = "&search=".$search."&order_by=".$order_by."&order=".$order;
	if (!isset($_GET['page'])) { $_GET['page'] = 0; }
	$_GET['page'] = check_str($_GET['page']);
	list($paging_controls_mini, $rows_per_page, $var_3) = paging($total_extensions, $param, $rows_per_page, true); //top
	list($paging_controls, $rows_per_page, $var_3) = paging($total_extensions, $param, $rows_per_page); //bottom
	$offset = $rows_per_page * $_GET['page'];

//get all the extensions from the database
	$sql = "select e.extension, m.mobile_twinning_number, e.description, m.mobile_twinning_uuid, e.extension_uuid \n";
	$sql .= "FROM  v_extensions AS e \n ";
	$sql .= "LEFT OUTER JOIN v_mobile_twinnings AS m ON m.extension_uuid = e.extension_uuid ";
	$sql .= "where true ";
	if (!(!empty($_GET['show']) && $_GET['show'] == "all" && permission_exists('extension_all'))) {
		$sql .= "and e.domain_uuid = :domain_uuid ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	}
	if (!empty($search)) {
		$sql .= "and ( ";
		$sql .= " lower(extension) like :search ";
		$sql .= " or lower(mobile_twinning_number) like :search ";
		$sql .= " or lower(description) like :search ";
		$sql .= ") ";
		$parameters['search'] = '%'.$search.'%';
	}
	$sql .= "and e.enabled = 'true' ";
	if (strlen($order_by)> 0) {
		$sql .= "order by $order_by $order ";
	}
	else {
		$sql .= "order by extension asc ";
	}
	$sql .= " limit $rows_per_page offset $offset ";
	$result = $database->select($sql, $parameters ?? null, 'all');
	$result_count = count($result);
	unset($parameters, $sql);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//include the header
	require_once "resources/header.php";

//set the alternating styles
	$c = 0;
	$row_style["0"] = "row_style0";
	$row_style["1"] = "row_style1";

//begin the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>".$text['header-mobile_twinning']."</b><div class='count'>".number_format($result_count)."</div></div>\n";
	echo "	<div class='actions'>\n";
	if ((if_group("admin") || if_group("superadmin"))) {
		echo "	<form method='get' action=''>\n";
		echo "		<input type='text' class='txt' style='width: 150px' name='search' id='search' value='".$search."'>";
		echo "		<input type='submit' class='btn' name='submit' value='".$text['button-search']."'>";
		if ($paging_controls_mini != '') {
			echo 	"<span style='margin-left: 15px;'>".$paging_controls_mini."</span>\n";
		}
		echo "	</form>\n";
	}

	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo $text['description-mobile_twinning']."\n";
	echo "<br /><br />";

	echo "<div class='card'>\n";
	echo "<table class='tr_hover' width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
	echo "<tr>\n";
	echo th_order_by('extension', $text['table-extension'], $order_by,$order);
	echo th_order_by('m.mobile_twinning_number', $text['table-twinning_number'], $order_by, $order);
	echo th_order_by('e.description', $text['table-description'], $order_by, $order);
	echo "</tr>\n";

	if ($result_count > 0) {
		foreach($result as $row) {
			$tr_link = (permission_exists('mobile_twinning_edit')) ? " href='mobile_twinning_edit.php?id=".$row['mobile_twinning_uuid']."&extid=".$row['extension_uuid']."'" : null;
			echo "<tr ".$tr_link.">\n";
			echo "	<td valign='top' class='".$row_style[$c]."'>".escape($row['extension'])."</td>\n";
			echo "	<td valign='top' class='".$row_style[$c]."'>".escape(format_phone(substr($row['mobile_twinning_number'],-10)))."</td>\n";
			echo "	<td valign='top' class='row_stylebg' width='40%'>".escape($row['description'])."&nbsp;</td>\n";
			echo "	<td class='list_control_icons'>";
			echo "     <a href='mobile_twinning_edit.php?id=".$row['mobile_twinning_uuid']."&extid=".$row['extension_uuid']."'>$v_link_label_edit</a>";
			echo "  </td>\n";
			echo "</tr>\n";
			if ($c==0) { $c=1; } else { $c=0; }
		} //end foreach
		unset($sql, $result, $row_count);
	} //end if results

	echo "</table>";
	echo "</div>\n";
	if (strlen($paging_controls) > 0) {
		echo "<br />";
		echo $paging_controls."\n";
	}
	echo "<br><br>";

//show the footer
	require_once "resources/footer.php";
?>
