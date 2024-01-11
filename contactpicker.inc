<div id="_contact_picker_dialog" class="dialog-box">
	<form id="_contact_picker_filter_form">
		<input type="hidden" id="_contact_picker_column_name" name="_contact_picker_column_name"/>
		<input type="hidden" id="_contact_picker_filter_where" name="_contact_picker_filter_where"/>
		<input type="hidden" id="_contact_picker_current_value" name="_contact_picker_current_value"/>

		<input type="text" id="contact_picker_filter_text" name="contact_picker_filter_text" size="20" placeholder="Filter"/>
		<?php
		$contactPickerPagePreferences = Page::getPagePreferences("GETCONTACTPICKERLIST");
		$resultSet = executeQuery("select * from contact_types where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
		if ($resultSet['row_count'] > 0) {
			?>
			<select id="contact_picker_contact_type_id" name="contact_picker_contact_type_id">
				<option value="">[Contact Type]</option>
				<?php
				while ($row = getNextRow($resultSet)) {
					?>
					<option value="<?= $row['contact_type_id'] ?>"<?= ($contactPickerPagePreferences['contact_picker_contact_type_id'] == $row['contact_type_id'] ? " selected" : "") ?>><?= htmlText($row['description']) ?></option>
					<?php
				}
				?>
			</select>
			<?php
		}
		?>
		<button id="contact_picker_filter">Filter</button>
		<button id="contact_picker_no_contact">None</button>
		<?php if (canAccessPageCode("CONTACTMAINT") >= _READWRITE) { ?>
			<button id="contact_picker_new_contact">Add</button>
		<?php } ?>
	</form>
	<div id="_contact_picker_list">
	</div>
</div>
<div id="_contact_picker_add_dialog" class="dialog-box">
	<form id="_contact_picker_add_form">
		<?= createFormControl("contacts", "first_name", array("column_name" => "contact_picker_first_name", "not_null" => true)) ?>
		<?= createFormControl("contacts", "last_name", array("column_name" => "contact_picker_last_name", "not_null" => true)) ?>
		<?= createFormControl("contacts", "email_address", array("column_name" => "contact_picker_email_address", "not_null" => true)) ?>
	</form>
</div>
