<div id="_maintenance_form">
<input type="hidden" id="time_read" name="time_read">
<input type="hidden" id="time_deleted" name="time_deleted">

%field:creator_user_id,time_submitted%
%basic_form_line%
%end repeat%

%field:acknowledge_acceptance%
<div class="basic-form-line hidden" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
    <div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

%field:subject%
%basic_form_line%

<div id="content">
</div>

</div> <!-- maintenance_form -->
