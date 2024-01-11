<div id="_maintenance_form">

<input type="hidden" id="date_completed" name="date_completed">
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
		<p><button id="print_receipt" class="keep-visible">Print Receipt</button></p>
		<p><button id="resend_receipt" class="keep-visible">Resend Receipt</button></p>
		<p><button id="change_contact">Change Contact Info</button></p>
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
		<input type='hidden' id='address_1'>
		<input type='hidden' id='address_2'>
		<input type='hidden' id='city'>
		<input type='hidden' id='state'>
		<input type='hidden' id='postal_code'>
		<p id="shipping_address"></p>
		<p><label>Method</label> <span id="shipping_method_display"></span></p>
		<p><label>Charge</label> <span id="shipping_charge"></span></p>
	</div>
</div>

<div id="payment_warning"></div>

%method:orderTags%

<h2>Items</h2>
<p class="error-message"></p>
<table id="order_items" class="order-information">
<tr>
	<th>Item</th>
	<th></th>
	<th class='align-right'>Qty</th>
	<th class='align-right'>Price</th>
	<th class='align-right'>Total</th>
</tr>
</table>

<h2>Payments</h2>
<div class='form-line' id="_show_deleted_payments_row">
<input type="checkbox" id="show_deleted_payments" name="show_deleted_payments" value="1"><label class="checkbox-label" for="show_deleted_payments">Show Deleted Payments</label>
<div class='clear-div'></div>
</div>
<table id="order_payments" class="order-information">
<tr>
	<th>Payment Date</th>
	<th>Method</th>
	<th>Account/Ref #</th>
	<th>Capture</th>
	<th>Trans #</th>
	<th class="align-right">Amount</th>
	<th class="align-right">Shipping<br>Charge</th>
	<th class="align-right">Tax<br>Charge</th>
	<th class="align-right">Handling<br>Charge</th>
	<th class="align-right">Total</th>
	<th></th>
</tr>
</table>

<p><button id="add_payment">Add Payment/Refund</button></p>

<h2>Touchpoints</h2>
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
	<th>Date Submitted</th>
	<th>Description</th>
	<th>Date Closed</th>
</tr>
</table>

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

<h2>Files</h2>
<table id="order_files" class="order-information">
</table>

%field:order_files_description,order_files_file_id,add_file%
%basic_form_line%
%end repeat%

<div id="jquery_templates">
</div>

</div>
