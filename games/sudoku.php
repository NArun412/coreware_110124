<?php

/*      This software is the unpublished, confidential, proprietary, intellectual
        property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
        or used in any manner without expressed written consent from Kim David Software, LLC.
        Kim David Software, LLC owns all rights to this work and intends to keep this
        software confidential so as to maintain its value as a trade secret.

        Copyright 2004-Present, Kim David Software, LLC.

        WARNING! This code is part of the Kim David Software's Coreware system.
        Changes made to this source file will be lost when new versions of the
        system are installed.
*/

$GLOBALS['gPageCode'] = "SUDOKU";
require_once "shared/startup.inc";

class ThisPage extends Page {
	var $iDebug = false;

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "solve_puzzle":
				ob_start();
				$puzzle = array();
				for ($row = 0; $row < 9; $row++) {
					if (!array_key_exists($row, $puzzle)) {
						$puzzle[$row] = array();
					}
					for ($column = 0; $column < 9; $column++) {
						$puzzle[$row][$column] = (empty($_POST['cell_' . ($row + 1) . "_" . ($column + 1)]) ? "0" : $_POST['cell_' . ($row + 1) . "_" . ($column + 1)]);
					}
				}
				$startTime = getMilliseconds();
				$solver = new Sudoku($puzzle);
				$solution = $solver->getSolution(false, true, true);
				$endTime = getMilliseconds();
				$secondsToSolve = round(($endTime - $startTime) / 1000, 2);
				if ($solution === false) {
					echo "<p class='align-center'>No Solution Found</p>";
				} else {
					if ($this->iDebug) {
						echo "<p class='align-center'>" . $secondsToSolve . " seconds to solve.</p>";
					}
					?>
                    <div class="full-puzzle">
						<?php
						for ($row = 1; $row <= 9; $row++) {
							?>
                            <div class="row row-<?php echo $row ?>">
								<?php
								for ($column = 1; $column <= 9; $column++) {
									$cellValue = $solution[$row - 1][$column - 1];
									if (is_array($cellValue)) {
										$cellValue = "<span class='possibles'>" . implode("", $cellValue) . "</span>";
									}
									?>
                                    <div class="cell row-<?php echo $row ?> column-<?php echo $column ?>"><span><?php echo(empty($cellValue) ? "" : $cellValue) ?></span></div>
									<?php
								}
								?>
                            </div>
							<?php
						}
						?>
                    </div>
					<?php
				}
				$returnArray['report_content'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
			case "get_difficult":
			case "create_puzzle":
				ob_start();
				$solver = new Sudoku();
				$startTime = getMilliseconds();
				if ($_GET['url_action'] == "get_difficult") {
					$solution = $solver->getMostDifficult();
				} else {
					$solution = $solver->createPuzzle(true);
					if (!$solution) {
						$returnArray['error_message'] = "Unable to create hard puzzle";
						ajaxResponse($returnArray);
						break;
					}
				}
				$endTime = getMilliseconds();
				$secondsToSolve = round(($endTime - $startTime) / 1000, 2);
				if ($this->iDebug) {
					echo "<p class='align-center'>" . $secondsToSolve . " seconds to create.</p>";
				}
				?>
                <div class="full-puzzle">
					<?php
					for ($row = 1; $row <= 9; $row++) {
						?>
                        <div class="row row-<?php echo $row ?>">
							<?php
							for ($column = 1; $column <= 9; $column++) {
								?>
                                <div class="cell row-<?php echo $row ?> column-<?php echo $column ?>"><span><?php echo(empty($solution[$row - 1][$column - 1]) ? "" : $solution[$row - 1][$column - 1]) ?></span></div>
								<?php
							}
							?>
                        </div>
						<?php
					}
					?>
                </div>
				<?php
				$returnArray['report_content'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
			case "create_small_puzzle":
				ob_start();
				$solver = new Sudoku();
				$solution = $solver->createPuzzle(true);
				?>
                <div class="small-puzzle">
					<?php
					for ($row = 1; $row <= 9; $row++) {
						?>
                        <div class="row row-<?php echo $row ?>">
							<?php
							for ($column = 1; $column <= 9; $column++) {
								?>
                                <div class="cell row-<?php echo $row ?> column-<?php echo $column ?>"><span><?php echo(empty($solution[$row - 1][$column - 1]) ? "" : $solution[$row - 1][$column - 1]) ?></span></div>
								<?php
							}
							?>
                        </div>
						<?php
					}
					?>
                </div>
				<?php
				$returnArray['report_content'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
		}
	}

	/* options to add:
	- create a single puzzle
	- create a sheet of 6 puzzles
	- enter and solve a puzzle
	-
	*/
	function mainContent() {
		?>
        <div id="action_choices">
            <p class="align-center">
                <button id="create_puzzle_button">Create Puzzle</button>
            </p>
            <p class="align-center">
                <button id="show_most_difficult">Show Most Difficult</button>
            </p>
            <p class="align-center">
                <button id="create_six_button">Create Sheet of Puzzles</button>
            </p>
            <p class="align-center" id="copy_option">
                <button id="copy_to_solver">Copy To Solver</button>
            </p>
            <p class="align-center">
                <button id="solve_puzzle_button">Solve Puzzle</button>
            </p>
        </div>

        <div id="_entry_puzzle_block">
            <form id="_puzzle_form">
                <p class="align-center">
                    <button id="solve">Solve</button>
                    <button id="edit_puzzle">Edit</button>
                </p>
                <div id="entry_puzzle">
					<?php
					for ($row = 1; $row <= 9; $row++) {
						?>
                        <div class="row row-<?php echo $row ?>">
							<?php
							for ($column = 1; $column <= 9; $column++) {
								?>
                                <div class="cell row-<?php echo $row ?> column-<?php echo $column ?>"><input type="text" class="cell-entry" data-row="<?php echo $row ?>" data-column="<?php echo $column ?>" id="cell_<?php echo $row ?>_<?php echo $column ?>" name="cell_<?php echo $row ?>_<?php echo $column ?>"></div>
								<?php
							}
							?>
                        </div>
						<?php
					}
					?>
                </div>
            </form>
        </div>
        <div id="_report_content">
        </div>
		<?php
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
            $(".full-puzzle,.small-puzzle").click(function () {
                window.open("printable.html");
            });
            $("#show_most_difficult").click(function () {
                $("#_report_content,#_entry_puzzle_block").hide();
                loadAjaxRequest("<?php echo $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_difficult", function(returnArray) {
                    if ("report_content" in returnArray) {
                        $("#_report_content").html(returnArray["report_content"]).show();
                        $("#copy_option").show();
                    }
                });
                return false;
            });
            $("#create_puzzle_button").click(function () {
                $("#_report_content,#_entry_puzzle_block").hide();
                loadAjaxRequest("<?php echo $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_puzzle", function(returnArray) {
                    if ("report_content" in returnArray) {
                        $("#_report_content").html(returnArray["report_content"]).show();
                        $("#copy_option").show();
                    }
                });
                return false;
            });
            $("#create_six_button").click(function () {
                $("#copy_option").hide();
                $("#_report_content,#_entry_puzzle_block").hide();
                $("#_report_content").html("<div class='clear-div'></div>");
                getSmallPuzzles();
                return false;
            });
            $("#copy_to_solver").click(function () {
                $("#copy_option").hide();
                $("#_report_content").hide();
                $(".cell-entry").val("").prop("readonly", false);
                $("#entry_puzzle").removeClass("entered-puzzle");
                $("#edit_puzzle").hide();
                $("#_entry_puzzle_block").show();
                if ($(".full-puzzle").length == 1) {
                    for (var row = 1; row <= 9; row++) {
                        for (var column = 1; column <= 9; column++) {
                            $("#cell_" + row + "_" + column).val($("div.cell.row-" + row + ".column-" + column + " span").html());
                        }
                    }
                }
                $("#cell_1_1").focus();
                return false;
            });
            $("#solve_puzzle_button").click(function () {
                $("#copy_option").hide();
                $("#_report_content").hide();
                $(".cell-entry").val("").prop("readonly", false);
                $("#entry_puzzle").removeClass("entered-puzzle");
                $("#_entry_puzzle_block").show();
                $("#cell_1_1").focus();
                return false;
            });
            $("#edit_puzzle").click(function () {
                $("#_report_content").hide();
                $("#entry_puzzle").removeClass("entered-puzzle").find(".cell-entry").prop("readonly", false);
                $("#edit_puzzle").hide();
                $("#cell_1_1").focus();
                return false;
            });
            $("#solve").click(function () {
                $("#entry_puzzle").addClass("entered-puzzle").find(".cell-entry").prop("readonly", true);
                loadAjaxRequest("<?php echo $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=solve_puzzle", $("#_puzzle_form").serialize(), function(returnArray) {
                    if ("report_content" in returnArray) {
                        $("#_report_content").html(returnArray["report_content"]).show();
                    }
                    $("#edit_puzzle").show();
                });
                return false;
            })
            $(".cell-entry").keydown(function (event) {
                var row = $(this).data("row");
                var column = $(this).data("column");
                var goNext = false;
                if (event.which >= 49 && event.which <= 57) {
                    $(this).val(event.which - 48);
                    goNext = true;
                } else if (event.which == 32) {
                    $(this).val("");
                    goNext = true;
                } else if (event.which == 13 || event.which == 3 || event.which == 9) {
                    goNext = true;
                } else if (event.which == 39) {
                    goNext = true;
                } else if (event.which == 38) {
                    row--;
                    if (row < 1) {
                        row = 9;
                    }
                    $("#cell_" + row + "_" + column).focus();
                } else if (event.which == 40) {
                    row++;
                    if (row > 9) {
                        row = 1;
                    }
                    $("#cell_" + row + "_" + column).focus();
                } else if (event.which == 37) {
                    column--;
                    if (column < 1) {
                        row--;
                        column = 9;
                    }
                    if (row < 1) {
                        row = 9;
                    }
                    $("#cell_" + row + "_" + column).focus();
                } else if (event.which == 8) {
                    column--;
                    if (column < 1) {
                        row--;
                        column = 9;
                    }
                    if (row < 1) {
                        row = 9;
                    }
                    $("#cell_" + row + "_" + column).val("").focus();
                }
                if (goNext) {
                    column++;
                    if (column > 9) {
                        row++;
                        column = 1;
                    }
                    if (row > 9) {
                        row = 1;
                    }
                    $("#cell_" + row + "_" + column).focus();
                }
                return false;
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function getSmallPuzzles() {
                loadAjaxRequest("<?php echo $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_small_puzzle", function(returnArray) {
                    if ("report_content" in returnArray) {
                        $("#_report_content").prepend(returnArray["report_content"]).show();
                        if ($(".small-puzzle").length < 4) {
                            console.log($(".small-puzzle").length);
                            setTimeout(function () {
                                console.log("Again");
                                getSmallPuzzles();
                            }, 500);
                        }
                    }
                });
            }
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style id="_printable_style">
            #_report_content {
                margin-top: 40px;
            }
            .full-puzzle {
                display: table;
                margin: 0 auto;
                cursor: pointer;
            }
            #entry_puzzle {
                display: table;
                margin: 0 auto;
            }
            #entry_puzzle.entered-puzzle {
                width: 200px;
            }
            .small-puzzle {
                float: left;
                width: 45%;
                width: calc(50% - 10px);
                margin: 5px;
                display: table;
            }
            .cell {
                width: 75px;
                height: 75px;
                display: table-cell;
                border-top: 1px solid rgb(50, 50, 50);
                border-left: 1px solid rgb(50, 50, 50);
                margin: 0;
                position: relative;
            }
            .small-puzzle .cell {
                width: 11%;
                height: auto;
                padding-bottom: 11%;
                position: relative;
            }
            .entered-puzzle .cell {
                width: 11%;
                height: auto;
                padding-bottom: 11%;
            }
            .cell span {
                display: inline-block;
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                font-size: 60px;
                font-family: Helvetica, san-serif;
                line-height: .8;
                font-weight: 300;
            }
            .cell span.possibles {
                font-size: 11px;
            }
            .small-puzzle .cell span {
                font-size: 30px;
            }
            .row {
                display: table-row;
            }
            .column-9 {
                border-right: 4px solid rgb(0, 0, 0);
            }
            .cell.row-1 {
                border-top: 4px solid rgb(0, 0, 0);
            }
            .cell.row-9 {
                border-bottom: 4px solid rgb(0, 0, 0);
            }
            .cell.column-1 {
                border-left: 4px solid rgb(0, 0, 0);
            }
            .cell.row-3 {
                border-bottom: 3px solid rgb(0, 0, 0);
            }
            .cell.row-6 {
                border-bottom: 3px solid rgb(0, 0, 0);
            }
            .cell.column-3 {
                border-right: 3px solid rgb(0, 0, 0);
            }
            .cell.column-6 {
                border-right: 3px solid rgb(0, 0, 0);
            }
            #_entry_puzzle_block {
                display: none;
                margin-top: 40px;
                margin-bottom: 20px;
            }
            input.cell-entry {
                padding: 0;
                border: none;
                outline: none;
                width: 50px;
                height: 50px;
                font-size: 50px;
                transform: translate(-50%, -50%);
                display: block;
                position: absolute;
                top: 50%;
                left: 50%;
                text-align: center;
            }
            input.cell-entry:focus {
                background-color: rgb(255, 255, 255);
            }
            #entry_puzzle.entered-puzzle input.cell-entry {
                width: 16px;
                height: 16px;
                font-size: 12px;
            }
            #copy_option {
                display: none;
            }
            #edit_puzzle {
                display: none;
            }
        </style>
		<?php
	}
}

$pageObject = new ThisPage();
$pageObject->displayPage();
