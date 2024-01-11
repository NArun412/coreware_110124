<div id="_maintenance_form">

%field:background_process_code,description,script_filename%
%basic_form_line%
%end repeat%

<input type="hidden" id="repeat_rules" name="repeat_rules">

%method:intervalFields%

%field:last_start_time,sort_order,run_immediately,internal_use_only,inactive,background_process_notifications%
%basic_form_line%
%end repeat%

</div> <!-- maintenance_form -->
