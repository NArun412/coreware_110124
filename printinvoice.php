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

$GLOBALS['gPageCode'] = "PRINTINVOICE";
//$GLOBALS['gProxyPageCode'] = "INVOICEMAINT";
require_once "shared/startup.inc";

$pageObject = new Page();
if(canAccessPageCode("INVOICEMAINT")) {
    $clientWhere = "client_id = " . $GLOBALS['gClientId'];
} else {
    $clientWhere = "contact_id = " . (empty($GLOBALS['gUserRow']['contact_id']) ? "0" : $GLOBALS['gUserRow']['contact_id']);
}
$invoiceId = getFieldFromId("invoice_id", "invoices", "invoice_id", $_GET['invoice_id'],
    "inactive = 0 and " . ($GLOBALS['gInternalConnection'] ? "" : "internal_use_only = 0 and ") . $clientWhere);
if (empty($invoiceId)) {
	echo "Invoice Not Found";
	exit;
}
$resultSet = executeQuery("select * from invoices where invoice_id = ?", $invoiceId);
$invoiceRow = getNextRow($resultSet);
$contactId = getFieldFromId("contact_id", "clients", "client_id", $GLOBALS['gClientId']);
$resultSet = executeQuery("select * from contacts where contact_id = ?", $contactId);
if (!$contactRow = getNextRow($resultSet)) {
	$contactRow = array();
}
$clientAddress = $contactRow['business_name'];
if (empty($clientAddress)) {
	$clientAddress = getDisplayName($contactId);
}
if (!empty($contactRow['address_1'])) {
	$clientAddress .= "<br/>" . $contactRow['address_1'];
}
$cityLine = $contactRow['city'];
if (!empty($contactRow['state'])) {
	if (!empty($cityLine)) {
		$cityLine .= ", ";
	}
	$cityLine .= $contactRow['state'];
}
if ($contactRow['country_id'] == 1000) {
	if (!empty($contactRow['postal_code'])) {
		if (!empty($cityLine)) {
			$cityLine .= " ";
		}
		$cityLine .= $contactRow['postal_code'];
	}
}
if (!empty($cityLine)) {
	$clientAddress .= "<br/>" . $cityLine;
}
if ($contactRow['country_id'] != 1000) {
	$countryLine = getFieldFromId("country_name", "countries", "country_id", $contactRow['country_id']) . " " . $contactRow['postal_code'];
	if (!empty($countryLine)) {
		$clientAddress .= "<br/>" . $countryLine;
	}
}

$resultSet = executeQuery("select * from contacts where contact_id = ?", $invoiceRow['contact_id']);
if (!$contactRow = getNextRow($resultSet)) {
	$contactRow = array();
}
$contactAddress = $contactRow['business_name'];
if (empty($contactAddress)) {
	$contactAddress = getDisplayName($invoiceRow['contact_id']);
}
if (!empty($contactRow['address_1'])) {
	$contactAddress .= "<br/>" . $contactRow['address_1'];
}
$cityLine = $contactRow['city'];
if (!empty($contactRow['state'])) {
	if (!empty($cityLine)) {
		$cityLine .= ", ";
	}
	$cityLine .= $contactRow['state'];
}
if ($contactRow['country_id'] == 1000) {
	if (!empty($contactRow['postal_code'])) {
		if (!empty($cityLine)) {
			$cityLine .= " ";
		}
		$cityLine .= $contactRow['postal_code'];
	}
}
if (!empty($cityLine)) {
	$contactAddress .= "<br/>" . $cityLine;
}
if ($contactRow['country_id'] != 1000) {
	$countryLine = getFieldFromId("country_name", "countries", "country_id", $contactRow['country_id']) . " " . $contactRow['postal_code'];
	if (!empty($countryLine)) {
		$contactAddress .= "<br/>" . $countryLine;
	}
}
$headerText = $pageObject->getFragment("INVOICE_HEADER");
$footerText = $pageObject->getFragment("INVOICE_FOOTER");
$imageId = getFieldFromId("image_id", "images", "image_code", "invoice_header");
if (empty($imageId)) {
	$imageId = getFieldFromId("image_id", "images", "image_code", "header_logo");
}
ob_start();
?>
    <html>
    <head>
        <style>
            <?php
				echo file_get_contents($GLOBALS['gDocumentRoot'] . "/css/reset.css");
				echo file_get_contents($GLOBALS['gDocumentRoot'] . "/css/table_editor.css");
			?>
            #_outer {
                width: 740px;
                margin: 20px 40px;
            }

            #detail_section {
                min-height: 300px;
                background-color: rgb(240, 240, 240);
                padding-bottom: 10px;
                margin-bottom: 10px;
            }

            #header_logo {
                width: 300px;
            }

            #print_body {
                width: 700px;
            }

            p {
                font-size: 16px;
            }

            table {
                width: 700px;
            }

            hr {
                height: 2px;
                color: rgb(150, 150, 150);
                background-color: rgb(150, 150, 150);
            }

            td, th {
                font-size: 12px;
                padding: 5px;
                padding-top: 5px;
                padding-bottom: 5px;
            }

            tr.details-line td {
                border-bottom: 1px solid rgb(150, 150, 150);
            }

            #_notes_section {
                margin: 10px auto;
            }

            #_notes_section p {
                padding: 0;
                margin: 0;
                font-size: 11px;
            }

            ul {
                list-style: disc;
                margin-left: 20px;
            }

            li {
                list-style: disc;
                font-size: 10px;
            }
        </style>
    </head>
    <body>
    <div id="_outer">

        <div id="print_body">
			<?php if (!empty($imageId)) { ?>
                <p><img id='header_logo' src="<?= getImageFilename($imageId,array("use_cdn"=>true)) ?>"/></p>
			<?php } ?>
            <p><?= $clientAddress ?></p>
            <p>Invoice Number: <?= (empty($invoiceRow['invoice_number']) ? $invoiceRow['invoice_id'] : $invoiceRow['invoice_number']) ?></p>
            <p>Invoice Date: <?= date("m/d/Y", strtotime($invoiceRow['invoice_date'])) ?></p>
            <?php if (!empty($invoiceRow['purchase_order_number'])) { ?>
            <p>PO Number: <?= $invoiceRow['purchase_order_number'] ?></p>
            <?php } ?>
			<?php if (!empty($headerText)) { ?>
				<?= $headerText ?>
			<?php } ?>
            <hr>
            <p class="highlighted-text">Bill To:</p>
            <p><?= $contactAddress ?></p>
            <hr>
            <p class="highlighted-text">Invoice Details:</p>
            <div id="detail_section">
                <table>
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th class="align-center">Qty</th>
                        <th class="align-right">Price</th>
                        <th class="align-right">Extended</th>
                    </tr>
					<?php
					$invoiceTotal = 0;
					$resultSet = executeQuery("select * from invoice_details where invoice_id = ? order by detail_date,invoice_detail_id", $invoiceRow['invoice_id']);
					while ($row = getNextRow($resultSet)) {
						$invoiceTotal += $row['amount'] * $row['unit_price'];
						?>
                        <tr class="details-line">
                            <td><?= date("m/d/Y", strtotime($row['detail_date'])) ?></td>
                            <td><?= str_replace("\n", "<br>",htmlText($row['description'])) ?></td>
                            <td class="align-center"><?= showSignificant($row['amount']) ?></td>
                            <td class="align-right"><?= number_format($row['unit_price'], 2) ?></td>
                            <td class="align-right"><?= number_format($row['amount'] * $row['unit_price'], 2) ?></td>
                        </tr>
						<?php
                        if(!empty($row['detailed_description'])) {
                            ?>
                            <tr class="details-line">
                                <td></td>
                                <td colspan="5"><?= str_replace("\n", "<br>",$row['detailed_description']) ?></td>
                            </tr>
                            <?php
                        }
					}
					?>
					<?php
					$resultSet = executeQuery("select * from invoice_payments where invoice_id = ? order by payment_date,invoice_payment_id", $invoiceRow['invoice_id']);
					while ($row = getNextRow($resultSet)) {
						$invoiceTotal -= $row['amount'];
						?>
                        <tr class="details-line">
                            <td><?= date("m/d/Y", strtotime($row['payment_date'])) ?></td>
                            <td>Payment</td>
                            <td class="align-right"></td>
                            <td class="align-right red-text"><?= number_format($row['amount'], 2) ?></td>
                            <td class="align-right red-text"><?= number_format($row['amount'], 2) ?></td>
                        </tr>
						<?php
					}
					?>
                    <tr>
                        <td colspan="3" class="align-right highlighted-text">Total</td>
                        <td colspan="2" class="align-right highlighted-text"><?= number_format($invoiceTotal, 2) ?></td>
                    </tr>
                </table>
            </div>
			<?php if (!empty($invoiceRow['notes'])) { ?>
                <div id="_notes_section">
					<?= makeHtml($invoiceRow['notes']) ?>
                </div>
			<?php } ?>
			<?php if (!empty($footerText)) { ?>
				<?= $footerText ?>
			<?php } ?>
        </div>
    </div>
    </body>
    </html>
<?php

$invoiceHtml = ob_get_clean();
$invoiceHtml = $pageObject->replaceImageReferences($invoiceHtml);
$invoiceNumber = getFieldFromId("invoice_number", "invoices", "invoice_id", $invoiceId);
if (empty($invoiceNumber)) {
	$invoiceNumber = $invoiceId;
}
$contactId = getFieldFromId("contact_id", "invoices", "invoice_id", $invoiceId);
$displayName = getFieldFromId("business_name", "contacts", "contact_id", $contactId);
if (empty($displayName)) {
	$displayName = getDisplayName($contactId);
}
$filename = $displayName . " - Invoice " . $invoiceNumber . ".pdf";

if ($_GET['send_email']) {
	$fileId = outputPDF($invoiceHtml, array("filename" => $filename, "create_file" => true));
	$emailId = getFieldFromId("email_id", "emails", "email_code", "INVOICE_CREATED",  "inactive = 0");
	header("location: /sendemail.php?record_touchpoint=true&email_id=" . $emailId . "&attachment_file_id=" . $fileId . "&invoice_total=" . $invoiceTotal . "&" . http_build_query($invoiceRow));
} else {
	outputPDF($invoiceHtml, array("output_filename" => $filename));
}
