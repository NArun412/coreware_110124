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

$GLOBALS['gPageCode'] = "CROWDFUNDSETUP";
require_once "shared/startup.inc";

class CrowdFundSetupPage extends Page {

	var $iUrlAliasTypeCode = false;
	var $iCrowdFundCampaignRow = array();

	function setup() {
		$this->iUrlAliasTypeCode = $this->getPageTextChunk("url_alias_type_code");
		if (empty($this->iUrlAliasTypeCode)) {
			$tableId = getFieldFromId("table_id", "tables", "table_name", "crowd_fund_campaign_participants");
			$this->iUrlAliasTypeCode = getFieldFromId("url_alias_type_code", "url_alias_types", "table_id", $tableId);
		}
		$this->iCrowdFundCampaignRow = getRowFromId("crowd_fund_campaigns", "crowd_fund_campaign_code", $_GET['crowd_fund_campaign_code'], "inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
		if (empty($this->iCrowdFundCampaignRow)) {
			$this->iCrowdFundCampaignRow = getRowFromId("crowd_fund_campaigns", "crowd_fund_campaign_code", $_POST['crowd_fund_campaign_code'], "inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
		}
		if (empty($this->iCrowdFundCampaignRow)) {
			header("Location: /");
			exit;
		}
	}

	function headerIncludes() {
		?>
        <script>(function (d, s, id) {
                var js, fjs = d.getElementsByTagName(s)[0];
                if (d.getElementById(id)) return;
                js = d.createElement(s);
                js.id = id;
                js.src = "https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v3.0";
                fjs.parentNode.insertBefore(js, fjs);
            }(document, 'script', 'facebook-jssdk'));</script>
		<?php
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "create_page":
				if (empty($this->iUrlAliasTypeCode)) {
					$returnArray['error_message'] = "Unable to create page. Please contact customer service.";
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					ajaxResponse($returnArray);
					exit;
				}
				$GLOBALS['gPrimaryDatabase']->startTransaction();
				if (!$GLOBALS['gLoggedIn']) {
					$returnArray['error_message'] = "Unable to create page. Please contact customer service.";
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					ajaxResponse($returnArray);
					exit;
				}
				$contactId = $GLOBALS['gUserRow']['contact_id'];
				$donationSourceId = getFieldFromId("donation_source_id", "donation_sources", "contact_id", $contactId);
				if (empty($donationSourceId)) {
					$insertSet = executeQuery("insert into donation_sources (client_id,donation_source_code,description,contact_id,internal_use_only) values (?,?,?,?,1)",
						$GLOBALS['gClientId'], getRandomString(16, array("uppercase" => true)), "Referral by " . getDisplayName($contactId), $contactId);
					$donationSourceId = $insertSet['insert_id'];
				}
				if (empty($donationSourceId)) {
					$returnArray['error_message'] = "Unable to create page. Please refresh and try again or contact customer service.";
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					ajaxResponse($returnArray);
					exit;
				}
				$designationIds = array();
				foreach ($_POST as $fieldName => $fieldData) {
					if (startsWith($fieldName, "designation_id_") && !empty($fieldData)) {
						$designationId = getFieldFromId("designation_id", "designations", "designation_id", $fieldData, "inactive = 0 and internal_use_only = 0");
						if (!empty($designationId)) {
							$designationIds[] = $designationId;
						}
					}
				}
				if (empty($_POST['link_name']) || empty($designationIds) || empty($_POST['title_text'])) {
					$returnArray['error_message'] = "Page Title, Link name, and at least one designation are required.";
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					ajaxResponse($returnArray);
					exit;
				}
				$crowdFundCampaignParticipantId = getFieldFromId("crowd_fund_campaign_participant_id", "crowd_fund_campaign_participants", "crowd_fund_campaign_id", $this->iCrowdFundCampaignRow['crowd_fund_campaign_id'],
					"contact_id = ?", $contactId);
				if (!empty($crowdFundCampaignParticipantId)) {
					executeQuery("delete from crowd_fund_campaign_participant_designations where crowd_fund_campaign_participant_id = ?", $crowdFundCampaignParticipantId);
				}
				$linkName = makeCode($_POST['link_name'], array("lowercase" => true, "use_dash" => true));
				$duplicateParticipantId = getFieldFromId("crowd_fund_campaign_participant_id", "crowd_fund_campaign_participants", "link_name", $linkName);
				if (!empty($duplicateParticipantId) && $duplicateParticipantId != $crowdFundCampaignParticipantId) {
					$returnArray['error_message'] = "Link name must be unique. This one is already in use. Please try another.";
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					ajaxResponse($returnArray);
					exit;
				}
				if (empty($crowdFundCampaignParticipantId)) {
					$insertSet = executeQuery("insert into crowd_fund_campaign_participants (client_id,crowd_fund_campaign_id,title_text,detailed_description,giving_goal,contact_id,donation_source_id,link_name) values (?,?,?,?,?, ?,?,?)",
						$GLOBALS['gClientId'], $this->iCrowdFundCampaignRow['crowd_fund_campaign_id'], $_POST['title_text'], $_POST['detailed_description'], $_POST['giving_goal'], $contactId, $donationSourceId, $linkName);
					$crowdFundCampaignParticipantId = $insertSet['insert_id'];
					if (empty($crowdFundCampaignParticipantId)) {
						$returnArray['error_message'] = "Unable to create donation page. Contact customer service.";
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						ajaxResponse($returnArray);
						exit;
					}
				} else {
					$dataTable = new DataTable("crowd_fund_campaign_participants");
					$nameValues = array("title_text" => $_POST['title_text'], "detailed_description" => $_POST['detailed_description'], "giving_goal" => $_POST['giving_goal'], "donation_source_id" => $donationSourceId, "link_name" => $linkName);
					if (!$dataTable->saveRecord(array("name_values" => $nameValues, "primary_id" => $crowdFundCampaignParticipantId))) {
						$returnArray['error_message'] = "Unable to update donation page. Contact customer service.";
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						ajaxResponse($returnArray);
						exit;
					}
				}
				$designationList = "";
				foreach ($designationIds as $designationId) {
					executeQuery("insert ignore into crowd_fund_campaign_participant_designations (crowd_fund_campaign_participant_id,designation_id) values (?,?)", $crowdFundCampaignParticipantId, $designationId);
					$designationList .= (empty($designationList) ? "" : "<br>") . getFieldFromId("description", "designations", "designation_id", $designationId);
				}
				$GLOBALS['gPrimaryDatabase']->commitTransaction();

				$linkUrl = getDomainName() . "/" . $this->iUrlAliasTypeCode . "/" . $linkName;
				if (!empty($this->iCrowdFundCampaignRow['email_id'])) {
					$contactRow = getRowFromId("contacts", "contact_id", $contactId);
					if (!empty($contactRow['email_address'])) {
						$substitutions = array_merge($contactRow, $_POST);
						$substitutions['link_url'] = $linkUrl;
						$substitutions['designation_list'] = $designationList;
						sendEmail(array("email_id" => $this->iCrowdFundCampaignRow['email_id'], "email_address" => $contactRow['email_address'], "contact_id" => $contactId, "substitutions" => $substitutions));
					}
				}

				$response = $this->getPageTextChunk("response");
				if (empty($response)) {
					$response = "<p>Thank you for your support. The URL for the fund raising page is:</p><p><a target='_blank' href='%link_url%'>%link_url%</a></p>";
				} else {
					$response = makeHtml($response);
				}
				if (strpos($response, "%link_url%") === false) {
					$response .= "<p>Fund Raising page URL: <a target='_blank' href='" . $linkUrl . "'>" . $linkUrl . "</a></p>";
				} else {
					$response = str_replace("%link_url%", $linkUrl, $response);
				}
				$response .= "\n<div class='fb-share-button' data-href='" . $linkUrl . "' data-layout='button_count' data-size='small'><a target='_blank' href='https://www.facebook.com/sharer/sharer.php?u=" . urlencode($linkUrl) . "' class='fb-xfbml-parse-ignore'>Share</a></div>";

				$returnArray['response'] = $response;

				ajaxResponse($returnArray);
				exit;
		}
	}

	function mainContent() {
		echo $this->getPageData("content");
		$designations = array();
		$resultSet = executeReadQuery("select * from designations where inactive = 0 and internal_use_only = 0 and client_id = ? and designation_id in " .
			"(select designation_id from crowd_fund_campaign_designations where crowd_fund_campaign_id = ?) order by sort_order,designation_id", $GLOBALS['gClientId'], $this->iCrowdFundCampaignRow['crowd_fund_campaign_id']);
		while ($row = getNextRow($resultSet)) {
			$designations[$row['designation_id']] = $row['description'];
		}
		if (empty($designations)) {
			?>
            <p>There are no designations available at this time. Please contact customer service.</p>
			<?php
			echo $this->getPageData("after_form_content");
			return true;
		}
		if (empty($this->iUrlAliasTypeCode)) {
			?>
            <p>No page setup to accept donations. Please contact customer service.</p>
			<?php
			echo $this->getPageData("after_form_content");
			return true;
		}
		$selectedDesignations = array();
		if ($GLOBALS['gLoggedIn']) {
			$crowdFundCampaignParticipantRow = getRowFromId("crowd_fund_campaign_participants", "crowd_fund_campaign_id", $this->iCrowdFundCampaignRow['crowd_fund_campaign_id'],
				"contact_id = ?", $GLOBALS['gUserRow']['contact_id']);
			if (empty($crowdFundCampaignParticipantRow['link_name'])) {
				$crowdFundCampaignParticipantRow['link_name'] = makeCode($GLOBALS['gUserRow']['first_name'] . " " . $GLOBALS['gUserRow']['last_name'] . " fund raising", array("lowercase" => true, "use_dash" => true));
			}
			if (empty($crowdFundCampaignParticipantRow['title_text'])) {
				$crowdFundCampaignParticipantRow['title_text'] = $GLOBALS['gUserRow']['first_name'] . " " . $GLOBALS['gUserRow']['last_name'] . "'s fund raising";
			}
			if (!empty($crowdFundCampaignParticipantRow)) {
				$resultSet = executeQuery("select * from crowd_fund_campaign_participant_designations where crowd_fund_campaign_participant_id = ?", $crowdFundCampaignParticipantRow['crowd_fund_campaign_participant_id']);
				while ($row = getNextRow($resultSet)) {
					$selectedDesignations[] = $row['designation_id'];
				}
			}
		} else {
			$crowdFundCampaignParticipantRow = array();
		}
		if (!$GLOBALS['gLoggedIn']) {
			$myAccountPage = getFieldFromId("page_id", "pages", "link_name", "my-account", "script_filename in ('retailstore/myaccount.php','publicaccount.php') and template_id = ?", $GLOBALS['gPageRow']['template_id']);
			$loginPage = getFieldFromId("page_id", "pages", "link_name", "login", "script_filename = 'loginform.php' and template_id = ?", $GLOBALS['gPageRow']['template_id']);
		}
		$introText = $this->getPageTextChunk("intro_text");
		if (empty($introText)) {
			$introText = "A user account is required to set up a fund raising page. Either log in or create an account.";
		}
		?>
        <div id="crowd_fund_wrapper">
            <p class='error-message' id="error_message"></p>
            <h2><?= htmlText($this->iCrowdFundCampaignRow['description']) ?></h2>
            <form name="_edit_form" id="_edit_form">
				<?php if (!$GLOBALS['gLoggedIn']) { ?>
                    <div id="login_wrapper">
                        <p><?= $introText ?></p>
                        <p><?= (empty($myAccountPage) ? "" : "<a class='button' href='/my-account?referrer=" . $GLOBALS['gLinkUrl'] . "'>Create Account</a>") ?><?= (empty($loginPage) ? "" : "<a class='button' href='/login?url=" . $GLOBALS['gLinkUrl'] . "'>Login</a>") ?></p>
                    </div>
				<?php } else { ?>
				<div class="form-line" id="_title_text_row">
					<label class='required-label'>Page Title</label>
					<input tabindex="10" type="text" class="validate[required]" maxlength='100' size='80' id="title_text" name="title_text" value='<?= htmlText($crowdFundCampaignParticipantRow['title_text']) ?>'>
				</div>
				<div class="form-line" id="_detailed_description_row">
					<label>Personalized Message</label>
					<textarea tabindex="10" id="detailed_description" name="detailed_description"><?= htmlText($crowdFundCampaignParticipantRow['title_text']) ?></textarea>
				</div>
                <div class="form-line" id="_giving_goal_row">
                    <label>Your Fund Raising Goal</label>
                    <span class='help-label'>Your overall goal, for all giving</span>
                    <input tabindex="10" type="text" class="align-right validate[custom[number]]" data-decimal-places='2' size='12' id="giving_goal" name="giving_goal" value='<?= $crowdFundCampaignParticipantRow['giving_goal'] ?>'>
                </div>
                <div class="form-line" id="_link_name_row">
                    <label class='required-label'>Link for the page</label>
                    <span class='help-label'>This has to be unique in the site, so don't choose something generic.</span>
                    <input tabindex="10" type="text" class="url-link lowercase validate[required]" maxlength='100' size='100' id="link_name" name="link_name" value='<?= $crowdFundCampaignParticipantRow['link_name'] ?>'>
                </div>
                <hr>
                <div class='form-line' id='_designations_row'>
                    <label class='required-label'>Choose one or more designations that will be available on your page</label>
                    <p>
                        <button id="select_all">Select All</button>
                    </p>
                    <div id='_designation_wrapper'>
						<?php
						foreach ($designations as $designationId => $description) {
							?>
                            <div class='designation-wrapper'><input class='designation-checkbox' <?= (in_array($designationId, $selectedDesignations) ? "checked " : "") ?>tabindex='10' type='checkbox' name='designation_id_<?= $designationId ?>' id='designation_id_<?= $designationId ?>' value='<?= $designationId ?>'><label class='checkbox-label' for='designation_id_<?= $designationId ?>'><?= htmlText($description) ?></label></div>
							<?php
						}
						?>
                    </div>
                </div>
            </form>
            <p>
                <button tabindex="10" id="submit_form">Create Page</button>
            </p>
			<?php } ?>
        </div>
		<?php
		echo $this->getPageData("after_form_content");
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", "#select_all", function () {
                $(".designation-checkbox").prop("checked", true);
                return false;
            });
            $(document).on("focus", "#title_text", function () {
                if (!empty($("#first_name").val()) && !empty($("#last_name").val()) && empty($(this).val())) {
                    $(this).val($("#first_name").val() + " " + $("#last_name").val() + "'s Fund Raising");
                }
            });
            $(document).on("focus", "#link_name", function () {
                if (!empty($("#first_name").val()) && !empty($("#last_name").val()) && empty($(this).val())) {
                    $(this).val(makeCode($("#first_name").val() + " " + $("#last_name").val() + " Fund Raising", {useDash: true, lowerCase: true}));
                }
            });
            $(document).on("click", "#submit_form", function () {
                if ($(".designation-checkbox:checked").length == 0) {
                    displayErrorMessage("At least one designation must be selected");
                    return false;
                }
                if ($("#_edit_form").validationEngine("validate")) {
                    $("#submit_form").addClass("hidden");
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_page", $("#_edit_form").serialize(), function (returnArray) {
                        if ("error_message" in returnArray) {
                            $("#submit_form").removeClass("hidden");
                        } else {
                            if ("response" in returnArray) {
                                $("#crowd_fund_wrapper").html(returnArray['response']);
                            } else {
                                $("#crowd_fund_wrapper").html("Page Created");
                            }
                        }
                    });
                }
                return false;
            });
            addCKEditor();
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #_designations_row {
                margin: 40px 0;
            }

            #_designation_wrapper {
                display: flex;
                flex-wrap: wrap;
            }

            #_designation_wrapper div {
                flex: 0 0 50%;
                white-space: nowrap;
                padding-right: 20px;
            }
            #login_wrapper {
                margin: 40px 0;
            }
            #login_wrapper p {
                font-size: 1rem;
                font-weight: 700;
            }
        </style>
		<?php
	}
}

$pageObject = new CrowdFundSetupPage();
$pageObject->displayPage();
