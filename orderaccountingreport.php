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

$GLOBALS['gPageCode'] = "ORDERACCOUNTINGREPORT";
require_once "shared/startup.inc";

class OrderAccountingReportPage extends Page implements BackgroundReport {

	private static function sortQuantity($a, $b) {
		if ($a['quantity'] == $b['quantity']) {
			return 0;
		}
		return ($a['quantity'] > $b['quantity'] ? -1 : 1);
	}

	function executePageUrlActions() {

		if ($_GET['url_action'] == "create_report") {
			$returnArray = self::getReportContent();
			if (array_key_exists("report_export", $returnArray)) {
				if (is_array($returnArray['export_headers'])) {
					foreach ($returnArray['export_headers'] as $thisHeader) {
						header($thisHeader);
					}
				}
				echo $returnArray['report_export'];
			} else {
				echo jsonEncode($returnArray);
			}
			exit;
		}
	}

	public static function getReportContent() {
		$returnArray = array();

		saveStoredReport(static::class);

		processPresetDates($_POST['preset_dates'], "order_time_from", "order_time_to");

		$whereStatement = "";
		$parameters = array($GLOBALS['gClientId']);
		if (!empty($_POST['order_time_from'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "date(order_time) >= ?";
			$parameters[] = $GLOBALS['gPrimaryDatabase']->makeDateParameter($_POST['order_time_from']);
		}
		if (!empty($_POST['order_time_to'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "date(order_time) <= ?";
			$parameters[] = $GLOBALS['gPrimaryDatabase']->makeDateParameter($_POST['order_time_to']);
		}
		if (!empty($_POST['source_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "orders.source_id = ?";
			$parameters[] = $_POST['source_id'];
		}
		if (!empty($_POST['product_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "product_id = ?";
			$parameters[] = $_POST['product_id'];
		}
		if (!empty($_POST['product_manufacturer_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "product_id in (select product_id from products where product_manufacturer_id = ?)";
			$parameters[] = $_POST['product_manufacturer_id'];
		}
		if (!empty($_POST['not_shipped'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "product_id not in (select product_id from products where virtual_product = 1) and (not exists (select order_item_id from order_shipment_items where order_item_id = order_items.order_item_id) or order_items.quantity > (select sum(quantity) from order_shipment_items where order_item_id = order_items.order_item_id))";
		}
		$productIds = array();
		foreach (explode(",", $_POST['products']) as $productId) {
			$productId = getFieldFromId("product_id", "products", "product_id", $productId);
			if (!empty($productId)) {
				$productIds[] = $productId;
			}
		}
		if (!empty($productIds)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "product_id in (" . implode(",", $productIds) . ")";
		}
		$productCategoryIds = array();
		foreach (explode(",", $_POST['product_categories']) as $productCategoryId) {
			$productCategoryId = getFieldFromId("product_category_id", "product_categories", "product_category_id", $productCategoryId);
			if (!empty($productCategoryId)) {
				$productCategoryIds[] = $productCategoryId;
			}
		}
		if (!empty($productCategoryIds)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "product_id in (select product_id from product_category_links where product_category_id in (" . implode(",", $productCategoryIds) . "))";
		}

		$productCategoryGroupIds = array();
		foreach (explode(",", $_POST['product_category_groups']) as $productCategoryGroupId) {
			$productCategoryGroupId = getFieldFromId("product_category_group_id", "product_category_groups", "product_category_group_id", $productCategoryGroupId);
			if (!empty($productCategoryGroupId)) {
				$productCategoryGroupIds[] = $productCategoryGroupId;
			}
		}
		if (!empty($productCategoryGroupIds)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "product_id in (select product_id from product_category_links where product_category_id in (select product_category_id from product_category_group_links where product_category_group_id in (" . implode(",", $productCategoryGroupIds) . ")))";
		}

		$userTypeIds = array();
		foreach (explode(",", $_POST['user_types']) as $userTypeId) {
			$userTypeId = getFieldFromId("user_type_id", "user_types", "user_type_id", $userTypeId);
			if (!empty($userTypeId)) {
				$userTypeIds[] = $userTypeId;
			}
		}
		if (!empty($userTypeIds)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "contact_id " . (empty($_POST['exclude_user_types']) ? "" : "not ")
				. "in (select contact_id from users where user_type_id in (" . implode(",", $userTypeIds) . "))";
		}

		ob_start();
		switch ($_POST['report_type']) {
			case "csv":
				$returnArray['export_headers'] = array();
				$returnArray['export_headers'][] = "Content-Type: text/csv";
				$returnArray['export_headers'][] = "Content-Disposition: attachment; filename=\"order_accounting.csv\"";
				$returnArray['export_headers'][] = 'Cache-Control: must-revalidate, post-check=0, pre-check=0';
				$returnArray['export_headers'][] = 'Pragma: public';
				$returnArray['filename'] = "order_accounting.csv";

				// Field list comes from QuickBooks, some fields are placeholders as no relevant data is available in Coreware
				$columnHeaders = array(
                    'Order Status',
                    'Customer Name',
					'Transaction Date',
					'Reference Number',
					'PO Number',
					'Ship Date',
					'Bill to Line 1',
					'Bill to Line 2',
					'Bill to Line 3',
					'Bill to Line 4',
					'Bill to City',
					'Bill to State',
					'Bill to Postal Code',
					'Bill to Country',
					'Ship to Line 1',
					'Ship to Line 2',
					'Ship to Line 3',
					'Ship to Line 4',
					'Ship to City',
					'Ship to State',
					'Ship to Postal Code',
					'Ship to Country',
					'Phone',
					'Email',
					'Contact Name',
					'First Name',
					'Last Name',
					'Due Date',
					'Ship Method',
					'Customer Message',
					'Memo',
					'Customer Tax Code',
					'Company Name',
					'Customer Type',
					'Item',
					'Quantity',
					'Description',
					'Price',
					'Service Date',
					'FOB',
					'Sales Tax Item',
					'To Be E-mailed',
					'Other');

				echo createCsvRow($columnHeaders);

				$resultSet = executeReadQuery("select orders.*, order_items.*, order_shipment_items.*, order_shipments.carrier_description, order_shipments.shipping_carrier_id, orders.full_name as order_full_name, orders.tax_charge as order_tax_charge, orders.shipping_charge as order_shipping_charge, order_items.quantity as order_item_quantity, "
                    . "(select description from locations where location_id in (select location_id from order_shipments where order_id = orders.order_id and order_shipment_id in "
					. "(select order_shipment_id from order_shipment_items where order_item_id = order_items.order_item_id)) limit 1) as location_description "
					. "from orders join order_items using (order_id) left join order_shipment_items using (order_item_id) left join order_shipments using (order_shipment_id) "
					. "where client_id = ?" .(empty($whereStatement) ? "" : " and " . $whereStatement) . " order by orders.order_id", $parameters);
				$saveOrderId = "";
				$contactRow = array();
				$billingRow = array();
				$shippingRow = array();
				$orderNotes = "";
				$productRow = array();
				while ($row = getNextRow($resultSet)) {
					if ($row['order_id'] !== $saveOrderId) {
						$saveOrderId = $row['order_id'];
						$contactRow = getRowFromId('contacts', 'contact_id', $row['contact_id']);
						// Get most recent phone number
						if (empty($row['phone_number'])) {
							$phoneResults = executeReadQuery("select phone_number from phone_numbers where contact_id = ? order by phone_number_id desc limit 1", $row['contact_id']);
							while ($phoneRow = getNextRow($phoneResults)) {
								$contactRow['phone_number'] = $phoneRow['phone_number'];
							}
							freeResult($phoneResults);
						} else {
							$contactRow['phone_number'] = $row['phone_number'];
						}
						if (empty($row['address_id'])) { // Shipping address
							$shippingRow = $contactRow;
						} else {
							$shippingRow = getRowFromId("addresses", "address_id", $row['address_id']);
						}
						if (empty($row['account_id'])) { // billing address
							$billingRow = $contactRow;
						} else {
							$accountRow = getRowFromId('accounts', 'account_id', $row['account_id']);
							if (empty($accountRow['address_id'])) {
								$billingRow = $shippingRow;
							} else {
								$billingRow = getRowFromId('addresses', 'address_id', $accountRow['address_id']);
							}
						}
						// Get public order notes
						$orderNotes = '';
						$noteResults = executeReadQuery("select content from order_notes where order_id = ? and public_access = 1", $row['order_id']);
						while ($noteRow = getNextRow($noteResults)) {
							$orderNotes .= (empty($noteRow) ? "" : ", ") . $noteRow['content'];
						}
						freeResult($noteResults);
						if ($row['order_shipping_charge'] > 0) {
							$productRow['product_code'] = 'SHIPPING';
							$productRow['description'] = 'Shipping Charge';
                            $orderItemRow = $row;
                            $orderItemRow['order_item_quantity'] = 1;
                            $orderItemRow['sale_price'] = $row['order_shipping_charge'];
							$fields = self::buildExportRow($orderItemRow, $contactRow, $billingRow, $shippingRow, $orderNotes, $productRow);
							echo createCsvRow($fields);
						}
						if ($row['order_tax_charge'] > 0) {
							$productRow['product_code'] = 'TAX';
							$productRow['description'] = 'Tax Charge';
                            $orderItemRow = $row;
                            $orderItemRow['order_item_quantity'] = 1;
                            $orderItemRow['sale_price'] = $row['order_tax_charge'];
							$fields = self::buildExportRow($orderItemRow, $contactRow, $billingRow, $shippingRow, $orderNotes, $productRow);
							echo createCsvRow($fields);
						}
						if ($row['order_discount'] > 0) {
							$promotionId = getFieldFromId("promotion_id", "order_promotions", "order_id", $row['order_id']);
							$promotionInfo = getMultipleFieldsFromId(array("promotion_code", "description"), "promotions", "promotion_id", $promotionId);
							$productRow['product_code'] = 'DISCOUNT ' . $promotionInfo['promotion_code'];
							$productRow['description'] = 'Discount Amount - ' . $promotionInfo['description'];
                            $orderItemRow = $row;
                            $orderItemRow['order_item_quantity'] = 1;
                            $orderItemRow['sale_price'] = $row['order_discount'] * -1;
							$fields = self::buildExportRow($orderItemRow, $contactRow, $billingRow, $shippingRow, $orderNotes, $productRow);
							echo createCsvRow($fields);
						}
					}
					$productRow = ProductCatalog::getCachedProductRow($row['product_id']);

					$fields = self::buildExportRow($row, $contactRow, $billingRow, $shippingRow, $orderNotes, $productRow);
					echo createCsvRow($fields);
				}
				$returnArray['report_export'] = ob_get_clean();
				return $returnArray;

		}
		$returnArray['report_title'] = "Order Accounting Report";
		$returnArray['report_content'] = ob_get_clean();
		return $returnArray;
	}

	private static function buildExportRow($orderItemRow, $contactRow, $billingRow, $shippingRow, $orderNotes, $productRow) {
		$dateFormat = 'm/d/Y';
		$fields = array();
        $fields[] = substr(getFieldFromId("description", "order_status", "order_status_id", $orderItemRow['order_status_id']), 0, 31);                                         //'Order Status',
		$fields[] = substr($orderItemRow['order_full_name'], 0, 31);                                         //'Customer Name',
		$fields[] = date($dateFormat, strtotime($orderItemRow['order_time']));          //'Transaction Date',
		$fields[] = substr($orderItemRow['order_number'], 0, 11);                                      //'Reference Number',
		$fields[] = $orderItemRow['purchase_order_number'];                             //'PO Number',
		$fields[] = (empty($orderItemRow['order_shipment_id']) ? "" : date($dateFormat, strtotime(
			getFieldFromId('date_shipped', 'order_shipments', 'order_shipment_id', $orderItemRow['order_shipment_id']))));          //'Ship Date',
		$fields[] = substr($billingRow['address_1'], 0, 41);                                           //'Bill to Line 1',
		$fields[] = substr($billingRow['address_2'], 0, 41);                                           //'Bill to Line 2',
		$fields[] = '';                                                                 //'Bill to Line 3',
		$fields[] = '';                                                                 //'Bill to Line 4',
		$fields[] = substr($billingRow['city'], 0, 31);                                                //'Bill to City',
		$fields[] = substr($billingRow['state'], 0, 21);                                               //'Bill to State',
		$fields[] = substr($billingRow['postal_code'], 0, 13);                                         //'Bill to Postal Code',
		$fields[] = substr(getFieldFromId("country_id", "countries", "country_code", $billingRow['countryCode']), 0, 31);                //'Bill to Country',
		$fields[] = substr($shippingRow['address_1'], 0, 41);                                          //'Ship to Line 1',
		$fields[] = substr($shippingRow['address_2'], 0, 41);                                          //'Ship to Line 2',
		$fields[] = '';                                                                 //'Ship to Line 3',
		$fields[] = '';                                                                 //'Ship to Line 4',
		$fields[] = substr($shippingRow['city'], 0, 31);                                               //'Ship to City',
		$fields[] = substr($shippingRow['state'], 0, 21);                                              //'Ship to State',
		$fields[] = substr($shippingRow['postal_code'], 0, 13);                                        //'Ship to Postal Code',
		$fields[] = substr(getFieldFromId("country_id", "countries", "country_code", $shippingRow['countryCode']), 0, 31);               //'Ship to Country',
		$fields[] = substr($contactRow['phone_number'], 0, 21);                                        //'Phone',
		$fields[] = $contactRow['email_address'];                                       //'Email',
		$fields[] = substr(getDisplayName($contactRow['contact_id']), 0, 31);                          //'Contact Name',
		$fields[] = substr($contactRow['first_name'], 0, 25);                                          //'First Name',
		$fields[] = substr($contactRow['last_name'], 0, 25);                                           //'Last Name',
		$fields[] = '';                                                                 //'Due Date',
		$fields[] = substr($orderItemRow['carrier_description'] ?: getFieldFromId('description', 'shipping_carriers', 'shipping_carrier_id', $orderItemRow['shipping_carrier_id']), 0, 15);  //'Ship Method',
		$fields[] = substr($orderNotes, 0, 101);                                                        //'Customer Message',
		$fields[] = substr($productRow['product_code'], 0, 31);                                                             //'Memo',
		$fields[] = empty(CustomField::getCustomFieldData($orderItemRow['contact_id'], "TAX_EXEMPT_ID")) ? "TAX" : "NON";          //'Customer Tax Code',
		$fields[] = $contactRow['business_name'];                                       //'Company Name',
		$fields[] = getFieldFromId('description', "user_types", 'user_type_id',
			getFieldFromId('user_type_id', 'users', 'contact_id', $contactRow['contact_id']));                             //'Customer Type',
		$fields[] = $productRow['product_code'];                                        //'Item',
		$fields[] = $orderItemRow['order_item_quantity'];                               //'Quantity',
		$fields[] = $productRow['description'];                                         //'Description',
		$fields[] = $orderItemRow['sale_price'];                                        //'Price',
		$fields[] = '';                                                                 //'Service Date',
		$fields[] = '';                                                                 //'FOB',
		$fields[] = '';                                                                 //'Sales Tax Item',
		$fields[] = '';                                                                 //'To Be E-mailed',
		$fields[] = '';                                                                 //'Other'
		return $fields;
	}

	function mainContent() {
		?>
        <div id="report_parameters">
            <form id="_report_form" name="_report_form">

				<?php getStoredReports() ?>

                <div class="basic-form-line" id="_report_type_row">
                    <label for="report_type">Report Type</label>
                    <select tabindex="10" id="report_type" name="report_type">
                        <option selected value="csv">Export CSV</option>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

				<?php getPresetDateOptions() ?>

                <div class="basic-form-line preset-date-custom" id="_order_time_row">
                    <label for="order_time_from">Order Date: From</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="order_time_from" name="order_time_from">
                    <label class="second-label">Through</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="order_time_to" name="order_time_to">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

				<?php
				$resultSet = executeReadQuery("select * from sources where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
				if ($resultSet['row_count'] > 0) {
					?>
                    <div class="basic-form-line" id="_source_id_row">
                        <label for="source_id">Source</label>
                        <select tabindex="10" id="source_id" name="source_id">
                            <option value="">[All]</option>
							<?php
							while ($row = getNextRow($resultSet)) {
								?>
                                <option value="<?= $row['source_id'] ?>"><?= htmlText($row['description']) ?></option>
								<?php
							}
							?>
                        </select>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>
				<?php } ?>

				<?php
				$productCount = 0;
				$resultSet = executeReadQuery("select count(*) from products where client_id = ?", $GLOBALS['gClientId']);
				if ($row = getNextRow($resultSet)) {
					$productCount = $row['count(*)'];
				}
				if ($productCount < 100) {
					$productsControl = new DataColumn("products");
					$productsControl->setControlValue("data_type", "custom");
					$productsControl->setControlValue("include_inactive", "true");
					$productsControl->setControlValue("control_class", "MultiSelect");
					$productsControl->setControlValue("control_table", "products");
					$productsControl->setControlValue("links_table", "product_category_links");
					$productsControl->setControlValue("primary_table", "product_categories");
					$customControl = new MultipleSelect($productsControl, $this);
					?>
                    <div class="basic-form-line custom-control-no-help custom-control-form-line" id="_products_row">
                        <label for="products">Products</label>
						<?= $customControl->getControl() ?>
                    </div>
				<?php } else { ?>
					<?= createFormControl("order_items", "product_id", array("not_null" => false, "help_label" => "leave blank to include all")) ?>
				<?php } ?>

				<?php
				$productsControl = new DataColumn("product_category_groups");
				$productsControl->setControlValue("data_type", "custom");
				$productsControl->setControlValue("include_inactive", "true");
				$productsControl->setControlValue("control_class", "MultiSelect");
				$productsControl->setControlValue("control_table", "product_category_groups");
				$productsControl->setControlValue("links_table", "product_category_group_links");
				$productsControl->setControlValue("primary_table", "product_categories");
				$customControl = new MultipleSelect($productsControl, $this);
				?>
                <div class="basic-form-line custom-control-no-help custom-control-form-line" id="_product_category_groups_row">
                    <label for="product_category_groups">Product Category Groups</label>
					<?= $customControl->getControl() ?>
                </div>

				<?php
				$productsControl = new DataColumn("product_categories");
				$productsControl->setControlValue("data_type", "custom");
				$productsControl->setControlValue("include_inactive", "true");
				$productsControl->setControlValue("control_class", "MultiSelect");
				$productsControl->setControlValue("control_table", "product_categories");
				$productsControl->setControlValue("links_table", "product_category_links");
				$productsControl->setControlValue("primary_table", "products");
				$customControl = new MultipleSelect($productsControl, $this);
				?>
                <div class="basic-form-line custom-control-no-help custom-control-form-line" id="_product_categories_row">
                    <label for="product_categories">Product Categories</label>
					<?= $customControl->getControl() ?>
                </div>

                <div class="basic-form-line" id="_product_manufacturer_id_row">
                    <label for="product_manufacturer_id">Manufacturer</label>
                    <select tabindex="10" id="product_manufacturer_id" name="product_manufacturer_id">
                        <option value="">[All]</option>
						<?php
						$resultSet = executeReadQuery("select * from product_manufacturers where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['product_manufacturer_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

				<?php
				$userTypeControl = new DataColumn("user_types");
				$userTypeControl->setControlValue("data_type", "custom");
				$userTypeControl->setControlValue("include_inactive", "true");
				$userTypeControl->setControlValue("control_class", "MultiSelect");
				$userTypeControl->setControlValue("control_table", "user_types");
				$userTypeControl->setControlValue("primary_table", "users");
				$customControl = new MultipleSelect($userTypeControl, $this);
				?>
                <div class="basic-form-line custom-control-no-help custom-control-form-line" id="_user_types_row">
                    <label for="user_types">User Types</label>
					<?= $customControl->getControl() ?>
                    <div class='clear-div'></div>
                </div>

                <div class="basic-form-line" id="_exclude_user_types_row">
                    <input type="checkbox" name="exclude_user_types" id="exclude_user_types" value="1"><label class="checkbox-label" for="exclude_user_types">Exclude (instead of include) these user types</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>


                <div class="basic-form-line" id="_not_shipped_row">
                    <input tabindex="10" type="checkbox" id="not_shipped" name="not_shipped"><label class="checkbox-label" for="not_shipped">Only Not Shipped</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_sort_quantity_row">
                    <input tabindex="10" type="checkbox" id="sort_quantity" name="sort_quantity"><label class="checkbox-label" for="sort_quantity">Sort by Quantity (Summary Report Only)</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

				<?php storedReportDescription() ?>

                <div class="basic-form-line">
                    <button tabindex="10" id="create_report">Create Report</button>
                </div>

            </form>
        </div>
        <div id="_button_row">
            <button id="refresh_button">Refresh</button>
            <button id="new_parameters_button">Search Again</button>
            <button id="printable_button">Printable Report</button>
            <button id="pdf_button">Download PDF</button>
        </div>
        <h1 id="_report_title"></h1>
        <div id="_report_content">
        </div>
        <div id="_pdf_data" class="hidden">
            <form id="_pdf_form">
            </form>
        </div>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("tap click", "#printable_button", function () {
                window.open("/printable.html");
                return false;
            });
            $(document).on("tap click", "#pdf_button", function () {
                $("#_pdf_form").html("");
                let input = $("<input>").attr("type", "hidden").attr("name", "report_title").val($("#_report_title").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "report_content").val($("#_report_content").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "printable_style").val($("#_printable_style").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "filename").val("orders.pdf");
                $('#_pdf_form').append($(input));
                $("#_pdf_form").attr("action", "/reportpdf.php").attr("method", "POST").submit();
                return false;
            });
            $(document).on("tap click", "#create_report,#refresh_button", function () {
                if ($("#_report_form").validationEngine("validate")) {
                    const reportType = $("#report_type").val();
                    if (reportType == "csv") {
                        $("#_report_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?url_action=create_report").attr("method", "POST").submit();
                    } else {
                        loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_report", $("#_report_form").serialize(), function(returnArray) {
                            if ("error_message" in returnArray) {
                                $("#_error_message").html(returnArray['error_message']);
                            } else {
                                $("#report_parameters").hide();
                                $("#_report_title").html(returnArray['report_title']).show();
                                $("#_report_content").html(returnArray['report_content']).show();
                                $("#_button_row").show();
                                $("html, body").animate({ scrollTop: 0 }, "slow");
                            }
                        });
                    }
                }
                return false;
            });
            $(document).on("tap click", "#new_parameters_button", function () {
                $("#report_parameters").show();
                $("#_report_title").hide();
                $("#_report_content").hide();
                $("#_button_row").hide();
                return false;
            });
        </script>
		<?php
	}

	function hiddenElements() {
		?>
        <div id="autocomplete_options">
            <ul>
            </ul>
        </div>
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

            #_report_content {
                display: none;
            }

            #_report_content table td {
                font-size: 13px;
            }

            #_button_row {
                display: none;
                margin-bottom: 20px;
            }
        </style>
		<?php
	}
}

$pageObject = new OrderAccountingReportPage();
$pageObject->displayPage();
