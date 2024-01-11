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

/*
Requirement to make a report function as background, scheduled report:

-   Report needs to implement the Interface BackgroundReport. This will require the report to have a public static function that generates the report and returns an array with
	at least two entries: report_title and report_content. The interactive report should call this static function to get the report contents. In the static function, when saving
	the report parameters, the code would call "saveStoredReport(static::class)". This saves the report and records the class of the object generating the report, which is needed
	by this report runner.
-	getReportContent needs to return an array containing one or more of the following keys:
	- report_content
	- report_title
	- report_export
	- export_headers
	- filename
	- orientation - only effective if set to 'landscape'
	- file_id
-	Some reports are NOT appropriate for running in the background, like ReceiptReport, for instance. To prevent a report from being able to be run in the background, when calling
	saveStoredReport, don't pass any parameters and also don't have the report implement BackgroundReport

*/

$GLOBALS['gPageCode'] = "BACKGROUNDPROCESS";
require_once __DIR__ . "/../shared/startup.inc";

if (!$GLOBALS['gUserRow']['superuser_flag'] && !$GLOBALS['gCommandLine']) {
	echo "ERROR: For security purposes, this program cannot be run from a browser - " . php_sapi_name() . ".\n";
	exit;
}

class ThisBackgroundProcess extends BackgroundProcess {
	function setProcessCode() {
		$this->iProcessCode = "report_runner";
	}

	function process() {
		executeQuery("delete from stored_report_results where log_time < date_sub(current_date,interval 6 month)");
        $serverTime = time();
        $resultSet = executeQuery("select now()");
        $row = getNextRow($resultSet);
        $dbTime = strtotime($row['now()']);
        $timeZoneAdjustment = 0;
        if(abs($dbTime - $serverTime) > 60) {
            $timeZoneAdjustment = round(($dbTime - $serverTime) / 60);
        }
		$resultSet = executeQuery("select * from stored_reports where repeat_rules is not null and user_id in (select user_id from users where inactive = 0) order by client_id,stored_report_id");
		while ($row = getNextRow($resultSet)) {
			$lastStartEpoch = (empty($row['last_start_time']) ? 0 : round(date("U", strtotime($row['last_start_time'])) / 60));
			$currentEpoch = round(date("U") / 60);
			$minutesSinceRun = $currentEpoch - $lastStartEpoch + $timeZoneAdjustment;
			if (empty($row['run_immediately'])) {
				$repeatParts = explode(":", $row['repeat_rules']);
				$repeatFields['frequency'] = $repeatParts[0];
				if (empty($repeatFields['frequency'])) {
					continue;
				}
				$repeatFields['minute_interval'] = $repeatParts[1];
				$repeatFields['months'] = explode(",", $repeatParts[2]);
				$repeatFields['month_days'] = explode(",", $repeatParts[3]);
				$repeatFields['weekdays'] = explode(",", $repeatParts[4]);
				$repeatFields['hours'] = explode(",", $repeatParts[5]);
				$repeatFields['hour_minute'] = $repeatParts[6];
				$this->addResult("Minutes since run for report '" . $row['description'] . "' (page: " . getFieldFromId("description", "pages", "page_id", $row['page_id']) . ") for " . getUserDisplayName($row['user_id']) . ": " . $minutesSinceRun);
				$this->addResult("Repeat Information: " . jsonEncode($repeatFields));
			} else {
				$this->addResult("Run report '" . $row['description'] . "' once immediately for user " . getUserDisplayName($row['user_id']));
            }

			if ($minutesSinceRun < 0) {
				$runReport = true;
			} if (!empty($row['run_immediately'])) {
                $runReport = true;
			} else {
				$runReport = false;
				switch ($repeatFields['frequency']) {
					case "MINUTES":
						$runReport = ($minutesSinceRun >= $repeatFields['minute_interval']);
						break;
					case "HOURLY":
						$runReport = (($minutesSinceRun > 10 && date("i") == $repeatFields['hour_minute']) || (date("i") > $repeatFields['hour_minute'] && $minutesSinceRun > 50));
						break;
					case "DAILY":
						$runReport = ($minutesSinceRun > (25 * 60)) || (in_array(date("G"), $repeatFields['hours']) && (($minutesSinceRun > 10 && date("i") == $repeatFields['hour_minute']) || (date("i") > $repeatFields['hour_minute'] && $minutesSinceRun > 50)));
						break;
					case "WEEKLY":
						$runReport = ($minutesSinceRun > (25 * 60 * 7)) || (in_array(date("w"), $repeatFields['weekdays']) && in_array(date("G"), $repeatFields['hours']) && (($minutesSinceRun > 10 && date("i") == $repeatFields['hour_minute']) || (date("i") > $repeatFields['hour_minute'] && $minutesSinceRun > 50)));
						break;
					case "MONTHLY":
						$runReport = ($minutesSinceRun > (24 * 60 * 32)) ||
								((in_array(date("j"), $repeatFields['month_days']) || (date("j") == date("t") && in_array(31, $repeatFields['month_days']))) && in_array(date("n"), $repeatFields['months']) &&
										in_array(date("G"), $repeatFields['hours']) && (($minutesSinceRun > 10 && date("i") == $repeatFields['hour_minute']) || (date("i") > $repeatFields['hour_minute'] && $minutesSinceRun > 50)));
						break;
				}
			}
			while ($runReport) {
                $savedUserId = $GLOBALS['gUserId'];
                logout();
                changeClient($row['client_id']);

                executeQuery("update stored_reports set run_immediately = 0 where stored_report_id = ?",$row['stored_report_id']);
				$pageRow = getRowFromId("pages", "page_id", $row['page_id'], "client_id = ?", $GLOBALS['gDefaultClientId']);
                $pageRow = $pageRow ?: getRowFromId("pages", "page_id", $row['page_id']);
				$scriptFilename = $pageRow['script_filename'];
				if (empty($row['class_name']) || empty($scriptFilename)) {
					executeQuery("insert into stored_report_results (stored_report_id,log_time,content) values (?,now(),?)", $row['stored_report_id'],
							jsonEncode(array("report_title" => "No Report Generated", "report_content" => "<p>Report '" . $pageRow['description'] . "' has not been enabled to run as a scheduled report: No Script or class</p>")));
					break;
				}

                include_once __DIR__ . "/../" . $scriptFilename;
				try {
					$class = new ReflectionClass($row['class_name']);
					if ($class->implementsInterface('BackgroundReport')) {
						$this->addResult("Run report '" . $row['description'] . "' (page: " . getFieldFromId("description", "pages", "page_id", $row['page_id']) . ") for " . getUserDisplayName($row['user_id']));
						login($row['user_id']);
						executeQuery("update stored_reports set last_start_time = now() where stored_report_id = ?", $row['stored_report_id']);
						$postParameters = (empty($row['parameters']) ? array() : json_decode($row['parameters'], true));
						unset($postParameters['stored_report_description']);
						$_POST = $postParameters;
						$reflectionMethod = new ReflectionMethod($row['class_name'], "getReportContent");
						$response = $reflectionMethod->invoke(null);
						if (!is_array($response) || !empty($response['empty_report'])) {
							$this->addResult("Report '" . $row['description'] . "' returned empty result");
						} else {
							if (method_exists($row['class_name'], "internalCSS")) {
								$object = new $row['class_name']();
								ob_start();
								$object->internalCSS();
								$printableStyle = str_replace("<style>", "", ob_get_clean());
								$styleLines = getContentLines($printableStyle);
								$printableStyle = "";
								$useLine = false;
								foreach ($styleLines as $thisLine) {
									if ($thisLine == "<style id=\"_printable_style\">" || $thisLine == "<style id='_printable_style'>") {
										$useLine = true;
										continue;
									} else if ($thisLine == "</style>") {
										$useLine = false;
									}
									if ($useLine) {
										$printableStyle .= $thisLine . "\n";
									}
								}
								$response['printable_style'] = $printableStyle;
							}
							$result = jsonEncode($response);
							executeQuery("insert into stored_report_results (stored_report_id,log_time,content,file_id) values (?,now(),?,?)", $row['stored_report_id'], (empty($result) ? "No report generated" : $result), $response['file_id']);
							if (!empty($row['email_results'])) {
                                $emailSubject = "Report Results";
                                $useStoredReportDescription = getPreference("USE_STORED_REPORT_DESCRIPTION_FOR_EMAIL");
								if (!empty($useStoredReportDescription)) {
									$emailSubject = $row['description'];
								}
								if (array_key_exists("report_content", $response)) {
                                    if (!empty($useStoredReportDescription)) {
										$response['filename'] = $row['description'] . ".pdf";
                                    } else if (empty($response['filename'])) {
										$response['filename'] = "report.pdf";
									}
									$pdfFileContent = $this->createPDF($response);
									$attachments = array(array("attachment_string" => $pdfFileContent, "attachment_filename" => $response['filename']));
									sendEmail(array("body" => "Report '" . $row['description'] . "' is attached.", "subject" => $emailSubject,
											"attachments" => $attachments, "email_address" => $GLOBALS['gUserRow']['email_address']));
								} else if (!empty($response['file_id'])) {
									$emailAddresses = array($GLOBALS['gUserRow']['email_address']);
									$emailSet = executeQuery("select * from stored_report_email_addresses where stored_report_id = ?", $row['stored_report_id']);
									while ($emailRow = getNextRow($emailSet)) {
										$emailAddresses[] = $emailRow['email_address'];
									}
									sendEmail(array("body" => "Report '" . $row['description'] . "' is attached.", "subject" => $emailSubject, "attachment_file_id" => $response['file_id'], "email_addresses" => $emailAddresses));
								} else if (array_key_exists("report_export", $response)) {
									if (!empty($useStoredReportDescription)) {
										$response['filename'] = $row['description'] . ".csv";
									} else if (empty($response['filename'])) {
										$response['filename'] = "export.csv";
									}
									$attachments = array(array("attachment_string" => $response['report_export'], "attachment_filename" => $response['filename']));
									$emailAddresses = array($GLOBALS['gUserRow']['email_address']);
									$emailSet = executeQuery("select * from stored_report_email_addresses where stored_report_id = ?", $row['stored_report_id']);
									while ($emailRow = getNextRow($emailSet)) {
										$emailAddresses[] = $emailRow['email_address'];
									}
									sendEmail(array("body" => "Report '" . $row['description'] . "' is attached.", "subject" => $emailSubject,
											"attachments" => $attachments, "email_addresses" => $emailAddresses));
								}
							}
						}
						logout();
						if (!empty($savedUserId)) {
							login($savedUserId);
						}
					} else {
						executeQuery("insert into stored_report_results (stored_report_id,log_time,content) values (?,now(),?)", $row['stored_report_id'],
								jsonEncode(array("report_title" => "No Report Generated", "report_content" => "<p>Report '" . $pageRow['description'] . "' has not been enabled to run as a scheduled report: does not implement background report</p>")));
					}
				} catch (Exception $e) {
					executeQuery("insert into stored_report_results (stored_report_id,log_time,content) values (?,now(),?)", $row['stored_report_id'],
							jsonEncode(array("report_title" => "No Report Generated", "report_content" => "<p>An error occurred in attempting to run report '" . $pageRow['description'] . "' as a scheduled report: " . $e->getMessage() . "</p>")));
				}
				break;
			}
		}
	}

	function createPDF($reportResponse) {
		ob_start();
		?>
		<html lang="en">
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
		<head>
			<link type="text/css" rel="stylesheet" href="file:///<?= $GLOBALS['gDocumentRoot'] ?>/css/reset.css"/>
			<link type="text/css" rel="stylesheet" href="file:///<?= $GLOBALS['gDocumentRoot'] ?>/fontawesome-core/css/all.min.css"/>
			<style>
				html {
					font-family: "Helvetica", sans-serif;
				}

				#_report_title {
					width: 880px;
					font-size: 22px;
				}

				#_report_content {
					width: 880px;
					padding: 20px;
				}

				#_report_title.landscape {
					width: 1100px;
				}

				#_report_content.landscape {
					width: 1100px;
				}

				a {
					text-decoration: none;
				}

				p {
					font-size: 10px;
					padding-bottom: 5px;
				}

				td {
					font-size: 10px;
					page-break-inside: avoid;
				}

				th {
					font-size: 10px;
					font-weight: bold;
				}

				tr {
					page-break-inside: avoid;
				}

				hr {
					height: 2px;
					color: rgb(150, 150, 150);
					background-color: rgb(150, 150, 150);
				}

				td, th {
					padding: 5px;
					padding-top: 5px;
					padding-bottom: 5px;
				}

				h1 {
					font-size: 18px;
					text-align: center;
					width: 740px;
					color: rgb(40, 40, 40);
				}

				h2 {
					font-size: 15px;
					font-weight: bold;
				}

				h3 {
					font-size: 13px;
					font-weight: bold;
				}

				ul {
					padding-left: 20px;
					list-style-type: disc;
					font-size: 10px;
					padding-bottom: 10px;
				}

				ul li {
					list-style-type: disc;
					font-size: 10px;
				}

				.grid-table tr:nth-child(odd) td {
					background-color: rgb(240, 240, 240);
				}

				.grid-table tr.thick-top td {
					border-top-width: 4px;
				}

				.grid-table tr.thick-top-black td {
					border-top: 4px solid rgb(0, 0, 0);
				}

				.printable-only {
					display: block;
				}

				<?= $reportResponse['printable_style'] ?>
			</style>
		</head>
		<body>
		<h1 id="_report_title"<?= ($reportResponse['orientation'] == "landscape" ? " class='landscape'" : "") ?>><?= $reportResponse['report_title'] ?></h1>
		<div id="_report_content"<?= ($reportResponse['orientation'] == "landscape" ? " class='landscape'" : "") ?>>
			<?= $reportResponse['report_content'] ?>
		</div>
		</body>
		</html>
		<?php
		$pdfContent = ob_get_clean();
		$parameters = array("output_filename" => $reportResponse['filename'], "get_contents" => true);
		if (array_key_exists("orientation", $reportResponse)) {
			$parameters['orientation'] = $reportResponse['orientation'];
		}
		return outputPDF($pdfContent, $parameters);
	}
}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
