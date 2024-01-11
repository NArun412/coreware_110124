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

$GLOBALS['gPageCode'] = "CROWDFUNDCAMPAIGNPARTICIPANTMAINT";
require_once "shared/startup.inc";

class CrowdFundCampaignParticipantMaintenancePage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("delete", "add"));
		}
		$filters = array();
		$designations = array();
		$resultSet = executeQuery("select * from designations where client_id = ? and designation_id in (select designation_id from crowd_fund_campaign_designations)", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$designations[$row['designation_id']] = $row['description'];
		}
		if (count($designations) > 0) {
			$filters['designation_id'] = array("form_label" => "Designation", "where" => "crowd_fund_campaign_participant_id in (select crowd_fund_campaign_participant_id from crowd_fund_campaign_participant_designations where designation_id = %key_value%)", "data_type" => "select", "choices" => $designations);
			$this->iTemplateObject->getTableEditorObject()->addVisibleFilters($filters);
		}
	}

	function supplementaryContent() {
		?>
        <h2>Funds Raised</h2>
        <div id='current_donations'></div>
		<?php
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("title_text", "data_type", "varchar");
		$this->iDataSource->addColumnControl("contact_id", "readonly", true);
		$this->iDataSource->addColumnControl("contact_id", "data_type", "contact_picker");
		$this->iDataSource->addColumnControl("donation_source_id", "readonly", true);
		$this->iDataSource->addColumnControl("crowd_fund_campaign_id", "readonly", true);
		$this->iDataSource->addColumnControl("crowd_fund_campaign_participant_designations", "data_type", "custom");
		$this->iDataSource->addColumnControl("crowd_fund_campaign_participant_designations", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("crowd_fund_campaign_participant_designations", "form_label", "Designations");
		$this->iDataSource->addColumnControl("crowd_fund_campaign_participant_designations", "links_table", "crowd_fund_campaign_participant_designations");
		$this->iDataSource->addColumnControl("crowd_fund_campaign_participant_designations", "control_table", "designations");
	}

	function internalCSS() {
		?>
        <style>
            #title_text {
                width: 500px;
            }
        </style>
		<?php
	}

	function afterGetRecord(&$returnArray) {
		ob_start();
		?>
        <table class='grid-table' id='current_donations_table'>
            <tr>
                <th>Designation</th>
                <th>Amount</th>
            </tr>
			<?php
			$crowdFundCampaignRow = getRowFromId("crowd_fund_campaigns", "crowd_fund_campaign_id", $returnArray['crowd_fund_campaign_id']['data_value']);
			$resultSet = executeQuery("select * from designations where designation_id in (select designation_id from crowd_fund_campaign_participant_designations where crowd_fund_campaign_participant_id = ?) order by description", $returnArray['primary_id']['data_value']);
			$totalDonations = 0;
			while ($row = getNextRow($resultSet)) {
				$donationSet = executeQuery("select sum(amount) from donations where designation_id = ? and donation_source_id = ? and donation_date between ? and ?", $row['designation_id'], $returnArray['donation_source_id']['data_value'], $crowdFundCampaignRow['start_date'], $crowdFundCampaignRow['end_date']);
				$donationRow = getNextRow($donationSet);
				$designationTotal = $donationRow['sum(amount)'];
				if (empty($designationTotal)) {
					$designationTotal = 0;
				}
                $totalDonations += $designationTotal;
				?>
                <tr>
                    <td><?= htmlText($row['description']) ?></td>
                    <td class='align-right'><?= number_format($designationTotal, 2) ?></td>
                </tr>
				<?php
			}
			if ($resultSet['row_count'] > 1) {
				?>
                <tr>
                    <th class='highlighted-text'>Total</th>
                    <th class='highlighted-text align-right'><?= number_format($totalDonations,2) ?></th>
                </tr>
				<?php
			}
			?>
        </table>
		<?php
		$returnArray['current_donations'] = array("data_value" => ob_get_clean());
	}
}

$pageObject = new CrowdFundCampaignParticipantMaintenancePage("crowd_fund_campaign_participants");
$pageObject->displayPage();
