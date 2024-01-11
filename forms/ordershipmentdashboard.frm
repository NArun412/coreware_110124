<div id="_maintenance_form">

%field:order_id_display,full_name,order_time,location_id,date_shipped,shipment_sent,shipping_charge,shipping_carrier_id,carrier_description,tracking_identifier,label_url%
%basic_form_line%
%end repeat%

<div id="order_shipment_items">
</div>

<h2>Full Order</h2>
<div id="order_items">
</div>

<div id="ffl_section">
</div>

%field:order_status_id,date_completed%
%basic_form_line%
%end repeat%

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

</div>
