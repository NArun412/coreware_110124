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

function actionSaveGenerateForm($formDefinitionRow) {
	if (empty($_POST['shopping_cart_item_id']) || empty($_POST['product_addon_id'])) {
		return false;
	}
	$content = jsonEncode($_POST);
	$shoppingCartItemAddonId = getFieldFromId("shopping_cart_item_addon_id", "shopping_cart_item_addons", "shopping_cart_item_id", $_POST['shopping_cart_item_id'],
		"product_addon_id = ?", $_POST['product_addon_id']);
	$productAddonRow = getRowFromId("product_addons", "product_addon_id", $_POST['product_addon_id']);
	if (array_key_exists("sale_price", $_POST) && !empty($_POST['sale_price']) && is_numeric($_POST['sale_price'])) {
		$salePrice = $_POST['sale_price'];
	} else {
		$salePrice = $productAddonRow['sale_price'];
	}
	if (empty($shoppingCartItemAddonId)) {
		$resultSet = executeQuery("insert into shopping_cart_item_addons (shopping_cart_item_id,product_addon_id,quantity,sale_price,content) values (?,?,1,?,?)",
			$_POST['shopping_cart_item_id'], $_POST['product_addon_id'], $salePrice, $content);
	} else {
		$resultSet = executeQuery("update shopping_cart_item_addons set sale_price = ?, content = ? where shopping_cart_item_addon_id = ?",
			$salePrice, $content, $shoppingCartItemAddonId);
	}
    $returnValue = empty($resultSet['sql_error']);
    if(function_exists("_localProductAddonSaveGenerateForm")) {
        $returnValue = $returnValue && _localProductAddonSaveGenerateForm($_POST);
    }

    return $returnValue;
}

function actionCustomFormValues() {
	$content = getFieldFromId("content", "shopping_cart_item_addons", "shopping_cart_item_id", $_GET['shopping_cart_item_id'], "product_addon_id = ?", $_GET['product_addon_id']);
	if (empty($content)) {
		return array();
	}
	$returnArray = json_decode($content, true);
	return $returnArray;
}

function actionJavascriptValues() {
    $fields = getPageTextChunk("PRODUCT_ADDON_FORM_DATA_FIELDS");
    $fields = $fields ?: getPreference("PRODUCT_ADDON_FORM_DATA_FIELDS");
    $customDataArray = array();
    if(!empty($fields)) {
        $fieldArray = explode(",",$fields);
        foreach($fieldArray as $thisField) {
            $parts = explode(".",$thisField);
            if(count($parts) > 1) {
                $tableName = strtoupper(array_shift($parts));
            } else {
                $tableName = "CONTACTS";
            }
            $thisFieldName = $parts[0];
            $productId = getFieldFromId("product_id", "shopping_cart_items", "shopping_cart_item_id", $_GET['shopping_cart_item_id']);
            switch($tableName) {
                case "PRODUCTS":
                    $primaryIdentifier = $productId;
                    $dataTable = new DataTable("products");
                    break;
                case "EVENTS":
                    $primaryIdentifier = getFieldFromId("event_id", "events", "product_id", $productId);
                    $dataTable = new DataTable("events");
                    break;
                case "EVENT_TYPES":
                    $primaryIdentifier = getFieldFromId("event_type_id", "events", "product_id", $productId);
                    $dataTable = new DataTable("event_types");
                    break;
                case "LOCATIONS":
                    $primaryIdentifier = getFieldFromId("location_id", "events", "product_id", $productId);
                    $dataTable = new DataTable("locations");
                    break;
                default:
                    $primaryIdentifier = $GLOBALS['gUserRow']['contact_id'];
                    $dataTable = new DataTable("contacts");
            }
            if($dataTable->columnExists($thisFieldName)) {
                $data = getFieldFromId($thisFieldName,strtolower($tableName), $dataTable->getPrimaryKey(), $primaryIdentifier);
            } else {
                $data = CustomField::getCustomFieldData($primaryIdentifier, $thisFieldName, $tableName);
            }
            if(startsWith($data,"{")) {
                try {
                    $dataArray = json_decode($data,true);
                } catch(Exception $exception) {
                }
                $data = $dataArray ?: $data;
            }
            $customDataArray[strtolower($thisField)] = $data;
        }
        if(function_exists("_localProductAddonJavascriptValues")) {
            $customDataArray = array_merge($customDataArray, _localProductAddonJavascriptValues());
        }
    }
    ?>
    var customData = <?= jsonEncode($customDataArray) ?>;
    <?php
}

function actionResponseContent() {
	$formLink = getFieldFromId("link_name", "pages", "script_filename", "retailstore/checkoutv2.php", "inactive = 0 and internal_use_only = 0");
	if (empty($formLink)) {
		$formLink = getFieldFromId("link_name", "pages", "script_filename", "retailstore/simplifiedcheckout.php", "inactive = 0 and internal_use_only = 0");
	}
	if (empty($formLink)) {
		$formLink = "/shopping-cart";
	}
	ob_start();
	if (empty($formLink)) {
		?>
        <p class='redirecting_wrapper'>The product has been updated. Return to the shopping cart to checkout.</p>
		<?php
	} else {
		?>
        <p id='redirecting_wrapper'>Redirecting back to the shopping cart...</p>
        <script>
            setTimeout(function() {
                document.location = "/<?= $formLink ?>";
            });
        </script>
		<?php
	}
    return ob_get_clean();
}
