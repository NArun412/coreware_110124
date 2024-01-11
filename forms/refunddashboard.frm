<div id="_maintenance_form">

<input type="hidden" id="date_completed" name="date_completed">
<input type="hidden" id="restocking_fee_percentage" name="restocking_fee_percentage">
<div id="order_status_wrapper">
<div id="order_status_display"></div>
<div id="order_id_wrapper">Order # <span id="order_id"></span></div>
</div>
<div id="order_information_block">
	<div id="contact_information">
		<p id="full_name"></p>
		<p id="order_time"></p>
		<p id="email_address"></p>
		<p id="phone_number"></p>
		<div id="contact_identifiers"></div>
		<p id="ip_address_message"></p>
		<p><button id="send_email" class="keep-visible">Send Email</button></p>
	</div>
	<div id="billing_information">
		<h4>Billing Address</h4>
		<p id="billing_address"></p>
		<p><label>Order Method</label> <span id="order_method_display"></span></p>

%field:order_status_id%
%basic_form_line%

<p id="date_completed_wrapper"></p>
<p id="order_promotion"></p>
<p><label>Order Discount</label> <span id="order_discount"></span></p>

	</div>
	<div id="shipping_information">
		<h4>Shipping Details</h4>
		<p id="shipping_address"></p>
		<p><label>Method</label> <span id="shipping_method_display"></span></p>
		<p><label>Shipping Charge</label> <span id="shipping_charge"></span></p>
		<p><label>Handling Charge</label> <span id="handling_charge"></span></p>
		<p id="_order_total_wrapper"><label>Refundable Amount</label> <span id="order_total"></span></p>
	</div>
</div>

<div id="details_wrapper">
    <p><button id="refund_all">Refund Full Order</button></p>

    <h2>Items</h2>
    <p class="error-message"></p>
    <table id="order_items" class="order-information">
    <tr>
        <th>Returned<br>Quantity</th>
        <th>Item</th>
        <th class='align-right'>Qty</th>
        <th class='align-right'>Price</th>
        <th class='align-right'>Total Tax</th>
        <th class='align-right'>Total</th>
    </tr>
    </table>

    <div class='basic-form-line'>
        <input type='checkbox' id='refund_shipping' name='refund_shipping' value='1'><label class='checkbox-label' for='refund_shipping'>Refund Shipping/Handling</label>
    </div>

    <div class='basic-form-line'>
        <label>Restocking Fee</label>
        <input size="12" class='validate[custom[number],min[0]] align-right' data-decimal-places="2" type='text' id='restocking_fee' name='restocking_fee' value=''>
		<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
    </div>

    <h2>Payments</h2>
    <table id="order_payments" class="order-information">
    <tr>
        <th>Payment Method</th>
        <th>Account #</th>
        <th class="align-right">Item Amount</th>
        <th class="align-right">Shipping<br>Charge</th>
        <th class="align-right">Tax<br>Charge</th>
        <th class="align-right">Handling<br>Charge</th>
        <th class="align-right">Total & Refund</th>
    </tr>
    </table>

    <p class="error-message"></p>

    <div class='basic-form-line'>
        <input type='checkbox' id='send_gift_card' name='send_gift_card' value='1'><label class='checkbox-label' for='send_gift_card'>Send Gift Card instead of refunding payment method(s)</label>
    </div>

    <p><button id="process_refund">Process Refund</button></p>

    <h2>Shipments</h2>

    <table id="order_shipments" class="order-information">
    <tr>
        <th>Shipped</th>
        <th>From</th>
        <th>#</th>
        <th>To</th>
        <th class="align-right">Charge</th>
        <th>Tracking ID</th>
        <th colspan="2">Carrier</th>
        <th colspan="2">Notes</th>
    </tr>
    </table>
</div>

<h2>Notes</h2>
<table id="order_notes" class="order-information">
<tr>
	<th>Time Created</th>
	<th>By</th>
	<th>Customer</th>
	<th>Notes</th>
</tr>
</table>

%field:content,public_access,add_note%
%basic_form_line%
%end repeat%

<h2>Touchpoints</h2>
<p><input type='checkbox' name='show_order_touchpoints' id='show_order_touchpoints' value='1'><label class='checkbox-label' for='show_order_touchpoints'>Show Only Touchpoints for this order</label></p>
<table id="touchpoints" class="order-information">
<tr>
	<th>Date</th>
	<th>Type</th>
	<th>Description</th>
	<th>Details</th>
</tr>
</table>

<h2>Help Desk Tickets</h2>
<table id="help_desk_entries" class="order-information">
<tr>
	<th>Ticket #</th>
	<th>Date Submitted</th>
	<th>Description</th>
	<th>Date Closed</th>
</tr>
</table>

</div>
