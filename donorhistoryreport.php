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

$GLOBALS['gPageCode'] = "DONORHISTORYREPORT";
require_once "shared/startup.inc";

class DonorHistoryReportPage extends Page implements BackgroundReport {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "create_report":
				$returnArray = self::getReportContent();
				if (array_key_exists("report_export", $returnArray)) {
					if (is_array($returnArray['export_headers'])) {
						foreach ($returnArray['export_headers'] as $thisHeader) {
							header($thisHeader);
						}
					}
					echo $returnArray['report_export'];
				} else {
					echo jsonEncode($returnArray);
				}
				exit;
		}
	}

	public static function getReportContent() {
		$returnArray = array();
		saveStoredReport(static::class);

		processPresetDates($_POST['preset_dates'], "donation_date_from", "donation_date_to");

		$exportOutput = ($_POST['report_type'] == "export" || $_POST['report_type'] == "summaryexport");
		$summaryExportOutput = $_POST['report_type'] == "summaryexport";
		$detailReport = $_POST['report_type'] == "detail";
		$fullName = getUserDisplayName($GLOBALS['gUserId']);
		$totalCount = 0;
		$totalFees = 0;
		$totalDonations = 0;

		$whereStatement = "";
		$donationWhereStatement = "";
		$parameters = array($GLOBALS['gClientId']);
		$donationParameters = array();
		$displayCriteria = "";

		if (empty($_POST['include_not_tax_deductible'])) {
			$donationWhereStatement .= (empty($donationWhereStatement) ? "" : " and ") . "designation_id in (select designation_id from designations where not_tax_deductible = 0)";
		}

		if (!empty($_POST['donation_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "donation_id = ?";
			if (!empty($donationWhereStatement)) {
				$donationWhereStatement .= " and ";
			}
			$donationWhereStatement .= "donation_id = ?";
			$parameters[] = $_POST['donation_id'];
			$donationParameters[] = $_POST['donation_id'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Donation ID is " . $_POST['donation_id'];
		}

		if (!empty($_POST['contact_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "contacts.contact_id = ?";
			$parameters[] = $_POST['contact_id'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Donor ID is " . $_POST['contact_id'];
		}

		if (!empty($_POST['donation_date_from'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "donation_date >= ?";
			if (!empty($donationWhereStatement)) {
				$donationWhereStatement .= " and ";
			}
			$donationWhereStatement .= "donation_date >= ?";
			$parameters[] = makeDateParameter($_POST['donation_date_from']);
			$donationParameters[] = makeDateParameter($_POST['donation_date_from']);
		}
		if (!empty($_POST['donation_date_to'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "donation_date <= ?";
			if (!empty($donationWhereStatement)) {
				$donationWhereStatement .= " and ";
			}
			$donationWhereStatement .= "donation_date <= ?";
			$parameters[] = makeDateParameter($_POST['donation_date_to']);
			$donationParameters[] = makeDateParameter($_POST['donation_date_to']);
		}
		if (!empty($_POST['donation_date_from']) && !empty($_POST['donation_date_to'])) {
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Donation date is between " . date("m/d/Y", strtotime($_POST['donation_date_from'])) . " and " . date("m/d/Y", strtotime($_POST['donation_date_to']));
		} else if (!empty($_POST['donation_date_from'])) {
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Donation date is on or after " . date("m/d/Y", strtotime($_POST['donation_date_from']));
		} else if (!empty($_POST['donation_date_to'])) {
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Donation date is on or before " . date("m/d/Y", strtotime($_POST['donation_date_to']));
		}

		if (!empty($_POST['pay_period_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "pay_period_id = ?";
			if (!empty($donationWhereStatement)) {
				$donationWhereStatement .= " and ";
			}
			$donationWhereStatement .= "pay_period_id = ?";
			$parameters[] = $_POST['pay_period_id'];
			$donationParameters[] = $_POST['pay_period_id'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Pay Period is from " . date("m/d/Y", strtotime(getFieldFromId("date_created", "pay_periods", "pay_period_id", $_POST['pay_period_id'])));
		}

		$datePaidOutWhere = "";
		if (!empty($_POST['date_paid_out_from'])) {
			if (!empty($datePaidOutWhere)) {
				$datePaidOutWhere .= " and ";
			}
			$datePaidOutWhere = "date_paid_out >= ?";
			$parameters[] = makeDateParameter($_POST['date_paid_out_from']);
			$donationParameters[] = makeDateParameter($_POST['date_paid_out_from']);
		}
		if (!empty($_POST['date_paid_out_to'])) {
			if (!empty($datePaidOutWhere)) {
				$datePaidOutWhere .= " and ";
			}
			$datePaidOutWhere .= "date_paid_out <= ?";
			$parameters[] = makeDateParameter($_POST['date_paid_out_to']);
			$donationParameters[] = makeDateParameter($_POST['date_paid_out_to']);
		}
		if (!empty($_POST['date_paid_out_from']) && !empty($_POST['date_paid_out_to'])) {
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Date paid is between " . date("m/d/Y", strtotime($_POST['date_paid_out_from'])) . " and " . date("m/d/Y", strtotime($_POST['date_paid_out_to']));
		} else if (!empty($_POST['date_paid_out_from'])) {
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Date paid is on or after " . date("m/d/Y", strtotime($_POST['date_paid_out_from']));
		} else if (!empty($_POST['date_paid_out_to'])) {
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Date paid is on or before " . date("m/d/Y", strtotime($_POST['date_paid_out_to']));
		}
		if (!empty($datePaidOutWhere)) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "pay_period_id in (select pay_period_id from pay_periods where " . $datePaidOutWhere . " and client_id = ?)";
			if (!empty($donationWhereStatement)) {
				$donationWhereStatement .= " and ";
			}
			$donationWhereStatement .= "pay_period_id in (select pay_period_id from pay_periods where " . $datePaidOutWhere . " and client_id = ?)";
			$parameters[] = $GLOBALS['gClientId'];
			$donationParameters[] = $GLOBALS['gClientId'];
		}
		if (!empty($_POST['only_not_paid'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "(pay_period_id is null or pay_period_id in (select pay_period_id from pay_periods where client_id = ? and date_paid_out is null))";
			if (!empty($donationWhereStatement)) {
				$donationWhereStatement .= " and ";
			}
			$donationWhereStatement .= "(pay_period_id is null or pay_period_id in (select pay_period_id from pay_periods where client_id = ? and date_paid_out is null))";
			$parameters[] = $GLOBALS['gClientId'];
			$donationParameters[] = $GLOBALS['gClientId'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Not yet paid out";
		}

		if (!empty($_POST['payment_method_type_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "payment_method_id in (select payment_method_id from payment_methods where payment_method_type_id = ?)";
			if (!empty($donationWhereStatement)) {
				$donationWhereStatement .= " and ";
			}
			$donationWhereStatement .= "payment_method_id in (select payment_method_id from payment_methods where payment_method_type_id = ?)";
			$parameters[] = $_POST['payment_method_type_id'];
			$donationParameters[] = $_POST['payment_method_type_id'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Payment method type is " . getFieldFromId("description", "payment_method_types", "payment_method_type_id", $_POST['payment_method_type_id']);
		}

		if (!empty($_POST['payment_method_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "payment_method_id = ?";
			if (!empty($donationWhereStatement)) {
				$donationWhereStatement .= " and ";
			}
			$donationWhereStatement .= "payment_method_id = ?";
			$parameters[] = $_POST['payment_method_id'];
			$donationParameters[] = $_POST['payment_method_id'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Payment method is " . getFieldFromId("description", "payment_methods", "payment_method_id", $_POST['payment_method_id']);
		}

		$batchWhere = "";
		if (!empty($_POST['batch_number_from']) && !empty($_POST['batch_number_to'])) {
			if (!empty($batchWhere)) {
				$batchWhere .= " and ";
			}
			$batchWhere .= "batch_number between ? and ?";
			$parameters[] = $GLOBALS['gClientId'];
			$parameters[] = $_POST['batch_number_from'];
			$parameters[] = $_POST['batch_number_to'];
			$donationParameters[] = $GLOBALS['gClientId'];
			$donationParameters[] = $_POST['batch_number_from'];
			$donationParameters[] = $_POST['batch_number_to'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Batch Number is between " . $_POST['batch_number_from'] . " and " . $_POST['batch_number_to'];
		} else if (!empty($_POST['batch_number_from'])) {
			if (!empty($batchWhere)) {
				$batchWhere .= " and ";
			}
			$batchWhere .= "batch_number >= ?";
			$parameters[] = $GLOBALS['gClientId'];
			$parameters[] = $_POST['batch_number_from'];
			$donationParameters[] = $GLOBALS['gClientId'];
			$donationParameters[] = $_POST['batch_number_from'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Batch number is greater than or equal " . $_POST['batch_number_from'];
		} else if (!empty($_POST['batch_number_to'])) {
			if (!empty($batchWhere)) {
				$batchWhere .= " and ";
			}
			$batchWhere .= "batch_number <= ?";
			$parameters[] = $GLOBALS['gClientId'];
			$parameters[] = $_POST['batch_number_to'];
			$donationParameters[] = $GLOBALS['gClientId'];
			$donationParameters[] = $_POST['batch_number_to'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Batch number is less than or equal " . $_POST['batch_number_to'];
		}
		if (!empty($batchWhere)) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "(" . (empty($_POST['include_no_batch']) ? "" : "donation_batch_id is null or ") . "donation_batch_id in (select donation_batch_id from donation_batches where client_id = ? and (" . $batchWhere . ")))";
			if (!empty($donationWhereStatement)) {
				$donationWhereStatement .= " and ";
			}
			$donationWhereStatement .= "(" . (empty($_POST['include_no_batch']) ? "" : "donation_batch_id is null or ") . "donation_batch_id in (select donation_batch_id from donation_batches where client_id = ? and (" . $batchWhere . ")))";
			if (!empty($_POST['include_no_batch'])) {
				$displayCriteria .= " (Include donations with no batch)";
			}
		} else if (!empty($_POST['include_no_batch'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "donation_batch_id is null";
			if (!empty($donationWhereStatement)) {
				$donationWhereStatement .= " and ";
			}
			$donationWhereStatement .= "donation_batch_id is null";
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Donation Batch is empty";
		}

		if (!empty($_POST['ignore_backout'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "associated_donation_id is null";
			if (!empty($donationWhereStatement)) {
				$donationWhereStatement .= " and ";
			}
			$donationWhereStatement .= "associated_donation_id is null";
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Donation not reversed";
		}

		if (!empty($_POST['amount_from']) && !empty($_POST['amount_to'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "amount between ? and ?";
			if (!empty($donationWhereStatement)) {
				$donationWhereStatement .= " and ";
			}
			$donationWhereStatement .= "amount between ? and ?";
			$parameters[] = $_POST['amount_from'];
			$parameters[] = $_POST['amount_to'];
			$donationParameters[] = $_POST['amount_from'];
			$donationParameters[] = $_POST['amount_to'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Amount is between " . $_POST['amount_from'] . " and " . $_POST['amount_to'];
		} else if (!empty($_POST['amount_from'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "amount >= ?";
			if (!empty($donationWhereStatement)) {
				$donationWhereStatement .= " and ";
			}
			$donationWhereStatement .= "amount >= ?";
			$parameters[] = $_POST['amount_from'];
			$donationParameters[] = $_POST['amount_from'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Amount is greater than or equal " . $_POST['amount_from'];
		} else if (!empty($_POST['amount_to'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "amount <= ?";
			if (!empty($donationWhereStatement)) {
				$donationWhereStatement .= " and ";
			}
			$donationWhereStatement .= "amount <= ?";
			$parameters[] = $_POST['amount_to'];
			$donationParameters[] = $_POST['amount_to'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Amount is less than or equal " . $_POST['amount_to'];
		}

		if (!empty($_POST['designation_groups'])) {
			$designationGroupArray = explode(",", $_POST['designation_groups']);
		} else {
			$designationGroupArray = array();
		}
		if (count($designationGroupArray) > 0) {
			$designationGroupWhere = "";
			$displayDesignationGroups = "";
			foreach ($designationGroupArray as $designationGroupId) {
				$designationGroupId = getFieldFromId("designation_group_id", "designation_groups", "designation_group_id", $designationGroupId);
				if (empty($designationGroupId)) {
					continue;
				}
				if (!empty($designationGroupWhere)) {
					$designationGroupWhere .= ",";
				}
				$designationGroupWhere .= "?";
				$parameters[] = $designationGroupId;
				$donationParameters[] = $designationGroupId;
				if (!empty($displayDesignationGroups)) {
					$displayDesignationGroups .= ", ";
				}
				$displayDesignationGroups .= getFieldFromId("description", "designation_groups", "designation_group_id", $designationGroupId);
			}
			if (!empty($designationGroupWhere)) {
				if (!empty($whereStatement)) {
					$whereStatement .= " and ";
				}
				$whereStatement .= "designation_id in (select designation_id from designation_group_links where designation_group_id in (" . $designationGroupWhere . "))";
				if (!empty($donationWhereStatement)) {
					$donationWhereStatement .= " and ";
				}
				$donationWhereStatement .= "designation_id in (select designation_id from designation_group_links where designation_group_id in (" . $designationGroupWhere . "))";
				if (!empty($displayCriteria)) {
					$displayCriteria .= " and ";
				}
				$displayCriteria .= "Designation Group in (" . $displayDesignationGroups . ")";
			}
		}

		if (!empty($_POST['designations'])) {
			$designationArray = explode(",", $_POST['designations']);
		} else {
			$designationArray = array();
		}
		if (count($designationArray) > 0) {
			$designationWhere = "";
			$displayDesignations = "";
			foreach ($designationArray as $designationId) {
				$designationId = getFieldFromId("designation_id", "designations", "designation_id", $designationId);
				if (empty($designationId)) {
					continue;
				}
				if (!empty($designationWhere)) {
					$designationWhere .= ",";
				}
				$designationWhere .= "?";
				$parameters[] = $designationId;
				$donationParameters[] = $designationId;
				if (!empty($displayDesignations)) {
					$displayDesignations .= ", ";
				}
				$displayDesignations .= getFieldFromId("designation_code", "designations", "designation_id", $designationId);
			}
			if (!empty($designationWhere)) {
				if (!empty($whereStatement)) {
					$whereStatement .= " and ";
				}
				$whereStatement .= "designation_id in (" . $designationWhere . ")";
				if (!empty($donationWhereStatement)) {
					$donationWhereStatement .= " and ";
				}
				$donationWhereStatement .= "designation_id in (" . $designationWhere . ")";
				if (!empty($displayCriteria)) {
					$displayCriteria .= " and ";
				}
				$displayCriteria .= "Designation Code in (" . $displayDesignations . ")";
			}
		}

		if (!empty($_POST['project_name'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "project_name like ?";
			if (!empty($donationWhereStatement)) {
				$donationWhereStatement .= " and ";
			}
			$donationWhereStatement .= "project_name like ?";
			$parameters[] = "%" . $_POST['project_name'] . "%";
			$donationParameters[] = "%" . $_POST['project_name'] . "%";
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Project Name contains '" . $_POST['project_name'] . "'";
		}

		$subqueryWhere = "";
		if (!empty($_POST['total_from']) && !empty($_POST['total_to'])) {
			if (!empty($subqueryWhere)) {
				$subqueryWhere .= " and ";
			}
			$subqueryWhere .= "(select sum(amount) from donations d where client_id = ? and contact_id = contacts.contact_id" .
				(empty($whereStatement) ? "" : " and " . $whereStatement) . ") between ? and ?";
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= $subqueryWhere;
			$parameters = array_merge($parameters, $parameters);
			$parameters[] = $_POST['total_from'];
			$parameters[] = $_POST['total_to'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Total Giving is between " . $_POST['total_from'] . " and " . $_POST['total_to'];
		} else if (!empty($_POST['total_from'])) {
			if (!empty($subqueryWhere)) {
				$subqueryWhere .= " and ";
			}
			$subqueryWhere .= "(select sum(amount) from donations d where client_id = ? and contact_id = contacts.contact_id" .
				(empty($whereStatement) ? "" : " and " . $whereStatement) . ") >= ?";
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= $subqueryWhere;
			$parameters = array_merge($parameters, $parameters);
			$parameters[] = $_POST['total_from'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Total giving is greater than or equal " . $_POST['total_from'];
		} else if (!empty($_POST['total_to'])) {
			if (!empty($subqueryWhere)) {
				$subqueryWhere .= " and ";
			}
			$subqueryWhere .= "(select sum(amount) from donations d where client_id = ? and contact_id = contacts.contact_id" .
				(empty($whereStatement) ? "" : " and " . $whereStatement) . ") <= ?";
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= $subqueryWhere;
			$parameters = array_merge($parameters, $parameters);
			$parameters[] = $_POST['total_to'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Total giving is less than or equal " . $_POST['total_to'];
		}

		$subqueryWhere = "";
		if (!empty($_POST['total_gifts_from']) && !empty($_POST['total_gifts_to'])) {
			if (!empty($subqueryWhere)) {
				$subqueryWhere .= " and ";
			}
			$subqueryWhere .= "(select count(*) from donations d where client_id = ? and contact_id = contacts.contact_id" .
				(empty($whereStatement) ? "" : " and " . $whereStatement) . ") between ? and ?";
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= $subqueryWhere;
			$parameters = array_merge($parameters, $parameters);
			$parameters[] = $_POST['total_gifts_from'];
			$parameters[] = $_POST['total_gifts_to'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Total Gifts is between " . $_POST['total_gifts_from'] . " and " . $_POST['total_gifts_to'];
		} else if (!empty($_POST['total_gifts_from'])) {
			if (!empty($subqueryWhere)) {
				$subqueryWhere .= " and ";
			}
			$subqueryWhere .= "(select count(*) from donations d where client_id = ? and contact_id = contacts.contact_id" .
				(empty($whereStatement) ? "" : " and " . $whereStatement) . ") >= ?";
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= $subqueryWhere;
			$parameters = array_merge($parameters, $parameters);
			$parameters[] = $_POST['total_gifts_from'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Total Gifts is greater than or equal " . $_POST['total_gifts_from'];
		} else if (!empty($_POST['total_gifts_to'])) {
			if (!empty($subqueryWhere)) {
				$subqueryWhere .= " and ";
			}
			$subqueryWhere .= "(select count(*) from donations d where client_id = ? and contact_id = contacts.contact_id" .
				(empty($whereStatement) ? "" : " and " . $whereStatement) . ") <= ?";
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= $subqueryWhere;
			$parameters = array_merge($parameters, $parameters);
			$parameters[] = $_POST['total_gifts_to'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Total Gifts is less than or equal " . $_POST['total_to'];
		}

		ob_start();
		$parameters = array_merge($donationParameters, $donationParameters, $parameters);

		$resultSet = executeReadQuery("select *,(select sum(amount) from donations where contact_id = contacts.contact_id" .
			(empty($donationWhereStatement) ? "" : " and " . $donationWhereStatement) . ") total_donations," .
			"(select donation_date from donations where contact_id = contacts.contact_id" .
			(empty($donationWhereStatement) ? "" : " and " . $donationWhereStatement) . " order by donation_date desc limit 1) last_gift_date" .
			" from donations join contacts using (contact_id) where donations.client_id = ?" .
			(empty($whereStatement) ? "" : " and " . $whereStatement) .
			" order by " . ($_POST['sort_order'] == "name" ? "last_name,first_name,business_name,donations.contact_id,donation_date" :
				($_POST['sort_order'] == "gift" ? "total_donations desc" : "last_gift_date desc,donations.contact_id")),
			$parameters);

		if (!$exportOutput) {
			?>
            <h1>Donor History <?= ($detailReport ? "Details" : "Summary") ?> Report</h1>
            <p><?= $displayCriteria ?></p>
            <p>Run on <?= date("m-d-Y") ?> by <?= $fullName ?></p>
            <table class="grid-table">
			<?php if ($detailReport) { ?>
                <tr>
                    <th>Donor</th>
                    <th>Date</th>
                    <th>For</th>
                    <th>Batch #</th>
                    <th>Receipt #</th>
                    <th>Amount</th>
                </tr>
			<?php } else { ?>
                <tr>
                    <th>Donor ID</th>
                    <th>Donor</th>
                    <th>Count</th>
                    <th>Total</th>
                </tr>
			<?php } ?>
			<?php
		} else {
			if ($summaryExportOutput) {
				if (empty($_POST['include_address'])) {
					echo "\"Contact ID\",\"Name\",\"Designations\",\"Total Gifts\",\"Total Amount\"\r\n";
				} else {
					echo "\"Contact ID\",\"First Name\",\"Last Name\",\"Business Name\",\"Address\",\"Address2\",\"City\",\"State\",\"Postal Code\",\"Email Address\",\"Country\",\"Phone\",\"Phone\",\"Phone\",\"Phone\",\"Phone\",\"Designations\",\"Total Gifts\",\"Total Amount\"\r\n";
				}
			} else {
				if (empty($_POST['include_address'])) {
					echo "\"Contact ID\",\"Name\",\"Donation Date\",\"Amount\",\"Designation Code\",\"Designation\",\"Payment Method\",\"Recurring Gift\"\r\n";
				} else {
					echo "\"Contact ID\",\"First Name\",\"Last Name\",\"Business Name\",\"Address\",\"Address2\",\"City\",\"State\",\"Postal Code\",\"Email Address\",\"Country\",\"Phone\",\"Phone\",\"Phone\",\"Phone\",\"Phone\",\"Donation Date\",\"Amount\",\"Designation Code\",\"Designation\",\"Payment Method\",\"Recurring Gift\"\r\n";
				}
			}
		}
		$saveContact = "";
		$saveRow = array();
		$saveCount = 0;
		$saveFee = 0;
		$saveAmount = 0;
		$donorCount = 0;
		$designationList = array();
		$contactInfo = "";
		while ($row = getNextRow($resultSet)) {
			if ($exportOutput && !$summaryExportOutput) {
				echo '"' . $row['contact_id'] . '",';
				if (!empty($_POST['include_address'])) {
					echo '"' . str_replace('"', '""', $row['first_name']) . '",';
					echo '"' . str_replace('"', '""', $row['last_name']) . '",';
					echo '"' . str_replace('"', '""', $row['business_name']) . '",';
					echo '"' . str_replace('"', '""', $row['address_1']) . '",';
					echo '"' . str_replace('"', '""', $row['address_2']) . '",';
					echo '"' . str_replace('"', '""', $row['city']) . '",';
					echo '"' . str_replace('"', '""', $row['state']) . '",';
					echo '"' . str_replace('"', '""', $row['postal_code']) . '",';
					echo '"' . str_replace('"', '""', $row['email_address']) . '",';
					echo '"' . str_replace('"', '""', getFieldFromId("country_name", "countries", "country_id", $row['country_id'])) . '",';
					$phoneCount = 0;
					$phoneSet = executeReadQuery("select * from phone_numbers where contact_id = ? order by description", $row['contact_id']);
					while ($phoneRow = getNextRow($phoneSet)) {
						echo '"' . str_replace('"', '""', $phoneRow['phone_number'] . " " . $phoneRow['description']) . '",';
						$phoneCount++;
						if ($phoneCount >= 5) {
							break;
						}
					}
					while ($phoneCount < 5) {
						echo '"",';
						$phoneCount++;
					}
				} else {
					echo '"' . str_replace('"', '""', getDisplayName($row['contact_id'])) . '",';
				}
				echo '"' . str_replace('"', '""', date('m/d/y', strtotime($row['donation_date']))) . '",';
				echo '"' . str_replace('"', '""', number_format($row['amount'], 2)) . '",';
				echo '="' . str_replace('"', '""', getFieldFromId("designation_code", "designations", "designation_id", $row['designation_id'])) . '",';
				echo '"' . str_replace('"', '""', getFieldFromId("description", "designations", "designation_id", $row['designation_id'])) . '",';
				echo '"' . str_replace('"', '""', getFieldFromId("description", "payment_methods", "payment_method_id", $row['payment_method_id'])) . '",';
				echo '"' . (empty($row['recurring_donation_id']) ? "" : "YES") . '"' . "\r\n";
				continue;
			}
			if (empty($row['donation_fee'])) {
				$row['donation_fee'] = 0;
			}
			if ($row['contact_id'] != $saveContact) {
				if (!empty($saveContact)) {
					if ($exportOutput) {
						echo '"' . $saveContact . '",';
						if (!empty($_POST['include_address'])) {
							echo '"' . str_replace('"', '""', getFieldFromId("first_name", "contacts", "contact_id", $saveContact)) . '",';
							echo '"' . str_replace('"', '""', getFieldFromId("last_name", "contacts", "contact_id", $saveContact)) . '",';
							echo '"' . str_replace('"', '""', getFieldFromId("business_name", "contacts", "contact_id", $saveContact)) . '",';
							echo '"' . str_replace('"', '""', getFieldFromId("address_1", "contacts", "contact_id", $saveContact)) . '",';
							echo '"' . str_replace('"', '""', getFieldFromId("address_2", "contacts", "contact_id", $saveContact)) . '",';
							echo '"' . str_replace('"', '""', getFieldFromId("city", "contacts", "contact_id", $saveContact)) . '",';
							echo '"' . str_replace('"', '""', getFieldFromId("state", "contacts", "contact_id", $saveContact)) . '",';
							echo '"' . str_replace('"', '""', getFieldFromId("postal_code", "contacts", "contact_id", $saveContact)) . '",';
							echo '"' . str_replace('"', '""', getFieldFromId("email_address", "contacts", "contact_id", $saveContact)) . '",';
							echo '"' . str_replace('"', '""', getFieldFromId("country_name", "countries", "country_id", getFieldFromId("country_id", "contacts", "contact_id", $saveContact))) . '",';
							$phoneCount = 0;
							$phoneSet = executeReadQuery("select * from phone_numbers where contact_id = ? order by description", $saveContact);
							while ($phoneRow = getNextRow($phoneSet)) {
								echo '"' . str_replace('"', '""', $phoneRow['phone_number'] . " " . $phoneRow['description']) . '",';
								$phoneCount++;
								if ($phoneCount >= 5) {
									break;
								}
							}
							while ($phoneCount < 5) {
								echo '"",';
								$phoneCount++;
							}
						} else {
							echo '"' . str_replace('"', '""', getDisplayName($saveContact)) . '",';
						}
						$designationList = array_unique($designationList);
						sort($designationList);
						echo '"' . str_replace('"', '""', implode(",", $designationList)) . '",';
						echo '"' . str_replace('"', '""', $saveCount) . '",';
						echo '"' . str_replace('"', '""', number_format($saveAmount, 2)) . '"' . "\r\n";
					} else {
						if ($detailReport) {
							?>
                            <tr>
                                <td colspan="4">Total for <?= getDisplayName($saveContact) ?></td>
                                <td><?= number_format($saveCount, 0) ?> gift<?= ($saveCount == 1 ? "" : "s") ?></td>
                                <td class="align-right"><?= number_format($saveAmount, 2) ?></td>
                            </tr>
							<?php
						} else {
							$donorCount++;
							$contactInfo = getDisplayName($saveRow['contact_id']);
							if (!empty($saveRow['address_1'])) {
								$contactInfo .= "<br/>" . $saveRow['address_1'];
							}
							if (!empty($saveRow['address_2'])) {
								$contactInfo .= "<br/>" . $saveRow['address_2'];
							}
							if (!empty($saveRow['city'])) {
								$contactInfo .= "<br/>" . $saveRow['city'] . (empty($saveRow['state']) ? "" : ", " . $saveRow['state']) . (empty($saveRow['postal_code']) ? "" : " " . $saveRow['postal_code']);
							}
							$phoneSet = executeReadQuery("select * from phone_numbers where contact_id = ? order by description", $saveRow['contact_id']);
							while ($phoneRow = getNextRow($phoneSet)) {
								$contactInfo .= "<br/>" . $phoneRow['phone_number'] . (empty($phoneRow['description']) ? "" : " " . $phoneRow['description']);
							}
							if (!empty($saveRow['email_address'])) {
								$contactInfo .= "<br/>" . $saveRow['email_address'];
							}
							?>
                            <tr>
                                <td><?= $saveContact ?></td>
                                <td><?= $contactInfo ?></td>
                                <td class="align-right"><?= number_format($saveCount, 0) ?></td>
                                <td class="align-right"><?= number_format($saveAmount, 2) ?></td>
                            </tr>
							<?php
						}
					}
				}
				$saveContact = $row['contact_id'];
				$saveRow = $row;
				$saveCount = 0;
				$saveFee = 0;
				$saveAmount = 0;
				$designationList = array();
			}
			$totalCount++;
			$totalDonations += $row['amount'];
			$totalFees += $row['donation_fee'];
			$saveCount++;
			$saveFee += $row['donation_fee'];
			$saveAmount += $row['amount'];
			$contactInfo = getDisplayName($row['contact_id']);
			$designationList[] = getFieldFromId("designation_code", "designations", "designation_id", $row['designation_id']);
			if ($exportOutput) {
				continue;
			}
			if (!empty($row['address_1'])) {
				$contactInfo .= "<br/>" . $row['address_1'];
			}
			if (!empty($row['address_2'])) {
				$contactInfo .= "<br/>" . $row['address_2'];
			}
			if (!empty($row['city'])) {
				$contactInfo .= "<br/>" . $row['city'] . (empty($row['state']) ? "" : ", " . $row['state']) . (empty($row['postal_code']) ? "" : " " . $row['postal_code']);
			}
			$phoneSet = executeReadQuery("select * from phone_numbers where contact_id = ? order by description", $row['contact_id']);
			while ($phoneRow = getNextRow($phoneSet)) {
				$contactInfo .= "<br/>" . $phoneRow['phone_number'] . (empty($phoneRow['description']) ? "" : " " . $phoneRow['description']);
			}
			if (!empty($row['email_address'])) {
				$contactInfo .= "<br/>" . $row['email_address'];
			}
			if ($detailReport) {
				?>
                <tr>
                    <td><?= ($saveCount == 1 ? $contactInfo : "") ?></td>
                    <td><?= date("m/d/Y", strtotime($row['donation_date'])) ?></td>
                    <td><?= getFieldFromId("designation_code", "designations", "designation_id", $row['designation_id']) . " - " . getFieldFromId("description", "designations", "designation_id", $row['designation_id']) ?></td>
                    <td class="align-right"><?= getFieldFromId("batch_number", "donation_batches", "donation_batch_id", $row['donation_batch_id']) ?></td>
                    <td class="align-right"><?= $row['donation_id'] ?></td>
                    <td class="align-right"><?= number_format($row['amount'], 2) ?></td>
                </tr>
				<?php
			}
		}
		if (!empty($saveContact)) {
			if ($exportOutput) {
				echo '"' . $saveContact . '",';
				if (!empty($_POST['include_address'])) {
					echo '"' . str_replace('"', '""', getFieldFromId("first_name", "contacts", "contact_id", $saveContact)) . '",';
					echo '"' . str_replace('"', '""', getFieldFromId("last_name", "contacts", "contact_id", $saveContact)) . '",';
					echo '"' . str_replace('"', '""', getFieldFromId("business_name", "contacts", "contact_id", $saveContact)) . '",';
					echo '"' . str_replace('"', '""', getFieldFromId("address_1", "contacts", "contact_id", $saveContact)) . '",';
					echo '"' . str_replace('"', '""', getFieldFromId("address_2", "contacts", "contact_id", $saveContact)) . '",';
					echo '"' . str_replace('"', '""', getFieldFromId("city", "contacts", "contact_id", $saveContact)) . '",';
					echo '"' . str_replace('"', '""', getFieldFromId("state", "contacts", "contact_id", $saveContact)) . '",';
					echo '"' . str_replace('"', '""', getFieldFromId("postal_code", "contacts", "contact_id", $saveContact)) . '",';
					echo '"' . str_replace('"', '""', getFieldFromId("email_address", "contacts", "contact_id", $saveContact)) . '",';
					echo '"' . str_replace('"', '""', getFieldFromId("country_name", "countries", "country_id", getFieldFromId("country_id", "contacts", "contact_id", $saveContact))) . '",';
					$phoneCount = 0;
					$phoneSet = executeReadQuery("select * from phone_numbers where contact_id = ? order by description", $saveContact);
					while ($phoneRow = getNextRow($phoneSet)) {
						echo '"' . str_replace('"', '""', $phoneRow['phone_number'] . " " . $phoneRow['description']) . '",';
						$phoneCount++;
						if ($phoneCount >= 5) {
							break;
						}
					}
					while ($phoneCount < 5) {
						echo '"",';
						$phoneCount++;
					}
				} else {
					echo '"' . str_replace('"', '""', getDisplayName($saveContact)) . '",';
				}
				echo '"' . str_replace('"', '""', $saveCount) . '",';
				echo '"' . str_replace('"', '""', number_format($saveAmount, 2)) . '"' . "\r\n";
			} else {
				if ($detailReport) {
					$contactInfo = getDisplayName($saveRow['contact_id']);
					if (!empty($saveRow['address_1'])) {
						$contactInfo .= "<br/>" . $saveRow['address_1'];
					}
					if (!empty($saveRow['address_2'])) {
						$contactInfo .= "<br/>" . $saveRow['address_2'];
					}
					if (!empty($saveRow['city'])) {
						$contactInfo .= "<br/>" . $saveRow['city'] . (empty($saveRow['state']) ? "" : ", " . $saveRow['state']) . (empty($saveRow['postal_code']) ? "" : " " . $saveRow['postal_code']);
					}
					$phoneSet = executeReadQuery("select * from phone_numbers where contact_id = ? order by description", $saveRow['contact_id']);
					while ($phoneRow = getNextRow($phoneSet)) {
						$contactInfo .= "<br/>" . $phoneRow['phone_number'] . (empty($phoneRow['description']) ? "" : " " . $phoneRow['description']);
					}
					if (!empty($saveRow['email_address'])) {
						$contactInfo .= "<br/>" . $saveRow['email_address'];
					}
					?>
                    <tr>
                        <td colspan="4">Total for <?= getDisplayName($saveContact) ?></td>
                        <td><?= number_format($saveCount, 0) ?> gift<?= ($saveCount == 1 ? "" : "s") ?></td>
                        <td class="align-right"><?= number_format($saveAmount, 2) ?></td>
                    </tr>
					<?php
				} else {
					$donorCount++;
					?>
                    <tr>
                        <td><?= $saveContact ?></td>
                        <td><?= $contactInfo ?></td>
                        <td class="align-right"><?= number_format($saveCount, 0) ?></td>
                        <td class="align-right"><?= number_format($saveAmount, 2) ?></td>
                    </tr>
					<?php
				}
			}
		}
		if (!$exportOutput) {
			?>
            <tr>
                <td colspan='<?= ($detailReport ? "6" : "4") ?>'></td>
            </tr>
			<?php if (!$detailReport) { ?>
                <tr>
                    <td colspan='4'><?= $donorCount ?> Donors</td>
                </tr>
			<?php } ?>
            <tr>
                <td colspan='2'>Total for Report</td>
                <td colspan='<?= ($detailReport ? "3" : "1") ?>'><?= number_format($totalCount, 0) ?> gift<?= ($totalCount == 1 ? "" : "s") ?></td>
                <td class="align-right"><?= number_format($totalDonations, 2) ?></td>
            </tr>
            </table>
			<?php
		}
		$reportContent = ob_get_clean();
		if ($exportOutput) {
			$returnArray['export_headers'] = array();
			$returnArray['export_headers'][] = "Content-Type: text/csv";
			$returnArray['export_headers'][] = "Content-Disposition: attachment; filename=\"donations.csv\"";
			$returnArray['export_headers'][] = 'Cache-Control: must-revalidate, post-check=0, pre-check=0';
			$returnArray['export_headers'][] = 'Pragma: public';
			$returnArray['filename'] = "donations.csv";
			$returnArray['report_export'] = $reportContent;
		} else {
			$returnArray['report_content'] = $reportContent;
		}
		return $returnArray;
	}

	function mainContent() {
		?>
        <div id="report_parameters">
            <form id="_report_form" name="_report_form">

				<?php getStoredReports() ?>

                <div class="basic-form-line" id="_report_type_row">
                    <label for="report_type">Report Type</label>
                    <select tabindex="10" id="report_type" name="report_type">
                        <option value="summary">Summary</option>
                        <option value="detail">Details</option>
                        <option value="export">Export</option>
                        <option value="summaryexport">Summary Export</option>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_sort_order_row">
                    <label for="sort_order">Sort Order (Summary only)</label>
                    <select tabindex="10" id="sort_order" name="sort_order">
                        <option value="name" selected="selected">Name</option>
                        <option value="gift">Total Gifts</option>
                        <option value="last_gift_date">Date of last gift (most recent first)</option>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_donation_id_row">
                    <label for="donation_id">Donation ID</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[integer]]" id="donation_id" name="donation_id">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_contact_id_row">
                    <label for="contact_id">Donor ID</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[integer]]" id="contact_id" name="contact_id">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

				<?php getPresetDateOptions() ?>

                <div class="basic-form-line preset-date-custom" id="_donation_date_row">
                    <label for="donation_date_from">Donation Date: From</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="donation_date_from" name="donation_date_from">
                    <label class="second-label">Through</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="donation_date_to" name="donation_date_to">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_pay_period_id_row">
                    <label for="pay_period_id">Pay Period</label>
                    <select tabindex="10" id="pay_period_id" name="pay_period_id">
                        <option value="">[All]</option>
						<?php
						$resultSet = executeReadQuery("select * from pay_periods where client_id = ? order by date_created desc limit 20", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['pay_period_id'] ?>"><?= date("m/d/Y", strtotime($row['date_created'])) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_date_paid_out_row">
                    <label for="date_paid_out_from">Date Paid: From</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="date_paid_out_from" name="date_paid_out_from">
                    <label class="second-label">Through</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="date_paid_out_to" name="date_paid_out_to">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_only_not_paid_row">
                    <label></label>
                    <input tabindex="10" type="checkbox" id="only_not_paid" name="only_not_paid"><label class="checkbox-label" for="only_not_paid">Only Not Paid Out</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_payment_method_type_id_row">
                    <label for="payment_method_type_id">Payment Method Type</label>
                    <select tabindex="10" id="payment_method_type_id" name="payment_method_type_id">
                        <option value="">[All]</option>
						<?php
						$resultSet = executeReadQuery("select * from payment_method_types where inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['payment_method_type_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_payment_method_id_row">
                    <label for="payment_method_id">Payment Method</label>
                    <select tabindex="10" id="payment_method_id" name="payment_method_id">
                        <option value="">[All]</option>
						<?php
						$resultSet = executeReadQuery("select * from payment_methods where inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['payment_method_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_amount_row">
                    <label for="amount_from">Amount: From</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[number]]" data-decimal-places="2" id="amount_from" name="amount_from">
                    <label class="second-label">Through</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[number]]" data-decimal-places="2" id="amount_to" name="amount_to">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_total_row">
                    <label for="total_from">Total Giving: From</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[number]]" data-decimal-places="2" id="total_from" name="total_from">
                    <label class="second-label">Through</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[number]]" data-decimal-places="2" id="total_to" name="total_to">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_total_gifts_row">
                    <label for="total_gifts_from">Total Number of Gifts: From</label>
                    <input tabindex="10" type="text" size="10" maxlength="10" class="align-right validate[custom[integer]]" id="total_gifts_from" name="total_gifts_from">
                    <label class="second-label">Through</label>
                    <input tabindex="10" type="text" size="10" maxlength="10" class="align-right validate[custom[integer]]" id="total_gifts_to" name="total_gifts_to">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_batch_number_row">
                    <label for="batch_number_from">Batch Number: From</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[integer]]" id="batch_number_from" name="batch_number_from">
                    <label class="second-label">Through</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[integer]]" id="batch_number_to" name="batch_number_to">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_include_no_batch_row">
                    <input tabindex="10" type="checkbox" id="include_no_batch" name="include_no_batch"><label class="checkbox-label" for="include_no_batch">Include Donations without Batch</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_ignore_backout_row">
                    <input tabindex="10" type="checkbox" id="ignore_backout" name="ignore_backout" checked="checked"><label class="checkbox-label" for="ignore_backout">Ignore reversed donations</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

				<?php
				$designationGroupControl = new DataColumn("designation_groups");
				$designationGroupControl->setControlValue("data_type", "custom");
				$designationGroupControl->setControlValue("include_inactive", "true");
				$designationGroupControl->setControlValue("control_class", "MultiSelect");
				$designationGroupControl->setControlValue("control_table", "designation_groups");
				$designationGroupControl->setControlValue("links_table", "designation_groups");
				$designationGroupControl->setControlValue("primary_table", "donations");
				$customControl = new MultipleSelect($designationGroupControl, $this);
				?>
                <div class="basic-form-line custom-control-form-line custom-control-no-help" id="_designation_groups_row">
                    <label for="designation_groups">Designation Groups</label>
					<?= $customControl->getControl() ?>
                </div>

				<?php
				$designationControl = new DataColumn("designations");
				$designationControl->setControlValue("data_type", "custom");
				$designationControl->setControlValue("include_inactive", "true");
				$designationControl->setControlValue("control_class", "MultiSelect");
				$designationControl->setControlValue("control_table", "designations");
				$designationControl->setControlValue("links_table", "designations");
				$designationControl->setControlValue("primary_table", "donations");
				$customControl = new MultipleSelect($designationControl, $this);
				?>
                <div class="basic-form-line custom-control-form-line custom-control-no-help" id="_designations_row">
                    <label for="designations">Designations</label>
					<?= $customControl->getControl() ?>
                </div>

                <div class="basic-form-line" id="_project_name_row">
                    <label for="project_name">Project</label>
                    <input tabindex="10" type="text" size="30" maxlength="30" id="project_name" name="project_name">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_include_not_tax_deductible_row">
                    <label></label>
                    <input tabindex="10" type="checkbox" id="include_not_tax_deductible" name="include_not_tax_deductible"><label class="checkbox-label" for="include_not_tax_deductible">Include designations that are not tax-deductible</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_include_address_row">
                    <label></label>
                    <input tabindex="10" type="checkbox" id="include_address" name="include_address"><label class="checkbox-label" for="include_address">Include name & address (only applies to export)</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

				<?php if ($GLOBALS['gUserRow']['superuser_flag']) { ?>
                    <div class="basic-form-line" id="_extra_where_row">
                        <label for="extra_where">Where</label>
                        <input tabindex="10" type="text" size="60" id="extra_where" name="extra_where">
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>
				<?php } ?>

				<?php storedReportDescription() ?>

                <div class="basic-form-line">
                    <button tabindex="10" id="create_report">Create Report</button>
                </div>

            </form>
        </div>
        <div id="_button_row">
            <button id="refresh_button">Refresh</button>
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
                input = $("<input>").attr("type", "hidden").attr("name", "filename").val("donorhistory.pdf");
                $('#_pdf_form').append($(input));
                $("#_pdf_form").attr("action", "/reportpdf.php").attr("method", "POST").submit();
                return false;
            });
            $(document).on("tap click", "#create_report,#refresh_button", function () {
                if ($("#_report_form").validationEngine("validate")) {
                    const reportType = $("#report_type").val();
                    if (reportType === "export" || reportType === "summaryexport") {
                        $("#_report_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?url_action=create_report").attr("method", "POST").submit();
                    } else {
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
            #_button_row {
                display: none;
                margin-bottom: 20px;
            }
        </style>
		<?php
	}
}

$pageObject = new DonorHistoryReportPage();
$pageObject->displayPage();
