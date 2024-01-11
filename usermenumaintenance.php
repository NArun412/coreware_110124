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

$GLOBALS['gPageCode'] = "USERMENUMAINT";
require_once "shared/startup.inc";

if (!$GLOBALS['gLoggedIn']) {
	header("Location: /");
	exit;
}

class UserMenuMaintenancePage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("delete", "list"));
		}
	}

	function massageUrlParameters() {
		$_GET['url_subpage'] = $_GET['url_page'];
		$_GET['url_page'] = "show";
		$_GET['primary_id'] = "";
	}

	function executePageUrlActions() {
		if ($_GET['url_action'] == "save_changes") {
			$returnArray = array();
			if ($GLOBALS['gPermissionLevel'] > 1 && array_key_exists("_form_submission", $_POST)) {
				executeQuery("delete from user_menus where user_id = ?", $GLOBALS['gUserId']);
				foreach ($_POST as $fieldName => $fieldData) {
					if (substr($fieldName, 0, strlen("link_title_")) == "link_title_") {
						$rowNumber = substr($fieldName, strlen("link_title_"));
						if (!is_numeric($rowNumber)) {
							continue;
						}
						$linkTitle = $fieldData;
						$scriptFilename = $_POST['script_filename_' . $rowNumber];
						$sequenceNumber = $_POST['sequence_number_' . $rowNumber];
						$displayColor = $_POST['display_color_' . $rowNumber];
						$separateWindow = (empty($_POST['separate_window_' . $rowNumber]) ? 0 : 1);
						$resultSet = executeQuery("insert into user_menus (user_menu_id,user_id,sequence_number,link_title,script_filename,display_color,separate_window,version) values " .
							"(null,?,?,?,?,?,?,1)", $GLOBALS['gUserId'], $sequenceNumber, $linkTitle, $scriptFilename, $displayColor, $separateWindow);
						if (!empty($resultSet['sql_error'])) {
							$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
						}
					}
				}
				removeCachedData("menu_contents", "*");
				removeCachedData("admin_menu", "*");
			} else {
				$returnArray['error_message'] = getSystemMessage("denied");
			}
			ajaxResponse($returnArray);
		}
	}

	function mainContent() {
		$introduction = $this->getPageData("content");
		if (!empty($introduction)) {
			echo $introduction;
		} else {
			?>
            <p id="basic_instructions" class="hidden">Drag & drop to change the order of the bookmarks. Click "Add" to add other items. After you have arranged the bookmarks the way you want, click 'Save'.</p>
            <p id="set_menu_div" class="hidden">Choose the program(s) you want to bookmark from the menu and then click the 'Done' button.</p>
			<?php
		}
		$resultSet = executeQuery("select * from user_menus where user_id = ? order by sequence_number", $GLOBALS['gUserId']);
		$rowNumber = 0;
		?>
        <form name="_edit_form" id="_edit_form">
            <input type="hidden" name="_form_submission" value="yes"/>
            <ul id="user_menus" data-next_row_number="<?= $resultSet['row_count'] + 1 ?>">
				<?php
				while ($row = getNextRow($resultSet)) {
					$rowNumber++;
					?>
                    <li>
                        <div class="grip">
                            <span class="fa fa-ellipsis-v"></span>
                            <span class="fa fa-ellipsis-v"></span>
                        </div>
                        <input type="text" name="link_title_<?= $rowNumber ?>" id="link_title_<?= $rowNumber ?>" class="link-title validate[required]" size="30" maxlength="40" value="<?= htmlText($row['link_title']) ?>" data-crc_value="<?= getCrcValue($row['link_title']) ?>"/>
                        <input type="text" class="display-color minicolors" name="display_color_<?= $rowNumber ?>" id="display_color_<?= $rowNumber ?>" size="8" maxlength="7" value="<?= htmlText($row['display_color']) ?>" data-crc_value="<?= getCrcValue($row['display_color']) ?>"/>
                        <input type="hidden" name="script_filename_<?= $rowNumber ?>" id="script_filename_<?= $rowNumber ?>" value="<?= htmlText($row['script_filename']) ?>"/>
                        <input type="hidden" name="sequence_number_<?= $rowNumber ?>" id="sequence_number_<?= $rowNumber ?>" value="<?= $row['sequence_number'] ?>"/>
                        <input type="checkbox" name="separate_window_<?= $rowNumber ?>" id="separate_window_<?= $rowNumber ?>" value="1"<?= ($row['separate_window'] == 1 ? " checked" : "") ?> data-crc_value="<?= getCrcValue($row['separate_window']) ?>"/>
                        <label class="checkbox-label" for="separate_window_<?= $rowNumber ?>">in new window</label><span class="delete-row fa fa-times"></span>
                    </li>
					<?php
				}
				?>
            </ul>
        </form>
		<?php
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
            setTimeout(function () {
                $("#_add_button").trigger("click");
            }, 500);
            $(document).on("tap click", ".delete-row", function () {
                $(this).parent("li").remove();
            });
            $("#user_menus").sortable({
                handle: ".grip",
                update: function () {
                    manualChangesMade = true;
                    let sequenceNumber = 1;
                    $("#user_menus").find("input[name^=sequence_number]").each(function () {
                        $(this).val(sequenceNumber++);
                    });
                }
            });
            const originalGoToLink = goToLink;
            goToLink = function (callingElement, linkUrl, separateWindow) {
                if ($("#_add_button").data("setting") === "true") {
                    manualChangesMade = true;
                    const rowNumber = $("#user_menus").data("next_row_number");
                    $("#user_menus").data("next_row_number", rowNumber - 0 + 1);
                    let menuText = callingElement.find(".menu-text").html();
                    if (empty(menuText)) {
                        menuText = callingElement.text();
                    }
                    if (empty(menuText)) {
                        return false;
                    }
                    let userMenu = $("#menu_template").html();
                    userMenu = userMenu.replace(new RegExp("%row_number%", 'g'), rowNumber);
                    userMenu = userMenu.replace(new RegExp("%link_title%", 'g'), menuText);
                    userMenu = userMenu.replace(new RegExp("%script_filename%", 'g'), linkUrl);
                    $("#user_menus").append(userMenu);
                    $("#line_number_" + rowNumber).find(".display-color").addClass("minicolors").minicolors({letterCase: 'uppercase', control: 'wheel'});
                    let sequenceNumber = 1;
                    $("#user_menus").find("input[name^=sequence_number]").each(function () {
                        $(this).val(sequenceNumber++);
                    });
                    ($("#_add_button").find("span").length > 0 ? $("#_add_button").find("span.button-icon-text").html("Done") : $("#_add_button").html("Done"));
                } else {
                    return originalGoToLink.apply(this, arguments);
                }
                return false;
            };
            $(document).on("tap click", "#_add_button", function (event) {
                if ($(this).data("setting") === "true") {
                    const addText = $("#_add_button").html();
                    $("#_add_button").html(addText.replace("Cancel", "Add").replace("Done","Add"));
                    $(this).data("setting", "false");
                    $("#set_menu_div").addClass("hidden");
                    $("#basic_instructions").removeClass("hidden");
                } else {
                    const addText = $("#_add_button").html();
                    $("#_add_button").html(addText.replace("Add", "Cancel"));
                    $(this).data("setting", "true");
                    $("#set_menu_div").removeClass("hidden");
                    $("#basic_instructions").addClass("hidden");
                }
                event.stopPropagation();
                return false;
            });
            $(document).on("tap click", "#_save_button", function () {
                disableButtons($(this));
                saveChanges(function () {
                    $("body").data("just_saved", "true");
                    ($("#_add_button").find("span").length > 0 ? $(this).find("span").html("Add") : $(this).html("Add"));
                    $("#set_menu_div").addClass("hidden");
                    $("#basic_instructions").removeClass("hidden");
                    sessionStorage.clear();
                    setTimeout("document.location = '/'", 1000);
                }, function () {
                    enableButtons($("#_save_button"));
                });
                return false;
            });
            displayFormHeader();
            $(".page-next-button").hide();
            $(".page-previous-button").hide();
            $(".page-record-display").hide();
            enableButtons();
        </script>
		<?php
		return true;
	}

	function jqueryTemplates() {
		?>
        <ul id="menu_template">
            <li id="line_number_%row_number%">
                <div class="grip">
                    <span class="fa fa-ellipsis-v"></span>
                    <span class="fa fa-ellipsis-v"></span>
                </div>
                <input type="text" name="link_title_%row_number%" id="link_title_%row_number%" class="link-title validate[required]" size="30" maxlength="40" value="%link_title%"/>
                <input type="text" class="display-color" name="display_color_%row_number%" id="display_color_%row_number%" size="8" maxlength="7" value=""/>
                <input type="hidden" name="script_filename_%row_number%" id="script_filename_%row_number%" value="%script_filename%"/>
                <input type="hidden" name="sequence_number_%row_number%" id="sequence_number_%row_number%" value="%sequence_number%"/>
                <input type="checkbox" name="separate_window_%row_number%" id="separate_window_%row_number%" value="1"/>
                <label class="checkbox-label" for="separate_window_%row_number%">in new window</label><span class="delete-row fa fa-times"></span>
            </li>
        </ul>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #user_menus {
                list-style-type: none;
                margin: 0;
                padding: 0;
                display: inline-block;
            }

            #user_menus li {
                border: 1px solid rgb(180, 180, 180);
                border-radius: 5px;
                margin: 10px 0;
                padding: 8px 20px 8px 0;
                font-size: 1rem;
                background-color: rgb(220, 220, 220);
                white-space: nowrap;
            }

            div.grip {
                display: inline-block;
                cursor: pointer;
                padding: 5px 20px;
            }

            .fa-ellipsis-v {
                color: rgb(180, 180, 180);
                margin-right: -2px;
            }

            input.link-title {
                margin: 0 20px 0 0;
                border: 1px solid rgb(100, 100, 100);
                border-radius: 5px;
            }

            .delete-row {
                margin-left: 50px;
                cursor: pointer;
            }

            #basic_instructions {
                font-size: 1rem;
                font-weight: bold;
            }

            #set_menu_div {
                font-size: 1rem;
                font-weight: bold;
            }

            .display-color {
                margin-right: 20px;
            }
        </style>
		<?php
	}
}

$pageObject = new UserMenuMaintenancePage("user_menus");
$pageObject->displayPage();
