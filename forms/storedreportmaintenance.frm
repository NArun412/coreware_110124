<div id="_maintenance_form">

%field:description,page_id,user_id,date_created%
%basic_form_line%
%end repeat%

<input type="hidden" id="repeat_rules" name="repeat_rules">

<div id="interval_controls">
%method:intervalFields%

%field:email_results,run_immediately,stored_report_email_addresses%
%basic_form_line%
%end repeat%
</div>

<div id="_stored_report_wrapper" class="hidden">
<div id="_stored_report_result_id" class="hidden"></div>

<div id="_button_row" class="hidden">
    <button id="hide_report">Hide This Report</button>
    <button id="printable_button">Printable Report</button>
    <button id="pdf_button">Download PDF</button>
</div>
<h1 id="_report_title" class="hidden"></h1>
<div id="_report_content" class="hidden">
</div>
</div>

<h2>Previously Run Reports</h2>
<div id='previous_reports'></div>

</div> <!-- maintenance_form -->
