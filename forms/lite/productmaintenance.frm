<div id="_maintenance_form">
<div id="_upper_section">
<div id="upper_image"></div>

%field:product_id,description,date_created,time_changed%
%basic_form_line%
%end repeat%

</div>

<div class="tabbed-form">
	<ul>
		<li><a href="#tab_1">Details</a></li>
		<li><a href="#tab_pricing">Pricing</a></li>
		<li><a href="#tab_6">Categories</a></li>
		<li><a href="#tab_7">Images</a></li>
		<li><a href="#tab_10">State Restrictions</a></li>
		<li><a href="#tab_13">Inventory</a></li>
	</ul>

	<div id="tab_1">

%field:detailed_description,link_name,product_manufacturer_id,cart_minimum,cart_maximum%
%basic_form_line%
%end repeat%

%method:productDataFields:model%
%method:productDataFields:upc_code%
%method:productDataFields:width%
%method:productDataFields:length%
%method:productDataFields:height%
%method:productDataFields:weight%
%method:displayCustomFields%

%field:virtual_product,not_taxable,non_inventory_item,inactive%
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

<div class='clear-div'></div>

%field:product_prices%
<div class="basic-form-line inline-block" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

	</div>

	<div id="tab_6">
%field:product_tag_links%
%basic_form_line%

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

	</div>

	<div id="tab_10">
		<p><strong>Note:</strong> State restrictions come from the Coreware catalog and originate in distributor data.<br>
		<strong>Changes made here are temporary and will be overwritten at the next distributor product import.</strong></p>

		%field:product_restrictions%
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