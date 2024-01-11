<div id="_maintenance_form">

	<div id="id_wrapper">
	%field:user_id_display,contact_id_display%
	%basic_form_line%
	%end repeat%

	</div>

	<div class="tabbed-form">
		<ul>
			<li><a href="#tab_1">%programText:Details%</a></li>
			<li><a href="#tab_2">%programText:Contact%</a></li>
			<li><a href="#tab_5">%programText:Access%</a></li>
		</ul>

		<div id="tab_1">

		%field:user_name,password,contacts.first_name,last_name,business_name,email_address,date_created%
		%basic_form_line%
		%end repeat%

		%field:administrator_flag,last_login,last_login_location,last_password_change,force_password_change%
		%basic_form_line%
		%end repeat%

		%field:locked,inactive%
		%basic_form_line%
		%end repeat%

		</div>

		<div id="tab_2">
			<div id="_contact_left_column" class="shorter-label">

				%field:address_1,address_2,city,city_select%
				%basic_form_line%
				%end repeat%

				%field:state%
				&nbsp;%input_control%

				%field:postal_code%
				%input_control%

				%field:country_id%
				%basic_form_line%
				<div class='clear-div'></div>
			</div>


			<div id="_contact_right_column" class="shortest-label">

				%field:phone_numbers%
				<div class="basic-form-line" id="_%column_name%_row">
					%input_control%
					<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
				</div>
				<div class='clear-div'></div>
			</div>

		</div>

		<div id="tab_5">
			<div id='non_admin_access'>
				<h3>Only administrators can have backend page access</h3>
			</div>
			<div id='page_access_checkboxes'>
				<h3>Pages the user can access</h3>
	%method:pageAccess%
			</div>
		</div>
	</div>
</div>