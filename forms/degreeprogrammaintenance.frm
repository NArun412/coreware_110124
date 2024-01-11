<div id="_maintenance_form">

<div class="accordion-form">
	<ul class="tab-control-element">
		<li><a href="#tab_1">%programText:Details%</a></li>
		<li><a href="#tab_2">%programText:Requirements%</a></li>
		<li><a href="#tab_3">%programText:Custom%</a></li>
	</ul>

<h3 class="accordion-control-element">%programText:Details%</h3>
	<div id="tab_1">

%field:degree_program_code,description,detailed_description,certificate_file_id,exam_id,sort_order,internal_use_only,inactive%
%basic_form_line%
%end repeat%

	<div class='clear-div'></div>
	</div>

<h3 class="accordion-control-element">%programText:Requirements%</h3>
	<div id="tab_2">
%field:degree_program_courses,degree_program_requirements%
%basic_form_line%
	</div>

<h3 class="accordion-control-element">%programText:Custom%</h3>
	<div id="tab_3">
%method:addCustomFields%
	</div>
</div>

</div>
