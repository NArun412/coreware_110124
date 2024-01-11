<div id="_maintenance_form">

%field:contact_id,selected_contacts,description,detailed_description,task_type_id,creator_user_id,requires_response%
%basic_form_line%
%end repeat%

%field:assigned_user_id%
<div class="basic-form-line requires-response hidden" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

%field:date_due%
<div class="basic-form-line requires-response hidden" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

%field:date_completed%
%basic_form_line%

</div> <!-- maintenance_form -->
