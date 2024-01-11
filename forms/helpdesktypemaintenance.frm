<div id="_maintenance_form">

%field:description%
%basic_form_line%

<div class="accordion-form">

	<ul class="tab-control-element">
		<li><a href="#tab_1">%programText:Details%</a></li>
		<li><a href="#tab_2">%programText:Notifications%</a></li>
		<li><a href="#tab_3">%programText:Categories%</a></li>
		<li><a href="#tab_4">%programText:Data%</a></li>
	</ul>

<h3 class="accordion-control-element">Details</h3>
	<div id="tab_1">

%field:help_desk_type_code,user_id,user_group_id,priority,sort_order,internal_use_only,inactive%
%basic_form_line%
%end repeat%

	</div>

<h3 class="accordion-control-element">Notifications</h3>
	<div id="tab_2">
%field:email_address,email_credential_id,response_content,email_id%
%basic_form_line%
%end repeat%

<p>Notifications of any activity on the Help Desk Entry will be sent to the user to whom it is assigned. These notifications can also be sent to the user group and to additional email addresses.</p>

%field:notify_user_group,help_desk_type_notifications%
%basic_form_line%
%end repeat%

	</div>

<h3 class="accordion-control-element">Categories</h3>
	<div id="tab_3">
%field:help_desk_type_categories%
%basic_form_line%
	</div>

<h3 class="accordion-control-element">Data</h3>
	<div id="tab_4">
%field:help_desk_type_custom_fields%
%basic_form_line%
	</div>

</div> <!-- accordion-form -->
</div> <!-- maintenance_list -->
