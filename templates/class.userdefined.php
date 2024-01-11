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

class Template extends AbstractTemplate {
	private $iDataSource = false;
	private $iErrorMessage = "";
	private $iImageArray = array();

	function setup() {
		$primaryTableName = $this->iPageObject->getPrimaryTableName();
		if (!empty($primaryTableName)) {
			$this->iDataSource = new DataSource($primaryTableName);
			$this->iPageObject->setDataSource($this->iDataSource);
			$this->iPageObject->setDatabase($this->iDataSource->getDatabase());
			$this->iPageObject->massageDataSource();
			addDataLimitations($this->iDataSource);
			$this->iDataSource->getPageControls();
		} else {
			$this->iPageObject->setDatabase($GLOBALS['gPrimaryDatabase']);
		}
		$this->iPageObject->executeSubaction();
		$this->iPageObject->setup();
	}

	function footer() {
		$this->iPageObject->footer();
	}

	function executeUrlActions() {
		if (!empty($this->iPageObject->iTemplateAddendumObject) && method_exists($this->iPageObject->iTemplateAddendumObject, "executeUrlActions")) {
			call_user_func(array($this->iPageObject->iTemplateAddendumObject, "executeUrlActions"));
		}
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_system_notice_content":
				if ($GLOBALS['gLoggedIn']) {
					$resultSet = executeQuery("select * from system_notices where inactive = 0 and client_id = ? and " .
						"(start_time is null or start_time <= current_time) and system_notice_id = ? and " .
						"(end_time is null or end_time >= current_time) and (all_user_access = 1 or system_notice_id in " .
						"(select system_notice_id from system_notice_users where user_id = " . $GLOBALS['gUserId'] . ")" .
						(empty($GLOBALS['gUserRow']['superuser_flag']) && empty($GLOBALS['gUserRow']['full_client_access']) ? "" : " or full_client_access = 1") . ") order by time_submitted",
						$GLOBALS['gClientId'], $_GET['system_notice_id']);
					if ($row = getNextRow($resultSet)) {
						$returnArray['system_notice_content'] = makeHtml($row['content']);
					}
				}
				ajaxResponse($returnArray);
				break;
			case "mark_system_notice_read":
				if ($GLOBALS['gLoggedIn']) {
					$resultSet = executeQuery("select * from system_notices where inactive = 0 and client_id = ? and " .
						"(start_time is null or start_time <= current_time) and system_notice_id = ? and " .
						"(end_time is null or end_time >= current_time) and (all_user_access = 1 or system_notice_id in " .
						"(select system_notice_id from system_notice_users where user_id = " . $GLOBALS['gUserId'] . ")" .
						(empty($GLOBALS['gUserRow']['superuser_flag']) && empty($GLOBALS['gUserRow']['full_client_access']) ? "" : " or full_client_access = 1") . ") order by time_submitted",
						$GLOBALS['gClientId'], $_GET['system_notice_id']);
					if ($row = getNextRow($resultSet)) {
						$systemNoticeUserId = getFieldFromId("system_notice_user_id", "system_notice_users", "system_notice_id", $row['system_notice_id'], "user_id = ?", $GLOBALS['gUserId']);
						if (empty($systemNoticeUserId)) {
							executeQuery("insert into system_notice_users (system_notice_id,user_id,time_read) values (?,?,now())", $row['system_notice_id'], $GLOBALS['gUserId']);
						} else {
							executeQuery("update system_notice_users set time_read = now() where time_read is null and system_notice_id = ? and user_id = ?", $row['system_notice_id'], $GLOBALS['gUserId']);
						}
					}
				}
				ajaxResponse($returnArray);
				break;
			case "log_click":
				if (empty($_POST['description'])) {
					$_POST['description'] = "Click from " . $_SERVER['REQUEST_URI'];
				}
				executeQuery("insert into click_log (client_id,description,user_id,ip_address,log_time) values (?,?,?,?,now())",
					$GLOBALS['gClientId'], $_POST['description'], $GLOBALS['gUserId'], $_SERVER['REMOTE_ADDR']);
				ajaxResponse($returnArray);
				break;
			case "newsletter_signup":
				$emailAddresses = array();
				for ($x = 0; $x <= 9; $x++) {
					$postFix = (empty($x) ? "" : "_" . $x);
					if ($x > 0 && !array_key_exists("signup_email_address" . $postFix, $_POST)) {
						continue;
					}
					if (empty($_POST['first_name']) || empty($_POST['signup_last_name'])) {
						$contactId = getFieldFromId("contact_id", "contacts", "email_address", $_POST['signup_email_address' . $postFix]);
					} else {
						$contactId = getFieldFromId("contact_id", "contacts", "email_address", $_POST['signup_email_address' . $postFix],
							"first_name = ? and last_name = ?", $_POST['signup_first_name' . $postFix], $_POST['signup_last_name' . $postFix]);
					}
					if (empty($_POST['signup_country_id' . $postFix])) {
						$_POST['signup_country_id' . $postFix] = 1000;
					}
					if (empty($contactId)) {
						$sourceId = getFieldFromId("source_id", "sources", "source_id", $_COOKIE['source_id'], "inactive = 0");
						if (empty($sourceId)) {
							$sourceId = getFieldFromId("source_id", "sources", "source_code", $_POST['signup_source_code' . $postFix], "inactive = 0");
						}
						if (empty($sourceId)) {
							$sourceId = getSourceFromReferer($_SERVER['HTTP_REFERER']);
						}
						$contactDataTable = new DataTable("contacts");
						$contactId = $contactDataTable->saveRecord(array("name_values" => array("first_name" => $_POST['signup_first_name' . $postFix], "last_name" => $_POST['signup_last_name' . $postFix],
							"address_1" => $_POST['signup_address_1' . $postFix], "address_2" => $_POST['signup_address_2' . $postFix], "city" => $_POST['signup_city' . $postFix],
							"state" => $_POST['signup_state' . $postFix], "postal_code" => $_POST['signup_postal_code' . $postFix], "email_address" => $_POST['signup_email_address' . $postFix],
							"country_id" => $_POST['signup_country_id' . $postFix], "source_id" => $sourceId)));
					}
					$mailingListCode = $_POST['mailing_list_code'];
					if (!array_key_exists("mailing_list_code", $_POST)) {
						$mailingListCode = "NEWSLETTER";
					}
					if (!empty($mailingListCode)) {
						$mailingListCodeArray = explode(",", $mailingListCode);
						foreach ($mailingListCodeArray as $mailingListCode) {
							$mailingListId = getFieldFromId("mailing_list_id", "mailing_lists", "mailing_list_code", $mailingListCode);
							if (!empty($mailingListId)) {
								$contactMailingId = getFieldFromId("contact_mailing_list_id", "contact_mailing_lists", "contact_id", $contactId, "mailing_list_id = ?", $mailingListId);
								if (empty($contactMailingId)) {
									executeQuery("insert into contact_mailing_lists (contact_id,mailing_list_id,date_opted_in,ip_address) values " .
										"(?,?,current_date,?)", $contactId, $mailingListId, $_SERVER['REMOTE_ADDR']);
								} else {
									executeQuery("update contact_mailing_lists set date_opted_out = null where contact_mailing_list_id = ?", $contactMailingId);
								}
							}
						}
					}
					if (!empty($_POST['signup_phone_number' . $postFix])) {
						$phoneNumberId = getFieldFromId("phone_number_id", "phone_numbers", "phone_number", $_POST['signup_phone_number' . $postFix], "contact_id = ?", $contactId);
						if (empty($phoneNumberId)) {
							executeQuery("insert into phone_numbers (contact_id,phone_number,description) values (?,?,?)", $contactId, $_POST['signup_phone_number' . $postFix], $_POST['signup_description' . $postFix]);
						}
					}
					$emailId = getFieldFromId("email_id", "emails", "email_code", $mailingListCode . "_signup_confirmation", "inactive = 0");
					if (!empty($emailId) && !empty($_POST['signup_email_address' . $postFix]) && !in_array($_POST['signup_email_address' . $postFix], $emailAddresses)) {
						$substitutions = array();
						foreach ($_POST as $fieldName => $fieldData) {
							$substitutions[str_replace("signup_", "", $fieldName)] = $fieldData;
						}
						sendEmail(array("email_id" => $emailId, "substitutions" => $substitutions, "email_address" => $_POST['signup_email_address' . $postFix]));
						$emailAddresses[] = $_POST['signup_email_address' . $postFix];
					}
					$returnArray['subscribe_response'] = getFragment($mailingListCode . "_SUBSCRIBE_RESPONSE");
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function displayPage() {
		$GLOBALS['gTemplateRow'] = getReadRowFromId("templates", "template_id", $GLOBALS['gPageTemplateId'], "client_id = ? or client_id = ?", $GLOBALS['gClientId'], $GLOBALS['gDefaultClientId']);

		$pageTextChunks = array();
		if (is_array($GLOBALS['gPageRow']['page_text_chunks'])) {
			foreach ($GLOBALS['gPageRow']['page_text_chunks'] as $pageTextChunkCode => $pageTextChunkContent) {
				$pageTextChunks[strtolower($pageTextChunkCode)] = $pageTextChunkContent;
				PlaceHolders::setPlaceholderValue("page_text_chunk:" . strtolower(strtolower($pageTextChunkCode)), $pageTextChunkContent);
			}
		}
		if (function_exists("massagePageTextChunks")) {
			massagePageTextChunks($pageTextChunks);
		}
		if (method_exists($this->iPageObject, "massagePageTextChunks")) {
			$this->iPageObject->massagePageTextChunks($pageTextChunks);
		}
		foreach ($pageTextChunks as $pageTextChunkCode => $content) {
			foreach ($GLOBALS['gTemplateRow'] as $fieldName => $fieldData) {
				$GLOBALS['gTemplateRow'][$fieldName] = str_replace("%page_text_chunk:" . $pageTextChunkCode . "%", $content, $fieldData);
			}
		}
		$resultSet = executeQuery("select * from template_text_chunks where template_id = ?", $GLOBALS['gTemplateRow']['template_id']);
		$templateTextChunks = array();
		while ($row = getNextRow($resultSet)) {
			$templateTextChunks[strtolower($row['template_text_chunk_code'])] = $row['content'];
			PlaceHolders::setPlaceholderValue("template_text_chunk:" . strtolower($row['template_text_chunk_code']), $row['content']);
		}
		foreach ($templateTextChunks as $templateTextChunkCode => $content) {
			foreach ($GLOBALS['gTemplateRow'] as $fieldName => $fieldData) {
				$GLOBALS['gTemplateRow'][$fieldName] = str_replace("%template_text_chunk:" . $templateTextChunkCode . "%", $content, $fieldData);
			}
		}
		$GLOBALS['gTemplateRow']['template_text_chunks'] = $templateTextChunks;

		$programTextChunks = getCachedData("program_text", "program_text", true);
		if (!is_array($programTextChunks)) {
			$programTextChunks = array();
			$resultSet = executeReadQuery("select * from program_text");
			while ($row = getNextRow($resultSet)) {
				$programTextChunks[strtolower($row['program_text_code'])] = $row['content'];
			}
			setCachedData("program_text", "program_text", $programTextChunks, 24, true);
		}
		foreach ($programTextChunks as $programTextChunkCode => $content) {
			PlaceHolders::setPlaceholderValue("program_text:" . strtolower($programTextChunkCode), $content);
		}
		if (function_exists("massageProgramText")) {
			massageProgramText($programTextChunks);
		}
		if (method_exists($this->iPageObject, "massageProgramText")) {
			$this->iPageObject->massageProgramText($programTextChunks);
		}
		foreach ($programTextChunks as $programTextChunkCode => $content) {
			foreach ($GLOBALS['gTemplateRow'] as $fieldName => $fieldData) {
				$GLOBALS['gTemplateRow'][$fieldName] = str_replace("%program_text:" . $programTextChunkCode . "%", $content, $fieldData);
			}
		}

		$templateContent = $GLOBALS['gTemplateRow']['content'];
		if (empty($GLOBALS['gTemplateRow']['content']) && !empty($GLOBALS['gTemplateRow']['filename'])) {
			$contentFilename = $GLOBALS['gDocumentRoot'] . "/templates/" . (empty($GLOBALS['gTemplateRow']['directory_name']) ? "" : $GLOBALS['gTemplateRow']['directory_name'] . "/") . $GLOBALS['gTemplateRow']['filename'];
			$templateContent = file_get_contents($contentFilename);
		}
		$templateLines = getContentLines($templateContent);
		foreach ($templateLines as $index => $thisLine) {
			$thisLine = str_replace("<!-- %", "%", $thisLine);
			$thisLine = str_replace("<!--%", "%", $thisLine);
			$thisLine = str_replace("% -->", "%", $thisLine);
			$thisLine = str_replace("%-->", "%", $thisLine);
			$templateLines[$index] = $thisLine;
		}
		$templateContent = implode("\n", $templateLines);
		$replacementValues = array();
		$resultSet = executeQuery("select * from template_data join template_data_uses using (template_data_id) where template_id = ?", $GLOBALS['gPageTemplateId']);
		while ($row = getNextRow($resultSet)) {
			$replacementValues["%pageData:" . $row['data_name'] . "%"] = $this->iPageObject->getPageData($row['data_name']);
		}
		foreach ($replacementValues as $placeholder => $replaceValue) {
			$templateContent = str_replace($placeholder, $replaceValue, $templateContent);
		}
		$templateContent = $this->iPageObject->replaceImageReferences($templateContent);
		$templateLines = getContentLines($templateContent);
		$loggedInOnly = false;
		$adminOnly = false;
		$notLoggedInOnly = false;
		$validData = true;
		$systemVersion = "";
		if ($GLOBALS['gUserRow']['superuser_flag']) {
            $systemVersion = getSystemVersion();
		}
		ob_start();

		foreach ($templateLines as $thisLine) {
			switch ($thisLine) {
				case "%metaKeywords%":
					if (!empty($GLOBALS['gPageRow']['meta_keywords'])) {
						?>
						<meta name="Keywords" content="<?= str_replace("\"", "'", $GLOBALS['gPageRow']['meta_keywords']) ?>">
						<?php
					}
					break;
				case "%metaDescription%":
					if (!empty($GLOBALS['gPageRow']['meta_description'])) {
						?>
						<meta name="Description" content="<?= str_replace("\"", "'", $GLOBALS['gPageRow']['meta_description']) ?>">
						<?php
					}
					break;
				case "%getPageTitle%":
					echo "<title>" . $this->getPageTitle() . "</title>\n";
					break;
				case "%headerIncludes%":
					$this->headerIncludes();
					break;
				case "%getAnalyticsCode%":
					echo $this->getAnalyticsCode();
					break;
				case "%onLoadJavascript%":
					break;
				case "%javascript%":
					if (method_exists($this->iPageObject, "inlineJavascript")) {
						ob_start();
						$this->iPageObject->inlineJavascript();
						$inlineJavascript = PlaceHolders::massageContent(ob_get_clean());
						$holdJavascriptLines = getContentLines($inlineJavascript);
						$javascriptLines = array();
						foreach ($holdJavascriptLines as $thisJavascriptLine) {
							if (substr($thisJavascriptLine, 0, strlen("<!--suppress")) != "<!--suppress") {
								$javascriptLines[] = $thisJavascriptLine;
							}
						}
						$startTag = "<script>";
						$endTag = "</script>";
						if (count($javascriptLines) > 0) {
							if (strpos($javascriptLines[count($javascriptLines) - 1], "</script") !== false) {
								$endTag = array_pop($javascriptLines);
							}
							if (strpos($javascriptLines[0], "<script") !== false) {
								$startTag = array_shift($javascriptLines);
							}
						}
						$inlineJavascript = $startTag . "\n" . implode("\n", $javascriptLines) . "\n" . $endTag . "\n";
						echo $inlineJavascript;
					}
					ob_start();
					$this->onLoadJavascript();
					if (!empty($GLOBALS['gUserId'])) {
						$userKey = md5($GLOBALS['gUserId'] . ":" . $GLOBALS['gUserRow']['user_name'] . ":" . $_SERVER['REMOTE_ADDR'] . ":" . $GLOBALS['gUserRow']['password_salt'] . ":" . date("m/d/Y"));
					} else {
						$userKey = "";
					}
					?>
					var developmentServer = <?= ($GLOBALS['gDevelopmentServer'] ? "true" : "false") ?>;
					var languageCode = "<?= $GLOBALS['gLanguageCode'] ?>";
					var scriptFilename = "<?= $GLOBALS['gLinkUrl'] ?>";
					var userLoggedIn = <?= $GLOBALS['gLoggedIn'] ? "true" : "false" ?>;
					var adminLoggedIn = <?= (!empty($GLOBALS['gUserRow']['administrator_flag']) ? "true" : "false") ?>;
					var gWebUserId = "<?= $GLOBALS['gWebUserId'] ?>";
					var gUserUid = "<?= $GLOBALS['gUserId'] ?>";
					var gUserKey = "<?= $userKey ?>";
					var thisIsAPublicWebsite = true;
					gDefaultAjaxTimeout = "<?= $GLOBALS['gDefaultAjaxTimeout'] ?>";

					<?php
					if (!empty($GLOBALS['gTemplateRow']['javascript_code'])) {
						echo $GLOBALS['gTemplateRow']['javascript_code'] . "\n";
					}
					if (!empty($systemVersion)) {
						?>
						$(function() {
						$("body").data("system_version","<?= $systemVersion ?>");
						$("#_system_version").html("<?= $systemVersion ?>");
						});
						<?php
					}
					$this->javascript();
					$fullPageJavascript = PlaceHolders::massageContent(ob_get_clean());
					$fullPageJavascript = str_replace("<script>", "", $fullPageJavascript);
					$fullPageJavascript = str_replace("</script>", "", $fullPageJavascript);
					if ($GLOBALS['gLocalExecution']) {
						echo "<script>\n";
						echo $fullPageJavascript . "\n";
						echo "</script>\n";
					} else {
						$mergedFilename = getMergedFilename($fullPageJavascript, "js")
						?>
						<script src="<?= autoVersion($mergedFilename) ?>"></script>
						<?php
					}
					break;
				case "%internalCSS%":
					if (!empty($GLOBALS['gTemplateRow']['css_file_id'])) {
						$cssFilename = createCSSFile($GLOBALS['gTemplateRow']['css_file_id']);
						?>
						<link media='screen' type="text/css" rel="stylesheet" href="<?= autoVersion($cssFilename) ?>"/>
						<?php
					}
					ob_start();
					if (!empty($GLOBALS['gTemplateRow']['css_content'])) {
						$cssContent = processCssContent(PlaceHolders::massageContent(getSassHeaders($GLOBALS['gTemplateRow']['template_id'])) . PlaceHolders::massageContent($GLOBALS['gTemplateRow']['css_content']));
						echo "<style>\n/* Template CSS */\n" . $cssContent . "\n</style>\n";
					}
					$resultSet = executeReadQuery("select * from product_tags where client_id = ? and display_color is not null and " .
						"internal_use_only = 0 and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						?>
						<style>
                            .catalog-item.product-tag-code-<?= strtolower(str_replace("_", "-", $row['product_tag_code'])) ?> .catalog-result-product-tag.catalog-result-product-tag-<?= strtolower(str_replace("_", "-", $row['product_tag_code'])) ?> {
                                display: inline-block;
                            }

                            .catalog-result-product-tag.catalog-result-product-tag-<?= strtolower(str_replace("_", "-", $row['product_tag_code'])) ?> {
                                background-color: <?= $row['display_color'] ?>;
                            }
						</style>
						<?php
					}
					$fullPageCSS = ob_get_clean();
					ob_start();
					$this->internalCSS();
					$fullPageCSS .= PlaceHolders::massageContent(ob_get_clean());
					if (strpos($fullPageCSS, "<style ") !== false) {
						echo $fullPageCSS;
					} else {
						$fullPageCSS = str_replace("<style>", "", $fullPageCSS);
						$fullPageCSS = str_replace("</style>", "", $fullPageCSS);
						$fullPageCSS = $this->iPageObject->replaceImageReferences($fullPageCSS);
						if ($GLOBALS['gLocalExecution']) {
							echo "<style>\n";
							echo $fullPageCSS . "\n";
							echo "</style>\n";
						} else {
							$mergedFilename = getMergedFilename($fullPageCSS, "css")
							?>
							<link media='screen' type="text/css" rel="stylesheet" href="<?= autoVersion($mergedFilename) ?>"/>
							<?php
						}
					}
					if ($GLOBALS['gTemplateRow']['client_id'] == $GLOBALS['gDefaultClientId'] && $GLOBALS['gTemplateRow']['template_code'] == "EMBED") {
						$cssFileId = getFieldFromId("css_file_id", "css_files", "css_file_code", "EMBED");
						if (!empty($cssFileId)) {
							echo "<!-- EMBED CSS -->\n";
							$cssFilename = createCSSFile($cssFileId);
							?>
							<link media='screen' type="text/css" rel="stylesheet" href="<?= autoVersion($cssFilename) ?>"/>
							<?php
						}
					}
					break;
				case "%mainContent%":
					$this->mainContent();
					break;
				case "%hiddenElements%":
					$this->hiddenElements();
					/*
					?>
					<iframe src='https://ncce.coreware.com/load-test' style='display: none; height: 0; width: 0; visibility: hidden;'></iframe>
					<iframe src='https://ncce.coreware.com' style='display: none; height: 0; width: 0; visibility: hidden;'></iframe>
					<?php
					*/
					break;
				case "%jqueryTemplates%":
					$this->jqueryTemplates();
					break;
				case "%postIframe%":
					?>
					<iframe id="_post_iframe" name="post_iframe" class="hidden"></iframe>
					<?php
					break;
				case "%endif%":
					$adminOnly = false;
					$loggedInOnly = false;
					$notLoggedInOnly = false;
					$validData = true;
					break;
				case "%ifLoggedIn%":
					$adminOnly = false;
					$loggedInOnly = true;
					$notLoggedInOnly = false;
					$validData = true;
					break;
				case "%ifAdmin%":
					$adminOnly = true;
					$loggedInOnly = true;
					$notLoggedInOnly = false;
					$validData = true;
					break;
				case "%ifNotLoggedIn%":
					$adminOnly = true;
					$loggedInOnly = false;
					$notLoggedInOnly = true;
					$validData = true;
					break;
				default:
					if (substr($thisLine, 0, strlen("%ifPageData:")) == "%ifPageData:") {
						$pageDataCode = substr($thisLine, strlen("%ifPageData:"), -1);
						$thisData = $this->iPageObject->getPageData($pageDataCode);
						$validData = !empty($thisData);
					} else if (substr($thisLine, 0, strlen("%ifNotPageData:")) == "%ifNotPageData:") {
						$pageDataCode = substr($thisLine, strlen("%ifNotPageData:"), -1);
						$thisData = $this->iPageObject->getPageData($pageDataCode);
						$validData = empty($thisData);
					} else if (substr($thisLine, 0, strlen("%ifIsInUserGroup:")) == "%ifIsInUserGroup:") {
						$userGroupCode = substr($thisLine, strlen("%ifIsInUserGroup:"), -1);
						$validData = isInUserGroupCode($GLOBALS['gUserId'], $userGroupCode);
					} else if ($validData && substr($thisLine, 0, strlen("%method:")) == "%method:") {
						$methodName = substr($thisLine, strlen("%method:"));
						if (substr($methodName, -1) == "%") {
							$methodName = substr($methodName, 0, -1);
						}
						$parts = explode(":", $methodName);
						if (count($parts) > 1) {
							$methodName = array_shift($parts);
						} else {
							$parts = array();
						}
						if (!empty($this->iPageObject->iTemplateAddendumObject) && method_exists($this->iPageObject->iTemplateAddendumObject, $methodName)) {
							if (!empty($parts)) {
								call_user_func(array($this->iPageObject->iTemplateAddendumObject, $methodName), $parts);
							} else {
								call_user_func(array($this->iPageObject->iTemplateAddendumObject, $methodName));
							}
						}
					} else if ($validData && substr($thisLine, 0, strlen("%module:")) == "%module:") {
						$methodName = substr($thisLine, strlen("%module:"));
						if (substr($methodName, -1) == "%") {
							$methodName = substr($methodName, 0, -1);
						}
						$parts = explode(":", $methodName);
						if (count($parts) > 1) {
							$methodName = array_shift($parts);
						}
						$pageModule = PageModule::getPageModuleInstance($methodName);
						if (!empty($pageModule)) {
							$pageModule->setParameters($parts);
							$pageModule->displayContent();
						}
					} else if ($validData && substr($thisLine, 0, strlen("%cssFileCode:")) == "%cssFileCode:") {
						$cssFileCode = getFieldFromId("css_file_code", "css_files", "css_file_code", substr($thisLine, strlen("%cssFileCode:"), -1));
						if (!empty($cssFileCode)) {
							?>
							<link media='screen' type="text/css" rel="stylesheet" href="<?= autoVersion(getCSSFilename($cssFileCode)) ?>"/>
							<?php
						}
					} else if ($validData && substr($thisLine, 0, strlen("%cssFile:")) == "%cssFile:") {
						$cssFilename = substr($thisLine, strlen("%cssFile:"), -1);
						$deferLoad = false;
						if (substr($cssFilename, 0, strlen("defer:")) == "defer:") {
							$cssFilename = substr($cssFilename, strlen("defer:"));
							$deferLoad = true;
						}
						if (strpos($cssFilename, "..") === false) {
							$mergedFilenames = explode(",", $cssFilename);
							if (count($mergedFilenames) == 1) {
								if ($deferLoad) {
									?>
									<script>
                                        document.addEventListener('DOMContentLoaded', function () {
                                            $("head").append('<link media="screen" type="text/css" rel="stylesheet" href="<?= autoVersion($cssFilename) ?>"/>');
                                        });
									</script>
									<?php
								} else {
									?>
									<link media='screen' type="text/css" rel="stylesheet" href="<?= autoVersion($cssFilename) ?>"/>
									<?php
								}
							} else {
								if ($GLOBALS['gDevelopmentServer']) {
									foreach ($mergedFilenames as $thisFilename) {
										?>
										<link media='screen' type="text/css" rel="stylesheet" href="<?= autoVersion($thisFilename) ?>"/>
										<?php
									}
								} else {
									$mergedFilename = getMergedFilename($mergedFilenames, "css");
									if ($deferLoad) {
										?>
										<script>
                                            document.addEventListener('DOMContentLoaded', function () {
                                                $("head").append('<link media="screen" type="text/css" rel="stylesheet" href="<?= autoVersion($mergedFilename) ?>" />');
                                            });
										</script>
										<?php
									} else {
										?>
										<link media='screen' type="text/css" rel="stylesheet" href="<?= autoVersion($mergedFilename) ?>"/>
										<?php
									}
								}
							}
						}
					} else if ($validData && substr($thisLine, 0, strlen("%javascriptFile:")) == "%javascriptFile:") {
						$javascriptFilename = substr($thisLine, strlen("%javascriptFile:"), -1);
						$deferLoad = false;
						if (substr($javascriptFilename, 0, strlen("defer:")) == "defer:") {
							$javascriptFilename = substr($javascriptFilename, strlen("defer:"));
							$deferLoad = true;
						}
						if (strpos($javascriptFilename, "..") === false) {
							$mergedFilenames = explode(",", $javascriptFilename);
							if (count($mergedFilenames) == 1) {
								?>
								<script <?= ($deferLoad ? "defer " : "") ?>src="<?= autoVersion($javascriptFilename) ?>"></script>
								<?php
							} else {
								if ($GLOBALS['gDevelopmentServer']) {
									foreach ($mergedFilenames as $thisFilename) {
										?>
										<script <?= ($deferLoad ? "defer " : "") ?>src="<?= autoVersion($thisFilename) ?>"></script>
										<?php
									}
								} else {
									$mergedFilename = getMergedFilename($mergedFilenames, "js");
									?>
									<script <?= ($deferLoad ? "defer " : "") ?>src="<?= autoVersion($mergedFilename) ?>"></script>
									<?php
								}
							}
						}
					} else if ($validData && substr($thisLine, 0, strlen("%getMenuByCode:")) == "%getMenuByCode:") {
						$menuInfo = substr($thisLine, strlen("%getMenuByCode:"), -1);
						$menuInfoParts = explode(",", $menuInfo);
						$menuCode = "";
						$menuParameters = array();
						foreach ($menuInfoParts as $thisPart) {
							if (empty($menuCode)) {
								$menuCode = trim($thisPart);
							} else {
								$thisPartParts = explode("=", $thisPart, 2);
								$menuParameters[$thisPartParts[0]] = $thisPartParts[1];
							}
						}
						echo getMenuByCode($menuCode, $menuParameters);
					} else if ($validData && substr($thisLine, 0, strlen("%getMenu:")) == "%getMenu:") {
						$menuInfo = substr($thisLine, strlen("%getMenu:"), -1);
						$menuInfoParts = explode(",", $menuInfo);
						$menuId = "";
						$menuParameters = array();
						foreach ($menuInfoParts as $thisPart) {
							if (empty($menuId)) {
								$menuId = $thisPart;
							} else {
								$thisPartParts = explode("=", $thisPart, 2);
								$menuParameters[$thisPartParts[0]] = $thisPartParts[1];
							}
						}
						echo getMenu($menuId, $menuParameters);
					} else if ($validData && ((!$adminOnly && !$loggedInOnly && !$notLoggedInOnly) ||
							($GLOBALS['gLoggedIn'] && $loggedInOnly && !$adminOnly) ||
							($GLOBALS['gLoggedIn'] && $GLOBALS['gUserRow']['administrator_flag'] && $loggedInOnly && $adminOnly) ||
							(!$GLOBALS['gLoggedIn'] && $notLoggedInOnly))) {
						echo $thisLine . "\n";
					}
					break;
			}
		}

		$templateContent = ob_get_clean();
		$templateContent = PlaceHolders::massageContent($templateContent);
		$templateContent = $this->iPageObject->replaceImageReferences($templateContent);
		if (!$GLOBALS['gLoggedIn'] && empty($_GET['ajax']) && empty($_GET['url_action']) && empty($GLOBALS['gTemplateRow']['include_crud']) && empty($GLOBALS['gCacheProhibited']) && $GLOBALS['gPageRow']['allow_cache']) {
			setCachedData("page_contents", $GLOBALS['gPageRow']['page_code'], $templateContent, 1, false);
		}

		echo $templateContent;
	}

	function headerIncludes() {
		if (!$this->iPageObject->headerIncludes()) {
			$resultSet = executeQuery("select * from page_meta_tags where page_id = ?", $GLOBALS['gPageRow']['page_id']);
			$propertyArray = array();
			while ($row = getNextRow($resultSet)) {
				if (!empty($row['content'])) {
					$propertyArray[] = $row['meta_value'];
					?>
					<meta <?= $row['meta_name'] ?>="<?= $row['meta_value'] ?>" content="<?= str_replace("\"", "'", str_replace("\n", " ", $row['content'])) ?>"/>
					<?php
				}
			}
		}
		echo $GLOBALS['gPageRow']['header_includes'];
		echo $GLOBALS['gCanonicalLink'] . "\n";
		if (empty($GLOBALS['gCanonicalLink']) && strpos($GLOBALS['gPageRow']['header_includes'], "canonical") === false) {
			echo "<link rel='canonical' href='https://" . str_replace("'","", $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) . "'>\n";
		}
		$cssFileCode = getFieldFromId("css_file_code", "css_files", "css_file_code", $GLOBALS['gPageRow']['css_file_id']);
		if (!empty($cssFileCode)) {
			?>
			<link media='screen' type="text/css" rel="stylesheet" href="<?= autoVersion(getCSSFilename($cssFileCode)) ?>"/>
			<?php
		}
	}

	function onLoadJavascript() {
		$this->iPageObject->onLoadPageJavascript();
	}

	function javascript() {
		$this->iPageObject->pageJavascript();
	}

	function internalCSS() {
		$this->iPageObject->internalPageCSS();
	}

	function mainContent() {
		if (Page::pageIsUnderMaintenance()) {
			return;
		}
		if (!$this->iPageObject->mainContent()) {
			echo $this->iPageObject->getPageData("content");
			echo $this->iPageObject->getPageData("after_form_content");
		}
	}

	function hiddenElements() {
		$this->iPageObject->hiddenElements();
	}

	function jqueryTemplates() {
		?>
		<div id="_templates">
			<?php
			$this->iPageObject->jqueryTemplates();
			?>
		</div>
		<?php
	}
}
