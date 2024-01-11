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

/*
%module:giving_progress:designation_code=XXXXX%

Options:
designation_code=xxxxx - limit to products also in this category
link_url=https://www.domainname.org/donate/giving-project - URL to go to when the progress bar is clicked
element_id=xxxxxxx - wrapper element around the progress bar
text_content= - text displayed in the progress bar. Default is %description% - $%amount%, where description and amount come from the designation giving goal
*/

class GivingProgressPageModule extends PageModule {
	function createContent() {
		$resultSet = executeQuery("select * from designation_giving_goals where designation_id = (select designation_id from designations where client_id = ? and inactive = 0 and designation_code = ?) and (start_date is null or start_date <= current_date) and (end_date is null or end_date >= current_date) order by end_date is null,end_date", $GLOBALS['gClientId'], $this->iParameters['designation_code']);
		if ($row = getNextRow($resultSet)) {
			$total = 0;
			$countSet = executeQuery("select sum(amount) from donations where designation_id = ? and donation_date between ? and ?", $row['designation_id'],
				(empty($row['start_date']) ? "1900-01-01" : $row['start_date']), (empty($row['end_date']) ? "2500-01-01" : $row['end_date']));
			if ($countRow = getNextRow($countSet)) {
				$total = $countRow['sum(amount)'];
			}
			if ($total > $row['amount']) {
				$total = $row['amount'];
			}
			if ($row['amount'] > 0) {
				if (!empty($this->iParameters['element_id'])) {
					?>
                    <div id="<?= strtolower(makeCode($this->iParameters['element_id'])) ?>">
					<?php
				}
			if (!empty($this->iParameters['link_url'])) {
				?>
                <a href="<?= $this->iParameters['link_url'] ?>">
				<?php
			}
				?>
                <style>
                    #goal_progress {
                        width: 80%;
                        max-width: 600px;
                        margin: 20px 0;
                        border-radius: 5px;
                        height: 100px;
                        background-color: rgb(255, 255, 255);
                        overflow: hidden;
                        position: relative;
                        border: 1px solid rgb(0, 125, 0);
                    }

                    #goal_progress_bar {
                        width: calc(100% - 30px);
                        height: 25px;
                        position: absolute;
                        bottom: 15px;
                        left: 15px;
                        border: 1px solid rgb(240,240,240);
                        overflow: hidden;
                        font-size: 16px;
                        border-radius: 10px;
                        text-align: center;
                        font-weight: bold;
                    }

                    #goal_progress_fill {
                        max-width: 100%;
                        height: 30px;
                        background-color: rgb(0, 125, 0);
                        position: absolute;
                        top: 0;
                        left: 0;
                    }

                    #goal_progress_description {
                        font-size: 1rem;
                        color: rgb(20, 30, 40);
                        text-align: left;
                        line-height: 24px;
                        z-index: 1000;
                        position: absolute;
                        top: 20px;
                        left: 20px;
                        width: calc(100% - 40px);
                    }
                </style>
				<?php
				$percentDone = round($total * 100 / $row['amount'], 2);
				?>
                <div id="goal_progress">
                    <div id="goal_progress_description"><?= $row['description'] ?></div>
                    <div id="goal_progress_bar"><div id="goal_progress_fill" style="width: <?= $percentDone ?>%"></div><?= $percentDone ?>% achieved</div>
                </div>
				<?php
			if (!empty($this->iParameters['link_url'])) {
				?>
                </a>
				<?php
			}
				if (!empty($this->iParameters['element_id'])) {
					?>
                    </div>>
					<?php
				}
			}
		}
	}
}
