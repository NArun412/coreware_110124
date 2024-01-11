<div id="_maintenance_form">
<div class="accordion-form">
	<ul class="tab-control-element">
		<li id="_start_tab"><a href="#tab_start">%programText:Getting Started%</a></li>
		<li id="_store_tab"><a href="#tab_store">%programText:Store%</a></li>
		<li id="_feeds_tab"><a href="#tab_feeds">%programText:Feeds%</a></li>
		<li id="_merchant_tab"><a href="#tab_merchant">%programText:Merchant Account%</a></li>
		<li id="_pricing_tab"><a href="#tab_pricing">%programText:Pricing%</a></li>
		<li id="_gift_card_tab"><a href="#tab_gift_cards">%programText:Gift Cards%</a></li>
		<li id="_map_tab"><a href="#tab_map">%programText:MAP%</a></li>
		<li id="_colors_tab"><a href="#tab_colors">%programText:Colors%</a></li>
		<li id="_placeholders_tab"><a href="#tab_placeholders">%programText:Placeholders%</a></li>
		<li id="_analytics_tab"><a href="#tab_analytics">%programText:Analytics%</a></li>
		<li id="_locations_tab"><a href="#tab_locations">%programText:Locations%</a></li>
		<li id="_corestore_tab"><a href="#tab_corestore">%programText:coreSTORE%</a></li>
		<li id="_easypost_tab"><a href="#tab_easypost">%programText:EasyPost%</a></li>
		<li><a href="#tab_credova">%programText:Credova%</a></li>
		%if:in_array('CORE_FFL',$GLOBALS['gClientSubsystemCodes'])%
		<li id="_flp_tab"><a href="#tab_flp">%programText:FLP%</a></li>
		<li id="_gunbroker_tab"><a href="#tab_gunbroker">%programText:GunBroker%</a></li>
		<li><a href="#tab_gundeals">%programText:gun.deals%</a></li>
		<li><a href="#tab_ammo_seek">%programText:AmmoSeek%</a></li>
		<li><a href="#tab_highcapdeals">%programText:High Cap Deals%</a></li>
		%endif%
		<li><a href="#tab_taxjar">%programText:TaxJar%</a></li>
		<li id="_nofraud_tab"><a href="#tab_nofraud">%programText:NoFraud%</a></li>
		<li><a href="#tab_emails">%programText:Emails%</a></li>
		<li><a href="#tab_notifications">%programText:Notifications%</a></li>
	</ul>


	<h3 class="accordion-control-element">%programText:Getting Started%</h3>
	<div id="tab_start">

		%method:goLiveChecklist%

	</div>

<h3 class="accordion-control-element">%programText:Store%</h3>
<div id="tab_store">

%field:client_business_name,client_first_name,client_last_name,client_address_1,client_address_2,client_city,client_state,client_postal_code,client_phone_number,client_email_address%
%basic_form_line%
%end repeat%

</div>

<h3 class="accordion-control-element">%programText:Feeds%</h3>
<div id="tab_feeds">
<p class='red-text'>DO NOT put the Domain name that is used for the backend in this field. This domain name is substituted into emails that go to customers.</p>

%field:domain_name%
%basic_form_line%

<p>The system can provide feeds for Gun.deals, WikiArms, Ammoseek, and Google Products. Add your domain name above and the links will appear below.</p>
<div id="feed_pages">
</div>

</div>

<h3 class="accordion-control-element">%programText:Merchant%</h3>
<div id="tab_merchant">

<p>A merchant services account is required to receive and process orders. Please enter that information below. Once you've saved these credentials, you can test them.</p>
<input type="hidden" id="merchant_account_id" name="merchant_account_id">

%field:setup_merchant_account%
%basic_form_line%

%field:merchant_accounts_merchant_service_id%
<div class="basic-form-line merchant-account hidden" id="_%column_name%_row" data-column_name="merchant_service_id">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

%field:merchant_accounts_account_login%
<div class="basic-form-line merchant-account hidden" id="_%column_name%_row" data-column_name="account_login">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

%field:merchant_accounts_account_key%
<div class="basic-form-line merchant-account hidden" id="_%column_name%_row" data-column_name="account_key">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

%field:merchant_accounts_merchant_identifier%
<div class="basic-form-line merchant-account hidden" id="_%column_name%_row" data-column_name="merchant_identifier">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

%field:merchant_accounts_link_url%
<div class="basic-form-line merchant-account hidden" id="_%column_name%_row" data-column_name="link_url">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

%method:merchantAccountCustomFields%


	<p id="test_merchant_results" class="merchant-account hidden"><button id="test_merchant">Test Merchant Account</button></p>
<div id="coreclear_avs_settings" class="merchant-account hidden">
%method:coreClearAvsSettings%
</div>

</div>

<h3 class="accordion-control-element">%programText:EasyPost%</h3>
<div id="tab_easypost">
<p><img class='integration-logo' src="/images/easypost_logo.svg"></p>
<p><span class='system-name'>coreFORCE lite</span> integrates with EasyPost for creating shipping labels. Add your EasyPost API Key here. If you don't have an EasyPost account, you can <a href='https://www.easypost.com/signup'>create one here.</a></p>

%field:easy_post_api_key%
%basic_form_line%

<p id="test_easypost_results"></p>
<p><button id="test_easypost">Test Easy Post API</button></p>

<p>The EasyPost Create Label dialog will check "Signature required" if the shipment is being sent to an FFL.</p>
	<p>To make "Adult Signature Required" checked by default, add the department codes you want to require an adult signature here in a comma-separated list.</p>
	%field:easypost_adult_signature_required_departments%
	%basic_form_line%

	<p>To make "Signature Required" checked by default, add the department codes you want to require any signature here in a comma-separated list.</p>
	%field:easypost_signature_required_departments%
	%basic_form_line%

	<p>Check this box to include an option to ship HazMat materials. The available options for HazMat are: PRIMARY_CONTAINED, PRIMARY_PACKED, PRIMARY, SECONDARY_CONTAINED, SECONDARY_PACKED, SECONDARY, LIMITED_QUANTITY, LITHIUM. You are responsible to know what these mean and how to use them.</p>
%field:easy_post_hazmat_options%
%basic_form_line%

</div>

<h3 class="accordion-control-element">%programText:coreSTORE%</h3>
<div id="tab_corestore">

<p><img class='integration-logo' src="/images/corestore.jpg"></p>
<p>coreSTORE fully integrates with <span class='system-name'>coreFORCE lite</span> and provides retailers with an easy to use, flexible point of sale solution that can be easily configured and handle single and multi-location retail operations. For more information, go to <a href='https://www.corestore.info' target='_blank'>corestore.info</a>.</p>

%field:corestore_api_key,corestore_endpoint%
%basic_form_line%
%end repeat%

</div>

<h3 class="accordion-control-element">%programText:Pricing%</h3>
<div id="tab_pricing">
	<p><span class='system-name'>coreFORCE lite</span> calculates the sales price of the products based on pricing structures. You can create any number of pricing structures at Products > Pricing Structures.
		These pricing structures can be applied to product categories, individual products, product types or numerous other groupings of products and can be calculated
		as a markup from cost (using the markup or margin methods) or a discount from list price. There should, however, be a Default pricing structure as a catch-all
		for any products that are not covered otherwise. Create or update this default pricing structure here.</p>
	<p><strong>Note:</strong> If you are using coreSTORE, the price from coreSTORE will override the pricing structure as long as the product is in stock in coreSTORE.</p>

%field:setup_pricing_structure%
%basic_form_line%

<div class='pricing-structure' id='pricing_structure_wrapper'>
%field:pricing_structure_percentage%
<div class="basic-form-line pricing-structure hidden" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

%field:pricing_structure_price_calculation_type_id%
<div class="basic-form-line pricing-structure hidden" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

%field:credit_card_handling_fee%
<div class="basic-form-line pricing-structure hidden" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>
</div>

</div>


<h3 class="accordion-control-element">%programText:Gift Cards%</h3>
<div id="tab_gift_cards">
<p>Will you be offering Gift Cards? This will create a page where the gift card can be created and added to the card and will also create the gift card product itself. Once the product is created, you are encouraged to do the following in Products->Products:</p>
<ul>
<li>Add an image</li>
<li>Create a detailed description with the terms of use of the card</li>
<li>Limit payment methods that can be used to purchase a gift card to, typically, just credit cards.</li>
</ul>

<input type="hidden" id="create_gift_cards" name="create_gift_cards" value="">
<p id="create_gift_cards_wrapper"><button id="create_gift_cards_button">Yes, the store will offer gift cards</button></p>

</div>


<h3 class="accordion-control-element">%programText:MAP%</h3>
<div id="tab_map">
	<p>Minimum Advertised Price (MAP) is a pricing policy which is common in the firearms industry.  It means that the product manufacturer sets a minimum price which can be publicly advertised for their products.  If the manufacturer has a MAP policy, no website is permitted to show a lower price than MAP.</p>
	<p>Many manufacturers use automated tools to enforce compliance with their policies, and will blacklist dealers who are found violating their policies. In most cases, websites are allowed to actually sell products at a lower price, as long as that price is not visible to the public. This is why <span class='system-name'>coreFORCE lite</span> displays "Add to cart for best price" for MAP-priced products.</p>
	<p>If you prefer not to provide this option to consumers, you can choose to simply sell all products at the MAP price or at a calculated price, as long as it no less than MAP.</p>
	<p>Thousands of firearms products, including most of the most popular brands, use MAP pricing. The majority of online sales of these products are actually below MAP via tools like "Add to cart for best price".</p>
	<p>A few manufacturers don't even allow "Add to cart for best price". In <span class='system-name'>coreFORCE lite</span>, these are set to "MAP is minimum sale price". Other editions of coreFORCE offer options that satisfy these manufacturer's requirements.</p>

%field:default_map_policy_id%
%basic_form_line%

</div>


<h3 class="accordion-control-element">%programText:Colors%</h3>
<div id="tab_colors">
%method:sassColors%

</div>

<h3 class="accordion-control-element">%programText:Placeholders%</h3>
<div id="tab_placeholders">
%method:templateTextChunks%

</div>


<h3 class="accordion-control-element">%programText:Analytics%</h3>
<div id="tab_analytics">

	<p>Paste the JavaScript code for your analytics platform(s) in this box. You need the full code including the opening and closing &lt;script&gt; tags.</p>
	<p>Instructions for Google can be found <a href="https://developers.google.com/tag-platform/gtagjs/install" target="_blank">here</a>.</p>

%field:analytics_code%
%basic_form_line%
</div>


<h3 class="accordion-control-element">%programText:Locations%</h3>
<div id="tab_locations">

<p>If this location is a local store and has an FFL license, entering that here will help the customer by choosing pickup if they choose your store as their FFL. Enter the shorten version of your license number. So, for license number 1-23-XXX-XX-XX-12345, enter 1-23-12345. If the full license number is entered, it will be converted.</p>
<p class='green-text' id='ffl_information'></p>

%method:locationSettings%

</div>

<h3 class="accordion-control-element">%programText:Credova%</h3>
<div id="tab_credova">

<p><img class='integration-logo' src="/images/credova.png"></p>
<p><span class='system-name'>coreFORCE lite</span> fully integrates with Credova Financial. With Credova's easy to use buy now, pay later payment option, your customers know their terms up front and can shop for what they want, when they want and then pay over time. For more information, go to <a href='https://credova.com' target='_blank'>credova.com</a></p>

%field:credova_username,credova_password%
%basic_form_line%
%end repeat%

	<p>
		%method:checkCredovaSetup%
	</p>


</div>

%if:in_array("CORE_FFL",$GLOBALS['gClientSubsystemCodes'])%
<h3 class="accordion-control-element">%programText:FLP%</h3>
<div id="tab_flp">
	<p><img class='integration-logo' src="/images/flp_logo.png"></p>
	<p><span class='system-name'>coreFORCE lite</span> integrates with Firearms Legal Protection to allow selling FLP memberships online. Add your Partner ID from FLP here to enable it. If you have not registered as a partner with FLP yet, you can do so <a target="_blank" href="https://firearmslegal.com/area-managers/partnership-program/">here</a>.</p>

	%field:flp_partner_id%
	%basic_form_line%
	%end repeat%

	<p>
		%method:checkFlpSetup%
	</p>

	<p>If you are in a state where FLP must be sold as insurance (see <a href="https://firearmslegal.com/availability/">list</a>), you can become an FLP affiliate rather than selling FLP in coreFORCE.</p>

	%field:flp_affiliate_link%
	%basic_form_line%
	%end repeat%
	<div id="_setup_flp_affiliate">
		<button id="setup_flp_affiliate">Add Affiliate Banner</button>
	</div>
	<div class='clear-div'></div>

</div>

<h3 class="accordion-control-element">%programText:GunBroker%</h3>
<div id="tab_gunbroker">
	<p><img class='integration-logo' src="/images/gunbroker_logo.svg"></p>
	<p><span class='system-name'>coreFORCE lite</span> integrates with GunBroker for listing your products. Add your username & password for GunBroker here to enable it. If you don't have a GunBroker account, you can <a href="https://www.gunbroker.com/newregistration/signupdetails">create one here.</a></p>

	%field:gunbroker_username,gunbroker_password%
	%basic_form_line%
	%end repeat%

	<br>
	<p>NOTE: To prevent overselling, <span class='system-name'>coreFORCE lite</span> automatically ends GunBroker listings if the product is out of stock. <span class='system-name'>coreFORCE lite</span> does NOT support listing products that are only in stock at distributors. Only products with inventory in your store location can be listed on GunBroker.</p>

</div>

<h3 class="accordion-control-element">%programText:gun.deals%</h3>
<div id="tab_gundeals">
<p><img class='integration-logo' src="/images/gundeals.png"></p>

<p class="ffl-store">If you have an account with Gun.Deals, the url for your feeds is https://www.yourdomainname.com/gundealsfeed.php. (Of course, replace 'yourdomainname' with your actual domain name.)</p>

<p>The system can feed your products to gun.deals. If you don't have a gun.deals account, you can <a href="https://gun.deals/user/register">register here.</a></p>

<div id="gundeals_settings">
%method:gundealsPreferences%
</div>

</div>

<h3 class="accordion-control-element">%programText:AmmoSeek%</h3>
<div id="tab_ammo_seek">
<p><img class='integration-logo' src="/images/ammoseek.png"></p>

<p class="ffl-store">If you have an account with AmmoSeek, the url for your feeds is https://www.yourdomainname.com/ammoseekfeed.php. (Of course, replace 'yourdomainname' with your actual domain name.)</p>

<p>The system can feed your products to AmmoSeek. If you don't have an AmmoSeek account, you can <a href="https://ammoseek.com/signin">join here.</a></p>

<div id="ammo_seek_settings">
%method:ammoSeekPreferences%
</div>

</div>

<h3 class="accordion-control-element">%programText:High Cap Deals%</h3>
<div id="tab_highcapdeals">
	<p><img class='integration-logo' src="/images/highcapdeals_logo.png"></p>

	<p class="ffl-store">If you have an account with High Cap Deals, the url for your feeds is https://www.yourdomainname.com/highcapdealsfeed.php. (Of course, replace 'yourdomainname' with your actual domain name.)</p>

	<p>The system can feed your products to High Cap Deals. If you don't have an account, you can <a href="https://www.highcapdeals.com/merchant/merchant_register.htm">register here.</a></p>

	<div id="highcapdeals_settings">
		%method:highCapDealsPreferences%
	</div>

</div>
%endif%

<h3 class="accordion-control-element">%programText:TaxJar%</h3>
<div id="tab_taxjar">

<p><img class='integration-logo' src="/images/taxjar.svg"></p>
<p><span class='system-name'>coreFORCE lite</span> integrates with TaxJar for calculating and reporting your taxes. If you have a TaxJar account, enter the API token here. If not, you can <a href='https://app.taxjar.com/api_sign_up'>signup for TaxJar here</a></p>

%field:taxjar_api_token%
%basic_form_line%
%end repeat%

<div id='taxjar_validation'></div>

%field:taxjar_api_reporting%
%basic_form_line%
%end repeat%

<div id='taxjar_nexus_data' class='taxjar-field'></div>

</div>

<h3 class="accordion-control-element">%programText:NoFraud%</h3>
<div id="tab_nofraud">
	<p><img class='integration-logo' src="/images/nofraud_logo.png"></p>
	<p><span class='system-name'>coreFORCE lite</span> integrates with NoFraud to verify eCommerce transactions and detect fraud. If NoFraud returns "fail" for an order, <span class='system-name'>coreFORCE lite</span> will void the payment and the order will be cancelled automatically.</p>

	<p>To connect <span class='system-name'>coreFORCE lite</span> to NoFraud, create an integration for <span class='system-name'>coreFORCE lite</span> in NoFraud (Integrations on the left sidebar), select integration type of "Direct API" and enter your NoFraud API Key below.</p>
	%field:nofraud_token%
	%basic_form_line%

	<p>NoFraud also requires Javascript on your site to verify purchase activity.  Enter your customer number below to enable this. It should look something like this:</p>

	<pre style="background-color: #f0f0f0; padding: 10px" >&lt;script&gt; type='text/javascript' src='https://services.nofraud.com/js/<span class="red-text">CUSTOMER_NUMBER_HERE</span>/customer_code.js' &lt;/script&gt;</pre>

	%field:nofraud_customer_number%
	%basic_form_line%

	<p><strong>Note:</strong> If "passive mode" is enabled in NoFraud, no orders will be cancelled for fraud risk.  If NoFraud is running in Passive Mode, it is highly recommended to turn on "Capture payment at shipment" to avoid merchant fees on refunds.</p>

	<p>
		%method:checkNoFraudSetup%
	</p>

</div>

<h3 class="accordion-control-element">%programText:Emails%</h3>
<div id="tab_emails">

</div>

<h3 class="accordion-control-element">%programText:Notifications%</h3>
<div id="tab_notifications">

</div>

</div> <!-- accordion-form -->
</div> <!-- _maintenance_form -->
