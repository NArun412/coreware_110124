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

?>
<div id="_donation_form">
<form name="_edit_form" id="_edit_form">
<input type="hidden" id="_add_hash" name="_add_hash" value="<?= md5(uniqid(mt_rand(), true)) ?>">
<input type="hidden" id="simplified" name="simplified" value="1">
<div class="donation-section" id="_donate_section">

<div class="form-line" id="_amount_row">
	<label for="amount" class="required-label">Amount</label>
	<input tabindex="10" type="text" size="12" maxlength="12" class="validate[required,custom[number]] align-right" id="amount" name="amount" placeholder="Amount" data-decimal-places="2">
	<div class='clear-div'></div>
</div>

<?php
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
			$designationArray[$row['designation_id']] = array("designation_code"=>$row['designation_code'],"alias"=>$row['alias'],"description"=>$row['description'],"merchant_account_id"=>$row['merchant_account_id']);
		}

		if ((!empty($_GET['id']) || !empty($_GET['code'])) && count($designationArray) == 1) {
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
		}
?>

<?= (!empty($customTitle) ? "<h2>" . $customTitle . "</h2>" : "") ?>
<div id="_designation_group_detailed_description">
<?= (isHTML($customDetailedDescription) ? $customDetailedDescription : makeHtml($customDetailedDescription)) ?>
<div class='clear-div'></div>
</div>

<?php if ((empty($_GET['id']) && empty($_GET['code'])) || count($designationArray) != 1) { ?>
<div class="form-line" id="_designation_id_row">
	<input type="hidden" id="merchant_account_id" name="merchant_account_id" value="">
	<label for="designation_id" class="required-label">Designated for</label>
	<select tabindex="10" class="validate[required]" id="designation_id" name="designation_id">
		<option data-merchant_account_id="" value="">Select or enter code below</option>
<?php
		foreach ($designationArray as $designationId => $designationInfo) {
			$description = (empty($designationInfo['alias']) ? $designationInfo['description'] : $designationInfo['alias']);
			$merchantAccountId = $GLOBALS['gMerchantAccountId'];
?>
		<option data-merchant_account_id="<?= $merchantAccountId ?>" value="<?= $designationId ?>"><?= htmlText($description) ?></option>
<?php
		}
?>
	</select>
	<div class='clear-div'></div>
</div>

<?php if (count($totalDesignations) > count($designationArray)) { ?>
<div class="form-line" id="_designation_code_row">
	<label for="designation_code" class="">Designation Code</label>
	<input tabindex="10" type="text" size="20" maxlength="100" id="designation_code" name="designation_code" placeholder="Designation Code">
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

<?php
	$projectNameArray = array();
	$projectLabel = "Project";
	$projectRequired = false;
	if ((!empty($_GET['id']) || !empty($_GET['code'])) && count($designationArray) == 1) {
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

<div class="form-line checkbox-input" id="_recurring_donation_type_id_row">
		<input type="radio" tabindex="10" name="recurring_donation_type_id" id="recurring_donation_type_id" checked="checked" value=""><label class="checkbox-label" for="recurring_donation_type_id">One Time Donation</label>
<?php
		$resultSet = executeQuery("select * from recurring_donation_types where manual_processing = 0 and client_id = ? and " .
			"inactive = 0 and internal_use_only = 0 order by sort_order,description",$GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
?>
		<br><input type="radio" tabindex="10" name="recurring_donation_type_id" id="recurring_donation_type_id_<?= $row['recurring_donation_type_id'] ?>" value="<?= $row['recurring_donation_type_id'] ?>"><label class="checkbox-label" for="recurring_donation_type_id_<?= $row['recurring_donation_type_id'] ?>"><?= htmlText($row['description']) ?></label>
<?php
		}
?>
	<div class='clear-div'></div>
</div>

</div> <!-- donate_section -->

<div class="donation-section" id="_donor_info_section">
<h2>Your Information</h2>
<?php
	if ($GLOBALS['gLoggedIn']) {
		$userRow = $GLOBALS['gUserRow'];
		if ($GLOBALS['gUserRow']['administrator_flag']) {
?>
<p class='highlighted-text'>As an administrator, you should make changes to your user account with "My Account".</p>
<?php
		} else {
?>
<p>Any changes will also be made to your user account.</p>
<?php
		}
	} else {
		$userRow = array();
?>
<p>Give us your information so we know who made the donation.</p>
<?php
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

<div class="form-line" id="_address_1_row">
	<label for="address_1" class="required-label">Address</label>
	<input tabindex="10" type="text" <?= ($GLOBALS['gUserRow']['administrator_flag'] ? "readonly='readonly'" : "class='validate[required]" . (in_array("address_1",$capitalizedFields) ? " capitalize" : "") . "'") ?> size="30" maxlength="60" id="address_1" name="address_1" placeholder="Address" value="<?= htmlText($userRow['address_1']) ?>">
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
	foreach (getCountryArray() as $countryId => $countryName) {
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

<div class="form-line" id="_home_phone_number_row">
	<label for="home_phone_number" class="">Home Phone</label>
	<input tabindex="10" type="text" <?= ($GLOBALS['gUserRow']['administrator_flag'] ? "readonly='readonly'" : "class='validate[custom[phone]]'") ?> size="20" maxlength="25" id="home_phone_number" name="home_phone_number" placeholder="Home Phone" value="<?= Contact::getContactPhoneNumber($GLOBALS['gUserRow']['contact_id'],"home",false) ?>">
	<div class='clear-div'></div>
</div>

<div class="form-line" id="_cell_phone_number_row">
	<label for="cell_phone_number" class="">Cell Phone</label>
	<input tabindex="10" type="text" <?= ($GLOBALS['gUserRow']['administrator_flag'] ? "readonly='readonly'" : "class='validate[custom[phone]]'") ?> size="20" maxlength="25" id="cell_phone_number" name="cell_phone_number" placeholder="Cell Phone" value="<?= Contact::getContactPhoneNumber($GLOBALS['gUserRow']['contact_id'],"cell",false) ?>">
	<div class='clear-div'></div>
</div>

</div> <!-- donor_info_section -->

<div class="donation-section" id="_billing_info_section">
<h2>Billing Information</h2>

<div class="form-line" id="_payment_method_id_row">
	<label for="payment_method_id" class="">Payment Method</label>
	<select tabindex="10" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" id="payment_method_id" name="payment_method_id">
		<option value="">[Select]</option>
<?php
	$resultSet = executeQuery("select *,(select payment_method_types.payment_method_type_code from payment_method_types where " .
        "payment_method_type_id = payment_methods.payment_method_type_id) payment_method_type_code from payment_methods where " .
        ($GLOBALS['gLoggedIn'] ? "" : "requires_user = 0 and ") .
        "(payment_method_id not in (select payment_method_id from payment_method_user_types) " .
        (empty($GLOBALS['gUserRow']['user_type_id']) ? "" : " or payment_method_id in (select payment_method_id from payment_method_user_types where user_type_id = " . $GLOBALS['gUserRow']['user_type_id'] . ")") . ") and " .
		"inactive = 0 and internal_use_only = 0 and client_id = ? and (payment_method_type_id is null or payment_method_type_id in " .
		"(select payment_method_type_id from payment_method_types where inactive = 0 and internal_use_only = 0 and " .
		"client_id = ?)) order by sort_order,description",$GLOBALS['gClientId'],$GLOBALS['gClientId']);
	while ($row = getNextRow($resultSet)) {
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
	<a href="https://www.cvvnumber.com/cvv.html" target="_blank"><img id="cvv_image" src="/images/cvv_code.gif"></a>
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

</div> <!-- billing_info_section -->

<div class="form-line" id="_bank_name_row">
	<label for="bank_name" class="">Put nothing in this field</label>
	<input tabindex="10" type="text" size="20" maxlength="20" id="bank_name" name="bank_name" placeholder="Bank Name" value="">
	<div class='clear-div'></div>
</div>

<p class="error-message" id="_error_message"></p>
<p id="_processing_paragraph">Processing...</p>
<p id="_button_paragraph" class="align-center"><button tabindex="10" id="_submit_form">Donate</button></p>
</form>
<?= $this->getPageData("after_form_content") ?>
</div>
