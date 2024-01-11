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

$GLOBALS['gPageCode'] = "INVOICEPAYMENTS";
$GLOBALS['gForceSSL'] = true;
require_once "shared/startup.inc";

class InvoicePaymentPage extends Page {

	var $iContactId = "";
	var $iContactRow = array();

	function setup() {
		if (empty($_GET['ajax'])) {
			if ($GLOBALS['gLoggedIn']) {
				$this->iContactId = $GLOBALS['gUserRow']['contact_id'];
			} else {
				$this->iContactId = getFieldFromId("contact_id", "contacts", "hash_code", $_GET['code']);
			}
			if (function_exists("_localServerImportInvoices")) {
				_localServerImportInvoices($this->iContactId);
			}
		} else {
			$this->iContactId = getFieldFromId("contact_id", "contacts", "contact_id", $_GET['contact_id']);
		}
		if (empty($this->iContactId)) {
			header("Location: /");
			exit;
		}
		if ($GLOBALS['gLoggedIn']) {
			$this->iContactRow = $GLOBALS['gUserRow'];
		} else {
			$resultSet = executeQuery("select * from contacts left outer join users using (contact_id) where contacts.contact_id = ?", $this->iContactId);
			if ($row = getNextRow($resultSet)) {
				$this->iContactRow = $row;
			}
		}
	}

	function executePageUrlActions() {
		switch ($_GET['url_action']) {
			case "get_invoice_details":
                $returnArray = Invoices::getInvoiceDetails(array("invoice_id" => $_GET['invoice_id']));
                ajaxResponse($returnArray);
                break;
			case "create_payment":
                $returnArray = Invoices::createInvoicePayment(array("contact_row" => $this->iContactRow, "contact_id" => $this->iContactId,
                    "invoice_payment_received_template" => $this->getFragment("INVOICE_PAYMENT_RECEIVED"), "database" => $this->iDatabase));
                ajaxResponse($returnArray);
                break;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", "#submit_form", function () {
                if (parseFloat($("#amount").val()) <= 0) {
                    displayErrorMessage("Select one or more invoices to pay");
                    return;
                }
                if ($("#_edit_form").validationEngine("validate")) {
                    $("#submit_paragraph").addClass("hidden");
                    $("#processing_payment").removeClass("hidden");
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_payment&contact_id=<?= $this->iContactId ?>", $("#_edit_form").serialize(), function (returnArray) {
                        if ("error_message" in returnArray) {
                            $("#submit_paragraph").removeClass("hidden");
                            $("#processing_payment").addClass("hidden");
                            return;
                        }
                        if ("response" in returnArray) {
                            $("#payment_wrapper").html(returnArray['response']);
                        } else {
                            $("#submit_paragraph").removeClass("hidden");
                            $("#processing_payment").addClass("hidden");
                        }
                    });
                }
                return false;
            });
            $("#billing_country_id").change(function () {
                if ($(this).val() === "1000") {
                    $("#_billing_state_row").hide();
                    $("#_billing_state_select_row").show();
                } else {
                    $("#_billing_state_row").show();
                    $("#_billing_state_select_row").hide();
                }
            }).trigger("change");
            $("#billing_state_select").change(function () {
                $("#billing_state").val($(this).val());
            });
            $(document).on("click", "#same_address", function () {
                if ($(this).prop("checked")) {
                    $("#_billing_address").addClass("hidden");
                    $("#_billing_address").find("input,select").val("");
                } else {
                    $("#_billing_address").removeClass("hidden");
                }
            });
            $("#account_id").change(function () {
                if ($(this).val() !== "") {
                    $("#_new_account").hide();
                } else {
                    $("#_new_account").show();
                }
                calculateAmount();
            });
            $("#payment_method_id").change(function () {
                $(".payment-method-fields").hide();
                if ($(this).val() !== "") {
                    const paymentMethodTypeCode = $(this).find("option:selected").data("payment_method_type_code");
                    $("#payment_method_" + paymentMethodTypeCode.toLowerCase()).show();
                }
                calculateAmount();
            }).trigger("change");
            $(document).on("click", ".pay-now", function () {
                calculateAmount();
            });
            $(document).on("click", "#select_all", function () {
                $(".pay-now").prop("checked", true);
                calculateAmount();
                return false;
            });
            $(document).on("click", ".invoice-details", function () {
                const invoiceId = $(this).closest("tr").data("invoice_id");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_invoice_details&contact_id=<?= $this->iContactId ?>&invoice_id=" + invoiceId, function (returnArray) {
                    if ("invoice_details" in returnArray) {
                        $("#_invoice_details_dialog").html(returnArray['invoice_details']);
                        $('#_invoice_details_dialog').dialog({
                            closeOnEscape: true,
                            draggable: false,
                            modal: true,
                            resizable: false,
                            position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                            width: 700,
                            title: 'Invoice Details',
                            buttons: {
                                Print: function (event) {
                                    document.location = "/printinvoice.php?invoice_id=" + $("#_invoice_header").data("invoice_id");
                                },
                                Close: function (event) {
                                    $("#_invoice_details_dialog").dialog('close');
                                }
                            }
                        });
                    }
                });
                return false;
            });
            $(document).on("change", ".pay-amount", function () {
                if (empty($(this).val())) {
                    $(this).closest("tr").find(".pay-now").prop("checked", false);
                } else {
                    $(this).closest("tr").find(".pay-now").prop("checked", true);
                }
                calculateAmount();
            });
            $(document).on("click", "#full_amount", function () {
                calculateAmount();
            });
            calculateAmount();
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            let selectedInvoicesTotal = 0;

            function calculateAmount() {
                let totalAmount = 0;
                const fullAmount = $("#full_amount").prop("checked");
                if (fullAmount) {
                    $(".custom-amount").addClass("hidden");
                    $("#totals_title").attr("colspan", "5");
                    $(".pay-amount").prop("readonly", true);
                    $("#invoice_list").find(".invoice-amount").each(function () {
                        if ($(this).closest("tr").find(".pay-now").prop("checked")) {
                            let invoiceTotal = parseFloat($(this).html().replace(/,/g, ""));
                            totalAmount = Round(totalAmount + invoiceTotal, 2);
                            $(this).closest("tr").find(".pay-amount").val(invoiceTotal);
                        } else {
                            $(this).closest("tr").find(".pay-amount").val("");
                        }
                    });
                } else {
                    $(".custom-amount").removeClass("hidden");
                    $("#totals_title").attr("colspan", "6");
                    $(".pay-amount").prop("readonly", false);
                    $("#invoice_list").find(".pay-amount").each(function () {
                        if ($(this).closest("tr").find(".pay-now").prop("checked")) {
                            if (empty($(this).val())) {
                                let invoiceTotal = parseFloat($(this).closest("tr").find(".invoice-amount").html().replace(/,/g, ""));
                                $(this).val(invoiceTotal);
                            }
                            totalAmount = Round(totalAmount + parseFloat($(this).val().replace(/,/g, "")), 2);
                        } else {
                            $(this).val("");
                        }
                    });
                }
                selectedInvoicesTotal = totalAmount;
                $("#amount").val(RoundFixed(totalAmount, 2));
                let feeAmount = 0;
                if (empty($("#account_id").val())) {
                    const flatRate = $("#payment_method_id").find("option:selected").data("flat_rate");
                    if (!empty(flatRate)) {
                        feeAmount += flatRate;
                    }
                    const feePercent = $("#payment_method_id").find("option:selected").data("fee_percent");
                    if (!empty(feePercent)) {
                        feeAmount += totalAmount * feePercent / 100;
                    }
                } else {
                    const flatRate = $("#account_id").find("option:selected").data("flat_rate");
                    if (!empty(flatRate)) {
                        feeAmount += flatRate;
                    }
                    const feePercent = $("#account_id").find("option:selected").data("fee_percent");
                    if (!empty(feePercent)) {
                        feeAmount += totalAmount * feePercent / 100;
                    }
                }
                $("#fee_amount").val(RoundFixed(feeAmount, 2));
                $("#total_charge").val(RoundFixed(feeAmount + totalAmount, 2));
                if (empty(feeAmount)) {
                    $("#_fee_amount_row").addClass("hidden");
                    $("#_total_charge_row").addClass("hidden");
                } else {
                    $("#_fee_amount_row").removeClass("hidden");
                    $("#_total_charge_row").removeClass("hidden");
                }
            }
        </script>
		<?php
	}

	function mainContent() {
		echo $this->iPageData['content'];
		?>
        <div id="payment_wrapper">
            <?= Invoices::getPayInvoicesForm(array("contact_row" => $this->iContactRow, "payment_text" => $this->getPageTextChunk("payment_text"))); ?>
        </div>
		<?php
		echo $this->iPageData['after_form_content'];
		return true;
	}

	function internalCSS() {
		?>
        <style>
            #payment_wrapper {
                padding: 20px;
                width: 80%;
                max-width: 1200px;
                margin: 0 auto;
            }

            .overdue {
                background-color: rgb(255, 208, 208);
                color: rgb(192, 0, 0);
            }
        </style>
		<?php
	}

	function hiddenElements() {
		?>
        <div id="_invoice_details_dialog" class="dialog-box">
        </div>
		<?php
	}
}

$pageObject = new InvoicePaymentPage();
$pageObject->displayPage();
