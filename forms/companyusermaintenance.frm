<div class="tabbed-form">
	<ul>
		<li><a href="#tab_1">%programText:Details%</a></li>
		<li><a href="#tab_2">%programText:Contact%</a></li>
		<li><a href="#tab_3">%programText:Security%</a></li>
		<li><a href="#tab_4">%programText:Access%</a></li>
	</ul>
	<div id="tab_1">
%field:user_name,contacts.first_name,last_name,business_name,email_address,date_created,administrator_flag,last_login,last_password_change,force_password_change,locked,inactive%
%basic_form_line%
%end repeat%
	</div>

	<div id="tab_2">
<div id="_contact_left_column" class="shorter-label">
%field:address_1,address_2%
%basic_form_line%
%end repeat%

%field:city%
<div class="basic-form-line" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
%input_control%
%field:city_select%
%input_control%
%field:state%
&nbsp;%input_control%
%field:postal_code%
&nbsp;%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>
%field:country_id%
%basic_form_line%
</div>
<div id="_contact_right_column" class="shortest-label">
%field:phone_numbers%
<div class="basic-form-line" id="_%column_name%_row">
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>
</div>
<div class='clear-div'></div>
	</div>

	<div id="tab_3">
%field:password,security_question_id,answer_text,secondary_security_question_id,secondary_answer_text%
%basic_form_line%
%end repeat%

	</div>

	<div id="tab_4">
%field:user_group_members%
%basic_form_line%

	</div>
</div>
