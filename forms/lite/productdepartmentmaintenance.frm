<div id="_maintenance_form">

    <div class="accordion-form">
        <ul class="tab-control-element">
            <li><a href="#tab_1">%programText:Details%</a></li>
            <li><a href="#tab_4">%programText:Restrictions%</a></li>
        </ul>

        <h3 class="accordion-control-element">%programText:Details%</h3>
        <div id="tab_1">

            %field:description,pricing_structure_id,link_name,meta_title,meta_description,image_id,sort_order%
            %basic_form_line%
            %end repeat%

        </div>

        <h3 class="accordion-control-element">%programText:Restrictions%</h3>
        <div id="tab_4">
            %field:product_department_restrictions,product_department_cannot_sell_distributors%
            %basic_form_line%
            %end repeat%

        </div>

    </div>

</div>

