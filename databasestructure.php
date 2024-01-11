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

$GLOBALS['gPageCode'] = "DATABASESTRUCTURE";
require_once "shared/startup.inc";

$databaseDefinitionId = getFieldFromId("database_definition_id", "database_definitions", "database_definition_id", $_GET['database_definition_id']);
if (empty($databaseDefinitionId)) {
	header("Location: /index.php");
	exit;
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
    <link type="text/css" rel="StyleSheet" href="cgl.css"/>
    <title>Database Structure</title>
	<?php if (empty($_GET['printable'])) { ?>
        <script src="<?= autoVersion("/js/jquery-3.4.0.min.js") ?>" type="text/javascript"></script>
        <script src="<?= autoVersion("/js/jquery-migrate-3.0.1.min.js") ?>" type="text/javascript"></script>
        <script type="text/javascript">
            $(function () {
                $("table").attr("cellspacing", "0").attr("cellpadding", "0");
                $(document).on("tap click", "#home_toc", function (event) {
                    document.location = "/index.php";
                });
                $(document).on("tap click", "#table_toc", function (event) {
                    $("#column_list").add("#column_content").hide();
                    $("#table_list").add("#table_content").show();
                    $("#subsystem_list").hide();
                    $(".tl").removeClass("hidden");
                    $(".table-definition").removeClass("hidden");
                    return false;
                });
                $(document).on("tap click", "#column_toc", function (event) {
                    $("#table_list").add("#table_content").hide();
                    $("#subsystem_list").hide();
                    $("#column_list").add("#column_content").show();
                    $(".tl").removeClass("hidden");
                    $(".table-definition").removeClass("hidden");
                    return false;
                });
                $(document).on("tap click", "#subsystem_toc", function (event) {
                    $("#column_list").add("#column_content").hide();
                    $("#table_list").hide();
                    $("#table_content").show();
                    $("#subsystem_list").show();
                    return false;
                });
                $(document).on("tap click", ".cl", function (event) {
                    $("#table_list").add("#table_content").hide();
                    $("#column_list").add("#column_content").show();
                    goToByScroll("c-" + $(this).html());
                    return false;
                });
                $(document).on("tap click", ".tl", function (event) {
                    $("#table_list").add("#table_content").show();
                    $("#column_list").add("#column_content").hide();
                    goToByScroll("t-" + $(this).html());
                    return false;
                });
                $(document).on("tap click", ".sl", function (event) {
                    const subsystemId = $(this).data("subsystem_id");
                    $(".tl").addClass("hidden");
                    $(".table-definition").addClass("hidden");
                    $(".tl.subsystem-" + subsystemId).removeClass("hidden");
                    $(".table-definition.subsystem-" + subsystemId).removeClass("hidden");
                    $("html,body").animate({scrollTop: $("#content").offset().top});
                })
            });

            function goToByScroll(id) {
                $("html,body").animate({scrollTop: $("#" + id).offset().top});
            }
        </script>
	<?php } ?>
    <style type="text/css">
		* {
			font-family: Helvetica, Arial, sans-serif;
			font-size: 14px;
			color: rgb(60, 60, 60);
		}

		body {
			margin: 0;
			padding: 0;
		}

		a {
			text-decoration: none;
			font-weight: bold;
			cursor: pointer;
		}

		.hidden {
			display: none;
		}

		a:hover {
			color: rgb(10, 70, 185);
		}

        <?php if (empty($_GET['printable'])) { ?>
		#toc {
			position: fixed;
			top: 0;
			left: 0;
			width: 200px;
			height: 100%;
			background-color: rgb(230, 230, 230);
			margin: 0;
			overflow: auto;
			padding: 10px;
			border-right: 5px solid rgb(150, 150, 150);
		}

		#toc ul {
			list-style: none;
			margin: 0;
			padding: 0 0 20px;
		}

		#toc li {
			padding-bottom: 2px;
		}

        <?php } ?>

		#content {
			background-color: rgb(250, 250, 240);
			padding: 20px;
			margin: 0 0 0 225px;
		}

        <?php if (!empty($_GET['printable'])) { ?>
		#content {
			padding: 40px;
			margin: 0;
		}
        <?php } ?>

		#table_content div {
			border-bottom: 1px solid black;
		}

        <?php if (!empty($_GET['printable'])) { ?>
		#table_content div {
			padding-bottom: 10px;
			margin-bottom: 40px;
		}
        <?php } ?>

		#column_list, #column_content {
			display: none;
			width: 100%;
		}

		#column_content div {
			border-bottom: 1px solid black;
		}

		.hd, p:first-child {
			font-size: 18px;
			margin-bottom: 10px;
			font-weight: bold;
		}

        <?php if (!empty($_GET['printable'])) { ?>
		.hd, p:first-child {
			font-size: 24px;
		}
        <?php } ?>

		#table_content table {
			border-collapse: collapse;
			border: 1px solid black;
			width: 100%;
		}

		#table_content table td, th {
			border: 1px solid black;
			padding: 2px 5px;
			vertical-align: top;
		}

		#column_content table td, th {
			padding: 2px 5px;
			vertical-align: top;
		}

		p {
			margin: 0;
			padding: 5px 0 0;
		}

		p.detailed-description {
			padding-bottom: 5px;
			font-size: 16px;
		}

		.field-type {
			white-space: nowrap;
		}
    </style>
</head>

<body>
<?php if (empty($_GET['printable'])) { ?>
    <div id="toc">
        <ul>
            <li><a id="home_toc">Home</a></li>
            <li><a id="table_toc">Tables</a></li>
            <li><a id="column_toc">Columns</a></li>
            <li><a id="subsystem_toc">Subsystems</a></li>
        </ul>
        <ul id="table_list">
            <li class="hd">Tables</li>
			<?php
			$resultSet = executeQuery("select * from tables where database_definition_id = ? order by table_name", $databaseDefinitionId);
			while ($row = getNextRow($resultSet)) {
				?>
                <li><a class='tl subsystem-<?= $row['subsystem_id'] ?>'><?= $row['table_name'] ?></a></li>
				<?php
			}
			?>
        </ul>
        <ul id="column_list">
            <li class="hd">Columns</li>
			<?php
			$resultSet = executeQuery("select column_name from column_definitions where column_definition_id in (select column_definition_id from table_columns where table_id in (select table_id from tables where database_definition_id = ?)) order by column_name", $databaseDefinitionId);
			while ($row = getNextRow($resultSet)) {
				?>
                <li><a class='cl'><?= $row['column_name'] ?></a></li>
				<?php
			}
			?>
        </ul>
        <ul id="subsystem_list">
            <li class="hd">Subsystems</li>
			<?php
			$resultSet = executeQuery("select * from subsystems");
			while ($row = getNextRow($resultSet)) {
				?>
                <li><a class='sl' data-subsystem_id="<?= $row['subsystem_id'] ?>"><?= $row['description'] ?></a></li>
				<?php
			}
			?>
        </ul>
    </div>
<?php } ?>
<div id="content">
    <div id="table_content">
		<?php
		$resultSet = executeQuery("select * from tables where database_definition_id = ? order by table_name", $databaseDefinitionId);
		while ($row = getNextRow($resultSet)) {
			?>
            <div id='t-<?= $row['table_name'] ?>' class="table-definition subsystem-<?= $row['subsystem_id'] ?>">
                <p><?= $row['table_name'] ?></p>
				<?php if (!empty($row['detailed_description'])) { ?>
                    <p class="detailed-description"><?= $row['detailed_description'] ?></p>
				<?php } ?>
				<?php
				$resultSet1 = executeQuery("select * from column_definitions,table_columns where column_definitions.column_definition_id = table_columns.column_definition_id and table_id = ? order by sequence_number", $row['table_id']);
				?>
                <p>Table has <?= $resultSet1['row_count'] ?> column<?= ($resultSet1['row_count'] == 1 ? "" : "s") ?>
                    .</p>
                <table>
                    <tr>
                        <th>Field Name</th>
                        <th>Field Type</th>
                        <th>Description</th>
                    </tr>
					<?php
					$primaryKey = "";
					$indexes = array();
					while ($row1 = getNextRow($resultSet1)) {
						if (!empty($row1['primary_table_key'])) {
							$primaryKey = $row1['column_name'];
						} else {
							if (!empty($row1['indexed'])) {
								$indexes[] = $row1['column_name'];
							}
						}
						?>
                        <tr>
                            <td><a class='cl'><?= $row1['column_name'] ?></a></td>
                            <td class="field-type"><?= $row1['column_type'] . (empty($row1['data_size']) ? "" : "(" . $row1['data_size'] . (empty($row1['decimal_places']) ? "" : "," . $row1['decimal_places']) . ")") . (empty($row1['not_null']) ? "" : " NOT NULL") ?></td>
                            <td><?= $row1['detailed_description'] ?></td>
                        </tr>
						<?php
					}
					?>
                </table>
                <p>Constraints for <?= $row['table_name'] ?></p>
                <p>PRIMARY KEY(<a class='cl'><?= $primaryKey ?></a>)</p>
				<?php
				foreach ($indexes as $columnName) {
					?>
                    <p>INDEX(<a class='cl'><?= $columnName ?></a>)</p>
					<?php
				}
				$resultSet1 = executeQuery("select * from unique_keys where table_id = ? order by unique_key_id", $row['table_id']);
				while ($row1 = getNextRow($resultSet1)) {
					$uniqueKey = "";
					$resultSet2 = executeQuery("select * from unique_key_columns where unique_key_id = ? order by unique_key_column_id", $row1['unique_key_id']);
					while ($row2 = getNextRow($resultSet2)) {
						if (!empty($uniqueKey)) {
							$uniqueKey .= ", ";
						}
						$columnName = getFieldFromId("column_name", "column_definitions", "column_definition_id", getFieldFromId("column_definition_id", "table_columns", "table_column_id", $row2['table_column_id']));
						$uniqueKey .= "<a class='cl'>" . $columnName . "</a>";
					}
					?>
                    <p>UNIQUE KEY(<?= $uniqueKey ?>)</p>
					<?php
				}
				$resultSet1 = executeQuery("select * from foreign_keys where table_column_id in (select table_column_id from table_columns where table_id = ?) order by foreign_key_id", $row['table_id']);
				while ($row1 = getNextRow($resultSet1)) {
					$columnName = getFieldFromId("column_name", "column_definitions", "column_definition_id", getFieldFromId("column_definition_id", "table_columns", "table_column_id", $row1['table_column_id']));
					$referencedTableName = getFieldFromId("table_name", "tables", "table_id", getFieldFromId("table_id", "table_columns", "table_column_id", $row1['referenced_table_column_id']));
					$referencedColumnName = getFieldFromId("column_name", "column_definitions", "column_definition_id", getFieldFromId("column_definition_id", "table_columns", "table_column_id", $row1['referenced_table_column_id']));
					?>
                    <p>FOREIGN KEY(<a class='cl'><?= $columnName ?></a>) REFERENCES <a
                                class='tl'><?= $referencedTableName ?></a>(<a
                                class='cl'><?= $referencedColumnName ?></a>)</p>
					<?php
				}
				?>
            </div>
			<?php
		}
		?>
    </div>
    <div id='column_content'>
		<?php
		$resultSet = executeQuery("select * from column_definitions where column_definition_id in (select column_definition_id from table_columns where table_id in (select table_id from tables where database_definition_id = ?)) order by column_name", $databaseDefinitionId);
		while ($row = getNextRow($resultSet)) {
			?>
            <div id='c-<?= $row['column_name'] ?>'>
                <p><?= $row['column_name'] ?></p>
                <table>
                    <tr>
                        <td>Type:</td>
                        <td><?= $row['column_type'] . (empty($row['data_size']) ? "" : "(" . $row['data_size'] . (empty($row['decimal_places']) ? "" : "," . $row['decimal_places']) . ")") . (empty($row['not_null']) ? "" : " NOT NULL") ?></td>
                    </tr>
					<?php if (strlen($row['minimum_value']) > 0) { ?>
                        <tr>
                            <td>Minimum Value:</td>
                            <td><?= $row['minimum_value'] ?></td>
                        </tr>
					<?php } ?>
					<?php if (strlen($row['maximum_value']) > 0) { ?>
                        <tr>
                            <td>Maximum Value:</td>
                            <td><?= $row['maximum_value'] ?></td>
                        </tr>
					<?php } ?>
					<?php if (strlen($row['valid_values']) > 0) { ?>
                        <tr>
                            <td>Valid Values:</td>
                            <td><?= implode(",", getContentLines($row['valid_values'])) ?></td>
                        </tr>
					<?php } ?>
					<?php if (strlen($row['letter_case']) > 0) { ?>
                        <tr>
                            <td>Case:</td>
                            <td><?= ($row['letter_case'] == "U" ? "Upper" : "Lower") ?></td>
                        </tr>
					<?php } ?>
					<?php if (strlen($row['default_value']) > 0) { ?>
                        <tr>
                            <td>Default Value:</td>
                            <td><?= $row['default_value'] ?></td>
                        </tr>
					<?php } ?>
					<?php
					$resultSet1 = executeQuery("select * from tables where database_definition_id = ? and table_id in (select table_id from table_columns " .
						"where column_definition_id = ?) order by table_name", array($databaseDefinitionId, $row['column_definition_id']));
					$usedTables = "";
					while ($row1 = getNextRow($resultSet1)) {
						if (!empty($usedTables)) {
							$usedTables .= ", ";
						}
						$usedTables .= "<a class='tl'>" . $row1['table_name'] . "</a>";
					}
					?>
                    <tr>
                        <td>Used in <?= $resultSet1['row_count'] ?>
                            table<?= ($resultSet1['row_count'] == 1 ? "" : "s") ?>:
                        </td>
                        <td><?= $usedTables ?></td>
                    </tr>
                </table>
            </div>
			<?php
		}
		?>
    </div>
</body>
</html>
