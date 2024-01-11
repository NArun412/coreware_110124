<div id="_maintenance_form">
<div class="accordion-form">
	<ul class="tab-control-element">
		<li><a href="#tab_0">%programText:Intro%</a></li>
		<li><a href="#tab_1">%programText:Contact%</a></li>
		<li><a href="#tab_2">%programText:Email%</a></li>
		<li><a href="#tab_5">%programText:Designations%</a></li>
		<li><a href="#tab_7">%programText:Merchant%</a></li>
		<li><a href="#tab_8">%programText:Privacy%</a></li>
		<li><a href="#tab_9">%programText:Refund%</a></li>
		<li><a href="#tab_11">%programText:Recurring%</a></li>
		<li><a href="#tab_12">%programText:Opt-In%</a></li>
		<li><a href="#tab_13">%programText:Users%</a></li>
		<li><a href="#tab_emails">%programText:Emails%</a></li>
		<li><a href="#tab_notifications">%programText:Notifications%</a></li>
		<li><a href="#tab_fragments">%programText:Fragments%</a></li>
	</ul>

<h3 class="accordion-control-element">%programText:Intro%</h3>
	<div id="tab_0">
<p>Welcome to the Coreware Donor Management System. With this program, you'll be able to set up many of the features and capabilities of the system. Full documentation for the DMS is available <a href="https://www.coreware.com/documentation" target="_blank">here</a>.</p>
<p>If you are building your website in Coreware's world class CMS or just putting your giving pages into the system, a template will be needed. Talk to your Coreware representative to get this completed.</p>
<p>Public giving pages can be created for any set of donations: a designation group, a designation type, or even a single designation.</p>
<p>You can safely run this program any number of times.</p>

	</div>

<h3 class="accordion-control-element">%programText:Details%</h3>
	<div id="tab_1">
<div id="_contact_left_column" class="shorter-label">

%field:business_name,first_name,last_name,address_1,address_2,city,city_select,state,postal_code,country_id%
%basic_form_line%
%end repeat%

</div> <!-- _contact_left_column -->

<div id="_contact_right_column" class="short-label">

%field:phone_numbers%
<div class="basic-form-line" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

%field:email_address,web_page,logo_image_id%
%basic_form_line%
%end repeat%

</div> <!-- _contact_right_column -->
	<div class='clear-div'></div>
</div> <!-- tab_1 -->

<h3 class="accordion-control-element">%programText:Email%</h3>
<div id="tab_2">
<p>The system needs email credentials for sending receipts, notifications and other emails. Please enter credentials for your default email sending account. You can manage this or create others at System->Preferences->EMail Credentials. If you are using GMail as your email provider, you MUST turn on the option to "Allow Less Secure Apps". Google sees a server as "less secure" because it doesn't implement two-factor authentication. You can do that <a href="https://myaccount.google.com/lesssecureapps">here</a>. Once the email credentials are saved, you can test sending an email at Contacts->Email->Send Email.</p>

%field:setup_email_credentials%
%basic_form_line%

%field:email_credentials_full_name%
<div class="basic-form-line email-credential hidden" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

%field:email_credentials_email_address%
<div class="basic-form-line email-credential hidden" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

%field:email_credentials_smtp_host%
<div class="basic-form-line email-credential hidden" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

%field:email_credentials_smtp_port%
<div class="basic-form-line email-credential hidden" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

%field:email_credentials_security_setting%
<div class="basic-form-line email-credential hidden" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

%field:email_credentials_smtp_authentication_type%
<div class="basic-form-line email-credential hidden" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

%field:email_credentials_smtp_user_name%
<div class="basic-form-line email-credential hidden" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

%field:email_credentials_smtp_password%
<div class="basic-form-line email-credential hidden" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

</div> <!-- tab_2 -->

<h3 class="accordion-control-element">%programText:Designations%</h3>
<div id="tab_5">
<p>Designations can be created using the Designations program at Donor Management->Designations. If you have many designations, they can be imported. If this is a need, please coordinate with Coreware staff.</p>
<p>Designation Types segment designations based on how payment is made for the designations and whether the designation is for individual support. Every designation is assigned one and only one designation type. A typical setup is to have the following designation types: Corporate, Staff and, if the organization has schools, Tuition. Other settings for these designation types can be made at Donor Management->Designations->Designation Types.</p>

%field:designation_types%
%basic_form_line%

<p>Designation Groups are a way to group designations. A designation can be in any number of designation groups. Designation groups are a convenient way to set up public giving pages. Other settings for designation groups can be made at Donor Management->Designations->Designation Groups.</p>

%field:designation_groups%
%basic_form_line%

</div> <!-- tab_5 -->

<h3 class="accordion-control-element">%programText:Merchant%</h3>
<div id="tab_7">

<p>A merchant services account is required to receive and process donations. Please enter that information below.</p>
<input type="hidden" id="merchant_account_id" name="merchant_account_id">

%field:setup_merchant_account%
%basic_form_line%

%field:merchant_accounts_merchant_service_id%
<div class="basic-form-line merchant-account hidden" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

%field:merchant_accounts_account_login%
<div class="basic-form-line merchant-account hidden" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

%field:merchant_accounts_account_key%
<div class="basic-form-line merchant-account hidden" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

%field:merchant_accounts_merchant_identifier%
<div class="basic-form-line merchant-account hidden" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

<p id="test_merchant_results" class="merchant-account hidden"><button id="test_merchant">Test Merchant Account</button></p>

</div> <!-- tab_7 -->

<h3 class="accordion-control-element">%programText:Privacy%</h3>
<div id="tab_8">
<p>The website requires a privacy policy for merchant services. A sample is provided if you haven't already created one. Make sure to change the placeholders.</p>

%field:privacy_text%
%basic_form_line%

</div> <!-- tab_8 -->

<h3 class="accordion-control-element">%programText:Refund%</h3>
<div id="tab_9">
<p>The website requires a refund policy for merchant services. A sample is provided if you haven't already created one. Make sure to change the placeholders.</p>

%field:refund_text%
%basic_form_line%

</div> <!-- tab_9 -->

<h3 class="accordion-control-element">%programText:Recurring%</h3>
<div id="tab_11">
<p>In order to accept recurring donations on your public giving page, you need some recurring donation types. Monthly is sufficient, but Weekly, Bi-Weekly, Quarterly, Semi-Annually and Annually are also options. The description is displayed on the public giving page, so it should be something friendly like "Recurring Monthly Gift" or "Make this a monthly gift".</p>

%field:recurring_donation_types%
%basic_form_line%

</div> <!-- tab_11 -->

<h3 class="accordion-control-element">%programText:Opt-In%</h3>
<div id="tab_12">
<p>Categories are means of tagging contacts and grouping them. They can also be used as a way of allowing contacts to put themselves into groups or set some preference. A category that is not tagged as internal use only can be selected or unselected by the contact themselves on their "My Account" page. Three categories that are used by the system are codes NO_RECEIPT, PAPER_RECEIPT, and EMAIL_RECEIPT. These are not required, but typically, these are not made internal use only so the donor can choose their receipt preference. Otherwise, you can create categories such as "Church", "Pastor", "Former Staff", etc. Be careful what you leave untagged as internal use only. It will appear on the public "My Account" page.</p>

%field:categories%
%basic_form_line%

<p>Mailing lists are opt-in/out lists, typically used for things like e-Newsletters. Again, those not tagged internal use only will appear on the public "My Account" page.</p>

%field:mailing_lists%
%basic_form_line%

</div> <!-- tab_12 -->

<h3 class="accordion-control-element">%programText:Users%</h3>
<div id="tab_13">
<p>Users are created at Contacts->Users. If you have a lengthy list of users, they can be imported. Contact your Coreware representative for details. When creating users, you have the option to check "Full Client Access". This should only be done on a minimum number of users. Checking this gives the user full access to everything in the system.</p>
<p>We recommend creating User Groups (Contacts->Users->Groups). Users can be assigned to any number of groups and groups can be assigned access to any number of admin pages. This would make assigning a user to a set of applications easy. For instance, front desk personnel might need access to 10 different admin pages. Instead of assigning those 10 pages to each user, assign them to the group and add the users to the group.</p>

</div> <!-- tab_13 -->

<h3 class="accordion-control-element">%programText:Emails%</h3>
<div id="tab_emails">

</div>

<h3 class="accordion-control-element">%programText:Notifications%</h3>
<div id="tab_notifications">

</div>

<h3 class="accordion-control-element">%programText:Fragments%</h3>
<div id="tab_fragments">

</div>

</div> <!-- accordion-form -->
</div> <!-- _maintenance_form -->
