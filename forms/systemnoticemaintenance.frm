<div id="_maintenance_form">

%field:subject,content,creator_user_id,time_submitted,start_date,start_time_part,end_date,end_time_part,display_color,user_group_id,all_user_access,full_client_access,require_acceptance%
%basic_form_line%
%end repeat%

%if:$GLOBALS['gUserRow']['superuser_flag']%
%field:all_clients%
%basic_form_line%
%endif%

%field:inactive,system_notice_users%
%basic_form_line%
%end repeat%

</div> <!-- maintenance_form -->
