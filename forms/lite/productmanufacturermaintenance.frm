<div id="_maintenance_form">
<div id="upper_image"></div>

%field:product_manufacturer_code,description%
%basic_form_line%
%end repeat%

<div class="tabbed-form">
	<ul>
		<li><a href="#tab_1">Details</a></li>
		<li><a href="#tab_2">MAP</a></li>
		<li><a href="#tab_3">Contact</a></li>
		<li><a href="#tab_4">Tags</a></li>
	</ul>

	<div id="tab_1">

%field:detailed_description,web_page,link_name,image_id,meta_title,meta_description,cannot_sell,cannot_dropship,internal_use_only,inactive%
%basic_form_line%
%end repeat%

    </div>

	<div id="tab_2">

		%field:map_policy_id,product_manufacturer_map_holidays%
		%basic_form_line%
		%end repeat%

	</div>

	<div id="tab_3">

		%field:first_name,last_name,business_name,address_1,address_2,city,city_select,state,postal_code,country_id,email_address,phone_numbers%
		%basic_form_line%
		%end repeat%

	</div>

    <div id="tab_4">

%field:product_manufacturer_tag_links%
%basic_form_line%
%end repeat%

    </div>
</div> <!-- maintenance_form -->
