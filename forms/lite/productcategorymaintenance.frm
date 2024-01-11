<div id="_maintenance_form">

    <div class="accordion-form">
        <ul class="tab-control-element">
            <li><a href="#tab_1">%programText:Details%</a></li>
            <li><a href="#tab_5">%programText:Restrictions%</a></li>
        </ul>

        <h3 class="accordion-control-element">%programText:Details%</h3>
        <div id="tab_1">

            %field:description,pricing_structure_id,image_id,meta_title,meta_description,sort_order,cannot_sell,inactive%
            %basic_form_line%
            %end repeat%

            %field:product_category_group_links%
            %basic_form_line%

        </div>

        <h3 class="accordion-control-element">%programText:Restrictions%</h3>
        <div id="tab_5">
            %field:product_category_restrictions,product_category_cannot_sell_distributors%
            %basic_form_line%
            %end repeat%

        </div>

    </div>

</div>
