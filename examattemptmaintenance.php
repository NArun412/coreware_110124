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

$GLOBALS['gPageCode'] = "EXAMATTEMPTMAINT";
require_once "shared/startup.inc";

class ExamAttemptMaintenancePage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$filters = array();
			$filters['hide_completed'] = array("form_label" => "Hide Completed", "where" => "date_completed is null", "data_type" => "tinyint", "set_default" => true);
			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("add"));
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("email_address", "data_type", "varchar");
		$this->iDataSource->addColumnControl("email_address", "form_label", "User");
		$this->iDataSource->addColumnControl("email_address", "select_value", "select email_address from contacts where contact_id = (select contact_id from users where user_id = exam_attempts.user_id)");
		$this->iDataSource->addColumnControl("email_address", "readonly", "true");
		$this->iDataSource->addColumnControl("exam_id", "readonly", "true");
		$this->iDataSource->addColumnControl("class_description", "data_type", "literal");
		$this->iDataSource->addColumnControl("class_description", "form_label", "Exam For");
		$this->iDataSource->addColumnControl("class_description", "readonly", "true");
		$this->iDataSource->addFilterWhere("date_submitted is not null");
	}

	function afterGetRecord(&$returnArray) {
		$contactId = Contact::getUserContactId($returnArray['user_id']['data_value']);
		$returnArray['email_address'] = array("data_value" => getFieldFromId("email_address", "contacts", "contact_id", $contactId));
		$classDescription = "";
		if (!empty($returnArray['lesson_id']['data_value'])) {
			$courseId = getFieldFromId("course_id", "lessons", "lesson_id", $returnArray['lesson_id']['data_value']);
			$classDescription = "<span class='highlighted-text'>" . getFieldFromId("description", "lessons", "lesson_id", $returnArray['lesson_id']['data_value']) .
				"</span> in course <span class='highlighted-text'>" . getFieldFromId("description", "courses", "course_id", $courseId) . "</span>";
			$degreeProgramId = getFieldFromId("degree_program_id", "courses", "course_id", $courseId);
			if (!empty($degreeProgramId)) {
				$classDescription .= " of degree program <span class='highlighted-text'>" . getFieldFromId("description", "degree_programs", "degree_program_id", $degreeProgramId) . "</span>";
			}
		} else if (!empty($returnArray['course_id']['data_value'])) {
			$classDescription = "<span class='highlighted-text'>" . getFieldFromId("description", "courses", "course_id", $returnArray['course_id']['data_value']) . "</span>";
			$degreeProgramId = getFieldFromId("degree_program_id", "courses", "course_id", $returnArray['course_id']['data_value']);
			if (!empty($degreeProgramId)) {
				$classDescription .= " of degree program <span class='highlighted-text'>" . getFieldFromId("description", "degree_programs", "degree_program_id", $degreeProgramId) . "</span>";
			}
		} else if (!empty($returnArray['degree_program_id']['data_value'])) {
			$classDescription = "<span class='highlighted-text'>" . getFieldFromId("description", "degree_programs", "degree_program_id", $returnArray['degree_program_id']['data_value']) . "</span>";
		}
		$returnArray['class_description'] = array("data_value" => $classDescription);

		ob_start();

		$examRow = getRowFromId("exams", "exam_id", $returnArray['exam_id']['data_value']);
		$resultSet = executeQuery("select * from exam_questions where inactive = 0 and exam_id = ? order by sort_order", $examRow['exam_id']);
		$questionNumber = 0;
		$passFailFound = false;
		while ($row = getNextRow($resultSet)) {
			$choices = array();
            if ($row['data_type'] == "select" || $row['data_type'] == "radio") {
				$choiceOptions = getContentLines($row['choices']);
				foreach ($choiceOptions as $thisChoice) {
					$choices[md5($thisChoice)] = $thisChoice;
				}
			}
			$examAnswerRow = getRowFromId("exam_answers", "exam_attempt_id", $returnArray['primary_id']['data_value'], "exam_question_id = ?", $row['exam_question_id']);
			$questionNumber++;
			?>
            <div class="exam-question" id="exam_question_id_<?= $row['exam_question_id'] ?>" data-exam_question_id="<?= $row['exam_question_id'] ?>">
                <input type="hidden" id="exam_question_id_<?= $row['exam_question_id'] ?>" name="exam_question_id_<?= $row['exam_question_id'] ?>" value="<?= $examAnswerRow['exam_question_id'] ?>">
                <input type="hidden" class="exam-answer-id" id="exam_answer_id_<?= $row['exam_question_id'] ?>" name="exam_answer_id_<?= $row['exam_question_id'] ?>" value="<?= $examAnswerRow['exam_answer_id'] ?>">
                <p class='exam-question-text'><span class="question-number"><?= $questionNumber ?></span>. <?= $row['content'] ?></p>
				<?php if ($row['data_type'] == "varchar" && empty($row['simple_acknowledgement'])) { ?>
                    <div class="basic-form-line">
                        <label>Answer</label>
                        <textarea class="exam-answer text-answer" readonly="readonly"><?= htmlText($examAnswerRow['answer_content']) ?></textarea>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>
				<?php } else if (($row['data_type'] == "select" || $row['data_type'] == "radio") && empty($row['simple_acknowledgement'])) { ?>
                    <div class="basic-form-line">
                        <label>Answer</label>
                        <select class="exam-answer" disabled="disabled" class="validate[required]" id="answer_content_<?= $row['exam_question_id'] ?>" name="answer_content_<?= $row['exam_question_id'] ?>">
                            <option value="">[Select]</option>
							<?php foreach ($choices as $index => $choiceText) { ?>
                                <option <?= ($choiceText == $examAnswerRow['answer_content'] ? " selected" : "") ?> value="<?= $index ?>"><?= htmlText($choiceText) ?></option>
							<?php } ?>
                        </select>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>
				<?php } else if (!empty($row['simple_acknowledgement'])) { ?>
                    <p class='exam-answer'>User <?= (empty($examAnswerRow['answer_content']) ? " hasn't answered this question." : " answered yes.") ?></p>
					<?php if (!empty($examAnswerRow['notes'])) { ?>
                        <div class="basic-form-line">
                            <label>Additional Comments</label>
                            <textarea class="exam-answer additional-comments" readonly="readonly"><?= htmlText($examAnswerRow['notes']) ?></textarea>
                            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                        </div>
					<?php } ?>
				<?php } ?>
				<?php if (empty($row['simple_acknowledgement'])) { ?>
                    <div class="basic-form-line">
                        <label>Feedback</label>
                        <span class='help-label'>Student will see this</span>
                        <textarea class="answer-feedback" id="exam_answer_<?= $row['exam_question_id'] ?>_feedback" name="exam_answer_<?= $row['exam_question_id'] ?>_feedback"><?= htmlText($examAnswerRow['feedback']) ?></textarea>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>
				<?php } ?>

				<?php if (empty($row['no_grade']) && empty($row['simple_acknowledgement'])) { ?>
                    <div class="basic-form-line">
                        <label>Grade on this question</label>
						<?php if (!empty($row['pass_fail'])) { ?>
                            <select class="pass-fail" id="exam_answer_<?= $row['exam_question_id'] ?>_grade_percent" name="exam_answer_<?= $row['exam_question_id'] ?>_grade_percent">
                                <option value="">[None Yet]</option>
                                <option value="0"<?= (strlen($examAnswerRow['grade_percent']) > 0 && $examAnswerRow['grade_percent'] == 0 ? " selected" : "") ?>>Unacceptable Answer</option>
                                <option value="100"<?= (strlen($examAnswerRow['grade_percent']) > 0 && $examAnswerRow['grade_percent'] > 0 ? " selected" : "") ?>>Pass</option>
                            </select>
							<?php
							$passFailFound = true;
						} else {
							?>
                            <input type="text" class="exam-answer validate[custom[number]]" data-decimal-places="2" id="exam_answer_<?= $row['exam_question_id'] ?>_grade_percent" name="exam_answer_<?= $row['exam_question_id'] ?>_grade_percent" value="<?= number_format($examAnswerRow['grade_percent'], 2) ?>">
						<?php } ?>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>
				<?php } ?>

            </div> <!-- exam-question -->
			<?php
		}
		if ($passFailFound) {
			?>
            <p>
                <button id="pass_all">Pass All and Complete</button>
            </p>
			<?php
		}
		?>
        <div class="basic-form-line">
            <input type="checkbox" id="email_student" name="email_student" value="1"><label for="email_student" class="checkbox-label">Send notification to the student that their exam has been checked.</label>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>
		<?php
		$returnArray['exam_questions'] = array("data_value" => ob_get_clean());
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", "#pass_all", function () {
                $(".pass-fail").val("100");
                if (empty($("#date_completed").val())) {
                    $("#date_completed").val("<?= date("m/d/Y") ?>");
                }
                $("#email_student").prop("checked", true);
                return false;
            });
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
			.exam-question {
				border-bottom: 1px solid rgb(180, 180, 180);
				padding: 20px 0;
				margin-bottom: 20px;
			}
			textarea {
				max-width: 600px;
				height: 200px;
			}
			.exam-answer {
				color: rgb(0, 150, 0);
			}
        </style>
		<?php
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		foreach ($nameValues as $fieldName => $fieldData) {
			if (substr($fieldName, 0, strlen("exam_question_id_")) == "exam_question_id_") {
				$examQuestionId = getFieldFromId("exam_question_id", "exam_questions", "exam_question_id", substr($fieldName, strlen("exam_question_id_")));
				if (empty($examQuestionId)) {
					continue;
				}
				$examAnswerId = getFieldFromId("exam_answer_id", "exam_answers", "exam_question_id", $examQuestionId, "exam_attempt_id = ?", $nameValues['primary_id']);
				$dataTable = new DataTable("exam_answers");
				$dataTable->setSaveOnlyPresent(true);
				$dataArray = array("exam_attempt_id" => $nameValues['primary_id'], "exam_question_id" => $examQuestionId, "feedback" => $nameValues["exam_answer_" . $examQuestionId . "_feedback"],
					"user_id" => $GLOBALS['gUserId'], "grade_percent" => $nameValues["exam_answer_" . $examQuestionId . "_grade_percent"]);
				$dataTable->saveRecord(array("name_values" => $dataArray, "primary_id" => $examAnswerId));
			}
		}
		if (!empty($nameValues['email_student'])) {
            $examAttemptRow = getRowFromId("exam_attempts","exam_attempt_id",$nameValues['primary_id']);
            $contactId = Contact::getUserContactId($examAttemptRow['user_id']);
			$substitutions = array("exam_description" => getFieldFromId("description", "exams", "exam_id", $examAttemptRow['exam_id']), "user_name" => getDisplayName($contactId));
			$emailId = getFieldFromId("email_id", "emails", "email_code", "STUDENT_NOTIFICATION",  "inactive = 0");
			$body = "%exam_description% has been checked. Log in and review the results.";
			$subject = "Exam checked";
			$emailAddress = getFieldFromId("email_address", "contacts", "contact_id", $contactId);
			sendEmail(array("email_address" => $emailAddress, "body" => $body, "subject" => $subject, "email_id" => $emailId, "substitutions" => $substitutions));
		}
		return true;
	}
}

$pageObject = new ExamAttemptMaintenancePage("exam_attempts");
$pageObject->displayPage();
