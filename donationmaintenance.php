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

$GLOBALS['gPageCode'] = "DONATIONMAINT";
require_once "shared/startup.inc";

class DonationMaintenancePage extends Page {
	var $iDonationBatchId = "";

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_donation_commitment":
				$returnArray['donation_commitment_id'] = Donations::getContactDonationCommitment($_GET['contact_id'], $_GET['designation_id']);
				ajaxResponse($returnArray);
				break;
			case "get_projects":
				$projectLabel = "Project";
				$projectLabelField = getFieldFromId("project_label", "designation_types", "designation_type_id",
					getFieldFromId("designation_type_id", "designations", "designation_id", $_GET['designation_id']));
				$projectLabel = (empty($projectLabelField) ? $projectLabel : $projectLabelField);
				$projectLabelField = getFieldFromId("project_label", "designations", "designation_id", $_GET['designation_id']);
				$projectLabel = (empty($projectLabelField) ? $projectLabel : $projectLabelField);
				$returnArray['project_label'] = $projectLabel;

				$memoLabel = "";
				$memoLabelField = getFieldFromId("memo_label", "designations", "designation_id", $_GET['designation_id']);
				$memoLabel = (empty($memoLabelField) ? $memoLabel : $memoLabelField);
				$returnArray['memo_label'] = $memoLabel;

				$returnArray['projects'] = array();
				$resultSet = executeQuery("select * from designation_projects where designation_id = ? order by project_name", $_GET['designation_id']);
				while ($row = getNextRow($resultSet)) {
					$returnArray['projects'][] = $row['project_name'];
				}
				ajaxResponse($returnArray);
				break;
			case "update_fee":
				if (strlen($_POST['donation_fee']) > 0) {
					$resultSet = executeQuery("update donations set donation_fee = ? where donation_id = ?", $_POST['donation_fee'], $_GET['primary_id']);
					if ($resultSet['affected_rows'] == 1) {
						$returnArray['info_message'] = "Donation fee successfully updated.";
					} else {
						$returnArray['error_message'] = "Donation fee not able to be updated.";
					}
				} else {
					$returnArray['error_message'] = "Donation fee not able to be updated.";
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
				$_GET['contact_id'] = getFieldFromId("contact_id", "contacts", "contact_id", $_GET['contact_id']);
				$returnArray['contact_notes'] = getFieldFromId("notes", "contacts", "contact_id", $_GET['contact_id']);
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
				$resultSet = executeQuery("select * from donation_commitments where contact_id = ? and date_completed is null order by start_date", $_GET['contact_id']);
				$returnArray['donation_commitment_ids'] = array();
				while ($row = getNextRow($resultSet)) {
					$returnArray['donation_commitment_ids'][] = array("donation_commitment_id" => $row['donation_commitment_id'],
						"description" => htmlText(getFieldFromId("description", "donation_commitment_types", "donation_commitment_type_id", $row['donation_commitment_type_id']) .
							", " . (empty($row['start_date']) ? "" : "started " . date("m/d/Y", strtotime($row['start_date'])))), "donation_commitment_type_id" => $row['donation_commitment_type_id']);
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

	function filtersChanged($setFilters) {
		if (empty($setFilters['cookie_batch'])) {
			setCoreCookie("donation_batch_id", "", -1);
			$_COOKIE['donation_batch_id'] = "";
		}
	}

	function setup() {
		if ($_GET['ajax'] != "true") {
			$this->iDonationBatchId = getFieldFromId("donation_batch_id", "donation_batches", "donation_batch_id", $_GET['donation_batch_id'], "date_completed is null and client_id = ?", $GLOBALS['gClientId']);
			if (empty($this->iDonationBatchId)) {
				$this->iDonationBatchId = getFieldFromId("donation_batch_id", "donation_batches", "donation_batch_id", $_POST['donation_batch_id'], "date_completed is null and client_id = ?", $GLOBALS['gClientId']);
			}
			if (empty($this->iDonationBatchId)) {
				$this->iDonationBatchId = getFieldFromId("donation_batch_id", "donation_batches", "donation_batch_id", $_COOKIE['donation_batch_id'], "date_completed is null and client_id = ?", $GLOBALS['gClientId']);
			}
			setCoreCookie("donation_batch_id", $this->iDonationBatchId, 24);
			$_COOKIE['donation_batch_id'] = $this->iDonationBatchId;
		}
		$this->iDonationBatchId = $_COOKIE['donation_batch_id'];
		$this->iDataSource->addColumnControl("donation_batch_id", "default_value", $this->iDonationBatchId);
		if (!empty($this->iDonationBatchId)) {
			$dateCompleted = getFieldFromId("date_completed", "donation_batches", "donation_batch_id", $this->iDonationBatchId);
			$this->iDataSource->addColumnControl("donation_date", "default_value", getFieldFromId("batch_date", "donation_batches", "donation_batch_id", $this->iDonationBatchId));
			$this->iDataSource->addColumnControl("payment_method_id", "default_value", getFieldFromId("payment_method_id", "donation_batches", "donation_batch_id", $this->iDonationBatchId));
			$this->iDataSource->addColumnControl("designation_id", "default_value", getFieldFromId("designation_id", "donation_batches", "donation_batch_id", $this->iDonationBatchId));
			if (!empty($dateCompleted)) {
				if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
					$this->iTemplateObject->getTableEditorObject()->setReadonly(true);
				}
			}
		} else {
			if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
				$this->iTemplateObject->getTableEditorObject()->setReadonly(true);
			}
		}
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$filters = array();
			if (!empty($this->iDonationBatchId)) {
				$filters['cookie_batch'] = array("form_label" => "Show only batch number " . getFieldFromId("batch_number", "donation_batches", "donation_batch_id", $this->iDonationBatchId), "where" => "donation_batch_id = " . $this->iDonationBatchId, "data_type" => "tinyint", "set_default" => true);
				$this->iDataSource->setFilterWhere("donation_batch_id = " . $this->iDonationBatchId);
				$this->iTemplateObject->getTableEditorObject()->addVisibleFilters($filters);
				$filterSettings = Page::getPagePreferences($GLOBALS['gPageCode'], "MAINTENANCE_SET_FILTERS");
				$filterSettings['cookie_batch'] = 1;
				Page::setPagePreferences($filterSettings, $GLOBALS['gPageCode'], "MAINTENANCE_SET_FILTERS");
				$filters = array();
			}
			$resultSet = executeQuery("select * from designation_groups where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$filters['designation_group_' . $row['designation_group_id']] = array("form_label" => $row['description'], "where" => "designation_id in (select designation_id from designation_group_links where designation_group_id = " . $row['designation_group_id'] . ")", "data_type" => "tinyint");
			}
			$filters['start_date'] = array("form_label" => "Start Date", "where" => "donation_date >= '%filter_value%'", "data_type" => "date", "conjunction" => "and");
			$filters['end_date'] = array("form_label" => "End Date", "where" => "donation_date <= '%filter_value%'", "data_type" => "date", "conjunction" => "and");
            $resultSet = executeQuery("select * from designations where inactive = 0 and client_id = ? order by description",$GLOBALS['gClientId']);
            $designations = array();
            if ($resultSet['row_count'] < 200) {
                while ($row = getNextRow($resultSet)) {
        			$designations[$row['designation_id']] = $row['description'];
		        }
	            $filters['designation_id'] = array("form_label" => "Designations", "where" => "designation_id = %key_value%","data_type" => "select", "choices" => $designations);
            }
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

		$this->iDataSource->addColumnControl("pay_period_id", "data_type", "int");
		$this->iDataSource->addColumnControl("pay_period_id", "readonly", true);

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

		$this->iDataSource->addColumnControl("batch_number", "select_value", "select batch_number from donation_batches where donation_batch_id = donations.donation_batch_id");
		$this->iDataSource->addColumnControl("batch_number", "data_type", "int");
		$this->iDataSource->addColumnControl("batch_number", "form_label", "Batch #");

		$this->iDataSource->addColumnControl("donation_commitment_id", "choices", array());
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		if (!empty($this->iDonationBatchId)) {
			executeQuery("update donation_batches set user_id = ? where donation_batch_id = ? and user_id is null", $GLOBALS['gUserId'], $this->iDonationBatchId);
		}
		return true;
	}

	function beforeSaveChanges(&$nameValues) {
		if ($nameValues['amount'] < 0) {
			unset($nameValues['contact_id']);
			unset($nameValues['payment_method_id']);
			unset($nameValues['reference_number']);
			unset($nameValues['amount']);
			unset($nameValues['designation_id']);
			unset($nameValues['project_name']);
			unset($nameValues['anonymous_gift']);
			unset($nameValues['receipted_contact_id']);
		}
		return true;
	}

	function contactPresets() {
		$resultSet = executeQuery("select contact_id,address_1,state,city,email_address from contacts where deleted = 0 and client_id = ? " .
			"and contact_id in (select contact_id from donations where donation_batch_id = ?) order by date_created", $GLOBALS['gClientId'], $this->iDonationBatchId);
		$contactList = array();
		while ($row = getNextRow($resultSet)) {
			$description = getDisplayName($row['contact_id'], array("include_company" => true));
			if (!empty($row['address_1'])) {
				if (!empty($description)) {
					$description .= " • ";
				}
				$description .= $row['address_1'];
			}
			if (!empty($row['state'])) {
				if (!empty($row['city'])) {
					$row['city'] .= ", ";
				}
				$row['city'] .= $row['state'];
			}
			if (!empty($row['city'])) {
				if (!empty($description)) {
					$description .= " • ";
				}
				$description .= $row['city'];
			}
			if (!empty($row['email_address'])) {
				if (!empty($description)) {
					$description .= " • ";
				}
				$description .= $row['email_address'];
			}
			$contactList[$row['contact_id']] = $description;
		}
		asort($contactList);
		return $contactList;
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#contact_id").click(function () {
                if ($(this).prop("readonly")) {
                    window.open("/contactmaintenance.php?clear_filter=true&url_page=show&primary_id=" + $(this).val());
                }
            });
            $("#amount").change(function () {
                if ($(this).val() <= 0) {
                    $("#amount").val("");
                    displayErrorMessage("Only positive values are valid");
                }
            });
            $(document).on("tap click", "#update_fee", function () {
                if ($("#_edit_form").validationEngine('validate') && !empty($("#donation_fee").val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=update_fee&primary_id=" + $("#primary_id").val(), $("#donation_fee").serialize());
                }
                return false;
            });
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
            $("#contact_id").change(function () {
                $("#designation_message").html("");
                $("#_designation_message_row").hide();
                $("#donation_commitment_id").find("option[value!='']").remove();
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_previous_donations&contact_id=" + $(this).val(), function(returnArray) {
                        if (empty(returnArray['contact_notes'])) {
                            $("#_contact_notes_row").addClass("hidden");
                        } else {
                            $("#_contact_notes_row").removeClass("hidden");
                        }
                        $("#contact_notes").html(returnArray['contact_notes']);
                        $("#previous_donations").html(returnArray['previous_donations']);
                        if ("donation_commitment_ids" in returnArray) {
                            for (const i in returnArray['donation_commitment_ids']) {
                                $("#donation_commitment_id").append($("<option></option>").attr("value", returnArray['donation_commitment_ids'][i]['donation_commitment_id']).text(returnArray['donation_commitment_ids'][i]['description']).data("donation_commitment_type_id", returnArray['donation_commitment_ids'][i]['donation_commitment_type_id']));
                            }
                        }
                    });
                    $("#designation_id").trigger("change");
                } else {
                    $("#previous_donations").html("");
                }
            });
            $("#designation_id").change(function () {
                getDonationCommitmentId();
            });
            $("#donation_source_id").change(function () {
                getDonationCommitmentId();
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
		$dateCompleted = getFieldFromId("date_completed", "donation_batches", "donation_batch_id", $this->iDonationBatchId);
		?>
        <script>
            function afterGetDataList() {
                if ($("#_set_filter_cookie_batch").length > 0 && !$("#_set_filter_cookie_batch").prop("checked")) {
                    $("#_set_filter_cookie_batch").closest("div.form-line").remove();
                }
            }
            function getDonationCommitmentId() {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_donation_commitment&contact_id=" + $("#contact_id").val() + "&designation_id=" +
                    $("#designation_id").val() + "&donation_source_id=" + $("#donation_source_id").val(), function(returnArray) {
                    if ("donation_commitment_id" in returnArray) {
                        $("#donation_commitment_id").val(returnArray['donation_commitment_id']);
                    }
                });
            }
            function afterGetRecord() {
                $("#contact_id").trigger("change");
				<?php if (!empty($this->iDonationBatchId) && empty($dateCompleted)) { ?>
                if ($("#amount").val() < 0) {
                    $(".contact-picker").hide();
                } else {
                    $(".contact-picker").show();
                }
                $("#contact_id_selector").prop("disabled", ($("#amount").val() < 0));
                $("#receipted_contact_id_selector").prop("disabled", ($("#amount").val() < 0));
                $("#payment_method_id").prop("disabled", ($("#amount").val() < 0));
                $("#reference_number").prop("readonly", ($("#amount").val() < 0));
                $("#amount").prop("readonly", ($("#amount").val() < 0));
                $("#designation_id_autocomplete_text").prop("readonly", ($("#amount").val() < 0));
                $("#project_name").prop("readonly", ($("#amount").val() < 0));
                $("#anonymous_gift").prop("readonly", ($("#amount").val() < 0));
                $("#amount").prop("readonly", ($("#amount").val() < 0));
                $("#_edit_form").find("input[type!=hidden]:not([readonly='readonly']):not([disabled='disabled']),select:not([disabled='disabled']),textarea:not([readonly='readonly'])")[0].focus();
				<?php } ?>
                if ($("#project_name option").length > 1) {
                    $("#_project_name_row").show();
                } else {
                    $("#_project_name_row").hide();
                }
                $("#project_name").find("option[value!='']").data("inactive", "1");
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
                                $("#project_name").append($("<option></option>").attr("value", returnArray['projects'][i]).text(returnArray['projects'][i]).data("inactive", "1"));
                            }
                        } else {
                            $("#_project_name_row").hide();
                        }
                        if ($("#project_name option").length > 1) {
                            $("#_project_name_row").show();
                        } else {
                            $("#_project_name_row").hide();
                        }
                        if ("project_label" in returnArray) {
                            $("#_project_name_row").find("label").html(returnArray['project_label']);
                        }
                        if ("memo_label" in returnArray) {
                            $("#_notes_row").find("label").html(returnArray['memo_label']);
                        }
                    });
                }
            }
        </script>
		<?php
	}

	function afterGetRecord(&$returnArray) {
		$orderId = getFieldFromId("order_id","orders","donation_id",$returnArray['primary_id']['data_value']);
		$returnArray['from_order'] = array("data_value"=>(empty($orderId) ? "" : "Round up from Order ID " .
			(canAccessPageCode("ORDERDASHBOARD") ? "<a target='_blank' href='/orderdashboard.php?clear_filter=true&url_page=show&primary_id=" . $orderId . "'>" . $orderId . "</a>" : $orderId)));
		$returnArray['batch_number'] = array("data_value" => getFieldFromId("batch_number", "donation_batches", "donation_batch_id", $returnArray['donation_batch_id']['data_value']));
		if (!is_array($returnArray['select_values'])) {
			$returnArray['select_values'] = array();
		}
		$returnArray['select_values']['project_name'] = array();
		$resultSet = executeQuery("select * from designation_projects where designation_id = ? order by project_name", $returnArray['designation_id']['data_value']);
		$foundProject = false;
		while ($row = getNextRow($resultSet)) {
			if ($returnArray['project_name']['data_value'] == $row['project_name']) {
				$foundProject = true;
			}
			$returnArray['select_values']['project_name'][] = array("key_value" => $row['project_name'], "description" => $row['project_name']);
		}
		if (!$foundProject) {
			$returnArray['select_values']['project_name'][] = array("key_value" => $returnArray['project_name']['data_value'], "description" => $returnArray['project_name']['data_value']);
		}
	}

	function internalCSS() {
		?>
        <style>
            <?php if (!empty($this->iDonationBatchId)) { ?>
            #_update_fee_row {
                display: none;
            }
            <?php } ?>
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
            #from_order {
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

$pageObject = new DonationMaintenancePage("donations");
$pageObject->displayPage();
