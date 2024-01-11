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

$GLOBALS['gPageCode'] = "PRODUCTREVIEWMAINT";
require_once "shared/startup.inc";

class ProductReviewMaintenancePage extends Page {

	function setup() {
		$filters = array();
		$filters['requires_approval'] = array("form_label" => "Requires Approval", "where" => "requires_approval = 1", "data_type" => "tinyint");
		$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("approve_selected", "Approve Selected Reviews");
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("make_selected_inactive", "Set Selected As Inactive");
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "approve_selected":
				$resultSet = executeQuery("update product_reviews set requires_approval = 0 where product_review_id in (select primary_identifier from selected_rows where page_id = ? and user_id = ?)", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				$returnArray['info_message'] = $resultSet['affected_rows'] . " reviews marked as approved";
				ajaxResponse($returnArray);
				break;
			case "make_selected_inactive":
				$resultSet = executeQuery("update product_reviews set inactive = 1 where product_review_id in (select primary_identifier from selected_rows where page_id = ? and user_id = ?)", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				$returnArray['info_message'] = $resultSet['affected_rows'] . " reviews marked inactive";
				ajaxResponse($returnArray);
				break;
		}
	}

	function javascript() {
		?>
        <script>
            function customActions(actionName) {
                if (actionName === "approve_selected") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=approve_selected", function (returnArray) {
                        if (!("error_message" in returnArray)) {
                            getDataList();
                        }
                    });
                    return true;
                }
                if (actionName === "make_selected_inactive") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=make_selected_inactive", function (returnArray) {
                        if (!("error_message" in returnArray)) {
                            getDataList();
                        }
                    });
                    return true;
                }
                return false;
            }
        </script>
		<?php
	}

	function massageDataSource() {
		$this->iDataSource->setFilterWhere("product_id in (select product_id from products where client_id = " . $GLOBALS['gUserRow']['client_id'] . ")");
		$this->iDataSource->addColumnControl("content", "classes", "ck-editor");
		$this->iDataSource->addColumnControl("response_content", "classes", "ck-editor");
	}

}

$pageObject = new ProductReviewMaintenancePage("product_reviews");
$pageObject->displayPage();
