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

$GLOBALS['gPageCode'] = "BACKGROUNDPROCESS";
$runEnvironment = php_sapi_name();
if ($runEnvironment == "cli") {
	require_once "shared/startup.inc";
} else {
	require_once "../shared/startup.inc";
}

if (!$GLOBALS['gUserRow']['superuser_flag'] && !$GLOBALS['gCommandLine']) {
	echo "ERROR: For security purposes, this program cannot be run from a browser - " . php_sapi_name() . ".\n";
	exit;
}

class ThisBackgroundProcess extends BackgroundProcess {
	function setProcessCode() {
		$this->iProcessCode = "update_crm_products";
	}

    private $iLogging = false;
	private $iLastMemory = 0;
    private $iLastLogTime = 0;

	function addResult($resultLine = "") {
        $timeNow = getMilliseconds();
        $timeElapsed = getTimeElapsed($this->iLastLogTime,$timeNow);
        if(strpos($resultLine, $timeElapsed) === false) {
            $resultLine .= " (" . $timeElapsed . ")";
        }
        $this->iLastLogTime = $timeNow;
        if ($GLOBALS['gDevelopmentServer'] || $this->iLogging) {
            $currentMemory = memory_get_usage() / 1000;
            $memoryChange = $currentMemory - $this->iLastMemory;
            $this->iLastMemory = $currentMemory;
            addDebugLog($resultLine . " Memory Used: " . number_format($currentMemory, 0, "", ",")
                . " KB Change: " . number_format($memoryChange, 0, "", ",") . " KB");
        }
        parent::addResult($resultLine);
    }

    function process() {
        $this->iLastLogTime = getMilliseconds();
        $this->iLogging = !empty(getPreference("LOG_CRM_PRODUCT_UPLOAD"));
        $clientSet = executeReadQuery("select * from contacts join clients using (contact_id) where inactive = 0");
		while ($clientRow = getNextRow($clientSet)) {
			changeClient($clientRow['client_id']);
            $productCount = getFieldFromId("count(*)", "products", "client_id", $GLOBALS['gClientId']);
            $memoryLimit = str_replace("M", "",ini_get("memory_limit"));
            $memoryEstimate = ceil($productCount / 100000) * 2048;
            if($memoryLimit < $memoryEstimate) {
                ini_set("memory_limit", $memoryEstimate . "M");
            }
			$zaiusApiKey = getPreference("ZAIUS_API_KEY");
			$zaiusUseUpc = getPreference("ZAIUS_USE_UPC");
			$useZaius = !empty($zaiusApiKey);

			$infusionSoftAccessToken = getPreference('INFUSIONSOFT_ACCESS_TOKEN');
			$useInfusionSoft = !empty($infusionSoftAccessToken);

			$yotpoAppKey = getPreference('YOTPO_APP_KEY');
			$yotpoSecretKey = getPreference('YOTPO_SECRET_KEY');
			$useYotpo = !empty($yotpoAppKey);

            $listrakClientId = getPreference('LISTRAK_CLIENT_ID');
            $listrakClientSecret = getPreference('LISTRAK_CLIENT_SECRET');
            $useListrak = !empty($listrakClientId);


            $batchSize = 10000;
			if ($useZaius || $useInfusionSoft || $useYotpo || $useListrak) {
                $sendInStock = $useZaius || $useListrak;
				$startTime = getMilliseconds();

				// Get last process runtime and only get products since then
				$lastStartTime = $this->iBackgroundProcessRow['last_start_time'];
                $inactiveProducts = array();

				$updateAllFlag = strtoupper($_GET['update_all']);
                // Do a full sync if requested or once per day to catch price changes
				if (date("Y-m-d") != date("Y-m-d", strtotime($lastStartTime)) || $updateAllFlag == $clientRow['client_code'] || $updateAllFlag == 'ALL') {
					$lastStartTime = '2000-01-01 00:00';
                    $inactiveSet = executeQuery("select * from products join product_data using (product_id) where products.client_id = ? and (inactive = 1 or products.product_id in (select product_id from product_category_links " .
                        " where product_category_id in (select product_category_id from product_categories where product_category_code in ('DISCONTINUED', 'INACTIVE'))))", $GLOBALS['gClientId']);
                    while($inactiveRow = getNextRow($inactiveSet)) {
                        unset($inactiveRow['detailed_description']);
                        $inactiveProducts[] = $inactiveRow;
                    }
				}
				// Get product IDs of updated products
				$changedSet = executeReadQuery("select product_id, time_changed from products where "
					. (empty($lastStartTime) ? "" : "time_changed >= '" . $lastStartTime . "' and ")
					. "products.client_id = ?", $clientRow['client_id']);
				$changedIds = array();
				while ($row = getNextRow($changedSet)) {
					$changedIds[$row['product_id']] = $row['time_changed'];
				}
				freeResult($changedSet);
				// Get inventory counts, current and at last run
				$productCatalog = new ProductCatalog();
				$inventoryCounts = $productCatalog->getInventoryCounts(true, array(), true);
				$lastInventoryFilename = $GLOBALS['gDocumentRoot'] . "/cache/lastInventoryCounts-" . $clientRow['client_code'] . ".json";
				$lastInventoryCounts = json_decode(file_get_contents($lastInventoryFilename), true);

                $updatedGroupId = getFieldFromId("search_group_id", "search_groups", "search_group_code", "CRM_UPDATED_PRODUCTS",
                    "client_id = ?", $GLOBALS['gClientId']);
                if(empty($updatedGroupId)) {
                    $resultSet = executeQuery("insert into search_groups (client_id, search_group_code, description, internal_use_only)" .
                        "values (?,'CRM_UPDATED_PRODUCTS','Updated Products for CRM upload', 1)", $GLOBALS['gClientId']);
                    $updatedGroupId = $resultSet['insert_id'];
                }
                $searchGroupIds = array($updatedGroupId);
                if($sendInStock) { // Zaius and Listrak track whether products are in stock or not.
                    $stockChangedGroupId = getFieldFromId("search_group_id", "search_groups", "search_group_code", "CRM_STOCK_CHANGE",
                        "client_id = ?", $GLOBALS['gClientId']);
                    if (empty($stockChangedGroupId)) {
                        $resultSet = executeQuery("insert into search_groups (client_id, search_group_code, description, internal_use_only)" .
                            "values (?,'CRM_STOCK_CHANGE','Products with in-stock status changed for CRM upload', 1)", $GLOBALS['gClientId']);
                        $stockChangedGroupId = $resultSet['insert_id'];
                    }
                    $searchGroupIds[] = $stockChangedGroupId;
                }
                $resultSet = executeQuery("select product_id from products where inactive = 0 and client_id = ?",$clientRow['client_id']);
                $insertCount = 0;
                $insertValues = "";
                while ($row = getNextRow($resultSet)) {
                    if(strlen($insertValues > 10000)) {
                        executeQuery("insert ignore into search_group_products (search_group_id, product_id) values ". $insertValues);
                        $insertValues = "";
                    }
                    $productId = $row['product_id'];
                    $row['is_in_stock'] = $inventoryCounts[$productId] > 0;
                    if (key_exists($productId, $changedIds)) { // Product data changed; update
                        $insertValues .= (empty($insertValues) ? "" : ",") . sprintf("(%s,%s)",$updatedGroupId,$productId);
                        $insertCount++;
                    } elseif($sendInStock) {
                        $wasInStock = $lastInventoryCounts[$productId] > 0;
                        if ($row['is_in_stock'] != $wasInStock) { // in-stock changed; update
                            $insertValues .= (empty($insertValues) ? "" : ",") . sprintf("(%s,%s)",$stockChangedGroupId,$productId);
                            $insertCount++;
                        }
                    }
                }
                if(!empty($insertValues)) {
                    executeQuery("insert ignore into search_group_products (search_group_id, product_id) values ". $insertValues);
                }
                // Get product categories
                $categoryResult = executeQuery("select * from product_categories where client_id = ?", $GLOBALS['gClientId']);
                $productCategories = array();
                while($categoryRow = getNextRow($categoryResult)) {
                    $productCategories[$categoryRow['product_category_id']] = $categoryRow['description'];
                }
                // Get product categories
                $tagResult = executeQuery("select * from product_tags where client_id = ?", $GLOBALS['gClientId']);
                $productTags = array();
                while($tagRow = getNextRow($tagResult)) {
                    $productTags[$tagRow['product_tag_id']] = $tagRow['description'];
                }

                freeResult($resultSet);

                $this->addResult($clientRow['client_code'] . " - " . $clientRow['business_name'] . ": Found " . $insertCount . " products changed"
                    . (empty($lastStartTime) ? "" : " since " . $lastStartTime));
                $totalProductCount = 0;
                $domainName = getDomainName();
                $productCatalog->setSearchGroups($searchGroupIds);
                $productCatalog->setGetProductSalePrice(true);
                $productCatalog->showOutOfStock(true);
                $productCatalog->setSendAllFields(true);
                $productCatalog->setSelectLimit(1000000);
                $updateArray = $productCatalog->getProducts();
                $this->addResult(count($updateArray) . " products prepared to send. Query details:\n" . $productCatalog->getQueryTime() );
                if(!empty($inactiveProducts)) {
                    $this->addResult(count($inactiveProducts) . " inactive or discontinued products prepared to send.");
                }
                foreach(array_keys($updateArray) as $thisIndex) {
                    $productId = $updateArray[$thisIndex]['product_id'];
                    if(array_key_exists($productId, $inventoryCounts)) {
                        $updateArray[$thisIndex]['inventory_count'] = $inventoryCounts[$productId];
                    } else {
                        $updateArray[$thisIndex]['inventory_count'] = 0;
                    }
                }
                $sentProductIds = array();

                if ($useZaius) {
					$this->addResult("Uploading products to Zaius for " . $clientRow['client_code'] . " - " . $clientRow['business_name']);
					// Update products with Zaius
					$zaius = new Zaius($zaiusApiKey);
                    $addFields = array(
                        array("name" => "product_url", "display_name" => "Product URL", "type" => "string"),
                        array("name" => "product_tags", "display_name" => "Product Tags", "type" => "string"),
                        array("name" => "is_in_stock", "display_name" => "Is In Stock", "type" => "boolean"));
                    $sendFacetCodes = explode(",", getPreference("ZAIUS_SEND_FACETS"));
                    $sendFacets = array();
                    if(!empty($sendFacetCodes)) {
                        foreach($sendFacetCodes as $thisFacetCode) {
                            $facetRow = getRowFromId("product_facets", "product_facet_code", trim($thisFacetCode));
                            if(!empty($facetRow)) {
                                $addFields[] = array("name"=>strtolower($facetRow['product_facet_code']),
                                    "display_name"=>$facetRow['description'], "type"=>"string");
                                $sendFacets[trim($thisFacetCode)] = $facetRow;
                            }
                        }
                    }

                    if(!$zaius->checkCustomFields("products", $addFields)) {
                        $this->addResult($clientRow['client_code'] . " - " . $clientRow['business_name'] . ": " . $zaius->getErrorMessage());
                    }
					$sendArray = array();

					foreach ($updateArray as $thisProduct) {
						if (count($sendArray) >= $batchSize) {
							$result = $zaius->postApi("objects/products", $sendArray);
							if ($result['status'] == '202') {
								$this->addResult(count($sendArray) . " products updated successfully.");
								$totalProductCount += count($sendArray);
								$sendArray = array();
							} elseif ($result === false) {
                                $this->addResult("Product updates failed. Response: " . getFirstPart($zaius->getErrorMessage(),5000));
								break;
							} else {
                                $this->addResult("Product updates failed. Response: " . getFirstPart(jsonEncode($result),5000));
								break;
							}
						}
						$productId = $thisProduct['product_id'];
                        $salePrice = str_replace(",","",$thisProduct['sale_price'] ?: $thisProduct['list_price']);
                        $salePrice = is_numeric($salePrice) ? number_format($salePrice, 2, ".", "") : 0;
                        $thisProduct['product_category'] = $productCategories[$thisProduct['product_category_id']];
                        if(!empty($thisProduct['product_tag_ids'])) {
                            foreach(explode(",",$thisProduct['product_tag_ids']) as $thisTagId) {
                                $thisProduct['product_tags'] .= (empty($thisProduct['product_tags']) ? "" : ",") . $productTags[$thisTagId];
                            }
                        }

						$sendProduct = array(
							"product_id" => (empty($zaiusUseUpc) || empty($thisProduct['upc_code']) ? $productId : $thisProduct['upc_code']),
							"name" => $thisProduct['description'],
							"brand" => $thisProduct['manufacturer_name'],
							"sku" => $thisProduct['product_code'],
							"upc" => $thisProduct['upc_code'],
							"image_url" => (startsWith($thisProduct['image_url'],"/") ? $domainName : "") . $thisProduct['image_url'],
							"product_url" => $domainName . $thisProduct['product_detail_link'],
							"category" => $thisProduct['product_category'],
							"product_tags" => $thisProduct['product_tags'],
							"is_in_stock" => $thisProduct['inventory_count'] > 0,
							"price" => $salePrice);
						if(!empty($sendFacets)) {
						    foreach($sendFacets as $thisFacet) {
						        $sendProduct[strtolower($thisFacet['product_facet_code'])] = getReadFieldFromId("facet_value", "product_facet_options", "product_facet_option_id",
							        getReadFieldFromId("product_facet_option_id", "product_facet_values", "product_id", $productId, "product_facet_id = ?", $thisFacet['product_facet_id']));
                            }
                        }
                        $sendArray[] = $sendProduct;
                        $sentProductIds[$productId] = $productId;
					}

					if (!empty($sendArray)) {
						$result = $zaius->postApi("objects/products", $sendArray);
						if ($result['status'] == '202') {
							$this->addResult(count($sendArray) . " products updated successfully.");
							$totalProductCount += count($sendArray);
						} elseif ($result === false) {
							$this->addResult("Product updates failed. Response: " . getFirstPart($zaius->getErrorMessage(),5000));
						} else {
							$this->addResult("Product updates failed. Response: " . getFirstPart(jsonEncode($result),5000));
						}
					}
					$endTime = getMilliseconds();
					$this->addResult($totalProductCount . " products updated taking " . getTimeElapsed($startTime, $endTime));
					$startTime = getMilliseconds();
					$totalProductCount = 0;
				}
				if ($useInfusionSoft) {
					$this->addResult("Uploading products to Infusionsoft for " . $clientRow['client_code'] . " - " . $clientRow['business_name']);
					$infusionSoft = new InfusionSoft($infusionSoftAccessToken);

					foreach ($updateArray as $thisProduct) {
						$productId = $thisProduct['product_id'];
                        $salePrice = str_replace(",","",$thisProduct['sale_price'] ?: $thisProduct['list_price']);
                        $salePrice = is_numeric($salePrice) ? number_format($salePrice, 2, ".", "") : 0;
						$result = $infusionSoft->updateProduct($productId, $salePrice, $thisProduct);
						if(!$result) {
						    $this->addResult($infusionSoft->getErrorMessage());
                        } else {
						    $totalProductCount++;
                        }
                        $sentProductIds[$productId] = $productId;
                    }
					$endTime = getMilliseconds();
					$this->addResult($totalProductCount . " products updated taking " . getTimeElapsed($startTime, $endTime));
                    $startTime = getMilliseconds();
                    $totalProductCount = 0;

                }
                if ($useListrak) {
                    $this->addResult("Uploading products to Listrak for " . $clientRow['client_code'] . " - " . $clientRow['business_name']);
                    $listrak = new Listrak($listrakClientId,$listrakClientSecret);

					if (function_exists("array_key_last")) {
						$lastKey = array_key_last($updateArray);
					} else {
						end($updateArray);
						$lastKey = key($updateArray);
					}
                    foreach ($updateArray as $key=>$thisProduct) {
                        $productId = $thisProduct['product_id'];
                        $salePrice = str_replace(",","",$thisProduct['sale_price'] ?: $thisProduct['list_price']);
                        $salePrice = is_numeric($salePrice) ? number_format($salePrice, 2, ".", "") : 0;
                        $thisProduct['link_url'] = $domainName . $thisProduct['product_detail_link'];
                        $thisProduct['product_category'] = $productCategories[$thisProduct['product_category_id']];

                        $result = $listrak->updateProduct($productId, $salePrice, $thisProduct, $key==$lastKey);
                        if(!$result) {
                            $this->addResult($listrak->getErrorMessage());
                        } else {
                            $totalProductCount++;
                        }
                        $sentProductIds[$productId] = $productId;
                    }
                    $endTime = getMilliseconds();
                    $this->addResult($totalProductCount . " products updated taking " . getTimeElapsed($startTime, $endTime));
                    $startTime = getMilliseconds();
                    $inactiveProductCount = 0;
                    if (function_exists("array_key_last")) {
                        $lastKey = array_key_last($inactiveProducts);
                    } else {
                        end($inactiveProducts);
                        $lastKey = key($inactiveProducts);
                    }
                    foreach($inactiveProducts as $key=>$inactiveProductRow) {
                        $result = $listrak->updateProduct($inactiveProductRow['product_id'], '', $inactiveProductRow, $key==$lastKey, true);
                        if(!$result) {
                            $this->addResult($listrak->getErrorMessage());
                        } else {
                            $inactiveProductCount++;
                        }
                    }
                    $endTime = getMilliseconds();
                    $this->addResult($inactiveProductCount . " products marked discontinued taking " . getTimeElapsed($startTime, $endTime));
                    $startTime = getMilliseconds();
                    $totalProductCount = 0;
                }
				if($useYotpo) {
                    $this->addResult("Uploading products to Yotpo for " . $clientRow['client_code'] . " - " . $clientRow['business_name']);
                    $yotpo = new Yotpo($yotpoAppKey, $yotpoSecretKey);

                    $sendArray = array();

                    $batchCount = 1;
                    $filePath = $GLOBALS['gDocumentRoot'] . "/cache/" . $clientRow['client_code'] . "-yotpo";
                    if(!is_dir($filePath)) {
                        mkdir($filePath);
                    }
                    $filenameTemplate = $filePath . "/batch-%batch_number%.json";
                    foreach ($updateArray as $thisProduct) {
                        if (count($sendArray) >= 300) {
                            $fileCounter = substr("000" . $batchCount++, -4);
                            $filename = str_replace('%batch_number%', $fileCounter, $filenameTemplate);
                            file_put_contents($filename, jsonEncode($sendArray));
                            $this->addResult(count($sendArray) . " products queued for update in " . $filename);
                            $totalProductCount += count($sendArray);
                            $sendArray = array();
                        }
                        $productId = $thisProduct['product_id'];
                        $salePrice = str_replace(",","",$thisProduct['sale_price'] ?: $thisProduct['list_price']);
                        $salePrice = is_numeric($salePrice) ? number_format($salePrice, 2, ".", "") : 0;

                        $thisProduct['upc_code'] = str_replace(" ", "_", $thisProduct['upc_code']);
                        $sendId = (empty($thisProduct['upc_code']) ? $thisProduct['product_code'] : $thisProduct['upc_code']);
                        $sku = str_replace(" ", "_", ($thisProduct['manufacturer_sku'] ?: $thisProduct['product_code']));
                        $sendArray[$sendId] = array(
                            "name" => $thisProduct['description'],
                            "url" => $domainName . $thisProduct['product_detail_link'],
                            "image_url" => (startsWith($thisProduct['image_url'],"/") ? $domainName : "") . $thisProduct['image_url'],
                            "description" => $thisProduct['detailed_description'],
                            "currency" => 'USD',
                            "price" => $salePrice,
                            "specs" => array(
                                "upc" => $thisProduct['upc_code'],
                                "brand" => $thisProduct['manufacturer_name'],
                                "sku" => $sku
                            ));
                        $sentProductIds[$productId] = $productId;
                    }

                    if (!empty($sendArray)) {
                        $fileCounter = substr("000" . $batchCount, -4);
                        $filename = str_replace('%batch_number%', $fileCounter, $filenameTemplate);
                        file_put_contents($filename, jsonEncode($sendArray));
                        $this->addResult(count($sendArray) . " products queued for update in " . $filename);
                        $totalProductCount += count($sendArray);
                    }
                    $count = $yotpo->massCreateProducts();
                    if($count > 0) {
                        $this->addResult("Update process initiated at Yotpo.");
                    } else {
                        $err = $yotpo->getErrorMessage();
                        if($err) {
                            $this->addResult("Error initiating update process at Yotpo: " . $err);
                        } else {
                            $this->addResult("Did not find any products to send to Yotpo.");
                        }
                    }
                    $endTime = getMilliseconds();
                    $this->addResult($totalProductCount . " products updated taking " . getTimeElapsed($startTime, $endTime));
                }

				// Persist inventory counts data to file for next run.
				file_put_contents($lastInventoryFilename, jsonEncode($inventoryCounts));

                $sentProductIdChunks = array_chunk($sentProductIds, 1000);
                foreach($sentProductIdChunks as $sentProductIdChunk) {
                    executeQuery("delete from search_group_products where search_group_id = ? and product_id in (" . implode(",",$sentProductIdChunk) . ")", $updatedGroupId);
                    if (!empty($stockChangedGroupId)) {
                        executeQuery("delete from search_group_products where search_group_id = ? and product_id in (" . implode(",", $sentProductIdChunk) . ")", $stockChangedGroupId);
                    }
                }
			} else {
				// No  API key set
				$this->addResult($clientRow['client_code'] . " - " . $clientRow['business_name'] . ": CRM not configured");
			}
		}
	}

}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
