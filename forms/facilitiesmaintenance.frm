<div id="_maintenance_form">

%field:description%
%basic_form_line%

<div class="tabbed-form">
	<ul>
		<li><a href="#tab_1">Details</a></li>
		<li><a href="#tab_2">Description</a></li>
		<li><a href="#tab_2a">Tags</a></li>
		<li><a href="#tab_3">Availability</a></li>
		<li><a href="#tab_4">Reservations</a></li>
	</ul>

	<div id="tab_1">
%field:facility_type_id,location_id,event_type_id,link_url,link_name,requires_approval,cost_per_hour,cost_per_day,cost_tbd,facility_prices,maximum_capacity,square_footage,uses_requirements,facility_notifications,sort_order,internal_use_only,inactive%
%basic_form_line%
%end repeat%

	</div>

	<div id="tab_2">

%field:detailed_description,facility_images,facility_files%
%basic_form_line%
%end repeat%

	</div>

	<div id="tab_2a">

%field:facility_tag_links%
%basic_form_line%
%end repeat%

	</div>

	<div id="tab_3">
%field:reservation_start%
%basic_form_line%

%method:availabilityHours%
	</div>

	<div id="tab_4">
%method:facilityCalendar%
	</div>
</div>

</div>
