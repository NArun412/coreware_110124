%field:description%
%basic_form_line%
<div class="tabbed-form">
	<ul>
		<li><a href="#tab_1">Details</a></li>
		<li><a href="#tab_2">Include</a></li>
		<li><a href="#tab_3">Exclude</a></li>
		<li><a href="#tab_4">Results</a></li>
	</ul>
	<div id="tab_1">
%field:query_definition_code,sort_order,internal_use_only,inactive%
%basic_form_line%
%end repeat%

	</div>
	<div id="tab_2">
%field:include_match_code%
%basic_form_line%
%method:includeControls%
	</div>
	<div id="tab_3">
%field:exclude_match_code%
%basic_form_line%
%method:excludeControls%
	</div>
	<div id="tab_4" class="longer-label">
<div class="basic-form-line" id="_date_last_run_row">
	<label for="date_last_run">Date Last Run</label>
	<input type="text" size="20" class="field-text" id="date_last_run" name="date_last_run" readonly="readonly">
    <div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>
<div class="basic-form-line" id="_contacts_chosen_row">
	<label for="contacts_chosen">Contacts Chosen By This Query</label>
	<input type="text" size="20" class="field-text align-right" id="contacts_chosen" name="contacts_chosen" readonly="readonly">
    <div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>
<div class="basic-form-line" id="_contacts_selected_row">
	<label for="contacts_selected">Contacts Currently Marked as Selected</label>
	<input type="text" size="20" class="field-text align-right" id="contacts_selected" name="contacts_selected" readonly="readonly">
    <div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>
<p><button id="run_query">Run Query</button></p>
<div class="basic-form-line" id="_query_action_row">
	<label for="query_action">Query Action</label>
	<select id="query_action" name="query_action" class="field-text">
		<option value="">[Select]</option>
		<option value="clearselect">Clear All and Select These Contacts</option>
		<option value="select">Select These Contacts</option>
		<option value="unselect">Unselect These Contacts</option>
		<option value="export">Export CSV</option>
	</select>
    <div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>
<p id="query_action_results" class="color-orange"></p>
%if:$GLOBALS['gUserRow']['superuser_flag']%
<p><textarea id="query_statements"></textarea></p>
%endif%
	</div>
</div>
