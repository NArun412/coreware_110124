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

$GLOBALS['gPageCode'] = "APIDOCUMENTATION";
require_once "shared/startup.inc";

class ApiDocumentationPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_documentation":
				$resultSet = executeQuery("select * from api_methods where api_method_id = ?", $_GET['api_method_id']);
				if ($row = getNextRow($resultSet)) {
					ob_start();
					?>
                    <h2><?= htmlText($row['description']) ?></h2>
                    <p>API Method Code: <?= $row['api_method_code'] ?></p>
					<?php if (!empty($row['detailed_description'])) { ?>
                        <p><?= htmlText($row['detailed_description']) ?></p>
					<?php } ?>
                    <p>Can be accessed
                        by <?= ($row['public_access'] ? "anyone" : ($row['all_user_access'] ? "logged in users" : "administrators only")) ?>
                        .</p>
					<?php
					$parameterSet = executeQuery("select * from api_parameters join api_method_parameters using (api_parameter_id) where " .
						"api_method_id = ? order by column_name", $_GET['api_method_id']);
					if ($parameterSet['row_count'] > 0) {
						?>
                        <h3>Parameters</h3>
                        <table class="grid-table">
                            <tr>
                                <th>Parameter Name</th>
                                <th>Description</th>
                                <th>Type</th>
                                <th>Required</th>
                            </tr>
							<?php
							while ($parameterRow = getNextRow($parameterSet)) {
								$dataTypes = array("A" => "Array", "S" => "String", "I" => "Integer", "N" => "Number", "D" => "Date", "B" => "Boolean");
								?>
                                <tr>
                                    <td><?= $parameterRow['column_name'] ?></td>
                                    <td><?= htmlText($parameterRow['description']) ?></td>
                                    <td><?= $dataTypes[$parameterRow['data_type']] ?></td>
                                    <td><?= ($parameterRow['required'] ? "YES" : "no") ?></td>
                                </tr>
								<?php
							}
							?>
                        </table>
						<?php
					}
					if (!empty($row['sample_return'])) {
						?>
                        <h3>Sample Return</h3>
                        <p><?= makeHtml($row['sample_return']) ?></p>
						<?php
					}
					$returnArray['method_documentation'] = ob_get_clean();
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#api_method_id").change(function () {
                if (empty($(this).val())) {
                    $("#method_documentation").html("");
                } else {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_documentation&api_method_id=" + $(this).val(), function(returnArray) {
                        if ("method_documentation" in returnArray) {
                            $("#method_documentation").html(returnArray['method_documentation']);
                        }
                    });
                }
            });
        </script>
		<?php
	}

	function mainContent() {
		echo $this->iPageData['content'];
		?>
        <div class="basic-form-line" id="_api_method_id_row">
            <label for="api_method_id">API Method</label>
            <select id="api_method_id" name="api_method_id">
                <option value="">[Select]</option>
				<?php
				$resultSet = executeQuery("select * from api_methods order by sort_order,description");
				while ($row = getNextRow($resultSet)) {
					?>
                    <option value="<?= $row['api_method_id'] ?>"><?= htmlText($row['description']) ?></option>
					<?php
				}
				?>
            </select>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>
        <div id="method_documentation">
        </div>
		<?php
		return true;
	}
}

$pageObject = new ApiDocumentationPage();
$pageObject->displayPage();
