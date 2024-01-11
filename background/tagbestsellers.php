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
		$this->iProcessCode = "tag_best_sellers";
	}

	function process() {
		$parameters = array("connection_key" => "760C0DCAB2BD193B585EB9734F34B3B6");
		$hostUrl = "https://shootingsports.coreware.com/api.php?action=get_top_product_data";
		$postParameters = "";
		foreach ($parameters as $parameterKey => $parameterValue) {
			$postParameters .= (empty($postParameters) ? "" : "&") . $parameterKey . "=" . rawurlencode($parameterValue);
		}
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postParameters);
		curl_setopt($ch, CURLOPT_URL, $hostUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $GLOBALS['gCurlTimeout']);
		curl_setopt($ch, CURLOPT_TIMEOUT, $GLOBALS['gCurlTimeout']);
		$response = curl_exec($ch);
		$topProductData = json_decode($response, true)['top_products'];

		$clientSet = executeQuery("select * from contacts join clients using (contact_id) where inactive = 0");
		while ($clientRow = getNextRow($clientSet)) {
			$this->addResult($clientRow['client_code'] . " - " . $clientRow['business_name']);
			changeClient($clientRow['client_id']);

			$departments = array();
			$resultSet = executeQuery("select * from product_departments where client_id = ?", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$departments[$row['department_id']] = 0;
			}

			$bestSellerLimit = getPreference("RETAIL_STORE_BEST_SELLER_LIMIT");
			if (empty($bestSellerLimit) || !is_numeric($bestSellerLimit)) {
				$bestSellerLimit = 50;
			}

			$departmentLimit = getPreference("RETAIL_STORE_BEST_SELLER_DEPARTMENT_LIMIT");
			if (empty($departmentLimit) || !is_numeric($departmentLimit)) {
				$departmentLimit = 10;
			}

			# Best Sellers By Sales

			$productTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "BEST_SELLERS_BY_SALES");
			if (!empty($productTagId)) {
			    $beforeProductIds = $this->getTaggedProductIds($productTagId);
				executeQuery("delete from product_tag_links where product_tag_id = ?", $productTagId);
				$resultSet = executeQuery("select product_id,sum(quantity) from order_items where deleted = 0 and order_id in (select order_id from orders where " .
					"deleted = 0 and order_id = order_items.order_id and order_time > date_sub(current_date,interval 30 day)) and " .
					"product_id in (select product_id from products where client_id = ? and inactive = 0 and virtual_product = 0) group by product_id " .
					"having sum(quantity) > 1 order by sum(quantity) desc limit " . $bestSellerLimit, $GLOBALS['gClientId']);
				$sequenceNumber = 0;
				while ($row = getNextRow($resultSet)) {
					foreach ($departments as $departmentId => $quantity) {
						if (ProductCatalog::productIsInDepartment($row['product_id'], $departmentId)) {
							if ($quantity >= $departmentLimit) {
								continue 2;
							}
							$departments[$departmentId]++;
							break;
						}
					}
					$sequenceNumber++;
					executeQuery("insert into product_tag_links (product_id,product_tag_id,sequence_number) values (?,?,?)", $row['product_id'], $productTagId, $sequenceNumber);
				}
				$afterProductIds = $this->getTaggedProductIds($productTagId);
				$this->markProductsChanged($beforeProductIds,$afterProductIds);
				$this->addResult($sequenceNumber . " best seller products created for client '" . $clientRow['business_name'] . "'");
			}

			# Best Sellers By Coreware Sales

			foreach ($departments as $departmentId => $quantity) {
				$departments[$departmentId] = 0;
			}

			$productTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "BEST_SELLERS_BY_COREWARE_SALES");
			if (!empty($productTagId)) {
                $beforeProductIds = $this->getTaggedProductIds($productTagId);
                executeQuery("delete from product_tag_links where product_tag_id = ?", $productTagId);
				$sequenceNumber = 0;
				foreach ($topProductData['sales'] as $upcCode => $quantity) {
					$productId = getFieldFromId("product_id", "product_data", "upc_code", $upcCode);
					if (empty($productId)) {
						continue;
					}
					foreach ($departments as $departmentId => $quantity) {
						if (ProductCatalog::productIsInDepartment($productId, $departmentId)) {
							if ($quantity >= $departmentLimit) {
								continue 2;
							}
							$departments[$departmentId]++;
							break;
						}
					}
					$sequenceNumber++;
					executeQuery("insert into product_tag_links (product_id,product_tag_id,sequence_number) values (?,?,?)", $productId, $productTagId, $sequenceNumber);
					if ($sequenceNumber >= $bestSellerLimit) {
						break;
					}
				}
                $afterProductIds = $this->getTaggedProductIds($productTagId);
                $this->markProductsChanged($beforeProductIds,$afterProductIds);
                $this->addResult($sequenceNumber . " all Coreware best seller products created for client '" . $clientRow['business_name'] . "'");
			}

			# Trending Products

			foreach ($departments as $departmentId => $quantity) {
				$departments[$departmentId] = 0;
			}

			$productTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "TRENDING_PRODUCTS");
			if (!empty($productTagId)) {
			    $beforeProductIds = $this->getTaggedProductIds($productTagId);
				executeQuery("delete from product_tag_links where product_tag_id = ?", $productTagId);
				$sequenceNumber = 0;
				foreach ($topProductData['trending'] as $upcCode => $quantity) {
					$productId = getFieldFromId("product_id", "product_data", "upc_code", $upcCode);
					if (empty($productId)) {
						continue;
					}
					foreach ($departments as $departmentId => $quantity) {
						if (ProductCatalog::productIsInDepartment($productId, $departmentId)) {
							if ($quantity >= $departmentLimit) {
								continue 2;
							}
							$departments[$departmentId]++;
							break;
						}
					}
					$sequenceNumber++;
					executeQuery("insert into product_tag_links (product_id,product_tag_id,sequence_number) values (?,?,?)", $productId, $productTagId, $sequenceNumber);
					if ($sequenceNumber >= $bestSellerLimit) {
						break;
					}
				}
                $afterProductIds = $this->getTaggedProductIds($productTagId);
                $this->markProductsChanged($beforeProductIds,$afterProductIds);
                $this->addResult($sequenceNumber . " trending products created for client '" . $clientRow['business_name'] . "'");
			}

			# Most Viewed

			$productTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "MOST_VIEWED");
			if (!empty($productTagId)) {
			    $beforeProductIds = $this->getTaggedProductIds($productTagId);
				executeQuery("delete from product_tag_links where product_tag_id = ?", $productTagId);
				$resultSet = executeQuery("select product_id,count(*) from product_view_log where log_time > date_sub(current_date,interval 30 day) having count(*) > 1 order by count(*) desc limit 100");
				$sequenceNumber = 0;
				while ($row = getNextRow($resultSet)) {
					$sequenceNumber++;
					executeQuery("insert into product_tag_links (product_id,product_tag_id,sequence_number) values (?,?,?)", $row['product_id'], $productTagId, $sequenceNumber);
				}
                $afterProductIds = $this->getTaggedProductIds($productTagId);
                $this->markProductsChanged($beforeProductIds,$afterProductIds);
                $this->addResult($sequenceNumber . " most viewed products created for client '" . $clientRow['business_name'] . "'");
			}

			# Most Viewed in All Coreware

			$productTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "MOST_VIEWED_IN_ALL_COREWARE");
			if (!empty($productTagId)) {
                $beforeProductIds = $this->getTaggedProductIds($productTagId);
                executeQuery("delete from product_tag_links where product_tag_id = ?", $productTagId);
				$sequenceNumber = 0;
				foreach ($topProductData['views'] as $upcCode => $quantity) {
					$productId = getFieldFromId("product_id", "product_data", "upc_code", $upcCode);
					if (empty($productId)) {
						continue;
					}
					$sequenceNumber++;
					executeQuery("insert into product_tag_links (product_id,product_tag_id,sequence_number) values (?,?,?)", $productId, $productTagId, $sequenceNumber);
					if ($sequenceNumber >= $bestSellerLimit) {
						break;
					}
				}
                $afterProductIds = $this->getTaggedProductIds($productTagId);
                $this->markProductsChanged($beforeProductIds,$afterProductIds);
                $this->addResult($sequenceNumber . " all Coreware most viewed products created for client '" . $clientRow['business_name'] . "'");
			}

			# Most Searched

			$productTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "MOST_SEARCHED");
			if (!empty($productTagId)) {
			    $beforeProductIds = $this->getTaggedProductIds($productTagId);
				executeQuery("delete from product_tag_links where product_tag_id = ?", $productTagId);
				$productValues = array();
				$sequenceNumber = 0;
				$resultSet = executeQuery("select * from search_terms where client_id = ? order by use_count desc limit 50", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$words = ProductCatalog::getSearchWords($row['search_term'])['search_words'];
					foreach ($words as $thisWord) {
						$wordSet = executeQuery("select * from product_search_word_values where product_id in (select product_id from products where client_id = ? and inactive = 0) and " .
							"product_search_word_id in (select product_search_word_id from product_search_words where client_id = ? and search_term = ?)", $GLOBALS['gClientId'], $GLOBALS['gClientId'], $thisWord);
						while ($wordRow = getNextRow($wordSet)) {
							if (!array_key_exists($wordRow['product_id'], $productValues)) {
								$productValues[$wordRow['product_id']] = 0;
							}
							$productValues[$wordRow['product_id']] += $wordRow['search_value'];
						}
					}
				}
				uasort($productValues, array($this, "sortProducts"));
				foreach ($productValues as $productId => $count) {
					$sequenceNumber++;
					executeQuery("insert into product_tag_links (product_id,product_tag_id,sequence_number) values (?,?,?)", $productId, $productTagId, $sequenceNumber);
					if ($sequenceNumber >= $bestSellerLimit) {
						break;
					}
				}
                $afterProductIds = $this->getTaggedProductIds($productTagId);
                $this->markProductsChanged($beforeProductIds,$afterProductIds);
                $this->addResult($sequenceNumber . " most searched products created for client '" . $clientRow['business_name'] . "'");
			}

			# Most Searched in all Coreware

			$productTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "MOST_SEARCHED_IN_ALL_COREWARE");
			if (!empty($productTagId)) {
			    $beforeProductIds = $this->getTaggedProductIds($productTagId);
				executeQuery("delete from product_tag_links where product_tag_id = ?", $productTagId);
				$productValues = array();
				$sequenceNumber = 0;
				foreach ($topProductData['search'] as $searchTerm => $quantity) {
					$words = ProductCatalog::getSearchWords($searchTerm)['search_words'];
					foreach ($words as $thisWord) {
						$wordSet = executeQuery("select * from product_search_word_values where product_id in (select product_id from products where client_id = ? and inactive = 0) and " .
							"product_search_word_id in (select product_search_word_id from product_search_words where client_id = ? and search_term = ?)", $GLOBALS['gClientId'], $GLOBALS['gClientId'], $thisWord);
						while ($wordRow = getNextRow($wordSet)) {
							if (!array_key_exists($wordRow['product_id'], $productValues)) {
								$productValues[$wordRow['product_id']] = 0;
							}
							$productValues[$wordRow['product_id']] += $wordRow['search_value'];
						}
					}
				}
				uasort($productValues, array($this, "sortProducts"));
				foreach ($productValues as $productId => $count) {
					$sequenceNumber++;
					executeQuery("insert into product_tag_links (product_id,product_tag_id,sequence_number) values (?,?,?)", $productId, $productTagId, $sequenceNumber);
					if ($sequenceNumber >= $bestSellerLimit) {
						break;
					}
				}
                $afterProductIds = $this->getTaggedProductIds($productTagId);
                $this->markProductsChanged($beforeProductIds,$afterProductIds);
                $this->addResult($sequenceNumber . " most searched in all coreware products created for client '" . $clientRow['business_name'] . "'");
			}

			$emailAddresses = getNotificationEmails("TOP_PRODUCTS");
			if (!empty($emailAddresses)) {
				$upcCodes = "";
				foreach ($topProductData['sales'] as $upcCode => $quantity) {
					if (!is_numeric($upcCode)) {
						continue;
					}
					$productId = getFieldFromId("product_id", "product_data", "upc_code", $upcCode);
					if (empty($productId)) {
						$upcCodes .= (empty($upcCodes) ? "" : "<br>") . $upcCode;
					}
				}
				if (!empty($upcCodes)) {
					$this->addResult("Top products for " . $clientRow['client_code']);
					sendEmail(array("subject" => "Top Products not in catalog",
						"body" => "<p>The following UPC codes are in the top sellers of Coreware, but not in your catalog:</p><p>" . $upcCodes . "</p>", "notification_code" => "TOP_PRODUCTS"));
				}
			}
		}
	}

	function sortProducts($a, $b) {
		if ($a == $b) {
			return 0;
		}
		return ($a > $b ? 1 : -1);
	}

    function getTaggedProductIds($productTagId) {
        $productIds = array();
        $resultSet = executeQuery("select product_id from product_tag_links where product_tag_id = ?", $productTagId);
        while($row = getNextRow($resultSet)) {
            $productIds[] = $row['product_id'];
        }
        freeResult($resultSet);
        return $productIds;
    }

    function markProductsChanged($beforeProductIds, $afterProductIds) {
        $taggedProductIds = array_diff($afterProductIds,$beforeProductIds);
        $untaggedProductIds = array_diff($beforeProductIds,$afterProductIds);
        $changedProductIds = array_merge($taggedProductIds,$untaggedProductIds);
        if (!empty($changedProductIds)) {
            executeQuery("update products set time_changed = now() where product_id in (" . implode(",", $changedProductIds) . ") and client_id = ?",
                $GLOBALS['gClientId']);
        }
    }
}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
