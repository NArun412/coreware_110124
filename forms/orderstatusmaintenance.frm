<div id="_maintenance_form">

%field:order_status_code,description,email_id,resend_days,display_color,sort_order,mark_completed%
%basic_form_line%
%end repeat%

%method:addCustomFields%

%field:internal_use_only,inactive,order_status_notifications%
%basic_form_line%
%end repeat%

</div> <!-- maintenance_form -->
