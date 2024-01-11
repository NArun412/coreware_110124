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

$GLOBALS['gPageCode'] = "RESETPASSWORD";
$GLOBALS['gPasswordReset'] = true;
$GLOBALS['gPreemptivePage'] = true;
$GLOBALS['gEmbeddablePage'] = true;
require_once "shared/startup.inc";
$forgotKey = $_GET['key'];

if (!empty($forgotKey)) {
	logout();
	$resultSet = executeQuery("select * from forgot_data where forgot_key = ? and time_requested >= (now() - interval 30 minute) and ip_address = ?",
		$forgotKey, $_SERVER['REMOTE_ADDR']);
	if ($forgotRow = getNextRow($resultSet)) {
		$GLOBALS['gUserId'] = $forgotRow['user_id'];
		addSecurityLog(getFieldFromId("user_name", "users", "user_id", $forgotRow['user_id']), "FORGOT-USED", "Forgot password link used");
		$_SESSION['forgot_key'] = $forgotKey;
		saveSessionData();
	} else {
		addSecurityLog(getFieldFromId("user_name", "users", "user_id", $forgotRow['user_id']), "FORGOT-INVALID", "Invalid forgot key attempted");
		header("Location: /");
		exit;
	}
}
if (empty($_GET['ajax']) && empty($GLOBALS['gUserId'])) {
	addSecurityLog(getFieldFromId("user_name", "users", "user_id", $forgotRow['user_id']), "FORGOT-NO-USER", "Forgot Key, but no user");
	header("Location: /");
	exit;
}

class ThisPage extends Page {

	function mainContent() {
        if(startsWith($GLOBALS['gUserRow']['password'], "SSO_")) {
            ?>
            <div>Your login is handled by Single Sign-on (SSO).  Please reset your password on the SSO provider system.</div>
            <?php
            return;
        }
		echo $this->iPageData['content'];
		switch ($_GET['url_subpage']) {
			case "forced":
				$errorMessage = "You must change your password.";
				break;
			case "expired":
				$errorMessage = "Your password has expired and must be changed.";
				break;
		}
		$securityQuestionId = getFieldFromId("security_question_id", "users", "user_id", $GLOBALS['gUserId']);
		$secondarySecurityQuestionId = getFieldFromId("secondary_security_question_id", "users", "user_id", $GLOBALS['gUserId']);
		$minimumPasswordLength = getPreference("minimum_password_length");
		if (empty($minimumPasswordLength)) {
			$minimumPasswordLength = 10;
		}
		if (getPreference("PCI_COMPLIANCE")) {
			$noPasswordRequirements = false;
		} else {
			$noPasswordRequirements = getPreference("no_password_requirements");
		}
		$passwordRequirements = "Password must be at least " . $minimumPasswordLength . " characters long" . ($noPasswordRequirements ? "" : " and include an upper and lowercase letter and a number");
		?>
        <p><?= $passwordRequirements ?></p>
        <form id="_edit_form" method='post'>
            <p id="error_message" class="error-message"><?= $errorMessage ?></p>
			<?php
			if (!empty($securityQuestionId) && !empty($_SESSION['forgot_key']) && getPreference("PCI_COMPLIANCE")) {
				?>
                <p class="info-message highlighted-text">Enter the security answers you have stored in your account.</p>
                <div class="form-line" id="_answer_text_row">
                    <label for="secondary_answer_text"><?= getFieldFromId("security_question", "security_questions", "security_question_id", $securityQuestionId) ?></label>
                    <input tabindex="10" type="text" class="validate[required]" size=40 id="answer_text" name="answer_text">
                    <div class='clear-div'></div>
                </div>

				<?php
				if (!empty($secondarySecurityQuestionId)) {
					?>
                    <div class="form-line" id="_secondary_answer_text_row">
                        <label for="secondary_answer_text"><?= getFieldFromId("security_question", "security_questions", "security_question_id", $secondarySecurityQuestionId) ?></label>
                        <input tabindex="10" type="text" class="validate[required]" size=40 id="secondary_answer_text" name="secondary_answer_text">
                        <div class='clear-div'></div>
                    </div>
					<?php
				}
			}
			if (empty($_SESSION['forgot_key'])) {
				?>
                <div class="form-line" id="_old_password_row">
                    <label for="old_password">Current Password</label>
                    <input tabindex="10" autocomplete="chrome-off" autocomplete="off" class="validate[required]" type="password" size="40" maxlength="40" id="old_password" name="old_password" value=""><span class='fad fa-eye show-password'></span>
                    <div class='clear-div'></div>
                </div>
				<?php
			}
			?>
            <div class="form-line" id="password_row">
                <label for="reset_password">New Password</label>
				<?php
				$minimumPasswordLength = getPreference("minimum_password_length");
				if (empty($minimumPasswordLength)) {
					$minimumPasswordLength = 10;
				}
				if (getPreference("PCI_COMPLIANCE")) {
					$noPasswordRequirements = false;
				} else {
					$noPasswordRequirements = getPreference("no_password_requirements");
				}
				?>
                <input tabindex="10" autocomplete="chrome-off" autocomplete="off" class="<?= ($noPasswordRequirements ? "no-password-requirements" : "") ?> <?= (getPreference("PCI_COMPLIANCE") ? "password-strength" : "") ?> validate[required,custom[pciPassword],minSize[<?= $minimumPasswordLength ?>]]" type="password" size="40" maxlength="40" id="reset_password" name="reset_password" value=""><span class='fad fa-eye show-password'></span>
				<?php if (getPreference("PCI_COMPLIANCE")) { ?>
                    <div class='strength-bar-div hidden' id='password_strength_bar_div'>
                        <p class='strength-bar-label' id='password_strength_bar_label'></p>
                        <div class='strength-bar' id='password_strength_bar'></div>
                    </div>
				<?php } ?>
                <div class='clear-div'></div>
            </div>

            <div class="form-line" id="_password_again_row">
                <label for="reset_password_again">Re-enter New Password</label>
                <input tabindex="10" autocomplete="chrome-off" autocomplete="off" class="validate[required,equals[reset_password]]" type="password" size="40" maxlength="40" id="reset_password_again" name="reset_password_again" value=""><span class='fad fa-eye show-password'></span>
                <div class='clear-div'></div>
            </div>

            <div class="form-line">
                <button id="reset_button">Reset Password</button>
                <div class='clear-div'></div>
            </div>
        </form>
		<?php
		echo $this->iPageData['after_form_content'];
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#reset_password,#reset_password_again").keyup(function (event) {
                if (event.which == 13 || event.which == 3) {
                    $("#reset_button").trigger("click");
                }
                return false;
            });

            $("#reset_button").click(function () {
                if ($("#_edit_form").validationEngine("validate")) {
                    loadAjaxRequest("<?= ($GLOBALS['gUserRow']['administrator_flag'] ? "resetpassword.php" : "reset-password") ?>?ajax=true&url_action=reset_password", $("#_edit_form").serialize(), function(returnArray) {
                        if (!("error_message" in returnArray)) {
                            $("body").data("just_saved", "true");
                            setTimeout(function () {
                                document.location = "/";
                            }, 2000);
                        }
                    });
                }
                return false;
            });
        </script>
		<?php
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "reset_password":
				$forgotReset = false;
				if (!empty($_SESSION['forgot_key'])) {
					$forgotReset = true;
					$resultSet = executeQuery("select * from forgot_data where forgot_key = ? and time_requested >= (now() - interval 30 minute) and ip_address = ?",
						$_SESSION['forgot_key'], $_SERVER['REMOTE_ADDR']);
					if ($forgotRow = getNextRow($resultSet)) {
						$userId = $forgotRow['user_id'];
					} else {
						addSecurityLog("", "FORGOT-INVALID", "Invalid forgot key attempted");
						$returnArray['error_message'] = getSystemMessage("forgot_expired", "Forgot password key has expired. Please try again from the login screen.");
						ajaxResponse($returnArray);
						break;
					}
				} else {
					$userId = $GLOBALS['gUserId'];
				}
				if (empty($_POST['reset_password'])) {
					$returnArray['error_message'] = getSystemMessage("password_required");
					ajaxResponse($returnArray);
					break;
				}
				if (!$forgotReset) {
					$testPassword = hash("sha256", $userId . $GLOBALS['gUserRow']['password_salt'] . $_POST['old_password']);
					if ($testPassword != $GLOBALS['gUserRow']['password']) {
						$returnArray['error_message'] = getSystemMessage("invalid_password");
						ajaxResponse($returnArray);
						break;
					}
				}
				if ($_POST['reset_password'] != $_POST['reset_password_again']) {
					$returnArray['error_message'] = getSystemMessage("passwords_agree");
					ajaxResponse($returnArray);
					break;
				}
				if (!isPCIPassword($_POST['reset_password'])) {
					$minimumPasswordLength = getPreference("minimum_password_length");
					if (empty($minimumPasswordLength)) {
						$minimumPasswordLength = 10;
					}
					if (getPreference("PCI_COMPLIANCE")) {
						$noPasswordRequirements = false;
					} else {
						$noPasswordRequirements = getPreference("no_password_requirements");
					}
					$returnArray['error_message'] = getSystemMessage("password_minimum_standards", "Password does not meet minimum standards. Must be at least " . $minimumPasswordLength .
						" characters long" . ($noPasswordRequirements ? "" : " and include an upper and lowercase letter and a number"));
					ajaxResponse($returnArray);
					break;
				}
				if (getPreference("PCI_COMPLIANCE")) {
					executeQuery("delete from user_passwords where time_changed < date_sub(current_date,interval 2 year)");
					$resultSet = executeQuery("select * from user_passwords where user_id = ?", $userId);
					while ($row = getNextRow($resultSet)) {
						$thisPassword = hash("sha256", $userId . $row['password_salt'] . $_POST['reset_password']);
						if ($thisPassword == $row['password']) {
							$returnArray['error_message'] = getSystemMessage("recent_password", "You cannot reuse a recent password.");
							ajaxResponse($returnArray);
							break;
						}
					}
					if ($forgotReset) {
						$foundUser = false;
						$resultSet = executeQuery("select * from users where user_id = ?", $userId);
						if ($row = getNextRow($resultSet)) {
							$answerText = strtolower(str_replace(" ", "", str_replace("'", "", $row['answer_text'])));
							$secondaryAnswerText = strtolower(str_replace(" ", "", str_replace("'", "", $row['secondary_answer_text'])));
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
								$foundUser = true;
							}
						}
						if (!$foundUser) {
							$resultSet = executeQuery("select user_id from users where user_id = ? and answer_text = ? and " .
								"secondary_answer_text = ?", $userId, $_POST['answer_text'], $_POST['secondary_answer_text']);
							if ($row = getNextRow($resultSet)) {
								$foundUser = true;
							}
						}
						if (!$foundUser) {
							$returnArray['error_message'] = getSystemMessage("invalid_credentials");
							ajaxResponse($returnArray);
							break;
						}
					}
				}
				$passwordSalt = getRandomString(64);
				$password = hash("sha256", $userId . $passwordSalt . $_POST['reset_password']);
				executeQuery("insert into user_passwords (user_id,password_salt,password) values (?,?,?)", $userId, $passwordSalt, $password);
				$resultSet = executeQuery("update users set password = ?,password_salt = ?,last_password_change = now(),force_password_change = 0 where user_id = ?", $password, $passwordSalt, $userId);
				if (empty($resultSet['sql_error'])) {
					$returnArray['info_message'] = getSystemMessage("password_reset");
					executeQuery("delete from forgot_data where forgot_key = ? or time_requested < (now() - interval 30 minute)", $_SESSION['forgot_key']);
					if ($forgotReset) {
						$_SESSION = array();
						saveSessionData();
						login($userId);
					}
					addSecurityLog($GLOBALS['gUserRow']['user_name'], "PASSWORD-RESET", "Password changed");
					addActivityLog("Reset Password");
				} else {
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function internalCSS() {
		?>
        <style>
            .strength-bar-div {
                height: 16px;
                width: 200px;
                margin: 0;
                margin-top: 10px;
                display: block;
                top: 5px;
            }

            #_main_content p.strength-bar-label {
                font-size: .6rem;
                margin: 0;
            }

            .strength-bar {
                font-size: 1px;
                height: 8px;
                width: 10px;
            }
        </style>
		<?php
	}
}

$pageObject = new ThisPage();
$pageObject->displayPage();
