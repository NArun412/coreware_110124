<div id="_maintenance_form">

%field:order_time,location_id,order_number,user_id,distributor_order_status_id,date_completed,notes,add_location_id%
%basic_form_line%
%end repeat%

<p>If adding to inventory, enter your cost for each item (which are prefilled with the distributors last cost), including shipping.</p>

<div class='form-line'>
    <label>Other Charges</label>
    <span class='help-label'>Enter shipping and other misc charges that are on the invoice from the distributor. These can be evenly distributed among the items.</span>
    <input type='text' class='validate[custom[number]] align-right' data-decimal-places='2' size='12' id='misc_charges' name='misc_charges'>
    <div class='clear-div'></div>
</div>

<p><button id="split_charges">Split Other Charges</button></p>

<div id="distributor_order_items">
</div>

<div class='form-line'>
    <label>Total Charges</label>
    <span class='help-label'>Total of the costs of the items.</span>
    <input type='text' readonly="readonly" class='validate[custom[number]] align-right' data-decimal-places='2' size='12' id='total_charges' name='total_charges'>
    <div class='clear-div'></div>
</div>

</div> <!-- maintenance_form -->
