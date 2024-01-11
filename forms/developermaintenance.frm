<div class="tabbed-form">
	<ul>
		<li><a href="#tab_1">Developer</a></li>
		<li><a href="#tab_2">Contact</a></li>
		<li><a href="#tab_3">Access</a></li>
	</ul>
	<div id="tab_1">
%field:business_name,first_name,last_name,date_created%
%basic_form_line%
%end repeat%

%field:users_user_id%
<input type="hidden" id="users_user_id" name="users_user_id">
%field:contact_un%
<div class="basic-form-line" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<span class="extra-info" id="_user_name_message"></span>
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

%field:contact_pw%
%basic_form_line%
%endif%

%field:connection_key%
<div class="basic-form-line" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<span class="extra-info"><button id="_get_new_connection_key">Regenerate</button></span>
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>
%field:connection_limit,test_account,inactive%
%basic_form_line%
%end repeat%

	</div>
	<div id="tab_2">
%field:address_1,address_2,city,city_select,state,postal_code,country_id,phone_numbers,email_address,web_page%
%basic_form_line%
%end repeat%

	</div>
	<div id="tab_3">
%field:user_id,developer_api_method_groups,developer_api_methods,developer_ip_addresses%
%basic_form_line%
%end repeat%
	</div>
</div>
