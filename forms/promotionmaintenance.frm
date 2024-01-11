<div id="_maintenance_form">

%field:description,promotion_code%
%basic_form_line%
%end repeat%

<div class="tabbed-form">
	<ul>
		<li><a href="#tab_1">Details</a></li>
		<li><a href="#tab_1_5">Resources</a></li>
		<li><a href="#tab_2">Groups</a></li>
		<li><a href="#tab_3">Audience</a></li>
		<li><a href="#tab_3a">Purchases</a></li>
		<li><a href="#tab_4">Requirements</a></li>
		<li><a href="#tab_5">Exclusions</a></li>
		<li><a href="#tab_6">Rewards</a></li>
		<li><a href="#tab_7">Exclusions</a></li>
	</ul>
	<div id="tab_1">

%field:detailed_description,start_date,expiration_date%
%basic_form_line%
%end repeat%

<div class='clear-div'></div>

%field:publish_start_date,publish_end_date%
%basic_form_line%
%end repeat%

<div class='clear-div'></div>

%field:event_start_date,event_end_date%
%basic_form_line%
%end repeat%

<div class='clear-div'></div>

%field:last_ship_date,minimum_amount,requires_user,user_id,maximum_usages,maximum_per_email,sort_order,no_previous_orders,apply_automatically,internal_use_only,inactive%
%basic_form_line%
%end repeat%

	</div>
	<div id="tab_1_5">

%field:link_url,link_name,product_manufacturer_id,image_id,promotion_banners,promotion_files%
%basic_form_line%
%end repeat%

<p>These promotion codes are alternate codes for using this promotion, but can only be used once.</p>
<p><button id='generate_one_time'>Generate One-time Codes</button></p>
%field:one_time_use_promotion_codes%
%basic_form_line%

%field:used_one_time_use_promotion_codes%
%basic_form_line%

	</div>
	<div id="tab_2">

%field:promotion_group_links%
%basic_form_line%
	</div>
	<div id="tab_3">
<p>Only valid for these contact types. Valid for all if none selected.</p>

%field:promotion_terms_contact_types%
%basic_form_line%
<p>Only valid for these user types. Valid for all if none selected.</p>

%field:exclude_contact_types%
%basic_form_line%

%field:promotion_terms_user_types%
%basic_form_line%
<p>Only valid for these countries. Valid for all if none selected.</p>

%field:exclude_user_types%
%basic_form_line%

%field:promotion_terms_countries%
%basic_form_line%
	</div>
	<div id="tab_3a">
<p>The following items must have been previously purchased by the user to qualify for this promotion. Obviously, since the coreFORCE is checking previous orders, the promotion can only be used by logged-in users.</p>

%field:promotion_purchased_products,promotion_purchased_product_departments,promotion_purchased_product_category_groups,promotion_purchased_product_categories,promotion_purchased_product_manufacturers,promotion_purchased_product_types,promotion_purchased_product_tags,promotion_purchased_sets%
%basic_form_line%
%end repeat%

	</div>
	<div id="tab_4">
<p>The following items must be in the order for this promotion to be used.</p>

%field:promotion_terms_products,promotion_terms_product_departments,promotion_terms_product_category_groups,promotion_terms_product_categories,promotion_terms_product_manufacturers,promotion_terms_product_types,promotion_terms_product_tags,promotion_terms_sets%
%basic_form_line%
%end repeat%

	</div>
	<div id="tab_5">
<p>The following products will NOT be counted for the requirements of this promotion. Only products that would be otherwise included in the requirements need to be added here.</p>

%field:promotion_terms_excluded_products,promotion_terms_excluded_product_departments,promotion_terms_excluded_product_category_groups,promotion_terms_excluded_product_categories,promotion_terms_excluded_product_manufacturers,promotion_terms_excluded_product_types,promotion_terms_excluded_product_tags,promotion_terms_excluded_sets%
%basic_form_line%
%end repeat%

	</div>
	<div id="tab_6">

%field:discount_amount,discount_percent%
%basic_form_line%
%end repeat%

<p>The following products will be included as rewards in the promotion.</p>

%field:promotion_rewards_products,promotion_rewards_product_departments,promotion_rewards_product_category_groups,promotion_rewards_product_categories,promotion_rewards_product_manufacturers,promotion_rewards_product_types,promotion_rewards_product_tags,promotion_rewards_sets,promotion_rewards_shipping_charges%
%basic_form_line%
%end repeat%

	</div>
	<div id="tab_7">

<p>The following products will NOT be part of the reward of this promotion. Only products that would be otherwise included in the rewards need to be added here.</p>

%field:promotion_rewards_excluded_products,promotion_rewards_excluded_product_departments,promotion_rewards_excluded_product_category_groups,promotion_rewards_excluded_product_categories,promotion_rewards_excluded_product_manufacturers,promotion_rewards_excluded_product_types,promotion_rewards_excluded_product_tags,promotion_rewards_excluded_sets%
%basic_form_line%
%end repeat%

	</div>
</div>

</div>
