<div id="_maintenance_form">
<h2>Contact Information</h2>
%field:first_name,last_name,business_name%
%basic_form_line%
%end repeat%

<h2>Account Information</h2>
%field:account_label,credit_limit,inactive%
%basic_form_line%
%end repeat%

%if:$GLOBALS['gPermissionLevel'] > _READONLY%
%field:credit_account_designations%
%basic_form_line%
%end repeat%
%endif%

<h2>Log</h2>
<div id="account_log">
</div>

%if:$GLOBALS['gPermissionLevel'] > _READONLY%
<div id="log_entry_wrapper">
<h2>Adjustment</h2>
<div id="_add_log_entry_row" class='form-line'>
	<input type='checkbox' tabindex='10' id='add_log_entry' name='add_log_entry' value='1'><label class='checkbox-label' for='add_log_entry'>Make an adjustment</label>
</div>

<div id='log_entry' class='hidden'>

%field:description,entry_type,amount,log_notes%
%basic_form_line%
%end repeat%
</div>
</div>
%endif%

</div> <!-- maintenance_form -->
