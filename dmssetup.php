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

$GLOBALS['gPageCode'] = "DMSSETUP";
require_once "shared/startup.inc";

class DMSSetupPage extends Page {

	var $iPaymentMethodTypes = array(
		array("payment_method_type_code" => "CREDIT_CARD", "description" => "Credit Card"),
		array("payment_method_type_code" => "BANK_ACCOUNT", "description" => "Bank Account")
	);

	var $iPaymentMethods = array(
		array("payment_method_code" => "VISA", "description" => "Visa", "payment_method_type_code" => "CREDIT_CARD"),
		array("payment_method_code" => "MASTERCARD", "description" => "MasterCard", "payment_method_type_code" => "CREDIT_CARD"),
		array("payment_method_code" => "AMEX", "description" => "American Express", "payment_method_type_code" => "CREDIT_CARD"),
		array("payment_method_code" => "DISCOVER", "description" => "Discover", "payment_method_type_code" => "CREDIT_CARD"),
		array("payment_method_code" => "ECHECK", "description" => "eCheck", "payment_method_type_code" => "BANK_ACCOUNT"),
		array("payment_method_code" => "CASH", "description" => "eCheck", "payment_method_type_code" => "", "internal_use_only" => true),
		array("payment_method_code" => "CHECK", "description" => "eCheck", "payment_method_type_code" => "", "internal_use_only" => true)
	);

	function setup() {
		setUserPreference("MAINTENANCE_SAVE_NO_LIST", "true", $GLOBALS['gPageRow']['page_code']);
		$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("add", "delete", "list"));

		$paymentMethodTypes = array();
		foreach ($this->iPaymentMethodTypes as $index => $thisPaymentMethodType) {
			$paymentMethodTypeId = getFieldFromId("payment_method_type_id", "payment_method_types", "payment_method_type_code", $thisPaymentMethodType['payment_method_type_code']);
			if (empty($paymentMethodTypeId)) {
				$insertSet = executeQuery("insert into payment_method_types (client_id,payment_method_type_code,description) values (?,?,?)", $GLOBALS['gClientId'], $thisPaymentMethodType['payment_method_type_code'], $thisPaymentMethodType['description']);
				$paymentMethodTypeId = $insertSet['insert_id'];
			}
			$paymentMethodTypes[$thisPaymentMethodType['payment_method_type_code']] = $paymentMethodTypeId;
		}
		foreach ($this->iPaymentMethods as $index => $thisPaymentMethod) {
			$paymentMethodId = getFieldFromId("payment_method_id", "payment_methods", "payment_method_code", $thisPaymentMethod['payment_method_code']);
			if (empty($paymentMethodId)) {
				$insertSet = executeQuery("insert into payment_methods (client_id,payment_method_code,description,payment_method_type_id,internal_use_only) values (?,?,?,?,?)",
					$GLOBALS['gClientId'], $thisPaymentMethod['payment_method_code'], $thisPaymentMethod['description'], $paymentMethodTypes[$thisPaymentMethod['payment_method_type_code']], (empty($thisPaymentMethod['internal_use_only']) ? 0 : 1));
			}
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "test_merchant":
				$eCommerce = eCommerce::getEcommerceInstance($_GET['merchant_account_id']);

				if ($eCommerce->testConnection()) {
					$returnArray['test_merchant_results'] = "Connection to Merchant Account works";
					$returnArray['class'] = "green-text";
				} else {
					$returnArray['test_merchant_results'] = "Connection to Merchant Account DOES NOT work";
					$returnArray['class'] = "red-text";
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function massageDataSource() {
		$this->iDataSource->setFilterWhere("clients.client_id = " . $GLOBALS['gClientId']);
		$this->iDataSource->setJoinTable("contacts", "contact_id", "contact_id");
		$this->iDataSource->setSaveOnlyPresent(true);

		$this->iDataSource->addColumnControl("setup_email_credentials", "data_type", "tinyint");
		$this->iDataSource->addColumnControl("setup_email_credentials", "form_label", "Set up the default Email Credentials");
		$this->iDataSource->addColumnControl("setup_merchant_account", "data_type", "tinyint");
		$this->iDataSource->addColumnControl("setup_merchant_account", "form_label", "Set up the default Merchant Account");

		$this->iDataSource->addColumnControl("web_page", "not_null", true);
		$this->iDataSource->addColumnControl("web_page", "help_label", "This will be used as your primary domain name.");

		$this->iDataSource->addColumnLikeColumn("email_credentials_full_name", "email_credentials", "full_name");
		$this->iDataSource->addColumnLikeColumn("email_credentials_email_address", "email_credentials", "email_address");
		$this->iDataSource->addColumnLikeColumn("email_credentials_smtp_host", "email_credentials", "smtp_host");
		$this->iDataSource->addColumnLikeColumn("email_credentials_smtp_port", "email_credentials", "smtp_port");
		$this->iDataSource->addColumnLikeColumn("email_credentials_security_setting", "email_credentials", "security_setting");
		$this->iDataSource->addColumnLikeColumn("email_credentials_smtp_authentication_type", "email_credentials", "smtp_authentication_type");
		$this->iDataSource->addColumnLikeColumn("email_credentials_smtp_user_name", "email_credentials", "smtp_user_name");
		$this->iDataSource->addColumnLikeColumn("email_credentials_smtp_password", "email_credentials", "smtp_password");
		$resultSet = executeQuery("select * from page_controls where page_id = (select page_id from pages where page_code = 'EMAILCREDENTIALMAINT')");
		while ($row = getNextRow($resultSet)) {
			$this->iDataSource->addColumnControl("email_credentials_" . $row['column_name'], $row['control_name'], $row['control_value']);
		}
		$this->iDataSource->addColumnLikeColumn("fragments_content", "fragments", "content");
		$resultSet = executeQuery("select * from page_controls where page_id = (select page_id from pages where page_code = 'FRAGMENTMAINT')");
		while ($row = getNextRow($resultSet)) {
			$this->iDataSource->addColumnControl("fragments_" . $row['column_name'], $row['control_name'], $row['control_value']);
		}

		$this->iDataSource->addColumnControl("designation_types", "data_type", "custom");
		$this->iDataSource->addColumnControl("designation_types", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("designation_types", "form_label", "Designation Types");
		$this->iDataSource->addColumnControl("designation_types", "list_table", "designation_types");
		$this->iDataSource->addColumnControl("designation_types", "column_list", array("designation_type_code", "description", "payment_type", "individual_support"));
		$this->iDataSource->addColumnControl("designation_types", "list_table_controls", array("designation_type_code" => array("form_label" => "Code"), "payment_type" => array("data_type" => "select", "choices" => array("D" => "Direct Debit", "C" => "Check"))));

		$this->iDataSource->addColumnControl("designation_groups", "data_type", "custom");
		$this->iDataSource->addColumnControl("designation_groups", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("designation_groups", "form_label", "Designation Groups");
		$this->iDataSource->addColumnControl("designation_groups", "column_list", array("designation_group_code", "description"));
		$this->iDataSource->addColumnControl("designation_groups", "list_table", "designation_groups");

		$this->iDataSource->addColumnLikeColumn("merchant_accounts_merchant_account_id", "merchant_accounts", "merchant_account_id");
		$this->iDataSource->addColumnControl("merchant_accounts_merchant_account_id", "data_type", "hidden");
		$this->iDataSource->addColumnLikeColumn("merchant_accounts_merchant_service_id", "merchant_accounts", "merchant_service_id");
		$this->iDataSource->addColumnLikeColumn("merchant_accounts_account_login", "merchant_accounts", "account_login");
		$this->iDataSource->addColumnLikeColumn("merchant_accounts_account_key", "merchant_accounts", "account_key");
		$this->iDataSource->addColumnLikeColumn("merchant_accounts_merchant_identifier", "merchant_accounts", "merchant_identifier");
		$resultSet = executeQuery("select * from page_controls where page_id = (select page_id from pages where page_code = 'MERCHANTACCOUNTMAINT')");
		while ($row = getNextRow($resultSet)) {
			$this->iDataSource->addColumnControl("merchant_accounts_" . $row['column_name'], $row['control_name'], $row['control_value']);
		}

		$this->iDataSource->addColumnControl("privacy_text", "data_type", "text");
		$this->iDataSource->addColumnControl("privacy_text", "wysiwyg", "true");
		$this->iDataSource->addColumnControl("refund_text", "data_type", "text");
		$this->iDataSource->addColumnControl("refund_text", "wysiwyg", "true");

		$this->iDataSource->addColumnControl("recurring_donation_types", "data_type", "custom");
		$this->iDataSource->addColumnControl("recurring_donation_types", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("recurring_donation_types", "form_label", "Recurring Donation Types");
		$this->iDataSource->addColumnControl("recurring_donation_types", "list_table", "recurring_donation_types");
		$this->iDataSource->addColumnControl("recurring_donation_types", "column_list", array("recurring_donation_type_code", "description", "units_between", "interval_unit"));
		$this->iDataSource->addColumnControl("recurring_donation_types", "list_table_controls", array("units_between" => array("minimum_value" => "1", "maximum_value" => "2"), "interval_unit" => array("data_type" => "select", "choices" => array('month' => 'Month', 'week' => 'Week', 'day' => 'Day'))));

		$this->iDataSource->addColumnControl("categories", "data_type", "custom");
		$this->iDataSource->addColumnControl("categories", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("categories", "form_label", "Categories");
		$this->iDataSource->addColumnControl("categories", "list_table", "categories");
		$this->iDataSource->addColumnControl("categories", "column_list", array("category_code", "description", "internal_use_only"));

		$this->iDataSource->addColumnControl("mailing_lists", "data_type", "custom");
		$this->iDataSource->addColumnControl("mailing_lists", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("mailing_lists", "form_label", "Mailing Lists");
		$this->iDataSource->addColumnControl("mailing_lists", "list_table", "mailing_lists");
		$this->iDataSource->addColumnControl("mailing_lists", "column_list", array("mailing_list_code", "description", "internal_use_only"));

	}

	function internalCSS() {
		?>
        <style>
            .default-section ul {
                margin-bottom: 10px;
                list-style-type: disc;
                margin-left: 30px;
            }

            .default-section {
                padding: 20px;
                margin: 20px;
                border-bottom: 1px solid rgb(200, 200, 200);
            }

            #_main_content p {
                line-height: 1.4;
            }

            .page-record-display {
                display: none;
            }

            #_contact_left_column {
                float: left;
                width: 50%;
            }

            #_contact_left_column input {
                max-width: 350px;
            }

            #_contact_right_column {
                float: right;
                width: 50%;
            }

            #_contact_right_column input {
                max-width: 350px;
            }

            #tab_10 ul {
                list-style: disc;
                margin-left: 30px;
                margin-top: 5px;
            }

            #tab_10 ul li {
                list-style: disc;
                margin: 15px 0;
                font-size: .9rem;
                line-height: 1.4;
            }

            #tab_10 ul ul {
                list-style: circle;
            }

            #tab_10 ul ul li {
                list-style: circle;
            }

            @media only screen and (max-width: 850px) {
                #_contact_left_column {
                    float: none;
                    width: 100%;
                }

                #_contact_left_column input {
                    max-width: 100%;
                }

                #_contact_right_column {
                    float: none;
                    width: 100%;
                }

                #_contact_right_column input {
                    max-width: 100%;
                }
            }
        </style>
		<?php
	}

	function massageUrlParameters() {
		$_GET['url_subpage'] = $_GET['url_page'];
		$_GET['url_page'] = "show";
		$_GET['primary_id'] = $GLOBALS['gClientId'];
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("keyup", ".filter-text", function (event) {
                const textFilter = $(this).val().toLowerCase();
                if (empty(textFilter)) {
                    $(this).closest("div").find(".filter-section").removeClass("hidden");
                } else {
                    $(this).closest("div").find(".filter-section").each(function () {
                        const description = $(this).find("p").text().toLowerCase() + " " + $(this).find("label").text().toLowerCase();
                        if (description.indexOf(textFilter) >= 0) {
                            $(this).removeClass("hidden");
                        } else {
                            $(this).addClass("hidden");
                        }
                    });
                }
            });
            $("#postal_code").blur(function () {
                if ($("#country_id").val() === "1000") {
                    validatePostalCode();
                }
            });

            $("#country_id").change(function () {
                $("#city").add("#state").prop("readonly", $("#country_id").val() === "1000");
                $("#city").add("#state").attr("tabindex", ($("#country_id").val() === "1000" ? "9999" : "10"));
                $("#_city_row").show();
                $("#_city_select_row").hide();
                if ($("#country_id").val() === "1000") {
                    validatePostalCode();
                }
            });

            $("#city_select").change(function () {
                $("#city").val($(this).val());
                $("#state").val($(this).find("option:selected").data("state"));
            });

            $("#setup_email_credentials").click(function () {
                if ($(this).prop("checked")) {
                    $(".email-credential").removeClass("hidden");
                } else {
                    $(".email-credential").addClass("hidden");
                }
            });

            $("#setup_merchant_account").click(function () {
                if ($(this).prop("checked")) {
                    $(".merchant-account").removeClass("hidden");
                } else {
                    $(".merchant-account").addClass("hidden");
                }
            });

            $("#test_merchant").click(function () {
                if (empty($("#merchant_accounts_merchant_account_id").val())) {
                    displayErrorMessage("Save to create the merchant account first");
                } else {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=test_merchant&merchant_account_id=" + $("#merchant_accounts_merchant_account_id").val(), function(returnArray) {
                        if ("test_merchant_results" in returnArray) {
                            $("#test_merchant_results").html(returnArray['test_merchant_results']).addClass(returnArray['class']);
                        }
                    });
                }
                return false;
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function afterGetRecord() {
                if ($("#setup_email_credentials").prop("checked")) {
                    $(".email-credential").removeClass("hidden");
                } else {
                    $(".email-credential").addClass("hidden");
                }
                if (empty($("#merchant_accounts_merchant_account_id").val())) {
                    $("#test_merchant").addClass("hidden");
                }
                if ($("#setup_merchant_account").prop("checked")) {
                    $(".merchant-account").removeClass("hidden");
                } else {
                    $(".merchant-account").addClass("hidden");
                }
                $("#city").add("#state").prop("readonly", $("#country_id").val() === "1000");
                $("#city").add("#state").attr("tabindex", ($("#country_id").val() === "1000" ? "9999" : "10"));
                $("#_city_select_row").hide();
                $("#_city_row").show();
            }

            function afterSaveChanges() {
                $("body").data("just_saved", "true");
                setTimeout(function () {
                    document.location = "/";
                }, 2000);
                return true;
            }
        </script>
		<?php
	}

	function afterGetRecord(&$returnArray) {
		$postParameters = array("connection_key" => "B6AA29BB062F44B94ADEA4766F3142EF");
		$response = getCurlReturn("https://defaults.coreware.com/api.php?action=get_coreware_defaults", $postParameters);
		$responseArray = json_decode($response, true);
		$defaults = $responseArray['defaults'];

		if (!array_key_exists("emails", $defaults)) {
			$defaults['emails'] = array();
		}
		if (!array_key_exists("fragments", $defaults)) {
			$defaults['fragments'] = array();
		}
		if (!array_key_exists("notifications", $defaults)) {
			$defaults['notifications'] = array();
		}

		ob_start();
		echo "<p>Check the emails you wish to create in the system. Once the email exists in the system, you can go to Contacts->Email->Emails to make changes to it. Make sure the Primary Domain Name is set in the Pages tab.</p>";
		echo "<p><input type='text' tabindex='10' class='filter-text' id='emails_filter_text' placeholder='Filter Emails'></p>";
		foreach ($defaults['emails'] as $thisEmail) {
			$emailId = getFieldFromId("email_id", "emails", "email_code", $thisEmail['email_code']);
			if (empty($emailId)) {
				?>
                <div class='default-section filter-section'>
                    <div class='basic-form-line'>
                        <label for='default_email_<?= $thisEmail['email_code'] ?>'><?= htmlText($thisEmail['description']) ?></label>
                        <select name='default_email_<?= $thisEmail['email_code'] ?>' id='default_email_<?= $thisEmail['email_code'] ?>'>
                            <option value=''>Use System Default</option>
                            <option value='1'>Customize</option>
                        </select>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>
					<?= makeHtml($thisEmail['detailed_description']) ?>
                </div>
				<?php
			} else {
				executeQuery("update emails set detailed_description = ? where email_id = ?", $thisEmail['detailed_description'], $emailId);
				?>
                <div class='default-section filter-section'><p class='highlighted-text'><?= htmlText($thisEmail['description']) ?></p>
					<?php if (canAccessPageCode("EMAILMAINT")) { ?>
                        <p>Email already created. Click <a href='/emails?url_page=show&primary_id=<?= $emailId ?>' target='_blank'>here</a> to edit it.</p>
					<?php } else { ?>
                        <p>Email already created. Go to Contacts->Email->Emails to make changes.</p>
					<?php } ?>
                </div>
				<?php
			}
		}
		$returnArray['tab_emails'] = array("data_value" => ob_get_clean());

		ob_start();
		echo "<p>While any fragment can be customized, generally, it is best to use the system default for fragments. Customizing a fragment may cause delay of implementation of new CoreFORCE features. Make sure the Primary Domain Name is set in the Pages tab if any fragments are customized. Once you have selected that a fragment is to be customized here, you can make changes to it at Website->Fragments.</p>";
		echo "<p><input type='text' tabindex='10' class='filter-text' id='fragment_filter_text' placeholder='Filter Fragments'></p>";
		foreach ($defaults['fragments'] as $thisFragment) {
			$fragmentId = getFieldFromId("fragment_id", "fragments", "fragment_code", $thisFragment['fragment_code']);
			if (empty($fragmentId)) {
				?>
                <div class='default-section filter-section'>
                    <div class='basic-form-line'>
                        <label for='default_fragment_<?= $thisFragment['fragment_code'] ?>'><?= htmlText($thisFragment['description']) ?></label>
                        <select name='default_fragment_<?= $thisFragment['fragment_code'] ?>' id='default_fragment_<?= $thisFragment['fragment_code'] ?>'>
                            <option value=''>Use System Default</option>
                            <option value='1'>Customize</option>
                        </select>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>
					<?= makeHtml($thisFragment['detailed_description']) ?>
                </div>
				<?php
			} else {
				executeQuery("update fragments set detailed_description = ? where fragment_id = ?", $thisFragment['detailed_description'], $fragmentId);
				?>
                <div class='default-section filter-section'><p class='highlighted-text'><?= htmlText($thisFragment['description']) ?></p>
					<?php if (canAccessPageCode("FRAGMENTMAINT")) { ?>
                        <p>Fragment already created. Click <a href='/fragments?url_page=show&primary_id=<?= $fragmentId ?>' target='_blank'>here</a> to edit it.</p>
					<?php } else { ?>
                        <p>Fragment already created. Go to Website->Fragments to make changes.</p>
					<?php } ?>
                </div>
				<?php
			}
		}
		$returnArray['tab_fragments'] = array("data_value" => ob_get_clean());

		ob_start();
		echo "<p>For each notification, add a comma separated list of email addresses you wish to be included in that notification.</p>";
		echo "<p><input type='text' tabindex='10' class='filter-text' id='notification_filter_text' placeholder='Filter Notifications'></p>";
		foreach ($defaults['notifications'] as $thisNotification) {
			$notificationId = getFieldFromId("notification_id", "notifications", "notification_code", $thisNotification['notification_code']);
			if (empty($notificationId)) {
				?>
                <div class='default-section filter-section'>
                    <div class='basic-form-line'>
                        <label><?= $thisNotification['description'] ?></label>
                        <input type='text' size="100" name='default_notification_<?= $thisNotification['notification_code'] ?>' id='default_notification_<?= $thisNotification['notification_code'] ?>'>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>
					<?= makeHtml($thisNotification['detailed_description']) ?>
                </div>
				<?php
			} else {
				?>
                <div class='default-section filter-section'><p class='highlighted-text'><?= htmlText($thisNotification['description']) ?>
						<?php if (canAccessPageCode("NOTIFICATIONMAINT")) { ?>
                    <p>Notification already created. Click <a href='/notification-maintenance?url_page=show&primary_id=<?= $notificationId ?>' target='_blank'>here</a> to edit it.</p>
					<?php } else { ?>
                        <p>Notification already created. Go to System->Notifications to make changes.</p>
					<?php } ?>
                </div>
				<?php
			}
		}
		$returnArray['tab_notifications'] = array("data_value" => ob_get_clean());

		$logoImageId = getFieldFromId("image_id", "images", "image_code", "HEADER_LOGO");
		$description = getFieldFromId("description", "images", "image_id", $logoImageId);
		if (!empty($logoImageId)) {
			$returnArray['select_values']["logo_image_id"] = array(array("key_value" => $logoImageId, "description" => $description));
		}
		$returnArray['logo_image_id'] = array("data_value" => $logoImageId, "crc_value" => getCrcValue($logoImageId));
		$returnArray["logo_image_id_file"] = array("data_value" => "", "crc_value" => getCrcValue(""));
		$returnArray["logo_image_id_view"] = array("data_value" => (empty($logoImageId) ? "" : getImageFilename($logoImageId)));
		$returnArray["logo_image_id_filename"] = array("data_value" => getFieldFromId("filename", "images", "image_id", $logoImageId, "client_id is not null"));
		$returnArray["remove_logo_image_id"] = array("data_value" => "0", "crc_value" => getCrcValue("0"));

		$resultSet = executeQuery("select * from email_credentials where client_id = ? and email_credential_code = 'DEFAULT'", $GLOBALS['gClientId']);
		if ($row = getNextRow($resultSet)) {
			$returnArray['setup_email_credentials'] = array("data_value" => "1");
			foreach ($row as $fieldName => $fieldValue) {
				if (in_array($fieldName, array("email_credential_id", "email_credential_code", "version"))) {
					continue;
				}
				$returnArray['email_credentials_' . $fieldName] = array("data_value" => $fieldValue, "crc_value" => getCrcValue($fieldValue));
			}
		}

		$resultSet = executeQuery("select * from fragments where (client_id = ? or client_id = ?) and fragment_code = 'ONLINE_DONATION_RESPONSE' order by client_id desc", $GLOBALS['gClientId'], $GLOBALS['gDefaultClientId']);
		if ($row = getNextRow($resultSet)) {
			foreach ($row as $fieldName => $fieldValue) {
				if (!in_array($fieldName, array("content"))) {
					continue;
				}
				$returnArray['fragments_' . $fieldName] = array("data_value" => $fieldValue, "crc_value" => getCrcValue($fieldValue));
			}
		} else {
			ob_start();
			?>
            <p>Thank you for your donation to Organization Name.</p>
            <p>Here is the confirmation of your donation. Print this page for your records. You will also receive an email confirmation of this transaction.</p>
            <p>Designated for: %designation_description%<br>
                Donation Amount: $%amount%</p>
            <p>Donor Information:<br>
                %address_block%</p>

            <p>If you selected a recurring giving option, your account will be continued to be charged the amount specified according to the frequency specified. If you need to make any changes to your giving account, please email us at accounting@yourdomainname.org.</p>
            <p>If you need to make any changes to your giving account or see past donations, please login to manage your donation on the <a href="https://www.yourdomainname.org/my-account">My Account page</a>. If you have not created an account, you will be prompted to.</p>
            <p>If you have any questions, please feel free to contact us.</p>
            <p>Thank you again for your generous gift!</p>

            <p>Click <a href="/">here to return to the home page</a>.</p>
			<?php
			$content = ob_get_clean();
			$returnArray['fragments_content'] = array("data_value" => $content, "crc_value" => getCrcValue($content));
		}

		$resultSet = executeQuery("select * from merchant_accounts where client_id = ? and merchant_account_code = 'DEFAULT'", $GLOBALS['gClientId']);
		if ($row = getNextRow($resultSet)) {
			$returnArray['setup_merchant_account'] = array("data_value" => "1");
			foreach ($row as $fieldName => $fieldValue) {
				if (!in_array($fieldName, array("merchant_account_id", "merchant_service_id", "account_login", "account_key", "merchant_identifier"))) {
					continue;
				}
				$returnArray['merchant_accounts_' . $fieldName] = array("data_value" => $fieldValue, "crc_value" => getCrcValue($fieldValue));
			}
		}

		$privacyText = "";
		$resultSet = executeQuery("select text_data,(select client_id from pages where page_id = page_data.page_id) client_id from page_data where page_id = " .
			"(select page_id from pages where (client_id = ? or client_id = ?) and link_name = 'privacy-policy' order by client_id desc limit 1) and " .
			"template_data_id = (select template_data_id from template_data where data_name = 'content') order by client_id desc", $GLOBALS['gClientId'], $GLOBALS['gDefaultClientId']);
		if ($row = getNextRow($resultSet)) {
			$privacyText = $row['text_data'];
		}
		$returnArray['privacy_text'] = array("data_value" => $privacyText, "crc_value" => getCrcValue($privacyText));

		$refundText = "";
		$resultSet = executeQuery("select text_data,(select client_id from pages where page_id = page_data.page_id) client_id from page_data where page_id = " .
			"(select page_id from pages where (client_id = ? or client_id = ?) and link_name = 'refund-policy' order by client_id desc limit 1) and " .
			"template_data_id = (select template_data_id from template_data where data_name = 'content') order by client_id desc", $GLOBALS['gClientId'], $GLOBALS['gDefaultClientId']);
		if ($row = getNextRow($resultSet)) {
			$refundText = $row['text_data'];
		}
		$returnArray['refund_text'] = array("data_value" => $refundText, "crc_value" => getCrcValue($refundText));
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		if (array_key_exists("logo_image_id_file", $_FILES) && !empty($_FILES['logo_image_id_file']['name'])) {
			$logoImageId = createImage("logo_image_id_file", array("maximum_width" => 500, "maximum_height" => 500, "image_code" => "HEADER_LOGO", "client_id" => $nameValues['primary_id']));
			if ($logoImageId === false) {
				return "Unable to Create Image";
			}
		}

		if (!empty($_POST['setup_email_credentials'])) {
			$emailCredentialRow = getRowFromId("email_credentials", "email_credential_code", "DEFAULT");
			$dataSource = new DataSource("email_credentials");
			if (empty($emailCredentialRow)) {
				$emailCredentialRow['description'] = "Default Email Credentials";
				$emailCredentialRow['email_credential_code'] = "DEFAULT";
                $emailCredentialRow['user_id'] = $GLOBALS['gUserId'];
			}
			foreach ($_POST as $fieldName => $fieldValue) {
				if (substr($fieldName, 0, strlen("email_credentials_")) == "email_credentials_") {
					$emailCredentialRow[substr($fieldName, strlen("email_credentials_"))] = $fieldValue;
				}
			}
			if (!$dataSource->saveRecord(array("name_values" => $emailCredentialRow, "primary_id" => $emailCredentialRow['email_credential_id']))) {
				return $dataSource->getErrorMessage();
			}
		}

		$fragmentRow = getRowFromId("fragments", "fragment_code", "ONLINE_DONATION_RESPONSE");
		$dataSource = new DataSource("fragments");
		if (empty($fragmentRow)) {
			$fragmentRow['description'] = "Default Online Response";
			$fragmentRow['fragment_code'] = "ONLINE_DONATION_RESPONSE";
		}
		foreach ($nameValues as $fieldName => $fieldValue) {
			if (substr($fieldName, 0, strlen("fragments_")) == "fragments_") {
				$fragmentRow[substr($fieldName, strlen("fragments_"))] = $fieldValue;
			}
		}
		if (!$dataSource->saveRecord(array("name_values" => $fragmentRow, "primary_id" => $fragmentRow['fragment_id']))) {
			return $dataSource->getErrorMessage();
		}

		if (!empty($_POST['setup_merchant_account'])) {
			$merchantAccountRow = getRowFromId("merchant_accounts", "merchant_account_code", "DEFAULT");
			$dataSource = new DataSource("merchant_accounts");
			if (empty($merchantAccountRow)) {
				$merchantAccountRow['description'] = "Default Merchant Account";
				$merchantAccountRow['merchant_account_code'] = "DEFAULT";
			}
			foreach ($_POST as $fieldName => $fieldValue) {
				if (substr($fieldName, 0, strlen("merchant_accounts_")) == "merchant_accounts_") {
					$merchantAccountRow[substr($fieldName, strlen("merchant_accounts_"))] = $fieldValue;
				}
			}
			if (!$dataSource->saveRecord(array("name_values" => $merchantAccountRow, "primary_id" => $merchantAccountRow['merchant_account_id']))) {
				return $dataSource->getErrorMessage();
			}
		}

		$clientCode = getFieldFromId("client_code", "clients", "client_id", $GLOBALS['gClientId']);
		$templateDataId = getFieldFromId("template_data_id", "template_data", "data_name", "content");
		$pageId = getFieldFromId("page_id", "pages", "link_name", "privacy-policy");
		if (empty($pageId)) {
			$resultSet = executeQuery("insert into pages (client_id,page_code,description,link_name,date_created,creator_user_id) values " .
				"(?,?,?,'privacy-policy',now(),?)", $GLOBALS['gClientId'], $clientCode . "_PRIVACY_POLICY", $GLOBALS['gClientName'] . " Privacy Policy", $GLOBALS['gUserId']);
			$pageId = $resultSet['insert_id'];
		}
		$pageDataId = getFieldFromId("page_data_id", "page_data", "page_id", $pageId, "template_data_id = ?", $templateDataId);
		if (empty($pageDataId)) {
			executeQuery("insert into page_data (page_id,template_data_id,text_data) values (?,?,?)", $pageId, $templateDataId, $nameValues['privacy_text']);
		} else {
			executeQuery("update page_data set text_data = ? where page_data_id = ?", $nameValues['privacy_text'], $pageDataId);
		}

		$pageId = getFieldFromId("page_id", "pages", "link_name", "refund-policy");
		if (empty($pageId)) {
			$resultSet = executeQuery("insert into pages (client_id,page_code,description,link_name,date_created,creator_user_id) values " .
				"(?,?,?,'refund-policy',now(),?)", $GLOBALS['gClientId'], $clientCode . "_REFUND_POLICY", $GLOBALS['gClientName'] . " Refund Policy", $GLOBALS['gUserId']);
			$pageId = $resultSet['insert_id'];
		}
		$pageDataId = getFieldFromId("page_data_id", "page_data", "page_id", $pageId, "template_data_id = ?", $templateDataId);
		if (empty($pageDataId)) {
			executeQuery("insert into page_data (page_id,template_data_id,text_data) values (?,?,?)", $pageId, $templateDataId, $nameValues['refund_text']);
		} else {
			executeQuery("update page_data set text_data = ? where page_data_id = ?", $nameValues['refund_text'], $pageDataId);
		}

		$postParameters = array("connection_key" => "B6AA29BB062F44B94ADEA4766F3142EF");
		$response = getCurlReturn("https://defaults.coreware.com/api.php?action=get_coreware_defaults", $postParameters);
		$responseArray = json_decode($response, true);
		$defaults = $responseArray['defaults'];

		if (!array_key_exists("emails", $defaults)) {
			$defaults['emails'] = array();
		}
		if (!array_key_exists("fragments", $defaults)) {
			$defaults['fragments'] = array();
		}
		if (!array_key_exists("notifications", $defaults)) {
			$defaults['notifications'] = array();
		}

		$clientAddressBlock = $GLOBALS['gClientRow']['address_1'];
		$clientCity = $GLOBALS['gClientRow']['city'];
		if (!empty($GLOBALS['gClientRow']['state'])) {
			$clientCity .= (empty($clientCity) ? "" : ", ") . $GLOBALS['gClientRow']['state'];
		}
		if (!empty($GLOBALS['gClientRow']['postal_code'])) {
			$clientCity .= (empty($clientCity) ? "" : ", ") . $GLOBALS['gClientRow']['postal_code'];
		}
		if (!empty($clientCity)) {
			$clientAddressBlock .= (empty($clientAddressBlock) ? "" : "<br>\n") . $clientCity;
		}
		$clientCountry = "";
		if ($GLOBALS['gClientRow']['country_id'] != 1000) {
			$clientCountry = getFieldFromId("country_name", "countries", "country_id", $GLOBALS['gClientRow']['country_id']);
		}
		if (!empty($clientCountry)) {
			$clientAddressBlock .= (empty($clientAddressBlock) ? "" : "<br>\n") . $clientCountry;
		}
		$clientFields = array("client_name" => $GLOBALS['gClientRow']['business_name'],
			"client_address_1" => $GLOBALS['gClientRow']['address_1'],
			"client_city" => $GLOBALS['gClientRow']['city'],
			"client_state" => $GLOBALS['gClientRow']['state'],
			"client_postal_code" => $GLOBALS['gClientRow']['postal_code'],
			"client_address_block" => $clientAddressBlock,
			"client_domain_name" => $_POST['domain_name'],
			"client_email_address" => $GLOBALS['gClientRow']['email_address']
		);

		foreach ($defaults['emails'] as $thisEmail) {
			$emailId = getFieldFromId("email_id", "emails", "email_code", $thisEmail['email_code']);
			if (empty($emailId)) {
				foreach ($clientFields as $thisFieldName => $thisFieldValue) {
					$thisEmail['subject'] = str_replace("%" . $thisFieldName . "%", $thisFieldValue, $thisEmail['subject']);
					$thisEmail['content'] = str_replace("%" . $thisFieldName . "%", $thisFieldValue, $thisEmail['content']);
				}
				if (!empty($_POST['default_email_' . $thisEmail['email_code']])) {
					executeQuery("insert into emails (client_id,email_code,description,detailed_description,subject,content) values (?,?,?,?,?, ?)", $GLOBALS['gClientId'], $thisEmail['email_code'],
						$thisEmail['description'], $thisEmail['detailed_description'], $thisEmail['subject'], $thisEmail['content']);
				}
			}
		}
		foreach ($defaults['fragments'] as $thisFragment) {
			$fragmentId = getFieldFromId("fragment_id", "fragments", "fragment_code", $thisFragment['fragment_code']);
			if (empty($fragmentId)) {
				foreach ($clientFields as $thisFieldName => $thisFieldValue) {
					$thisFragment['content'] = str_replace("%" . $thisFieldName . "%", $thisFieldValue, $thisFragment['content']);
				}
				if (!empty($_POST['default_fragment_' . $thisFragment['fragment_code']])) {
					executeQuery("insert into fragments (client_id,fragment_code,description,detailed_description,content) values (?,?,?,?,?)", $GLOBALS['gClientId'], $thisFragment['fragment_code'],
						$thisFragment['description'], $thisFragment['detailed_description'], $thisFragment['content']);
				}
			}
		}
		foreach ($defaults['notifications'] as $thisNotification) {
			$notificationId = getFieldFromId("notification_id", "notifications", "notification_code", $thisNotification['notification_code']);
			if (empty($notificationId)) {
				if (!empty($_POST['default_notification_' . $thisNotification['notification_code']])) {
					$insertSet = executeQuery("insert into notifications (client_id,notification_code,description,detailed_description) values (?,?,?,?)", $GLOBALS['gClientId'], $thisNotification['notification_code'],
						$thisNotification['description'], $thisNotification['detailed_description']);
					$notificationId = $insertSet['insert_id'];
					$emailAddresses = explode(",", str_replace(" ", ",", $_POST['default_notification_' . $thisNotification['notification_code']]));
					foreach ($emailAddresses as $emailAddress) {
						executeQuery("insert into notification_emails (notification_id,email_address) values (?,?)", $notificationId, trim($emailAddress));
					}
				}
			}
		}

		if (!empty($_POST['web_page'])) {
			$domainName = (substr($_POST['web_page'], 0, 4) == "http" ? $_POST['domain_name'] : "https://" . $_POST['web_page']);
			$preferenceId = getFieldFromId("preference_id", "preferences", "preference_code", "WEB_URL");
			executeQuery("delete from client_preferences where client_id = ? and preference_id = ?", $GLOBALS['gClientId'], $preferenceId);
			executeQuery("insert into client_preferences (client_id,preference_id,preference_value) values (?,?,?)", $GLOBALS['gClientId'], $preferenceId, $domainName);
		}

		return true;
	}
}

$pageObject = new DMSSetupPage("clients");
$pageObject->displayPage();
