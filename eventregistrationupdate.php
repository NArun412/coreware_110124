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

$GLOBALS['gPageCode'] = "EVENTREGISTRATIONUPDATE";
require_once "shared/startup.inc";

class EventRegistrationUpdatePage extends Page {

	private $iEventId = "";
	private $iEventRegistrantId = "";
	private $iCustomFieldContent = "";
	private $iCustomFieldData = array();

	function setup() {
		if (!empty($_POST)) {
			$_GET = array_merge($_POST, $_GET);
		}
		$this->iEventId = getFieldFromId("event_id", "events", "event_id", $_GET['event_id'], "(end_date is null or end_date >= current_date)");
		$this->iEventRegistrantId = getFieldFromId("event_registrant_id", "event_registrants", "event_registrant_id",
			$_GET['event_registrant_id'], "contact_id = ?", $GLOBALS['gUserRow']['contact_id']);
		if (empty($this->iEventId) || empty($this->iEventRegistrantId)) {
			header("Location: /");
			exit;
		}
	}

	function getCustomFields() {
		$eventRegistrantRow = getRowFromId("event_registrants", "event_registrant_id", $this->iEventRegistrantId);
		$customFields = CustomField::getCustomFields("event_registrations");
		$resultSet = executeQuery("select * from event_registration_custom_fields where event_id = ? order by sequence_number", $this->iEventId);
		while ($row = getNextRow($resultSet)) {
			if (!array_key_exists($row['custom_field_id'], $customFields)) {
				continue;
			}
			$customField = CustomField::getCustomField($row['custom_field_id']);
			$customField->setPrimaryIdentifier($eventRegistrantRow['event_registrant_id']);
			$customFieldType = getFieldFromId("control_value", "custom_field_controls", "custom_field_id",
				$row['custom_field_id'], "control_name = 'data_type'");
			if (strtolower($customFieldType) == "custom") {
				$this->iCustomFieldContent .= $customField->getControl();
				$this->iCustomFieldData["custom_field_id_" . $row['custom_field_id']] = $customField->getData($eventRegistrantRow['event_registrant_id']);
			} else {
				$this->iCustomFieldContent .= $customField->displayData($eventRegistrantRow['event_registrant_id'], false);
			}
		}
	}

	function headerIncludes() {
		?>
        <script src="<?= autoVersion('/js/jsignature/jSignature.js') ?>"></script>
        <script src="<?= autoVersion('/js/jsignature/jSignature.CompressorSVG.js') ?>"></script>
        <script src="<?= autoVersion('/js/jsignature/jSignature.UndoButton.js') ?>"></script>
        <script src="<?= autoVersion('/js/jsignature/signhere/jSignature.SignHere.js') ?>"></script>
		<?php
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "update":
				$eventId = getFieldFromId("event_id", "events", "event_id", $_POST['event_id'], "(end_date is null or end_date >= current_date)");
				if (empty($eventId)) {
					$returnArray['error_message'] = "Invalid Event";
					ajaxResponse($returnArray);
					break;
				}
				$eventRow = getRowFromId("events", "event_id", $eventId);
				$eventRegistrantRow = getRowFromId("event_registrants", "event_registrant_id", $this->iEventRegistrantId);
				$orderId = $eventRegistrantRow['order_id'];
				$contactId = $eventRegistrantRow['contact_id'];

				$resultSet = executeQuery("select * from event_registration_custom_fields where event_id = ?", $eventId);
				$GLOBALS['gPrimaryDatabase']->startTransaction();
				while ($row = getNextRow($resultSet)) {
					$customField = CustomField::getCustomField($row['custom_field_id'], "custom_field_id_" . $row['custom_field_id'] . (empty($registrantRowNumber) ? "" : "_" . $registrantRowNumber));
					if (!$customField->saveData(array_merge(array("primary_id" => $this->iEventRegistrantId), $_POST))) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = $customField->getErrorMessage();
						ajaxResponse($returnArray);
						break;
					}
				}

				$GLOBALS['gPrimaryDatabase']->commitTransaction();
				if (function_exists("_localServerUpdateEventRegistration")) {
					$result = _localServerUpdateEventRegistration($this->iEventRegistrantId);
					if ($result !== true) {
						$returnArray = $result;
					}
				}

				$substitutions = Events::getEventRegistrationSubstitutions($eventRow, $contactId);

				if (empty($eventRow['email_id'])) {
					$eventRow['email_id'] = getFieldFromId("email_id", "event_type_location_emails", "event_type_id", $eventRow['event_type_id'], "location_id = ?", $eventRow['location_id']);
					if (empty($eventRow['email_id'])) {
						$eventRow['email_id'] = getFieldFromId("email_id", "event_types", "event_type_id", $eventRow['event_type_id']);
					}
				}
				if (!empty($eventRow['email_id'])) {
					sendEmail(array("email_id" => $eventRow['email_id'], "email_address" => $substitutions['email_address'], "substitutions" => $substitutions, "contact_id" => $contactId));
				}
				$returnArray['response'] = (empty($eventRow['response_content']) ? $this->getFragment("REGISTRATION_UPDATE_RESPONSE") : $eventRow['response_content']);
                $returnArray['response'] = PlaceHolders::massageContent($returnArray['response'], $substitutions);
				ajaxResponse($returnArray);
				break;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            for (const i in returnArray) {
                if (empty(i)) {
                    continue;
                }
                if ($("input[type=radio][name='" + i + "']").length > 0) {
                    $("input[type=radio][name='" + i + "']").prop("checked", false);
                    $("input[type=radio][name='" + i + "'][value='" + returnArray[i] + "']").prop("checked", true);
                } else if ($("#" + i).is("input[type=checkbox]")) {
                    $("#" + i).prop("checked", returnArray[i] === 1);
                } else if ($("input[name=" + i + "]").is("input[type=radio]")) {
                    $("input[name=" + i + "][value=" + returnArray[i] + "]").prop("checked", true);
                } else if ($("#" + i).is("a")) {
                    $("#" + i).attr("href", returnArray[i]).css("display", (empty(returnArray[i]) ? "none" : "inline"));
                } else if ($("#" + i).is("div") || $("#" + i).is("span") || $("#" + i).is("td") || $("#" + i).is("tr")) {
                    $("#" + i).html(returnArray[i]);
                } else if ($("#_" + i + "_table").is(".editable-list")) {
                    $("#_" + i + "_table tr").not(":first").not(":last").remove();
                    const editableListRows = returnArray[i];
                    for (const j in editableListRows) {
                        addEditableListRow(i, editableListRows[j]);
                    }
                } else {
                    $("#" + i).val(returnArray[i]);
                }
            }
            $(document).on("click", "#add_another", function () {
                let fieldHtml = $("#fields_wrapper_template").html();
                let rowNumber = $("#fields_wrapper").find(".registration-row_number").last().val();
                if (empty(rowNumber)) {
                    rowNumber = 1;
                } else {
                    rowNumber++;
                }
                fieldHtml = fieldHtml.replace(new RegExp("%row_number%", 'g'), rowNumber);
                $("#fields_wrapper").append(fieldHtml);
                $("#first_name_" + rowNumber).focus();
                return false;
            });
            $(document).on("click", ".close-registration", function () {
                $(this).closest(".additional-registration-wrapper").remove();
            });


            $("#update_button").click(function () {
                if ($("#_edit_form").validationEngine('validate')) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=update", $("#_edit_form").serialize(), function(returnArray) {
                        if ("response" in returnArray) {
                            $("#register_fields").html(returnArray['response']);
                        }
                    });
                }
                return false;
            });
            $("#first_name").focus();
        </script>
		<?php
	}

	function javascript() {
		$this->getCustomFields();
		?>
        <script>
            var returnArray = <?= jsonEncode($this->iCustomFieldData) ?>;
        </script>
		<?php

	}

	function mainContent() {
		$capitalizedFields = array();
		if (getPreference("USE_FIELD_CAPITALIZATION")) {
			$resultSet = executeQuery("select column_name from column_definitions where letter_case = 'C'");
			while ($row = getNextRow($resultSet)) {
				$capitalizedFields[] = $row['column_name'];
			}
		}
		echo $this->getPageData("content");
		?>
		<?php if ($GLOBALS['gLoggedIn']) { ?>
            <p><a href="/logout.php?url=<?= urlencode($_SERVER['REQUEST_URI']) ?>">If you are not <?= getUserDisplayName() ?>, click here</a></p>
		<?php } ?>
		<?php
		$eventRow = getRowFromId("events", "event_id", $this->iEventId);
		$eventRegistrantRow = getRowFromId("event_registrants", "event_registrant_id", $this->iEventRegistrantId);
		$contactRow = Contact::getContact($eventRegistrantRow['contact_id']);

		$firstName = $contactRow['first_name'];
		$lastName = $contactRow['last_name'];
		$emailAddress = $contactRow['email_address'];
		$phoneNumber = Contact::getContactPhoneNumber($contactRow['contact_id']);

		$eventProducts = array();
		if (!empty($eventRow['product_id'])) {
			$eventProducts[] = array("event_id" => $eventRow['event_id'], "product_id" => $eventRow['product_id'], "start_date" => "", "end_date" => "", "required" => 1, "price" => "");
		}
		$resultSet = executeQuery("select * from event_registration_products where (start_date is null or start_date <= current_date) and (end_date is null or end_date >= current_date) and " .
			"(product_id is not null or product_group_id is not null) and event_id = ?", $eventRow['event_id']);
		while ($row = getNextRow($resultSet)) {
			$eventProducts[] = $row;
		}

		?>
        <div id="register_form_wrapper">

            <form id="_edit_form">
                <input type="hidden" id="event_id" name="event_id" value="<?= $this->iEventId ?>">
                <input type="hidden" id="event_registrant_id" name="event_registrant_id" value="<?= $this->iEventRegistrantId ?>">

                <div id="register_fields">

                    <h1><?= htmlText($eventRow['description']) ?></h1>
                    <p><?= date("m/d/Y", strtotime($eventRow['start_date'])) ?></p>
					<?php if (!empty($eventRow['detailed_description'])) { ?>
                        <p><?= makeHtml($eventRow['detailed_description']) ?></p>
					<?php } ?>

                    <hr>

                    <div id="fields_wrapper">

                        <h2>Contact Information of Registrant</h2>
						<?= createFormControl("contacts", "first_name", array("not_null" => true, "placeholder" => "First Name", "readonly" => $GLOBALS['gLoggedIn'], "initial_value" => $firstName, "data-user_value" => $firstName, "form_line_classes" => "contact-info", "force_validation_classes" => true)) ?>
						<?= createFormControl("contacts", "last_name", array("not_null" => true, "placeholder" => "Last Name", "readonly" => $GLOBALS['gLoggedIn'], "initial_value" => $lastName, "data-user_value" => $lastName, "form_line_classes" => "contact-info", "force_validation_classes" => true)) ?>
						<?= createFormControl("contacts", "email_address", array("not_null" => true, "placeholder" => "Email", "readonly" => $GLOBALS['gLoggedIn'], "initial_value" => $emailAddress, "data-user_value" => $emailAddress, "form_line_classes" => "contact-info", "force_validation_classes" => true)) ?>
						<?= createFormControl("phone_numbers", "phone_number", array("not_null" => false, "placeholder" => "Phone", "data_format" => "phone", "readonly" => $GLOBALS['gLoggedIn'], "initial_value" => $phoneNumber, "data-user_value" => $phoneNumber, "form_line_classes" => "contact-info", "force_validation_classes" => true)) ?>
                        <input type='hidden' id='country_id' value='1000'>
						<?= $this->iCustomFieldContent ?>
                    </div>

					<?php if (count($eventProducts) > 0) { ?>
                        <div id="payment_wrapper">
                            <h2>Event Order</h2>
                            <p>To change paid options, contact customer service</p>
                            <table class="grid-table" id="products_table">
                                <tr>
                                    <th>Description</th>
                                    <th>Details</th>
                                    <th>Quantity</th>
                                    <th>Price</th>
                                </tr>
								<?php
								$totalCost = 0;
								foreach ($eventProducts as $row) {
									$orderItemRow = getRowFromId("order_items", "order_id", $eventRegistrantRow['order_id'], "product_id = ?", $row['product_id']);
									if (empty($orderItemRow)) {
										continue;
									}
									$detailedDescription = getFieldFromId("detailed_description", "products", "product_id", $row['product_id']);
									?>
                                    <tr class='product-row'>
                                        <td class="product-description">
											<?= htmlText($orderItemRow['description']) ?>
                                        </td>
                                        <td class="product-detailed-description">
											<?= ($eventRow['product_id'] == $row['product_id'] ? "" : makeHtml($detailedDescription)) ?>
                                        </td>
                                        <td class="align-center"><?= $orderItemRow['quantity'] ?></td>
                                        <td class="align-right product-price"><?= number_format($orderItemRow['sale_price'], 2) ?></td>
                                    </tr>
									<?php
									$totalCost += $orderItemRow['quantity'] * $orderItemRow['sale_price'];
								}
								?>
                                <tr>
                                    <td colspan="3" class="highlighted-text" id="total_cost_label">Total</td>
                                    <td class="align-right" class="highlighted-text" id="total_cost_display"><?= number_format($totalCost, 2) ?></td>
                                </tr>
                            </table>


                        </div> <!-- payment_wrapper -->
					<?php } ?>

                    <p class="error-message" id="error_message"></p>
                    <p>
                        <button tabindex="10" id="update_button">Update Registration</button>
                    </p>
                </div> <!-- register_fields -->

            </form>
        </div> <!-- register_form_wrapper -->

		<?php
		echo $this->getPageData("after_form_content");
		return true;
	}

	function internalCSS() {
		?>
        <style>

            #products_table {
                max-width: 100%;
                margin-bottom: 40px;
            }

            #products_table td {
                height: 40px;
                vertical-align: middle;
                padding: 0 20px;
            }
        </style>
		<?php
	}

	function jqueryTemplates() {
		$customFields = CustomField::getCustomFields("event_registrations");
		$resultSet = executeQuery("select * from event_registration_custom_fields where event_id = ? order by sequence_number", $this->iEventId);
		while ($row = getNextRow($resultSet)) {
			if (!array_key_exists($row['custom_field_id'], $customFields)) {
				continue;
			}
			$customField = CustomField::getCustomField($row['custom_field_id']);
			echo $customField->getTemplate();
		}
	}
}

$pageObject = new EventRegistrationUpdatePage("event_registrants");
$pageObject->displayPage();
