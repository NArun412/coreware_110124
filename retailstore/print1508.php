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

$GLOBALS['gPageCode'] = "RETAILSTOREPRINT1508";
$GLOBALS['gCacheProhibited'] = true;
$GLOBALS['gProxyPageCode'] = "ORDERDASHBOARD";
require_once "shared/startup.inc";

class Print1508 extends Page {

    var $iOrderId = "";

    function setup() {
        $this->iOrderId = getFieldFromId("order_id", "orders", "order_id", $_GET['order_id']);
        if (empty($_GET['ajax']) && empty($this->iOrderId)) {
            header("Location: /");
            exit;
        }
    }

    function mainContent() {
        echo $this->iPageData['content'];
        $orderRow = getRowFromId("orders", "order_id", $this->iOrderId);
        if (empty($orderRow['federal_firearms_licensee_id'])) {
            $fflRow = array();
        } else {
	        $fflRow = (new FFL($orderRow['federal_firearms_licensee_id']))->getFFLRow();
            $addressPrefix = (empty($fflRow['mailing_address_preferred']) || empty($fflRow['mailing_address_1']) ? "" : "mailing_");
        }

        ob_start();
        ?>
        <div id="city_block"><?= $GLOBALS['gClientRow']['city'] . ", " . $GLOBALS['gClientRow']['state'] . " " . $GLOBALS['gClientRow']['postal_code'] ?></div>
        <div id="date_block"><?= date("m/d/Y") ?></div>
        <div id="from_block"><?= $GLOBALS['gClientName'] . "<br>" . $GLOBALS['gClientRow']['address_1'] . "<br>" . $GLOBALS['gClientRow']['city'] . ", " . $GLOBALS['gClientRow']['state'] . " " . $GLOBALS['gClientRow']['postal_code'] ?></div>
        <div id="to_block"><?= (empty($fflRow['business_name']) ? $fflRow['licensee_name'] : $fflRow['business_name']) . "<br>" . $fflRow[$addressPrefix . 'address_1'] . "<br>" .
            (empty($fflRow[$addressPrefix . 'address_2']) ? "" : $fflRow[$addressPrefix . 'address_2'] . "<br>") . $fflRow[$addressPrefix . 'city'] . ", " . $fflRow[$addressPrefix . 'state'] . " " .
            $fflRow[$addressPrefix . 'postal_code'] ?></div>
        <div id="firm_block"><?= (empty($GLOBALS['gClientRow']['alternate_name']) ? $GLOBALS['gClientName'] : $GLOBALS['gClientRow']['alternate_name']) ?></div>
        <div id="signature_block"><img src="/getimage.php?code=signature_1508">&nbsp;<?= $GLOBALS['gClientRow']['job_title'] ?></div>
        <?php
        $formContent = ob_get_clean();
        ?>
        <div id="_button_row">
            <button id="printable_button">Printable 1508</button>
            <button id="pdf_button">Download PDF</button>
        </div>
        <h1 id="_report_title"></h1>
        <div id="_report_content">
            <img id="form_image" src="https://images.coreware.com/getimage.php?code=ps1508">
            <?php
            echo $formContent;
            ?>
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
            <?php if (!empty($_GET['printable'])) { ?>
            setTimeout(function () {
                $("#printable_button").trigger("click");
            }, 1000);
            <?php } ?>
            $(document).on("tap click", "#pdf_button", function () {
                $("#_pdf_form").html("");
                let input = $("<input>").attr("type", "hidden").attr("name", "report_title").val($("#_report_title").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "report_content").val($("#_report_content").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "printable_style").val($("#_printable_style").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "filename").val("form1508.pdf");
                $('#_pdf_form').append($(input));
                $("#_pdf_form").attr("action", "/reportpdf.php").attr("method", "POST").submit();
                return false;
            });
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
        </style>
        <style id="_printable_style">
            p {
                margin-bottom: 20px;
            }

            #_report_content {
                position: relative;
                padding: 0 20px;
            }

            #city_block {
                position: absolute;
                top: 18.6%;
                left: 20px;
                font-size: 1rem;
            }

            #date_block {
                position: absolute;
                top: 18.6%;
                left: 72.8%;
                font-size: 1rem;
            }

            #to_block {
                position: absolute;
                top: 55.2%;
                left: 20px;
                font-size: 1rem;
            }

            #from_block {
                position: absolute;
                top: 55.2%;
                left: 60%;
                font-size: 1rem;
            }

            #firm_block {
                position: absolute;
                top: 81.9%;
                left: 20px;
                font-size: 1rem;
            }

            #signature_block {
                position: absolute;
                top: 87.2%;
                left: 20px;
                height: 4.9%;
                width: 38%;
                font-size: 1rem;
            }

            #signature_block img {
                margin-right: 20px;
                max-height: 100%;
            }

            #form_image {
                width: 100%;
            }

            #_report_title {
                margin: 0;
            }

        </style>
        <?php
    }
}

$pageObject = new Print1508();
$pageObject->displayPage();
