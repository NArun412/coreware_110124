<div id="_maintenance_form">

%field:form_definition_code,description%
%basic_form_line%
%end repeat%

<p><button tabindex="10" id="view_form">View Form HTML</button></p>

<input type="hidden" id="form_content" name="form_content">

<div id="_item_selectors">
	<button tabindex="10" class="chunk-button" data-chunk_type="field">Field</button>
	<button tabindex="10" class="chunk-button" data-chunk_type="paragraph">Paragraph</button>
	<button tabindex="10" class="chunk-button" data-chunk_type="header">Heading</button>
	<button tabindex="10" class="chunk-button" data-chunk_type="image">Image</button>
	<button tabindex="10" class="chunk-button" data-chunk_type="html">HTML</button>
	<button tabindex="10" id="payment_button" class="chunk-button" data-chunk_type="payment">Payment</button>
</div>
<input type="hidden" id="form_chunk_number" name="form_chunk_number" value="0">
<div id="form_builder_content">
</div>

</div>
