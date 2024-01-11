<?php

/*	This software is the unpublished, confidential, proprietary, intellectual
	property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
	or used in any manner without expressed written consent from Kim David Software, LLC.
	Kim David Software, LLC owns all rights to this work and intends to keep this
	software confidential so as to maintain its value as a trade secret.

	Copyright 2004-Present, Kim David Software, LLC.

	WARNING! This code is part of the Kim David Software's Coreware system.
	Changes made to this source file will be lost when new versions of the
	system are installed.
*/

$GLOBALS['gPageCode'] = "RETAILSTOREPICKLIST";
$GLOBALS['gCacheProhibited'] = true;
$GLOBALS['gProxyPageCode'] = "PICKUPDASHBOARD";
require_once "shared/startup.inc";

class PickListPage extends Page {

    var $iOrderIds = array();
    var $iOrderShipmentIds = array();

    function setup() {
        $orderIds = explode("|", $_GET['order_id']);
        foreach ($orderIds as $orderId) {
            $orderId = getFieldFromId("order_id", "orders", "order_id", $orderId);
            if (!empty($orderId)) {
                $this->iOrderIds[] = $orderId;
            }
        }
        $orderShipmentIds = explode("|", $_GET['order_shipment_id']);
        foreach ($orderShipmentIds as $orderShipmentId) {
            $orderShipmentId = getFieldFromId("order_shipment_id", "order_shipments", "order_shipment_id", $orderShipmentId,
                "order_id in (select order_id from orders where client_id = " . $GLOBALS['gClientId'] . ")");
            if (!empty($orderShipmentId)) {
                $this->iOrderShipmentIds[] = $orderShipmentId;
            }
        }
        if (empty($_GET['ajax']) && empty($this->iOrderIds) && empty($this->iOrderShipmentIds)) {
            header("Location: /");
            exit;
        }
    }

    function mainContent() {
        echo $this->iPageData['content'];
        $pickListReport = "";
        $orderList = array();
        foreach ($this->iOrderIds as $thisOrderId) {
            $orderList[] = array("order_id" => $thisOrderId, "order_shipment_id" => "");
        }
        foreach ($this->iOrderShipmentIds as $thisOrderShipmentId) {
            $orderList[] = array("order_id" => getFieldFromId("order_id","order_shipments","order_shipment_id",$thisOrderShipmentId), "order_shipment_id" => $thisOrderShipmentId);
        }
        foreach ($orderList as $orderInformation) {
            $thisOrderId = $orderInformation['order_id'];
            $thisOrderShipmentId = $orderInformation['order_shipment_id'];
            $orderRow = getRowFromId("orders", "order_id", $thisOrderId);
            $shippingMethodRow = getRowFromId("shipping_methods", "shipping_method_id", $orderRow['shipping_method_id']);
            if (empty($thisOrderShipmentId) && (empty($shippingMethodRow['location_id']) || empty($shippingMethodRow['pickup']))) {
                continue;
            }
            $contactRow = Contact::getContact($orderRow['contact_id']);
            if (empty($orderRow['address_id'])) {
                $addressRow = array();
            } else {
                $addressRow = getRowFromId("addresses", "address_id", $orderRow['address_id']);
            }
            $orderItems = array();
            $resultSet = executeReadQuery("select *,(select group_concat(serial_number) from order_item_serial_numbers where " .
	            "order_item_id = order_items.order_item_id) as serial_numbers,(select sum(quantity) from order_shipment_items where " .
                (empty($thisOrderShipmentId) ? "" : "order_shipment_id = " . $thisOrderShipmentId . " and ") . "order_item_id = order_items.order_item_id and " .
                "order_shipment_id in (select order_shipment_id from order_shipments where location_id is not null and location_id not in (select location_id from locations where " .
                "product_distributor_id is not null))) as shipped_quantity from order_items " .
                "join products using (product_id) left outer join product_data using (product_id) where order_id = ?", $thisOrderId);
            while ($row = getNextRow($resultSet)) {
                if (empty($row['shipped_quantity'])) {
                    $row['shipped_quantity'] = 0;
                }
                # check to see if shipping method location has inventory
                if (empty($thisOrderShipmentId)) {
                    $inventoryQuantity = getFieldFromId("quantity", "product_inventories", "product_id", $row['product_id'], "location_id = ?", $shippingMethodRow['location_id']);
                    if (empty($inventoryQuantity)) {
                        continue;
                    }
                }
                if (empty($thisOrderShipmentId)) {
                    $row['quantity'] -= $row['shipped_quantity'];
                } else {
                    $row['quantity'] = $row['shipped_quantity'];
                }
                if ($row['quantity'] <= 0) {
                    continue;
                }
                $orderItems[] = $row;
            }
            $pickListFragment = getFragment("RETAIL_STORE_PICK_LIST");
            $headerImageId = getFieldFromId("image_id", "images", "image_code", "RECEIPT_HEADER_LOGO");
            if (empty($headerImageId)) {
                $headerImageId = getFieldFromId("image_id", "images", "image_code", "HEADER_LOGO");
            }
            if (empty($pickListFragment)) {
                ob_start();
                ?>
                <div id="_pick_list_wrapper">
                    <p><img alt="Header Image" class="header-image" src="/getimage.php?id=%header_image_id%"></p>

                    <table id="company_section">
                        <tr>
                            <td id="pick_list_info">
                                <h2>Pick List</h2>
                                <img src='/barcodegenerator.php?text=%order_number%&size=60'><br>
                                Order Number: %order_number%<br>
                                Order Date: %order_date%
                            </td>
                            <td id="company_address_section">
                                %store_name%<br>%store_address%
                            </td>
                        </tr>
                    </table>

                    %pick_list_header_text%

                    <table id="address_section">
                        <tr>
                            <td id="contact_section">
                                <h3>Ordered By</h3>
                                %contact_id%<br>
                                %contact_name%<br>
                                %contact_address%
                            </td>
                            <td id="shipping_section">
                                <h3>Ship To</h3>
                                %full_name%<br>
                                %shipping_address%<br>
                                <br>
                                Ship By: %shipping_method%
                            </td>
                            <td>
                                %ffl_dealer%
                            </td>
                        </tr>
                    </table>

                    <p>The following items are included in this pick list. This may not be all items in the order, as the order might include dropship items.</p>

                    <h2>Order Items</h2>

                    %order_items_table%
                    %print_notes%

                    %pick_list_footer_text%
                </div>
                <?php
                $pickListFragment = ob_get_clean();
            }
            $substitutions = $orderRow;
	        $substitutions['print_notes'] = "";
	        $includeInternalNotes = getPreference("INCLUDE_INTERNAL_NOTES_ON_PICKLIST");
	        $resultSet = executeQuery("select * from order_notes where " . (empty($includeInternalNotes) ? "public_access = 1 and " : "") . "order_id = ?", $orderRow['order_id']);
	        while ($row = getNextRow($resultSet)) {
		        $substitutions['print_notes'] .= (empty($substitutions['print_notes']) ? "" : "\n") . $row['content'];
	        }
	        $substitutions['print_notes'] = makeHtml($substitutions['print_notes']);

            $substitutions['header_image_id'] = $headerImageId;
            $substitutions['store_name'] = $GLOBALS['gClientName'];
            $substitutions['store_address'] = $GLOBALS['gClientRow']['address_1'] . "<br>" . $GLOBALS['gClientRow']['city'] . ", " . $GLOBALS['gClientRow']['state'] . " " . $GLOBALS['gClientRow']['postal_code'];
            $resultSet = executeReadQuery("select * from phone_numbers where contact_id = ?", $GLOBALS['gClientRow']['contact_id']);
            while ($row = getNextRow($resultSet)) {
                $substitutions['store_address'] .= "<br>" . $row['phone_number'] . " " . $row['description'];
            }
            if (!empty($GLOBALS['gClientRow']['email_address'])) {
                $substitutions['store_address'] .= "<br>" . $GLOBALS['gClientRow']['email_address'];
            }
            $substitutions['pick_list_header_text'] = makeHtml(getFragment("RETAIL_STORE_PICK_LIST_HEADER"));
            $substitutions['pick_list_footer_text'] = makeHtml(getFragment("RETAIL_STORE_PICK_LIST_FOOTER"));
            $substitutions['order_date'] = date("m/d/Y", strtotime($orderRow['order_time']));
            $substitutions['contact_name'] = getDisplayName($orderRow['contact_id']);
            $substitutions['contact_address'] = getAddressBlock($contactRow);
            if (!empty($orderRow['phone_number'])) {
                $substitutions['contact_address'] .= "<br>" . $orderRow['phone_number'];
            }
            if (empty($addressRow)) {
                $substitutions['shipping_address'] = getAddressBlock($contactRow);
            } else {
                $substitutions['shipping_address'] = getAddressBlock($addressRow);
            }
            if (!empty($orderRow['attention_line'])) {
                $substitutions['shipping_address'] = $orderRow['attention_line'] . "<br>" . $substitutions['shipping_address'];
            }
            $substitutions['shipping_method'] = getFieldFromId("description", "shipping_methods", "shipping_method_id", $orderRow['shipping_method_id']);
            $locationId = getFieldFromId("location_id","order_shipments","order_shipment_id",$thisOrderShipmentId,"location_id in (select location_id from locations where product_distributor_id is null)");
            if (empty($locationId)) {
	            $locationId = getFieldFromId("location_id", "shipping_methods", "shipping_method_id", $orderRow['shipping_method_id']);
            }
            $substitutions['location'] = getFieldFromId("description", "locations", "location_id", $locationId);
	        $substitutions['ffl_dealer'] = "";
	        if (!empty($orderRow['federal_firearms_licensee_id'])) {
		        $substitutions['ffl_dealer'] = "<h3>FFL Dealer Address</h3>";
		        $fflRow = (new FFL($orderRow['federal_firearms_licensee_id']))->getFFLRow();
		        $substitutions['ffl_dealer'] .= $fflRow['license_number'] . "<br>";
		        $substitutions['ffl_dealer'] .= (empty($fflRow['business_name']) ? $fflRow['licensee_name'] : $fflRow['business_name']) . "<br>";
		        $substitutions['ffl_dealer'] .= $fflRow['address_1'] . "<br>" . (empty($fflRow['address_2']) ? "" : $fflRow['address_2'] . "<br>") . $fflRow['city'] . ", " . $fflRow['state'] . " " . substr($fflRow['postal_code'], 0, 5);
		        $substitutions['ffl_dealer'] .= (empty($fflRow['phone_number']) ? "" : "<br>" . $fflRow['phone_number']);
	        }
            ob_start();
            ?>
            <table class="grid-table" id="order_items_table">
                <tr>
                    <th>Product</th>
                    <th>Bin</th>
                    <th>Qty</th>
                </tr>
                <?php
                $totalQuantity = 0;
                foreach ($orderItems as $itemRow) {
                    $serialNumbers = "";
                    if (!empty($itemRow['serial_numbers'])) {
                        $serialNumbersArray = explode(",",$itemRow['serial_numbers']);
                        $count = 0;
                        foreach ($serialNumbersArray as $thisSerialNumber) {
                            $count++;
                            if ($count % 5 == 0) {
                                $serialNumbers .= "<br>&nbsp;&nbsp;";
                            }
                            $serialNumbers .= $thisSerialNumber . "&nbsp;&nbsp;&nbsp;&nbsp;";
                        }
                    }
                    $productAddons = "";
                    $addonSet = executeReadQuery("select * from product_addons join order_item_addons using (product_addon_id) where order_item_id = ?", $itemRow['order_item_id']);
                    while ($addonRow = getNextRow($addonSet)) {
                        $productAddons .= "<br>Add on: " . htmlText($addonRow['description']) . ($addonRow['quantity'] <= 1 ? "" : " (Qty: " . $addonRow['quantity'] . ")");
                    }
                    $totalQuantity += $itemRow['quantity'];
                    $binNumber = getFieldFromId("bin_number", "product_inventories", "product_id", $itemRow['product_id'], "location_id = ?", $locationId);
                    ?>
                    <tr>
                        <td><?= htmlText($itemRow['description']) . $productAddons ?><?= (empty(getPreference("RETAIL_STORE_INCLUDE_PRODUCT_CODE")) ? "" : "<br>Product Code: " .
                                htmlText($itemRow['product_code'])) ?><?= (empty($itemRow['upc_code']) ? "" : "<br>UPC: " . htmlText($itemRow['upc_code'])) ?><?= (empty($serialNumbers) ? "" : "<br>Serial Number: " .
		                        "<span id='serial_number_list'>" . $serialNumbers . "</span>") ?></td>
                        <td><?= $binNumber ?></td>
                        <td class="white-space align-right"><?= $itemRow['quantity'] ?></td>
                    </tr>
                    <?php
                }
                ?>
                <tr>
                    <td class="total-line align-right">Total items in this pick list</td>
                    <td class="total-line"></td>
                    <td class="total-line align-right"><?= $totalQuantity ?></td>
                </tr>
            </table>
            <?php
            $substitutions['order_items_table'] = ob_get_clean();
            $pickListFragment = PlaceHolders::massageContent($pickListFragment, $substitutions);
            $pickListReport .= $pickListFragment;
        }
        ?>
        <div id="_button_row">
            <button id="printable_button">Printable Pick List</button>
            <button id="pdf_button">Download PDF</button>
        </div>
        <h1 id="_report_title"></h1>
        <div id="_report_content">
            <?php
            echo $pickListReport;
            ?>
        </div>
        <div id="_pdf_data" class="hidden">
            <form id="_pdf_form">
            </form>
        </div>
        <?php
        echo $this->iPageData['after_form_content'];
        return true;
    }

    function onLoadJavascript() {
        ?>
        <script>
            $(document).on("tap click", "#printable_button", function () {
                window.open("/printable.html");
                return false;
            });
            <?php if (!empty($_GET['printable'])) { ?>
            setTimeout(function () {
                $("#printable_button").trigger("click");
            }, 1000);
            <?php } ?>
            $(document).on("tap click", "#pdf_button", function () {
                $("#_pdf_form").html("");
                let input = $("<input>").attr("type", "hidden").attr("name", "report_title").val($("#_report_title").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "report_content").val($("#_report_content").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "printable_style").val($("#_printable_style").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "filename").val("orderpicklist.pdf");
                $('#_pdf_form').append($(input));
                $("#_pdf_form").attr("action", "/reportpdf.php").attr("method", "POST").submit();
                return false;
            });
        </script>
        <?php
    }

    function internalCSS() {
        ?>
        <style>
            #report_parameters {
                width: 100%;
                margin-left: auto;
                margin-right: auto;
            }

            #_button_row {
                margin-bottom: 20px;
            }
        </style>
        <style id="_printable_style">
            #serial_number_list {
                font-size: 110%;
            }

            #_pick_list_wrapper {
                page-break-after: always;
            }

            p {
                margin-bottom: 20px;
            }

            .header-image {
                max-width: 300px;
                max-height: 75px;
            }

            #_report_title {
                margin: 0;
            }

            #company_section {
                width: 100%;
                margin-bottom: 20px;
            }

            #company_section td {
                width: 50%;
                vertical-align: top;
            }

            #address_section {
                width: 100%;
                margin-bottom: 20px;
            }

            #address_section td {
                width: 33%;
                vertical-align: top;
                padding-right: 10px;
            }

            .grid-table td.total-line {
                font-weight: bold;
                font-size: .9rem;
                border-top: 2px solid rgb(0, 0, 0);
                padding-top: 5px;
                padding-bottom: 5px;
            }

            .grid-table td.border-bottom {
                border-bottom: 2px solid rgb(0, 0, 0);
            }

            #order_items_table {
                margin-bottom: 20px;
            }

            .white-space {
                white-space: nowrap;
            }

            .ffl-image {
                max-width: 80%;
                margin-top: 20px;
                max-height: 300px;
            }
        </style>
        <?php
    }
}

$pageObject = new PickListPage();
$pageObject->displayPage();
