<div id="_maintenance_form">

%field:invoice_id,invoice_number,contact_id,invoice_type_id,invoice_link,invoice_date,date_due,date_completed,purchase_order_number,designation_id,notes,internal_use_only,inactive%
%basic_form_line%
%end repeat%

<div class="basic-form-line">
	<label></label>
%field:print_invoice%
	%input_control%
%field:email_invoice%
	%input_control%
%field:import_button%
	%input_control%
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>

%field:invoice_details,invoice_total,payment_total,balance_due,invoice_payments%
%basic_form_line%
%end repeat%

</div> <!-- maintenance_form -->
