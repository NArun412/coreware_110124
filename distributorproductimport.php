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

$GLOBALS['gPageCode'] = "DISTRIBUTORPRODUCTIMPORT";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 6000000;
$memoryLimit = ini_get("memory_limit");
if (str_replace("M", "", $memoryLimit) < 8192) {
	ini_set("memory_limit", "8192M");
}

class DistributorProductImportPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "import_products":
				$locationId = getFieldFromId("location_id", "locations", "location_id", $_GET['location_id']);
				$productDistributor = ProductDistributor::getProductDistributorInstance($locationId);
				if (!$productDistributor) {
					$returnArray['error_message'] = "Can't get product distributor";
					ajaxResponse($returnArray);
					break;
				}
				$response = $productDistributor->syncProducts();
				if ($response === false) {
					$returnArray['error_message'] = $productDistributor->getErrorMessage();
					ajaxResponse($returnArray);
					break;
				} else {
					$returnArray['sync_results'] = $response;
				}
				ajaxResponse($returnArray);
				break;
            case "sync_inventory":
                $locationId = getFieldFromId("location_id", "locations", "location_id", $_GET['location_id']);
                $productDistributor = ProductDistributor::getProductDistributorInstance($locationId);
                if (!$productDistributor) {
                    $returnArray['error_message'] = "Can't get product distributor";
                    ajaxResponse($returnArray);
                    break;
                }
                $response = $productDistributor->syncInventory();
                if ($response === false) {
                    $returnArray['error_message'] = $productDistributor->getErrorMessage();
                    ajaxResponse($returnArray);
                    break;
                } else {
                    $returnArray['sync_results'] = $response;
                }
                ajaxResponse($returnArray);
                break;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#import_products").click(function () {
                if (!empty($("#location_id").val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=import_products&location_id=" + $("#location_id").val(), function(returnArray) {
                        if ("sync_results" in returnArray) {
                            $("#sync_results").html(returnArray['sync_results']);
                        }
                    });
                }
                return false;
            });
            $("#sync_inventory").click(function () {
                if (!empty($("#location_id").val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=sync_inventory&location_id=" + $("#location_id").val() , function(returnArray) {
                        if ("sync_results" in returnArray) {
                            $("#sync_results").html(returnArray['sync_results']);
                        }
                    });
                }
                return false;
            });

        </script>
		<?php
	}

	function mainContent() {
        ProductDistributor::setPrimaryDistributorLocation();
		?>
        <form id="_edit_form" name="_edit_form">

            <div class="basic-form-line" id="_location_id_row">
                <label for="location_id">Choose Location</label>
                <select tabindex="10" class="validate[required]" id="location_id" name="location_id">
                    <option value="">[Select]</option>
					<?php
					$resultSet = executeQuery("select * from locations join location_credentials using (location_id) join product_distributors using (product_distributor_id) where locations.client_id = ? and locations.inactive = 0 and location_credentials.inactive = 0 and primary_location = 1 order by product_distributors.description", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						?>
                        <option value="<?= $row['location_id'] ?>"><?= htmlText($row['description']) ?></option>
						<?php
					}
					?>
                </select>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>

            <div class="basic-form-line">
                <button tabindex="10" id="import_products">Import Products</button>
            </div>

            <div class="basic-form-line">
                <button tabindex="10" id="sync_inventory">Sync Inventory</button>
            </div>

            <div id="sync_results"></div>

        </form>
		<?php
		return true;
	}
}

$pageObject = new DistributorProductImportPage();
$pageObject->displayPage();
