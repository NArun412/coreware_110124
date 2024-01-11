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

$GLOBALS['gPageCode'] = "PRODUCTQUESTIONMAINT";
require_once "shared/startup.inc";

class ProductQuestionMaintenancePage extends Page {

	function setup() {
		$filters = array();
		$filters['requires_approval'] = array("form_label" => "Requires Approval", "where" => "requires_approval = 1", "data_type" => "tinyint");
		$filters['answers_requires_approval'] = array("form_label" => "Has answers that require approval", "where" => "product_question_id in (select product_question_id from product_answers where requires_approval = 1)", "data_type" => "tinyint");
		$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("approve_selected", "Approve Selected Questions");
	}

	function massageDataSource() {
		$this->iDataSource->getPrimaryTable()->setSubTables("product_answers");
		$this->iDataSource->addColumnControl("product_answers", "title_generator", "answerTitle");
		$this->iDataSource->addColumnControl("product_answers", "data_type", "custom");
		$this->iDataSource->addColumnControl("product_answers", "control_class", "FormList");
		$this->iDataSource->addColumnControl("product_answers", "list_table", "product_answers");
		$this->iDataSource->addColumnControl("product_answers", "form_label", "Answers");
		$this->iDataSource->addColumnControl("product_answers", "after_add_row", "afterAddAnswer");
		$this->iDataSource->addColumnControl("product_answers", "list_table_controls", array("content"=>array("classes"=>"answer-content"),
            "full_name"=>array("form_label"=>"Name of person answering question","help_label"=>"leave blank to use name of user"),
            "user_id"=>array("readonly"=>true,"default_value"=>$GLOBALS['gUserId'],"form_label"=>"User who created answer")));

		$this->iDataSource->addColumnControl("content", "classes", "ck-editor");
		$this->iDataSource->addColumnControl("full_name", "form_label", "Name of person asking question");
		$this->iDataSource->addColumnControl("full_name", "help_label", "leave blank to use name of user");
		$this->iDataSource->addColumnControl("user_id", "data_type", "user_picker");
		$this->iDataSource->addColumnControl("user_id", "default_value", $GLOBALS['gUserId']);
		$this->iDataSource->addColumnControl("user_id", "form_label", "User who created question");
		$this->iDataSource->addColumnControl("user_id", "readonly", true);
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "approve_selected":
				$resultSet = executeQuery("update product_questions set requires_approval = 0 where product_question_id in (select primary_identifier from selected_rows where page_id = ? and user_id = ?)", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				$returnArray['info_message'] = $resultSet['affected_rows'] . " questions marked as approved";
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
                return false;
            }
            function afterAddAnswer() {
                $("#_product_answers_row").find(".answer-content").addClass("ck-editor");
                addCKEditor();
            }
            function answerTitle(listName) {
                const $listName = $("#" + listName);
                let textContent = $listName.find("textarea.answer-content").val();
                if (!empty(textContent) && textContent.length > 30) {
                    textContent = textContent.substr(0,30);
                }
                return textContent;
            }
        </script>
		<?php
	}

}

$pageObject = new ProductQuestionMaintenancePage("product_questions");
$pageObject->displayPage();
