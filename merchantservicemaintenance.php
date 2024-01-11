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

$GLOBALS['gPageCode'] = "MERCHANTSERVICEMAINT";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 300000;

class MerchantServiceMaintenancePage extends Page {

	protected $iMerchantServicesList = array(
		array('description' => 'Authorize.net', 'class_name' => 'AuthorizeNet', 'sort_order' => 100, "field_labels" => array("account_login" => "API Login ID", "account_key" => "Transaction Key", "merchant_identifier" => "Merchant Number")),
		array('description' => 'USAePay', 'class_name' => 'usaEpay', 'sort_order' => 100, "field_labels" => array("account_login" => "PIN", "account_key" => "Source Key")),
		array('description' => 'NMI', 'class_name' => 'TNBC', 'sort_order' => 100, "field_labels" => array("account_key" => "Security Key", "link_url" => "Link URL"), "not_required" => array("link_url")),
		array('description' => 'Stripe', 'class_name' => 'Stripe', 'sort_order' => 100, "field_labels" => array("account_login" => "Account Login", "account_key" => "Account Key")),
        array('description' => 'CardConnect', 'class_name' => 'CardConnect', 'sort_order' => 100, "field_labels" => array("account_login" => "Username", "account_key" => "Password", "link_url" => "Link URL", "merchant_identifier"=>"Merchant ID"), "not_required" => array("link_url")),
		array('description' => 'CyberSource', 'class_name' => 'Cybersource', 'sort_order' => 100, "field_labels" => array("account_login" => "Organization ID", "account_key" => "Shared Secret", "merchant_identifier" => "Key", "custom_field_signing_key" => "SOAP Toolkit Key")),
		array('description' => 'Blue Dog', 'class_name' => 'BlueDog', 'sort_order' => 100, "field_labels" => array("account_login" => "Account Login", "account_key" => "Account Key")),
		array('description' => 'Transnational Fluid Pay', 'class_name' => 'Transnational', 'sort_order' => 100, "field_labels" => array("account_login" => "Account Login", "account_key" => "Account Key")),
		array('description' => 'Fluid Pay', 'class_name' => 'FluidPay', 'sort_order' => 100, "field_labels" => array("account_login" => "Account Login", "account_key" => "Account Key")),
		array('description' => 'coreCLEAR Quest', 'class_name' => 'Clearent', 'sort_order' => 90, "field_labels" => array("account_login" => "Username", "account_key" => "API Key")),
		array('description' => 'coreCLEAR MX', 'class_name' => 'MxMerchant', 'sort_order' => 90, "field_labels" => array("account_login" => "Consumer API Key", "account_key" => "Consumer API Secret", "merchant_identifier" => "Merchant ID")),
		array('description' => 'coreCLEAR', 'class_name' => 'BlockChyp', 'sort_order' => 80, "field_labels" => array("account_login" => "API Key", "account_key" => "Bearer Token", "custom_field_signing_key" => "Signing Key")),
		array('description' => 'eBizCharge', 'class_name' => 'eBizCharge', 'sort_order' => 100, "field_labels" => array("account_login" => "User ID", "account_key" => "Security ID"))
	);

	function setup() {
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("update_services", "Update supported merchant services");
	}

	function massageDataSource() {
		if ($GLOBALS['gUserRow']['superuser_flag']) {
			$this->iDataSource->addColumnControl("client_count", "data_type", "int");
			$this->iDataSource->addColumnControl("client_count", "form_label", "Clients using service");
			$this->iDataSource->addColumnControl("client_count", "select_value",
				"select count(*) from merchant_accounts where merchant_accounts.merchant_service_id = merchant_services.merchant_service_id and client_id in (select client_id from clients where clients.inactive = 0)");
			$this->iDataSource->addColumnControl("client_count", "readonly", "true");

			$this->iDataSource->addColumnControl("merchant_service_field_labels", "data_type", "custom");
			$this->iDataSource->addColumnControl("merchant_service_field_labels", "form_label", "Field Labels");
			$this->iDataSource->addColumnControl("merchant_service_field_labels", "control_class", "EditableList");
			$this->iDataSource->addColumnControl("merchant_service_field_labels", "list_table", "merchant_service_field_labels");
			$this->iDataSource->addColumnControl("merchant_service_field_labels", "help_label", "If column name isn't specified, default will be used. If form label is empty, column will be hidden.");

			$this->iDataSource->addColumnControl("merchant_accounts", "data_type", "custom");
			$this->iDataSource->addColumnControl("merchant_accounts", "form_label", "Clients");
			$this->iDataSource->addColumnControl("merchant_accounts", "control_class", "EditableList");
			$this->iDataSource->addColumnControl("merchant_accounts", "list_table", "merchant_accounts");
			$this->iDataSource->addColumnControl("merchant_accounts", "foreign_key_field", "merchant_service_id");
			$this->iDataSource->addColumnControl("merchant_accounts", "primary_key_field", "merchant_service_id");
			$this->iDataSource->addColumnControl("merchant_accounts", "column_list", "client_id,merchant_account_code,description");
			$this->iDataSource->addColumnControl("merchant_accounts", "list_table_controls", array(
				"client_id" => array("inline-width" => "200px"),
				"merchant_account_code" => array("inline-width" => "300px"),
				"description" => array("inline-width" => "300px")));
			$this->iDataSource->addColumnControl("merchant_accounts", "readonly", "true");
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "update_services":
				foreach ($this->iMerchantServicesList as $thisService) {
					$merchantServiceId = getFieldFromId("merchant_service_id", "merchant_services", "class_name", $thisService['class_name']);
					if (empty($merchantServiceId)) {
						$resultSet = executeQuery("insert into merchant_services (description, class_name, sort_order) values (?,?,?)",
							$thisService['description'], $thisService['class_name'], $thisService['sort_order']);
						if (!empty($resultSet['sql_error'])) {
							$returnArray['error_message'] = $resultSet['sql_error'];
							break;
						}
						$merchantServiceId = $resultSet['insert_id'];
					} else {
						$resultSet = executeQuery("update merchant_services set description = ?, sort_order = ? where merchant_service_id = ?",
							$thisService['description'], $thisService['sort_order'], $merchantServiceId);
						if (!empty($resultSet['sql_error'])) {
							$returnArray['error_message'] = $resultSet['sql_error'];
							break;
						}
					}
                    executeQuery("delete from merchant_service_field_labels where merchant_service_id = ?",$merchantServiceId);
					foreach ($thisService['field_labels'] as $thisColumnName => $thisLabel) {
						$fieldLabelId = getFieldFromId("merchant_service_field_label_id", "merchant_service_field_labels", "column_name", $thisColumnName,
							"merchant_service_id = ?", $merchantServiceId);
                        $notNull = 1;
                        if (array_key_exists("not_required",$thisService) && in_array($thisColumnName,$thisService['not_required'])) {
                            $notNull = 0;
                        }
						if (empty($fieldLabelId)) {
							executeQuery("insert into merchant_service_field_labels (merchant_service_id, column_name, form_label, not_null) values (?,?,?,?)",
								$merchantServiceId, $thisColumnName, $thisLabel, $notNull);
						} else {
							executeQuery("update merchant_service_field_labels set form_label = ?, not_null = ? where merchant_service_field_label_id = ?", $thisLabel, $notNull, $fieldLabelId);
						}
					}
				}

				$returnArray['info_message'] = "Merchant Services list updated successfully.";
				ajaxResponse($returnArray);
				break;
		}
	}

	function afterGetRecord(&$returnArray) {
		if ($GLOBALS['gUserRow']['superuser_flag']) {
			$returnArray['merchant_accounts'] = array();
			$resultSet = executeQuery("select * from merchant_accounts where merchant_service_id = ? and client_id in (select client_id from clients where clients.inactive = 0)",
				$returnArray['primary_id']['data_value']);
			while ($row = getNextRow($resultSet)) {
				$returnArray['merchant_accounts'][] = array(
					"merchant_account_id" => array("data_value" => $row['merchant_account_id']),
					"client_id" => array("data_value" => $row['client_id']),
					"merchant_account_code" => array("data_value" => $row['merchant_account_code']),
					"description" => array("data_value" => $row['description']),
					"merchant_service_id" => array("data_value" => $row['merchant_service_id']));
			}
		}
	}

	function javascript() {
		?>
        <script>
            function customActions(actionName) {
                if (actionName === "update_services") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=" + actionName, function (returnArray) {
                        getDataList();
                    });
                    return true;
                }
            }
        </script>
		<?php
	}
}

$pageObject = new MerchantServiceMaintenancePage("merchant_services");
$pageObject->displayPage();
