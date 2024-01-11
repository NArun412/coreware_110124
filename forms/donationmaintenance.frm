<div id="_maintenance_form">

%field:donation_id,batch_number,donation_date,contact_id%
%basic_form_line%
%end repeat%

<div id="from_order"></div>

<div class="basic-form-line">
	<label>Previous Donations</label>
	<div id="previous_donations" class='data-content'></div>
</div>

<div class="basic-form-line">
	<label></label>
	<button id="view_donations">View All Previous Donations</button>
</div>

<div class="basic-form-line" id="_contact_notes_row">
	<label>Contact Notes</label>
	<div id="contact_notes"></div>
</div>

%field:payment_method_id,reference_number,amount,designation_id%
%basic_form_line%
%end repeat%

<div class="basic-form-line" id="_designation_message_row">
	<label></label>
	<span id="designation_message" class='data-content'></span>
</div>

%field:project_name,anonymous_gift,donation_commitment_id,donation_source_id,receipted_contact_id,notes,donation_fee,pay_period_id%
%basic_form_line%
%end repeat%

</div> <!-- maintenance_form -->
