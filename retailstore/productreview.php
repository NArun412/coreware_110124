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

$GLOBALS['gPageCode'] = "RETAILSTOREPRODUCTREVIEW";
$GLOBALS['gCacheProhibited'] = true;
require_once "shared/startup.inc";

class RetailStoreProductReviewPage extends Page {

	var $iProductId = "";

	function setup() {
		$productId = $_GET['product_id'];
		if (empty($productId)) {
			$productId = $_POST['product_id'];
		}
		if (empty($productId)) {
			$productId = $_GET['id'];
		}
		if (empty($productId) && !empty($_GET['upc_code'])) {
			$productId = getFieldFromId("product_id", "product_data", "upc_code", $_GET['upc_code']);
		}
		$this->iProductId = getFieldFromId("product_id", "products", "product_id", $productId, "inactive = 0 and internal_use_only = 0");
		if (empty($this->iProductId)) {
			header("Location: /");
			exit;
		}
		$reviewRequiresUser = getPreference("RETAIL_STORE_REVIEW_REQUIRES_USER");
		if (!empty($reviewRequiresUser) && !$GLOBALS['gLoggedIn']) {
			header("Location: /");
			exit;
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "save_review":
				if (empty($_POST['title_text']) || empty($_POST['content']) || empty($_POST['product_id'])) {
					$returnArray['error_message'] = "Missing information" . ($GLOBALS['gUserRow']['superuser_flag'] ? ": " . jsonEncode($_POST) : "");
					ajaxResponse($returnArray);
					break;
				}
				$requiresApproval = getPreference("RETAIL_STORE_REVIEWS_REQUIRE_APPROVAL");
				$resultSet = executeQuery("insert into product_reviews (product_id,user_id,reviewer,date_created,rating,title_text,content,requires_approval) values (?,?,?,current_date,?,?,?,?)",
					$_POST['product_id'], $GLOBALS['gUserId'], $_POST['reviewer'], $_POST['rating'], $_POST['title_text'], $_POST['content'], (empty($requiresApproval) ? 0 : 1));
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = "Unable to save review";
				} else {
					$returnArray['info_message'] = "Product review successfully saved";
					if ($requiresApproval) {
						$pageLink = getFieldFromId("link_name", "pages", "script_filename", "productreviewmaintenance.php", "client_id = " . $GLOBALS['gClientId']);
						$link = sprintf("%s/%s?url_page=show&clear_filter=true&primary_id=%s", getDomainName(), $pageLink, $resultSet['insert_id']);
						sendEmail(array("subject" => "Product Review", "body" => "A product review has been submitted and requires approval: " . $link, "notification_code" => "PRODUCT_REVIEW"));
					}
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#save_review").click(function () {
                if (empty($("#rating").val())) {
                    displayErrorMessage("Please select a star rating");
                    return false;
                }
                for (let instance in CKEDITOR.instances) {
                    CKEDITOR.instances[instance].updateElement();
                }
                if ($("#_edit_form").validationEngine("validate")) {
                    $("#save_review").hide();
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?url_action=save_review", $("#_edit_form").serialize(), function(returnArray) {
                        if ("error_message" in returnArray) {
                            $("#save_review").show();
                            return;
                        }
                        $("body").data("just_saved", "true");
                        setTimeout(function () {
                            document.location = "/";
                        }, 2000);
                    });
                }
                return false;
            });
            $(".star-rating").mouseover(function () {
                const label = $(this).data("label");
                $("#star_label").html(label);
            }).mouseout(function () {
                $("#star_label").html("");
                if ($(".star-rating.set-rating").length > 0) {
                    const label = $(".star-rating.set-rating").data("label");
                    $("#star_label").html(label);
                }
            }).click(function () {
                let starRating = $(this).data("star_number");
                $("#rating").val(starRating);
                $(".star-rating").removeClass("selected").removeClass("fas").addClass("far").removeClass("set-rating");
                $(this).addClass("set-rating");
                while (starRating > 0) {
                    $("#star_rating_" + starRating).addClass("selected").removeClass("far").addClass("fas");
                    starRating--;
                }
            });
            addCKEditor();
        </script>
		<?php
	}

	function mainContent() {
		echo $this->iPageData['content'];
		$productRow = ProductCatalog::getCachedProductRow($this->iProductId);
		?>
        <div id="_product_review_wrapper">

            <h1>Product Review</h1>
            <h2>Review for</h2>
            <div id="product_information">
                <div id="product_image"><img alt="product image" src="<?= ProductCatalog::getProductImage($productRow['product_id']) ?>"></div>
                <div id="product_description"><?= htmlText($productRow['description']) ?></div>
            </div>
            <form id="_edit_form">
				<?php
				if (!$GLOBALS['gLoggedIn']) {
					echo createFormLineControl("product_reviews", "reviewer", array("not_null" => true, "form_label" => "Your Name"));
				} else {
					?>
                    <p>Reviewing product as <?= getUserDisplayName() ?></p>
					<?php
				}
				?>
                <input type="hidden" id="rating" name="rating" value="">
                <input type="hidden" id="product_id" name="product_id" value="<?= $productRow['product_id'] ?>">
                <div class="form-line" id="_star_rating_row">
                    <label id="star_label"></label>
                    <span class="star-rating far fa-star" id="star_rating_1" data-star_number="1" data-label="It's terrible"></span><span class="star-rating far fa-star" id="star_rating_2" data-star_number="2" data-label="It's not good"></span><span class="star-rating far fa-star" id="star_rating_3" data-star_number="3" data-label="It's Ok"></span><span class="star-rating far fa-star" id="star_rating_4" data-star_number="4" data-label="It's good"></span><span class="star-rating far fa-star" id="star_rating_5" data-star_number="5" data-label="It's Awesome"></span>
                    <div class='clear-div'></div>
                </div>
				<?php
				echo createFormLineControl("product_reviews", "title_text", array("form_label" => "Review Title", "data_type" => "varchar", "inline-width" => "500px", "not_null" => true));
				echo createFormLineControl("product_reviews", "content", array("not_null" => true, "form_label" => "Review Details", "classes"=>"ck-editor"));
				?>
                <p id="error_message" class="error-message"></p>
                <p id="save_review_wrapper">
                    <button id="save_review">Save Review</button>
                </p>
            </form>
        </div>
		<?php
		echo $this->iPageData['after_form_content'];
		return true;
	}

	function internalCSS() {
		?>
        <style>
            #product_information {
                display: flex;
                margin-bottom: 40px;
            }
            #product_image {
                flex: 1 1 350px;
                vertical-align: middle;
                max-width: 350px;
            }
            #product_image img {
                width: 90%;
            }
            #product_description {
                font-size: 2rem;
                padding-left: 20px;
            }
            span.star-rating {
                color: rgb(100, 100, 100);
                font-size: 2.5rem;
                margin-right: 5px;
            }
            span.star-rating.selected {
                color: rgb(205, 160, 75);
            }
            span.star-rating:hover {
                color: rgb(95, 140, 205);
            }
            #_star_rating_row {
                margin-bottom: 20px;
            }
            #star_label {
                height: 40px;
                font-weight: 900;
                color: rgb(150, 150, 150);
            }
            #content {
                width: 600px;
                max-width: 100%;
                height: 200px;
            }
            #save_review_wrapper {
                margin-top: 20px;
            }
        </style>
		<?php
	}
}

$pageObject = new RetailStoreProductReviewPage();
$pageObject->displayPage();
