<div id="_maintenance_form">
<p class='green-text'>License Files are now in the Details tab</p>
<div class="accordion-form">
	<ul class="tab-control-element">
		<li><a href="#tab_1">%programText:Details%</a></li>
		<li><a href="#tab_2">%programText:Store Address%</a></li>
		<li><a href="#tab_4">%programText:Mailing Address%</a></li>
		<li><a href="#tab_4a">%programText:Contacts%</a></li>
		<li><a href="#tab_5">%programText:Resources%</a></li>
		<li><a href="#tab_6">%programText:Terms%</a></li>
		<li><a href="#tab_7">%programText:Hours%</a></li>
		<li><a href="#tab_8">%programText:Services%</a></li>
		<li><a href="#tab_9">%programText:Custom%</a></li>
	</ul>

<h3 class="accordion-control-element">%programText:Details%</h3>
	<div id="tab_1">

%if:($GLOBALS['gDefaultClientId'] != $GLOBALS['gClientId'] && !empty(getPreference("CENTRALIZED_FFL_STORAGE")))%
<p class='centralized-editable'>Only fields with a purple label are editable.</p>
%endif%
%if:($GLOBALS['gDefaultClientId'] == $GLOBALS['gClientId'] || empty(getPreference("CENTRALIZED_FFL_STORAGE")))%
<p class='red-text'>Business name and address information comes from the ATF. Changes made will be overwritten by data from the ATF.</p>
%endif%

%field:contact_id_display,license_number,licensee_name,business_name,file_id,sot_file_id,date_created,expiration_date,mailing_address_preferred,preferred,inactive,notes%
%basic_form_line%
%end repeat%

	</div>

<h3 class="accordion-control-element">%programText:Store Address%</h3>
	<div id="tab_2">

<p class='red-text'>Business name and address information comes from the ATF. Changes made will be overwritten by data from the ATF.</p>

%field:address_1,address_2,city,city_select,state,postal_code,latitude,longitude,phone_number,email_address,web_page,ffl_locations%
%basic_form_line%
%end repeat%

	</div>

<h3 class="accordion-control-element">%programText:Mailing Address%</h3>
	<div id="tab_4">
%field:mailing_address_1,mailing_address_2,mailing_city,mailing_city_select,mailing_state,mailing_postal_code%
%basic_form_line%
%end repeat%

	</div>

<h3 class="accordion-control-element">%programText:Contacts%</h3>
	<div id="tab_4a">
%field:ffl_contacts%
%basic_form_line%
	</div>

<h3 class="accordion-control-element">%programText:Resources%</h3>
	<div id="tab_5">

%field:image_id,ffl_images,ffl_files,ffl_videos%
%basic_form_line%
%end repeat%

	</div>

<h3 class="accordion-control-element">%programText:Terms%</h3>
	<div id="tab_6">

%field:public_content,content%
%basic_form_line%
%end repeat%

	</div>

<h3 class="accordion-control-element">%programText:Hours%</h3>
	<div id="tab_7">
%method:availabilityHours%
	</div>

<h3 class="accordion-control-element">%programText:Services%</h3>
	<div id="tab_8">
%field:ffl_product_manufacturers,ffl_product_departments%
%basic_form_line%
%end repeat%
	</div>

<h3 class="accordion-control-element">%programText:Custom%</h3>
	<div id="tab_9">
%method:addCustomFields%
	</div>

</div> <!-- accordion-form -->

</div> <!-- maintenance_form -->
