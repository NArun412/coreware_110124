<div id="_maintenance_form">

%field:description%
%basic_form_line%

<div class="accordion-form">
	<ul class="tab-control-element">
		<li><a href="#tab_1">Details</a></li>
		<li><a href="#tab_2">Form</a></li>
		<li><a href="#tab_3">Javascript</a></li>
		<li><a href="#tab_4">Introduction</a></li>
		<li><a href="#tab_5">Response</a></li>
		<li><a href="#tab_status">Status</a></li>
		<li><a href="#tab_6">Controls</a></li>
		<li><a href="#tab_7">Files</a></li>
		<li><a href="#tab_8">Notifications</a></li>
		<li><a href="#tab_9">Contacts</a></li>
		<li><a href="#tab_10">Payment</a></li>
		<li><a href="#tab_11">Submissions</a></li>
	</ul>

<h3 class="accordion-control-element">Details</h3>
	<div id="tab_1">

%field:form_definition_code,date_created,creator_user_id%
%basic_form_line%
%end repeat%

%if:!empty($GLOBALS['gUserRow']['superuser_flag'])%
%field:form_filename,action_filename%
%basic_form_line%
%end repeat%
%endif%

%field:parent_form_required,user_group_id,auto_delete_days,expiration_days,expiration_email_id,use_captcha,save_progress,create_contact_pdf,internal_use_only,inactive%
%basic_form_line%
%end repeat%

	</div>

<h3 class="accordion-control-element">Form</h3>
	<div id="tab_2">

%field:css_file_id,form_description,form_field_tags%
%basic_form_line%
%end repeat%

<p class="form-builder-content">This form was built in the form builder. Converting it to manual editing cannot be reversed.</p>
<p class="form-builder-content"><button id="convert_content">Convert to Manual Form</button></p>
<p class="open-form-builder"><button id="open_form_builder">Open in Form Builder</button></p>
<p class="form-builder-instructions">Once the form definition is created, it can be opened in Form Builder.</p>

%field:form_content%
%basic_form_line%
	</div>

<h3 class="accordion-control-element">Javascript</h3>
	<div id="tab_3">

%field:javascript_code%
%basic_form_line%
	</div>

<h3 class="accordion-control-element">Introduction</h3>
	<div id="tab_4">

%field:introduction_content%
%basic_form_line%
	</div>

<h3 class="accordion-control-element">Response</h3>
	<div id="tab_5">

%field:response_content%
%basic_form_line%
	</div>

<h3 class="accordion-control-element">Status</h3>
	<div id="tab_status">

%field:form_definition_status,form_definition_group_links%
%basic_form_line%
%end repeat%
	</div>

<h3 class="accordion-control-element">Controls</h3>
	<div id="tab_6">

%field:form_definition_controls%
%basic_form_line%
	</div>

<h3 class="accordion-control-element">Files</h3>
	<div id="tab_7">

%field:form_definition_files%
%basic_form_line%
	</div>

<h3 class="accordion-control-element">Notifications</h3>
	<div id="tab_8">

%field:email_credential_id,email_id,parent_form_email_id,form_definition_emails%
%basic_form_line%
%end repeat%

	</div>

<h3 class="accordion-control-element">Contacts</h3>
	<div id="tab_9">

%field:category_id,remove_category_id,contact_type_id,form_definition_mailing_lists%
%basic_form_line%
%end repeat%

<p>In order to create a contact for each form submission, Country ID field needs to be defined. Other fields should also be defined. Phone numbers will not be created unless a contact is created.</p>
<p>When 'Use User Contact' is selected, the logged in user will be the contact of the submitted form. Contact fields will be prefilled with the user's information and these fields will be readonly. If no user is logged in, the form will create a contact.</p>

%field:use_user_contact,form_definition_contact_fields,form_definition_phone_number_fields,form_definition_contact_identifiers%
%basic_form_line%
%end repeat%

	</div>

<h3 class="accordion-control-element">Payment</h3>
	<div id="tab_10">

<p>In order for a payment section to appear on the form, some minimum requirements must be met:</p>
<ul class="disc-list">
	<li>The designation or product ID must be specified below and active.</li>
	<li>Either a user must be logged in or the form needs to contain first name, last name and email address and these need to be set to be written to the contacts table.</li>
	<li>The payment block must be placed in the form in Form Builder.</li>
</ul>
<p>Payment will be required if either the amount is preset or the payment required flag is set.</p>

%field:designation_id,product_id,payment_required,amount,form_field_payments,form_definition_discounts%
%basic_form_line%
%end repeat%

	</div>

<h3 class="accordion-control-element">Submissions</h3>
	<div id="tab_11">

%field:submitted_count%
%basic_form_line%

	<p><button id="export_submissions">Export Submitted Forms</button></p>
	</div>
</div>
