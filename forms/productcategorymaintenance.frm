<div id="_maintenance_form">

<div class="accordion-form">
	<ul class="tab-control-element">
		<li><a href="#tab_1">%programText:Details%</a></li>
		<li><a href="#tab_2">%programText:Departments%</a></li>
		<li><a href="#tab_3">%programText:Groups%</a></li>
		<li><a href="#tab_4">%programText:Facets%</a></li>
		<li><a href="#tab_addons">%programText:Addons%</a></li>
		<li><a href="#tab_5">%programText:Restrictions%</a></li>
		<li><a href="#tab_6">%programText:Shipping%</a></li>
	</ul>

<h3 class="accordion-control-element">%programText:Details%</h3>
	<div id="tab_1">

%field:product_category_code,description,detailed_description,product_tax_code,meta_title,meta_description,pricing_structure_id,user_group_id,link_name,image_id,search_multiplier,points_multiplier,sort_order,cannot_dropship,cannot_sell,add_new_product,internal_use_only,inactive,product_count,remove_products%
%basic_form_line%
%end repeat%

	</div>

<h3 class="accordion-control-element">%programText:Departments%</h3>
	<div id="tab_2">
%field:product_category_departments%
%basic_form_line%
	</div>

<h3 class="accordion-control-element">%programText:Groups%</h3>
	<div id="tab_3">
%field:product_category_group_links%
%basic_form_line%
	</div>

<h3 class="accordion-control-element">%programText:Facets%</h3>
	<div id="tab_4">
%field:product_facet_categories%
%basic_form_line%
	</div>

<h3 class="accordion-control-element">%programText:Addons%</h3>
	<div id="tab_addons">
%field:product_category_addons%
%basic_form_line%
%end repeat%

	</div>

<h3 class="accordion-control-element">%programText:Restrictions%</h3>
	<div id="tab_5">
%field:product_category_restrictions,product_category_cannot_sell_distributors%
%basic_form_line%
%end repeat%

	</div>

<h3 class="accordion-control-element">%programText:Shipping%</h3>
	<div id="tab_6">
%field:product_category_shipping_methods,product_category_shipping_carriers%
%basic_form_line%
%end repeat%

	</div>
</div>

</div>

