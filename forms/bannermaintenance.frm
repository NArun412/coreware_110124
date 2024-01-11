<div class="tabbed-form">
	<ul>
		<li><a href="#tab_1">Details</a></li>
		<li><a href="#tab_2">Context</a></li>
	</ul>

	<div id="tab_1">

%field:banner_code,description,content,css_classes,image_id,link_url,domain_name%
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

%field:sort_order,use_content,hide_description,internal_use_only,inactive,banner_tag_links%
%basic_form_line%
%end repeat%

	</div>

	<div id="tab_2">
%field:banner_context,banner_group_links%
%basic_form_line%
%end repeat%

	</div>
</div>
