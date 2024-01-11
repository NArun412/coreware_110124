<?php

/*      This software is the unpublished, confidential, proprietary, intellectual
        property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
        or used in any manner without expressed written consent from Kim David Software, LLC.
        Kim David Software, LLC owns all rights to this work and intends to keep this
        software confidential so as to maintain its value as a trade secret.

        Copyright 2004-Present, Kim David Software, LLC.
*/

$GLOBALS['gPageCode'] = "INVOICEMAINT";
require_once "shared/startup.inc";

class ThisPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_rate":
				$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $_GET['contact_id'], "client_id = ?", $GLOBALS['gClientId']);
				if ($contactId) {
					$hourlyRate = getFieldFromId("number_data", "custom_field_data", "primary_identifier", $contactId,
						"custom_field_id = (select custom_field_id from custom_fields where custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = 'CONTACTS') and client_id = ? and custom_field_code = 'HOURLY_RATE')",
						$GLOBALS['gClientId']);
					if ($hourlyRate) {
						$returnArray['import_unit_price'] = $hourlyRate;
					}
				}
				ajaxResponse($returnArray);
				break;
			case "totalinvoices":
				$totalAmount = 0;
				$resultSet = executeQuery("select sum(amount * unit_price) total_invoices from invoice_details where invoice_id in (" .
					"select invoice_id from invoices where date_completed is null and client_id = ?)", $GLOBALS['gClientId']);
				if ($row = getNextRow($resultSet)) {
					$totalAmount = $row['total_invoices'];
				}
				$returnArray['info_message'] = "Total of unpaid invoices is " . number_format($totalAmount, 2, ".", ",");
				ajaxResponse($returnArray);
				break;
			case "import_time":
				$timeArray = array();
				if (empty($_POST['import_end_date'])) {
					$_POST['import_end_date'] = date("m/d/Y");
				}
				$resultSet = executeQuery("select * from time_log where total_hours is not null and log_date between ? and ? and contact_id = ? and user_id = ?",
					date("Y-m-d", strtotime($_POST['import_start_date'])), date("Y-m-d", strtotime($_POST['import_end_date'])), $_GET['contact_id'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$thisArray = array();
					$thisArray['detail_date'] = array("data_value" => date("m/d/Y", strtotime($row['log_date'])));
					$thisArray['description'] = array("data_value" => $row['description']);
					$thisArray['amount'] = array("data_value" => $row['total_hours']);
					$thisArray['unit_id'] = array("data_value" => $_POST['import_unit_id']);
					$thisArray['unit_price'] = array("data_value" => $_POST['import_unit_price']);
					$timeArray[] = $thisArray;
				}
				$returnArray['invoice_details'] = $timeArray;
				ajaxResponse($returnArray);
				break;
		}
	}

	function contactPresets() {
		$resultSet = executeQuery("select contact_id,address_1,state,city,email_address from contacts where deleted = 0 and client_id = ? " .
			"and contact_id in (select contact_id from invoices where client_id = ? and invoice_date > DATE_SUB(NOW(),INTERVAL 1 YEAR)) order by business_name,first_name,last_name",
			$GLOBALS['gClientId'], $GLOBALS['gClientId']);
		$contactList = array();
		while ($row = getNextRow($resultSet)) {
			$description = getDisplayName($row['contact_id'], array("include_company" => true, "prepend_company" => true));
			if (!empty($row['address_1'])) {
				if (!empty($description)) {
					$description .= " &bull; ";
				}
				$description .= $row['address_1'];
			}
			if (!empty($row['state'])) {
				if (!empty($row['city'])) {
					$row['city'] .= ", ";
				}
				$row['city'] .= $row['state'];
			}
			if (!empty($row['city'])) {
				if (!empty($description)) {
					$description .= " &bull; ";
				}
				$description .= $row['city'];
			}
			if (!empty($row['email_address'])) {
				if (!empty($description)) {
					$description .= " &bull; ";
				}
				$description .= $row['email_address'];
			}
			$contactList[$row['contact_id']] = $description;
		}
		asort($contactList);
		return $contactList;
	}

	function setup() {
		$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => "contacts", "referenced_column_name" => "contact_id",
			"foreign_key" => "contact_id", "description" => "business_name"));
		$this->iDataSource->getPrimaryTable()->setSubtables("invoice_details", "invoice_payments");
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addExcludeFormColumn(array("first_name", "last_name", "business_name"));
			$filters = array();
			$filters['hide_paid'] = array("form_label" => "Hide Paid", "where" => "date_completed is null", "data_type" => "tinyint", "conjunction" => "and", "set_default" => true);
            $filters['start_date_due'] = array("form_label" => "Start Date Due", "where" => "date_due is not null and date_due >= '%filter_value%'", "data_type" => "date", "conjunction" => "and");
            $filters['end_date_due'] = array("form_label" => "End Date Due", "where" => "date_due is not null and date_due <= '%filter_value%'", "data_type" => "date", "conjunction" => "and");
            $filters['payment_method_header'] = array("form_label" => "Used with Payment Method", "data_type" => "header");
            $resultSet = executeQuery("select distinct payment_methods.payment_method_id, payment_methods.description from payment_methods join order_payments using (payment_method_id) where invoice_id is not null and order_id in (select order_id from orders where client_id = ?) order by sort_order,description", $GLOBALS['gClientId']);
            while ($row = getNextRow($resultSet)) {
                $filters['payment_method_id_' . $row['payment_method_id']] = array("form_label" => $row['description'], "where" => "invoice_id in (select invoice_id from order_payments where payment_method_id = " . $row['payment_method_id'] . ")", "data_type" => "tinyint");
            }

			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
			$this->iTemplateObject->getTableEditorObject()->addCustomAction("totalinvoices", "Total Unpaid Invoices");
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("invoice_number", "form_label", "External Invoice Number");
		$this->iDataSource->addColumnControl("invoice_payments", "data_type", "custom");
		$this->iDataSource->addColumnControl("invoice_payments", "control_class", "FormList");
		$this->iDataSource->addColumnControl("invoice_payments", "list_table", "invoice_payments");
		$this->iDataSource->addColumnControl("invoice_payments", "form_label", "Payments");
		$this->iDataSource->addColumnControl("invoice_payments", "column_list", array("payment_date", "payment_method_id", "reference_number", "amount", "donation_id", "notes"));
		$this->iDataSource->addColumnControl("invoice_payments", "list_table_controls", array('donation_id' => array('form_label' => "Donation ID", 'data_type' => 'varchar', 'inline-width' => '120px', "classes" => "align-right", 'readonly' => 'true')));

		$this->iDataSource->addColumnControl("contact_id", "not_editable", true);
		$this->iDataSource->addColumnControl("designation_id", "help_label", "If this invoice is for a donation, set the designation.");
		$this->iDataSource->addColumnControl("designation_id", "not_editable", true);

		$this->iDataSource->addColumnControl("invoice_link", "data_type", "varchar");
		$this->iDataSource->addColumnControl("invoice_link", "form_label", "Payment link for contact");
		$this->iDataSource->addColumnControl("invoice_link", "readonly", true);
		$this->iDataSource->addColumnControl("invoice_link", "inline-width", "800px");

		$this->iDataSource->addColumnControl("invoice_total", "form_label", "Invoice Total");
		$this->iDataSource->addColumnControl("invoice_total", "data_type", "decimal");
		$this->iDataSource->addColumnControl("invoice_total", "decimal_places", "2");
		$this->iDataSource->addColumnControl("invoice_total", "readonly", true);
		$this->iDataSource->addColumnControl("invoice_total", "select_value", "select sum(amount * unit_price) from invoice_details where invoice_id = invoices.invoice_id");

		$this->iDataSource->addColumnControl("payment_total", "form_label", "Total Payments");
		$this->iDataSource->addColumnControl("payment_total", "data_type", "decimal");
		$this->iDataSource->addColumnControl("payment_total", "decimal_places", "2");
		$this->iDataSource->addColumnControl("payment_total", "readonly", true);
		$this->iDataSource->addColumnControl("payment_total", "select_value", "coalesce((select sum(amount) from invoice_payments where invoice_id = invoices.invoice_id),0)");

		$this->iDataSource->addColumnControl("balance_due", "form_label", "Balance Due");
		$this->iDataSource->addColumnControl("balance_due", "data_type", "decimal");
		$this->iDataSource->addColumnControl("balance_due", "decimal_places", "2");
		$this->iDataSource->addColumnControl("balance_due", "readonly", true);
		$this->iDataSource->addColumnControl("balance_due", "select_value", "coalesce((select sum(amount * unit_price) from invoice_details where invoice_id = invoices.invoice_id),0) - coalesce((select sum(amount) from invoice_payments where invoice_id = invoices.invoice_id),0)");

		$this->iDataSource->addColumnControl("first_name", "data_type", "varchar");
		$this->iDataSource->addColumnControl("first_name", "form_label", "First Name");
		$this->iDataSource->addColumnControl("first_name", "select_value", "select first_name from contacts where contact_id = invoices.contact_id");
		$this->iDataSource->addColumnControl("last_name", "data_type", "varchar");
		$this->iDataSource->addColumnControl("last_name", "form_label", "Last Name");
		$this->iDataSource->addColumnControl("last_name", "select_value", "select last_name from contacts where contact_id = invoices.contact_id");
		$this->iDataSource->addColumnControl("business_name", "data_type", "varchar");
		$this->iDataSource->addColumnControl("business_name", "form_label", "Business Name");
		$this->iDataSource->addColumnControl("business_name", "select_value", "select business_name from contacts where contact_id = invoices.contact_id");

		$this->iDataSource->addColumnControl("print_invoice", "button_label", "Print");
		$this->iDataSource->addColumnControl("print_invoice", "data_type", "button");
		$this->iDataSource->addColumnControl("email_invoice", "button_label", "Email");
		$this->iDataSource->addColumnControl("email_invoice", "data_type", "button");
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#import_button").click(function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_rate&contact_id=" + $("#contact_id").val(), function (returnArray) {
                    if ("import_unit_price" in returnArray) {
                        $("#import_unit_price").val(returnArray['import_unit_price']);
                    }
                });
                $('#_import_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 400,
                    title: 'Import from Time Log',
                    buttons: {
                        Save: function (event) {
                            if ($("#_import_form").validationEngine('validate')) {
                                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=import_time&contact_id=" + $("#contact_id").val(), $("#_import_form").serialize(), function (returnArray) {
                                    if ("invoice_details" in returnArray) {
                                        for (var i in returnArray['invoice_details']) {
                                            var rowNumber = addEditableListRow("invoice_details", returnArray['invoice_details'][i]);
                                            $("#invoice_details_detail_date-" + rowNumber).trigger("blur");
                                            $("#invoice_details_amount-" + rowNumber).trigger("blur");
                                            $("#invoice_details_unit_price-" + rowNumber).trigger("blur");
                                        }
                                    }
                                });
                                $("#_import_dialog").dialog('close');
                            }
                        },
                        Cancel: function (event) {
                            $("#_import_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
            $(document).on("blur", "input[name^=invoice_details_amount]", function () {
                totalInvoice();
            });
            $(document).on("blur", "input[name^=invoice_details_unit_price]", function () {
                totalInvoice();
            });
            $(document).on("blur", "input[name^=invoice_payments_amount]", function () {
                totalInvoice();
            });
            $("#print_invoice").click(function () {
                if (changesMade()) {
                    saveChanges(function () {
                        $("#print_invoice").data("primary_id", $("#primary_id").val());
                        getRecord($("#primary_id").val());
                        $("#_save_button").button("enable");
                    }, function () {
                        $("#_save_button").button("enable");
                    });
                } else {
                    document.location = "/printinvoice.php?invoice_id=" + $("#primary_id").val();
                }
                return false;
            });
            $("#email_invoice").click(function () {
                if (changesMade()) {
                    saveChanges(function () {
                        $("#email_invoice").data("primary_id", $("#primary_id").val());
                        getRecord($("#primary_id").val());
                        $("#_save_button").button("enable");
                    }, function () {
                        $("#_save_button").button("enable");
                    });
                } else {
                    window.open("/printinvoice.php?send_email=true&invoice_id=" + $("#primary_id").val());
                }
                return false;
            });

        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function customActions(actionName) {
                if (actionName == "totalinvoices") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?url_action=totalinvoices");
                    return true;
                }
                return false;
            }

            function totalInvoice() {
                var invoiceTotal = 0;
                $("#_invoice_details_table tr").each(function () {
                    var rowId = $(this).data("row_id");
                    var amount = $("#invoice_details_amount-" + rowId).val();
                    if (amount == "" || amount == undefined) {
                        amount = 0;
                    }
                    var unitPrice = $("#invoice_details_unit_price-" + rowId).val();
                    if (unitPrice == "" || unitPrice == undefined) {
                        unitPrice = 0;
                    }
                    var lineAmount = 0;
                    try {
                        lineAmount = parseFloat(amount) * parseFloat(unitPrice);
                    } catch (e) {
                        lineAmount = 0;
                    }
                    if (lineAmount == "" || isNaN(lineAmount) || lineAmount == undefined) {
                        lineAmount = 0;
                    }
                    invoiceTotal += parseFloat(lineAmount);
                });

                var paymentTotal = 0;
                $("#_invoice_payments_form_list").find(".form-list-item").each(function () {
                    const rowId = $(this).data("row_id");
                    let thisPaymentAmount = $("#invoice_payments_amount-" + rowId).val();
                    if (empty(thisPaymentAmount)) {
                        thisPaymentAmount = 0;
                    }
                    paymentTotal += parseFloat(thisPaymentAmount);
                });
                $("#invoice_total").val(RoundFixed(invoiceTotal, 2));
                $("#payment_total").val(RoundFixed(paymentTotal, 2));
                $("#balance_due").val(RoundFixed(invoiceTotal - paymentTotal, 2));
            }

            function afterGetRecord() {
                totalInvoice();
                var printPrimaryId = $("#print_invoice").data("primary_id");
                $("#print_invoice").data("primary_id", "");
                if (printPrimaryId != "" && printPrimaryId == $("#primary_id").val()) {
                    $("#print_invoice").trigger("click");
                }
                var emailPrimaryId = $("#email_invoice").data("primary_id");
                $("#email_invoice").data("primary_id", "");
                if (emailPrimaryId != "" && emailPrimaryId == $("#primary_id").val()) {
                    $("#email_invoice").trigger("click");
                }

            }
        </script>
		<?php
	}

	function afterGetRecord(&$returnArray) {
		$linkName = getFieldFromId("link_name", "pages", "script_filename", "invoicepayments.php", "inactive = 0");
		$hashCode = getFieldFromId("hash_code", "contacts", "contact_id", $returnArray['contact_id']['data_value']);
		if (empty($hashCode)) {
			$hashCode = getRandomString();
			executeQuery("update contacts set hash_code = ? where contact_id = ?", $hashCode, $returnArray['contact_id']['data_value']);
		}
		if (empty($hashCode) || empty($linkName) || empty($returnArray['primary_id']['data_value'])) {
			$returnArray['invoice_link'] = array("data_value" => "");
		} else {
			$domainName = getDomainName();
			$returnArray['invoice_link'] = array("data_value" => $domainName . "/" . $linkName . "?code=" . $hashCode);
		}
	}

	function hiddenElements() {
		?>
        <div id="_import_dialog" class="dialog-box">
            <form id="_import_form">
                <table>
                    <tr>
                        <td class="field-label"><label for="import_start_date">Start Date</label></td>
                        <td class="field-text"><input type="text" class="field-text validate[required,custom[date]]" size="12" id="import_start_date" name="import_start_date"/></td>
                    </tr>
                    <tr>
                        <td class="field-label"><label for="import_end_date">End Date</label></td>
                        <td class="field-text"><input type="text" class="field-text validate[custom[date]]" size="12" id="import_end_date" name="import_end_date"/></td>
                    </tr>
                    <tr>
                        <td class="field-label"><label for="import_unit_id">Unit</label></td>
                        <td class="field-text"><select id="import_unit_id" name="import_unit_id" class="field-text">
                                <option value="">[None]</option>
								<?php
								$resultSet = executeQuery("select * from units where inactive = 0 and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
								$firstOne = true;
								while ($row = getNextRow($resultSet)) {
									?>
                                    <option<?= ($firstOne ? " selected" : "") ?> value="<?= $row['unit_id'] ?>"><?= htmlText($row['description']) ?></option>
									<?php
									$firstOne = false;
								}
								?>
                            </select></td>
                    </tr>
                    <tr>
                        <td class="field-label"><label for="import_unit_price">Unit Price</label></td>
                        <td class="field-text"><input type="text" size="8" id="import_unit_price" class="validate[required,custom[number]]" data-decimal-places="2" name="import_unit_price"/></td>
                    </tr>
                </table>
            </form>
        </div>
		<?php
	}

	function internalCSS() {
		?>
        #_import_form { margin-top: 30px; }
		<?php
	}

	function afterSaveDone($nameValues) {
		Invoices::postPaymentInvoiceProcessing($nameValues['primary_id']);
		return true;
	}
}

$pageObject = new ThisPage("invoices");
$pageObject->displayPage();
