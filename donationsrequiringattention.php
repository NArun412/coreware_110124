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

$GLOBALS['gPageCode'] = "DONATIONSREQUIRINGATTENTION";
require_once "shared/startup.inc";

class DonationsRequiringAttentionPage extends Page {
	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("donation_date", "amount", "designation_id", "project_name", "first_name", "last_name", "business_name", "email_address", "address_1", "phone_number"));
			$this->iTemplateObject->getTableEditorObject()->addExcludeFormColumn(array("receipted_contact_id", "associated_donation_id", "recurring_donation_id", "donation_source_id", "donation_batch_id", "pay_period_id"));
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("add"));
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_projects":
				$returnArray['projects'] = array();
				$resultSet = executeQuery("select * from designation_projects where designation_id = ? order by project_name", $_GET['designation_id']);
				while ($row = getNextRow($resultSet)) {
					$returnArray['projects'][] = $row['project_name'];
				}
				$returnArray['project_label'] = getFieldFromId("project_label", "designations", "designation_id", $_GET['designation_id']);
				if (empty($returnArray['project_label'])) {
					$returnArray['project_label'] = "Project";
				}
				$returnArray['memo_label'] = getFieldFromId("memo_label", "designations", "designation_id", $_GET['designation_id']);
				if (empty($returnArray['memo_label'])) {
					$returnArray['memo_label'] = "Notes";
				}
				ajaxResponse($returnArray);
				break;
			case "check_donation":
				$resultSet = executeQuery("select * from donations where contact_id = ? and designation_id = ?", $_GET['contact_id'], $_GET['designation_id']);
				if (!$row = getNextRow($resultSet)) {
					$returnArray['designation_message'] = "This donor has never given to this designation";
				}
				ajaxResponse($returnArray);
				break;
			case "get_previous_donations":
				$previousDonations = "";
				$resultSet = executeQuery("select * from donations where contact_id = ? order by donation_date desc, donation_id desc limit 3", $_GET['contact_id']);
				while ($row = getNextRow($resultSet)) {
					$previousDonations .= (empty($previousDonations) ? "" : "<br>") . date("m/d/Y", strtotime($row['donation_date'])) . ", " .
						getFieldFromId("designation_code", "designations", "designation_id", $row['designation_id']) . " - " .
						getFieldFromId("description", "designations", "designation_id", $row['designation_id']) . ", " . number_format($row['amount'], 2);
				}
				if ($previousDonations) {
					$returnArray['previous_donations'] = $previousDonations;
				} else {
					$returnArray['previous_donations'] = "No Previous Donations";
				}
				ajaxResponse($returnArray);
				break;
			case "get_all_donations":
				$previousDonations = "";
				$resultSet = executeQuery("select * from donations where contact_id = ? order by donation_date desc, donation_id desc", $_GET['contact_id']);
				while ($row = getNextRow($resultSet)) {
					$previousDonations .= (empty($previousDonations) ? "" : "<br>") . date("m/d/Y", strtotime($row['donation_date'])) . ", " .
						getFieldFromId("designation_code", "designations", "designation_id", $row['designation_id']) . " - " .
						getFieldFromId("description", "designations", "designation_id", $row['designation_id']) . ", " . number_format($row['amount'], 2);
				}
				if ($previousDonations) {
					$returnArray['all_donation_list'] = $previousDonations;
				} else {
					$returnArray['all_donation_list'] = "No Previous Donations";
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function massageDataSource() {
		$designations = "";
		$resultSet = executeQuery("select designation_id from designations where requires_attention = 1 and client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$designations .= (empty($designations) ? "" : ",") . $row['designation_id'];
		}
		if (empty($designations)) {
			$designations = "0";
		}
		$this->iDataSource->setFilterWhere("designation_id in (" . $designations . ") and " .
			"pay_period_id is null and client_id = " . $GLOBALS['gClientId']);
		$this->iDataSource->setSaveOnlyPresent(true);
		$this->iDataSource->addColumnControl("donation_date", "readonly", "true");
		$this->iDataSource->addColumnControl("donor_info", "readonly", "true");
		$this->iDataSource->addColumnControl("donor_info", "data_type", "varchar");
		$this->iDataSource->addColumnControl("donor_info", "css-width", "500px");
		$this->iDataSource->addColumnControl("donor_info", "form_label", "Donor");
		$this->iDataSource->addColumnControl("amount", "readonly", "true");
		$this->iDataSource->addColumnControl("project_name", "form_label", "Project");
		$this->iDataSource->addColumnControl("project_name", "data_type", "select");

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
		$this->iDataSource->addColumnControl("phone_number", "select_value", "select phone_number from phone_numbers where contact_id = donations.contact_id limit 1");
		$this->iDataSource->addColumnControl("phone_number", "data_type", "varchar");
		$this->iDataSource->addColumnControl("phone_number", "form_label", "Phone");

		$this->iDataSource->addColumnControl("address_1", "select_value", "select address_1 from contacts where contact_id = donations.contact_id");
		$this->iDataSource->addColumnControl("address_1", "data_type", "varchar");
		$this->iDataSource->addColumnControl("address_1", "form_label", "Address");
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#designation_id_autocomplete_text").blur(function () {
                $("#designation_message").html("");
                $("#_designation_message_row").hide();
                if (!empty($("#designation_id").val()) && !empty($("#contact_id").val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=check_donation&contact_id=" + $("#contact_id").val() + "&designation_id=" + $("#designation_id").val(), function(returnArray) {
                        if ("designation_message" in returnArray) {
                            $("#designation_message").html(returnArray['designation_message']);
                            $("#_designation_message_row").show();
                        }
                    });
                }
                getProjects();
            });
            $(document).on("tap click", "#view_donations", function () {
                if (!empty($("#contact_id").val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_all_donations&contact_id=" + $("#contact_id").val(), function(returnArray) {
                        $("#all_donation_list").html(returnArray['all_donation_list']);
                        $('#_donations_dialog').dialog({
                            closeOnEscape: true,
                            draggable: false,
                            modal: true,
                            resizable: false,
                            position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                            width: 600,
                            title: 'Previous Donations',
                            buttons: {
                                Close: function (event) {
                                    $("#_donations_dialog").dialog('close');
                                }
                            }
                        });
                    });
                } else {
                    $("#previous_donations").html("");
                }
                return false;
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function displayPreviousDonations() {
                $("#designation_message").html("");
                $("#_designation_message_row").hide();
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_previous_donations&contact_id=" + $("#contact_id").val(), function(returnArray) {
                    $("#previous_donations").html(returnArray['previous_donations']);
                });
            }
            function afterGetRecord() {
                displayPreviousDonations();
                if ($("#project_name option").length > 1) {
                    $("#_project_name_row").show();
                } else {
                    $("#_project_name_row").hide();
                }
                setTimeout(function () {
                    $("#designation_id_autocomplete_text").blur();
                }, 300);
                getProjects();
            }
            function getProjects() {
                const designationId = $("#designation_id").val();
                if (empty(designationId)) {
                    $("#_project_name_row").hide();
                } else {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_projects&designation_id=" + designationId, function(returnArray) {
                        if ("projects" in returnArray) {
                            $("#project_name").find("option[value!='']").remove();
                            for (const i in returnArray['projects']) {
                                $("#project_name").append($("<option></option>").attr("value", returnArray['projects'][i]).text(returnArray['projects'][i]).data("inactive", "0"));
                            }
                        } else {
                            $("#_project_name_row").hide();
                        }
                        if ($("#project_name option").length > 1) {
                            $("#_project_name_row").show();
                        } else {
                            $("#_project_name_row").hide();
                        }
                        $("#_project_name_row").find("label").html(returnArray['project_label']);
                        $("#_notes_row").find("label").html(returnArray['memo_label']);
                    });
                }
            }
        </script>
		<?php
	}

	function afterGetRecord(&$returnArray) {
		if (!is_array($returnArray['select_values'])) {
			$returnArray['select_values'] = array();
		}
		$returnArray['select_values']['project_name'] = array();
		$resultSet = executeQuery("select * from designation_projects where designation_id = ? order by project_name", $returnArray['designation_id']['data_value']);
		while ($row = getNextRow($resultSet)) {
			$returnArray['select_values']['project_name'][] = array("key_value" => $row['project_name'], "description" => $row['project_name']);
		}
		if (!empty($returnArray['project_name']['data_value'])) {
			$returnArray['notes']['data_value'] .= $returnArray['project_name']['data_value'];
			$returnArray['notes']['crc_value'] = getCrcValue($returnArray['notes']['data_value']);
			$returnArray['project_name']['data_value'] = "";
			$returnArray['project_name']['crc_value'] = getCrcValue($returnArray['project_name']['data_value']);
		}
        $contactData = Contact::getContact($returnArray['contact_id']['data_value']);
		$donorInfo = getDisplayName($returnArray['contact_id']['data_value']) . "<br>" . getAddressBlock($contactData, "<br>", array("include_email"=>true, "include_phone"=>true));
		$returnArray['donor_info'] = array("data_value" => $donorInfo);
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		$donationFee = Donations::getDonationFee(getRowFromId("donations", "donation_id", $nameValues['primary_id']));
		executeQuery("update donations set donation_fee = ? where donation_id = ?", $donationFee, $nameValues['primary_id']);
		$emailId = getFieldFromId("email_id", "emails", "email_code", "DONATION_RECEIVED", "inactive = 0");
		if (!empty($emailId)) {
			Donations::sendDonationNotifications($nameValues['primary_id'], $emailId);
		}
		return true;
	}

	function internalCSS() {
		?>
        <style>
            #all_donation_list {
                max-height: 600px;
                overflow: auto;
                margin: 20px 0;
                font-size: 14px;
                font-weight: bold;
            }
            #previous_donations {
                font-size: 13px;
                font-weight: bold;
            }
            #_designation_message_row {
                display: none;
            }
            #designation_message {
                font-size: 16px;
                color: rgb(192, 0, 0);
                font-weight: bold;
            }
            #_project_name_row {
                display: none;
            }
            #donor_info {
                margin-bottom: 20px;
            }
        </style>
		<?php
	}

	function hiddenElements() {
		?>
        <div id="_donations_dialog" class="dialog-box">
            <div id="all_donation_list">
            </div>
        </div>
		<?php
	}
}

$pageObject = new DonationsRequiringAttentionPage("donations");
$pageObject->displayPage();
