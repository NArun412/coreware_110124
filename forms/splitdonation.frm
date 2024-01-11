<div id="_maintenance_form">

<h3 class='red-text'>This page should be used carefully and with an understanding of the tax, accounting and audit implications.</h3>

%field:donation_id,donation_date,contact_id,payment_method_id,reference_number,amount,designation_id%
%basic_form_line%
%end repeat%

<p>When saving this record, the following will happen:</p>
<ul>
<li>The original donation, above, will be reduced by the split amount. If the split amount equals the amount above, the original donation will be removed.</li>
<li>A new donation, with the exact same data except the new amount and new designation, will be created.</li>
</ul>
%field:split_amount,new_designation_id%
%basic_form_line%
%end repeat%

</div> <!-- maintenance_form -->
