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

$GLOBALS['gPageCode'] = "DOCUMENTATIONSEARCH";
require_once "shared/startup.inc";

class DocumentationSearchPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "create_report":
				$fullName = getUserDisplayName($GLOBALS['gUserId']);

				$whereStatement = "";
				$parameters = array();
				$displayCriteria = "";

				if (!empty($_POST['documentation_type_id'])) {
					if (!empty($whereStatement)) {
						$whereStatement .= " and ";
					}
					$whereStatement .= "documentation_type_id = ?";
					$parameters[] = $_POST['documentation_type_id'];
					if (!empty($displayCriteria)) {
						$displayCriteria .= " and ";
					}
					$displayCriteria .= "Documentation Type is " . getFieldFromId("description", "documentation_types", "documentation_type_id", $_POST['documentation_type_id']);
				}

				if (!empty($_POST['parameter_name'])) {
					if (!empty($whereStatement)) {
						$whereStatement .= " and ";
					}
					$whereStatement .= "documentation_entry_id in (select documentation_entry_id from documentation_parameters where parameter_name = ?)";
					$parameters[] = $_POST['parameter_name'];
					if (!empty($displayCriteria)) {
						$displayCriteria .= " and ";
					}
					$displayCriteria .= "Parameter name is " . $_POST['parameter_name'];
				}

				if (!empty($_POST['table_id'])) {
					if (!empty($whereStatement)) {
						$whereStatement .= " and ";
					}
					$whereStatement .= "documentation_entry_id in (select documentation_entry_id from documentation_entry_tables where table_id = ?)";
					$parameters[] = $_POST['table_id'];
					if (!empty($displayCriteria)) {
						$displayCriteria .= " and ";
					}
					$displayCriteria .= "Affected table is " . getFieldFromId("table_name", "tables", "table_id", $_POST['table_id']);
				}

				if (!empty($_POST['search_text'])) {
					if (!empty($whereStatement)) {
						$whereStatement .= " and ";
					}
					$whereStatement .= "(description like ? or detailed_description like ? or sample_return like ? or " .
						"documentation_entry_id in (select documentation_entry_id from documentation_parameters where description like ? or detailed_description like ? or sample_data like ?) or " .
						"documentation_entry_id in (select documentation_entry_id from documentation_entry_tables where detailed_description like ?))";
					$parameters[] = "%" . $_POST['search_text'] . "%";
					$parameters[] = "%" . $_POST['search_text'] . "%";
					$parameters[] = "%" . $_POST['search_text'] . "%";
					$parameters[] = "%" . $_POST['search_text'] . "%";
					$parameters[] = "%" . $_POST['search_text'] . "%";
					$parameters[] = "%" . $_POST['search_text'] . "%";
					$parameters[] = "%" . $_POST['search_text'] . "%";
					if (!empty($displayCriteria)) {
						$displayCriteria .= " and ";
					}
					$displayCriteria .= "Search text is '" . $_POST['search_text'] . "'";
				}

				ob_start();

				?>
                <p><?= $displayCriteria ?></p>
                <p>Run on <?= date("m-d-Y") ?> by <?= $fullName ?></p>
				<?php
				$resultSet = executeReadQuery("select *,(select description from documentation_types where documentation_type_id = documentation_entries.documentation_type_id) documentation_type_description," .
					"(select sort_order from documentation_types where documentation_type_id = documentation_entries.documentation_type_id) documentation_type_sort_order from documentation_entries" .
					(!empty($whereStatement) ? " where " . $whereStatement : "") . " order by documentation_type_sort_order", $parameters);
				while ($row = getNextRow($resultSet)) {
					?>
                    <div class="documentation-entry">
                        <h2><?= htmlText($row['description']) ?></h2>
                        <p class="documentation-type"><span class="highlighted-text">Type:</span> <?= htmlText($row['documentation_type_description']) ?></p>
						<?php if (!empty($row['accessibility'])) { ?>
                            <p class="documentation-accessibility"><span class="highlighted-text">Accessibility:</span> <?= ucwords($row['accessibility']) ?></p>
						<?php } ?>
						<?php if (!empty($row['filename'])) { ?>
                            <p class="documentation-filename"><span class="highlighted-text">Located In:</span> <?= $row['filename'] ?></p>
						<?php } ?>
                        <div class="documentation-details-wrapper">
							<?= makeHtml($row['detailed_description']) ?>
                        </div>
						<?php if (!empty($row['sample_return'])) { ?>
                            <div class="documentation-sample-return-wrapper">
                                <h3>Sample Return</h3>
								<?= makeHtml($row['sample_return']) ?>
                            </div>
						<?php } ?>
						<?php
						$parameterSet = executeReadQuery("select * from documentation_parameters where documentation_entry_id = ? order by sequence_number,documentation_parameter_id", $row['documentation_entry_id']);
						if ($parameterSet['row_count'] > 0) {
							?>
                            <h3>Parameters</h3>
                            <table class="grid-table parameters-table">
                                <tr>
                                    <th>Parameter Name</th>
                                    <th>Details</th>
                                    <th>Sample Data</th>
                                    <th>Required</th>
                                </tr>
								<?php
								while ($parameterRow = getNextRow($parameterSet)) {
									?>
                                    <tr>
                                        <td><?= $parameterRow['parameter_name'] ?></td>
                                        <td><?= makeHtml($parameterRow['detailed_description']) ?></td>
                                        <td><?= makeHtml($parameterRow['sample_data']) ?></td>
                                        <td><?= (empty($parameterRow['required']) ? "" : "Yes") ?></td>
                                    </tr>
									<?php
								}
								?>
                            </table>
							<?php
						}
						$tableSet = executeReadQuery("select *,(select table_name from tables where table_id = documentation_entry_tables.table_id) table_name from documentation_entry_tables where documentation_entry_id = ?", $row['documentation_entry_id']);
						if ($tableSet['row_count'] > 0) {
							?>
                            <h3>Database Tables</h3>
                            <table class="grid-table tables-table">
                                <tr>
                                    <th>Table Name</th>
                                    <th>How used or affected</th>
                                </tr>
								<?php
								while ($tableRow = getNextRow($tableSet)) {
									?>
                                    <tr>
                                        <td><?= $tableRow['table_name'] ?></td>
                                        <td><?= makeHtml($tableRow['detailed_description']) ?></td>
                                    </tr>
									<?php
								}
								?>
                            </table>
							<?php
						}
						?>
                    </div>
					<?php
				}
				$reportContent = ob_get_clean();
				$returnArray['report_content'] = $reportContent;
				ajaxResponse($returnArray);
				break;
		}
	}

	function mainContent() {
		?>
        <div id="report_parameters">
            <form id="_report_form" name="_report_form">

                <div class="basic-form-line" id="_report_type_row">
                    <label for="documentation_type_id">Documentation Type</label>
                    <select tabindex="10" id="documentation_type_id" name="documentation_type_id">
                        <option value="">[All]</option>
						<?php
						$resultSet = executeReadQuery("select * from documentation_types order by sort_order,description");
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['documentation_type_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_parameter_name_row">
                    <label for="search_text">Search Text</label>
                    <input tabindex="10" type="text" size="40" id="search_text" name="search_text">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_parameter_name_row">
                    <label for="parameter_name">Parameter Name</label>
                    <input tabindex="10" type="text" size="40" id="parameter_name" name="parameter_name">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_table_id_row">
                    <label for="table_id">Table Affected</label>
                    <select tabindex="10" id="table_id" name="table_id">
                        <option value="">[All]</option>
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

                <div class="basic-form-line">
                    <button tabindex="10" id="create_report">Search Documentation</button>
                </div>

            </form>
        </div>
        <div id="_button_row">
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
            $("#search_text,#parameter_name").keyup(function (event) {
                if (event.which === 13 || event.which === 3) {
                    $("#create_report").trigger("click");
                }
                return false;
            });
            $(document).on("tap click", "#printable_button", function () {
                window.open("/printable.html");
                return false;
            });
            $(document).on("tap click", "#pdf_button", function () {
                $("#_pdf_form").html("");
                let input = $("<input>").attr("type", "hidden").attr("name", "report_title").val($("#_report_title").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "report_content").val($("#_report_content").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "printable_style").val($("#_printable_style").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "filename").val("designationtotals.pdf");
                $('#_pdf_form').append($(input));
                $("#_pdf_form").attr("action", "/reportpdf.php").attr("method", "POST").submit();
                return false;
            });
            $(document).on("tap click", "#create_report", function () {
                if ($("#_report_form").validationEngine("validate")) {
                    const reportType = $("#report_type").val();
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

            #_report_content table td {
                font-size: 13px;
            }

            #_button_row {
                display: none;
                margin-bottom: 20px;
            }

            .total-line {
                font-weight: bold;
                font-size: 15px;
            }

            .grid-table td.border-bottom {
                border-bottom: 2px solid rgb(0, 0, 0);
            }

            .documentation-entry {
                padding: 20px 0;
                margin: 20px 0;
                border-top: 1px solid rgb(200, 200, 200);
            }

            #_report_content table.parameters-table td, #_report_content table.tables-table td {
                font-size: .9rem;
                max-width: 600px;
            }

            #_report_content table.parameters-table td p, #_report_content table.tables-table td p {
                font-size: .9rem;
                padding: 0;
                margin: 10px 0 0;
            }

            #_report_content table.parameters-table td p:first-child, #_report_content table.tables-table td p:first-child {
                margin-top: 0;
            }

            .documentation-details-wrapper ul {
                list-style: disc;
                margin: 20px 60px 10px 40px;
            }

            .documentation-details-wrapper ul li {
                list-style: disc;
                margin-bottom: 10px;
            }

            pre {
                font-size: 14px;
                font-family: Courier, serif;
                line-height: 1;
                tab-size: 4;
            }
        </style>
		<?php
	}
}

$pageObject = new DocumentationSearchPage();
$pageObject->displayPage();
