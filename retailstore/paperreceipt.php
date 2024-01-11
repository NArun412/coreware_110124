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

$GLOBALS['gPageCode'] = "RETAILSTOREPAPERRECEIPT";
$GLOBALS['gCacheProhibited'] = true;
require_once "shared/startup.inc";

class RetailStorePaperReceiptPage extends Page {

	var $iOrderId = "";

	function setup() {
		if ($GLOBALS['gLoggedIn'] && !empty($_GET['order_id'])) {
			$this->iOrderId = getFieldFromId("order_id", "orders", "order_id", $_GET['order_id'], ($GLOBALS['gInternalConnection'] ? "" : "contact_id = " . $GLOBALS['gUserRow']['contact_id']));
		}
		if (empty($this->iOrderId) && $GLOBALS['gLoggedIn'] && !empty($_GET['order_id'])) {
			$this->iOrderId = getFieldFromId("order_id", "orders", "order_id", base64_decode($_GET['order_id']), ($GLOBALS['gInternalConnection'] ? "" : "contact_id = " . $GLOBALS['gUserRow']['contact_id']));
		}
		if (empty($_GET['ajax']) && empty($this->iOrderId)) {
			header("Location: /");
			exit;
		}
	}

	function mainContent() {
		echo $this->iPageData['content'];
		$orderReceiptContent = Order::getPaperReceipt($this->iOrderId);
		?>
		<div id="_button_row">
			<button id="printable_button">Printable Receipt</button>
			<button id="pdf_button">Download PDF</button>
		</div>
		<h1 id="_report_title"></h1>
		<div id="_report_content">
			<?= $orderReceiptContent ?>
		</div>
		<div id="_pdf_data" class="hidden">
			<form id="_pdf_form">
			</form>
		</div>
		<?php
		echo $this->iPageData['after_form_content'];
		return true;
	}

	function onLoadJavascript() {
		?>
		<script>
            $(document).on("tap click", "#printable_button", function () {
                window.open("/printable.html");
                return false;
            });
            $(document).on("tap click", "#pdf_button", function () {
                const $pdfForm = $("#_pdf_form");
                $pdfForm.html("");
                let input = $("<input>").attr("type", "hidden").attr("name", "report_title").val($("#_report_title").html());
                $pdfForm.append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "report_content").val($("#_report_content").html());
                $pdfForm.append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "printable_style").val($("#_printable_style").html());
                $pdfForm.append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "filename").val("orderreceipt.pdf");
                $pdfForm.append($(input));
                $pdfForm.attr("action", "/reportpdf.php").attr("method", "POST").submit();
                return false;
            });
			<?php if (!empty($_GET['printable'])) { ?>
            $("#printable_button").trigger("click");
			<?php } ?>
		</script>
		<?php
	}

	function internalCSS() {
		?>
		<style>
			#report_parameters {
				width: 100%;
				margin-left: auto;
				margin-right: auto;
			}

			#_button_row {
				margin-bottom: 20px;
			}

			#_report_content ul {
				list-style-type: disc;
				margin-left: 40px
			}

			#_report_content li {
				list-style-type: disc;
			}
		</style>
		<style id="_printable_style">
            #_report_content {
                margin: 0;
                padding: 0;
                width: 250px;
            }

			.header-image {
				max-width: 150px;
				max-height: 40px;
			}

            #order_items_table th {
                font-size: .4rem;
                padding: 2px;
            }

            #order_items_table td {
                font-size: .4rem;
                padding: 2px;
            }

            .grid-table td.total-line {
				font-weight: bold;
				font-size: .4rem;
				border-top: 2px solid rgb(0, 0, 0);
				padding-top: 2px;
				padding-bottom: 2px;
			}

			.grid-table td.border-bottom {
				border-bottom: 2px solid rgb(0, 0, 0);
			}

			#address_section {
				margin-bottom: 20px;
			}

			#order_items_table {
				margin-bottom: 20px;
			}

			#_report_title {
				margin: 0;
			}
		</style>
		<?php
	}
}

$pageObject = new RetailStorePaperReceiptPage();
$pageObject->displayPage();
