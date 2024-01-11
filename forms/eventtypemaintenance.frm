<div id="_maintenance_form">

%field:event_type_id,event_type_code%
%basic_form_line%
%end repeat%

<div class="accordion-form">

	<ul class="tab-control-element">
		<li><a href="#tab_1">Details</a></li>
		<li><a href="#tab_2">Registration</a></li>
		<li><a href="#tab_3">Tags</a></li>
		<li><a href="#tab_4">Requirements</a></li>
		<li><a href="#tab_5">Emails</a></li>
		<li><a href="#tab_10">Custom</a></li>
	</ul>


<h3 class="accordion-control-element">Details</h3>
	<div id="tab_1">

		%field:description,detailed_description,excerpt,link_name,image_id,display_color,sort_order,hide_in_calendar,internal_use_only,inactive%
		%basic_form_line%
		%end repeat%

	</div>

	<h3 class="accordion-control-element">Registration</h3>
	<div id="tab_2">

		%field:class_instructor_id,attendees,product_id,price,change_days,cancellation_days%
		%basic_form_line%
		%end repeat%

	</div>

	<h3 class="accordion-control-element">Tags</h3>
	<div id="tab_3">

		%field:event_type_tag_links%
		%basic_form_line%

	</div>

	<h3 class="accordion-control-element">Requirements</h3>
	<div id="tab_4">

%field:any_requirement,event_type_requirements%
%basic_form_line%
%end repeat%


	</div>


<h3 class="accordion-control-element">Emails</h3>
	<div id="tab_5">


%field:email_id,reminder_email_id,ended_email_id,event_type_location_emails,event_type_notifications%
%basic_form_line%
%end repeat%
	</div>


<h3 class="accordion-control-element">Custom</h3>
	<div id="tab_10">

		<h4>Event Type Custom Fields</h4>
		<label>These apply to this event type</label>
		<div class="clear-div"></div>
		%method:displayCustomFields%

		<h4>Event Custom Fields</h4>
		<label>Events of this event type will have these custom fields</label>
		<div class="clear-div"></div>
		%field:event_type_custom_fields%
		%basic_form_line%


		<div id="custom_data"></div>
	</div>

</div> <!-- accordion-form -->

</div> <!-- maintenance_form -->
