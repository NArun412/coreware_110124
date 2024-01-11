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

$GLOBALS['gPageCode'] = "DONATIONREFUND";
require_once "shared/startup.inc";

class DonationRefundPage extends Page {
	var $iDonationBatchId = "";

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "refund_donation":
				$donationId = getFieldFromId("donation_id", "donations", "donation_id", $_GET['donation_id'], "associated_donation_id is null and transaction_identifier is not null and account_id is not null");
				if (empty($donationId)) {
					$returnArray['error_message'] = "Invalid Donation";
					ajaxResponse($returnArray);
					break;
				}

				$donationRow = getRowFromId("donations", "donation_id", $donationId);
				$GLOBALS['gPrimaryDatabase']->startTransaction();

				$resultSet = executeQuery("insert into donations (client_id,contact_id,donation_date,payment_method_id,reference_number,account_id," .
					"designation_id,project_name,donation_source_id,amount,anonymous_gift,donation_fee,recurring_donation_id," .
					"associated_donation_id,receipted_contact_id,notes) values (?,?,?,?,?, ?,?,?,?,?, ?,?,?,?,?, ?)", $GLOBALS['gClientId'],
					$donationRow['contact_id'], date("Y-m-d"), $donationRow['payment_method_id'], $donationRow['reference_number'], $donationRow['account_id'],
					$donationRow['designation_id'], $donationRow['project_name'], $donationRow['donation_source_id'], ($donationRow['amount'] * -1),
					$donationRow['anonymous_gift'], ($donationRow['donation_fee'] * -1), $donationRow['recurring_donation_id'], $donationRow['donation_id'],
					$donationRow['receipted_contact_id'], "Refunded by " . getUserDisplayName() . " at " . date("g:ia"));
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					ajaxResponse($returnArray);
					break;
				}
				$refundDonationId = $resultSet['insert_id'];
				Donations::processDonation($refundDonationId);
				$resultSet = executeQuery("update donations set associated_donation_id = ? where donation_id = ?", $refundDonationId, $donationId);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					ajaxResponse($returnArray);
					break;
				}
				$accountRow = getRowFromId("accounts", "account_id", $donationRow['account_id']);
				$eCommerce = eCommerce::getEcommerceInstance($accountRow['merchant_account_id']);
				if (!$eCommerce) {
					$returnArray['error_message'] = "Unable to process refund - #192";
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					ajaxResponse($returnArray);
					break;
				}
				$refundSuccess = $eCommerce->voidCharge(array("transaction_identifier" => $donationRow['transaction_identifier'], "amount" => $donationRow['amount'],
					"card_number"=>substr($accountRow['account_number'],4)));
				if (!$refundSuccess) {
					$refundSuccess = $eCommerce->refundCharge(array("transaction_identifier" => $donationRow['transaction_identifier'], "amount" => $donationRow['amount'],
						"card_number" => substr($accountRow['account_number'], 4)));
				}
				$gatewayResponse = $eCommerce->getResponse();
				if (!$refundSuccess) {
					$returnArray['error_message'] = ($GLOBALS['gUserRow']['superuser_flag'] ? jsonEncode($gatewayResponse) : $gatewayResponse['response_reason_text']) . " Unable to process refund for " . number_format($donationRow['amount'], 2, ".", ",") . ".";
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					ajaxResponse($returnArray);
					break;
				}
				$response = "<p class='highlighted-text green-text'>Refund for account " . $accountRow['account_number'] . " for " . number_format($donationRow['amount'], 2, ".", ",") . " successfully processed.</p>";

				$GLOBALS['gPrimaryDatabase']->commitTransaction();
				$returnArray['response'] = $response;

				ajaxResponse($returnArray);

				break;
		}
	}

	function setup() {
		$this->iTemplateObject->getTableEditorObject()->setReadonly(true);
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$filters = array();
			$resultSet = executeQuery("select * from designation_groups where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$filters['designation_group_' . $row['designation_group_id']] = array("form_label" => $row['description'], "where" => "designation_id in (select designation_id from designation_group_links where designation_group_id = " . $row['designation_group_id'] . ")", "data_type" => "tinyint");
			}
			$filters['start_date'] = array("form_label" => "Start Date", "where" => "donation_date >= '%filter_value%'", "data_type" => "date", "conjunction" => "and");
			$filters['end_date'] = array("form_label" => "End Date", "where" => "donation_date <= '%filter_value%'", "data_type" => "date", "conjunction" => "and");
			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("donation_id", "batch_number", "first_name", "last_name", "business_name", "donation_date", "designation_id", "project_name", "amount", "email_address", "address_1", "phone_number", "recurring_donation", "payment_method_id", "reference_number", "receipt_sent", "donation_source_id"));
			$this->iTemplateObject->getTableEditorObject()->setListSortOrder(array("donation_id", "batch_number", "first_name", "last_name", "business_name", "donation_date", "designation_id", "amount", "project_name", "email_address", "address_1", "phone_number", "recurring_donation", "payment_method_id", "reference_number"));
			$this->iTemplateObject->getTableEditorObject()->setMaximumListColumns(8);
		}
	}

	function filterTextProcessing($filterText) {
		if (!empty($filterText)) {
			$parts = explode(" ", $filterText);
			if (count($parts) == 2) {
				$whereStatement = "(contact_id in (select contact_id from contacts where (first_name like " . $GLOBALS['gPrimaryDatabase']->makeParameter($parts[0] . "%") .
					" and last_name like " . $GLOBALS['gPrimaryDatabase']->makeParameter($parts[1] . "%") . ")))";
				$this->iDataSource->addSearchWhereStatement($whereStatement);
			}
			$this->iDataSource->setFilterText($filterText);
		}
	}

	function massageDataSource() {
		$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => "contacts",
			"referenced_column_name" => "contact_id", "foreign_key" => "contact_id",
			"description" => "first_name"));
		$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => "contacts",
			"referenced_column_name" => "contact_id", "foreign_key" => "contact_id",
			"description" => "last_name"));
		$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => "contacts",
			"referenced_column_name" => "contact_id", "foreign_key" => "contact_id",
			"description" => "business_name"));
		$this->iDataSource->addColumnControl("first_name", "select_value", "select first_name from contacts where contact_id = donations.contact_id");
		$this->iDataSource->addColumnControl("first_name", "data_type", "varchar");
		$this->iDataSource->addColumnControl("first_name", "form_label", "First");
		$this->iDataSource->addColumnControl("last_name", "select_value", "select last_name from contacts where contact_id = donations.contact_id");
		$this->iDataSource->addColumnControl("last_name", "data_type", "varchar");
		$this->iDataSource->addColumnControl("last_name", "form_label", "Last");
		$this->iDataSource->addColumnControl("business_name", "select_value", "select business_name from contacts where contact_id = donations.contact_id");
		$this->iDataSource->addColumnControl("business_name", "data_type", "varchar");
		$this->iDataSource->addColumnControl("business_name", "form_label", "Company");

		$this->iDataSource->addColumnControl("email_address", "select_value", "select email_address from contacts where contact_id = donations.contact_id");
		$this->iDataSource->addColumnControl("email_address", "data_type", "varchar");
		$this->iDataSource->addColumnControl("email_address", "form_label", "Email");

		$this->iDataSource->addColumnControl("address_1", "select_value", "select address_1 from contacts where contact_id = donations.contact_id");
		$this->iDataSource->addColumnControl("address_1", "data_type", "varchar");
		$this->iDataSource->addColumnControl("address_1", "form_label", "Address");

		$this->iDataSource->addColumnControl("phone_number", "select_value", "select phone_number from phone_numbers where contact_id = donations.contact_id limit 1");
		$this->iDataSource->addColumnControl("phone_number", "data_type", "varchar");
		$this->iDataSource->addColumnControl("phone_number", "form_label", "Phone");

		$this->iDataSource->addColumnControl("recurring_donation", "select_value", "IF(recurring_donation_id IS NULL,'','Yes')");
		$this->iDataSource->addColumnControl("recurring_donation", "data_type", "varchar");
		$this->iDataSource->addColumnControl("recurring_donation", "form_label", "Recurring");

		$this->iDataSource->setFilterWhere("associated_donation_id is null and transaction_identifier is not null and account_id is not null");
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", "#process_refund", function () {
                $('#_confirm_refund_dialog').dialog({
                    closeOnEscape: true,
                    draggable: true,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 600,
                    title: 'Confirm Refund',
                    buttons: {
                        Refund: function (event) {
                            loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=refund_donation&donation_id=" + $("#primary_id").val(), function(returnArray) {
                                if (!("error_message" in returnArray)) {
                                    $("#instructions").html(returnArray['response']);
                                }
                            });
                            $("#_confirm_refund_dialog").dialog('close');
                        },
                        Cancel: function (event) {
                            $("#_confirm_refund_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
        </script>
		<?php
	}

	function hiddenElements() {
		?>
        <div id="_confirm_refund_dialog" class='dialog-box'><p>Are you sure you want to refund this donation?</p></div>
		<?php
	}
}

$pageObject = new DonationRefundPage("donations");
$pageObject->displayPage();
