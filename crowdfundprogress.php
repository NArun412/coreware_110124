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

$GLOBALS['gPageCode'] = "CROWDFUNDPROGRESS";
require_once "shared/startup.inc";

class CrowdFundProgressPage extends Page {

	var $iCrowdFundCampaignRow = array();
	var $iUrlAliasTypeCode = false;
	var $iDesignationId = false;

	function setup() {
		$this->iUrlAliasTypeCode = $this->getPageTextChunk("url_alias_type_code");
		if (empty($this->iUrlAliasTypeCode)) {
			$tableId = getFieldFromId("table_id", "tables", "table_name", "crowd_fund_campaign_participants");
			$this->iUrlAliasTypeCode = getFieldFromId("url_alias_type_code", "url_alias_types", "table_id", $tableId);
		}
		$this->iCrowdFundCampaignRow = getRowFromId("crowd_fund_campaigns", "crowd_fund_campaign_code", $_GET['crowd_fund_campaign_code']);
		if (empty($this->iCrowdFundCampaignRow)) {
			$this->iCrowdFundCampaignRow = getRowFromId("crowd_fund_campaigns", "crowd_fund_campaign_code", $_POST['crowd_fund_campaign_code']);
		}
		if (empty($this->iCrowdFundCampaignRow)) {
			header("Location: /");
			exit;
		}
		$this->iDesignationId = getFieldFromId("designation_id", "designations", "designation_id", $_GET['designation_id']);
	}

	function mainContent() {
		echo $this->getPageData("content");
		$joinLink = getFieldFromId("link_name", "pages", "script_filename", "crowdfundsetup.php", "script_arguments = 'crowd_fund_campaign_code=" . $this->iCrowdFundCampaignRow['crowd_fund_campaign_code'] . "'");
		$crowdFundCampaignParticipantId = ($GLOBALS['gLoggedIn'] ? getFieldFromId("crowd_fund_campaign_participant_id", "crowd_fund_campaign_participants", "contact_id", $GLOBALS['gUserRow']['contact_id']) : false);

		?>
        <h1><?= htmlText($this->iCrowdFundCampaignRow['description']) ?></h1>
		<?= makeHtml($this->iCrowdFundCampaignRow['detailed_description']) ?>
        <div class='clear-div'></div>
		<?php
		if (!empty($this->iCrowdFundCampaignRow['giving_goal'])) {
			$donationTotal = 0;
			$resultSet = executeQuery("select sum(amount) from donations where donation_source_id in (select donation_source_id from crowd_fund_campaign_participants where crowd_fund_campaign_id = ?) and client_id = ?",
				$this->iCrowdFundCampaignRow['crowd_fund_campaign_id'], $GLOBALS['gClientId']);
			if ($row = getNextRow($resultSet)) {
				$donationTotal = $row['sum(amount)'];
			}
			if ($donationTotal < $this->iCrowdFundCampaignRow['giving_goal']) {
				$percent = round($donationTotal * 100 / $this->iCrowdFundCampaignRow['giving_goal'], 1);
				?>
                <div id="progress_wrapper">
                    <div id="amount_raised_wrapper">
                        <div id="amount_raised" style='left: <?= $percent ?>%'>$<?= number_format($donationTotal, 2) ?></div>
                    </div>
                    <div id="giving_progress_bar_wrapper">
                        <div id="giving_progress_bar" style='left: <?= $percent ?>%'></div>
                    </div>
                    <div id="giving_goal_wrapper">
                        <div id="giving_goal">$<?= number_format($this->iCrowdFundCampaignRow['giving_goal'], 2) ?></div>
                    </div>
                </div>
				<?php
			}
		}
		if ($crowdFundCampaignParticipantId) {
			$resultSet = executeQuery("select *,(select description from designations where designation_id = crowd_fund_campaign_participant_designations.designation_id) as description from crowd_fund_campaign_participant_designations join " .
				"crowd_fund_campaign_participants using (crowd_fund_campaign_participant_id) where contact_id = ? and crowd_fund_campaign_id = ? order by description", $GLOBALS['gUserRow']['contact_id'], $this->iCrowdFundCampaignRow['crowd_fund_campaign_id']);
			?>
            <h2>Your Fund Raising Progress</h2>
            <table id='my_fund_raising_progress' class='grid-table'>
                <tr>
                    <th>Designation</th>
                    <th>Amount Raised</th>
                </tr>
				<?php
				$totalDonations = 0;
				$givingGoal = false;
				$donationArray = array();
				while ($row = getNextRow($resultSet)) {
					if ($givingGoal === false) {
						$givingGoal = $row['giving_goal'];
					}
					$donationAmount = 0;
					$donationSet = executeQuery("select * from donations join contacts using (contact_id) where donation_source_id = ? and donation_date between ? and ?" .
						(empty($this->iDesignationId) ? "" : " and designation_id = " . $this->iDesignationId), $row['donation_source_id'], $this->iCrowdFundCampaignRow['start_date'], $this->iCrowdFundCampaignRow['end_date']);
					while ($donationRow = getNextRow($donationSet)) {
						$donationAmount += $donationRow['amount'];
						if (empty($donationRow['anonymous_gift'])) {
							$donationArray[] = $donationRow;
						}
					}
					if (empty($donationAmount) || $donationAmount < 0) {
						$donationAmount = 0;
					}
					$totalDonations += $donationAmount;
					?>
                    <tr>
                        <td><?= htmlText($row['description']) ?></td>
                        <td class='align-right'>$<?= number_format($donationAmount, 2) ?></td>
                    </tr>
					<?php
				}
				?>
            </table>
            <table id='my_fund_raising_details' class='grid-table'>
                <tr>
                    <th>Donation Date</th>
                    <th>For</th>
                    <th>From</th>
                    <th>Amount</th>
                </tr>
				<?php
				foreach ($donationArray as $row) {
					?>
                    <tr>
                        <td><?= date("m/d/Y", strtotime($row['donation_date'])) ?></td>
                        <td><?= htmlText(getFieldFromId("description", "designations", "designation_id", $row['designation_id'])) ?></td>
                        <td><?= htmlText($row['first_name'] . " " . substr($row['last_name'], 0, 1)) ?></td>
                        <td class='align-right'><?= number_format($row['amount'], 2) ?></td>
                    </tr>
					<?php
				}
				?>
            </table>
			<?php
			if (!empty($givingGoal)) {
				echo "<p>Fund Raising Goal: " . number_format($givingGoal, 2) . "</p>";
			}
			echo "<p>Total Raised: " . number_format($totalDonations, 2) . "</p>";
			if (!empty($this->iUrlAliasTypeCode)) {
				$linkName = getFieldFromId("link_name", "crowd_fund_campaign_participants", "crowd_fund_campaign_participant_id", $crowdFundCampaignParticipantId);
				if (!empty($linkName)) {
					?>
                    <p><a href='/<?= $this->iUrlAliasTypeCode ?>/<?= $linkName ?>' target='_blank'>Click here for your giving page.</a></p>
					<?php
				}
			}
			$resultSet = executeQuery("select * from crowd_fund_campaign_participants join crowd_fund_campaigns using (crowd_fund_campaign_id) where crowd_fund_campaigns.crowd_fund_campaign_id <> ? and crowd_fund_campaigns.inactive = 0 and " .
				"internal_use_only = 0 and start_date <= current_date and end_date >= current_date and contact_id = ?", $this->iCrowdFundCampaignRow['crowd_fund_campaign_id'], $GLOBALS['gUserRow']['contact_id']);
			if ($resultSet['row_count'] > 0) {
				?>
                <h2>Your Other Campaigns</h2>
				<?php
				while ($row = getNextRow($resultSet)) {
					?>
                    <p><a href='/crowdfundprogress.php?crowd_fund_campaign_code=<?= $row['crowd_fund_campaign_code'] ?>'><?= htmlText($row['description']) ?></a></p>
					<?php
				}
			}
		} else {
			if (!empty($joinLink)) {
				?>
                <p><a class='button' href='/<?= $joinLink ?>'>Join this fund-raising effort</a></p>
				<?php
			}
		}

		$resultSet = executeQuery("select donation_source_id,sum(amount) from donations where client_id = ? and donation_date between ? and ? and " .
			(empty($this->iDesignationId) ? "" : "designation_id = " . $this->iDesignationId . " and ") .
			"donation_source_id in (select donation_source_id from crowd_fund_campaign_participants where crowd_fund_campaign_id = ?) group by donation_source_id having sum(amount) > 0 order by sum(amount) desc",
			$GLOBALS['gClientId'], makeDateParameter($this->iCrowdFundCampaignRow['start_date']), makeDateParameter($this->iCrowdFundCampaignRow['end_date']), $this->iCrowdFundCampaignRow['crowd_fund_campaign_id']);

		$dataArray = array();
		$designationArray = array();
		while ($row = getNextRow($resultSet)) {
			$crowdFundCampaignParticipantRow = getRowFromId("crowd_fund_campaign_participants", "crowd_fund_campaign_id", $this->iCrowdFundCampaignRow['crowd_fund_campaign_id'], "donation_source_id = ?", $row['donation_source_id']);
			if (empty($crowdFundCampaignParticipantRow)) {
				continue;
			}
			$row['crowd_fund_campaign_participant_row'] = $crowdFundCampaignParticipantRow;
			$designationSet = executeQuery("select designation_id,description from designations where inactive = 0 and internal_use_only = 0 and designation_id in (select designation_id from crowd_fund_campaign_participant_designations where crowd_fund_campaign_participant_id = ?)", $crowdFundCampaignParticipantRow['crowd_fund_campaign_participant_id']);
			while ($designationRow = getNextRow($designationSet)) {
				if (!array_key_exists($designationRow['designation_id'], $designationArray)) {
					$designationArray[$designationRow['designation_id']] = $designationRow['description'];
				}
			}
			$dataArray[] = $row;
		}
		asort($designationArray);
		?>
        <h2>Fund Raising Leaders</h2>
		<?php if (!empty($this->iUrlAliasTypeCode)) { ?>
            <p>Click the name to go to the fund raiser's donation page.</p>
		<?php } ?>
		<?php if (count($designationArray) > 1 && empty($this->iDesignationId)) { ?>
            <div class='form-line'>
                <label>Filter List</label>
                <select id='designation_id_filter'>
                    <option value=''>[All]</option>
					<?php foreach ($designationArray as $designationId => $description) { ?>
                        <option <?= ($designationId == $this->iDesignationId ? "selected " : "") ?>value='<?= $designationId ?>'><?= htmlText($description) ?></option>
					<?php } ?>
                </select>
            </div>
		<?php } ?>
		<?php if (!empty($this->iDesignationId)) { ?>
            <p>
                <button id='show_all_designations'>Show All Giving Funds</button>
            </p>
		<?php } ?>
        <table class='grid-table' id='fund_raising_leaders'>
            <tr>
                <td>Name</td>
                <td class='details-designation'>Raising Funds For</td>
                <td>Goal</td>
                <td>Amount Raised</td>
            </tr>
			<?php
			$crowdFundCampaignParticipantIds = array();
			foreach ($dataArray as $row) {
				$crowdFundCampaignParticipantRow = $row['crowd_fund_campaign_participant_row'];
				$crowdFundCampaignParticipantIds[] = $crowdFundCampaignParticipantRow['crowd_fund_campaign_participant_id'];
				$contactRow = getRowFromId("contacts", "contact_id", $crowdFundCampaignParticipantRow['contact_id']);
				$name = htmlText($contactRow['first_name'] . " " . substr($contactRow['last_name'], 0, 1));
				$goal = (empty($crowdFundCampaignParticipantRow['giving_goal']) ? "" : "$" . number_format($crowdFundCampaignParticipantRow['giving_goal'], 2));
				$total = number_format($row['sum(amount)'], 2);
				if (!empty($this->iUrlAliasTypeCode)) {
					$name = "<a href='/" . $this->iUrlAliasTypeCode . "/" . $crowdFundCampaignParticipantRow['link_name'] . "'>" . $name . "</a>";
				}
				$designations = "";
				$designationSet = executeQuery("select * from designations where inactive = 0 and internal_use_only = 0 and designation_id in (select designation_id from crowd_fund_campaign_participant_designations where crowd_fund_campaign_participant_id = ?) order by sort_order,description", $crowdFundCampaignParticipantRow['crowd_fund_campaign_participant_id']);
				while ($designationRow = getNextRow($designationSet)) {
					if (!empty($this->iDesignationId) && $this->iDesignationId != $designationRow['designation_id']) {
						continue;
					}
					$designations .= (empty($designations) ? "" : "<br>") . $designationRow['description'];
				}
				?>
                <tr class='leader-data-row'>
                    <td><?= $name ?></td>
                    <td class='details-designation'><?= $designations ?></td>
                    <td class='align-right'><?= $goal ?></td>
                    <td class='align-right'>$<?= $total ?></td>
                </tr>
				<?php
			}
			$resultSet = executeQuery("select * from crowd_fund_campaign_participants where crowd_fund_campaign_id = ?", $this->iCrowdFundCampaignRow['crowd_fund_campaign_id']);
			while ($row = getNextRow($resultSet)) {
				if (in_array($row['crowd_fund_campaign_participant_id'], $crowdFundCampaignParticipantIds)) {
					continue;
				}
				$contactRow = getRowFromId("contacts", "contact_id", $row['contact_id']);
				$name = htmlText($contactRow['first_name'] . " " . substr($contactRow['last_name'], 0, 1));
				$goal = (empty($row['giving_goal']) ? "" : "$" . number_format($row['giving_goal'], 2));
				$total = "0.00";
				if (!empty($this->iUrlAliasTypeCode)) {
					$name = "<a href='/" . $this->iUrlAliasTypeCode . "/" . $row['link_name'] . "'>" . $name . "</a>";
				}
				$designations = "";
				$designationSet = executeQuery("select * from designations where inactive = 0 and internal_use_only = 0 and designation_id in (select designation_id from crowd_fund_campaign_participant_designations where crowd_fund_campaign_participant_id = ?) order by sort_order,description", $row['crowd_fund_campaign_participant_id']);
				while ($designationRow = getNextRow($designationSet)) {
					if (!empty($this->iDesignationId) && $this->iDesignationId != $designationRow['designation_id']) {
						continue;
					}
					$designations .= (empty($designations) ? "" : "<br>") . $designationRow['description'];
				}
				if (empty($designations)) {
					continue;
				}
				?>
                <tr class='leader-data-row'>
                    <td><?= $name ?></td>
                    <td class='details-designation'><?= $designations ?></td>
                    <td class='align-right'><?= $goal ?></td>
                    <td class='align-right'>$<?= $total ?></td>
                </tr>
				<?php
			}
			?>
        </table>
		<?php

		echo $this->getPageData("after_form_content");
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#designation_id_filter").change(function () {
                document.location = "<?= $GLOBALS['gLinkUrl'] ?>?designation_id=" + $(this).val();
            });
            $(document).on("click", "#show_all_designations", function () {
                document.location = "<?= $GLOBALS['gLinkUrl'] ?>";
                return false;
            });
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #my_fund_raising_progress {
                margin-bottom: 10px;
            }

            #giving_goal_wrapper {
                position: relative;
                line_height: 30px;
                height: 30px;
                padding: 0;
                padding-left: 3px;
                margin: 0 0 10px 0;
            }

            #giving_goal {
                color: rgb(255, 255, 255);
                position: absolute;
                display: inline-block;
                height: 100%;
                border-radius: 4px;
                font-family: "Open Sans";
                font-size: 1em;
                font-weight: bold;
                padding: 0 0.8em;
                background-color: rgb(180, 180, 180);
                right: 0;
                margin-top: 8px;
                line-height: 30px;
            }

            #giving_goal:after {
                position: absolute;
                width: 0;
                height: 0;
                border-bottom: 10px solid rgb(180, 180, 180);
                border-right: 8px solid rgb(180, 180, 180);
                border-left: 8px solid transparent;
                border-top: 10px solid transparent;
                content: "";
                top: -11px;
                right: 0;
            }

            #progress_wrapper {
                max-width: 600px;
                width: 100%;
                margin: 20px auto;
            }

            #giving_progress_bar_wrapper {
                overflow: hidden;
                height: 30px;
                border: 1px solid rgb(150, 150, 150);
                position: relative;
            }

            #giving_progress_bar {
                background-color: rgb(240, 240, 150);
                position: absolute;
                top: 0;
                height: 30px;
                width: 100%;
            }

            #amount_raised_wrapper {
                position: relative;
                line-height: 30px;
                height: 30px;
                padding: 0;
                padding-left: 3px;
                margin: 0 0 10px 0;
            }

            #amount_raised {
                color: rgb(0, 0, 0);
                position: absolute;
                display: inline-block;
                height: 100%;
                border-radius: 4px;
                font-family: "Open Sans";
                font-size: 1em;
                font-weight: bold;
                padding: 0 0.8em;
                background-color: rgb(240, 240, 240);
                line-height: 30px;
            }

            #amount_raised:after {
                position: absolute;
                width: 0;
                height: 0;
                border-right: 8px solid transparent;
                border-bottom: 10px solid transparent;
                border-left: 8px solid rgb(240, 240, 240);
                border-top: 10px solid rgb(240, 240, 240);
                content: "";
                bottom: -12px;
                left: 0;
            }
            #fund_raising_leaders {
                max-width: 600px;
                width: 100%;
                margin: 20px auto;
            }
        </style>
		<?php
	}
}

$pageObject = new CrowdFundProgressPage();
$pageObject->displayPage();
