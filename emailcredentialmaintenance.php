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

$GLOBALS['gPageCode'] = "EMAILCREDENTIALMAINT";
require_once "shared/startup.inc";

class EmailCredentialsMaintenancePage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addIncludeColumn(array("email_credential_code", "description", "full_name", "email_address", "smtp_host", "smtp_port", "security_setting", "smtp_authentication_type", "smtp_user_name", "smtp_password", "pop_host", "pop_port", "pop_security_setting", "pop_user_name", "pop_password"));
			$this->iTemplateObject->getTableEditorObject()->addExcludeListColumn(array("smtp_password", "pop_password", "user_id"));
		}
		$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("send_test_email" => array("icon" => "fad fa-envelope", "label" => getLanguageText("Send Test Email"), "disabled" => false)));
        if($GLOBALS['gUserRow']['superuser_flag']) {
            $this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("use_coreware_ses" => array("icon" => "fad fa-cog", "label" => getLanguageText("Use Coreware SES"), "disabled" => false)));
        }
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "send_test_email":
				$result = sendTestEmail($_POST);
				if ($result === true) {
					$returnArray['info_message'] = "Test email successfully sent to " . $GLOBALS['gUserRow']['email_address'];
				} else {
					$returnArray['error_message'] = $result;
				}
				ajaxResponse($returnArray);
				break;
			case "suggest_server_settings":
				if (empty($_POST['email_address'])) {
					$returnArray['error_message'] = "Enter email address first.";
				} else {
					$hostName = explode("@", $_POST['email_address'])[1];
					$mxRecords = array();
					getmxrr($hostName, $mxRecords);
					foreach ($mxRecords as $thisMxRecord) {
						if (stristr($thisMxRecord, "aspmx.l.google.com") !== false) {
							$returnArray = array('smtp_host' => "smtp.gmail.com", "smtp_port" => "587", "security_setting" => "tls", "smtp_authentication_type" => "");
							break;
						}
						if (stristr($thisMxRecord, "mail.protection.outlook.com") !== false) {
							$returnArray = array('smtp_host' => "smtp.office365.com", "smtp_port" => "587", "security_setting" => "tls", "smtp_authentication_type" => "");
							break;
						}
						if (stristr($thisMxRecord, "olc.protection.outlook.com") !== false) {
							$returnArray = array('smtp_host' => "smtp-mail.outlook.com", "smtp_port" => "587", "security_setting" => "tls", "smtp_authentication_type" => "");
							break;
						}
						if (stristr($thisMxRecord, "yahoodns.net") !== false) {
							$returnArray = array('smtp_host' => "smtp.mail.yahoo.com", "smtp_port" => "465", "security_setting" => "ssl", "smtp_authentication_type" => "");
							break;
						}
						if (stristr($thisMxRecord, "mx1.emailsrvr.com") !== false) {
							$returnArray = array('smtp_host' => "secure.emailsrvr.com", "smtp_port" => "465", "security_setting" => "ssl", "smtp_authentication_type" => "PLAIN");
							break;
						}
						if (stristr($thisMxRecord, "mailchannels.net") !== false || stristr($thisMxRecord, "barracudanetworks.com") !== false) {
							$returnArray['error_message'] = "Cannot detect SMTP settings";
							break;
						}
						$returnArray = array('smtp_host' => "smtp." . $hostName, "smtp_port" => "587", "security_setting" => "tls", "smtp_authentication_type" => "");
					}
				}
				ajaxResponse($returnArray);
				break;
            case "use_coreware_ses":
                if($GLOBALS['gUserRow']['superuser_flag']) {
                    if(empty($_POST['email_address'])) {
                        $returnArray['error_message'] = "Enter email address first";
                    } else {
                        $hasCredentials = !empty($sesAccessKey = getPreference("AWS_SES_ACCESS_KEY"));
                        $hasCredentials = $hasCredentials && !empty($sesSecretKey = getPreference("AWS_SES_SECRET_KEY"));
                        $hasCredentials = $hasCredentials && !empty($sesSmtpUsername = getPreference("AWS_SES_SMTP_USERNAME"));
                        $hasCredentials = $hasCredentials && !empty($sesSmtpPassword = getPreference("AWS_SES_SMTP_PASSWORD"));
                        if ($hasCredentials) {
                            try {
                                $ses = new SimpleEmailService($sesAccessKey, $sesSecretKey);
                                $ses->verifyEmailAddress($_POST['email_address']);
                                $returnArray['info_message'] = "Email identity for " . $_POST['email_address'] . " created in SES.";
                            } catch(Exception $e) {
                                $returnArray['error_message'] = "Setting up email identity in SES failed: " . $e->getMessage();
                            }
                        } else {
                            $returnArray['error_message'] = "SES API credentials are missing. Email identity not created.";
                        }
                        $returnArray = array_merge($returnArray, array('smtp_host' => 'email-smtp.us-east-1.amazonaws.com',
                            'smtp_port' => '587',
                            'security_setting' => 'tls',
                            "smtp_authentication_type" => "",
                            'smtp_user_name' => $sesSmtpUsername,
                            'smtp_password' => $sesSmtpPassword));
                    }
                }
                ajaxResponse($returnArray);
                break;
		}
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		executeQuery("update email_credentials set authentication_error = 0 where email_credential_id = ?", $nameValues['primary_id']);
		return true;
	}

	function massageDataSource() {
		if (!$GLOBALS['gUserRow']['superuser_flag']) {
			$this->iDataSource->addColumnControl("smtp_password", "data_type", "password");
			$this->iDataSource->addColumnControl("smtp_password", "show_data", true);
			$this->iDataSource->addColumnControl("pop_password", "data_type", "password");
			$this->iDataSource->addColumnControl("pop_password", "show_data", true);
		}
		$this->iDataSource->addColumnControl("user_id", "readonly", true);
		$this->iDataSource->addColumnControl("user_id", "default_value", $GLOBALS['gUserId']);
		if (empty($GLOBALS['gUserRow']['administrator_flag'])) {
			$this->iDataSource->setFilterWhere("user_id = " . $GLOBALS['gUserId']);
		}
	}

	function onLoadJavascript() {
        $sesAccessKey = getPreference("AWS_SES_ACCESS_KEY");
        $sesSecretKey = getPreference("AWS_SES_SECRET_KEY");
        $sesCredentialsExist = !empty($sesAccessKey) && !empty($sesSecretKey);
		?>
        <script>
            $(document).on("tap click", "#_send_test_email_button", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=send_test_email", $("#_edit_form").serialize());
                return false;
            });
            $(document).on("tap click", "#suggest_server_settings", function () {
                $("body").addClass("waiting-for-ajax");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=suggest_server_settings", $("#_edit_form").serialize(), function(returnArray) {
                    setServerSettings(returnArray);
                });
                return false;
            });
            <?php if($sesCredentialsExist) { ?>
            $(document).on("tap click", "#_use_coreware_ses_button", function () {
                $('#_confirm_ses_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 600,
                    title: 'Confirm Simple Email Service',
                    buttons:{
                        Continue: function (event) {
                            $("#_confirm_ses_dialog").dialog('close');
                            $("body").addClass("waiting-for-ajax");
                            loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=use_coreware_ses", $("#_edit_form").serialize(), function(returnArray) {
                                setServerSettings(returnArray);
                            });
                        },
                        Cancel: function (event) {
                            $("#_confirm_ses_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
            <?php } else { ?>
            $(document).on("tap click", "#_use_coreware_ses_button", function () {
                $('#_ses_not_setup_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 600,
                    title: 'Simple Email Service Credentials missing',
                    buttons:{
                        Cancel: function (event) {
                            $("#_ses_not_setup_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
            <?php } ?>
            function setServerSettings(returnArray) {
                if (!("error_message" in returnArray)) {
                    $("#smtp_host").val(returnArray['smtp_host']);
                    $("#smtp_port").val(returnArray['smtp_port']);
                    $("#security_setting").find("option").each(function () {
                        if ($(this).val() === returnArray['security_setting']) {
                            selectedIndex = $(this).val();
                            return false;
                        }
                    });
                    $("#security_setting").val(selectedIndex).trigger("change");
                    $("#smtp_authentication_type").find("option").each(function () {
                        if ($(this).val() === returnArray['smtp_authentication_type']) {
                            selectedIndex = $(this).val();
                            return false;
                        }
                    });
                    $("#smtp_authentication_type").val(selectedIndex).trigger("change");
                    if("smtp_user_name" in returnArray) {
                        $("#smtp_user_name").val(returnArray['smtp_user_name']);
                    }
                    if("smtp_password" in returnArray) {
                        $("#smtp_password").val(returnArray['smtp_password']);
                    }
                }
            }
        </script>
		<?php
	}

    function hiddenElements() {
        ?>
            <div id="_confirm_ses_dialog" class="dialog-box" data-keypress_added="false">
                When setting up SES, the following will happen:<ol>
                    <li>The system will create an email identity in SES via API.</li>
                    <li>SES will send an email to that address asking for verification.</li>
                    <li>The client must click the verification link that SES sends.</li></ol>
            </div> <!-- _confirm_ses_dialog -->
        <div id="_ses_not_setup_dialog" class="dialog-box" data-keypress_added="false">
            SES Credentials are not set up.  Add these in System Preferences first.
        </div> <!-- _confirm_ses_dialog -->
        <?php
    }
}

$pageObject = new EmailCredentialsMaintenancePage("email_credentials");
$pageObject->displayPage();
