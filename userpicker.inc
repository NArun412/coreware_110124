<div id="_user_picker_dialog" class="dialog-box">
<form id="_user_picker_filter_form">
<input type="hidden" id="_user_picker_column_name" name="_user_picker_column_name" />
<input type="hidden" id="_user_picker_filter_where" name="_user_picker_filter_where" />

<input tabindex="10" type="text" id="user_picker_filter_text" name="user_picker_filter_text" size="40" placeholder="Filter" />
<?php
	$userPickerPagePreferences = Page::getPagePreferences("GETUSERPICKERLIST");
	$resultSet = executeQuery("select * from user_types where client_id = ? and inactive = 0 order by sort_order,description",$GLOBALS['gClientId']);
	if ($resultSet['row_count'] > 0) {
?>
<select id="user_picker_user_type_id" name="user_picker_user_type_id">
	<option value="">[Type]</option>
<?php
		while ($row = getNextRow($resultSet)) {
?>
	<option value="<?= $row['user_type_id'] ?>"<?= ($userPickerPagePreferences['user_picker_user_type_id'] == $row['user_type_id'] ? " selected" : "") ?>><?= htmlText($row['description']) ?></option>
<?php
		}
?>
</select>
<?php
	}
?>
<?php
	$resultSet = executeQuery("select * from user_groups where client_id = ? and inactive = 0 order by sort_order,description",$GLOBALS['gClientId']);
	if ($resultSet['row_count'] > 0) {
?>
<select id="user_picker_user_group_id" name="user_picker_user_group_id">
	<option value="">[Group]</option>
<?php
		while ($row = getNextRow($resultSet)) {
?>
	<option value="<?= $row['user_group_id'] ?>"<?= ($userPickerPagePreferences['user_picker_user_group_id'] == $row['user_group_id'] ? " selected" : "") ?>><?= htmlText($row['description']) ?></option>
<?php
		}
?>
</select>
<?php
	}
?>
<button tabindex="10" id="user_picker_filter">Filter</button><button id="user_picker_no_user">None</button><?php if (canAccessPageCode("USERMAINT") >= _READWRITE) { ?><button tabindex="10" id="user_picker_new_user">Add</button><?php } ?>
</form>
<div id="_user_picker_list">
</div>
</div>
