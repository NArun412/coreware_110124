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

$GLOBALS['gPageCode'] = "AUTHORIZECOMPUTER";
$GLOBALS['gAuthorizeComputer'] = true;
$GLOBALS['gPreemptivePage'] = true;
$GLOBALS['gEmbeddablePage'] = true;
require_once "shared/startup.inc";

class AuthorizeComputerPage extends Page {
	var $iTwoFactorAuthentication = false;

	function setup() {
		if ($GLOBALS['gUserRow']['administrator_flag'] && !$GLOBALS['gDevelopmentServer']) {
			$this->iTwoFactorAuthentication = true;
		} else {
			$this->iTwoFactorAuthentication = getPreference("TWO_FACTOR_AUTHENTICATION");
		}
		if (empty($GLOBALS['gUserRow']['email_address'])) {
			$this->iTwoFactorAuthentication = false;
		}
		if (getPreference("NEVER_TWO_FACTOR_AUTHENTICATION")) {
			$this->iTwoFactorAuthentication = false;
		}
		if (!$this->iTwoFactorAuthentication && empty($GLOBALS['gUserRow']['security_question_id']) && empty($GLOBALS['gUserRow']['secondary_security_question_id'])) {
			setCoreCookie("COMPUTER_AUTHORIZATION_" . $GLOBALS['gUserId'], hash("sha256", $GLOBALS['gUserRow']['security_question_id'] . ":" . $GLOBALS['gUserRow']['secondary_security_question_id'] . ":" . $GLOBALS['gUserId']),
				($_POST['computer_environment_private'] == "1" ? 24 * 730 : false));
			executeQuery("update users set verification_code = null where user_id = ?", $GLOBALS['gUserId']);
			header("Location: /");
			exit;
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "authorize_computer":
				if ($this->iTwoFactorAuthentication) {
					if (empty($_POST['verification_code'])) {
						ajaxResponse($returnArray);
						break;
					}
					$_POST['verification_code'] = trim(strtoupper($_POST['verification_code']));
					$verificationCode = getFieldFromId("verification_code", "users", "user_id", $GLOBALS['gUserId']);
					if ($verificationCode == $_POST['verification_code']) {
						executeQuery("update users set verification_code = null where user_id = ?", $GLOBALS['gUserId']);
						setCoreCookie("COMPUTER_AUTHORIZATION_" . $GLOBALS['gUserId'], hash("sha256", $GLOBALS['gUserRow']['security_question_id'] . ":" . $GLOBALS['gUserRow']['secondary_security_question_id'] . ":" . $GLOBALS['gUserId']),
							($_POST['computer_environment_private'] == "1" ? 24 * 730 : false));
						executeQuery("update users set verification_code = null where user_id = ?", $GLOBALS['gUserId']);
						$returnArray['go_to_uri'] = $_SESSION['GO_TO_URI'];
						$_SESSION['GO_TO_URI'] = "";
						saveSessionData();
						if (empty($returnArray['go_to_uri'])) {
							$returnArray['go_to_uri'] = "/";
						}
					} else {
						$returnArray['error_message'] = "Incorrect verification code.";
						$returnArray['attempt_count'] = $attemptCount = $_POST['attempt_count']++;
						if ($attemptCount > 5) {
							$returnArray['go_to_uri'] = "/";
						}
					}
				} else {
					$answerText = strtolower(str_replace(" ", "", str_replace("'", "", $GLOBALS['gUserRow']['answer_text'])));
					$secondaryAnswerText = strtolower(str_replace(" ", "", str_replace("'", "", $GLOBALS['gUserRow']['secondary_answer_text'])));
					$givenAnswerText = strtolower(str_replace(" ", "", str_replace("'", "", $_POST['answer_text'])));
					$givenSecondaryAnswerText = strtolower(str_replace(" ", "", str_replace("'", "", $_POST['secondary_answer_text'])));

					$answerSame = true;
					if (strlen($answerText) != strlen($givenAnswerText)) {
						$answerSame = false;
					} else {
						$charsWrong = 0;
						for ($x = 0; $x < strlen($answerText); $x++) {
							if (substr($givenAnswerText, $x, 1) != substr($answerText, $x, 1)) {
								$charsWrong++;
							}
						}
						if ($charsWrong > 1) {
							$answerSame = false;
						}
					}
					if (strlen($secondaryAnswerText) != strlen($givenSecondaryAnswerText)) {
						$answerSame = false;
					} else {
						$charsWrong = 0;
						for ($x = 0; $x < strlen($secondaryAnswerText); $x++) {
							if (substr($givenSecondaryAnswerText, $x, 1) != substr($secondaryAnswerText, $x, 1)) {
								$charsWrong++;
							}
						}
						if ($charsWrong > 1) {
							$answerSame = false;
						}
					}

					if ($answerSame) {
						setCoreCookie("COMPUTER_AUTHORIZATION_" . $GLOBALS['gUserId'], hash("sha256", $GLOBALS['gUserRow']['security_question_id'] . ":" . $GLOBALS['gUserRow']['secondary_security_question_id'] . ":" . $GLOBALS['gUserId']),
							($_POST['computer_environment_private'] == "1" ? 24 * 730 : false));
						executeQuery("update users set verification_code = null where user_id = ?", $GLOBALS['gUserId']);
						$returnArray['go_to_uri'] = $_SESSION['GO_TO_URI'];
						$_SESSION['GO_TO_URI'] = "";
						saveSessionData();
						if (empty($returnArray['go_to_uri'])) {
							$returnArray['go_to_uri'] = "/";
						}
					} else {
						$returnArray['error_message'] = "These answers do not match those you originally entered.";
					}
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function mainContent() {
		if ($this->iTwoFactorAuthentication) {
			$randomCode = getRandomString(6, "23456789ABCDEFGHJKLMNPQRSTUVWXYZ", array("number_start" => true));
			if (empty($GLOBALS['gUserRow']['verification_code'])) {
				executeQuery("update users set verification_code = ? where user_id = ?", $randomCode, $GLOBALS['gUserId']);
			} else {
				$randomCode = $GLOBALS['gUserRow']['verification_code'];
			}
			$returnValue = sendEmail(array("email_address" => $GLOBALS['gUserRow']['email_address'], "subject" => "Verification Code",
				"body" => "<p style='font-size: 24px;'>Your verification code is %random_code% requested from %ip_address%" .
					" for client '" . $GLOBALS['gClientRow']['client_code'] . "')" .
					". If you did not request this, you might want to log in and change your password ASAP.</p>",
				"substitutions" => array("random_code" => $randomCode, "ip_address" => $_SERVER['REMOTE_ADDR']), "send_immediately" => true,
				"no_notifications" => true, "contact_id" => $GLOBALS['gUserRow']['contact_id'], "email_code" => "TWO_FACTOR_AUTHENTICATION"));
			if ($returnValue !== true) {
				sendEmail(array("email_address" => $GLOBALS['gUserRow']['email_address'], "subject" => "Verification Code", "body" =>
					"<p style='font-size: 24px;'>Your verification code is " . $randomCode . " requested from " . $_SERVER['REMOTE_ADDR'] . " for client " . $GLOBALS['gClientRow']['client_code'] .
					". If you did not request this, you might want to log in and change your password ASAP.</p>", "send_immediately" => true, "primary_client" => true, "no_notifications" => true));
			}
			?>
            <h3>This computer has not been authorized for use. A verification code has been sent to your email address.
                Enter that code here to authorize this computer. If you select that this computer is in a public place,
                the authorization will only last until the browser quits.</h3>

            <form name="_edit_form" id="_edit_form">
                <input type='hidden' name='attempt_count' id='attempt_count' value="0">
                <div class="form-line" id="_verification_code_row">
                    <p class="highlighted-text">Verification Code</p>
                    <input tabindex="10" type="text" class="validate[required]" size=10 id="verification_code"
                           name="verification_code">
                    <div class='clear-div'></div>
                </div>
                <div class="form-line" id="_computer_environment_private_row">
                    <p><input tabindex="10" type="checkbox" id="computer_environment_private" name="computer_environment_private" value="1"><label class="checkbox-label" for="computer_environment_private">Register this device. It is not a public computer.</label></p>
                    <div class='clear-div'></div>
                </div>
            </form>
            <p class='error-message' id="error_message"></p>
            <p>
                <button tabindex="10" id="submit_form">Submit</button>
            </p>
			<?php
		} else {
			?>
            <h3>This computer has not been authorized for use. Please answer your security questions to authorize it.
                The answers must be exactly as you originally entered them. Too many wrong attempts will result in you
                being locked out of the system.</h3>

            <form name="_edit_form" id="_edit_form">
                <div class="form-line" id="_answer_text_row">
                    <p class="highlighted-text"><?= getFieldFromId("security_question", "security_questions", "security_question_id", $GLOBALS['gUserRow']['security_question_id']) ?></p>
                    <input tabindex="10" type="text" class="validate[required]" size=40 id="answer_text"
                           name="answer_text">
                    <div class='clear-div'></div>
                </div>
                <div class="form-line" id="_secondary_answer_text_row">
                    <p class="highlighted-text"><?= getFieldFromId("security_question", "security_questions", "security_question_id", $GLOBALS['gUserRow']['secondary_security_question_id']) ?></p>
                    <input tabindex="10" type="text" class="validate[required]" size=40 id="secondary_answer_text"
                           name="secondary_answer_text">
                    <div class='clear-div'></div>
                </div>
                <div class="form-line" id="_computer_environment_private_row">
					<p><input tabindex="10" type="checkbox" id="computer_environment_private" name="computer_environment_private" value="1"><label class="checkbox-label" for="computer_environment_private">Register this device. It is not a public computer.</label></p>
                    <div class='clear-div'></div>
                </div>
            </form>
            <p class='error-message' id="error_message"></p>
            <p>
                <button tabindex="10" id="submit_form">Submit</button>
            </p>
			<?php
		}
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", "#submit_form", function () {
                if (!empty($("#verification_code").val()) || !empty($("#answer_text").val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=authorize_computer", $("#_edit_form").serialize(), function(returnArray) {
                        if ("go_to_uri" in returnArray) {
                            document.location = returnArray['go_to_uri'];
                            return;
                        }
                        if ("attempt_count" in returnArray) {
                            $("#attempt_count").val(returnArray['attempt_count']);
                        }
                        $("#verification_code").val("");
                    });
                }
            });
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #_edit_form {
                margin-top: 40px;
                margin-bottom: 30px;
            }

            .form-line {
                margin-top: 20px;
            }

            p {
                margin: 0 0 2px 0;
                padding: 0;
            }
        </style>
		<?php
	}
}

$pageObject = new AuthorizeComputerPage();
$pageObject->displayPage();
