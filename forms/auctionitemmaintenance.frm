<div id="_maintenance_form">

	<div class="tabbed-form">
		<ul>
			<li><a href="#tab_1">Details</a></li>
			<li><a href="#tab_auction">Auction</a></li>
			<li><a href="#tab_3">Specifications</a></li>
			<li><a href="#tab_4">Resources</a></li>
			<li><a href="#tab_6">Bids</a></li>
		</ul>

		<div id="tab_1">

			%field:description,detailed_description,link_name,auction_item_group_id,sort_order,relist_until_sold,approved,published,deleted%
			%basic_form_line%
			%end repeat%

		</div>

		<div id="tab_auction">
			%field:start_time,end_time,starting_bid,reserve_price,buy_now_price,bid_increment,bid_close_extension%
			%basic_form_line%
			%end repeat%
		</div>

		<div id="tab_3">

			%field:auction_item_specifications,auction_item_product_category_links%
			%basic_form_line%
			%end repeat%

		</div>

		<div id="tab_4">

			%field:auction_item_images,auction_item_files,auction_item_videos%
			%basic_form_line%
			%end repeat%

		</div>

		<div id="tab_6">

        <div id='auction_item_bids'>
            %field:auction_item_bids%
            %basic_form_line%
            %end repeat%
        </div>

		</div>
	</div>
</div>