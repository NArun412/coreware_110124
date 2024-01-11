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

$GLOBALS['gPageCode'] = "INVOICEHISTORY";
$GLOBALS['gForceSSL'] = true;
require_once "shared/startup.inc";

class InvoiceHistoryPage extends Page {

	var $iContactId = "";

	function setup() {
		if (empty($_GET['ajax'])) {
            if($GLOBALS['gLoggedIn']) {
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
	}

	function executePageUrlActions() {
        switch ($_GET['url_action']) {
			case "get_invoice_details":
                $returnArray = Invoices::getInvoiceDetails(array("invoice_id" => $_GET['invoice_id']));
				ajaxResponse($returnArray);
				break;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", ".invoice-details", function () {
                const invoiceId = $(this).closest("tr").data("invoice_id");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_invoice_details&contact_id=<?= $this->iContactId ?>&invoice_id=" + invoiceId, function(returnArray) {
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
        </script>
		<?php
	}

	function mainContent() {
		echo $this->iPageData['content'];
		?>
        <div id="form_wrapper">
            <form id="_edit_form">
                <h2>Invoice History</h2>
                <table class="grid-table" id="invoice_list">
                    <tr>
                        <th class="invoice-number-header">Invoice #</th>
                        <th class="invoice-date-header">Invoice Date</th>
                        <th class="due-date-header">Due Date</th>
                        <th class="date-completed-header">Date Completed</th>
                        <th class="invoice-total-header">Invoice Total</th>
                        <th class="balance-due-header">Balance Due</th>
                        <th class="order-number-header">Order Number</th>
                    </tr>
					<?php

                    $returnArray = Invoices::getInvoices($this->iContactId);
                    $totalBalance = 0;

                    if (!empty($returnArray['invoices'])) {
                        $totalBalance = $returnArray['total_balance'];
                        foreach ($returnArray['invoices'] as $row) {
                            ?>
                            <tr class="invoice<?= ($row['overdue'] ? " overdue" : "") ?>" data-invoice_id="<?= $row['invoice_id'] ?>">
                                <td class="invoice-number-cell"><a href="#" class='invoice-details'><?= (empty($row['invoice_number']) ? $row['invoice_id'] : $row['invoice_number']) ?></a></td>
                                <td class="invoice-date-cell"><?= date("m/d/Y", strtotime($row['invoice_date'])) ?></td>
                                <td class="date-due-cell"><?= (empty($row['date_due']) ? "" : date("m/d/Y", strtotime($row['date_due']))) ?></td>
                                <td class="date-completed-cell"><?= (empty($row['date_completed']) ? "" : date("m/d/Y", strtotime($row['date_completed']))) ?></td>
                                <td class="invoice-total-cell align-right"><?= number_format($row['invoice_total'], 2, ".", ",") ?></td>
                                <td class="balance-due-cell align-right invoice-amount"><?= number_format($row['balance'], 2, ".", ",") ?></td>
                                <td class="order-number-cell"><?= (empty($row['order_id']) ? "" : "<a href='/order-receipt?order_id=" . $row['order_id'] . "'>" . $row['order_id'] . "</a>") ?></td>
                            </tr>
                            <?php
                        }
						?>
                        <tr>
                            <td id="totals_title" colspan="5" class="highlighted-text">Total Outstanding Invoices</td>
                            <td class="align-right highlighted-text"><?= number_format($returnArray['total_balance'], 2) ?></td>
                            <td></td>
                        </tr>
						<?php
					} else {
						?>
                        <tr>
                            <td colspan="7">No invoices found</td>
                        </tr>
						<?php
					}
					?>
                </table>
            </form>
            <?php
            if ($totalBalance > 0 && getFieldFromId("page_id", "pages", "link_name",
                    "invoice-payments", "client_id = ?", $GLOBALS['gClientId'])) {
                echo "<div class='form-line payment-line'>To make a payment, go to <a id='invoice_payments_link'  href='/invoice-payments'>Invoice Payments</a>.</div>";
            }
            ?>
        </div> <!-- form_wrapper -->
		<?php
		echo $this->iPageData['after_form_content'];
		return true;
	}

	function internalCSS() {
		?>
        <style>
            #form_wrapper {
                padding: 20px;
                width: 80%;
                max-width: 1200px;
                margin: 0 auto;
            }

            .overdue {
                background-color: rgb(255, 208, 208);
                color: rgb(192, 0, 0);
            }

            .payment-line {
                padding: 20px 0px;
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

$pageObject = new InvoiceHistoryPage();
$pageObject->displayPage();
