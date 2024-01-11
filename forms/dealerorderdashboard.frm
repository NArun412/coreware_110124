<div id="_maintenance_form">

    <input type="hidden" id="date_completed" name="date_completed">
    <input type="hidden" id="federal_firearms_licensee_id" name="federal_firearms_licensee_id">
    <div id="order_status_wrapper">
        <div id="order_status_display"></div>
        <div id="order_id_wrapper">Order # <span id="order_id_display"></span></div>
    </div>

    <div id="order_information_block">
        <div id="contact_information">
            <p id="contact_name"></p>
            <p id="reverse_white_pages"></p>
            <p id="white_pages"></p>
            <p id="order_time"></p>
            <p id="email_address"></p>
            <p id="phone_number"></p>
            <div id="contact_identifiers"></div>
            <p id="ip_address_message"></p>
            <p><button id="send_email" class="keep-visible">Send Email</button></p>
            <p id="_send_text_message_wrapper"><button id="send_text_message" class="keep-visible">Send Text Message</button></p>
            <p><button id="print_receipt" class="keep-visible">Print Receipt</button></p>
            <p><button id="resend_receipt" class="keep-visible">Resend Receipt</button></p>
        </div>
        <div id="billing_information">
            <h4>Billing Address</h4>
            <p id="billing_address"></p>
            <p><label>Order Method</label> <span id="order_method_display"></span></p>

            %field:order_status_id%
            %basic_form_line%

            <p id="date_completed_wrapper"></p>

        </div>
        <div id="shipping_information">
            <h4>Shipping Details</h4>
            <p id="shipping_address"></p>
            <p><label>Method</label> <span id="shipping_method_display"></span></p>
            <p><label>Source</label> <span id="source_display"></span></p>
            <p><label>Charge</label> <span id="shipping_charge"></span></p>
            <p id='donation_amount_wrapper'></p>

            <p id="order_count"></p>

        </div>
    </div>

    <div id="payment_warning"></div>

    %method:orderTags%

    <h2>Items</h2>
    <p class="error-message"></p>
    <table id="order_items" class="order-information">
        <tr>
            <th>Item</th>
            <th>Status</th>
            <th class='align-center'><span class="ship-all-items fas fa-truck"></span></th>
            <th class='align-right'>Qty</th>
            <th class='align-right'>Price</th>
            <th class='align-right'>Total</th>
            <th></th>
        </tr>
    </table>
    <div id="order_items_quantity"></div>

    <h2>Distributor Orders</h2>
    <p id="_capture_message"></p>
    <p id="_invoice_message"></p>
    <p class="error-message"></p>

    %field:location_id%
    <div class="basic-form-line inline-block" id="_%column_name%_row">
        <label for="%column_name%" class="%label_class%">%form_label%</label>
        %input_control%
		<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
    </div>

    %field:create_shipment%
    <div class="basic-form-line inline-block" id="_%column_name%_row">
        <label for="%column_name%" class="%label_class%">%form_label%</label>
        %input_control%
		<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
    </div>

    <table id="order_shipments" class="order-information">
        <tr>
            <th>Shipped</th>
            <th>From</th>
            <th>#</th>
            <th>To</th>
            <th></th>
            <th class="align-right">Charge</th>
            <th>Tracking ID</th>
            <th colspan="2">Carrier</th>
            <th colspan="2">Notes</th>
        </tr>
    </table>
    <div id="order_shipment_items_quantity"></div>

    <h2>Payments</h2>
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

    <div id="jquery_templates">
    </div>

</div>
