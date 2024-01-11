<?php

/*      This software is the unpublished, confidential, proprietary, intellectual
        property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
        or used in any manner without expressed written consent from Kim David Software, LLC.
        Kim David Software, LLC owns all rights to this work and intends to keep this
        software confidential so as to maintain its value as a trade secret.

        Copyright 2004-Present, Kim David Software, LLC.
*/

$GLOBALS['gPageCode'] = "RECURRINGPAYMENTCSVIMPORT";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 300000;

class ThisPage extends Page {

	var $iErrorMessages = array();
    private $iValidFields = array("old_contact_id","contact_id","recurring_payment_type_code","payment_method_code","shipping_method_code","promotion_code",
			    "start_date","next_billing_date","end_date","account_id","account_number","subscription_code","customer_paused",
			    "product_code","product_id","quantity","sale_price");

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
		case "remove_import":
			$csvImportId = getFieldFromId("csv_import_id","csv_imports","csv_import_id",$_GET['csv_import_id']);
			if (empty($csvImportId)) {
				$returnArray['error_message'] = "Invalid CSV Import";
				ajaxResponse($returnArray);
				break;
			}
			$changeLogId = getFieldFromId("log_id","change_log","table_name","recurring_payments","primary_identifier in (select primary_identifier from csv_import_details where csv_import_id = ?)",$csvImportId);
			if (!empty($changeLogId)) {
				$returnArray['error_message'] = "Unable to remove import due to use of or changes to recurring payments #872";
				ajaxResponse($returnArray);
				break;
			}
			$GLOBALS['gPrimaryDatabase']->startTransaction();

			$deleteSet = executeQuery("delete from recurring_payment_order_items where recurring_payment_id in (select primary_identifier from csv_import_details where csv_import_id = ?)",$csvImportId);
			if (!empty($deleteSet['sql_error'])) {
				$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
				$returnArray['error_message'] = "Unable to remove import due to use of or changes to recurring payments #294";
				ajaxResponse($returnArray);
				break;
			}

			$deleteSet = executeQuery("delete from recurring_payments where recurring_payment_id in (select primary_identifier from csv_import_details where csv_import_id = ?)",$csvImportId);
			if (!empty($deleteSet['sql_error'])) {
				$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
				$returnArray['error_message'] = "Unable to remove import due to use of or changes to recurring payments #294";
				ajaxResponse($returnArray);
				break;
			}

			$deleteSet = executeQuery("delete from csv_import_details where csv_import_id = ?",$csvImportId);
			if (!empty($deleteSet['sql_error'])) {
				$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
				$returnArray['error_message'] = "Unable to remove import due to use of or changes to recurring payments #183";
				ajaxResponse($returnArray);
				break;
			}

			$deleteSet = executeQuery("delete from csv_imports where csv_import_id = ?",$csvImportId);
			if (!empty($deleteSet['sql_error'])) {
				$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
				$returnArray['error_message'] = $deleteSet['sql_error'];
				ajaxResponse($returnArray);
				break;
			}

			$returnArray['info_message'] = "Import successfully removed";
			$returnArray['csv_import_id'] = $csvImportId;
			$GLOBALS['gPrimaryDatabase']->commitTransaction();

			ajaxResponse($returnArray);

			break;
		case "import_csv":
			if (!array_key_exists("csv_file",$_FILES)) {
				$returnArray['error_message'] = "No File uploaded";
				ajaxResponse($returnArray);
				break;
			}

			$fieldValue = file_get_contents($_FILES['csv_file']['tmp_name']);
			$hashCode = md5($fieldValue);
			$csvImportId = getFieldFromId("csv_import_id","csv_imports","hash_code",$hashCode);
			if (!empty($csvImportId)) {
				$returnArray['error_message'] = "This file has already been imported.";
				ajaxResponse($returnArray);
				break;
			}
			$fullFile = file_get_contents($_FILES['csv_file']['tmp_name']);
			$fullFileLines = getContentLines($fullFile);
			$fileLines = array();
			foreach ($fullFileLines as $index => $thisLine) {
			    if (empty($index)) {
			        continue;
			    }
			    $fileLines[] = array("content"=>trim($thisLine));
			}

			$openFile = fopen($_FILES['csv_file']['tmp_name'],"r");

			$allValidFields = $this->iValidFields;
			$requiredFields = array("recurring_payment_type_code","start_date","next_billing_date");
			$numericFields = array("quantity","sale_price");

			$fieldNames = array();
			$importRecords = array();
			$count = 0;
			$this->iErrorMessages = array();
			while ($csvData = fgetcsv($openFile)) {
				if ($count == 0) {
					foreach ($csvData as $thisName) {
						$fieldNames[] = makeCode(trim($thisName),array("lowercase"=>true,"allow_dash"=>true));
					}
					$invalidFields = "";
					foreach ($fieldNames as $fieldName) {
						if (!in_array($fieldName,$allValidFields)) {
							$invalidFields .= (empty($invalidFields) ? "" : ", ") . $fieldName;
						}
					}
					if (!empty($invalidFields)) {
						$this->addErrorMessage("Invalid fields in CSV: " . $invalidFields);
						$this->addErrorMessage("Valid fields are: " . implode(", ",$allValidFields));
					}
				} else {
					$fieldData = array();
					foreach ($csvData as $index => $thisData) {
						$thisFieldName = $fieldNames[$index];
						$fieldData[$thisFieldName] = trim($thisData);
					}
					$importRecords[] = $fieldData;
				}
				$count++;
			}
			fclose($openFile);

			$recurringPaymentTypes = array();
			$paymentMethods = array();
			$shippingMethods = array();
			$promotions = array();
			$subscriptions = array();
			foreach ($importRecords as $index => $thisRecord) {
				$contactId = getFieldFromId("contact_id","contacts","contact_id",$thisRecord['contact_id']);
				if (empty($contactId) && !empty($thisRecord['contact_id'])) {
					$contactId = getFieldFromId("contact_id","contact_redirect","retired_contact_identifier",$thisRecord['contact_id']);
				}
				if (empty($contactId) && !empty($thisRecord['old_contact_id'])) {
					$contactId = getFieldFromId("contact_id","contact_redirect","retired_contact_identifier",$thisRecord['old_contact_id']);
				}
				if (empty($contactId)) {
				    $this->addErrorMessage("Line " . ($index + 2) . " - contact not found");
				    $fileLines[$index]['error_message'] = "Contact Not Found";
				}
				$importRecords[$index]['contact_id'] = $contactId;

				$missingFields = "";
				foreach ($requiredFields as $thisField) {
					if (empty($thisRecord[$thisField])) {
						$missingFields .= (empty($missingFields) ? "" : ", ") . $thisField;
					}
				}
				if (!empty($missingFields)) {
					$this->addErrorMessage("Line " . ($index + 2) . " has missing fields: " . $missingFields);
				}

				foreach ($numericFields as $fieldName) {
					if (!empty($thisRecord[$fieldName]) && !is_float($thisRecord[$fieldName] + 0) && !is_numeric($thisRecord[$fieldName] + 0)) {
						$this->addErrorMessage("Line " . ($index + 2) . ": " . $fieldName . " needs to be numeric: " . $thisRecord[$fieldName]);
					}
				}
				if (!empty($thisRecord['subscription_code'])) {
					if (!array_key_exists($thisRecord['subscription_code'],$subscriptions)) {
						$subscriptions[$thisRecord['subscription_code']] = "";
					}
				}
				if (!empty($thisRecord['payment_method_code'])) {
					if (!array_key_exists($thisRecord['payment_method_code'],$paymentMethods)) {
						$paymentMethods[$thisRecord['payment_method_code']] = "";
					}
				}
				if (!empty($thisRecord['shipping_method_code'])) {
					if (!array_key_exists($thisRecord['shipping_method_code'],$shippingMethods)) {
						$shippingMethods[$thisRecord['shipping_method_code']] = "";
					}
				}
				if (!empty($thisRecord['promotion_code'])) {
					if (!array_key_exists($thisRecord['promotion_code'],$promotions)) {
						$promotions[$thisRecord['promotion_code']] = "";
					}
				}
				if (!empty($thisRecord['recurring_payment_type_code'])) {
					if (!array_key_exists($thisRecord['recurring_payment_type_code'],$recurringPaymentTypes)) {
						$recurringPaymentTypes[$thisRecord['recurring_payment_type_code']] = "";
					}
				}

				$productIdArray = array();
				if (empty($thisRecord['product_id'])) {
				    $productCodes = explode(",",$thisRecord['product_code']);
				    foreach ($productCodes as $productCode) {
				        if (empty($productCode)) {
				            continue;
				        }
				        $productId = getFieldFromId("product_id","products","product_code",$productCode);
				        if (!empty($productId)) {
				            $productIdArray[] = $productId;
				        } else {
    						$this->addErrorMessage("Line " . ($index + 2) . ": Invalid Product Code: " . $productCode);
				        }
				    }
                } else {
        			$productIds = explode(",",$thisRecord['product_id']);
				    foreach ($productIds as $productId) {
				        if (empty($productId)) {
				            continue;
				        }
				        $productId = getFieldFromId("product_id","products","product_id",$productId);
				        if (!empty($productId)) {
				            $productIdArray[] = $productId;
				        } else {
    						$this->addErrorMessage("Line " . ($index + 2) . ": Invalid Product ID: " . $productId);
				        }
				    }
				}
				$quantityArray = array();
                $quantities = explode(",",$thisRecord['quantity']);
                foreach ($quantities as $quantity) {
                    if (empty($quantity)) {
                        continue;
                    }
                    if (is_numeric($quantity)) {
                        $quantityArray[] = $quantity;
                    } else {
                        $this->addErrorMessage("Line " . ($index + 2) . ": Invalid Quantity: " . $quantity);
                    }
                }
				$salePriceArray = array();
                $salePrices = explode(",",$thisRecord['sale_price']);
                foreach ($salePrices as $salePrice) {
                    if (empty($salePrice)) {
                        continue;
                    }
                    if (is_numeric($salePrice)) {
                        $salePriceArray[] = $salePrice;
                    } else {
                        $this->addErrorMessage("Line " . ($index + 2) . ": Invalid Sale Price: " . $salePrice);
                    }
                }
                if (empty($productIdArray)) {
                    $this->addErrorMessage("Line " . ($index + 2) . ": No products setup for payment");
                } else if (count($productIdArray) != count($quantityArray)) {
                    $this->addErrorMessage("Line " . ($index + 2) . ": Number of quantities doesn't match products");
                } else if (count($productIdArray) != count($salePriceArray)) {
                    $this->addErrorMessage("Line " . ($index + 2) . ": Number of sale prices doesn't match products");
                }
				$importRecords[$index]['product_id_array'] = $productIdArray;
				$importRecords[$index]['quantity_array'] = $quantityArray;
				$importRecords[$index]['sale_price_array'] = $salePriceArray;

				if (!empty($thisRecord['account_id'])) {
				    $accountId = getFieldFromId("account_id","accounts","contact_id",$contactId,"account_id = ?",$thisRecord['account_id']);
				    if (empty($accountId)) {
                        $this->addErrorMessage("Line " . ($index + 2) . ": Invalid Account ID");
                    } else {
				        $accountRow = getRowFromId("accounts","account_id",$accountId);
                        $eCommerce = eCommerce::getEcommerceInstance($accountRow['merchant_account_id']);
                        if(!$eCommerce || empty($accountRow['account_token']) || ($eCommerce->requiresCustomerToken() && empty($accountRow['merchant_identifier']))) {
                            $this->addErrorMessage("Line " . ($index + 2) . ": Account exists but is not in the merchant gateway");
				        }
                    }
    				$importRecords[$index]['account_id'] = $accountId;
				} else if (!empty($thisRecord['account_number'])) {
				    $accountId = getFieldFromId("account_id","accounts","contact_id",$contactId,
				    "account_number like ?","%" . $thisRecord['account_number']);
				    if (empty($accountId)) {
                        $this->addErrorMessage("Line " . ($index + 2) . ": Invalid Account Number");
                    } else {
				        $accountRow = getRowFromId("accounts","account_id",$accountId);
                        $eCommerce = eCommerce::getEcommerceInstance($accountRow['merchant_account_id']);
                        if(!$eCommerce || empty($accountRow['account_token']) || ($eCommerce->requiresCustomerToken() && empty($accountRow['merchant_identifier']))) {
                            $this->addErrorMessage("Line " . ($index + 2) . ": Account exists but is not in the merchant gateway");
				        }
                    }
    				$importRecords[$index]['account_id'] = $accountId;
				}
				if (empty($importRecords[$index]['account_id'])) {
				    $resultSet = executeQuery("select account_id from accounts where contact_id = ? and merchant_account_id is not null and merchant_identifier is not null and account_token is not null and inactive = 0",$contactId);
				    if ($row = getNextRow($resultSet)) {
        				$importRecords[$index]['account_id'] = $row['account_id'];
				    }
				}
				if (empty($importRecords[$index]['account_id'])) {
                    $this->addErrorMessage("Line " . ($index + 2) . ": " . (empty($thisRecord['contact_id']) ? $thisRecord['old_contact_id'] : $thisRecord['contact_id']) . ", No account for payment");
				    $fileLines[$index]['error_message'] = "No Account for Payment";
				}
			}

			foreach ($recurringPaymentTypes as $thisCode => $recurringPaymentTypeId) {
                $recurringPaymentTypeId = getFieldFromId("recurring_payment_type_id","recurring_payment_types","description",$thisCode);
				if (empty($recurringPaymentTypeId)) {
					$this->addErrorMessage("Invalid recurring payment type: " . $thisCode);
				} else {
					$recurringPaymentTypes[$thisCode] = $recurringPaymentTypeId;
				}
			}
			foreach ($shippingMethods as $thisCode => $shippingMethodId) {
				$shippingMethodId = getFieldFromId("shipping_method_id","shipping_methods","shipping_method_code",makeCode($thisCode));
				if (empty($shippingMethodId)) {
					$shippingMethodId = getFieldFromId("shipping_method_id","shipping_methods","description",$thisCode);
				}
				if (empty($shippingMethodId)) {
					$this->addErrorMessage("Invalid shipping method: " . $thisCode);
				} else {
					$shippingMethods[$thisCode] = $shippingMethodId;
				}
			}
			foreach ($promotions as $thisCode => $promotionId) {
				$promotionId = getFieldFromId("promotion_id","promotions","promotion_code",makeCode($thisCode));
				if (empty($promotionId)) {
					$promotionId = getFieldFromId("promotion_id","promotions","description",$thisCode);
				}
				if (empty($promotionId)) {
					$this->addErrorMessage("Invalid Promotion: " . $thisCode);
				} else {
					$promotions[$thisCode] = $promotionId;
				}
			}
			foreach ($paymentMethods as $thisCode => $paymentMethodId) {
				$paymentMethodId = getFieldFromId("payment_method_id","payment_methods","payment_method_code",makeCode($thisCode));
				if (empty($paymentMethodId)) {
					$paymentMethodId = getFieldFromId("payment_method_id","payment_methods","description",$thisCode);
				}
				if (empty($paymentMethodId)) {
					$this->addErrorMessage("Invalid Payment Method: " . $thisCode);
				} else {
					$paymentMethods[$thisCode] = $paymentMethodId;
				}
			}
			foreach ($subscriptions as $thisCode => $subscriptionId) {
				$subscriptionId = getFieldFromId("subscription_id","subscriptions","subscription_code",makeCode($thisCode));
				if (empty($subscriptionId)) {
					$subscriptionId = getFieldFromId("subscription_id","subscriptions","description",$thisCode);
				}
				if (empty($subscriptionId)) {
					$this->addErrorMessage("Invalid Subscription: " . $thisCode);
				} else {
					$subscriptions[$thisCode] = $subscriptionId;
				}
			}
			foreach ($importRecords as $index => $thisRecord) {
			    if (!empty($thisRecord['subscription_code'])) {
			        $subscriptionId = $subscriptions[$thisRecord['subscription_code']];
			        $contactSubscriptionId = getFieldFromId("contact_subscription_id","contact_subscriptions","contact_id",$thisRecord['contact_id'],
			            "subscription_id = ?",$subscriptionId);
			        $importRecords[$index]['contact_subscription_id'] = $contactSubscriptionId;
			        if (empty($contactSubscriptionId)) {
                        $this->addErrorMessage("Line " . ($index + 2) . ": " . (!empty($thisRecord['old_contact_id']) ? $thisRecord['old_contact_id'] : $thisRecord['contact_id']) . ", Can't find contact subscription");
    				    $fileLines[$index]['error_message'] = "No Contact Subscription";
			        }
			    }
			}

			if (!empty($this->iErrorMessages)) {
				$returnArray['import_error'] = "<p>" . count($this->iErrorMessages) . " errors found</p>";
				foreach ($this->iErrorMessages as $thisMessage => $count) {
					$returnArray['import_error'] .= "<p>" . $count . ": " . $thisMessage . "</p>";
				}

				$returnArray['error_lines'] = "";
				foreach ($fileLines as $thisLine) {
				    $returnArray['error_lines'] .= $thisLine['error_message'] . "," . $thisLine['content'] . "\r";
				}

				ajaxResponse($returnArray);

				break;
			}

			$resultSet = executeQuery("insert into csv_imports (client_id,description,table_name,hash_code,time_submitted,user_id) values (?,?,'recurring_payments',?,now(),?)",$GLOBALS['gClientId'],$_POST['description'],$hashCode,$GLOBALS['gUserId']);
			if (!empty($resultSet['sql_error'])) {
				$returnArray['error_message'] = $returnArray['import_error'] = getSystemMessage("basic",$resultSet['sql_error']);
				ajaxResponse($returnArray);
				break;
			}
			$csvImportId = $resultSet['insert_id'];

			$insertCount = 0;
			$updateCount = 0;
			$recurringPayments = array();
			foreach ($importRecords as $index => $thisRecord) {
				$contactId = $thisRecord['contact_id'];
				if (empty($contactId)) {
					if (!empty($recurringPayments)) {
						executeQuery("delete from recurring_payment_order_items where recurring_payment_id in (" . implode(",",$recurringPayments) . ")");
						executeQuery("delete from recurring_payments where recurring_payment_id in (" . implode(",",$recurringPayments) . ")");
					}
					executeQuery("delete from csv_import_details where csv_import_id = ?",$csvImportId);
					executeQuery("delete from csv_imports where csv_import_id = ?",$csvImportId);
					$returnArray['error_message'] = $returnArray['import_error'] = "Line " . ($index + 2) . ": unable to find contact";
					ajaxResponse($returnArray);
					break;
				}
                $resultSet = executeQuery("insert into recurring_payments (contact_id,recurring_payment_type_id,payment_method_id," .
                    "shipping_method_id,promotion_id,start_date,next_billing_date,end_date,account_id,contact_subscription_id) values (?,?,?, ?,?,?,?,?,?,?)",
                    $contactId,$recurringPaymentTypes[$thisRecord['recurring_payment_type_code']],$paymentMethods[$thisRecord['payment_method_code']],
                    $shippingMethods[$thisRecord['shipping_method_code']],$promotions[$thisRecord['promotion_code']],date("Y-m-d",strtotime($thisRecord['start_date'])),
                    (empty($thisRecord['next_billing_date']) ? "" : date("Y-m-d",strtotime($thisRecord['next_billing_date']))),
                    (empty($thisRecord['end_date']) ? "" : date("Y-m-d",strtotime($thisRecord['end_date']))),
                    $thisRecord['account_id'],$thisRecord['contact_subscription_id']);
                if (!empty($resultSet['sql_error'])) {
                    if (!empty($recurringPayments)) {
                        executeQuery("delete from recurring_payment_order_items where recurring_payment_id in (" . implode(",",$recurringPayments) . ")");
                        executeQuery("delete from recurring_payments where recurring_payment_id in (" . implode(",",$recurringPayments) . ")");
                    }
                    executeQuery("delete from csv_import_details where csv_import_id = ?",$csvImportId);
                    executeQuery("delete from csv_imports where csv_import_id = ?",$csvImportId);
                    $returnArray['error_message'] = $returnArray['import_error'] = getSystemMessage("basic",$resultSet['sql_error']);
                    ajaxResponse($returnArray);
                    break;
                }
                $recurringPaymentId = $resultSet['insert_id'];
                $recurringPayments[] = $recurringPaymentId;
                $insertCount++;
                $insertSet = executeQuery("insert into csv_import_details (csv_import_id,primary_identifier) values (?,?)",$csvImportId,$recurringPaymentId);
                if (!empty($insertSet['sql_error'])) {
                    if (!empty($recurringPayments)) {
                        executeQuery("delete from recurring_payment_order_items where recurring_payment_id in (" . implode(",",$recurringPayments) . ")");
                        executeQuery("delete from recurring_payments where recurring_payment_id in (" . implode(",",$recurringPayments) . ")");
                    }
                    executeQuery("delete from csv_import_details where csv_import_id = ?",$csvImportId);
                    executeQuery("delete from csv_imports where csv_import_id = ?",$csvImportId);
                    $returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
                    ajaxResponse($returnArray);
                    break;
                }
                foreach ($thisRecord['product_id_array'] as $productIndex => $productId) {
                    $insertSet = executeQuery("insert into recurring_payment_order_items (recurring_payment_id,product_id,quantity,sale_price) values (?,?,?,?)",
                        $recurringPaymentId,$productId,$thisRecord['quantity_array'][$productIndex],$thisRecord['sale_price_array'][$productIndex]);
                    if (!empty($insertSet['sql_error'])) {
                        if (!empty($recurringPayments)) {
                            executeQuery("delete from recurring_payment_order_items where recurring_payment_id in (" . implode(",",$recurringPayments) . ")");
                            executeQuery("delete from recurring_payments where recurring_payment_id in (" . implode(",",$recurringPayments) . ")");
                        }
                        executeQuery("delete from csv_import_details where csv_import_id = ?",$csvImportId);
                        executeQuery("delete from csv_imports where csv_import_id = ?",$csvImportId);
                        $returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
                        ajaxResponse($returnArray);
                        break;
                    }
                }
			}

			$returnArray['response'] = "<p>" . $insertCount . " recurring payments imported.</p>";
			$returnArray['response'] .= "<p>" . $updateCount . " existing recurring payments found.</p>";
			ajaxResponse($returnArray);
			break;
		}

	}

	function addErrorMessage($errorMessage) {
		if (array_key_exists($errorMessage,$this->iErrorMessages)) {
			$this->iErrorMessages[$errorMessage]++;
		} else {
			$this->iErrorMessages[$errorMessage] = 1;
		}
	}

	function mainContent() {
		echo $this->iPageData['content'];

?>
<div id="_form_div">
            <p><strong>Valid Fields: </strong><?= implode(", ", $this->iValidFields) ?></p>
<form id="_edit_form" enctype='multipart/form-data'>

<div class="basic-form-line" id="_csv_file_row">
	<label for="description" class="required-label">Description</label>
	<input tabindex="10" class="validate[required]" size="40" type="text" id="description" name="description">
    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
</div>

<div class="basic-form-line" id="_csv_file_row">
	<label for="csv_file" class="required-label">CSV File</label>
	<input tabindex="10" class="validate[required]" type="file" id="csv_file" name="csv_file">
    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
</div>

<div class="basic-form-line">
	<button tabindex="10" id="_submit_form">Import</button>
	<div id="import_message"></div>
</div>

<div id="import_error"></div>

<div class='basic-form-line hidden' id="_error_lines_row">
    <textarea id="error_lines"></textarea>
</div>

</form>
</div> <!-- form_div -->

<table class="grid-table">
	<tr>
		<th>Description</th>
		<th>Imported On</th>
		<th>By</th>
		<th>Count</th>
		<th></th>
	</tr>
<?php
		$resultSet = executeQuery("select * from csv_imports where table_name = 'recurring_payments' and client_id = ? order by time_submitted desc",$GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$importCount = 0;
			$countSet = executeQuery("select count(*) from csv_import_details where csv_import_id = ?",$row['csv_import_id']);
			if ($countRow = getNextRow($countSet)) {
				$importCount = $countRow['count(*)'];
			}
			$minutesSince = (time() - strtotime($row['time_submitted'])) / 60;
			$canUndo = $minutesSince < 48;
?>
<tr id="csv_import_id_<?= $row['csv_import_id'] ?>" class="import-row" data-csv_import_id="<?= $row['csv_import_id'] ?>">
	<td><?= htmlText($row['description']) ?></td>
	<td><?= date("m/d/Y g:i a",strtotime($row['time_submitted'])) ?></td>
	<td><?= getUserDisplayName($row['user_id']) ?></td>
	<td><?= $importCount ?></td>
	<td><?= ($canUndo ? "<span class='far fa-undo remove-import'></span>" : "") ?></td>
</tr>
<?php
		}
		echo "</table>";
		return true;
	}

	function onLoadJavascript() {
?>
<script>
$(document).on("click",".remove-import",function() {
	let csvImportId = $(this).closest("tr").data("csv_import_id");
	$('#_confirm_undo_dialog').dialog({
		closeOnEscape: true,
		draggable: false,
		modal: true,
		resizable: false,
		position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
		width: 400,
		title: 'Remove Import',
		buttons:{
			Yes: function (event) {
				loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=remove_import&csv_import_id=" + csvImportId,function(returnArray){
						if ("csv_import_id" in returnArray) {
							$("#csv_import_id_" + returnArray['csv_import_id']).remove();
						}
					});
				$("#_confirm_undo_dialog").dialog('close');
			},
			Cancel: function (event) {
				$("#_confirm_undo_dialog").dialog('close');
			}
		}
	});
	return false;
});
$(document).on("tap click","#_submit_form",function() {
	if ($("#_submit_form").data("disabled") == "true") {
		return false;
	}
	if ($("#_edit_form").validationEngine("validate")) {
	    $("#_error_lines_row").addClass("hidden");
		disableButtons($("#_submit_form"));
		$("body").addClass("waiting-for-ajax");
		$("#_edit_form").attr("action","<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=import_csv").attr("method","POST").attr("target","post_iframe").submit();
		$("#_post_iframe").off("load");
		$("#_post_iframe").on("load",function() {
			$("body").removeClass("no-waiting-for-ajax").removeClass("waiting-for-ajax");
			let returnText = $(this).contents().find("body").html();
			const returnArray = processReturn(returnText);
			if (returnArray === false) {
				enableButtons($("#_submit_form"));
				return;
			}
			if ("import_error" in returnArray) {
				$("#import_error").html(returnArray['import_error']);
			}
			if ("error_lines" in returnArray) {
				$("#error_lines").val(returnArray['error_lines']);
				$("#_error_lines_row").removeClass("hidden");
			}
			if ("response" in returnArray) {
				$("#_form_div").html(returnArray['response']);
			}
			enableButtons($("#_submit_form"));
		});
	}
	return false;
});
</script>
<?php
	}

	function internalCSS() {
?>
#import_error { color: rgb(192,0,0); }
.remove-import { cursor: pointer; }
<?php
	}

	function hiddenElements() {
?>
<iframe id="_post_iframe" name="post_iframe"></iframe>

<div id="_confirm_undo_dialog" class="dialog-box">
This will result in these recurring payments being removed. Are you sure?
</div> <!-- confirm_undo_dialog -->
<?php
	}
}

$pageObject = new ThisPage();
$pageObject->displayPage();
