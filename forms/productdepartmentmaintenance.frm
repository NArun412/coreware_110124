<div id="_maintenance_form">

<div class="accordion-form">
	<ul class="tab-control-element">
		<li><a href="#tab_1">%programText:Details%</a></li>
		<li><a href="#tab_2">%programText:Categories%</a></li>
		<li><a href="#tab_3">%programText:Groups%</a></li>
		<li><a href="#tab_4">%programText:Restrictions%</a></li>
	</ul>

<h3 class="accordion-control-element">%programText:Details%</h3>
	<div id="tab_1">

%field:product_department_code,description,detailed_description,fragment_id,meta_title,meta_description,pricing_structure_id,link_name,image_id,search_multiplier,cart_maximum,out_of_stock_threshold,sort_order,internal_use_only,inactive%
%basic_form_line%
%end repeat%

	</div>

<h3 class="accordion-control-element">%programText:Categories%</h3>
	<div id="tab_2">
%field:product_category_departments%
%basic_form_line%
	</div>

<h3 class="accordion-control-element">%programText:Groups%</h3>
	<div id="tab_3">
%field:product_category_group_departments,product_department_group_links%
%basic_form_line%
%end repeat%

	</div>

<h3 class="accordion-control-element">%programText:Restrictions%</h3>
	<div id="tab_4">
%field:product_department_restrictions,product_department_cannot_sell_distributors%
%basic_form_line%
%end repeat%

	</div>

</div>

