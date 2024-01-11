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

$GLOBALS['gPageCode'] = "SUDOKUCREATOR";
require_once "shared/startup.inc";

/*
 √ dialog to configure cell with features
 √ save cell information and features
 √ display existing grid with features
 √ Show cell features in grid
 * way to redefine the blocks
 * control to create thermometer
 * control to create cage
 * control to create arrow
 * control to create line
 * outside numbers and arrows
 */

class SudokuCreatorPage extends Page {
	function massageDataSource() {
		$this->iDataSource->addColumnControl("link_name", "default_value", strtolower(getRandomString(16)));
		$this->iDataSource->getPrimaryTable()->setSubtables(array("sudoku_puzzle_cells","sudoku_puzzle_cages"));
	}

	function beforeDeleteRecord($primaryId) {
		executeQuery("delete from sudoku_puzzle_cell_feature_links where sudoku_puzzle_cell_id in (select sudoku_puzzle_cell_id from sudoku_puzzle_cells where sudoku_puzzle_id = ?)", $primaryId);
		return true;
	}

	function supplementaryContent() {
		?>
        <div id="sudoku_layout">
        </div>
        <input type="hidden" name="puzzle_width" id="puzzle_width" value="">
        <input type="hidden" name="puzzle_height" id="puzzle_height" value="">
        <input type="hidden" name="sudoku_puzzle_cages" id="sudoku_puzzle_cages" value="">
        <div id="grid_wrapper">
        </div>
        <h3>Lines</h3>
        <div id="sudoku_puzzle_lines"></div>
		<?php
	}

	function afterGetRecord(&$returnArray) {
		ob_start();
		?>
        <h2>Puzzle Grid</h2>
        <p class='green-text' id='grid_message'></p>
		<?php
		if (empty($returnArray['primary_id']['data_value'])) {
			?>
            <div class='form-line'>
                <label>Grid Width</label>
                <input type="text" class='validate[custom[integer],required]' id='grid_width' name='grid_width' value='9'>
            </div>
            <div class='form-line'>
                <label>Grid Height</label>
                <input type="text" class='validate[custom[integer],required]' id='grid_height' name='grid_height' value='9'>
            </div>
            <div class='form-line'>
                <button id="create_grid">Create Grid</button>
            </div>
			<?php
		}
		?>
		<?php
		$returnArray['sudoku_layout'] = array("data_value" => ob_get_clean());
		if (!empty($returnArray['primary_id']['data_value'])) {
		    $cellData = array();
			$resultSet = executeQuery("select * from sudoku_puzzle_cells where sudoku_puzzle_id = ?", $returnArray['primary_id']['data_value']);
			$maxRowNumber = 0;
			$maxColumnNumber = 0;
			while ($row = getNextRow($resultSet)) {
				if ($row['row_number'] > $maxRowNumber) {
					$maxRowNumber = $row['row_number'];
				}
				if ($row['column_number'] > $maxColumnNumber) {
					$maxColumnNumber = $row['column_number'];
				}
                $featureSet = executeQuery("select * from sudoku_puzzle_cell_feature_links where sudoku_puzzle_cell_id = ?",$row['sudoku_puzzle_cell_id']);
                $features = "";
                while ($featureRow = getNextRow($featureSet)) {
                    $features .= $featureRow['sudoku_puzzle_cell_feature_id'] . "|" . $featureRow['compass_points'] . "|" . $featureRow['display_color'] . "|" .
                        $featureRow['icon_name'] . "|" . $featureRow['text_data'] . "\n";
                }
                $row['cell_features'] = $features;
                $cellData[] = $row;
			}
			$returnArray['cell_data'] = $cellData;
			$returnArray['grid_width'] = $maxColumnNumber - 1;
			$returnArray['grid_height'] = $maxRowNumber - 1;
		} else {
			$returnArray['cell_data'] = array();
		}
		$cages = "";
		$resultSet = executeQuery("select sudoku_puzzle_cage_id,cage_total,content from sudoku_puzzle_cages where sudoku_puzzle_id = ?",$returnArray['primary_id']['data_value']);
		while ($row = getNextRow($resultSet)) {
			$cells = explode("|",$row['content']);
			foreach ($cells as $index => $thisCell) {
				$parts = explode(",",$thisCell);
				$cells[$index] = array("row_number"=>$parts[0],"column_number"=>$parts[1]);
			}
			usort($cells,array($this,"sortCells"));
			$content = "";
			foreach ($cells as $thisCell) {
				$content .= (empty($content) ? "" : "|") . $thisCell['row_number'] . "," . $thisCell['column_number'];
			}
			$cages .= $row['sudoku_puzzle_cage_id'] . "|" . $row['cage_total'] . "|" . $content . "\n";
		}
		$returnArray['sudoku_puzzle_cages'] = array("data_value"=>$cages);
		$linesDisplay = "";
		$resultSet = executeQuery("select * from sudoku_puzzle_lines where sudoku_puzzle_id = ?",$returnArray['primary_id']['data_value']);
		$count = 0;
		while ($row = getNextRow($resultSet)) {
		    if ($row['line_width'] <= 1) {
			    $row['line_width'] = 1;
		    }
		    $parts = explode("|",$row['content']);
		    $count++;
			ob_start();
		    ?>
            <div class='line-display'>
                <input type='hidden' class='line-data' name="sudoku_puzzle_line_<?= $count ?>" value='<?= $row['sudoku_puzzle_line_id'] . "|" . $row['line_start_type'] . "|" . $row['line_end_type'] . "|" . $row['display_color'] . "|" . $row['line_width'] . "|" . $row['content'] ?>'>
                <p><span class='remove-line far fa-times'></span><?= $row['line_width'] ?> pixel line starting at <?= $parts[0] ?> and ending at <?= $parts[count($parts) - 1] ?></p>
            </div>
            <?php
		    $linesDisplay .= ob_get_clean();
		}
		$returnArray['sudoku_puzzle_lines'] = array("data_value"=>$linesDisplay);
    }

    function sortCells($a,$b) {
	    if ($a['row_number'] == $b['row_number']) {
	        if ($a['column_number'] == $b['column_number']) {
	            return 0;
            }
	        return ($a['column_number'] > $b['column_number'] ? 1 : -1);
        }
	    return ($a['row_number'] > $b['row_number'] ? 1 : -1);
    }

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click",".remove-line",function() {
                $(this).closest("div.line-display").remove();
                drawLines();
            });
            $(document).on("click","#clear_blocks",function() {
                $("#grid_wrapper").find(".sudoku-puzzle-cell").find(".cell-block-number").val("");
                drawBlocks();
                return false;
            });
            $(document).on("click","#redraw_blocks",function() {
                if (empty($(this).data("active"))) {
                    $("#grid_wrapper").find(".sudoku-puzzle-cell").find(".cell-block-number").val("");
                    drawBlocks();
                    $("#grid_message").html("Drag through cells for each block. Click button when done.");
                    $(this).html("Finish Blocks").data("active","true");
                    $("#grid_wrapper").find(".sudoku-puzzle-cell").addClass("drag-select");
                    $("input.cell-value").prop("readonly", true);
                    dragSelecting = true;
                    drawingBlocks = true;
                    $(this).data("block_number", "0");
                } else {
                    $(this).html("Redraw Blocks").data("active","");
                    $("#grid_wrapper").find(".sudoku-puzzle-cell").removeClass("drag-select");
                    $("#grid_wrapper").find(".sudoku-puzzle-cell").removeClass("selected");
                    $("input.cell-value").prop("readonly", false);
                    dragSelecting = false;
                    drawingBlocks = false;
                    isMouseDown = false;
                }
                return false;
            });
            $(document).on("click","#draw_cages",function() {
                if (empty($(this).data("active"))) {
                    $("#grid_message").html("Drag through cells for each cage. Click button when done. Click Existing Cage to delete.");
                    $(this).html("Cages Done").data("active","true");
                    $("#grid_wrapper").find(".sudoku-puzzle-cell").addClass("drag-select");
                    $("input.cell-value").prop("readonly", true);
                    dragSelecting = true;
                    drawingCages = true;
                    $(this).data("cage_number", "0");
                } else {
                    $(this).html("Edit Cages").data("active","");
                    $("#grid_wrapper").find(".sudoku-puzzle-cell").removeClass("drag-select");
                    $("#grid_wrapper").find(".sudoku-puzzle-cell").removeClass("selected");
                    $("input.cell-value").prop("readonly", false);
                    dragSelecting = false;
                    drawingCages = false;
                    isMouseDown = false;
                }
                return false;
            });
            $(document).on("click","#draw_lines",function() {
                if (empty($(this).data("active"))) {
                    $("#grid_message").html("Drag through cells for the line. Click button when done. Click Existing Line to delete.");
                    $(this).html("Done with Lines").data("active","true");
                    $("#grid_wrapper").find(".sudoku-puzzle-cell").addClass("drag-select");
                    $("input.cell-value").prop("readonly", true);
                    dragSelecting = true;
                    drawingLines = true;
                    $(this).data("line_number", "0");
                } else {
                    $(this).html("Draw Lines & Thermometers").data("active","");
                    $("#grid_wrapper").find(".sudoku-puzzle-cell").removeClass("drag-select");
                    $("#grid_wrapper").find(".sudoku-puzzle-cell").removeClass("selected");
                    $("input.cell-value").prop("readonly", false);
                    dragSelecting = false;
                    drawingLines = false;
                    isMouseDown = false;
                }
                return false;
            });
            $(document).on("mousedown","#puzzle_grid_table .cell-value", function () {
                if (dragSelecting) {
                    isMouseDown = true;
                    $(this).closest(".sudoku-puzzle-cell").addClass("selected");
                    return false;
                }
                return true;
            });
            $(document).on("mouseover","#puzzle_grid_table .cell-value", function () {
                if (isMouseDown && dragSelecting) {
                    $(this).closest(".sudoku-puzzle-cell").addClass("selected");
                    return false;
                }
                return true;
            });

            $(document).mouseup(function () {
                if (!dragSelecting) {
                    return true;
                }
                isMouseDown = false;
                if (drawingBlocks) {
                    const blockNumber = parseInt($("#redraw_blocks").data("block_number") + 1);
                    $("#redraw_blocks").data("block_number",blockNumber);
                    $(".sudoku-puzzle-cell.selected").each(function() {
                        $(this).find(".cell-block-number").val(blockNumber);
                        $(this).removeClass("selected");
                    });
                    drawBlocks();
                    return false;
                } else if (drawingCages) {
                    if ($(".sudoku-puzzle-cell.selected").length == 0) {
                        dragSelecting = true;
                        return false;
                    }
                    if ($(".sudoku-puzzle-cell.selected").length == 1) {
                        const cageNumber = $(".sudoku-puzzle-cell.selected").find(".cell-cage-number").val();
                        if (!empty(cageNumber)) {
                            $(".sudoku-puzzle-cell").each(function() {
                                if ($(this).find(".cell-cage-number").val() == cageNumber) {
                                    $(this).find(".cell-cage-number").val("");
                                    $(this).find(".cell-cage-total").val("");
                                }
                            });
                            $(".sudoku-puzzle-cell.selected").removeClass("selected");
                            setTimeout(function() {
                                drawCages();
                            },100);
                            dragSelecting = true;
                            return true;
                        }
                    }
                    dragSelecting = false;
                    $("#cage_total").val("");
                    $("#cage_dialog").dialog({
                        closeOnEscape: false,
                        draggable: true,
                        modal: true,
                        resizable: true,
                        position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                        width: 400,
                        title: 'Cage Total',
                        buttons: {
                            Save: function (event) {
                                const cageNumber = parseInt($("#draw_cages").data("cage_number") - 1);
                                $("#draw_cages").data("cage_number",cageNumber);
                                let cageTotal = $("#cage_total").val();
                                $(".sudoku-puzzle-cell.selected").each(function() {
                                    $(this).find(".cell-cage-number").val(cageNumber);
                                    $(this).find(".cell-cage-total").val(cageTotal);
                                    cageTotal = "";
                                    $(this).removeClass("selected");
                                });
                                $("#cage_dialog").dialog('close');
                                setTimeout(function() {
                                    drawCages();
                                    dragSelecting = true;
                                },100);
                            },
                            Cancel: function (event) {
                                $(".sudoku-puzzle-cell.selected").removeClass("selected");
                                dragSelecting = true;
                                $("#cage_dialog").dialog('close');
                            }
                        }
                    });
                    return false;
                } else if (drawingLines) {
                    if ($(".sudoku-puzzle-cell.selected").length == 0) {
                        dragSelecting = true;
                        return false;
                    }
                    dragSelecting = false;
                    $("#cage_total").val("");
                    $("#cage_dialog").dialog({
                        closeOnEscape: false,
                        draggable: true,
                        modal: true,
                        resizable: true,
                        position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                        width: 400,
                        title: 'Cage Total',
                        buttons: {
                            Save: function (event) {
                                const cageNumber = parseInt($("#draw_cages").data("cage_number") - 1);
                                $("#draw_cages").data("cage_number",cageNumber);
                                let cageTotal = $("#cage_total").val();
                                $(".sudoku-puzzle-cell.selected").each(function() {
                                    $(this).find(".cell-cage-number").val(cageNumber);
                                    $(this).find(".cell-cage-total").val(cageTotal);
                                    cageTotal = "";
                                    $(this).removeClass("selected");
                                });
                                $("#cage_dialog").dialog('close');
                                setTimeout(function() {
                                    drawCages();
                                    dragSelecting = true;
                                },100);
                            },
                            Cancel: function (event) {
                                $(".sudoku-puzzle-cell.selected").removeClass("selected");
                                dragSelecting = true;
                                $("#cage_dialog").dialog('close');
                            }
                        }
                    });
                    return false;
                }
                return true;
            });


            $(document).on("click", "#create_grid", function () {
                const gridWidth = parseInt($("#grid_width").val());
                const gridHeight = parseInt($("#grid_height").val());
                if (empty(gridWidth) || isNaN(gridWidth) || empty(gridHeight) || isNaN(gridHeight)) {
                    displayErrorMessage("Invalid grid dimensions");
                    return;
                }
                createGrid(gridWidth,gridHeight);
                return false;
            });
            $(document).on("click", "#edit_values", function () {
                if ($(this).prop("checked")) {
                    $("#puzzle_grid_table").find(".cell-value").prop("readonly", false);
                } else {
                    $("#puzzle_grid_table").find(".cell-value").prop("readonly", true);
                }
                return true;
            });
            $(document).on("click", ".sudoku-puzzle-cell", function () {
                const rowNumber = $(this).data("row_number");
                const columnNumber = $(this).data("column_number");
                const cellFeatures = $("#cell_feature_ids_" + rowNumber + "_" + columnNumber).val();
                $("#cell_features_dialog").find("input[type=checkbox]").prop("checked",false);
                $("#cell_features_dialog").find("input[type=text]").val("");
                $("#cell_features_dialog").find(".icon-code").val("");
                $("#cell_display_color").val($("#cell_display_color_" + rowNumber + "_" + columnNumber).val()).trigger("keyup");
                $("#cell_readonly").prop("checked",!empty($("#cell_readonly_" + rowNumber + "_" + columnNumber).val()));
                $("#cell_features_dialog").find(".selected-icon").attr("class","selected-icon");
                $("#cell_features_dialog").find(".display-color").trigger("keyup");
                const featureLines = cellFeatures.split("\n");
                for (var i in featureLines) {
                    const lineParts = featureLines[i].split("|");
                    $("#sudoku_puzzle_cell_feature_id_" + lineParts[0]).prop("checked",true);
                    if (!empty(lineParts[1])) {
                        const compassPoints = lineParts[1].split(",");
                        for (var j in compassPoints) {
                            $("#sudoku_puzzle_cell_feature_id_" + lineParts[0] + "_compass_" + compassPoints[j]).prop("checked",true);
                        }
                    }
                    $("#cell_feature_" + lineParts[0]).find(".display-color").val(lineParts[2]).trigger("keyup");
                    if (!empty(lineParts[3])) {
                        $("#cell_feature_" + lineParts[0]).find(".icon-code").val(lineParts[3]);
                        $("#cell_feature_" + lineParts[0]).find(".selected-icon").attr("class","selected-icon far fa-" + lineParts[3]);
                    }
                    $("#cell_feature_" + lineParts[0]).find(".cell-text").val(lineParts[4]);
                }
                if (!$("#edit_values").prop("checked")) {
                    $("#dialog_row_number").html(rowNumber);
                    $("#dialog_column_number").html(columnNumber);
                    $("#cell_features_dialog").dialog({
                        closeOnEscape: true,
                        draggable: true,
                        modal: true,
                        resizable: true,
                        position: {my: "center top", at: "center top+100px", of: window, collision: "none"},
                        width: 900,
                        title: 'Cell Features',
                        buttons: {
                            Save: function (event) {
                                let featureContent = "";
                                $(".feature-row").each(function() {
                                    if (!$(this).find(".puzzle-cell-feature").prop("checked")) {
                                        return true;
                                    }
                                    featureContent += $(this).find(".puzzle-cell-feature").val() + "|";
                                    let compassPoints = "";
                                    $(this).find(".compass-points-table").find("input[type=checkbox]").each(function() {
                                        if ($(this).prop("checked")) {
                                            compassPoints += (empty(compassPoints) ? "" : ",") + $(this).val();
                                        }
                                    });
                                    featureContent += compassPoints + "|";
                                    featureContent += $(this).find(".display-color").val() + "|";
                                    if ($(this).find(".icon-code").length > 0) {
                                        featureContent += $(this).find(".icon-code").val();
                                    }
                                    featureContent += "|";
                                    if ($(this).find(".cell-text").length > 0) {
                                        featureContent += $(this).find(".cell-text").val();
                                    }
                                    featureContent += "\n";
                                });
                                $("#cell_feature_ids_" + rowNumber + "_" + columnNumber).val(featureContent);
                                $("#cell_readonly_" + rowNumber + "_" + columnNumber).val($("#cell_readonly").prop("checked") ? "1" : "0");
                                $("#cell_display_color_" + rowNumber + "_" + columnNumber).val($("#cell_display_color").val());
                                $("#cell_features_dialog").dialog('close');
                                setTimeout(function() {
                                    styleCells();
                                },100);
                            },
                            Cancel: function (event) {
                                $("#cell_features_dialog").dialog('close');
                            }
                        }
                    });
                }
            });
            $(document).on("click",".select-icon-button",function() {
                if (empty($("#icon_list").html())) {
                    for (const i in iconList) {
                        $("#icon_list").append("<span data-icon_code='" + iconList[i] + "' class='icon-choice far fa-" + iconList[i] + "'></span>")
                    }
                }
                $("#icon_dialog_feature_id").val($(this).closest("tr.feature-row").data("sudoku_puzzle_cell_feature_id"));
                $("#icon_dialog").dialog({
                    closeOnEscape: true,
                    draggable: true,
                    modal: true,
                    resizable: true,
                    position: {my: "center top", at: "center top+100px", of: window, collision: "none"},
                    width: 1000,
                    title: 'Icons',
                    buttons: {
                        Cancel: function (event) {
                            $("#icon_dialog").dialog('close');
                        }
                    }
                });
            });
            $(document).on("click",".icon-choice",function() {
                const iconCode = $(this).data("icon_code");
                $("#cell_feature_" + $("#icon_dialog_feature_id").val()).find(".selected-icon").attr("class","selected-icon far fa-" + iconCode);
                $("#cell_feature_" + $("#icon_dialog_feature_id").val()).find(".icon-code").val(iconCode);
                $("#icon_dialog").dialog('close');
            });
            $(document).on("keydown", ".cell-value", function (event) {
                var row = $(this).data("row_number");
                var column = $(this).data("column_number");
                var maxRow = parseInt($("#puzzle_height").val()) + 1;
                var maxColumn = parseInt($("#puzzle_width").val()) + 1;
                var goNext = false;
                if (event.which == 32) {
                    $(this).val("");
                    goNext = true;
                } else if (event.which == 13 || event.which == 3 || event.which == 9) {
                    goNext = true;
                } else if (event.which == 39) {
                    goNext = true;
                } else if (event.which == 38) {
                    row--;
                    if (row < 0) {
                        row = maxRow;
                    }
                    $("#cell_value_" + row + "_" + column).focus();
                    return false;
                } else if (event.which == 40) {
                    row++;
                    if (row > maxRow) {
                        row = 0;
                    }
                    $("#cell_value_" + row + "_" + column).focus();
                    return false;
                } else if (event.which == 37) {
                    column--;
                    if (column < 0) {
                        row--;
                        column = maxColumn;
                    }
                    if (row < 0) {
                        row = maxRow;
                    }
                    $("#cell_value_" + row + "_" + column).focus();
                    return false;
                }
                if (goNext) {
                    column++;
                    if (column > maxColumn) {
                        row++;
                        column = 0;
                    }
                    if (row > maxRow) {
                        row = 0;
                    }
                    $("#cell_value_" + row + "_" + column).focus();
                } else {
                    return true;
                }
                return false;
            });
        </script>
		<?php
	}

	function javascript() {
	    ?>
        <script>
            var isMouseDown = false;
            var dragSelecting = false;
            var drawingBlocks = false;
            var drawingCages = false;
            var drawingLines = false;

            function afterGetRecord(returnArray) {
                if (empty(returnArray['primary_id']['data_value'])) {
                    return;
                }
                createGrid(returnArray['grid_width'], returnArray['grid_height'], true);
                for (var i in returnArray['cell_data']) {
                    if ($("#sudoku_puzzle_cell_" + returnArray['cell_data'][i]['row_number'] + "_" + returnArray['cell_data'][i]['column_number']).length == 0) {
                        continue;
                    }
                    const $cell = $("#sudoku_puzzle_cell_" + returnArray['cell_data'][i]['row_number'] + "_" + returnArray['cell_data'][i]['column_number']);
                    $("#cell_readonly_" + returnArray['cell_data'][i]['row_number'] + "_" + returnArray['cell_data'][i]['column_number']).val(returnArray['cell_data'][i]['readonly']);
                    $("#cell_display_color_" + returnArray['cell_data'][i]['row_number'] + "_" + returnArray['cell_data'][i]['column_number']).val(returnArray['cell_data'][i]['display_color']);
                    $("#cell_feature_ids_" + returnArray['cell_data'][i]['row_number'] + "_" + returnArray['cell_data'][i]['column_number']).val(returnArray['cell_data'][i]['cell_features']);
                    $("#cell_block_number_" + returnArray['cell_data'][i]['row_number'] + "_" + returnArray['cell_data'][i]['column_number']).val(returnArray['cell_data'][i]['block_number']);
                    $("#cell_value_" + returnArray['cell_data'][i]['row_number'] + "_" + returnArray['cell_data'][i]['column_number']).val(returnArray['cell_data'][i]['fill_value']);
                }
                $(".cell-cage-number").val("");
                const cageContent = $("#sudoku_puzzle_cages").val().split("\n");
                for (var i in cageContent) {
                    if (empty(cageContent[i])) {
                        continue;
                    }
                    parts = cageContent[i].split("|");
                    let cageNumber = "";
                    let cageTotal = "";
                    for (var j in parts) {
                        if (j == 0) {
                            cageNumber = parts[j];
                        } else if (j == 1) {
                            cageTotal = parts[j];
                        } else {
                            let cellParts = parts[j].split(',');
                            $("#cell_cage_number_" + cellParts[0] + "_" + cellParts[1]).val(cageNumber);
                            $("#cell_cage_total_" + cellParts[0] + "_" + cellParts[1]).val(cageTotal);
                            cageTotal = "";
                        }
                    }
                }
                drawBlocks();
                styleCells();
            }

            function styleCells() {
                const puzzleWidth = parseInt($("#puzzle_width").val());
                const puzzleHeight = parseInt($("#puzzle_height").val());
                for (let rowNumber = 0; rowNumber <= puzzleHeight + 1; rowNumber++) {
                    for (let columnNumber = 0; columnNumber <= puzzleWidth + 1; columnNumber++) {
                        const $cell = $("#sudoku_puzzle_cell_" + rowNumber + "_" + columnNumber);
                        if (empty($cell.find(".cell-display-color").val())) {
                            $cell.css("background-color", "transparent");
                        } else {
                            $cell.css("background-color",$cell.find(".cell-display-color").val());
                        }
                        const featureData = $cell.find(".cell-feature-ids").val();
                        const featureLines = featureData.split("\n");
                        $cell.find(".additional-element").remove();
                        $cell.removeClass("double-border-top").removeClass("double-border-right").removeClass("double-border-bottom").removeClass("double-border-left");
                        for (var i in featureLines) {
                            if (empty(featureLines[i])) {
                                continue;
                            }
                            const lineParts = featureLines[i].split("|");
                            const $featureRow = $("#cell_feature_" + lineParts[0]);
                            const featureCode = $featureRow.data("sudoku_puzzle_cell_feature_code");
                            const compassPoints = (empty(lineParts[1]) ? [] : lineParts[1].split(","));
                            const displayColor = lineParts[2];
                            const iconCode = lineParts[3];
                            const textData = lineParts[4];
                            switch (featureCode) {
                                case "NO_LEFT_BLOCK_BORDER":
                                    if (columnNumber > 1) {
                                        $cell.removeClass("left-edge-cell");
                                    }
                                    break;
                                case "NO_TOP_BLOCK_BORDER":
                                    if (rowNumber > 1) {
                                        $cell.removeClass("top-edge-cell");
                                    }
                                    break;
                                case "OPEN_DOT":
                                    for (var j in compassPoints) {
                                        if (empty(compassPoints[j])) {
                                            continue;
                                        }
                                        $cell.append("<div class='additional-element open-dot compass-point compass-point-" + compassPoints[j] + "'" + (empty(displayColor) ? "" : " style='border-color: " + displayColor + ";'") + "></div>");
                                    }
                                    break;
                                case "CLOSED_DOT":
                                    for (var j in compassPoints) {
                                        if (empty(compassPoints[j])) {
                                            continue;
                                        }
                                        $cell.append("<div class='additional-element closed-dot compass-point compass-point-" + compassPoints[j] + "'" + (empty(displayColor) ? "" : " style='background-color: " + displayColor + ";'") + "></div>");
                                    }
                                    break;
                                case "EQUAL_SIGN":
                                case "GREATER_THAN":
                                case "LESS_THAN":
                                case "DASH":
                                    for (var j in compassPoints) {
                                        if (empty(compassPoints[j])) {
                                            continue;
                                        }
                                        let iconCode = "";
                                        switch (featureCode) {
                                            case "EQUAL_SIGN":
                                                iconCode = "equals";
                                                break;
                                            case "GREATER_THAN":
                                                iconCode = "greater-than";
                                                break;
                                            case "LESS_THAN":
                                                iconCode = "less-than";
                                                break;
                                            case "DASH":
                                                iconCode = "minus";
                                                break;
                                        }
                                        if (!empty(iconCode)) {
                                            $cell.append("<div class='additional-element compass-point compass-point-" + compassPoints[j] + "'" + (empty(displayColor) ? "" : " style='color: " + displayColor + ";'") + "><span class='far fa-" + iconCode +"'></span></div>");
                                        }
                                    }
                                    break;
                                case "DOUBLE_BORDER":
                                    for (var j in compassPoints) {
                                        if (empty(compassPoints[j])) {
                                            continue;
                                        }
                                        const borderSide = getBorderSide(compassPoints[j]);
                                        if (!empty(borderSide)) {
                                            $cell.addClass("double-border-" + borderSide);
                                            if (!empty(displayColor)) {
                                                $cell.css("border-" + borderSide + "-color", displayColor);
                                            }
                                        }
                                    }
                                    break;
                                case "DOWN_RIGHT_ARROW":
                                case "DOWN_LEFT_ARROW":
                                case "UP_RIGHT_ARROW":
                                case "UP_LEFT_ARROW":
                                    let arrowClass = featureCode.replace(new RegExp("_", 'g'), "-").toLowerCase();
                                    $cell.append("<div class='additional-element " + arrowClass + "'" + (empty(displayColor) ? "" : " style='color: " + displayColor + ";'") + "><span class='far fa-long-arrow-right'></span></div>");
                                    if (!empty(textData)) {
                                        $cell.append("<div class='additional-element " + arrowClass + "-text-data'" + (empty(displayColor) ? "" : " style='color: " + displayColor + ";'") + ">" + textData + "</div>");
                                    }
                                    break;
                                case "INTERNAL_ICON":
                                    if (!empty(iconCode)) {
                                        $cell.append("<div class='additional-element internal-icon'" + (empty(displayColor) ? "" : " style='color: " + displayColor + ";'") + "><span class='far fa-" + iconCode + "'></span></div>");
                                    }
                                    break;
                            }
                        }
                    }
                }
                drawCages();
                drawLines();
            }

            function drawCages() {
                $(".cage-border").remove();
                const puzzleWidth = parseInt($("#puzzle_width").val());
                const puzzleHeight = parseInt($("#puzzle_height").val());
                for (let rowNumber = 1; rowNumber <= puzzleHeight; rowNumber++) {
                    for (let columnNumber = 1; columnNumber <= puzzleWidth; columnNumber++) {
                        const cageNumber = $("#cell_cage_number_" + rowNumber + "_" + columnNumber).val();
                        if (empty(cageNumber)) {
                            continue;
                        }
                        const cageTotal = $("#cell_cage_total_" + rowNumber + "_" + columnNumber).val();
                        let topBorder = false, rightBorder = false, bottomBorder = false, leftBorder = false;
                        if (rowNumber == 1 || $("#cell_cage_number_" + (rowNumber - 1) + "_" + columnNumber).val() != cageNumber) {
                            topBorder = true;
                        }
                        if (columnNumber == puzzleWidth || $("#cell_cage_number_" + rowNumber + "_" + (columnNumber + 1)).val() != cageNumber) {
                            rightBorder = true;
                        }
                        if (rowNumber == puzzleHeight || $("#cell_cage_number_" + (rowNumber + 1) + "_" + columnNumber).val() != cageNumber) {
                            bottomBorder = true;
                        }
                        if (columnNumber == 1 || $("#cell_cage_number_" + rowNumber + "_" + (columnNumber - 1)).val() != cageNumber) {
                            leftBorder = true;
                        }
                        if (!topBorder && !rightBorder && !bottomBorder && !leftBorder) {
                            continue;
                        }
                        $cell = $("#sudoku_puzzle_cell_" + rowNumber + "_" + columnNumber);
                        $cell.append("<div class='additional-element cage-border" + (topBorder ? " top-border" : "") + (rightBorder ? " right-border" : "") + (bottomBorder ? " bottom-border" : "") + (leftBorder ? " left-border" : "") + "'>" +
                            (empty(cageTotal) ? "" : "<span class='cage-total'>" + cageTotal + "</span>") + "</div>")
                    }
                }
            }

            function drawLines() {
                $(".puzzle-line").remove();
                let lineContent = [];
                $("#sudoku_puzzle_lines").find(".line-display").each(function() {
                    lineContent.push($(this).find(".line-data").val());
                });
                for (var i in lineContent) {
                    if (empty(lineContent[i])) {
                        continue;
                    }
                    parts = lineContent[i].split("|");
                    let lineNumber = "";
                    let lineStartType = "";
                    let lineEndType = "";
                    let displayColor = "";
                    let lineWidth = "";
                    let lineSegments = [];
                    let lineSegmentCount = 0;
                    for (var j in parts) {
                        if (j == 0) {
                            lineNumber = parts[j];
                        } else if (j == 1) {
                            lineStartType = parts[j];
                        } else if (j == 2) {
                            lineEndType = parts[j];
                        } else if (j == 3) {
                            displayColor = parts[j];
                            if (empty(displayColor)) {
                                displayColor = "#000";
                            }
                        } else if (j == 4) {
                            lineWidth = parts[j];
                            if (empty(lineWidth)) {
                                lineWidth = 1;
                            }
                        } else {
                            lineSegmentCount++;
                            let cellParts = parts[j].split(',');
                            lineSegments.push({ row_number: cellParts[0], column_number: cellParts[1] });
                        }
                    }
                    if (lineSegmentCount == 0) {
                        continue;
                    }
                    let previousCell = false;
                    let lastRowNumber = 0;
                    let lastColumnNumber = 0;
                    let compassPoint = "";
                    for (var i in lineSegments) {
                        lastRowNumber = lineSegments[i].row_number;
                        lastColumnNumber = lineSegments[i].column_number;
                        if (previousCell !== false) {
                            // going out of previous cell segment
                            compassPoint = "";
                            if (previousCell.row_number < lineSegments[i].row_number) {
                                compassPoint += "s";
                            } else if (previousCell.row_number > lineSegments[i].row_number) {
                                compassPoint += "n";
                            }
                            if (previousCell.column_number < lineSegments[i].column_number) {
                                compassPoint += "e";
                            } else if (previousCell.column_number > lineSegments[i].column_number) {
                                compassPoint += "w";
                            }
                            const degrees = getRotateDegrees(compassPoint);
                            $("#sudoku_puzzle_cell_" + previousCell.row_number + "_" + previousCell.column_number).append("<div class='puzzle-line line-segment-" + compassPoint +
                                "' style='height: " + lineWidth + "px; background-color: " + displayColor + "; border-radius: " + lineWidth + "px; " +
                                "transform-origin: " + (lineWidth / 2) + "px 50%; transform: translate(-" + (lineWidth / 2) + "px,-50%) rotate(" + degrees + "deg); width: calc(" + (compassPoint.length == 2 ? "142%" : "100%") + " + " + lineWidth + "px);'></div>");
                        }
                        if (i == 0) {
                            switch (lineStartType) {
                                case "thermo":
                                    $("#sudoku_puzzle_cell_" + lineSegments[i].row_number + "_" + lineSegments[i].column_number).append("<div class='puzzle-line'><div style='width: 55px; height: 55px; background-color: " + displayColor + "; border-radius: 50%; top: 50%; left: 50%; position: absolute; transform: translate(-50%,-50%);'></div>");
                                    break;
                                case "open-thermo":
                                    $("#sudoku_puzzle_cell_" + lineSegments[i].row_number + "_" + lineSegments[i].column_number).append("<div class='puzzle-line' style='z-index: 500'><div style='width: 55px; height: 55px; background-color: rgb(255,255,255); border: 4px solid " + displayColor + "; border-radius: 50%; top: 50%; left: 50%; position: absolute; transform: translate(-50%,-50%);'></div>");
                                    break;
                            }
                        }
                        previousCell = lineSegments[i];
                    }
                    switch (lineEndType) {
                        case "arrow":
                            $("#sudoku_puzzle_cell_" + lastRowNumber + "_" + lastColumnNumber).append("<div class='puzzle-line compass-point-" + compassPoint + "'><div style='width: 40px; height: 40px; top: 50%; left: 50%; position: absolute; transform: translate(-50%,-50%); font-size: 3rem; color: " + displayColor + ";'><span class='fas fa-arrow-right'></span></div>");
                            break;
                    }
                }
            }

            function getRotateDegrees(compassPoint) {
                switch (compassPoint) {
                    case "n":
                        return 270;
                    case "ne":
                        return 315;
                    case "e":
                        return 0;
                    case "se":
                        return 45;
                    case "s":
                        return 90;
                    case "sw":
                        return 135;
                    case "w":
                        return 180;
                    case "nw":
                        return 225;
                }
                return 0;
            }

            function getBorderSide(compassPoint) {
                let borderSide = "";
                switch (compassPoint) {
                    case "n":
                        borderSide = "top";
                        break;
                    case "e":
                        borderSide = "right";
                        break;
                    case "s":
                        borderSide = "bottom";
                        break;
                    case "w":
                        borderSide = "left";
                        break;
                }
                return borderSide;
            }

            function drawBlocks() {
                const puzzleWidth = parseInt($("#puzzle_width").val());
                const puzzleHeight = parseInt($("#puzzle_height").val());
                for (let rowNumber = 1; rowNumber <= puzzleHeight; rowNumber++) {
                    for (let columnNumber = 1; columnNumber <= puzzleWidth; columnNumber++) {
                        const blockNumber = $("#sudoku_puzzle_cell_" + rowNumber + "_" + columnNumber).find(".cell-block-number").val();
                        const overBlockNumber = $("#sudoku_puzzle_cell_" + (rowNumber - 1) + "_" + columnNumber).find(".cell-block-number").val();
                        const leftBlockNumber = $("#sudoku_puzzle_cell_" + rowNumber + "_" + (columnNumber - 1)).find(".cell-block-number").val();
                        if (rowNumber > 1) {
                            if (overBlockNumber == blockNumber) {
                                $("#sudoku_puzzle_cell_" + rowNumber + "_" + columnNumber).removeClass("top-edge-cell");
                            } else {
                                $("#sudoku_puzzle_cell_" + rowNumber + "_" + columnNumber).addClass("top-edge-cell");
                            }
                        }
                        if (columnNumber > 1) {
                            if (leftBlockNumber == blockNumber) {
                                $("#sudoku_puzzle_cell_" + rowNumber + "_" + columnNumber).removeClass("left-edge-cell");
                            } else {
                                $("#sudoku_puzzle_cell_" + rowNumber + "_" + columnNumber).addClass("left-edge-cell");
                            }
                        }
                    }
                }
            }

            function createGrid(gridWidth,gridHeight,noDefaults) {
                if (empty(noDefaults)) {
                    noDefaults = false;
                }
                $("#puzzle_width").val(gridWidth);
                $("#puzzle_height").val(gridHeight);
                let gridTable = "<table id='puzzle_grid_table'>";
                for (let rowNumber = 0; rowNumber <= (gridHeight + 1); rowNumber++) {
                    gridTable += "<tr id='grid_row_" + rowNumber + "'>";
                    for (let columnNumber = 0; columnNumber <= (gridWidth + 1); columnNumber++) {
                        let puzzleCell = $("#sudoku_puzzle_cell").html();
                        let classes = "";
                        let blockNumber = "";
                        if (columnNumber == 0 || rowNumber == 0 || columnNumber > gridWidth || rowNumber > gridHeight) {
                            classes += " outer-cell";
                        } else if (gridWidth == 9 && gridHeight == 9 && !noDefaults) {
                            if (columnNumber > 0 && rowNumber > 0 && columnNumber <= 9 && rowNumber <= 9) {
                                blockNumber = (Math.floor((rowNumber - 1) / 3) * 3) + Math.floor((columnNumber - 1) / 3) + 1;
                            }
                            if (columnNumber == 1 || columnNumber == 4 || columnNumber == 7) {
                                classes += " left-edge-cell";
                            }
                            if (rowNumber == 1 || rowNumber == 4 || rowNumber == 7) {
                                classes += " top-edge-cell";
                            }
                            if (columnNumber == 9) {
                                classes += " right-edge-cell";
                            }
                            if (rowNumber == 9) {
                                classes += " bottom-edge-cell";
                            }
                        } else {
                            if (columnNumber == 1) {
                                classes += " left-edge-cell";
                            }
                            if (rowNumber == 1) {
                                classes += " top-edge-cell";
                            }
                            if (columnNumber == gridWidth) {
                                classes += " right-edge-cell";
                            }
                            if (rowNumber == gridHeight) {
                                classes += " bottom-edge-cell";
                            }
                        }
                        puzzleCell = puzzleCell.replace(new RegExp("%row_number%", 'g'), rowNumber);
                        puzzleCell = puzzleCell.replace(new RegExp("%column_number%", 'g'), columnNumber);
                        puzzleCell = puzzleCell.replace(new RegExp("%block_number%", 'g'), blockNumber);
                        puzzleCell = puzzleCell.replace(new RegExp("%classes%", 'g'), classes);
                        gridTable += "<td>" + puzzleCell + "</td>";
                    }

                }
                gridTable += "</table><p><input checked type='checkbox' id='edit_values' name='edit_values' value='1'><label class='checkbox-label' for='edit_values'>Edit Values (Turn off to add cell features)</label></p>";
                gridTable += "<p id='button_block'><button id='clear_blocks'>Clear Blocks</button><button id='redraw_blocks'>Redraw Blocks</button><button id='draw_cages'>Edit Cages</button><button id='draw_lines'>Draw Lines & Thermometers</button></p>"
                $("#grid_wrapper").html(gridTable);
            }

            const iconList = [ "abacus", "acorn", "ad", "address-book", "address-card", "adjust", "air-conditioner", "air-freshener", "alarm-clock",
                "alarm-exclamation", "alarm-plus", "alarm-snooze", "album-collection", "album", "alicorn", "alien-monster", "alien", "align-center", "align-justify",
                "align-left", "align-right", "align-slash", "allergies", "ambulance", "american-sign-language-interpreting", "amp-guitar", "analytics", "anchor",
                "angel", "angle-double-down", "angle-double-left", "angle-double-right", "angle-double-up", "angle-down", "angle-left", "angle-right", "angle-up",
                "angry", "ankh", "apple-alt", "apple-crate", "archive", "archway", "arrow-alt-circle-down", "arrow-alt-circle-left", "arrow-alt-circle-right",
                "arrow-alt-circle-up", "arrow-alt-down", "arrow-alt-from-bottom", "arrow-alt-from-left", "arrow-alt-from-right", "arrow-alt-from-top",
                "arrow-alt-left", "arrow-alt-right", "arrow-alt-square-down", "arrow-alt-square-left", "arrow-alt-square-right", "arrow-alt-square-up",
                "arrow-alt-to-bottom", "arrow-alt-to-left", "arrow-alt-to-right", "arrow-alt-to-top", "arrow-alt-up", "arrow-circle-down", "arrow-circle-left",
                "arrow-circle-right", "arrow-circle-up", "arrow-down", "arrow-from-bottom", "arrow-from-left", "arrow-from-right", "arrow-from-top", "arrow-left",
                "arrow-right", "arrow-square-down", "arrow-square-left", "arrow-square-right", "arrow-square-up", "arrow-to-bottom", "arrow-to-left",
                "arrow-to-right", "arrow-to-top", "arrow-up", "arrows-alt-h", "arrows-alt-v", "arrows-alt", "arrows-h", "arrows-v", "arrows",
                "assistive-listening-systems", "asterisk", "at", "atlas", "atom-alt", "atom", "audio-description", "award", "axe-battle", "axe", "baby-carriage",
                "baby", "backpack", "backspace", "backward", "bacon", "bacteria", "bacterium", "badge-check", "badge-dollar", "badge-percent", "badge-sheriff",
                "badge", "badger-honey", "bags-shopping", "bahai", "balance-scale-left", "balance-scale-right", "balance-scale", "ball-pile", "ballot-check",
                "ballot", "ban", "band-aid", "banjo", "barcode-alt", "barcode-read", "barcode-scan", "barcode", "bars", "baseball-ball", "baseball",
                "basketball-ball", "basketball-hoop", "bat", "bath", "battery-bolt", "battery-empty", "battery-full", "battery-half", "battery-quarter",
                "battery-slash", "battery-three-quarters", "bed-alt", "bed-bunk", "bed-empty", "bed", "beer", "bell-exclamation", "bell-on", "bell-plus",
                "bell-school-slash", "bell-school", "bell-slash", "bell", "bells", "betamax", "bezier-curve", "bible", "bicycle", "biking-mountain", "biking",
                "binoculars", "biohazard", "birthday-cake", "blanket", "blender-phone", "blender", "blind", "blinds-open", "blinds-raised", "blinds", "blog", "bold",
                "bolt", "bomb", "bone-break", "bone", "bong", "book-alt", "book-dead", "book-heart", "book-medical", "book-open", "book-reader", "book-spells",
                "book-user", "book", "bookmark", "books-medical", "books", "boombox", "boot", "booth-curtain", "border-all", "border-bottom", "border-center-h",
                "border-center-v", "border-inner", "border-left", "border-none", "border-outer", "border-right", "border-style-alt", "border-style", "border-top",
                "bow-arrow", "bowling-ball", "bowling-pins", "box-alt", "box-ballot", "box-check", "box-fragile", "box-full", "box-heart", "box-open", "box-tissue",
                "box-up", "box-usd", "box", "boxes-alt", "boxes", "boxing-glove", "brackets-curly", "brackets", "braille", "brain", "bread-loaf", "bread-slice",
                "briefcase-medical", "briefcase", "bring-forward", "bring-front", "broadcast-tower", "broom", "browser", "brush", "bug", "building", "bullhorn",
                "bullseye-arrow", "bullseye-pointer", "bullseye", "burger-soda", "burn", "burrito", "bus-alt", "bus-school", "bus", "business-time", "cabinet-filing",
                "cactus", "calculator-alt", "calculator", "calendar-alt", "calendar-check", "calendar-day", "calendar-edit", "calendar-exclamation", "calendar-minus",
                "calendar-plus", "calendar-star", "calendar-times", "calendar-week", "calendar", "camcorder", "camera-alt", "camera-home", "camera-movie",
                "camera-polaroid", "camera-retro", "camera", "campfire", "campground", "candle-holder", "candy-cane", "candy-corn", "cannabis", "capsules", "car-alt",
                "car-battery", "car-building", "car-bump", "car-bus", "car-crash", "car-garage", "car-mechanic", "car-side", "car-tilt", "car-wash", "car",
                "caravan-alt", "caravan", "caret-circle-down", "caret-circle-left", "caret-circle-right", "caret-circle-up", "caret-down", "caret-left",
                "caret-right", "caret-square-down", "caret-square-left", "caret-square-right", "caret-square-up", "caret-up", "carrot", "cars", "cart-arrow-down",
                "cart-plus", "cash-register", "cassette-tape", "cat-space", "cat", "cauldron", "cctv", "certificate", "chair-office", "chair", "chalkboard-teacher",
                "chalkboard", "charging-station", "chart-area", "chart-bar", "chart-line-down", "chart-line", "chart-network", "chart-pie-alt", "chart-pie",
                "chart-scatter", "check-circle", "check-double", "check-square", "check", "cheese-swiss", "cheese", "cheeseburger", "chess-bishop-alt",
                "chess-bishop", "chess-board", "chess-clock-alt", "chess-clock", "chess-king-alt", "chess-king", "chess-knight-alt", "chess-knight", "chess-pawn-alt",
                "chess-pawn", "chess-queen-alt", "chess-queen", "chess-rook-alt", "chess-rook", "chess", "chevron-circle-down", "chevron-circle-left",
                "chevron-circle-right", "chevron-circle-up", "chevron-double-down", "chevron-double-left", "chevron-double-right", "chevron-double-up",
                "chevron-down", "chevron-left", "chevron-right", "chevron-square-down", "chevron-square-left", "chevron-square-right", "chevron-square-up",
                "chevron-up", "child", "chimney", "church", "circle-notch", "circle", "city", "clarinet", "claw-marks", "clinic-medical", "clipboard-check",
                "clipboard-list-check", "clipboard-list", "clipboard-prescription", "clipboard-user", "clipboard", "clock", "clone", "closed-captioning",
                "cloud-download-alt", "cloud-download", "cloud-drizzle", "cloud-hail-mixed", "cloud-hail", "cloud-meatball", "cloud-moon-rain", "cloud-moon",
                "cloud-music", "cloud-rain", "cloud-rainbow", "cloud-showers-heavy", "cloud-showers", "cloud-sleet", "cloud-snow", "cloud-sun-rain", "cloud-sun",
                "cloud-upload-alt", "cloud-upload", "cloud", "clouds-moon", "clouds-sun", "clouds", "club", "cocktail", "code-branch", "code-commit", "code-merge",
                "code", "coffee-pot", "coffee-togo", "coffee", "coffin-cross", "coffin", "cog", "cogs", "coin", "coins", "columns", "comet", "comment-alt-check",
                "comment-alt-dollar", "comment-alt-dots", "comment-alt-edit", "comment-alt-exclamation", "comment-alt-lines", "comment-alt-medical",
                "comment-alt-minus", "comment-alt-music", "comment-alt-plus", "comment-alt-slash", "comment-alt-smile", "comment-alt-times", "comment-alt",
                "comment-check", "comment-dollar", "comment-dots", "comment-edit", "comment-exclamation", "comment-lines", "comment-medical", "comment-minus",
                "comment-music", "comment-plus", "comment-slash", "comment-smile", "comment-times", "comment", "comments-alt-dollar", "comments-alt",
                "comments-dollar", "comments", "compact-disc", "compass-slash", "compass", "compress-alt", "compress-arrows-alt", "compress-wide", "compress",
                "computer-classic", "computer-speaker", "concierge-bell", "construction", "container-storage", "conveyor-belt-alt", "conveyor-belt", "cookie-bite",
                "cookie", "copy", "copyright", "corn", "couch", "cow", "cowbell-more", "cowbell", "credit-card-blank", "credit-card-front", "credit-card", "cricket",
                "croissant", "crop-alt", "crop", "cross", "crosshairs", "crow", "crown", "crutch", "crutches", "cube", "cubes", "curling", "cut", "dagger",
                "database", "deaf", "debug", "deer-rudolph", "deer", "democrat", "desktop-alt", "desktop", "dewpoint", "dharmachakra", "diagnoses", "diamond",
                "dice-d10", "dice-d12", "dice-d20", "dice-d4", "dice-d6", "dice-d8", "dice-five", "dice-four", "dice-one", "dice-six", "dice-three", "dice-two",
                "dice", "digging", "digital-tachograph", "diploma", "directions", "disc-drive", "disease", "divide", "dizzy", "dna", "do-not-enter", "dog-leashed",
                "dog", "dollar-sign", "dolly-empty", "dolly-flatbed-alt", "dolly-flatbed-empty", "dolly-flatbed", "dolly", "donate", "door-closed", "door-open",
                "dot-circle", "dove", "download", "drafting-compass", "dragon", "draw-circle", "draw-polygon", "draw-square", "dreidel", "drone-alt", "drone",
                "drum-steelpan", "drum", "drumstick-bite", "drumstick", "dryer-alt", "dryer", "duck", "dumbbell", "dumpster-fire", "dumpster", "dungeon", "ear-muffs",
                "ear", "eclipse-alt", "eclipse", "edit", "egg-fried", "egg", "eject", "elephant", "ellipsis-h-alt", "ellipsis-h", "ellipsis-v-alt", "ellipsis-v",
                "empty-set", "engine-warning", "envelope-open-dollar", "envelope-open-text", "envelope-open", "envelope-square", "envelope", "equals", "eraser",
                "ethernet", "euro-sign", "exchange-alt", "exchange", "exclamation-circle", "exclamation-square", "exclamation-triangle", "exclamation", "expand-alt",
                "expand-arrows-alt", "expand-arrows", "expand-wide", "expand", "external-link-alt", "external-link-square-alt", "external-link-square",
                "external-link", "eye-dropper", "eye-evil", "eye-slash", "eye", "fan-table", "fan", "farm", "fast-backward", "fast-forward", "faucet-drip", "faucet",
                "fax", "feather-alt", "feather", "female", "field-hockey", "fighter-jet", "file-alt", "file-archive", "file-audio", "file-certificate",
                "file-chart-line", "file-chart-pie", "file-check", "file-code", "file-contract", "file-csv", "file-download", "file-edit", "file-excel",
                "file-exclamation", "file-export", "file-image", "file-import", "file-invoice-dollar", "file-invoice", "file-medical-alt", "file-medical",
                "file-minus", "file-music", "file-pdf", "file-plus", "file-powerpoint", "file-prescription", "file-search", "file-signature", "file-spreadsheet",
                "file-times", "file-upload", "file-user", "file-video", "file-word", "file", "files-medical", "fill-drip", "fill", "film-alt", "film-canister",
                "film", "filter", "fingerprint", "fire-alt", "fire-extinguisher", "fire-smoke", "fire", "fireplace", "first-aid", "fish-cooked", "fish",
                "fist-raised", "flag-alt", "flag-checkered", "flag-usa", "flag", "flame", "flashlight", "flask-poison", "flask-potion", "flask", "flower-daffodil",
                "flower-tulip", "flower", "flushed", "flute", "flux-capacitor", "fog", "folder-download", "folder-minus", "folder-open", "folder-plus",
                "folder-times", "folder-tree", "folder-upload", "folder", "folders", "font-case", "font", "football-ball", "football-helmet", "forklift", "forward",
                "fragile", "french-fries", "frog", "frosty-head", "frown-open", "frown", "function", "funnel-dollar", "futbol", "galaxy", "game-board-alt",
                "game-board", "game-console-handheld", "gamepad-alt", "gamepad", "garage-car", "garage-open", "garage", "gas-pump-slash", "gas-pump", "gavel", "gem",
                "genderless", "ghost", "gift-card", "gift", "gifts", "gingerbread-man", "glass-champagne", "glass-cheers", "glass-citrus", "glass-martini-alt",
                "glass-martini", "glass-whiskey-rocks", "glass-whiskey", "glass", "glasses-alt", "glasses", "globe-africa", "globe-americas", "globe-asia",
                "globe-europe", "globe-snow", "globe-stand", "globe", "golf-ball", "golf-club", "gopuram", "graduation-cap", "gramophone", "greater-than-equal",
                "greater-than", "grimace", "grin-alt", "grin-beam-sweat", "grin-beam", "grin-hearts", "grin-squint-tears", "grin-squint", "grin-stars", "grin-tears",
                "grin-tongue-squint", "grin-tongue-wink", "grin-tongue", "grin-wink", "grin", "grip-horizontal", "grip-lines-vertical", "grip-lines", "grip-vertical",
                "guitar-electric", "guitar", "guitars", "h-square", "h1", "h2", "h3", "h4", "hamburger", "hammer-war", "hammer", "hamsa", "hand-heart",
                "hand-holding-box", "hand-holding-heart", "hand-holding-magic", "hand-holding-medical", "hand-holding-seedling", "hand-holding-usd",
                "hand-holding-water", "hand-holding", "hand-lizard", "hand-middle-finger", "hand-paper", "hand-peace", "hand-point-down", "hand-point-left",
                "hand-point-right", "hand-point-up", "hand-pointer", "hand-receiving", "hand-rock", "hand-scissors", "hand-sparkles", "hand-spock", "hands-heart",
                "hands-helping", "hands-usd", "hands-wash", "hands", "handshake-alt-slash", "handshake-alt", "handshake-slash", "handshake", "hanukiah", "hard-hat",
                "hashtag", "hat-chef", "hat-cowboy-side", "hat-cowboy", "hat-santa", "hat-winter", "hat-witch", "hat-wizard", "hdd", "head-side-brain",
                "head-side-cough-slash", "head-side-cough", "head-side-headphones", "head-side-mask", "head-side-medical", "head-side-virus", "head-side", "head-vr",
                "heading", "headphones-alt", "headphones", "headset", "heart-broken", "heart-circle", "heart-rate", "heart-square", "heart", "heartbeat", "heat",
                "helicopter", "helmet-battle", "hexagon", "highlighter", "hiking", "hippo", "history", "hockey-mask", "hockey-puck", "hockey-sticks", "holly-berry",
                "home-alt", "home-heart", "home-lg-alt", "home-lg", "home", "hood-cloak", "horizontal-rule", "horse-head", "horse-saddle", "horse", "hospital-alt",
                "hospital-symbol", "hospital-user", "hospital", "hospitals", "hot-tub", "hotdog", "hotel", "hourglass-end", "hourglass-half", "hourglass-start",
                "hourglass", "house-damage", "house-day", "house-flood", "house-leave", "house-night", "house-return", "house-signal", "house-user", "house",
                "hryvnia", "humidity", "hurricane", "i-cursor", "ice-cream", "ice-skate", "icicles", "icons-alt", "icons", "id-badge", "id-card-alt", "id-card",
                "igloo", "image-polaroid", "image", "images", "inbox-in", "inbox-out", "inbox", "indent", "industry-alt", "industry", "infinity", "info-circle",
                "info-square", "info", "inhaler", "integral", "intersection", "inventory", "island-tropical", "italic", "jack-o-lantern", "jedi", "joint",
                "journal-whills", "joystick", "jug", "kaaba", "kazoo", "kerning", "key-skeleton", "key", "keyboard", "keynote", "khanda", "kidneys", "kiss-beam",
                "kiss-wink-heart", "kiss", "kite", "kiwi-bird", "knife-kitchen", "lambda", "lamp-desk", "lamp-floor", "lamp", "landmark-alt", "landmark", "language",
                "laptop-code", "laptop-house", "laptop-medical", "laptop", "lasso", "laugh-beam", "laugh-squint", "laugh-wink", "laugh", "layer-group", "layer-minus",
                "layer-plus", "leaf-heart", "leaf-maple", "leaf-oak", "leaf", "lemon", "less-than-equal", "less-than", "level-down-alt", "level-down", "level-up-alt",
                "level-up", "life-ring", "light-ceiling", "light-switch-off", "light-switch-on", "light-switch", "lightbulb-dollar", "lightbulb-exclamation",
                "lightbulb-on", "lightbulb-slash", "lightbulb", "lights-holiday", "line-columns", "line-height", "link", "lips", "lira-sign", "list-alt",
                "list-music", "list-ol", "list-ul", "list", "location-arrow", "location-circle", "location-slash", "location", "lock-alt", "lock-open-alt",
                "lock-open", "lock", "long-arrow-alt-down", "long-arrow-alt-left", "long-arrow-alt-right", "long-arrow-alt-up", "long-arrow-down", "long-arrow-left",
                "long-arrow-right", "long-arrow-up", "loveseat", "low-vision", "luchador", "luggage-cart", "lungs-virus", "lungs", "mace", "magic", "magnet",
                "mail-bulk", "mailbox", "male", "mandolin", "map-marked-alt", "map-marked", "map-marker-alt-slash", "map-marker-alt", "map-marker-check",
                "map-marker-edit", "map-marker-exclamation", "map-marker-minus", "map-marker-plus", "map-marker-question", "map-marker-slash", "map-marker-smile",
                "map-marker-times", "map-marker", "map-pin", "map-signs", "map", "marker", "mars-double", "mars-stroke-h", "mars-stroke-v", "mars-stroke", "mars",
                "mask", "meat", "medal", "medkit", "megaphone", "meh-blank", "meh-rolling-eyes", "meh", "memory", "menorah", "mercury", "meteor", "microchip",
                "microphone-alt-slash", "microphone-alt", "microphone-slash", "microphone-stand", "microphone", "microscope", "microwave", "mind-share",
                "minus-circle", "minus-hexagon", "minus-octagon", "minus-square", "minus", "mistletoe", "mitten", "mobile-alt", "mobile-android-alt",
                "mobile-android", "mobile", "money-bill-alt", "money-bill-wave-alt", "money-bill-wave", "money-bill", "money-check-alt", "money-check-edit-alt",
                "money-check-edit", "money-check", "monitor-heart-rate", "monkey", "monument", "moon-cloud", "moon-stars", "moon", "mortar-pestle", "mosque",
                "motorcycle", "mountain", "mountains", "mouse-alt", "mouse-pointer", "mouse", "mp3-player", "mug-hot", "mug-marshmallows", "mug-tea", "mug",
                "music-alt-slash", "music-alt", "music-slash", "music", "narwhal", "network-wired", "neuter", "newspaper", "not-equal", "notes-medical",
                "object-group", "object-ungroup", "octagon", "oil-can", "oil-temp", "om", "omega", "ornament", "otter", "outdent", "outlet", "oven", "overline",
                "page-break", "pager", "paint-brush-alt", "paint-brush", "paint-roller", "palette", "pallet-alt", "pallet", "paper-plane", "paperclip",
                "parachute-box", "paragraph-rtl", "paragraph", "parking-circle-slash", "parking-circle", "parking-slash", "parking", "passport", "pastafarianism",
                "paste", "pause-circle", "pause", "paw-alt", "paw-claws", "paw", "peace", "pegasus", "pen-alt", "pen-fancy", "pen-nib", "pen-square", "pen",
                "pencil-alt", "pencil-paintbrush", "pencil-ruler", "pencil", "pennant", "people-arrows", "people-carry", "pepper-hot", "percent", "percentage",
                "person-booth", "person-carry", "person-dolly-empty", "person-dolly", "person-sign", "phone-alt", "phone-laptop", "phone-office", "phone-plus",
                "phone-rotary", "phone-slash", "phone-square-alt", "phone-square", "phone-volume", "phone", "photo-video", "pi", "piano-keyboard", "piano", "pie",
                "pig", "piggy-bank", "pills", "pizza-slice", "pizza", "place-of-worship", "plane-alt", "plane-arrival", "plane-departure", "plane-slash", "plane",
                "planet-moon", "planet-ringed", "play-circle", "play", "plug", "plus-circle", "plus-hexagon", "plus-octagon", "plus-square", "plus", "podcast",
                "podium-star", "podium", "police-box", "poll-h", "poll-people", "poll", "poo-storm", "poo", "poop", "popcorn", "portal-enter", "portal-exit",
                "portrait", "pound-sign", "power-off", "pray", "praying-hands", "prescription-bottle-alt", "prescription-bottle", "prescription", "presentation",
                "print-search", "print-slash", "print", "procedures", "project-diagram", "projector", "pump-medical", "pump-soap", "pumpkin", "puzzle-piece",
                "qrcode", "question-circle", "question-square", "question", "quidditch", "quote-left", "quote-right", "quran", "rabbit-fast", "rabbit", "racquet",
                "radar", "radiation-alt", "radiation", "radio-alt", "radio", "rainbow", "raindrops", "ram", "ramp-loading", "random", "raygun", "receipt",
                "record-vinyl", "rectangle-landscape", "rectangle-portrait", "rectangle-wide", "recycle", "redo-alt", "redo", "refrigerator", "registered",
                "remove-format", "repeat-1-alt", "repeat-1", "repeat-alt", "repeat", "reply-all", "reply", "republican", "restroom", "retweet-alt", "retweet",
                "ribbon", "ring", "rings-wedding", "road", "robot", "rocket-launch", "rocket", "route-highway", "route-interstate", "route", "router", "rss-square",
                "rss", "ruble-sign", "ruler-combined", "ruler-horizontal", "ruler-triangle", "ruler-vertical", "ruler", "running", "rupee-sign", "rv", "sack-dollar",
                "sack", "sad-cry", "sad-tear", "salad", "sandwich", "satellite-dish", "satellite", "sausage", "save", "sax-hot", "saxophone", "scalpel-path",
                "scalpel", "scanner-image", "scanner-keyboard", "scanner-touchscreen", "scanner", "scarecrow", "scarf", "school", "screwdriver", "scroll-old",
                "scroll", "scrubber", "scythe", "sd-card", "search-dollar", "search-location", "search-minus", "search-plus", "search", "seedling", "send-back",
                "send-backward", "sensor-alert", "sensor-fire", "sensor-on", "sensor-smoke", "sensor", "server", "shapes", "share-all", "share-alt-square",
                "share-alt", "share-square", "share", "sheep", "shekel-sign", "shield-alt", "shield-check", "shield-cross", "shield-virus", "shield", "ship",
                "shipping-fast", "shipping-timed", "shish-kebab", "shoe-prints", "shopping-bag", "shopping-basket", "shopping-cart", "shovel-snow", "shovel",
                "shower", "shredder", "shuttle-van", "shuttlecock", "sickle", "sigma", "sign-in-alt", "sign-in", "sign-language", "sign-out-alt", "sign-out", "sign",
                "signal-1", "signal-2", "signal-3", "signal-4", "signal-alt-1", "signal-alt-2", "signal-alt-3", "signal-alt-slash", "signal-alt", "signal-slash",
                "signal-stream", "signal", "signature", "sim-card", "sink", "siren-on", "siren", "sitemap", "skating", "skeleton", "ski-jump", "ski-lift",
                "skiing-nordic", "skiing", "skull-cow", "skull-crossbones", "skull", "slash", "sledding", "sleigh", "sliders-h-square", "sliders-h",
                "sliders-v-square", "sliders-v", "smile-beam", "smile-plus", "smile-wink", "smile", "smog", "smoke", "smoking-ban", "smoking", "sms", "snake",
                "snooze", "snow-blowing", "snowboarding", "snowflake", "snowflakes", "snowman", "snowmobile", "snowplow", "soap", "socks", "solar-panel",
                "solar-system", "sort-alpha-down-alt", "sort-alpha-down", "sort-alpha-up-alt", "sort-alpha-up", "sort-alt", "sort-amount-down-alt",
                "sort-amount-down", "sort-amount-up-alt", "sort-amount-up", "sort-circle-down", "sort-circle-up", "sort-circle", "sort-down", "sort-numeric-down-alt",
                "sort-numeric-down", "sort-numeric-up-alt", "sort-numeric-up", "sort-shapes-down-alt", "sort-shapes-down", "sort-shapes-up-alt", "sort-shapes-up",
                "sort-size-down-alt", "sort-size-down", "sort-size-up-alt", "sort-size-up", "sort-up", "sort", "soup", "spa", "space-shuttle",
                "space-station-moon-alt", "space-station-moon", "spade", "sparkles", "speaker", "speakers", "spell-check", "spider-black-widow", "spider-web",
                "spider", "spinner-third", "spinner", "splotch", "spray-can", "sprinkler", "square-full", "square-root-alt", "square-root", "square", "squirrel",
                "staff", "stamp", "star-and-crescent", "star-christmas", "star-exclamation", "star-half-alt", "star-half", "star-of-david", "star-of-life",
                "star-shooting", "star", "starfighter-alt", "starfighter", "stars", "starship-freighter", "starship", "steak", "steering-wheel", "step-backward",
                "step-forward", "stethoscope", "sticky-note", "stocking", "stomach", "stop-circle", "stop", "stopwatch-20", "stopwatch", "store-alt-slash",
                "store-alt", "store-slash", "store", "stream", "street-view", "stretcher", "strikethrough", "stroopwafel", "subscript", "subway", "suitcase-rolling",
                "suitcase", "sun-cloud", "sun-dust", "sun-haze", "sun", "sunglasses", "sunrise", "sunset", "superscript", "surprise", "swatchbook", "swimmer",
                "swimming-pool", "sword-laser-alt", "sword-laser", "sword", "swords-laser", "swords", "synagogue", "sync-alt", "sync", "syringe", "table-tennis",
                "table", "tablet-alt", "tablet-android-alt", "tablet-android", "tablet-rugged", "tablet", "tablets", "tachometer-alt-average", "tachometer-alt-fast",
                "tachometer-alt-fastest", "tachometer-alt-slow", "tachometer-alt-slowest", "tachometer-alt", "tachometer-average", "tachometer-fast",
                "tachometer-fastest", "tachometer-slow", "tachometer-slowest", "tachometer", "taco", "tag", "tags", "tally", "tanakh", "tape", "tasks-alt", "tasks",
                "taxi", "teeth-open", "teeth", "telescope", "temperature-down", "temperature-frigid", "temperature-high", "temperature-hot", "temperature-low",
                "temperature-up", "tenge", "tennis-ball", "terminal", "text-height", "text-size", "text-width", "text", "th-large", "th-list", "th", "theater-masks",
                "thermometer-empty", "thermometer-full", "thermometer-half", "thermometer-quarter", "thermometer-three-quarters", "thermometer", "theta",
                "thumbs-down", "thumbs-up", "thumbtack", "thunderstorm-moon", "thunderstorm-sun", "thunderstorm", "ticket-alt", "ticket", "tilde", "times-circle",
                "times-hexagon", "times-octagon", "times-square", "times", "tint-slash", "tint", "tire-flat", "tire-pressure-warning", "tire-rugged", "tire", "tired",
                "toggle-off", "toggle-on", "toilet-paper-alt", "toilet-paper-slash", "toilet-paper", "toilet", "tombstone-alt", "tombstone", "toolbox", "tools",
                "tooth", "toothbrush", "torah", "torii-gate", "tornado", "tractor", "trademark", "traffic-cone", "traffic-light-go", "traffic-light-slow",
                "traffic-light-stop", "traffic-light", "trailer", "train", "tram", "transgender-alt", "transgender", "transporter-1", "transporter-2",
                "transporter-3", "transporter-empty", "transporter", "trash-alt", "trash-restore-alt", "trash-restore", "trash-undo-alt", "trash-undo", "trash",
                "treasure-chest", "tree-alt", "tree-christmas", "tree-decorated", "tree-large", "tree-palm", "tree", "trees", "triangle-music", "triangle",
                "trophy-alt", "trophy", "truck-container", "truck-couch", "truck-loading", "truck-monster", "truck-moving", "truck-pickup", "truck-plow",
                "truck-ramp", "truck", "trumpet", "tshirt", "tty", "turkey", "turntable", "turtle", "tv-alt", "tv-music", "tv-retro", "tv", "typewriter", "ufo-beam",
                "ufo", "umbrella-beach", "umbrella", "underline", "undo-alt", "undo", "unicorn", "union", "universal-access", "university", "unlink", "unlock-alt",
                "unlock", "upload", "usb-drive", "usd-circle", "usd-square", "user-alien", "user-alt-slash", "user-alt", "user-astronaut", "user-chart", "user-check",
                "user-circle", "user-clock", "user-cog", "user-cowboy", "user-crown", "user-edit", "user-friends", "user-graduate", "user-hard-hat", "user-headset",
                "user-injured", "user-lock", "user-md-chat", "user-md", "user-minus", "user-music", "user-ninja", "user-nurse", "user-plus", "user-robot",
                "user-secret", "user-shield", "user-slash", "user-tag", "user-tie", "user-times", "user-unlock", "user-visor", "user", "users-class", "users-cog",
                "users-crown", "users-medical", "users-slash", "users", "utensil-fork", "utensil-knife", "utensil-spoon", "utensils-alt", "utensils", "vacuum-robot",
                "vacuum", "value-absolute", "vector-square", "venus-double", "venus-mars", "venus", "vhs", "vial", "vials", "video-plus", "video-slash", "video",
                "vihara", "violin", "virus-slash", "virus", "viruses", "voicemail", "volcano", "volleyball-ball", "volume-down", "volume-mute", "volume-off",
                "volume-slash", "volume-up", "volume", "vote-nay", "vote-yea", "vr-cardboard", "wagon-covered", "walker", "walkie-talkie", "walking", "wallet",
                "wand-magic", "wand", "warehouse-alt", "warehouse", "washer", "watch-calculator", "watch-fitness", "watch", "water-lower", "water-rise", "water",
                "wave-sine", "wave-square", "wave-triangle", "waveform-path", "waveform", "webcam-slash", "webcam", "weight-hanging", "weight", "whale", "wheat",
                "wheelchair", "whistle", "wifi-1", "wifi-2", "wifi-slash", "wifi", "wind-turbine", "wind-warning", "wind", "window-alt", "window-close",
                "window-frame-open", "window-frame", "window-maximize", "window-minimize", "window-restore", "window", "windsock", "wine-bottle", "wine-glass-alt",
                "wine-glass", "won-sign", "wreath", "wrench", "x-ray", "yen-sign", "yin-yang" ];
        </script>
        <?php
    }

	function hiddenElements() {
		?>
        <div id="sudoku_puzzle_cell">
            <div class='sudoku-puzzle-cell%classes%' id="sudoku_puzzle_cell_%row_number%_%column_number%" data-row_number="%row_number%" data-column_number="%column_number%">
                <input type='hidden' class='cell-readonly' id='cell_readonly_%row_number%_%column_number%' name='cell_readonly_%row_number%_%column_number%' value=''>
                <input type='hidden' class='cell-display-color' id='cell_display_color_%row_number%_%column_number%' name='cell_display_color_%row_number%_%column_number%' value=''>
                <input type='hidden' class='cell-feature-ids' id='cell_feature_ids_%row_number%_%column_number%' name='cell_feature_ids_%row_number%_%column_number%' value=''>
                <input type='hidden' class='cell-block-number' id='cell_block_number_%row_number%_%column_number%' name='cell_block_number_%row_number%_%column_number%' value='%block_number%'>
                <input type='hidden' class='cell-cage-number' id='cell_cage_number_%row_number%_%column_number%' name='cell_cage_number_%row_number%_%column_number%' value=''>
                <input type='hidden' class='cell-cage-total' id='cell_cage_total_%row_number%_%column_number%' name='cell_cage_total_%row_number%_%column_number%' value=''>
                <input tabindex='10' type='text' data-row_number="%row_number%" data-column_number="%column_number%" class='cell-value' id='cell_value_%row_number%_%column_number%' name='cell_value_%row_number%_%column_number%' value=''>
            </div>
        </div>

        <div id="cage_dialog" class='dialog-box'>
            <h2>Cage Total</h2>
            <div class='form-line'>
                <label>Cage Total</label>
                <input type='text' size='6' maxlength="4" id='cage_total'>
            </div>
        </div>

        <div id="line_dialog" class='dialog-box'>
            <h2>Line/Thermometer Characteristics</h2>

            <div class='form-line'>
                <label>Line Start Type</label>
                <select id='line_start_type'>
                    <option value=''>Normal</option>
                    <option value='thermo'>Thermometer Bulb</option>
                    <option value='open_thermo'>Open Bulb</option>
                </select>
            </div>

            <div class='form-line'>
                <label>Line End Type</label>
                <select id='line_end_type'>
                    <option value=''>Normal</option>
                    <option value='arrow'>Arrow</option>
                </select>
            </div>

            <div class='form-line'>
                <label>Line Color</label>
                <input type='text' class='minicolors' id='line_end_type'>
            </div>

            <div class='form-line'>
                <label>Line Width</label>
                <input type='text' size='4' class='validate[custom[integer],min[1]] align-right' id='line_end_type' value='1'>
            </div>
        </div>

        <div id="icon_dialog" class='dialog-box'>
            <h2>Click an icon to select</h2>
            <input type="hidden" id="icon_dialog_feature_id" value="">
            <div id="icon_list"></div>
        </div>

        <div id="cell_features_dialog" class='dialog-box'>
            <h2>Cell at Row <span id='dialog_row_number'></span>, Column <span id='dialog_column_number'></span></h2>
            <div class='form-line'>
                <input type='checkbox' id="cell_readonly" name="cell_readonly" value="1"><label class='checkbox-label' for='cell_readonly'>Cell is not editable</label>
            </div>
            <div class='form-line'>
                <label>Cell Color</label>
                <input type='text' class='minicolors' id="cell_display_color">
            </div>
            <table class='grid-table' id="features_table">
                <tr>
                    <th>Choose Features</th>
                    <th>Icon</th>
                    <th>Cell Sides</th>
                    <th>Color</th>
                    <th>Text</th>
                </tr>
				<?php
				$resultSet = executeQuery("select * from sudoku_puzzle_cell_features where internal_use_only = 0 and inactive = 0 order by sort_order,description");
				while ($row = getNextRow($resultSet)) {
					?>
                    <tr class='feature-row' id="cell_feature_<?= $row['sudoku_puzzle_cell_feature_id'] ?>" data-sudoku_puzzle_cell_feature_code="<?= $row['sudoku_puzzle_cell_feature_code'] ?>" data-sudoku_puzzle_cell_feature_id="<?= $row['sudoku_puzzle_cell_feature_id'] ?>">
                        <td><input type='checkbox' class='puzzle-cell-feature' name='sudoku_puzzle_cell_feature_id_<?= $row['sudoku_puzzle_cell_feature_id'] ?>' id='sudoku_puzzle_cell_feature_id_<?= $row['sudoku_puzzle_cell_feature_id'] ?>' value='<?= $row['sudoku_puzzle_cell_feature_id'] ?>'><label class='checkbox-label' for='sudoku_puzzle_cell_feature_id_<?= $row['sudoku_puzzle_cell_feature_id'] ?>'><?= $row['description'] ?></label></td>
                        <td class='align-center'>
		                    <?php if ($row['uses_icon']) { ?>
                                <span class='selected-icon'></span><input type='hidden' class='icon-code' id='sudoku_puzzle_cell_feature_id_<?= $row['sudoku_puzzle_cell_feature_id'] ?>_icon' name='sudoku_puzzle_cell_feature_id_<?= $row['sudoku_puzzle_cell_feature_id'] ?>_icon'><br>
                                <button class='select-icon-button'>Select Icon</button>
		                    <?php } ?>
                        </td>
                        <td>
		                    <?php if ($row['uses_compass_points']) { ?>
		                        <table class='compass-points-table'>
                                    <tr>
                                        <td><input type='checkbox' name='sudoku_puzzle_cell_feature_id_<?= $row['sudoku_puzzle_cell_feature_id'] ?>_compass_nw' id='sudoku_puzzle_cell_feature_id_<?= $row['sudoku_puzzle_cell_feature_id'] ?>_compass_nw' value='nw'></td>
                                        <td><input type='checkbox' name='sudoku_puzzle_cell_feature_id_<?= $row['sudoku_puzzle_cell_feature_id'] ?>_compass_n' id='sudoku_puzzle_cell_feature_id_<?= $row['sudoku_puzzle_cell_feature_id'] ?>_compass_n' value='n'></td>
                                        <td><input type='checkbox' name='sudoku_puzzle_cell_feature_id_<?= $row['sudoku_puzzle_cell_feature_id'] ?>_compass_ne' id='sudoku_puzzle_cell_feature_id_<?= $row['sudoku_puzzle_cell_feature_id'] ?>_compass_ne' value='ne'></td>
                                    </tr>
                                    <tr>
                                        <td><input type='checkbox' name='sudoku_puzzle_cell_feature_id_<?= $row['sudoku_puzzle_cell_feature_id'] ?>_compass_w' id='sudoku_puzzle_cell_feature_id_<?= $row['sudoku_puzzle_cell_feature_id'] ?>_compass_w' value='w'></td>
                                        <td></td>
                                        <td><input type='checkbox' name='sudoku_puzzle_cell_feature_id_<?= $row['sudoku_puzzle_cell_feature_id'] ?>_compass_e' id='sudoku_puzzle_cell_feature_id_<?= $row['sudoku_puzzle_cell_feature_id'] ?>_compass_e' value='e'></td>
                                    </tr>
                                    <tr>
                                        <td><input type='checkbox' name='sudoku_puzzle_cell_feature_id_<?= $row['sudoku_puzzle_cell_feature_id'] ?>_compass_sw' id='sudoku_puzzle_cell_feature_id_<?= $row['sudoku_puzzle_cell_feature_id'] ?>_compass_sw' value='sw'></td>
                                        <td><input type='checkbox' name='sudoku_puzzle_cell_feature_id_<?= $row['sudoku_puzzle_cell_feature_id'] ?>_compass_s' id='sudoku_puzzle_cell_feature_id_<?= $row['sudoku_puzzle_cell_feature_id'] ?>_compass_s' value='s'></td>
                                        <td><input type='checkbox' name='sudoku_puzzle_cell_feature_id_<?= $row['sudoku_puzzle_cell_feature_id'] ?>_compass_se' id='sudoku_puzzle_cell_feature_id_<?= $row['sudoku_puzzle_cell_feature_id'] ?>_compass_se' value='se'></td>
                                    </tr>
                                </table>
		                    <?php } ?>
                        </td>
                        <td><input type="text" class='minicolors display-color' name='sudoku_puzzle_cell_feature_id_<?= $row['sudoku_puzzle_cell_feature_id'] ?>_display_color' id='sudoku_puzzle_cell_feature_id_<?= $row['sudoku_puzzle_cell_feature_id'] ?>_display_color'>
                        <td>
		                    <?php if ($row['include_text']) { ?>
                                <input type='text' class='cell-text' size='6' maxlength='6' name='sudoku_puzzle_cell_feature_id_<?= $row['sudoku_puzzle_cell_feature_id'] ?>_text_data' id='sudoku_puzzle_cell_feature_id_<?= $row['sudoku_puzzle_cell_feature_id'] ?>_text_data' value=''>
		                    <?php } ?>
                        </td>
                    </tr>
					<?php
				}
				?>
            </table>
        </div>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            .far.remove-line {
                color: rgb(150,0,0);
                font-size: 1.2rem;
                margin-right: 10px;
                cursor: pointer;
            }
            #puzzle_grid_table {
                margin-bottom: 20px;
                td {
                    padding: 0;
                }
            }
            .sudoku-puzzle-cell {
                width: 80px;
                height: 80px;
                cursor: pointer;
                padding: 10px;
                border: .5px solid rgb(200, 200, 200);
                position: relative;
                &.outer-cell {
                    border: .5px dashed rgb(210, 210, 210);
                }
                &.left-edge-cell {
                    border-left: 2px solid rgb(50, 50, 50);
                }
                &.top-edge-cell {
                    border-top: 2px solid rgb(50, 50, 50);
                }
                &.right-edge-cell {
                    border-right: 2px solid rgb(50, 50, 50);
                }
                &.bottom-edge-cell {
                    border-bottom: 2px solid rgb(50, 50, 50);
                }
                &:hover {
                    background-color: rgb(240, 240, 180);
                }
                &.drag-select {
                    cursor: cell;
                }
                &.selected {
                    background-color: rgb(180,240,240) !important;
                }
            }

            input[type=text].cell-value {
                height: 50px;
                width: 50px;
                border: none;
                font-size: 2.5rem;
                padding: 0;
                text-align: center;
                cursor: pointer;
                background-color: transparent;
                z-index: 1000;
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%,-50%);
                &:focus {
                    background-color: transparent;
                }
            }

            .sudoku-puzzle-cell.drag-select input[type=text].cell-value {
                cursor: cell;
            }

            table.compass-points-table {
                margin: 0 auto;
                td {
                    border: none;
                    padding: 0 2px;
                    min-width: 0;
                }
            }

            #icon_list {
                display: flex;
                flex-wrap: wrap;
                height: 600px;
                overflow: scroll;
                span {
                    display: block;
                    font-size: 2rem;
                    cursor: pointer;
                    padding: 5px;
                    margin: 5px;
                    border: 1px solid rgb(220,220,220);
                    flex: 0 0 auto;
                }
            }

            .ui-widget button.select-icon-button {
                font-size: .8rem;
            }

            .selected-icon {
                font-size: 2rem;
            }

            .open-dot {
                width: 15px;
                height: 15px;
                border-radius: 50%;
                border: 2px solid rgb(0,0,0);
                z-index: 500;
            }

            .closed-dot {
                width: 15px;
                height: 15px;
                border-radius: 50%;
                background-color: rgb(0,0,0);
                z-index: 500;
            }

            .additional-element span.far {
                font-size: 1.6rem;
            }

            .compass-point {
                position: absolute;
            }

            .compass-point-nw {
                left: 0;
                top: 0;
                transform: translate(-55%,-55%) rotate(225deg);
                transform-origin: 50% 50%;
            }

            .compass-point-n {
                left: 50%;
                top: 0;
                transform: translate(-50%,-55%) rotate(270deg);
                transform-origin: 50% 50%;
            }

            .compass-point-ne {
                left: 100%;
                top: 0;
                transform: translate(-45%,-55%) rotate(315deg);
                transform-origin: 50% 50%;
            }

            .compass-point-w {
                left: 0;
                top: 50%;
                transform: translate(-55%,-50%) rotate(180deg);
                transform-origin: 50% 50%;
            }

            .compass-point-e {
                left: 100%;
                top: 50%;
                transform: translate(-45%,-50%);
                transform-origin: 50% 50%;
            }

            .compass-point-sw {
                left: 0;
                top: 100%;
                transform: translate(-55%,-45%) rotate(135deg);
            }

            .compass-point-s {
                left: 50%;
                top: 100%;
                transform: translate(-50%,-45%) rotate(90deg);
            }

            .compass-point-se {
                left: 100%;
                top: 100%;
                transform: translate(-45%,-45%) rotate(45deg);
            }

            .double-border-top {
                border-top: 3px double rgb(0,0,0);
            }

            .sudoku-puzzle-cell.double-border-right {
                border-right: 3px double rgb(0,0,0);
            }

            .sudoku-puzzle-cell.double-border-bottom {
                border-bottom: 3px double rgb(0,0,0);
            }

            .sudoku-puzzle-cell.double-border-left {
                border-left: 3px double rgb(0,0,0);
            }

            .additional-element.up-left-arrow {
                position: absolute;
                left: 0;
                top: 0;
                transform: translate(-5%,-15%) rotate(225deg);
                span.far {
                    font-size: 2.5rem;
                }
            }

            .additional-element.up-left-arrow-text-data {
                position: absolute;
                left: 100%;
                top: 100%;
                transform: translate(-100%,-100%);
                font-size: 2rem;
            }

            .additional-element.up-right-arrow {
                position: absolute;
                left: 100%;
                top: 0;
                transform: translate(-90%,-10%) rotate(315deg);
                span.far {
                    font-size: 2.5rem;
                }
            }

            .additional-element.up-right-arrow-text-data {
                position: absolute;
                left: 0;
                top: 100%;
                transform: translate(0,-100%);
                font-size: 2rem;
            }

            .additional-element.down-left-arrow {
                position: absolute;
                left: 0;
                top: 100%;
                transform: translate(-10%, -85%) rotate(135deg);
                span.far {
                    font-size: 2.5rem;
                }
            }

            .additional-element.down-left-arrow-text-data {
                position: absolute;
                left: 100%;
                top: 0;
                transform: translate(-100%,0);
                font-size: 2rem;
            }

            .additional-element.down-right-arrow {
                position: absolute;
                left: 100%;
                top: 100%;
                transform: translate(-95%,-85%) rotate(45deg);
                span.far {
                    font-size: 2.5rem;
                }
            }

            .additional-element.down-right-arrow-text-data {
                position: absolute;
                left: 0;
                top: 0;
                transform: translate(0,0);
                font-size: 2rem;
            }

            .additional-element.internal-icon {
                opacity: .25;
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%,-50%);
                span.far {
                    font-size: 3rem;
                }
            }

            .cage-border {
                width: 74px;
                height: 74px;
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%,-50%);
                z-index: 200;
                &.top-border {
                    border-top: 1px dashed rgb(100,100,100);
                }
                &.right-border {
                    border-right: 1px dashed rgb(100,100,100);
                }
                &.bottom-border {
                    border-bottom: 1px dashed rgb(100,100,100);
                }
                &.left-border {
                    border-left: 1px dashed rgb(100,100,100);
                }
                span.cage-total {
                    font-size: .6rem;
                    position: absolute;
                    top: 2px;
                    left: 2px;
                    color: rgb(100,100,100);
                }
            }

            .puzzle-line {
                position: absolute;
                top: 50%;
                left: 50%;
                z-index: 400;
            }

        </style>
		<?php
	}

	function afterSaveChanges($nameValues,$actionPerformed) {
	    executeQuery("delete from sudoku_puzzle_cages where sudoku_puzzle_id = ?",$nameValues['primary_id']);
	    $cages = array();
        foreach ($nameValues as $fieldName => $fieldValue) {
            if (substr($fieldName,0,strlen("cell_value_")) == "cell_value_") {
                $parts = explode("_",$fieldName);
                $rowNumber = $parts[2];
                $columnNumber = $parts[3];
                if (empty($rowNumber) || empty($columnNumber)) {
                    $nameValues['cell_block_number_' . $rowNumber . "_" . $columnNumber] = "";
                }
                $sudokuPuzzleCellId = getFieldFromId("sudoku_puzzle_cell_id","sudoku_puzzle_cells","sudoku_puzzle_id",$nameValues['primary_id'],
                    "row_number = ? and column_number = ?",$rowNumber,$columnNumber);
                if (empty($sudokuPuzzleCellId)) {
                    $resultSet = executeQuery("insert into sudoku_puzzle_cells (sudoku_puzzle_id,row_number,column_number,block_number,fill_value,display_color,readonly) values (?,?,?,?,?, ?,?)",
                        $nameValues['primary_id'], $rowNumber, $columnNumber, $nameValues['cell_block_number_' . $rowNumber . "_" . $columnNumber], $nameValues['cell_value_' . $rowNumber . "_" . $columnNumber],
                        $nameValues['cell_display_color_' . $rowNumber . "_" . $columnNumber], (empty($nameValues['cell_readonly_' . $rowNumber . "_" . $columnNumber]) ? "0" : "1"));
                    $sudokuPuzzleCellId = $resultSet['insert_id'];
                } else {
                    executeQuery("update sudoku_puzzle_cells set block_number = ?, fill_value = ?, display_color = ?, readonly = ? where sudoku_puzzle_cell_id = ?",
                        $nameValues['cell_block_number_' . $rowNumber . "_" . $columnNumber], $nameValues['cell_value_' . $rowNumber . "_" . $columnNumber],
                        $nameValues['cell_display_color_' . $rowNumber . "_" . $columnNumber], (empty($nameValues['cell_readonly_' . $rowNumber . "_" . $columnNumber]) ? "0" : "1"),$sudokuPuzzleCellId);
                }
	            executeQuery("delete from sudoku_puzzle_cell_feature_links where sudoku_puzzle_cell_id = ?",$sudokuPuzzleCellId);
                $featureData = getContentLines($nameValues['cell_feature_ids_' . $rowNumber . "_" . $columnNumber]);
                foreach ($featureData as $thisFeature) {
                    $parts = explode("|",$thisFeature);
                    $featureId = getFieldFromId("sudoku_puzzle_cell_feature_id","sudoku_puzzle_cell_features","sudoku_puzzle_cell_feature_id",$parts[0]);
                    if (empty($featureId)) {
	                    continue;
                    }
	                $compassPoints = $parts[1];
	                $displayColor = $parts[2];
                    $iconName = $parts[3];
                    $textData = $parts[4];
                    executeQuery("insert into sudoku_puzzle_cell_feature_links (sudoku_puzzle_cell_id,sudoku_puzzle_cell_feature_id,compass_points,display_color,icon_name,text_data) values (?,?,?,?,?, ?)",
                        $sudokuPuzzleCellId,$featureId,$compassPoints,$displayColor,$iconName,$textData);
                }
                if (!empty($nameValues['cell_cage_number_' . $rowNumber . "_" . $columnNumber])) {
	                $cageNumber = $nameValues['cell_cage_number_' . $rowNumber . "_" . $columnNumber];
	                if (!array_key_exists($cageNumber,$cages)) {
	                    $cages[$cageNumber] = array("cage_total"=>"","content"=>"");
	                }
	                $cages[$cageNumber]['content'] .= (empty($cages[$cageNumber]['content']) ? "" : "|") . $rowNumber . "," . $columnNumber;
	                if (!empty($nameValues['cell_cage_total_' . $rowNumber . "_" . $columnNumber])) {
	                    $cages[$cageNumber]['cage_total'] = $nameValues['cell_cage_total_' . $rowNumber . "_" . $columnNumber];
	                }
                }
            }
        }
        if (!empty($cages)) {
            foreach ($cages as $thisCage) {
                if (empty($thisCage)) {
	                continue;
                }
                executeQuery("insert into sudoku_puzzle_cages (sudoku_puzzle_id,cage_total,content) values (?,?,?)",$nameValues['primary_id'],$thisCage['cage_total'],$thisCage['content']);
            }
        }

        executeQuery("delete from sudoku_puzzle_lines where sudoku_puzzle_id = ?",$nameValues['primary_id']);
        foreach ($nameValues as $fieldName => $fieldValue) {
            if (substr($fieldName,0,strlen("sudoku_puzzle_line_")) == "sudoku_puzzle_line_") {
                $parts = explode("|",$fieldValue,6);
                executeQuery("insert into sudoku_puzzle_lines (sudoku_puzzle_id,line_start_type,line_end_type,display_color,line_width,content) values " .
                    "(?,?,?,?,?,?)",$nameValues['primary_id'],$parts[1],$parts[2],$parts[3],$parts[4],$parts[5]);
            }
        }
        return true;
	}

}

$pageObject = new SudokuCreatorPage("sudoku_puzzles");
$pageObject->displayPage();
