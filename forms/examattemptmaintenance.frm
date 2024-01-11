<div id="_maintenance_form">

%field:email_address,exam_id,class_description%
%basic_form_line%
%end repeat%

%field:start_date%
<div class="basic-form-line inline-block" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

%field:date_completed%
<div class="basic-form-line inline-block" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

<h3>Questions and Essays</h3>
<div id="exam_questions">
</div>

</div> <!-- maintenance_form -->
