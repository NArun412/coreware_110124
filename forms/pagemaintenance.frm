<div id="_maintenance_form">

%field:description%
<div class="basic-form-line" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
	<div id='action_button_wrapper'>
		<button id="validate_page">Validate</button>
		<button id="speed_page">Speed</button>
		<button id="go_to_page" accesskey="g">Go</button>
	</div>
</div>

<div class="tabbed-form">
	<ul>
		<li><a href="#tab_1">%programText:Details%</a></li>
		<li><a href="#tab_security">%programText:Security%</a></li>
		<li><a href="#tab_2">%programText:Controls%</a></li>
		<li><a href="#tab_properties">%programText:Properties%</a></li>
		<li><a href="#tab_3">%programText:Notifications%</a></li>
		<li><a href="#tab_4">%programText:Functions%</a></li>
		<li><a href="#tab_7">%programText:Javascript%</a></li>
		<li><a href="#tab_8">%programText:CSS%</a></li>
		<li><a href="#tab_9">%programText:Text%</a></li>
		<li><a href="#tab_10">%programText:Data%</a></li>
	</ul>
	<div id="tab_1">
%field:page_code,page_tag,subsystem_id%
%basic_form_line%
%end repeat%

%field:date_created%
<div class="basic-form-line" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<span class="extra-info">By</span>
%field:creator_user_id%
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

%field:link_url%
%basic_form_line%

%field:link_name%
%basic_form_line%

<div id="potential_conflicts"></div>

%field:page_aliases,template_id,script_filename,script_arguments,page_pattern_id,allow_cache,not_searchable,exclude_sitemap,internal_use_only,inactive%
%basic_form_line%
%end repeat%

	</div>

	<div id="tab_security">
%field:publish_start_date,publish_end_date,login_script,domain_name,page_access,requires_ssl,page_maintenance_schedules%
%basic_form_line%
%end repeat%

	</div>

	<div id="tab_2">
%field:page_controls%
%basic_form_line%
	</div>

	<div id="tab_properties">

%field:meta_description%
<div class="basic-form-line" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<span id="_meta_description_character_count" class="extra-info"></span>
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

%field:meta_keywords,window_description,window_title,validation_date,page_meta_tags,header_includes%
%basic_form_line%
%end repeat%

	</div>

	<div id="tab_3">
%field:page_notifications,page_error_notifications,page_data_changes%
%basic_form_line%
%end repeat%

	</div>

	<div id="tab_4">
%field:page_functions,page_sections%
%basic_form_line%
%end repeat%

	</div>

	<div id="tab_7">
%field:remove_analytics,analytics_code_chunk_id,javascript_code%
%basic_form_line%
%end repeat%

	</div>

	<div id="tab_8">
%field:css_file_id,sass_headers,css_content%
%basic_form_line%
%end repeat%

	</div>

	<div id="tab_9">
%field:help_text%
%basic_form_line%

<div id="text_instructions">
</div>

<p>Programs access text chunks by code, so changing the code will require a program change as well.</p>

%field:page_text_chunks%
%basic_form_line%
	</div>

	<div id="tab_10">
<div id="page_instructions">
</div>

<div id="page_data_div">
</div>
	</div>
</div>

</div>
