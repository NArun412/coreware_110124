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

$GLOBALS['gPageCode'] = "RETAILSTOREPRODUCTDETAILS";
$GLOBALS['gCacheProhibited'] = true;
require_once "shared/startup.inc";

class RetailStoreProductDetailsPage extends Page {

	var $iProductId = "";
	var $iProductDetails = null;
	var $iProductRow = null;

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

        $this->setProductId();

		if (empty($this->iProductId)) {
			header("Location: /");
			exit;
		}
		PlaceHolders::setPlaceholderKeyValue("product_id",$this->iProductId);
		$this->iProductDetails = new ProductDetails($this->iProductId);
		if (strpos($_SERVER['REQUEST_URI'], "?id=") !== false || strpos($_SERVER['REQUEST_URI'], "&id=") !== false) {
			$this->iProductRow = ProductCatalog::getCachedProductRow($this->iProductId);
			$urlAliasTypeCode = getUrlAliasTypeCode("products","product_id", "id");
			if (!empty($urlAliasTypeCode) && !empty($this->iProductRow['link_name'])) {
				$linkUrl = "/" . $urlAliasTypeCode . "/" . $this->iProductRow['link_name'];
				header("HTTP/1.1 301 Moved Permanently");
				header("Location: " . $linkUrl);
				exit();
			}
		}

        $metaKeywords = CustomField::getCustomFieldData($this->iProductId,"META_KEYWORDS","PRODUCTS");
        if (empty($metaKeywords)) {
            $categories = "";
            $resultSet = executeReadQuery("select * from product_categories where product_category_id in " .
                "(select product_category_id from product_category_links where product_id = ?) and inactive = 0 and internal_use_only = 0", $this->iProductId);
            while ($row = getNextRow($resultSet)) {
                $categories .= (empty($categories) ? "" : ",") . $row['description'];
            }
            $metaKeywords = $categories;
        }
		$GLOBALS['gPageRow']['meta_keywords'] = $metaKeywords;

		$metaDescription = CustomField::getCustomFieldData($this->iProductId,"META_DESCRIPTION","PRODUCTS");
		if (empty($metaDescription)) {
			$metaDescription = getReadFieldFromId("description", "products", "product_id", $this->iProductId);
		}
		$GLOBALS['gPageRow']['meta_description'] = $metaDescription;
	}

    function setProductId() {
        if (empty($_GET['product_id']) && empty($_GET['id']) && !empty($_GET['product_category_code'])) {
            $productCategoryId = getReadFieldFromId("product_category_id", "product_categories", "product_category_code", $_GET['product_category_code']);

            $resultSet = executeReadQuery("select product_id from product_category_links where product_category_id = ? and product_id in (select product_id from products where " .
                "(product_manufacturer_id is null or product_manufacturer_id not in (select product_manufacturer_id from product_manufacturers where cannot_sell = 1" . ($GLOBALS['gLoggedIn'] ? "" : " or requires_user = 1") . ")) and " .
                ($GLOBALS['gLoggedIn'] ? "" : "product_id not in (select product_id from product_tag_links where product_tag_id in (select product_tag_id from product_tags where requires_user = 1)) and ") .
                (empty($GLOBALS['gUserRow']['user_type_id']) ? "" : "product_id not in (select product_id from product_tag_links where product_tag_id in (select product_tag_id from user_type_product_tag_restrictions where user_type_id = " . $GLOBALS['gUserRow']['user_type_id'] . ")) and ") .
                (empty($GLOBALS['gUserRow']['user_type_id']) ? "" : "product_manufacturer_id not in (select product_manufacturer_id from user_type_product_manufacturer_restrictions where user_type_id = " . $GLOBALS['gUserRow']['user_type_id'] . ") and ") .
                "inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and product_id not in (select product_id from product_category_links where product_category_id in " .
                "(select product_category_id from product_categories where cannot_sell = 1 or product_category_code in ('INACTIVE'" . ($GLOBALS['gInternalConnection'] ? "" : ",'INTERNAL_USE_ONLY'") . "))) and product_id not in " .
                "(select product_id from product_tag_links where product_tag_id in (select product_tag_id from product_tags where cannot_sell = 1))) order by " . (empty($_GET['random']) ? "product_id" : "rand()"), $productCategoryId);
            if ($row = getNextRow($resultSet)) {
                $_GET['product_id'] = $row['product_id'];
            }
        }

        if (empty($_GET['product_id']) && !empty($_GET['id'])) {
            $_GET['product_id'] = $_GET['id'];
        }
        $this->iProductId = getReadFieldFromId("product_id", "products", "product_id", $_GET['product_id'],
            "(product_manufacturer_id is null or product_manufacturer_id not in (select product_manufacturer_id from product_manufacturers where cannot_sell = 1)) and " .
            "inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and product_id not in (select product_id from product_category_links where product_category_id in " .
            "(select product_category_id from product_categories where cannot_sell = 1 or product_category_code in ('INACTIVE'" . ($GLOBALS['gInternalConnection'] ? "" : ",'INTERNAL_USE_ONLY'") . "))) and product_id not in " .
            "(select product_id from product_tag_links where product_tag_id in (select product_tag_id from product_tags where cannot_sell = 1))");
    }

	function headerIncludes() {
		if (!empty($this->iProductId)) {
		    $this->iProductDetails->loadProductRow();
		    $this->iProductRow = $this->iProductDetails->iProductRow;
			$urlAliasTypeCode = getUrlAliasTypeCode("products","product_id", "id");
			$linkUrl = (empty($urlAliasTypeCode) || empty($this->iProductRow['link_name']) ? "product-details?id=" . $this->iProductId : $urlAliasTypeCode . "/" . $this->iProductRow['link_name']);
			$metaDescription = CustomField::getCustomFieldData($this->iProductId,"META_DESCRIPTION","PRODUCTS");
			if (empty($metaDescription)) {
				$metaDescription = $this->iProductRow['description'];
			}

            $canonicalLink = CustomField::getCustomFieldData($this->iProductId,"CANONICAL_LINK","PRODUCTS");
            if (empty($canonicalLink)) {
                $canonicalLink = $linkUrl;
            }
            $GLOBALS['gCanonicalLink'] = '<link rel="canonical" href="https://' . $_SERVER['HTTP_HOST'] . '/' . $canonicalLink . '"/>';
			?>
            <meta property="og:title" content="<?= str_replace('"', "'", $metaDescription) ?>"/>
            <meta property="og:type" content="product"/>
            <meta property="og:url" content="https://<?= $_SERVER['HTTP_HOST'] ?>/<?= $linkUrl ?>"/>
            <meta property="og:image" content="<?= $this->iProductRow['image_url'] ?>"/>
            <meta property="og:description" content="<?= str_replace('"', "'", $this->iProductRow['detailed_description']) ?>"/>
            <?php if(!empty(getPreference("RETAIL_STORE_USE_PRODUCT_SCHEMA"))) { ?>
            <script type="application/ld+json">
                [ {
                    "@context" : "http://schema.org",
                    "@id": "<?= getDomainName() ?>/#Product",
                    "@type" : "Product",
                    "mpn" : "<?= $this->iProductRow['upc_code'] ?>",
                    "sku" : "<?= $this->iProductRow['manufacturer_sku'] ?>",
                    "name" : "<?= htmlText(strip_tags($this->iProductRow['description'])) ?>",
                    "image" : "<?= $this->iProductRow['image_url']?>",
                    "description" : "<?= htmlText(strip_tags($this->iProductRow['detailed_description'])) ?>",
                    "brand" : {
                        "@type" : "Brand",
                        "name" : "<?= htmlText(strip_tags($this->iProductRow['manufacturer_name'])) ?>",
                        "url" : "<?= empty($this->iProductRow['product_manufacturer_url']) ? getDomainName() : $this->iProductRow['product_manufacturer_url'] ?>"
                    },
                    "offers" : {
                        "@type" : "Offer",
                        "availability": "<?= $this->iProductRow['inventory_count'] > 0 ? "https://schema.org/InStock" : "https://schema.org/OutOfStock" ?>",
                        "price" : "<?= str_replace(",","",str_replace("$","", $this->iProductRow['sale_price'])) ?>",
                        "priceCurrency": "USD",
                        "priceValidUntil":"<?= date("Y-m-d", strtotime("+1 month")) ?>",
                        "url" : "<?= $this->iProductRow['product_url'] ?>"
                    }
                    <?php if (!empty($this->iProductRow['star_reviews'])) { ?>
                    ,"aggregateRating": {
                       "@type": "AggregateRating",
                       "ratingValue": "<?= $this->iProductRow['star_rating'] ?>",
                       "reviewCount": "<?= $this->iProductRow['review_count'] ?>"
                    }
                  <?php }
                if (!empty($this->iProductRow['sample_reviews_data'])) {
                    ?>
                    ,"review": [
                    <?php
                    $count = 1;
                    foreach($this->iProductRow['sample_reviews_data'] as $thisReview) {
                    ?>
                    <?= $count++ == 1 ? "" : "," ?> {
                        "@type": "Review",
                        "author": "<?= $thisReview['reviewer'] ?>",
                        "datePublished": "<?= $thisReview['date_created'] ?>",
                        "name": "<?= htmlText($thisReview['title_text']) ?>",
                        "reviewBody": "<?= htmlText($thisReview['content']) ?>",
                        "reviewRating": {
                             "@type": "Rating",
                             "ratingValue": "<?= $thisReview['star_rating'] ?>"
                          } }
                  <?php } ?>
                  ]
                <?php } ?>
                } ]
            </script>
			<?php }
			return true;
		}
		return false;
	}

	function setPageTitle() {
        $this->setProductId();

		if (!empty($this->iProductId)) {
			$metaDescription = CustomField::getCustomFieldData($this->iProductId,"PAGE_TITLE","PRODUCTS");
			if (empty($metaDescription)) {
				$metaDescription = getReadFieldFromId("description", "products", "product_id", $this->iProductId,
						"(product_manufacturer_id is null or product_manufacturer_id not in (select product_manufacturer_id from product_manufacturers where cannot_sell = 1)) and " .
						"inactive = 0 and internal_use_only = 0 and product_id not in (select product_id from product_category_links where product_category_id in " .
						"(select product_category_id from product_categories where cannot_sell = 1 or product_category_code in ('INACTIVE','INTERNAL_USE_ONLY'))) and product_id not in " .
						"(select product_id from product_tag_links where product_tag_id in (select product_tag_id from product_tags where cannot_sell = 1))") . " | " . $GLOBALS['gClientName'];
			}
			return $metaDescription;
		}
		return false;
	}

	function inlineJavascript() {
		echo $this->iProductDetails->inlineJavascript();
	}

	function onLoadJavascript() {
		echo $this->iProductDetails->onLoadJavascript();
	}

	function javascript() {
		echo $this->iProductDetails->javascript();
	}

	function mainContent() {
		echo $this->iPageData['content'];
		echo $this->iProductDetails->mainContent();
		echo $this->iPageData['after_form_content'];
		return true;
	}

	function internalCSS() {
		echo $this->iProductDetails->internalCSS();
	}

	function hiddenElements() {
		echo $this->iProductDetails->hiddenElements();
	}

}

$pageObject = new RetailStoreProductDetailsPage();
$pageObject->displayPage();
