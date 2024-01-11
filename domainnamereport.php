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

$GLOBALS['gPageCode'] = "DOMAINNAMEREPORT";
require_once "shared/startup.inc";

class DomainNameReportPage extends Page {

	function mainContent() {
		echo $this->iPageData['content'];
		?>
        <table class="grid-table">
            <tr>
                <th><a href="<?= $GLOBALS['gLinkUrl'] ?>?sort=domain_name">Domain Name</a></th>
                <th><a href="<?= $GLOBALS['gLinkUrl'] ?>?sort=forward_domain_name">Forward to</a></th>
                <th><a href="<?= $GLOBALS['gLinkUrl'] ?>?sort=business_name">Client</a></th>
                <th><a href="<?= $GLOBALS['gLinkUrl'] ?>?sort=ip_address">IP Address</a></th>
                <th>Notes</th>
            </tr>
			<?php
			$domainNames = array();
			$resultSet = executeQuery("select * from domain_names" . ($GLOBALS['gUserRow']['superuser_flag'] ? "" : " where domain_client_id = " . $GLOBALS['gClientId']));
			while ($row = getNextRow($resultSet)) {
				$row['ip_address'] = gethostbyname($row['domain_name']);
				$row['business_name'] = getFieldFromId("business_name", "contacts", "contact_id", getFieldFromId("contact_id", "clients", "client_id", $row['domain_client_id']));
				$domainNames[] = $row;
				if (empty($_GET['sort']) || !array_key_exists($_GET['sort'], $row)) {
					$_GET['sort'] = "domain_name";
				}
			}
			if (!empty($domainNames)) {
				usort($domainNames, array($this, "listSort"));
			}
			foreach ($domainNames as $row) {
				?>
                <tr>
                    <td><?= htmlText($row['domain_name']) ?></td>
                    <td><?= htmlText($row['forward_domain_name']) ?></td>
                    <td><?= htmlText($row['business_name']) ?></td>
                    <td><?= $row['ip_address'] ?></td>
                    <td><?= htmlText($row['notes']) ?></td>
                </tr>
				<?php
			}
			?>
        </table>
		<?php
		return true;
	}

	function listSort($a, $b) {
		if ($a[$_GET['sort']] == $b[$_GET['sort']]) {
			return 0;
		}
		return ($a[$_GET['sort']] > $b[$_GET['sort']] ? 1 : -1);
	}

}

$pageObject = new DomainNameReportPage();
$pageObject->displayPage();
