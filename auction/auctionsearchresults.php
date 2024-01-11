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

$GLOBALS['gPageCode'] = "AUCTIONSEARCHRESULTS";
$GLOBALS['gCacheProhibited'] = true;
$GLOBALS['gLogTimeRequired'] = true;
require_once "shared/startup.inc";

class AuctionSearchResultsPage extends Page {

	function setup() {
		$sourceId = "";
		if (array_key_exists("aid", $_GET)) {
			$sourceId = getReadFieldFromId("source_id", "sources", "source_code", strtoupper($_GET['aid']));
		}
		if (array_key_exists("source", $_GET)) {
			$sourceId = getReadFieldFromId("source_id", "sources", "source_code", strtoupper($_GET['source']));
		}
		if (!empty($sourceId)) {
			setCoreCookie("source_id", $sourceId, 6);
		}
	}

	function setPageTitle() {
		$identifierCount = 0;

		$_POST = array_merge($_POST, $_GET);
		$productDepartmentIds = array();
		if (array_key_exists("product_department_id", $_POST) && !empty($_POST['product_department_id'])) {
			if (!is_array($_POST['product_department_id'])) {
				$_POST['product_department_id'] = explode("|", $_POST['product_department_id']);
			}
			foreach ($_POST['product_department_id'] as $productDepartmentId) {
				$productDepartmentId = getReadFieldFromId("product_department_id", "product_departments", "product_department_id", $productDepartmentId);
				if (!empty($productDepartmentId) && !in_array($productDepartmentId, $productDepartmentIds)) {
					$productDepartmentIds[] = $productDepartmentId;
				}
			}
		}
		if (array_key_exists("product_department_code", $_POST) && !empty($_POST['product_department_code'])) {
			if (!is_array($_POST['product_department_code'])) {
				$_POST['product_department_code'] = explode("|", $_POST['product_department_code']);
			}
			foreach ($_POST['product_department_code'] as $productDepartmentCode) {
				$productDepartmentId = getReadFieldFromId("product_department_id", "product_departments", "product_department_code", $productDepartmentCode);
				if (!empty($productDepartmentId) && !in_array($productDepartmentId, $productDepartmentIds)) {
					$productDepartmentIds[] = $productDepartmentId;
				}
			}
		}
		$identifierCount += count($productDepartmentIds);

		$productCategoryIds = array();
		if (array_key_exists("product_category_id", $_POST) && !empty($_POST['product_category_id'])) {
			if (!is_array($_POST['product_category_id'])) {
				$_POST['product_category_id'] = explode("|", $_POST['product_category_id']);
			}
			foreach ($_POST['product_category_id'] as $productCategoryId) {
				$productCategoryId = getReadFieldFromId("product_category_id", "product_categories", "product_category_id", $productCategoryId);
				if (!empty($productCategoryId) && !in_array($productCategoryId, $productCategoryIds)) {
					$productCategoryIds[] = $productCategoryId;
				}
			}
		}
		if (array_key_exists("product_category_code", $_POST) && !empty($_POST['product_category_code'])) {
			if (!is_array($_POST['product_category_code'])) {
				$_POST['product_category_code'] = explode("|", $_POST['product_category_code']);
			}
			foreach ($_POST['product_category_code'] as $productCategoryCode) {
				$productCategoryId = getReadFieldFromId("product_category_id", "product_categories", "product_category_code", $productCategoryCode);
				if (!empty($productCategoryId) && !in_array($productCategoryId, $productCategoryIds)) {
					$productCategoryIds[] = $productCategoryId;
				}
			}
		}
		$identifierCount += count($productCategoryIds);

		$productCategoryGroupIds = array();
		if (array_key_exists("product_category_group_id", $_POST) && !empty($_POST['product_category_group_id'])) {
			if (!is_array($_POST['product_category_group_id'])) {
				$_POST['product_category_group_id'] = explode("|", $_POST['product_category_group_id']);
			}
			foreach ($_POST['product_category_group_id'] as $productCategoryGroupId) {
				$productCategoryGroupId = getReadFieldFromId("product_category_group_id", "product_category_groups", "product_category_group_id", $productCategoryGroupId);
				if (!empty($productCategoryGroupId) && !in_array($productCategoryGroupId, $productCategoryGroupIds)) {
					$productCategoryGroupIds[] = $productCategoryGroupId;
				}
			}
		}
		if (array_key_exists("product_category_group_code", $_POST) && !empty($_POST['product_category_group_code'])) {
			if (!is_array($_POST['product_category_group_code'])) {
				$_POST['product_category_group_code'] = explode("|", $_POST['product_category_group_code']);
			}
			foreach ($_POST['product_category_group_code'] as $productCategoryCode) {
				$productCategoryGroupId = getReadFieldFromId("product_category_group_id", "product_category_groups", "product_category_group_code", $productCategoryCode);
				if (!empty($productCategoryGroupId) && !in_array($productCategoryGroupId, $productCategoryGroupIds)) {
					$productCategoryGroupIds[] = $productCategoryGroupId;
				}
			}
		}
		$identifierCount += count($productCategoryGroupIds);

		$productTagIds = array();
		if (array_key_exists("product_tag_id", $_POST) && !empty($_POST['product_tag_id'])) {
			if (!is_array($_POST['product_tag_id'])) {
				$_POST['product_tag_id'] = explode("|", $_POST['product_tag_id']);
			}
			foreach ($_POST['product_tag_id'] as $productTagId) {
				$productTagId = getReadFieldFromId("product_tag_id", "product_tags", "product_tag_id", $productTagId);
				if (!empty($productTagId) && !in_array($productTagId, $productTagIds)) {
					$productTagIds[] = $productTagId;
				}
			}
		}
		if (array_key_exists("product_tag_code", $_POST) && !empty($_POST['product_tag_code'])) {
			if (!is_array($_POST['product_tag_code'])) {
				$_POST['product_tag_code'] = explode("|", $_POST['product_tag_code']);
			}
			foreach ($_POST['product_tag_code'] as $productTagCode) {
				$productTagId = getReadFieldFromId("product_tag_id", "product_tags", "product_tag_code", $productTagCode);
				if (!empty($productTagId) && !in_array($productTagId, $productTagIds)) {
					$productTagIds[] = $productTagId;
				}
			}
		}
		$identifierCount += count($productTagIds);

		if ($identifierCount == 1) {
			if (count($productDepartmentIds) > 0) {
				$tableRow = getRowFromId("product_departments", "product_department_id", $productDepartmentIds[0]);
				if (!empty($tableRow['meta_description'])) {
					$GLOBALS['gPageRow']['meta_description'] = $tableRow['meta_description'];
				}
				if (empty($tableRow['meta_title'])) {
					return $tableRow['description'] . " | " . $GLOBALS['gClientName'];
				} else {
					return $tableRow['meta_title'];
				}
			}
			if (count($productCategoryIds) > 0) {
				$tableRow = getRowFromId("product_categories", "product_category_id", $productCategoryIds[0]);
				if (!empty($tableRow['meta_description'])) {
					$GLOBALS['gPageRow']['meta_description'] = $tableRow['meta_description'];
				}
				if (empty($tableRow['meta_title'])) {
					return $tableRow['description'] . " | " . $GLOBALS['gClientName'];
				} else {
					return $tableRow['meta_title'];
				}
			}
			if (count($productTagIds) > 0) {
				$tableRow = getRowFromId("product_tags", "product_tag_id", $productTagIds[0]);
				if (!empty($tableRow['meta_description'])) {
					$GLOBALS['gPageRow']['meta_description'] = $tableRow['meta_description'];
				}
				if (empty($tableRow['meta_title'])) {
					return $tableRow['description'] . " | " . $GLOBALS['gClientName'];
				} else {
					return $tableRow['meta_title'];
				}
			}
			if (count($productCategoryGroupIds) > 0) {
				$tableRow = getRowFromId("product_category_groups", "product_category_group_id", $productCategoryGroupIds[0]);
				if (!empty($tableRow['meta_description'])) {
					$GLOBALS['gPageRow']['meta_description'] = $tableRow['meta_description'];
				}
				if (empty($tableRow['meta_title'])) {
					return $tableRow['description'] . " | " . $GLOBALS['gClientName'];
				} else {
					return $tableRow['meta_title'];
				}
			}
		}
		return false;
	}

	function mainContent() {
		?>
        <div id="_search_result_details_wrapper" class="hidden"></div>
		<?php
		return false;
	}

	function onLoadJavascript() {
		$cdnDomain = ($GLOBALS['gDevelopmentServer'] ? "" : getPreference("CDN_DOMAIN"));
		if (!empty($cdnDomain) && substr($cdnDomain, 0, 4) != "http") {
			$cdnDomain = "https://" . $cdnDomain;
			$cdnDomain = trim($cdnDomain, "/");
		}
		?>
        <script>
			<?php if (!empty($cdnDomain)) { ?>
            cdnDomain = "<?= $cdnDomain ?>";
			<?php } ?>
            $(document).on("click", "#reload_cache", function () {
                let thisUrl = $(location).attr('href');
                if (thisUrl.indexOf("?") > 0) {
                    thisUrl += "&no_cache=true";
                } else {
                    thisUrl += "?no_cache=true";
                }
                document.location = thisUrl;
            });
        </script>
		<?php
	}

	function inlineJavascript() {
		$queryTime = "";
		$startTime = getMilliseconds();
		$endTime = getMilliseconds();
		$queryTime .= "Start Search Results: " . round(($endTime - $startTime) / 1000, 2) . ", Memory Used: " . number_format(memory_get_usage(), 0, "", ",") . "\n";
		$startTime = getMilliseconds();

		$auctionItemFieldNames = array();
		$auctionItemResults = array();
		$productCategoryGroupIds = array();
		$constraints = array();
		$resultCount = 0;
		$cachedResultsUsed = false;

		$pageGroupingData = array();
		$displaySearchText = "";

		$_POST = array_merge($_POST, $_GET);
		$missingProductImage = getImageFilenameFromCode("NO_PRODUCT_IMAGE");
		if (empty($missingProductImage) || $missingProductImage == "/images/empty.jpg") {
			$missingProductImage = getPreference("DEFAULT_PRODUCT_IMAGE");
		}
		if (empty($missingProductImage)) {
			$missingProductImage = "/images/empty.jpg";
		}
		$catalogResultHtml = Auction::getAuctionResultHtml();
        $auctionObject = new Auction();
        if (array_key_exists("search_text", $_POST)) {
            $auctionObject->setSearchText($_POST['search_text']);
        }
        $auctionObject->setSelectLimit(isWebCrawler() || $_SESSION['speed_tester'] ? 20 : 1000000);
        if (isWebCrawler() || $_SESSION['speed_tester']) {
            $auctionObject->setLimitQuery(true);
            $sidebarFacetLimit = 5;
        }

        $productDepartmentIds = array();
        if (array_key_exists("product_department_ids", $_POST) && !empty($_POST['product_department_ids'])) {
            if (!is_array($_POST['product_department_ids'])) {
                $_POST['product_department_ids'] = explode("|", $_POST['product_department_ids']);
            }
            foreach ($_POST['product_department_ids'] as $productDepartmentId) {
                $productDepartmentId = getReadFieldFromId("product_department_id", "product_departments", "product_department_id", $productDepartmentId);
                if (!empty($productDepartmentId) && !in_array($productDepartmentId, $productDepartmentIds)) {
                    $productDepartmentIds[] = $productDepartmentId;
                }
            }
        }
        if (array_key_exists("product_department_codes", $_POST) && !empty($_POST['product_department_codes'])) {
            if (!is_array($_POST['product_department_codes'])) {
                $_POST['product_department_codes'] = explode("|", $_POST['product_department_codes']);
            }
            foreach ($_POST['product_department_codes'] as $productDepartmentCode) {
                $productDepartmentId = getReadFieldFromId("product_department_id", "product_departments", "product_department_code", $productDepartmentCode);
                if (!empty($productDepartmentId) && !in_array($productDepartmentId, $productDepartmentIds)) {
                    $productDepartmentIds[] = $productDepartmentId;
                }
            }
        }
        if (array_key_exists("product_department_id", $_POST) && !empty($_POST['product_department_id'])) {
            if (!is_array($_POST['product_department_id'])) {
                $_POST['product_department_id'] = explode("|", $_POST['product_department_id']);
            }
            foreach ($_POST['product_department_id'] as $productDepartmentId) {
                $productDepartmentId = getReadFieldFromId("product_department_id", "product_departments", "product_department_id", $productDepartmentId);
                if (!empty($productDepartmentId) && !in_array($productDepartmentId, $productDepartmentIds)) {
                    $productDepartmentIds[] = $productDepartmentId;
                }
            }
        }
        if (array_key_exists("product_department_code", $_POST) && !empty($_POST['product_department_code'])) {
            if (!is_array($_POST['product_department_code'])) {
                $_POST['product_department_code'] = explode("|", $_POST['product_department_code']);
            }
            foreach ($_POST['product_department_code'] as $productDepartmentCode) {
                $productDepartmentId = getReadFieldFromId("product_department_id", "product_departments", "product_department_code", $productDepartmentCode);
                if (!empty($productDepartmentId) && !in_array($productDepartmentId, $productDepartmentIds)) {
                    $productDepartmentIds[] = $productDepartmentId;
                }
            }
        }
        if (!empty($productDepartmentIds)) {
            $auctionObject->setDepartments($productDepartmentIds);
        }

        $productCategoryIds = array();
        if (array_key_exists("product_category_ids", $_POST) && !empty($_POST['product_category_ids'])) {
            if (!is_array($_POST['product_category_ids'])) {
                $_POST['product_category_ids'] = explode("|", $_POST['product_category_ids']);
            }
            foreach ($_POST['product_category_ids'] as $productCategoryId) {
                $productCategoryId = getReadFieldFromId("product_category_id", "product_categories", "product_category_id", $productCategoryId);
                if (!empty($productCategoryId) && !in_array($productCategoryId, $productCategoryIds)) {
                    $productCategoryIds[] = $productCategoryId;
                }
            }
        }
        if (array_key_exists("product_category_codes", $_POST) && !empty($_POST['product_category_codes'])) {
            if (!is_array($_POST['product_category_codes'])) {
                $_POST['product_category_codes'] = explode("|", $_POST['product_category_codes']);
            }
            foreach ($_POST['product_category_codes'] as $productCategoryCode) {
                $productCategoryId = getReadFieldFromId("product_category_id", "product_categories", "product_category_code", $productCategoryCode);
                if (!empty($productCategoryId) && !in_array($productCategoryId, $productCategoryIds)) {
                    $productCategoryIds[] = $productCategoryId;
                }
            }
        }
        if (array_key_exists("product_category_id", $_POST) && !empty($_POST['product_category_id'])) {
            if (!is_array($_POST['product_category_id'])) {
                $_POST['product_category_id'] = explode("|", $_POST['product_category_id']);
            }
            foreach ($_POST['product_category_id'] as $productCategoryId) {
                $productCategoryId = getReadFieldFromId("product_category_id", "product_categories", "product_category_id", $productCategoryId);
                if (!empty($productCategoryId) && !in_array($productCategoryId, $productCategoryIds)) {
                    $productCategoryIds[] = $productCategoryId;
                }
            }
        }
        if (array_key_exists("product_category_code", $_POST) && !empty($_POST['product_category_code'])) {
            if (!is_array($_POST['product_category_code'])) {
                $_POST['product_category_code'] = explode("|", $_POST['product_category_code']);
            }
            foreach ($_POST['product_category_code'] as $productCategoryCode) {
                $productCategoryId = getReadFieldFromId("product_category_id", "product_categories", "product_category_code", $productCategoryCode);
                if (!empty($productCategoryId) && !in_array($productCategoryId, $productCategoryIds)) {
                    $productCategoryIds[] = $productCategoryId;
                }
            }
        }
        if (!empty($productCategoryIds)) {
            $auctionObject->setCategories($productCategoryIds);
        }

        $productCategoryGroupIds = array();
        if (array_key_exists("product_category_group_ids", $_POST) && !empty($_POST['product_category_group_ids'])) {
            if (!is_array($_POST['product_category_group_ids'])) {
                $_POST['product_category_group_ids'] = explode("|", $_POST['product_category_group_ids']);
            }
            foreach ($_POST['product_category_group_ids'] as $productCategoryGroupId) {
                $productCategoryGroupId = getReadFieldFromId("product_category_group_id", "product_category_groups", "product_category_group_id", $productCategoryGroupId);
                if (!empty($productCategoryGroupId) && !in_array($productCategoryGroupId, $productCategoryGroupIds)) {
                    $productCategoryGroupIds[] = $productCategoryGroupId;
                }
            }
        }
        if (array_key_exists("product_category_group_codes", $_POST) && !empty($_POST['product_category_group_codes'])) {
            if (!is_array($_POST['product_category_group_codes'])) {
                $_POST['product_category_group_codes'] = explode("|", $_POST['product_category_group_codes']);
            }
            foreach ($_POST['product_category_group_codes'] as $productCategoryCode) {
                $productCategoryGroupId = getReadFieldFromId("product_category_group_id", "product_category_groups", "product_category_group_code", $productCategoryCode);
                if (!empty($productCategoryGroupId) && !in_array($productCategoryGroupId, $productCategoryGroupIds)) {
                    $productCategoryGroupIds[] = $productCategoryGroupId;
                }
            }
        }
        if (array_key_exists("product_category_group_id", $_POST) && !empty($_POST['product_category_group_id'])) {
            if (!is_array($_POST['product_category_group_id'])) {
                $_POST['product_category_group_id'] = explode("|", $_POST['product_category_group_id']);
            }
            foreach ($_POST['product_category_group_id'] as $productCategoryGroupId) {
                $productCategoryGroupId = getReadFieldFromId("product_category_group_id", "product_category_groups", "product_category_group_id", $productCategoryGroupId);
                if (!empty($productCategoryGroupId) && !in_array($productCategoryGroupId, $productCategoryGroupIds)) {
                    $productCategoryGroupIds[] = $productCategoryGroupId;
                }
            }
        }
        if (array_key_exists("product_category_group_code", $_POST) && !empty($_POST['product_category_group_code'])) {
            if (!is_array($_POST['product_category_group_code'])) {
                $_POST['product_category_group_code'] = explode("|", $_POST['product_category_group_code']);
            }
            foreach ($_POST['product_category_group_code'] as $productCategoryCode) {
                $productCategoryGroupId = getReadFieldFromId("product_category_group_id", "product_category_groups", "product_category_group_code", $productCategoryCode);
                if (!empty($productCategoryGroupId) && !in_array($productCategoryGroupId, $productCategoryGroupIds)) {
                    $productCategoryGroupIds[] = $productCategoryGroupId;
                }
            }
        }

        if (!empty($productCategoryGroupIds)) {
            $auctionObject->setCategoryGroups($productCategoryGroupIds);
        }

        $productTagIds = array();
        if (array_key_exists("product_tag_ids", $_POST) && !empty($_POST['product_tag_ids'])) {
            if (!is_array($_POST['product_tag_ids'])) {
                $_POST['product_tag_ids'] = explode("|", $_POST['product_tag_ids']);
            }
            foreach ($_POST['product_tag_ids'] as $productTagId) {
                $productTagId = getReadFieldFromId("product_tag_id", "product_tags", "product_tag_id", $productTagId);
                if (!empty($productTagId) && !in_array($productTagId, $productTagIds)) {
                    $productTagIds[] = $productTagId;
                }
            }
        }
        if (array_key_exists("product_tag_codes", $_POST) && !empty($_POST['product_tag_codes'])) {
            if (!is_array($_POST['product_tag_codes'])) {
                $_POST['product_tag_codes'] = explode("|", $_POST['product_tag_codes']);
            }
            foreach ($_POST['product_tag_codes'] as $productTagCode) {
                $productTagId = getReadFieldFromId("product_tag_id", "product_tags", "product_tag_code", $productTagCode);
                if (!empty($productTagId) && !in_array($productTagId, $productTagIds)) {
                    $productTagIds[] = $productTagId;
                }
            }
        }
        if (array_key_exists("product_tag_id", $_POST) && !empty($_POST['product_tag_id'])) {
            if (!is_array($_POST['product_tag_id'])) {
                $_POST['product_tag_id'] = explode("|", $_POST['product_tag_id']);
            }
            foreach ($_POST['product_tag_id'] as $productTagId) {
                $productTagId = getReadFieldFromId("product_tag_id", "product_tags", "product_tag_id", $productTagId);
                if (!empty($productTagId) && !in_array($productTagId, $productTagIds)) {
                    $productTagIds[] = $productTagId;
                }
            }
        }
        if (array_key_exists("product_tag_code", $_POST) && !empty($_POST['product_tag_code'])) {
            if (!is_array($_POST['product_tag_code'])) {
                $_POST['product_tag_code'] = explode("|", $_POST['product_tag_code']);
            }
            foreach ($_POST['product_tag_code'] as $productTagCode) {
                $productTagId = getReadFieldFromId("product_tag_id", "product_tags", "product_tag_code", $productTagCode);
                if (!empty($productTagId) && !in_array($productTagId, $productTagIds)) {
                    $productTagIds[] = $productTagId;
                }
            }
        }
        if (!empty($productTagIds)) {
            $auctionObject->setTags($productTagIds);
        }

        $excludeIds = array();
        if (array_key_exists("exclude_product_category_ids", $_POST) && !empty($_POST['exclude_product_category_ids'])) {
            if (!is_array($_POST['exclude_product_category_ids'])) {
                $_POST['exclude_product_category_ids'] = explode("|", $_POST['exclude_product_category_ids']);
            }
            foreach ($_POST['exclude_product_category_ids'] as $excludeId) {
                $excludeId = getReadFieldFromId("product_category_id", "product_categories", "product_category_id", $excludeId);
                if (!empty($excludeId) && !in_array($excludeId, $excludeIds)) {
                    $excludeIds[] = $excludeId;
                }
            }
        }
        if (array_key_exists("exclude_product_category_id", $_POST) && !empty($_POST['exclude_product_category_id'])) {
            if (!is_array($_POST['exclude_product_category_id'])) {
                $_POST['exclude_product_category_id'] = explode("|", $_POST['exclude_product_category_id']);
            }
            foreach ($_POST['exclude_product_category_id'] as $excludeId) {
                $excludeId = getReadFieldFromId("product_category_id", "product_categories", "product_category_id", $excludeId);
                if (!empty($excludeId) && !in_array($excludeId, $excludeIds)) {
                    $excludeIds[] = $excludeId;
                }
            }
        }
        if (array_key_exists("exclude_internal_product_categories", $_POST) && $_POST['exclude_internal_product_categories']) {
            $resultSet = executeReadQuery("select * from product_categories where client_id = ? and internal_use_only = 1", $GLOBALS['gClientId']);
            while ($row = getNextRow($resultSet)) {
                if (!in_array($row['product_category_id'], $excludeIds)) {
                    $excludeIds[] = $row['product_category_id'];
                }
            }
        }
        if (!empty($excludeIds)) {
            $auctionObject->setExcludeCategories($excludeIds);
        }
        $excludeIds = array();
        if (array_key_exists("exclude_product_department_ids", $_POST) && !empty($_POST['exclude_product_department_ids'])) {
            if (!is_array($_POST['exclude_product_department_ids'])) {
                $_POST['exclude_product_department_ids'] = explode("|", $_POST['exclude_product_department_ids']);
            }
            foreach ($_POST['exclude_product_department_ids'] as $excludeId) {
                $excludeId = getReadFieldFromId("product_department_id", "product_departments", "product_department_id", $excludeId);
                if (!empty($excludeId) && !in_array($excludeId, $excludeIds)) {
                    $excludeIds[] = $excludeId;
                }
            }
        }
        if (array_key_exists("exclude_product_department_id", $_POST) && !empty($_POST['exclude_product_department_id'])) {
            if (!is_array($_POST['exclude_product_department_id'])) {
                $_POST['exclude_product_department_id'] = explode("|", $_POST['exclude_product_department_id']);
            }
            foreach ($_POST['exclude_product_department_id'] as $excludeId) {
                $excludeId = getReadFieldFromId("product_department_id", "product_departments", "product_department_id", $excludeId);
                if (!empty($excludeId) && !in_array($excludeId, $excludeIds)) {
                    $excludeIds[] = $excludeId;
                }
            }
        }
        if (array_key_exists("exclude_internal_product_departments", $_POST) && $_POST['exclude_internal_product_departments']) {
            $resultSet = executeReadQuery("select * from product_departments where client_id = ? and internal_use_only = 1", $GLOBALS['gClientId']);
            while ($row = getNextRow($resultSet)) {
                if (!in_array($row['product_department_id'], $excludeIds)) {
                    $excludeIds[] = $row['product_department_id'];
                }
            }
        }
        if (!empty($excludeIds)) {
            $auctionObject->setExcludeDepartments($excludeIds);
        }

        if (count($productDepartmentIds) > 0) {
            $pageGroupingData['primary_id'] = $productDepartmentIds[0];
            $pageGroupingData['primary_key'] = "product_department_id";
            $pageGroupingData['label'] = "Department";
            $pageGroupingData['description'] = getReadFieldFromId("description", "product_departments", "product_department_id", $productDepartmentIds[0]);
            $pageGroupingData['detailed_description'] = getReadFieldFromId("detailed_description", "product_departments", "product_department_id", $productDepartmentIds[0]);
            $pageGroupingData['image_id'] = getReadFieldFromId("image_id", "product_departments", "product_department_id", $productDepartmentIds[0]);
            $pageGroupingData['image_url'] = (empty($pageGroupingData['image_id']) ? "" : getImageFilename($pageGroupingData['image_id']));
        } else if (count($productCategoryGroupIds) > 0) {
            $pageGroupingData['primary_id'] = $productCategoryGroupIds[0];
            $pageGroupingData['primary_key'] = "product_category_group_id";
            $pageGroupingData['label'] = "Category Group";
            $pageGroupingData['description'] = getReadFieldFromId("description", "product_category_groups", "product_category_group_id", $productCategoryGroupIds[0]);
            $pageGroupingData['detailed_description'] = getReadFieldFromId("detailed_description", "product_category_groups", "product_category_group_id", $productCategoryGroupIds[0]);
            $pageGroupingData['image_id'] = getReadFieldFromId("image_id", "product_category_groups", "product_category_group_id", $productCategoryGroupIds[0]);
            $pageGroupingData['image_url'] = (empty($pageGroupingData['image_id']) ? "" : getImageFilename($pageGroupingData['image_id']));
        } else if (count($productCategoryIds) > 0) {
            $pageGroupingData['primary_id'] = $productCategoryIds[0];
            $pageGroupingData['primary_key'] = "product_category_id";
            $pageGroupingData['label'] = "Category";
            $pageGroupingData['description'] = getReadFieldFromId("description", "product_categories", "product_category_id", $productCategoryIds[0]);
            $pageGroupingData['detailed_description'] = getReadFieldFromId("detailed_description", "product_categories", "product_category_id", $productCategoryIds[0]);
            $pageGroupingData['image_id'] = getReadFieldFromId("image_id", "product_categories", "product_category_id", $productCategoryIds[0]);
            $pageGroupingData['image_url'] = (empty($pageGroupingData['image_id']) ? "" : getImageFilename($pageGroupingData['image_id']));
        } else if (count($productTagIds) > 0) {
            $pageGroupingData['primary_id'] = $productTagIds[0];
            $pageGroupingData['primary_key'] = "product_tag_id";
            $pageGroupingData['label'] = "Tag";
            $pageGroupingData['description'] = getReadFieldFromId("description", "product_tags", "product_tag_id", $productTagIds[0]);
            $pageGroupingData['detailed_description'] = getReadFieldFromId("detailed_description", "product_tags", "product_tag_id", $productTagIds[0]);
            $pageGroupingData['image_id'] = getReadFieldFromId("image_id", "product_tags", "product_tag_id", $productTagIds[0]);
            $pageGroupingData['image_url'] = (empty($pageGroupingData['image_id']) ? "" : getImageFilename($pageGroupingData['image_id']));
        }

        $endTime = getMilliseconds();
        $queryTime .= "Before Get Search Results: " . round(($endTime - $startTime) / 1000, 2) . ", Memory Used: " . number_format(memory_get_usage(), 0, "", ",") . "\n";
        $startTime = getMilliseconds();
        $auctionItemResults = $auctionObject->getAuctionItems();
        $endTime = getMilliseconds();
        $queryTime .= "Get Search Results: " . round(($endTime - $startTime) / 1000, 2) . ", Memory Used: " . number_format(memory_get_usage(), 0, "", ",") . "\n";
        $startTime = getMilliseconds();
        $displaySearchText = $auctionObject->getDisplaySearchText();
        $constraints = $auctionObject->getConstraints();
        $resultCount = $auctionObject->getResultCount();
        $queryTime .= $auctionObject->getQueryTime() . "\n";
        if ($GLOBALS['gUserRow']['superuser_flag']) {
            $queryString = $auctionObject->getQueryString();
        }
        unset($auctionObject);
        $auctionObject = false;
		$endTime = getMilliseconds();
		$queryTime .= "After products are loaded: " . round(($endTime - $startTime) / 1000, 2) . ", Memory Used: " . number_format(memory_get_usage(), 0, "", ",") . "\n";
		$startTime = getMilliseconds();

		$productCategories = array();
		$resultSet = executeReadQuery("select * from product_categories where inactive = 0 and internal_use_only = 0 and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$productCategories[] = array("id" => $row['product_category_id'], "description" => $row['description']);
		}

		$specificationDescriptions = array();
		$resultSet = executeReadQuery("select * from auction_specifications where inactive = 0 and internal_use_only = 0 and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$specificationDescriptions[] = array("id" => $row['auction_specification_id'], "description" => $row['description']);
		}

        $watchListAuctionItemIds = array();
        $resultSet = executeReadQuery("select * from user_watchlists where user_id = ?", $GLOBALS['gUserId']);
        while ($row = getNextRow($resultSet)) {
            $watchListAuctionItemIds[] = $row['auction_item_id'];
        }

		$endTime = getMilliseconds();
		$queryTime .= "Search Finished: " . round(($endTime - $startTime) / 1000, 2) . ", Memory Used: " . number_format(memory_get_usage(), 0, "", ",") . "\n";
		$startTime = getMilliseconds();

		if (count($auctionItemResults) > 0) {

			$auctionItemFieldNames = array_keys(reset($auctionItemResults));
			foreach ($auctionItemResults as $index => $result) {
				$auctionItemResults[$index] = array_values($result);
			}
		}

		$resultsKey = "";
		$problemCaching = false;

		# If the number of results is greater than 1000, store results in the SESSION so that the page loads quickly. Results will be gotten by ajax.

		$resultsString = jsonEncode($auctionItemResults);
		$resultsString = str_replace(".0000", "", $resultsString);
		$resultsString = str_replace(",false", ",", $resultsString);
		$resultsString = str_replace(',""', ',', $resultsString);

		$endTime = getMilliseconds();
		$queryTime .= "Search Completed: " . round(($endTime - $startTime) / 1000, 2) . ", Memory Used: " . number_format(memory_get_usage(), 0, "", ",") . "\n";
		$startTime = getMilliseconds();
		if (!is_array($_POST)) {
			$_POST = array();
		}

		?>
        <script>
            var auctionItemSearchResultsKey = "<?= $resultsKey ?>";
            postVariables = <?= jsonEncode($_POST) ?>;
            displaySearchText = "<?= $displaySearchText ?>";
            auctionItemFieldNames = <?= jsonEncode($auctionItemFieldNames) ?>;
            auctionItemKeyLookup = false;
            auctionItemResults = <?= $resultsString ?>;
            productCategories = <?= jsonEncode($productCategories) ?>;
            constraints = <?= jsonEncode($constraints) ?>;
            resultCount = <?= (empty($resultCount) ? 0 : $resultCount) ?>;
            queryTime = <?= jsonEncode(array($queryTime)) ?>;
            pageGroupingData = <?= jsonEncode($pageGroupingData) ?>;
            productCategoryGroupIds = <?= jsonEncode($productCategoryGroupIds) ?>;
            emptyImageFilename = "<?= $missingProductImage ?>";
            specificationDescriptions = <?= jsonEncode($specificationDescriptions) ?>;
            watchListAuctionItemIds = <?= jsonEncode($watchListAuctionItemIds) ?>;
            console.log(queryTime[0]);
			<?php if ($GLOBALS['gUserRow']['superuser_flag']) { ?>
			<?php if (!empty($queryString)) { ?>
            console.log("<?= $queryString ?>");
			<?php } ?>
			<?php } ?>
        </script>
		<?php
	}

	function javascript() {

		/* possible classes
		page-grouping-data-primary_id
		page-grouping-data-primary_key
		page-grouping-data-label
		page-grouping-data-description
		page-grouping-data-detailed_description
		page-grouping-data-image_id
		page-grouping-data-image_url
		*/

		?>
        <!--suppress JSUnresolvedVariable -->
        <script>
            function initialAuctionItemLoad() {
				<?php if (isWebCrawler() || $_SESSION['speed_tester']) { ?>
                $(".results-count-wrapper").css("display", "none");
				<?php } ?>
                for (let i in pageGroupingData) {
                    const $pageGroupingData = $(".page-grouping-data-" + i);
                    if ($pageGroupingData.length > 0) {
                        $pageGroupingData.each(function () {
                            if ($(this).is("textarea") || $(this).is("select") || $(this).is("input")) {
                                $(this).val(pageGroupingData[i]);
                            } else {
                                $(this).html(pageGroupingData[i]);
                            }
                        });
                    }
                }
                for (let i in postVariables) {
                    const $postElement = $("#" + i);
                    if ($postElement.length > 0) {
                        $postElement.val(postVariables[i]);
                    }
                }
                if (typeof beforeDisplaySearchResults === "function") {
                    if (beforeDisplaySearchResults()) {
                        return;
                    }
                }
                if ("label" in pageGroupingData && !empty(pageGroupingData['label'])) {
                    addFilter(pageGroupingData['label'], pageGroupingData['description'], pageGroupingData['primary_key']);
                }
                displaySearchResults();
                buildSidebarFilters();
                if (!empty(displaySearchText)) {
                    addFilter("Search Text", displaySearchText, "search_text");
                    if (!empty(siteSearchPageLink)) {
                        $("#selected_filters").append("<div id='_site_search_link'><a href='" + siteSearchPageLink + "'></a></div>");
                        $("#_site_search_link").find("a").text("search site for '" + displaySearchText + "'");
                    }
                }
                if (resultCount <= 0 && !empty(postVariables['search_text']) && "primary_id" in pageGroupingData && !empty(pageGroupingData['primary_id'])) {
                    $("#_search_results").append("<h2 class='align-center' id='searching_just_text'>Rerunning search with just text</h2>");
                    setTimeout(function () {
                        $("#selected_filters").find(".primary-selected-filter").each(function () {
                            const fieldName = $(this).data("field_name");
                            if (fieldName !== "search_text") {
                                $(this).trigger("click");
                                return false;
                            }
                        });
                    }, 2000);
                }
            }

            $(function () {
                setTimeout(function () {
                    if (empty(auctionItemSearchResultsKey)) {
                        initialAuctionItemLoad()
                    } else {
                        $("body").addClass("no-waiting-for-ajax");
                        $("#_search_results").html("<h3 class='align-left' style='margin: 40px auto;text-align: left;'>Loading initial results</h3><p class='align-left' style='margin-bottom: 40px;'><span style='font-size: 6rem' class='fad fa-spinner fa-spin'></span></p>");
                        loadAjaxRequest("/auction-controller?ajax=true&url_action=get_auction_item_search_results&results_key=" + auctionItemSearchResultsKey, function(returnArray) {
                            if ("auction_item_search_results" in returnArray) {
                                auctionItemResults = returnArray['auction_item_search_results'];
                                initialAuctionItemLoad();
                            }
                        });
                    }
                }, 500);
            });
        </script>
		<?php
	}

	function auctionSearchResults() {
		?>
        <div id="_search_results_outer">
            <div id="_search_controls">
				<?php
				$sortOptions = array("ending_soon" => "Auction: Ending Soon", "just_listed" => "Auction: Just Listed", "lowest_price" => "Price: Low to High",
					"highest_price" => "Price: High to Low", "lowest_bid" => "Bid: Low to High", "highest_bid" => "Bid: High to Low");

				$currentSort = $_COOKIE['auction_item_sort_order'];
				if (empty($currentSort) || !array_key_exists($currentSort, $sortOptions)) {
					$currentSort = "relevance";
				}
				?>
                <div id="auction_item_sort_order_control_wrapper">
                    <label id="auction_item_sort_order_label" for='auction_item_sort_order'>Sort By</label>
                    <select id="auction_item_sort_order">
						<?php foreach ($sortOptions as $thisSort => $description) { ?>
                            <option <?= ($thisSort == $currentSort ? "selected " : "") ?> value="<?= $thisSort ?>"><?= $description ?></option>
						<?php } ?>
                    </select>
                </div>

				<?php
				$countOptions = array("8", "20", "40", "60", "80", "100");
				$showCount = $_COOKIE['show_count'];
				if (empty($showCount) || !in_array($showCount, $countOptions)) {
					$showCount = "40";
				}
				?>
                <div id="show_count_control_wrapper">
                    <label id="show_count_label" for="show_count">Show</label>
                    <select id="show_count">
						<?php foreach ($countOptions as $thisCount) { ?>
                            <option <?= ($thisCount == $showCount ? "selected" : "") ?> value="<?= $thisCount ?>"><?= $thisCount ?></option>
						<?php } ?>
                    </select>
                </div>

				<?php
				$listType = $_COOKIE['result_display_type'];
				if (empty($listType) || $listType != "list") {
					$listType = "tile";
				}
				?>
                <div id="result_display_type_wrapper">
                    <span id="result_display_type_tile" data-result_display_type="tile" class='result-display-type fad fa-th<?= ($listType != "list" ? " selected" : "") ?>'></span><span id="result_display_type_list" data-result_display_type="list" class='result-display-type fad fa-list<?= ($listType == "list" ? " selected" : "") ?>'></span>
                </div>

                <div id="paging_control_wrapper" class="paging-control hidden align-right">
                    <input type="hidden" id="page_number" value="1">
                    <a href="#" class="previous-page"><span class="fas fa-backward"></span></a><span id="_top_paging_control_pages"></span><a href="#" class="next-page"><span class="fas fa-forward"></span></a>
                </div>

            </div> <!-- _search_controls -->

            <div id="_search_results">
            </div>

            <div id="bottom_paging_control_wrapper" class="paging-control hidden">
                <a href="#" class="previous-page"><span class="fas fa-backward"></span></a><span id="_bottom_paging_control_pages"></span><a href="#" class="next-page"><span class="fas fa-forward"></span></a>
            </div>

        </div> <!-- _search_results_outer -->
		<?php
		return true;
	}

	function jqueryTemplates() {
		?>
        <div id='catalog_result_product_tags_template'>
            <div class='catalog-result-product-tags'>
				<?php
				$resultSet = executeReadQuery("select * from product_tags where client_id = ? and display_color is not null and internal_use_only = 0 and " .
					"inactive = 0 and product_tag_id in (select product_tag_id from auction_item_product_tag_links) order by sort_order,description", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					?>
                    <div class='catalog-result-product-tag catalog-result-product-tag-<?= strtolower(str_replace("_", "-", $row['product_tag_code'])) ?>'><?= htmlText($row['description']) ?></div>
					<?php
				}
				?>
            </div>
        </div>
		<?php
		$sidebarFilter = $this->getPageTextChunk("auction_sidebar_filter");
		if (empty($sidebarFilter)) {
			$sidebarFilter = $this->getFragment("auction_sidebar_filter");
		}
		if (empty($sidebarFilter)) {
			ob_start();
			?>
            <div id="_sidebar_filter">
                <div class='sidebar-filter %other_classes%' data-field_name='%field_name%' id="%filter_id%">
                    <h3>%filter_title%<span class='fa fa-plus'></span><span class='fa fa-minus'></span></h3>
                    <div class='filter-options'>
                        <p class="filter-text-filter-wrapper">
                            <input type="text" class="filter-text-filter" placeholder="%search_text%"></p>
                        <div class='filter-checkboxes'>
                            %filter_options%
                        </div>
                    </div>
                </div>
            </div>
			<?php
			$sidebarFilter = ob_get_clean();
		}
		echo $sidebarFilter;
	}

	function internalCSS() {
		?>
        <style>
            #_search_result_details_wrapper {
                position: relative;
                padding: 20px;
            }

            #_search_result_close_details_wrapper {
                position: absolute;
                top: 30px;
                left: 30px;
                width: 40px;
                height: 40px;
                cursor: pointer;
            }

            #_search_result_close_details_wrapper span {
                color: rgb(200, 200, 200);
                font-size: 1.6rem;
            }

            #_search_results_outer {
                flex: 1 1 auto;
            }

            #_search_results {
                padding: 0 20px;
            }

            #results_count_wrapper {
                background: none;
                color: rgb(5, 57, 107);
                font-size: 1.5rem;
                font-weight: 700;
                text-align: left;
            }

            #_search_controls {
                padding: 10px;
                border: 1px solid rgba(177, 177, 177, 0.44);
                margin: 0 20px 20px 20px;
                background: #fff;
                box-shadow: 0 1px 0.5px 0 rgba(177, 177, 177, 0.44);
                border-radius: 3px;
                display: flex;
                justify-content: flex-start;
            }

            #_search_controls div {
                padding-right: 20px;
                position: relative;
            }

            #_search_results_outer select {
                max-width: 250px;
                font-size: 1.0rem;
                font-weight: normal;
                margin: 0;
                height: 34px;
            }

            #_search_results_outer label {
                margin-right: 20px;
                font-weight: 700;
            }

            #_search_results_outer label.checkbox-label {
                padding: 0;
                margin: 0;
            }

            #_search_results_outer select.page-number {
                margin: 0 10px;
            }

            #bottom_paging_control_wrapper {
                text-align: center;
                padding-bottom: 40px;
            }

            #_bottom_paging_control_pages {
                margin: 0 10px;
            }

            #_bottom_paging_control_pages a {
                margin: 0 10px;
                font-weight: 300;
            }

            #_bottom_paging_control_pages a.current-page {
                font-weight: 900;
                font-size: 150%;
            }

            #bottom_paging_control_wrapper select.page-number {
                margin: 0 10px;
                height: 34px;
            }

            #_top_paging_control_pages {
                margin: 0 10px;
            }

            #_top_paging_control_pages a {
                margin: 0 10px;
                font-weight: 300;
            }

            #_top_paging_control_pages a.current-page {
                font-weight: 900;
                font-size: 150%;
            }

            #top_paging_control_wrapper select.page-number {
                margin: 0 10px;
                height: 34px;
            }

            #results_wrapper {
                display: flex;
                width: 100%;
                padding: 0 20px;
            }

            #filter_sidebar {
                flex: 0 0 300px;
                border: 1px solid rgb(200, 200, 200);
                margin: 0 0 20px 20px;
                background: #fff;
                box-shadow: 0 1px 0.5px 0 rgba(177, 177, 177, 0.44);
                border-radius: 3px;
                max-width: 300px;
            }

            #sidebar_filter_title {
                padding: 20px;
                background-color: rgb(200, 200, 200);
                width: 100%;
                color: #ffffff;
                font-family: 'Muli', sans-serif;
                font-weight: 700;
                text-transform: uppercase;
                position: relative;
            }

            #results_count_wrapper {
                margin: 0 auto 20px auto;
                padding: 20px 40px;
                text-transform: uppercase;
            }

            h2#results_count_wrapper span {
                font-size: inherit;
            }

            h2#results_count_wrapper span.results-count {
                font-size: 2.4rem;
            }

            #sidebar_filters {
                margin-top: 10px;
            }

            .sidebar-filter {
                padding: 0;
                width: 100%;
                border-bottom: 1px solid rgb(200, 200, 200);
            }

            .sidebar-filter h3 {
                text-align: left;
                font-size: 1.0rem;
                font-weight: 700;
                position: relative;
                margin: 0 auto;
                color: rgb(50, 50, 50);
                padding: 5px 10px;
                cursor: pointer;
            }

            .sidebar-filter h3 span {
                position: absolute;
                right: 10px;
                top: 50%;
                transform: translate(0px, -50%);
                font-size: 1.2rem;
            }

            .sidebar-filter h3 span.fa-plus {
                display: inline;
            }

            .sidebar-filter h3 span.fa-minus {
                display: none;
            }

            .sidebar-filter.opened h3 span.fa-plus {
                display: none;
            }

            .sidebar-filter.opened h3 span.fa-minus {
                display: inline;
            }

            .sidebar-filter div.filter-options {
                display: none;
                padding-bottom: 10px;
            }

            .sidebar-filter.opened div.filter-options {
                display: block;
            }

            .sidebar-filter input[type=text] {
                font-size: .9rem;
                padding: 5px 10px;
                height: auto;
                width: 100%;
                max-width: 100%;
                margin: 0;
            }

            .sidebar-filter p {
                font-size: .9rem;
                font-weight: 300;
            }

            .sidebar-filter div label {
                font-weight: 300;
                font-size: .9rem;
                white-space: normal;
                display: block;
                flex: 1 1 auto;
                line-height: 1.2;
                cursor: pointer;
            }

            .sidebar-filter div.filter-checkboxes {
                margin-top: 5px;
                max-height: 150px;
                overflow: auto;
            }

            .sidebar-filter div.filter-options div.filter-option {
                margin: 0;
                display: flex;
                align-items: center;
            }

            .sidebar-filter div.filter-option-checkbox {
                padding: 0 10px 4px 10px;
                flex: 0 0 auto;
            }

            .sidebar-filter div.filter-option-label {
                flex: 1 1 auto;
            }

            div.selected_filters span.reductive-count {
                display: none;
            }

            div.selected-filter {
                width: 90%;
                margin: 0 auto 5px auto;
                font-size: .9rem;
                cursor: pointer;
            }

            div.selected-filter span.fa-check-square {
                margin-right: 10px;
                color: rgb(0, 150, 0);
                font-weight: 900;
                font-size: 1rem;
            }

            div.selected-filter.not-removable span.fa-check-square {
                visibility: hidden;
            }

            .sidebar-filter.no-filter-text p.filter-text-filter-wrapper {
                display: none;
            }

            p.filter-text-filter-wrapper {
                margin: 0 auto;
                width: 90%;
            }

            #_auction_item_details_content {
                font-family: 'Muli', serif;
                width: 100%;
                margin: 20px auto;
                padding-bottom: 20px;
            }

            #_auction_item_details_wrapper {
                display: flex;
                margin-bottom: 20px;
            }

            #_auction_item_details_image {
                flex: 1 1 50%;
                text-align: center;
                padding: 20px;
                position: relative;
                min-height: 300px;
            }

            #_auction_item_details_image img {
                max-height: 300px;
                max-width: 90%;
                display: block;
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
            }

            #_auction_item_details {
                flex: 1 1 50%;
                font-size: 1.4rem;
            }

            #_auction_item_details div {
                margin-bottom: 10px;
                color: rgb(88, 87, 87);
            }

            #_auction_item_details_description {
                font-size: 1.8rem;
                margin-bottom: 30px;
            }

            #_auction_item_details_full_page {
                width: 90%;
                margin: 0 auto 50px auto;
            }

            #_auction_item_details_detailed_description {
                width: 90%;
                margin: auto;
                font-size: 1.2rem;
            }

            #_auction_item_details_detailed_description p {
                letter-spacing: 1.5px;
                color: rgb(88, 87, 87);
            }

            #_auction_item_details_content h3 {
                width: 90%;
                margin: auto;
                display: none;
            }

            #_auction_item_details_specifications_wrapper {
                margin: 10px 0;
                display: none;
            }

            #_auction_item_details_price_wrapper {
                margin: 20px 0;
                font-size: 1.6rem;
            }

            #_auction_item_details_price {
                font-size: 2.0rem;
                font-weight: 600;
            }

            #_auction_item_details_quantity {
                text-align: right;
                padding: 5px 10px;
                border-radius: 4px;
                margin-right: 10px;
                width: 80px;
                font-size: 1.2rem;
            }

            #_auction_item_details_buttons {
                margin-top: 10px;
                display: flex;
                flex-direction: column;
                width: 50%;
            }

            #_auction_item_details_buttons button {
                width: auto;
                margin-bottom: 10px;
                margin-right: 10px;
                font-family: 'Black Ops One', sans-serif;
            }

            #_auction_item_details_brand {
                text-transform: uppercase;
            }

            #_auction_item_details_product_code {
                text-transform: uppercase;
            }

            #_auction_item_details_quantity_wrapper {
                text-transform: uppercase;
            }

            .product-details-specification {
                display: flex;
                width: 50%;
                margin: 0 60px;
            }

            .product-details-specification div {
                flex: 0 0 50%;
                padding: 8px 10px;
                box-shadow: 1px 0 0 0 rgb(220, 220, 220), 0 1px 0 0 #dcdcdc, 1px 1px 0 0 #dcdcdc, 1px 0 0 0 #dcdcdc inset, 0 1px 0 0 #dcdcdc inset;
            }

            .product-details-specification:nth-child(odd) {
                background-color: rgb(220, 220, 220);
            }

            @media (max-width: 1400px) {
                #_search_controls div {
                    padding-right: 10px;
                }

                #_search_results_outer label {
                    margin-right: 10px;
                }

                #_search_results_outer label {
                    display: block;
                }

                #_search_results_outer select {
                    height: 24px;
                    margin-top: 4px;
                }

                #_search_controls div#paging_control_wrapper {
                    padding-top: 15px;
                }
            }

            @media (max-width: 1000px) {
                #_search_controls {
                    display: block;
                }

                #_search_controls div {
                    margin-bottom: 10px;
                }

                #_search_controls div#paging_control_wrapper {
                    padding-top: 0;
                }
            }

            @media (max-width: 800px) {
                #_auction_item_details_wrapper {
                    flex-direction: column;
                }

                #_auction_item_details_image {
                    min-height: 150px;
                }

                #_auction_item_details_buttons {
                    width: 100%;
                }

                #_auction_item_details_image img {
                    max-height: 300px;
                    max-width: 90%;
                    display: block;
                    position: relative;
                    top: 0;
                    left: 0;
                    transform: none;
                    margin: auto;
                }

                #_auction_item_details_buttons {
                    margin-right: 0;
                }

                #_auction_item_details_content h3 {
                    text-align: center;
                }

                .product-details-specification {
                    width: 80%;
                    margin: auto;
                }

                #sidebar_filters {
                    max-height: 300px;
                    overflow-y: scroll;
                }
            }

            @media (max-width: 625px) {
                #results_wrapper {
                    flex-direction: column;
                }

                #_search_controls {
                    margin: 10px 0;
                }
            }

            @media (max-width: 800px) {
                #filter_sidebar {
                    margin: 0;
                }

                #_auction_item_details_description {
                    font-size: 1.5rem;
                    font-weight: 600;
                }

                #_auction_item_details_buttons button {
                    margin-bottom: 5px;
                    margin-right: 0;
                }

                #category_banner h1 {
                    font-size: 3rem;
                }
            }

            #_auction_item_details_content {
                width: 100%;
                margin: 20px auto;
                padding-bottom: 20px;
            }

            #_auction_item_details_wrapper {
                margin-bottom: 20px;
            }

            #_auction_item_details_image {
                text-align: center;
                padding: 20px;
                position: relative;
                min-height: 300px;
            }

            #_auction_item_details_image img {
                max-height: 300px;
                max-width: 90%;
                display: block;
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
            }

            #_auction_item_details {
                font-size: 1.4rem;
                display: block;
            }

            #_auction_item_details div {
                margin-bottom: 10px;
                color: rgb(88, 87, 87);
            }

            #_auction_item_details_description {
                font-size: 1.8rem;
                margin-bottom: 30px;
            }

            #_auction_item_details_full_page {
                width: 90%;
                margin: 0 auto 50px auto;
            }

            #_auction_item_details_detailed_description {
                width: 90%;
                margin: auto;
                font-size: 1.2rem;
            }

            #_auction_item_details_detailed_description p {
                letter-spacing: 1.5px;
                color: rgb(88, 87, 87);
            }

            #_auction_item_details_content h3 {
                width: 90%;
                margin: auto;
            }

            #_auction_item_details_specifications_wrapper {
                margin: 10px 0;
            }

            #_auction_item_details_price_wrapper {
                margin: 20px 0;
                font-size: 1.6rem;
            }

            #_auction_item_details_price {
                font-size: 2.0rem;
                font-weight: 600;
            }

            #_auction_item_details_quantity {
                text-align: right;
                padding: 5px 10px;
                border-radius: 4px;
                margin-right: 10px;
                width: 80px;
                font-size: 1.2rem;
            }

            #_auction_item_details_buttons {
                margin-top: 10px;
                display: flex;
                flex-direction: column;
                width: 50%;
            }

            #_auction_item_details_buttons button {
                width: auto;
                margin-bottom: 10px;
                margin-right: 10px;
                font-family: 'Black Ops One', sans-serif;
            }

            #_auction_item_details_brand {
                text-transform: uppercase;
            }

            #_auction_item_details_product_code {
                text-transform: uppercase;
            }

            #_auction_item_details_quantity_wrapper {
                text-transform: uppercase;
            }

            .product-details-specification {
                display: flex;
                width: 50%;
                margin: 0 60px;
            }

            .product-details-specification div {
                flex: 0 0 50%;
                padding: 8px 10px;
                box-shadow: 1px 0 0 0 rgb(220, 220, 220), 0 1px 0 0 #dcdcdc, 1px 1px 0 0 #dcdcdc, 1px 0 0 0 #dcdcdc inset, 0 1px 0 0 #dcdcdc inset;
            }

            .product-details-specification:nth-child(odd) {
                background-color: rgb(220, 220, 220);
            }

            #specifications_table tbody tr td.specification-name {
                font-weight: 600;
            }

            @media (max-width: 800px) {
                #_auction_item_details_wrapper {
                    flex-direction: column;
                }

                #_auction_item_details_image {
                    min-height: 150px;
                }

                #_auction_item_details_buttons {
                    width: 100%;
                }

                #_auction_item_details_image img {
                    max-height: 300px;
                    max-width: 90%;
                    display: block;
                    position: relative;
                    top: 0;
                    left: 0;
                    transform: none;
                    margin: auto;
                }

                #_auction_item_details_buttons {
                    margin-right: 0;
                }

                #_auction_item_details_content h3 {
                    text-align: center;
                }

                .product-details-specification {
                    width: 80%;
                    margin: auto;
                }

                #_search_controls select {
                    margin-right: 20px;
                }
            }

            @media (max-width: 625px) {
                #_search_controls {
                    margin: 10px 0;
                }
            }

            @media only screen and (max-width: 800px) {
                #_auction_item_details_description {
                    font-size: 1.5rem;
                    font-weight: 600;
                }

                #_auction_item_details_buttons button {
                    margin-bottom: 5px;
                    margin-right: 0;
                }
            }

            #_search_results_wrapper {
                display: flex;
                flex-wrap: wrap;
            }

            .auction-item {
                width: 280px;
                max-width: 350px;
                margin: 0 20px 20px 0;
                border: 1px solid rgb(200, 200, 200);
                padding: 20px;
                line-height: 1.2;
                background: #fff;
            }

            .auction-item:hover {
                box-shadow: 0 1px 5px #aaa;
                border: 1px solid rgba(68, 68, 68, 0.62);
            }

            .auction-item .info-label {
                font-size: 90%;
                margin-right: 10px;
            }

            .auction-item img {
                max-width: 100%;
            }

            .click-product-detail {
                cursor: pointer;
            }

            .click-product-detail a:hover {
                color: rgb(140, 140, 140);
            }

            .auction-item-description {
                font-size: 1.1rem;
                text-align: center;
                font-weight: 700;
                height: 110px;
                overflow: hidden;
                position: relative;
            }

            .auction-item-description:after {
                content: "";
                position: absolute;
                top: 90px;
                left: 0;
                height: 20px;
                width: 100%;
                background: linear-gradient(rgba(255, 255, 255, 0), rgb(255, 255, 255));
            }

            .auction-item-detailed-description {
                font-size: .8rem;
                margin-bottom: 10px;
                height: 100px;
                overflow: hidden;
                position: relative;
            }

            .auction-item-detailed-description:after {
                content: "";
                position: absolute;
                top: 60px;
                left: 0;
                height: 40px;
                width: 100%;
                background: linear-gradient(rgba(255, 255, 255, 0), rgb(255, 255, 255));
            }

            .auction-item-price-wrapper {
                font-size: 1.5rem;
                font-weight: 700;
                margin-bottom: 20px;
                margin-top: 10px;
                text-align: center;
            }

            .auction-item-thumbnail {
                text-align: center;
                margin-bottom: 10px;
                height: 120px;
                position: relative;
            }

            .auction-item-thumbnail img {
                max-height: 120px;
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                cursor: zoom-in;
            }

            .button-subtext {
                display: none;
            }

            .map-priced-product .button-subtext {
                display: inline;
            }

            #result_display_type_wrapper {
                width: 120px;
                max-width: 120px;
            }

            #result_display_type_wrapper span {
                font-size: 30px;
                cursor: pointer;
                position: absolute;
                top: 50%;
                left: 30%;
                transform: translate(-50%, -50%);
                color: rgb(200, 200, 200);
            }

            #result_display_type_wrapper span:last-child {
                left: 70%;
            }

            #result_display_type_wrapper span.selected {
                color: rgb(0, 125, 0);
            }

            #result_display_type_wrapper span:hover {
                color: rgb(100, 100, 100);
            }

            @media only screen and (max-width: 1050px) {
                #result_display_type_wrapper {
                    display: none;
                }
            }

            .catalog-result-product-tag {
                display: none;
                padding: 2px;
                margin-right: 4px;
                margin-bottom: 4px;
                color: rgb(255, 255, 255);
            }

            <?php
				$resultSet = executeReadQuery("select * from product_tags where client_id = ? and display_color is not null and internal_use_only = 0 and " .
					"inactive = 0 and product_tag_id in (select product_tag_id from auction_item_product_tag_links) order by sort_order,description", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					?>
            .auction-item.product-tag-code-<?= strtolower(str_replace("_","-",$row['product_tag_code'])) ?> .catalog-result-product-tag.catalog-result-product-tag-<?= strtolower(str_replace("_","-",$row['product_tag_code'])) ?> {
                display: inline-block;
            }

            .catalog-result-product-tag.catalog-result-product-tag-<?= strtolower(str_replace("_","-",$row['product_tag_code'])) ?> {
                background-color: <?= $row['display_color'] ?>;
            }

            <?php
			}
	?>

        </style>
		<?php
	}
}

$pageObject = new AuctionSearchResultsPage();
$pageObject->displayPage();
