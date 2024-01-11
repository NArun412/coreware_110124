<div id="_maintenance_form">
<p id="_contact_display"></p>
<div class="tabbed-form" id="main_tab_panel">
	<ul id="main_tabs">
		<li><a href="#summary_tab">Summary</a></li>
		<li><a href="#contact_tab" class='new-record-tab'>Contact</a></li>
%if:canAccessPageSection('details')%
		<li><a href="#details_tab">Details</a></li>
%endif%
%if:canAccessPageSection('addresses')%
		<li><a href="#addresses_tab">Addresses</a></li>
%endif%
%if:canAccessPageSection('custom')%
		<li><a href="#custom_tab">Custom</a></li>
%endif%
%if:canAccessPageSection('member')%
		<li><a href="#member_tab">Member</a></li>
%endif%
%if:canAccessPageSection('touchpoints')%
		<li><a href="#touchpoints_tab">Touchpoints</a></li>
%endif%
%if:canAccessPageSection('forms')%
		<li><a href="#forms_tab">Forms</a></li>
%endif%
%if:canAccessPageSection('relationships') && $GLOBALS['gPageObject']->hasRelationshipTypes()%
		<li><a href="#relationships_tab">Relationships</a></li>
%endif%
%if:canAccessPageSection('donations') && $GLOBALS['gPageObject']->hasDonations()%
		<li><a href="#donations_tab">Donations</a></li>
%endif%
%if:canAccessPageSection('recurring') && $GLOBALS['gPageObject']->hasRecurringDonations()%
		<li><a href="#recurring_tab">Recurring</a></li>
%endif%
%if:canAccessPageSection('accounts') && $GLOBALS['gPageObject']->hasPaymentMethods()%
		<li><a href="#accounts_tab">Accounts</a></li>
%endif%
%if:canAccessPageSection('orders')%
		<li><a href="#orders_tab">Orders</a></li>
%endif%
%if:canAccessPageSection('events')%
		<li><a href="#events_tab">Events</a></li>
%endif%
	</ul>

	<div id="summary_tab">
%method:generateSummary%
	</div>

	<div id="contact_tab">
<input type="hidden" id="date_created" name="date_created" />

<div id="_name_table">

<div class='basic-form-line inline-block'>
%field:title%
<div><label for="%column_name%" class="%label_class%">%form_label%</label></div>
<div>%input_control%</div>
</div>

<div class='basic-form-line inline-block'>
%field:first_name%
<div><label for="%column_name%" class="%label_class%">%form_label%</label></div>
<div>%input_control%</div>
</div>

<div class='basic-form-line inline-block'>
%field:middle_name%
<div><label for="%column_name%" class="%label_class%">%form_label%</label></div>
<div>%input_control%</div>
</div>

<div class='basic-form-line inline-block'>
%field:last_name%
<div><label for="%column_name%" class="%label_class%">%form_label%</label></div>
<div>%input_control%</div>
</div>

<div class='basic-form-line inline-block'>
%field:suffix%
<div><label for="%column_name%" class="%label_class%">%form_label%</label></div>
<div>%input_control%</div>
</div>

</div> <!-- name_table -->

<div id="_contact_left_column" class="shorter-label">
%method:addCustomNameFields%
%field:salutation%
%basic_form_line%
%field:preferred_first_name%
%basic_form_line%
%field:alternate_name%
%basic_form_line%
%field:business_name%
%basic_form_line%
%field:company_id%
%basic_form_line%
%field:job_title%
%basic_form_line%
%method:addCustomFieldsBeforeAddress%
%field:address_1%
%basic_form_line%
%field:address_2%
%basic_form_line%
%field:city%
%basic_form_line%
%field:city_select%
%basic_form_line%
%field:state%
%basic_form_line%
%field:postal_code%
%basic_form_line%
%field:country_id%
%basic_form_line%
%method:addCustomFieldsAfterAddress%
%field:attention_line%
%basic_form_line%
</div>

<div id="_contact_right_column" class="shortest-label">
%field:phone_numbers%
<div class="basic-form-line" id="_%column_name%_row">
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

%field:email_address%
%basic_form_line%
<p id="duplicate_message"></p>

%field:contact_emails%
<div class="basic-form-line" id="_%column_name%_row">
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

%field:web_page,latitude,longitude%
%basic_form_line%
%end repeat%

%method:addCustomContactFields%
</div>
<div class='clear-div'></div>
	</div>

%if:canAccessPageSection('details')%
	<div id="details_tab">
%if:$GLOBALS['gUserRow']['administrator_flag']%

%if:canAccessPageCode("USERMAINT")%
%field:user_id%
<input type="hidden" id="user_id" name="user_id">
%field:contact_un%
<div class="basic-form-line" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<span class="extra-info" id="_user_name_message"></span>
	<div id='simulate_user_wrapper'></div>
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

<div id="_contact_pw_non_sso">
%field:contact_pw%
%basic_form_line%
</div>
<div id="_contact_pw_sso" class="hidden">
	This user's login is handled by Single Sign-On (SSO).
</div>
%endif%

<div id='create_contact_user'></div>

<div id="loyalty_points">
</div>

%field:responsible_user_id%
%basic_form_line%
%endif%

%field:private_access,timezone_id,contact_type_id,source_id%
%basic_form_line%
%end repeat%

%field:birthdate%
<div class="basic-form-line" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%<span id='age' class="extra-info">Age: <span id="calculated_age">23</span></span>
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

%field:image_id,contact_files,contact_identifiers,notes%
%basic_form_line%
%end repeat%

	</div>
%endif%

%if:canAccessPageSection('addresses')%
	<div id="addresses_tab">
%field:addresses%
<div class="basic-form-line" id="_%column_name%_row">
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>
	</div>
%endif%

%if:canAccessPageSection('custom')%
	<div id="custom_tab">
%method:addCustomFields%
	</div>
%endif%

%if:canAccessPageSection('member')%
	<div id="member_tab">
%method:membershipFields%

%if:$GLOBALS['gPageObject']->hasSubscriptions()%
%field:contact_subscriptions%
%basic_form_line%
%endif%

<div id='subscription_links'>
</div>

	</div>
%endif%

%if:canAccessPageSection('touchpoints')%
	<div id="touchpoints_tab">
<h2>Touchpoints</h2>
%field:touchpoints%
<div class="basic-form-line" id="_%column_name%_row">
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

<h2>Help Desk Tickets</h2>
<div id="help_desk_entries"></div>

<h2>Emails Sent</h2>
<div id="emails_sent"></div>

	</div>
%endif%

%if:canAccessPageSection('forms')%
	<div id="forms_tab">
	</div>
%endif%

%if:canAccessPageSection('relationships') && $GLOBALS['gPageObject']->hasRelationshipTypes()%
	<div id="relationships_tab">

%field:relationships%
<div class="basic-form-line" id="_%column_name%_row">
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

	<div id="reverse_relationships">
	</div>
	</div>

%endif%

%if:canAccessPageSection('donations') && $GLOBALS['gPageObject']->hasDonations()%

	<div id="donations_tab">

%field:donation_commitments%
%basic_form_line%

%field:recurring_donations%
<input type="hidden" id="reverse_donation_ids" name="reverse_donation_ids">
<div id="donations_tab_contents">
</div>

	</div>
%endif%

%if:canAccessPageSection('recurring') && $GLOBALS['gPageObject']->hasRecurringDonations()%
	<div id="recurring_tab">
%field:recurring_donations%
%basic_form_line%
	</div>
%endif%

%if:canAccessPageSection('accounts') && $GLOBALS['gPageObject']->hasPaymentMethods()%
	<div id="accounts_tab">
	<p>Adding an account here does not add it to the merchant gateway.</p>
%field:accounts%
%input_control%
	</div>
%endif%

%if:canAccessPageSection('orders')%
	<div id="orders_tab">
	</div>
%endif%

%if:canAccessPageSection('events')%
	<div id="events_tab">
	    <div id='completed_events'>
	    </div>

%field:contact_event_types%
%basic_form_line%

		<div id='regenerate_certificates_wrapper'></div>

%field:contact_certifications%
%basic_form_line%

	</div>
%endif%

</div>
</div>
