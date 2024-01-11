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

$GLOBALS['gPageCode'] = "ORDERHISTORYCSVIMPORT";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 300000;
$GLOBALS['gSkipCorestoreContactUpdate'] = true;

class OrderHistoryCsvImportPage extends Page {

	protected $iCountriesArray = array();
	protected $iStatesArray = array();

	var $iContactIdentifierTypes = array();
	var $iContactIdentifierFields = array();
	private $iValidOrderFields = array("order_number", "order_status", "order_time", "full_name", "first_name", "last_name", "order_method", "address_1", "address_2", "city", "state", "postal_code",
		"country", "phone_number", "email_address", "purchase_order_number", "order_total", "tax_charge", "shipping_charge", "order_discount", "ip_address", "promotion_code", "source");
	private $iValidOrderItemFields = array("product_description", "product_code", "upc_code", "sale_price", "quantity", "item_tax_charge");
	private $iShowDetailedErrors = false;


	function setup() {
		$this->iShowDetailedErrors = $GLOBALS['gUserRow']['superuser_flag'] ?: !empty(getPreference("CSV_IMPORT_DETAILED_ERRORS"));
		$resultSet = executeQuery("select * from contact_identifier_types where inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$this->iContactIdentifierFields[] = "contact_identifier-" . strtolower($row['contact_identifier_type_code']);
			$this->iContactIdentifierTypes[strtolower($row['contact_identifier_type_code'])] = $row['contact_identifier_type_id'];
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "remove_import":
				$csvImportId = getFieldFromId("csv_import_id", "csv_imports", "csv_import_id", $_GET['csv_import_id']);
				if (empty($csvImportId)) {
					$returnArray['error_message'] = "Invalid CSV Import";
					ajaxResponse($returnArray);
					break;
				}
				$changeLogId = getFieldFromId("log_id", "change_log", "table_name", "orders", "primary_identifier in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($changeLogId)) {
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to orders: change log";
					ajaxResponse($returnArray);
					break;
				}
				$GLOBALS['gPrimaryDatabase']->startTransaction();

				$deleteSet = executeQuery("delete from order_notes where order_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				$this->checkSqlError($deleteSet, $returnArray, "Unable to remove import due to use of or changes to orders: order_notes");

				$deleteSet = executeQuery("delete from order_items where order_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				$this->checkSqlError($deleteSet, $returnArray, "Unable to remove import due to use of or changes to orders: order_items");

				$deleteSet = executeQuery("delete from order_promotions where order_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				$this->checkSqlError($deleteSet, $returnArray, "Unable to remove import due to use of or changes to orders: order_promotions");

				$deleteSet = executeQuery("delete from orders where order_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				$this->checkSqlError($deleteSet, $returnArray, "Unable to remove import due to use of or changes to orders: orders");

				$deleteSet = executeQuery("delete from csv_import_details where csv_import_id = ?", $csvImportId);
				$this->checkSqlError($deleteSet, $returnArray, "Unable to remove import due to use of or changes to orders");

				$deleteSet = executeQuery("delete from csv_imports where csv_import_id = ?", $csvImportId);
				$this->checkSqlError($deleteSet, $returnArray, "Unable to remove import");

				$returnArray['info_message'] = "Import successfully removed";
				$returnArray['csv_import_id'] = $csvImportId;
				$GLOBALS['gPrimaryDatabase']->commitTransaction();

				ajaxResponse($returnArray);

				break;

			case "import_file":
				if (!array_key_exists("csv_file", $_FILES)) {
					$returnArray['error_message'] = "No File uploaded";
					ajaxResponse($returnArray);
					break;
				}

				$fieldValue = file_get_contents($_FILES['csv_file']['tmp_name']);
				$hashCode = md5($fieldValue);
				$csvImportId = getFieldFromId("csv_import_id", "csv_imports", "hash_code", $hashCode);
				if (!empty($csvImportId)) {
					$returnArray['error_message'] = "This file has already been imported.";
					ajaxResponse($returnArray);
					break;
				}

				// Build lookup arrays
				$existingOrdersResult = executeQuery("select * from orders where client_id = ?", $GLOBALS['gClientId']);
				$existingOrders = array();
				while ($row = getNextRow($existingOrdersResult)) {
					$existingOrders[$row['order_number']] = $row['order_id'];
				}
				freeResult($existingOrdersResult);

				$upcProductIdsResult = executeQuery("select * from product_data where client_id = ?", $GLOBALS['gClientId']);
				$upcProductIds = array();
				while ($row = getNextRow($upcProductIdsResult)) {
					if (!empty($row['upc_code'])) {
						$upcProductIds[$row['upc_code']] = $row['product_id'];
					}
				}

				$productCodeUpcs = array();
				ProductDistributor::downloadProductMetaData();
				if (is_array($GLOBALS['coreware_product_metadata'])) {
					foreach ($GLOBALS['coreware_product_metadata'] as $thisProduct) {
						$upcCode = $thisProduct['upc_code'];
						$distributorProductCodes = explode("|", $thisProduct['distributor_product_codes']);
						foreach ($distributorProductCodes as $thisCode) {
							$code = explode(",", $thisCode)[1];
							if (!empty($code)) {
                                if (is_array($productCodeUpcs[$code])) {
									$productCodeUpcs[$code][] = $upcCode;
								} else {
									$productCodeUpcs[$code] = array($productCodeUpcs[$code], $upcCode);
								}
							}
						}
					}
				}

				$productCodesResult = executeQuery("select product_code,product_id from products where client_id = ?", $GLOBALS['gClientId']);
				$productCodes = array();
				while ($row = getNextRow($productCodesResult)) {
					$productCodes[$row['product_code']] = $row['product_id'];
				}
				freeResult($productCodesResult);

				$productCodesResult = executeQuery("select product_code,product_id from distributor_product_codes where client_id = ?", $GLOBALS['gClientId']);
				while ($row = getNextRow($productCodesResult)) {
					$productCodes[$row['product_code']] = $row['product_id'];
				}
				freeResult($productCodesResult);

				$skuResult = executeQuery("select manufacturer_sku, product_id from product_data where client_id = ?", $GLOBALS['gClientId']);
				while ($row = getNextRow($skuResult)) {
					$productCodes[$row['manufacturer_sku']] = $row['product_id'];
				}
				freeResult($skuResult);

				$userEmailResult = executeQuery("select contact_id, user_id, email_address from contacts join users using (contact_id) where contacts.client_id = ?", $GLOBALS['gClientId']);
				$userEmails = array();
				while ($row = getNextRow($userEmailResult)) {
					$userEmails[$row['email_address']]['contact_id'] = $row['contact_id'];
					$userEmails[$row['email_address']]['user_id'] = $row['user_id'];
				}
				freeResult($userEmailResult);

				$contactEmailResult = executeQuery("select contact_id, email_address from contacts where client_id = ?", $GLOBALS['gClientId']);
				$contactEmails = array();
				while ($row = getNextRow($contactEmailResult)) {
					$contactEmails[$row['email_address']] = $row['contact_id'];
				}
				freeResult($contactEmailResult);

				$countriesResult = executeQuery("select * from countries");
				while ($row = getNextRow($countriesResult)) {
					$this->iCountriesArray[$row['country_code']] = $row['country_id'];
				}
				freeResult($countriesResult);

				$this->iStatesArray = array_flip(getStateArray());

				$promotionsResult = executeQuery("select * from promotions where client_id = ?", $GLOBALS['gClientId']);
				$promotions = array();
				while ($row = getNextRow($promotionsResult)) {
					$promotions[strtoupper($row['promotion_code'])] = $row['promotion_id'];
				}
				freeResult($promotionsResult);

				// Make sure custom product exists
				if ($_POST['use_custom_product_for_products_not_found']) {
					$customProductId = getFieldFromId('product_id', 'products', 'product_code', 'IMPORTED_PRODUCT');
					if (empty($customProductId)) {
						$result = executeQuery("insert into products (client_id, product_code, description, date_created, custom_product, internal_use_only) "
							. "values (?,'IMPORTED_PRODUCT', 'Imported Product', current_date, 1, 1)", $GLOBALS['gClientId']);
						$customProductId = $result['insert_id'];
					}
				}

				// Load and validate data
				$openFile = fopen($_FILES['csv_file']['tmp_name'], "r");
				$lineNumber = 0;
				$errorMessage = "";
				$importArray = array();
				$allValidFields = array_merge($this->iValidOrderFields, $this->iValidOrderItemFields, $this->iContactIdentifierFields);
				$requiredFields = array("order_number", "order_time", "product_code", "sale_price", "quantity");
				$requiredFields[] = "add_contacts_not_found|email_address|" . implode("|", $this->iContactIdentifierFields);
				$numericFields = array("order_number", "sale_price", "quantity", "order_total", "tax_charge", "item_tax_charge", "shipping_charge");
				$dateTimeFields = array("order_time");

				$fieldNames = array();
				$importRecords = array();
				$count = 0;
				$this->iErrorMessages = array();
				# parse file and check for invalid fields
				while ($csvData = fgetcsv($openFile)) {
					if ($count == 0) {
						foreach ($csvData as $thisName) {
							$fieldNames[] = makeCode(trim($thisName), array("lowercase" => true, "allow_dash" => true));
						}
						$invalidFields = "";
						foreach ($fieldNames as $fieldName) {
							if (!in_array($fieldName, $allValidFields)) {
								$invalidFields .= (empty($invalidFields) ? "" : ", ") . $fieldName;
							}
						}
						if (!empty($invalidFields)) {
							$this->addErrorMessage("Invalid fields in CSV: " . $invalidFields);
							$this->addErrorMessage("Valid fields are: " . implode(", ", $allValidFields));
						}
					} else {
						$fieldData = array("add_contacts_not_found" => !empty($_POST['add_contacts_not_found']));
						foreach ($csvData as $index => $thisData) {
							$thisFieldName = $fieldNames[$index];
							// strip non printable characters - preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $thisData)
							if(in_array($thisFieldName,$numericFields)) {
								$fieldData[$thisFieldName] = str_replace(["$",","],"",trim($thisData));
							} else {
								$fieldData[$thisFieldName] = trim(convertSmartQuotes($thisData));
							}
						}
						if (empty(array_filter($fieldData))) {
							continue;
						}
						$importRecords[] = $fieldData;
					}
					$count++;
				}
				fclose($openFile);

				# check for required fields and invalid data
				$orderStatuses = array();
				foreach ($importRecords as $index => $thisRecord) {
					$missingFields = "";
					foreach ($requiredFields as $thisField) {
						if (strpos($thisField, "|") !== false) {
							$alternateRequiredFields = explode("|", $thisField);
							$found = false;
							foreach ($alternateRequiredFields as $thisAlternate) {
								$found = $found ?: !empty($thisRecord[$thisAlternate]);
							}
							if (!$found) {
								$missingFields .= (empty($missingFields) ? "" : ", ") . str_replace("|", " or ", $thisField);
							}
						} else {
							if (empty($thisRecord[$thisField])) {
								$missingFields .= (empty($missingFields) ? "" : ", ") . $thisField;
							}
						}
					}
					if (!empty($missingFields)) {
						$this->addErrorMessage("Line " . ($index + 2) . " has missing fields: " . $missingFields);
					}

					foreach ($numericFields as $fieldName) {
						if (!empty($thisRecord[$fieldName]) && !is_float($thisRecord[$fieldName]) && !is_numeric($thisRecord[$fieldName])) {
							$this->addErrorMessage("Line " . ($index + 2) . ": " . $fieldName . " needs to be numeric: " . $thisRecord[$fieldName]);
						}
					}
					foreach ($dateTimeFields as $fieldName) {
						if (!empty($thisRecord[$fieldName]) && strtotime($thisRecord[$fieldName]) == false) {
							$this->addErrorMessage("Line " . ($index + 2) . ": " . $fieldName . " needs to be a valid date or time: " . $thisRecord[$fieldName]);
						}
					}
					if (!empty($thisRecord['order_status'])) {
						$orderStatusId = $orderStatuses[$thisRecord['order_status']];
						if (empty($orderStatusId)) {
							$orderStatusId = getFieldFromId("order_status_id", "order_status", "order_status_code", makeCode($thisRecord['order_status']));
						}
						if (empty($orderStatusId)) {
							$orderStatusId = getFieldFromId("order_status_id", "order_status", "description", $thisRecord['order_status']);
						}
						if (empty($orderStatusId)) {
							$this->addErrorMessage("Line " . ($index + 2) . ": Order Status does not exist: " . $thisRecord['order_status']);
						} else {
							$orderStatuses[$thisRecord['order_status']] = $orderStatusId;
						}
					}
				}

				if (!empty($this->iErrorMessages)) {
					$returnArray['import_error'] = "<p>" . count($this->iErrorMessages) . " errors found</p>";
					foreach ($this->iErrorMessages as $thisMessage => $count) {
						$returnArray['import_error'] .= "<p>" . ($count > 1 ? $count . ": " : "") . $thisMessage . "</p>";
					}
					ajaxResponse($returnArray);
					break;
				}

				# order specific validation

				$ordersArray = array();
				$foundProducts = array();
				$missingProducts = array();
				$multiMatchProducts = array();
				$matchedProducts = array();
				$usedCustom = array();
				$importUpcs = array();
				$missingSkipCount = 0;

				foreach ($importRecords as $index => $thisRecord) {

					$orderNumber = $thisRecord['order_number'];
					$contactEmail = $thisRecord['email_address'];
					$contactId = "";
					$userId = "";
					if (!empty($contactEmail)) {
						if (array_key_exists($contactEmail, $userEmails)) {
							$contactId = $userEmails[$contactEmail]['contact_id'];
							$userId = $userEmails[$contactEmail]['user_id'];
						} elseif (array_key_exists($contactEmail, $contactEmails)) {
							$contactId = $contactEmails[$contactEmail];
						}
					}
					if (empty($contactId)) {
						foreach ($this->iContactIdentifierTypes as $identifierTypeCode => $identifierTypeId) {
							if (empty($thisRecord['contact_identifier-' . $identifierTypeCode])) {
								continue;
							}
							$contactId = getFieldFromId("contact_id", "contact_identifiers", "identifier_value", $thisRecord['contact_identifier-' . $identifierTypeCode],
								"contact_identifier_type_id = ?", $identifierTypeId);
							if (!empty($contactId)) {
								break;
							}
						}
					}
					if (empty($contactId) && empty($_POST['add_contacts_not_found'])) {
						$this->addErrorMessage("Order " . $orderNumber . ": Contact Not found: " . $contactEmail);
					} else {
						$thisRecord['contact_id'] = $contactId;
						$thisRecord['user_id'] = $userId;
					}

					if (!array_key_exists($orderNumber, $ordersArray)) {
						$order = $thisRecord;
						foreach ($this->iValidOrderItemFields as $thisField) {
							unset($order[$thisField]);
						}
						$order['order_items'] = array();
						$order['check_total'] = 0;
						$ordersArray[$orderNumber] = $order;
					}

					$orderItem = array();
					foreach ($this->iValidOrderItemFields as $thisField) {
						$orderItem[$thisField] = $thisRecord[$thisField];
					}

					# identify products

					$found = false;
					$foundInCatalog = false;
					$foundMultiple = false;
					$foundCustom = false;
					$foundUpc = "";
					if (!empty($orderItem['upc_code']) && !empty($upcProductIds[$orderItem['upc_code']])) {
						$found = true;
						$orderItem['product_id'] = $upcProductIds[$orderItem['upc_code']];
					}
					$tries = 0;
					$productCode = makeCode($thisRecord['product_code']);
					while (!$found) {
						if ($tries == 1) {
							$parts = explode("-", $productCode);
							$last = array_pop($parts);
							if (strlen($last) > 3 && preg_match('/^(?=.*[a-z])(?=.*[A-Z])[a-zA-Z0-9]*$/', $last)) {
								$productCode = makeCode(implode("-", $parts), array("allow_dash" => true));
							} else {
								$productCode = makeCode($productCode, array("allow_dash" => true));
							}
						} elseif ($tries == 2) {
							$productCode = str_replace("-", " ", $productCode);
						} elseif ($tries == 3) {
							$productCode = str_replace(" ", "_", $productCode);
						} elseif ($tries == 4) {
							$productCode = str_replace("_", "", $productCode);
						} elseif ($tries > 4) {
							if (!empty($_POST['use_custom_product_for_products_not_found'])) {
								$orderItem['product_id'] = $customProductId;
								$foundCustom = true;
								$found = true;
							}
							break;
						}

						if (array_key_exists($productCode, $productCodes)) {
							$orderItem['product_id'] = $productCodes[$productCode];
							$found = true;
						}

						if (!$found) {
							if (array_key_exists($productCode, $productCodeUpcs)) {
								if (is_array($productCodeUpcs[$productCode])) {
									$foundMultiple = true;
									$foundUpc = implode(",", $productCodeUpcs[$productCode]);
								} else {
									$productId = $upcProductIds[$productCodeUpcs[$productCode]];
									if (empty($productId)) {
										$foundInCatalog = true;
										$foundUpc = $productCodeUpcs[$productCode];
										$orderItem['upc_code'] = $productCodeUpcs[$productCode];
									} else {
										$orderItem['product_id'] = $productId;
										$found = true;
									}
								}
							}
						}
						$tries++;
					}

					if (!$found) {
						if ($foundInCatalog) {
							$matchedProducts[$productCode][] = $index;
							if (empty($_POST['import_products_not_found'])) {
								if (empty($_POST['skip_orders_products_not_found'])) {
									$this->addErrorMessage("Order " . $orderNumber . ": Product not found but available in Coreware Catalog: " . $productCode . " UPC " . $foundUpc);
								} else {
									$missingSkipCount++;
									continue;
								}
							} else {
								$importUpcs[] = $foundUpc;
							}
						} elseif ($foundMultiple) {
							$multiMatchProducts[$productCode][] = $index;
							if (empty($_POST['skip_orders_products_not_found'])) {
								$this->addErrorMessage("Order " . $orderNumber . ": Product matches multiple UPCs: " . $productCode . " UPCs " . $foundUpc);
							} else {
								$missingSkipCount++;
								continue;
							}
						} else {
							$missingProducts[$thisRecord['product_code']][] = $index;
							if (empty($_POST['skip_orders_products_not_found'])) {
								$this->addErrorMessage("Order " . $orderNumber . ": Product Not found: " . $thisRecord['product_code']);
							} else {
								$missingSkipCount++;
								continue;
							}
						}
					} else {
						if ($foundCustom) {
							$usedCustom[$thisRecord['product_code']][] = $index;
						} else {
							$foundProducts[$productCode][] = $index;
						}
					}

					$ordersArray[$orderNumber]['order_items'][] = $orderItem;
					$ordersArray[$orderNumber]['check_total'] += $thisRecord['quantity'] * $thisRecord['sale_price'] + $thisRecord['item_tax_charge'];
				}

				foreach ($ordersArray as $thisOrder) {
					if (strlen($thisOrder['order_total']) > 0 && round($thisOrder['order_total'], 2) != round($thisOrder['check_total'], 2)) {
						$this->addErrorMessage(sprintf("Order Number %s: order item total (%s) does not match order_total (%s)", $thisOrder['order_number'],
							$thisOrder['check_total'], $thisOrder['order_total']));
					}
				}

				if (!empty($this->iErrorMessages)) {
					$returnArray['import_error'] = "<p>" . count($this->iErrorMessages) . " errors found</p>";
					foreach ($this->iErrorMessages as $thisMessage => $count) {
						$returnArray['import_error'] .= "<p>" . ($count > 1 ? $count . ": " : "") . $thisMessage . "</p>";
					}
					ajaxResponse($returnArray);
					break;
				}

				if (!empty($_POST['validate_only'])) {
					$returnArray['response'] = "File validated successfully. No errors found.";
					ajaxResponse($returnArray);
					break;
				}

				// Import missing products
				if (!empty($_POST['import_products_not_found'])) {
					foreach ($importUpcs as $upcCode) {
						// Make sure product doesn't already exist in local database (and wasn't just imported)
						$productId = getFieldFromId('product_id', 'product_data', 'upc_code', $upcCode, "client_id = " . $GLOBALS['gClientId']);
						if (empty($productId)) {
							$result = ProductCatalog::importProductFromUPC($upcCode);
							if (key_exists('error_message', $result)) {
								$result['error_message'] = " UPC Code: " . $upcCode . " " . $result['error_message'];
								ajaxResponse($result);
								break;
							}
						}
					}
				}

				// Import data
				$GLOBALS['gPrimaryDatabase']->startTransaction();
				$resultSet = executeQuery("insert into csv_imports (client_id,description,table_name,hash_code,time_submitted,user_id,content) values (?,?,'orders',?,now(),?,?)",
					$GLOBALS['gClientId'], $_POST['description'], $hashCode, $GLOBALS['gUserId'], file_get_contents($_FILES['csv_file']['tmp_name']));
				if (!empty($resultSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = $returnArray['import_error'] = getSystemMessage("basic", $resultSet['sql_error']) . ($this->iShowDetailedErrors ? ": " . $resultSet['sql_error'] : "");
					ajaxResponse($returnArray);
					break;
				}
				$csvImportId = $resultSet['insert_id'];

				$insertCount = 0;
				$updateCount = 0;
				$existingSkipCount = 0;
				foreach ($ordersArray as $thisOrder) {
					// If skipping orders with missing products, make sure all products exist
					if (!empty($_POST['skip_orders_products_not_found'])) {
						$productsExist = true;
						foreach ($thisOrder['order_items'] as $thisItem) {
							$productId = getFieldFromId('product_id', 'products', 'product_id', $thisItem['product_id']);
							if (empty($productId) && array_key_exists('upc_code', $thisItem)) {
								$productId = getFieldFromId('product_id', 'product_data', 'upc_code', $thisItem['upc_code']);
							}
							if (empty($productId)) {
								$productsExist = false;
								break;
							}
						}
						if (!$productsExist) {
							$missingSkipCount++;
							continue;
						}
					}

					if (empty($thisOrder['first_name']) && empty($thisOrder['last_name'])) {
						$nameParts = explode(" ", $thisOrder['full_name']);
						$thisOrder['first_name'] = array_shift($nameParts);
						$thisOrder['middle_name'] = (count($nameParts) > 1 ? array_shift($nameParts) : "");
						$thisOrder['last_name'] = trim(implode(" ", $nameParts));
					}

					$addressId = "";
					if (empty($thisOrder['contact_id'])) {
						// (client_id, responsible_user_id, private_access, title, first_name, middle_name, last_name, suffix, preferred_first_name, alternate_name, business_name, company_id, job_title, salutation, address_1, address_2, city, state, postal_code, country_id, latitude, longitude, validation_status, attention_line, timezone_id, email_address, web_page, date_created, contact_type_id, source_id, birthdate, image_id, deleted, hash_code, mailchimp_identifier, notes, version) "

						$nameValues = array(
							"first_name" => $thisOrder['first_name'],
							"middle_name" => $thisOrder['middle_name'],
							"last_name" => $thisOrder['last_name'],
							"address_1" => $thisOrder['address_1'],
							"address_2" => $thisOrder['address_2'],
							"city" => $thisOrder['city'],
							"state" => $thisOrder['state'],
							"postal_code" => $thisOrder['postal_code'],
							"email_address" => $thisOrder['email_address'],
							"country_id" => $this->iCountriesArray[$thisOrder['country']]);
						$contactDataTable = new DataTable("contacts");
						if (!$thisOrder['contact_id'] = $contactDataTable->saveRecord(array("name_values" => array_filter($nameValues)))) {
							$returnArray['error_message'] = $contactDataTable->getErrorMessage();
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							ajaxResponse($returnArray);
							break 2;
						}
						if (!empty($thisOrder['phone_number'])) {
							executeQuery("insert into phone_numbers (contact_id, phone_number, description) "
								. " values (?,?,'Primary')", $thisOrder['contact_id'], $thisOrder['phone_number']);
						}
					} else {
						// Add billing address if needed
						$contactRow = Contact::getContact($thisOrder['contact_id']);
                        if((!empty($thisOrder['address_1']) && $contactRow['address_1'] != $thisOrder['address_1']) ||
                            (!empty($thisOrder['postal_code']) && $contactRow['postal_code'] != $thisOrder['postal_code'])) {
							$insertSet = executeQuery("insert into addresses (contact_id, address_label, full_name, address_1, address_2, city, state, postal_code, country_id, email_address, phone_number) " .
								"values (?,?,?,?,?,?,?,?,?,?,?)", $thisRecord['contact_id'], "Billing Address", $thisOrder['full_name'], $thisOrder['address_1'], $thisOrder['address_2'],
								$thisOrder['city'], $thisOrder['state'], $thisOrder['postal_code'], $this->iCountriesArray[$thisOrder['country']], $thisOrder['email_address'], $thisOrder['phone_number']);
							$addressId = $insertSet['insert_id'];
						}
					}
					$thisOrder['order_time'] = date("Y-m-d H:i:s", strtotime($thisOrder['order_time']));
					$dateCompleted = ($_POST['mark_completed'] == "current_date" ? date('Y-m-d') : ($_POST['mark_completed'] == "order_date" ? $thisOrder['order_time'] : null));

                    if($addressId) {
                        $insertSet = executeQuery("insert into orders (client_id, order_id, order_number, contact_id, address_id, user_id, full_name, phone_number, order_time, "
                            . "shipping_charge, tax_charge, order_discount, purchase_order_number, ip_address, order_status_id, date_completed)  values (?,?,?,?,?,?,?,?,?, ?,?,?,?,?,?,?)",
                            $GLOBALS['gClientId'], $thisOrder['order_number'], $thisOrder['order_number'], $thisOrder['contact_id'], $addressId, $thisOrder['user_id'], $thisOrder['full_name'],
                            $thisOrder['phone_number'], $thisOrder['order_time'], $thisOrder['shipping_charge'] ?: 0, $thisOrder['tax_charge'] ?: 0, $thisOrder['order_discount'] ?: 0,
                            $thisOrder['purchase_order_number'], $thisOrder['ip_address'], $orderStatuses[$thisOrder['order_status']], $dateCompleted);
                    } else {
                        $insertSet = executeQuery("insert into orders (client_id, order_id, order_number, contact_id, user_id, full_name, phone_number, order_time, "
                            . "shipping_charge, tax_charge, order_discount, purchase_order_number, ip_address, order_status_id, date_completed)  values (?,?,?,?,?,?,?,?, ?,?,?,?,?,?,?)",
                            $GLOBALS['gClientId'], $thisOrder['order_number'], $thisOrder['order_number'], $thisOrder['contact_id'], $thisOrder['user_id'], $thisOrder['full_name'],
                            $thisOrder['phone_number'], $thisOrder['order_time'], $thisOrder['shipping_charge'] ?: 0, $thisOrder['tax_charge'] ?: 0, $thisOrder['order_discount'] ?: 0,
                            $thisOrder['purchase_order_number'], $thisOrder['ip_address'], $orderStatuses[$thisOrder['order_status']], $dateCompleted);
                    }
					if (!empty($insertSet['sql_error'])) {
						$returnArray['error_message'] = "Error saving order " . $thisOrder['order_number'] . ": " . $insertSet['sql_error'];
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						ajaxResponse($returnArray);
						break 2;
					} else {
						$orderId = $insertSet['insert_id'];
					}

					if (!empty($thisOrder['promotion_code'])) {
						$couponCode = trim(strtoupper($thisOrder['promotion_code']));
						if (array_key_exists($couponCode, $promotions)) {
							$promotionId = $promotions[$couponCode];
						} else {
							$insertSet = executeQuery("insert into promotions (client_id, promotion_code, description, start_date) "
								. " values (?, ?, 'Imported Promotion', ?)", $GLOBALS['gClientId'], $couponCode, $thisOrder['order_time']);
							if (!empty($insertSet['sql_error'])) {
								$returnArray['error_message'] = "Error saving promotion " . $couponCode . ": " . $insertSet['sql_error'];
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								ajaxResponse($returnArray);
								break 2;
							} else {
								$promotionId = $insertSet['insert_id'];
							}
							// Save promotion Id in case used again in later orders
							$promotions[$couponCode] = $promotionId;
						}
						executeQuery("insert into order_promotions (order_id, promotion_id) values (?,?)", $orderId, $promotionId);
					}
					foreach ($thisOrder['order_items'] as $thisItem) {
						if ($_POST['use_custom_product_for_products_not_found'] && $thisItem['product_id'] == $customProductId) {
							$newOrderItem = array('product_id' => $customProductId, 'description' => $thisItem['product_description'] ?: $thisItem['product_code']);
						} else {
							$newOrderItem = getMultipleFieldsFromId(array('product_id', 'description'), 'products', 'product_id', $thisItem['product_id']);
						}
						$insertSet = executeQuery("insert into order_items (order_id, product_id, description, quantity, sale_price) values (?,?,?,?,?)",
							$orderId, $newOrderItem['product_id'], $newOrderItem['description'], $thisItem['quantity'], $thisItem['sale_price']);
						if (!empty($insertSet['sql_error'])) {
							$returnArray['error_message'] = "Error saving order item " . $thisItem['product_code'] . ": " . $insertSet['sql_error'];
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							ajaxResponse($returnArray);
							break 2;
						}
					}
					$insertCount++;
					$insertSet = executeQuery("insert into csv_import_details (csv_import_id,primary_identifier) values (?,?)", $csvImportId, $orderId);
					if (!empty($insertSet['sql_error'])) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
						ajaxResponse($returnArray);
						break 2;
					}
				}

				$GLOBALS['gPrimaryDatabase']->commitTransaction();

				$returnArray['response'] = "<p>" . $insertCount . " Orders imported.</p>";
				$returnArray['response'] .= "<p>" . $existingSkipCount . " existing Orders skipped.</p>";
				if (!empty($_POST['skip_orders_products_not_found'])) {
					$returnArray['response'] .= "<p>" . $missingSkipCount . " Orders with missing products skipped.</p>";
				}
				ajaxResponse($returnArray);
				break;

		}

	}

	function addErrorMessage($errorMessage) {
		if (array_key_exists($errorMessage, $this->iErrorMessages)) {
			$this->iErrorMessages[$errorMessage]++;
		} else {
			$this->iErrorMessages[$errorMessage] = 1;
		}
	}

	function checkSqlError($resultSet, &$returnArray, $errorMessage = "") {
		if (!empty($resultSet['sql_error'])) {
			if ($this->iShowDetailedErrors) {
				$returnArray['error_message'] = $returnArray['import_error'] = $resultSet['sql_error'];
			} else {
				$returnArray['error_message'] = $returnArray['import_error'] = $errorMessage ?: getSystemMessage("basic", $resultSet['sql_error']);
			}
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			ajaxResponse($returnArray);
		}
	}

	function mainContent() {
		echo $this->iPageData['content'];
		?>
        <div id="_form_div">
            <form id="_edit_form" enctype='multipart/form-data'>

                <div class="basic-form-line" id="_description_row">
                    <label for="description" class="required-label">Description</label>
                    <input tabindex="10" class="validate[required]" size="40" type="text" id="description"
                           name="description">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_csv_file_row">
                    <label for="csv_file" class="required-label">CSV File</label>
                    <input tabindex="10" class="validate[required]" type="file" id="csv_file" name="csv_file">
					<a class="valid-fields-trigger" href="#"><span class="help-label">Click here to check Valid Fields</span></a>
					<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_mark_completed_row">
                    <span class="checkbox-label">Mark Orders completed:</span>
                    <input type="radio" checked tabindex="10" id="mark_completed_current" name="mark_completed" value="current_date"><label class="checkbox-label" for="mark_completed_current">Use current date</label>
                    <input type="radio" tabindex="10" id="mark_completed_order" name="mark_completed" value="order_date"><label class="checkbox-label" for="mark_completed_order">Use Order date</label>
                    <input type="radio" tabindex="10" id="mark_completed_none" name="mark_completed" value="none"><label class="checkbox-label" for="mark_completed_none">Do not complete</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_add_contacts_not_found_row">
                    <input type="checkbox" tabindex="10" id="add_contacts_not_found" name="add_contacts_not_found" value="1" checked><label
                            class="checkbox-label" for="add_contacts_not_found">Add Contacts not found (instead of
                        failing)</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_import_products_not_found_row">
                    <input type="checkbox" tabindex="10" id="import_products_not_found" name="import_products_not_found" value="1" checked><label
                            class="checkbox-label" for="import_products_not_found">Import Products not found from Coreware Catalog (instead of
                        failing) NOTE: Product imports can not be undone.</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_skip_orders_products_not_found_row">
                    <input type="checkbox" tabindex="10" id="skip_orders_products_not_found" name="skip_orders_products_not_found" value="1"><label
                            class="checkbox-label" for="skip_orders_products_not_found">Skip orders with products that cannot be found (instead of
                        failing)</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_use_custom_product_for_products_not_found_row">
                    <input type="checkbox" tabindex="10" id="use_custom_product_for_products_not_found" name="use_custom_product_for_products_not_found" value="1"><label
                            class="checkbox-label" for="use_custom_product_for_products_not_found">Replace products that cannot be found with a custom product (instead of
                        failing)</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_validate_only">
                    <input type="checkbox" tabindex="10" id="validate_only" name="validate_only" value="1"><label
                            class="checkbox-label" for="validate_only">Validate Data only (do not import)</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line">
                    <button tabindex="10" id="_submit_form">Import</button>
                    <div id="import_message"></div>
                </div>

                <div id="import_error"></div>

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
			$resultSet = executeQuery("select * from csv_imports where table_name = 'orders' and client_id = ? order by time_submitted desc", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$importCount = 0;
				$countSet = executeQuery("select count(*) from csv_import_details where csv_import_id = ?", $row['csv_import_id']);
				if ($countRow = getNextRow($countSet)) {
					$importCount = $countRow['count(*)'];
				}
				$minutesSince = (time() - strtotime($row['time_submitted'])) / 60;
				$canUndo = ($minutesSince < 120 || $GLOBALS['gDevelopmentServer']);
				?>
                <tr id="csv_import_id_<?= $row['csv_import_id'] ?>" class="import-row"
                    data-csv_import_id="<?= $row['csv_import_id'] ?>">
                    <td><?= htmlText($row['description']) ?></td>
                    <td><?= date("m/d/Y g:i a", strtotime($row['time_submitted'])) ?></td>
                    <td><?= getUserDisplayName($row['user_id']) ?></td>
                    <td><?= $importCount ?></td>
                    <td><?= ($canUndo ? "<span class='far fa-undo remove-import'></span>" : "") ?></td>
                </tr>
				<?php
			}
			?>
        </table>
		<?php
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", ".remove-import", function () {
                const csvImportId = $(this).closest("tr").data("csv_import_id");
                $('#_confirm_undo_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 400,
                    title: 'Remove Import',
                    buttons: {
                        Yes: function (event) {
                            loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=remove_import&csv_import_id=" + csvImportId, function (returnArray) {
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
            $(document).on("tap click", "#_submit_form", function () {
                const $submitForm = $("#_submit_form");
                const $editForm = $("#_edit_form");
                const $postIframe = $("#_post_iframe");
                if ($submitForm.data("disabled") === "true") {
                    return false;
                }
                if ($editForm.validationEngine("validate")) {
                    $("#import_error").html("");
                    disableButtons($submitForm);
                    $("body").addClass("waiting-for-ajax");
                    $editForm.attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=import_file").attr("method", "POST").attr("target", "post_iframe").submit();
                    $postIframe.off("load");
                    $postIframe.on("load", function () {
                        $("body").removeClass("no-waiting-for-ajax").removeClass("waiting-for-ajax");
                        const returnText = $(this).contents().find("body").html();
                        const returnArray = processReturn(returnText);
                        if (returnArray === false) {
                            enableButtons($("#_submit_form"));
                            return;
                        }
                        if ("import_error" in returnArray) {
                            $("#import_error").html(returnArray['import_error']);
                        }
                        if ("response" in returnArray) {
                            $("#_form_div").html(returnArray['response']);
                        }
                        enableButtons($submitForm);
                    });
                }
                return false;
            });
			$(document).on("tap click", ".valid-fields-trigger", function () {
				$("#_valid_fields_dialog").dialog({
					modal: true,
					resizable: true,
					width: 1000,
					title: 'Valid Fields',
					buttons: {
						Close: function (event) {
							$("#_valid_fields_dialog").dialog('close');
						}
					}
				});
			});
			$("#_valid_fields_dialog .accordion").accordion({
				active: false,
				heightStyle: "content",
				collapsible: true
			});
        </script>
		<?php
	}

	function internalCSS() {
		?>
		<style>
			#import_error {
				color: rgb(192, 0, 0);
			}

			.remove-import {
				cursor: pointer;
			}
			#_valid_fields_dialog .ui-accordion-content {
				max-height: 200px;
			}

			#_valid_fields_dialog > ul {
				columns: 3;
				padding-bottom: 1rem;
			}

			#_valid_fields_dialog .ui-accordion ul {
				columns: 2;
			}

			#_valid_fields_dialog ul li {
				padding-right: 20px;
			}
		</style>
		<?php
	}

	function hiddenElements() {
		?>
        <iframe id="_post_iframe" name="post_iframe"></iframe>

		<div id="_valid_fields_dialog" title="Valid Fields" class="dialog-box">
		<ul>
			<li><?= implode("</li><li>", array_merge($this->iValidOrderFields,$this->iValidOrderItemFields)) ?></li>
		</ul>

		<div class="accordion">
			<?php if (!empty($this->iContactIdentifierFields)) { ?>
				<h3>Valid Contact Identifiers</h3>
				<!-- Has an extra wrapper div since columns CSS property doesn't work properly with accordion content's max height -->
				<div>
					<ul>
						<li><?= implode("</li><li>", $this->iContactIdentifierFields) ?></li>
					</ul>
				</div>
			<?php } ?>
		</div>

        <div id="_confirm_undo_dialog" class="dialog-box">
            This will result in these orders being removed. Are you sure?
        </div> <!-- confirm_undo_dialog -->
		<?php
	}
}

$pageObject = new OrderHistoryCsvImportPage();
$pageObject->displayPage();
