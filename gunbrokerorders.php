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

$GLOBALS['gPageCode'] = "GUNBROKERORDERS";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 150000;

class GunBrokerOrdersPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_order_products":
				try {
					$gunBroker = new GunBroker();
				} catch (Exception $e) {
					$returnArray['error_message'] = "Unable to get orders from GunBroker. Make sure username & password are set and correct.";
					ajaxResponse($returnArray);
					break;
				}
				$gunBrokerOrderId = $_GET['gunbroker_order_id'];
				$orderData = $gunBroker->getOrder($gunBrokerOrderId);

				$itemCount = 0;
				ob_start();
				?>

                <form id="_select_products_form">
                    <h2>Select the products</h2>
                    <table class='grid-table' id="_select_products_table">
                        <tr>
                            <th>Product</th>
                            <th>Description</th>
                            <th>Sale Price</th>
                        </tr>
						<?php
						foreach ($orderData['items'] as $thisItem) {
							$itemCount++;
							$itemData = $gunBroker->getItemData($thisItem['itemID']);
							if (empty($itemData)) {
								$returnArray['error_message'] = "Unable to get product for '" . $thisItem['title'] . "': " . jsonEncode($thisItem);
								ajaxResponse($returnArray);
								break;
							}
							$productId = "";
							$itemData['upc'] = trim($itemData['upc']);
							if (empty($itemData['upc']) && !empty($itemData['gtin'])) {
								$itemData['upc'] = trim($itemData['gtin']);
							}
							if (!empty($itemData['upc'])) {
								$productId = getFieldFromId("product_id", "product_data", "upc_code", $itemData['upc']);
							}
							?>
                            <tr>
								<?php if (empty($productId)) { ?>
                                    <td><input class="" type="hidden" id="product_id_<?= $thisItem['itemID'] ?>" name="product_id_<?= $thisItem['itemID'] ?>" value=""><input autocomplete="chrome-off" tabindex="10" class="autocomplete-field validate[required]" type="text" size="50" name="product_id_<?= $thisItem['itemID'] ?>_autocomplete_text" id="product_id_<?= $thisItem['itemID'] ?>_autocomplete_text" data-autocomplete_tag="products">
								<?php } else { ?>
                                    <td>ID <?= $productId ?></td>
								<?php } ?>
                                <td><?= $thisItem['title'] ?></td>
                                <td class='align-right'><?= number_format($thisItem['itemPrice'], 2, ".", ",") ?></td>
                            </tr>
							<?php
						}
						?>
                    </table>
                </form>
				<?php
				if ($itemCount == 0) {
					$wasted = ob_get_clean();
					$returnArray['error_message'] = "Unable to get products from GunBroker";
				} else {
					$returnArray['select_products_dialog'] = ob_get_clean();
				}
				ajaxResponse($returnArray);
				break;
            case "save_preferences":
                if (!array_key_exists("mark_completed",$_POST)) {
	                $_POST['mark_completed'] = 0;
                }
	            $valuesArray = Page::getPagePreferences();
	            $valuesArray = array_merge($valuesArray, $_POST);
	            Page::setPagePreferences($valuesArray);
	            ajaxResponse($returnArray);
	            break;
			case "get_orders":
				try {
					$gunBroker = new GunBroker();
				} catch (Exception $e) {
					$returnArray['error_message'] = "Unable to get orders from GunBroker. Make sure username & password are set and correct.";
					ajaxResponse($returnArray);
					break;
				}
				$unpaidAlso = getPreference("GUNBROKER_CREATE_UNPAID_ORDERS");
				$sourceId = getFieldFromId("source_id", "sources", "source_code", "GUNBROKER");
				if (empty($sourceId)) {
					$insertSet = executeQuery("insert into sources (client_id,source_code,description,internal_use_only) values (?,?,?,1)", $GLOBALS['gClientId'], "GUNBROKER", "GunBroker");
					$sourceId = $insertSet['insert_id'];
				}
				$taxCollectedSourceId = getFieldFromId("source_id", "sources", "source_code", "GUNBROKER_WITH_TAXES");
				if (empty($taxCollectedSourceId)) {
					$insertSet = executeQuery("insert into sources (client_id,source_code,description,tax_exempt,internal_use_only) values (?,?,?,1,1)", $GLOBALS['gClientId'], "GUNBROKER_WITH_TAXES", "GunBroker With Taxes Already Collected");
					$taxCollectedSourceId = $insertSet['insert_id'];
				}
				$orderFilter = $_POST['order_filter'];
				if (!empty($orderFilter)) {
					$_POST['PageSize'] = 300;
				}

				if (!array_key_exists("mark_completed",$_POST)) {
					$_POST['mark_completed'] = 0;
				}
				$valuesArray = Page::getPagePreferences();
				$valuesArray = array_merge($valuesArray, $_POST);
				Page::setPagePreferences($valuesArray);
				unset($_POST['order_filter']);
				unset($_POST['order_status_id']);
				unset($_POST['set_products']);
				unset($_POST['mark_completed']);

				$orders = $gunBroker->getOrders($_POST);
                if(!is_array($orders)) {
                    $orders = array();
                }
				$returnArray['error_message'] = $gunBroker->getErrorMessage();
				ob_start();
				$noRemoveSalePrices = $this->getPageTextChunk("NO_REMOVE_SALE_PRICES");
				?>
                <p>Orders highlighted in red contain products with a lower price than sold for on GunBroker. If the checkbox below is checked, all sale prices (for any location) that are below the GunBroker sale price will be removed and a sale price equal to the GunBroker sale price will be added when the customer is emailed instructions to complete the order (though NOT when Coreware orders are simply created).</p>
                <p><input type='checkbox' value='1' id='remove_sale_prices'<?= (empty($noRemoveSalePrices) ? " checked" : "") ?>><label for='remove_sale_prices' class='checkbox-label'>Remove sale prices lower than purchase price and add sale price set to sale price.</label></p>
				<?php if (empty($orderFilter)) { ?>
                <p><?= count($orders) ?> order<?= (count($orders) == 1 ? "" : "s") ?> found</p>
			<?php } ?>
                <p>
                    <button id="email_all_customers">Email All</button>
                    <button id="create_all_orders">Create All Orders</button>
                </p>
                <table class='grid-table' id="orders_table">
                    <tr>
                        <th>Order Date</th>
                        <th>Customer Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>City</th>
                        <th>State</th>
                        <th>Status</th>
                        <th>GB ID</th>
                        <th>Items</th>
                        <th>Total Price</th>
                        <th>Paid</th>
                        <th>Order ID</th>
                        <th></th>
                    </tr>
					<?php
					$productCatalog = new ProductCatalog();
					$itemIdArray = array();
					$itemDataArray = array();
					foreach ($orders as $thisOrder) {
						foreach ($thisOrder['orderItemsCollection'] as $thisItem) {
							$itemIdArray[] = $thisItem['itemID'];
						}
					}
					if (!empty($itemIdArray)) {
						while (!empty($itemIdArray)) {
							if (count($itemIdArray) > 50) {
								$queryArray = array();
								while (count($queryArray) < 40) {
									$queryArray[] = array_shift($itemIdArray);
								}
							} else {
								$queryArray = $itemIdArray;
								$itemIdArray = array();
							}
							$itemData = $gunBroker->getItemData($queryArray);
							if (is_array($itemData)) {
								foreach ($itemData as $thisItem) {
									$itemDataArray[$thisItem['itemID']] = $thisItem;
								}
							}
						}
					}

					foreach ($orders as $thisOrder) {
						$belowPrice = false;
						$items = "";
						$itemsWithoutUpcFound = false;
						foreach ($thisOrder['orderItemsCollection'] as $thisItem) {
							if (array_key_exists($thisItem['itemID'], $itemDataArray)) {
								$itemData = $itemDataArray[$thisItem['itemID']];
							} else {
								$itemData = $gunBroker->getItemData($thisItem['itemID']);
							}
							$itemData['upc'] = trim($itemData['upc']);
							if (empty($itemData['upc']) && !empty($itemData['gtin'])) {
								$itemData['upc'] = trim($itemData['gtin']);
							}
							if (empty($itemData['upc'])) {
								$itemsWithoutUpcFound = true;
								$productId = "";
							} else {
								$productId = getFieldFromId("product_id", "product_data", "upc_code", $itemData['upc']);
								if (empty($productId)) {
									$productId = getFieldFromId("product_id", "product_data", "upc_code", $itemData['sku']);
								}
								if (empty($productId)) {
									$productId = getFieldFromId("product_id", "product_data", "manufacturer_sku", $itemData['sku']);
								}
							}
							if (empty($productId)) {
								$itemsWithoutUpcFound = true;
								$items .= (empty($items) ? "" : "<br>") . $thisItem['quantity'] . " of NO UPC - " . $thisItem['title'];
							} else {
								$salePriceInfo = $productCatalog->getProductSalePrice($productId, array("no_stored_prices" => true, "contact_type_id" => "", "user_type_id" => ""));
								$salePrice = $salePriceInfo['sale_price'];
								if ($salePrice < $thisItem['itemPrice']) {
									$belowPrice = true;
								}
								$items .= (empty($items) ? "" : "<br>") . $thisItem['quantity'] . " of <a target='_blank' href='/products?url_page=show&primary_id=" . $productId . "'>" . $itemData['upc'] . " - " . $thisItem['title'] . "</a>";
							}
						}

						$status = "";
						foreach ($thisOrder['status'] as $thisStatus) {
							$status .= (empty($status) ? "" : ", ") . $thisStatus;
						}
                        // GunBroker uses UTC; check for orders created 1 day before to catch orders where the timezone difference changes the date
						$orderIdDisplay = getFieldFromId("order_id", "orders", "source_id", $sourceId, "order_time > date_sub(?, interval 1 day) and purchase_order_number = ?", date("Y-m-d", strtotime($thisOrder['orderDate'])), $thisOrder['orderID']);
						if (empty($orderIdDisplay)) {
							$orderIdDisplay = getFieldFromId("order_id", "orders", "source_id", $taxCollectedSourceId, "order_time > ? and purchase_order_number = ?", date("Y-m-d", strtotime($thisOrder['orderDate'])), $thisOrder['orderID']);
						}
						if (!empty($orderIdDisplay) && canAccessPageCode("ORDERDASHBOARD")) {
							$orderIdDisplay = "<a href='/orderdashboard.php?url_page=show&primary_id=" . $orderIdDisplay . "&clear_filter=true' target='_blank'>" . $orderIdDisplay . "</a>";
						}
						$orderFilterType = "";
                        $createOrderDisplay = "";
						if (empty($orderIdDisplay)) {
							if (!$thisOrder['orderCancelled']) {
								if ($thisOrder['paymentReceived'] || $unpaidAlso) {
									if ($itemsWithoutUpcFound) {
										$orderIdDisplay = "<a href='#' class='select-products-create-order' data-gunbroker_order_id='" . $thisOrder['orderID'] . "'>Select Product & Create Order</a>";
										$orderFilterType = "product";
									} else {
										$orderIdDisplay = "<a href='#' class='create-order' data-gunbroker_order_id='" . $thisOrder['orderID'] . "'>Create Order</a>";
										$orderFilterType = "create";
										$createOrderDisplay = "<a href='#' class='enter-order' data-gunbroker_order_id='" . $thisOrder['orderID'] . "'>Enter Order</a>";
									}
								} else {
									$gunbrokerOrderCode = "GUNBROKER_CUSTOMER_" . $thisOrder['orderID'];
									$emailQueueId = getFieldFromId("email_queue_id", "email_queue", "client_id", $GLOBALS['gClientId'], "parameters like ?", "%" . $gunbrokerOrderCode . "%");
									if (empty($emailQueueId)) {
										$emailLogId = getFieldFromId("email_log_id", "email_log", "client_id", $GLOBALS['gClientId'], "time_submitted > ? and parameters like ?", date("Y-m-d", strtotime($thisOrder['orderDate'])), "%" . $gunbrokerOrderCode . "%");
									} else {
										$emailLogId = "";
									}

									$orderIdDisplay = "<a href='#' class='email-customer " . (empty($emailLogId) && empty($emailQueueId) ? "" : "email-again") . "' data-gunbroker_order_id='" . $thisOrder['orderID'] . "'>Email Customer</a>" .
										(empty($emailLogId) && empty($emailQueueId) ? "" : "<br><span class='red-text'>Email Already Sent</span>");
									$orderFilterType = (empty($emailLogId) && empty($emailQueueId) ? "email" : "sent");
								}
							}
						} else {
							$orderFilterType = "order";
						}
						if (empty($orderFilter) || $orderFilterType == $orderFilter) {
							?>
                            <tr<?= ($belowPrice ? " class='below-price'" : "") ?>>
                                <td><?= date("m/d/Y g:i a", strtotime($thisOrder['orderDate'])) ?></td>
                                <td><?= $thisOrder['billToName'] ?></td>
                                <td><?= $thisOrder['billToEmail'] ?></td>
                                <td><?= $thisOrder['billToPhone'] ?></td>
                                <td><?= $thisOrder['billToCity'] ?></td>
                                <td><?= $thisOrder['billToState'] ?></td>
                                <td><?= $status ?></td>
                                <td class='align-right'><?= $thisOrder['orderID'] ?></td>
                                <td><?= $items ?></td>
                                <td class='align-right'><?= number_format($thisOrder['orderTotal'], 2, ".", ",") ?></td>
                                <td class='align-center'><?= ($thisOrder['paymentReceived'] ? "YES" : "") ?></td>
                                <td id="gunbroker_order_id_<?= $thisOrder['orderID'] ?>" class='order-wrapper order-filter-<?= $orderFilterType ?>'><?= $orderIdDisplay ?></td>
                                <td class='create-order'><?= $createOrderDisplay ?></td>
                            </tr>
							<?php
						}
					}
					?>
                </table>
				<?php
				$returnArray['orders_wrapper'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
            case "enter_order":
	            $sourceId = getFieldFromId("source_id", "sources", "source_code", "GUNBROKER");
	            if (empty($sourceId)) {
		            $insertSet = executeQuery("insert into sources (client_id,source_code,description,internal_use_only) values (?,?,?,1)", $GLOBALS['gClientId'], "GUNBROKER", "GunBroker");
		            $sourceId = $insertSet['insert_id'];
	            }
	            $taxCollectedSourceId = getFieldFromId("source_id", "sources", "source_code", "GUNBROKER_WITH_TAXES");
	            if (empty($taxCollectedSourceId)) {
		            $insertSet = executeQuery("insert into sources (client_id,source_code,description,tax_exempt,internal_use_only) values (?,?,?,1,1)", $GLOBALS['gClientId'], "GUNBROKER_WITH_TAXES", "GunBroker With Taxes Already Collected");
		            $taxCollectedSourceId = $insertSet['insert_id'];
	            }
                $orderMethodId = getFieldFromId("order_method_id", "order_methods", "order_method_code", "GUNBROKER");
	            if (empty($orderMethodId)) {
		            $insertSet = executeQuery("insert into order_methods (client_id,order_method_code,description,internal_use_only) values (?,?,?,1)", $GLOBALS['gClientId'], "GUNBROKER", "GunBroker");
		            $orderMethodId = $insertSet['insert_id'];
	            }

                $gunBrokerOrderId = $_GET['gunbroker_order_id'];
	            try {
		            $gunBroker = new GunBroker();
	            } catch (Exception $e) {
		            $returnArray = "Unable to get orders from GunBroker. Make sure username & password are set and correct.";
                    ajaxResponse($returnArray);
                    break;
	            }
	            $orderData = $gunBroker->getOrder($gunBrokerOrderId);
	            $userContactInfo = $gunBroker->getUserContactInfo($orderData['buyer']['userID']);

	            $orderNote = "GunBroker order ID <a href='https://www.gunbroker.com/order?orderid=" . $orderData['orderID'] . "'>" . $orderData['orderID'] . "</a>";
	            $existingOrderId = getFieldFromId("order_id", "order_notes", "content", $orderNote);
	            if (!empty($existingOrderId)) {
		            $returnArray['error_message'] = "GunBroker order " . $orderData['orderID'] . " has already been created as order " . $existingOrderId . ".";
                    ajaxResponse($returnArray);
                    break;
	            }
	            if (!empty($orderData['salesTaxTotal'])) {
		            $sourceId = $taxCollectedSourceId;
                }
	            $_SESSION['create_order'] = array();
	            $_SESSION['create_order']['source_id'] = $sourceId;
	            $_SESSION['create_order']['order_method_code'] = "GUNBROKER";
	            $_SESSION['create_order']['order_note'] = $orderNote;

	            $GLOBALS['gPrimaryDatabase']->startTransaction();

	            $resultSet = executeQuery("select * from contacts where client_id = ? and email_address = ? and contact_id not in (select contact_id from accounts) and " .
		            "contact_id not in (select contact_id from donations) and contact_id not in (select contact_id from users)", $GLOBALS['gClientId'], $userContactInfo['email']);
	            if ($contactRow = getNextRow($resultSet)) {
		            $contactId = $contactRow['contact_id'];
	            }
	            if (empty($contactId)) {
		            $contactDataTable = new DataTable("contacts");
		            if (!$contactId = $contactDataTable->saveRecord(array("name_values" => array("first_name" => $userContactInfo['firstName'], "last_name" => $userContactInfo['lastName'],
			            "business_name" => $userContactInfo['companyName'], "address_1" => $userContactInfo['address1'], "address_2" => $userContactInfo['address2'],
			            "city" => $userContactInfo['city'], "state" => $userContactInfo['state'],
			            "postal_code" => $userContactInfo['postalCode'], "email_address" => $userContactInfo['email'], "source_id" => $sourceId)))) {
			            $GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			            $returnArray['error_message'] = $contactDataTable->getErrorMessage();
                        ajaxResponse($returnArray);
                        break;
		            }
		            $contactRow = Contact::getContact($contactId);
	            }
	            $_SESSION['create_order']['contact_id'] = $contactId;

                $federalFirearmsLicenseeId = "";
                if (!empty($orderData['fflNumber'])) {
                    $fflLookup = substr($orderData['fflNumber'], 0, 5) . substr($orderData['fflNumber'], -5);
	                $federalFirearmsLicenseeId = (new FFL(array("license_lookup"=>$fflLookup)))->getFieldData("federal_firearms_licensee_id");
                    if (empty($federalFirearmsLicenseeId)) {
                        $GLOBALS['gPrimaryDatabase']->logError("GunBroker FFL does not exist: " . $orderData['fflNumber']);
                    } else {
                        $_SESSION['create_order']['federal_firearms_licensee_id'] = $federalFirearmsLicenseeId;
                    }
                }
	            $addressId = false;
                // GunBroker puts FFL address into shipping address.  If FFL is specified, ignore shipping address
	            if (empty($federalFirearmsLicenseeId) && !empty($orderData['shipToAddress1'])) {
		            $userAddress = strtolower(str_replace(" ", "", implode(",", array($contactRow['address_1'], $contactRow['address_2'],
			            $contactRow['city'], $contactRow['state'], $contactRow['postal_code']))));
		            $orderAddress = strtolower(str_replace(" ", "", implode(",", array($orderData['shipToAddress1'], $orderData['shipToAddress2'],
			            $orderData['shipToCity'], $orderData['shipToState'], $orderData['shipToPostalCode']))));
		            if ($userAddress !== $orderAddress) {
			            $addressId = getFieldFromId("address_id", "addresses", "contact_id", $contactId,
				            "address_1 = ? and city = ? and state = ? and postal_code = ?",
				            $orderData['shipToAddress1'], $orderData['shipToCity'], $orderData['shipToState'], $orderData['shipToPostalCode']);
			            if (empty($addressId)) {
				            $resultSet = executeQuery("insert into addresses (contact_id, address_label, address_1, address_2, city, state, postal_code, country_id)"
					            . " values (?,?,?,?,?,?,?,?)", $contactId, "Shipping", $orderData['shipToAddress1'], $orderData['shipToAddress2'],
					            $orderData['shipToCity'], $orderData['shipToState'], $orderData['shipToPostalCode'], 1000);
				            if (!empty($resultSet['sql_error'])) {
					            $GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					            $returnArray['error_message'] = "Unable to create shipping Address";
					            ajaxResponse($returnArray);
					            break;
				            }
				            $addressId = $resultSet['insert_id'];
			            }
		            }
	            }
	            $_SESSION['create_order']['address_id'] = $addressId;

	            $phoneNumber = "";
	            if (!empty($userContactInfo['phone'])) {
		            $phoneNumber = formatPhoneNumber($userContactInfo['phone']);
		            $phoneNumberId = getFieldFromId("phone_number_id", "phone_numbers", "phone_number", $phoneNumber, "contact_id = ?", $contactId);
		            if (empty($phoneNumberId)) {
			            executeQuery("insert into phone_numbers (contact_id,phone_number,description) values (?,?,'Primary')", $contactId, $phoneNumber);
		            }
	            }

	            $_SESSION['create_order']['purchase_order_number'] = $orderData['orderID'];

                $shoppingCart = ShoppingCart::getShoppingCartForContact($contactId,"ORDERENTRY");
	            foreach ($orderData['items'] as $thisItem) {
		            $itemData = $gunBroker->getItemData($thisItem['itemID']);
		            if (empty($itemData)) {
			            $GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			            $returnArray['error_message'] = "Unable to identify products";
			            ajaxResponse($returnArray);
			            break;
		            }
		            $itemData['upc'] = trim($itemData['upc']);
		            if (empty($itemData['upc']) && !empty($itemData['gtin'])) {
			            $itemData['upc'] = trim($itemData['gtin']);
		            }
		            $productId = getFieldFromId("product_id", "product_data", "upc_code", $itemData['upc']);
		            if (empty($productId)) {
			            $GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			            $returnArray['error_message'] = "Unable to identify products";
			            ajaxResponse($returnArray);
			            break;
		            }
		            $orderItem = array("product_id" => $productId, "sale_price" => $thisItem['itemPrice'], "quantity" => $thisItem['quantity']);

                    $shoppingCart->addItem($orderItem);
	            }

	            $GLOBALS['gPrimaryDatabase']->commitTransaction();
                saveSessionData();

                ajaxResponse($returnArray);
                break;
			case "create_order":
				if (!array_key_exists("mark_completed",$_POST)) {
					$_POST['mark_completed'] = 0;
				}
				$valuesArray = Page::getPagePreferences();
				$valuesArray['mark_completed'] = $_POST['mark_completed'];
				$valuesArray['order_status_id'] = $_POST['order_status_id'];
				Page::setPagePreferences($valuesArray);

				$orderId = Order::createGunBrokerOrder($_GET['gunbroker_order_id'], $_POST);
				if (is_numeric($orderId)) {
					$returnArray['order_id'] = $orderId;
					if (canAccessPageCode("ORDERDASHBOARD")) {
						$returnArray['order_id'] = "<a href='/orderdashboard.php?url_page=show&primary_id=" . $orderId . "&clear_filter=true' target='_blank'>" . $orderId . "</a>";
					}
					$returnArray['gunbroker_order_id'] = $_GET['gunbroker_order_id'];
				} else {
					$returnArray['error_message'] = $orderId;
				}
				ajaxResponse($returnArray);
				break;
			case "email_customer":
				try {
					$gunBroker = new GunBroker();
				} catch (Exception $e) {
					$returnArray['error_message'] = "Unable to get orders from GunBroker. Make sure username & password are set and correct.";
					ajaxResponse($returnArray);
					break;
				}
				$gunBrokerOrderId = $_GET['gunbroker_order_id'];
				$orderData = $gunBroker->getOrder($gunBrokerOrderId);
				if ($orderData['paymentReceived']) {
					$returnArray['error_message'] = "Payment has already been received for this order";
					ajaxResponse($returnArray);
					break;
				}
				$userContactInfo = $gunBroker->getUserContactInfo($orderData['buyer']['userID']);
				if (empty($orderData['billToEmail'])) {
					$orderData['billToEmail'] = $userContactInfo['email'];
				}
				if (empty($orderData['billToEmail'])) {
					$returnArray['error_message'] = "Unable to email customer";
					ajaxResponse($returnArray);
					break;
				}
				$this->iDatabase->startTransaction();

				$productIdList = "";
				$productIdArray = array();
				$productRow = array();
				$productDataRow = array();
				$salePriceProductPriceTypeId = getFieldFromId("product_price_type_id", "product_price_types", "product_price_type_code", "SALE_PRICE");
				foreach ($orderData['items'] as $thisItem) {
					$itemData = $gunBroker->getItemData($thisItem['itemID']);
					if (empty($itemData)) {
						$returnArray['error_message'] = "Unable to get product for '" . $thisItem['title'] . "': " . jsonEncode($thisItem);
						$this->iDatabase->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
					$itemData['upc'] = trim($itemData['upc']);
					if (empty($itemData['upc']) && !empty($itemData['gtin'])) {
						$itemData['upc'] = trim($itemData['gtin']);
					}
					if (empty($itemData['upc'])) {
						$returnArray['error_message'] = "Product '" . $thisItem['title'] . "' in this order has no UPC";
						$this->iDatabase->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
					$productId = getFieldFromId("product_id", "product_data", "upc_code", $itemData['upc'], "product_id in (select product_id from products where inactive = 0 and internal_use_only = 0)");
					if (empty($productId)) {
						$returnArray['error_message'] = "Unable to get product for '" . $itemData['upc'] . "'";
						$this->iDatabase->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
					if (empty($productRow)) {
						$productRow = ProductCatalog::getCachedProductRow($productId);
						$productDataRow = getRowFromId("product_data", "product_id", $productId);
					}
					if (!empty($_GET['remove_sale_prices'])) {
						executeQuery("delete from product_prices where product_price_type_id = ? and price < ? and product_id = ?",
							$salePriceProductPriceTypeId, $thisItem['itemPrice'], $productId);
						$productPriceId = getFieldFromId("product_price_id", "product_prices", "product_id", $productId,
							"product_price_type_id = ? and start_date is null and end_date is null and location_id is null and sale_count is null and user_type_id is null and price >= ?", $salePriceProductPriceTypeId, $thisItem['itemPrice']);
						if (empty($productPriceId)) {
							executeQuery("insert into product_prices (product_id,product_price_type_id,price) values (?,?,?)", $productId, $salePriceProductPriceTypeId, $thisItem['itemPrice']);
						}
					}
					$productIdList .= (empty($productIdList) ? "" : "|") . $productId;
					$thisItem = array("product_id" => $productId, "sale_price" => $thisItem['itemPrice'], "quantity" => $thisItem['quantity']);
					$productIdArray[] = $thisItem;
				}

				$substitutions = array();
				$promotionCode = $substitutions['promotion_code'] = "GUNBROKER_" . strtoupper(getRandomString(24));
				$resultSet = executeQuery("insert into promotions (client_id,promotion_code,description,start_date,expiration_date,maximum_usages) values (?,?,?,current_date,date_add(current_date,interval 7 day),1)",
					$GLOBALS['gClientId'], $promotionCode, "GunBroker Sale");
				$promotionId = $resultSet['insert_id'];
				foreach ($productIdArray as $thisItem) {
					executeQuery("insert into promotion_rewards_products (promotion_id,product_id,maximum_quantity,amount) values (?,?,?,?)",
						$promotionId, $thisItem['product_id'], $thisItem['quantity'], $thisItem['sale_price']);
				}
				$domainName = getDomainName();
				$addToCartLink = $domainName . "/shopping-cart?product_id=" . $productIdList . "&promotion_code=" . $promotionCode;
				$emailAddress = $orderData['billToEmail'];
				$gunbrokerOrderCode = "GUNBROKER_CUSTOMER_" . $gunBrokerOrderId;
				$substitutions = array_merge($productRow, $productDataRow, array("promotion_code" => $promotionCode, "add_to_cart_link" => $addToCartLink, "first_name" => $userContactInfo['firstName'], "last_name" => $userContactInfo['lastName'],
					"email_address" => $emailAddress, "gunbroker_order_code" => $gunbrokerOrderCode));
				$emailId = getFieldFromId("email_id", "emails", "email_code", "GUNBROKER_CUSTOMER_CART", "inactive = 0");
				$subject = "Gunbroker purchase finalization";
				$body = "<p>Congratulations, %first_name%, for your purchase from Gunbroker!</p><p>Your purchase can be finalized by going to this link:</p><p><a href='%add_to_cart_link%'>%add_to_cart_link%</a></p><p>Shipment will take place shortly after completing the checkout process. You will need promotion code %promotion_code% to get the price you agreed to on GunBroker.</p>";
				sendEmail(array("email_address" => $emailAddress, "body" => $body, "subject" => $subject, "email_id" => $emailId, "substitutions" => $substitutions));
				$returnArray['info_message'] = "Email sent to " . $emailAddress;
				$this->iDatabase->commitTransaction();

				ajaxResponse($returnArray);

				break;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", "#email_all_customers", function () {
                if ($(".email-customer").not(".email-again").length > 0) {
                    $(this).data("email_all", true);
                    $(".email-customer").not(".email-again").first().trigger("click");
                }
                return false;
            });
            $(document).on("click", "#create_all_orders", function () {
                if ($(".create-order").length > 0) {
                    $(this).data("create_all", true);
                    $(".create-order").first().trigger("click");
                }
                return false;
            });
            $(".reload-field").change(function () {
                getOrders();
            });
            $(document).on("click","#mark_completed",function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_preferences", $("#_edit_form").serialize(), false, true);
            });
            $(document).on("click", ".select-products-create-order", function () {
                const gunbrokerOrderId = $(this).data("gunbroker_order_id");
                const $createCell = $(this).closest("td");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_order_products&gunbroker_order_id=" + gunbrokerOrderId, function(returnArray) {
                    $("#_select_products_dialog").html(returnArray['select_products_dialog']);
                    $("#_select_products_dialog").dialog({
                        closeOnEscape: true,
                        draggable: false,
                        modal: true,
                        resizable: false,
                        position: {my: "center top", at: "center top+100px", of: window, collision: "none"},
                        width: 1200,
                        title: 'Select Products',
                        buttons: {
                            "Create Order": function (event) {
                                if ($("#_select_products_form").validationEngine('validate')) {
                                    $("#set_products").val("1");
                                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_order&gunbroker_order_id=" + gunbrokerOrderId, $("#_edit_form,#_select_products_form").serialize(), function(returnArray) {
                                        $createCell.html("");
                                        if ("gunbroker_order_id" in returnArray) {
                                            $("#gunbroker_order_id_" + returnArray['gunbroker_order_id']).html(returnArray['order_id']);
                                        }
                                        $("#_select_products_dialog").dialog('close');
                                    });
                                }
                            },
                            Cancel: function (event) {
                                $("#_select_products_dialog").dialog('close');
                            }
                        }
                    });
                });
                return false;
            });
            $(document).on("click", ".create-order", function () {
                const gunbrokerOrderId = $(this).data("gunbroker_order_id");
                const $createCell = $(this).closest("td");
                $("#set_products").val("");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_order&gunbroker_order_id=" + gunbrokerOrderId, $("#_edit_form").serialize(), function(returnArray) {
                    $createCell.html("");
                    if ("gunbroker_order_id" in returnArray) {
                        $("#gunbroker_order_id_" + returnArray['gunbroker_order_id']).html(returnArray['order_id']);
                    }
                    if (!empty($("#create_all_orders").data("create_all")) && $(".create-order").length > 0) {
                        setTimeout(function () {
                            $(".create-order").first().trigger("click");
                        }, 200);
                    } else {
                        $("#create_all_orders").removeData("create_all");
                    }
                });
                return false;
            });
            $(document).on("click", ".enter-order", function () {
                const gunbrokerOrderId = $(this).data("gunbroker_order_id");
                const $createCell = $(this).closest("td");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=enter_order&gunbroker_order_id=" + gunbrokerOrderId, function(returnArray) {
                    if (!("error_message" in returnArray)) {
                        $createCell.html("");
                        $createCell.closest("tr").find(".order-wrapper").html("");
                        window.open("/orderentry.php");
                    }
                });
                return false;
            });
            $(document).on("click", ".email-customer", function () {
                const gunbrokerOrderId = $(this).data("gunbroker_order_id");
                const $emailCell = $(this).closest("td");
                $("#set_products").val("");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=email_customer&gunbroker_order_id=" + gunbrokerOrderId + "&remove_sale_prices=" + ($("#remove_sale_prices").prop("checked") ? 1 : 0), $("#_edit_form").serialize(), function(returnArray) {
                    $emailCell.html("<span class='red-text'>Email Already Sent</span>");
                    if (!empty($("#email_all_customers").data("email_all")) && $(".email-customer").not(".email-again").length > 0) {
                        setTimeout(function () {
                            $(".email-customer").not(".email-again").first().trigger("click");
                        }, 200);
                    } else {
                        $("#email_all_customers").removeData("email_all");
                    }
                });
                return false;
            });
            getOrders();
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function getOrders() {
                $("#orders_wrapper").html("<p>Loading...</p>");
                $("#set_products").val("");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_orders", $("#_edit_form").serialize(), function(returnArray) {
                    if ("orders_wrapper" in returnArray) {
                        $("#orders_wrapper").html(returnArray['orders_wrapper']);
                    }
                });
            }
        </script>
		<?php
	}

	function mainContent() {
		echo $this->iPageData['content'];
		$valuesArray = Page::getPagePreferences();
		?>
        <form id="_edit_form">
            <input type='hidden' id="set_products" name="set_products" value="">
            <div class="basic-form-line inline-block">
                <label>Number per Page</label>
                <select class="reload-field" id="page_size" name="PageSize">
                    <option<?= ($valuesArray['PageSize'] == "25" ? " selected" : "") ?> value="25" selected>25</option>
                    <option<?= ($valuesArray['PageSize'] == "50" ? " selected" : "") ?> value="50">50</option>
                    <option<?= ($valuesArray['PageSize'] == "100" ? " selected" : "") ?> value="100">100</option>
                    <option<?= ($valuesArray['PageSize'] == "200" ? " selected" : "") ?> value="200">200</option>
                    <option<?= ($valuesArray['PageSize'] == "300" ? " selected" : "") ?> value="300">300</option>
                </select>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>

            <div class="basic-form-line inline-block">
                <label>Page Number</label>
                <select class="reload-field" id="page_index" name="PageIndex">
					<?php
					for ($x = 1; $x <= 20; $x++) {
						echo "<option value='" . $x . "'>" . $x . "</option>";
					}
					?>
                </select>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>

            <div class="basic-form-line inline-block">
                <label>Filter</label>
                <select class="reload-field" id="order_filter" name="order_filter">
                    <option value=''>[ALL]</option>
                    <option value='email'>Ready to email customer</option>
                    <option value='sent'>Email already sent</option>
                    <option value='create'>Order ready to be created</option>
                    <option value='product'>Product needs to be set</option>
                    <option value='order'>Order Already Created</option>
                </select>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>

            <div class='clear-div'></div>

            <div class="basic-form-line inline-block">
                <label>Order Status</label>
                <select class="reload-field" id="order_status" name="OrderStatus">
                    <option value="0" selected>All</option>
                    <option value="1">Pending Seller Review</option>
                    <option value="2">Pending Buyer Confirmation</option>
                    <option value="3">Pending Payment Received</option>
                    <option value="4">Pending Shipment</option>
                    <option value="5">Complete</option>
                    <option value="6">Cancelled</option>
                    <option value="7">Pending Buyer Review</option>
                </select>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>

            <div class="basic-form-line inline-block">
                <label>Time Frame</label>
                <select class="reload-field" id="time_frame" name="TimeFrame">
                    <option value="1">Last 24 Hours</option>
                    <option value="2">Last 48 Hours</option>
                    <option value="3" selected>Last Week</option>
                    <option value="4">Last 2 Weeks</option>
                    <option value="5">Last 30 days</option>
                    <option value="6">Last 60 Days</option>
                    <option value="7">Last 90 Days</option>
                </select>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>

            <h3>When creating a Coreware Order, use the following:</h3>

            <div class="basic-form-line">
                <input type='checkbox' id="mark_completed" name="mark_completed" value='1'<?= (!array_key_exists("mark_completed",$valuesArray) || !empty($valuesArray['mark_completed']) ? " checked" : "") ?>><label class='checkbox-label' for='mark_completed'>Mark Order Completed</label>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>

            <div class="basic-form-line">
                <label>Set Order Status</label>
                <select id="order_status_id" name="order_status_id">
                    <option value=''>None</option>
					<?php
					$resultSet = executeQuery("select * from order_status where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						?>
                        <option<?= ($row['order_status_id'] == $valuesArray['order_status_id'] ? " selected" : "") ?> value='<?= $row['order_status_id'] ?>'><?= htmlText($row['description']) ?></option>
						<?php
					}
					?>
                </select>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
        </form>

        <p>Coreware orders can be created when GunBroker orders are complete. Orders with items without a UPC or not found in your catalog cannot be created in Coreware without setting the UPC to a valid product. For orders that have not had payment received through GunBroker, the customer can be emailed a link to add the product to their shopping cart and checkout on your site.</p>
        <p>If the customer is emailed a link to complete the order through your website, the cart will show the lower of the current price for the product on your website and the price the customer got at GunBroker. Again, IF the current price of the product on your website is LOWER than the price the customer purchased the product for on GunBroker, they will get the lower price.</p>
        <div id="orders_wrapper">
        </div>
		<?php
		return true;
	}

	function internalCSS() {
		?>
        <style>
            #orders_wrapper {
                margin: 40px auto;
            }

            #orders_table th {
                font-size: .7rem;
            }

            #orders_table td {
                font-size: .7rem;
            }

            tr.below-price td {
                background-color: rgb(250, 200, 200);
            }
        </style>
		<?php
	}

	function hiddenElements() {
		?>
        <div class='dialog-box' id='_select_products_dialog'>
        </div>
		<?php
	}

}

$pageObject = new GunBrokerOrdersPage();
$pageObject->displayPage();
