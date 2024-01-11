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

$GLOBALS['gPageCode'] = "BACKGROUNDPROCESS";
$runEnvironment = php_sapi_name();
if ($runEnvironment == "cli") {
    require_once "shared/startup.inc";
} else {
    require_once "../shared/startup.inc";
}

if (!$GLOBALS['gUserRow']['superuser_flag'] && !$GLOBALS['gCommandLine']) {
    echo "ERROR: For security purposes, this program cannot be run from a browser - " . php_sapi_name() . ".\n";
    exit;
}

class ThisBackgroundProcess extends BackgroundProcess {
    function setProcessCode() {
        $this->iProcessCode = "event_product_variants";
    }

    function process() {
        $clientSet = executeQuery("select * from clients");
        while ($clientRow = getNextRow($clientSet)) {
        	changeClient($clientRow['client_id']);
            $eventsArray = array();
            $eventTypes = array();
            $resultSet = executeQuery("select * from events where location_id is not null and event_type_id is not null and product_id is not null and client_id = ? and start_date >= current_date and (end_date is null or end_date >= current_date)",
                $clientRow['client_id']);
            if ($resultSet['row_count'] == 0) {
            	continue;
            }
            $this->addResult($resultSet['row_count'] . " events found to add to variants for client " . $clientRow['client_code'] . ".");
            while ($row = getNextRow($resultSet)) {
                $eventsArray[] = $row;
                if (!array_key_exists($row['event_type_id'],$eventTypes)) {
                    $eventTypes[$row['event_type_id']] = getRowFromId("event_types","event_type_id",$row['event_type_id']);
                }
            }
            if (count($eventsArray) == 0) {
                continue;
            }

            $typeProductOptionId = getFieldFromId("product_option_id","product_options","product_option_code","EVENT_TYPE");
            if (empty($typeProductOptionId)) {
                $insertSet = executeQuery("insert into product_options (client_id,product_option_code,description) values (?,?,?)",$GLOBALS['gClientId'],'EVENT_TYPE','Event Type');
                $typeProductOptionId = $insertSet['insert_id'];
            }
            $dateProductOptionId = getFieldFromId("product_option_id","product_options","product_option_code","EVENT_DATE");
            if (empty($dateProductOptionId)) {
                $insertSet = executeQuery("insert into product_options (client_id,product_option_code,description) values (?,?,?)",$GLOBALS['gClientId'],'EVENT_DATE','Event Date');
                $dateProductOptionId = $insertSet['insert_id'];
            }
            $locationProductOptionId = getFieldFromId("product_option_id","product_options","product_option_code","EVENT_LOCATION");
            if (empty($locationProductOptionId)) {
                $insertSet = executeQuery("insert into product_options (client_id,product_option_code,description) values (?,?,?)",$GLOBALS['gClientId'],'EVENT_LOCATION','Event Location');
                $locationProductOptionId = $insertSet['insert_id'];
            }

            $productGroupId = getFieldFromId("product_group_id","product_groups","product_group_code","EVENT_REGISTRATION");
            if (empty($productGroupId)) {
                $insertSet = executeQuery("insert into product_groups (client_id,product_group_code,description) values (?,?,?)",$GLOBALS['gClientId'],'EVENT_REGISTRATION','Class Registration');
                $productGroupId = $insertSet['insert_id'];
            }

            $productGroupOptionId = getFieldFromId("product_group_option_id","product_group_options","product_group_id",$productGroupId,
                "product_option_id = ?",$typeProductOptionId);
            if (empty($productGroupOptionId)) {
                executeQuery("insert ignore into product_group_options (product_group_id,product_option_id,sequence_number) values (?,?,1)",$productGroupId,$typeProductOptionId);
            }
            $productGroupOptionId = getFieldFromId("product_group_option_id","product_group_options","product_group_id",$productGroupId,
                "product_option_id = ?",$dateProductOptionId);
            if (empty($productGroupOptionId)) {
                executeQuery("insert ignore into product_group_options (product_group_id,product_option_id,sequence_number) values (?,?,2)",$productGroupId,$dateProductOptionId);
            }
            $productGroupOptionId = getFieldFromId("product_group_option_id","product_group_options","product_group_id",$productGroupId,
                "product_option_id = ?",$locationProductOptionId);
            if (empty($productGroupOptionId)) {
                executeQuery("insert ignore into product_group_options (product_group_id,product_option_id,sequence_number) values (?,?,3)",$productGroupId,$locationProductOptionId);
            }

	        $this->addResult(count($eventsArray) . " events found to process");
            foreach ($eventsArray as $thisEvent) {
                $changesMade = false;
                $productId = $thisEvent['product_id'];
                $productGroupVariantId = getFieldFromId("product_group_variant_id","product_group_variants","product_id",$productId,"product_group_id <> ?",$productGroupId);
                if (!empty($productGroupVariantId)) {
                	$this->addResult("Event ID " . $thisEvent['event_id'] . ", Product ID " . $productId . " already has a variant");
                    continue;
                }
                $productGroupVariantId = getFieldFromId("product_group_variant_id","product_group_variants","product_id",$productId,"product_group_id = ?",$productGroupId);

                if (empty($productGroupVariantId)) {
                    $insertSet = executeQuery("insert into product_group_variants (product_group_id,product_id) values (?,?)",$productGroupId,$productId);
                    $productGroupVariantId = $insertSet['insert_id'];
                    $changesMade = true;
                }

                # check for type variants
                $typeChoiceValue = $eventTypes[$thisEvent['event_type_id']]['description'];
                if (empty($typeChoiceValue)) {
					$this->addResult("Unable to get description for event type ID " . $thisEvent['event_type_id'] . " - " . jsonEncode($eventTypes));
                	continue;
                }
                if ($this->addUpdateVariant($productGroupVariantId, $typeProductOptionId, $typeChoiceValue)) {
                    $changesMade = true;
                }

                # check for date variants
                $dateChoiceValue = date("m/d/Y",strtotime($thisEvent['start_date']));
                $hour = false;
                $endHour = "";
                $hourSet = executeQuery("select * from event_facilities where date_needed = ? and event_id = ? order by hour",$thisEvent['start_date'],$thisEvent['event_id']);
                while ($hourRow = getNextRow($hourSet)) {
                    if ($hour === false) {
                        $hour = $hourRow['hour'];
                    }
                    $endHour = $hourRow['hour'];
                }
                if (!empty($hour)) {
	                $workingHour = floor($hour);
	                $displayHour = ($workingHour == 0 ? "12" : ($workingHour > 12 ? $workingHour - 12 : $workingHour));
	                $displayMinutes = ($hour - $workingHour) * 60;
	                $displayAmpm = ($hour == 0 ? "midnight" : ($hour == 12 ? "noon" : ($workingHour < 12 ? "am" : "pm")));
	                $displayTime = $displayHour . ":" . str_pad($displayMinutes,2,"0",STR_PAD_LEFT) . " " . $displayAmpm;
                    $workingHour = floor($endHour + .25);
                    $displayHour = ($workingHour == 0 ? "12" : ($workingHour > 12 ? $workingHour - 12 : $workingHour));
                    $displayMinutes = ($endHour + .25 - $workingHour) * 60;
                    $displayAmpm = ($endHour == 23.75 ? "midnight" : ($endHour == 11.75 ? "noon" : ($workingHour < 12 ? "am" : "pm")));
                    $displayTime .= "-" . $displayHour . ":" . str_pad($displayMinutes,2,"0",STR_PAD_LEFT) . " " . $displayAmpm;
	                $dateChoiceValue .= " " . $displayTime;
                }
                if ($this->addUpdateVariant($productGroupVariantId, $dateProductOptionId, $dateChoiceValue)) {
                    $changesMade = true;
                }

                # check for location variants
                $locationChoiceValue = getFieldFromId("description","locations","location_id",$thisEvent['location_id']);
                if ($this->addUpdateVariant($productGroupVariantId, $locationProductOptionId, $locationChoiceValue)) {
                    $changesMade = true;
                }

                if($changesMade) {
                    $this->addResult("Variants added for product ID " . $productId);
                }
            }
        }
    }

	private function addUpdateVariant($productGroupVariantId, $productOptionId, $choiceValue) {
		$changesMade = false;
		$productOptionChoiceId = getFieldFromId("product_option_choice_id","product_option_choices","product_option_id",$productOptionId,"description = ?",$choiceValue);
		if (empty($productOptionChoiceId)) {
			$insertSet = executeQuery("insert into product_option_choices (product_option_id,description) values (?,?)",$productOptionId,$choiceValue);
			$productOptionChoiceId = $insertSet['insert_id'];
			$changesMade = true;
		}
		$productGroupVariantChoicesRow = getRowFromId("product_group_variant_choices","product_group_variant_id",$productGroupVariantId,
			"product_option_id = ?",$productOptionId);
		if (empty($productGroupVariantChoicesRow) || $productGroupVariantChoicesRow['product_option_choice_id'] != $productOptionChoiceId) {
			if (empty($productGroupVariantChoicesRow)) {
				executeQuery("insert into product_group_variant_choices (product_group_variant_id,product_option_id,product_option_choice_id) values (?,?,?)",
					$productGroupVariantId,$productOptionId,$productOptionChoiceId);
				$changesMade = true;
			} else { // values do not match - update
				executeQuery("update product_group_variant_choices set product_option_choice_id = ? where product_group_variant_choice_id = ?",
					$productOptionChoiceId, $productGroupVariantChoicesRow['product_group_variant_choice_id']);
				$changesMade = true;
			}
		}
		return $changesMade;
	}
}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
