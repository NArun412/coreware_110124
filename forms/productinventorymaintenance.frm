<div id="_maintenance_form">

%field:product_id,upc_code,isbn_13,manufacturer_sku,location_id,bin_number,quantity,reorder_level,replenishment_level,maximum_price,manual_order%
%basic_form_line%
%end repeat%

<h2>Add Inventory Log Entry</h2>
%method:addInventoryLogEntry%

<h2>Inventory Log</h2>
<div id="product_inventory_log">
</div>

</div> <!-- maintenance_form -->
