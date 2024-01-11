<?php

/*	This software is the unpublished, confidential, proprietary, intellectual
	property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
	or used in any manner without expressed written consent from Kim David Software, LLC.
	Kim David Software, LLC owns all rights to this work and intends to keep this
	software confidential so as to maintain its value as a trade secret.

	Copyright 2004-Present, Kim David Software, LLC.

	WARNING! This code is part of the Kim David Software's Coreware system.
	Changes made to this source file will be lost when new versions of the
	system are installed.
*/

$GLOBALS['gPageCode'] = "TASKTYPEMAINT";
require_once "shared/startup.inc";

class ThisPage extends Page {
	function massageDataSource() {
		$this->iDataSource->getPrimaryTable()->setSubtables(array("task_type_attributes","task_type_users"));
	}

	function taskTypeAttributes() {
?>
<p class="subheader">Task Attributes</p>
<table>
<?php
		$resultSet = executeQuery("select * from task_attributes where inactive = 0 order by sort_order,description");
		while ($row = getNextRow($resultSet)) {
?>
<tr>
	<td class="field-label"><label for="task_attribute_id_<?= $row['task_attribute_id'] ?>"><?= htmlText($row['description']) ?></label></td>
	<td><select id="task_attribute_id_<?= $row['task_attribute_id'] ?>" name="task_attribute_id_<?= $row['task_attribute_id'] ?>">
		<option value="">Not Used</option>
		<option value="Y">Adding and editing task</option>
		<option value="E">Editing Only</option>
	</select></td>
</tr>
<?php
		}
?>
</table>
<?php
	}

	function afterGetRecord(&$returnArray) {
		$resultSet = executeQuery("select * from task_attributes where inactive = 0 order by sort_order,description");
		while ($row = getNextRow($resultSet)) {
			if (!empty($returnArray['task_type_id']['data_value'])) {
				$taskTypeAttributeId = getFieldFromId("task_type_attribute_id","task_type_attributes","task_attribute_id",$row['task_attribute_id'],"task_type_id = " . $returnArray['task_type_id']['data_value']);
				$editOnly = getFieldFromId("edit_only","task_type_attributes","task_attribute_id",$row['task_attribute_id'],"task_type_id = " . $returnArray['task_type_id']['data_value']);
			} else {
				$taskTypeAttributeId = "";
				$editOnly = false;
			}
			$fieldValue = (empty($taskTypeAttributeId) ? "" : ($editOnly ? "E" : "Y"));
			$returnArray["task_attribute_id_" . $row['task_attribute_id']] = array("data_value"=>$fieldValue,"crc_value"=>getCrcValue($fieldValue));
		}
		return true;
	}

	function afterSaveChanges($nameValues,$actionPerformed) {
		$resultSet = executeQuery("select * from task_attributes where inactive = 0 order by sort_order,description");
		while ($row = getNextRow($resultSet)) {
			executeQuery("delete from task_type_attributes where task_attribute_id = ? and task_type_id = ?",$row['task_attribute_id'],$nameValues['primary_id']);
			if (!empty($nameValues['task_attribute_id_' . $row['task_attribute_id']])) {
				executeQuery("insert into task_type_attributes (task_type_id,task_attribute_id,edit_only) values (?,?,?)",$nameValues['primary_id'],$row['task_attribute_id'],($nameValues['task_attribute_id_' . $row['task_attribute_id']] == "E" ? 1 : 0));
			}
		}
		return true;
	}
}
$pageObject = new ThisPage("task_types");
$pageObject->displayPage();
