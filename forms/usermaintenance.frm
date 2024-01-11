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
		<li><a href="#tab_3">%programText:Security%</a></li>
		<li><a href="#custom_tab">%programText:Custom%</a></li>
		<li><a href="#member_tab">Member</a></li>
		<li><a href="#tab_4">%programText:Groups%</a></li>
		<li><a href="#tab_5">%programText:Access%</a></li>
	</ul>

	<div id="tab_1">

%field:user_name%
<div class="basic-form-line" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div id='simulate_user_wrapper'></div>
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>

</div>

%field:user_name_alias,contacts.first_name,last_name,business_name,email_address,date_created,user_type_id%
%basic_form_line%
%end repeat%

%field:administrator_flag,last_login,last_login_location,last_password_change,force_password_change%
%basic_form_line%
%end repeat%

%if:$GLOBALS['gUserRow']['superuser_flag'] || $GLOBALS['gUserRow']['full_client_access']%
%field:full_client_access%
%basic_form_line%
%endif%

%if:$GLOBALS['gUserRow']['superuser_flag'] && (!empty(getPreference('ALLOW_SUPERUSER_CREATION')) || (strpos($GLOBALS['gUserRow']['email_address'],'coreware.com') !== false || $GLOBALS['gUserId'] <= 10001))%
%field:superuser_flag%
%basic_form_line%
%endif%

%field:security_level_id,language_id,locked,inactive%
%basic_form_line%
%end repeat%

	</div>

	<div id="tab_2">
<div id="_contact_left_column" class="shorter-label">

%method:addCustomFieldsBeforeAddress%
%field:address_1,address_2%
%basic_form_line%
%end repeat%

%field:city%
<div class="basic-form-line" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
%if:$thisColumn && $thisColumn->getControlValue('help_label')%
	<span class="help-label">%help_label%</span>
%endif%
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
%method:addCustomFieldsAfterAddress%

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
		<div id="_security_non_sso">
%field:password,security_question_id,answer_text,secondary_security_question_id,secondary_answer_text%
%basic_form_line%
%end repeat%
</div>
<div id="_security_sso" class="hidden">
	This user's login is handled by Single Sign-On (SSO).
</div>
%if:in_array('CORE_DEVELOPER',$GLOBALS['gClientSubsystemCodes']) && canAccessPageCode("DEVELOPERMAINT")%
		<h3>Developer (API) Access</h3>
		<div id="_security_developer">
%field:connection_key_display%
%basic_form_line%
		</div>
		<div id="_security_create_developer" class="hidden"><button id='create_developer'>Create Developer record</button></div>
%endif%

	</div>

	<div id="custom_tab">
%method:addCustomFields%
	</div>

	<div id="member_tab">
%method:membershipFields%
	</div>

	<div id="tab_4">

%field:user_group_members,user_attributions,company_id%
%basic_form_line%
%end repeat%

	</div>

	<div id="tab_5">

%field:user_subsystem_access,user_access,user_function_uses,user_page_functions,page_section_user_access%
%basic_form_line%
%end repeat%

	</div>

</div>

</div>
