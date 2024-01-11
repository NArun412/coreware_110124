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

$GLOBALS['gPageCode'] = "NORMALIZEPRODUCTFACETVALUES";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 600000;
ini_set("memory_limit", "4096M");

class NormalizeProductFacetValuesPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "save_options":
				$productFacetId = getFieldFromId("product_facet_id", "product_facets", "product_facet_id", $_POST['product_facet_id']);
				if (empty($productFacetId)) {
					$returnArray['error_message'] = "Invalid Product Facet";
					ajaxResponse($returnArray);
					exit;
				}
				$productFacetRow = getRowFromId("product_facets", "product_facet_id", $productFacetId);
				$deleteCount = 0;
				$mergeCount = 0;
				foreach ($_POST as $fieldName => $fieldValue) {
					if (empty($fieldValue) || !startsWith($fieldName, "product_facet_option_id_")) {
						continue;
					}
					$productFacetOptionId = getFieldFromId("product_facet_option_id", "product_facet_options", "product_facet_id", $productFacetId,
						"product_facet_option_id = ?", str_replace("product_facet_option_id_", "", $fieldName));
					if (empty($productFacetOptionId)) {
						continue;
					}
					if ($fieldValue == "delete") {
						executeQuery("delete from product_facet_values where product_facet_option_id = ?", $productFacetOptionId);
						executeQuery("delete from product_facet_options where product_facet_option_id = ?", $productFacetOptionId);
						$deleteCount++;
					} elseif ($fieldValue == "merge") {
						$newProductFacetOptionId = getFieldFromId("product_facet_option_id", "product_facet_options", "product_facet_id", $productFacetId,
							"product_facet_option_id = ?", $_POST['new_product_facet_option_id_' . $productFacetOptionId]);
						if (!empty($newProductFacetOptionId)) {
							$productFacetOptionRow = getRowFromId("product_facet_options", "product_facet_option_id", $productFacetOptionId);
							executeQuery("update product_facet_values set product_facet_option_id = ? where product_facet_option_id = ?", $newProductFacetOptionId, $productFacetOptionId);
							executeQuery("update product_distributor_conversions set primary_identifier = ? where primary_identifier = ? and table_name = 'product_facet_options' and client_id = ? and original_value_qualifier = ?", $newProductFacetOptionId, $productFacetOptionId, $GLOBALS['gClientId'], $productFacetRow['product_facet_code']);
							executeQuery("delete from product_facet_options where product_facet_option_id = ?", $productFacetOptionId);
							executeQuery("delete from product_distributor_conversions where client_id = ? and table_name = 'product_facet_options' and original_value = ? and original_value_qualifier = ?",
								$GLOBALS['gClientId'], $productFacetOptionRow['facet_value'], $productFacetRow['product_facet_code']);
							executeQuery("insert into product_distributor_conversions (client_id, table_name, original_value, original_value_qualifier, primary_identifier) values (?,'product_facet_options',?,?,?)",
								$GLOBALS['gClientId'], $productFacetOptionRow['facet_value'], $productFacetRow['product_facet_code'], $productFacetOptionId);
							$mergeCount++;
						}
					}
				}
				ob_start();
				?>
				<h2>For Facet '<?= htmlText($productFacetRow['description']) ?>'</h2>
				<p><?= $deleteCount ?> Facet Options deleted</p>
				<p><?= $mergeCount ?> Facet Options merged</p>
				<p>
					<button id='reset'>Restart</button>
				</p>
				<?php
				$returnArray['response'] = ob_get_clean();
				ajaxResponse($returnArray);
				exit;
			case "import_facet_values":
				$productFacetId = getFieldFromId("product_facet_id", "product_facets", "product_facet_code", $_GET['product_facet_code']);
				if (empty($productFacetId)) {
					$returnArray['error_message'] = "Invalid Product Facet";
					ajaxResponse($returnArray);
					exit;
				}
				$productFacetRow = getRowFromId("product_facets", "product_facet_id", $productFacetId);

				executeQuery("delete from product_distributor_conversions where client_id = ? and table_name = 'product_facet_options' and primary_identifier not in (select product_facet_option_id from product_facet_options)", $GLOBALS['gClientId']);
				executeQuery("DELETE t1 FROM product_distributor_conversions t1 INNER JOIN product_distributor_conversions t2 WHERE t1.product_distributor_conversion_id < t2.product_distributor_conversion_id AND " .
					"t1.client_id = t2.client_id and t1.product_distributor_id is null and t2.product_distributor_id is null and t1.table_name = t2.table_name and t1.original_value = t2.original_value and " .
					"t1.original_value_qualifier = t2.original_value_qualifier");
				executeQuery("delete from product_distributor_conversions where client_id = ? and table_name = 'product_facet_options' and original_value_qualifier = ? and " .
					"original_value = (select facet_value from product_facet_options where product_facet_option_id = product_distributor_conversions.primary_identifier)", $GLOBALS['gClientId'], $productFacetRow['product_facet_code']);

				$hostUrl = "https://shootingsports.coreware.com/api.php?action=get_normalized_facet_values";
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, "connection_key=760C0DCAB2BD193B585EB9734F34B3B6&product_facet_code=" . $_GET['product_facet_code']);
				curl_setopt($ch, CURLOPT_URL, $hostUrl);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $GLOBALS['gCurlTimeout']);
				curl_setopt($ch, CURLOPT_TIMEOUT, $GLOBALS['gCurlTimeout']);
				$response = curl_exec($ch);
				$responseArray = json_decode($response, true);
				if (array_key_exists("error_message", $responseArray)) {
					$returnArray['error_message'] = $responseArray['error_message'];
					ajaxResponse($returnArray);
					exit;
				}

				$insertCount = 0;
				$productFacetOptions = array();
				$productFacetOptionIdArray = array();
				foreach ($responseArray['product_facet_options'] as $primaryIdentifier => $facetValue) {
					$productFacetOptionId = getFieldFromId("product_facet_option_id", "product_facet_options", "product_facet_id", $productFacetId, "lower(facet_value) = ?", strtolower($facetValue));
					if (empty($productFacetOptionId)) {
						$insertSet = executeQuery("insert into product_facet_options (product_facet_id,facet_value) values (?,?)", $productFacetId, $facetValue);
						$productFacetOptionId = $insertSet['insert_id'];
						$insertCount++;
					}
					$productFacetOptions[$primaryIdentifier] = array("facet_value" => $facetValue, "product_facet_option_id" => $productFacetOptionId, "duplicate_values" => array());
					$productFacetOptionIdArray[$productFacetOptionId] = true;
				}
				foreach ($responseArray['product_facet_value_conversions'] as $facetValue => $primaryIdentifier) {
					if (array_key_exists($primaryIdentifier, $productFacetOptions)) {
						$productFacetOptions[$primaryIdentifier]['duplicate_values'][] = $facetValue;
					}
				}

				foreach ($productFacetOptions as $facetInformation) {
					foreach ($facetInformation['duplicate_values'] as $duplicateValue) {
						$productDistributorConversionId = getFieldFromId("product_distributor_conversion_id", "product_distributor_conversions", "table_name", "product_facet_options",
							"original_value_qualifier = ? and original_value = ? and primary_identifier <> ?", $productFacetRow['product_facet_code'], $duplicateValue, $facetInformation['product_facet_option_id']);
						if (strtolower($duplicateValue) == strtolower($facetInformation['facet_value'])) {
							if (!empty($productDistributorConversionId)) {
								executeQuery("delete from product_distributor_conversions where product_distributor_conversion_id = ?", $productDistributorConversionId);
							}
						} elseif (empty($productDistributorConversionId)) {
							executeQuery("insert into product_distributor_conversions (client_id, table_name, original_value, original_value_qualifier, primary_identifier) values (?,'product_facet_options',?,?,?)",
								$GLOBALS['gClientId'], $duplicateValue, $productFacetRow['product_facet_code'], $facetInformation['product_facet_option_id']);
							$duplicateProductFacetOptionId = getFieldFromId("product_facet_option_id", "product_facet_options", "product_facet_id", $productFacetId, "facet_value = ? and product_facet_option_id <> ?", $duplicateValue, $facetInformation['product_facet_option_id']);
							if (!empty($duplicateProductFacetOptionId)) {
								executeQuery("update product_facet_values set product_facet_option_id = ? where product_facet_option_id = ?", $facetInformation['product_facet_option_id'], $duplicateProductFacetOptionId);
								executeQuery("update product_distributor_conversions set primary_identifier = ? where primary_identifier = ? and table_name = 'product_facet_options' and client_id = ? and original_value_qualifier = ?", $facetInformation['product_facet_option_id'], $duplicateProductFacetOptionId, $GLOBALS['gClientId'], $productFacetRow['product_facet_code']);
								executeQuery("delete from product_facet_options where product_facet_option_id = ?", $duplicateProductFacetOptionId);
							}
						}
					}
				}
				$facetSetCount = 0;
				$facetUpdatedCount = 0;
				$upcCodeData = array();
				$resultSet = executeQuery("select product_id,upc_code,(select product_facet_option_id from product_facet_options where product_facet_id = ? and product_id = product_data.product_id) as product_facet_option_id " .
					"from product_data where client_id = ?", $productFacetId, $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$upcCodeData[$row['upc_code']] = $row;
				}
				$checkedCount = 0;
				foreach ($responseArray['product_facet_values'] as $upcCode => $primaryIdentifier) {
					$checkedCount++;
					if (!array_key_exists($primaryIdentifier, $productFacetOptions) || empty($upcCode)) {
						continue;
					}
					if (!array_key_exists($upcCode, $upcCodeData)) {
						continue;
					}
					$productId = $upcCodeData[$upcCode]['product_id'];
					if (empty($upcCodeData['product_facet_option_id'])) {
						executeQuery("insert into product_facet_values (product_id, product_facet_id, product_facet_option_id) values (?,?,?)", $productId, $productFacetId, $productFacetOptions[$primaryIdentifier]['product_facet_option_id']);
						$facetSetCount++;
					} elseif ($upcCodeData['product_facet_option_id'] != $productFacetOptions[$primaryIdentifier]['product_facet_option_id']) {
						executeQuery("update product_facet_values set product_facet_option_id = ? where product_id = ? and product_facet_id = ?", $productFacetOptions[$primaryIdentifier]['product_facet_option_id'], $upcCodeData['product_id'], $productFacetId);
						$facetUpdatedCount++;
					}
				}
				$productFacetOptionRows = array();
				$resultSet = executeQuery("select * from product_facet_options where product_facet_id = ?", $productFacetId);
				while ($row = getNextRow($resultSet)) {
					if (array_key_exists($row['product_facet_option_id'], $productFacetOptionIdArray)) {
						continue;
					}
					$productFacetOptionRows[] = $row;
				}
				ob_start();
				?>
				<p><?= $insertCount ?> Facet Values Created</p>
				<p><?= $checkedCount ?> Facet Values Checked</p>
				<p><?= $facetSetCount ?> Facet Values Set</p>
				<p><?= $facetUpdatedCount ?> Facet Values Updated</p>
				<?php
				if (!empty($productFacetOptionRows)) {
					?>
					<p>
						<button id="save_changes">Save</button>
						<button id="done">Done</button>
					</p>
					<h2>Facets Not in the CSSC Normalized List for <?= htmlText($productFacetRow['description']) ?></h2>
					<form id='_edit_form'>
						<input type='hidden' name='product_facet_id' value='<?= $productFacetId ?>'>
						<table class='grid-table' id='not_normalized_table'>
							<tr>
								<th>Facet Value</th>
								<th>Options</th>
								<th>Merge Into</th>
							</tr>
							<?php
							foreach ($productFacetOptionRows as $row) {
								?>
								<tr>
									<td><?= htmlText($row['facet_value']) ?></td>
									<td>
										<select tabindex='10' class='option-choices' id='product_facet_option_id_<?= $row['product_facet_option_id'] ?>' name='product_facet_option_id_<?= $row['product_facet_option_id'] ?>'>
											<option value=''>Leave as is</option>
											<option value='delete'>Remove this facet value</option>
											<option value='merge'>Merge into another facet value</option>
										</select>
									</td>
									<td>
										<?= createFormControl("product_facet_values", "product_facet_option_id", array("not_null" => false, "classes" => "merge-selection hidden", "column_name" => "new_product_facet_option_id_" . $row['product_facet_option_id'], "data_type" => "autocomplete", "data-autocomplete_tag" => "product_facet_options", "control_only" => true, "data-additional_filter" => $productFacetId)) ?>
									</td>
								</tr>
								<?php
							}
							?>
						</table>
					</form>
					<?php
				} else {
					?>
					<p>
						<button id="reset">Restart</button>
					</p>
					<?php
				}
				$returnArray['response'] = ob_get_clean();
				ajaxResponse($returnArray);
				exit;
			case "list_facet_values":
				$productFacetId = getFieldFromId("product_facet_id", "product_facets", "product_facet_code", $_GET['product_facet_code']);
				if (empty($productFacetId)) {
					$returnArray['error_message'] = "Invalid Product Facet";
					ajaxResponse($returnArray);
					exit;
				}
				$hostUrl = "https://shootingsports.coreware.com/api.php?action=get_normalized_facet_values";
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, "connection_key=760C0DCAB2BD193B585EB9734F34B3B6&product_facet_code=" . $_GET['product_facet_code']);
				curl_setopt($ch, CURLOPT_URL, $hostUrl);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $GLOBALS['gCurlTimeout']);
				curl_setopt($ch, CURLOPT_TIMEOUT, $GLOBALS['gCurlTimeout']);
				$response = curl_exec($ch);
				$responseArray = json_decode($response, true);
				if (array_key_exists("error_message", $responseArray)) {
					$returnArray['error_message'] = $responseArray['error_message'];
					ajaxResponse($returnArray);
					exit;
				}
				$productFacetOptions = array();
				foreach ($responseArray['product_facet_options'] as $productFacetOptionId => $facetValue) {
					$productFacetOptions[$productFacetOptionId] = array("facet_value" => $facetValue, "duplicate_values" => array());
				}
				foreach ($responseArray['product_facet_value_conversions'] as $facetValue => $productFacetOptionId) {
					if (array_key_exists($productFacetOptionId, $productFacetOptions)) {
						$productFacetOptions[$productFacetOptionId]['duplicate_values'][] = $facetValue;
					}
				}
				$existingFacetValues = array();
				$resultSet = executeQuery("select * from product_facet_options where product_facet_id = ?", $productFacetId);
				while ($row = getNextRow($resultSet)) {
					$existingFacetValues[strtolower($row['facet_value'])] = false;
				}

				$displayFacetOptions = array();
				foreach ($productFacetOptions as $facetInformation) {
					$displayFacet = false;
					if (!array_key_exists(strtolower($facetInformation['facet_value']), $existingFacetValues)) {
						$displayFacet = true;
					}
					$existingFacetValues[strtolower($facetInformation['facet_value'])] = true;
					foreach ($facetInformation['duplicate_values'] as $duplicateValue) {
						if (strtolower($duplicateValue) != strtolower($facetInformation['facet_value']) && array_key_exists(strtolower($duplicateValue), $existingFacetValues)) {
							$existingFacetValues[strtolower($duplicateValue)] = true;
							$displayFacet = true;
							break;
						}
					}
					if (!$displayFacet) {
						continue;
					}
					$displayFacetOptions[] = $facetInformation;
				}
				$unknownFacetValues = array();
				foreach ($existingFacetValues as $facetValue => $processed) {
					if (!$processed) {
						$unknownFacetValues[] = $facetValue;
					}
				}

				ob_start();
				if (empty($displayFacetOptions) && empty($unknownFacetValues)) {
					?>
					<p>Nothing found to sync. Your facets are all up to date.</p>
					<p>
						<button id='reset'>Restart</button>
					</p>
					<?php
				} else {
					?>
					<p>
						<button id='import_facet_values'>Sync</button>
						<button id='reset'>Return</button>
					</p>
					<?php if (!empty($displayFacetOptions)) { ?>
						<p>These facet values will be imported and/or merged.</p>
						<table class='grid-table' id='_product_facet_values'>
							<tr>
								<th>Facet Value</th>
								<th>Duplicate Values</th>
							</tr>
							<?php
							foreach ($displayFacetOptions as $facetInformation) {
								?>
								<tr>
									<td><?= htmlText($facetInformation['facet_value']) ?></td>
									<td class='duplicate-values'>
										<?php
										foreach ($facetInformation['duplicate_values'] as $facetValue) {
											?>
											<span class='duplicate-facet-value'><?= htmlText($facetValue) ?></span>
											<?php
										}
										?>
									</td>
								</tr>
								<?php
							}
							?>
						</table>
					<?php } ?>
					<?php if (!empty($unknownFacetValues)) { ?>
						<p>These facet values need manual processing.</p>
						<table class='grid-table'>
							<tr>
								<th>Value</th>
							</tr>
							<?php foreach ($unknownFacetValues as $facetValue) { ?>
								<tr>
									<td><?= htmlText($facetValue) ?></td>
								</tr>
							<?php } ?>
						</table>
					<?php } ?>
					<?php
				}
				$returnArray['product_facets'] = ob_get_clean();
				ajaxResponse($returnArray);
				exit;
		}
	}

	function mainContent() {
		$hostUrl = "https://shootingsports.coreware.com/api.php?action=get_syncable_facets";
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, "connection_key=760C0DCAB2BD193B585EB9734F34B3B6");
		curl_setopt($ch, CURLOPT_URL, $hostUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $GLOBALS['gCurlTimeout']);
		curl_setopt($ch, CURLOPT_TIMEOUT, $GLOBALS['gCurlTimeout']);
		$response = curl_exec($ch);
		$responseArray = json_decode($response, true);
		$productFacets = $responseArray['product_facets'];
		?>
		<div id="_criteria_wrapper">
			<p>Syncing facets from the Coreware Shooting Sports Catalog (CSSC) cannot be undone. Make sure it is something you want to do. We recommend looking through the values coming from the CSSC. The process will work as follows:</p>
			<ul>
				<li>Facet values from the normalized list that don't exist will be created.</li>
				<li>Duplicate values identified in the CSSC will be merged into the correct values.</li>
				<li>A list of remaining values in your catalog that are not in the normalized list will be displayed to allow you to merge them into a normalized value or delete them.</li>
				<li>Products will have their facet set based on the data in CSSC.</li>
				<li>Loading the facets can take some time, so don't get impatient.</li>
			</ul>
			<div class='basic-form-line'>
				<label>Product Facets</label>
				<select id='product_facet_code' name='product_facet_code'>
					<option value=''>[Select]</option>
					<?php
					foreach ($productFacets as $productFacetRow) {
						$description = getFieldFromId("description", "product_facets", "product_facet_code", $productFacetRow['product_facet_code']);
						if (empty($description)) {
							continue;
						}
						?>
						<option value='<?= $productFacetRow['product_facet_code'] ?>'><?= htmlText($description) ?></option>
						<?php
					}
					?>
				</select>
			</div>
			<p>
				<button id='list_facet_values'>List</button>
			</p>
		</div>
		<div id="results_wrapper" class='hidden'>
			<p class='error-message'></p>
			<div id='product_facets'>
			</div>
		</div>
		<?php
		return true;
	}

	function onLoadJavascript() {
		?>
		<script>
            $(document).on("change", ".option-choices", function () {
                if ($(this).val() == "merge") {
                    $(this).closest("tr").find(".merge-selection").removeClass("hidden");
                    $(this).closest("tr").find(".autocomplete-field").focus();
                } else {
                    $(this).closest("tr").find(".merge-selection").addClass("hidden");
                }
            });
            $(document).on("click", "#list_facet_values", function () {
                if (!empty($("#product_facet_code").val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=list_facet_values&product_facet_code=" + $("#product_facet_code").val(), function (returnArray) {
                        if ("product_facets" in returnArray) {
                            $("#product_facets").html(returnArray['product_facets']);
                            $("#results_wrapper").removeClass("hidden");
                            $("#_criteria_wrapper").addClass("hidden");
                        }
                    });
                }
                return false;
            });
            $(document).on("click", "#save_changes", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_options", $("#_edit_form").serialize(), function (returnArray) {
                    if ("response" in returnArray) {
                        $("#product_facets").html(returnArray['response']);
                    }
                });
            });
            $(document).on("click", "#reset,#done", function () {
                $("#product_facets").html("");
                $("#results_wrapper").addClass("hidden");
                $("#_criteria_wrapper").removeClass("hidden");
                return false;
            });
            $(document).on("click", "#import_facet_values", function () {
                $('#_confirm_import_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 400,
                    title: 'Confirm Import',
                    buttons: {
                        Yes: function (event) {
                            loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=import_facet_values&product_facet_code=" + $("#product_facet_code").val(), function (returnArray) {
                                if ("response" in returnArray) {
                                    $("#product_facets").html(returnArray['response']);
                                }
                            });
                            $("#_confirm_import_dialog").dialog('close');
                        },
                        Cancel: function (event) {
                            $("#_confirm_import_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
		</script>
		<?php
	}

	function internalCSS() {
		?>
		<style>
            #_main_content ul {
                list-style: disc;
                margin: 20px 40px;
            }
            .duplicate-facet-value {
                white-space: nowrap;
                padding: 4px 10px;
                margin-right: 10px;
                border-radius: 2px;
                border: 1px solid rgb(180, 180, 180);
                background-color: rgb(220, 220, 220);
                display: inline-block;
            }
            #_product_facet_values td {
                padding: 2px 10px;
            }
            table.grid-table td.duplicate-values {
                background-color: rgb(245, 245, 245);
            }
            #_product_facet_values {
                margin-bottom: 40px;
            }
		</style>
		<?php
	}

	function hiddenElements() {
		?>
		<div id="_confirm_import_dialog" class="dialog-box">
			Are you sure you want to import and sync these facet values from the Coreware Shooting Sports Catalog into your catalog?
		</div> <!-- confirm_undo_dialog -->
		<?php
	}
}

$pageObject = new NormalizeProductFacetValuesPage();
$pageObject->displayPage();
