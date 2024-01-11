<div id="_maintenance_form">

%field:description%
%basic_form_line%

<div class="accordion-form">

	<ul class="tab-control-element">
		<li><a href="#tab_1">%programText:Details%</a></li>
		<li><a href="#tab_2">%programText:Content%</a></li>
		<li><a href="#custom_data">%programText:Data%</a></li>
		<li><a href="#tab_4">%programText:Admin Notes%</a></li>
		<li><a href="#tab_5">%programText:Customer Thread%</a></li>
	</ul>

<h3 class="accordion-control-element">Details</h3>
	<div id="tab_1">

%field:contact_id,help_desk_type_id,help_desk_category_id,time_submitted,user_id,user_group_id,priority,help_desk_status_id,help_desk_tag_links,project_id,project_milestone_id,time_closed%
%basic_form_line%
%end repeat%

	</div>

<h3 class="accordion-control-element">Content</h3>
	<div id="tab_2">
%field:content,image_id,file_id%
%basic_form_line%
%end repeat%

	</div>

<h3 class="accordion-control-element">Data</h3>
	<div id="custom_data">
	</div>

<h3 class="accordion-control-element">Admin Notes</h3>
	<div id="tab_4">
%field:help_desk_private_notes%
%basic_form_line%
	</div>

<h3 class="accordion-control-element">Customer Thread</h3>
	<div id="tab_5">
%field:help_desk_public_notes%
%basic_form_line%
	</div>

</div> <!-- accordion-form -->
</div> <!-- maintenance_list -->
