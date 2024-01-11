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

$GLOBALS['gPageCode'] = "CREATEEVENTS";
require_once "shared/startup.inc";

class CreateEventsPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "import_product_addons":
				$resultSet = executeQuery("select * from product_addon_set_entries where product_addon_set_id = ?", $_GET['product_addon_set_id']);
				$returnArray['product_addons'] = array();
				while ($row = getNextRow($resultSet)) {
					$rowValues = array();
					$rowValues['description'] = array("data_value" => $row['description'], "crc_value" => getCrcValue($row['description']));
					$rowValues['group_description'] = array("data_value" => $row['group_description'], "crc_value" => getCrcValue($row['group_description']));
					$rowValues['manufacturer_sku'] = array("data_value" => $row['manufacturer_sku'], "crc_value" => getCrcValue($row['manufacturer_sku']));
					$rowValues['form_definition_id'] = array("data_value" => $row['form_definition_id'], "crc_value" => getCrcValue($row['form_definition_id']));
					$rowValues['inventory_product_id'] = array("data_value" => $row['inventory_product_id'], "crc_value" => getCrcValue($row['inventory_product_id']));
					$rowValues['maximum_quantity'] = array("data_value" => $row['maximum_quantity'], "crc_value" => getCrcValue($row['maximum_quantity']));
					$rowValues['sale_price'] = array("data_value" => $row['sale_price'], "crc_value" => getCrcValue($row['sale_price']));
					$rowValues['sort_order'] = array("data_value" => $row['sort_order'], "crc_value" => getCrcValue($row['sort_order']));
					$returnArray['product_addons'][] = $rowValues;
				}
				ajaxResponse($returnArray);
				break;
			case "get_event_type_info":
				$returnArray['detailed_description'] = getFieldFromId("detailed_description", "event_types", "event_type_id", $_GET['event_type_id']);
				$returnArray['price'] = getFieldFromId("price", "event_types", "event_type_id", $_GET['event_type_id']);
				$returnArray['attendees'] = getFieldFromId("attendees", "event_types", "event_type_id", $_GET['event_type_id']);
				ajaxResponse($returnArray);
				break;
			case "create_events":
				$locationId = "";
				foreach ($_POST as $fieldName => $fieldValue) {
					if (startsWith($fieldName, "event_facilities_facility_id-")) {
						$locationId = getFieldFromId("location_id", "facilities", "facility_id", $fieldValue);
					}
				}
				$eventData = array("name_values" => array("description" => $_POST['description'], "detailed_description" => $_POST['detailed_description'],
					"event_type_id" => $_POST['event_type_id'], "attendees" => $_POST['attendees'], "date_created" => date("m/d/Y"), "location_id" => $locationId,
					"mailing_list_id" => $_POST['mailing_list_id'], "category_id" => $_POST['category_id'], "email_id" => $_POST['email_id'],
					"internal_use_only" => (empty($_POST['internal_use_only']) ? 1 : 0)));
				$startDate = date("Y-m-d", strtotime($_POST['recurring_start_date']));
				$currentDate = $startDate;
				$endDate = date("Y-m-d", strtotime($_POST['until']));
				$GLOBALS['gPrimaryDatabase']->startTransaction();
				$eventDataTable = new DataTable("events");
				$salePriceTypeId = getFieldFromId("product_price_type_id", "product_price_types", "product_price_type_code", "SALE_PRICE");
				if (empty($salePriceTypeId)) {
					$resultSet = executeQuery("insert into product_price_types (client_id,product_price_type_code,description) values (?,'SALE_PRICE','Sale Price')", $GLOBALS['gClientId']);
					$salePriceTypeId = $resultSet['insert_id'];
				}
				$productTypeId = getFieldFromId("product_type_id", "product_types", "product_type_code", "EVENT_REGISTRATION");
				if (empty($productTypeId)) {
					$resultSet = executeQuery("insert into product_types (client_id,product_type_code,description) values (?,'EVENT_REGISTRATION','Event Registration')", $GLOBALS['gClientId']);
					$productTypeId = $resultSet['insert_id'];
				}

				$displayTime = Events::getDisplayTime($_POST['reserve_start_time']) . "-" . Events::getDisplayTime($_POST['reserve_end_time']);

				$count = 0;
				$eventDates = array();
				if ($_POST['date_type'] == "R") {
					$_POST['recurring'] = true;
					$repeatRules = Events::makeRepeatRules($_POST);
					while ($currentDate < $endDate) {
                        if (isInSchedule($currentDate,$repeatRules)) {
	                        $eventDates[] = array("date" => date("m/d/Y", strtotime($currentDate)), "start_time" => $_POST['reserve_start_time'], "end_time" => $_POST['reserve_end_time'], "display_time" => $displayTime);
                        }
						$currentDate = date("Y-m-d", strtotime($currentDate . '+1 day'));
					}
				} else {
					foreach ($_POST as $fieldName => $fieldValue) {
						if (!startsWith($fieldName, "specific_dates_date-")) {
							continue;
						}
						$rowNumber = substr($fieldName, strlen("specific_dates_date-"));
						if (!is_numeric($rowNumber)) {
							continue;
						}
						$reserveStartTime = Events::getTime($_POST['specific_dates_start_time-' . $rowNumber]);
						$reserveEndTime = Events::getTime($_POST['specific_dates_end_time-' . $rowNumber], true);
						$displayTime = Events::getDisplayTime($reserveStartTime) . "-" . Events::getDisplayTime($reserveEndTime);
						$eventDates[] = array("date" => $_POST['specific_dates_date-' . $rowNumber], "start_time" => $reserveStartTime, "end_time" => $reserveEndTime, "display_time" => $displayTime);
					}
				}
				if (empty($eventDates)) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "No dates to create";
					ajaxResponse($returnArray);
					break;
				}
				foreach ($eventDates as $thisDateInfo) {
					$thisDate = $currentDate = date("Y-m-d", strtotime($thisDateInfo['date']));
					$eventData['name_values']['start_date'] = date("m/d/Y", strtotime($thisDate));

					$hour = floor($thisDateInfo['start_time']);
					$minutes = ($thisDateInfo['start_time'] - $hour) * 60;
					$eventData['name_values']['start_time'] = str_pad($hour, 2, "0", STR_PAD_LEFT)
						. str_pad($minutes, 2, "0", STR_PAD_LEFT);
					if (!empty($_POST['create_product']) && strlen($_POST['product_price']) > 0) {
						$productCode = "EVENT_" . date("mdY", strtotime($currentDate)) . "_" . strtoupper(getRandomString(10));
						$detailedDescription = $_POST['detailed_description'];
						$imageId = "";
						if (!empty($_FILES['image_id_file'])) {
							$imageId = createImage("image_id_file");
						}
						if (empty($imageId)) {
							$imageId = getFieldFromId("image_id", "event_types", "event_type_id", $_POST['event_type_id']);
						}
						$linkName = makeCode("registration for " . $_POST['description'] . " on " . $eventData['name_values']['start_date'] . " at " . $eventData['name_values']['start_time'],
							array("use_dash" => true, "lowercase" => true));
						$insertSet = executeQuery("insert into products (client_id,product_code,description,detailed_description,image_id,product_type_id,link_name,cart_maximum,tax_rate_id,date_created,time_changed,expiration_date,list_price,not_taxable,reindex,virtual_product,non_inventory_item,internal_use_only) values (?,?,?,?,?,?,?,?,?,now(),now(),?,?,?,1,1,1,?)",
							$GLOBALS['gClientId'], $productCode, $_POST['description'] . ", " . date("m/d/Y", strtotime($thisDate)) . " " . $thisDateInfo['display_time'] . " registration", $detailedDescription, $imageId, $productTypeId,
							$linkName, $_POST['cart_maximum'], $_POST['tax_rate_id'], date("Y-m-d", strtotime($eventData['name_values']['start_date'] . "-" . $_POST['expiration_days'] . " days")), $_POST['product_price'],
							(empty($_POST['not_taxable']) ? 0 : 1), (empty($_POST['product_internal_use_only']) ? 0 : 1));
						if (!empty($insertSet['sql_error'])) {
							$returnArray['error_message'] = $insertSet['sql_error'];
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							break;
						}
						$eventData['name_values']['product_id'] = $productId = $insertSet['insert_id'];

						$eventTypeProductId = getFieldFromId("product_id", "event_types", "event_type_id", $_POST['event_type_id']);
                        if (!empty($eventTypeProductId)) {
                            $resultSet = executeQuery("select * from related_products where product_id = ?",$eventTypeProductId);
                            while ($row = getNextRow($resultSet)) {
                                executeQuery("insert into related_products (product_id,associated_product_id,related_product_type_id) values (?,?,?)",$productId,$row['associated_product_id'],$row['related_product_type_id']);
                            }
                        }

						if (!empty($_POST['product_category_id'])) {
							$productCategoryLinksDataTable = new DataTable("product_category_links");
							$productCategoryLinksDataTable->saveRecord(array("name_values" => array("product_category_id" => $_POST['product_category_id'], "product_id" => $productId)));
						}
						$relatedProductIds = array();
						foreach ($_POST as $fieldName => $fieldValue) {
							if (startsWith($fieldName, "related_products_product_id-")) {
								$associatedProductId = getFieldFromId("product_id", "products", "product_id", $fieldValue);
								if (!empty($associatedProductId) && !in_array($associatedProductId, $relatedProductIds) && $associatedProductId != $productId) {
									executeQuery("insert ignore into related_products (product_id,associated_product_id) values (?,?)", $productId, $associatedProductId);
									$relatedProductIds[] = $associatedProductId;
								}
							}
							if (startsWith($fieldName, "product_addons_description-")) {
								$rowNumber = substr($fieldName, strlen("product_addons_description-"));
								executeQuery("insert into product_addons (product_id,description,group_description,manufacturer_sku,form_definition_id,maximum_quantity,sale_price,sort_order) values (?,?,?,?,?,?,?,?)",
									$productId, $fieldValue, $_POST['product_addons_group_description-' . $rowNumber], $_POST['product_addons_manufacturer_sku-' . $rowNumber],
                                    $_POST['product_addons_form_definition_id-' . $rowNumber],($_POST['product_addons_maximum_quantity-' . $rowNumber] ?: 0), $_POST['product_addons_sale_price-' . $rowNumber],
									$_POST['product_addons_sort_order-' . $rowNumber]);
							}
						}
					}
					$linkName = (empty($_POST['link_name']) ? $_POST['description'] : $_POST['link_name']) . " " . $eventData['name_values']['start_date'] . " " . $eventData['name_values']['start_time'];
					$eventData['name_values']['link_name'] = makeCode($linkName, array("use_dash" => true, "lowercase" => true));
					$eventId = $eventDataTable->saveRecord($eventData);
					if (empty($eventId)) {
						$returnArray['error_message'] = $eventDataTable->getErrorMessage();
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						break;
					}
					if (!empty($_POST['event_group_id'])) {
						executeQuery("insert into event_group_links (event_id,event_group_id) values (?,?)", $eventId, $_POST['event_group_id']);
					}
					foreach ($_POST as $fieldName => $fieldValue) {
						if (substr($fieldName, 0, strlen("event_facilities_facility_id-")) != "event_facilities_facility_id-") {
							continue;
						}
						$rowNumber = substr($fieldName, strlen("event_facilities_facility_id-"));
						$facilityId = $fieldValue;

						$startHour = empty($_POST['event_facilities_start_time-' . $rowNumber]) ? $thisDateInfo['start_time'] : Events::getTime($_POST['event_facilities_start_time-' . $rowNumber]);
						$endHour = empty($_POST['event_facilities_end_time-' . $rowNumber]) ? $thisDateInfo['end_time'] : Events::getTime($_POST['event_facilities_end_time-' . $rowNumber], true);

						$dateNeeded = $currentDate;
						if (empty($_POST['ignore_facility_usages'])) {
							$availableFacilityIds = Events::getAvailableFacilities("", $facilityId, $dateNeeded, $startHour, $endHour);
							if (!is_array($availableFacilityIds) || !in_array($facilityId, $availableFacilityIds)) {
								$returnArray['error_message'] = "The facility '" . getFieldFromId("description", "facilities", "facility_id", $facilityId)
									. "' is not available on " . date("m/d/Y", strtotime($dateNeeded)) . ". It may be already booked or not open at the requested time.";
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								ajaxResponse($returnArray);
								break;
							}
						}
						for ($x = $startHour; $x < $endHour; $x += .25) {
							executeQuery("insert into event_facilities (event_id,facility_id,date_needed,hour) values (?,?,?,?)",
								$eventId, $facilityId, $dateNeeded, $x);
						}
					}
					$count++;
					$currentDate = date("Y-m-d", strtotime($currentDate . '+1 day'));
				}
				if (empty($returnArray['error_message'])) {
					$returnArray['info_message'] = $count . " events created";
					executeQuery("update background_processes set run_immediately = 1 where background_process_code = 'EVENT_PRODUCT_VARIANTS'");
					$GLOBALS['gPrimaryDatabase']->commitTransaction();
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#frequency").change(function () {
                $("#_bymonth_row").hide();
                $("#_byday_row").hide();
                $("#byday_weekly_table").hide();
                $("#byday_monthly_table").hide();
                $(".byday-monthly-row").hide();
                $(".ordinal-day").val("");
                $(".weekday-select").val("");
                const thisValue = $(this).val();
                if (thisValue === "WEEKLY") {
                    $("#_byday_row").show();
                    $("#byday_weekly_table").show();
                } else if (thisValue === "MONTHLY") {
                    $("#_byday_row").show();
                    $("#byday_monthly_table").show();
                    $(".byday-monthly-row:first-child").show();
                } else if (thisValue === "YEARLY") {
                    $("#_bymonth_row").show();
                    $("#_byday_row").show();
                    $("#byday_monthly_table").show();
                    $(".byday-monthly-row:first-child").show();
                }
            });

            $(".ordinal-day").change(function () {
                if (!empty($(this).val())) {
                    $(".ordinal-day").each(function () {
                        if (!$(this).is(":visible")) {
                            $(this).closest(".byday-monthly-row").show();
                            return false;
                        } else if (empty($(this).val())) {
                            return false;
                        }
                    });
                }
            });

            $(".bymonth-month").click(function () {
                $(".bymonth-month:first").validationEngine("hideAll").removeClass("formFieldError");
            });

            $(".byday-weekday").click(function () {
                $(".byday-weekday:first").validationEngine("hideAll").removeClass("formFieldError");
            });

            $(document).on("change", "#product_addon_set_id", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=import_product_addons&product_addon_set_id=" + encodeURIComponent($(this).val()), function (returnArray) {
                    if ("product_addons" in returnArray) {
                        for (const i in returnArray['product_addons']) {
                            addEditableListRow("product_addons", returnArray['product_addons'][i]);
                        }
                        $("#product_addon_set_id").val("");
                    }
                });
                return false;
            });
            $(document).on("keyup change", "#description, #link_name", function (event) {
                const linkName = $('#link_name');
                const preformattedUrl = empty(linkName.val()) ? $('#description').val() : linkName.val()
                linkName.attr('placeholder', preformattedUrl ? makeCode(preformattedUrl, {useDash: true, lowercase: true}) : "");
            });
            $(document).on("change", "#description", function (event) {
                if (!empty($(this).val()) && $(this).val() !== $("#event_type_id").find(":selected").text()) {
                    $(this).data("manually_changed", true);
                } else {
                    $(this).data("manually_changed", false);
                }
            });
            $("#event_type_id").change(function () {
                if ($(this).val() > 0) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_event_type_info&event_type_id=" + $(this).val(), function (returnArray) {
                        CKEDITOR.instances["detailed_description"].setData(returnArray['detailed_description']);
                        if (empty($("#attendees").val()) && !empty(returnArray['attendees'])) {
                            $("#attendees").val(returnArray['attendees']);
                        }
                        if (empty($("#product_price").val()) && !empty(returnArray['price'])) {
                            $("#product_price").val(returnArray['price']);
                            if (parseFloat(returnArray['price']) > 0) {
                                if (!($("#create_product").prop("checked"))) {
                                    $("#create_product").trigger("click");
                                }
                            }
                        }
                    });
                    if (empty($("#description").data("manually_changed"))) {
                        $("#description").val($(this).find(":selected").text()).trigger("change");
                    }
                }
            });
            $(document).on("click", "#create_events", function () {
                for (let instance in CKEDITOR.instances) {
                    CKEDITOR.instances[instance].updateElement();
                }
                if ($("#_event_facilities_table").find(".editable-list-data-row").length === 0) {
                    displayErrorMessage("Choose at least one facility");
                    return false;
                }
                if ($("#date_type_specific").is(":checked")) {
                    const specificDatesElement = $("#_specific_dates_table");
                    if (specificDatesElement.length && !specificDatesElement.find(".editable-list-data-row").length) {
                        specificDatesElement.validationEngine("showPrompt", "At least one event date is required");
                        displayErrorMessage("At least one event date is required");
                        return false;
                    }
                }
                if ($("#_edit_form").validationEngine('validate')) {
                    $("body").addClass("waiting-for-ajax");
                    $("#_post_iframe").off("load");
                    $("#_edit_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_events").attr("method", "POST").attr("target", "post_iframe").submit();
                    $("#_post_iframe").on("load", function () {
                        if (postTimeout != null) {
                            clearTimeout(postTimeout);
                        }
                        $("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
                        const returnText = $(this).contents().find("body").html();
                        const returnArray = processReturn(returnText);
                        if (returnArray === false) {
                            return;
                        }
                        if (!returnArray["error_message"]) {
                            $("#_edit_form").addClass("hidden");
                            $("#_event_published_message").removeClass("hidden");
                            $("body").data("just_saved", "true");
                            window.scrollTo(0, 0);
                        }
                    });
                    postTimeout = setTimeout(function () {
                        postTimeout = null;
                        $("#_post_iframe").off("load");
                        $("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
                        displayErrorMessage("<?= getSystemMessage("not_responding") ?>");
                        regardlessFunction();
                    }, <?= (empty($GLOBALS['gDefaultAjaxTimeout']) || !is_numeric($GLOBALS['gDefaultAjaxTimeout']) ? "30000" : $GLOBALS['gDefaultAjaxTimeout']) ?>);
                }
                return false;
            });
            $("#internal_use_only").prop('checked', true);
            $("#date_type_specific").click(function () {
                $("#date_type_repeating_wrapper").addClass("hidden");
                $("#date_type_specific_wrapper").removeClass("hidden");
            });
            $("#date_type_repeating").click(function () {
                $("#date_type_repeating_wrapper").removeClass("hidden");
                $("#date_type_specific_wrapper").addClass("hidden");
                $("#frequency").trigger("change");
            });
            $(document).on("click", "#create_product", function () {
                if ($(this).prop("checked")) {
                    $(".product-field").removeClass("hidden");
                } else {
                    $(".product-field").addClass("hidden");
                }
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function afterAddEditableRow(listName, rowNumber, rowData) {
                if (listName === "specific_dates") {
                    const startTimeElements = $("#date_type_specific_wrapper [id^=specific_dates_start_time]");
                    if (startTimeElements.length > 1) {
                        // If second to the last element value is valid, copy over to the next row
                        const priorElement = $(startTimeElements.get(startTimeElements.length - 2));
                        if (!priorElement.validationEngine('validate')) {
                            $(startTimeElements.get(startTimeElements.length - 1)).val(priorElement.val());
                        }
                    }
                    const endTimeElements = $("#date_type_specific_wrapper [id^=specific_dates_end_time]");
                    if (endTimeElements.length > 1) {
                        // If second to the last element value is valid, copy over to the next row
                        const priorElement = $(endTimeElements.get(endTimeElements.length - 2));
                        if (!priorElement.validationEngine('validate')) {
                            $(endTimeElements.get(endTimeElements.length - 1)).val(priorElement.val());
                        }
                    }
                }
            }
        </script>
		<?php
	}

	function customGetControlRecords($controlCode) {
		if ($controlCode == "facilities") {
			return $this->facilityChoices();
		}
		return false;
	}

	function facilityChoices($showInactive = false) {
		$facilityChoices = array();
		$resultSet = executeQuery("select * from facilities where inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$facilityChoices[$row['facility_id']] = array("key_value" => $row['facility_id'], "description" => $row['description'], "inactive" => $row['inactive']);
		}
		freeResult($resultSet);
		return $facilityChoices;
	}

	function formChoices($showInactive = false) {
		$formChoices = array();
		$resultSet = executeQuery("select * from form_definitions where inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$formChoices[$row['form_definition_id']] = array("key_value" => $row['form_definition_id'], "description" => $row['description'], "inactive" => $row['inactive']);
		}
		freeResult($resultSet);
		return $formChoices;
	}

	function mainContent() {
		echo $this->iPageData['content'];
		?>
        <form id="_edit_form" name="_edit_form" enctype="multipart/form-data">
            <div class="form-fields">
                <h2>Event Details</h2>
				<?php
				echo createFormControl("events", "description", array("form_label" => "Event name", "not_null" => true));
				echo createFormControl("events", "event_type_id", array("form_label" => "Event type", "not_null" => true));
				?>
                <div class='clear-div'></div>
				<?php
				echo createFormControl("events", "link_name", array("form_label" => "Event page URL", "classes" => "url-link"));
				echo createFormControl("products", "image_id", array("form_label" => "Event image", "not_null" => false, "data_type" => "image_input"));
				echo createFormControl("events", "detailed_description", array("form_label" => "Event description", "classes" => "ck-editor"));
				?>
            </div>

            <div class="form-fields">
                <h2>Event Schedule</h2>
                <div class="basic-form-line" id="_date_type_row">
                    <input tabindex="10" type="radio" checked="checked" name="date_type" id="date_type_specific" value="S"><label for="date_type_specific" class="checkbox-label">Specific Dates</label>
                    <input tabindex="10" type="radio" name="date_type" id="date_type_repeating" value="R"><label for="date_type_repeating" class="checkbox-label">Repeating event</label><br>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
                <div id="date_type_specific_wrapper">
                    <div class="basic-form-line" id="_specific_dates_row">
                        <label>Event Dates</label>
						<?= $this->getSpecificDatesEditableList()->getControl() ?>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>
                </div>
                <div id="date_type_repeating_wrapper" class="hidden">
                    <div>
		                <?php
		                echo createFormControl("events", "start_date", array("column_name"=>"recurring_start_date","not_null" => true));
		                echo createFormControl("events", "end_date", array("column_name"=>"until","not_null" => true));
		                ?>
                    </div>
                    <div>
                        <div class="basic-form-line" id="_reserve_start_time_row">
                            <label for="reserve_start_time" class="required-label">Start Time</label>
                            <select tabindex='10' id="reserve_start_time" name="reserve_start_time" class="validate[required]">
                                <option value="" data-hour="0" data-minute="0">[Select]</option>
				                <?php
				                for ($x = 0; $x < 24; $x += .25) {
					                $timeArray = Events::getDisplayTime($x, false, true);
					                ?>
                                    <option value="<?= $x ?>" data-hour="<?= $timeArray['hour'] ?>" data-minute="<?= $timeArray['minutes'] ?>"><?= $timeArray['formatted'] ?></option>
				                <?php } ?>
                            </select>
                            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                        </div>
                        <div class="basic-form-line" id="_reserve_end_time_row">
                            <label for="reserve_end_time" class="required-label">End Time</label>
                            <select tabindex='10' id="reserve_end_time" name="reserve_end_time" class="validate[required]">
                                <option value="" data-hour="24" data-minute="0">[Select]</option>
				                <?php
				                for ($x = 0.25; $x <= 24; $x += .25) {
					                $timeArray = Events::getDisplayTime($x, false, true);
					                ?>
                                    <option value="<?= $x ?>" data-hour="<?= $timeArray['hour'] ?>" data-minute="<?= $timeArray['minutes'] ?>"><?= $timeArray['formatted'] ?></option>
				                <?php } ?>
                            </select>
                            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                        </div>
                    </div>

                    <div class="basic-form-line" id="_frequency_row">
                        <label for="frequency">Frequency</label>
                        <select class="recurring-event-field" name='frequency' id='frequency'>
                            <option value="DAILY">Daily</option>
                            <option value="WEEKLY">Weekly</option>
                            <option value="MONTHLY">Monthly</option>
                            <option value="YEARLY">Yearly</option>
                        </select>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>

                    <div class="basic-form-line" id="_bymonth_row">
                        <label>Months</label>
                        <table id='bymonth_table'>
                            <tr>
                                <td><input class='recurring-event-field bymonth-month' type='checkbox' value='1' name='bymonth_1' id='bymonth_1'/><label for="bymonth_1" class="checkbox-label">January</label></td>
                                <td><input class='recurring-event-field bymonth-month' type='checkbox' value='4' name='bymonth_4' id='bymonth_4'/><label for="bymonth_4" class="checkbox-label">April</label></td>
                                <td><input class='recurring-event-field bymonth-month' type='checkbox' value='7' name='bymonth_7' id='bymonth_7'/><label for="bymonth_7" class="checkbox-label">July</label></td>
                                <td><input class='recurring-event-field bymonth-month' type='checkbox' value='10' name='bymonth_10' id='bymonth_10'/><label for="bymonth_10" class="checkbox-label">October</label></td>
                            </tr>
                            <tr>
                                <td><input class='recurring-event-field bymonth-month' type='checkbox' value='2' name='bymonth_2' id='bymonth_2'/><label for="bymonth_2" class="checkbox-label">February</label></td>
                                <td><input class='recurring-event-field bymonth-month' type='checkbox' value='5' name='bymonth_5' id='bymonth_5'/><label for="bymonth_5" class="checkbox-label">May</label></td>
                                <td><input class='recurring-event-field bymonth-month' type='checkbox' value='8' name='bymonth_8' id='bymonth_8'/><label for="bymonth_8" class="checkbox-label">August</label></td>
                                <td><input class='recurring-event-field bymonth-month' type='checkbox' value='11' name='bymonth_11' id='bymonth_11'/><label for="bymonth_11" class="checkbox-label">November</label></td>
                            </tr>
                            <tr>
                                <td><input class='recurring-event-field bymonth-month' type='checkbox' value='3' name='bymonth_3' id='bymonth_3'/><label for="bymonth_3" class="checkbox-label">March</label></td>
                                <td><input class='recurring-event-field bymonth-month' type='checkbox' value='6' name='bymonth_6' id='bymonth_6'/><label for="bymonth_6" class="checkbox-label">June</label></td>
                                <td><input class='recurring-event-field bymonth-month' type='checkbox' value='9' name='bymonth_9' id='bymonth_9'/><label for="bymonth_9" class="checkbox-label">September</label></td>
                                <td><input class='recurring-event-field bymonth-month' type='checkbox' value='12' name='bymonth_12' id='bymonth_12'/><label for="bymonth_12" class="checkbox-label">December</label></td>
                            </tr>
                        </table>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>

                    <div class="basic-form-line" id="_byday_row">
                        <label>Days</label>
                        <table id="byday_weekly_table">
                            <tr>
                                <td><input class='recurring-event-field byday-weekday' type='checkbox' value='SUN' name='byday_sun' id='byday_sun'/><label for="byday_sun" class="checkbox-label">Sunday</label></td>
                                <td><input class='recurring-event-field byday-weekday' type='checkbox' value='MON' name='byday_mon' id='byday_mon'/><label for="byday_mon" class="checkbox-label">Monday</label></td>
                                <td><input class='recurring-event-field byday-weekday' type='checkbox' value='TUE' name='byday_tue' id='byday_tue'/><label for="byday_tue" class="checkbox-label">Tuesday</label></td>
                                <td><input class='recurring-event-field byday-weekday' type='checkbox' value='WED' name='byday_wed' id='byday_wed'/><label for="byday_wed" class="checkbox-label">Wednesday</label></td>
                                <td><input class='recurring-event-field byday-weekday' type='checkbox' value='THU' name='byday_thu' id='byday_thu'/><label for="byday_thu" class="checkbox-label">Thursday</label></td>
                                <td><input class='recurring-event-field byday-weekday' type='checkbox' value='FRI' name='byday_fri' id='byday_fri'/><label for="byday_fri" class="checkbox-label">Friday</label></td>
                                <td><input class='recurring-event-field byday-weekday' type='checkbox' value='SAT' name='byday_sat' id='byday_sat'/><label for="byday_sat" class="checkbox-label">Saturday</label></td>
                            </tr>
                        </table>
                        <table id="byday_monthly_table">
                            <tr class="byday-monthly-row">
                                <td><select class='recurring-event-field ordinal-day' id='ordinal_day_1' name='ordinal_day_1'>
                                        <option value="">[Select]</option>
                                        <option value="1">1st</option>
                                        <option value="2">2nd</option>
                                        <option value="3">3rd</option>
                                        <option value="4">4th</option>
                                        <option value="5">5th</option>
                                        <option value="6">6th</option>
                                        <option value="7">7th</option>
                                        <option value="8">8th</option>
                                        <option value="9">9th</option>
                                        <option value="10">10th</option>
                                        <option value="11">11th</option>
                                        <option value="12">12th</option>
                                        <option value="13">13th</option>
                                        <option value="14">14th</option>
                                        <option value="15">15th</option>
                                        <option value="16">16th</option>
                                        <option value="17">17th</option>
                                        <option value="18">18th</option>
                                        <option value="19">19th</option>
                                        <option value="20">20th</option>
                                        <option value="21">21st</option>
                                        <option value="22">22nd</option>
                                        <option value="23">23rd</option>
                                        <option value="24">24th</option>
                                        <option value="25">25th</option>
                                        <option value="26">26th</option>
                                        <option value="27">27th</option>
                                        <option value="28">28th</option>
                                        <option value="29">29th</option>
                                        <option value="30">30th</option>
                                        <option value="31">31st</option>
                                        <option value="-">Last</option>
                                    </select></td>
                                <td><select class='recurring-event-field weekday-select' id='weekday_1' name='weekday_1'>
                                        <option value="">Day of the Month</option>
                                        <option value="SUN">Sunday</option>
                                        <option value="MON">Monday</option>
                                        <option value="TUE">Tuesday</option>
                                        <option value="WED">Wednesday</option>
                                        <option value="THU">Thursday</option>
                                        <option value="FRI">Friday</option>
                                        <option value="SAT">Saturday</option>
                                    </select></td>
                            </tr>
                            <tr class="byday-monthly-row">
                                <td><select class='recurring-event-field ordinal-day' id='ordinal_day_2' name='ordinal_day_2'>
                                        <option value="">[Select]</option>
                                        <option value="1">1st</option>
                                        <option value="2">2nd</option>
                                        <option value="3">3rd</option>
                                        <option value="4">4th</option>
                                        <option value="5">5th</option>
                                        <option value="6">6th</option>
                                        <option value="7">7th</option>
                                        <option value="8">8th</option>
                                        <option value="9">9th</option>
                                        <option value="10">10th</option>
                                        <option value="11">11th</option>
                                        <option value="12">12th</option>
                                        <option value="13">13th</option>
                                        <option value="14">14th</option>
                                        <option value="15">15th</option>
                                        <option value="16">16th</option>
                                        <option value="17">17th</option>
                                        <option value="18">18th</option>
                                        <option value="19">19th</option>
                                        <option value="20">20th</option>
                                        <option value="21">21st</option>
                                        <option value="22">22nd</option>
                                        <option value="23">23rd</option>
                                        <option value="24">24th</option>
                                        <option value="25">25th</option>
                                        <option value="26">26th</option>
                                        <option value="27">27th</option>
                                        <option value="28">28th</option>
                                        <option value="29">29th</option>
                                        <option value="30">30th</option>
                                        <option value="31">31st</option>
                                        <option value="-">Last</option>
                                    </select></td>
                                <td><select class='recurring-event-field weekday-select' id='weekday_2' name='weekday_2'>
                                        <option value="">Day of the Month</option>
                                        <option value="SUN">Sunday</option>
                                        <option value="MON">Monday</option>
                                        <option value="TUE">Tuesday</option>
                                        <option value="WED">Wednesday</option>
                                        <option value="THU">Thursday</option>
                                        <option value="FRI">Friday</option>
                                        <option value="SAT">Saturday</option>
                                    </select></td>
                            </tr>
                            <tr class="byday-monthly-row">
                                <td><select class='recurring-event-field ordinal-day' id='ordinal_day_3' name='ordinal_day_3'>
                                        <option value="">[Select]</option>
                                        <option value="1">1st</option>
                                        <option value="2">2nd</option>
                                        <option value="3">3rd</option>
                                        <option value="4">4th</option>
                                        <option value="5">5th</option>
                                        <option value="6">6th</option>
                                        <option value="7">7th</option>
                                        <option value="8">8th</option>
                                        <option value="9">9th</option>
                                        <option value="10">10th</option>
                                        <option value="11">11th</option>
                                        <option value="12">12th</option>
                                        <option value="13">13th</option>
                                        <option value="14">14th</option>
                                        <option value="15">15th</option>
                                        <option value="16">16th</option>
                                        <option value="17">17th</option>
                                        <option value="18">18th</option>
                                        <option value="19">19th</option>
                                        <option value="20">20th</option>
                                        <option value="21">21st</option>
                                        <option value="22">22nd</option>
                                        <option value="23">23rd</option>
                                        <option value="24">24th</option>
                                        <option value="25">25th</option>
                                        <option value="26">26th</option>
                                        <option value="27">27th</option>
                                        <option value="28">28th</option>
                                        <option value="29">29th</option>
                                        <option value="30">30th</option>
                                        <option value="31">31st</option>
                                        <option value="-">Last</option>
                                    </select></td>
                                <td><select class='recurring-event-field weekday-select' id='weekday_3' name='weekday_3'>
                                        <option value="">Day of the Month</option>
                                        <option value="SUN">Sunday</option>
                                        <option value="MON">Monday</option>
                                        <option value="TUE">Tuesday</option>
                                        <option value="WED">Wednesday</option>
                                        <option value="THU">Thursday</option>
                                        <option value="FRI">Friday</option>
                                        <option value="SAT">Saturday</option>
                                    </select></td>
                            </tr>
                            <tr class="byday-monthly-row">
                                <td><select class='recurring-event-field ordinal-day' id='ordinal_day_4' name='ordinal_day_4'>
                                        <option value="">[Select]</option>
                                        <option value="1">1st</option>
                                        <option value="2">2nd</option>
                                        <option value="3">3rd</option>
                                        <option value="4">4th</option>
                                        <option value="5">5th</option>
                                        <option value="6">6th</option>
                                        <option value="7">7th</option>
                                        <option value="8">8th</option>
                                        <option value="9">9th</option>
                                        <option value="10">10th</option>
                                        <option value="11">11th</option>
                                        <option value="12">12th</option>
                                        <option value="13">13th</option>
                                        <option value="14">14th</option>
                                        <option value="15">15th</option>
                                        <option value="16">16th</option>
                                        <option value="17">17th</option>
                                        <option value="18">18th</option>
                                        <option value="19">19th</option>
                                        <option value="20">20th</option>
                                        <option value="21">21st</option>
                                        <option value="22">22nd</option>
                                        <option value="23">23rd</option>
                                        <option value="24">24th</option>
                                        <option value="25">25th</option>
                                        <option value="26">26th</option>
                                        <option value="27">27th</option>
                                        <option value="28">28th</option>
                                        <option value="29">29th</option>
                                        <option value="30">30th</option>
                                        <option value="31">31st</option>
                                        <option value="-">Last</option>
                                    </select></td>
                                <td><select class='recurring-event-field weekday-select' id='weekday_4' name='weekday_4'>
                                        <option value="">Day of the Month</option>
                                        <option value="SUN">Sunday</option>
                                        <option value="MON">Monday</option>
                                        <option value="TUE">Tuesday</option>
                                        <option value="WED">Wednesday</option>
                                        <option value="THU">Thursday</option>
                                        <option value="FRI">Friday</option>
                                        <option value="SAT">Saturday</option>
                                    </select></td>
                            </tr>
                            <tr class="byday-monthly-row">
                                <td><select class='recurring-event-field ordinal-day' id='ordinal_day_5' name='ordinal_day_5'>
                                        <option value="">[Select]</option>
                                        <option value="1">1st</option>
                                        <option value="2">2nd</option>
                                        <option value="3">3rd</option>
                                        <option value="4">4th</option>
                                        <option value="5">5th</option>
                                        <option value="6">6th</option>
                                        <option value="7">7th</option>
                                        <option value="8">8th</option>
                                        <option value="9">9th</option>
                                        <option value="10">10th</option>
                                        <option value="11">11th</option>
                                        <option value="12">12th</option>
                                        <option value="13">13th</option>
                                        <option value="14">14th</option>
                                        <option value="15">15th</option>
                                        <option value="16">16th</option>
                                        <option value="17">17th</option>
                                        <option value="18">18th</option>
                                        <option value="19">19th</option>
                                        <option value="20">20th</option>
                                        <option value="21">21st</option>
                                        <option value="22">22nd</option>
                                        <option value="23">23rd</option>
                                        <option value="24">24th</option>
                                        <option value="25">25th</option>
                                        <option value="26">26th</option>
                                        <option value="27">27th</option>
                                        <option value="28">28th</option>
                                        <option value="29">29th</option>
                                        <option value="30">30th</option>
                                        <option value="31">31st</option>
                                        <option value="-">Last</option>
                                    </select></td>
                                <td><select class='recurring-event-field weekday-select' id='weekday_5' name='weekday_5'>
                                        <option value="">Day of the Month</option>
                                        <option value="SUN">Sunday</option>
                                        <option value="MON">Monday</option>
                                        <option value="TUE">Tuesday</option>
                                        <option value="WED">Wednesday</option>
                                        <option value="THU">Thursday</option>
                                        <option value="FRI">Friday</option>
                                        <option value="SAT">Saturday</option>
                                    </select></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="form-fields">
                <h2>Event Settings</h2>
				<?php
				echo createFormControl("events", "attendees", array("form_label" => "Maximum attendees", "not_null" => true));
				echo createFormControl("events", "category_id", array("form_label" => "Categorize attendees"));
				echo createFormControl("events", "mailing_list_id");
				echo createFormControl("events", "email_id", array("form_label" => "Email notification"));
				echo createFormControl("events", "internal_use_only", array("form_label" => "Event publicly available"));
				?>
                <div class='basic-form-line' id='_create_product_row'>
                    <input tabindex='10' type='checkbox' id='create_product' name='create_product' value='1'><label class='checkbox-label' for='create_product'>Enable shopping cart checkout</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

				<?= createFormControl("product_category_links", "product_category_id",
					array("not_null" => true, "data-conditional-required" => '$("#create_product").prop("checked")', "form_line_classes" => "product-field hidden")) ?>

                <div class='basic-form-line product-field hidden' id='_product_price_row'>
                    <label>Price per person (USD)</label>
                    <input tabindex='10' type='text' id='product_price' name='product_price' class='align-right validate[required,custom[number]]' data-decimal-places="2" data-conditional-required="$('#create_product').prop('checked')" value=''>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class='basic-form-line product-field hidden' id='_expiration_days_row'>
                    <label>Signup allowed until X days before event</label>
                    <span class='help-label'>Zero allows signup until the day of event</span>
                    <input tabindex='10' type='text' id='expiration_days' name='expiration_days' class='validate[required,custom[integer],min[0]]' data-conditional-required="$('#create_product').prop('checked')" value='0'>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

				<?= createFormControl("products", "not_taxable", array("form_line_classes" => "product-field hidden", "initial_value" => !empty(getPreference("EVENTS_NOT_TAXABLE")))) ?>
				<?= createFormControl("products", "tax_rate_id", array("form_line_classes" => "product-field hidden", "help_label" => "Tax rate used for this product", "empty_text" => "[Use Default]")) ?>
				<?= createFormControl("products", "cart_maximum", array("form_line_classes" => "product-field hidden", "form_label" => "Customer purchase limit", "initial_value" => "")) ?>

                <div class="basic-form-line product-field hidden custom-control-form-line custom-control-no-help" id="_related_products_row">
                    <label>Related Products / Events</label>
					<?= $this->getRelatedProductsEditableList()->getControl() ?>
                </div>

                <div class="basic-form-line product-field hidden" id="_product_addon_set_id_row">
                    <label for="product_addon_set_id" class="">Import Product Addon Set</label>
                    <select data-link_url="product-addon-set-maintenance?url_page=new" data-control_code="product_addon_sets"
                            tabindex="10" id="product_addon_set_id" name="product_addon_set_id" class="add-new-option">
						<?php if (empty(getPreference("NO_ADD_NEW_OPTION"))) { ?>
                            <option value="">[Select to Load]</option>
						<?php } ?>
                        <option value="-9999">[Add New]</option>
						<?php
						$resultSet = executeQuery("select * from product_addon_sets where inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['product_addon_set_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line product-field hidden custom-control-no-help custom-control-form-line" id="_product_addons_row">
                    <label>Event add-ons</label>
					<?= $this->getProductAddOnsEditableList()->getControl() ?>
                </div>
            </div>

            <div class="form-fields">
                <h2>Facilities</h2>
                <div class='basic-form-line custom-control-no-help custom-control-form-line'>
                    <label>Facilities List</label>
                    <span class='help-label'>Leave start or end time blank to use overall event time</span>
					<?= $this->getFacilitiesEditableList()->getControl(); ?>
                </div>
            </div>

            <div class='basic-form-line' id='_ignore_facility_usages_row'>
                <p>By default, events will not be created in facilities that have conflicting events. Checking this box will not perform this check and allow multiple events to be booked in the same facility at the same time.</p>
                <input tabindex='10' type='checkbox' id='ignore_facility_usages' name='ignore_facility_usages' value='1'><label class='checkbox-label' for='ignore_facility_usages'>Ignore Facility Usages</label>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>

            <p class='error-message'></p>
            <div class="basic-form-line">
                <button tabindex="10" id="create_events">Publish New Event(s)</button>
            </div>
        </form>

        <div id="_event_published_message" class="hidden">
            <h1>Event has been published</h1>
            <button type="button"><a href="/createevents.php">Create another event</a></button>
            <button type="button"><a href="/event-maintenance">View all events</a></button>
        </div>

		<?php
		echo $this->iPageData['content'];
		return true;
	}

	function getSpecificDatesEditableList() {
		$specificDatesColumn = new DataColumn("specific_dates");
		$specificDatesColumn->setControlValue("data_type", "custom");
		$specificDatesColumn->setControlValue("control_class", "EditableList");
		$specificDates = new EditableList($specificDatesColumn, $this);

		$dateColumn = new DataColumn("date");
		$dateColumn->setControlValue("data_type", "date");
		$dateColumn->setControlValue("form_label", "Date");
		$dateColumn->setControlValue("not_null", true);
		$dateColumn->setControlValue("classes", "template-datepicker");

		$startTimeColumn = new DataColumn("start_time");
		$startTimeColumn->setControlValue("data_type", "time");
		$startTimeColumn->setControlValue("form_label", "Start time");
		$startTimeColumn->setControlValue("not_null", true);

		$endTimeColumn = new DataColumn("end_time");
		$endTimeColumn->setControlValue("data_type", "time");
		$endTimeColumn->setControlValue("form_label", "End time");
		$endTimeColumn->setControlValue("not_null", true);

		$columnList = array("date" => $dateColumn, "start_time" => $startTimeColumn, "end_time" => $endTimeColumn);
		$specificDates->setColumnList($columnList);
		return $specificDates;
	}

	function getRelatedProductsEditableList() {
		$relatedProductsColumn = new DataColumn("related_products");
		$relatedProductsColumn->setControlValue("data_type", "custom");
		$relatedProductsColumn->setControlValue("control_class", "EditableList");
		$relatedProducts = new EditableList($relatedProductsColumn, $this);
		$productId = new DataColumn("product_id");
		$productId->setControlValue("data_type", "autocomplete");
		$productId->setControlValue("data-autocomplete_tag", "products");
		$productId->setControlValue("form_label", "Product");
		$columnList = array("product_id" => $productId);
		$relatedProducts->setColumnList($columnList);
		return $relatedProducts;
	}

	function getProductAddOnsEditableList() {
		$productAddonsColumn = new DataColumn("product_addons");
		$productAddonsColumn->setControlValue("data_type", "custom");
		$productAddonsColumn->setControlValue("control_class", "EditableList");
		$productAddons = new EditableList($productAddonsColumn, $this);
		$descriptionColumn = new DataColumn("description");
		$descriptionColumn->setControlValue("data_type", "varchar");
		$descriptionColumn->setControlValue("form_label", "Description");
		$descriptionColumn->setControlValue("not_null", true);
		$groupDescriptionColumn = new DataColumn("group_description");
		$groupDescriptionColumn->setControlValue("data_type", "varchar");
		$groupDescriptionColumn->setControlValue("form_label", "Group Description");
		$manufacturerSkuColumn = new DataColumn("manufacturer_sku");
		$manufacturerSkuColumn->setControlValue("data_type", "varchar");
		$manufacturerSkuColumn->setControlValue("form_label", "SKU");
		$maximumQuantityColumn = new DataColumn("maximum_quantity");
		$maximumQuantityColumn->setControlValue("data_type", "int");
		$maximumQuantityColumn->setControlValue("form_label", "Max Qty");
		$maximumQuantityColumn->setControlValue("minimum_value", "1");
		$maximumQuantityColumn->setControlValue("not_null", true);
		$salePriceColumn = new DataColumn("sale_price");
		$salePriceColumn->setControlValue("data_type", "decimal");
		$salePriceColumn->setControlValue("decimal_places", "2");
		$salePriceColumn->setControlValue("data_size", "12");
		$salePriceColumn->setControlValue("form_label", "Sale Price");
		$salePriceColumn->setControlValue("not_null", true);
		$sortOrderColumn = new DataColumn("sort_order");
		$sortOrderColumn->setControlValue("data_type", "int");
		$sortOrderColumn->setControlValue("form_label", "Sort Order");
		$sortOrderColumn->setControlValue("not_null", true);
		$formDefinitionIdColumn = new DataColumn("form_definition_id");
		$formDefinitionIdColumn->setControlValue("data_type", "select");
		$formDefinitionIdColumn->setControlValue("form_label", "Form");
		$formDefinitionIdColumn->setControlValue("not_null", false);
		$formDefinitionIdColumn->setControlValue("get_choices", "formChoices");
		$columnList = array("description" => $descriptionColumn, "group_description" => $groupDescriptionColumn, "form_definition_id" => $formDefinitionIdColumn, "sale_price" => $salePriceColumn, "sort_order" => $sortOrderColumn);
		$productAddons->setColumnList($columnList);
		return $productAddons;
	}

	function getFacilitiesEditableList() {
		$facilitiesControlColumn = new DataColumn("event_facilities");
		$facilitiesControlColumn->setControlValue("data_type", "custom");
		$facilitiesControlColumn->setControlValue("control_class", "EditableList");
		$facilitiesControl = new EditableList($facilitiesControlColumn, $this);

		$facilityColumn = new DataColumn("facility_id");
		$facilityColumn->setControlValue("data_type", "select");
		$facilityColumn->setControlValue("form_label", "Facility");
		$facilityColumn->setControlValue("not_null", true);
		$facilityColumn->setControlValue("get_choices", "facilityChoices");

		$startTimeColumn = new DataColumn("start_time");
		$startTimeColumn->setControlValue("data_type", "time");
		$startTimeColumn->setControlValue("form_label", "Start time");
		$startTimeColumn->setControlValue("not_null", false);

		$endTimeColumn = new DataColumn("end_time");
		$endTimeColumn->setControlValue("data_type", "time");
		$endTimeColumn->setControlValue("form_label", "End time");
		$endTimeColumn->setControlValue("not_null", false);

		$columnList = array("date" => $facilityColumn, "start_time" => $startTimeColumn, "end_time" => $endTimeColumn);
		$facilitiesControl->setColumnList($columnList);
		return $facilitiesControl;
	}

	function internalCSS() {
		?>
        <style>
            #bymonth_table td {
                padding-right: 20px;
            }

            #bymonth_row {
                display: none;
            }

            #byday_row {
                display: none;
            }

            #byday_weekly_table {
                display: none;
            }

            #byday_weekly_table td {
                padding-right: 20px;
            }

            #byday_monthly_table {
                display: none;
            }

            #_description_row,
            #_event_type_id_row,
            #_link_name_row,
            #_image_id_row,
            #_recurring_start_date_row,
            #_until_row,
            #_reserve_start_time_row,
            #_reserve_end_time_row,
            #_attendees_row,
            #_category_id_row,
            #_product_price_row,
            #_cart_maximum_row,
            #_tax_rate_id_row {
                display: inline-block;
            }

            #_description_row,
            #_link_name_row,
            #_attendees_row,
            #_tax_rate_id_row,
            #_product_price_row {
                margin-right: 0.5rem;
            }

            #_specific_dates_table,
            #_related_products_table {
                min-width: 400px;
            }

            #_product_addons_table {
                min-width: 600px;
            }

            #date_type_repeating {
                margin-left: 1rem;
            }

            ::placeholder {
                text-align: left;
            }
        </style>
		<?php
	}

	function hiddenElements() {
		?>
        <iframe id="_post_iframe" name="post_iframe"></iframe>
		<?php
	}

	function jqueryTemplates() {
		echo $this->getRelatedProductsEditableList()->getTemplate();
		echo $this->getProductAddOnsEditableList()->getTemplate();
		echo $this->getSpecificDatesEditableList()->getTemplate();
		echo $this->getFacilitiesEditableList()->getTemplate();
	}

}

$pageObject = new CreateEventsPage();
$pageObject->displayPage();
