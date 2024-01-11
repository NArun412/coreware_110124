<div id="_maintenance_form">
<div id="upper_image"></div>

%field:product_manufacturer_code,description%
%basic_form_line%
%end repeat%

<div class="tabbed-form">
	<ul>
		<li><a href="#tab_1">Details</a></li>
		<li><a href="#tab_2">Contact</a></li>
		<li><a href="#tab_3">Settings</a></li>
		<li><a href="#tab_4">Distributors</a></li>
		<li><a href="#tab_5">Images</a></li>
	</ul>

	<div id="tab_1">

%field:detailed_description,web_page,link_name,meta_title,meta_description,requires_user,cannot_sell,cannot_dropship,internal_use_only,inactive%
%basic_form_line%
%end repeat%

    </div>
    <div id="tab_2">

%field:first_name,last_name,business_name,address_1,address_2,city,city_select,state,postal_code,country_id,email_address,phone_numbers%
%basic_form_line%
%end repeat%

    </div>
    <div id="tab_3">

%field:pricing_structure_id,search_multiplier,sort_order,map_policy_id,percentage,product_manufacturer_map_holidays,shipping_charge,product_manufacturer_tag_links%
%basic_form_line%
%end repeat%

    </div>
    <div id="tab_4">

%field:product_distributor_id,product_manufacturer_dropship_exclusions,product_manufacturer_distributor_dropships,product_manufacturer_cannot_sell_distributors%
%basic_form_line%
%end repeat%

    </div>
    <div id="tab_5">

%field:image_id,product_manufacturer_images%
%basic_form_line%
%end repeat%

    </div>

</div> <!-- maintenance_form -->
