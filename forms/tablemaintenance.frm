<div id="_maintenance_form">
    %repeat%
    %basic_form_line%
    %next field%
    %end repeat%
    <div id="create_page"></div>
    <div class="section">
        <h3>Columns</h3>
        <table class="grid-table" id="columns" data-row_number="0">
            <tr>
                <th>&nbsp;</th>
                <th>&nbsp;</th>
                <th>Column</th>
                <th>Label</th>
                <th>Description</th>
                <th>Type</th>
                <th>Size</th>
                <th>Scale</th>
                <th>Index</th>
                <th>Full<br>Text
                </th>
                <th>Reqd</th>
                <th>Default</th>
                <th>&nbsp;</th>
            </tr>
        </table>
    </div>

    <p>
        <button id="view_data">View Sample of Data</button>
    </p>

    <div class="section">
        <h3>Unique Keys
            <button id="create_unique_key">Create Unique Key</button>
        </h3>
        <table class="grid-table" id="unique_keys" data-row_number="0">
        </table>
    </div>

    <div class="section">
        <h3>Foreign Keys
            <button id="create_foreign_keys">Create Foreign Keys</button>
        </h3>
        <table class="grid-table" id="foreign_keys" data-row_number="0">
            <tr>
                <th>Column</th>
                <th>Referenced Table</th>
                <th>Referenced Column</th>
                <th>&nbsp;</th>
            </tr>
        </table>
    </div>

</div> <!-- maintenance_form -->
