<div id="_maintenance_form">
%field:user_id%
%basic_form_line%
%field:date_entered%
%basic_form_line%
%field:start_time%
%basic_form_line%
%field:set_start_time%
<div class="basic-form-line" id="_set_start_time_row">
	<label for="set_start_hour">Set Start Time</label>
	<select id="start_time_hour" name="start_time_hour">
		<option value="">[Select]</option>
		<option value="00">12 midnight</option>
		<option value="01">1 am</option>
		<option value="02">2 am</option>
		<option value="03">3 am</option>
		<option value="04">4 am</option>
		<option value="05">5 am</option>
		<option value="06">6 am</option>
		<option value="07">7 am</option>
		<option value="08">8 am</option>
		<option value="09">9 am</option>
		<option value="10">10 am</option>
		<option value="11">11 am</option>
		<option value="12">12 noon</option>
		<option value="13">1 pm</option>
		<option value="14">2 pm</option>
		<option value="15">3 pm</option>
		<option value="16">4 pm</option>
		<option value="17">5 pm</option>
		<option value="18">6 pm</option>
		<option value="19">7 pm</option>
		<option value="20">8 pm</option>
		<option value="21">9 pm</option>
		<option value="22">10 pm</option>
		<option value="23">11 pm</option>
	</select>:<select id="start_time_minute" name="start_time_minute">
		<option value="">[Select]</option>
%method:minuteOptions%
	</select>
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>
%field:end_time%
<div class="basic-form-line" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>
%field:set_end_time%
<div class="basic-form-line" id="_set_end_time_row">
	<label for="end_time_hour">Set End Time</label>
	<select id="end_time_hour" name="end_time_hour">
		<option value="">[Select]</option>
		<option value="00">12 midnight</option>
		<option value="01">1 am</option>
		<option value="02">2 am</option>
		<option value="03">3 am</option>
		<option value="04">4 am</option>
		<option value="05">5 am</option>
		<option value="06">6 am</option>
		<option value="07">7 am</option>
		<option value="08">8 am</option>
		<option value="09">9 am</option>
		<option value="10">10 am</option>
		<option value="11">11 am</option>
		<option value="12">12 noon</option>
		<option value="13">1 pm</option>
		<option value="14">2 pm</option>
		<option value="15">3 pm</option>
		<option value="16">4 pm</option>
		<option value="17">5 pm</option>
		<option value="18">6 pm</option>
		<option value="19">7 pm</option>
		<option value="20">8 pm</option>
		<option value="21">9 pm</option>
		<option value="22">10 pm</option>
		<option value="23">11 pm</option>
	</select>:<select id="end_time_minute" name="end_time_minute">
		<option value="">[Select]</option>
%method:minuteOptions%
	</select>
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>
%field:notes%
<div class="basic-form-line" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>
</div> <!-- maintenance_form -->
