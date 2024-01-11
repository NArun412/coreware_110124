<div id="_maintenance_form">

    %field:description%
    %basic_form_line%

    <div class="accordion-form">

        <ul class="tab-control-element">
            <li>
                <a href="#tab_1">%programText:Details%</a>
            </li>
            <li>
                <a href="#tab_2">%programText:Javascript%</a>
            </li>
            <li>
                <a href="#tab_3">%programText:CSS%</a>
            </li>
            <li>
                <a href="#tab_3a">%programText:Components%</a>
            </li>
            <li>
                <a href="#tab_4">%programText:Data%</a>
            </li>
            <li>
                <a href="#tab_5">%programText:Text%</a>
            </li>
            <li>
                <a href="#tab_6">%programText:Content%</a>
            </li>
        </ul>

        <h3 class="accordion-control-element">Details</h3>
        <div id="tab_1">

            %field:template_code,detailed_description,template_group_id,image_id,filename,addendum_filename,directory_name,sort_order,include_crud,internal_use_only,inactive%
            %basic_form_line%
            %end repeat%

        </div>

        <h3 class="accordion-control-element">Javascript</h3>
        <div id="tab_2">
            %field:analytics_code_chunk_id,javascript_code%
            %basic_form_line%
            %end repeat%

        </div>

        <h3 class="accordion-control-element">CSS</h3>
        <div id="tab_3">
            %field:css_file_id,template_sass_headers,sass_headers,css_content%
            %basic_form_line%
            %end repeat%

        </div>

        <h3 class="accordion-control-element">Components</h3>
        <div id="tab_3a">
            %field:template_banner_groups,template_images,template_menus,template_fragments%
            %basic_form_line%
            %end repeat%

        </div>

        <h3 class="accordion-control-element">Data</h3>
        <div id="tab_4">
            %field:template_data%
            %basic_form_line%

            %field:template_custom_fields%
            %basic_form_line%

        </div>

        <h3 class="accordion-control-element">Text</h3>
        <div id="tab_5">
            %field:template_text_chunks%
            %basic_form_line%

        </div>

        <h3 class="accordion-control-element">Content</h3>
        <div id="tab_6">
            %field:content%
            <h2>Template Content</h2>
            <p>Click
                <a href='#' id='placeholder_list'>here</a> to see a list of placeholders. Content is only valid when the template is not assigned a filename. Further requirements are needed for a template to successfully use the Coreware CRUD capabilities. Click
                <a href='#' id='crud_documentation'>here</a> to see these requirements.
            </p>
            <p>
                <button id="check_requirements">Check CRUD Requirements</button>
            </p>
            <p id="_requirements_results"></p>
            %basic_form_line%
        </div>

    </div> <!-- maintenance_list -->
