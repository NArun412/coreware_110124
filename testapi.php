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

$GLOBALS['gPageCode'] = "TESTAPI";
require_once "shared/startup.inc";

class ThisPage extends Page {
	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_parameters":
				$returnArray['parameters'] = array();
				$resultSet = executeQuery("select * from api_parameters where api_parameter_id in (" .
					"select api_parameter_id from api_method_parameters where api_method_id = ?) order by column_name", $_GET['api_method_id']);
				while ($row = getNextRow($resultSet)) {
					$returnArray['parameters'][] = $row['column_name'];
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function mainContent() {
		?>
        <form id="_edit_form">
            <input type="hidden" id="api_app_version" name="api_app_version" value="2.0">
            <input type="hidden" id="device_identifier" name="device_identifier" value="TEST-PHONE-0NIFWI7IFNIW87IFDFISU">
            <p><label>Domain</label><input type="text" size="40" class="validate[required]" name="domain_name" id="domain_name" value="<?= $_SERVER['HTTP_HOST'] ?>"></p>
            <p><label>App</label><select class="" id="api_app_code" name="api_app_code">
                    <option value="">[Select]</option>
					<?php
					$resultSet = executeQuery("select * from api_apps where client_id = ? order by description", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						?>
                        <option value="<?= $row['api_app_code'] ?>"><?= htmlText($row['description']) ?></option>
						<?php
					}
					?>
                </select>
            </p>
            <p><label>Action</label><select class="validate[required]" id="api_method_id" name="api_method_id">
                    <option value="">[Select]</option>
					<?php
					$resultSet = executeQuery("select * from api_methods order by sort_order,description");
					while ($row = getNextRow($resultSet)) {
						?>
                        <option value="<?= $row['api_method_id'] ?>" data-api_method_code="<?= $row['api_method_code'] ?>"><?= htmlText($row['description']) ?></option>
						<?php
					}
					?>
                </select>
            </p>
            <p><label>Session ID</label><input type="text" size="40" class="" name="session_identifier" id="session_identifier" value=""></p>
            <p><label>Connection Key</label><input type="text" size="40" class="" name="connection_key" id="connection_key" value=""></p>
            <table class="grid-table" id="parameters">
                <tr>
                    <th></th>
                    <th>Field Name</th>
                    <th>Field Data</th>
                </tr>
                <tr>
                    <td class="highlighted-text">1.</td>
                    <td><input type="text" class="validate[] code-value lowercase field-name" data-field_number="1" size="30" id="field_name_1" name="field_name_1"></td>
                    <td><input type="text" class="field-value" size="30" id="field_value_1" name="field_value_1"></td>
                </tr>
            </table>
            <p>
                <button id="submit_form">Submit</button>
            </p>
        </form>
        <p><textarea id="results"></textarea></p>
		<?php
		return true;
	}

	function onLoadJavascript() {
		$addressInfo = array();
		?>
        <script>
            $("#api_method_id").change(function () {
                $(".field-name").val("");
                $(".field-value").val("");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_parameters&api_method_id=" + $(this).val(), function(returnArray) {
                    for (var i in returnArray['parameters']) {
                        var foundOne = false;
                        $("#parameters").find(".field-name").each(function () {
                            if (empty($(this).val())) {
                                $(this).val(returnArray['parameters'][i]);
                                foundOne = true;
                                return false;
                            }
                        });
                        if (!foundOne) {
                            var rowNumber = $("#parameters").find("tr").length;
                            var newRow = $("#parameter_row").html().replace(new RegExp("%rowNumber%", 'g'), rowNumber);
                            $("#parameters").append(newRow);
                            $("#parameter_row_" + rowNumber).find(".field-name").val(returnArray['parameters'][i]);
                        }
                    }
                    $("#parameters").find(".field-name").filter(function () {
                        return !this.value;
                    }).closest("tr").remove();
                });
            });
            $(".field-name").blur(function () {
                if (empty($(this).val())) {
                    $("#submit_form").focus();
                }
            });
            $("#submit_form").click(function () {
                if ($("#_edit_form").validationEngine('validate')) {
                    $(this).hide();
                    $("#results").val("");
                    var domainName = $("#domain_name").val();
                    var apiMethodCode = $("#api_method_id option:selected").data('api_method_code');
                    var postData = new Object();
                    if (empty($("#connection_key").val())) {
                        postData['api_app_code'] = $("#api_app_code").val();
                        postData['api_app_version'] = $("#api_app_version").val();
                        postData['device_identifier'] = $("#device_identifier").val();
                        postData['session_identifier'] = $("#session_identifier").val();
                    } else {
                        postData['connection_key'] = $("#connection_key").val();
                    }
                    $("#parameters").find(".field-name").each(function () {
                        var fieldNumber = $(this).data("field_number");
                        if ($("#field_name_" + fieldNumber).val() != "" && $("#field_value_" + fieldNumber).val() != "") {
                            postData[$("#field_name_" + fieldNumber).val()] = $("#field_value_" + fieldNumber).val();
                        }
                    });
                    $.ajax({
                            url: (domainName.substring(0, 4) == "http" ? domainName : "//" + domainName) + "/api.php?action=" + apiMethodCode,
                            type: "POST",
                            data: postData,
                            timeout: <?= (empty($GLOBALS['gDefaultAjaxTimeout']) || !is_numeric($GLOBALS['gDefaultAjaxTimeout']) ? "300000" : ($GLOBALS['gDefaultAjaxTimeout'] * 10)) ?>,
                            success: function (returnText) {
                                $("#results").val(returnText);
                                $("#submit_form").show();
                                const returnArray = processReturn(returnText);
                                if (returnArray === false) {
                                    return;
                                }
                                $("#results").val(JSON.stringify(returnArray, null, '\t'));
                                $("#api_method_id").select().focus();
                            },
                            error: function (XMLHttpRequest, textStatus, errorThrown) {
                                $("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
                                displayErrorMessage("<?= getSystemMessage("not_responding") ?>");
                            },
                            dataType: "text"
                        }
                    );
                }
                return false;
            });
            $("#domain_name").focus();
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            label {
                padding-right: 20px;
            }

            textarea {
                height: 600px;
                width: 800px;
            }

            #parameter_row {
                display: none;
            }

            table {
                margin: 20px 0;
            }
        </style>
		<?php
	}

	function hiddenElements() {
		?>
        <table id="parameter_row">
            <tr id="parameter_row_%rowNumber%">
                <td class="highlighted-text">%rowNumber%.</td>
                <td><input type="text" class="validate[] code-value lowercase field-name" data-field_number="%rowNumber%" size="30" id="field_name_%rowNumber%" name="field_name_%rowNumber%"></td>
                <td><input type="text" class="field-value" size="30" id="field_value_%rowNumber%" name="field_value_%rowNumber%"></td>
            </tr>
        </table>
		<?php
	}
}

$pageObject = new ThisPage();
$pageObject->displayPage();
