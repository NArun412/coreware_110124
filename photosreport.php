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

$GLOBALS['gPageCode'] = "PHOTOSREPORT";
require_once "shared/startup.inc";

class PhotosReportPage extends Page implements BackgroundReport {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "create_report":
				$returnArray = self::getReportContent();
				if (array_key_exists("report_export", $returnArray)) {
					if (is_array($returnArray['export_headers'])) {
						foreach ($returnArray['export_headers'] as $thisHeader) {
							header($thisHeader);
						}
					}
					echo $returnArray['report_export'];
				} else {
					echo jsonEncode($returnArray);
				}
				exit;
		}
	}

	public static function getReportContent() {
		$returnArray = array();
		saveStoredReport(static::class);

		$fullName = getUserDisplayName($GLOBALS['gUserId']);
		$totalCount = 0;
		$totalFees = 0;
		$totalDonations = 0;

		$whereStatement = "client_id = ?";
		$parameters = array($GLOBALS['gClientId']);
		$displayCriteria = "";

		if (!empty($_POST['selected_contacts'])) {
			$whereStatement .= ($whereStatement ? " and " : "") . "contact_id in (select primary_identifier from selected_rows where page_id = ? and user_id = ?)";
			$parameters[] = $GLOBALS['gAllPageCodes']["CONTACTMAINT"];
			$parameters[] = $GLOBALS['gUserId'];
			$displayCriteria .= (empty($displayCriteria) ? "" : " and ") . "Contact is selected";
		}

		if (!empty($_POST['only_photos'])) {
			$whereStatement .= ($whereStatement ? " and " : "") . "image_id is not null";
			$displayCriteria .= (empty($displayCriteria) ? "" : " and ") . "Contact has photo";
		}

		if (!empty($_POST['category_ids'])) {
			$categoryIdArray = array_filter(explode(",", $_POST['category_ids']));
		} else {
			$categoryIdArray = array();
		}

		if (!empty($categoryIdArray)) {
			$whereStatement .= ($whereStatement ? " and " : "") . "contact_id in (select contact_id from contact_categories where category_id in (" . implode(",", array_fill(0, count($categoryIdArray), "?")) . "))";
			$parameters = array_merge($parameters, $categoryIdArray);
			$displayCriteria .= (empty($displayCriteria) ? "" : " and ") . "Categories selected";
		}

		if (!empty($_POST['custom_fields'])) {
			$customFieldArray = explode(",", $_POST['custom_fields']);
		} else {
			$customFieldArray = array();
		}

		$sortOrder = ($_POST['sort_order'] == "city" ? "country_id,state,city" : "last_name,first_name");

		ob_start();
		?>
        <div id="contact_wrapper">
			<?php
			$resultSet = executeReadQuery("select * from contacts where " . $whereStatement . " order by " . $sortOrder, $parameters);
			while ($row = getNextRow($resultSet)) {
				?>
                <div class="contact-photo">
                    <div class="photo-block align-center"<?= (!empty($row['image_id']) ? " style=\"background-image: url('" . getImageFilename($row['image_id'], array("use_cdn" => true)) . "');\"" : "") ?>><?php if (empty($row['image_id'])) { ?><span class="fa fa-user-circle"></span><?php } ?></div>
                    <p class="align-center highlighted-text"><?= htmlText(getDisplayName($row['contact_id'], array("include_company" => false))) ?></p>
					<?php if ($_POST['include_address']) { ?>
						<?php if (!empty($row['address_1'])) { ?>
                            <p class="align-center"><?= htmlText($row['address_1']) ?></p>
						<?php } ?>
						<?php if (!empty($row['city'])) { ?>
                            <p class="align-center"><?= htmlText($row['city'] . ", " . $row['state'] . " " . $row['postal_code']) ?></p>
						<?php } ?>
					<?php } ?>
					<?php
					foreach ($customFieldArray as $customFieldId) {
						$customField = CustomField::getCustomField($customFieldId);
						$customField->setPrimaryIdentifier($row['contact_id']);
						?>
                        <p class="align-center"><?= htmlText($customField->getFormLabel()) ?>: <?= htmlText($customField->getDisplayData()) ?></p>
						<?php
					}
					?>
                </div>
				<?php
			}
			?>
            <div class='clear-div'></div>
        </div>
		<?php
		$reportContent = ob_get_clean();
		$returnArray['report_content'] = $reportContent;
		$returnArray['report_title'] = "Photos Report";
		return $returnArray;
	}

	function mainContent() {
		?>
        <div id="report_parameters">
            <form id="_report_form" name="_report_form">

                <h2>Selection Criteria</h2>

				<?php getStoredReports() ?>

                <div class="basic-form-line" id="_sort_order_row">
                    <label for="sort_order">Sort Order</label>
                    <select tabindex="10" id="sort_order" name="sort_order">
                        <option value="">Name</option>
                        <option value="city">City</option>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_selected_contacts_row">
                    <input tabindex="10" type="checkbox" id="selected_contacts" name="selected_contacts"><label class="checkbox-label" for="selected_contacts">Include Selected Contacts</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_only_photos_row">
                    <input tabindex="10" type="checkbox" id="only_photos" name="only_photos"><label class="checkbox-label" for="only_photos">Only Contacts with Photos</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

				<?php
				$categoryControl = new DataColumn("category_ids");
				$categoryControl->setControlValue("data_type", "custom");
				$categoryControl->setControlValue("control_class", "MultiSelect");
				$categoryControl->setControlValue("control_table", "categories");
				$categoryControl->setControlValue("links_table", "contact_categories");
				$categoryControl->setControlValue("primary_table", "contacts");
				$categoryMultipleSelect = new MultipleSelect($categoryControl, $this);
				?>
                <div class="basic-form-line custom-control-form-line custom-control-no-help" id="_category_ids_row">
                    <label for="category_ids">Categories</label>
					<?= $categoryMultipleSelect->getControl() ?>
                </div>

                <h2>Print Information</h2>

                <div class="basic-form-line" id="_include_address_row">
                    <input tabindex="10" type="checkbox" id="include_address" name="include_address"><label class="checkbox-label" for="include_address">Include Address</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

				<?php
				$customFieldControl = new DataColumn("custom_fields");
				$customFieldControl->setControlValue("data_type", "custom");
				$customFieldControl->setControlValue("control_class", "MultiSelect");
				$customFieldControl->setControlValue("control_table", "custom_fields");
				$customFieldControl->setControlValue("links_table", "custom_fields");
				$customFieldControl->setControlValue("primary_table", "contacts");
				$customFieldControl->setControlValue("choice_where", "custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = 'CONTACTS')");
				$customControl = new MultipleSelect($customFieldControl, $this);
				?>
                <div class="basic-form-line custom-control-no-help custom-control-form-line" id="_custom_fields_row">
                    <label for="custom_fields">Custom Fields</label>
					<?= $customControl->getControl() ?>
                </div>

                <div class="basic-form-line" id="_orientation_row">
                    <label for="orientation">Orientation</label>
                    <select tabindex="10" id="orientation" name="orientation">
                        <option value="portrait">Portrait</option>
                        <option value="landscape">Landscape</option>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

				<?php storedReportDescription() ?>

                <div class="basic-form-line">
                    <button tabindex="10" id="create_report">Create Report</button>
                </div>

            </form>
        </div>
        <div id="_button_row">
            <button id="refresh_button">Refresh</button>
            <button id="new_parameters_button">Search Again</button>
            <button id="printable_button">Printable Report</button>
            <button id="pdf_button">Download PDF</button>
        </div>
        <h1 id="_report_title"></h1>
        <div id="_report_content">
        </div>
        <div id="_pdf_data" class="hidden">
            <form id="_pdf_form">
            </form>
        </div>
		<?php
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("tap click", "#printable_button", function () {
                $("#_report_content").removeClass("landscape").removeClass("portrait").addClass($("#orientation").val());
                window.open("/printable.html");
                return false;
            });
            $(document).on("tap click", "#pdf_button", function () {
                $("#_report_content").removeClass("landscape").removeClass("portrait").addClass($("#orientation").val());
                $("#_pdf_form").html("");
                let input = $("<input>").attr("type", "hidden").attr("name", "report_title").val($("#_report_title").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "report_content").val($("#_report_content").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "printable_style").val($("#_printable_style").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "filename").val("photoreport.pdf");
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "orientation").val($("#orientation").val());
                $('#_pdf_form').append($(input));
                $("#_pdf_form").attr("action", "/reportpdf.php").attr("method", "POST").submit();
                return false;
            });
            $(document).on("tap click", "#create_report,#refresh_button", function () {
                $("#_report_content").removeClass("landscape").removeClass("portrait").addClass($("#orientation").val());
                if ($("#_report_form").validationEngine("validate")) {
                    var reportType = $("#report_type").val();
                    if (reportType == "export" || reportType == "summaryexport") {
                        $("#_report_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?url_action=create_report").attr("method", "POST").submit();
                    } else {
                        loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_report", $("#_report_form").serialize(), function(returnArray) {
                            if ("report_content" in returnArray) {
                                $("#report_parameters").hide();
                                $("#_report_title").html(returnArray['report_title']).show();
                                $("#_report_content").html(returnArray['report_content']).show();
                                $("#_button_row").show();
                                $("html, body").animate({ scrollTop: 0 }, "slow");
                            }
                        });
                    }
                }
                return false;
            });
            $(document).on("tap click", "#new_parameters_button", function () {
                $("#report_parameters").show();
                $("#_report_title").hide();
                $("#_report_content").hide();
                $("#_button_row").hide();
                return false;
            });
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #report_parameters {
                width: 100%;
                margin-left: auto;
                margin-right: auto;
            }

            #_report_content {
                display: none;
            }

            #_button_row {
                display: none;
                margin-bottom: 20px;
            }
        </style>
        <style id="_printable_style">
            #contact_wrapper div.photo-block {
                width: 100%;
                position: relative;
                margin-bottom: 20px;
                padding-bottom: 100%;
                background-repeat: no-repeat;
                background-size: cover;
                background-position: center center;
                page-break-inside: avoid;
            }

            #contact_wrapper .fa-user-circle {
                font-size: 100px;
                color: rgb(180, 180, 180);
            }

            .contact-photo {
                width: 205px;
                float: left;
                margin-bottom: 20px;
                margin-right: 20px;
            }

            #_report_content.portrait .contact-photo:nth-child(3n+1) {
                clear: both;
            }

            #_report_content.landscape .contact-photo:nth-child(4n+1) {
                clear: both;
            }
        </style>
		<?php
	}
}

$pageObject = new PhotosReportPage();
$pageObject->displayPage();
