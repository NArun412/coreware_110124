<div id="_maintenance_form">
    <div class="accordion-form">
        <ul class="tab-control-element">
            <li><a href="#tab_1">%programText:Contact%</a></li>
            <li><a href="#tab_2">%programText:Qualifications%</a></li>
        </ul>

        <h3 class="accordion-control-element">%programText:Contact%</h3>
        <div id="tab_1">

            %field:first_name,last_name,address_1,address_2,city,city_select,state,postal_code,phone_numbers,email_address,image_id%
            %basic_form_line%
            %end repeat%

        </div>

        <h3 class="accordion-control-element">%programText:Qualifications%</h3>
        <div id="tab_2">

            %field:class_instructor_qualifications%
            %basic_form_line%

        </div>

    </div> <!-- accordion-form -->

</div> <!-- maintenance_form -->
