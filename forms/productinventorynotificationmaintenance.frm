<div id="_maintenance_form">

%field:product_id,email_address,product_distributor_id%
%basic_form_line%
%end repeat%

%field:comparator%
<div class="basic-form-line" id="_%column_name%_row">
	<label for="%column_name%" class="%label_class%">%form_label%</label>
	%input_control%
%field:quantity%
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

%field:place_order,location_id,order_quantity,use_lowest_price,allow_multiple%
%basic_form_line%
%end repeat%

</div> <!-- maintenance_form -->
