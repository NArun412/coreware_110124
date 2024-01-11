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

	function setup() {
		if (empty($_GET['url_action']) && empty($_GET['url_page'])) {
			setUserPreference("MAINTENANCE_START_ROW", 0, $GLOBALS['gPageRow']['page_code']);
		}
		$primaryTableName = $this->iPageObject->getPrimaryTableName();
		if (!empty($primaryTableName)) {
			$this->iDataSource = new DataSource($primaryTableName);
			$this->iPageObject->setDataSource($this->iDataSource);
			$this->iPageObject->setDatabase($this->iDataSource->getDatabase());
			$this->iPageObject->massageDataSource();
			if (function_exists("_localServerMassageDataSource")) {
				_localServerMassageDataSource($this->iPageObject);
			}
			addDataLimitations($this->iDataSource);
			$this->iDataSource->getPageControls();
			if (!empty($_GET['primary_id'])) {
				$primaryId = getFieldFromId($this->iDataSource->getPrimaryTable()->getPrimaryKey(),
					$this->iDataSource->getPrimaryTable()->getName(), $this->iDataSource->getPrimaryTable()->getPrimaryKey(),
					$_GET['primary_id'], ($this->iDataSource->getPrimaryTable()->isLimitedByClient() || !$this->iDataSource->getPrimaryTable()->columnExists("client_id") ? "" : "client_id is not null"));
				if (empty($primaryId)) {
					$this->iErrorMessage = getSystemMessage("not_found");
					$_GET['url_page'] = "";
				} else {
					$this->iDataSource->setPrimaryId($primaryId);
				}
			}
		} else {
			$this->iPageObject->setDatabase($GLOBALS['gPrimaryDatabase']);
		}
		$this->iPageObject->executeSubaction();
		if ($this->iDataSource) {
			switch ($_GET['url_page']) {
				case "show":
				case "new":
					$this->iTableEditorObject = new MaintenanceForm($this->iDataSource);
					break;
				case "guisort":
					$this->iTableEditorObject = new VisualSort($this->iDataSource);
					break;
				case "spreadsheet":
					$this->iTableEditorObject = new Spreadsheet($this->iDataSource);
					break;
				case "resetsort":
					executeQuery("update " . $this->iDataSource->getPrimaryTable()->getName() . " set sort_order = 0");
				default:
					$this->iTableEditorObject = new MaintenanceList($this->iDataSource);
					break;
			}
		}
		if ($this->iTableEditorObject) {
			$this->iTableEditorObject->setPageObject($this->iPageObject);
		}
		$this->iPageObject->setup();
		if (function_exists("_localServerSetup")) {
			_localServerSetup($this->iPageObject);
		}
	}

	function getTableEditorObject() {
		return $this->iTableEditorObject;
	}

	function executeUrlActions() {
		switch ($_GET['url_action']) {
			case "ibuwuyiqeuig":
				if (!empty($_SESSION['original_user_id'])) {
					$newUserId = $_SESSION['original_user_id'];
					$_SESSION['original_user_id'] = "";
					unset($_SESSION['original_user_id']);
					saveSessionData();
					$resultSet = executeQuery("select * from users where user_id = ? and inactive = 0 and client_id = ?", $newUserId, $GLOBALS['gClientId']);
					if ($row = getNextRow($resultSet)) {
						login($row['user_id']);
					}
				}
				break;
			case "get_sort_list":
				if (!method_exists($this->iPageObject, "getSortList") || !$this->iPageObject->getSortList()) {
					if ($this->iTableEditorObject) {
						$this->iTableEditorObject->getSortList();
					}
				}
				break;
			case "preferences":
				if (!method_exists($this->iPageObject, "setPreferences") || !$this->iPageObject->setPreferences()) {
					if ($this->iTableEditorObject) {
						$this->iTableEditorObject->setPreferences();
					}
				}
				break;
			case "get_data_list":
				if (!method_exists($this->iPageObject, "getDataList") || !$this->iPageObject->getDataList()) {
					if ($this->iTableEditorObject) {
						$this->iTableEditorObject->getDataList();
					}
				}
				break;
			case "select_row":
				if (!method_exists($this->iPageObject, "selectRow") || !$this->iPageObject->selectRow()) {
					if ($this->iTableEditorObject) {
						$this->iTableEditorObject->selectRow();
					}
				}
				break;
			case "exportcsv":
				if (!method_exists($this->iPageObject, "exportCSV") || !$this->iPageObject->exportCSV()) {
					if ($this->iTableEditorObject) {
						$this->iTableEditorObject->exportCSV();
					}
				}
				break;
			case "get_record":
				if (!method_exists($this->iPageObject, "getRecord") || !$this->iPageObject->getRecord()) {
					if ($this->iTableEditorObject) {
						$this->iTableEditorObject->getRecord();
					}
				}
				break;
			case "save_changes":
				if ($GLOBALS['gPermissionLevel'] > _READONLY) {
					if (!empty($_POST['primary_id'])) {

# Check to see if data limitations restrict this record to readonly. If so, don't allow save

						$checkQuery = "";
						$permissionLevel = "1";
						$resultSet = executeQuery("select * from user_type_data_limitations where user_type_id = ? and page_id = ? and permission_level = ?",
							$GLOBALS['gUserRow']['user_type_id'], $GLOBALS['gPageId'], $permissionLevel);
						while ($row = getNextRow($resultSet)) {
							if (!empty($checkQuery)) {
								$checkQuery .= " or ";
							}
							$checkQuery .= "(" . PlaceHolders::massageContent($row['query_text']) . ")";
						}
						$resultSet = executeQuery("select * from user_data_limitations where user_id = ? and page_id = ? and permission_level = ?",
							$GLOBALS['gUserId'], $GLOBALS['gPageId'], $permissionLevel);
						while ($row = getNextRow($resultSet)) {
							if (!empty($checkQuery)) {
								$checkQuery .= " or ";
							}
							$checkQuery .= "(" . PlaceHolders::massageContent($row['query_text']) . ")";
						}
						foreach ($GLOBALS['gUserRow'] as $fieldName => $fieldData) {
							$checkQuery = str_replace("%" . $fieldName . "%", (is_scalar($fieldData) ? $fieldData : ""), $checkQuery);
						}
						if (!empty($checkQuery)) {

							$countQuery = "select count(*) from " . $this->iDataSource->getPrimaryTable()->getName() .
								" where " . $this->iDataSource->getPrimaryTable()->getPrimaryKey() . " = ? and " . $checkQuery;
							$count = getCachedData("data_source_count", $countQuery . ":" . $_POST['primary_id']);
							if (!$count) {
								$resultSet = executeQuery($countQuery, $_POST['primary_id']);
								if ($row = getNextRow($resultSet)) {
									$count = $row['count(*)'];
								} else {
									$count = 0;
								}
								freeResult($resultSet);
								setCachedData("data_source_count", $countQuery . ":" . $_POST['primary_id'], $count, .1);
							}
							if ($count > 0) {
								$GLOBALS['gPermissionLevel'] = $permissionLevel;
							}

						}
					}
				}
				if ($GLOBALS['gPermissionLevel'] > _READONLY) {
					if (!method_exists($this->iPageObject, "saveChanges") || !$this->iPageObject->saveChanges()) {
						if ($this->iTableEditorObject) {
							$this->iTableEditorObject->saveChanges();
						}
					}
				} else {
					$returnArray = array("error_message" => getSystemMessage("denied"));
					ajaxResponse($returnArray);
					break;
				}
				break;
			case "delete_record":
				if ($GLOBALS['gPermissionLevel'] > _READWRITE) {
					if (!empty($_POST['primary_id'])) {
						$permissionArray = array("1", "2");
						foreach ($permissionArray as $permissionLevel) {
							if ($GLOBALS['gPermissionLevel'] < _FULLACCESS) {
								break;
							}
							$checkQuery = "";
							$resultSet = executeQuery("select * from user_type_data_limitations where user_type_id = ? and page_id = ? and permission_level = ?",
								$GLOBALS['gUserRow']['user_type_id'], $GLOBALS['gPageId'], $permissionLevel);
							while ($row = getNextRow($resultSet)) {
								if (!empty($checkQuery)) {
									$checkQuery .= " or ";
								}
								$checkQuery .= "(" . PlaceHolders::massageContent($row['query_text']) . ")";
							}
							$resultSet = executeQuery("select * from user_data_limitations where user_id = ? and page_id = ? and permission_level = ?",
								$GLOBALS['gUserId'], $GLOBALS['gPageId'], $permissionLevel);
							while ($row = getNextRow($resultSet)) {
								if (!empty($checkQuery)) {
									$checkQuery .= " or ";
								}
								$checkQuery .= "(" . PlaceHolders::massageContent($row['query_text']) . ")";
							}
							foreach ($GLOBALS['gUserRow'] as $fieldName => $fieldData) {
								$checkQuery = str_replace("%" . $fieldName . "%", (is_scalar($fieldData) ? $fieldData : ""), $checkQuery);
							}
							if (!empty($checkQuery)) {
								$countQuery = "select count(*) from " . $this->iDataSource->getPrimaryTable()->getName() .
									" where " . $this->iDataSource->getPrimaryTable()->getPrimaryKey() . " = ? and " . $checkQuery;
								$count = getCachedData("data_source_count", $countQuery . ":" . $_POST['primary_id']);
								if (!$count) {
									$resultSet = executeQuery($countQuery, $_POST['primary_id']);
									if ($row = getNextRow($resultSet)) {
										$count = $row['count(*)'];
									} else {
										$count = 0;
									}
									freeResult($resultSet);
									setCachedData("data_source_count", $countQuery . ":" . $_POST['primary_id'], $count, .1);
								}
								if ($count > 0) {
									$GLOBALS['gPermissionLevel'] = $permissionLevel;
								}
							}
						}
					}
				}
				if ($GLOBALS['gPermissionLevel'] > _READWRITE) {
					if (!method_exists($this->iPageObject, "deleteRecord") || !$this->iPageObject->deleteRecord()) {
						if ($this->iTableEditorObject) {
							$this->iTableEditorObject->deleteRecord();
						}
					}
				} else {
					$returnArray = array("error_message" => getSystemMessage("denied"));
					ajaxResponse($returnArray);
					break;
				}
				break;
			case "get_spreadsheet_list":
				if (!method_exists($this->iPageObject, "getSpreadsheetList") || !$this->iPageObject->getSpreadsheetList()) {
					if ($this->iTableEditorObject && ($GLOBALS['gUserRow']['superuser_flag'] || $GLOBALS['gUserRow']['full_client_access'] || hasCapability("SPREADSHEET_EDITING"))) {
						$this->iTableEditorObject->getSpreadsheetList();
					}
				}
				break;
			case "select_tab":
				setUserPreference("MAINTENANCE_ACTIVE_TAB", $_GET['tab_index'], $GLOBALS['gPageRow']['page_code']);
				echo jsonEncode(array());
				exit;
			case "show_search_fields":
				setUserPreference("MAINTENANCE_SHOW_FILTER_COLUMNS", $_GET['value'], $GLOBALS['gPageRow']['page_code']);
				echo jsonEncode(array());
				exit;
		}
	}

	function onLoadJavascript() {
		if ($this->iTableEditorObject) {
			$this->iTableEditorObject->onLoadPageJavascript();
		} else {
			$this->iPageObject->onLoadPageJavascript();
		}
	}

	function javascript() {
		$javascriptCode = getFieldFromId("javascript_code", "templates", "template_id", $GLOBALS['gPageRow']['template_id']);
		if (!empty($javascriptCode)) {
			echo "<script>\n" . $javascriptCode . "\n</script>\n";
		}
		if ($this->iTableEditorObject) {
			$this->iTableEditorObject->pageJavascript();
		} else {
			$this->iPageObject->pageJavascript();
		}
	}

	function internalCSS() {
		if ($this->iTableEditorObject) {
			$this->iTableEditorObject->internalPageCSS();
		} else {
			$this->iPageObject->internalPageCSS();
		}
	}

	function pageHeader() {
		$returnValue = false;
		if (method_exists($this->iPageObject, "pageHeader")) {
			$returnValue = $this->iPageObject->pageHeader();
		}
		if (!$returnValue && $this->iTableEditorObject) {
			$this->iTableEditorObject->pageHeader();
		}
	}

	function jqueryTemplates() {
		if (!$this->iPageObject->jqueryTemplates() && $this->iTableEditorObject) {
			$this->iTableEditorObject->jqueryTemplates();
		}
	}

	function hiddenElements() {
		if (!$this->iPageObject->hiddenElements() && $this->iTableEditorObject) {
			$this->iTableEditorObject->hiddenElements();
		}
	}

	function mainContent() {
		if (Page::pageIsUnderMaintenance()) {
			return;
		}
		if (!$this->iPageObject->mainContent() && $this->iTableEditorObject) {
			$this->iTableEditorObject->mainContent();
		}
	}

	function footer() {
		$this->iPageObject->footer();
	}

	function displayPage() {
		$pageHelp = getFieldFromId("help_text", "pages", "page_id", $GLOBALS['gPageId'], "client_id is not null");
		$logoLink = str_replace("%user_id%", $GLOBALS['gUserId'], getPreference("ADMIN_LOGO_LINK"));
		header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma: no-cache");

        $systemVersion = getSystemVersion();
		?>
        <!--
This software is the unpublished, confidential, proprietary, intellectual
property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
or used in any manner without expressed written consent from Kim David Software, LLC.
Kim David Software, LLC owns all rights to this work and intends to keep this
software confidential so as to maintain its value as a trade secret.

Copyright 2004-Present, Kim David Software, LLC.

WARNING! This code is part of the Kim David Software's Coreware system.
Changes made to this source file will be lost when new versions of the
system are installed.

Last Update: <?= $systemVersion ?>

-->

        <!DOCTYPE html>
        <html>
        <head>

            <meta name="author" content="Kim David Software, LLC"/>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>

            <title><?= $this->getPageTitle() ?></title>

            <link type="text/css" rel="stylesheet" href="<?= autoVersion('/css/reset.css') ?>"/>
            <link type="text/css" rel="stylesheet" href="<?= autoVersion('/fontawesome-core/css/all.min.css') ?>" media="screen"/>
            <link type="text/css" rel="stylesheet" href="<?= autoVersion('/css/jquery-ui.css') ?>"/>
            <link type="text/css" rel="stylesheet" href="<?= autoVersion('/css/validationEngine.jquery.css') ?>"/>
            <link type="text/css" rel="stylesheet" href="<?= autoVersion('/css/prettyPhoto.css') ?>" media="screen"/>
            <link type="text/css" rel="stylesheet" href="<?= autoVersion('/css/jquery.minicolors.css') ?>" media="screen"/>
            <link type="text/css" rel="stylesheet" href="<?= autoVersion('/css/jquery.timepicker.css') ?>" media="screen"/>
            <link type="text/css" rel="stylesheet" href="<?= autoVersion('/css/admin.css') ?>"/>
			<?php $this->internalCSS() ?>
			<?php if ($GLOBALS['gUserRow']['superuser_flag']) { ?>
                <style>
                    #_system_version {
                        font-size: 8px;
                        width: 200px;
                        text-align: right;
                        position: absolute;
                        top: 10px;
                        right: 0;
                    }
                </style>
			<?php } ?>

            <script src="<?= autoVersion("/js/jquery-3.4.0.min.js") ?>"></script>
            <script src="<?= autoVersion("/js/jquery-migrate-3.0.1.min.js") ?>"></script>

            <script src="<?= autoVersion('/js/json3.js') ?>"></script>
            <script src="<?= autoVersion('/js/jquery-ui.js') ?>"></script>
			<?php if ($GLOBALS['gLanguageId'] != $GLOBALS['gEnglishLanguageId'] and (file_exists($GLOBALS['gDocumentRoot'] . "/js/jquery.validationEngine-" . strtolower(getFieldFromId("iso_code", "languages", "language_id", $GLOBALS['gLanguageId'])) . ".js"))) { ?>
                <script src="<?= autoVersion('/js/jquery.validationEngine-' . strtolower(getFieldFromId("iso_code", "languages", "language_id", $GLOBALS['gLanguageId'])) . '.js') ?>"></script>
			<?php } else { ?>
                <script src="<?= autoVersion('/js/jquery.validationEngine-en.js') ?>"></script>
			<?php } ?>
            <script src="<?= autoVersion('/js/jquery.validationEngine.js') ?>"></script>
            <script src="<?= autoVersion('/js/jquery.prettyPhoto.js') ?>"></script>
            <script src="<?= autoVersion('/js/jquery.address.js') ?>"></script>
            <script src="<?= autoVersion('/js/jquery.cookie.js') ?>"></script>
            <script src="/ckeditor/ckeditor.js"></script>
            <script src="<?= autoVersion('/js/jquery.minicolors.js') ?>"></script>
            <script src="<?= autoVersion('/js/jquery.timepicker.js') ?>"></script>
            <script src="<?= autoVersion('/js/general.js') ?>"></script>
            <script src="<?= autoVersion('/js/admin.js') ?>"></script>
            <script src="<?= autoVersion('/js/editablelist.js') ?>"></script>
            <script src="<?= autoVersion('/js/multipleselect.js') ?>"></script>
            <script src="<?= autoVersion('/js/formlist.js') ?>"></script>
			<?php if (!empty($GLOBALS['gPageRow']['script_filename']) && file_exists($GLOBALS['gDocumentRoot'] . "/js/" . str_replace(".php", ".js", $GLOBALS['gPageRow']['script_filename']))) { ?>
                <script src="<?= autoVersion('/js/' . str_replace(".php", ".js", $GLOBALS['gPageRow']['script_filename'])) ?>"></script>
			<?php } ?>
			<?php if (!empty($GLOBALS['gClientRow']['client_code']) && file_exists($GLOBALS['gDocumentRoot'] . "/js/" . strtolower($GLOBALS['gClientRow']['client_code']) . ".js")) { ?>
                <script src="<?= autoVersion('/js/' . strtolower($GLOBALS['gClientRow']['client_code']) . ".js") ?>"></script>
			<?php } ?>
			<?php if (file_exists($GLOBALS['gDocumentRoot'] . "/js/adminextra.js")) { ?>
                <script src="<?= autoVersion('/js/adminextra.js') ?>"></script>
			<?php } ?>

			<?php $this->headerIncludes() ?>

            <script type="text/javascript">
                //<![CDATA[
                var displayErrors = <?= ($GLOBALS['gUserRow']['superuser_flag'] ? "true" : "false") ?>;
                var scriptFilename = "<?= $GLOBALS['gLinkUrl'] ?>";
                var logoLink = "<?= $logoLink ?>";
                $(function () {
					<?php if (!empty($_SESSION['original_user_id'])) { ?>
                    $("#_return_user").click(function () {
                        loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=ibuwuyiqeuig", function(returnArray) {
                            document.location = "/";
                        });
                    });
					<?php } ?>
                });

                function saveChanges(afterFunction, regardlessFunction) {
                    for (instance in CKEDITOR.instances) {
                        CKEDITOR.instances[instance].updateElement();
                    }
                    if ($("#_edit_form").validationEngine('validate')) {
                        loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_changes", $("#_edit_form").serialize(), function(returnArray) {
                            if ("error_message" in returnArray) {
                                regardlessFunction();
                            } else {
                                displayInfoMessage("<?= getSystemMessage("save_success") ?>");
                                afterFunction();
                            }
                        }, function(returnArray) {
                            regardlessFunction();
                        });
                    } else {
                        regardlessFunction();
                    }
                }

				<?php if ($GLOBALS['gDevelopmentServer'] || $GLOBALS['gUserRow']['superuser_flag']) { ?>
                function showError(errorText) {
                    $("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
                    displayErrorMessage("Errors in console log");
                    console.log(errorText);
                }
				<?php } ?>
                //]]>
            </script>
			<?php $this->onLoadJavascript() ?>
			<?php $this->javascript() ?>
			<?= $this->getAnalyticsCode() ?>
        </head>
        <body>

        <div id="_wrapper">
            <div id="_header_wrapper">
                <div id="_banner">
					<?php
					$imageFilename = getImageFilenameFromCode("HEADER_LOGO", array("use_cdn" => true));
					?>
					<?php if (!empty($imageFilename)) { ?>
                        <div id="_banner_left"><img alt="<?= $GLOBALS['gClientName'] ?>" src="<?= $imageFilename ?>"/></div>
					<?php } else { ?>
                        <div id="_banner_left"><h1><?= $GLOBALS['gClientName'] ?></h1></div>
					<?php } ?>
                    <div id="_banner_info"><p class="error-message" id="_error_message"></p></div>
                    <div id="_banner_right">
						<?php if (!empty($pageHelp)) { ?><a href='#' id='_page_help_link' class='general-link'><?= getLanguageText("Help") ?></a><span class="general-link">&nbsp;|&nbsp;</span><?php } ?>
						<?= (empty($_SESSION['original_user_id']) ? "" : "<a href='#' id='_return_user'>Return</a>&nbsp;|&nbsp;") ?>
                        <a href='#' id="_my_home_link" class="general-link"><?= getLanguageText("My Home") ?></a>
                        <span class="general-link">&nbsp;|&nbsp;</span>
						<?php if ($GLOBALS['gLoggedIn']) { ?>
                            <a href='#' id="_logout" class="general-link">Logout</a>
						<?php } else { ?>
                            <a href='#' id="_login" class="general-link">Login</a>
						<?php } ?>
                    </div>

					<?php if ($GLOBALS['gUserRow']['superuser_flag']) { ?>
                        <div id='_system_version'><?= $systemVersion ?></div>
					<?php } ?>

                </div> <!-- banner -->

                <div id="_menu">
					<?= getMenuByCode("ADMIN_MENU") ?>
                </div> <!-- menu -->

                <div id="_page_header_wrapper">
                    <div id="_page_header">
						<?php $this->pageHeader() ?>
                    </div> <!-- page_header -->
                </div> <!-- page_header_wrapper -->
            </div> <!-- header_wrapper -->

            <div id="_inner">

                <div id="_main_content">
					<?php $this->mainContent() ?>
                    <div class='clear-div'></div>
                </div> <!-- main_content -->

                <div id="_footer">
					<?php $this->footer() ?>
                    <div class='clear-div'></div>
                </div> <!-- footer -->

            </div> <!-- inner -->

            <div id="_templates">
				<?php $this->jqueryTemplates() ?>
            </div> <!-- templates -->

			<?php
			$this->hiddenElements();
			if (file_exists($GLOBALS['gDocumentRoot'] . (empty($GLOBALS['gDocumentRoot']) ? "" : "/templates/") . "adminextra.inc")) {
				include_once "adminextra.inc";
			}
			?>

            <div class="modal"><span class="fad fa-spinner fa-spin"></span></div>

            <div id="_save_changes_dialog" class="dialog-box" data-keypress_added="false">
                Do you wish to save the changes?
            </div> <!-- save_changes_dialog -->

            <div id="_confirm_delete_dialog" class="dialog-box">
                Are you sure you want to delete this record?
            </div> <!-- confirm_delete_dialog -->

            <div id="_page_help" class="dialog-box">
				<?= $pageHelp ?>
            </div> <!-- page_help -->

        </div> <!-- wrapper -->
        </body>
        </html>
		<?php
	}
}
