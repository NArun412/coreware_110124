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

$GLOBALS['gPageCode'] = "REPORTPDF";
require_once "shared/startup.inc";

if (empty($_POST['filename'])) {
    $_POST['filename'] = "report.pdf";
}
ob_start();
?>
    <html lang="en">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <head>
        <link type="text/css" rel="stylesheet" href="file:///<?= $GLOBALS['gDocumentRoot'] ?>/css/reset.css"/>
        <link type="text/css" rel="stylesheet" href="file:///<?= $GLOBALS['gDocumentRoot'] ?>/fontawesome-core/css/all.min.css"/>
        <style>
            html { font-family: "Helvetica", sans-serif; }
            #_report_title { width: 950px; font-size: 22px; }
            #_report_content { width: 950px; padding: 20px; }
            #_report_title.landscape { width: 1100px; }
            #_report_content.landscape { width: 1100px; }
            a { text-decoration: none; }
            p { font-size: 10px; padding-bottom: 5px; }
            td { font-size: 10px; page-break-inside: avoid; }
            th { font-size: 10px; font-weight: bold; }
            tr { page-break-inside: avoid; }
            hr { height: 2px; color: rgb(150,150,150); background-color: rgb(150,150,150); }
            td,th { padding: 5px; padding-top: 5px; padding-bottom: 5px; }
            h1 { font-size: 18px; text-align: center; width: 740px; color: rgb(40,40,40); }
            h2 { font-size: 15px; font-weight: bold; }
            h3 { font-size: 13px; font-weight: bold; }
            ul { padding-left: 20px; list-style-type: disc; font-size: 10px; padding-bottom: 10px; }
            ul li { list-style-type: disc; font-size: 10px; }
            .grid-table tr:nth-child(odd) td { background-color: rgb(250,250,250); }
            .grid-table tr.thick-top td { border-top-width: 4px; }
            .grid-table tr.thick-top-black td { border-top: 4px solid rgb(0,0,0); }
            .grid-table th { border-width: 1px; border-color: rgb(100,100,100); }
			.grid-table td { border-width: 1px; border-color: rgb(100,100,100); background-clip: padding-box; }
            .printable-only { display: block; }
            <?= $_POST['printable_style'] ?>
        </style>
    </head>
    <body>
    <h1 id="_report_title"<?= ($_POST['orientation'] == "landscape" ? " class='landscape'" : "") ?>><?= $_POST['report_title'] ?></h1>
    <div id="_report_content"<?= ($_POST['orientation'] == "landscape" ? " class='landscape'" : "") ?>>
        <?= $_POST['report_content'] ?>
    </div>
    </body>
    </html>
<?php
$pdfContent = ob_get_clean();
$parameters = array("output_filename"=>$_POST['filename']);
if (array_key_exists("orientation",$_POST)) {
    $parameters['orientation'] = $_POST['orientation'];
}
outputPDF($pdfContent,$parameters);
exit;
