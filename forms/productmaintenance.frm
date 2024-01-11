<div id="_maintenance_form">
<div id="_upper_section">
<div id="upper_image"></div>

%field:product_id_display,description,product_code,date_created,expiration_date,time_changed%
%basic_form_line%
%end repeat%

<p id="missing_information"></p>

<div class="basic-form-line inline-block">
	<div id="product_manufacturer_website"></div>
</div>
</div>

<div class="tabbed-form">
	<ul>
		<li><a href="#tab_1">Details</a></li>
		<li><a href="#tab_pricing">Pricing</a></li>
		<li><a href="#tab_3">Data</a></li>
		<li><a href="#tab_4">Options</a></li>
		<li><a href="#tab_6">Categories</a></li>
		<li><a href="#tab_7">Images</a></li>
		<li><a href="#tab_8">Notifications</a></li>
		<li><a href="#tab_10">Shipping</a></li>
%if:$GLOBALS['gPageObject']->showRelatedProducts()%
		<li><a href="#tab_11">Related</a></li>
%endif%
		<li><a href="#tab_12">Vendors</a></li>
		<li><a href="#tab_13">Inventory</a></li>
	</ul>

	<div id="tab_1">

%field:detailed_description,link_name,product_type_id,product_format_id,product_manufacturer_id,virtual_product,file_id,user_group_id,error_message,search_terms,search_multiplier,points_multiplier,sort_order,cart_minimum,cart_maximum,order_maximum,tax_rate_id,requires_user,no_update,not_searchable,cannot_dropship,reindex,custom_product,not_taxable,non_inventory_item,serializable,no_online_order,internal_use_only,inactive,notes%
%basic_form_line%
%end repeat%

	</div>

	<div id="tab_pricing">
%field:pricing_structure_id%
%basic_form_line%
<div class='clear-div'></div>

%field:list_price%
<div class="basic-form-line inline-block" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

%field:base_cost%
<div class="basic-form-line inline-block" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>
<div class='clear-div'></div>

%field:manufacturer_advertised_price%
<div class="basic-form-line inline-block" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

%field:minimum_price%
<div class="basic-form-line inline-block" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

<p>For distributor products, MAP is updated from the Coreware catalog unless the existing MAP is higher.</p>
<div class='clear-div'></div>

%field:displayed_sale_price%
%basic_form_line%

%field:calculation_log%
<div class="basic-form-line" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>
<div class='clear-div'></div>

%field:sale_price%
%basic_form_line%
<div class='clear-div'></div>

<h3>Surcharge for low inventory</h3>
<p>The amount will be added to the sale price of the product when total inventory is at or below low inventory quantity.</p>

%field:low_inventory_quantity%
<div class="inline-block basic-form-line" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

%field:low_inventory_surcharge_amount%
<div class="inline-block basic-form-line" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>
<div class='clear-div'></div>

%field:product_payment_methods%
%basic_form_line%

<p>If a location is set, a product price of type "Sale Price" will only be valid for inventory from that location.</p>

%field:product_prices%
%basic_form_line%
	</div>

	<div id="tab_3">

%field:product_custom_fields,product_contributors,full_name,contributor_type_id%
%basic_form_line%
%end repeat%

%method:productDataFields%
%method:displayCustomFields%
	</div>

	<div id="tab_4">

%field:product_tag_links,product_serial_numbers,product_bulk_packs,product_pack_contents,product_addon_set_id,product_addons%
%basic_form_line%
%end repeat%

	</div>

	<div id="tab_6">
<div id="_product_taxonomy"></div>
%field:product_categories%
%basic_form_line%

<p><button id='load_all_facets'>Load All Facets</button></p>
%field:product_facet_values%
%basic_form_line%

	</div>

	<div id="tab_7">

%field:image_id%
%basic_form_line%

<p id="image_message"></p>

%field:product_images%
%basic_form_line%

%field:product_videos%
%basic_form_line%
	</div>

	<div id="tab_8">

%field:product_sale_notifications%
%basic_form_line%

<p>The following one-time notifications will be sent out when the inventory level reaches the specified quantity. If an order is being placed, the quantity needs to be set. If [Any] order location is chosen, you can choose lowest price or the location sort order (defined at Products->Settings->Locations). Also, if [Any] order location is chosen, you can choose to allow multiple orders (from multiple distributors) to be placed.</p>

%field:product_inventory_notifications%
%basic_form_line%
	</div>

	<div id="tab_10">

%field:product_restrictions,product_shipping_methods,product_shipping_carriers,product_distributor_dropship_prohibitions%
%basic_form_line%
%end repeat%

	</div>

%if:$GLOBALS['gPageObject']->showRelatedProducts()%
	<div id="tab_11">

%field:related_products%
%basic_form_line%
	</div>
%endif%

	<div id="tab_12">

%field:product_vendors%
%basic_form_line%
	</div>

	<div id="tab_13">
<div id="product_inventory">
</div>

%field:distributor_product_codes%
%basic_form_line%
	</div>
</div>
</div>