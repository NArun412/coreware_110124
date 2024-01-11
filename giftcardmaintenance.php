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

$GLOBALS['gPageCode'] = "GIFTCARDMAINT";
require_once "shared/startup.inc";

class ThisPage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addExcludeColumn(array("order_item_id"));
		}
	}

	function massageDataSource() {
		if (empty($_GET['ajax']) || $_GET['url_action'] != "save_changes") {
			$this->iDataSource->addColumnControl("gift_card_number", "readonly", true);
		}
		$this->iDataSource->addColumnControl("user_id", "data_type", "user_picker");
		$this->iDataSource->addColumnControl("gift_card_number", "css-width", "500px");
		$this->iDataSource->addColumnControl("contact_id", "data_type", "contact_picker");
		$this->iDataSource->addColumnControl("contact_id", "form_label", "Send email to contact");
		$this->iDataSource->addColumnControl("contact_id", "help_label", "an email will be sent to this contact if the subject and body has a value");
		$this->iDataSource->addColumnControl("subject", "data_type", "varchar");
		$this->iDataSource->addColumnControl("subject", "form_label", "Email Subject");
		$this->iDataSource->addColumnControl("body", "data_type", "text");
		$this->iDataSource->addColumnControl("body", "form_label", "Email Body");
	}

	function beforeSaveChanges(&$nameValues) {
		$this->iDataSource->addColumnControl("gift_card_number", "readonly", false);
		return true;
	}

	function afterGetRecord(&$returnArray) {
		$returnArray['contact_id'] = array("data_value" => "", "crc_value" => getCrcValue(""));
		$emailRow = getRowFromId("emails", "email_code", "contact_gift_card");
		$returnArray['subject'] = array("data_value" => $emailRow['subject'], "crc_value" => getCrcValue($emailRow['subject']));
		$returnArray['body'] = array("data_value" => $emailRow['content'], "crc_value" => getCrcValue($emailRow['content']));
		if (empty($returnArray['gift_card_number']['data_value'])) {
			$returnArray['gift_card_number'] = array("data_value" => strtoupper(getRandomString(25)));
			$returnArray['gift_card_number']['crc_value'] = getCrcValue($returnArray['gift_card_number']['data_value']);
		}
		ob_start();
		?>
        <table class='grid-table'>
            <tr>
                <th>Description</th>
                <th>Date & Time</th>
                <th>Order ID</th>
                <th>Amount</th>
            </tr>
			<?php
			$resultSet = executeQuery("select * from gift_card_log where gift_card_id = ?", $returnArray['primary_id']['data_value']);
			while ($row = getNextRow($resultSet)) {
				?>
                <tr>
                    <td><?= htmlText($row['description']) ?></td>
                    <td><?= date("m/d/Y g:ia", strtotime($row['log_time'])) ?></td>
					<?php if (canAccessPageCode("ORDERDASHBOARD")) { ?>
                        <td><a href='/orderdashboard.php?clear_filter=true&url_page=show&primary_id=<?= $row['order_id'] ?>' target='_blank'><?= $row['order_id'] ?></a></td>
					<?php } else { ?>
                        <td><?= $row['order_id'] ?></td>
					<?php } ?>
                    <td><?= number_format($row['amount'], 2, ".", ",") ?></td>
                </tr>
				<?php
			}
			?>
        </table>
		<?php
		$returnArray['gift_card_log']['data_value'] = ob_get_clean();
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
        $giftCard = new GiftCard($nameValues['primary_id']);
		coreSTORE::giftCardNotification($nameValues['gift_card_number'], "update", "Manual Update", $nameValues['balance']);
		executeQuery("insert into gift_card_log (gift_card_id,description,log_time,amount) values (?,?,current_time,?)",
			$nameValues['primary_id'], "Amount set by " . getUserDisplayName(), $nameValues['balance']);
		if (!empty($nameValues['contact_id']) && !empty($nameValues['subject']) && !empty($nameValues['body'])) {
			$substitutions = array_merge(Contact::getContact($nameValues['contact_id']), $nameValues);
			sendEmail(array("body" => $nameValues['body'], "subject" => $nameValues['subject'], "substitutions" => $substitutions, "email_address" => $substitutions['email_address']));
		}
		return true;
	}
}

$pageObject = new ThisPage("gift_cards");
$pageObject->displayPage();
