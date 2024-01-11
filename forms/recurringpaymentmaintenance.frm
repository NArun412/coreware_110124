<div id="_maintenance_form">

<h2>Contact</h2>
%field:contact_id,contact_first_name,contact_last_name,contact_business_name,contact_address_1,contact_city,contact_state,contact_postal_code,contact_email_address,shipping_method_id,promotion_id%
%basic_form_line%
%end repeat%

<h2>Recurring Payment Information</h2>
%field:recurring_payment_type_id,recurring_payment_order_items,start_date,next_billing_date,end_date,requires_attention%
%basic_form_line%
%end repeat%

%field:contact_subscription_id_display%
%basic_form_line%
<span class="extra-info" id="contact_subscription_message"></span>

%if:canAccessPageCode("CONTACTMAINT")%
%field:contact_notes%
%basic_form_line%
%endif%

%field:notes,last_attempted,error_message%
%basic_form_line%
%end repeat%

<div id="_payment_section">
<h2>Payment Information</h2>

%field:account_id%
%basic_form_line%
%if:canAccessPageCode("ACCOUNTMAINT")%
<p id="edit_existing_account_wrapper"><button id='edit_existing_account'>Edit Account</button></p>
%endif%

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
