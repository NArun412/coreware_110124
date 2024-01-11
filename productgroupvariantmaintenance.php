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

$GLOBALS['gPageCode'] = "PRODUCTGROUPVARIANTMAINT";
require_once "shared/startup.inc";

class ThisPage extends Page {
	function massageDataSource() {
		$this->iDataSource->getPrimaryTable()->setSubtables(array("product_group_variant_choices"));
		$this->iDataSource->setFilterWhere("product_group_id in (select product_group_id from product_groups where client_id = " . $GLOBALS['gClientId'] . ")");
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_options":
				$primaryId = getFieldFromId("product_group_variant_id", "product_group_variants", "product_group_variant_id", $_GET['primary_id']);
				$resultSet = executeQuery("select * from product_group_options join product_options using (product_option_id) where product_group_id = ? and client_id = ? order by sequence_number", $_GET['product_group_id'], $GLOBALS['gClientId']);
				ob_start();
				while ($row = getNextRow($resultSet)) {
					$productOptionChoiceId = getFieldFromId("product_option_choice_id", "product_group_variant_choices", "product_group_variant_id", $primaryId, "product_option_id = ?", $row['product_option_id']);
					?>
                    <div class="basic-form-line">
                        <label><?= htmlText($row['description']) ?><span class="required-tag fa fa-asterisk"></span></label>
                        <select tabindex="10" class="validate[required]" id="product_option_id_<?= $row['product_option_id'] ?>" name="product_option_id_<?= $row['product_option_id'] ?>" data-crc_value="<?= getCrcValue($productOptionChoiceId) ?>">
                            <option value="">[Select]</option>
							<?php
							$optionSet = executeQuery("select * from product_option_choices where product_option_id = ? order by sort_order,description", $row['product_option_id']);
							while ($optionRow = getNextRow($optionSet)) {
								?>
                                <option value="<?= $optionRow['product_option_choice_id'] ?>"<?= ($optionRow['product_option_choice_id'] == $productOptionChoiceId ? " selected" : "") ?>><?= htmlText($optionRow['description']) ?></option>
								<?php
							}
							?>
                        </select>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>
					<?php
				}
				$returnArray['product_options'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#product_group_id").change(function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_options&primary_id=" + $("#primary_id").val() + "&product_group_id=" + $(this).val(), function(returnArray) {
                    $("#product_options").html(returnArray['product_options']);
                });
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function afterGetRecord() {
                $("#product_group_id").trigger("change");
            }
        </script>
		<?php
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		executeQuery("delete from product_group_variant_choices where product_group_variant_id = ?", $nameValues['primary_id']);
		foreach ($nameValues as $fieldName => $fieldValue) {
			if (substr($fieldName, 0, strlen("product_option_id_")) == "product_option_id_") {
				$productOptionId = substr($fieldName, strlen("product_option_id_"));
				$resultSet = executeQuery("insert into product_group_variant_choices (product_group_variant_id,product_option_id,product_option_choice_id) values (?,?,?)",
					$nameValues['primary_id'], $productOptionId, $fieldValue);
				if (!empty($resultSet['sql_error'])) {
					return $resultSet['sql_error'];
				}
			}
		}
		return true;
	}

	function internalCSS() {
		?>
        #product_id_autocomplete_text { width: 800px; }
		<?php
	}
}

$pageObject = new ThisPage("product_group_variants");
$pageObject->displayPage();
