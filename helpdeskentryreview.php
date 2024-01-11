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

$GLOBALS['gPageCode'] = "HELPDESKENTRYREVIEW";
require_once "shared/startup.inc";

class HelpDeskEntryReview extends Page {

	var $iHelpDeskEntryRow = array();

	function setup() {
		if (empty($_GET['ajax'])) {
			$this->iHelpDeskEntryRow = getRowFromId("help_desk_entries", "help_desk_entry_id", $_GET['id']);
			$hashCode = md5($this->iHelpDeskEntryRow['contact_id'] . ":" . $this->iHelpDeskEntryRow['time_submitted']);
			if (empty($this->iHelpDeskEntryRow) || $hashCode != $_GET['hash']) {
				header("Location: /");
				exit;
			}
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "submit_review":
				$helpDeskEntryId = getFieldFromId("help_desk_entry_id", "help_desk_entries", "help_desk_entry_id", $_POST['help_desk_entry_id'],
					"time_submitted = ?", $_POST['time_submitted']);
				if (empty($helpDeskEntryId)) {
					$returnArray['error_message'] = "Unable to create review";
					ajaxResponse($returnArray);
					exit;
				}
				$helpDeskEntryReviewId = getFieldFromId("help_desk_entry_review_id", "help_desk_entry_reviews", "help_desk_entry_review_id", $_POST['help_desk_entry_review_id'],
					"help_desk_entry_id = ?", $helpDeskEntryId);
				$dataTable = new DataTable("help_desk_entry_reviews");
				$nameValues = array("help_desk_entry_id" => $helpDeskEntryId, "comments" => $_POST['comments']);
				if (!$helpDeskEntryReviewId = $dataTable->saveRecord(array("name_values" => $nameValues, "primary_id" => $helpDeskEntryReviewId))) {
					$returnArray['error_message'] = "Unable to create review";
					ajaxResponse($returnArray);
					exit;
				}
				$dataTable = new DataTable("help_desk_entry_review_answers");
				foreach ($_POST as $fieldName => $fieldValue) {
					if (!substr($fieldName, 0, strlen("help_desk_review_question_id_")) == "help_desk_review_question_id_") {
						continue;
					}
					$helpDeskReviewQuestionId = getFieldFromId("help_desk_review_question_id", "help_desk_review_questions", "help_desk_review_question_id",
						substr($fieldName, strlen("help_desk_review_question_id_")));
					if (empty($helpDeskReviewQuestionId)) {
						continue;
					}
					$helpDeskEntryReviewAnswerId = getFieldFromId("help_desk_entry_review_answer_id", "help_desk_entry_review_answers", "help_desk_entry_review_id", $helpDeskEntryReviewId,
						"help_desk_review_question_id = ?", $helpDeskReviewQuestionId);
					$nameValues = array("content" => $fieldValue, "help_desk_entry_review_id" => $helpDeskEntryReviewId, "help_desk_review_question_id" => $helpDeskReviewQuestionId);
					$dataTable->saveRecord(array("name_values" => $nameValues, "primary_id" => $helpDeskEntryReviewAnswerId));
				}
				$response = $this->getFragment("HELP_DESK_REVIEW_RESPONSE");
				if (empty($response)) {
					$response = "<p>Thank you for your feedback.</p>";
				}
				$returnArray['response'] = $response;
				ajaxResponse($returnArray);
				break;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", "#submit_form", function () {
                if ($("#_review_form").validationEngine("validate")) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=submit_review", $("#_review_form").serialize(), function(returnArray) {
                        if (!("error_message" in returnArray)) {
                            if ("response" in returnArray) {
                                $("#_questionnaire_wrapper").html(returnArray['response']);
                            } else {
                                $("#_questionnaire_wrapper").html("<p class='info-message'>Thank you for your feedback!</p>");
                            }
                            setTimeout(function () {
                                document.location = "/";
                            }, 5000);
                        }
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
            #_questionnaire_wrapper {

            h1 {
                margin: 10px 0 0;
            }

            h2 {
                margin: 10px 0 0;
            }

            #_review_form {
                margin: 40px 0 0;
            }

            }
        </style>
		<?php
	}

	function mainContent() {
		echo $this->iPageData['content'];
		$resultSet = executeQuery("select * from help_desk_review_questions where inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " order by sort_order,description");
		$helpDeskEntryReviewRow = getRowFromId("help_desk_entry_reviews", "help_desk_entry_id", $this->iHelpDeskEntryRow['help_desk_entry_id']);
		?>
        <div id="_questionnaire_wrapper">
            <h1>Review for Ticket #<?= $this->iHelpDeskEntryRow['help_desk_entry_id'] ?></h1>
            <h2><?= htmlText($this->iHelpDeskEntryRow['description']) ?></h2>
            <form id="_review_form">
                <input type='hidden' id="help_desk_entry_review_id" name="help_desk_entry_review_id" value="<?= $helpDeskEntryReviewRow['help_desk_entry_review_id'] ?>">
                <input type='hidden' id="help_desk_entry_id" name="help_desk_entry_id" value="<?= $this->iHelpDeskEntryRow['help_desk_entry_id'] ?>">
                <input type='hidden' id="time_submitted" name="time_submitted" value="<?= $this->iHelpDeskEntryRow['time_submitted'] ?>">
				<?php
				while ($row = getNextRow($resultSet)) {
					$answer = getFieldFromId("content", "help_desk_entry_review_answers", "help_desk_entry_review_id", $helpDeskEntryReviewRow['help_desk_entry_review_id'],
						"help_desk_review_question_id = ?", $row['help_desk_review_question_id']);
					switch ($row['data_type']) {
						case "tinyint":
							?>
                            <div class='form-line' id="_help_desk_review_question_id_<?= $row['help_desk_review_question_id'] ?>_row">
                                <input type='checkbox'<?= (empty($answer) ? "" : " checked") ?> id="help_desk_review_question_id_<?= $row['help_desk_review_question_id'] ?>" name="help_desk_review_question_id_<?= $row['help_desk_review_question_id'] ?>" value="1"><label class='checkbox-label' for='help_desk_review_question_id_<?= $row['help_desk_review_question_id'] ?>'><?= $row['content'] ?></label>
                                <div class='clear-div'></div>
                            </div>
							<?php
							break;
						case "select":
							if (!empty($row['choices'])) {
								?>
                                <div class='form-line' id="_help_desk_review_question_id_<?= $row['help_desk_review_question_id'] ?>_row">
                                    <label for='help_desk_review_question_id_<?= $row['help_desk_review_question_id'] ?>'><?= $row['content'] ?></label>
                                    <select id="help_desk_review_question_id_<?= $row['help_desk_review_question_id'] ?>" name="help_desk_review_question_id_<?= $row['help_desk_review_question_id'] ?>">
                                        <option value=''>[Select]</option>
										<?php
										$choices = getContentLines($row['choices']);
										foreach ($choices as $thisChoice) {
											?>
                                            <option value='<?= $thisChoice ?>'<?= ($answer == $thisChoice ? " selected" : "") ?>><?= $thisChoice ?></option>
											<?php
										}
										?>
                                    </select>
                                    <div class='clear-div'></div>
                                </div>
								<?php
							}
							break;
						case "text":
							?>
                            <div class='form-line' id="_help_desk_review_question_id_<?= $row['help_desk_review_question_id'] ?>_row">
                                <label for='help_desk_review_question_id_<?= $row['help_desk_review_question_id'] ?>'><?= $row['content'] ?></label>
                                <textarea id="help_desk_review_question_id_<?= $row['help_desk_review_question_id'] ?>" name="help_desk_review_question_id_<?= $row['help_desk_review_question_id'] ?>"><?= htmlText($answer) ?></textarea>
                                <div class='clear-div'></div>
                            </div>
							<?php
							break;
					}
				}
				?>
                <div class='form-line' id="_comments_row">
                    <label for='comments'>Other Comments</label>
                    <textarea id="comments" name="comments"><?= htmlText($helpDeskEntryReviewRow['comments']) ?></textarea>
                    <div class='clear-div'></div>
                </div>
            </form>
            <p>
                <button id="submit_form">Submit</button>
            </p>
        </div>
		<?php
		echo $this->iPageData['after_form_content'];
		return true;
	}
}

$pageObject = new HelpDeskEntryReview();
$pageObject->displayPage();
