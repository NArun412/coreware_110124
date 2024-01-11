<div id="_maintenance_form">
<h2>Contact Information</h2>
%field:first_name,last_name,address_1,address_2,city,state,postal_code,country_id,merchant_identifier%
%basic_form_line%
%end repeat%

<h2>Account Information</h2>
%field:account_label,payment_method_id,expiration_date%
%basic_form_line%
%end repeat%

<h2>New Merchant Services Information</h2>
<p>Changes to this information will not affect the contact information. It will only change the payment information on the Merchant account.</p>

<p><button id="copy_contact">Copy Contact Information</button></p>

%field:bill_to_first_name,bill_to_last_name,bill_to_business_name,bill_to_address_1,bill_to_city,bill_to_state,bill_to_postal_code,bill_to_country%
%basic_form_line%
%end repeat%

<div id="credit_card_info">
%field:expiration_month,expiration_year%
%basic_form_line%
%end repeat%
<div>

</div> <!-- maintenance_form -->
