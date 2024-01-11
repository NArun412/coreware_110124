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

$GLOBALS['gPageCode'] = "FACILITYDETAILS";
require_once "shared/startup.inc";

class FacilityDetailsPage extends Page {
	function setup() {
		if (empty($_GET['facility_id']) && !empty($_GET['id'])) {
			$_GET['facility_id'] = $_GET['id'];
		}
		$facilityId = getFieldFromId("facility_id", "facilities", "facility_id", $_GET['facility_id'],
			"client_id = " . $GLOBALS['gClientId'] . " and inactive = 0 and internal_use_only = 0");
	}

	function headerIncludes() {
		$facilityId = getFieldFromId("facility_id", "facilities", "facility_id", $_GET['facility_id'],
			"client_id = " . $GLOBALS['gClientId'] . " and inactive = 0 and internal_use_only = 0");
		if (!empty($facilityId)) {
			$facilityRow = getRowFromId("facilities", "facility_id", $facilityId);
			$urlAliasTypeCode = getReadFieldFromId("url_alias_type_code", "url_alias_types", "parameter_name", "facility_id",
				"table_id = (select table_id from tables where table_name = 'facilities')");
			$urlLink = "https://" . $_SERVER['HTTP_HOST'] . "/" .
				(empty($urlAliasTypeCode) || empty($facilityRow['link_name']) ? "facilitydetails.php?id=" . $facilityId : $urlAliasTypeCode . "/" . $facilityRow['link_name']);
			$imageUrl = (empty($facilityRow['image_id']) ? "" : "https://" . $_SERVER['HTTP_HOST'] . "/getimage.php?id=" . $facilityRow['image_id']);
			?>
            <meta property="og:title" content="<?= str_replace('"', "'", $facilityRow['description']) ?>"/>
            <meta property="og:type" content="website"/>
            <meta property="og:url" content="<?= $urlLink ?>"/>
            <meta property="og:image" content="<?= $imageUrl ?>"/>
            <meta property="og:description" content="<?= str_replace('"', "'", $facilityRow['description']) ?>"/>
			<?php
		}
	}

	function setPageTitle() {
		if (empty($_GET['facility_id']) && !empty($_GET['id'])) {
			$_GET['facility_id'] = $_GET['id'];
		}
		$description = getFieldFromId("description", "facilities", "facility_id", $_GET['facility_id'], "inactive = 0 and internal_use_only = 0");
		if (!empty($description)) {
			return $GLOBALS['gClientRow']['business_name'] . " | " . $description;
		}
	}

	function mainContent() {
		$facilityId = getFieldFromId("facility_id", "facilities", "facility_id", $_GET['facility_id'],
			"client_id = " . $GLOBALS['gClientId'] . " and inactive = 0 and internal_use_only = 0");
		if (empty($facilityId)) {
			$urlAliasTypeCode = getReadFieldFromId("url_alias_type_code", "url_alias_types", "parameter_name", "facility_id",
				"table_id = (select table_id from tables where table_name = 'facilities')");
			$resultSet = executeQuery("select * from facilities where inactive = 0 and internal_use_only = 0 and client_id = ?", $GLOBALS['gClientId']);
			if ($resultSet['row_count'] > 0) {
				?>
                <ul id='facilities_list'>
					<?php
					while ($facilityRow = getNextRow($resultSet)) {
						$urlLink = "/" . (empty($urlAliasTypeCode) || empty($facilityRow['link_name']) ? "facilitydetails.php?id=" . $facilityRow['facility_id'] : $urlAliasTypeCode . "/" . $facilityRow['link_name']);
						?>
                        <li><a href='<?= $urlLink ?>'><?= htmlText($facilityRow['description']) ?></a></li>
						<?php
					}
					?>
                </ul>
				<?php
			}
		} else {
			$facilityRow = getRowFromId("facilities", "facility_id", $facilityId);
			$facilityRow['facility_type'] = getFieldFromId("description", "facility_types", "facility_type_id", $facilityRow['facility_type_id']);
			$facilityRow['location'] = getFieldFromId("description", "location", "location_id", $facilityRow['location_id']);
			echo PlaceHolders::massageContent($this->iPageData['content'], $facilityRow);
			echo PlaceHolders::massageContent($this->iPageData['after_form_content'], $facilityRow);
		}
		return true;
	}
}

$pageObject = new FacilityDetailsPage();
$pageObject->displayPage();
