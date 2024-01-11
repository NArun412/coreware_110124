%field:designation_code,description%
%basic_form_line%
%end repeat%

<div class="tabbed-form">
	<ul>
		<li><a href="#tab_1">Details</a></li>
		<li><a href="#tab_2">Contact</a></li>
		<li><a href="#tab_3">Accounting</a></li>
		<li><a href="#tab_4">Payroll</a></li>
		<li><a href="#tab_5">Projects</a></li>
		<li><a href="#tab_6">Files</a></li>
		<li><a href="#tab_7">Custom</a></li>
	</ul>

	<div id="tab_1">
%field:detailed_description,alias,designation_type_id,designation_group_links,email_id,date_created,sort_order,public_access,requires_attention,not_tax_deductible,internal_use_only,inactive,end_recurring_gifts,expenses_button%
%basic_form_line%
%end repeat%

	<div class='clear-div'></div>
	</div>

	<div id="tab_2">

%field:designation_users,contact_id,designation_email_addresses,opt_out_emails,link_name,image_id,notes%
%basic_form_line%
%end repeat%

	<div class='clear-div'></div>
	</div>

	<div id="tab_3">

%field:merchant_account_id,gl_account_number,class_code,full_name,secondary_class_code,secondary_full_name%
%basic_form_line%
%end repeat%

	<div class='clear-div'></div>
	</div>

	<div id="tab_4">

%field:payroll_group_id%
%basic_form_line%

<div id="payroll_information">

<div id="direct_debit_info">
%field:account_number,routing_number,account_type%
%basic_form_line%
%end repeat%
</div>

<div id="check_info">
%field:address_text%
%basic_form_line%
</div>
</div>

%field:designation_deductions%
%basic_form_line%

	<div class='clear-div'></div>
	</div>

	<div id="tab_5">

%field:designation_giving_goals,project_label,project_required,designation_projects,memo_label,memo_required%
%basic_form_line%
%end repeat%

	<div class='clear-div'></div>
	</div>

	<div id="tab_6">

%field:designation_files%
%basic_form_line%

	<div class='clear-div'></div>
	</div>

	<div id="tab_7">

%method:addCustomFields%

	</div>

</div> <!-- tabbed-form -->
