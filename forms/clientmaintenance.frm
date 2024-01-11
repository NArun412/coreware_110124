<div id="_maintenance_form">
%field:business_name%
%basic_form_line%

<div class="accordion-form">
	<ul class="tab-control-element">
		<li><a href="#tab_1">%programText:Details%</a></li>
		<li><a href="#tab_2">%programText:Custom%</a></li>
		<li><a href="#tab_4">%programText:Pages%</a></li>
		<li><a href="#tab_5">%programText:Users%</a></li>
	</ul>

<h3 class="accordion-control-element">%programText:Details%</h3>
	<div id="tab_1">
<div id="_contact_left_column" class="shorter-label">

%field:client_code,client_type_id%
%basic_form_line%
%end repeat%

%method:addCustomNameFields%

%field:first_name,last_name%
%basic_form_line%
%end repeat%

%method:addCustomFieldsBeforeAddress%

%field:address_1,address_2,city,city_select,state,postal_code,country_id%
%basic_form_line%
%end repeat%

%method:addCustomFieldsAfterAddress%
</div>

<div id="_contact_right_column" class="short-label">

%field:phone_numbers,email_address,contact_emails,web_page%
%basic_form_line%
%end repeat%

%method:addCustomContactFields%

%field:start_date,cancellation_date,logo_image_id,development,inactive%
%basic_form_line%
%end repeat%

</div>
<div class='clear-div'></div>

%field:notes%
%basic_form_line%

	<div class='clear-div'></div>
	</div>

<h3 class="accordion-control-element">%programText:Custom%</h3>
	<div id="tab_2">
%method:addCustomFields%
	</div>

<h3 class="accordion-control-element">%programText:Pages%</h3>
	<div id="tab_4">
%field:client_access,client_subsystems,page_section_client_access%
%basic_form_line%
%end repeat%

	</div>

<h3 class="accordion-control-element">%programText:Users%</h3>
	<div id="tab_5">
<h2>Add a User</h2>

%field:new_user_user_name%
<div class="basic-form-line" id="_%column_name%_row">
	%input_control%
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	<span class="help-label">%help_label%</span><span class='field-error-text'></span>
	<span class="extra-info" id="_user_name_message"></span>
</div>

%field:new_user_password,new_user_first_name,new_user_last_name,new_user_email_address,new_user_administrator_flag,new_user_force_password_change%
%basic_form_line%
%end repeat%

<div id="client_users">
</div>
	</div>
</div>

</div>
