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

$GLOBALS['gPageCode'] = "SIMULATEUSER";
require_once "shared/startup.inc";

class ThisPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "search_users":
				$allowAdministratorLogin = getPreference("allow_administrator_login");
				$userList = "";
				if ($GLOBALS['gUserRow']['superuser_flag'] || $allowAdministratorLogin) {
					$query = ($GLOBALS['gUserRow']['superuser_flag'] ? "" : "superuser_flag = 0" .
						(empty($GLOBALS['gUserRow']['full_client_access']) ? "  and full_client_access = 0 and administrator_flag = 0" : ""));
					$resultSet = executeQuery("select * from users join contacts using (contact_id) where inactive = 0 and " .
						"(user_name like ? or first_name like ? or last_name like ? or business_name like ?) and users.client_id = ?" .
						(empty($query) ? "" : " and " . $query) . " order by first_name,last_name", $_POST['search_text'] . "%",
						$_POST['search_text'] . "%", $_POST['search_text'] . "%", $_POST['search_text'] . "%", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						$userList .= "<li><a class='user-account' href='#' data-user_id='" . $row['user_id'] . "'>" . htmlText(getUserDisplayName($row['user_id'])) . " (" . $row['user_name'] . ")</a></li>";
					}
				}
				$returnArray['user_list'] = $userList;
				ajaxResponse($returnArray);
				break;
			case "simulate_user":
				$allowAdministratorLogin = getPreference("allow_administrator_login");
				if ($GLOBALS['gUserRow']['superuser_flag'] || $allowAdministratorLogin) {
					$query = ($GLOBALS['gUserRow']['superuser_flag'] ? "" : "superuser_flag = 0" .
						(empty($GLOBALS['gUserRow']['full_client_access']) ? " and full_client_access = 0 and administrator_flag = 0" : ""));
					$resultSet = executeQuery("select * from users where user_id = ? and inactive = 0 and client_id = ?" .
						(empty($query) ? "" : " and " . $query), $_GET['user_id'], $GLOBALS['gClientId']);
					if ($row = getNextRow($resultSet)) {
						addProgramLog("Simulate user ID " . $row['user_id'] . " by user ID " . $GLOBALS['gUserId']);
						$_SESSION['original_user_id'] = $GLOBALS['gUserId'];
                        saveSessionData();
						login($row['user_id'], false);
					}
				}
				if (empty($_GET['ajax'])) {
					header("Location: /");
					exit;
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#user_search").keyup(function (event) {
                if (event.which == 13 || event.which == 3) {
                    var filterValue = $(this).val().toLowerCase();
                    if (!empty(filterValue)) {
                        loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=search_users", { search_text: filterValue }, function(returnArray) {
                            if ("user_list" in returnArray) {
                                $("#user_list").html(returnArray['user_list']);
                            }
                        });
                    }
                }
            });
            $(document).on("click", ".user-account", function () {
                var userId = $(this).data("user_id");
                if (userId != "" && userId != undefined) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=simulate_user&user_id=" + userId, function(returnArray) {
                        document.location = "/";
                    });
                }
                return false;
            });
            $("#user_filter").keyup(function (event) {
                var filterValue = $(this).val().toLowerCase();
                $(".user-account").each(function () {
                    if ($(this).text().toLowerCase().indexOf(filterValue) >= 0) {
                        $(this).removeClass("hidden");
                    } else {
                        $(this).addClass("hidden");
                    }
                });
                if (event.which == 13 || event.which == 3) {
                    if ($(".user-account").not(".hidden").length == 1) {
                        $(".user-account").not(".hidden").trigger("click");
                    }
                }
            });
            $(window).ready(function () {
                $("#user_filter").focus();
            });
        </script>
		<?php
	}

	function mainContent() {
		echo $this->iPageData['content'];
		$allowAdministratorLogin = getPreference("allow_administrator_login");
		if ($GLOBALS['gUserRow']['superuser_flag'] || $allowAdministratorLogin) {
			$query = ($GLOBALS['gUserRow']['superuser_flag'] ? "" : "superuser_flag = 0" .
				(empty($GLOBALS['gUserRow']['full_client_access']) ? " and full_client_access = 0 and administrator_flag = 0" : ""));
			$resultSet = executeQuery("select * from users join contacts using (contact_id) where inactive = 0 and users.client_id = ?" .
				(empty($query) ? "" : " and " . $query) . " order by first_name,last_name", $GLOBALS['gClientId']);
			$showAll = $resultSet['row_count'] < 500;
			?>
            <p><input tabindex="10" type="text" id="user_<?= ($showAll ? "filter" : "search") ?>" name="user_<?= ($showAll ? "filter" : "search") ?>" placeholder="<?= ($showAll ? "Filter" : "Search") ?>"></p>
            <ul id="user_list">
				<?php if ($showAll) { ?>
					<?php
					while ($row = getNextRow($resultSet)) {
						?>
                        <li><a class='user-account' href="#" data-user_id="<?= $row['user_id'] ?>"><?= getUserDisplayName($row['user_id']) . " (" . $row['user_name'] . ")" ?></a></li>
						<?php
					}
					?>
				<?php } ?>
            </ul>
			<?php
		}
		return true;
	}

	function internalCSS() {
		?>
        <style>
            #user_list li {
                margin: 5px 0;
                cursor: pointer;
            }
        </style>
		<?php
	}
}

$pageObject = new ThisPage();
$pageObject->displayPage();
