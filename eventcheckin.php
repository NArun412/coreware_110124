<?php

$GLOBALS['gPageCode'] = "EVENTCHECKIN";
require_once "shared/startup.inc";

class EventCheckInPage extends Page {

	var $iEventid = "";

	function setup() {
		if (empty($_GET['ajax'])) {
			$this->iEventId = getFieldFromId("event_id", "events", "event_id", $_GET['id'], "(end_date is null or end_date >= current_date)");
			if (empty($this->iEventId)) {
				header("Location: /");
				exit;
			}
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "check_in":
				$eventId = getFieldFromId("event_id", "events", "event_id", $_POST['event_id'], "(end_date is null or end_date >= current_date)");
				if (empty($eventId)) {
					$returnArray['error_message'] = "Invalid Event";
					echo jsonEncode($returnArray);
					exit;
				}
				if (empty($_POST['email_address'])) {
					$returnArray['error_message'] = "Missing Information";
					echo jsonEncode($returnArray);
					exit;
				}
				$resultSet = executeQuery("select * from contacts where email_address = ? and client_id = ? and contact_id in (select contact_id from event_registrants where event_id = ?)",
					$_POST['email_address'], $GLOBALS['gClientId'], $eventId);
				if ($resultSet['row_count'] > 1) {
					$returnArray['registrant_choices'] = "<h2>More than one found. Choose one:</h2>";
					while ($row = getNextRow($resultSet)) {
						$returnArray['registrant_choices'] .= "<p data-contact_id='" . $row['contact_id'] . "' class='registrant-choice'>" . getDisplayName($row['contact_id']) . "</p>";
					}
					echo jsonEncode($returnArray);
					exit;
				}
				if ($row = getNextRow($resultSet)) {
					$contactId = $row['contact_id'];
				} else {
					$returnArray['error_message'] = "Registration Not Found";
					echo jsonEncode($returnArray);
					exit;
				}
				$eventRegistrantId = getFieldFromId("event_registrant_id", "event_registrants", "event_id", $eventId, "contact_id = ?", $contactId);
				if (empty($eventRegistrantId)) {
					$returnArray['error_message'] = "Registration Not Found";
					echo jsonEncode($returnArray);
					exit;
				}
				executeQuery("update event_registrants set check_in_time = now() where check_in_time is null and event_registrant_id = ?", $eventRegistrantId);
				$returnArray['info_message'] = "Thanks for Checking In!";
				echo jsonEncode($returnArray);
				exit;
			case "search":
				$eventId = getFieldFromId("event_id", "events", "event_id", $_POST['event_id'], "(end_date is null or end_date >= current_date)");
				if (empty($eventId)) {
					$returnArray['error_message'] = "Invalid Event";
					echo jsonEncode($returnArray);
					exit;
				}
				if (empty($_POST['email_address'])) {
					$returnArray['error_message'] = "Missing Information";
					echo jsonEncode($returnArray);
					exit;
				}
				$resultSet = executeQuery("select * from contacts where email_address like ? and client_id = ? and contact_id in (select contact_id from event_registrants where event_id = ?)",
					$_POST['email_address'] . "%", $GLOBALS['gClientId'], $eventId);
				while ($row = getNextRow($resultSet)) {
					$returnArray['registrant_choices'] .= "<p data-contact_id='" . $row['contact_id'] . "' class='registrant-choice'>" . getDisplayName($row['contact_id']) . ", " . $row['email_address'] . "</p>";
				}
				echo jsonEncode($returnArray);
				exit;
			case "check_in_contact":
				$eventId = getFieldFromId("event_id", "events", "event_id", $_GET['event_id'], "(end_date is null or end_date >= current_date)");
				if (empty($eventId)) {
					$returnArray['error_message'] = "Invalid Event";
					echo jsonEncode($returnArray);
					exit;
				}
				$resultSet = executeQuery("select * from contacts where contact_id = ? and client_id = ? and contact_id in (select contact_id from event_registrants where event_id = ?)",
					$_GET['contact_id'], $GLOBALS['gClientId'], $eventId);
				if ($row = getNextRow($resultSet)) {
					$contactId = $row['contact_id'];
				} else {
					$returnArray['error_message'] = "Registration Not Found";
					echo jsonEncode($returnArray);
					exit;
				}
				$eventRegistrantId = getFieldFromId("event_registrant_id", "event_registrants", "event_id", $eventId, "contact_id = ?", $contactId);
				if (empty($eventRegistrantId)) {
					$returnArray['error_message'] = "Registration Not Found";
					echo jsonEncode($returnArray);
					exit;
				}
				executeQuery("update event_registrants set check_in_time = now() where check_in_time is null and event_registrant_id = ?", $eventRegistrantId);
				$returnArray['info_message'] = "Thanks for Checking In!";
				echo jsonEncode($returnArray);
				exit;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", ".registrant-choice", function () {
                var contactId = $(this).data("contact_id");
                loadAjaxRequest("<?php echo $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=check_in_contact&contact_id=" + contactId + "&event_id=" + $("#event_id").val(), function(returnArray) {
                    if (!("error_message" in returnArray)) {
                        $("#first_name").val("");
                        $("#last_name").val("");
                        $("#email_address").val("");
                        $("#_edit_form").validationEngine("hideAll");
                        $(".formFieldError").removeClass("formFieldError");
                        $("#email_address").focus();
                        $("#registrant_choices").html("");
                    }
                });
            });
            $("#check_in_button").click(function () {
                if ($("#_edit_form").validationEngine('validate')) {
                    loadAjaxRequest("<?php echo $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=check_in", $("#_edit_form").serialize(), function(returnArray) {
                        if ("registrant_choices" in returnArray) {
                            $("#registrant_choices").html(returnArray['registrant_choices']).removeClass("hidden");
                        } else if (!("error_message" in returnArray)) {
                            $("#first_name").val("");
                            $("#last_name").val("");
                            $("#email_address").val("");
                            $("#_edit_form").validationEngine("hideAll");
                            $(".formFieldError").removeClass("formFieldError");
                            $("#email_address").focus();
                            $("#registrant_choices").html("");
                        }
                    });
                }
                return false;
            });
            $("#email_address").keyup(function (event) {
                if (event.which == 13 || event.which == 3) {
                    $("#check_in_button").trigger("click");
                }
                var searchText = $(this).val();
                $("#registrant_choices").hide().html(searchText);
                if (searchText.length > 2) {
                    $("#registrant_choices").show().html("Searching...");
                    loadAjaxRequest("<?php echo $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=search", { email_address: $("#email_address").val(), event_id: $("#event_id").val() }, function(returnArray) {
                        if ("registrant_choices" in returnArray) {
                            $("#registrant_choices").html(returnArray['registrant_choices']);
                        }
                    });
                }
            });
            $("#email_address").focus();
        </script>
		<?php
	}

	function mainContent() {
		$eventRow = getRowFromId("events", "event_id", $this->iEventId);
		$contactRow = Contact::getContact($eventRow['contact_id']);
		echo $this->getPageData("content");
		?>
        <div id="check_in_form_wrapper">
            <form id="_edit_form">
                <input type="hidden" id="event_id" name="event_id" value="<?php echo $this->iEventId ?>">

                <div id="check_in_fields">
                    <h1><?= htmlText($eventRow['description']) ?></h1>
                    <p><?= date("m/d/Y", strtotime($eventRow['start_date'])) ?></p>
					<?php if (!empty($eventRow['detailed_description'])) {
						echo "<p>" . $eventRow['detailed_description'] . "</p>";
					}
					if (!empty($contactRow)) { ?>
                        <p>
							<?php if (!empty($contactRow['business_name'])) {
								echo htmlText($contactRow['business_name']) . "<br>";
							}
							echo htmlText($contactRow['address_1']) . "<br>";
							echo htmlText($contactRow['city']) . ", " . $contactRow['state']; ?>
                        </p>
					<?php } ?>
                    <div id="fields_wrapper">
						<?php echo createFormControl("contacts", "email_address", array("not_null" => true, "placeholder" => "Email")) ?>
                    </div>
                    <div id="registrant_choices">
                    </div>

                    <p class="error-message" id="error_message"></p>
                    <p class="align-center">
                        <button class="check-in-only" id="check_in_button">Check In</button>
                    </p>
                </div>

            </form>
        </div> <!-- create_form_wrapper -->

		<?php
		echo $this->getPageData("after_form_content");
		return true;
	}
}

$pageObject = new EventCheckInPage();
$pageObject->displayPage();
