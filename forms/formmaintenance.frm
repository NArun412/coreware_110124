<div id="_maintenance_form">
%field:description,form_definition_id,forms.date_created,forms.ip_address,forms.user_id,date_completed,form_group_links,first_name,last_name%
%basic_form_line%
%end repeat%

<div class="basic-form-line">
	<label></label>
	<div id="contact_link"></div>
</div>

<p id="payment_details"></p>

%field:form_notes,form_attachments%
%basic_form_line%
%end repeat%

<div class="basic-form-line">
	<label>Forms</label>
	<span class='data-content' id="forms_links"></span>
</div>

<div id="form_status">
</div>

</div> <!-- maintenance_form -->
