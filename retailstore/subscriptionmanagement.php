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

/* text instructions
<p>The Subscription Management page has Page text chunks that can be used. The code is important and must match the code listed here. The description is just informational and can contain anything. The value is what is used by the page.</p>
<ul>
    <li><strong>SUBSCRIPTION_TERM</strong> - Replace the word "subscription" with another term such as "Membership".</li>
    <li><strong>USER_CANNOT_CANCEL_SUBSCRIPTION</strong> - If this exists and has a value other than zero, the "cancel subscription" button will not be available to the user.</li>
    <li><strong>USER_CANNOT_PAUSE_SUBSCRIPTION</strong> - If this exists and has a value other than zero, the "pause subscription" button will not be available to the user.</li>
</ul>
*/


$GLOBALS['gPageCode'] = "SUBSCRIPTIONMANAGEMENT";
$GLOBALS['gCacheProhibited'] = true;
$GLOBALS['gForceSSL'] = true;
require_once "shared/startup.inc";

class SubscriptionManagementPage extends Page {

	var $iSubscriptionTerm = null;

	function setup() {
		$this->iSubscriptionTerm = $this->getPageTextChunk("SUBSCRIPTION_TERM");
		if (empty($this->iSubscriptionTerm)) {
			$this->iSubscriptionTerm = "Subscription";
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		$userCannotPause = $this->getPageTextChunk("USER_CANNOT_PAUSE_SUBSCRIPTION");
		$userCannotCancel = $this->getPageTextChunk("USER_CANNOT_CANCEL_SUBSCRIPTION");
		$contactSubscriptionRow = getRowFromId("contact_subscriptions", "contact_subscription_id", $_POST['contact_subscription_id'],
			"contact_id = ?", $GLOBALS['gUserRow']['contact_id']);
		$subscriptionRow = getRowFromId("subscriptions", "subscription_id", $contactSubscriptionRow['subscription_id']);
		$subscriptionName = $this->iSubscriptionTerm . " '" . getFieldFromId("description", "subscriptions", "subscription_id", $contactSubscriptionRow['subscription_id']) . "'";
		switch ($_GET['url_action']) {
			case "change_account":
				$accountId = getFieldFromId("account_id", "accounts", "account_id", $_POST['account_id'], "contact_id = ? and inactive = 0 and account_token is not null", $GLOBALS['gUserRow']['contact_id']);
				if (empty($accountId)) {
					$returnArray['error_message'] = "Invalid Account";
					ajaxResponse($returnArray);
					break;
				}
				$accountRow = getRowFromId("accounts", "account_id", $accountId);
				$contactSubscriptionRow = getRowFromId("contact_subscriptions", "contact_id", $GLOBALS['gUserRow']['contact_id']);
				if (empty($contactSubscriptionRow) || empty($contactSubscriptionRow['expiration_date'])) {
					$returnArray['error_message'] = "Invalid Subscription";
					ajaxResponse($returnArray);
					break;
				}
				$contactSubscriptionId = $contactSubscriptionRow['contact_subscription_id'];
				$originalRecurringPaymentRow = getRowFromId("recurring_payments", "contact_id", $GLOBALS['gUserRow']['contact_id'], "contact_subscription_id = ?", $contactSubscriptionId);
				$recurringPaymentRow = getRowFromId("recurring_payments", "contact_id", $GLOBALS['gUserRow']['contact_id'], "contact_subscription_id = ? and (end_date is null or end_date >= current_date)", $contactSubscriptionId);
				$recurringPaymentId = $recurringPaymentRow['recurring_payment_id'];
				if (empty($recurringPaymentId)) {
					if (!empty($_POST['original_account_id']) || empty($_POST['recurring_payment_type_id'])) {
						$returnArray['error_message'] = "Unable to update account";
						ajaxResponse($returnArray);
						break;
					}
					$originalProductId = getRowFromId("product_id", "recurring_payment_order_items", "recurring_payment_id", $originalRecurringPaymentRow['recurring_payment_id']);
					$subscriptionProductRow = getRowFromId("subscription_products", "subscription_id", $contactSubscriptionRow['subscription_id'], "recurring_payment_type_id = ? and product_id = ?", $_POST['recurring_payment_type_id'], $originalProductId);
					if (empty($subscriptionProductRow)) {
						$subscriptionProductRow = getRowFromId("subscription_products", "subscription_id", $contactSubscriptionRow['subscription_id'], "recurring_payment_type_id = ?", $_POST['recurring_payment_type_id']);
					}
					if (empty($subscriptionProductRow)) {
						$returnArray['error_message'] = "Unable to update account";
						ajaxResponse($returnArray);
						break;
					}
					if (empty($subscriptionProductRow['product_id']) || empty($subscriptionProductRow['recurring_payment_type_id']) || empty($accountId)) {
						$returnArray['error_message'] = "Unable to update account";
						ajaxResponse($returnArray);
						break;
					}
					$nextBillingDate = date("Y-m-d", strtotime($contactSubscriptionRow['expiration_date'] . ' -1 day'));
					$resultSet = executeQuery("insert into recurring_payments (contact_id,recurring_payment_type_id,payment_method_id,start_date,next_billing_date,account_id) values " .
						"(?,?,?,current_date,?,?)", $GLOBALS['gUserRow']['contact_id'], $subscriptionProductRow['recurring_payment_type_id'], $accountRow['payment_method_id'], $nextBillingDate, $accountId);
					$recurringPaymentId = $resultSet['insert_id'];
					$productCatalog = new ProductCatalog();
					$salePriceInfo = $productCatalog->getProductSalePrice($subscriptionProductRow['product_id']);
					$salePrice = $salePriceInfo['sale_price'];
					$insertSet = executeQuery("insert into recurring_payment_order_items (recurring_payment_id,product_id,quantity,sale_price) values (?,?,1,?)",
						$recurringPaymentId, $subscriptionProductRow['product_id'], $salePrice);
					$recurringPaymentOrderItemId = $insertSet['insert_id'];
					if (!empty($recurringPaymentId) && !empty($contactSubscriptionId)) {
						executeQuery("update recurring_payments set contact_subscription_id = ? where contact_subscription_id is null and recurring_payment_id = ?", $contactSubscriptionId, $recurringPaymentId);
					}
					if (empty($recurringPaymentId)) {
						$returnArray['error_message'] = "No Recurring Payment found to change";
						ajaxResponse($returnArray);
						break;
					}
					$returnArray['info_message'] = "Recurring Payment successfully changed";
				} else {
					$dataTable = new DataTable("recurring_payments");
					$dataTable->setSaveOnlyPresent(true);
					$paymentMethodId = getFieldFromId("payment_method_id", "accounts", "account_id", $accountId);
					if (!$dataTable->saveRecord(array("name_values" => array("account_id" => $accountId, "payment_method_id" => $paymentMethodId), "primary_id" => $recurringPaymentId))) {
						$returnArray['error_message'] = "Unable to update recurring payment. Please contact customer service.";
					} else {
						$returnArray['info_message'] = "Account successfully changed";
					}
				}
				ajaxResponse($returnArray);
				break;
			case "pause_subscription":
				if ($userCannotPause) {
					$returnArray['error_message'] = getLanguageText("Unable to pause " . $this->iSubscriptionTerm);
					ajaxResponse($returnArray);
					break;
				}
				if (empty($contactSubscriptionRow)) {
					$returnArray['error_message'] = getLanguageText("Invalid " . $this->iSubscriptionTerm);
					ajaxResponse($returnArray);
					break;
				}
				if (!empty($subscriptionRow['days_between']) && $subscriptionRow['days_between'] > 0 && !empty($contactSubscriptionRow['date_paused'])) {
					$nextPauseDate = date("Y-m-d", strtotime($contactSubscriptionRow['date_paused']) + ($subscriptionRow['days_between'] * 24 * 60 * 60));
					if (date("Y-m-d") < $nextPauseDate) {
						$returnArray['error_message'] = "This subscription was last paused on " . date("m/d/Y",strtotime($contactSubscriptionRow['date_paused'])) .
							" and cannot be paused again until " . date("m/d/Y",strtotime($nextPauseDate)) . ".";
						ajaxResponse($returnArray);
						break;
					}
				}
				$dataTable = new DataTable("contact_subscriptions");
				$dataTable->setSaveOnlyPresent(true);
				$dataTable->saveRecord(array("name_values" => array("customer_paused" => 1, "date_paused" => date("Y-m-d"), "notes" => $_POST['notes']), "primary_id" => $contactSubscriptionRow['contact_subscription_id']));
				sendEmail(array("subject" => $subscriptionName . " Paused", "body" => $subscriptionName . " paused by " . getDisplayName($GLOBALS['gUserRow']['contact_id'])
					. " (Contact ID " . $GLOBALS['gUserRow']['contact_id'] . ").\n\nReason given: " . $_POST['notes'], "notification_code" => "SUBSCRIPTIONS"));
				updateUserSubscriptions($GLOBALS['gUserRow']['contact_id']);
				ajaxResponse($returnArray);
				break;
			case "continue_subscription":
				if ($userCannotPause) {
					$returnArray['error_message'] = getLanguageText("Unable to continue " . $this->iSubscriptionTerm);
					ajaxResponse($returnArray);
					break;
				}
				if (empty($contactSubscriptionRow)) {
					$returnArray['error_message'] = getLanguageText("Invalid " . $this->iSubscriptionTerm);
					ajaxResponse($returnArray);
					break;
				}
				$dataTable = new DataTable("contact_subscriptions");
				$dataTable->setSaveOnlyPresent(true);
				$dataTable->saveRecord(array("name_values" => array("customer_paused" => 0), "primary_id" => $contactSubscriptionRow['contact_subscription_id']));
				// update next billing date so that missed payments are skipped
				$nextBillingDate = getFieldFromId('next_billing_date', 'recurring_payments', 'contact_subscription_id', $contactSubscriptionRow['contact_subscription_id']);
				$nextBillingDate = date_create($nextBillingDate);
				if (empty($nextBillingDate)) {
					$nextBillingDate = date_create();
				}
				$subscriptionProductSet = executeQuery("select units_between, interval_unit from recurring_payments"
					. " join recurring_payment_order_items using (recurring_payment_id) join subscription_products using (product_id) where contact_subscription_id = ?",
					$contactSubscriptionRow['contact_subscription_id']);
				$subscriptionProductRow = getNextRow($subscriptionProductSet);
				freeResult($subscriptionProductSet);
				$today = date_create();
				while ($nextBillingDate < $today) {
					$nextBillingDate->add(date_interval_create_from_date_string($subscriptionProductRow['units_between'] . " " . $subscriptionProductRow['interval_unit']));
				}
				executeQuery("Update recurring_payments set next_billing_date = ? where contact_subscription_id = ?",
					date_format($nextBillingDate, "Y-m-d"), $contactSubscriptionRow['contact_subscription_id']);
				sendEmail(array("subject" => $subscriptionName . " Unpaused", "body" => $subscriptionName . " unpaused by " . getDisplayName($GLOBALS['gUserRow']['contact_id']) . " (Contact ID " . $GLOBALS['gUserRow']['contact_id'] . ")", "notification_code" => "SUBSCRIPTIONS"));
				updateUserSubscriptions($GLOBALS['gUserRow']['contact_id']);
				ajaxResponse($returnArray);
				break;
			case "cancel_subscription":
				if ($userCannotCancel) {
					$returnArray['error_message'] = getLanguageText("Unable to cancel " . $this->iSubscriptionTerm);
					ajaxResponse($returnArray);
					break;
				}
				if (empty($contactSubscriptionRow)) {
					$returnArray['error_message'] = getLanguageText("Invalid " . $this->iSubscriptionTerm);
					ajaxResponse($returnArray);
					break;
				}
				$recurringPaymentId = getFieldFromId("recurring_payment_id", "recurring_payments", "contact_subscription_id", $contactSubscriptionRow['contact_subscription_id']);
				if (empty($recurringPaymentId)) {
					$dataTable = new DataTable("contact_subscriptions");
					$dataTable->setSaveOnlyPresent(true);
					$dataTable->saveRecord(array("name_values" => array("inactive" => 1, "notes" => $_POST['notes']), "primary_id" => $contactSubscriptionRow['contact_subscription_id']));
				} else {
					$dataTable = new DataTable("contact_subscriptions");
					$dataTable->setSaveOnlyPresent(true);
					$dataTable->saveRecord(array("name_values" => array("notes" => $_POST['notes']), "primary_id" => $contactSubscriptionRow['contact_subscription_id']));
					$dataTable = new DataTable("recurring_payments");
					$dataTable->setSaveOnlyPresent(true);
					$dataTable->saveRecord(array("name_values" => array("end_date" => date("Y-m-d")), "primary_id" => $recurringPaymentId));
				}
				sendEmail(array("subject" => $subscriptionName . " Cancelled", "body" => $subscriptionName . " cancelled by " . getDisplayName($GLOBALS['gUserRow']['contact_id'])
					. " (Contact ID " . $GLOBALS['gUserRow']['contact_id'] . ").\n\nReason given: " . $_POST['notes'], "notification_code" => "SUBSCRIPTIONS"));
				updateUserSubscriptions($GLOBALS['gUserRow']['contact_id']);
				ajaxResponse($returnArray);
				break;
			case "undo_cancel":
				if ($userCannotCancel) {
					$returnArray['error_message'] = getLanguageText("Unable to undo cancel " . $this->iSubscriptionTerm);
					ajaxResponse($returnArray);
					break;
				}
				if (empty($contactSubscriptionRow)) {
					$returnArray['error_message'] = getLanguageText("Invalid " . $this->iSubscriptionTerm);
					ajaxResponse($returnArray);
					break;
				}
				$recurringPaymentId = getFieldFromId("recurring_payment_id", "recurring_payments", "contact_subscription_id", $contactSubscriptionRow['contact_subscription_id']);
				if (empty($recurringPaymentId)) {
					$dataTable = new DataTable("contact_subscriptions");
					$dataTable->setSaveOnlyPresent(true);
					$dataTable->saveRecord(array("name_values" => array("inactive" => 0), "primary_id" => $contactSubscriptionRow['contact_subscription_id']));
				} else {
					$dataTable = new DataTable("recurring_payments");
					$dataTable->setSaveOnlyPresent(true);
					$dataTable->saveRecord(array("name_values" => array("end_date" => ""), "primary_id" => $recurringPaymentId));
				}
				sendEmail(array("subject" => $subscriptionName . " Cancellation undone", "body" => $subscriptionName . " cancellation reversed by " . getDisplayName($GLOBALS['gUserRow']['contact_id']) . " (Contact ID " . $GLOBALS['gUserRow']['contact_id'] . ")", "notification_code" => "SUBSCRIPTIONS"));
				updateUserSubscriptions($GLOBALS['gUserRow']['contact_id']);
				ajaxResponse($returnArray);
				break;
		}
	}

	function onLoadJavascript() {
		?>
		<script>
            $(document).on("change", ".contact-subscription-account", function () {
                const $contactSubscriptionAccount = $(this).closest("tr").find(".contact-subscription-account");
                const $recurringPaymentType = $(this).closest("tr").find(".recurring-payment-type");
                const accountId = $contactSubscriptionAccount.val();
                const contactSubscriptionId = $(this).closest("tr").data("contact_subscription_id");
                const originalAccountId = $(this).closest("tr").data("account_id");
                const recurringPaymentTypeId = $recurringPaymentType.val();
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=change_account", { account_id: accountId, contact_subscription_id: contactSubscriptionId, recurring_payment_type_id: recurringPaymentTypeId, original_account_id: originalAccountId }, function (returnArray) {
                    if ("error_message" in returnArray) {
                        $contactSubscriptionAccount.val(originalAccountId);
                    } else {
                        $contactSubscriptionAccount.closest("tr").data("account_id", accountId);
                        $contactSubscriptionAccount.find("option[value='']").remove();
                        $recurringPaymentType.prop("disabled", true);
                    }
                });
            });
            $(document).on("click", ".pause-subscription", function () {
                const $tableRow = $(this).closest("tr");
                const $tableCell = $(this).closest("td");
                const contactSubscriptionId = $tableRow.data("contact_subscription_id");
                $('#_reason_dialog').dialog({
                    closeOnEscape: true,
                    draggable: true,
                    modal: true,
                    resizable: true,
                    position: { my: "center top", at: "center top+5%", of: window, collision: "none" },
                    width: 600,
                    title: 'Subscription Action',
                    buttons: {
                        Save: function (event) {
                            let notes = $("#reason_text").val();
                            if (!empty(notes)) {
                                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=pause_subscription", { contact_subscription_id: contactSubscriptionId, notes: notes }, function (returnArray) {
                                    if (!("error_message" in returnArray)) {
                                        $tableCell.html("<button class='continue-subscription'>Continue <?= $this->iSubscriptionTerm ?></button>");
                                    }
                                });
                                $("#_reason_dialog").dialog('close');
                            } else {
                                $("#reason_text").validationEngine("showPrompt", "Please enter a reason");
                            }
                        },
                        Cancel: function (event) {
                            $("#_reason_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
            $(document).on("click", ".continue-subscription", function () {
                const $tableRow = $(this).closest("tr");
                const $tableCell = $(this).closest("td");
                const contactSubscriptionId = $tableRow.data("contact_subscription_id");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=continue_subscription", { contact_subscription_id: contactSubscriptionId }, function (returnArray) {
                    if (!("error_message" in returnArray)) {
                        $tableCell.html("<button class='pause-subscription'>Pause Now</button>");
                    }
                });
                return false;
            });
            $(document).on("click", ".cancel-subscription", function () {
                const $tableRow = $(this).closest("tr");
                const $tableCell = $(this).closest("td");
                const contactSubscriptionId = $tableRow.data("contact_subscription_id");
                $('#_reason_dialog').dialog({
                    closeOnEscape: true,
                    draggable: true,
                    modal: true,
                    resizable: true,
                    position: { my: "center top", at: "center top+5%", of: window, collision: "none" },
                    width: 600,
                    title: 'Subscription Action',
                    buttons: {
                        Save: function (event) {
                            let notes = $("#reason_text").val();
                            if (!empty(notes)) {
                                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=cancel_subscription", { contact_subscription_id: contactSubscriptionId, notes: notes }, function (returnArray) {
                                    if (!("error_message" in returnArray)) {
                                        $tableCell.html("<button class='undo-cancel'>Undo Cancel</button>");
                                    }
                                });
                                $("#_reason_dialog").dialog('close');
                            } else {
                                $("#reason_text").validationEngine("showPrompt", "Please enter a reason");
                            }
                        },
                        Cancel: function (event) {
                            $("#_reason_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
            $(document).on("click", ".undo-cancel", function () {
                const $tableRow = $(this).closest("tr");
                const $tableCell = $(this).closest("td");
                const contactSubscriptionId = $tableRow.data("contact_subscription_id");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=undo_cancel", { contact_subscription_id: contactSubscriptionId }, function (returnArray) {
                    if (!("error_message" in returnArray)) {
                        $tableCell.html("<button class='cancel-subscription'>Cancel <?= $this->iSubscriptionTerm ?></button>");
                    }
                });
                return false;
            });
		</script>
		<?php
	}

	function mainContent() {
		$preContent = $this->iPageData['content'];
		if (empty($preContent)) {
			$preContent = "<p>Cancelling a " . $this->iSubscriptionTerm . " cannot be undone once this page is closed.</p>";
		}
		echo makeHtml($preContent);
		?>
		<p class='error-message' id="_error_message"></p>
		<p><a href='/my-account'>Back to My Account</a></p>
		<?php
		$accountsArray = array();
		$resultSet = executeQuery("select *,(select description from payment_methods where payment_method_id = accounts.payment_method_id) as payment_method from accounts where account_token is not null and inactive = 0 and contact_id = ? order by account_label,payment_method,account_number", $GLOBALS['gUserRow']['contact_id']);
		while ($row = getNextRow($resultSet)) {
			if (empty($row['account_label'])) {
				$row['account_label'] = $row['payment_method'] . " - " . $row['account_number'];
			}
			$accountsArray[$row['account_id']] = $row;
		}

		$resultSet = executeQuery("select * from contact_subscriptions join subscriptions using (subscription_id) where contact_subscriptions.inactive = 0 and " .
			"subscriptions.inactive = 0 and internal_use_only = 0 and contact_id = ? and subscriptions.client_id = ? order by start_date,description", $GLOBALS['gUserRow']['contact_id'], $GLOBALS['gClientId']);
		$userCannotPause = $this->getPageTextChunk("USER_CANNOT_PAUSE_SUBSCRIPTION");
		$userCannotCancel = $this->getPageTextChunk("USER_CANNOT_CANCEL_SUBSCRIPTION");
		if ($resultSet['row_count'] == 0) {
			echo "<p>No " . $this->iSubscriptionTerm . "s found</p>";
		} else {
			?>
			<table class='grid-table' id="subscription_wrapper">
				<tr>
					<th>Description</th>
					<th>Started</th>
					<th class='expiration-date'>Expires</th>
					<th class='payment-type'>Type</th>
					<th class='payment-account'>Account</th>
					<?php if (!$userCannotPause) { ?>
						<th>Pause</th>
					<?php } ?>
					<?php if (!$userCannotCancel) { ?>
						<th>Cancel</th>
					<?php } ?>
				</tr>
				<?php
				while ($row = getNextRow($resultSet)) {
					$accountId = getFieldFromId("account_id", "recurring_payments", "contact_id", $GLOBALS['gUserRow']['contact_id'],
						"contact_subscription_id = ? and (end_date is null or end_date >= current_date)", $row['contact_subscription_id']);
					if (!array_key_exists($accountId, $accountsArray)) {
						$accountId = "";
					}
					$recurringPaymentTypes = array();
					$typeSet = executeQuery("select * from recurring_payment_types where client_id = ? and inactive = 0 and internal_use_only = 0 and recurring_payment_type_id in " .
						"(select recurring_payment_type_id from subscription_products where subscription_id = ?) order by sort_order,description", $GLOBALS['gClientId'], $row['subscription_id']);
					while ($typeRow = getNextRow($typeSet)) {
						$recurringPaymentTypes[] = $typeRow;
					}
					$row['recurring_payment_type_id'] = getFieldFromId("recurring_payment_type_id", "recurring_payments", "contact_subscription_id", $row['contact_subscription_id']);
					?>
					<tr class='data-row' data-contact_subscription_id="<?= $row['contact_subscription_id'] ?>" data-account_id="<?= $accountId ?>">
						<td><?= htmlText($row['description']) ?></td>
						<td><?= date("m/d/Y", strtotime($row['start_date'])) ?></td>
						<?php if (empty($accountId) && empty($recurringPaymentTypes)) { ?>
							<td class='expiration-date'><?= (empty($row['expiration_date']) ? "N/A" : date("m/d/Y", strtotime($row['expiration_date']))) ?></td>
							<td class='payment-type'>N/A</td>
							<td class='payment-account'>N/A</td>
						<?php } else { ?>
							<td><?= (empty($row['expiration_date']) ? "Never" : date("m/d/Y", strtotime($row['expiration_date']))) ?></td>
							<td>
								<select class='recurring-payment-type' <?= empty($accountId) ? "" : "disabled=\"true\"" ?> >
									<?php
									foreach ($recurringPaymentTypes as $thisRecurringPaymentType) {
										$selected = ($thisRecurringPaymentType['recurring_payment_type_id'] == $row['recurring_payment_type_id']);
										?>
										<option value='<?= $thisRecurringPaymentType['recurring_payment_type_id'] ?>'<?= ($selected ? " selected" : "") ?>><?= htmlText($thisRecurringPaymentType['description']) ?></option>
										<?php
									}
									?>
								</select>
							</td>
							<td>
								<select class='contact-subscription-account' id="account_id_<?= $row['contact_subscription_id'] ?>">
									<?php
									if (empty($accountId)) {
										?>
										<option value='' selected>[No recurring payment setup]</option>
										<?php
									}
									foreach ($accountsArray as $thisAccount) {
										?>
										<option <?= ($thisAccount['account_id'] == $accountId ? "selected" : "") ?> value='<?= $thisAccount['account_id'] ?>'><?= htmlText($thisAccount['account_label']) ?></option>
										<?php
									}
									?>
								</select>
							</td>
						<?php } ?>
						<?php if (!$userCannotPause) { ?>
							<td><?= (empty($row['customer_paused']) ? "<button class='pause-subscription'>Pause Now</button>" : "<button class='continue-subscription'>Continue " . $this->iSubscriptionTerm . "</button>") ?></td>
						<?php } ?>
						<?php if (!$userCannotCancel) { ?>
							<td>
								<button class='cancel-subscription'>Cancel <?= $this->iSubscriptionTerm ?></button>
							</td>
						<?php } ?>
					</tr>
					<?php
				}
				?>
			</table> <!-- subscription_wrapper -->
			<?php
		}
		echo $this->iPageData['after_form_content'];
		return true;
	}

	function hiddenElements() {
		?>
		<div class='dialog-box' id="_reason_dialog">
			<p>Please enter your reason for <span id='reason_type'>pausing</span> your subscription.</p>
			<textarea id='reason_text' name='reason_text'></textarea>
		</div>
		<?php
	}
}

$pageObject = new SubscriptionManagementPage();
$pageObject->displayPage();
