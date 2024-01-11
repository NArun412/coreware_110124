<div id="_maintenance_form">

%field:event_id,description%
%basic_form_line%
%end repeat%

<div class="accordion-form">

	<ul class="tab-control-element">
		<li><a href="#tab_1">Details</a></li>
		<li><a href="#tab_2">Contact</a></li>
		<li><a href="#tab_3">Requirements</a></li>
		<li><a href="#tab_4">Reserve Facilities</a></li>
		<li><a href="#tab_5">Recurring Schedule</a></li>
		<li><a href="#tab_6">Reservations</a></li>
		<li><a href="#tab_7">Facility Calendars</a></li>
		<li><a href="#tab_8">Registration</a></li>
		<li><a href="#tab_8a">Instructors</a></li>
		<li><a href="#tab_9">Registrants</a></li>
		<li><a href="#tab_10">Custom</a></li>
	</ul>

<h3 class="accordion-control-element">Details</h3>
	<div id="tab_1">

%field:events.event_type_id,events.location_id,events.user_id,detailed_description,link_name,link_url,start_date,end_date,cost,attendees,payment_date,notes,finalize,tentative,no_statistics,internal_use_only,inactive%
%basic_form_line%
%end repeat%

	</div>

<h3 class="accordion-control-element">Contact</h3>
	<div id="tab_2">

%field:contacts.first_name,contacts.last_name,contacts.business_name,contacts.address_1,contacts.address_2,city,city_select,state,postal_code,country_id,contacts.email_address,phone_numbers%
%basic_form_line%
%end repeat%
	</div>

<h3 class="accordion-control-element">Requirements</h3>
	<div id="tab_3">

%field:event_facility_requirements%
%basic_form_line%

%field:event_group_links%
%basic_form_line%
	</div>

<h3 class="accordion-control-element">Reserve Facilities</h3>
	<div id="tab_4">
%method:reserveFacilities%
	</div>

<h3 class="accordion-control-element">Recurring Schedule</h3>
	<div id="tab_5">
%method:recurringSchedules%
	</div>

<h3 class="accordion-control-element">Reservations</h3>
	<div id="tab_6">
%method:showReservations%
	</div>

<h3 class="accordion-control-element">Facility Calendars</h3>
	<div id="tab_7">
%method:facilityCalendars%
	</div>


<h3 class="accordion-control-element">Registration</h3>
	<div id="tab_8">

%field:mailing_list_id,category_id,email_id,response_content,cancellation_date,cancellation_fee%
%basic_form_line%
%end repeat%

<p>Leave price blank to use the product's calculated sale price. Start & End dates can be used to set early and late registration fees.</p>

%field:product_id%
%basic_form_line%
%end repeat%

%if:canAccessPageCode('PRODUCTMAINT')%
<div id='product_link'></div>
%endif%

%field:event_registration_products,event_registration_custom_fields,event_registration_notifications%
%basic_form_line%
%end repeat%
	</div>

<h3 class="accordion-control-element">Instructors</h3>
	<div id="tab_8a">

%field:class_instructor_id,event_class_instructors%
%basic_form_line%
%end repeat%
	</div>

<h3 class="accordion-control-element">Registrants</h3>
	<div id="tab_9">

%field:event_registrant_count,event_registrants%
%basic_form_line%
%end repeat%
	</div>

<h3 class="accordion-control-element">Custom</h3>
	<div id="tab_10">

%field:event_images%
%basic_form_line%

<div id="custom_data"></div>
	</div>

</div> <!-- accordion-form -->

</div> <!-- maintenance_form -->
