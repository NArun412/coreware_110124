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

class Education {

	public static function getNextCourse($degreeProgramId = "", $userId = "") {
		if (empty($userId)) {
			$userId = $GLOBALS['gUserId'];
		}
		$resultSet = executeQuery("select * from courses where client_id = ? and degree_program_id <=> ? and " .
			"course_id not in (select course_id from course_attendances where user_id = ? and date_completed is not null) order by sort_order",
			$GLOBALS['gClientId'], $degreeProgramId, $userId);
		while ($row = getNextRow($resultSet)) {
			$requirementsSet = executeQuery("select * from course_requirements where course_id = ? and required_course_id in (select course_id from courses where inactive = 0) and " .
				"required_course_id <> ? and required_course_id not in (select course_id from course_attendances where user_id = ? and date_completed is not null)", $row['course_id'],
				$row['course_id'], $userId);
			if ($requirementsRow = getNextRow($requirementsSet)) {
				continue;
			}
			if (!empty($row['product_id'])) {
				$orderItemId = getFieldFromId("order_item_id", "order_items", "product_id", $row['product_id'],
					"deleted = 0 and order_id in (select order_id from orders where deleted = 0 and contact_id = (select contact_id from users where user_id = ?))", $userId);
				if (empty($orderItemId)) {
					continue;
				}
			}
			return $row['course_id'];
		}
		return false;
	}

	public static function canAccessCourse($courseId, $userId = "") {
		if (empty($userId)) {
			$userId = $GLOBALS['gUserId'];
		}
		$resultSet = executeQuery("select * from courses where client_id = ? and course_id = ?", $GLOBALS['gClientId'], $courseId);
		while ($row = getNextRow($resultSet)) {
			$requirementsSet = executeQuery("select * from course_requirements where course_id = ? and required_course_id in (select course_id from courses where inactive = 0) and " .
				"required_course_id <> ? and required_course_id not in (select course_id from course_attendances where user_id = ? and date_completed is not null)", $row['course_id'],
				$row['course_id'], $userId);
			if ($requirementsRow = getNextRow($requirementsSet)) {
				continue;
			}
			if (!empty($row['product_id'])) {
				$orderItemId = getFieldFromId("order_item_id", "order_items", "product_id", $row['product_id'],
					"deleted = 0 and order_id in (select order_id from orders where deleted = 0 and contact_id = (select contact_id from users where user_id = ?))", $userId);
				if (empty($orderItemId)) {
					continue;
				}
			}
			return true;
		}
		return false;
	}

	public static function isDegreeProgramFinished($degreeProgramId, $userId = "", $exceptExam = false) {
		if (empty($userId)) {
			$userId = $GLOBALS['gUserId'];
		}
		$degreeProgramRow = getRowFromId("degree_programs", "degree_program_id", $degreeProgramId);
		if (empty($degreeProgramRow)) {
			return false;
		}
		if (!$exceptExam && !empty($degreeProgramRow['exam_id'])) {
			$examAttemptId = getFieldFromId("exam_attempt_id", "exam_attempts", "degree_program_id", $degreeProgramId, "user_id = ?", $userId);
			if (empty($examAttemptId)) {
				return false;
			}
			if (Education::isExamCompleted($examAttemptId) !== true) {
				return false;
			}
		}
		$resultSet = executeQuery("select * from degree_program_courses join courses using (course_id) where degree_program_id = ? and inactive = 0", $degreeProgramId);
		$degreeProgramFinished = true;
		while ($row = getNextRow($resultSet)) {
			if (!Education::isCourseFinished($row['course_id'], $userId)) {
				$degreeProgramFinished = false;
				break;
			}
		}
		return $degreeProgramFinished;
	}

	public static function isExamCompleted($examAttemptId, $setCompleted = true) {
		$examAttemptRow = getRowFromId("exam_attempts", "exam_attempt_id", $examAttemptId);
		if (empty($examAttemptRow)) {
			return false;
		}
		if (!empty($examAttemptRow['date_completed'])) {
			return true;
		}
		$examRow = getRowFromId("exams", "exam_id", $examAttemptRow['exam_id']);
		$questionCount = 0;
		$totalGrade = 0;
		$resultSet = executeQuery("select * from exam_questions left outer join exam_answers using (exam_question_id) where exam_questions.exam_id = ? and exam_answers.exam_attempt_id = ? order by exam_questions.sort_order",
			$examRow['exam_id'], $examAttemptRow['exam_attempt_id']);
		$resultOptions = array("not_completed" => false, "not_graded" => false, "not_passed" => false);
		while ($row = getNextRow($resultSet)) {
			$gradePercent = $row['grade_percent'];
			if (!empty($row['simple_acknowledgement']) && !empty($row['answer_content'])) {
				$gradePercent = 100;
			} else if (!empty($row['no_grade']) && !empty($row['answer_content'])) {
				$gradePercent = 100;
			} else if (!empty($row['answer_text']) && $row['answer_text'] == $row['answer_content']) {
				$gradePercent = 100;
			} else if (!empty($row['answer_text']) && $row['answer_text'] != $row['answer_content'] && !empty($row['answer_content'])) {
				$gradePercent = 0;
			}

# Questions not completed

			if (empty($row['answer_content'])) {
				$resultOptions['not_completed'] = true;
				continue;
			}

# Exam requires grading and hasn't been graded

			if (strlen($gradePercent) == 0 && empty($row['simple_acknowledgement']) && empty($row['no_grade'])) {
				$resultOptions['not_graded'] = true;
				continue;
			}
			if (empty($gradePercent)) {
				$gradePercent = 0;
			}

			$questionCount++;
			$totalGrade += $gradePercent;
		}
		if ($questionCount > 0) {
			$grade = $totalGrade / $questionCount;
		} else {
			$grade = 0;
		}
		$passed = ($grade >= $examRow['minimum_grade']);
		if (!$passed) {
			$resultOptions['not_passed'] = true;
		}
		foreach ($resultOptions as $returnValue => $valueSet) {
			if ($valueSet) {
				return $returnValue;
			}
		}
		if ($setCompleted) {
			executeQuery("update exam_attempts set date_completed = now() where exam_attempt_id = ?", $examAttemptRow['exam_attempt_id']);
		}
		return true;
	}

	public static function isCourseFinished($courseId, $userId = "", $exceptExam = false) {
		if (empty($userId)) {
			$userId = $GLOBALS['gUserId'];
		}
		$courseRow = getRowFromId("courses", "course_id", $courseId);
		if (empty($courseRow)) {
			return false;
		}
		if (!$exceptExam && !empty($courseRow['exam_id'])) {
			$examAttemptId = getFieldFromId("exam_attempt_id", "exam_attempts", "course_id", $courseId, "user_id = ?", $userId);
			if (empty($examAttemptId)) {
				return false;
			}
			if (Education::isExamCompleted($examAttemptId) !== true) {
				return false;
			}
		}
		$courseAttendanceRow = getRowFromId("course_attendances", "course_id", $courseId, "user_id = ?", $userId);
		if (!empty($courseAttendanceRow['date_completed'])) {
			return $courseAttendanceRow['date_completed'];
		}
		if (empty($courseAttendanceRow)) {
			return false;
		}
		$resultSet = executeQuery("select count(*) from lessons where inactive = 0 and lesson_id in (select lesson_id from course_lessons where course_id = ?) and " .
			"lesson_id not in (select lesson_id from lesson_progress where course_attendance_id in (select course_attendance_id from course_attendances where user_id = ? and course_id = ?) and time_completed is not null)", $courseId, $userId, $courseId);
		if ($row = getNextRow($resultSet)) {
			if ($row['count(*)'] == 0) {
				executeQuery("update course_attendances set date_completed = now() where course_attendance_id = ?", $courseAttendanceRow['course_attendance_id']);
				return date("Y-m-d");
			}
		}
		return false;
	}

	public static function isCourseStarted($courseId, $userId = "") {
		if (empty($userId)) {
			$userId = $GLOBALS['gUserId'];
		}
		$resultSet = executeQuery("select * from course_attendances where user_id = ? and course_id = ?", $userId, $courseId);
		if ($row = getNextRow($resultSet)) {
			return true;
		}
		return false;
	}

	public static function getNextLesson($courseId, $userId = "") {
		if (empty($userId)) {
			$userId = $GLOBALS['gUserId'];
		}
		$resultSet = executeQuery("select * from lessons join course_lessons using (lesson_id) where course_id = ? and " .
			"lesson_id not in (select lesson_id from lesson_progress join course_attendances using (course_attendance_id) where user_id = ? and time_completed is not null) order by sequence_number",
			$courseId, $userId);
		while ($row = getNextRow($resultSet)) {
			return $row['lesson_id'];
		}
		return false;
	}

	public static function isLessonFinished($lessonId, $userId = "", $exceptExam = false, $courseId = "") {
		if (empty($userId)) {
			$userId = $GLOBALS['gUserId'];
		}
		$lessonRow = getRowFromId("lessons", "lesson_id", $lessonId);
		if (empty($lessonRow)) {
			return false;
		}
		if (!$exceptExam && !empty($lessonRow['exam_id'])) {
			$examAttemptId = getFieldFromId("exam_attempt_id", "exam_attempts", "lesson_id", $lessonId, "user_id = ?", $userId);
			if (empty($examAttemptId)) {
				return false;
			}
			if (Education::isExamCompleted($examAttemptId) !== true) {
				return false;
			}
		}
		if (!empty($courseId)) {
			$resultSet = executeQuery("select * from lesson_progress where lesson_id = ? and course_attendance_id in (select course_attendance_id from course_attendances where user_id = ? and course_id = ?) and time_completed is not null", $lessonId, $userId, $courseId);
		} else {
			$resultSet = executeQuery("select * from lesson_progress where lesson_id = ? and course_attendance_id in (select course_attendance_id from course_attendances where user_id = ?) and time_completed is not null", $lessonId, $userId);
		}
		if ($row = getNextRow($resultSet)) {
			return true;
		}
		return false;
	}

# check if the exam attempt is completed. If setCompleted is true, mark the attempt complete IF all the answers are completed.

	public static function isLessonStarted($lessonId, $userId = "", $courseId = "") {
		if (empty($userId)) {
			$userId = $GLOBALS['gUserId'];
		}
		if (empty($courseId)) {
			$resultSet = executeQuery("select * from lesson_progress where lesson_id = ? and course_attendance_id in (select course_attendance_id from course_attendances where " .
				"user_id = ?)", $lessonId, $userId);
		} else {
			$resultSet = executeQuery("select * from lesson_progress where lesson_id = ? and course_attendance_id in (select course_attendance_id from course_attendances where " .
				"user_id = ? and course_id = ?)", $lessonId, $userId, $courseId);
		}
		if ($row = getNextRow($resultSet)) {
			return true;
		}
		return false;
	}

	public static function markLessonCompleted($courseAttendanceId, $lessonId) {
		$lessonId = getFieldFromId("lesson_id", "lessons", "lesson_id", $lessonId);
		if (empty($lessonId)) {
			return false;
		}
		$lessonProgressRow = getRowFromId("lesson_progress", "lesson_id", $lessonId, "course_attendance_id = ?", $courseAttendanceId);
		if (empty($lessonProgressRow)) {
			executeQuery("insert into lesson_progress (course_attendance_id,lesson_id,start_time,time_completed) values (?,?,now(),now())", $courseAttendanceId, $lessonId);
		} else {
			executeQuery("update lesson_progress set time_completed = now() where lesson_progress_id = ? and time_completed is null", $lessonProgressRow['lesson_progress_id']);
		}
		return true;
	}

	public static function logTimeInLesson($courseAttendanceId, $lessonId, $seconds) {
		$lessonProgressId = getFieldFromId("lesson_progress_id", "lesson_progress", "lesson_id", $lessonId, "course_attendance_id = ?", $courseAttendanceId);
		if (empty($lessonProgressId)) {
			return false;
		} else {
			executeQuery("insert into lesson_progress_time_log (lesson_progress_id,time_submitted,elapsed_seconds) values (?,now(),?)", $lessonProgressId, $seconds);
			return true;
		}
	}

	public static function getTimeInLesson($courseAttendanceId, $lessonId) {
		$lessonProgressId = getFieldFromId("lesson_progress_id", "lesson_progress", "lesson_id", $lessonId, "course_attendance_id = ?", $courseAttendanceId);
		$resultSet = executeQuery("select sum(elapsed_seconds) from lesson_progress_time_log where lesson_progress_id = ?", $lessonProgressId);
		if ($row = getNextRow($resultSet)) {
			$elapsedSeconds = $row['sum(elapsed_seconds)'];
		}
		return $elapsedSeconds ?: 0;
	}

	public static function displayExam($examData, $examAttemptData) {
		if (is_array($examData)) {
			$examRow = $examData;
		} else {
			$examRow = getRowFromId("exams", "exam_id", $examData);
		}
		if (is_array($examAttemptData)) {
			$examAttemptRow = $examAttemptData;
		} else {
			$examAttemptRow = getRowFromId("exam_attempts", "exam_attempt_id", $examAttemptData);
		}
		?>
        <div id="exam_wrapper">
            <style>
                .answer-checkbox {
                    margin-right: 10px;
                }
            </style>
            <script>
                $(function () {
                    $(document).on("click", "#complete_exam_button", function () {
                        let notCompleted = false;
                        $(".checkbox-answer").each(function () {
                            if ($(this).val() != "1") {
                                displayErrorMessage("All checkboxes must be checked and all questions have an answer");
                                notCompleted = true;
                                return false;
                            }
                        });
                        if (notCompleted) {
                            return false;
                        }
                        if ($("#_exam_form").validationEngine("validate")) {
                            loadAjaxRequest("/examanswers.php?ajax=true&url_action=complete_exam", $("#_exam_form").serialize(), function(returnArray) {
                                $("#exam_questions").html("<p>Exam submitted</p>");
                            });
                        } else {
                            displayErrorMessage("All checkboxes must be checked and all questions have an answer");
                        }
                        return false;
                    });
                    $(document).on("click", "span.fa-square", function () {
                        $(this).removeClass("fa-square");
                        $(this).addClass("fa-check-square");
                        $(this).closest(".exam-question").find(".checkbox-answer").val("1");
                        updateQuestion($(this).closest(".exam-question").data("exam_question_id"));
                    });
                    $(document).on("click", "span.fa-check-square", function () {
                        $(this).addClass("fa-square");
                        $(this).removeClass("fa-check-square");
                        $(this).closest(".exam-question").find(".checkbox-answer").val("0");
                        updateQuestion($(this).closest(".exam-question").data("exam_question_id"));
                    });
                    $(document).on("change", ".exam-answer", function () {
                        updateQuestion($(this).closest(".exam-question").data("exam_question_id"));
                    });
                });

                function updateQuestion(examQuestionId) {
                    let postData = {};
                    postData['exam_question_id'] = examQuestionId;
                    postData['exam_id'] = $("#exam_id").val();
                    postData['exam_answer_id'] = $("#exam_answer_id_" + examQuestionId).val();
                    postData['answer_content'] = $("#answer_content_" + examQuestionId).val();
                    postData['notes'] = $("#notes_" + examQuestionId).val();
                    $("body").addClass("no-waiting-for-ajax");
                    loadAjaxRequest("/examanswers.php?ajax=true&url_action=save_answer", postData, function(returnArray) {
                        if ("exam_attempt_id" in returnArray) {
                            $("#exam_attempt_id").val(returnArray['exam_attempt_id']);
                        }
                    });
                }
            </script>
			<?php
			if (!empty($examRow['content'])) {
				?>
                <div id="exam_introduction">
					<?php echo $examRow['content'] ?>
                </div> <!-- exam_introduction -->
				<?php
			}
			?>
            <div id="exam_questions">
                <form id="_exam_form">
                    <input type="hidden" id="exam_id" name="exam_id" value="<?= $examRow['exam_id'] ?>">
                    <input type="hidden" id="exam_attempt_id" name="exam_attempt_id" value="<?= $examAttemptRow['exam_attempt_id'] ?>">
					<?php
					$resultSet = executeQuery("select * from exam_questions where inactive = 0 and exam_id = ? order by sort_order", $examRow['exam_id']);
					$questionNumber = 0;
					while ($row = getNextRow($resultSet)) {
						$choices = array();
						if ($row['data_type'] == "select") {
							$choiceOptions = getContentLines($row['choices']);
							foreach ($choiceOptions as $thisChoice) {
								$choices[md5($thisChoice)] = $thisChoice;
							}
						}
						$examAnswerRow = getRowFromId("exam_answers", "exam_attempt_id", $examAttemptRow['exam_attempt_id'], "exam_question_id = ?", $row['exam_question_id']);
						$questionNumber++;
						?>
                        <div class="exam-question" id="exam_question_id_<?php echo $row['exam_question_id'] ?>" data-exam_question_id="<?php echo $row['exam_question_id'] ?>">
                            <input type="hidden" class="exam-answer-id" id="exam_answer_id_<?php echo $row['exam_question_id'] ?>" name="exam_answer_id_<?php echo $row['exam_question_id'] ?>" value="<?php echo $examAnswerRow['exam_answer_id'] ?>">
                            <p class='exam-question-text'><?php if ($row['data_type'] == "tinyint" || !empty($row['simple_acknowledgement'])) { ?><input type="hidden" class="checkbox-answer" id="answer_content_<?php echo $row['exam_question_id'] ?>" name="answer_content_<?php echo $row['exam_question_id'] ?>" value="<?php echo(empty($examAnswerRow['answer_content']) ? "0" : "1") ?>"><span
                                        class="far fa<?php echo(empty($examAnswerRow['answer_content']) ? "" : "-check") ?>-square answer-checkbox"></span><?php } ?><span class="question-number" data-question_number="<?= $questionNumber ?>"></span><?php echo $row['content'] ?></p>
							<?php if ($row['data_type'] == "varchar" && empty($row['simple_acknowledgement'])) { ?>
                                <p class="text-answer-wrapper"><textarea class="exam-answer text-answer validate[required]" placeholder="Enter Your Response" id="answer_content_<?php echo $row['exam_question_id'] ?>" name="answer_content_<?php echo $row['exam_question_id'] ?>"><?php echo htmlText($examAnswerRow['answer_content']) ?></textarea></p>
							<?php } else if ($row['data_type'] == "select" && empty($row['simple_acknowledgement'])) { ?>
                                <p class="text-answer-wrapper">
                                    <select class="exam-answer" class="validate[required]" id="answer_content_<?php echo $row['exam_question_id'] ?>" name="answer_content_<?php echo $row['exam_question_id'] ?>">
                                        <option value="">[Select]</option>
										<?php foreach ($choices as $index => $choiceText) { ?>
                                            <option<?php echo($choiceText == $examAnswerRow['answer_content'] ? " selected" : "") ?> value="<?php echo $index ?>"><?php echo htmlText($choiceText) ?></option>
										<?php } ?>
                                    </select>
                                </p>
							<?php } ?>
							<?php if ($row['data_type'] != "varchar" || !empty($row['simple_acknowledgement'])) { ?>
                                <p><textarea class="exam-answer" class="additional-comments" placeholder="Additional Comments" id="notes_<?php echo $row['exam_question_id'] ?>" name="notes_<?php echo $row['exam_question_id'] ?>"><?php echo htmlText($examAnswerRow['notes']) ?></textarea></p>
							<?php } ?>
                        </div> <!-- exam-question -->
						<?php
					}
					?>
                </form>
            </div> <!-- exam_questions -->

            <p class="error-message" id="_error_message"></p>
            <p class="align-center">
                <button id='complete_exam_button'>Complete Exam</button>
            </p>
        </div> <!-- exam_wrapper -->
		<?php
	}
}
