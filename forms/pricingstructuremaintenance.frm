<div id="_maintenance_form">

%field:pricing_structure_code,description%
%basic_form_line%
%end repeat%

<div id="setup_types" class="hidden">
	<div class='setup-button' id="basic_setup_button" data-setup_type="basic">
		<h5>Basic</h5>
	</div>
	<div class='setup-button' id="advanced_setup_button" data-setup_type="advanced">
		<h5>Advanced</h5>
	</div>
</div>

<div id="structure_application">
	<div id="structure_application_types">
		<div class='setup-button' id="all_application" data-application_type="all">
			<h5>All Products</h5>
			<p>Products that are not otherwise tagged. Only one of these pricing structures can exist.</p>
		</div>
		<div class='setup-button' id="specific_application" data-application_type="specific">
			<h5>Specific Products</h5>
			<p>Tag by Department, Category, Manufacturer, Product Type or specific Product.</p>
		</div>
	</div>

	<div id="application_filters">
		<p>Select products to which this pricing structure applies. NOTE: Anything selected in this section can only be used in ONE pricing structure. So, if the "Accessories" department is selected in this pricing structure, it can't be selected in another other pricing structure. This restriction will be removed in a later release.</p>
		<div class='setup-button' id="structure_application_department" data-filter_type="department">
			<input type="hidden" id="has_department_filters" name="has_department_filters" value="">
			<h5>Department(s)</h5>
		</div>
		<div class='setup-button' id="structure_application_category" data-filter_type="category">
			<input type="hidden" id="has_category_filters" name="has_category_filters" value="">
			<h5>Category(s)</h5>
		</div>
		<div class='setup-button' id="structure_application_manufacturer" data-filter_type="manufacturer">
			<input type="hidden" id="has_manufacturer_filters" name="has_manufacturer_filters" value="">
			<h5>Manufacturer(s)</h5>
		</div>
		<div class='setup-button' id="structure_application_product_type" data-filter_type="product_type">
			<input type="hidden" id="has_product_type_filters" name="has_product_type_filters" value="">
			<h5>Product Type(s)</h5>
		</div>
		<div class='setup-button' id="structure_application_product" data-filter_type="product">
			<input type="hidden" id="has_product_filters" name="has_product_filters" value="">
			<h5>Specific Product(s)</h5>
		</div>
	</div>

	<div id="filter_types">
		<div class='filter-control' id='department_filters'>
		<h2>Choose Departments</h2>
		%field:price_structure_departments%
		%basic_form_line%
		%end repeat%
		</div>

		<div class='filter-control' id='category_filters'>
		<h2>Choose Categories</h2>
		%field:price_structure_categories%
		%basic_form_line%
		%end repeat%
		</div>

		<div class='filter-control' id='manufacturer_filters'>
		<h2>Choose Manufacturers</h2>
		%field:price_structure_manufacturers%
		%basic_form_line%
		%end repeat%
		</div>

		<div class='filter-control' id='product_type_filters'>
		<h2>Choose Product Types</h2>
		%field:price_structure_product_types%
		%basic_form_line%
		%end repeat%
		</div>

		<div class='filter-control' id='product_filters'>
		<h2>Choose Products</h2>
		%field:price_structure_products%
		%basic_form_line%
		%end repeat%
		</div>
	</div>

</div>

%field:price_calculation_type_id,user_type_id,percentage,minimum_markup,minimum_amount,maximum_discount%
%basic_form_line%
%end repeat%

<div class='advanced-feature'>
	<h5>Low Inventory Markup</h5>
	%field:low_inventory_quantity%
	<div class="basic-form-line" id="_%column_name%_row">
		<label class="inline-block">%form_label%</label>
		%input_control%
		<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
	</div>

	%field:low_inventory_percentage%
	<div class="basic-form-line" id="_%column_name%_row">
		<label class="inline-block">%form_label%</label>
		%input_control%
		<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
	</div>
</div>

%field:use_list_price,internal_use_only,inactive%
%basic_form_line%
%end repeat%

<p>These percentages can replace the base percentage above.</p>

%field:pricing_structure_price_discounts%
%basic_form_line%

<div class='advanced-feature'>
%field:pricing_structure_quantity_discounts,pricing_structure_category_quantity_discounts,pricing_structure_distributor_surcharges%
%basic_form_line%
%end repeat%

<p>Best discount will be gotten from user and contact discounts and will be applied to the final percentage found above.</p>

%field:pricing_structure_user_discounts%
%basic_form_line%
</div>

</div> <!-- maintenance_form -->
