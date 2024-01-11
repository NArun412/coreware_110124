<?php

/*      This software is the unpublished, confidential, proprietary, intellectual
        property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
        or used in any manner without expressed written consent from Kim David Software, LLC.
        Kim David Software, LLC owns all rights to this work and intends to keep this
        software confidential so as to maintain its value as a trade secret.

        Copyright 2004-Present, Kim David Software, LLC.
*/

Class Sudoku {
	private $iStartBoard;
	private $iCurrentBoard;
	private $iSkipOne = false;
	private $iFirstSolution = false;
	private $iStepCount = 0;
	private $iProbablesFilled = 0;
	private $iDebug = false;

	public function __construct($startBoard = array()) {
		if (empty($startBoard)) {
			$this->iStartBoard = $this->emptyBoard();
		} else {
			$this->iStartBoard = $startBoard;
		}
	}

	public function hasSolution($skipOne = false) {
		$saveDebug = $this->iDebug;
		$this->iDebug = false;
		$solution = $this->getSolution($skipOne);
		$this->iDebug = $saveDebug;
		return ($solution !== false);
	}

	public function getSolution($skipOne = false, $logicOnly = false, $allowPartial = false) {
		if (!$logicOnly && $this->iFirstSolution !== false) {
			return $this->iFirstSolution;
		}
		$this->iCurrentBoard = $this->iStartBoard;
		$this->iSkipOne = $skipOne;

		$this->getNextEmptyCell();
		$loops = 0;
		while (true) {
			$possibleValues = $this->getPossibleValues();
			$possibleValues = $this->solveWithLogic($possibleValues);
			if ($possibleValues === true) {
				$loops++;
			} else {
				break;
			}
		}
		foreach ($possibleValues as $possibleInfo) {
			if (count($possibleInfo['possible_values']) == 0) {
				return false;
			}
		}
		if ($this->iDebug) {
			echo $loops . " algorithm loops taken, filling " . $this->iProbablesFilled . " cells<br>";
		}

		$cell = $this->getNextEmptyCell(true);

		# The puzzle was successfully solved

		if ($cell == 0) {
			if ($this->iDebug) {
				echo "Solved with only Logic<br>";
			}
			if ($logicOnly) {
				return true;
			}
			return ($skipOne ? false : $this->iCurrentBoard);
		}
		if ($logicOnly) {
			if ($allowPartial) {
				foreach ($possibleValues as $thisPossibleValue) {
					if (empty($this->iCurrentBoard[$thisPossibleValue['row']][$thisPossibleValue['column']])) {
						$this->iCurrentBoard[$thisPossibleValue['row']][$thisPossibleValue['column']] = $thisPossibleValue['possible_values'];
					}
				}
				return $this->iCurrentBoard;
			} else {
				return false;
			}
		}
		if ($this->iDebug) {
			echo "Logic solutions exhausted. Forcing Solution<br>";
		}
		if ($this->fillCell($cell)) {
			return $this->iCurrentBoard;
		} else {
			return false;
		}
	}

	public function getStepCount() {
		return $this->iStepCount;
	}

	public function createPuzzle($hard = false) {
		while (true) {
			$validBoard = $this->createValidBoard();
			$cellValues = array();
			foreach ($validBoard as $rowNumber => $boardRow) {
				foreach ($boardRow as $columnNumber => $value) {
					$cellValues[] = array("row" => $rowNumber, "column" => $columnNumber, "value" => $value);
				}
			}
			shuffle($cellValues);
			foreach ($cellValues as $thisCellValue) {
				$validBoard[$thisCellValue['row']][$thisCellValue['column']] = 0;
				$solver = new Sudoku($validBoard);
				$hasUniqueSolution = $solver->hasUniqueSolution();
				if ($hasUniqueSolution) {
					continue;
				} else {
					$validBoard[$thisCellValue['row']][$thisCellValue['column']] = $thisCellValue['value'];
				}
			}
			if ($hard) {
				$solver = new Sudoku($validBoard);
				if ($solver->getSolution(false,true) === true) {
					continue;
				}
			}
			break;
		}
		$this->iStartBoard = $validBoard;
		return $validBoard;
	}

	private function possibleValuesBlockSort($a, $b) {
		if ($a['block_number'] == $b['block_number']) {
			if ($a['row'] == $b['row']) {
				if ($a['column'] == $b['column']) {
					return 0;
		 		}
				return ($a['column'] < $b['column'] ? -1 : 1);
	 		}
			return ($a['row'] < $b['row'] ? -1 : 1);
 		}
		return ($a['block_number'] < $b['block_number'] ? -1 : 1);
	}

	private function possibleValuesRowSort($a, $b) {
		if ($a['row'] == $b['row']) {
			if ($a['column'] == $b['column']) {
				return 0;
	 		}
			return ($a['column'] < $b['column'] ? -1 : 1);
 		}
		return ($a['row'] < $b['row'] ? -1 : 1);
	}

	private function possibleValuesColumnSort($a, $b) {
		if ($a['column'] == $b['column']) {
			if ($a['row'] == $b['row']) {
				return 0;
	 		}
			return ($a['row'] < $b['row'] ? -1 : 1);
 		}
		return ($a['column'] < $b['column'] ? -1 : 1);
	}

	private function getPossibleValues() {
		$possibleValues = array();
		foreach ($this->iCurrentBoard as $rowNumber => $boardRow) {
			foreach ($boardRow as $columnNumber => $value) {
				if ($value != 0) {
					continue;
				}
				$thisCellPossibleValues = array();
				foreach (array(1,2,3,4,5,6,7,8,9) as $checkValue) {
					if ($this->isCellValid($rowNumber,$columnNumber,$checkValue)) {
						$thisCellPossibleValues[] = $checkValue;
					}
				}
				$possibleValues[] = array("row"=>$rowNumber,"column"=>$columnNumber,"block_number"=>$this->getBlockNumber($rowNumber,$columnNumber),"possible_values"=>$thisCellPossibleValues);
			}
		}
		return $possibleValues;
	}

	private function solveWithLogic($possibleValues) {
		$someSolved = false;

		do {
			$someRemoved = false;

# Only one possible value in cell

			foreach ($possibleValues as $possibleInfo) {
				if (count($possibleInfo['possible_values']) == 1) {
					$someSolved = true;
					$thisCellValue = array_shift($possibleInfo['possible_values']);
					if ($this->iDebug) {
						echo "Only one possible value: (" . ($possibleInfo['row'] + 1) . "," . ($possibleInfo['column'] + 1) . ") = " . $thisCellValue . "<br>";
					}
					if ($this->iCurrentBoard[$possibleInfo['row']][$possibleInfo['column']] == 0) {
						$this->iProbablesFilled++;
						$this->iCurrentBoard[$possibleInfo['row']][$possibleInfo['column']] = $thisCellValue;
					}
				}
			}
			if ($someSolved) {
				return true;
			}

# only one occurrence of a number in a row, column or block

			$rowValues = array();
			$columnValues = array();
			$blockValues = array();
			foreach ($possibleValues as $possibleInfo) {
				foreach ($possibleInfo['possible_values'] as $value) {
					if (!array_key_exists($possibleInfo['row'],$rowValues)) {
						$rowValues[$possibleInfo['row']] = array();
					}
					if (!array_key_exists($value,$rowValues[$possibleInfo['row']])) {
						$rowValues[$possibleInfo['row']][$value] = array("count"=>0,"column"=>"");
					}
					$rowValues[$possibleInfo['row']][$value]['count']++;
					$rowValues[$possibleInfo['row']][$value]['column'] = $possibleInfo['column'];

					if (!array_key_exists($possibleInfo['column'],$columnValues)) {
						$columnValues[$possibleInfo['column']] = array();
					}
					if (!array_key_exists($value,$columnValues[$possibleInfo['column']])) {
						$columnValues[$possibleInfo['column']][$value] = array("count"=>0,"row"=>"");
					}
					$columnValues[$possibleInfo['column']][$value]['count']++;
					$columnValues[$possibleInfo['column']][$value]['row'] = $possibleInfo['row'];

					$blockNumber = $this->getBlockNumber($possibleInfo['row'],$possibleInfo['column']);
					if (!array_key_exists($blockNumber,$blockValues)) {
						$blockValues[$blockNumber] = array();
					}
					if (!array_key_exists($value,$blockValues[$blockNumber])) {
						$blockValues[$blockNumber][$value] = array("count"=>0,"column"=>"","row"=>"");
					}
					$blockValues[$blockNumber][$value]['count']++;
					$blockValues[$blockNumber][$value]['row'] = $possibleInfo['row'];
					$blockValues[$blockNumber][$value]['column'] = $possibleInfo['column'];
				}
			}
			foreach ($rowValues as $rowNumber => $values) {
				foreach ($values as $thisValue => $thisValueInfo) {
					if ($thisValueInfo['count'] == 1) {
						$someSolved = true;
						if ($this->iCurrentBoard[$rowNumber][$thisValueInfo['column']] == 0) {
							if ($this->iDebug) {
								echo "Only one in row: (" . ($rowNumber + 1) . "," . ($thisValueInfo['column'] + 1) . ") = " . $thisValue . "<br>";
							}
							$this->iProbablesFilled++;
							$this->iCurrentBoard[$rowNumber][$thisValueInfo['column']] = $thisValue;
						}
					}
				}
			}
			foreach ($columnValues as $columnNumber => $values) {
				foreach ($values as $thisValue => $thisValueInfo) {
					if ($thisValueInfo['count'] == 1) {
						$someSolved = true;
						if ($this->iCurrentBoard[$thisValueInfo['row']][$columnNumber] == 0) {
							if ($this->iDebug) {
								echo "Only one in column: (" . ($thisValueInfo['row'] + 1) . "," . ($columnNumber + 1) . ") = " . $thisValue . "<br>";
							}
							$this->iProbablesFilled++;
							$this->iCurrentBoard[$thisValueInfo['row']][$columnNumber] = $thisValue;
						}
					}
				}
			}
			foreach ($blockValues as $blockNumber => $values) {
				foreach ($values as $thisValue => $thisValueInfo) {
					if ($thisValueInfo['count'] == 1) {
						$someSolved = true;
						if ($this->iCurrentBoard[$thisValueInfo['row']][$thisValueInfo['column']] == 0) {
							if ($this->iDebug) {
								echo "Only one in block: (" . ($thisValueInfo['row'] + 1) . "," . ($thisValueInfo['column'] + 1) . ") = " . $thisValue . "<br>";
							}
							$this->iProbablesFilled++;
							$this->iCurrentBoard[$thisValueInfo['row']][$thisValueInfo['column']] = $thisValue;
						}
					}
				}
			}
			if ($someSolved) {
				return true;
			}

			$endingArray = array(array("row"=>10,"column"=>10,"block_number"=>10,"possible_values"=>array()));

# Look for Hidden Pairs. This is where two numbers ONLY appear in the same two cells in a row, column or block. All other numbers can then be removed from those two cells, creating a naked pair.

# Look for Hidden Triplets. This is where three numbers ONLY appear in the same three cells in a row, column or block. All other numbers can then be removed from those three cells, creating a naked triplet.

# Look for naked pairs and remove from others in row, column & block

			do {
				$pairsFound = false;
				foreach ($possibleValues as $index => $possibleInfo) {
					if (count($possibleInfo['possible_values']) == 2) {
						foreach ($possibleValues as $compareIndex => $compareInfo) {
							if ($index == $compareIndex) {
								continue;
							}
							if ($possibleInfo['possible_values'] != $compareInfo['possible_values']) {
								continue;
							}
							if ($possibleInfo['row'] == $compareInfo['row']) {
								foreach ($possibleValues as $fixIndex => $fixInfo) {
									if ($fixIndex == $index || $fixIndex == $compareIndex || $fixInfo['row'] != $possibleInfo['row']) {
										continue;
									}
									$possibleValues[$fixIndex]['possible_values'] = array_diff($fixInfo['possible_values'],$possibleInfo['possible_values']);
									if (count($possibleValues[$fixIndex]['possible_values']) != count($fixInfo['possible_values'])) {
										$pairsFound = true;
									}
								}
							}
							if ($possibleInfo['column'] == $compareInfo['column']) {
								foreach ($possibleValues as $fixIndex => $fixInfo) {
									if ($fixIndex == $index || $fixIndex == $compareIndex || $fixInfo['column'] != $possibleInfo['column']) {
										continue;
									}
									$possibleValues[$fixIndex]['possible_values'] = array_diff($fixInfo['possible_values'],$possibleInfo['possible_values']);
									if (count($possibleValues[$fixIndex]['possible_values']) != count($fixInfo['possible_values'])) {
										$pairsFound = true;
									}
								}
							}
							if ($possibleInfo['block_number'] == $compareInfo['block_number']) {
								foreach ($possibleValues as $fixIndex => $fixInfo) {
									if ($fixIndex == $index || $fixIndex == $compareIndex || $fixInfo['block_number'] != $possibleInfo['block_number']) {
										continue;
									}
									$possibleValues[$fixIndex]['possible_values'] = array_diff($fixInfo['possible_values'],$possibleInfo['possible_values']);
									if (count($possibleValues[$fixIndex]['possible_values']) != count($fixInfo['possible_values'])) {
										$pairsFound = true;
									}
								}
							}
						}
					}
				}
				if ($pairsFound) {
					$someRemoved = true;
				}

# Look for naked triples and remove from others in row, column & block

				usort($possibleValues,array($this, "possibleValuesBlockSort"));
				$saveBlockNumber = -1;
				$possiblesArray = array();
				foreach (array_merge($possibleValues,$endingArray) as $index => $possibleInfo) {
					if ($saveBlockNumber != $possibleInfo['block_number']) {
						if ($saveBlockNumber > 0) {
							if (count($possiblesArray) >= 3) {
								$comboArray = array();
								for ($x=0;$x<count($possiblesArray) - 2;$x++) {
									for ($y=($x+1);$y<count($possiblesArray) - 1;$y++) {
										for ($z=($y+1);$z<count($possiblesArray);$z++) {
											$comboArray[] = array($possiblesArray[$x],$possiblesArray[$y],$possiblesArray[$z]);
										}
									}
								}
								foreach ($comboArray as $thisCombo) {
									$numbers = array();
									$cellArray = array();
									foreach ($thisCombo as $comboPart) {
										$cellArray[] = $comboPart['row'] . ":" . $comboPart['column'];
										foreach ($comboPart['possible_values'] as $thisValue) {
											if (!in_array($thisValue,$numbers)) {
												$numbers[] = $thisValue;
											}
										}
									}
									if (count($numbers) == 3) {
										foreach ($possibleValues as $fixIndex => $fixInfo) {
											if ($fixInfo['block_number'] != $saveBlockNumber || in_array($fixInfo['row'] . ":" . $fixInfo['column'],$cellArray)) {
												continue;
											}
											$possibleValues[$fixIndex]['possible_values'] = array_diff($fixInfo['possible_values'],$numbers);
											if (count($possibleValues[$fixIndex]['possible_values']) != count($fixInfo['possible_values'])) {
												$pairsFound = true;
											}
										}
									}
								}
							}
						}
						$saveBlockNumber = $possibleInfo['block_number'];
						$possiblesArray = array();
					}
					if (count($possibleInfo['possible_values']) == 2 || count($possibleInfo['possible_values']) == 3) {
						$possiblesArray[] = $possibleInfo;
					}
				}
				if ($pairsFound) {
					$someRemoved = true;
				}

				usort($possibleValues,array($this, "possibleValuesRowSort"));
				$saveRowNumber = -1;
				foreach (array_merge($possibleValues,$endingArray) as $index => $possibleInfo) {
					if ($saveRowNumber != $possibleInfo['row']) {
						if ($saveRowNumber >= 0) {
							if (count($possiblesArray) >= 3) {
								$comboArray = array();
								for ($x=0;$x<count($possiblesArray) - 2;$x++) {
									for ($y=($x+1);$y<count($possiblesArray) - 1;$y++) {
										for ($z=($y+1);$z<count($possiblesArray);$z++) {
											$comboArray[] = array($possiblesArray[$x],$possiblesArray[$y],$possiblesArray[$z]);
										}
									}
								}
								foreach ($comboArray as $thisCombo) {
									$numbers = array();
									$cellArray = array();
									foreach ($thisCombo as $comboPart) {
										$cellArray[] = $comboPart['row'] . ":" . $comboPart['column'];
										foreach ($comboPart['possible_values'] as $thisValue) {
											if (!in_array($thisValue,$numbers)) {
												$numbers[] = $thisValue;
											}
										}
									}
									if (count($numbers) == 3) {
										foreach ($possibleValues as $fixIndex => $fixInfo) {
											if ($fixInfo['row'] != $saveRowNumber || in_array($fixInfo['row'] . ":" . $fixInfo['column'],$cellArray)) {
												continue;
											}
											$possibleValues[$fixIndex]['possible_values'] = array_diff($fixInfo['possible_values'],$numbers);
											if (count($possibleValues[$fixIndex]['possible_values']) != count($fixInfo['possible_values'])) {
												$pairsFound = true;
											}
										}
									}
								}
							}
						}
						$saveRowNumber = $possibleInfo['row'];
						$possiblesArray = array();
					}
					if (count($possibleInfo['possible_values']) == 2 || count($possibleInfo['possible_values']) == 3) {
						$possiblesArray[] = $possibleInfo;
					}
				}
				if ($pairsFound) {
					$someRemoved = true;
				}

				usort($possibleValues,array($this, "possibleValuesColumnSort"));
				$saveColumnNumber = -1;
				foreach (array_merge($possibleValues,$endingArray) as $index => $possibleInfo) {
					if ($saveColumnNumber != $possibleInfo['column']) {
						if ($saveColumnNumber >= 0) {
							if (count($possiblesArray) >= 3) {
								$comboArray = array();
								for ($x=0;$x<count($possiblesArray) - 2;$x++) {
									for ($y=($x+1);$y<count($possiblesArray) - 1;$y++) {
										for ($z=($y+1);$z<count($possiblesArray);$z++) {
											$comboArray[] = array($possiblesArray[$x],$possiblesArray[$y],$possiblesArray[$z]);
										}
									}
								}
								foreach ($comboArray as $thisCombo) {
									$numbers = array();
									$cellArray = array();
									foreach ($thisCombo as $comboPart) {
										$cellArray[] = $comboPart['row'] . ":" . $comboPart['column'];
										foreach ($comboPart['possible_values'] as $thisValue) {
											if (!in_array($thisValue,$numbers)) {
												$numbers[] = $thisValue;
											}
										}
									}
									if (count($numbers) == 3) {
										foreach ($possibleValues as $fixIndex => $fixInfo) {
											if ($fixInfo['column'] != $saveColumnNumber || in_array($fixInfo['row'] . ":" . $fixInfo['column'],$cellArray)) {
												continue;
											}
											$possibleValues[$fixIndex]['possible_values'] = array_diff($fixInfo['possible_values'],$numbers);
											if (count($possibleValues[$fixIndex]['possible_values']) != count($fixInfo['possible_values'])) {
												$pairsFound = true;
											}
										}
									}
								}
							}
						}
						$saveColumnNumber = $possibleInfo['column'];
						$possiblesArray = array();
					}
					if (count($possibleInfo['possible_values']) == 2 || count($possibleInfo['possible_values']) == 3) {
						$possiblesArray[] = $possibleInfo;
					}
				}
				if ($pairsFound) {
					$someRemoved = true;
				}
			} while ($pairsFound);

# Look for pointing pairs and triplets. This is where the only occurrences of a number are in one row or column of a block, so that number can be removed from other cells of that row/column

			$rowArray = array();
			$columnArray = array();
			do {
				$pairsFound = false;
				usort($possibleValues,array($this, "possibleValuesBlockSort"));
				$saveBlockNumber = -1;
				foreach (array_merge($possibleValues,$endingArray) as $index => $possibleInfo) {
					if ($saveBlockNumber != $possibleInfo['block_number']) {
						if ($saveBlockNumber >= 0) {
							foreach ($rowArray as $thisValue => $rowNumbers) {
								if (count($rowNumbers) == 1) {
									foreach ($possibleValues as $fixIndex => $fixInfo) {
										if ($fixInfo['block_number'] == $saveBlockNumber || $fixInfo['row'] != $rowNumbers[0]) {
											continue;
										}
										$possibleValues[$fixIndex]['possible_values'] = array_diff($fixInfo['possible_values'],array($thisValue));
										if (count($possibleValues[$fixIndex]['possible_values']) != count($fixInfo['possible_values'])) {
											$pairsFound = true;
										}
									}
								}
							}
							foreach ($columnArray as $thisValue => $columnNumbers) {
								if (count($columnNumbers) == 1) {
									foreach ($possibleValues as $fixIndex => $fixInfo) {
										if ($fixInfo['block_number'] == $saveBlockNumber || $fixInfo['column'] != $columnNumbers[0]) {
											continue;
										}
										$possibleValues[$fixIndex]['possible_values'] = array_diff($fixInfo['possible_values'],array($thisValue));
										if (count($possibleValues[$fixIndex]['possible_values']) != count($fixInfo['possible_values'])) {
											$pairsFound = true;
										}
									}
								}
							}
						}
						$saveBlockNumber = $possibleInfo['block_number'];
						$rowArray = array();
						$columnArray = array();
					}
					foreach ($possibleInfo['possible_values'] as $thisValue) {
						if (!array_key_exists($thisValue,$rowArray)) {
							$rowArray[$thisValue] = array();
						}
						if (!array_key_exists($thisValue,$columnArray)) {
							$columnArray[$thisValue] = array();
						}
						if (!in_array($possibleInfo['row'],$rowArray[$thisValue])) {
							$rowArray[$thisValue][] = $possibleInfo['row'];
						}
						if (!in_array($possibleInfo['column'],$columnArray[$thisValue])) {
							$columnArray[$thisValue][] = $possibleInfo['column'];
						}
					}
				}
				if ($pairsFound) {
					$someRemoved = true;
				}
			} while ($pairsFound);

# Look for Box line reductions. This is where the only occurrences of a number are in one block of a row or column, so that number can be removed from other cells of that block

			$blockArray = array();
			do {
				$pairsFound = false;
				usort($possibleValues,array($this, "possibleValuesRowSort"));
				$saveRowNumber = -1;
				foreach (array_merge($possibleValues,$endingArray) as $index => $possibleInfo) {
					if ($saveRowNumber != $possibleInfo['row']) {
						if ($saveRowNumber >= 0) {
							foreach ($blockArray as $thisValue => $blockNumbers) {
								if (count($blockNumbers) == 1) {
									foreach ($possibleValues as $fixIndex => $fixInfo) {
										if ($fixInfo['row'] == $saveRowNumber || $fixInfo['block_number'] != $blockNumbers[0]) {
											continue;
										}
										$possibleValues[$fixIndex]['possible_values'] = array_diff($fixInfo['possible_values'],array($thisValue));
										if (count($possibleValues[$fixIndex]['possible_values']) != count($fixInfo['possible_values'])) {
											$pairsFound = true;
										}
									}
								}
							}
						}
						$saveRowNumber = $possibleInfo['row'];
						$blockArray = array();
					}
					foreach ($possibleInfo['possible_values'] as $thisValue) {
						if (!array_key_exists($thisValue,$blockArray)) {
							$blockArray[$thisValue] = array();
						}
						if (!in_array($possibleInfo['block_number'],$blockArray[$thisValue])) {
							$blockArray[$thisValue][] = $possibleInfo['block_number'];
						}
					}
				}
				if ($pairsFound) {
					$someRemoved = true;
				}
			} while ($pairsFound);

			do {
				$pairsFound = false;
				usort($possibleValues,array($this, "possibleValuesColumnSort"));
				$saveColumnNumber = -1;
				foreach (array_merge($possibleValues,$endingArray) as $index => $possibleInfo) {
					if ($saveColumnNumber != $possibleInfo['column']) {
						if ($saveColumnNumber >= 0) {
							foreach ($blockArray as $thisValue => $blockNumbers) {
								if (count($blockNumbers) == 1) {
									foreach ($possibleValues as $fixIndex => $fixInfo) {
										if ($fixInfo['column'] == $saveColumnNumber || $fixInfo['block_number'] != $blockNumbers[0]) {
											continue;
										}
										$possibleValues[$fixIndex]['possible_values'] = array_diff($fixInfo['possible_values'],array($thisValue));
										if (count($possibleValues[$fixIndex]['possible_values']) != count($fixInfo['possible_values'])) {
											$pairsFound = true;
										}
									}
								}
							}
						}
						$saveColumnNumber = $possibleInfo['column'];
						$blockArray = array();
					}
					foreach ($possibleInfo['possible_values'] as $thisValue) {
						if (!array_key_exists($thisValue,$blockArray)) {
							$blockArray[$thisValue] = array();
						}
						if (!in_array($possibleInfo['block_number'],$blockArray[$thisValue])) {
							$blockArray[$thisValue][] = $possibleInfo['block_number'];
						}
					}
				}
				if ($pairsFound) {
					$someRemoved = true;
				}
			} while ($pairsFound);

			# Simple Chain

			# look for cell with only 2 numbers in it
			# for each number, see if there are only two of that number in row/column/block
			# if so, see if there are only two of that number in the new row/column/block (but not the original)
			# if so, after 4 cells, see if there are any of that number in any cells that have both the original and the last cell in common and remove them
			# check after 6 cells

			# X-Wing

			# Look for row or column that has one number in the same two column/row
			# Remove that number from all other rows/columns for that column/row

			# Y-Wing

			# look for a cell with two possibles (A & B)
			# Then, look for two cells that this cell can see that have a common number that is not A or B, AC & BC.
			# Any cell that can be seen by both these cells cannot have C in it

		} while ($someRemoved);

		return $possibleValues;
	}

	public function hasUniqueSolution() {
		return (!$this->hasSolution(true));
	}

	private function createValidBoard() {
		$this->iCurrentBoard = $this->emptyBoard();
		$this->randomFillBoard();
		return $this->iCurrentBoard;
	}

	private function randomFillBoard() {
		$valuesArray = array(1,2,3,4,5,6,7,8,9);
		shuffle($valuesArray);
		$cell = $this->getNextEmptyCell();
		if (empty($cell)) {
			return true;
		}
		foreach ($valuesArray as $thisValue) {
			$this->iStepCount++;
			if ($this->isCellValid($cell['row'],$cell['column'],$thisValue)) {
				$this->iCurrentBoard[$cell['row']][$cell['column']] = $thisValue;
				if ($this->randomFillBoard()) {
					return true;
				}
			}
		}
		$this->iCurrentBoard[$cell['row']][$cell['column']] = 0;
		return false;
	}

	private function emptyBoard() {
		$emptyBoard = array();
		$emptyBoard[] = array(0,0,0,0,0,0,0,0,0);
		$emptyBoard[] = array(0,0,0,0,0,0,0,0,0);
		$emptyBoard[] = array(0,0,0,0,0,0,0,0,0);
		$emptyBoard[] = array(0,0,0,0,0,0,0,0,0);
		$emptyBoard[] = array(0,0,0,0,0,0,0,0,0);
		$emptyBoard[] = array(0,0,0,0,0,0,0,0,0);
		$emptyBoard[] = array(0,0,0,0,0,0,0,0,0);
		$emptyBoard[] = array(0,0,0,0,0,0,0,0,0);
		$emptyBoard[] = array(0,0,0,0,0,0,0,0,0);
		return $emptyBoard;
	}

	public function getMostDifficult() {
		$puzzle = array();
		$puzzle[] = array(8,0,0,0,0,0,0,0,0);
		$puzzle[] = array(0,0,3,6,0,0,0,0,0);
		$puzzle[] = array(0,7,0,0,9,0,2,0,0);
		$puzzle[] = array(0,5,0,0,0,7,0,0,0);
		$puzzle[] = array(0,0,0,0,4,5,7,0,0);
		$puzzle[] = array(0,0,0,1,0,0,0,3,0);
		$puzzle[] = array(0,0,1,0,0,0,0,6,8);
		$puzzle[] = array(0,0,8,5,0,0,0,1,0);
		$puzzle[] = array(0,9,0,0,0,0,4,0,0);
		return $puzzle;
	}

	private function getNextEmptyCell($getCount = false) {
		$cell = false;
		$count = 0;
		foreach ($this->iCurrentBoard as $rowNumber => $boardRow) {
			foreach ($boardRow as $columnNumber => $value) {
				if ($value == 0) {
					$count++;
					if ($cell === false) {
						$cell = array("row"=>$rowNumber,"column"=>$columnNumber);
					}
					if (!$getCount) {
						break;
					}
				}
			}
		}
		if ($this->iDebug && $getCount) {
			echo $count . " empty cells still found<br>";
		}
		return $cell;
	}

	private function fillCell($cell) {
		$cellValue = $this->iCurrentBoard[$cell['row']][$cell['column']];
		$cellValue++;
		for ($testCellValue = $cellValue;$testCellValue <= 9;$testCellValue++) {
			if (!$this->isCellValid($cell['row'],$cell['column'],$testCellValue)) {
				continue;
			}
			$this->iStepCount++;
			$this->iCurrentBoard[$cell['row']][$cell['column']] = $testCellValue;
			$nextCell = $this->getNextEmptyCell();
			if (empty($nextCell)) {
				if ($this->iSkipOne) {
					$this->iFirstSolution = $this->iCurrentBoard;
					$this->iSkipOne = false;
					continue;
				}
				return true;
			}
			if ($this->fillCell($nextCell)) {
				if ($this->iSkipOne) {
					$this->iSkipOne = false;
					continue;
				}
				return true;
			}
		}
		$this->iCurrentBoard[$cell['row']][$cell['column']] = 0;
		return false;
	}

	private function isCellValid($row,$column,$value,$board = false) {
		if ($board === false || !is_array($board)) {
			$board = $this->iCurrentBoard;
		}
		$blockNumber = $this->getBlockNumber($row,$column);
		foreach ($board as $rowNumber => $boardRow) {
			foreach ($boardRow as $columnNumber => $thisValue) {
				if ($row == $rowNumber && $column == $columnNumber) {
					continue;
				}
				if ($row == $rowNumber && $value == $thisValue) {
					return false;
				}
				if ($column == $columnNumber && $value == $thisValue) {
					return false;
				}
				$thisBlockNumber = $this->getBlockNumber($rowNumber,$columnNumber);
				if ($blockNumber == $thisBlockNumber && $value == $thisValue) {
					return false;
				}
			}
		}
		return true;
	}

	private function getBlockNumber($row,$column) {
		return (int)(((ceil(($row + 1) / 3) - 1) * 3) + ceil(($column + 1) / 3));
	}
}
