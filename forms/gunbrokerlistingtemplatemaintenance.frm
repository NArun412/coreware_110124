<div id="_maintenance_form">

%field:gunbroker_listing_template_code,description,header_content,footer_content%
%basic_form_line%
%end repeat%

%field:listing_type,auto_relist,auto_relist_fixed_count,can_offer,item_condition,ground_shipping_cost,inspection_period,listing_duration,prop_65_warning,quantity,starting_bid,who_pays_for_shipping,shipping_profile_identifier,standard_text_identifier,will_ship_international%
%basic_form_line%
%end repeat%

<h2>Premium Features</h2>
<p class='info-message'>GunBroker charges extra for these features</p>
%field:has_view_counter,is_featured_item,is_highlighted,is_show_case_item,is_title_boldface,is_sponsored_onsite,title_color%
%basic_form_line%
%end repeat%

%field:sort_order,internal_use_only,inactive%
%basic_form_line%
%end repeat%

</div> <!-- maintenance_form -->
