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

$GLOBALS['gPageCode'] = "SHIPMENTMAINT";
require_once "shared/startup.inc";
require_once "classes/easypost/lib/easypost.php";

class ShipmentMaintenancePage extends Page {

	var $iEasyPostActive = false;

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
            case "load_contact_information":
                $contactRow = Contact::getContact($_GET['contact_id']);
                $returnArray['full_name'] = getDisplayName($contactRow['contact_id']);
                $fieldArray = array("address_1","address_2","city","state","postal_code","country_id");
                foreach ($fieldArray as $fieldName) {
                    $returnArray[$fieldName] = $contactRow[$fieldName];
                }
                ajaxResponse($returnArray);
                break;
			case "get_easy_post_label_rates":
				$pagePreferences = Page::getPagePreferences();
				$pagePreferences['weight_unit'] = $_POST['weight_unit'];
				Page::setPagePreferences($pagePreferences);
				EasyPostIntegration::setRecentlyUsedDimensions($_POST['height'], $_POST['width'], $_POST['length']);
				$returnArray = EasyPostIntegration::getLabelRates($this->iEasyPostActive, $_POST);

				ajaxResponse($returnArray);

				break;

			case "create_easy_post_label":
				$shipmentRow = getRowFromId("shipments", "shipment_id", $_GET['shipment_id']);
				if (empty($shipmentRow)) {
					$returnArray['error_message'] = "Invalid Shipment";
					ajaxResponse($returnArray);
					break;
				}
				$returnArray = EasyPostIntegration::createLabel($this->iEasyPostActive, $_POST);
				if (!empty($returnArray['error_message'])) {
					ajaxResponse($returnArray);
					break;
				}

				executeQuery("update shipments set date_shipped = current_date,shipping_charge = ?,tracking_identifier = ?,label_url = ?,shipping_carrier_id = ?,carrier_description = ? where shipment_id = ?",
					$returnArray['shipping_charge'], $returnArray['tracking_identifier'], $returnArray['label_url'], $returnArray['shipping_carrier_id'], $returnArray['carrier_description'], $shipmentRow['shipment_id']);

				ajaxResponse($returnArray);

				break;
		}
	}

	function sortRates($a, $b) {
		if ($a['rate'] == $b['rate']) {
			return 0;
		}
		return ($a['rate'] > $b['rate']) ? 1 : -1;
	}

	function setup() {
		$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("duplicate" => array("icon" => "fad fa-copy", "label" => getLanguageText("Duplicate"),
			"disabled" => false)));
		$this->iEasyPostActive = getPreference($GLOBALS['gDevelopmentServer'] ? "EASY_POST_TEST_API_KEY" : "EASY_POST_API_KEY");
		if ($this->iEasyPostActive) {
			$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("create_label" => array("label" => getLanguageText("Create Label"), "disabled" => false),
				"send_email" => array("label" => getLanguageText("Send Tracking Email"), "disabled" => false)));
		}
	}

	function massageDataSource() {
		$this->iDataSource->getPrimaryTable()->setSubtables(array("shipment_line_items"));
		$this->iDataSource->addColumnControl("shipment_line_items", "data_type", "custom");
		$this->iDataSource->addColumnControl("shipment_line_items", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("shipment_line_items", "list_table", "shipment_line_items");
		$this->iDataSource->addColumnControl("shipment_line_items", "form_label", "Line Items");

		$this->iDataSource->addColumnControl("contact_id", "data_type", "contact_picker");
		$this->iDataSource->addColumnControl("contact_id", "form_label", "Choose Contact");
		$this->iDataSource->addColumnControl("contact_id", "help_label", "Prefill information with this contact");

		$this->iDataSource->addColumnControl("city", "size", "30");
		$this->iDataSource->addColumnControl("city_select", "data_type", "select");
		$this->iDataSource->addColumnControl("city_select", "form_label", "City");
		$this->iDataSource->addColumnControl("postal_code", "data-city_hide", "city");
		$this->iDataSource->addColumnControl("postal_code", "data-city_select_hide", "city_select");
		$this->iDataSource->addColumnControl("state", "size", "10");
		$this->iDataSource->addColumnControl("country_id", "default_value", "1000");
		if ($_GET['url_page'] == "show" && $_GET['subaction'] == "duplicate" && $GLOBALS['gPermissionLevel'] > _READONLY) {
			$shipmentId = getFieldFromId("shipment_id", "shipments", "shipment_id", $_GET['primary_id'], "client_id is not null");
			if (empty($shipmentId)) {
				return;
			}
			$resultSet = executeQuery("select * from shipments where shipment_id = ?", $shipmentId);
			$shipmentRow = getNextRow($resultSet);
			$shipmentRow['date_shipped'] = "";
			$shipmentRow['shipping_charge'] = "";
			$shipmentRow['shipping_carrier_id'] = "";
			$shipmentRow['carrier_description'] = "";
			$shipmentRow['tracking_identifier'] = "";
			$shipmentRow['label_url'] = "";

			$queryString = "";
			foreach ($shipmentRow as $fieldName => $fieldData) {
				if (empty($queryString)) {
					$shipmentRow[$fieldName] = "";
				}
				if ($fieldName == "client_id") {
					$shipmentRow[$fieldName] = $GLOBALS['gClientId'];
				}
				$queryString .= (empty($queryString) ? "" : ",") . "?";
			}
			$resultSet = executeQuery("insert into shipments values (" . $queryString . ")", $shipmentRow);
			$newShipmentId = $resultSet['insert_id'];
			$_GET['primary_id'] = $newShipmentId;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
			<?php if ($GLOBALS['gPermissionLevel'] > _READONLY) { ?>
            $(document).on("tap click", "#_duplicate_button", function () {
                if (!empty($("#primary_id").val())) {
                    if (changesMade()) {
                        askAboutChanges(function () {
                            $('body').data('just_saved', 'true');
                            document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=show&subaction=duplicate&primary_id=" + $("#primary_id").val();
                        });
                    } else {
                        document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=show&subaction=duplicate&primary_id=" + $("#primary_id").val();
                    }
                }
                return false;
            });
			<?php } ?>
			<?php if ($this->iEasyPostActive) { ?>
            $(document).on("click", ".postage-rate", function () {
                $("#rate_shipment_id").val($(this).closest("p").find(".rate-shipment-id").val());
            });
            $(document).on("change", "#contact_id", function() {
                if (empty($(this).val())) {
                    return false;
                }
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=load_contact_information&contact_id=" + $(this).val(), function(returnArray) {
                    for (var i in returnArray) {
                        $("#" + i).val(returnArray[i]);
                    }
                });
            });
            $(document).on("change", "#_recently_used_dimensions", function () {
                const selectedDimension = $("#_recently_used_dimensions option:selected").text();
                if (selectedDimension != "[None]") {
                    const dimensions = selectedDimension.split("x");
                    $("#height").val(dimensions[0]);
                    $("#width").val(dimensions[1]);
                    $("#length").val(dimensions[2]);
                }
            })
            $(document).on("click", "#_send_email_button", function () {
                window.open("/sendemail.php?content=" + encodeURIComponent("<p>Your package has shipped by " + $("#carrier_description").val() + ". The tracking ID is " + $("#tracking_identifier").val() + ".</p>"));
            });
            $(document).on("click", "#_create_label_button", function () {
                if (empty($("#primary_id").val())) {
                    return false;
                }
                $("#postage_rates").html("");
                $("#to_full_name").val($("#full_name").val());
                $("#to_attention_line").val($("#attention_line").val());
                $("#to_address_1").val($("#address_1").val());
                $("#to_address_2").val($("#address_2").val());
                $("#to_city").val($("#city").val());
                $("#to_state").val($("#state").val());
                $("#to_postal_code").val($("#postal_code").val());
                $("#height").focus();
                $("#_easy_post_wrapper").find("input").prop("disabled", false);
                $("#_easy_post_wrapper").find("select").prop("disabled", false);

                $('#_easy_post_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 1000,
                    title: 'Create Postage Shipping Label',
                    buttons: [ {
                        text: "Get Rates",
                        click: function () {
                            if ($("#_easy_post_form").validationEngine("validate")) {
                                if (empty($("#postage_rates").html())) {
                                    $("#postage_rates").html("<h4 class='align-center'>Getting Available Rates...</h4>");
                                    $(".create-label-button").prop("disabled", true);
                                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_easy_post_label_rates", $("#_easy_post_form").serialize(), function(returnArray) {
                                        if (!("error_message" in returnArray)) {
                                            $("#_easy_post_wrapper").find("input").prop("disabled", true);
                                            $("#_easy_post_wrapper").find("select").prop("disabled", true);
                                            $(".create-label-button").html("Create Label");
                                            $(".create-label-button").prop("disabled", false);
                                            $("#postage_rates").html("<h3>Available Rates (Choose One)</h3>");
                                            if (!empty(returnArray['insurance_charge'])) {
                                                $("#postage_rates").append("<p class='green-text'>Insurance charges: " + returnArray['insurance_charge'] + "</p>");
                                            }
                                            $("#postage_rates").append("<input type='hidden' id='rate_shipment_id' name='rate_shipment_id' value=''>");
                                            for (const i in returnArray['rates']) {
                                                $("#postage_rates").append("<p><input class='rate-shipment-id' type='hidden' id='rate_shipment_id_" + i + "' value='" + returnArray['rates'][i]['rate_shipment_id'] +
                                                    "'><input tabindex='10' type='radio' class='validate[required] postage-rate' id='postage_rate_" + i + "' name='postage_rate_id' value='" +
                                                    returnArray['rates'][i]['id'] + "'><label class='checkbox-label' for='postage_rate_" + i +
                                                    "'>" + returnArray['rates'][i]['rate'] + ", " + returnArray['rates'][i]['description'] + "</label></p>");
                                            }
                                        }
                                    });
                                } else {
                                    $("#_easy_post_wrapper").find("input").prop("disabled", false);
                                    $("#_easy_post_wrapper").find("select").prop("disabled", false);
                                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_easy_post_label&shipment_id=" + $("#primary_id").val(), $("#_easy_post_form").serialize(), function(returnArray) {
                                        if (!("error_message" in returnArray)) {
                                            $("#shipping_charge").val(returnArray['shipping_charge']).data("crc_value", getCrcValue(returnArray['shipping_charge']));
                                            $("#tracking_identifier").val(returnArray['tracking_identifier']).data("crc_value", getCrcValue(returnArray['tracking_identifier']));
                                            $("#carrier_description").val(returnArray['carrier_description']).data("crc_value", getCrcValue(returnArray['carrier_description']));
                                            $("#shipping_carrier_id").val(returnArray['shipping_carrier_id']).data("crc_value", getCrcValue(returnArray['shipping_carrier_id']));
                                            $("#label_url").val(returnArray['label_url']).data("crc_value", getCrcValue(returnArray['label_url']));
                                            window.open(returnArray['label_url']);
                                            $("#_easy_post_dialog").dialog('close');
                                        } else {
                                            $("#_easy_post_wrapper").find("input").prop("disabled", true);
                                            $("#_easy_post_wrapper").find("select").prop("disabled", true);
                                        }
                                    });
                                }
                            }
                        },
                        'class': 'create-label-button'
                    },
                        {
                            text: "Cancel",
                            click: function () {
                                $("#_easy_post_dialog").dialog('close');
                            }
                        }
                    ]
                });
                return false;
            });
			<?php } ?>
            $(document).on("blur", "#state", function () {
                if ($("#country_id").val() === "1000") {
                    $(this).val($(this).val().toUpperCase());
                }
            });
            $(document).on("blur", "#postal_code", function () {
                const $city = $("#city");
                const $countryId = $("#country_id");
                const $postalCode = $("#postal_code");
                $city.add("#state").prop("readonly", $countryId.val() === "1000" && !empty($postalCode.val()));
                $city.add("#state").attr("tabindex", ($countryId.val() === "1000" && !empty($postalCode.val()) ? "9999" : "10"));
                if ($countryId.val() === "1000") {
                    $postalCode.data("city_hide", "_city_row").data("city_select_hide", "_city_select_row");
                    validatePostalCode();
                }
            });
            $("#country_id").change(function () {
                const $city = $("#city");
                const $countryId = $("#country_id");
                const $postalCode = $("#postal_code");
                $city.add("#state").prop("readonly", $countryId.val() === "1000" && !empty($postalCode.val()));
                $city.add("#state").attr("tabindex", ($countryId.val() === "1000" && !empty($postalCode.val()) ? "9999" : "10"));
                $("#_city_row").show();
                $("#_city_select_row").hide();
                if ($countryId.val() === "1000") {
                    $postalCode.data("city_hide", "_city_row").data("city_select_hide", "_city_select_row");
                    validatePostalCode();
                }
            });
            $("#city_select").change(function () {
                $("#city").val($(this).val());
                $("#state").val($(this).find("option:selected").data("state"));
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function afterGetRecord(returnArray) {
                if (empty(returnArray['primary_id']['data_value']) || !empty(returnArray['tracking_identifier']['data_value'])) {
                    disableButtons($("#_create_label_button"));
                } else {
                    enableButtons($("#_create_label_button"));
                }
                if (empty(returnArray['tracking_identifier']['data_value'])) {
                    disableButtons($("#_send_email_button"));
                } else {
                    enableButtons($("#_send_email_button"));
                }
                const $city = $("#city");
                const $countryId = $("#country_id");
                const $postalCode = $("#postal_code");
                $city.add("#state").attr("tabindex", ($countryId.val() === "1000" && !empty($postalCode.val()) ? "9999" : "10"));
                $city.add("#state").prop("readonly", $countryId.val() === "1000" && !empty($postalCode.val()));
                $("#_city_select_row").hide();
                $("#_city_row").show();
				<?php if ($GLOBALS['gPermissionLevel'] > _READONLY) { ?>
                if (empty($("#primary_id").val())) {
                    disableButtons($("#_duplicate_button"));
                } else {
                    enableButtons($("#_duplicate_button"));
                }
				<?php } ?>
            }
        </script>
		<?php
	}

	function hiddenElements() {
		?>
        <div id="_easy_post_dialog" class="dialog-box">
            <form id="_easy_post_form">
                <p class="error-message"></p>
                <div id="_easy_post_wrapper">
                    <div id="_easy_post_from_address">
                        <h3>From Address</h3>
						<?= createFormControl("orders", "full_name", array("column_name" => "from_full_name", "initial_value" => $GLOBALS['gClientName'], "not_null" => true)) ?>
						<?= createFormControl("contacts", "address_1", array("column_name" => "from_address_1", "initial_value" => $GLOBALS['gClientRow']['address_1'], "not_null" => true)) ?>
						<?= createFormControl("contacts", "address_2", array("column_name" => "from_address_2", "initial_value" => $GLOBALS['gClientRow']['address_2'], "not_null" => false)) ?>
						<?= createFormControl("contacts", "city", array("column_name" => "from_city", "initial_value" => $GLOBALS['gClientRow']['city'], "not_null" => true)) ?>
						<?= createFormControl("contacts", "state", array("column_name" => "from_state", "initial_value" => $GLOBALS['gClientRow']['state'], "not_null" => true)) ?>
						<?= createFormControl("contacts", "postal_code", array("column_name" => "from_postal_code", "initial_value" => $GLOBALS['gClientRow']['postal_code'], "not_null" => true)) ?>
						<?= createFormControl("phone_numbers", "phone_number", array("column_name" => "from_phone_number", "not_null" => true, "data-conditional-required" => "$(\"#signature_required\").prop(\"checked\") || $(\"#adult_signature_required\").prop(\"checked\")")) ?>
                    </div>
                    <div id="_easy_post_to_address">
                        <h3>To Address</h3>
						<?= createFormControl("orders", "full_name", array("column_name" => "to_full_name", "not_null" => true)) ?>
						<?= createFormControl("orders", "attention_line", array("column_name" => "to_attention_line", "not_null" => false)) ?>
						<?= createFormControl("contacts", "address_1", array("column_name" => "to_address_1", "not_null" => true)) ?>
						<?= createFormControl("contacts", "address_2", array("column_name" => "to_address_2", "not_null" => false)) ?>
						<?= createFormControl("contacts", "city", array("column_name" => "to_city", "not_null" => true)) ?>
						<?= createFormControl("contacts", "state", array("column_name" => "to_state", "not_null" => true)) ?>
						<?= createFormControl("contacts", "postal_code", array("column_name" => "to_postal_code", "not_null" => true)) ?>
						<?= createFormControl("phone_numbers", "phone_number", array("column_name" => "to_phone_number", "not_null" => true, "data-conditional-required" => "$(\"#signature_required\").prop(\"checked\") || $(\"#adult_signature_required\").prop(\"checked\")")) ?>
                    </div>
                    <div id="_easy_post_parameters">
                        <h3>Contents Details</h3>
                        <p id='shipment_details'></p>
                        <h4>Package Dimensions</h4>
                        <div class="form-line">
                            <input tabindex="10" type="checkbox" value="1" id="letter_package" name="letter_package"><label class="checkbox-label" for="letter_package">Ship as a letter</label>
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line letter-package">
                            <label class="" for="_recently_used_dimensions">Recently used dimensions</label>
                            <select id="_recently_used_dimensions">
                                <option selected>[None]</option>
								<?php
								$dimensionArray = EasyPostIntegration::getRecentlyUsedDimensions();
								if (!empty($dimensionArray)) {
									foreach ($dimensionArray as $thisDimension) {
										echo "<option>" . $thisDimension . "</option>";
									}
								}
								?>
                            </select>
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line letter-package">
                            <label class="required-label">Height</label>
                            <input tabindex="10" type="text" class="validate[required,custom[number],min[.01]]" data-decimal-places="2" size="10" id="height" name="height">
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line letter-package">
                            <label class="required-label">Width</label>
                            <input tabindex="10" type="text" class="validate[required,custom[number],min[.01]]" data-decimal-places="2" size="10" id="width" name="width">
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line letter-package">
                            <label class="required-label">Length</label>
                            <input tabindex="10" type="text" class="validate[required,custom[number],min[.01]]" data-decimal-places="2" size="10" id="length" name="length">
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line">
                            <label class="required-label">Weight (lbs or oz)</label>
                            <input tabindex="10" type="text" class="validate[required,custom[number],min[.01]]" data-decimal-places="4" size="10" id="weight" name="weight">
                            <select id="weight_unit" name="weight_unit">
								<?php
								$pagePreferences = Page::getPagePreferences();
								if ($pagePreferences['weight_unit'] != "ounce") {
									$pagePreferences['weight_unit'] = "pound";
								}
								?>
                                <option value="pound"<?= ($pagePreferences['weight_unit'] == "pound" ? " selected" : "") ?>>Lbs</option>
                                <option value="ounce"<?= ($pagePreferences['weight_unit'] == "ounce" ? " selected" : "") ?>>Oz</option>
                            </select>
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line letter-package">
                            <input tabindex="10" type="checkbox" id="signature_required" name="signature_required" value="SIGNATURE"><label class="checkbox-label" for="signature_required">Signature is required</label>
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line letter-package">
                            <input tabindex="10" type="checkbox" id="adult_signature_required" name="adult_signature_required" value="ADULT_SIGNATURE"><label class="checkbox-label" for="adult_signature_required">Adult Signature is required</label>
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line letter-package">
                            <input tabindex="10" type="checkbox" id="include_media" name="include_media" value="1"><label class="checkbox-label" for="include_media">Include Media Mail (books & videos)</label>
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line">
                            <label>Insurance Amount</label>
                            <span class='help-label'><?= getPreference("EASY_POST_INSURANCE_PERCENT") ?: 1 ?>% charge applies. Leave blank to use carrier default</span>
                            <input tabindex="10" type="text" class="validate[custom[number],min[.01]]" data-decimal-places="2" size="10" id="insurance_amount" name="insurance_amount">
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line">
                            <input tabindex="10" type="checkbox" id="use_carrier_insurance" name="use_carrier_insurance" value="1"><label class="checkbox-label" for="use_carrier_insurance">Use Carrier Insurance instead of EasyPost Insurance</label>
                            <div class='clear-div'></div>
                        </div>

                    </div>
                </div>

                <div id="postage_rates">
                </div>
            </form>
        </div>

		<?php
	}

	function internalCSS() {
		?>
        <style>
            #postage_rates {
                border-top: 1px solid rgb(180, 180, 180);
                padding-top: 20px;
                margin-top: 20px;
            }

            #_easy_post_wrapper {
                display: flex;
            }

            #_easy_post_wrapper > div {
                margin: 0 10px;
                flex: 1 1 auto;
            }

            #_easy_post_wrapper input {
                max-width: 300px;
            }

            #_easy_post_wrapper select {
                max-width: 300px;
            }

            #_easy_post_wrapper input#weight {
                min-width: 10px;
                max-width: 120px;
                width: 120px;
            }

            #_easy_post_wrapper select#weight_unit {
                min-width: 10px;
                max-width: 100px;
                width: 100px;
            }

        </style>
		<?php
	}
}

$pageObject = new ShipmentMaintenancePage("shipments");
$pageObject->displayPage();
