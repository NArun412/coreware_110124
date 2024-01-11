<div id="_maintenance_form">

%field:donation_date%
%basic_form_line%

<input type="hidden" name="contact_id" id="contact_id">
<div id="donor_info">
</div>

<div class="basic-form-line">
	<label>Previous Donations</label>
	<div id="previous_donations" class='data-content'></div>
</div>

<div class="basic-form-line">
	<label></label>
	<button id="view_donations">View All Previous Donations</button>
</div>

%field:amount,anonymous_gift,designation_id%
%basic_form_line%
%end repeat%

<div class="basic-form-line" id="_designation_message_row">
	<span id="designation_message" class='data-content'></span>
</div>

%field:project_name,notes%
%basic_form_line%
%end repeat%

</div> <!-- maintenance_form -->
