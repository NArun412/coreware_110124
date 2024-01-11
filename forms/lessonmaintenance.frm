<div id="_maintenance_form">

%field:description%
%basic_form_line%

<div class="accordion-form">
	<ul class="tab-control-element">
		<li><a href="#tab_1">%programText:Details%</a></li>
		<li><a href="#tab_1a">%programText:Content%</a></li>
		<li><a href="#tab_2">%programText:Resources%</a></li>
		<li><a href="#tab_3">%programText:Assignments%</a></li>
		<li><a href="#tab_4">%programText:Custom%</a></li>
	</ul>

<h3 class="accordion-control-element">%programText:Details%</h3>
	<div id="tab_1">

        %field:detailed_description,minimum_time,sort_order,internal_use_only,inactive%
        %basic_form_line%
        %end repeat%

	<div class='clear-div'></div>
	</div>

<h3 class="accordion-control-element">%programText:Content%</h3>
	<div id="tab_1a">

        %field:content_type,content,media_id,pdf_file_id%
        %basic_form_line%
        %end repeat%

        %field:template_id%
        %basic_form_line%

    <p><button id='view_lesson'>Preview Lesson</button></p>

	<div class='clear-div'></div>
	</div>

<h3 class="accordion-control-element">%programText:Resources%</h3>
	<div id="tab_2">
%field:file_id%
%basic_form_line%
	</div>

<h3 class="accordion-control-element">%programText:Assignments%</h3>
	<div id="tab_3">
%field:lesson_assignments,exam_id%
%basic_form_line%
%end repeat%

	</div>

<h3 class="accordion-control-element">%programText:Custom%</h3>
	<div id="tab_4">
%method:addCustomFields%
	</div>
</div>

</div>
