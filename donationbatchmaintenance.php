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

$GLOBALS['gPageCode'] = "DONATIONBATCHMAINT";
require_once "shared/startup.inc";

class DonationBatchMaintenance extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("batch_number", "batch_date", "user_id", "donation_count", "total_donations", "date_completed", "date_posted", "donations_entered"));
			$this->iTemplateObject->getTableEditorObject()->setMaximumListColumns(8);
			$filters = array();
			$filters['hide_posted'] = array("form_label" => "Hide Posted", "where" => "date_posted is null", "data_type" => "tinyint", "set_default" => true);
			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
			$this->iTemplateObject->getTableEditorObject()->addCustomAction("close_batches", "Close Selected Batches");
			$this->iTemplateObject->getTableEditorObject()->addCustomAction("reopen_batches", "Reopen Selected Batches");
			$this->iTemplateObject->getTableEditorObject()->addCustomAction("post_batches", "Post Selected Batches");
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("pdf_button", "data_type", "button");
		$this->iDataSource->addColumnControl("pdf_button", "button_label", "Download PDF");
		$this->iDataSource->addColumnControl("actual_donation_count", "data_type", "hidden");
		$this->iDataSource->addColumnControl("donations_entered", "select_value", "select concat_ws(' ',count(*),'/',format(coalesce(sum(amount),0),2)) from donations where donation_batch_id = donation_batches.donation_batch_id");
		$this->iDataSource->addColumnControl("donations_entered", "list_header", "Entered");

		$this->iDataSource->addColumnControl("donations_entry", "before_save_record", "massageDonationEntry");
		$this->iDataSource->addColumnControl("donations_entry", "column_list", "contact_id,payment_method_id,reference_number,amount,designation_id,anonymous,donation_source_id");
		$this->iDataSource->addColumnControl("donations_entry", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("donations_entry", "data_type", "custom");
		$this->iDataSource->addColumnControl("donations_entry", "filter_where", "amount > 0");
		$this->iDataSource->addColumnControl("donations_entry", "list_table", "donations");
		$listTableControls = array("contact_id" => array("show_id_field" => "true"), "payment_method_id" => array("not_null" => "true"), "reference_number" => array("inline-width" => "80px", "form_label" => "Ref #"), "donation_source_id" => array("form_label" => "Source"), "donation_date" => array("default_value" => "return date('m/d/Y')"), "amount" => array("minimum_value" => ".01"), "designation_id" => array("classes" => "designation-id"));
		$this->iDataSource->addColumnControl("donations_entry", "list_table_controls", $listTableControls);
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "contact_notes":
				$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $_GET['contact_id']);
				$returnArray['contact_notes'] = makeHtml(getFieldFromId("notes", "contacts", "contact_id", $contactId));
				$returnArray['row_number'] = $_GET['row_number'];
				ajaxResponse($returnArray);
				break;
			case "check_donation":
				$resultSet = executeQuery("select * from donations where contact_id = ? and designation_id = ?", $_GET['contact_id'], $_GET['designation_id']);
				if (!$row = getNextRow($resultSet)) {
					$returnArray['designation_message'] = "This donor has never given to this designation";
				}
				ajaxResponse($returnArray);
				break;
			case "post_batches":
				$donationBatchIds = array();
				$resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$donationBatchIds[] = $row['primary_identifier'];
				}
				$notClosed = array();
				$alreadyPosted = array();
				$emailId = getFieldFromId("email_id", "emails", "email_code", "DONATION_RECEIVED", "inactive = 0");
				foreach ($donationBatchIds as $donationBatchId) {
					$resultSet = executeQuery("select batch_number,date_completed,date_posted from " .
						"donation_batches where client_id = ? and donation_batch_id = ?", $GLOBALS['gClientId'], $donationBatchId);
					if ($row = getNextRow($resultSet)) {
						$batchNumber = $row['batch_number'];
						$dateCompleted = $row['date_completed'];
						$datePosted = $row['date_posted'];
					} else {
						continue;
					}
					if (empty($dateCompleted)) {
						$notClosed[] = $batchNumber;
						continue;
					}
					if (!empty($datePosted)) {
						$alreadyPosted[] = $batchNumber;
						continue;
					}
					executeQuery("update donation_batches set date_posted = now() where donation_batch_id = ?", $donationBatchId);
					executeQuery("update donations set donation_date = (select batch_date from donation_batches where donation_batch_id = ?) where donation_batch_id = ?", $donationBatchId, $donationBatchId);
					$resultSet = executeQuery("select * from donations where amount > 0 and donation_fee is null and donation_batch_id = ?", $donationBatchId);
					while ($row = getNextRow($resultSet)) {
						if (empty($row['associated_donation_id'])) {
							$donationId = $row['donation_id'];
							$donationFee = Donations::getDonationFee(array("designation_id" => $row['designation_id'], "amount" => $row['amount'], "payment_method_id" => $row['payment_method_id']));
							executeQuery("update donations set donation_fee = ? where donation_id = ?", $donationFee, $donationId);
						}
					}
					$resultSet = executeQuery("select * from donations where associated_donation_id is not null and donation_batch_id = ?", $donationBatchId);
					while ($row = getNextRow($resultSet)) {
						executeQuery("update donations set associated_donation_id = ? where donation_id = ?", $row['donation_id'], $row['associated_donation_id']);
					}
					$resultSet = executeQuery("select * from donations where donation_batch_id = ?", $donationBatchId);
					while ($row = getNextRow($resultSet)) {
						Donations::completeDonationCommitment($row['donation_commitment_id']);
					}
					if (!empty($emailId)) {
						$resultSet = executeQuery("select * from donations join contacts using (contact_id) where amount > 0 and donation_batch_id = ?", $donationBatchId);
						while ($row = getNextRow($resultSet)) {
							Donations::sendDonationNotifications($row['donation_id'], $emailId);
						}
					}
				}
				$errorText = "";
				if (!empty($notClosed)) {
					$batchList = "";
					foreach ($notClosed as $batchNumber) {
						$batchList .= (empty($batchList) ? "" : ",") . $batchNumber;
					}
					$errorText = "Batch(es) not closed: " . $batchList;
				}
				if (!empty($alreadyPosted)) {
					$batchList = "";
					foreach ($alreadyPosted as $batchNumber) {
						$batchList .= (empty($batchList) ? "" : ",") . $batchNumber;
					}
					$errorText = (empty($errorText) ? "" : "<br>") . "Batch(es) already posted: " . $batchList;
				}
				$returnArray['error_message'] = $errorText;
				ajaxResponse($returnArray);
				break;
			case "close_batches":
				$donationBatchIds = array();
				$resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$donationBatchIds[] = $row['primary_identifier'];
				}
				$alreadyClosed = array();
				$wrongTotals = array();
				foreach ($donationBatchIds as $donationBatchId) {
					$resultSet = executeQuery("select batch_number,donation_count,total_donations,date_completed from " .
						"donation_batches where client_id = ? and donation_batch_id = ?", $GLOBALS['gClientId'], $donationBatchId);
					if ($row = getNextRow($resultSet)) {
						$batchNumber = $row['batch_number'];
						$dateCompleted = $row['date_completed'];
						$donationCount = $row['donation_count'];
						$totalDonations = $row['total_donations'];
					} else {
						continue;
					}
					if (!empty($dateCompleted)) {
						$alreadyClosed[] = $batchNumber;
						continue;
					}
					$resultSet = executeQuery("select count(*),coalesce(sum(amount),0) from donations where donation_batch_id = ?", $donationBatchId);
					if ($row = getNextRow($resultSet)) {
						$realCount = $row['count(*)'];
						$realTotal = $row['coalesce(sum(amount),0)'];
						if (empty($realTotal)) {
							$realTotal = 0;
						}
					} else {
						$realCount = 0;
						$realTotal = 0;
					}
					if ($donationCount != $realCount || $totalDonations != $realTotal) {
						$wrongTotals[] = $batchNumber;
						continue;
					}
					executeQuery("update donation_batches set date_completed = now() where donation_batch_id = ?", $donationBatchId);
				}
				$errorText = "";
				if (!empty($alreadyClosed)) {
					$batchList = "";
					foreach ($alreadyClosed as $batchNumber) {
						$batchList .= (empty($batchList) ? "" : ",") . $batchNumber;
					}
					$errorText = "Batch(es) already closed: " . $batchList;
				}
				if (!empty($wrongTotals)) {
					$batchList = "";
					foreach ($wrongTotals as $batchNumber) {
						$batchList .= (empty($batchList) ? "" : ",") . $batchNumber;
					}
					$errorText = (empty($errorText) ? "" : "<br>") . "Batch(es) don't match: " . $batchList;
				}
				$returnArray['error_message'] = $errorText;
				ajaxResponse($returnArray);
				break;
			case "reopen_batches":
				$donationBatchIds = array();
				$resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$donationBatchIds[] = $row['primary_identifier'];
				}
				$notClosed = array();
				$alreadyPosted = array();
				foreach ($donationBatchIds as $donationBatchId) {
					$resultSet = executeQuery("select batch_number,date_completed,date_posted from " .
						"donation_batches where client_id = ? and donation_batch_id = ?", $GLOBALS['gClientId'], $donationBatchId);
					if ($row = getNextRow($resultSet)) {
						$batchNumber = $row['batch_number'];
						$dateCompleted = $row['date_completed'];
						$datePosted = $row['date_posted'];
					} else {
						continue;
					}
					if (empty($dateCompleted)) {
						$notClosed[] = $batchNumber;
						continue;
					}
					if (!empty($datePosted)) {
						$alreadyPosted[] = $batchNumber;
						continue;
					}
					executeQuery("update donation_batches set date_completed = null where donation_batch_id = ?", $donationBatchId);
				}
				$errorText = "";
				if (!empty($notClosed)) {
					$batchList = "";
					foreach ($notClosed as $batchNumber) {
						$batchList .= (empty($batchList) ? "" : ",") . $batchNumber;
					}
					$errorText = "Batch(es) not closed: " . $batchList;
				}
				if (!empty($alreadyPosted)) {
					$batchList = "";
					foreach ($alreadyPosted as $batchNumber) {
						$batchList .= (empty($batchList) ? "" : ",") . $batchNumber;
					}
					$errorText = (empty($errorText) ? "" : "<br>") . "Batch(es) already posted: " . $batchList;
				}
				$returnArray['error_message'] = $errorText;
				ajaxResponse($returnArray);
				break;
			case "get_report":
				$returnArray['report_title'] = "Donations for Batch #" . getFieldFromId("batch_number", "donation_batches", "donation_batch_id", $_GET['donation_batch_id']);
				switch ($_GET['report_type']) {
					case "contact_id":
						$orderBy = "contacts.contact_id,donation_id";
						break;
					case "first_name":
						$orderBy = "first_name,last_name,donation_id";
						break;
					case "last_name":
						$orderBy = "last_name,first_name,donation_id";
						break;
					default:
						$orderBy = "donation_id";
				}
				$resultSet = executeQuery("select sum(amount) from donations where donation_batch_id = ? and client_id = ?", $_GET['donation_batch_id'], $GLOBALS['gClientId']);
				$totalDonations = 0;
				if ($row = getNextRow($resultSet)) {
					if (!empty($row['sum(amount)'])) {
						$totalDonations = $row['sum(amount)'];
					}
				}
				$resultSet = executeQuery("select * from donations join contacts using (contact_id) where " .
					"donation_batch_id = ? and contacts.client_id = ? order by " . $orderBy, $_GET['donation_batch_id'], $GLOBALS['gClientId']);
				$donationCount = (empty($resultSet['row_count']) ? 0 : $resultSet['row_count']);
				$donationRows = array();
				$receiptedOther = false;
				while ($row = getNextRow($resultSet)) {
					if (!empty($row['receipted_contact_id'])) {
						$receiptedOther = true;
					}
					$donationRows[] = $row;
				}
				ob_start();
				?>
                <table class="grid-table">
                    <tr>
                        <td colspan="6" class="size-16-point"><?= $donationCount ?> donation<?= ($donationCount == 1 ? "" : "s") ?> totalling $<?= number_format($totalDonations, 2) ?></td>
                    </tr>
                    <tr>
                        <th>Donation ID</th>
                        <th>Partner ID</th>
						<?php if ($receiptedOther) { ?>
                            <th>Receipted ID</th>
						<?php } ?>
                        <th>Donor Name</th>
                        <th class="align-right">Amount</th>
                        <th>Ref #</th>
                        <th>Designation</th>
                    </tr>
					<?php
					foreach ($donationRows as $row) {
						?>
                        <tr>
                            <td><a href="/donationmaintenance.php?id=<?= $row['donation_id'] ?>"><?= $row['donation_id'] ?></td>
                            <td><?= $row['contact_id'] ?></td>
							<?php if ($receiptedOther) { ?>
                                <td><?= $row['receipted_contact_id'] ?></td>
							<?php } ?>
                            <td><?= getDisplayName($row['contact_id']) ?></td>
                            <td class="align-right"><?= number_format($row['amount'], 2) ?></td>
                            <td><?= htmlText($row['reference_number']) ?></td>
                            <td><?= getFieldFromId("designation_code", "designations", "designation_id", $row['designation_id']) ?> - <?= getFieldFromId("description", "designations", "designation_id", $row['designation_id']) ?><?= ($row['anonymous_gift'] ? " (Anonymous)" : "") ?></td>
                        </tr>
						<?php
					}
					?>
                </table>
				<?php
				$returnArray['report_content'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
		}
	}

	function saveChanges() {
		if (empty($_POST['primary_id'])) {
			$batchNumber = 0;
			while (true) {
				$batchNumber = 1000;
				$resultSet = executeQuery("select max(batch_number) from donation_batches where client_id = ?", $GLOBALS['gClientId']);
				if ($row = getNextRow($resultSet)) {
					if (!empty($row['max(batch_number)'])) {
						$batchNumber = $row['max(batch_number)'] + 1;
					}
				}
				$resultSet = executeQuery("insert into donation_batches (client_id,batch_number,batch_date,donation_count,total_donations," .
					"designation_id,donation_source_id,payment_method_id) values (?,?,?,?,?,?,?,?)",
					$GLOBALS['gClientId'], $batchNumber, makeDateParameter($_POST['batch_date']), $_POST['donation_count'],
					$_POST['total_donations'], $_POST['designation_id'], $_POST['donation_source_id'], $_POST['payment_method_id']);
				if ($resultSet['sql_error_number'] != 1062) {
					$_POST['primary_id'] = $resultSet['insert_id'];
					setCoreCookie("donation_batch_id", $_POST['primary_id'], 24);
					$_COOKIE['donation_batch_id'] = $_POST['primary_id'];
					break;
				}
			}
			echo jsonEncode(array("info_message" => "Batch Created"));
			exit;
		}
	}

	function massageDonationEntry(&$saveDataArray) {
		$saveDataArray['donation_date'] = $_POST['batch_date'];
		if (empty($saveDataArray['donation_id'])) {
			$saveDataArray['donation_commitment_id'] = Donations::getContactDonationCommitment($saveDataArray['contact_id'], $saveDataArray['designation_id'], $saveDataArray['donation_source_id']);
		}
		return true;
	}

	function afterGetRecord(&$returnArray) {
		if (empty($returnArray['primary_id']['data_value'])) {
			$returnArray['batch_number'] = array("data_value" => "Assigned After Save");
		} else {
			setCoreCookie("donation_batch_id", $returnArray['primary_id']['data_value'], 24);
			$_COOKIE['donation_batch_id'] = $returnArray['primary_id']['data_value'];
		}
		$returnArray['report_type'] = array("data_value" => "");
		if ($returnArray['primary_id']['data_value']) {
			$resultSet = executeQuery("select count(*) from donations where donation_batch_id = ?", $returnArray['primary_id']['data_value']);
			if ($row = getNextRow($resultSet)) {
				$returnArray['actual_donation_count'] = array("data_value" => $row['count(*)']);
			} else {
				$returnArray['actual_donation_count'] = array("data_value" => "0");
			}
		} else {
			$returnArray['actual_donation_count'] = array("data_value" => "0");
		}
	}

	function afterSaveDone($nameValues) {
		executeQuery("update donation_batches set user_id = ? where donation_batch_id = ? and user_id is null and donation_batch_id in " .
			"(select donation_batch_id from donations where donation_batch_id = ?)", $GLOBALS['gUserId'], $nameValues['primary_id'], $nameValues['primary_id']);
		setCoreCookie("donation_batch_id", $nameValues['primary_id'], 24);
		$_COOKIE['donation_batch_id'] = $nameValues['primary_id'];
		return true;
	}

	function reportTypeChoices() {
		$reportTypeChoices = array();
		$reportTypeChoices["donation_id"] = array("key_value" => "donation_id", "description" => "Donation Receipt ID", "inactive" => false);
		$reportTypeChoices["contact_id"] = array("key_value" => "contact_id", "description" => "Donor ID", "inactive" => false);
		$reportTypeChoices["first_name"] = array("key_value" => "first_name", "description" => "Donor First Name", "inactive" => false);
		$reportTypeChoices["last_name"] = array("key_value" => "last_name", "description" => "Donor Last Name", "inactive" => false);
		return $reportTypeChoices;
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", ".contact-note", function () {
                $("#_contact_notes").html($(this).data("contact_notes"));
                $('#_contact_notes_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    title: 'Contact Notes',
                    buttons: {
                        Close: function (event) {
                            $("#_contact_notes_dialog").dialog('close');
                        }
                    }
                });
            });
            $(".editable-list-add").click(function () {
                if ($(this).data("list_identifier") === "donations_entry") {
                    $('html, body').animate({
                        scrollTop: $(this).offset().top - ($(window).height() - $(this).outerHeight(true)) / 2
                    }, 200);
                }
            });
            $(document).on("blur", ".designation-id,.contact-picker-selector", function () {
                $("#designation_message").html("");
                const designationId = $(this).closest("tr").find("input[type=hidden].designation-id").val();
                const contactId = $(this).closest("tr").find(".contact-picker-selector").val();
                if (!empty(designationId) && !empty(contactId)) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=check_donation&contact_id=" + contactId + "&designation_id=" + designationId, function (returnArray) {
                        if ("designation_message" in returnArray) {
                            $("#designation_message").html(returnArray['designation_message']);
                            $("#_designation_message_row").show();
                        }
                    });
                }
            });
            $("#report_type").change(function () {
                $(this).data("last_value", $(this).val());
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_report&report_type=" + $(this).val() + "&donation_batch_id=" + $("#primary_id").val(), function (returnArray) {
                    if ("report_content" in returnArray) {
                        $("#_report_content").html(returnArray['report_content']);
                        $("#_report_title").html(returnArray['report_title']);
                    }
                });
            });
            $(document).on("tap click", "#printable_button", function () {
                window.open("/printable.html");
                return false;
            });
            $(document).on("tap click", "#pdf_button", function () {
                $("#_pdf_form").html("");
                let input = $("<input>").attr("type", "hidden").attr("name", "report_title").val($("#_report_title").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "report_content").val($("#_report_content").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "printable_style").val($("#_printable_style").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "filename").val("donationbatch_" + $("#batch_number").val() + ".pdf");
                $('#_pdf_form').append($(input));
                $("#_pdf_form").attr("action", "/reportpdf.php").attr("method", "POST").submit();
                return false;
            });
            $(document).on("tap click", "#donations_button", function () {
                if (!empty($("#date_completed").val())) {
                    $("#_edit_message").html("This batch is already closed.");
                } else {
                    window.open("/donationmaintenance.php?donation_batch_id=" + $("#primary_id").val() + ($("#donation_count").val() === "0" ? "&url_page=new" : ""));
                }
                return false;
            });
            $(document).on("change", ".contact-picker-value", function () {
                const rowNumber = $(this).closest(".editable-list-data-row").data("row_id");
                $(this).closest(".editable-list-data-row").find(".contact-note").addClass("hidden").data("contact_notes", "");
                if (!empty($("#donations_entry_contact_id-" + rowNumber).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=contact_notes&contact_id=" + $("#donations_entry_contact_id-" + rowNumber).val() + "&row_number=" + rowNumber, function (returnArray) {
                        if ("row_number" in returnArray && !empty(returnArray['contact_notes'])) {
                            $("#_donations_entry_row-" + returnArray['row_number']).find(".contact-note").removeClass("hidden").data("contact_notes", returnArray['contact_notes']);
                        }
                    });
                }
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function afterAddEditableRow(listName, rowNumber, rowData) {
                if (listName === "donations_entry") {
                    if (empty($("#donations_entry_contact_id-" + rowNumber).val())) {
                        $("#donations_entry_payment_method_id-" + rowNumber).val($("#payment_method_id").val());
                        $("#donations_entry_designation_id-" + rowNumber).val($("#designation_id").val());
                        $("#donations_entry_designation_id-" + rowNumber + "_autocomplete_text").val($("#designation_id_autocomplete_text").val());
                    }
                    if ($("#_donations_entry_row-" + rowNumber).find(".contact-note").length === 0) {
                        $("#_donations_entry_row-" + rowNumber).find(".contact-picker").after("<span class='hidden contact-note far fa-comment-alt'></span>");
                    }
                    $("#_donations_entry_row-" + rowNumber).find(".contact-picker-selector").trigger("change");
                }
            }
            function customActions(actionName) {
                if (actionName === "close_batches" || actionName === "post_batches" || actionName === "reopen_batches") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?url_action=" + actionName, function (returnArray) {
                        if ("info_message" in returnArray) {
                            displayInfoMessage(returnArray['info_message']);
                            setTimeout(function () {
                                getDataList();
                            }, 3000);
                        } else {
                            getDataList();
                        }
                    });
                    return true;
                }
                return false;
            }

            function afterGetRecord() {
                $("#report_type").val($("#report_type").data("last_value"));
                if (empty($("#report_type").val())) {
                    $("#report_type").val("donation_id");
                }
                if (empty($("#primary_id").val())) {
                    $("#donations_section").hide();
                } else {
                    $("#report_type").trigger("change");
                    $("#donations_section").show();
                }
                $("#_edit_message").html("");
                $("#batch_date").prop("readonly", !empty($("#date_completed").val()));
                $("#donation_count").prop("readonly", !empty($("#date_completed").val()));
                $("#total_donations").prop("readonly", !empty($("#date_completed").val()));
                $("#designation_id_autocomplete_text").prop("readonly", !empty($("#date_completed").val()));
                $("#payment_method_id").prop("disabled", !empty($("#date_completed").val()));
                if (empty($("#date_completed").val())) {
                    $("#donations_entry_section").show();
                } else {
                    $("#donations_entry_section").hide();
                }
            }
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #_report_type_row {
                display: inline-block;
            }

            #_printable_button_row {
                display: inline-block;
                margin-left: 40px;
            }

            #_pdf_button_row {
                display: inline-block;
                margin-left: 40px;
            }

            #_report_title {
                display: none;
            }

            #_edit_message {
                padding-left: 40px;
                color: rgb(192, 0, 0);
                font-size: 14px;
                font-weight: bold;
            }

            h2 {
                margin-top: 20px;
            }

            #designation_message {
                font-size: 16px;
                color: rgb(192, 0, 0);
                font-weight: bold;
            }

            .contact-note {
                font-size: 1.2rem;
                cursor: pointer;
            }

            .contact-note:hover {
                color: rgb(50, 60, 66);
            }

            #_donations_entry_table {
                width: 100%;
            }

            #_donations_entry_table td {
                white-space: nowrap;
            }

            #_maintenance_form .editable-select.designation-id {
                width: 100%;
                max-width: 400px;
                min-width: 150px;
            }
        </style>
		<?php
	}

	function hiddenElements() {
		?>
        <div id="_pdf_data" class="hidden">
            <form id="_pdf_form">
            </form>
        </div>

        <div id="_contact_notes_dialog" class="dialog-box">
            <div id="_contact_notes">
            </div>
        </div>

		<?php
	}
}

$pageObject = new DonationBatchMaintenance("donation_batches");
$pageObject->displayPage();
