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

$GLOBALS['gPageCode'] = "BOILERPLATE";
require_once "shared/startup.inc";

/**
 * Class BoilerPlatePage
 *
 * This class is a "stub" for any admin page. None of the functions are required, but each can be included to alter
 * the default Coreware functionality.
 *
 */
class BoilerPlatePage extends Page {

	/**
	 * The setup function is the first thing that is called when the template begins to load the page. The DataSource object has already been created,
	 * so actions can be taken on it. The typical things that would be done in this function are setting controls in the TableEditor object, setting class variables,
	 * and adding filters to the page.
	 *
	 * @return bool|void
	 */
	function setup() {
		$filters['hide_completed'] = array("form_label" => "Hide Completed", "where" => "date_completed is null", "data_type" => "tinyint", "set_default" => true);
		$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
		$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("add"));
		$this->iTemplateObject->getTableEditorObject()->setFormSortOrder(array("search_term", "search_term_synonym_redirects", "search_term_synonym_product_categories", "search_term_synonym_product_departments", "search_term_synonym_product_manufacturers"));
		$this->iTemplateObject->getTableEditorObject()->addExcludeListColumn(array("search_term"));
		$this->iTemplateObject->getTableEditorObject()->setFileUpload(true);
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("clearlog", "Clear Log");
	}

	/**
	 * This function allows the developer to include stuff in the header just for this page. Most CSS and javascript includes are put into the template. However, there might be
	 * things that the page developer wants to include just on a single page. This is where that is done. Another classic use of this function is custom OG tags based on the specific information
	 * being displayed on the page. The example assumes that specific videos are being displayed.
	 *
	 * @return bool|void
	 */
	function headerIncludes() {
		?>
        <link type="text/css" rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.2.0/fullcalendar.min.css"/>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.17.1/moment.min.js"></script>
        <script src="//cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.2.0/fullcalendar.min.js"></script>
        <link type="text/css" rel="stylesheet" href="<?= autoVersion('/css/fullcalendar.css') ?>"/>
		<?php
		$mediaId = getFieldFromId("media_id", "media", "media_id", $_GET['media_id'],
			"client_id = " . $GLOBALS['gClientId'] . " and video_identifier is not null and inactive = 0 and internal_use_only = 0");
		if (!empty($mediaId)) {
			$mediaSet = executeQuery("select * from media where media_id = ?", $mediaId);
			$mediaRow = getNextRow($mediaSet);
			$description = $mediaRow['detailed_description'];
			if (empty($description)) {
				$description = $mediaRow['subtitle'];
			}
			$urlAliasTypeCode = getUrlAliasTypeCode("media", "media_id");
			$urlLink = "https://" . $_SERVER['HTTP_HOST'] . "/" .
				(empty($urlAliasTypeCode) || empty($mediaRow['link_name']) ? "video.php?media_id=" . $mediaId : $urlAliasTypeCode . "/" . $mediaRow['link_name']);
			$imageUrl = (empty($mediaRow['image_id']) ? "" : "https://" . $_SERVER['HTTP_HOST'] . "/getimage.php?id=" . $mediaRow['image_id']);
			?>
            <meta property="og:title" content="<?= str_replace('"', "'", $mediaRow['description']) ?>"/>
            <meta property="og:type" content="website"/>
            <meta property="og:url" content="<?= $urlLink ?>"/>
            <meta property="og:image" content="<?= $imageUrl ?>"/>
            <meta property="og:description" content="<?= str_replace('"', "'", $description) ?>"/>
			<?php
		}
	}

	/**
	 * Throughout the Coreware system, calls are made using ajax. These calls communicate to the system to do some action. The action is sent in the GET parameter "url_action". The executePageUrlActions
	 * function intercepts and processes these actions. Typically, and almost without exception, the action is processed and a JSON object is returned to the browser.
	 */
	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_action":
				$contacts = array();
				$resultSet = executeQuery("select * from contacts where postal_code = '80920'");
				while ($row = getNextRow($resultSet)) {
					$contacts[] = $row;
				}
				$returnArray['contacts'] = $contacts;
				ajaxResponse($returnArray);
				break;
		}
	}

	/**
	 * The DataSource contains all the information for the database table(s) and columns that are included in the page. This function allows the developer to make
	 * changes to the metadata for both. This would include adding columns/controls that aren't in the table, setting filters for what rows are displayed, setting joined tables,
	 * declaring subtables (for deletes), and making changes to existing columns/controls.
	 *
	 */
	function massageDataSource() {
		$this->iDataSource->getPrimaryTable()->setSubtables(array("pricing_structure_quantity_discounts", "pricing_structure_user_discounts", "pricing_structure_contact_discounts",
			"pricing_structure_order_method_discounts", "pricing_structure_payment_method_discounts"));
		$this->iDataSource->addColumnControl("pricing_structure_quantity_discounts", "list_table_controls", array("user_type_id" => array("empty_text" => "[Any]"), "contact_type_id" => array("empty_text" => "[Any]")));
		$this->iDataSource->addColumnControl("pricing_structure_user_discounts", "list_table_controls", array("user_type_id" => array("empty_text" => "[Any]"), "contact_type_id" => array("empty_text" => "[Any]")));
		$this->iDataSource->addColumnControl("pricing_structure_category_quantity_discounts", "data_type", "custom");
		$this->iDataSource->addColumnControl("pricing_structure_category_quantity_discounts", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("pricing_structure_category_quantity_discounts", "list_table", "pricing_structure_category_quantity_discounts");
		$this->iDataSource->addColumnControl("pricing_structure_category_quantity_discounts", "form_label", "Category Quantity Discounts");
		$this->iDataSource->addColumnControl("percentage", "form_label", "Base Percentage");
		$this->iDataSource->addColumnControl("minimum_markup", "form_label", "Minimum Markup Percentage");
		$this->iDataSource->addColumnControl("minimum_amount", "form_label", "Minimum Markup Amount");
		$this->iDataSource->addColumnControl("maximum_discount", "form_label", "Maximum Discount Percentage");
		$this->iDataSource->addFilterWhere("date_submitted is not null");
	}

	/**
	 * A simple function that is called early by the Template object so that the developer can make changes to the URL parameters in order to force specific behavior.
	 */
	function massageUrlParameters() {
		$_GET['url_subpage'] = $_GET['url_page'];
		$_GET['url_page'] = "show";
		$_GET['primary_id'] = $GLOBALS['gClientRow']['contact_id'];
	}

	/**
	 * Javascript code that will be wrapped in the JQuery "ready" function and loaded once the page is loaded. The return of this function is important. If the function returns true,
	 * that communicates to Coreware that everything needed for onLoadJavascript is done and any default code by the template should be ignored. This is NOT typical behavior. If false is returned
	 * or there is no return, the template adds its onLoadJavascript code as well.
	 *
	 * @return bool|void
	 */
	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", "#click_button", function () {
                $("#_preference_dialog").dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 600,
                    title: 'Preferences',
                    buttons: {
                        Save: function (event) {
                            if ($("#_preference_form").validationEngine('validate')) {
                                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_preferences", $("#_preference_form").serialize(), function(returnArray) {
                                    getContents();
                                });
                                $("#_preference_dialog").dialog('close');
                            }
                        },
                        Cancel: function (event) {
                            $("#_preference_dialog").dialog('close');
                        }
                    }
                });
            });
        </script>
		<?php
	}

	/**
	 * Javascript functions are typically put into this function. As with onLoadJavascript, the return determines whether the template adds default javascript or not.
	 *
	 * @return bool|void
	 */
	function javascript() {
		?>
        <script>
            let selectedInvoicesTotal = 0;

            function calculateAmount() {
                if (!$("#full_amount").prop("checked")) {
                    return;
                }
                let totalAmount = 0;
                $("#invoice_list").find(".invoice-amount").each(function () {
                    if ($(this).closest("tr").find(".pay-now").prop("checked")) {
                        totalAmount = Round(totalAmount + parseFloat($(this).html().replace(/,/g, "")), 2);
                    }
                });
                selectedInvoicesTotal = totalAmount;
                $("#amount").val(RoundFixed(totalAmount, 2));
            }
        </script>
		<?php
	}

	/**
	 * Normal behavior for Coreware is that javascript code in the previous two functions is combined with other javascript code, minified and put into an external file, to
	 * improve performance and take advantage of browser caching. However, there are times when the developer does not want this behavior. An example would be javascript code
	 * that includes JSON objects of data that change with almost every load of the page. This would end up creating innumerable external files and put unnecessary load on the system.
	 * Coreware provides the inlineJavascript function that will force the enclosed Javascript code to be included in the HTML of the page.
	 */
	function inlineJavascript() {
		$products = array();
		$resultSet = executeQuery("select * from products where inactive = 0");
		while ($row = getNextRow($resultSet)) {
			$products[] = $row;
		}
		?>
        <script>
            var products = <?= jsonEncode($products) ?>;
        </script>
		<?php
	}

	/**
	 * @return bool|void
	 */
	function internalCSS() {
		?>
        <style>
            #div_element {
                background-color: rgb(13, 48, 29);
            }
        </style>
		<?php
	}

	/**
	 * @return bool
	 */
	function mainContent() {
		echo $this->iPageData['content'];
		return true;
	}

	/**
	 * If this function exists, the list of records will be run through it before being displayed on the screen.
	 * @param $dataList
	 */
	function dataListProcessing(&$dataList) {
		foreach ($dataList as $index => $row) {
			if ($row['hide_system_value']) {
				$dataList[$index]['system_value'] = "SYSTEM VALUE NOT DISPLAYED";
			} else {
				$clientValue = getFieldFromId("preference_value", "client_preferences", "preference_id", $row['preference_id'],
					"client_id = ?", $GLOBALS['gClientId']);
				if (!empty($clientValue)) {
					$dataList[$index]['system_value'] = $clientValue;
				}
			}
		}
	}

	/**
	 * This function allows the code to massage the POST variables before the save function is called by Coreware. Notice the "&" on the parameter. This means
	 * that the parameter is passed as a pointer, so changes to the parameter will be seen by the calling function. The return on this function is important. Only
	 * if the function returns true will Coreware continue the save. A return of false means the save failed and a generic error message will be returned to the
	 * user. Any text that is returned will also mean failure and the text will be used as the error message.
	 *
	 * @param $nameValues
	 * @return bool|string
	 */
	function beforeSaveChanges(&$nameValues) {
		if (strlen($nameValues['license_number']) == 15) {
			$nameValues['license_number'] = substr($nameValues['license_number'], 0, 1) . "-" . substr($nameValues['license_number'], 1, 2) . "-" . substr($nameValues['license_number'], 3, 3) . "-" . substr($nameValues['license_number'], 6, 2) . "-" . substr($nameValues['license_number'], 8, 2) . "-" . substr($nameValues['license_number'], 10, 5);
		}
		if (strlen($nameValues['license_number']) != 20) {
			return "Invalid License Number";
		}
		$nameValues['license_number'] = strtoupper($nameValues['license_number']);
		$nameValues['license_lookup'] = substr($nameValues['license_number'], 0, 5) . substr($nameValues['license_number'], 15, 5);
		return true;
	}


	/**
	 * JQuery templates are chunks of code that the developer include in the DOM to copy and duplicate. For instance, the page could have a way to add lines to a table.
	 * Instead of adding a literal, the developer can create a table as a template, copy the row from the template and add it to the table in the DOM. As with other functions,
	 * the return indicates what the template does. Returning true tells the template that JQuery Templates are taken care of and the template needs to do nothing. False or no
	 * return tells the template to do its default action.
	 *
	 * @return bool|void
	 */
	function jqueryTemplates() {
	}

	/**
	 * @return bool|void
	 */
	function hiddenElements() {
	}

	/**
	 * @param $nameValues
	 * @param $actionPerformed
	 */
	function afterSaveChanges($nameValues, $actionPerformed) {
	}

	/**
	 * @param $nameValues
	 */
	function afterSaveDone($nameValues) {
	}

	/**
	 * @return bool|void
	 */
	function beforeDeleteRecord() {

	}

	/**
	 * @return bool|void
	 */
	function deleteRecord() {
	}

	/**
	 * @param $returnArray
	 */
	function afterGetRecord(&$returnArray) {
	}

	/**
	 * @param $filterText
	 */
	function filterTextProcessing($filterText) {
	}
}

$pageObject = new BoilerPlatePage("table_name");
$pageObject->displayPage();
