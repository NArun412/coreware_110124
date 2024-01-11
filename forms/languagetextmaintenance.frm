%field:description%
%basic_form_line%
<div id="translation_list">
</div>
<div id="translation_details">
<div class="basic-form-line" id="_english_translation_row">
	<input type="hidden" id="primary_identifier" name="primary_identifier">
	<label for="english_translation">%programText:English_Translation%</label>
	<textarea readonly="readonly" class="field-text" id="english_translation" name="english_translation"></textarea>
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>
<div id='default_text_translation'></div>
<div class="basic-form-line" id="_language_translation_row">
	<label for="language_translation">%programText:Translation%</label>
	<textarea class="field-text" id="translation" name="translation"></textarea>
	<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
</div>
<div id="_button_row">
	<button id="previous_translation" data-action="previous">%programText:Previous%</button>
	<button id="next_translation" data-action="next">%programText:Next%</button>
	<button id="list_translation" data-action="list">%programText:List%</button>
	<button id="save_translation" data-action="list">%programText:Save%</button>
	<button id="cancel_translation">%programText:Cancel%</button>
</div>
</div>
