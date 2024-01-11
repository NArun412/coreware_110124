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

	$eCommerce = eCommerce::getEcommerceInstance();
	$designationLabel = $this->getPageTextChunk("designation_label");
	if (empty($designationLabel)) {
		$designationLabel = "Designated For";
	}
	$totalDesignations = array();
	$designationArray = array();
	$parameters = array();
	$query = "client_id = ? and inactive = 0 and internal_use_only = 0";
	$parameters[] = $GLOBALS['gClientId'];
	if (!empty($_GET['id'])) {
		$query .= " and designation_id = ?";
		$parameters[] = $_GET['id'];
	}
	if (!empty($_GET['code'])) {
		$query .= " and designation_code = ?";
		$parameters[] = strtoupper($_GET['code']);
	}
	if (empty($_GET['code']) && !empty($_GET['group'])) {
		$query .= " and designation_id in (select designation_id from designation_group_links where " .
			"designation_group_id = (select designation_group_id from designation_groups where " .
			"client_id = ? and designation_group_code = ?))";
		$parameters[] = $GLOBALS['gClientId'];
		$parameters[] = strtoupper($_GET['group']);
		$donationSourceId = getFieldFromId("donation_source_id","designation_groups","designation_group_code",$_GET['group']);
		if (!empty($donationSourceId)) {
			$_GET['donation_source_id'] = $donationSourceId;
		}
		$customDetailedDescription = getFieldFromId("detailed_description","designation_groups","designation_group_code",$_GET['group']);
		$customTitle = getFieldFromId("description","designation_groups","designation_group_code",$_GET['group']);
	} else if (empty($_GET['code']) && !empty($_GET['designation_group_id'])) {
		$query .= " and designation_id in (select designation_id from designation_group_links where " .
			"designation_group_id = ?)";
		$parameters[] = $_GET['designation_group_id'];
		$donationSourceId = getFieldFromId("donation_source_id","designation_groups","designation_group_id",$_GET['designation_group_id']);
		if (!empty($donationSourceId)) {
			$_GET['donation_source_id'] = $donationSourceId;
		}
		$customDetailedDescription = getFieldFromId("detailed_description","designation_groups","designation_group_id",$_GET['designation_group_id']);
		$customTitle = getFieldFromId("description","designation_groups","designation_group_id",$_GET['designation_group_id']);
	}

	if (!empty($_GET['crowd_fund_campaign_participant_id'])) {
		$query .= " and designation_id in (select designation_id from crowd_fund_campaign_participant_designations where " .
			"crowd_fund_campaign_participant_id = ?)";
		$parameters[] = $_GET['crowd_fund_campaign_participant_id'];
		$donationSourceId = getFieldFromId("donation_source_id","crowd_fund_campaign_participants","crowd_fund_campaign_participant_id",$_GET['crowd_fund_campaign_participant_id']);
		if (!empty($donationSourceId)) {
			$_GET['donation_source_id'] = $donationSourceId;
		}
		$customDetailedDescription = getFieldFromId("detailed_description","crowd_fund_campaigns","crowd_fund_campaign_id",getFieldFromId("crowd_fund_campaign_id","crowd_fund_campaign_participants","crowd_fund_campaign_participant_id",$_GET['crowd_fund_campaign_participant_id']));
		$personalizedDetailedDescription = getFieldFromId("detailed_description","crowd_fund_campaign_participants","crowd_fund_campaign_participant_id",$_GET['crowd_fund_campaign_participant_id']);
		if (!isHtml($personalizedDetailedDescription)) {
			$customDetailedDescription .= (empty($customDetailedDescription) ? "" : "\r") . $personalizedDetailedDescription;
		}
		$customTitle = getFieldFromId("title_text","crowd_fund_campaign_participants","crowd_fund_campaign_participant_id",$_GET['crowd_fund_campaign_participant_id']);
	}

	if (empty($_GET['code']) && !empty($_GET['type'])) {
		$query .= " and designation_type_id = (select designation_type_id from designation_types where client_id = ? and designation_type_code = ?)";
		$parameters[] = $GLOBALS['gClientId'];
		$parameters[] = strtoupper($_GET['type']);
	}
	$resultSet = executeQuery("select * from designations where " . $query . " order by sort_order,description",$parameters);
	while ($row = getNextRow($resultSet)) {
		$totalDesignations[$row['designation_id']] = (empty($row['alias']) ? $row['description'] : $row['alias']);
	}

	if (empty($_GET['id']) && empty($_GET['code'])) {
		$query .= " and (";
		if ($GLOBALS['gLoggedIn']) {
			$query .= "designation_id in (select designation_id from donations where contact_id = ?) or ";
			$parameters[] = $GLOBALS['gUserRow']['contact_id'];
		}
		$query .= "(designation_id not in (select designation_id from designation_group_links) or " .
			"designation_id not in (select designation_id from designation_group_links where designation_group_id in (select " .
			"designation_group_id from designation_groups where inactive = 0 and internal_use_only = 1)) or " .
			"public_access = 1))";
	}

	$resultSet = executeQuery("select * from designations where " . $query . " order by sort_order,description",$parameters);
	while ($row = getNextRow($resultSet)) {
		$designationArray[$row['designation_id']] = $row;
	}

	$designationRow = array();
	if ((!empty($_GET['id']) || !empty($_GET['code'])) && count($designationArray) == 1) {
		reset($designationArray);
		$designationId = key($designationArray);
		$description = $designationArray[$designationId]['alias'];
		if (empty($description)) {
			$designationGroupId = getFieldFromId("designation_group_id","designation_group_links","designation_id",$designationId,"designation_group_id in (select designation_group_id from designation_groups where hide_description = 1)");
			if (!empty($designationGroupId)) {
				$description = $designationArray[$designationId]['designation_code'];
			} else {
				$description = $designationArray[$designationId]['description'];
			}
		}
		$detailedDescription = $designationArray[$designationId]['detailed_description'];
        $resultSet = executeQuery("select * from designation_giving_goals where designation_id = ? and (start_date is null or start_date <= current_date) and (end_date is null or end_date >= current_date) order by end_date is null,end_date", $designationId);
        if ($row = getNextRow($resultSet)) {
            $total = 0;
            $countSet = executeQuery("select sum(amount) from donations where designation_id = ? and donation_date between ? and ?", $row['designation_id'],
                (empty($row['start_date']) ? "1900-01-01" : $row['start_date']), (empty($row['end_date']) ? "2500-01-01" : $row['end_date']));
            if ($countRow = getNextRow($countSet)) {
                $total = $countRow['sum(amount)'];
            }
            if ($total < $row['amount'] && $row['amount'] > 0) {
                ob_start();
                ?>
                <div id="goal_progress"><div id="goal_progress_bar" style="width: <?= round($total * 100 / $row['amount'],2) ?>%"></div><div id="goal_progress_description"><?= $row['description'] . " - $" . number_format($row['amount'],2,",",".") ?></div></div>
                <?php
                $progress = ob_get_clean();
                $detailedDescription = $progress . $detailedDescription;
            }
        }
		$designationRow = getRowFromId("designations","designation_id",$designationId);
	}
	$imageId = getFieldFromId("image_id","designations","designation_id",$designationId);
	if (!empty($imageId)) {
		$allowImage = true;
		$groupSet = executeQuery("select * from designation_groups where designation_group_id in (select designation_group_id " .
			"from designation_group_links where designation_id = ?)",$designationId);
		while ($groupRow = getNextRow($groupSet)) {
			if (empty($groupRow['allow_image'])) {
				$allowImage = false;
				break;
			}
		}
	}
	$presetAmountString = getPreference("preset_donations");
	if (!empty($presetAmountString)) {
		$presetAmounts = explode(",",str_replace(" ",",",$presetAmountString));
	}
	$customPresetAmount = $this->getPageTextChunk("preset_amount_1");
	if (!empty($customPresetAmount) && is_numeric($customPresetAmount)) {
		$presetAmounts = array();
		$presetIndex = 0;
		while (true) {
			$presetIndex++;
			$presetAmount = $this->getPageTextChunk("preset_amount_" . $presetIndex);
			if (strlen($presetAmount) == 0) {
				break;
			}
			$presetText = $this->getPageTextChunk("preset_text_" . $presetIndex);
			$presetAmounts[] = array("amount"=>$presetAmount,"text"=>$presetText,"id"=>"preset_amount_" . $presetIndex);
		}
	}
	echo makeHtml(getFragment("donation_introduction"));
?>
<div id="_donation_form">
<form name="_edit_form" id="_edit_form" method="POST">
<input type="hidden" id="_add_hash" name="_add_hash" value="<?= md5(uniqid(mt_rand(), true)) ?>">

<?= (!empty($customTitle) ? "<h2>" . $customTitle . "</h2>" : "") ?>
<div id="_designation_group_detailed_description">
<?= (isHTML($customDetailedDescription) ? $customDetailedDescription : makeHtml($customDetailedDescription)) ?>
<div class='clear-div'></div>
</div>

<?php if (!((empty($_GET['id']) && empty($_GET['code'])) || count($designationArray) != 1)) { ?>
<div id="_designation_information">
<div id="_detailed_description_row">
<?php if ($allowImage) { ?>
<p id="designation_image"><img src="<?= getImageFilename($imageId,array("use_cdn"=>true)) ?>"></p>
<?php } ?>
<?= (isHTML($detailedDescription) ? htmlText($detailedDescription) : makeHtml($detailedDescription)) ?>
</div> <!-- detailed_description_row -->
</div> <!-- _designation_information -->
<?php } ?>

<?php
	if ($GLOBALS['gLoggedIn']) {
		$linkUrl = "logout.php?url=" . urlencode($_SERVER['REQUEST_URI']);
?>
<p id="if_not_user">If you are not <?= getUserDisplayName() ?>, log out <a href="<?= $linkUrl ?>">here</a>.</p>
<?php } ?>
<div class="donation-section" data-next_section="_donor_info_section" id="_donate_section">

<?= $this->getPageTextChunk("introduction_text") ?>

<h2>Donation Information</h2>
<p class="error-message" id="_top_error_message"></p>
<?php
	if (!empty($presetAmounts)) {
?>
<div class="form-line" id="_preset_amounts_row">
<?php
			foreach ($presetAmounts as $presetInformation) {
				if (is_array($presetInformation)) {
					$thisAmount = $presetInformation['amount'];
					$thisDescription = $presetInformation['text'];
					$thisAmountId = $presetInformation['id'];
				} else {
					$thisAmount = $presetInformation;
					$thisDescription = "";
					$thisAmountId = "preset_amount_" . makeCode($thisAmount);
				}
				if (!is_numeric($thisAmount)) {
					if (empty($thisDescription)) {
						$thisDescription = $thisAmount;
					}
					$thisAmount = 0;
				}
?>
	<button tabindex="10" id='<?= $thisAmountId ?>' class="preset-amount" data-amount="<?= $thisAmount ?>"><?php if (!empty($thisAmount) && $thisAmount > 0) { ?>$<?= $thisAmount ?><?php } ?><?= (empty($thisDescription) ? "" : ((!empty($thisAmount) && $thisAmount > 0) ? "<br>" : "") . $thisDescription) ?></button>
<?php
			}
?>
	<div class='clear-div'></div>
</div>
<?php
		}
?>
<div class="form-line" id="_amount_row">
	<label for="amount" class="required-label">Amount (US <span class='fa fa-dollar-sign'></span>)</label>
	<input tabindex="10" type="text" size="12" maxlength="12" class="validate[required,custom[number]] align-right min[1]" id="amount" name="amount" placeholder="Amount (USD)" data-decimal-places="2" value="<?= (is_numeric($_GET['amount']) && !empty($_GET['amount']) ? number_format($_GET['amount'],2,".","") : "") ?>">
	<div class='clear-div'></div>
</div>

<?php if ((empty($_GET['id']) && empty($_GET['code'])) || count($designationArray) != 1) { ?>
<div class="form-line" id="_designation_id_row">
	<input type="hidden" id="merchant_account_id" name="merchant_account_id" value="">
	<label for="designation_id" class="required-label"><?= htmlText($designationLabel) ?></label>
	<select tabindex="10" class="validate[required]" id="designation_id" name="designation_id">
		<option data-merchant_account_id="" value="">[Select<?= (count($totalDesignations) > count($designationArray) ? " or enter code below" : "") ?>]</option>
<?php
        if (empty($_GET['default_designation_id']) && !empty($_GET['default_designation_code'])) {
            $_GET['default_designation_id'] = getFieldFromId("designation_id","designations","designation_code",$_GET['default_designation_code'],"inactive = 0 and internal_use_only = 0");
        }
		foreach ($designationArray as $designationId => $designationInfo) {
			$description = (empty($designationInfo['alias']) ? $designationInfo['description'] : $designationInfo['alias']);
			$merchantAccountId = $GLOBALS['gMerchantAccountId'];
?>
		<option data-merchant_account_id="<?= (empty($merchantAccountId) ? $GLOBALS['gMerchantAccountId'] : $merchantAccountId) ?>"<?= ((count($totalDesignations) == 1 && count($designationArray) == 1) || $_GET['default_designation_id'] == $designationId ? " selected" : "") ?> value="<?= $designationId ?>"><?= htmlText($description) ?></option>
<?php
		}
?>
	</select>
	<div class='clear-div'></div>
</div>

<div id="_designation_information">
<div id="_detailed_description_row">
</div>
</div>

<?php
    $showDesignationCodeField = $this->getPageTextChunk("SHOW_DESIGNATION_CODE_FIELD");
    if ($showDesignationCodeField || count($totalDesignations) > count($designationArray)) {
?>
<div class="form-line" id="_designation_code_row">
	<label for="designation_code" class="">Designation Code</label>
	<input tabindex="10" type="text" size="20" maxlength="100" id="designation_code" name="designation_code" placeholder="Designation Code">
	<p id="designation_code_error"></p>
	<div class='clear-div'></div>
</div>
<?php } ?>
<?php
	} else {
		$merchantAccountId = $GLOBALS['gMerchantAccountId'];
?>
<input type="hidden" id="merchant_account_id" name="merchant_account_id" value="<?= $merchantAccountId ?>">
<input type="hidden" id="designation_id" name="designation_id" data-merchant_account_id="<?= $merchantAccountId ?>" value="<?= $designationId ?>">
<p id="for_designation">For: <span class="highlighted-text"><?= htmlText($description) ?></span></p>
<?php } ?>
<p id="tax_deductible_message" class="hidden"></p>

<?php
	$projectNameArray = array();
	$projectLabel = "Project";
	$projectRequired = false;

	if ((!empty($_GET['id']) || !empty($_GET['code'])) && count($designationArray) == 1) {
		reset($designationArray);
		$designationId = key($designationArray);

		$resultSet = executeQuery("select * from designation_projects where designation_id = ?",$designationId);
		while ($row = getNextRow($resultSet)) {
			$projectNameArray[] = $row['project_name'];
		}

		$projectNameFields = getMultipleFieldsFromId(array("project_label","project_required"),"designation_types","designation_type_id",
			getFieldFromId("designation_type_id","designations","designation_id",$designationId));
		$projectLabel = (empty($projectNameFields['project_label']) ? $projectLabel : $projectNameFields['project_label']);
		$projectRequired = (empty($projectNameFields['project_label']) ? $projectRequired : $projectNameFields['project_required']);

		$projectNameFields = getMultipleFieldsFromId(array("project_label","project_required"),"designations","designation_id",$designationId);
		$projectLabel = (empty($projectNameFields['project_label']) ? $projectLabel : $projectNameFields['project_label']);
		$projectRequired = (empty($projectNameFields['project_label']) ? $projectRequired : $projectNameFields['project_required']);
	}
?>
<div class="form-line" id="_project_name_row">
	<label for="project_name" id="project_label"<?= ($projectRequired ? " class='required-label'" : "") ?>><?= htmlText($projectLabel) ?></label>
	<select tabindex="10" id="project_name" name="project_name" class="<?= ($projectRequired ? "validate[required]" : "") ?>">
		<option id="no_project" value="">[None]</option>
<?php
	foreach ($projectNameArray as $projectName) {
?>
		<option value="<?= htmlText(str_replace('"','',$projectName)) ?>"<?= ($projectName == $_GET['project'] ? " selected" : "") ?>><?= htmlText($projectName) ?></option>
<?php
	}
?>
	</select>
	<div class='clear-div'></div>
</div>

<?php
	$memoLabel = "";
	$memoRequired = false;
	if ((!empty($_GET['id']) || !empty($_GET['code'])) && count($designationArray) == 1) {
			reset($designationArray);
		$designationId = key($designationArray);
		$memoNameFields = getMultipleFieldsFromId(array("memo_label","memo_required"),"designations","designation_id",$designationId);
		$memoLabel = (empty($memoNameFields['memo_label']) ? $memoLabel : $memoNameFields['memo_label']);
		$memoRequired = (empty($memoNameFields['memo_label']) ? $memoRequired : $memoNameFields['memo_required']);
	}
?>
<div class="form-line" id="_notes_row">
	<label for="notes" id="notes_label"<?= ($memoRequired ? " class='required-label'" : "") ?>><?= $memoLabel ?></label>
	<input tabindex="10" type="text" size="20" maxlength="255" id="notes" name="notes" class="<?= ($memoRequired ? "validate[required]" : "") ?>" placeholder="<?= $memoLabel ?>" value="<?= htmlText($_GET['memo']) ?>">
	<div class='clear-div'></div>
</div>

<?php
		$donationSourceId = getFieldFromId("donation_source_id","donation_sources","donation_source_id",$_GET['donation_source_id']);
		if (empty($donationSourceId)) {
			$donationSourceId = getFieldFromId("donation_source_id","donation_sources","donation_source_code",$_GET['donation_source_code']);
		}
		if (empty($donationSourceId)) {
			$resultSet = executeQuery("select * from donation_sources where client_id = ? and inactive = 0 and internal_use_only = 0 " .
				"order by sort_order,description",$GLOBALS['gClientId']);
			if ($resultSet['row_count'] > 0) {
?>

<div class="form-line" id="_donation_source_id_row">
	<label for="donation_source_id">What brought you here?</label>
	<select tabindex="10" id="donation_source_id" name="donation_source_id">
		<option value="">[Unknown]</option>
<?php
		while ($row = getNextRow($resultSet)) {
?>
		<option value="<?= $row['donation_source_id'] ?>"><?= htmlText($row['description']) ?></option>
<?php
		}
?>
	</select>
	<div class='clear-div'></div>
</div>
<?php
			}
		} else {
?>
<input type="hidden" id="donation_source_id" name="donation_source_id" value="<?= $donationSourceId ?>">
<?php
		}
?>

<div class="form-line checkbox-input" id="_anonymous_gift_row">
	<input tabindex="10" type="checkbox" id="anonymous_gift" name="anonymous_gift" value="1"><label class="checkbox-label" for="anonymous_gift">Make this gift anonymous</label>
	<div class='clear-div'></div>
</div>

<?php if (!array_key_exists("one_time",$_GET)) { ?>
<div class="form-line checkbox-input" id="_recurring_donation_type_id_row">
<?php
		$selectedRecurringDonationTypeId = "";
		$recurringDonationTypes = array();
		$resultSet = executeQuery("select * from recurring_donation_types where manual_processing = 0 and client_id = ? and " .
			"inactive = 0 and internal_use_only = 0 order by sort_order,description",$GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			if (strtoupper($_GET['recurring_donation_type_code']) == $row['recurring_donation_type_code'] || $_GET['recurring_donation_type_id'] == $row['recurring_donation_type_id']) {
				$selectedRecurringDonationTypeId = $row['recurring_donation_type_id'];
			}
			$recurringDonationTypes[] = $row;
		}
		$useSelect = $this->getPageTextChunk("RECURRING_DONATION_TYPE_SELECT");
		if (empty($useSelect)) {
?>
		<p><input type="radio" tabindex="10" name="recurring_donation_type_id" id="recurring_donation_type_id" <?= (empty($selectedRecurringDonationTypeId) ? "checked='checked'" : "") ?> value=""><label class="checkbox-label" for="recurring_donation_type_id">One Time Donation</label></p>
<?php
			foreach ($recurringDonationTypes as $row) {
?>
		<p id="recurring_donation_type_<?= $row['recurring_donation_type_code'] ?>_wrapper"><input type="radio" tabindex="10" name="recurring_donation_type_id" id="recurring_donation_type_id_<?= $row['recurring_donation_type_id'] ?>" <?= ($selectedRecurringDonationTypeId == $row['recurring_donation_type_id'] ? "checked='checked'" : "") ?> value="<?= $row['recurring_donation_type_id'] ?>"><label class="checkbox-label" for="recurring_donation_type_id_<?= $row['recurring_donation_type_id'] ?>"><?= htmlText($row['description']) ?></label></p>
<?php
			}
		} else {
?>
		<label>Donation Type</label>
		<select tabindex="10" name="recurring_donation_type_id" id="recurring_donation_type_id">
			<option selected value="">One Time Donation</option>
<?php
			foreach ($recurringDonationTypes as $row) {
?>
			<option value="<?= $row['recurring_donation_type_id'] ?>"<?= ($selectedRecurringDonationTypeId == $row['recurring_donation_type_id'] ? " selected" : "") ?>><?= htmlText($row['description']) ?></option>
<?php
			}
?>
		</select>
<?php
		}
?>
	<div class='clear-div'></div>
</div>
<?php } ?>

<div class="form-line" id="_start_date_row">
	<label for="start_date" class="">Start Date</label>
	<input tabindex="10" type="text" class="validate[min[<?= date("m/01/Y") ?>],required,custom[date]]" size="12" id="start_date" name="start_date" placeholder="Start Date" data-default_value="<?= date("m/d/Y") ?>" value="<?= date("m/d/Y") ?>">
	<div class='clear-div'></div>
</div>

</div> <!-- donate_section -->

<div class="donation-section" data-previous_section="_donate_section" data-next_section="_billing_info_section" id="_donor_info_section">
<h2>Your Information</h2>
<?php
	if ($GLOBALS['gLoggedIn']) {
		$userRow = $GLOBALS['gUserRow'];
		if ($GLOBALS['gUserRow']['administrator_flag']) {
?>
<p class='highlighted-text error-message'>As an administrator, you should make changes to your user account with "My Account". Make sure you do not add a donation or recurring gift that is from someone else!</p>
<?php
		} else {
?>
<p>Any changes will also be made to your user account.</p>
<?php
		}
	} else {
		$userRow = array();
	}
?>
<div class="form-line" id="_first_name_row">
	<label for="first_name" class="required-label">First Name</label>
	<input tabindex="10" type="text" <?= ($GLOBALS['gUserRow']['administrator_flag'] ? "readonly='readonly'" : "class='validate[required]" . (in_array("first_name",$capitalizedFields) ? " capitalize" : "") . "'") ?> size="25" maxlength="25" id="first_name" name="first_name" placeholder="First Name" value="<?= htmlText($userRow['first_name']) ?>">
	<div class='clear-div'></div>
</div>

<div class="form-line" id="_last_name_row">
	<label for="last_name" class="required-label">Last Name</label>
	<input tabindex="10" type="text" <?= ($GLOBALS['gUserRow']['administrator_flag'] ? "readonly='readonly'" : "class='validate[required]" . (in_array("last_name",$capitalizedFields) ? " capitalize" : "") . "'") ?> size="30" maxlength="35" id="last_name" name="last_name" placeholder="Last Name" value="<?= htmlText($userRow['last_name']) ?>">
	<div class='clear-div'></div>
</div>

<div class="form-line" id="_business_name_row">
	<label for="business_name">Business Name</label>
	<input tabindex="10" type="text" <?= ($GLOBALS['gUserRow']['administrator_flag'] ? "readonly='readonly'" : "class='" . (in_array("business_name",$capitalizedFields) ? "validate[] capitalize" : "") . "'") ?> size="30" maxlength="35" id="business_name" name="business_name" placeholder="Business Name" value="<?= htmlText($userRow['business_name']) ?>">
	<div class='clear-div'></div>
</div>

<div class="form-line" id="_address_1_row">
	<label for="address_1" class="required-label">Address</label>
	<input tabindex="10" autocomplete="chrome-off" autocomplete="off" type="text" <?= ($GLOBALS['gUserRow']['administrator_flag'] ? "readonly='readonly'" : "class='validate[required] autocomplete-address" . (in_array("address_1",$capitalizedFields) ? " capitalize" : "") . "'") ?> size="30" maxlength="60" id="address_1" name="address_1" placeholder="Address" value="<?= htmlText($userRow['address_1']) ?>">
	<div class='clear-div'></div>
</div>

<div class="form-line" id="_address_2_row">
	<label for="address_2" class=""></label>
	<input tabindex="10" type="text" <?= ($GLOBALS['gUserRow']['administrator_flag'] ? "readonly='readonly'" : "class='" . (in_array("address_2",$capitalizedFields) ? "validate[] capitalize" : "") . "'") ?> size="30" maxlength="60" id="address_2" name="address_2" value="<?= htmlText($userRow['address_2']) ?>">
	<div class='clear-div'></div>
</div>

<div class="form-line" id="_city_row">
	<label for="city" class="required-label">City</label>
	<input tabindex="10" type="text" <?= ($GLOBALS['gUserRow']['administrator_flag'] ? "readonly='readonly'" : "class='validate[required]" . (in_array("city",$capitalizedFields) ? " capitalize" : "") . "'") ?> size="30" maxlength="60" id="city" name="city" placeholder="City" value="<?= htmlText($userRow['city']) ?>">
	<div class='clear-div'></div>
</div>

<div class="form-line" id="_state_row">
	<label for="state" class="">State</label>
	<input tabindex="10" type="text" <?= ($GLOBALS['gUserRow']['administrator_flag'] ? "readonly='readonly'" : "class='validate[required]" . (in_array("state",$capitalizedFields) ? " capitalize" : "") . "'") ?> data-conditional-required="$('#country_id').val() == 1000" size="10" maxlength="30" id="state" name="state" placeholder="State" value="<?= htmlText($userRow['state']) ?>">
	<div class='clear-div'></div>
</div>

<?php if (!$GLOBALS['gUserRow']['administrator_flag']) { ?>
<div class="form-line" id="_state_select_row">
	<label for="state_select" class="">State</label>
	<select tabindex="10" id="state_select" name="state_select" class='validate[required]' data-conditional-required="$('#country_id').val() == 1000">
<?php if (!$GLOBALS['gUserRow']['administrator_flag'] || empty($userRow['state'])) { ?>
		<option value="">[Select]</option>
<?php } ?>
<?php
	foreach (getStateArray() as $stateCode => $state) {
		if ($GLOBALS['gUserRow']['administrator_flag'] && $userRow['state'] != $stateCode && !empty($userRow['state'])) {
			continue;
		}
?>
		<option value="<?= $stateCode ?>"<?= ($userRow['state'] == $stateCode ? " selected" : "") ?>><?= htmlText($state) ?></option>
<?php
	}
?>
	</select>
	<div class='clear-div'></div>
</div>
<?php } ?>

<div class="form-line" id="_postal_code_row">
	<label for="postal_code" class="">Postal Code</label>
	<input tabindex="10" type="text" <?= ($GLOBALS['gUserRow']['administrator_flag'] ? "readonly='readonly'" : "class='validate[required] uppercase'") ?> size="10" maxlength="10" data-conditional-required="$('#country_id').val() == 1000" id="postal_code" name="postal_code" placeholder="Postal Code" value="<?= htmlText($userRow['postal_code']) ?>">
	<div class='clear-div'></div>
</div>

<div class="form-line" id="_country_id_row">
	<label for="country_id" class="">Country</label>
	<select tabindex="10" class='validate[required]' id="country_id" name="country_id">
<?php
	foreach (getCountryArray(true) as $countryId => $countryName) {
		if ($GLOBALS['gUserRow']['administrator_flag'] && $userRow['country_id'] != $countryId) {
			continue;
		}
?>
	<option<?= ($userRow['country_id'] == $countryId ? " selected" : "") ?> value="<?= $countryId ?>"><?= htmlText($countryName) ?></option>
<?php
	}
?>
	</select>
	<div class='clear-div'></div>
</div>

<div class="form-line" id="_email_address_row">
	<label for="email_address" class="required-label">Email</label>
	<input tabindex="10" type="text" <?= ($GLOBALS['gUserRow']['administrator_flag'] ? "readonly='readonly'" : "class='validate[required,custom[email]]'") ?> size="30" maxlength="60" id="email_address" name="email_address" placeholder="Email Address" value="<?= htmlText($userRow['email_address']) ?>">
	<div class='clear-div'></div>
</div>

<?php
	$phoneNumberTypes = $this->getPageTextChunk("phone_number_types");
	if (empty($phoneNumberTypes)) {
		$phoneNumberTypes = array("home","cell");
	} else {
		$phoneNumberTypes = explode(",",$phoneNumberTypes);
	}
	foreach ($phoneNumberTypes as $phoneNumberType) {
		$fieldRequired = false;
		if (substr($phoneNumberType,0,1) == "*") {
			$fieldRequired = true;
			$phoneNumberType = makeCode(substr($phoneNumberType,1),array("lowercase"=>true));
		}
?>
<div class="form-line" id="_<?= $phoneNumberType ?>_phone_number_row">
	<label for="<?= $phoneNumberType ?>_phone_number" class="<?= ($fieldRequired ? "required-label" : "") ?>"><?= ucwords($phoneNumberType) ?></label>
	<input tabindex="10" type="text" <?= ($GLOBALS['gUserRow']['administrator_flag'] ? "readonly='readonly'" : "class='validate[" . ($fieldRequired ? "required," : "") . "custom[phone]]'") ?> size="20" maxlength="25" id="<?= $phoneNumberType ?>_phone_number" name="<?= $phoneNumberType ?>_phone_number" placeholder="<?= ucwords($phoneNumberType) ?>" value="<?= Contact::getContactPhoneNumber($GLOBALS['gUserRow']['contact_id'],$phoneNumberType) ?>">
	<div class='clear-div'></div>
</div>
<?php
	}
?>

</div> <!-- donor_info_section -->

<div class="donation-section" data-previous_section="_donor_info_section" data-next_section="_finalize_section" id="_billing_info_section">
<h2>Billing Information</h2>

<?php if ($GLOBALS['gUserRow']['administrator_flag']) { ?>
<p class='highlighted-text error-message'>As an administrator, you can add a donation that is FROM you. Make sure you do not add a donation or recurring gift that is from someone else!</p>
<?php } ?>
<?php
	$resultSet = executeQuery("select * from accounts where contact_id = ? and inactive = 0 and account_token is not null and (expiration_date is null or expiration_date > current_date)",$GLOBALS['gUserRow']['contact_id']);
	if ($resultSet['row_count'] == 0 || empty($eCommerce) || !$eCommerce->hasCustomerDatabase()) {
?>
<input type="hidden" id="account_id" name="account_id" value="">
<?php
	} else {
?>
<div class="form-line" id="_account_id_row">
	<label for="account_id" class="">Select Account</label>
	<select tabindex="10" id="account_id" name="account_id">
		<option value="">[New Account]</option>
<?php
	while ($row = getNextRow($resultSet)) {
		$merchantAccountId = eCommerce::getAccountMerchantAccount($row['account_id']);
?>
		<option data-merchant_account_id="<?= $merchantAccountId ?>" value="<?= $row['account_id'] ?>"><?= htmlText((empty($row['account_label']) ? $row['account_number'] : $row['account_label'])) ?></option>
<?php
	}
?>
	</select>
	<div class='clear-div'></div>
</div>
<?php } ?>

<div id="_new_account">

<div class="form-line checkbox-input" id="_same_address_row">
	<label class=""></label>
	<input tabindex="10" type="checkbox" id="same_address" name="same_address" checked="checked" value="1"><label class="checkbox-label" for="same_address">Billing address is same as above</label>
	<div class='clear-div'></div>
</div>

<div id="_billing_address">

<div class="form-line" id="_billing_first_name_row">
	<label for="billing_first_name" class="required-label">First Name</label>
	<input tabindex="10" type="text" class="validate[required]<?= (in_array("first_name",$capitalizedFields) ? " capitalize" : "") ?>" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="25" maxlength="25" id="billing_first_name" name="billing_first_name" placeholder="First Name" value="<?= htmlText($userRow['first_name']) ?>">
	<div class='clear-div'></div>
</div>

<div class="form-line" id="_billing_last_name_row">
	<label for="billing_last_name" class="required-label">Last Name</label>
	<input tabindex="10" type="text" class="validate[required]<?= (in_array("last_name",$capitalizedFields) ? " capitalize" : "") ?>" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="30" maxlength="35" id="billing_last_name" name="billing_last_name" placeholder="Last Name" value="<?= htmlText($userRow['last_name']) ?>">
	<div class='clear-div'></div>
</div>

<div class="form-line" id="_billing_business_name_row">
	<label for="billing_business_name">Business Name</label>
	<input tabindex="10" type="text" class="<?= (in_array("business_name",$capitalizedFields) ? "validate[] capitalize" : "") ?>" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="30" maxlength="35" id="billing_business_name" name="billing_business_name" placeholder="Business Name" value="<?= htmlText($userRow['business_name']) ?>">
	<div class='clear-div'></div>
</div>

<div class="form-line" id="_billing_address_1_row">
	<label for="billing_address_1" class="required-label">Street</label>
	<input tabindex="10" type="text" autocomplete="chrome-off" autocomplete="off" data-prefix="billing_" class="autocomplete-address validate[required]<?= (in_array("address_1",$capitalizedFields) ? " capitalize" : "") ?>" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="30" maxlength="60" id="billing_address_1" name="billing_address_1" placeholder="Address" value="">
	<div class='clear-div'></div>
</div>

<div class="form-line" id="_billing_address_2_row">
	<label for="billing_address_2" class=""></label>
	<input tabindex="10" type="text" class="<?= (in_array("address_2",$capitalizedFields) ? "validate[] capitalize" : "") ?>" size="30" maxlength="60" id="billing_address_2" name="billing_address_2" value="">
	<div class='clear-div'></div>
</div>

<div class="form-line" id="_billing_city_row">
	<label for="billing_city" class="required-label">City</label>
	<input tabindex="10" type="text" class="validate[required]<?= (in_array("city",$capitalizedFields) ? " capitalize" : "") ?>" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="30" maxlength="60" id="billing_city" name="billing_city" placeholder="City" value="">
	<div class='clear-div'></div>
</div>

<div class="form-line" id="_billing_state_row">
	<label for="billing_state" class="">State</label>
	<input tabindex="10" type="text" class="validate[required]<?= (in_array("state",$capitalizedFields) ? " capitalize" : "") ?>" data-conditional-required="($('#account_id').length == 0 || $('#account_id').val() == '') && $('#billing_country_id').val() == 1000" size="10" maxlength="30" id="billing_state" name="billing_state" placeholder="State" value="">
	<div class='clear-div'></div>
</div>

<div class="form-line" id="_billing_state_select_row">
	<label for="billing_state_select" class="">State</label>
	<select tabindex="10" id="billing_state_select" name="billing_state_select" class="validate[required]" data-conditional-required="$('#billing_country_id').val() == 1000">
		<option value="">[Select]</option>
<?php
	foreach (getStateArray() as $stateCode => $state) {
?>
		<option value="<?= $stateCode ?>"><?= htmlText($state) ?></option>
<?php
	}
?>
	</select>
	<div class='clear-div'></div>
</div>

<div class="form-line" id="_billing_postal_code_row">
	<label for="billing_postal_code" class="">Postal Code</label>
	<input tabindex="10" type="text" class="validate[required]" size="10" maxlength="10" data-conditional-required="($('#account_id').length == 0 || $('#account_id').val() == '') && $('#billing_country_id').val() == 1000" id="billing_postal_code" name="billing_postal_code" placeholder="Postal Code" value="">
	<div class='clear-div'></div>
</div>

<div class="form-line" id="_billing_country_id_row">
	<label for="billing_country_id" class="">Country</label>
	<select tabindex="10" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" id="billing_country_id" name="billing_country_id">
<?php
	foreach (getCountryArray() as $countryId => $countryName) {
?>
	<option value="<?= $countryId ?>"><?= htmlText($countryName) ?></option>
<?php
	}
?>
	</select>
	<div class='clear-div'></div>
</div>
</div> <!-- billing_address -->

<div id="payment_information">
<div class="form-line" id="_payment_method_id_row">
	<label for="payment_method_id" class="">Payment Method</label>
	<select tabindex="10" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" id="payment_method_id" name="payment_method_id">
		<option value="">[Select]</option>
<?php
	$paymentLogos = array();
	$resultSet = executeQuery("select *,(select payment_method_types.payment_method_type_code from payment_method_types where " .
        "payment_method_type_id = payment_methods.payment_method_type_id) payment_method_type_code from payment_methods where " .
        ($GLOBALS['gLoggedIn'] ? "" : "requires_user = 0 and ") .
        "(payment_method_id not in (select payment_method_id from payment_method_user_types) " .
        (empty($GLOBALS['gUserRow']['user_type_id']) ? "" : " or payment_method_id in (select payment_method_id from payment_method_user_types where user_type_id = " . $GLOBALS['gUserRow']['user_type_id'] . ")") . ") and " .
		"inactive = 0 and " . ($GLOBALS['gUserRow']['superuser_flag'] || $GLOBALS['gUserRow']['full_client_access'] ? "" : "internal_use_only = 0 and ") . "client_id = ? and (payment_method_type_id is null or payment_method_type_id in " .
		"(select payment_method_type_id from payment_method_types where inactive = 0 and internal_use_only = 0 and " .
		"client_id = ?)) order by sort_order,description",$GLOBALS['gClientId'],$GLOBALS['gClientId']);
	while ($row = getNextRow($resultSet)) {
		if (empty($row['image_id'])) {
			$paymentMethodRow = getRowFromId("payment_methods","payment_method_code",$row['payment_method_code'],"client_id = ?",$GLOBALS['gDefaultClientId']);
			$row['image_id'] = $paymentMethodRow['image_id'];
		}
		if (!empty($row['image_id'])) {
			$paymentLogos[$row['payment_method_id']] = $row['image_id'];
		}
?>
		<option value="<?= $row['payment_method_id'] ?>" data-payment_method_type_code="<?= strtolower($row['payment_method_type_code']) ?>"><?= htmlText($row['description']) ?></option>
<?php
	}
?>
	</select>
	<div class='clear-div'></div>
</div>

<div class="payment-method-fields" id="payment_method_credit_card">
<div class="form-line" id="_account_number_row">
	<label for="account_number" class="">Card Number</label>
	<input tabindex="10" type="text" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="20" maxlength="20" id="account_number" name="account_number" placeholder="Account Number" value="">
	<div id="payment_logos">
<?php
	foreach ($paymentLogos as $paymentMethodId => $imageId) {
?>
<img id="payment_method_logo_<?= strtolower($paymentMethodId) ?>" class="payment-method-logo" src="<?= getImageFilename($imageId,array("use_cdn"=>true)) ?>">
<?php
	}
?>
	</div>
	<div class='clear-div'></div>
</div>

<div class="form-line" id="_expiration_month_row">
	<label for="expiration_month" class="">Expiration Date</label>
	<select tabindex="10" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" id="expiration_month" name="expiration_month">
		<option value="">[Month]</option>
<?php
	for ($x=1;$x<=12;$x++) {
?>
		<option value="<?= $x ?>"><?= $x . " - " . date("F",strtotime($x . "/01/2000")) ?></option>
<?php
	}
?>
	</select>
	<select tabindex="10" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" id="expiration_year" name="expiration_year">
		<option value="">[Year]</option>
<?php
	for ($x=0;$x<12;$x++) {
		$year = date("Y") + $x;
?>
		<option value="<?= $year ?>"><?= $year ?></option>
<?php
	}
?>
	</select>
	<div class='clear-div'></div>
</div>

<div class="form-line" id="_cvv_code_row">
	<label for="cvv_code" class="">Security Code</label>
	<input tabindex="10" type="text" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="5" maxlength="4" id="cvv_code" name="cvv_code" placeholder="CVV Code" value="">
	<a href="https://www.cvvnumber.com/cvv.html" target="_blank"><img id="cvv_image" src="/images/cvv_code.gif" alt="CVV Code"></a>
	<div class='clear-div'></div>
</div>
</div> <!-- payment_method_credit_card -->

<div class="payment-method-fields" id="payment_method_bank_account">
<div class="form-line" id="_routing_number_row">
	<label for="routing_number" class="">Bank Routing Number</label>
	<input tabindex="10" type="text" class="validate[required,custom[routingNumber]]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="20" maxlength="20" id="routing_number" name="routing_number" placeholder="Routing Number" value="">
	<div class='clear-div'></div>
</div>

<div class="form-line" id="_bank_account_number_row">
	<label for="bank_account_number" class="">Account Number</label>
	<input tabindex="10" type="text" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="20" maxlength="20" id="bank_account_number" name="bank_account_number" placeholder="Bank Account Number" value="">
	<div class='clear-div'></div>
</div>
</div> <!-- payment_method_bank_account -->

<?php if ($GLOBALS['gLoggedIn'] && !empty($eCommerce) && $eCommerce->hasCustomerDatabase()) { ?>
<div class="form-line checkbox-input" id="_save_account_row">
	<label class=""></label>
	<input tabindex="10" type="checkbox" id="save_account" name="save_account" value="1"><label class="checkbox-label" for="save_account">Save Account</label>
	<div class='clear-div'></div>
</div>

<div class="form-line hidden" id="_account_label_row">
	<label for="account_label" class="">Account Nickname</label>
	<span class="help-label">for future reference, if saved</span>
	<input tabindex="10" type="text" class="" size="20" maxlength="30" id="account_label" name="account_label" placeholder="Account Label" value="">
	<div class='clear-div'></div>
</div>
<?php } ?>

</div> <!-- payment_information -->
</div> <!-- new_account -->
</div> <!-- billing_info_section -->

<?php
		$mailingLists = array();
		$resultSet = executeQuery("select * from mailing_lists where client_id = ? and inactive = 0 and " .
			"internal_use_only = 0 order by sort_order,description",$GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$mailingLists[] = array("mailing_list_id"=>$row['mailing_list_id'],"description"=>$row['description']);
		}
?>
<div class="donation-section" data-previous_section="_billing_info_section" id="_finalize_section">
<?= $this->getPageTextChunk("finalize_section") ?>

<?php if (count($mailingLists) > 0) { ?>
<?php
		foreach ($mailingLists as $mailingListInfo) {
			if ($GLOBALS['gLoggedIn']) {
				$contactMailingListId = getFieldFromId("contact_mailing_list_id","contact_mailing_lists","mailing_list_id",$mailingListInfo['mailing_list_id'],"contact_id = ? and " .
					"(date_opted_out is null or date_opted_out > current_date)",$GLOBALS['gUserRow']['contact_id']);
				$optedIn = (!empty($contactMailingListId));
			} else {
				$optedIn = false;
			}
?>
<div class="form-line checkbox-input" id="_mailing_list_id_<?= $mailingListInfo['mailing_list_id'] ?>_row">
	<input type="hidden" id="mailing_list_id_<?= $mailingListInfo['mailing_list_id'] ?>" name="mailing_list_id_<?= $mailingListInfo['mailing_list_id'] ?>" value="<?= ($optedIn ? "Y" : "N") ?>">
	<input tabindex="10" type="checkbox" class="mailing-list" id="mailing_list_id_<?= $mailingListInfo['mailing_list_id'] ?>_checkbox" data-mailing_list_id="<?= $mailingListInfo['mailing_list_id'] ?>"<?= ($optedIn ? " checked" : "") ?> value="1">
	<label class="checkbox-label" for="mailing_list_id_<?= $mailingListInfo['mailing_list_id'] ?>_checkbox"><?= htmlText($mailingListInfo['description']) ?></label>
	<div class='clear-div'></div>
</div>
<?php
		}
	}
?>

<div class="form-line" id="_bank_name_row">
	<label for="bank_name" class="">Bank Name</label>
	<input tabindex="10" autocomplete="chrome-off" autocomplete="off" type="text" size="20" maxlength="20" id="bank_name" name="bank_name" placeholder="Bank Name" value="">
	<div class='clear-div'></div>
</div>

<div class="form-line" id="_agree_terms_row">
	<input tabindex="10" type="checkbox" name="agree_terms" id="agree_terms" value="1"><label for="agree_terms">Agree to our terms of service</label>
	<div class='clear-div'></div>
</div>

<div class="form-line" id="_confirm_human_row">
	<input tabindex="10" type="checkbox" name="confirm_human" id="confirm_human" value="1"><label class='checkbox-label'>Click here to confirm you are human</label>
	<div class='clear-div'></div>
</div>

<?php
	$useCaptcha = getPreference("USE_DONATION_CAPTCHA") && !$GLOBALS['gUserRow']['administrator_flag'];
    if ($useCaptcha) {
		$useRecaptchaV2 = !empty(getPreference("ORDER_RECAPTCHA_V2_SITE_KEY")) && !empty(getPreference("ORDER_RECAPTCHA_V2_SECRET_KEY"));
		if (!empty($this->iUseRecaptchaV2)) {
?>
<div class="g-recaptcha" data-sitekey="<?= getPreference("ORDER_RECAPTCHA_V2_SITE_KEY") ?>"></div>
<?php
		} else {
            $captchaCodeId = createCaptchaCode();
?>
<input type='hidden' id='captcha_code_id' name='captcha_code_id' value='<?= $captchaCodeId ?>'>
<div class='form-line' id=_captcha_image_row'>
    <label></label>
    <img src="/captchagenerator.php?id=<?= $captchaCodeId ?>">
</div>

<div class="form-line" id="_captcha_code_row">
	<label for="captcha_code" class="">Enter text from above</label>
	<input tabindex="10" type="text" size="10" maxlength="10" id="captcha_code" name="captcha_code" value="">
	<div class='clear-div'></div>
</div>

<?php
		}
	}
?>

<p class="error-message" id="_error_message"></p>
<p id="_processing_paragraph">Processing...</p>
<p id="_button_paragraph" class="align-center"><button tabindex="10" id="_submit_form">Donate</button></p>
</div>
</form>
<?= $this->getPageData("after_form_content") ?>
</div>
