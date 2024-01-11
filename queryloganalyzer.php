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

$GLOBALS['gPageCode'] = "QUERYLOGANALYZER";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 300000;

class QueryLogAnalyzerPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_details":
				$queryLogRow = getRowFromId("query_log", "query_log_id", $_GET['query_log_id']);
				ob_start();
				$queryText = str_replace("  ", " ", str_replace(",", ", ", str_replace("\n", " ", $queryLogRow['query_text'])));
				$queryText = str_replace("\t", "", $queryText);
				?>
                <div id="query_log_details">

                    <div class='detail-section'><h3>Query</h3><textarea class='query-textarea' readonly='readonly'><?= $queryText ?></textarea></div>
                    <div class='detail-section'><h3>Parameters & Backtrace:</h3><?= str_replace("\n", "<br>", str_replace(" : ", "<br>", $queryLogRow['content'])) ?></div>
                    <div class='detail-section'><h3>Time required</h3><?= $queryLogRow['elapsed_time'] ?></div>
                    <div class='detail-section'><h3>Memory Usages</h3><?= $queryLogRow['memory_usage'] ?></div>
                    <div class='detail-section'><h3>SQL Statements</h3><?= $queryLogRow['statement_count'] ?></div>
                </div>
				<?php
				$returnArray['query_log_details'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
			case "get_api_log_details":
				$apiLogRow = getRowFromId("api_log", "api_log_id", $_GET['api_log_id']);
				ob_start();
				?>
                <div id="api_log_details">

                    <div class='detail-section'><h3>Details</h3>
                        <p><?= $apiLogRow['link_url'] ?></p>
                        <p><?= getFieldFromId("description", "api_apps", "api_app_id", $apiLogRow['api_app_id']) ?></p>
                        <p><?= getFieldFromId("description", "api_methods", "api_method_id", $apiLogRow['api_method_id']) ?></p></div>
                    <div class='detail-section'><h3>Parameters</h3><textarea class="query-textarea" readonly='readonly'><?= $apiLogRow['parameters'] ?></textarea></div>
                    <div class='detail-section'><h3>Parameters</h3><textarea class="query-textarea" readonly='readonly'><?= $apiLogRow['results'] ?></textarea></div>
                    <div class='detail-section'><h3>Time required</h3><?= $apiLogRow['elapsed_time'] ?></div>
                </div>
				<?php
				$returnArray['query_log_details'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
			case "get_background_process_log_details":
				$backgroundProcessLogRow = getRowFromId("background_process_log", "background_process_log_id", $_GET['background_process_log_id']);
				ob_start();
				?>
                <div id="background_process_log_details">

                    <div class='detail-section'><h3>Details</h3>
                        <p><?= getFieldFromId("description", "background_processes", "background_process_id", $backgroundProcessLogRow['background_process_id']) ?></p></div>
                    <div class='detail-section'><h3>Results</h3><textarea class="query-textarea" readonly='readonly'><?= $backgroundProcessLogRow['results'] ?></textarea></div>
                    <div class='detail-section'><h3>Time required</h3><?= $backgroundProcessLogRow['elapsed_time'] ?></div>
                </div>
				<?php
				$returnArray['query_log_details'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
			case "clear_log":
				executeQuery("delete from query_log");
				ajaxResponse($returnArray);
				break;
			case "get_log":
				$resultSet = executeQuery("select count(*) from query_log");
				if ($row = getNextRow($resultSet)) {
					$returnArray['row_count'] = $row['count(*)'];
				}
				switch ($_GET['format']) {
					case "by_time":
					case "by_memory":
					case "by_statements":
						if ($_GET['format'] == "by_time") {
							$orderBy = "elapsed_time";
						} else if ($_GET['format'] == "by_memory") {
							$orderBy = "memory_usage";
						} else {
							$orderBy = "statement_count";
						}
						$resultSet = executeQuery("select * from query_log where" . ($_GET['format'] == "by_statements" ? " query_log_code = 'Query Count'" : " elapsed_time > 0") . " order by " . $orderBy . " desc limit 40");
						ob_start();
						?>
                        <table id="query_log" class="header-sortable log-results grid-table">
                            <tr>
                                <th>Log Code</th>
                                <th>Query Text</th>
                                <th>Elapsed Time</th>
                                <th>Memory Usage</th>
                                <th>SQL Statements</th>
                            </tr>
							<?php
							$queryTexts = array();
							while ($row = getNextRow($resultSet)) {
								if (in_array($row['query_text'], $queryTexts)) {
									continue;
								}
								$queryTexts[] = $row['query_text'];
								?>
                                <tr class='query-log' data-query_log_id="<?= $row['query_log_id'] ?>">
                                    <td><?= $row['query_log_code'] ?></td>
                                    <td><?= str_replace("  ", " ", str_replace(",", ", ", substr($row['query_text'], 0, 100) . (strlen($row['query_text']) > 100 ? "..." : ""))) ?></td>
                                    <td class='align-right'><?php echo $row['elapsed_time'] ?></td>
                                    <td class='align-right'><?php echo $row['memory_usage'] ?></td>
                                    <td class='align-right'><?php echo $row['statement_count'] ?></td>
                                </tr>
								<?php
							}
							?>
                        </table>
						<?php
						$returnArray['query_log_wrapper'] = ob_get_clean();
						ajaxResponse($returnArray);
						break;
					case "by_frequency":
						$resultSet = executeQuery("select query_text,count(*),avg(elapsed_time) from query_log group by query_text having count(*) > 1 order by count(*) desc");
						ob_start();
						?>
                        <table id="query_log" class="header-sortable log-results grid-table">
                            <tr>
                                <th>Query Text</th>
                                <th>Average Time</th>
                                <th>Count</th>
                            </tr>
							<?php
							while ($row = getNextRow($resultSet)) {
								$querySet = executeQuery("select * from query_log where query_text = ? order by elapsed_time desc", $row['query_text']);
								$queryRow = getNextRow($querySet);
								?>
                                <tr class='query-log' data-query_log_id="<?= $queryRow['query_log_id'] ?>">
                                    <td><?php echo str_replace("  ", " ", str_replace(",", ", ", substr($row['query_text'], 0, 150) . (strlen($row['query_text']) > 150 ? "..." : ""))) ?></td>
                                    <td class='align-right'><?php echo $row['avg(elapsed_time)'] ?></td>
                                    <td class='align-right'><?php echo $row['count(*)'] ?></td>
                                </tr>
								<?php
							}
							?>
                        </table>
						<?php
						$returnArray['query_log_wrapper'] = ob_get_clean();
						ajaxResponse($returnArray);
						break;
				}
				ajaxResponse($returnArray);
				break;
			case "get_api_log":
				$resultSet = executeQuery("select count(*) from api_log where client_id = ?", $GLOBALS['gClientId']);
				if ($row = getNextRow($resultSet)) {
					$returnArray['row_count'] = $row['count(*)'];
				}
				$apiMethodId = "";
				if (!empty($_GET['api_method_id'])) {
					$apiMethodId = getFieldFromId("api_method_id", "api_methods", "api_method_id", $_GET['api_method_id']);
				}
				$resultSet = executeQuery("select * from api_log where " . (empty($apiMethodId) ? "" : "api_method_id = " . $apiMethodId . " and ") . "client_id = ? and elapsed_time > 0 order by elapsed_time desc limit 40", $GLOBALS['gClientId']);
				ob_start();
				?>
                <table id="api_log" class="log-results grid-table">
                    <tr>
                        <th>API App</th>
                        <th>API Method</th>
                        <th>Elapsed Time</th>
                    </tr>
					<?php
					while ($row = getNextRow($resultSet)) {
						?>
                        <tr class='api-log' data-api_log_id="<?= $row['api_log_id'] ?>">
                            <td><?= getFieldFromId("description", "api_apps", "api_app_id", $row['api_app_id']) ?></td>
                            <td><?= getFieldFromId("description", "api_methods", "api_method_id", $row['api_method_id']) ?></td>
                            <td class='align-right'><?php echo $row['elapsed_time'] ?></td>
                        </tr>
						<?php
					}
					?>
                </table>
				<?php
				$returnArray['query_log_wrapper'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
			case "get_background_process_log":
				$resultSet = executeQuery("select count(*) from background_process_log");
				if ($row = getNextRow($resultSet)) {
					$returnArray['row_count'] = $row['count(*)'];
				}
				$backgroundProcessId = "";
				if (!empty($_GET['background_process_id'])) {
					$backgroundProcessId = getFieldFromId("background_process_id", "background_processes", "background_process_id", $_GET['background_process_id']);
				}
				$resultSet = executeQuery("select * from background_process_log where " . (empty($backgroundProcessId) ? "" : "background_process_id = " . $backgroundProcessId . " and ") . "elapsed_time > 0 order by elapsed_time desc limit 40");
				ob_start();
				?>
                <table id="background_log" class="log-results grid-table">
                    <tr>
                        <th>Background Process</th>
                        <th>Elapsed Time</th>
                    </tr>
					<?php
					while ($row = getNextRow($resultSet)) {
						?>
                        <tr class='background-process-log' data-background_process_log_id="<?= $row['background_process_log_id'] ?>">
                            <td><?= getFieldFromId("description", "background_processes", "background_process_id", $row['background_process_id']) ?></td>
                            <td class='align-right'><?php echo $row['elapsed_time'] ?></td>
                        </tr>
						<?php
					}
					?>
                </table>
				<?php
				$returnArray['query_log_wrapper'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", ".query-log", function () {
                const queryLogId = $(this).data("query_log_id");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_details&query_log_id=" + queryLogId, function(returnArray) {
                    if ("query_log_details" in returnArray) {
                        const $detailsDialog = $("#details_dialog");
                        $detailsDialog.html(returnArray['query_log_details']);
                        $detailsDialog.dialog({
                            closeOnEscape: true,
                            draggable: true,
                            modal: true,
                            resizable: true,
                            position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                            width: 1200,
                            title: 'Details',
                            buttons: {
                                Close: function (event) {
                                    $detailsDialog.dialog('close');
                                }
                            }
                        });
                    }
                });
            });
            $(document).on("click", ".api-log", function () {
                const apiLogId = $(this).data("api_log_id");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_api_log_details&api_log_id=" + apiLogId, function(returnArray) {
                    if ("query_log_details" in returnArray) {
                        const $detailsDialog = $("#details_dialog");
                        $detailsDialog.html(returnArray['query_log_details']);
                        $detailsDialog.dialog({
                            closeOnEscape: true,
                            draggable: true,
                            modal: true,
                            resizable: true,
                            position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                            width: 1200,
                            title: 'Details',
                            buttons: {
                                Close: function (event) {
                                    $detailsDialog.dialog('close');
                                }
                            }
                        });
                    }
                });
            });
            $(document).on("click", ".background-process-log", function () {
                const backgroundProcessLogId = $(this).data("background_process_log_id");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_background_process_log_details&background_process_log_id=" + backgroundProcessLogId, function(returnArray) {
                    if ("query_log_details" in returnArray) {
                        const $detailsDialog = $("#details_dialog");
                        $detailsDialog.html(returnArray['query_log_details']);
                        $detailsDialog.dialog({
                            closeOnEscape: true,
                            draggable: true,
                            modal: true,
                            resizable: true,
                            position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                            width: 1200,
                            title: 'Details',
                            buttons: {
                                Close: function (event) {
                                    $detailsDialog.dialog('close');
                                }
                            }
                        });
                    }
                });
            });
            $(document).on("click", "#clear_log", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=clear_log", function(returnArray) {
                    $("#by_time").trigger("click");
                });
            });
            $(document).on("click", "#by_time,#by_frequency,#by_statements,#by_memory", function () {
                $("button.active").removeClass("active");
                $(this).addClass("active");
                $("#_background_process_id_row").addClass("hidden");
                $("#_api_method_id_row").addClass("hidden");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_log&format=" + $(this).attr("id"), function(returnArray) {
                    if ("query_log_wrapper" in returnArray) {
                        $("#query_log_wrapper").html(returnArray['query_log_wrapper']);
                        $("#row_count").html(returnArray['row_count']);
                    }
                });
            });
            $(document).on("click", "#api_log", function () {
                $("button.active").removeClass("active");
                $(this).addClass("active");
                $("#_background_process_id_row").addClass("hidden");
                $("#_api_method_id_row").removeClass("hidden");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_api_log&api_method_id=" + $("#api_method_id").val(), function(returnArray) {
                    if ("query_log_wrapper" in returnArray) {
                        $("#query_log_wrapper").html(returnArray['query_log_wrapper']);
                        $("#row_count").html(returnArray['row_count']);
                    }
                });
            });
            $(document).on("click", "#background_process_log", function () {
                $("button.active").removeClass("active");
                $(this).addClass("active");
                $("#_background_process_id_row").removeClass("hidden");
                $("#_api_method_id_row").addClass("hidden");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_background_process_log&background_process_id=" + $("#background_process_id").val(), function(returnArray) {
                    if ("query_log_wrapper" in returnArray) {
                        $("#query_log_wrapper").html(returnArray['query_log_wrapper']);
                        $("#row_count").html(returnArray['row_count']);
                    }
                });
            });
            $("#by_time").trigger("click");
        </script>
		<?php
	}

	function mainContent() {
		echo $this->getPageData("content");
		?>
        <p>
            <button id="by_time" class='active'>By Time</button>
            <button id="by_frequency">By Frequency</button>
            <button id="by_memory">By Memory</button>
            <button id="by_statements">By Statements</button>
            <button id="api_log">API</button>
            <button id="background_process_log">Background Processes</button>
            <button id="clear_log">Clear Query Log</button>
        </p>
        <p><span id='row_count'></span> Found</p>
		<?= createFormControl("background_process_log", "background_process_id", array("empty_text" => "[All]", "not_null" => false, "form_line_classes" => "hidden")) ?>
		<?= createFormControl("api_log", "api_method_id", array("empty_text" => "[All]", "not_null" => false, "form_line_classes" => "hidden")) ?>
        <div id="query_log_wrapper"></div>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            .query-log, .api-log, .background-process-log {
                cursor: pointer;
            }
            .query-log:hover, .api-log:hover, .background-process-log:hover {
                background-color: rgb(240, 240, 180);
            }
            .detail-section {
                border-bottom: 1px solid rgb(200, 200, 200);
                padding: 10px 0;
            }
            table.log-results {
                max-width: 100%;
            }
            textarea.query-textarea {
                height: 400px;
                width: 100%;
            }
            button.active {
                background-color: rgb(180, 180, 180);
                color: rgb(255, 255, 255);
            }
        </style>
		<?php
	}

	function hiddenElements() {
		?>
        <div id="details_dialog" class="dialog-box">
        </div>
		<?php
	}
}

$pageObject = new QueryLogAnalyzerPage();
$pageObject->displayPage();
