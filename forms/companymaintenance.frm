<div class="tabbed-form">
	<ul>
		<li><a href="#tab_1">Details</a></li>
		<li><a href="#tab_2">Custom</a></li>
	</ul>

	<div id="tab_1">

%field:company_type_id%
%basic_form_line%

%method:addCustomNameFields%
%field:business_name,first_name,last_name%
%basic_form_line%
%end repeat%

%method:addCustomFieldsBeforeAddress%

%field:address_1,address_2,city,city_select,state,postal_code,country_id%
%basic_form_line%
%end repeat%

%method:addCustomFieldsAfterAddress%

%field:phone_numbers,email_address,web_page%
%basic_form_line%
%end repeat%

%method:addCustomContactFields%

%field:inactive%
%basic_form_line%

	<div class='clear-div'></div>
	</div>

	<div id="tab_2">
%method:addCustomFields%
	</div>

</div>
