<div id="_maintenance_form">

<h2>Contact</h2>
%field:contact_id,contact_first_name,contact_last_name,contact_business_name,contact_address_1,contact_city,contact_state,contact_postal_code,contact_email_address,contact_phone_number%
%basic_form_line%
%end repeat%

<p><button id="_activity_button">View User Activity</button></p>

<h2>Recurring Donation Information</h2>
%field:recurring_donation_type_id,amount,start_date,next_billing_date,end_date,designation_id,donation_source_id,anonymous_gift,requires_attention,notes,last_attempted,error_message%
%basic_form_line%
%end repeat%

<div id="_payment_section">
<h2>Payment Information</h2>

%field:account_id%
%basic_form_line%

<div id="new_account">
%field:first_name,last_name,business_name,address_1,postal_code,payment_method_id%
%basic_form_line%
%end repeat%

<div class="payment-method-fields" id="payment_method_credit_card">
%field:account_number,expiration_month,expiration_year,card_code%
%basic_form_line%
%end repeat%

</div>

<div class="payment-method-fields" id="payment_method_bank_account">
%field:routing_number,bank_account_number%
%basic_form_line%
%end repeat%

</div> <!-- payment_method_bank_account -->

</div>
</div>

</div> <!-- maintenance_form -->
