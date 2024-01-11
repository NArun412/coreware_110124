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

$GLOBALS['gPageCode'] = "ORDERITEMSREPORT";
$GLOBALS['gCacheProhibited'] = true;
$GLOBALS['gProxyPageCode'] = "ORDERDASHBOARD";
require_once "shared/startup.inc";

class OrderItemsReportPage extends Page {

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
		if (empty($_GET['ajax']) && empty($this->iOrderIds)) {
			header("Location: /");
			exit;
		}
	}

	function mainContent() {
		echo $this->iPageData['content'];
		$orderList = array();
		foreach ($this->iOrderIds as $thisOrderId) {
			$orderList[] = array("order_id" => $thisOrderId);
		}
		foreach ($orderList as $orderInformation) {
			$thisOrderId = $orderInformation['order_id'];
			$orderRow = getRowFromId("orders", "order_id", $thisOrderId);
			$contactRow = Contact::getContact($orderRow['contact_id']);
			if (empty($orderRow['address_id'])) {
				$addressRow = array();
			} else {
				$addressRow = getRowFromId("addresses", "address_id", $orderRow['address_id']);
			}
			$orderItems = array();
			$resultSet = executeReadQuery("select *,(select group_concat(serial_number) from order_item_serial_numbers where " .
				"order_item_id = order_items.order_item_id) as serial_numbers, order_items.description as order_item_description from order_items " .
				"join products using (product_id) left outer join product_data using (product_id) where order_id = ?", $thisOrderId);
			while ($row = getNextRow($resultSet)) {
				$orderItems[] = $row;
			}
			$headerImageId = getFieldFromId("image_id", "images", "image_code", "RECEIPT_HEADER_LOGO");
			if (empty($headerImageId)) {
				$headerImageId = getFieldFromId("image_id", "images", "image_code", "HEADER_LOGO");
			}
			ob_start();
			?>
            <div id="_order_items_wrapper">
                <p><img alt="Header Image" class="header-image" src="/getimage.php?id=%header_image_id%"></p>

                <table id="company_section">
                    <tr>
                        <td id="order_items_info">
                            <h2>Order Items</h2>
                            Order Number: %order_number%<br>
                            Order Date: %order_date%
                        </td>
                        <td id="company_address_section">
                            %store_name%<br>%store_address%
                        </td>
                    </tr>
                </table>

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

                <h2>Order Items</h2>

                %order_items_table%
                %print_notes%
            </div>
			<?php
			$orderReportFragment = ob_get_clean();
			$substitutions = $orderRow;
			$substitutions['print_notes'] = "";
			$resultSet = executeQuery("select * from order_notes where order_id = ?", $orderRow['order_id']);
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
                    <th>Qty</th>
                </tr>
				<?php
				$totalQuantity = 0;
				foreach ($orderItems as $itemRow) {
					$serialNumbers = "";
					if (!empty($itemRow['serial_numbers'])) {
						$serialNumbersArray = explode(",", $itemRow['serial_numbers']);
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
					?>
                    <tr>
                        <td><?= htmlText($itemRow['description']) . $productAddons ?><?= (empty(getPreference("RETAIL_STORE_INCLUDE_PRODUCT_CODE")) ? "" : "<br>Product Code: " .
								htmlText($itemRow['product_code'])) ?><?= (empty($itemRow['upc_code']) ? "" : "<br>UPC: " . htmlText($itemRow['upc_code'])) .
	                        (empty($itemRow['manufacturer_sku']) ? "" : "<br>SKU: " . htmlText($itemRow['manufacturer_sku'])) .
	                        (empty($itemRow['order_item_description']) || $itemRow['order_item_description'] == $itemRow['description'] ? "" : "<br>Description: " . htmlText($itemRow['order_item_description'])) ?><?= (empty($serialNumbers) ? "" : "<br>Serial Number: " .
								"<span id='serial_number_list'>" . $serialNumbers . "</span>") ?></td>
                        <td class="white-space align-right"><?= $itemRow['quantity'] ?></td>
                    </tr>
					<?php
				}
				?>
                <tr>
                    <td class="total-line align-right">Total items</td>
                    <td class="total-line align-right"><?= $totalQuantity ?></td>
                </tr>
            </table>
			<?php
			$substitutions['order_items_table'] = ob_get_clean();
			$orderReportFragment = PlaceHolders::massageContent($orderReportFragment, $substitutions);
			$orderItemsReport .= $orderReportFragment;
		}
		?>
        <div id="_button_row">
            <button id="printable_button">Printable Report</button>
            <button id="pdf_button">Download PDF</button>
        </div>
        <h1 id="_report_title"></h1>
        <div id="_report_content">
			<?php
			echo $orderItemsReport;
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
                input = $("<input>").attr("type", "hidden").attr("name", "filename").val("orderitems.pdf");
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

            #_order_items_wrapper {
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

$pageObject = new OrderItemsReportPage();
$pageObject->displayPage();
