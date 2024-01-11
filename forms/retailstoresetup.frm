<div id="_maintenance_form">
<div class="accordion-form">
	<ul class="tab-control-element">
		<li><a href="#tab_products">%programText:Products%</a></li>
		<li><a href="#tab_pages">%programText:Pages%</a></li>
		<li><a href="#tab_results">%programText:Results%</a></li>
		<li><a href="#tab_details">%programText:Details%</a></li>
		<li><a href="#tab_email">%programText:Email Account%</a></li>
		<li><a href="#tab_merchant">%programText:Merchant Account%</a></li>
		<li><a href="#tab_distributors">%programText:Distributors%</a></li>
%if:in_array('CORE_FFL',$GLOBALS['gClientSubsystemCodes'])%
		<li><a href="#tab_catalog">%programText:Catalog%</a></li>
%endif%
		<li><a href="#tab_taxonomy">%programText:Taxonomy%</a></li>
		<li><a href="#tab_gift_cards">%programText:Gift Cards%</a></li>
%if:canAccessPageCode('AUCTIONITEMMAINT')%
		<li><a href="#tab_auctions">%programText:Auctions%</a></li>
%endif%
		<li><a href="#tab_pricing">%programText:Pricing%</a></li>
		<li><a href="#tab_emails">%programText:Emails%</a></li>
		<li><a href="#tab_notifications">%programText:Notifications%</a></li>
		<li><a href="#tab_fragments">%programText:Fragments%</a></li>
		<li><a href="#tab_settings">%programText:Settings%</a></li>
%if:in_array('CORE_FFL',$GLOBALS['gClientSubsystemCodes'])%
		<li><a href="#tab_ammo_seek">%programText:AmmoSeek%</a></li>
%endif%
		<li><a href="#tab_activecampaign">%programText:ActiveCampaign%</a></li>
%if:in_array($GLOBALS['gClientRow']['client_code'],explode(",",getPreference("ENABLE_HIGHLEVEL_CLIENTS")))%
		<li><a href="#tab_highlevel">%programText:coreILLA%</a></li>
%endif%
		<li><a href="#tab_corestore">%programText:coreSTORE%</a></li>
		<li><a href="#tab_credova">%programText:Credova%</a></li>
		<li><a href="#tab_easypost">%programText:EasyPost%</a></li>
%if:in_array('CORE_FFL',$GLOBALS['gClientSubsystemCodes'])%
		<li><a href="#tab_flp">%programText:FLP%</a></li>
		<li><a href="#tab_gunbroker">%programText:GunBroker%</a></li>
		<li><a href="#tab_gundeals">%programText:gun.deals%</a></li>
%endif%
%if:in_array($GLOBALS['gClientRow']['client_code'],explode(",",getPreference("ENABLE_GUN_SALE_FINDER_CLIENTS")))%
		<li><a href="#tab_gunsalefinder">%programText:Gun Sale Finder%</a></li>
%endif%
		<li><a href="#tab_highcapdeals">%programText:High Cap Deals%</a></li>
		<li><a href="#tab_infusionsoft">%programText:Infusionsoft%</a></li>
%if:in_array($GLOBALS['gClientRow']['client_code'],explode(",",getPreference("ENABLE_LISTRAK_CLIENTS")))%
		<li><a href="#tab_listrak">%programText:Listrak%</a></li>
%endif%
		<li><a href="#tab_nofraud">%programText:NoFraud%</a></li>
		<li><a href="#tab_taxjar">%programText:TaxJar%</a></li>
		<li><a href="#tab_twilio">%programText:Twilio%</a></li>
		<li><a href="#tab_yotpo">%programText:Yotpo%</a></li>
		<li><a href="#tab_zaius">%programText:Zaius%</a></li>
	</ul>


<h3 class="accordion-control-element">%programText:Products%</h3>
<div id="tab_products">
<div id="using_ffl_wrapper">
<p>Will you be selling products that require a dealer with an Federal Firearms License? This would include any type of handgun, rifle, or shotgun. Choosing Yes will create some product tags and set up the custom fields required for FFL products.</p>

<input type="hidden" id="using_ffl" name="using_ffl" value="">
<p><button id="using_ffl_button">Yes, the store sells FFL products</button></p>
%field:hide_using_ffl%
%basic_form_line%
</div>

<div class="ffl-store">
<p>If you are selling FFL products, coreFORCE can import a default set of categories, category groups, departments and manufacturers. The manufacturers will include logos, correct MAP settings, and drop ship rules. This can be safely run multiple times. Manufacturers can take some time, so they are tagged to be sync'd in the background. That process can take as much as 30 minutes or more. We recommend that you set your system to ALWAYS sync FFL dealers. You can do that in the settings tab.</p>

<input type="hidden" id="import_defaults" name="import_defaults" value="">
<p><button id="import_defaults_button">Yes, import defaults</button></p>

<p>If you are selling FFL products, coreFORCE can also import a current list of FFL dealers, so your customers can choose a dealer to which to have their FFL products delivered. Importing is done in the background and can take a couple hours to complete. This import is going to result in about 80,000 FFL dealers being added to your system. Make sure you really want to do this. This can safely be run multiple times and should be run monthly to import new FFL dealers and update licenses and expiration dates.</p>

<input type="hidden" id="import_ffl_dealers" name="import_ffl_dealers" value="">
<p><button id="import_ffl_dealers_button">Yes, import FFL Dealers</button></p>
</div>

<p>You can import products by going to Products->CSV Import.</p>

</div>


<h3 class="accordion-control-element">%programText:Pages%</h3>
<div id="tab_pages">
	<p>Basic pages used in the retail store. Checked pages will be created IF a template is selected. If a Template is not selected, NO pages will be created. Select a template to see a list of possible pages. Starred pages are important for full functionality of the online store, so will default to being created.</p>

%field:template_id%
%basic_form_line%

<div id="page_list" class="hidden">
</div>

<p class='red-text'>DO NOT put the Domain name that is used for the backend in this field. This domain name is substituted into emails that go to customers.</p>

%field:domain_name%
%basic_form_line%

<p>coreFORCE can provide feeds for Gun.deals, WikiArms, Ammoseek, and Google Products. Add your domain name above and the links will appear below.</p>
<div id="feed_pages">
</div>

</div>


<h3 class="accordion-control-element">%programText:Results%</h3>
<div id="tab_results">
<p>Choose the layout that will be the default layout for product search results. If you don't choose one, the system default will be used. If you have a custom layout for your store, none of these will be used.</p>
<div id="product_result_html_options">
</div>

<input type="hidden" id="product_result_html_fragment_id" name="product_result_html_fragment_id" value="">

</div>


<h3 class="accordion-control-element">%programText:Details%</h3>
<div id="tab_details">
<p>Choose the layout that will be the default layout for the product detail page. If you don't choose one, the system default will be used. If you have a custom layout for your store, none of these will be used.</p>
<div id="product_detail_html_options">
</div>

<input type="hidden" id="product_detail_html_fragment_id" name="product_detail_html_fragment_id" value="">

</div>


<h3 class="accordion-control-element">%programText:Gift Cards%</h3>
<div id="tab_gift_cards">
<p>Will you be offering Gift Cards? This will require creating a product type and a gift card product. A gift card page can be created on the page tab. Once the product is created, you are encouraged to do the following in Products->Products:</p>
<ul>
<li>Add an image</li>
<li>Create a detailed description with the terms of use of the card</li>
<li>Limit payment methods that can be used to purchase a gift card to credit cards or ACH</li>
</ul>

<input type="hidden" id="create_gift_cards" name="create_gift_cards" value="">
<p id="create_gift_cards_wrapper"><button id="create_gift_cards_button">Yes, the store will offer gift cards</button></p>

<div id="loyalty_program_wrapper">
<p>Will you have a loyalty program for users? If checked, a basic program will be created. Changes can be made to the program at Orders->Loyalty Programs.</p>

<input type="hidden" id="create_loyalty_program" name="create_loyalty_program" value="">
<p id="create_loyalty_program_wrapper"><button id="create_loyalty_program_button">Yes, the store will have a loyalty program</button></p>
</div>

</div>


<h3 class="accordion-control-element">%programText:Distributors%</h3>
<div id="tab_distributors">

<div id="product_distributor_wrapper" class="ffl-store">
%field:location_id%
%basic_form_line%

<div id="product_distributor_information">
</div>

</div>
</div>

<h3 class="accordion-control-element">%programText:Taxonomy%</h3>
<div id="tab_taxonomy">

<div id="taxonomy_stats">
</div>

</div>


<h3 class="accordion-control-element">%programText:Email%</h3>
<div id="tab_email">

<p>The system needs email credentials for sending receipts, notifications and other emails. Please enter credentials for your default email sending account. You can manage this or create others at System->Preferences->EMail Credentials. If you are using GMail as your email provider, you MUST turn on the option to "Allow Less Secure Apps". Google sees a server as "less secure" because it doesn't implement two-factor authentication. You can do that <a href="https://myaccount.google.com/lesssecureapps">here</a>.</p>

%field:setup_email_credentials%
%basic_form_line%

%field:email_credentials_full_name%
<div class="basic-form-line email-credential hidden" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

</div>

%field:email_credentials_email_address%
<div class="basic-form-line email-credential hidden" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

</div>

%field:email_credentials_smtp_host%
<div class="basic-form-line email-credential hidden" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

</div>

%field:email_credentials_smtp_port%
<div class="basic-form-line email-credential hidden" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

</div>

%field:email_credentials_security_setting%
<div class="basic-form-line email-credential hidden" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

</div>

%field:email_credentials_smtp_authentication_type%
<div class="basic-form-line email-credential hidden" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

</div>

%field:email_credentials_smtp_user_name%
<div class="basic-form-line email-credential hidden" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

</div>

%field:email_credentials_smtp_password%
<div class="basic-form-line email-credential hidden" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

</div>

<p id="test_email_results" class="email-credential hidden"><button id="test_email">Send Test Email</button></p>

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
	<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

</div>

%field:merchant_accounts_account_login%
<div class="basic-form-line merchant-account hidden" id="_%column_name%_row" data-column_name="account_login">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

</div>

%field:merchant_accounts_account_key%
<div class="basic-form-line merchant-account hidden" id="_%column_name%_row" data-column_name="account_key">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

</div>

%field:merchant_accounts_merchant_identifier%
<div class="basic-form-line merchant-account hidden" id="_%column_name%_row" data-column_name="merchant_identifier">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

</div>

%field:merchant_accounts_link_url%
<div class="basic-form-line merchant-account hidden" id="_%column_name%_row" data-column_name="link_url">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

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
<p>coreFORCE integrates with EasyPost for creating shipping labels. Add your EasyPost API Key here. If you don't have an EasyPost account, you can <a href='https://www.easypost.com/signup'>create one here.</a></p>

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

%if:in_array("CORE_FFL",$GLOBALS['gClientSubsystemCodes'])%
<h3 class="accordion-control-element">%programText:FLP%</h3>
<div id="tab_flp">
	<p><img class='integration-logo' src="/images/flp_logo.png"></p>
	<p>coreFORCE integrates with Firearms Legal Protection to allow selling FLP memberships online. Add your Partner ID from FLP here to enable it. If you have not registered as a partner with FLP yet, you can do so <a target="_blank" href="https://firearmslegal.com/area-managers/partnership-program/">here</a>.</p>

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
<p>coreFORCE integrates with GunBroker for listing your products. Add your username & password for GunBroker here to enable it. If you don't have a GunBroker account, you can <a href="https://www.gunbroker.com/newregistration/signupdetails">create one here.</a></p>

%field:gunbroker_username,gunbroker_password,gunbroker_auto_email,gunbroker_auto_create_orders,gunbroker_create_unpaid_orders%
%basic_form_line%
%end repeat%

<p>If using auto email for GunBroker orders, it will only work for orders on GB that meet the following criteria:</p>
<ul>
<li>Only includes valid products with a UPC</li>
<li>The price the product(s) sold for on GunBroker is at or less than the price the product sells for on your site</li>
<li>Payment has NOT been received on GunBroker</li>
<li>An email has not yet been sent to the customer about the order</li>
<li>GunBroker has an email for the customer</li>
</ul>
%if:getRowFromId("preferences","preference_code","GUNBROKER_ADDITIONAL_PAYMENT_METHODS")%
<br>
<p>By default, listings created from coreFORCE will allow only credit card payments.  If you would like to allow additional payment types, include a list below.</p>
<p>The options that GunBroker allows are: Check, COD, Escrow, PayPal, CertifiedCheck, USPSMoneyOrder, MoneyOrder, and FreedomCoin.</p>
%field:gunbroker_additional_payment_methods%
%basic_form_line%
%endif%
<br>
<p>To prevent overselling, coreFORCE automatically ends GunBroker listings if the product is out of stock.  Also, optionally, coreFORCE can update the quantity of fixed price listings based on
	inventory available. Choose which inventory to use for quantities on GunBroker (If [None] is selected, quantities will never be updated):</p>
%field:gunbroker_inventory_for_fixed_price%
%basic_form_line%

</div>

<h3 class="accordion-control-element">%programText:gun.deals%</h3>
<div id="tab_gundeals">
<p><img class='integration-logo' src="/images/gundeals.png"></p>

<p class="ffl-store">If you have an account with Gun.Deals, the url for your feeds is https://www.yourdomainname.com/gundealsfeed.php. (Of course, replace 'yourdomainname' with your actual domain name.)</p>

<p>coreFORCE can feed your products to gun.deals. If you don't have a gun.deals account, you can <a href="https://gun.deals/user/register">register here.</a></p>

<div id="gundeals_settings">
%method:gundealsPreferences%
</div>

</div>

%if:in_array($GLOBALS['gClientRow']['client_code'],explode(",",getPreference("ENABLE_GUN_SALE_FINDER_CLIENTS")))%
<h3 class="accordion-control-element">%programText:Gun Sale Finder%</h3>
<div id="tab_gunsalefinder">

	<p><img class='integration-logo' src="/images/gunsalefinder-logo-v1.png"></p>

	<p class="ffl-store">If you have an account with Gun Sale Finder, the url for your feeds is https://www.yourdomainname.com/gunsalefinderfeed.php. (Of course, replace 'yourdomainname' with your actual domain name.)</p>

	<div id="gun_sale_finder_settings">
		%method:gunSaleFinderPreferences%
	</div>

</div>
%endif%

<h3 class="accordion-control-element">%programText:High Cap Deals%</h3>
<div id="tab_highcapdeals">
	<p><img class='integration-logo' src="/images/highcapdeals_logo.png"></p>

	<p class="ffl-store">If you have an account with High Cap Deals, the url for your feeds is https://www.yourdomainname.com/highcapdealsfeed.php. (Of course, replace 'yourdomainname' with your actual domain name.)</p>

	<p>coreFORCE can feed your products to High Cap Deals. If you don't have an account, you can <a href="https://www.highcapdeals.com/merchant/merchant_register.htm">register here.</a></p>

	<div id="highcapdeals_settings">
		%method:highCapDealsPreferences%
	</div>
</div>

<h3 class="accordion-control-element">%programText:AmmoSeek%</h3>
<div id="tab_ammo_seek">
<p><img class='integration-logo' src="/images/ammoseek.png"></p>

<p class="ffl-store">If you have an account with AmmoSeek, the url for your feeds is https://www.yourdomainname.com/ammoseekfeed.php. (Of course, replace 'yourdomainname' with your actual domain name.)</p>

<p>coreFORCE can feed your products to AmmoSeek. If you don't have an AmmoSeek account, you can <a href="https://ammoseek.com/signin">join here.</a></p>

<div id="ammo_seek_settings">
%method:ammoSeekPreferences%
</div>

</div>
%endif%

<h3 class="accordion-control-element">%programText:activeCampaign%</h3>
<div id="tab_activecampaign">
<p><img class='integration-logo' src="/images/activecampaign_logo.png"></p>
<p>coreFORCE integrates with ActiveCampaign for marketing, website and ecommerce analytics. Put your ActiveCampaign API Key here.</p>

%field:activecampaign_api_key%
%basic_form_line%

<p>Leave the URL blank to use the default:
%method:getActiveCampaignUrl%
</p>
%field:activecampaign_url%
%basic_form_line%

%field:activecampaign_event_key%
%basic_form_line%

	<p>Once the API key is set, you may install ActiveCampaign analytics on your site in <a href="/activecampaignsync.php">ActiveCampaign Sync</a>.</p>

</div>

%if:in_array($GLOBALS['gClientRow']['client_code'],explode(",",getPreference("ENABLE_HIGHLEVEL_CLIENTS")))%
<h3 class="accordion-control-element">%programText:coreILLA%</h3>
<div id="tab_highlevel">
<p><img class='integration-logo' src="/images/coreilla_logo.png"></p>
<p>coreFORCE integrates with coreILLA for marketing. Mailing lists and contact categories will be synced to coreILLA as contact tags.</p>
<p>Click below to authorize coreFORCE to access your coreILLA application. Once authorized, coreFORCE will sync all contacts the next time the background process runs. </p>
<p id="get_highlevel_token_wrapper"><a id="get_highlevel_token" target="_blank">Authorize coreFORCE in coreILLA</a></p>
<span id='highlevel_authorized'></span>
</div>
%endif%

<h3 class="accordion-control-element">%programText:Zaius%</h3>
<div id="tab_zaius">
<p><img class='integration-logo' src="/images/zaius_logo.png"></p>
<p>coreFORCE integrates with Zaius for website and ecommerce analytics. Put your Zaius API Private Key here. If you don't have a Zaius account, you can <a href='https://www.zaius.com/trial/'>create one here.</a></p>

%field:zaius_api_key%
%basic_form_line%

%field:zaius_use_upc%
%basic_form_line%

<p>To send some product facets to Zaius in the product upload background process, enter a comma-separted list of Product Facet Codes here</p>
%field:zaius_send_facets%
%basic_form_line%

</div>

<h3 class="accordion-control-element">%programText:Infusionsoft%</h3>
<div id="tab_infusionsoft">
<p><img class='integration-logo' src="/images/infusionsoft.png"></p>
<p>coreFORCE integrates with Infusionsoft for website and ecommerce analytics. Click below to authorize coreFORCE to access your InfusionSoft application.</p>

<p id="get_infusionsoft_token_wrapper"><a id="get_infusionsoft_token" target="_blank">Authorize coreFORCE in Infusionsoft</a></p>
<span id='infusionsoft_authorized'></span>

</div>
%if:in_array($GLOBALS['gClientRow']['client_code'],explode(",",getPreference("ENABLE_LISTRAK_CLIENTS")))%

	<h3 class="accordion-control-element">%programText:Listrak%</h3>
	<div id="tab_listrak">
		<p><img class='integration-logo' src="/images/listrak_logo.png"></p>
		<p>coreFORCE integrates with Listrak for website and eCommerce analytics.</p>

		<p>To connect coreFORCE to Listrak, create an integration for coreFORCE in Listrak (Manage > Integrations), select integration type of "Data" and enter your Client ID and Client Secret below.</p>
		%field:listrak_client_id%
		%basic_form_line%

		%field:listrak_client_secret%
		%basic_form_line%

		<p>
		%method:checkListrakSetup%
		</p>

		<p>Select which coreFORCE contacts to sync with Listrak via the SyncCRM background process.</p>
		%field:listrak_sync_which_contacts%
		%basic_form_line%

		<p>To initially populate Listrak with historical data, check the following to sync all orders with Listrak.  This will happen when the background process runs.</p>
		%field:listrak_sync_order_history%
		%basic_form_line%

	</div>
%endif%
	<h3 class="accordion-control-element">%programText:NoFraud%</h3>
	<div id="tab_nofraud">
		<p><img class='integration-logo' src="/images/nofraud_logo.png"></p>
		<p>coreFORCE integrates with NoFraud to verify eCommerce transactions and detect fraud. If NoFraud returns "fail" for an order, coreFORCE will void the payment and the order will be cancelled automatically.</p>

		<p>To connect coreFORCE to NoFraud, create an integration for coreFORCE in NoFraud (Integrations on the left sidebar), select integration type of "Direct API" and enter your NoFraud API Key below.</p>
		%field:nofraud_token%
		%basic_form_line%

		<p>NoFraud also requires Javascript on your site to verify purchase activity.  Enter your customer number below to enable this. It should look something like this:</p>

		<pre style="background-color: #f0f0f0; padding: 10px" >&lt;script&gt; type='text/javascript' src='https://services.nofraud.com/js/<span class="red-text">CUSTOMER_NUMBER_HERE</span>/customer_code.js' &lt;/script&gt;</pre>

		%field:nofraud_customer_number%
		%basic_form_line%

		<p>By default, orders in which all products require FFL will not be sent to NoFraud due to lower risk of fraud in a face-to-face transaction. To check these orders anyway, turn on this setting.</p>
		%field:nofraud_check_ffl_orders%
		%basic_form_line%

		<p><strong>Note:</strong> If "passive mode" is enabled in NoFraud, no orders will be cancelled for fraud risk.  If NoFraud is running in Passive Mode, it is highly recommended to turn on "Capture payment at shipment" to avoid merchant fees on refunds.</p>

		<p>
		%method:checkNoFraudSetup%
		</p>

	</div>
<h3 class="accordion-control-element">%programText:Yotpo%</h3>
<div id="tab_yotpo">
<p><img class='integration-logo' src="/images/yotpo.png"></p>
<p>coreFORCE integrates with Yotpo for reviews and loyalty programs. Yotpo includes multiple products which integrate separately.</p>

<p><strong>Yotpo Reviews and Ratings (R&R)</strong>: To connect coreFORCE to Yotpo R&R, enter your App Key and Secret Key from Store Settings below.</p>
%field:yotpo_app_key%
%basic_form_line%

%field:yotpo_secret_key%
%basic_form_line%

<p><strong>Yotpo Loyalty and Referrals (L&R)</strong>: To connect coreFORCE to Yotpo L&R, enter your API Key and GUID from Settings > General below.</p>
%field:yotpo_loyalty_api_key%
%basic_form_line%

%field:yotpo_loyalty_guid%
%basic_form_line%

</div>

<h3 class="accordion-control-element">%programText:TaxJar%</h3>
<div id="tab_taxjar">

<p><img class='integration-logo' src="/images/taxjar.svg"></p>
<p>coreFORCE integrates with TaxJar for calculating and reporting your taxes. If you have a TaxJar account, enter the API token here. If not, you can <a href='https://app.taxjar.com/api_sign_up'>signup for TaxJar here</a></p>

%field:taxjar_api_token%
%basic_form_line%
%end repeat%

<div id='taxjar_validation'></div>

%field:taxjar_api_reporting%
%basic_form_line%
%end repeat%

<div id='taxjar_nexus_data' class='taxjar-field'></div>

</div>

<h3 class="accordion-control-element">%programText:coreSTORE%</h3>
<div id="tab_corestore">

<p><img class='integration-logo' src="/images/corestore.jpg"></p>
<p>coreSTORE fully integrates with coreFORCE and provides retailers with an easy to use, flexible point of sale solution that can be easily configured and handle single and multi-location retail operations. For more information, go to <a href='https://www.corestore.info' target='_blank'>corestore.info</a>.</p>

%field:corestore_api_key,corestore_endpoint,corestore_url%
%basic_form_line%
%end repeat%

</div>

<h3 class="accordion-control-element">%programText:Credova%</h3>
<div id="tab_credova">

<p><img class='integration-logo' src="/images/credova.png"></p>
<p>coreFORCE fully integrates with Credova Financial. With Credova's easy to use buy now, pay later payment option, your customers know their terms up front and can shop for what they want, when they want and then pay over time. For more information, go to <a href='https://credova.com' target='_blank'>credova.com</a></p>

%field:credova_username,credova_password%
%basic_form_line%
%end repeat%

	<p>
		%method:checkCredovaSetup%
	</p>


</div>

<h3 class="accordion-control-element">%programText:Twilio%</h3>
<div id="tab_twilio">

<p><img class='integration-logo' src="/images/twilio.png"></p>
<p>coreFORCE can send text notification to your customers. Enter the Twilio Account SID, Auth Token, and the "from" phone number. All three of these are required for SMS messages to work. After saving changes, you can send a test Text Message. For more information or to sign up for a Twilio account, go to <a href='https://twilio.com' target='_blank'>twilio.com</a></p>

%field:twilio_account_sid,twilio_auth_token,twilio_from_number%
%basic_form_line%
%end repeat%

<p class='red-text' id="twilio_error_message"></p>
<p id="send_text_message_wrapper"><button id="send_text_message">Send Test Text</button></p>

</div>

%if:canAccessPageCode('AUCTIONITEMMAINT')%
<h3 class="accordion-control-element">%programText:Auctions%</h3>
<div id="tab_auctions">

<p>Minimum amount over the current bid the next bid can be. The default is $1.00.</p>
%field:auction_bid_increment%
%basic_form_line%
%end repeat%

<p>The amount of minutes to extend the sale of an auction item after the last bid. This is to prevent people from bidding at the last second and "stealing" the item from someone else. The default is 2 minutes. It can be set to zero, if the seller does not want to extend the auction at all.</p>
%field:auction_bid_close_extension%
%basic_form_line%
%end repeat%

<p>The internet can be unpredictable and slow, at times. This setting lets the seller set a number of seconds after the close of an auction to still accept bids to account for slow internet connections and other kinds of delays. Typically, this shouldn't be high. The default is 3 seconds.</p>
%field:auction_grace_setting%
%basic_form_line%
%end repeat%

</div>
%endif%

<h3 class="accordion-control-element">%programText:Pricing%</h3>
<div id="tab_pricing">
<p>coreFORCE will calculate the sales price of the store products based on pricing structures. You can create any number of pricing structures at Products->Pricing->Pricing Structures. These pricing structures can be applied to product categories, individual products, product types or numerous other groupings of products and can be calculated as a markup from cost or a discount from list price. There should, however, be a Default pricing structure as a catch-all for any products that are not covered otherwise. Create or update this default pricing structure here.</p>

%field:setup_pricing_structure%
%basic_form_line%

%field:pricing_structure_percentage%
<div class="basic-form-line pricing-structure hidden" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

</div>

%field:pricing_structure_price_calculation_type_id%
<div class="basic-form-line pricing-structure hidden" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

</div>

</div>


<h3 class="accordion-control-element">%programText:Settings%</h3>
<div id="tab_settings">

</div>

<h3 class="accordion-control-element">%programText:Emails%</h3>
<div id="tab_emails">

</div>

<h3 class="accordion-control-element">%programText:Notifications%</h3>
<div id="tab_notifications">

</div>

<h3 class="accordion-control-element">%programText:Fragments%</h3>
<div id="tab_fragments">

</div>

%if:in_array('CORE_FFL',$GLOBALS['gClientSubsystemCodes'])%
<h3 class="accordion-control-element">%programText:Catalog%</h3>
<div id="tab_catalog">
<p>Coreware, LLC maintains the Coreware Shooting Sports Catalog (CSSC) of products available from all distributors. This catalog is regularly updated and maintained. You, the dealer, can benefit from this by allowing the CSSC to update your catalog. At times, though, you might not want the CSSC to update your catalog, for instance if you spend a lot of time update product descriptions.</p>
<p>This is a list of fields that CSSC can update. Choose the setting for each field. You can also go to Products->Products and, on any individual product, in the Details tab, set that product to never update.</p>
%method:productUpdateFields%
</div>
%endif%

</div> <!-- accordion-form -->
</div> <!-- _maintenance_form -->
