<div class="form-line %form_line_classes%" id="_%column_name%_row" data-column_name="%column_name%">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
%if:$thisColumn && $thisColumn->getControlValue('help_label')%
	<span class="help-label">%help_label%</span>
%endif%
	%input_control%
	<div class='clear-div'></div>
</div>
