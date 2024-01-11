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

$GLOBALS['gPageCode'] = "ORDERHISTORYJSONIMPORT";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 300000;

class OrderHistoryJsonImportPage extends Page {

	protected $iCountriesArray = array();
	protected $iStatesArray = array();

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
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to products: order_notes";
					ajaxResponse($returnArray);
					break;
				}

				$deleteSet = executeQuery("delete from order_items where order_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to products: order_items";
					ajaxResponse($returnArray);
					break;
				}

				$deleteSet = executeQuery("delete from order_promotions where order_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to products: order promotions";
					ajaxResponse($returnArray);
					break;
				}

				$deleteSet = executeQuery("delete from orders where order_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to products: orders";
					ajaxResponse($returnArray);
					break;
				}

				$deleteSet = executeQuery("delete from csv_import_details where csv_import_id = ?", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to products";
					ajaxResponse($returnArray);
					break;
				}

				$deleteSet = executeQuery("delete from csv_imports where csv_import_id = ?", $csvImportId);
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

			case "import_file":
				if (!array_key_exists("json_file", $_FILES)) {
					$returnArray['error_message'] = "No File uploaded";
					ajaxResponse($returnArray);
					break;
				}

				$fieldValue = file_get_contents($_FILES['json_file']['tmp_name']);
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
				$oldProductIdUpcs = array();
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
				if (array_key_exists("sku_file", $_FILES)) {
					$openFile = fopen($_FILES['sku_file']['tmp_name'], "r");
					$headers = fgetcsv($openFile);
					while ($row = fgetcsv($openFile)) {
						$productInfo = array_combine($headers, $row);
						if (!empty($productInfo['upc_code'])) {
							$codes = array($productInfo['sku'], $productInfo['source_sku']);
							$upcCode = $productInfo['upc_code'];
							if (!array_key_exists($upcCode, $GLOBALS['coreware_product_metadata']) &&
								!array_key_exists($upcCode, $upcProductIds)) {
								// Don't save UPCs that are not available for import
								continue;
							} else {
								// Make sure UPC matches exactly (no extra leading zeros)
								$upcCode = $GLOBALS['coreware_product_metadata'][$upcCode]['upc_code'];
							}
							foreach ($codes as $code) {
								if (!empty($code)) {
									if (!isset($productCodeUpcs[$code])) {
										$productCodeUpcs[$code] = $upcCode;
									} elseif ($productCodeUpcs[$code] == $upcCode) {
										continue;
									} elseif (is_array($productCodeUpcs[$code])) {
										$productCodeUpcs[$code][] = $upcCode;
									} else {
										$productCodeUpcs[$code] = array($productCodeUpcs[$code], $upcCode);
									}
								}
							}
							if (!empty($productInfo['old_product_id'])) {
								$oldProductIdUpcs[$productInfo['old_product_id']] = $upcCode;
							}
						}
					}
					fclose($openFile);
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
				$openFile = fopen($_FILES['json_file']['tmp_name'], "r");
				$lineNumber = 0;
				$errorMessage = "";
				$importArray = array();
				// JSON file will include unnecessary fields, many will be ignored.
				$dataRequiredFields = array('action', 'ts');
				$orderRequiredFields = array('order_id', 'total', 'email', 'first_name', 'last_name');
				$purchaseRequiredFields = array('bill_address');
				$itemRequiredFields = array('sku', 'quantity', 'price');
				$foundProducts = array();
				$missingProducts = array();
				$multiMatchProducts = array();
				$matchedProducts = array();
				$usedCustom = array();
				$importUpcs = array();
				$missingSkipCount = 0;
				while ($jsonData = fgets($openFile)) {
					$lineNumber++;
					$importRecord = json_decode($jsonData, true, 512, JSON_BIGINT_AS_STRING);
					if (empty($importRecord) || json_last_error() > 0) {
						$errorMessage .= "<p>Line " . $lineNumber . ": Data format is invalid: " . json_last_error_msg() . "</p>";
					}
					if ($importRecord['type'] !== "order") {
						continue;
					}
					foreach ($dataRequiredFields as $thisField) {
						if (!array_key_exists($thisField, $importRecord['data']) || empty($importRecord['data'][$thisField])) {
							$errorMessage .= "<p>Line " . $lineNumber . ": missing field: " . $thisField . "</p>";
						}
					}
					foreach ($orderRequiredFields as $thisField) {
						if (!array_key_exists($thisField, $importRecord['data']['order']) || empty($importRecord['data']['order'][$thisField])) {
							$errorMessage .= "<p>Line " . $lineNumber . ": missing field in order: " . $thisField . "</p>";
						}
					}
					if ($importRecord['data']['action'] == 'purchase') {
						foreach ($purchaseRequiredFields as $thisField) {
							if (!array_key_exists($thisField, $importRecord['data']['order']) || empty($importRecord['data']['order'][$thisField])) {
								$errorMessage .= "<p>Line " . $lineNumber . ": missing field in order: " . $thisField . "</p>";
							}
						}
					}
					foreach ($itemRequiredFields as $thisField) {
						foreach ($importRecord['data']['order']['items'] as $thisItem) {
							if (!array_key_exists($thisField, $thisItem) || empty($thisItem[$thisField])) {
								$errorMessage .= "<p>Line " . $lineNumber . ": missing field in items: " . $thisField . "</p>";
							}
						}
					}

					$orderNumber = $importRecord['data']['order']['order_id'];
					$contactEmail = $importRecord['data']['order']['email'];
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
					if (empty($contactId) && empty($_POST['add_contacts_not_found'])) {
						$errorMessage .= "<p>Line " . $lineNumber . "(Order " . $orderNumber . "): Contact Not found: " . $contactEmail . "</p>";
					} else {
						$importRecord['data']['order']['contact_id'] = $contactId;
						$importRecord['data']['order']['user_id'] = $userId;
					}
					foreach ($importRecord['data']['order']['items'] as $index => $thisItem) {
						$found = false;
						$foundInCatalog = false;
						$foundMultiple = false;
						$foundCustom = false;
						$foundUpc = "";
						if (!empty($thisItem['product_id']) && array_key_exists($thisItem['product_id'], $oldProductIdUpcs)) {
							$foundUpc = $oldProductIdUpcs[$thisItem['product_id']];
							$productId = $upcProductIds[$foundUpc];
							if (empty($productId)) {
								$importRecord['data']['order']['items'][$index]['upc_code'] = $foundUpc;
								$foundInCatalog = true;
							} else {
								$importRecord['data']['order']['items'][$index]['product_id'] = $productId;
								$found = true;
							}
						}
						$tries = 0;
						$productCode = $thisItem['sku'];
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
									$importRecord['data']['order']['items'][$index]['product_id'] = $customProductId;
									$foundCustom = true;
									$found = true;
								}
								break;
							}

							if (array_key_exists($productCode, $productCodes)) {
								$importRecord['data']['order']['items'][$index]['product_id'] = $productCodes[$productCode];
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
											$importRecord['data']['order']['items'][$index]['upc_code'] = $productCodeUpcs[$productCode];
										} else {
											$importRecord['data']['order']['items'][$index]['product_id'] = $productId;
											$found = true;
										}
									}
								}
							}
							$tries++;
						}

						if (!$found) {
							if ($foundInCatalog) {
								$matchedProducts[$productCode][] = $lineNumber;
								if (empty($_POST['import_products_not_found'])) {
									if (empty($_POST['skip_orders_products_not_found'])) {
										$errorMessage .= "<p>Line " . $lineNumber . "(Order " . $orderNumber . "): Product not found but available in Coreware Catalog: " . $productCode . " UPC " . $foundUpc . "</p>";
									} else {
										$missingSkipCount++;
										continue 2;
									}
								} else {
									$importUpcs[] = $foundUpc;
								}
							} elseif ($foundMultiple) {
								$multiMatchProducts[$productCode][] = $lineNumber;
								if (empty($_POST['skip_orders_products_not_found'])) {
									$errorMessage .= "<p>Line " . $lineNumber . "(Order " . $orderNumber . "): Product matches multiple UPCs: " . $productCode . " UPCs " . $foundUpc . "</p>";
								} else {
									$missingSkipCount++;
									continue 2;
								}
							} else {
								$missingProducts[$thisItem['sku']][] = $lineNumber;
								if (empty($_POST['skip_orders_products_not_found'])) {
									$errorMessage .= "<p>Line " . $lineNumber . "(Order " . $orderNumber . "): Product Not found: " . $thisItem['sku'] . "</p>";
								} else {
									$missingSkipCount++;
									continue 2;
								}
							}
						} else {
							if ($foundCustom) {
								$usedCustom[$thisItem['sku']][] = $lineNumber;
							} else {
								$foundProducts[$productCode][] = $lineNumber;
							}
						}
					}
					// Add data validation here as needed
					$importArray[] = $importRecord['data'];
				}

				if (!empty($errorMessage)) {
					$responseMessage = "";
					$responseMessage .= "<p>" . count($foundProducts) . " Products found.</p>";
					$responseMessage .= "<p>" . count($usedCustom) . " Products replaced with custom product.</p>";
					$responseMessage .= "<p>" . count($matchedProducts) . " Products not found but available in Coreware Catalog:</p>";
					foreach ($matchedProducts as $productCode => $lines) {
						$responseMessage .= "<p>" . $productCode . " on line(s) " . implode(",", $lines) . "</p>";
					}
					$responseMessage .= "<p>" . count($missingProducts) . " Products not found:</p>";
					foreach ($missingProducts as $productCode => $lines) {
						$responseMessage .= "<p>" . $productCode . " on line(s) " . implode(",", $lines) . "</p>";
					}
					$responseMessage .= "<p>" . count($multiMatchProducts) . " Products matching multiple UPCs:</p>";
					foreach ($multiMatchProducts as $productCode => $lines) {
						$responseMessage .= "<p>" . $productCode . " on line(s) " . implode(",", $lines) . "</p>";
					}
					$returnArray['import_error'] = $responseMessage;
					$returnArray['import_error'] .= $errorMessage;
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
					$returnArray['error_message'] = $returnArray['import_error'] = getSystemMessage("basic", $resultSet['sql_error']) . ($GLOBALS['gUserRow']['superuser_flag'] ? ": " . $resultSet['sql_error'] : "");
					ajaxResponse($returnArray);
					break;
				}
				$csvImportId = $resultSet['insert_id'];

				$insertCount = 0;
				$updateCount = 0;
				$existingSkipCount = 0;
				foreach ($importArray as $importRecord) {
					if ($importRecord['action'] == 'purchase') {
						$newOrder = array();
						// Make sure order doesn't already exist
						if (array_key_exists($importRecord['order']['order_id'], $existingOrders)) {
							$existingSkipCount++;
							continue;
						} else {
							$newOrder['order_number'] = $importRecord['order']['order_id'];
						}
						// If skipping orders with missing products, make sure all products exist
						if (!empty($_POST['skip_orders_products_not_found'])) {
							$productsExist = true;
							foreach ($importRecord['order']['items'] as $thisItem) {
								$productId = "";
								if (key_exists('upc_code', $thisItem)) {
									$productId = getFieldFromId('product_id', 'product_data', 'upc_code', $thisItem['upc_code']);
								}
								if (empty($productId)) {
									$productId = getFieldFromId('product_id', 'products', 'product_id', $thisItem['product_id']);
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

						$newOrder['user_id'] = $importRecord['order']['user_id'];
						if (empty($importRecord['order']['contact_id'])) {
							$billAddress = $this->splitAddress($importRecord['order']['bill_address']);

							$contactDataTable = new DataTable("contacts");
							if (!$importRecord['order']['contact_id'] = $contactDataTable->saveRecord(array("name_values" => array("first_name" => $importRecord['order']['first_name'], "last_name" => $importRecord['order']['last_name'],
								"address_1" => $billAddress['address_1'], "address_2" => $billAddress['address_2'], "city" => $billAddress['city'], "state" => $billAddress['state'],
								"postal_code" => $billAddress['postal_code'], "email_address" => $importRecord['order']['email'], "country_id" => $billAddress['country_id'])))) {
								$returnArray['error_message'] = $contactDataTable->getErrorMessage();
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								ajaxResponse($returnArray);
								break 2;
							}
							if (!empty($importRecord['order']['phone']) && $importRecord['order']['phone'] !== 'n/r') {
								executeQuery("insert into phone_numbers (contact_id, phone_number, description) "
									. " values (?,?,'Primary')", $importRecord['order']['contact_id'], $importRecord['order']['phone']);
							}
							if ($importRecord['order']['bill_address'] !== $importRecord['order']['ship_address']) {
								$shipAddress = $this->splitAddress($importRecord['order']['ship_address']);

								$insertSet = executeQuery("insert into addresses (contact_id, address_label, address_1, address_2, city, state, postal_code, country_id)"
									. " values (?,'Shipping',?,?,?,?,?,?)", $importRecord['order']['contact_id'], $shipAddress['address_1'], $shipAddress['address_2'],
									$shipAddress['city'], $shipAddress['state'], $shipAddress['postal_code'], $shipAddress['country_id']);
								if (!empty($insertSet['sql_error'])) {
									$returnArray['error_message'] = "Error saving shipping address " . $importRecord['order']['email'] . ": " . $insertSet['sql_error'];
									$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
									ajaxResponse($returnArray);
									break 2;
								} else {
									$shippingAddressId = $insertSet['insert_id'];
								}
							} else {
								$shippingAddressId = null;
							}
						}
						$newOrder['contact_id'] = $importRecord['order']['contact_id'];
						$newOrder['user_id'] = $importRecord['order']['user_id'] ?: null;
						$newOrder['full_name'] = $importRecord['order']['first_name'] . " " . $importRecord['order']['last_name'];
						if (!empty($importRecord['order']['phone']) && $importRecord['order']['phone'] !== 'n/r') {
							$newOrder['phone_number'] = $importRecord['order']['phone'];
						}
						$newOrder['order_time'] = date("Y-m-d H:i:s", $importRecord['ts']);
						$newOrder['shipping_charge'] = $importRecord['order']['shipping'] ?: 0;
						$newOrder['tax_charge'] = $importRecord['order']['tax'] ?: 0;
						$newOrder['order_discount'] = $importRecord['order']['discount'] ?: 0;
						$newOrder['ip_address'] = $importRecord['ip'];

						$insertSet = executeQuery("insert into orders (client_id, order_id, order_number, contact_id, user_id, address_id, full_name, phone_number, order_time, shipping_charge, tax_charge, order_discount, ip_address, date_completed) "
							. " values (?,?,?,?,?,?,?,?,?,?,?,?,?,?)", $GLOBALS['gClientId'], $newOrder['order_number'], $newOrder['order_number'], $newOrder['contact_id'], $newOrder['user_id'], $shippingAddressId, $newOrder['full_name'],
							$newOrder['phone_number'], $newOrder['order_time'], $newOrder['shipping_charge'], $newOrder['tax_charge'], $newOrder['order_discount'], $newOrder['ip_address'],
							(empty($_POST['mark_completed']) ? null : date('Y-m-d')));
						if (!empty($insertSet['sql_error'])) {
							$returnArray['error_message'] = "Error saving order " . $newOrder['order_number'] . ": " . $insertSet['sql_error'];
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							ajaxResponse($returnArray);
							break 2;
						} else {
							$orderId = $insertSet['insert_id'];
						}

						if (!empty($importRecord['order']['coupon_code'])) {
							$couponCode = trim(strtoupper($importRecord['order']['coupon_code']));
							if (array_key_exists($couponCode, $promotions)) {
								$promotionId = $promotions[$couponCode];
							} else {
								$insertSet = executeQuery("insert into promotions (client_id, promotion_code, description, start_date) "
									. " values (?, ?, 'Imported Promotion', ?)", $GLOBALS['gClientId'], $couponCode, $newOrder['order_time']);
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
						foreach ($importRecord['order']['items'] as $thisItem) {
							if ($_POST['use_custom_product_for_products_not_found'] && $thisItem['product_id'] == $customProductId) {
								$newOrderItem = array('product_id' => $customProductId, 'description' => $thisItem['sku']);
							} else {
								$newOrderItem = getMultipleFieldsFromId(array('product_id', 'description'), 'products', 'product_id', $thisItem['product_id']);
							}
							$insertSet = executeQuery("insert into order_items (order_id, product_id, description, quantity, sale_price) values (?,?,?,?,?)",
								$orderId, $newOrderItem['product_id'], $newOrderItem['description'], $thisItem['quantity'], $thisItem['price']);
							if (!empty($insertSet['sql_error'])) {
								$returnArray['error_message'] = "Error saving order item " . $thisItem['sku'] . ": " . $insertSet['sql_error'];
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
					} else {
						// deal with cancellations and refunds
						if ($importRecord['action'] == 'refund' || $importRecord['action'] == 'cancel') {
							$orderId = getFieldFromId("order_id", "orders", "order_number", $importRecord['order']['order_id']);
							if (empty($orderId)) {
								if (!empty($_POST['skip_orders_products_not_found'])) {
									$missingSkipCount++;
									continue;
								} else {
									$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
									$returnArray['error_message'] = "Order number " . $importRecord['order']['order_id'] . " not found to " . $importRecord['action'];
									ajaxResponse($returnArray);
									break 2;
								}
							}
							executeQuery("update orders set deleted = 1 where order_id = ?", $orderId);
							$orderNoteUserId = $GLOBALS['gUserId'];
							if (empty($orderNoteUserId)) {
								$orderNoteUserId = getFieldFromId("user_id", "users", "full_client_access", "1", "superuser_flag = 0");
							}
							executeQuery("insert into order_notes (order_id,user_id,time_submitted,content) values (?,?,now(),?)", $orderId, $orderNoteUserId,
								"Order marked as '" . $importRecord['action'] . "' in order history import.");
							$updateCount++;
							$insertSet = executeQuery("insert into csv_import_details (csv_import_id,primary_identifier) values (?,?)", $csvImportId, $orderId);
							if (!empty($insertSet['sql_error'])) {
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
								ajaxResponse($returnArray);
								break 2;
							}
						}
					}
				}

				$GLOBALS['gPrimaryDatabase']->commitTransaction();

				$returnArray['response'] = "<p>" . $insertCount . " Orders imported.</p>";
				$returnArray['response'] .= "<p>" . $updateCount . " Orders marked deleted.</p>";
				$returnArray['response'] .= "<p>" . $existingSkipCount . " existing Orders skipped.</p>";
				if (!empty($_POST['skip_orders_products_not_found'])) {
					$returnArray['response'] .= "<p>" . $missingSkipCount . " Orders with missing products skipped.</p>";
				}
				ajaxResponse($returnArray);
				break;

		}

	}

	function splitAddress($addressString) {
		$returnArray = array();
		$addressParts = array_reverse(explode(", ", $addressString));
		$countryCode = trim(array_shift($addressParts));
		$returnArray['country_id'] = $this->iCountriesArray[$countryCode];
		if (empty($countryId)) {
			$returnArray['country_id'] = $this->iCountriesArray['US'];
		}
		$returnArray['postal_code'] = trim(array_shift($addressParts));
		if (strlen($returnArray['postal_code']) > 10) { // Ignore bad data
			$returnArray['postal_code'] = "";
		}
		$stateName = trim(array_shift($addressParts));
		$returnArray['state'] = $this->iStatesArray[$stateName];
		$returnArray['city'] = substr(strip_tags(trim(array_shift($addressParts))), 0, 60);
		if (count($addressParts) > 1) {
			$returnArray['address_2'] = substr(strip_tags(trim(array_shift($addressParts))), 0, 60);
		} else {
			$returnArray['address_2'] = "";
		}
		$returnArray['address_1'] = substr(strip_tags(trim(implode(",", $addressParts))), 0, 60);

		return $returnArray;
	}

	function mainContent() {
		echo $this->iPageData['content'];
		?>
        <div id="_form_div">
            <form id="_edit_form" enctype='multipart/form-data'>

                <div class="basic-form-line" id="_json_file_row">
                    <label for="description" class="required-label">Description</label>
                    <input tabindex="10" class="validate[required]" size="40" type="text" id="description"
                           name="description">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_json_file_row">
                    <label for="json_file" class="required-label">JSON File</label>
                    <input tabindex="10" class="validate[required]" type="file" id="json_file" name="json_file">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_sku_file_row">
                    <label for="sku_file" class="required-label">SKU-UPC CSV File (if supplied, will be used to match products by UPC)</label>
                    <input tabindex="10" class="" type="file" id="sku_file" name="sku_file">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_mark_completed">
                    <input type="checkbox" tabindex="10" id="mark_completed" name="mark_completed" value="1" checked><label
                            class="checkbox-label" for="mark_completed">Mark imported orders as completed (using current date)</label>
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
				$canUndo = $minutesSince < 120;
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
                            loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=remove_import&csv_import_id=" + csvImportId, function(returnArray) {
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

        </style>
		<?php
	}

	function hiddenElements() {
		?>
        <iframe id="_post_iframe" name="post_iframe"></iframe>

        <div id="_confirm_undo_dialog" class="dialog-box">
            This will result in these orders being removed. Are you sure?
        </div> <!-- confirm_undo_dialog -->
		<?php
	}
}

$pageObject = new OrderHistoryJsonImportPage();
$pageObject->displayPage();
