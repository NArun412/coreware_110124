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

/*
%module:display_exam:exam_code=simple_test%
*/

class DisplayExamPageModule extends PageModule {
	function createContent() {
		$examCode = (empty($this->iParameters['exam_code']) ? array_shift($this->iParameters) : $this->iParameters['exam_code']);
		$examId = getFieldFromId("exam_id", "exams", "exam_code", $examCode);
		if (empty($examId) || !$GLOBALS['gLoggedIn']) {
			return;
		}
		$examRow = getRowFromId("exams", "exam_id", $examId);
		$examAttemptRow = getRowFromId("exam_attempts","exam_id",$examId,"date_completed is null and lesson_id is null and course_id is null and degree_program_id is null and user_id = ?",$GLOBALS['gUserId']);
		Education::displayExam($examRow,$examAttemptRow);
	}
}
