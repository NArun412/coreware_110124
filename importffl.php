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

$GLOBALS['gPageCode'] = "IMPORTFFL";
require_once "shared/startup.inc";
$GLOBALS['gDontReloadUsersContacts'] = true;

/*
 * Get the import file by going to https://www.atf.gov/firearms/listing-federal-firearms-licensees
 * the ATF is usually about 2 months behind. You have to just look at the most recent one available. Under "Download a complete list of FFLs", select the month/year and choose Apply
 * Select the month and download the .xlsx file. If .xlsx is not available for the month, go to the previous month. Open in Excel and Save As a CSV file
 * Go to https://shootingsports.coreware.com/importffl.php
 * Import this file
 */

class ThisPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "purge_dealers":
				$resultSet = executeQuery("select * from federal_firearms_licensees where (license_number like '_-__-___-03%' or license_number like '_-__-___-06%' or expiration_date < date_add(current_date,interval 45 day)) and " .
					"federal_firearms_licensee_id not in (select federal_firearms_licensee_id from orders where federal_firearms_licensee_id is not null) and client_id = ?", $GLOBALS['gClientId']);
				$federalFirearmsLicensees = array();
				while ($row = getNextRow($resultSet)) {
					$federalFirearmsLicensees[] = $row;
				}
				$returnArray['results'] = "<p>" . count($federalFirearmsLicensees) . " found to delete</p>";
				$count = 0;
				foreach ($federalFirearmsLicensees as $federalFirearmsLicenseeInfo) {
					$GLOBALS['gPrimaryDatabase']->startTransaction();
					$resultSet = executeQuery("delete from federal_firearms_licensees where federal_firearms_licensee_id = ?", $federalFirearmsLicenseeInfo['federal_firearms_licensee_id']);
					if (!empty($resultSet['sql_error'])) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						continue;
					}
					$resultSet = executeQuery("delete from phone_numbers where contact_id = ?", $federalFirearmsLicenseeInfo['contact_id']);
					if (!empty($resultSet['sql_error'])) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						continue;
					}
					$resultSet = executeQuery("delete from addresses where contact_id = ?", $federalFirearmsLicenseeInfo['contact_id']);
					if (!empty($resultSet['sql_error'])) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						continue;
					}
					$resultSet = executeQuery("delete from contacts where contact_id = ?", $federalFirearmsLicenseeInfo['contact_id']);
					if (!empty($resultSet['sql_error'])) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						continue;
					}
					if (!empty($federalFirearmsLicenseeInfo['file_id'])) {
						executeQuery("delete from download_log where file_id = ?", $federalFirearmsLicenseeInfo['file_id']);
						executeQuery("delete ignore from files where file_id = ?", $federalFirearmsLicenseeInfo['file_id']);
					}
					if (!empty($federalFirearmsLicenseeInfo['sot_file_id'])) {
						executeQuery("delete from download_log where file_id = ?", $federalFirearmsLicenseeInfo['sot_file_id']);
						executeQuery("delete ignore from files where file_id = ?", $federalFirearmsLicenseeInfo['sot_file_id']);
					}
					$GLOBALS['gPrimaryDatabase']->commitTransaction();
					$count++;
				}
				$returnArray['results'] .= "<p>" . $count . " dealers deleted</p>";
				ajaxResponse($returnArray);
				break;
			case "import_images":
//			    $returnArray['error_message'] = "Unable to import images";
//			    ajaxResponse($returnArray);
				$results = "";
				$maxDBSize = getPreference("EXTERNAL_FILE_SIZE");
				if (empty($maxDBSize) || !is_numeric($maxDBSize)) {
					$maxDBSize = 1000000;
				}
				$fileDirectory = getPreference("EXTERNAL_FILE_DIRECTORY");
				if (empty($fileDirectory)) {
					$fileDirectory = "/documents/";
				}
				if (substr($fileDirectory, -1) != "/" && substr($fileDirectory, -1) != "\\") {
					$fileDirectory .= "/";
				}
				if (empty($_GET['continue_import'])) {
					$fileList = scandir($GLOBALS['gDocumentRoot'] . "/cache/ffl_images");
					sort($fileList);
					$_SESSION['ffl_image_list'] = $fileList;
					saveSessionData();
				} else {
					$fileList = $_SESSION['ffl_image_list'];
					if (!is_array($fileList)) {
						$fileList = array();
					}
				}
				if (empty($fileList)) {
					if (empty($_GET['continue_import'])) {
						$returnArray['error_message'] = "No Files Found";
					} else {
						$returnArray['info_message'] = "Import complete";
					}
					ajaxResponse($returnArray);
					break;
				}
				$returnArray['continue_import'] = "YES";

				$returnArray['file_count'] = count($fileList);
				$count = 0;
				$insertCount = 0;
				while (true) {
					if (empty($fileList)) {
						break;
					}
					$fflFilename = array_shift($fileList);
					if ($fflFilename == "." || $fflFilename == "..") {
						continue;
					}
					$count++;
					if (strlen($fflFilename) < 5) {
						continue;
					}
					$_SESSION['ffl_image_list'] = $fileList;
					saveSessionData();
					$parts = explode(".", strtolower($fflFilename));
					$filename = $parts[0];
					$extension = $parts[1];
					if (!in_array($extension, array("png", "jpg", "pdf"))) {
						continue;
					}
					$sotLicense = false;
					if (substr($filename, -4) == "-sot") {
						$licenseNumber = substr($filename, 0, -4);
						$sotLicense = true;
					} else {
						$licenseNumber = $filename;
					}
					$fflRow = getRowFromId("federal_firearms_licensees", "license_number", strtoupper($licenseNumber));
					if (empty($fflRow)) {
						continue;
					}
					if ($sotLicense && !empty($fflRow['sot_file_id'])) {
						continue;
					}
					if (!$sotLicense && !empty($fflRow['file_id'])) {
						continue;
					}

					$originalFilename = $fflFilename;
					$originalFileContent = file_get_contents($GLOBALS['gDocumentRoot'] . "/cache/ffl_images/" . $fflFilename);
					if (strlen($originalFileContent) < $maxDBSize) {
						$fileContent = $originalFileContent;
						$osFilename = "";
					} else {
						$fileContent = "";
						$osFilename = "/documents/tmp." . $extension;
					}
					$insertSet = executeQuery("insert into files (client_id,description,date_uploaded," .
						"filename,extension,file_content,os_filename,public_access,all_user_access,administrator_access," .
						"sort_order) values (?,?,now(),?,?,?,?,0,0,1,0)", $GLOBALS['gClientId'], ($sotLicense ? "SOT License Image" : "FFL License Image"),
						$originalFilename, $extension, $fileContent, $osFilename);
					if (!empty($insertSet['sql_error'])) {
						$results .= "<p>" . getSystemMessage("basic", $insertSet['sql_error']) . "</p>";
						continue;
					}
					$fileId = $insertSet['insert_id'];
					if (!empty($osFilename)) {
						$osFilename = $fileDirectory . "file" . $fileId . "." . $extension;
						if (file_put_contents($osFilename, $originalFileContent)) {
							executeQuery("update files set os_filename = ? where file_id = ?", $osFilename, $fileId);
							executeQuery("update federal_firearms_licensees set " . ($sotLicense ? "sot_" : "") . "file_id = ? where federal_firearms_licensee_id = ?", $fileId, $fflRow['federal_firearms_licensee_id']);
							$insertCount++;
						} else {
							$results .= "<p>Unable to write file</p>";
							executeQuery("delete ignore from files where file_id = ?", $fileId);
							continue;
						}
					} else {
						executeQuery("update federal_firearms_licensees set " . ($sotLicense ? "sot_" : "") . "file_id = ? where federal_firearms_licensee_id = ?", $fileId, $fflRow['federal_firearms_licensee_id']);
						$insertCount++;
					}
					if ($count % 100 == 0) {
						$_SESSION[$GLOBALS['gSystemName']]['last_hit'] = time();
						saveSessionData();
						break;
					}
				}
				$results .= "<p>" . $count . " files found, " . $insertCount . " files created</p>";
				$returnArray['results'] = $results;
				ajaxResponse($returnArray);
				break;
			case "import_ffl":
				$atfSourceId = getFieldFromId("source_id", "sources", "source_code", "ATF");
				if (empty($atfSourceId)) {
					$insertSet = executeQuery("insert into sources (client_id,source_code,description,internal_use_only) values (?,?,?,1)", $GLOBALS['gClientId'], "ATF", "ATF");
					$atfSourceId = $insertSet['insert_id'];
				}
				$skipCount = 0;
				$insertCount = 0;
				$updateCount = 0;
				$count = 0;
				if (array_key_exists("ffl_import", $_FILES) && !empty($_FILES['ffl_import']['name'])) {
					$importRecords = array();

					$openFile = fopen($_FILES['ffl_import']['tmp_name'], "r");
					$fieldNames = array();
					while ($csvData = fgetcsv($openFile)) {
						$count++;
						if ($count == 1) {
							foreach ($csvData as $fieldName) {
								$fieldName = makeCode($fieldName, array("lowercase" => true));
								$fieldNames[] = $fieldName;
							}
							continue;
						}
						$thisRecord = array();
						foreach ($fieldNames as $index => $fieldName) {
							$thisRecord[$fieldName] = (strtolower($csvData[$index]) == "null" ? "" : trim($csvData[$index]));
						}
						$importRecords[] = $thisRecord;
					}

					if (!in_array("lic_type", $fieldNames) || !in_array("lic_regn", $fieldNames) || !in_array("lic_dist", $fieldNames)) {
						$returnArray['error_message'] = "Invalid file format";
						ajaxResponse($returnArray);
						break;
					}

					foreach ($importRecords as $thisRecord) {
						if ($thisRecord['lic_type'] == "03" || $thisRecord['lic_type'] == "06" || strlen($thisRecord['lic_regn']) != 1) {
							$skipCount++;
							continue;
						}
						$licenseNumber = $thisRecord['lic_regn'] . "-" . $thisRecord['lic_dist'] . "-" . $thisRecord['lic_cnty'] . "-" . $thisRecord['lic_type'] . "-" . $thisRecord['lic_xprdte'] . "-" . $thisRecord['lic_seqn'];
						$licenseLookup = $thisRecord['lic_regn'] . "-" . $thisRecord['lic_dist'] . "-" . $thisRecord['lic_seqn'];
						$expirationDate = getFflExpirationDate($licenseNumber);
						if (strlen($thisRecord['premise_zip_code']) == 9) {
							$plus4 = substr($thisRecord['premise_zip_code'], 5, 4);
							$thisRecord['premise_zip_code'] = substr($thisRecord['premise_zip_code'], 0, 5) . (empty($plus4) || $plus4 == "0000" ? "" : "-" . $plus4);
						} else if (strlen($thisRecord['premise_zip_code']) < 9) {
							$thisRecord['premise_zip_code'] = substr($thisRecord['premise_zip_code'], 0, 5);
						}
						if (strlen($thisRecord['mail_zip_code']) == 9) {
							$plus4 = substr($thisRecord['mail_zip_code'], 5, 4);
							$thisRecord['mail_zip_code'] = substr($thisRecord['mail_zip_code'], 0, 5) . (empty($plus4) || $plus4 == "0000" ? "" : "-" . $plus4);
						} else if (strlen($thisRecord['mail_zip_code']) < 9) {
							$thisRecord['mail_zip_code'] = substr($thisRecord['mail_zip_code'], 0, 5);
						}
						if (empty($thisRecord['business_name'])) {
							$thisRecord['business_name'] = $thisRecord['license_name'];
						}

						$resultSet = executeQuery("select * from federal_firearms_licensees where license_lookup = ? and client_id = ?", $licenseLookup, $GLOBALS['gClientId']);
						if ($row = getNextRow($resultSet)) {
							$updated = 0;
							$contactRow = Contact::getContact($row['contact_id']);
							$contactId = $contactRow['contact_id'];
							$updateSet = executeQuery("update federal_firearms_licensees set license_number = ?, licensee_name = ?, expiration_date = ? where federal_firearms_licensee_id = ?",
								$licenseNumber, $thisRecord['license_name'], $expirationDate, $row['federal_firearms_licensee_id']);
							if (!empty($updateSet['sql_error'])) {
								$returnArray['error_message'] = getSystemMessage("basic", $updateSet['sql_error']);
								ajaxResponse($returnArray);
								break;
							}
							$updated += $updateSet['affected_rows'];
							$updateSet = executeQuery("update contacts set business_name = ?, address_1 = ?, city = ?, " .
								"state = ?, postal_code = ?,source_id = ? where contact_id = ?", $thisRecord['business_name'],
								$thisRecord['premise_street'], $thisRecord['premise_city'], $thisRecord['premise_state'], $thisRecord['premise_zip_code'], $row['contact_id'], $atfSourceId);
							if (!empty($updateSet['sql_error'])) {
								$returnArray['error_message'] = getSystemMessage("basic", $updateSet['sql_error']);
								ajaxResponse($returnArray);
								break;
							}
							$updated += $updateSet['affected_rows'];
							if ($updated) {
								$updateCount++;
							}

							if (!empty($thisRecord['voice_phone'])) {
								$phoneNumberId = getFieldFromId("phone_number_id", "phone_numbers", "contact_id", $row['contact_id'], "description = 'Store'");
								if (empty($phoneNumberId)) {
									executeQuery("insert into phone_numbers (contact_id,phone_number,description) values (?,?,'Store')", $row['contact_id'], formatPhoneNumber($thisRecord['voice_phone']));
								} else {
									executeQuery("update phone_numbers set phone_number = ? where phone_number_id = ?", formatPhoneNumber($thisRecord['voice_phone']), $phoneNumberId);
								}
							}

							if (!empty($thisRecord['mail_street']) || !empty($thisRecord['mail_city'])) {
								$dataTable = new DataTable("addresses");
								$addressId = getFieldFromId("address_id", "addresses", "address_label", "Mailing", "contact_id = ?", $row['contact_id']);
								$dataTable->setPrimaryId($addressId);
								$dataTable->saveRecord(array("name_values" => array("contact_id" => $row['contact_id'], "address_label" => "Mailing",
									"address_1" => $thisRecord['mail_street'], "city" => $thisRecord['mail_city'], "state" => $thisRecord['mail_state'], "postal_code" => $thisRecord['mail_zip_code'], "country_id" => "1000")));
							}
						} else {
							if ($expirationDate < date("Y-m-d")) {
								$contactId = "";
								$skipCount++;
							} else {
								$nameValues = array();
								$nameValues['date_created'] = date("m/d/Y");
								$nameValues['country_id'] = "1000";
								$nameValues['business_name'] = $thisRecord['business_name'];
								$nameValues['address_1'] = $thisRecord['premise_street'];
								$nameValues['city'] = $thisRecord['premise_city'];
								$nameValues['state'] = $thisRecord['premise_state'];
								$nameValues['postal_code'] = $thisRecord['premise_zip_code'];
								$nameValues['source_id'] = $atfSourceId;
								$dataTable = new DataTable("contacts");
								$contactId = $dataTable->saveRecord(array("name_values" => $nameValues));

								if (!empty($thisRecord['mail_street']) || !empty($thisRecord['mail_city'])) {
									$dataTable = new DataTable("addresses");
									$addressId = $dataTable->saveRecord(array("name_values" => array("contact_id" => $contactId, "address_label" => "Mailing", "address_1" => $thisRecord['mail_street'],
										"city" => $thisRecord['mail_city'], "state" => $thisRecord['mail_state'], "postal_code" => $thisRecord['mail_zip_code'], "country_id" => "1000")));
								}

								if (!empty($thisRecord['voice_phone'])) {
									$dataTable = new DataTable("phone_numbers");
									$phoneNumberId = $dataTable->saveRecord(array("name_values" => array("contact_id" => $contactId, "phone_number" => formatPhoneNumber($thisRecord['voice_phone']), "description" => "Store")));
								}

								$dataTable = new DataTable("federal_firearms_licensees");
								$nameValues = array();
								$nameValues['license_number'] = $licenseNumber;
								$nameValues['license_lookup'] = $licenseLookup;
								$nameValues['licensee_name'] = $thisRecord['license_name'];
								$nameValues['expiration_date'] = $expirationDate;
								$nameValues['contact_id'] = $contactId;
								$fflId = $dataTable->saveRecord(array("name_values" => $nameValues));
								$insertCount++;
							}
							$contactRow = Contact::getContact($contactId);
						}
						if (!$GLOBALS['gDevelopmentServer'] && !empty($contactId) && $expirationDate > date("Y-m-d") && (empty($contactRow['latitude']) || empty($contactRow['longitude']) || strtolower($contactRow['address_1']) != strtolower($thisRecord['premise_street']))) {
							$geoCode = getAddressGeocode($contactRow);
							if (!empty($geoCode) && !empty($geoCode['validation_status']) && !empty($geoCode['latitude']) && !empty($geoCode['longitude'])) {
								executeQuery("update contacts set latitude = ?,longitude = ? where contact_id = ?", $geoCode['latitude'], $geoCode['longitude'], $contactId);
							}
						}
						if ($insertCount % 100 == 0 || $updateCount % 100 == 0) {
							$_SESSION[$GLOBALS['gSystemName']]['last_hit'] = time();
							saveSessionData();
						}
					}
				}
				$returnArray['results'] = "<p>" . $count . " FFLs processed, " . $skipCount . " FFLs skipped, " . $insertCount . " FFLs created, " . $updateCount . " FFLs updated</p>";
				ajaxResponse($returnArray);
				break;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("tap click", "#purge_dealers", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=purge_dealers", function(returnArray) {
                    if ("results" in returnArray) {
                        $("#results").append(returnArray['results']);
                    }
                });
                return false;
            });
            $(document).on("tap click", "#import_images", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=import_images&continue_import=" + $("#continue_import").val(), function(returnArray) {
                    if ("results" in returnArray) {
                        $("#results").append(returnArray['results']);
                    }
                    if ("continue_import" in returnArray) {
                        $("#continue_import").val("1");
                        setTimeout(function () {
                            $("#import_images").trigger("click");
                        }, 200);
                    }
                });
                return false;
            });
            $(document).on("tap click", "#_submit_form", function () {
                if ($("#_submit_form").data("disabled") == "true") {
                    return false;
                }
                if ($("#_edit_form").validationEngine("validate")) {
                    disableButtons($("#_submit_form"));
                    $("#loading_message").html("File upload in process");
                    $("body").addClass("waiting-for-ajax");
                    $("#_edit_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=import_ffl").attr("method", "POST").attr("target", "post_iframe").submit();
                    $("#_post_iframe").off("load");
                    $("#_post_iframe").on("load", function () {
                        $("body").removeClass("no-waiting-for-ajax").removeClass("waiting-for-ajax");
                        var returnText = $(this).contents().find("body").html();
                        const returnArray = processReturn(returnText);
                        $("#loading_message").html("");
                        if (returnArray === false) {
                            enableButtons($("#_submit_form"));
                            return;
                        }
                        if (!("error_message" in returnArray)) {
                            if ("results" in returnArray) {
                                $("#results").html(returnArray['results']);
                            }
                        } else {
                            enableButtons($("#_submit_form"));
                        }
                    });
                } else {
                    displayErrorMessage("Select a file");
                }
                return false;
            });
        </script>
		<?php
	}

	function mainContent() {
		?>
        <form id="_edit_form" enctype="multipart/form-data">
            <input type="hidden" id="add_hash" name="add_hash" value="LSKDJFLSJDFLJWEIHFIW">
            <div class="basic-form-line">
                <label>FFL File</label>
                <input type="file" id="ffl_import" name="ffl_import" class="validate[required]">
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>

            <p id="loading_message"></p>
            <p>
                <button id="_submit_form">Submit</button>
            </p>

			<?php if ($GLOBALS['gUserRow']['superuser_flag']) { ?>
                <p>Images should be in a folder named "ffl_images" in the cache folder. Files should have name set to the FFL license number with an optional "-SOT" at the end.</p>
                <p>
                    <button id="import_images">Import Images</button>
                </p>
                <input type="hidden" id="continue_import" value="">

                <p>FFL Dealers that are expired and not used in orders will be deleted</p>
                <p>
                    <button id="purge_dealers">Delete Expired Dealers</button>
                </p>

			<?php } ?>
            <div id="results">
            </div>
        </form>
		<?php
		return true;
	}

	function internalCSS() {
		?>
        #loading_message {
        color: rgb(15,180,50);
        }
		<?php
	}

	function hiddenElements() {
		?>
        <iframe id="_post_iframe" name="post_iframe"></iframe>
		<?php
	}
}

$pageObject = new ThisPage();
$pageObject->displayPage();
