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

$GLOBALS['gPageCode'] = "VIEWDONATIONRECEIPT";
$GLOBALS['gCacheProhibited'] = true;
$GLOBALS['gProxyPageCode'] = "DONATIONMAINT";
require_once "shared/startup.inc";

class ViewDonationReceiptPage extends Page {

	var $iDonationId = "";

	function setup() {
		if ($GLOBALS['gLoggedIn'] && !empty($_GET['donation_id'])) {
			$this->iDonationId = getFieldFromId("donation_id", "donations", "donation_id", $_GET['donation_id']);
		}
		if (empty($_GET['ajax']) && empty($this->iDonationId)) {
			header("Location: /");
			exit;
		}
	}

	function mainContent() {
		echo $this->iPageData['content'];
		$receiptInfo = Donations::processDonationReceipt($this->iDonationId, array("substitutions_only" => true));
		if (empty($receiptInfo['email_id'])) {
			?>
            <p class='error-message'>No Email Receipt found</p>
			<?php
			return true;
		}
		$emailContent = getFieldFromId("content", "emails", "email_id", $receiptInfo['email_id']);
		foreach ($receiptInfo['substitutions'] as $fieldName => $fieldValue) {
			$emailContent = str_replace("%" . $fieldName . "%", (is_scalar($fieldValue) ? $fieldValue : ""), $emailContent);
		}
		?>
        <div id="_button_row">
            <button id="printable_button">Printable Receipt</button>
            <button id="pdf_button">Download PDF</button>
        </div>
        <h1 id="_report_title"></h1>
        <div id="_report_content">
			<?= $emailContent ?>
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
                input = $("<input>").attr("type", "hidden").attr("name", "filename").val("donationreceipt.pdf");
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
		<?php
	}
}

$pageObject = new ViewDonationReceiptPage();
$pageObject->displayPage();
