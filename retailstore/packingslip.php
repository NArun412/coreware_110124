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

$GLOBALS['gPageCode'] = "RETAILSTOREPACKINGSLIP";
$GLOBALS['gCacheProhibited'] = true;
$GLOBALS['gProxyPageCode'] = "ORDERDASHBOARD";
require_once "shared/startup.inc";

class PackingSlipPage extends Page {

	var $iOrderShipmentId = "";

	function setup() {
		$this->iOrderShipmentId = getFieldFromId("order_shipment_id", "order_shipments", "order_shipment_id", $_GET['order_shipment_id'], ($GLOBALS['gInternalConnection'] ? "" : "order_id in (select order_id from orders where contact_id = " . $GLOBALS['gUserRow']['contact_id'] . ")"));
		if (empty($_GET['ajax']) && empty($this->iOrderShipmentId)) {
			header("Location: /");
			exit;
		}
	}

	function mainContent() {
		echo $this->iPageData['content'];
		$orderShipmentRow = getRowFromId("order_shipments", "order_shipment_id", $this->iOrderShipmentId);
		$orderRow = getRowFromId("orders", "order_id", $orderShipmentRow['order_id']);
		$contactRow = Contact::getContact($orderRow['contact_id']);
		if (empty($orderRow['address_id'])) {
			$addressRow = array();
		} else {
			$addressRow = getRowFromId("addresses", "address_id", $orderRow['address_id']);
		}
		$orderItems = array();
		$resultSet = executeReadQuery("select *,(select group_concat(serial_number separator ', ') from order_item_serial_numbers where " .
			"order_item_id = order_items.order_item_id) as serial_numbers,(select quantity from order_shipment_items where order_item_id = order_items.order_item_id and order_shipment_id = ?) as shipped_quantity from order_items " .
			"join products using (product_id) left outer join product_data using (product_id) where order_id = ? and order_items.deleted = 0", $this->iOrderShipmentId, $orderRow['order_id']);
		while ($row = getNextRow($resultSet)) {
			if (empty($row['shipped_quantity'])) {
				$row['shipped_quantity'] = 0;
			}
			$orderItems[] = $row;
		}
		$packingSlipFragment = getFragment("RETAIL_STORE_PACKING_SLIP");
		$headerImageId = getFieldFromId("image_id", "images", "image_code", "RECEIPT_HEADER_LOGO");
		if (empty($headerImageId)) {
			$headerImageId = getFieldFromId("image_id", "images", "image_code", "HEADER_LOGO");
		}
		if (empty($packingSlipFragment)) {
			ob_start();
			?>
            <div id="_packing_slip_wrapper">
                <p><img alt='Header Image' class="header-image" src="/getimage.php?id=%header_image_id%"></p>

                <table id="company_section">
                    <tr>
                        <td id="packing_slip_info">
                            <h2>Packing Slip</h2>
                            Order Number: <span id='order_id'>%order_number%</span><br>
                            Order Date: %order_date%
                        </td>
                        <td id="company_address_section">
                            %store_name%<br>%store_address%
                        </td>
                    </tr>
                </table>

                %packing_slip_header_text%

                <table id="address_section">
                    <tr>
                        <td id="contact_section">
                            <h3>Ordered By</h3>
                            %contact_id%<br>
                            %contact_name%<br>
                            %contact_address%
                        </td>
                        <td id="shipping_section">
                            <h3>%customer_title%</h3>
                            %full_name%<br>
                            %shipping_address%<br>
                            <br>
                            Ship By: %shipping_carrier%
                        </td>
                        <td>
                            %ffl_dealer%
                        </td>
                    </tr>
                </table>

                <p>The following items are included in this package. This may not be all items in the order, as the order might be shipped in multiple packages.</p>

                <h2>Order Items</h2>

                %order_items_table%

                %gift_notice%
                %gift_message%

                %packing_slip_footer_text%

                %ffl_image%
            </div>
			<?php
			$packingSlipFragment = ob_get_clean();
		}
		$substitutions = $orderRow;
        $substitutions['print_notes'] = "";
        $resultSet = executeQuery("select * from order_notes where public_access = 1 and order_id = ?", $orderRow['order_id']);
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
		$substitutions['packing_slip_header_text'] = makeHtml(getFragment("RETAIL_STORE_PACKING_SLIP_HEADER"));
		$substitutions['packing_slip_footer_text'] = makeHtml(getFragment("RETAIL_STORE_PACKING_SLIP_FOOTER"));
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
		if ($orderRow['gift_order']) {
			$substitutions['gift_notice'] = "<p>This order is a gift</p>";
			$substitutions['gift_message'] = makeHtml($orderRow['gift_text']);
		} else {
			$substitutions['gift_notice'] = "";
			$substitutions['gift_message'] = "";
		}
		$substitutions['ffl_dealer'] = "";
		$substitutions['customer_title'] = "Ship To";
		if (!empty($orderRow['federal_firearms_licensee_id'])) {
			$substitutions['customer_title'] = "Customer Information";
			$substitutions['ffl_dealer'] = "<h3>Ship To FFL Dealer</h3>";
			$fflRow = (new FFL($orderRow['federal_firearms_licensee_id']))->getFFLRow();
            $substitutions['ffl_dealer'] .= $fflRow['license_number'] . "<br>";
            $substitutions['ffl_dealer'] .= (empty($fflRow['business_name']) ? $fflRow['licensee_name'] : $fflRow['business_name']) . "<br>";
            $substitutions['ffl_dealer'] .= $fflRow['address_1'] . "<br>" . (empty($fflRow['address_2']) ? "" : $fflRow['address_2'] . "<br>") . $fflRow['city'] . ", " . $fflRow['state'] . " " . substr($fflRow['postal_code'], 0, 5);
            $substitutions['ffl_dealer'] .= (empty($fflRow['phone_number']) ? "" : "<br>" . $fflRow['phone_number']);
        }
        $substitutions['ffl_image'] = "";
        if (!empty($orderRow['federal_firearms_licensee_id']) || empty(getPreference("HIDE_PACKING_SLIP_FFL_IMAGE_FOR_CUSTOMER_ORDERS"))) {
            $fflImageId = getFieldFromId("image_id", "images", "image_code", "DEALER_FFL");
            if (!empty($fflImageId)) {
                $substitutions['ffl_image'] = "<p><img alt='FFL Image' class='ffl-image' src='" . getImageFilename($fflImageId,array("use_cdn"=>true)) . "'></p>";
            }
        }
		$substitutions['shipping_carrier'] = (empty($orderShipmentRow['shipping_carrier_id']) ? $orderShipmentRow['carrier_description'] : getFieldFromId("description", "shipping_carriers", "shipping_carrier_id", $orderShipmentRow['shipping_carrier_id']));
        $substitutions['shipping_method'] = getFieldFromId('description', "shipping_methods", "shipping_method_id", $orderRow['shipping_method_id']);
        ob_start();
		?>
        <table class="grid-table" id="order_items_table">
            <tr>
                <th>Product</th>
                <th>Qty</th>
            </tr>
			<?php
			$totalQuantity = 0;
            $wordWrapChars = getPageTextChunk("WORD_WRAP_CHARS");
            if(!is_numeric($wordWrapChars)) {
                $wordWrapChars = 85;
            }
            foreach ($orderItems as $itemRow) {
				$productAddons = "";
				$addonSet = executeReadQuery("select * from product_addons join order_item_addons using (product_addon_id) where order_item_id = ?", $itemRow['order_item_id']);
				while ($addonRow = getNextRow($addonSet)) {
					$productAddons .= "<br>Add on: " . htmlText($addonRow['description']) . ($addonRow['quantity'] <= 1 ? "" : " (Qty: " . $addonRow['quantity'] . ")");
				}
				$totalQuantity += $itemRow['shipped_quantity'];
				?>
                <tr>
                    <td><?= htmlText($itemRow['description']) . $productAddons ?><?= (empty(getPreference("RETAIL_STORE_INCLUDE_PRODUCT_CODE")) ? "" : "<br>Product Code: " . htmlText($itemRow['product_code'])) ?><?= (empty($itemRow['upc_code']) ? "" : "<br>UPC: " . htmlText($itemRow['upc_code'])) ?><?= (empty($itemRow['serial_numbers']) ? "" : "<br>Serial Number(s): " . wordwrap(htmlText($itemRow['serial_numbers']), $wordWrapChars, "<br>")) ?></td>
                    <td class="white-space align-right"><?= $itemRow['shipped_quantity'] ?> of <?= $itemRow['quantity'] ?></td>
                </tr>
				<?php
			}
			?>
            <tr>
                <td class="total-line align-right">Total items in this shipment</td>
                <td class="total-line align-right"><?= $totalQuantity ?></td>
            </tr>
        </table>
		<?php
		$substitutions['order_items_table'] = ob_get_clean();
		?>
        <div id="_button_row">
            <button id="printable_button">Printable Packing Slip</button>
            <button id="pdf_button">Download PDF</button>
        </div>
        <h1 id="_report_title"></h1>
        <div id="_report_content">
			<?php
            $packingSlipFragment = PlaceHolders::massageContent($packingSlipFragment, $substitutions);
			echo $packingSlipFragment;
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
        $(document).on("tap click","#printable_button",function() {
        window.open("/printable.html");
        return false;
        });
        $(document).on("tap click","#pdf_button",function() {
        $("#_pdf_form").html("");
        let input = $("<input>").attr("type", "hidden").attr("name", "report_title").val($("#_report_title").html());
        $('#_pdf_form').append($(input));
        input = $("<input>").attr("type", "hidden").attr("name", "report_content").val($("#_report_content").html());
        $('#_pdf_form').append($(input));
        input = $("<input>").attr("type", "hidden").attr("name", "printable_style").val($("#_printable_style").html());
        $('#_pdf_form').append($(input));
        input = $("<input>").attr("type", "hidden").attr("name", "filename").val("orderpackingslip" + $("#order_id").html() + ".pdf");
        $('#_pdf_form').append($(input));
        $("#_pdf_form").attr("action","/reportpdf.php").attr("method","POST").submit();
        return false;
        });
        </script>
		<?php
	}

	function internalCSS() {
		$orderShipmentRow = getRowFromId("order_shipments", "order_shipment_id", $this->iOrderShipmentId);
		$orderRow = getRowFromId("orders", "order_id", $orderShipmentRow['order_id']);
		?>
        <style>
            <?php if ($orderRow['gift_order']) { ?>
            #contact_section {
                display: none;
            }

            <?php } ?>
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

$pageObject = new PackingSlipPage();
$pageObject->displayPage();
