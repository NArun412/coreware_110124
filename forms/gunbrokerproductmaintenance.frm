<div id="_maintenance_form">

<h2>Product Information</h2>
%field:product_id%
%basic_form_line%
%end repeat%

<label for="upc_code" class="help-label">Click UPC to open product record</label>
%field:upc_code,description,detailed_description%
%basic_form_line%
%end repeat%

%field:gunbroker_listing_template_id%
%basic_form_line%

%field:header_content,footer_content%
%basic_form_line%
%end repeat%

<h2>Listing Details</h2>
%field:listing_type,quantity,auto_accept_price,auto_reject_price,auto_relist,auto_relist_fixed_count,buy_now_price,can_offer,category_identifier,item_condition,fixed_price,ground_shipping_cost,inspection_period,listing_duration,prop_65_warning,serial_number,starting_bid,weight,weight_unit,who_pays_for_shipping,shipping_profile_identifier,standard_text_identifier,will_ship_international%
%basic_form_line%
%end repeat%

<h2>Premium Features</h2>
<p class='info-message'>GunBroker charges extra for these features</p>
%field:reserve_price,has_view_counter,is_featured_item,is_highlighted,is_show_case_item,is_title_boldface,is_sponsored_onsite,scheduled_start_date_part,scheduled_start_time_part,scheduled_starting_time,subtitle,title_color%
%basic_form_line%
%end repeat%

<h2>coreFORCE integration</h2>
%field:date_sent,gunbroker_identifier%
%basic_form_line%
%end repeat%

</div> <!-- maintenance_form -->
