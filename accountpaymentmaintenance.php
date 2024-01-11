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

$GLOBALS['gPageCode'] = "ACCOUNTPAYMENTMAINT";
require_once "shared/startup.inc";

class AccountPaymentMaintenancePage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_accounts":
				$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $_GET['contact_id']);
				if (empty($contactId)) {
					$returnArray['error_message'] = "Invalid Contact";
				} else {
					$returnArray['accounts'] = array();
					$resultSet = executeQuery("select * from accounts where inactive = 0 and contact_id = ?", $contactId);
					while ($row = getNextRow($resultSet)) {
						$description = getFieldFromId("description", "payment_methods", "payment_method_id", $row['payment_method_id']) . " " . str_replace("X", "", $row['account_number']);
						$returnArray['accounts'][] = array("key_value" => $row['account_id'], "description" => $description);
					}
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("contact_id", "data_type", "contact_picker");
		$this->iDataSource->addColumnControl("contact_id", "form_label", "Contact");
		$this->iDataSource->addColumnControl("contact_id", "not_editable", true);
		$this->iDataSource->addColumnControl("account_id", "choices", array());
		$this->iDataSource->addColumnControl("payment_account_id", "choices", array());
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("change", "#contact_id", function () {
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_accounts&contact_id=" + $(this).val(), function(returnArray) {
                        const $accountId = $("#account_id");
                        const $paymentAccountId = $("#payment_account_id");
                        if ("accounts" in returnArray) {
                            $accountId.find("option[value!='']").remove();
                            for (let i in returnArray['accounts']) {
                                let thisOption = $("<option></option>").attr("value", returnArray['accounts'][i]['key_value']).text(returnArray['accounts'][i]['description']);
                                $accountId.append(thisOption);
                            }
                            $paymentAccountId.find("option[value!='']").remove();
                            for (let i in returnArray['accounts']) {
                                let thisOption = $("<option></option>").attr("value", returnArray['accounts'][i]['key_value']).text(returnArray['accounts'][i]['description']);
                                $paymentAccountId.append(thisOption);
                            }
                        }
                    });
                }
            });
        </script>
		<?php
	}

	function afterGetRecord(&$returnArray) {
		$contactId = getFieldFromId("contact_id", "accounts", "account_id", $returnArray['account_id']['data_value']);
		$returnArray['contact_id'] = array("data_value" => $contactId);
		if (!empty($contactId)) {
			$description = getDisplayName($contactId, array("include_company" => true, "prepend_company" => true));
			$address1 = getFieldFromId("address_1", "contacts", "contact_id", $contactId);
			if (!empty($address1)) {
				if (!empty($description)) {
					$description .= " • ";
				}
				$description .= $address1;
			}
			$city = getFieldFromId("city", "contacts", "contact_id", $contactId);
			$state = getFieldFromId("state", "contacts", "contact_id", $contactId);
			if (!empty($state)) {
				if (!empty($city)) {
					$city .= ", ";
				}
				$city .= $state;
			}
			if (!empty($city)) {
				if (!empty($description)) {
					$description .= " • ";
				}
				$description .= $city;
			}
			$emailAddress = getFieldFromId("email_address", "contacts", "contact_id", $contactId);
			if (!empty($emailAddress)) {
				if (!empty($description)) {
					$description .= " • ";
				}
				$description .= $emailAddress;
			}
			$returnArray['select_values']["contact_id_selector"] = array(array("key_value" => $contactId, "description" => $description));
			$returnArray["contact_id_selector"] = array("data_value" => $contactId);
		}
		$returnArray['select_values']["account_id"] = array();
		if (!empty($returnArray['contact_id']['data_value'])) {
			$resultSet = executeQuery("select * from accounts where contact_id = ?", $returnArray['contact_id']['data_value']);
			while ($row = getNextRow($resultSet)) {
				$description = getFieldFromId("description", "payment_methods", "payment_method_id", $row['payment_method_id']) . " " . str_replace("X", "", $row['account_number']);
				$returnArray['select_values']["account_id"][] = array("key_value" => $row['account_id'], "description" => $description);
			}
		}
		$returnArray['select_values']["payment_account_id"] = $returnArray['select_values']["account_id"];
	}
}

$pageObject = new AccountPaymentMaintenancePage("account_payments");
$pageObject->displayPage();
