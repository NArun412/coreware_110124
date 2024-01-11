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

/* page instructions
<p>These are the page instructions.</p>
*/

/* text instructions
<p>These are the text instructions.</p>
*/

$GLOBALS['gPageCode'] = "PAGEMAINT";
require_once "shared/startup.inc";

class PageMaintenancePage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$filters = array();

			if ($GLOBALS['gDefaultClientId'] == $GLOBALS['gClientId'] && $GLOBALS['gUserRow']['superuser_flag']) {
				$filters['public_access'] = array('form_label' => "Public Accessible", "data_type" => "tinyint", "where" => "page_id in (select page_id from page_access where public_access = 1)", "conjunction" => "and");
				$filters['user_access'] = array('form_label' => "User Accessible", "data_type" => "tinyint", "where" => "page_id in (select page_id from page_access where all_user_access = 1)", "conjunction" => "and");
				$filters['admin_access'] = array('form_label' => "All Admin Accessible", "data_type" => "tinyint", "where" => "page_id in (select page_id from page_access where administrator_access = 1)", "conjunction" => "and");
			}

			$pageTags = array();
			$resultSet = executeQuery("select distinct(page_tag) from pages where client_id = ? and page_tag is not null order by page_tag", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$pageTags[$row['page_tag']] = $row['page_tag'];
			}
			if (!empty($pageTags)) {
				$filters['page_tag'] = array('form_label' => "Page Tag", "data_type" => "select", "where" => "page_tag = %filter_value%", "choices" => $pageTags, "conjunction" => "and");
			}

			$filters['template_header'] = array("form_label" => "Templates", "data_type" => "header");
			$resultSet = executeQuery("select * from templates where client_id = ? or client_id = ? order by sort_order,description", $GLOBALS['gClientId'], $GLOBALS['gDefaultClientId']);
			while ($row = getNextRow($resultSet)) {
				$filters['template_' . $row['template_id']] = array("form_label" => $row['description'], "where" => "template_id = " . $row['template_id'], "data_type" => "tinyint");
			}

			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
			if ($GLOBALS['gPermissionLevel'] > _READONLY) {
				$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("duplicate" => array("icon" => "fad fa-copy", "label" => getLanguageText("Duplicate"),
					"disabled" => false)));
			}
			$this->iTemplateObject->getTableEditorObject()->setFileUpload(true);
			$pageIds = array();
			$resultSet = executeQuery("select * from user_editable_pages where user_id = ?", $GLOBALS['gUserId']);
			while ($row = getNextRow($resultSet)) {
				$pageIds[] = $row['page_id'];
			}
			if (!$GLOBALS['gUserRow']['superuser_flag'] && (hasPageCapability("NO_ADD") || count($pageIds) > 0)) {
				$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("add"));
			}
			$this->iTemplateObject->getTableEditorObject()->addCustomAction("set_page_tag", "Set Page Tag for Selected Pages");
			$this->iTemplateObject->getTableEditorObject()->addCustomAction("clear_page_tag", "Clear Page Tag for Selected Pages");
		}
	}

	function userSubsystemChoices($showInactive = false) {
		$subsystemChoices = array();
		$resultSet = executeQuery("select * from subsystems where subsystem_id in (select subsystem_id from subsystem_users where user_id = ?) order by sort_order,description", $GLOBALS['gUserId']);
		while ($row = getNextRow($resultSet)) {
			if (empty($row['inactive']) || $showInactive) {
				$subsystemChoices[$row['subsystem_id']] = array("key_value" => $row['subsystem_id'], "description" => $row['description'], "inactive" => $row['inactive'] == 1);
			}
		}
		freeResult($resultSet);
		return $subsystemChoices;
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("page_data_changes", "data_type", "custom");
		$this->iDataSource->addColumnControl("page_data_changes", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("page_data_changes", "column_list", "description,change_date");
		$this->iDataSource->addColumnControl("page_data_changes", "filter_where", "change_date > current_date");
		$this->iDataSource->addColumnControl("page_data_changes", "form_label", "Upcoming Changes");
		$this->iDataSource->addColumnControl("page_data_changes", "list_table", "page_data_changes");
		$this->iDataSource->addColumnControl("page_data_changes", "readonly", true);

		$this->iDataSource->addColumnControl("template_id", "include_default_client", "true");
		$this->iDataSource->addColumnControl("date_created", "default_value", date("m/d/Y"));
		$this->iDataSource->addColumnControl("creator_user_id", "default_value", $GLOBALS['gUserId']);
		$this->iDataSource->addColumnControl("date_created", "readonly", true);
		$this->iDataSource->addColumnControl("creator_user_id", "readonly", true);
		$this->iDataSource->addColumnControl("creator_user_id", "get_choices", "userChoices");

		$this->iDataSource->addColumnControl("link_name", "not_null", false);
		$this->iDataSource->addColumnControl("link_name", "data_type", "varchar");
		$this->iDataSource->addColumnControl("link_name", "css-width", "500px");

		$this->iDataSource->addColumnControl("page_url", "data_type", "varchar");
		$this->iDataSource->addColumnControl("page_url", "select_value", "concat('" . getDomainName() . "/',coalesce(link_name,script_filename))");
		$this->iDataSource->addColumnControl("page_url", "form_label", "Page URL");

		$this->iDataSource->addColumnControl("link_url", "data_type", "varchar");
		$this->iDataSource->addColumnControl("link_url", "css-width", "500px");
		$this->iDataSource->addColumnControl("link_url", "form_label", "Redirect URL");
		$this->iDataSource->addColumnControl("link_url", "help_label", "Access to this page will result in a 301 redirect to this URL");

		$this->iDataSource->addColumnControl("sass_headers", "data_type", "text");
		$this->iDataSource->addColumnControl("sass_headers", "readonly", true);
		$this->iDataSource->addColumnControl("sass_headers", "form_label", "SASS Headers");
		$this->iDataSource->addColumnControl("sass_headers", "help_label", "Defined in Template");

		$this->iDataSource->addColumnControl("page_pattern_id", "readonly", true);

		$this->iDataSource->addColumnControl("page_maintenance_schedules", "data_type", "custom");
		$this->iDataSource->addColumnControl("page_maintenance_schedules", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("page_maintenance_schedules", "list_table", "page_maintenance_schedules");
		$this->iDataSource->addColumnControl("page_maintenance_schedules", "form_label", "Maintenance Schedules");
		$this->iDataSource->addColumnControl("page_maintenance_schedules", "list_table_controls", array("user_group_id" => array("empty_text" => "[User Group]"), "start_date" => array("inline-width" => "95px"), "end_date" => array("inline-width" => "95px")));
		$whereStatement = "";
		if (hasPageCapability("LIMIT_ACCESS") && !$GLOBALS['gUserRow']['superuser_flag']) {
			$this->iDataSource->addColumnControl("subsystem_id", "not_null", "true");
			$subsystemIds = array();
			$resultSet = executeQuery("select subsystem_id from subsystem_users where user_id = ?", $GLOBALS['gUserId']);
			while ($row = getNextRow($resultSet)) {
				$subsystemIds[] = $row['subsystem_id'];
			}
			if (empty($subsystemIds)) {
				$subsystemIds[] = "0";
			}
			$whereStatement = "subsystem_id is not null and subsystem_id in (" . implode(",", $subsystemIds) . ")";
			$this->iDataSource->addColumnControl("subsystem_id", "not_null", "true");
			$this->iDataSource->addColumnControl("subsystem_id", "get_choices", "userSubsystemChoices");
		}
		$pageIds = array();
		$resultSet = executeQuery("select * from user_editable_pages where user_id = ?", $GLOBALS['gUserId']);
		while ($row = getNextRow($resultSet)) {
			$pageIds[] = $row['page_id'];
		}
		if (!empty($pageIds)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "page_id in (" . implode(",", $pageIds) . ")";
		}
		if (!empty($whereStatement)) {
			$this->iDataSource->setFilterWhere($whereStatement);
		}
		$this->iDataSource->getPrimaryTable()->setSubtables(array("menu_items", "page_text_chunks", "page_meta_tags", "page_notifications", "page_data", "page_controls",
			"page_functions", "selected_rows", "user_access", "page_access", "web_user_pages", "page_sections", "user_editable_pages", "user_group_access", "page_aliases"));
		$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => "page_data",
			"referenced_column_name" => "page_id", "foreign_key" => "page_id",
			"description" => "text_data"));
		if ($_GET['url_page'] == "show" && $_GET['subaction'] == "duplicate" && $GLOBALS['gPermissionLevel'] > _READONLY) {
			$pageId = getFieldFromId("page_id", "pages", "page_id", $_GET['primary_id'], "client_id is not null");
			if (empty($pageId)) {
				return;
			}
			$resultSet = executeQuery("select * from pages where page_id = ?", $pageId);
			$pageRow = getNextRow($resultSet);
			$originalPageCode = $pageRow['page_code'];
			$originalLinkName = $pageRow['link_name'];
			$subNumber = 1;
			$queryString = "";
			foreach ($pageRow as $fieldName => $fieldData) {
				if (empty($queryString)) {
					$pageRow[$fieldName] = "";
				}
				if ($fieldName == "client_id") {
					$pageRow[$fieldName] = $GLOBALS['gClientId'];
				}
				$queryString .= (empty($queryString) ? "" : ",") . "?";
			}
			$newPageId = "";
			$pageRow['description'] .= " Copy";
			while (empty($newPageId)) {
				$pageRow['page_code'] = $originalPageCode . "_" . $subNumber;
				$pageRow['link_name'] = $originalLinkName . (empty($originalLinkName) ? "" : "-" . $subNumber);
				$pageRow['date_created'] = date("Y-m-d");
				$pageRow['creator_user_id'] = $GLOBALS['gUserId'];
				$resultSet = executeQuery("select * from pages where page_code = ? or (link_name is not null and link_name = ?)",
					$pageRow['page_code'], $pageRow['link_name']);
				if ($row = getNextRow($resultSet)) {
					$subNumber++;
					$pageRow['link_name'] = $originalLinkName . (empty($originalLinkName) ? "" : "-" . $subNumber);
					continue;
				}
				$resultSet = executeQuery("insert into pages values (" . $queryString . ")", $pageRow);
				if ($resultSet['sql_error_number'] == 1062) {
					$subNumber++;
					continue;
				}
				$newPageId = $resultSet['insert_id'];
			}
			$_GET['primary_id'] = $newPageId;
			$subTables = array("page_controls", "page_data", "page_functions", "page_meta_tags", "page_notifications", "page_text_chunks", "page_access", "page_sections");
			foreach ($subTables as $tableName) {
				$resultSet = executeQuery("select * from " . $tableName . " where page_id = ?", $pageId);
				while ($row = getNextRow($resultSet)) {
					$queryString = "";
					foreach ($row as $fieldName => $fieldData) {
						if (empty($queryString)) {
							$row[$fieldName] = "";
						}
						$queryString .= (empty($queryString) ? "" : ",") . "?";
					}
					$row['page_id'] = $newPageId;
					executeQuery("insert into " . $tableName . " values (" . $queryString . ")", $row);
				}
			}
		}
	}

	function internalCSS() {
		?>
        <style>
            #action_button_wrapper {
                position: absolute;
                right: 0;
                top: 0;
            }
            <?php if ($GLOBALS['gDevelopmentServer']) { ?>
            #validate_page {
                display: none;
            }

            #speed_page {
                display: none;
            }
            <?php } ?>
            #page_instructions, #text_instructions {
                margin: 20px;
            }

            #page_instructions p, #text_instructions p {
                padding: 0;
                font-size: 14px;
                color: rgb(0, 120, 20);
                font-weight: bold;
            }

            #page_instructions ul, #text_instructions ul {
                padding: 0;
                margin: 20px 0 10px 40px;
                color: rgb(0, 120, 20);
                list-style: disc;
            }

            #page_instructions li, #text_instructions li {
                font-size: 14px;
                padding-bottom: 10px;
            }

            #go_to_page {
                float: right;
            }

            #javascript_code {
                width: 1150px;
                height: 650px;
            }

            #css_content {
                width: 1150px;
                height: 650px;
                max-width: 100%;
            }

            #sass_headers {
                width: 1150px;
                height: 200px;
                max-width: 100%;
            }

            #potential_conflicts {
                color: rgb(192, 0, 0);
            }
        </style>
		<?php
	}

	function jqueryTemplates() {
		?>
        <div id="page_data_templates"></div>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
			<?php
			if ($GLOBALS['gPermissionLevel'] > _READONLY) {
			?>
            $(document).on("tap click", "#_duplicate_button", function () {
                const $primaryId = $("#primary_id");
                if (!empty($primaryId.val())) {
                    if (changesMade()) {
                        askAboutChanges(function () {
                            $('body').data('just_saved', 'true');
                            document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=show&subaction=duplicate&primary_id=" + $primaryId.val();
                        });
                    } else {
                        document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=show&subaction=duplicate&primary_id=" + $primaryId.val();
                    }
                }
                return false;
            });
			<?php } ?>
            $(document).on("tap click", "#validate_page", function () {
                if (!empty($("#primary_id").val())) {
                    const $linkName = $("#link_name");
                    const linkUrl = (empty($linkName.val()) ? $("#script_filename").val() : $linkName.val());
                    if (!empty(linkUrl)) {
                        const $domainName = $("#domain_name");
                        const $scriptArguments = $("#script_arguments");
                        window.open("http://validator.w3.org/check?uri=" + encodeURIComponent((empty($domainName.val()) ? window.location.hostname : $domainName.val()) + "/" + linkUrl + (empty($scriptArguments.val()) ? "" : "?" + $scriptArguments.val())));
                    }
                }
                return false;
            });
            $(document).on("tap click", "#speed_page", function () {
                if (!empty($("#primary_id").val())) {
                    const $linkName = $("#link_name");
                    const linkUrl = (empty($linkName.val()) ? $("#script_filename").val() : $linkName.val());
                    if (!empty(linkUrl)) {
                        const $domainName = $("#domain_name");
                        const $scriptArguments = $("#script_arguments");
                        window.open("https://developers.google.com/speed/pagespeed/insights/?url=" + encodeURIComponent((empty($domainName.val()) ? window.location.hostname : $domainName.val()) + "/" + linkUrl + (empty($scriptArguments.val()) ? "" : "?" + $scriptArguments.val())));
                    }
                }
                return false;
            });
            $(document).on("tap click", "#go_to_page", function () {
                if (!empty($("#primary_id").val())) {
                    const linkName = $("#link_name").val();
                    if (empty(linkName) && empty($("#script_filename").val())) {
                        return;
                    }
                    const linkUrl = (empty(linkName) ? $("#script_filename").val() : linkName);
                    if (!empty(linkUrl)) {
                        const $domainName = $("#domain_name");
                        const $scriptArguments = $("#script_arguments");
                        window.open(<?php if (!$GLOBALS['gDevelopmentServer']) { ?>(empty($domainName.val()) ? "" : "http://" + $domainName.val()) + <?php } ?>"/" + linkUrl + (empty($scriptArguments.val()) ? "" : "?" + $scriptArguments.val()));
                    }
                }
                return false;
            });
            $("#script_filename").change(function () {
                $("#page_instructions").html("");
                $("#text_instructions").html("");
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=change_script_filename", { script_filename: $("#script_filename").val() }, function (returnArray) {
                        if ("page_instructions" in returnArray) {
                            $("#page_instructions").html(returnArray['page_instructions']);
                        }
                        if ("text_instructions" in returnArray) {
                            $("#text_instructions").html(returnArray['text_instructions']);
                        }
                    });
                }
            });
            $("#meta_description").keydown(function () {
                limitText($(this), $("#_meta_description_character_count"), 255);
            }).keyup(function () {
                limitText($(this), $("#_meta_description_character_count"), 255);
            });
            $("#template_id").change(function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=change_template&template_id=" + $(this).val() + "&primary_id=" + $("#primary_id").val(), function (returnArray) {
                    if (!("error_message" in returnArray)) {
                        if ("page_data_div" in returnArray && "data_value" in returnArray['page_data_div']) {
                            $("#page_data_div").html(returnArray['page_data_div']['data_value']);
                        } else {
                            $("#page_data_div").html("");
                        }
                        if ("page_data_templates" in returnArray && "data_value" in returnArray['page_data_templates']) {
                            $("#page_data_templates").html(returnArray['page_data_templates']['data_value']);
                        } else {
                            $("#page_data_templates").html("");
                        }
                        if ("sass_headers" in returnArray) {
                            $("#sass_headers").val(returnArray['sass_headers']['data_value']);
                        }
                        afterGetRecord();
                    }
                });
            });
            $("#link_name").change(function () {
                $(this).val($(this).val().replace("wp-admin", ""));
                if ($(this).val() === "") {
                    $("#potential_conflicts").html("");
                } else {
                    setTimeout(function () {
                        loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=check_link_name&link_name=" + encodeURIComponent($("#link_name").val()) + "&primary_id=" + $("#primary_id").val(), function (returnArray) {
                            if ("potential_conflicts" in returnArray) {
                                $("#potential_conflicts").html(returnArray['potential_conflicts']);
                            } else {
                                $("#potential_conflicts").html("");
                            }
                        });
                    }, 200);
                }
            });
        </script>
		<?php
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "clear_page_tag":
			case "set_page_tag":
				$pageIds = array();
				$resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$pageIds[] = $row['primary_identifier'];
				}
				$count = 0;
				if (!empty($pageIds)) {
					$resultSet = executeQuery("update pages set page_tag = ? where client_id = ? and page_id in (" . implode(",", $pageIds) . ")", $_POST['_set_page_tag'], $GLOBALS['gClientId']);
					$count = $resultSet['affected_rows'];
				}
				$returnArray['info_message'] = $count . " pages updated";
				executeQuery("delete from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				ajaxResponse($returnArray);
				break;
			case "check_link_name":
				if (!empty($_GET['link_name'])) {
					$potentialConflicts = "";
					$resultSet = executeQuery("select * from pages where link_name = ? and client_id = ? and page_id <> ? and inactive = 0 order by domain_name",
						$_GET['link_name'], $GLOBALS['gClientId'], (empty($_GET['primary_id']) ? "0" : $_GET['primary_id']));
					while ($row = getNextRow($resultSet)) {
						if (empty($potentialConflicts)) {
							$potentialConflicts = "<p><span class='highlighted-text'>Potential Conflicts:</span><br>";
						} else {
							$potentialConflicts .= "<br>";
						}
						$potentialConflicts .= "Page '" . $row['description'] . "'" . (empty($row['domain_name']) ? "" : " for domain " . $row['domain_name']) .
							($row['inactive'] ? " (inactive)" : "") . " is accessed by /" . $row['link_name'];
					}
					if (!empty($potentialConflicts)) {
						$potentialConflicts .= "</p>";
						$returnArray['potential_conflicts'] = $potentialConflicts;
					}
				}
				ajaxResponse($returnArray);
				break;
			case "change_template":
				$returnArray['template_id'] = array("data_value" => $_GET['template_id']);
				$returnArray['primary_id'] = array("data_value" => $_GET['primary_id']);
				$sassHeaders = "";
				$resultSet = executeQuery("select * from sass_headers join template_sass_headers using (sass_header_id) where template_id = ?", $_GET['template_id']);
				while ($row = getNextRow($resultSet)) {
					$sassHeaders .= $row['content'] . "\n\n";
				}
				$returnArray['sass_headers'] = array("data_value" => $sassHeaders);

				$this->afterGetRecord($returnArray);
				ajaxResponse($returnArray);
				break;
			case "change_script_filename":
				$scriptFilename = $_POST['script_filename'];
				if ($scriptFilename && file_exists($GLOBALS['gDocumentRoot'] . "/" . $scriptFilename)) {
					try {
						$filename = $GLOBALS['gDocumentRoot'] . "/" . $scriptFilename;
						$handle = fopen($filename, 'r');
						$programLines = array();
						while ($thisLine = fgets($handle)) {
							$programLines[] = $thisLine;
						}
						fclose($handle);
						$pageInstructions = "";
						$useLine = false;
						foreach ($programLines as $thisLine) {
							if ($useLine) {
								if (trim($thisLine) == "*/") {
									break;
								} else {
									$pageInstructions .= $thisLine;
								}
							} else if (strtolower(trim($thisLine)) == "/* page instructions") {
								$useLine = true;
							}
						}
						$returnArray['page_instructions'] = $pageInstructions;
						$textInstructions = "";
						$useLine = false;
						foreach ($programLines as $thisLine) {
							if ($useLine) {
								if (trim($thisLine) == "*/") {
									break;
								} else {
									$textInstructions .= $thisLine;
								}
							} else if (strtolower(trim($thisLine)) == "/* text instructions") {
								$useLine = true;
							}
						}
						$returnArray['text_instructions'] = $textInstructions;
					} catch (Exception $e) {
						$returnArray['page_instructions'] = "";
						$returnArray['text_instructions'] = "";
					}
				} else {
					$returnArray['page_instructions'] = "";
					$returnArray['text_instructions'] = "";
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function afterGetRecord(&$returnArray) {
		$resultSet = executeQuery("select count(*) from page_data_changes where page_id = ? and change_date > current_date", $returnArray['primary_id']['data_value']);
		if ($row = getNextRow($resultSet)) {
			if ($row['count(*)'] > 0) {
				$returnArray['info_message'] = "Data changes are pending. See Notifications tab.";
			}
		}
		$sassHeaders = "";
		$resultSet = executeQuery("select * from sass_headers join template_sass_headers using (sass_header_id) where template_id = ?", $returnArray['template_id']['data_value']);
		while ($row = getNextRow($resultSet)) {
			$sassHeaders .= $row['content'] . "\n\n";
		}
		$returnArray['sass_headers'] = array("data_value" => $sassHeaders);
		$rawCssContent = $sassHeaders . $returnArray['css_content']['data_value'];

		if (!empty($rawCssContent)) {
			try {
				$scss = new ScssPhp\ScssPhp\Compiler();
				$cssContentLines = getContentLines($rawCssContent);
				$thisChunk = "";
				foreach ($cssContentLines as $thisLine) {
					if (substr($thisLine, 0, 1) == "%" && substr($thisLine, -1) == "%") {
						continue;
					}
					$thisChunk .= $thisLine . "\n";
				}
				$scss->compile($thisChunk);
			} catch (Exception $e) {
				$returnArray['error_message'] = "Style processing error: " . $e->GetMessage();
			}
		}
		if (!$GLOBALS['gUserRow']['superuser_flag']) {
			$restrictedAccess = getFieldFromId("restricted_access", "subsystems", "subsystem_id", $returnArray['subsystem_id']['data_value']);
			if (!empty($restrictedAccess)) {
				$userId = getFieldFromId("user_id", "subsystem_users", "subsystem_id", $returnArray['subsystem_id']['data_value'], "user_id = ?", $GLOBALS['gUserId']);
				if (empty($userId)) {
					$returnArray['_permission'] = array("data_value" => _READONLY);
				}
			}
		}
		$scriptFilename = $returnArray['script_filename']['data_value'];
		if ($scriptFilename && file_exists($GLOBALS['gDocumentRoot'] . "/" . $scriptFilename)) {
			try {
				$filename = $GLOBALS['gDocumentRoot'] . "/" . $scriptFilename;
				$handle = fopen($filename, 'r');
				$programLines = array();
				while ($thisLine = fgets($handle)) {
					$programLines[] = $thisLine;
				}
				fclose($handle);
				$pageInstructions = "";
				$useLine = false;
				foreach ($programLines as $thisLine) {
					if ($useLine) {
						if (trim($thisLine) == "*/") {
							break;
						} else {
							$pageInstructions .= $thisLine;
						}
					} else if (strtolower(trim($thisLine)) == "/* page instructions") {
						$useLine = true;
					}
				}
				$returnArray['page_instructions'] = array("data_value" => $pageInstructions);
				$textInstructions = "";
				$useLine = false;
				foreach ($programLines as $thisLine) {
					if ($useLine) {
						if (trim($thisLine) == "*/") {
							break;
						} else {
							$textInstructions .= $thisLine;
						}
					} else if (strtolower(trim($thisLine)) == "/* text instructions") {
						$useLine = true;
					}
				}
				$returnArray['text_instructions'] = array("data_value" => $textInstructions);
			} catch (Exception $e) {
				$returnArray['page_instructions'] = array('data_value' => "");
				$returnArray['text_instructions'] = array('data_value' => "");
			}
		} else {
			$returnArray['page_instructions'] = array('data_value' => "");
			$returnArray['text_instructions'] = array('data_value' => "");
		}
		$templateId = $returnArray['template_id']['data_value'];
		if (empty($templateId)) {
			$returnArray['page_data_div'] = array();
			$returnArray['page_data_div']['data_value'] = "";
			return;
		}
		ob_start();
		$returnArray['page_data_div'] = array();

		$templateDataArray = array();
		$resultSet = executeQuery("select * from template_data,template_data_uses where template_data.template_data_id = template_data_uses.template_data_id and " .
			"template_id = ? and inactive = 0 order by sequence_number,description", $templateId);
		while ($row = getNextRow($resultSet)) {
			$templateDataArray[$row['template_data_id']] = $row;
		}
		$templates = "";
		$dataArray = array();
		$dataArray['select_values'] = array();
		while (count($templateDataArray) > 0) {
			$row = array_shift($templateDataArray);
			Template::massageTemplateColumnData($row);
			$column = new DataColumn(strtolower($row['column_name']));
			foreach ($row as $fieldName => $fieldData) {
				$column->setControlValue($fieldName, $fieldData);
			}
			if (!empty($row['css_content'])) {
				echo "<style>#" . $row['column_name'] . "{" . $row['css_content'] . "}</style>\n";
			}
			?>
            <div class='basic-form-line' id="_row_<?= $row['column_name'] ?>">
				<?php
				if ((empty($row['group_identifier']) && $row['allow_multiple'] == 0) || $row['subtype'] == "image") {
					$templateDirectory = getFieldFromId("directory_name", "templates", "template_id", $templateId, "client_id = ? or client_id = ?", $GLOBALS['gClientId'], $GLOBALS['gDefaultClientId']);
					if (!empty($templateDirectory) && $row['data_type'] == "text") {
						if (file_exists($GLOBALS['gDocumentRoot'] . "/templates/" . $templateDirectory . "/ckeditor.css")) {
							$column->setControlValue("data-contents_css", "/templates/" . $templateDirectory . "/ckeditor.css");
						}
						if (file_exists($GLOBALS['gDocumentRoot'] . "/templates/" . $templateDirectory . "/ckeditor.js")) {
							$column->setControlValue("data-styles_set", "/templates/" . $templateDirectory . "/ckeditor.js");
						}
					}
					echo($row['data_type'] == "tinyint" ? "<label></label>" : "<label id='_label_" . $row['column_name'] . "' for='" . $row['column_name'] . "' " . ($row['not_null'] ? " class='required-label'" : "") . " >" . htmlText($row['description']) . "</label>");
					$column->setControlValue("form_label", $row['description']);
					echo $column->getControl();
					$resultSet = executeQuery("select * from page_data where page_id = ? and template_data_id = ? and sequence_number is null",
						$returnArray['primary_id']['data_value'], $row['template_data_id']);
					if ($dataRow = getNextRow($resultSet)) {
						$fieldData = $dataRow[$row['data_field']];
					} else {
						$fieldData = "";
					}
					$fieldName = $row['column_name'];
					switch ($row['data_type']) {
						case "datetime":
						case "date":
							$fieldData = (empty($fieldData) ? "" : date("m/d/Y" . ($row['data_type'] == "datetime" ? " g:i:sa" : ""), strtotime($fieldData)));
							break;
						case "tinyint":
							$fieldData = ($fieldData == 1 ? 1 : 0);
							break;
					}
					switch ($row['subtype']) {
						case "image":
							$dataArray[$fieldName . "_file"] = array("data_value" => "", "crc_value" => getCrcValue(""));
							$dataArray[$fieldName . "_view"] = array("data_value" => getImageFilename($fieldData));
							$dataArray[$fieldName . "_filename"] = array("data_value" => getFieldFromId("filename", "images", "image_id", $fieldData));
							$dataArray["remove_" . $fieldName] = array("data_value" => "0", "crc_value" => getCrcValue("0"));
							$dataArray['select_values'][$fieldName] = array(array("key_value" => $fieldData, "description" => getFieldFromId("description", "images", "image_id", $fieldData), "inactive" => "0"));
							break;
					}
					$dataArray[$row['column_name']] = array("data_value" => $fieldData, "crc_value" => getCrcValue($fieldData));
				} else {
					if ($row['data_name'] == "content") {
						?>
                        <p>
                            <label><?= htmlText(empty($row['group_identifier']) ? $row['description'] : $row['group_identifier']) ?></label>
                        </p>
						<?php
					} else {
						?>
                        <label><?= htmlText(empty($row['group_identifier']) ? $row['description'] : $row['group_identifier']) ?></label>
						<?php
					}
					$rowArray = array($column->getControlValue("column_name") => $column);
					if (!empty($row['group_identifier'])) {
						foreach ($templateDataArray as $index => $templateDataRow) {
							if ($templateDataRow['group_identifier'] == $row['group_identifier']) {
								Template::massageTemplateColumnData($templateDataRow);
								$thisColumn = new DataColumn(strtolower($templateDataRow['column_name']));
								foreach ($templateDataRow as $fieldName => $fieldData) {
									$thisColumn->setControlValue($fieldName, $fieldData);
								}
								$rowArray[$thisColumn->getControlValue("column_name")] = $thisColumn;
								unset($templateDataArray[$index]);
							}
						}
					}
					$multipleDataRow = array();
					foreach ($rowArray as $column) {
						$templateDataId = $column->getControlValue('template_data_id');
						$column->setControlValue("form_label", $column->getControlValue("description"));
						$dataField = $column->getControlValue('data_field');
						$resultSet = executeQuery("select sequence_number,$dataField from page_data where page_id = ? and template_data_id = ? and sequence_number is not null order by sequence_number",
							$returnArray['primary_id']['data_value'], $templateDataId);
						while ($dataRow = getNextRow($resultSet)) {
							if (!array_key_exists($dataRow['sequence_number'], $multipleDataRow)) {
								$multipleDataRow[$dataRow['sequence_number']] = array();
							}
							$multipleDataRow[$dataRow['sequence_number']][$column->getControlValue('column_name')] = array("data_value" => $dataRow[$dataField], "crc_value" => getCrcValue($dataRow[$dataField]));
						}
					}
					$groupIdentifier = $column->getControlValue("group_identifier");
					$controlName = (empty($groupIdentifier) ? $row['data_name'] : str_replace(" ", "_", strtolower($groupIdentifier)));
					$dataArray[$controlName] = array("data_value" => $multipleDataRow);
					$editableListColumn = new DataColumn($controlName);
					$editableList = new EditableList($editableListColumn, $this);
					$editableList->setColumnList($rowArray);
					echo $editableList->getControl();
					$templates .= $editableList->getTemplate();
				}
				?>
            </div>
			<?php
		}
		?>
        <script type="text/javascript">
            dataArray = <?= jsonEncode($dataArray) ?>;
        </script>
		<?php
		$returnArray['page_data_div']['data_value'] = ob_get_clean();
		$returnArray['page_data_templates']['data_value'] = $templates;

	}

	function javascript() {
		?>
        <script>
            function customActions(actionName) {
                if (actionName === "set_page_tag") {
                    $('#_set_page_tag_dialog').dialog({
                        closeOnEscape: true,
                        draggable: false,
                        modal: true,
                        resizable: false,
                        position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                        width: 600,
                        title: 'Set Page Tag',
                        buttons: {
                            Save: function (event) {
                                if ($("#_set_page_tag_form").validationEngine('validate')) {
                                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=set_page_tag", $("#_set_page_tag_form").serialize(), function (returnArray) {
                                        getDataList();
                                    });
                                    $("#_set_page_tag_dialog").dialog('close');
                                }
                            },
                            Cancel: function (event) {
                                $("#_set_page_tag_dialog").dialog('close');
                            }
                        }
                    });
                    return true;
                }
                if (actionName === "clear_page_tag") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=set_page_tag", { _set_page_tag: "" }, function (returnArray) {
                        getDataList();
                    });
                    return true;
                }
            }

            function limitText(limitField, limitCount, limitNum) {
                if (limitField.val().length > limitNum) {
                    limitField.val(limitField.val().substring(0, limitNum));
                }
                limitCount.html("<?= getLanguageText("Character Count") ?>: " + (limitField.val().length));
            }

            let dataArray = [];

            function afterGetRecord(returnArray) {
                if (!empty(returnArray) && "page_pattern_id" in returnArray && !empty(returnArray['page_pattern_id']['data_value'])) {
                    $("#pattern_page_id").attr("onClick", "return false");
                } else {
                    $("#pattern_page_id").prop("onClick", "");
                }
                limitText($("#meta_description"), $("#_meta_description_character_count"), 255);
                $("#page_data_div .datepicker").datepicker({
                    showOn: "button",
                    buttonText: "<span class='fad fa-calendar-alt'></span>",
                    constrainInput: false,
                    dateFormat: "mm/dd/y",
                    yearRange: "c-100:c+10"
                });
                $("#page_data_div .required-label").append("<span class='required-tag'>*</span>");
                $("#page_data_div a[rel^='prettyPhoto']").prettyPhoto({
                    social_tools: false,
                    default_height: 480,
                    default_width: 854,
                    deeplinking: false
                });
				<?php if ($GLOBALS['gPermissionLevel'] > _READONLY) { ?>
                if (empty($("#primary_id").val())) {
                    disableButtons($("#_duplicate_button"));
                } else {
                    enableButtons($("#_duplicate_button"));
                }
				<?php } ?>
                $("#link_name").trigger("change");
                createEditors();
                setTimeout("setPageData()", 100);
            }

            function setPageData() {
                if ("select_values" in dataArray) {
                    for (let index in dataArray['select_values']) {
                        const elementId = "#" + index;
                        const $element = $(elementId);
                        if (!$element.is("select")) {
                            continue;
                        }
                        $element.find("option").each(function () {
                            if ($(this).data("inactive") === "1") {
                                $(this).remove();
                            }
                        });
                        for (let subindex in dataArray['select_values'][index]) {
                            if ($(elementId + " option[value='" + dataArray['select_values'][index][subindex]['key_value'] + "']").length === 0) {
                                const inactive = ("inactive" in dataArray['select_values'][index][subindex] ? dataArray['select_values'][index][subindex]['inactive'] : "0");
                                $element.append($("<option></option>").data("inactive", inactive).val(dataArray['select_values'][index][subindex]['key_value']).text(dataArray['select_values'][index][subindex]['description']));
                            }
                        }
                    }
                }
                for (let i in dataArray) {
                    if (typeof dataArray[i] == "object" && "data_value" in dataArray[i]) {
                        const $radioValue = $("input[type=radio][name='" + i + "']");
                        if ($radioValue.length > 0) {
                            $radioValue.prop("checked", false);
                            $("input[type=radio][name='" + i + "'][value='" + dataArray[i]['data_value'] + "']").prop("checked", true);
                        } else if ($("#" + i).is("input[type=checkbox]")) {
                            $("#" + i).prop("checked", !empty(dataArray[i].data_value));
                        } else if ($("#" + i).is("a")) {
                            $("#" + i).attr("href", dataArray[i].data_value).css("display", (empty(dataArray[i].data_value) ? "none" : "inline"));
                        } else if ($("#_" + i + "_table").is(".editable-list")) {
                            for (let j in dataArray[i].data_value) {
                                addEditableListRow(i, dataArray[i]['data_value'][j]);
                            }
                        } else {
                            $("#" + i).val(dataArray[i].data_value);
                        }
                        if ("crc_value" in dataArray[i]) {
                            $("#" + i).data("crc_value", dataArray[i]['crc_value']);
                        } else {
                            $("#" + i).removeData("crc_value");
                        }
                    }
                }
                $("#page_data_div .view-image-link").each(function () {
                    $(this).show();
                    if ($(this).closest(".basic-form-line").find("input[type=hidden]").length === 1 && empty($(this).closest(".basic-form-line").find("input[type=hidden]").val())) {
                        $(this).hide();
                    }
                    if ($(this).closest(".basic-form-line").find("select").length === 1 && empty($(this).closest(".basic-form-line").find("select").val())) {
                        $(this).hide();
                    }
                });
                $(".ace-javascript-editor,.ace-css-editor,.ace-html-editor").each(function () {
                    const elementId = $(this).data("element_id");
                    if (empty(elementId)) {
                        return true;
                    }
                    const javascriptEditor = ace.edit(elementId + "-ace_editor");
                    const $element = $("#" + elementId);
                    if ($element.length > 0 && !empty(javascriptEditor)) {
                        javascriptEditor.setValue($element.val(), 1);
                    }
                });
            }
        </script>
		<?php
	}

	function beforeDeleteRecord($pageId) {
		executeQuery("delete from menu_contents where menu_item_id in (select menu_item_id from menu_items where page_id = ?)", $pageId);
		return true;
	}

	function beforeSaveChanges(&$nameValues) {
		if (!empty($nameValues['page_pattern_id'])) {
			$nameValues['pattern_page'] = "";
		}
		$rawCssContent = "";
		$resultSet = executeQuery("select * from sass_headers join template_sass_headers using (sass_header_id) where template_id = ?", $nameValues['template_id']);
		while ($row = getNextRow($resultSet)) {
			$rawCssContent .= $row['content'] . "\n\n";
		}
		$rawCssContent .= $nameValues['css_content'];

		if (!empty($rawCssContent)) {
			$scss = new ScssPhp\ScssPhp\Compiler();
			try {
				$cssContentLines = getContentLines($rawCssContent);
				$thisChunk = "";
				foreach ($cssContentLines as $thisLine) {
					if (substr($thisLine, 0, 1) == "%" && substr($thisLine, -1) == "%") {
						continue;
					}
					$thisChunk .= $thisLine . "\n";
				}
				$scss->compile($thisChunk);
			} catch (Exception $e) {
				return ("Style processing error: " . $e->GetMessage());
			}
		}
		if (empty($nameValues['primary_id']) && empty($nameValues['page_code'])) {
			$nameValues['page_code'] = strtoupper(getRandomString());
		}
		foreach ($nameValues as $fieldName => $fieldContent) {
			$nameValues[$fieldName] = processBase64Images($fieldContent);
		}

		return true;
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		removeCachedData($GLOBALS['gPrimaryDatabase']->getName() . "-page_row_by_id", $nameValues['primary_id'], true);
		removeCachedData($GLOBALS['gPrimaryDatabase']->getName() . "-page_row_by_code", $nameValues['page_code'], true);
		removeCachedData("initialized_template_page_data", $nameValues['primary_id']);
		removeCachedData("all_page_codes", "");
		$templateId = $nameValues['template_id'];
		$pageDataSource = new DataSource("page_data");
		$pageDataSource->disableTransactions();
		executeQuery("update template_data set allow_multiple = 1 where group_identifier is not null");
		executeQuery("update template_data set allow_multiple = 0,group_identifier = null where data_type = 'image'");
		$templateDataSet = executeQuery("select * from template_data,template_data_uses where template_data.template_data_id = template_data_uses.template_data_id and template_id = ? and inactive = 0 order by sequence_number,description", $templateId);
		while ($templateDataRow = getNextRow($templateDataSet)) {
			if ($templateDataRow['data_name'] == "primary_table_name" && !$GLOBALS['gUserRow']['superuser_flag']) {
				continue;
			}
			$dataName = "template_data-" . $templateDataRow['data_name'] . "-" . $templateDataRow['template_data_id'];
			if ($templateDataRow['allow_multiple'] == 0) {
				$dataValue = $_POST[$dataName];
				$resultSet = executeQuery("select * from page_data where page_id = ? and template_data_id = ? and sequence_number is null",
					$nameValues['primary_id'], $templateDataRow['template_data_id']);
				if ($row = getNextRow($resultSet)) {
					if (empty($dataValue)) {
						if (!$pageDataSource->deleteRecord(array("primary_id" => $row['page_data_id'], "ignore_subtables" => array("images")))) {
							return $pageDataSource->getErrorMessage();
						}
					}
				} else {
					$pageDataSource->setPrimaryId("");
					$row = array("page_id" => $nameValues['primary_id'], "template_data_id" => $templateDataRow['template_data_id']);
				}
				if (!empty($dataValue)) {
					$fieldName = getFieldFromDataType($templateDataRow['data_type']);
					$row[$fieldName] = $dataValue;
					if (!$pageDataSource->saveRecord(array("name_values" => $row, "primary_id" => $row['page_data_id']))) {
						return $pageDataSource->getErrorMessage();
					}
				}
			} else {
				$controlName = (empty($templateDataRow['group_identifier']) ? $templateDataRow['data_name'] : str_replace(" ", "_", strtolower($templateDataRow['group_identifier'])));
				$dataName = $controlName . "_" . $dataName;
				$sequenceNumberArray = array();
				foreach ($_POST as $fieldName => $dataValue) {
					if (substr($fieldName, 0, strlen($dataName . "-")) == $dataName . "-") {
						$sequenceNumber = substr($fieldName, strlen($dataName . "-"));
						if (!is_numeric($sequenceNumber)) {
							continue;
						}
						$sequenceNumberArray[] = $sequenceNumber;
						$resultSet = executeQuery("select * from page_data where page_id = ? and template_data_id = ? and sequence_number = ?",
							$nameValues['primary_id'], $templateDataRow['template_data_id'], $sequenceNumber);
						if ($row = getNextRow($resultSet)) {
							$pageDataSource->setPrimaryId($row['page_data_id']);
							if (empty($dataValue)) {
								if (!$pageDataSource->deleteRecord()) {
									return $pageDataSource->getErrorMessage();
								}
							}
						} else {
							$pageDataSource->setPrimaryId("");
							$row = array("page_id" => $nameValues['primary_id'], "template_data_id" => $templateDataRow['template_data_id'], "sequence_number" => $sequenceNumber);
						}
						if (!empty($dataValue)) {
							$fieldName = getFieldFromDataType($templateDataRow['data_type']);
							$row[$fieldName] = $dataValue;
							if (!$pageDataSource->saveRecord(array("name_values" => $row, "primary_id" => $row['page_data_id']))) {
								return $pageDataSource->getErrorMessage();
							}
						}
					}
				}
				$sequenceNumberList = implode(",", $sequenceNumberArray);
				executeQuery("delete from page_data where page_id = ? and template_data_id = ?" .
					(empty($sequenceNumberList) ? "" : " and sequence_number not in ($sequenceNumberList)"),
					$nameValues['primary_id'], $templateDataRow['template_data_id']);
			}
		}
		removeCachedData("page_contents", $nameValues['page_code']);
		return true;
	}

	function hiddenElements() {
		?>
        <div id="_set_page_tag_dialog" class="dialog-box">
            <form id="_set_page_tag_form">
                <div class="basic-form-line" id="_page_tag_row">
                    <label for="page_tag">Page Tag</label>
                    <input type="text" size="50" id="_set_page_tag" name="_set_page_tag" class="validate[required]"/>
                </div>
            </form>
        </div>
		<?php
	}

}

$pageObject = new PageMaintenancePage("pages");
$pageObject->displayPage();
