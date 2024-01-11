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

$GLOBALS['gPageCode'] = "MAKECENTRALIZEDFFLS";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 3600000;

class MakeCentralizedFFLsPage extends Page {
	function setup() {
		if (!$GLOBALS['gUserRow']['superuser_flag']) {
			header("Location: /");
			exit;
		}
	}

	function mainContent() {
		?>
        <div id='intro_wrapper'>
			<?php
			if ($GLOBALS['gClientCount'] < 10) {
				?>
                <p>This server only has <?= $GLOBALS['gClientCount'] ?> client<?= $GLOBALS['gClientCount'] == 1 ? "" : "s" ?>. It doesn't make sense to switch it to centralized FFLs. Ten or more clients are required.</p>
				<?php
				return true;
			}
			$preferenceId = getFieldFromId("preference_id", "preferences", "preference_code", "CENTRALIZED_FFL_STORAGE");
			if (!empty($preferenceId) && getPreference("CENTRALIZED_FFL_STORAGE")) {
				$resultSet = executeQuery("select count(distinct client_id) from federal_firearms_licensees where client_id <> ?", $GLOBALS['gDefaultClientId']);
				$clientCount = 0;
				if ($row = getNextRow($resultSet)) {
					$clientCount = $row['count(distinct client_id)'];
				}
				?>
                <p>Conversion process started. <?= $clientCount ?> clients left to convert.</p>
				<?php
			}
			?>
            <p class='red-text'>This is a serious and irreversible operation. Don't do this without consulting one or more other people in the company.</p>
            <p>This operation will do the following:</p>
            <ul>
                <li>Create a system preference with code "CENTRALIZED_FFL_STORAGE" and set it. Unsetting this preference after this operation will, essentially, remove ALL FFLs from every client. For this reason, DO NOT unset this preference once this operation is done.</li>
                <li>Reconnect orders on all clients with FFLs on the primary client.</li>
                <li>Remove ALL FFL records from all clients except the primary client.</li>
            </ul>
            <p><input type='checkbox' value='1' id='confirm_switch'><label class='checkbox-label' for='confirm_switch'>I understand the risks and want to make the switch.</label></p>
            <p>
                <button id='switch_button'>Convert to Centralized FFLs</button>
            </p>
        </div>
        <div id='convert_wrapper'>
        </div>
		<?php
		return true;
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "convert_ffls":
				$returnArray['results'] = "";
				$preferenceId = getFieldFromId("preference_id", "preferences", "preference_code", "CENTRALIZED_FFL_STORAGE");
				if (empty($preferenceId) || empty(getPreference("CENTRALIZED_FFL_STORAGE"))) {
					$GLOBALS['gPrimaryDatabase']->startTransaction();
					if (empty($preferenceId)) {
						$resultSet = executeQuery("insert into preferences (preference_code,description,data_type,system_value) values " .
							"('CENTRALIZED_FFL_STORAGE','Centralized FFL Storage','tinyint','true')");
						if (!empty($resultSet['sql_error'])) {
							$returnArray['results'] .= $this->addResult("Error at line " . __LINE__ . ": " . $resultSet['sql_error']);
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}
					} else {
						executeQuery("update preferences set system_value = 'true' where preference_id = ?", $preferenceId);
					}

					# convert orders

					$resultSet = executeQuery("select federal_firearms_licensee_id,license_lookup from federal_firearms_licensees where client_id = ?", $GLOBALS['gDefaultClientId']);
					$primaryClientFFLs = array();
					while ($row = getNextRow($resultSet)) {
						$primaryClientFFLs[$row['license_lookup']] = $row['federal_firearms_licensee_id'];
					}
					$returnArray['results'] .= $this->addResult(count($primaryClientFFLs) . " FFLs found in the primary client.");
					$resultSet = executeQuery("select *,(select license_lookup from federal_firearms_licensees where " .
						"federal_firearms_licensee_id = orders.federal_firearms_licensee_id) license_lookup from orders where client_id <> ? and federal_firearms_licensee_id is not null", $GLOBALS['gDefaultClientId']);
					$returnArray['results'] .= $this->addResult($resultSet['row_count'] . " orders found to have their FFL converted.");
					while ($row = getNextRow($resultSet)) {
						$newFFLId = $primaryClientFFLs[$row['license_lookup']];
						if (empty($newFFLId)) {
							$subtableId = getFieldFromId("ffl_contact_id", "ffl_contacts", "federal_firearms_licensee_id", $row['federal_firearms_licensee_id']);
							if (empty($subtableId)) {
								$subtableId = getFieldFromId("ffl_location_id", "ffl_locations", "federal_firearms_licensee_id", $row['federal_firearms_licensee_id']);
							}
							if (empty($subtableId)) {
								$subtableId = getFieldFromId("ffl_product_manufacturer_id", "ffl_product_manufacturers", "federal_firearms_licensee_id", $row['federal_firearms_licensee_id']);
							}
							if (empty($subtableId)) {
								$subtableId = getFieldFromId("ffl_product_restriction_id", "ffl_product_restrictions", "federal_firearms_licensee_id", $row['federal_firearms_licensee_id']);
							}
							if (empty($subtableId)) {
								$subtableId = getFieldFromId("ffl_category_restriction_id", "ffl_category_restrictions", "federal_firearms_licensee_id", $row['federal_firearms_licensee_id']);
							}
							if (empty($subtableId)) {
								$subtableId = getFieldFromId("ffl_file_id", "ffl_files", "federal_firearms_licensee_id", $row['federal_firearms_licensee_id']);
							}
							if (empty($subtableId)) {
								$subtableId = getFieldFromId("ffl_image_id", "ffl_images", "federal_firearms_licensee_id", $row['federal_firearms_licensee_id']);
							}
							if (empty($subtableId)) {
								$subtableId = getFieldFromId("ffl_manufacturer_restriction_id", "ffl_manufacturer_restrictions", "federal_firearms_licensee_id", $row['federal_firearms_licensee_id']);
							}
							if (empty($subtableId)) {
								$subtableId = getFieldFromId("ffl_product_department_id", "ffl_product_departments", "federal_firearms_licensee_id", $row['federal_firearms_licensee_id']);
							}
							if (empty($subtableId)) {
								$fflContactId = getFieldFromId("contact_id", "federal_firearms_licensees", "federal_firearms_licensee_id", $row['federal_firearms_licensee_id'], "client_id is not null");
								if (!empty($fflContactId)) {
									executeQuery("update contacts set client_id = ? where contact_id = ?", $GLOBALS['gDefaultClientId'], $fflContactId);
									executeQuery("update federal_firearms_licensees set client_id = ? where federal_firearms_licensee_id = ?", $GLOBALS['gDefaultClientId'], $row['federal_firearms_licensee_id']);
									$primaryClientFFLs[$row['license_lookup']] = $row['federal_firearms_licensee_id'];
									$newFFLId = $row['federal_firearms_licensee_id'];
								}
							}
						}
						if (empty($newFFLId)) {
							$returnArray['results'] .= $this->addResult("Unable to find new FFL ID for Order ID " . $row['order_id'] . " in client '" . getFieldFromId("client_code", "clients", "client_id", $row['client_id']) . ".");
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}
						if ($newFFLId != $row['federal_firearms_licensee_id']) {
							$updateSet = executeQuery("update orders set federal_firearms_licensee_id = ? where order_id = ?", $newFFLId, $row['order_id']);
							if (!empty($updateSet['sql_error'])) {
								$returnArray['results'] .= $this->addResult("Error at line " . __LINE__ . ": " . $updateSet['sql_error']);
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								ajaxResponse($returnArray);
								break;
							}
						}
					}
					$returnArray['results'] .= $this->addResult("All orders successfully converted.");
					$GLOBALS['gPrimaryDatabase']->commitTransaction();
				}

				$clientSet = executeQuery("select * from clients where client_id <> ? and client_id in (select client_id from federal_firearms_licensees) order by client_id", $GLOBALS['gDefaultClientId']);
				$returnArray['results'] .= $this->addResult("Found " . $clientSet['row_count'] . " client" . ($clientSet['row_count'] == 1 ? "" : "s") . " to convert");
				if ($clientRow = getNextRow($clientSet)) {
					$returnArray['results'] .= $this->addResult("Converting " . $clientRow['client_code']);
					$GLOBALS['gPrimaryDatabase']->startTransaction();

					# Delete FFL records and contacts for all clients except primary

					$versionNumber = getRandomString(6, "0123456789");
					$contactIdentifier = "CENTRALIZED_FFL_" . $versionNumber;

					$imageIds = array();
					$resultSet = executeQuery("select distinct image_id from ffl_images where image_id is not null and federal_firearms_licensee_id in " .
						"(select federal_firearms_licensee_id from federal_firearms_licensees where client_id = ?)", $clientRow['client_id']);
					while ($row = getNextRow($resultSet)) {
						if (!array_key_exists($row['image_id'], $imageIds)) {
							$imageIds[] = $row['image_id'];
						}
					}
					$resultSet = executeQuery("select distinct image_id from contacts where image_id is not null and contact_id in (select contact_id from federal_firearms_licensees where client_id = ?)", $clientRow['client_id']);
					while ($row = getNextRow($resultSet)) {
						if (!array_key_exists($row['image_id'], $imageIds)) {
							$imageIds[] = $row['image_id'];
						}
					}
					$returnArray['results'] .= $this->addResult(count($imageIds) . " images collected");
					$fileIds = array();
					$resultSet = executeQuery("select file_id from ffl_files where file_id is not null and federal_firearms_licensee_id in (select federal_firearms_licensee_id from federal_firearms_licensees where client_id = ?)", $clientRow['client_id']);
					while ($row = getNextRow($resultSet)) {
						$fileIds[] = $row['file_id'];
					}
					$returnArray['results'] .= $this->addResult(count($fileIds) . " files collected");
					$updateSet = executeQuery("update contacts set hash_code = ? where client_id = ? and contact_id in (select contact_id from federal_firearms_licensees) and contact_id not in (select contact_id from orders)", $contactIdentifier, $clientRow['client_id']);
					if (!empty($updateSet['sql_error'])) {
						$returnArray['results'] .= $this->addResult("Error at line " . __LINE__ . ": " . $updateSet['sql_error']);
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
					$returnArray['results'] .= $this->addResult("Set hash code - " . $updateSet['affected_rows'] . " rows affected");

					$deleteTables = array();
					$deleteTables[] = array("table_name" => "ffl_availability", "where" => "federal_firearms_licensee_id in (select federal_firearms_licensee_id from federal_firearms_licensees where client_id = " . $clientRow['client_id'] . ")");
					$deleteTables[] = array("table_name" => "ffl_category_restrictions", "where" => "federal_firearms_licensee_id in (select federal_firearms_licensee_id from federal_firearms_licensees where client_id = " . $clientRow['client_id'] . ")");
					$deleteTables[] = array("table_name" => "ffl_contacts", "where" => "federal_firearms_licensee_id in (select federal_firearms_licensee_id from federal_firearms_licensees where client_id = " . $clientRow['client_id'] . ")");
					$deleteTables[] = array("table_name" => "ffl_files", "where" => "federal_firearms_licensee_id in (select federal_firearms_licensee_id from federal_firearms_licensees where client_id = " . $clientRow['client_id'] . ")");
					$deleteTables[] = array("table_name" => "ffl_images", "where" => "federal_firearms_licensee_id in (select federal_firearms_licensee_id from federal_firearms_licensees where client_id = " . $clientRow['client_id'] . ")");
					$deleteTables[] = array("table_name" => "ffl_manufacturer_restrictions", "where" => "federal_firearms_licensee_id in (select federal_firearms_licensee_id from federal_firearms_licensees where client_id = " . $clientRow['client_id'] . ")");
					$deleteTables[] = array("table_name" => "ffl_product_departments", "where" => "federal_firearms_licensee_id in (select federal_firearms_licensee_id from federal_firearms_licensees where client_id = " . $clientRow['client_id'] . ")");
					$deleteTables[] = array("table_name" => "ffl_locations", "where" => "federal_firearms_licensee_id in (select federal_firearms_licensee_id from federal_firearms_licensees where client_id = " . $clientRow['client_id'] . ")");
					$deleteTables[] = array("table_name" => "ffl_product_manufacturers", "where" => "federal_firearms_licensee_id in (select federal_firearms_licensee_id from federal_firearms_licensees where client_id = " . $clientRow['client_id'] . ")");
					$deleteTables[] = array("table_name" => "ffl_product_restrictions", "where" => "federal_firearms_licensee_id in (select federal_firearms_licensee_id from federal_firearms_licensees where client_id = " . $clientRow['client_id'] . ")");
					$deleteTables[] = array("table_name" => "ffl_videos", "where" => "federal_firearms_licensee_id in (select federal_firearms_licensee_id from federal_firearms_licensees where client_id = " . $clientRow['client_id'] . ")");
					$deleteTables[] = array("table_name" => "federal_firearms_licensees", "where" => "client_id = " . $clientRow['client_id']);
					$deleteTables[] = array("table_name" => "phone_numbers", "where" => "contact_id in (select contact_id from contacts where hash_code = '" . $contactIdentifier . "')");
					$deleteTables[] = array("table_name" => "addresses", "where" => "contact_id in (select contact_id from contacts where hash_code = '" . $contactIdentifier . "')");
					$deleteTables[] = array("table_name" => "contacts", "where" => "client_id = " . $clientRow['client_id'] . " and hash_code = '" . $contactIdentifier . "'");
					foreach ($deleteTables as $deleteInfo) {
						$deleteSet = executeQuery("delete from " . $deleteInfo['table_name'] . " where " . $deleteInfo['where']);
						if (!empty($deleteSet['sql_error'])) {
							$returnArray['results'] .= $this->addResult("Error at line " . __LINE__ . ": " . $deleteSet['sql_error']);
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}
						$returnArray['results'] .= $this->addResult($deleteSet['affected_rows'] . " rows deleted from " . $deleteInfo['table_name']);
					}

					foreach ($imageIds as $imageId) {
						executeQuery("delete from images where image_id = ?", $imageId);
					}
					foreach ($fileIds as $fileId) {
						executeQuery("delete from files where file_id = ?", $fileId);
					}
					$GLOBALS['gPrimaryDatabase']->commitTransaction();
					executeQuery("update contacts set hash_code = null where contact_id = (select contact_id from clients where client_id = ?)", $clientRow['client_id']);
					$returnArray['results'] .= $this->addResult("Conversion completed for " . $clientRow['client_code']);
					$GLOBALS['gEndTime'] = getMilliseconds();
					$returnArray['results'] .= $this->addResult("Make Centralized FFLs: " . round(($GLOBALS['gEndTime'] - $GLOBALS['gStartTime']) / 1000, 0));
					if ($clientSet['row_count'] > 1) {
						$returnArray['more'] = true;
					}
					$returnArray['results'] .= "<br>";
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function addResult($message) {
		addDebugLog($message);
		return $message . "<br>";
	}

	function internalCSS() {
		?>
        <style>
            #_main_content ul {
                list-style: disc;
                margin: 20px 0 40px 30px;
            }

            #_main_content ul li {
                margin: 5px;
            }
        </style>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", "#switch_button", function () {
                if ($("#confirm_switch").prop("checked")) {
                    $("#intro_wrapper").addClass("hidden");
                    $("body").addClass("no-waiting-for-ajax");
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=convert_ffls", function (returnArray) {
                        console.log(returnArray);
                        if ("results" in returnArray) {
                            $("#convert_wrapper").html(returnArray['results']);
                        }
                        if ("more" in returnArray) {
                            setTimeout(function () {
                                $("#switch_button").trigger("click");
                            }, 1000)
                        }
                    });
                }
                return false;
            });
        </script>
		<?php
	}
}

$pageObject = new MakeCentralizedFFLsPage();
$pageObject->displayPage();
