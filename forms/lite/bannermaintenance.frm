<div id="_maintenance_form">

%field:description,content,image_id,link_url%
%basic_form_line%
%end repeat%

%field:start_date%
<div class="basic-form-line" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
%field:start_time_part%
	<label for="%column_name%" class="second-label">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

%field:end_date%
<div class="basic-form-line" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
%field:end_time_part%
	<label for="%column_name%" class="second-label">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

%field:sort_order,use_content,hide_description,internal_use_only,inactive%
%basic_form_line%
%end repeat%

%field:banner_group_links%
%basic_form_line%
%end repeat%

</div>
