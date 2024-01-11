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

$GLOBALS['gPageCode'] = "CREATEDESIGNATIONUSER";
require_once "shared/startup.inc";

class CreateDesignationUserPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "create_user":
				$resultSet = executeQuery("select * from users where contact_id = ?" . ($GLOBALS['gDomainClientId'] ? " and client_id = " . $GLOBALS['gDomainClientId'] : ""), $_POST['contact_id']);
				if ($row = getNextRow($resultSet)) {
					$returnArray['error_message'] = "There is already a user for this contact. Please see the system administrator.";
					ajaxResponse($returnArray);
					break;
				}
				$resultSet = executeQuery("select designation_id,client_id from designations where designation_code = ? and " .
					"client_id = (select client_id from clients where client_code = ?) and " .
					"designation_id in (select designation_id from designation_email_addresses where email_address = ?) and contact_id = ? and inactive = 0",
					$_POST['designation_code'], $_POST['client_code'], $_POST['email_address'], $_POST['contact_id']);
				$userId = "";
				if ($row = getNextRow($resultSet)) {
					$designationUserId = getFieldFromId("user_id", "designation_users", "designation_id", $row['designation_id']);
					if (empty($designationUserId)) {
						$designationId = $row['designation_id'];
						$clientId = $row['client_id'];
						$resultSet = executeQuery("select contact_id from contacts where client_id = ? and contact_id not in (select contact_id from users) and contact_id = ?", $clientId, $_POST['contact_id']);
						if ($row = getNextRow($resultSet)) {
							$passwordSalt = getRandomString(64);
							$password = hash("sha256", $passwordSalt . $_POST['password']);

							$checkUserId = getFieldFromId("user_id", "users", "user_name", strtolower($_POST['user_name']), "client_id = ? or superuser_flag = 1", $GLOBALS['gClientId']);
							if (!empty($checkUserId)) {
								$returnArray['error_message'] = "Unable to create user. Please see the system administrator. " . $resultSet['sql_error'];
								ajaxResponse($returnArray);
								break;
							}

							$usersTable = new DataTable("users");
							if (!$userId = $usersTable->saveRecord(array("name_values"=>array("client_id"=>$GLOBALS['gClientId'],"contact_id"=>$row['contact_id'],"user_name"=>strtolower($_POST['user_name']),
								"password_salt"=>$passwordSalt,"password"=>$password,"administrator_flag"=>"1","date_created"=>date("Y-m-d H:i:s"))))) {
								$returnArray['error_message'] = "Unable to create user. Please see the system administrator.";
							} else {
								$password = hash("sha256", $userId . $passwordSalt . $_POST['password']);
								executeQuery("insert into user_passwords (user_id,password_salt,password) values (?,?,?)", $userId, $passwordSalt, $password);
								executeQuery("update users set password = ? where user_id = ?", $password, $userId);
								executeQuery("insert ignore into designation_users (designation_id,user_id) values (?,?)", $designationId, $userId);
								$_SESSION = array();
								saveSessionData();
								login($userId);
							}
						}
					}
				}
				if (empty($userId)) {
					addSecurityLog($_POST['user_name'], "CREATE-USER", 'Attempt to create a user for a designation failed');
					$returnArray['error_message'] = "User '" . $_POST['user_name'] . "' unable to be created. Please see the system administrator.";
				} else {
					$returnArray['info_message'] = "User '" . $_POST['user_name'] . "' successfully created.";
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function mainContent() {
		$officeCode = getFieldFromId("client_code", "clients", "client_code", $_GET['office_code']);
		$designationCode = getFieldFromId("designation_code", "designations", "designation_code", $_GET['designation_code']);
		$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $_GET['contact_id']);
		$emailAddress = $_GET['email_address'];
		?>
        <div id="report_form">
            <form id="_edit_form" name="_edit_form" method="POST">

                <h2>Credentials</h2>
                <div class="form-line" id="_client_code_row">
                    <label for="client_code" class="required-label">Office Code</label>
                    <input tabindex="10" type="text" size="20" maxlength="20" class="validate[required] uppercase" id="client_code" name="client_code" value="<?= $officeCode ?>">
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_designation_code_row">
                    <label for="designation_code" class="required-label">Designation Code</label>
                    <input tabindex="10" type="text" size="20" maxlength="100" class="validate[required] uppercase" id="designation_code" name="designation_code" value="<?= $designationCode ?>">
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_contact_id_row">
                    <label for="contact_id" class="required-label">Your Contact ID</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[integer],required]" id="contact_id" name="contact_id" value="<?= $contactId ?>">
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_email_address_row">
                    <label for="email_address" class="required-label">Email Address</label>
                    <input tabindex="10" type="text" size="30" maxlength="60" class="validate[custom[email],required]" id="email_address" name="email_address" value="<?= htmlText($emailAddress) ?>">
                    <div class='clear-div'></div>
                </div>

                <h2>New User Information</h2>

                <div class="form-line" id="_user_name_row">
                    <label for="user_name" class="required-label">User Name</label>
                    <input tabindex="10" type="text" size="12" maxlength="20" class="validate[required] allow-dash" id="user_name" name="user_name">
                    <span class="extra-info" id="_user_name_message"></span>
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_password_row">
                    <label for="password" class="required-label">Password</label>
                    <input type='password' tabindex='10' class='password-strength validate[required]' size='15' maxlength='255' name='password' id='password'>
                    <div class='strength-bar-div hidden' id='password_strength_bar_div'>
                        <p class='strength-bar-label' id='password_strength_bar_label'></p>
                        <div class='strength-bar' id='password_strength_bar'></div>
                    </div>
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_password_again_row">
                    <label for="password_again" class="">Re-enter Password</label>
                    <input class="validate[equals[password]]" type='password' tabindex='10' size='15' maxlength='255' name='password_again' id='password_again'>
                    <div class='clear-div'></div>
                </div>

                <div class="form-line">
                    <label></label>
                    <button id="create_user" tabindex="10">Create User</button>
                    <div class='clear-div'></div>
                </div>

            </form>
        </div>
		<?php
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#user_name").change(function () {
                if (!empty($(this).val())) {
                    loadAjaxRequest("/checkusername.php?ajax=true&user_name=" + $(this).val(), function(returnArray) {
                        $("#_user_name_message").removeClass("info-message").removeClass("error-message");
                        if ("info_user_name_message" in returnArray) {
                            $("#_user_name_message").html(returnArray['info_user_name_message']).addClass("info-message");
                        }
                        if ("error_user_name_message" in returnArray) {
                            $("#_user_name_message").html(returnArray['error_user_name_message']).addClass("error-message");
                            $("#user_name").val("");
                            $("#user_name").focus();
                            setTimeout(function () {
                                $("#_edit_form").validationEngine("hideAll");
                            }, 10);
                        }
                    });
                } else {
                    $("#_user_name_message").val("");
                }
            });
            $(document).on("tap click", "#create_user", function () {
                if ($("#_edit_form").validationEngine("validate")) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_user", $("#_edit_form").serialize(), function(returnArray) {
                        if ("info_message" in returnArray) {
                            $("#report_form").html("");
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
            #_button_row {
                margin-bottom: 20px;
            }

            #_user_name_message {
                font-size: 14px;
                font-weight: bold;
            }
        </style>
		<?php
	}
}

$pageObject = new CreateDesignationUserPage();
$pageObject->displayPage();
