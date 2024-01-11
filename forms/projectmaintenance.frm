%field:description%
<div class="basic-form-line" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
	<button id="project_link">Go To Project Page</button>
</div>
<div class="tabbed-form">
	<ul>
		<li><a href="#tab_1">Overview</a></li>
		<li><a href="#tab_2">Details</a></li>
		<li><a href="#tab_3">Members</a></li>
		<li><a href="#tab_4">Milestones</a></li>
		<li><a href="#tab_5">Resources</a></li>
		<li><a href="#tab_6">Custom</a></li>
		<li><a href="#tab_7">Log</a></li>
		<li><a href="#tab_8">Notifications</a></li>
		<li><a href="#tab_9">Tickets</a></li>
	</ul>

	<div id="tab_1">
%field:date_created,user_id,project_type_id,link_name,start_date,date_due,date_completed,leader_user_id,display_color,sort_order,members_only,internal_use_only,inactive%
%basic_form_line%
%end repeat%

	</div>

	<div id="tab_2">
%field:detailed_description,css_file_id,content%
%basic_form_line%
%end repeat%
	</div>

	<div id="tab_3">
<p class="subheader">The following users and groups will be members of the project.</p>
%field:project_users,project_user_groups%
%basic_form_line%
%end repeat%
	</div>

	<div id="tab_4">
%field:project_milestones%
%basic_form_line%
	</div>

	<div id="tab_5">
%field:project_files,project_images%
%basic_form_line%
%end repeat%
	</div>

	<div id="tab_6">
<div id="custom_data"></div>
	</div>

	<div id="tab_7">
<div id="message_board">
</div>
	</div>

	<div id="tab_8">
<p>Notifications will normally go out to the project creator, leader and all members.</p>
%field:project_notifications,project_notification_exclusions%
%basic_form_line%
%end repeat%

	</div>

	<div id="tab_9">
	<p id="_ticket_creation_message"></p>
<div id="ticket_list">
</div>
	</div>
</div>
