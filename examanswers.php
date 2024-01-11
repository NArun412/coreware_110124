<?php

$GLOBALS['gPageCode'] = "EXAMANSWERS";
require_once "shared/startup.inc";

class ExamAnswersPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "complete_exam":
				$examId = getFieldFromId("exam_id", "exams", "exam_id", $_POST['exam_id']);
				if (empty($examId)) {
					$returnArray['error_message'] = "No exam found";
					ajaxResponse($returnArray);
					break;
				}
				executeQuery("update exam_attempts set date_completed = current_date where exam_attempt_id = ? and exam_id = ? and user_id = ? and date_completed is null", $_POST['exam_attempt_id'], $examId, $GLOBALS['gUserId']);
				ajaxResponse($returnArray);
				break;
			case "save_answer":
				$examId = getFieldFromId("exam_id", "exams", "exam_id", $_POST['exam_id']);
				if (empty($examId)) {
					$returnArray['error_message'] = "No exam found";
					ajaxResponse($returnArray);
					break;
				}
				$examQuestionId = getFieldFromId("exam_question_id", "exam_questions", "exam_question_id", $_POST['exam_question_id'], "exam_id = ?", $examId);
				if (empty($examQuestionId)) {
					$returnArray['error_message'] = "Question not found";
					ajaxResponse($returnArray);
					break;
				}

				$this->iDatabase->startTransaction();

				$examAttemptId = getFieldFromId("exam_attempt_id", "exam_attempts", "exam_attempt_id", $_POST['exam_attempt_id'], "exam_id = ? and user_id = ? and " .
					"date_completed is null and degree_program_id <=> ? and course_id <=> ? and lesson_id <=> ?", $examId, $GLOBALS['gUserId'], $_POST['degree_program_id'], $_POST['course_id'], $_POST['lesson_id']);
				if (empty($examAttemptId)) {
					if (!empty($_POST['lesson_id'])) {
						$examAttemptId = getFieldFromId("exam_attempt_id", "exam_attempts", "exam_id", $examId, "user_id = ? and lesson_id = ? and date_completed is null", $GLOBALS['gUserId'], $_POST['lesson_id']);
						if (empty($examAttemptId)) {
							$resultSet = executeQuery("insert into exam_attempts (user_id,exam_id,lesson_id,start_date) values (?,?,?,now())", $GLOBALS['gUserId'],
								$examId, $_POST['lesson_id']);
							$examAttemptId = $resultSet['insert_id'];
						}
					} else if (!empty($_POST['course_id'])) {
						$examAttemptId = getFieldFromId("exam_attempt_id", "exam_attempts", "exam_id", $examId, "user_id = ? and course_id = ? and date_completed is null", $GLOBALS['gUserId'], $_POST['course_id']);
						if (empty($examAttemptId)) {
							$resultSet = executeQuery("insert into exam_attempts (user_id,exam_id,course_id,start_date) values (?,?,?,now())", $GLOBALS['gUserId'],
								$examId, $_POST['course_id']);
							$examAttemptId = $resultSet['insert_id'];
						}
					} else if (!empty($_POST['degree_program_id'])) {
						$examAttemptId = getFieldFromId("exam_attempt_id", "exam_attempts", "exam_id", $examId, "user_id = ? and degree_program_id = ? and date_completed is null", $GLOBALS['gUserId'], $_POST['degree_program_id']);
						if (empty($examAttemptId)) {
							$resultSet = executeQuery("insert into exam_attempts (user_id,exam_id,degree_program_id,start_date) values (?,?,?,now())", $GLOBALS['gUserId'],
								$examId, $_POST['degree_program_id']);
							$examAttemptId = $resultSet['insert_id'];
						}
					} else {
						$examAttemptId = getFieldFromId("exam_attempt_id", "exam_attempts", "exam_id", $examId, "user_id = ? and date_completed is null and degree_program_id is null and course_id is null and lesson_id is null", $GLOBALS['gUserId']);
						if (empty($examAttemptId)) {
							$resultSet = executeQuery("insert into exam_attempts (user_id,exam_id,start_date) values (?,?,now())", $GLOBALS['gUserId'], $examId);
							$examAttemptId = $resultSet['insert_id'];
						}
					}
				}
				$dataSource = new DataSource("exam_answers");
				$examAnswerId = getFieldFromId("exam_answer_id", "exam_answers", "exam_answer_id", $_POST['exam_answer_id'], "exam_attempt_id = ? and exam_question_id = ?", $examAttemptId, $examQuestionId);
				if (empty($examAnswerId)) {
					$examAnswerId = getFieldFromId("exam_answer_id", "exam_answers", "exam_attempt_id", $examAttemptId, "exam_question_id = ?", $examQuestionId);
				}
				$examAnswerRow = getRowFromId("exam_answers", "exam_answer_id", $examAnswerId);
				$examAnswerRow['exam_attempt_id'] = $examAttemptId;
				$examAnswerRow['exam_question_id'] = $examQuestionId;
				$examAnswerRow['answer_content'] = $_POST['answer_content'];
				$examAnswerRow['notes'] = $_POST['notes'];
				if (!$dataSource->saveRecord(array("name_values" => $examAnswerRow, "primary_id" => $examAnswerId))) {
					$returnArray['error_message'] = $dataSource->getErrorMessage();
					$this->iDatabase->rollbackTransaction();
				} else {
					$this->iDatabase->commitTransaction();
					$returnArray['exam_attempt_id'] = $examAttemptId;
				}
				ajaxResponse($returnArray);
				break;
		}
	}
}

$pageObject = new ExamAnswersPage();
$pageObject->displayPage();
