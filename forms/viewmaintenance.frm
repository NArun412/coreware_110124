<div id="_maintenance_form">

%field:database_definition_id,table_name,description,detailed_description,subsystem_id,custom_definition%
%basic_form_line%
%end repeat%

<h3>Where statement need not include information about join.</h3>
%field:query_text%
%basic_form_line%

%field:full_query_text%
%basic_form_line%

<p><button id="view_data">View Sample Data</button></p>
<p>Data view will be of last created view parameters.</p>

<h3>Order of tables will be retained in the join. Subsequent tables will be joined by its primary key to foreign key in previous table.</h3>
<h4>Drag to change order.</h4>
%field:view_tables%
%basic_form_line%

<input type="hidden" id="table_id_list">
<h3>Select columns to appear in View. If none are included, ALL will be in view.</h3>
<p><input type="text" id="column_filter" placeholder="Filter"></p>
<div id="column_selector">
</div>

</div> <!-- maintenance_form -->
