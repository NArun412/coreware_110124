<?php

/*      This software is the unpublished, confidential, proprietary, intellectual
        property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
        or used in any manner without expressed written consent from Kim David Software, LLC.
        Kim David Software, LLC owns all rights to this work and intends to keep this
        software confidential so as to maintain its value as a trade secret.

        Copyright 2004-Present, Kim David Software, LLC.
*/

$GLOBALS['gPageCode'] = "VISITORCHECKIN";
require_once "shared/startup.inc";

class VisitorCheckInPage extends Page {

	function setup() {
		$categoryId = getFieldFromId("category_id", "categories", "category_code", "VISITOR");
		if (empty($categoryId)) {
			executeQuery("insert into categories (client_id,category_code,description) values (?,'VISITOR','Visitor')", $GLOBALS['gClientId']);
		}
		$sourceId = getFieldFromId("source_id", "sources", "source_code", "VISITOR");
		if (empty($sourceId)) {
			executeQuery("insert into sources (client_id,source_code,description) values (?,'VISITOR','Visitor')", $GLOBALS['gClientId']);
		}
		$taskSourceId = getFieldFromId("task_source_id", "task_sources", "task_source_code", "VISIT");
		if (empty($taskSourceId)) {
			executeQuery("insert into task_sources (client_id,task_source_code,description) values (?,'VISIT','Visit')", $GLOBALS['gClientId']);
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "set_purpose":
				$visitorLogId = $_POST['visitor_log_id'];
				$visitTypeId = getFieldFromId("visit_type_id", "visit_types", "visit_type_id", $_POST['visit_type_id']);
				executeQuery("update visitor_log set visit_type_id = ? where visit_type_id is null and visitor_log_id = ?", $visitTypeId, $visitorLogId);
				if (function_exists("customVisitorLogResponse")) {
					$returnArray['response'] = customVisitorLogResponse();
				}
				ajaxResponse($returnArray);
				break;
			case "search":
				$contactArray = array();
				$resultSet = executeQuery("select * from contacts where contact_id in (select contact_id from contact_identifiers where identifier_value = ?) and client_id = ? and email_address is not null order by email_address,contact_id",
					$_POST['search_text'], $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$row['email_address'] = strtolower($row['email_address']);
					if (!array_key_exists($row['email_address'], $contactArray)) {
						$contactArray[$row['email_address']] = $row;
					}
				}
				if (is_numeric($_POST['search_text'])) {
					$resultSet = executeQuery("select * from contacts where (contact_id = ? or contact_id in (select contact_id from contact_redirect where retired_contact_identifier = ? and client_id = ?)) and client_id = ? and email_address is not null order by email_address,contact_id",
						$_POST['search_text'], $_POST['search_text'], $GLOBALS['gClientId'], $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						$row['email_address'] = strtolower($row['email_address']);
						if (!array_key_exists($row['email_address'], $contactArray)) {
							$contactArray[$row['email_address']] = $row;
						}
					}
				}
				$resultSet = executeQuery("select * from contacts where email_address like ? and contact_id in " .
					"(select contact_id from contact_categories where category_id = (select category_id from categories " .
					"where category_code = 'VISITOR' and client_id = ?)) and client_id = ? order by email_address,contact_id",
					$_POST['search_text'] . "%", $GLOBALS['gClientId'], $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$row['email_address'] = strtolower($row['email_address']);
					if (!array_key_exists($row['email_address'], $contactArray)) {
						$contactArray[$row['email_address']] = $row;
					}
				}

				if (empty($contactArray)) {
					$resultSet = executeQuery("select * from contacts where email_address like ? and client_id = ? order by email_address,contact_id",
						$_POST['search_text'] . "%", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						$row['email_address'] = strtolower($row['email_address']);
						if (!array_key_exists($row['email_address'], $contactArray)) {
							$contactArray[$row['email_address']] = $row;
						}
					}
				}

				if (empty($contactArray)) {
					$returnArray['search_results'] = "<p>No results found. Check in as a new visitor.</p>";
				} else {
					if (count($contactArray) > 8) {
						$returnArray['search_results'] = "<p>Too many to display... keep typing</p>";
					}
					foreach ($contactArray as $row) {
						$returnArray['search_results'] .= "<p class='select-contact' data-contact_id='" . $row['contact_id'] . "'>" .
							$row['email_address'] . "</p>";
					}
				}
				ajaxResponse($returnArray);
				break;
			case "select_contact":
				$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $_POST['contact_id'], "client_id = " . $GLOBALS['gClientId']);
				if ($contactId) {
					$visitTime = "";
					$resultSet = executeQuery("select * from visitor_log where client_id = ? and contact_id = ? and date(visit_time) < current_date order by visit_time desc limit 1", $GLOBALS['gClientId'], $contactId);
					if ($row = getNextRow($resultSet)) {
						$visitTime = date('l, F d, Y \a\t g:ia', strtotime($row['visit_time']));
					}
					$resultSet = executeQuery("select * from visitor_log where client_id = ? and contact_id = ? and date(visit_time) = current_date and end_time is null", $GLOBALS['gClientId'], $contactId);
					if ($resultSet['row_count'] == 0) {
						executeQuery("insert into visitor_log (client_id,contact_id,visit_time) values (?,?,now())", $GLOBALS['gClientId'], $contactId);
					}
					$welcomeText = $this->getPageTextChunk("welcome_text");
					if (empty($welcomeText)) {
						$welcomeText = "<p>Welcome, <span class='highlighted-text'>" . getDisplayName($contactId) . "</span>." . ($visitTime ? " Your last visit was " . $visitTime . "." : "") . "</p>";
					} else {
						$welcomeText = makeHtml(str_replace("%full_name%", getDisplayName($contactId), $welcomeText) . ($visitTime ? " Your last visit was " . $visitTime . "." : ""));
					}
					$currentHour = date("G");
					$resultSet = executeQuery("select * from events join event_facilities using (event_id) where start_date = current_date and contact_id = ? and " .
						"date_needed = current_date and hour >= ? order by hour", $contactId, $currentHour);
					if ($row = getNextRow($resultSet)) {
						$displayTime = Events::getDisplayTime($row['hour']);
						$welcomeText .= "<p class='found-event'>You are in " . getFieldFromId("description", "facilities", "facility_id", $row['facility_id']) .
							" at " . $displayTime . "</p>";
					}
					$returnArray['welcome'] = $welcomeText;
				}
				ajaxResponse($returnArray);
				break;
			case "save_contact":
				$sourceId = getFieldFromId("source_id", "sources", "source_code", "VISITOR");
				$resultSet = executeQuery("select * from contacts where client_id = ? and last_name = ? and first_name = ? and email_address <=> ? order by contact_id",
					$GLOBALS['gClientId'], $_POST['last_name'], $_POST['first_name'], $_POST['email_address']);
				if ($row = getNextRow($resultSet)) {
					$contactId = $row['contact_id'];
				} else {
					$contactDataTable = new DataTable("contacts");
					if (!$contactDataTable->saveRecord(array("name_values" => array("first_name" => $_POST['first_name'], "last_name" => $_POST['last_name'],
						"email_address" => strtolower($_POST['email_address']), "source_id" => $sourceId)))) {
						$returnArray['error_message'] = "An error occurred";
						ajaxResponse($returnArray);
						break;
					}
					$contactId = $resultSet['insert_id'];
				}
				$taskSourceId = getFieldFromId("task_source_id", "task_sources", "task_source_code", "VISIT");
				if (!empty($contactId) && !empty($taskSourceId)) {
					executeQuery("insert into tasks (client_id,contact_id,description,date_completed,simple_contact_task,task_source_id) values " .
						"(?,?,'Visit',now(),1,?)", $GLOBALS['gClientId'], $contactId, $taskSourceId);
				}
				if ($contactId) {
					$categoryId = getFieldFromId("category_id", "categories", "category_code", "VISITOR");
					$contactCategoryId = getFieldFromId('contact_category_id', 'contact_categories', "contact_id", $contactId, "category_id = " . $categoryId);
					if (empty($contactCategoryId) && !empty($categoryId)) {
						executeQuery("insert into contact_categories (contact_id,category_id) values (?,?)", $contactId, $categoryId);
					}
					$visitTime = "";
					if (!empty($_POST['email_address'])) {
						$resultSet = executeQuery("select * from visitor_log where client_id = ? and contact_id = ? order by visit_time desc limit 1", $GLOBALS['gClientId'], $contactId);
						if ($row = getNextRow($resultSet)) {
							$visitTime = date('l, F d, Y \a\t g:ia', strtotime($row['visit_time']));
						}
					}
					$resultSet = executeQuery("insert into visitor_log (client_id,contact_id,visit_time) values (?,?,now())", $GLOBALS['gClientId'], $contactId);
					$returnArray['visitor_log_id'] = $resultSet['insert_id'];
					$returnArray['welcome'] = "<span class='highlighted-text'>Welcome, " . getDisplayName($contactId) . "</span> to " . $GLOBALS['gClientId']['business_name'] . "." .
						($visitTime ? " Your last visit was " . $visitTime . "." : "");
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function mainContent() {
		?>
		<?php
	}

	function displayPage() {
		$this->executeUrlActions();
		$imageFilename = getImageFilenameFromCode("VISITOR_SIGN_IN", array("use_cdn" => true));
		$logoImageFilename = getImageFilenameFromCode("VISITOR_LOGO", array("use_cdn" => true));
		?>
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
            <meta name="viewport" content="width=1024">
            <meta name="viewport" content="initial-scale=1.0, user-scalable=no">

            <title><?php echo $this->iTemplateObject->getPageTitle() ?></title>

            <link type="text/css" rel="stylesheet" href="<?php echo autoVersion('/css/reset.css') ?>"/>
            <link type="text/css" rel="stylesheet"
                  href="<?php echo autoVersion('/css/validationEngine.jquery.css') ?>"/>

            <script src="https://code.jquery.com/jquery-3.1.1.min.js" integrity="sha256-hVVnYaiADRTO2PzUGmuLJr8BLUSjGIZsDYGmIJLv2b8=" crossorigin="anonymous"></script>
            <script src="https://code.jquery.com/jquery-migrate-3.0.0.min.js" integrity="sha256-JklDYODbg0X+8sPiKkcFURb5z7RvlNMIaE3RA2z97vw=" crossorigin="anonymous"></script>
            <script type="text/javascript" src="<?php echo autoVersion('/js/json3.js') ?>"></script>
            <script type="text/javascript" src="<?php echo autoVersion('/js/jquery.validationEngine-en.js') ?>"></script>
            <script type="text/javascript" src="<?php echo autoVersion('/js/jquery.validationEngine.js') ?>"></script>
            <script type="text/javascript" src="<?php echo autoVersion('/js/general.js') ?>"></script>

            <script type="text/javascript">
				let searchTimer = null;
				let resetTimer = null;
                $(function () {
                    $("#new_visitor").click(function () {
                        $("#_search_form").hide();
                        $("#_contact_form input").val("");
                        $("#_contact_form").show();
                        $("#first_name").focus();
                        return false;
                    });
                    $("#cancel_form").click(function () {
                        $("#_contact_form").hide();
                        $("#_contact_form input").val("");
                        $("#_search_form").show();
                        $("#_search_results").html("").hide();
                        $("#_visitor_form").validationEngine("hideAll");
                        $("#search_text").val("").focus();
                        return false;
                    });
                    $("#search_text").keyup(function () {
                        if (!empty(searchTimer)) {
                            clearTimeout(searchTimer);
                            searchTimer = null;
                        }
                        searchTimer = setTimeout(function () {
                            const searchText = $("#search_text").val();
                            $("#_search_results").hide().html(searchText);
                            if (searchText.length > 4) {
                                $("#_search_results").show().html("Searching...");
                                loadAjaxRequest("<?php echo $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=search", {search_text: $("#search_text").val()}, function(returnArray) {
                                    if ("search_results" in returnArray) {
                                        $("#_search_results").html(returnArray['search_results']);
                                    }
                                });
                            }
                        }, 500);
                    });
                    $(document).on("click", ".select-contact", function () {
                        $(this).css("background-color", "rgb(0,0,0)").css("color", "rgb(255,255,255)");
                        loadAjaxRequest("<?php echo $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=select_contact", {contact_id: $(this).data("contact_id")}, function(returnArray) {
                                if ("welcome" in returnArray) {
                                    $(".chosen").removeClass("chosen");
                                    $("#visitor_log_id").val(returnArray['visitor_log_id']);
                                    $("#_welcome_content").html(returnArray['welcome']);
									$("#_welcome_response").html("");
                                    $("#_contact_form").hide();
                                    $("#_search_form").hide();
                                    $("#_purpose_choices").show();
                                    $("#_welcome").show();
                                    resetForm();
                                }
                            }
                        );
                        return false;
                    });
                    $(document).on("click", ".purpose-button", function () {
                        $(this).addClass("chosen");
                        $("#_purpose_choices").hide();
                        loadAjaxRequest("<?php echo $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=set_purpose", {visitor_log_id: $("#visitor_log_id").val(), visit_type_id: $(this).data("visit_type_id")}, function(returnArray) {
							if ("response" in returnArray) {
								$("#_welcome_response").html(returnArray['response']);
							}
                            resetForm();
                        });
                        return false;
                    });
                    $(document).on("click", "#submit_form", function () {
                        if (empty($("#first_name").val()) || empty($("#last_name").val())) {
                            displayErrorMessage("All fields are required");
                            return false;
                        }
                        if ($("#_visitor_form").validationEngine('validate')) {
                            loadAjaxRequest("<?php echo $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_contact", $("#_visitor_form").serialize(), function(returnArray) {
                                if ("welcome" in returnArray) {
                                    $(".chosen").removeClass("chosen");
                                    $("#visitor_log_id").val(returnArray['visitor_log_id']);
                                    $("#_welcome_content").html(returnArray['welcome']);
                                    $("#_contact_form").hide();
                                    $("#_search_form").hide();
                                    $("#_purpose_choices").show();
                                    $("#_welcome").show();
                                    resetForm();
                                }
                            });
                        }
                        return false;
                    });
                    $(document).on('touchmove', function (e) {
                        e.preventDefault();
                        return false;
                    });
                    $("#_wrapper").removeClass("hidden");
                    $("#search_text").focus();
                });

                function resetForm() {
					if (!empty(resetTimer)) {
						clearTimeout(resetTimer);
					}
                    resetTimer = setTimeout(function () {
                        $("#_contact_form").hide();
                        $("#_contact_form input").val("");
                        $("#_welcome").hide();
                        $("#_search_form").show();
                        $("#_search_results").html("").hide();
                        $("#search_text").val("").focus();
                        $("#_purpose").hide();
                    }, 10000);
                }
            </script>
            <style>

                #_wrapper {
                    width: 100vw;
                    height: 100vh;
                <?= (strpos($imageFilename,"empty.jpg") === false ? "background-image: url('" . $imageFilename . "'); " : "") ?> background-color: rgb(200, 205, 210);
                    background-repeat: no-repeat;
                    background-position: top center;
                    background-size: cover;
                    margin: 0 auto;
                    border: 1px solid rgb(200, 200, 200);
                    overflow: hidden;
                }

                #_header {
                    width: 75%;
                    max-width: 500px;
                    height: auto;
                    margin: 0 auto;
                    min-height: 100px;
                    text-align: center;
                }

                a, a:link, a:active, a:visited {
                    font-weight: bold;
                    color: rgb(0, 0, 0);
                    text-align: left;
                    text-decoration: none;
                }

                a:hover {
                    text-decoration: none;
                }

                .logo {
                    max-width: 400px;
                    margin: 20px auto;
                    max-height: 100px;
                }

                #_search_form {
                    margin: 100px auto;
                    width: 90%;
                    max-width: 600px;
                    text-align: center;
                }

                input[type=text], input[type=email] {
                    width: 90%;
                    max-width: 600px;
                    font-size: 24px;
                    padding: 10px 20px;
                    text-align: center;
                    background-color: rgba(255, 255, 255, .95);
                    border-radius: 10px;
                    display: block;
                    margin: 20px auto;
                }

                #_search_results {
                    width: 90%;
                    max-width: 600px;
                    margin: 20px auto;
                    background-color: rgba(255, 255, 255, .95);
                    border-radius: 10px;
                    padding: 20px;
                    display: none;
                }

                .select-contact {
                    border-bottom: 1px solid rgb(225, 225, 225);
                    font-size: 20px;
                    margin: 0;
                    padding: 10px 0;
                    cursor: pointer;
                }

                #_contact_form {
                    display: none;
                    height: 300px;
                    padding: 40px;
                    text-align: center;
                }

                .button {
                    height: 60px;
                    width: 90%;
                    max-width: 600px;
                    background-color: rgba(255, 255, 255, .95);
                    border-radius: 10px;
                    font-size: 24px;
                    text-align: center;
                    line-height: 60px;
                    margin: 0 auto;
                    font-weight: bold;
                    color: rgb(80, 80, 100);
                    cursor: pointer;
                }

                .button.chosen {
                    color: rgb(255, 255, 255);
                    background-color: rgb(80, 80, 100);
                }

                #submit_form {
                    display: inline-block;
                    margin-right: 40px;
                    width: 50%;
                    max-width: 200px;
                }

                #cancel_form {
                    display: inline-block;
                    width: 75%;
                    max-width: 500px
                }

                #_welcome {
                    display: none;
                    width: 90%;
                    max-width: 600px;
                    margin: 80px auto;
                }

                #_welcome p {
                    font-size: 20px;
                    line-height: 1.3;
                    margin-bottom: 20px;
                    color: rgb(50, 50, 50);
                    padding: 20px;
                    border-radius: 10px;
                }

                #_welcome p.found-event {
                    font-weight: 900;
                    font-size: 28px;
                }

                .purpose-button {
                    float: left;
                    margin: 20px;
                    width: 50%;
                    max-width: 200px;
                }

                #_purpose {
                    width: 90%;
                    max-width: 600px;
                    margin: 150px auto;
                    display: none;
                }

                #_purpose p {
                    font-size: 28px;
                    line-height: 1.3;
                    margin-bottom: 20px;
                    background-color: rgba(255, 255, 255, .95);
                    color: rgb(80, 80, 100);
                    padding: 20px;
                    border-radius: 10px;
                    font-weight: bold;
                    text-align: center;
                }

                .main-text {
                    font-size: 24px;
                    color: rgb(255, 255, 255);
                    padding-top: 10px;
                    font-weight: bold;
                    text-shadow: 0 0 10px rgb(0, 0, 0);
                }

                #_error_message {
                    position: relative;
                    font-size: 18px;
                }

                <?php $this->internalPageCSS() ?>
            </style>
            <style type="text/css">
            </style>
        </head>

        <body>
        <div id="_wrapper" class='hidden'>

            <div id="_header">
				<?php if (strpos($logoImageFilename, "empty.jpg") === false) { ?>
                    <img alt="Logo" src="<?= $logoImageFilename ?>" class="logo">
				<?php } ?>
            </div> <!-- _header -->

            <div id="_search_form">
                <div class="button" id="new_visitor">Check-in as a New Visitor</div>
                <p class="main-text align-center">or</p>
                <input type="email" id="search_text" name="search_text"
                       placeholder="Returning visitor: Search by email">
                <div id="_search_results">
                </div> <!-- search_results -->
            </div> <!-- search_form -->

            <div id="_contact_form">
                <form id="_visitor_form">
                    <p id="_error_message"></p>
                    <input type="text" id="first_name" name="first_name" placeholder="First Name" class="">
                    <input type="text" id="last_name" name="last_name" placeholder="Last Name" class="">
                    <input type="email" id="email_address" name="email_address" placeholder="Email Address"
                           class="validate[custom[email]]">
                    <div class="button" id="submit_form">Submit Form</div>
                    <div class="button" id="cancel_form">Cancel</div>
                </form>
            </div> <!-- contact_form -->

            <div id="_welcome">
                <input type="hidden" id="visitor_log_id" name="visitor_log_id">
                <div id="_welcome_content"></div>
				<div id="_welcome_response"></div>
                <div id="_purpose_choices">
					<?php
					$resultSet = executeQuery("select * from visit_types where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
					if ($resultSet['row_count'] > 0) {
						?>
                        <p class="align-center">What is the primary purpose of your visit?</p>
						<?php
					}
					while ($row = getNextRow($resultSet)) {
						?>
                        <div class="button purpose-button"
                             data-visit_type_id="<?php echo $row['visit_type_id'] ?>"><?php echo htmlText($row['description']) ?></div>
						<?php
					}
					?>
                </div>
                <div class='clear-div'></div>
            </div>

            <div id="_purpose">
                <p>Thank you for checking in!</p>
            </div>

        </div> <!-- wrapper -->

        </body>
        </html>
		<?php
	}
}

$pageObject = new VisitorCheckInPage();
$pageObject->displayPage();
