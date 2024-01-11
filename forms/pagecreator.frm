%field:description%
<div class="basic-form-line" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<button id="go_to_page" accesskey="g">View Page</button>
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

<div class="tabbed-form">
	<ul>
		<li><a href="#tab_1">Details</a></li>
		<li><a href="#tab_2">Menu</a></li>
		<li><a href="#tab_3">CSS</a></li>
		<li><a href="#tab_5">Data</a></li>
	</ul>

	<div id="tab_1">
%field:date_created,creator_user_id%
%basic_form_line%
%end repeat%

%field:meta_description%
<div class="basic-form-line" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<span id="_meta_description_character_count" class="extra-info"></span>
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

%field:meta_keywords,link_name,template_id,public_access,inactive%
%basic_form_line%
%end repeat%

	</div>

	<div id="tab_2">
%field:link_title,menu_id%
%basic_form_line%
%end repeat%

%field:menu_position%
<div class="basic-form-line" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<span class="extra-info">(leave blank to make last item)</span>
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>
	</div>

	<div id="tab_3">
%field:css_content%
%basic_form_line%
	</div>

	<div id="tab_5">
		<div id="page_instructions">
		</div>

		<div id="page_data_div">
		</div>
	</div>

</div>
