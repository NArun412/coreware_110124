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

$GLOBALS['gPageCode'] = "CREATETABLECSV";
require_once "shared/startup.inc";

class CreateTableCsvPage extends Page {

	function setup() {
		if (empty($GLOBALS['gUserRow']['superuser_flag'])) {
			header("Location: /");
			exit;
		}
	}

	function executePageUrlActions() {
		switch ($_GET['url_action']) {
			case "create_report":
                $tableName = getFieldFromId("table_name","tables","table_id",$_POST['table_id']);
				header("Content-Type: text/csv");
				header("Content-Disposition: attachment; filename=\"" . $tableName . ".csv\"");
				header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
				header('Pragma: public');

                $resultSet = executeQuery("select *" . (empty($_POST['additional_columns']) ? "" : "," . $_POST['additional_columns']) . " from " . $tableName . (empty($_POST['where_statement']) ? "" : " where " . $_POST['where_statement']));
                $headerDisplayed = false;
                while ($row = getNextRow($resultSet)) {
                    if (!$headerDisplayed) {
	                    $headerArray = array();
                        foreach ($row as $fieldName => $fieldData) {
                            $headerArray[] = $fieldName;
                        }
                        echo createCsvRow($headerArray);
                        $headerDisplayed = true;
                    }
					echo createCsvRow($row);
				}
				exit;
		}
	}

	function mainContent() {
		?>
		<div id="report_parameters">
			<form id="_report_form" name="_report_form">

				<div class="basic-form-line" id="_table_id_row">
					<label for="table_id">Table</label>
					<select class='validate[required]' tabindex="10" id="table_id" name="table_id">
						<option value="">[Select]</option>
						<?php
						$resultSet = executeReadQuery("select * from tables order by table_name");
						while ($row = getNextRow($resultSet)) {
							?>
							<option value="<?= $row['table_id'] ?>"><?= htmlText($row['table_name']) ?></option>
							<?php
						}
						?>
					</select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
				</div>

                <div class="basic-form-line" id="_where_statement_row">
                    <label for="where_statement">Where</label>
                    <textarea tabindex="10" id="where_statement" name="where_statement"></textarea>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_additional_columns_row">
                    <label for="additional_columns">Additional Columns</label>
                    <textarea tabindex="10" id="additional_columns" name="additional_columns"></textarea>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line">
					<button tabindex="10" id="create_report">Create Report</button>
				</div>

			</form>
		</div>
		<div id="_button_row">
			<button id="new_parameters_button">Search Again</button>
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
            $(document).on("tap click", "#create_report", function () {
                $("#_report_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?url_action=create_report").attr("method", "POST").submit();
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
		<?php
	}
}

$pageObject = new CreateTableCsvPage();
$pageObject->displayPage();
