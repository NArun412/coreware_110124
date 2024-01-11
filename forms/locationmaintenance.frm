<div id="_maintenance_form">

%field:description%
%basic_form_line%

%if:!$GLOBALS['gUserRow']['administrator_flag']%
%field:federal_firearms_licensee_id%
%basic_form_line%
%endif%

<div class="accordion-form">

	<ul class="tab-control-element">
		<li><a href="#tab_1">Details</a></li>
		<li><a href="#tab_2">Contact</a></li>
		<li><a href="#tab_3">Availability</a></li>
		<li><a href="#tab_7">Custom</a></li>
	</ul>

<h3 class="accordion-control-element">Details</h3>
	<div id="tab_1">

%if:$GLOBALS['gUserRow']['administrator_flag']%
%field:location_code%
%basic_form_line%
%endif%

%field:location_group_id,location_status_id,link_name,image_id,percentage,amount,search_multiplier,out_of_stock_threshold,cost_threshold%
%basic_form_line%
%end repeat%

<p>Notification will be sent to the location email if 1) the location ships and total shippable inventory is at or below the notification threshold or 2) the location doesn't ship, has inventory level at or below the threshold, and the order is for pickup at the location.</p>
%field:notification_threshold,sort_order,warehouse_location,cannot_ship,not_searchable,ignore_inventory,internal_use_only,inactive%
%basic_form_line%
%end repeat%

	</div>

<h3 class="accordion-control-element">Contact</h3>
	<div id="tab_2">
<p>For product distributor locations, the name & address is where dealer orders will be shipped. If Business Name, address line 1, city or postal code is blank, the dealer client address will be used.</p>

%field:product_distributor_id%
%basic_form_line%
%end repeat%

<div id='local_location_wrapper'>
<p>If this location is a local store and has an FFL license, entering that here will help the customer by choosing pickup if they choose your store as their FFL. Enter the shorten version of your license number. So, for license number 1-23-XXX-XX-XX-12345, enter 1-23-12345.</p>
<p class='green-text' id='ffl_information'></p>

%field:license_lookup%
%basic_form_line%
</div>

%field:first_name,last_name,business_name,alternate_name,address_1,address_2,city,city_select,state,postal_code,country_id,latitude,longitude,metro_area,email_address,phone_numbers,store_information%
%basic_form_line%
%end repeat%

	</div>

<h3 class="accordion-control-element">Availability</h3>
	<div id="tab_3">
%method:availabilityHours%
	</div>

<h3 class="accordion-control-element">Custom</h3>
	<div id="tab_7">
%method:addCustomFields%
	</div>
</div> <!-- maintenance_form -->
