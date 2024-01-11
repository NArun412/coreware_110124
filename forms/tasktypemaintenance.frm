%field:description%
%basic_form_line%
<div class="tabbed-form">
	<ul>
		<li><a href="#tab_1">Details</a></li>
		<li><a href="#tab_2">Access</a></li>
		<li><a href="#tab_3">Custom Data</a></li>
		<li><a href="#tab_4">Attributes</a></li>
	</ul>
	<div id="tab_1">
%field:task_type_code,responsible_user_id,user_id,user_group_id,display_color,sort_order,internal_use_only,inactive%
%basic_form_line%
%end repeat%

	</div>
	<div id="tab_2">
<p class="subheader">If both users and user groups access are empty, the task type is usable by everyone.</p>
%field:task_type_users,task_type_user_groups%
%basic_form_line%
%end repeat%

	</div>
	<div id="tab_3">
%field:task_type_data%
%basic_form_line%
	</div>
	<div id="tab_4">
%method:taskTypeAttributes%
	</div>
</div>
