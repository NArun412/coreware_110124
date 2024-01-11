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

$GLOBALS['gPageCode'] = "ORDERSTATUSMAINT";
require_once "shared/startup.inc";

class OrderStatusMaintenancePage extends Page {

	var $iPlaceHolders = array();

	function setup() {
		$this->iPlaceHolders = array();
		$this->iPlaceHolders['contact_id'] = "";
		$this->iPlaceHolders['first_name'] = "";
		$this->iPlaceHolders['middle_name'] = "";
		$this->iPlaceHolders['last_name'] = "";
		$this->iPlaceHolders['business_name'] = "";
		$this->iPlaceHolders['address_1'] = "";
		$this->iPlaceHolders['address_2'] = "";
		$this->iPlaceHolders['city'] = "";
		$this->iPlaceHolders['state'] = "";
		$this->iPlaceHolders['postal_code'] = "";
		$this->iPlaceHolders['email_address'] = "";
		$this->iPlaceHolders['order_id'] = "";
		$this->iPlaceHolders['order_number'] = "";
		$this->iPlaceHolders['full_name'] = "";
		$this->iPlaceHolders['order_total'] = "";
		$this->iPlaceHolders['order_items_quantity'] = "";
		$this->iPlaceHolders['order_items'] = "";
		$this->iPlaceHolders['order_items_table'] = "";
		$this->iPlaceHolders['business_address'] = "";
		$this->iPlaceHolders['phone_number'] = "";
		$this->iPlaceHolders['order_time'] = "";
		$this->iPlaceHolders['date_completed'] = "";
		$this->iPlaceHolders['gift_text'] = "";
		$this->iPlaceHolders['shipping_charge'] = "";
		$this->iPlaceHolders['tax_charge'] = "";
		$this->iPlaceHolders['handling_charge'] = "";
		$this->iPlaceHolders['order_discount'] = "";
		$this->iPlaceHolders['cart_total'] = "";
		$this->iPlaceHolders['shipping_address_block'] = "";
		$this->iPlaceHolders['order_date'] = "";
		$this->iPlaceHolders['ffl_name'] = "";
		$this->iPlaceHolders['store_name'] = "The FFL receiving the order";
		$this->iPlaceHolders['ffl_phone_number'] = "";
		$this->iPlaceHolders['ffl_license_number'] = "";
		$this->iPlaceHolders['ffl_license_number_masked'] = "";
		$this->iPlaceHolders['ffl_address'] = "";
		$this->iPlaceHolders['store_address'] = "";
		$this->iPlaceHolders['domain_name'] = "";
		$this->iPlaceHolders['shipping_method'] = "";
		$this->iPlaceHolders['location'] = "The name of the pickup location";
		$this->iPlaceHolders['location_address_block'] = "Address of the pickup location";
        $globalPlaceholders = PlaceHolders::getGlobalPlaceholders();
        foreach ($globalPlaceholders as $placeholder) {
            if (!array_key_exists($placeholder,$this->iPlaceHolders) && !startsWith($placeholder, "user")) {
                $this->iPlaceHolders[$placeholder] = "";
            }
        }
        ksort($this->iPlaceHolders);
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("email_id", "help_label", "IMPORTANT NOTE: not all substitution values are available when changing order status. <a class='valid-fields-trigger'>View valid placeholders</a>");
		$this->iDataSource->addColumnControl("display_color", "classes", "minicolors");
		$this->iDataSource->addColumnControl("order_status_notifications", "data_type", "custom");
		$this->iDataSource->addColumnControl("order_status_notifications", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("order_status_notifications", "form_label", "Notifications");
		$this->iDataSource->addColumnControl("order_status_notifications", "list_table", "order_status_notifications");
		$this->iDataSource->addColumnControl("resend_days", "help_label", "Email will be resent after this number of days if the status isn't changed. Set to zero to ignore.");
    }

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "check_email_content":
				$content = getFieldFromId("content", "emails", "email_id", $_GET['email_id']);
				$placeholders = explode("%", $content);
				$invalidCharacters = array(" ", "\r", "/", "-");
				$invalidPlaceHolders = array();
				foreach ($placeholders as $thisPlaceholder) {
					if (strlen($thisPlaceholder) > 50 || strlen($thisPlaceholder) < 5) {
						continue;
					}
					foreach ($invalidCharacters as $thisChar) {
						if (strpos($thisPlaceholder, $thisChar) !== false) {
							continue 2;
						}
					}
					if (!array_key_exists($thisPlaceholder, $this->iPlaceHolders) && !in_array($thisPlaceholder,$invalidPlaceHolders)) {
						$invalidPlaceHolders[] = $thisPlaceholder;
					}
				}
				if (!empty($invalidPlaceHolders)) {
                    sort($invalidPlaceHolders);
					$returnArray['invalid_placeholders'] = "<p>This email appears to contain invalid placeholders, including:</p><ul><li>" . implode("</li><li>", $invalidPlaceHolders) . "</li></ul>";
				}
				ajaxResponse($returnArray);
				exit;
		}
	}

    function afterGetRecord(&$returnArray) {
        $customFields = CustomField::getCustomFields("order_status");
        foreach ($customFields as $thisCustomField) {
            $customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
            $customFieldData = $customField->getRecord($returnArray['primary_id']['data_value']);
            if (array_key_exists("select_values", $returnArray) && array_key_exists("select_values", $customFieldData)) {
                $returnArray['select_values'] = $customFieldData['select_values'] = array_merge($returnArray['select_values'], $customFieldData['select_values']);
            }
            $returnArray = array_merge($returnArray, $customFieldData);
        }
    }

    function afterSaveChanges($nameValues) {
        $customFields = CustomField::getCustomFields("order_status");
        foreach ($customFields as $thisCustomField) {
            $customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
            if (!$customField->saveData($nameValues)) {
                return $customField->getErrorMessage();
            }
        }
        return true;
    }

    function addCustomFields() {
        $customFields = CustomField::getCustomFields("order_status");
        foreach ($customFields as $thisCustomField) {
            $customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
            echo $customField->getControl(array("basic_form_line" => true));
        }
    }

	function onLoadJavascript() {
		?>
        <script>
            $(function () {
                $("#email_id").change(function () {
                    if (!empty($(this).val())) {
                        loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=check_email_content&email_id=" + $(this).val(), function (returnArray) {
                            if ("invalid_placeholders" in returnArray) {
                                $("#_invalid_placeholders_dialog").html(returnArray['invalid_placeholders']);
                                $('#_invalid_placeholders_dialog').dialog({
                                    closeOnEscape: true,
                                    draggable: false,
                                    modal: true,
                                    resizable: false,
                                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                                    width: 700,
                                    title: 'Invalid Placeholders',
                                    buttons: [
                                        {
                                            text: "View Valid Placeholders",
                                            click: function () {
                                                setTimeout(function() {
                                                    $(".valid-fields-trigger").trigger("click");
                                                },100);
                                                $("#_invalid_placeholders_dialog").dialog('close');
                                            }
                                        },
                                        {
                                            text: "Close",
                                            click: function () {
                                                $("#_invalid_placeholders_dialog").dialog('close');
                                            }
                                        }
                                    ]
                                });
                            }
                        });
                    }
                });
                $(document).on("tap click", ".valid-fields-trigger", function () {
                    $("#_valid_fields_dialog").dialog({
                        modal: true,
                        resizable: true,
                        width: 1000,
                        title: 'Valid Substitutions for Order Status emails',
                        buttons: {
                            Close: function (event) {
                                $("#_valid_fields_dialog").dialog('close');
                            }
                        }
                    });
                });
            });
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #_valid_fields_dialog > ul {
                columns: 3;
                padding-bottom: 1rem;
            }

            #_valid_fields_dialog ul li {
                padding-right: 20px;
            }
        </style>
		<?php
	}

	function hiddenElements() {
		?>
        <div id="_valid_fields_dialog" title="Valid Substitutions" class="dialog-box">
            <p class='red-text'>ONLY these placeholders will be used in emails sent due to order status change. Specifically, because it has come up so many times in the past, TRACKING information for order shipments cannot be included in these emails.</p>
            <ul>
				<?php
				foreach ($this->iPlaceHolders as $placeholder => $description) {
					echo "<li>" . $placeholder . (empty($description) ? "" : " - " . $description) . "</li>";
				}
				?>
            </ul>
        </div>
        <div id="_invalid_placeholders_dialog" class="dialog-box">
        </div>
		<?php
	}
}

$pageObject = new OrderStatusMaintenancePage("order_status");
$pageObject->displayPage();
