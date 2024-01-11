<div id="_maintenance_form">
<input type="hidden" id="checked" name="checked" readonly="readonly">
%repeat%
%basic_form_line%
%next field%
%end repeat%
<p>
<button id="check_integrity">Check Integrity</button>
<button id="sql_scripts">SQL Scripts</button>
<button id="alter_script">Alter Script</button>
<button id="update">Run Alter Script</button>
</p>
<table id="results_table">
<tr>
	<td><textarea class="field-text results" readonly="readonly" name="results_1" id="results_1" wrap="off"></textarea></td>
	<td><textarea class="field-text results" readonly="readonly" name="results_2" id="results_2" wrap="off"></textarea></td>
</tr>
</table>
<textarea id='last_script' class='hidden'></textarea>
</div> <!-- maintenance_form -->
