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

$GLOBALS['gPageCode'] = "RECURRINGDONATIONCHANGEMAINT";
require_once "shared/startup.inc";

class RecurringDonationChangePage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$filters = array();
			$filters['hide_completed'] = array("form_label" => "Hide Completed", "where" => "date_completed is null", "data_type" => "tinyint", "set_default" => true);
			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_recurring_donations":
				$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $_GET['contact_id']);
				if (empty($contactId)) {
					$returnArray['error_message'] = "Invalid Contact";
					ajaxResponse($returnArray);
					exit;
				}
				$resultSet = executeQuery("select * from recurring_donations where contact_id = ? and (end_date is null or end_date >= current_date)", $contactId);
				if ($resultSet['row_count'] == 0) {
					$returnArray['error_message'] = "No recurring donations found";
					ajaxResponse($returnArray);
					exit;
				}
				ob_start();
				?>
                <table class='grid-table'>
                    <tr>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Next Billing Date</th>
                        <th>For</th>
                    </tr>
				<?php
				while ($row = getNextRow($resultSet)) {
					?>
                    <tr class='recurring-donation' data-recurring_donation_id='<?= $row['recurring_donation_id'] ?>'>
                        <td><?= htmlText(getFieldFromId("description", "recurring_donation_types", "recurring_donation_type_id", $row['recurring_donation_type_id'])) ?></td>
                        <td><?= number_format($row['amount'], 2) ?></td>
                        <td><?= date("m/d/Y", strtotime($row['next_billing_date'])) ?></td>
                        <td><?= htmlText(getFieldFromId("description", "designations", "designation_id", $row['designation_id'])) ?></td>
                    </tr>
					<?php
				}
				?>
                </table>
				<?php
				$returnArray['recurring_donation_list'] = ob_get_clean();
				ajaxResponse($returnArray);
				exit;
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("contact_id", "data_type", "contact_picker");
		$this->iDataSource->addColumnControl("contact_id", "form_label", "Select Contact");
		$this->iDataSource->addColumnControl("contact_id", "not_editable", true);

		$this->iDataSource->addColumnControl("amount", "help_label", "leave blank for no change");
		$this->iDataSource->addColumnControl("next_billing_date", "help_label", "leave blank for no change");
		$this->iDataSource->addColumnControl("date_completed", "readonly", true);
		$this->iDataSource->addColumnControl("change_date", "validation_classes", "future");
		$this->iDataSource->addColumnControl("recurring_donation_id", "data_type", "hidden");
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#contact_id").change(function () {
                $("#recurring_donation_list").html("");
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_recurring_donations&contact_id=" + $(this).val(), function (returnArray) {
                        if ("recurring_donation_list" in returnArray) {
                            $("#recurring_donation_list").html(returnArray['recurring_donation_list']);
                        }
                    });
                }
            });
            $(document).on("click", ".recurring-donation", function () {
                $(".recurring-donation").removeClass("selected");
                $("#recurring_donation_id").val($(this).data("recurring_donation_id"));
                $(this).addClass("selected");
            });
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            .recurring-donation {
                cursor: pointer;
            }
            .recurring-donation:hover {
                background-color: rgb(240, 240, 160);
            }
            .recurring-donation.selected {
                background-color: rgb(180, 210, 250);
            }
        </style>
		<?php
	}

	function afterGetRecord(&$returnArray) {
		$returnArray['contact_id'] = array("data_value" => getFieldFromId("contact_id", "recurring_donations", "recurring_donation_id", $returnArray['recurring_donation_id']['data_value']));
		if (!empty($returnArray['recurring_donation_id']['data_value'])) {
			$row = getRowFromId("recurring_donations", "recurring_donation_id", $returnArray['recurring_donation_id']['data_value']);
			$details = getFieldFromId("description", "recurring_donation_types", "recurring_donation_type_id", $row['recurring_donation_type_id']) . ", " .
				number_format($row['amount'], 2) . ", bill next on " .
				date("m/d/Y", strtotime($row['next_billing_date'])) . ", " .
				getFieldFromId("description", "designations", "designation_id", $row['designation_id']);
			$returnArray['recurring_donation_details'] = array("data_value" => $details);
		}
	}

	function beforeSaveChanges(&$nameValues) {
		if (empty($nameValues['amount']) && empty($nameValues['next_billing_date'])) {
			return "Nothing set to change";
		}
		return true;
	}
}

$pageObject = new RecurringDonationChangePage("recurring_donation_changes");
$pageObject->displayPage();
