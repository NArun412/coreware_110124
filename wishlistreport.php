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

$GLOBALS['gPageCode'] = "WISHLISTREPORT";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 150000;
ini_set("memory_limit", "8192M");

class WishListReportPage extends Page implements BackgroundReport {

	private static function sortResults($a, $b) {
		if ($a[$_POST['order_by']] == $b[$_POST['order_by']]) {
			return 0;
		}
		return ($_POST['order_by'] == "quantity" ? -1 : 1) * ($a[$_POST['order_by']] > $b[$_POST['order_by']] ? 1 : -1);
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "create_report":
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

		$exportReport = in_array($_POST['report_type'], array("csv", "csv_user"));
		ob_start();

		if ($_POST['report_type'] != "summary") {
			$_POST['include_shopping_cart'] = $_POST['only_shopping_cart'] = false;
		}
		$whereStatement = "";
		$productCategoryIds = array();
		foreach (explode(",", $_POST['product_categories']) as $productCategoryId) {
			$productCategoryId = getFieldFromId("product_category_id", "product_categories", "product_category_id", $productCategoryId);
			if (!empty($productCategoryId)) {
				$productCategoryIds[] = $productCategoryId;
			}
		}
		if (!empty($productCategoryIds)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "products.product_id in (select product_id from product_category_links where product_category_id in (" . implode(",", $productCategoryIds) . "))";
		}

		$productDepartmentIds = array();
		foreach (explode(",", $_POST['product_departments']) as $productDepartmentId) {
			$productDepartmentId = getFieldFromId("product_department_id", "product_departments", "product_department_id", $productDepartmentId);
			if (!empty($productDepartmentId)) {
				$productDepartmentIds[] = $productDepartmentId;
			}
		}
		if (!empty($productDepartmentIds)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "(products.product_id in"
				. " (select product_id from product_category_links where product_category_id in (select product_category_id from product_category_departments where"
				. " product_department_id in (" . implode(",", $productDepartmentIds)
				. "))) or products.product_id in (select product_id from product_category_links where product_category_id in (select product_category_id from product_category_group_links where"
				. " product_category_group_id in (select product_category_group_id from product_category_group_departments where product_department_id in ("
				. implode(",", $productDepartmentIds) . ")))))";
		}

		if (!empty($_POST['time_submitted'])) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "time_submitted >= '" . makeDateParameter($_POST['time_submitted']) . "'";
		}

		if (!empty($_POST['custom_fields'])) {
			$customFieldArray = explode(",", $_POST['custom_fields']);
		} else {
			$customFieldArray = array();
		}
		$customFieldHeaders = array();
		$customFieldCodes = array();
		foreach ($customFieldArray as $customFieldId) {
			$customField = CustomField::getCustomField($customFieldId);
			$customFieldHeaders[] = $customField->getFormLabel();
			$customFieldCodes[] = getFieldFromId("custom_field_code", "custom_fields", "custom_field_id", $customFieldId);
		}

		$reportArray = array();
		$userReportArray = array();
		$dataArray = array();
		$manufacturerArray = array();
		if (!empty($_POST['map_policy_id'])) {
			$resultSet = executeQuery("select product_manufacturer_id from product_manufacturers where client_id = ? and map_policy_id = ?",$_POST['map_policy_id']);
			while ($row = getNextRow($resultSet)) {
				$manufacturerArray[] = $row['product_manufacturer_id'];
			}
		}
		if (empty($_POST['only_shopping_cart'])) {
			$resultSet = executeReadQuery("select wish_list_items.*, products.*, product_data.*, contacts.* from wish_list_items join products using (product_id)
                left outer join product_data using (product_id) join wish_lists using (wish_list_id) join users using (user_id) join contacts using (contact_id)
                where wish_list_id in (select wish_list_id from wish_lists where wish_lists.user_id in (select user_id from users where client_id = ?))"
				. (empty($whereStatement) ? "" : " and " . $whereStatement), $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				if (!empty($_POST['include_map']) && empty($row['manufacturer_advertised_price'])) {
					continue;
				}
				if (!empty($_POST['map_policy_id']) && (empty($row['product_manufacturer_id']) || !in_array($row['product_manufacturer_id'],$manufacturerArray))) {
					continue;
				}
				$dataArray[] = $row;
			}
		}
		if (!empty($_POST['only_shopping_cart']) || !empty($_POST['include_shopping_cart'])) {
			$resultSet = executeReadQuery("select shopping_cart_items.*, products.*, product_data.* from shopping_cart_items join products using (product_id)
                left outer join product_data using (product_id) join shopping_carts using (shopping_cart_id) where shopping_carts.client_id = ?" . (empty($whereStatement) ? "" : " and " . $whereStatement), $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				if (!empty($_POST['include_map']) && empty($row['manufacturer_advertised_price'])) {
					continue;
				}
				if (!empty($_POST['map_policy_id']) && (empty($row['product_manufacturer_id']) || !in_array($row['product_manufacturer_id'],$manufacturerArray))) {
					continue;
				}
				$dataArray[] = $row;
			}
		}
		foreach ($dataArray as $row) {
			if (!array_key_exists($row['product_id'], $reportArray)) {
				$row['quantity'] = 0;
				$reportArray[$row['product_id']] = $row;
			}
			$reportArray[$row['product_id']]['quantity']++;
			$user = array('full_name' => getDisplayName($row['contact_id']), 'first_name' => $row['first_name'],
				'last_name' => $row['last_name'], 'email_address' => $row['email_address'], 'business_name'=>$row['business_name']);
			foreach($customFieldCodes as $customFieldCode) {
				$user['custom_field_' . $customFieldCode] = CustomField::getCustomFieldData($row['contact_id'], $customFieldCode, "CONTACTS", true);
			}
			$reportArray[$row['product_id']]['users'][] = $user;
			$userReportArray[$row['contact_id']]['user'] = $user;
			$userReportArray[$row['contact_id']]['products'][] = $row;
		}
		$sortedArray = array_values($reportArray);
		usort($sortedArray, array(static::class, "sortResults"));

		if ($_POST['quantity'] > 0) {
			foreach ($sortedArray as $index => $thisRow) {
				if ($thisRow['quantity'] <= $_POST['quantity']) {
					unset($sortedArray[$index]);
				}
			}
		}

		$returnArray['report_title'] = "Wish List Report";
		if ($exportReport) {
			$filename = ($_POST['report_type'] == "csv_user" ? "wishlistproductsbyuser.csv" : "wishlistproducts.csv");
			$returnArray['export_headers'] = array();
			$returnArray['export_headers'][] = "Content-Type: text/csv";
			$returnArray['export_headers'][] = "Content-Disposition: attachment; filename=\"" . $filename . "\"";
			$returnArray['export_headers'][] = 'Cache-Control: must-revalidate, post-check=0, pre-check=0';
			$returnArray['export_headers'][] = 'Pragma: public';
			$returnArray['filename'] = $filename;

			$headers = array("Product Code", "Description", "UPC");
			if ($_POST['report_type'] == "csv_user") {
				$headers[] = "First Name";
				$headers[] = "Last Name";
				$headers[] = "Email";
				if(!empty($_POST['include_business_name'])) {
					$headers[] = "Business Name";
				}
				if(!empty($_POST['include_responsible_user'])) {
					$headers[] = "Responsible User";
				}
				foreach($customFieldHeaders as $thisCustomFieldHeader) {
					$headers[] = $thisCustomFieldHeader;
				}
				$headers[] = "Added";
			} else {
				$headers[] = "Quantity";
			}

			echo createCsvRow($headers);

			if ($_POST['report_type'] == "csv_user") {
				foreach ($userReportArray as $userRow) {
					foreach ($userRow['products'] as $row) {
						$timeSubmitted = empty($row['time_submitted']) ? "" : date("m/d/Y g:i a", strtotime($row['time_submitted']));
						$dataArray = array($row['product_code'], $row['description'], $row['upc_code'],
							$userRow['user']['first_name'], $userRow['user']['last_name'], $userRow['user']['email_address']);
						if(!empty($_POST['include_business_name'])) {
							$dataArray[] = $userRow['user']['business_name'];
						}
						if(!empty($_POST['include_responsible_user'])) {
							$dataArray[] = (empty($row['responsible_user_id']) ? "" : getUserDisplayName($row['responsible_user_id']));
						}
						foreach($customFieldCodes as $customFieldCode) {
							$dataArray[] = $userRow['user']['custom_field_' . $customFieldCode] ;
						}
						$dataArray[] = $timeSubmitted;
						echo createCsvRow($dataArray);
					}
				}
			} else {
				foreach ($sortedArray as $row) {
					echo createCsvRow(array($row['product_code'], $row['description'], $row['upc_code'], $row['quantity']));
				}
			}

			$returnArray['report_export'] = ob_get_clean();
			return $returnArray;
		}
		?>
        <table class='grid-table'>
            <tr>
                <th>Product Code</th>
                <th>Description</th>
                <th>UPC</th>
				<?php if ($_POST['report_type'] == "detail_user") { ?>
                    <th>Added</th>
				<?php } else { ?>
                    <th>Quantity</th>
                    <th>Price</th>
                    <th>Extended</th>
				<?php } ?>
            </tr>
			<?php
			if ($_POST['report_type'] == "detail_user") {
				foreach ($userReportArray as $userRow) {
					?>
                    <tr>
                        <td colspan="4"><?= htmlText($userRow['user']['full_name'] . (!empty($_POST['include_business_name']) ? ", " . $userRow['user']['business_name'] : "") .  ', ' . $userRow['user']['email_address']) ?></td>
                    </tr>
					<?php
					foreach ($userRow['products'] as $row) {
						$timeSubmitted = empty($row['time_submitted']) ? "" : date("m/d/Y g:i a", strtotime($row['time_submitted']));
						?>
                        <tr>
                            <td><?= self::wrapProductCode($row['product_code']) ?></td>
                            <td><?= htmlText($row['description']) ?></td>
                            <td><?= $row['upc_code'] ?></td>
                            <td><?= $timeSubmitted ?></td>
                        </tr>
						<?php
					}
				}
			} else {
				$productCatalog = new ProductCatalog();
				foreach ($sortedArray as $row) {
					$salePriceInfo = $productCatalog->getProductSalePrice($row['product_id'], array("product_information" => $row));
					?>
                    <tr>
                        <td><?= self::wrapProductCode($row['product_code']) ?></td>
                        <td><?= htmlText($row['description']) ?></td>
                        <td><?= $row['upc_code'] ?></td>
                        <td class="align-right"><?= $row['quantity'] ?></td>
                        <td class="align-right"><?= number_format($salePriceInfo['sale_price'],2) ?></td>
                        <td class="align-right"><?= (is_numeric($salePriceInfo['sale_price']) ? number_format($salePriceInfo['sale_price'] * $row['quantity'],2) : "n/a") ?></td>
                    </tr>
					<?php
					if ($_POST['report_type'] == "detail") {
						foreach ($row['users'] as $user) {
							$timeSubmitted = empty($row['time_submitted']) ? "" : date("m/d/Y g:i a", strtotime($row['time_submitted']));
							?>
                            <tr>
                                <td></td>
                                <td colspan="2"><?= htmlText($user['full_name'] . ', ' . $user['email_address']) ?></td>
                                <td><?= $timeSubmitted ?></td>

                            </tr>
							<?php
						}
					}
				}
			}
			?>
        </table>
		<?php
		$reportContent = ob_get_clean();
		$returnArray['report_content'] = $reportContent;
		return $returnArray;
	}

	private static function wrapProductCode($productCode) {
		$lineLength = 50;
		if (strlen($productCode) > $lineLength) {
			$underscorePos = strpos($productCode, "_", $lineLength);
			if ($underscorePos) {
				$parts = str_split($productCode, $underscorePos);
				$productCode = $parts[0] . " " . $parts[1];
			}
		}
		return $productCode;
	}

	function mainContent() {

# The report form is where the user can set parameters for how the report would be run.

		?>
        <div id="report_parameters">
            <form id="_report_form" name="_report_form">

				<?php getStoredReports() ?>

                <div class="form-line" id="_report_type_row">
                    <label for="report_type">Report Type</label>
                    <select tabindex="10" id="report_type" name="report_type">
                        <option value="summary">Summary</option>
                        <option value="detail">Details by product</option>
                        <option value="detail_user">Details by user</option>
                        <option value="csv">CSV Export by product</option>
                        <option value="csv_user">CSV Export by user</option>
                    </select>
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_order_by_row">
                    <label for="order_by">Order By</label>
                    <select tabindex="10" id="order_by" name="order_by">
                        <option value="quantity">Quantity</option>
                        <option value="upc_code">UPC</option>
                    </select>
                    <div class='clear-div'></div>
                </div>

                <div class="basic-form-line" id="_map_policy_id_row">
                    <label for="map_policy_id">Product with this MAP policy</label>
                    <select tabindex="10" id="map_policy_id" name="map_policy_id">
                        <option value="">[All]</option>
						<?php
						$resultSet = executeReadQuery("select * from map_policies order by sort_order,description");
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['map_policy_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

	            <div class="basic-form-line" id="_time_submitted_row">
		            <label for="time_submitted_from">Added Since</label>
		            <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="time_submitted" name="time_submitted">
		            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
	            </div>

	            <div class="form-line" id="_include_map_row">
		            <input type="checkbox" tabindex="10" id="include_map" name="include_map" value="1"><label class="checkbox-label" for="include_map">Include only products with a MAP price</label>
		            <div class='clear-div'></div>
	            </div>

	            <div class="form-line" id="_include_shopping_cart_row">
                    <input type="checkbox" tabindex="10" id="include_shopping_cart" name="include_shopping_cart" value="1"><label
                            class="checkbox-label" for="include_shopping_cart">Include items left in Shopping Cart (summary only)</label>
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_only_shopping_cart_row">
                    <input type="checkbox" tabindex="10" id="only_shopping_cart" name="only_shopping_cart" value="1"><label class="checkbox-label" for="only_shopping_cart">ONLY items left in Shopping Cart (summary only, ignore Wish List items)</label>
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_include_business_name_row">
                    <input type="checkbox" tabindex="10" id="include_business_name" name="include_business_name" value="1"><label
                            class="checkbox-label" for="include_business_name">Include Business Name for Users</label>
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_include_responsible_user_row">
                    <input type="checkbox" tabindex="10" id="include_responsible_user" name="include_responsible_user" value="1"><label
                            class="checkbox-label" for="include_responsible_user">Include Responsible User</label>
                    <div class='clear-div'></div>
                </div>

				<?php
				$customFieldControl = new DataColumn("custom_fields");
				$customFieldControl->setControlValue("data_type", "custom");
				$customFieldControl->setControlValue("control_class", "MultiSelect");
				$customFieldControl->setControlValue("control_table", "custom_fields");
				$customFieldControl->setControlValue("links_table", "contacts");
				$customFieldControl->setControlValue("primary_table", "contacts");
				$customFieldControl->setControlValue("choice_where", "custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = 'CONTACTS')");
				$customControl = new MultipleSelect($customFieldControl, $this);
				?>
                <div class="form-line" id="_custom_fields_row">
                    <label for="custom_fields">Contact Custom Fields for CSV Export</label>
					<?= $customControl->getControl() ?>
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_quantity_row">
                    <label for="quantity">Greater Than</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[integer]]" id="quantity" name="quantity" value="0">
                    <div class='clear-div'></div>
                </div>

				<?php
				$departmentControl = new DataColumn("product_departments");
				$departmentControl->setControlValue("data_type", "custom");
				$departmentControl->setControlValue("include_inactive", "true");
				$departmentControl->setControlValue("control_class", "MultiSelect");
				$departmentControl->setControlValue("control_table", "product_departments");
				$departmentControl->setControlValue("links_table", "product_departments");
				$departmentControl->setControlValue("primary_table", "products");
				$customControl = new MultipleSelect($departmentControl, $this);
				?>
                <div class="form-line" id="_product_department_row">
                    <label for="product_department">Include only these departments (leave blank for all)</label>
					<?= $customControl->getControl() ?>
                    <div class='clear-div'></div>
                </div>

				<?php
				$categoryControl = new DataColumn("product_categories");
				$categoryControl->setControlValue("data_type", "custom");
				$categoryControl->setControlValue("include_inactive", "true");
				$categoryControl->setControlValue("control_class", "MultiSelect");
				$categoryControl->setControlValue("control_table", "product_categories");
				$categoryControl->setControlValue("links_table", "product_category_links");
				$categoryControl->setControlValue("primary_table", "products");
				$customControl = new MultipleSelect($categoryControl, $this);
				?>
                <div class="form-line" id="_product_category_row">
                    <label for="product_category">Include only these categories (leave blank for all)</label>
					<?= $customControl->getControl() ?>
                    <div class='clear-div'></div>
                </div>

				<?php storedReportDescription() ?>

                <div class="form-line">
                    <label></label>
                    <button tabindex="10" id="create_report">Create Report</button>
                    <div class='clear-div'></div>
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
		return true;
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
                input = $("<input>").attr("type", "hidden").attr("name", "filename").val("wishlistproducts.pdf");
                $('#_pdf_form').append($(input));
                $("#_pdf_form").attr("action", "/reportpdf.php").attr("method", "POST").submit();
                return false;
            });
            $(document).on("tap click", "#create_report,#refresh_button", function () {
                if ($("#_report_form").validationEngine("validate")) {
                    const reportType = $("#report_type").val();
                    if (reportType === "csv" || reportType === "csv_user") {
                        $("#_report_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?url_action=create_report").attr("method", "POST").submit();
                    } else {
                        loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_report", $("#_report_form").serialize(), function(returnArray) {
                            if ("report_content" in returnArray) {
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
                font-size: .9rem;
            }

            #_button_row {
                display: none;
                margin-bottom: 20px;
            }
        </style>
        <style id="_printable_style">
            #_report_content {
                width: auto;
                display: block;
            }

            #_report_title {
                width: auto;
                display: block;
            }
        </style>
		<?php
	}
}

$pageObject = new WishListReportPage();
$pageObject->displayPage();
