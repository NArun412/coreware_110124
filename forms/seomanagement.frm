<h2>Descriptions</h2>
<input type="hidden" id="script_filename" name="script_filename">

%field:description%
%basic_form_line%

%field:meta_description%
<div class="basic-form-line" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<span class="extra-info" id="_meta_description_character_count"></span>
    <div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
	<div class='clear-div'></div>
</div>

%field:meta_keywords,link_name,window_description,window_title%
%basic_form_line%
%end repeat%

<h2>Meta Tags</h2>
%method:requiredMetaTags%

<h2>Images used in page</h2>
<div id="page_images">
</div>

<h2>Page Content</h2>
<div id="page_data">
</div>
