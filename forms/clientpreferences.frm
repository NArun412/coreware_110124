<div id="_maintenance_form">
%field:preference_id,preference_qualifier%
%basic_form_line%
%end repeat%

%field:preference_value%
<div class="basic-form-line" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	<div id="preference_value_control"></div>
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

%field:system_value%
%basic_form_line%

</div> <!-- maintenance_form -->
