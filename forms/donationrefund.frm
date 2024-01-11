<div id="_maintenance_form">

<div id="instructions">
    <p class='highlighted-text green-text'>Refunding this donation will create a refund in the merchant gateway and a backout in Coreware. IF the refund does not succeed, the backout will NOT be created. Note that, typically, refunds cannot be issued until the donation is settled at the Merchant Gateway, which is, typically, the end of the day on which the donation was made.</p>
    <p class='highlighted-text red-text'>Once processed, the refund CANNOT be reversed, so be sure this is the correct donation.</p>
    <p><button id='process_refund'>Refund this donation</button></p>
</div>

%field:donation_id,donation_date,contact_id,payment_method_id,reference_number,amount,designation_id,anonymous_gift,donation_source_id,receipted_contact_id,notes,donation_fee%
%basic_form_line%
%end repeat%

</div> <!-- maintenance_form -->
