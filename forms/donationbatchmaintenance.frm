<div id="_maintenance_form">

%field:batch_number,batch_date,donation_count,total_donations,user_id,designation_id,donation_source_id,payment_method_id,date_completed,date_posted,date_deposited%
%basic_form_line%
%end repeat%

<div id="donations_section">
<div class='divider'></div>

<div id="donations_entry_section">
<div class="basic-form-line">
	<button id="donations_button">Add/Edit Donations</button><span id="_edit_message"></span>
</div>

<h2>Donation Entry</h2>
<p>Donations entered here will not appear in list until save.</p>
%field:donations_entry%
<div class="basic-form-line" id="_%column_name%_row">
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

<p id="designation_message"></p>
</div>

<h2>Report</h2>
%field:report_type%
<div class="basic-form-line" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>
%field:printable_button%
<div id="_%column_name%_row">
	%input_control%
</div>
%field:pdf_button%
<div id="_%column_name%_row">
	%input_control%
</div>

<div id="_report_title"></div>
<div id="_report_content"></div>
</div>

</div> <!-- maintenance_form -->
