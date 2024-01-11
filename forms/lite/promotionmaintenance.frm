<div id="_maintenance_form">

%field:description,promotion_code%
%basic_form_line%
%end repeat%

<div class="tabbed-form">
	<ul>
		<li><a href="#tab_1">Details</a></li>
		<li><a href="#tab_4">Requirements</a></li>
		<li><a href="#tab_6">Rewards</a></li>
	</ul>
	<div id="tab_1">

%field:start_date,expiration_date%
%basic_form_line%
%end repeat%

<div class='clear-div'></div>

%field:minimum_amount,maximum_per_email,sort_order,apply_automatically,internal_use_only,inactive%
%basic_form_line%
%end repeat%

	</div>
	<div id="tab_4">
<p>The following items must be in the order for this promotion to be used.</p>

%field:promotion_terms_products,promotion_terms_product_departments,promotion_terms_product_categories,promotion_terms_product_manufacturers,promotion_terms_product_tags%
%basic_form_line%
%end repeat%

	</div>
	<div id="tab_6">

%field:discount_amount,discount_percent%
%basic_form_line%
%end repeat%

<p>The following products will be included as rewards in the promotion.</p>

%field:promotion_rewards_products,promotion_rewards_product_departments,promotion_rewards_product_categories,promotion_rewards_product_manufacturers,promotion_rewards_product_tags%
%basic_form_line%
%end repeat%

	</div>
</div>

</div>
