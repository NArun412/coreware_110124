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

$GLOBALS['gPageCode'] = "FORMBUILDER";
require_once "shared/startup.inc";

class FormBuilderPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "generate_form":
				$formChunks = array();
				foreach ($_POST as $fieldName => $fieldData) {
					if (startsWith($fieldName, "chunk_information_field_names_")) {
						$formChunkNumber = substr($fieldName, strlen("chunk_information_field_names_"));
						if (!is_numeric($formChunkNumber)) {
							continue;
						}
						$thisFormChunk = array();
						$fieldNames = explode(",", $fieldData);
						foreach ($fieldNames as $thisFieldName) {
							$thisFormChunk[$thisFieldName] = $_POST['chunk_information_' . $thisFieldName . "_" . $formChunkNumber];
						}
						$sequenceNumber = $_POST["chunk_information_sequence_number_" . $formChunkNumber];
						$formChunks[$sequenceNumber] = $thisFormChunk;
					}
				}

				$formContent = "<!-- Coreware Form Builder -->\n\n<div id='_maintenance_form'>\n\n";
				ksort($formChunks);
				ob_start();

				foreach ($formChunks as $formChunkInfo) {
					switch ($formChunkInfo['data_type']) {
						case "payment":
							?>

                            <!-- Chunk - Payment -->

                            %payment_block%

                            <!-- End Chunk -->

							<?php
							break;
						case "field":
							$formFieldCode = getFieldFromId("form_field_code", "form_fields", "form_field_id", $formChunkInfo['form_field_id']);
							$dataType = getFieldFromId("control_value", "form_field_controls", "form_field_id", $formChunkInfo['form_field_id'], "control_name = 'data_type'");
							if (!empty($formFieldCode)) {
								?>

                                <!-- Chunk - Field -->

                                %field:<?= $formFieldCode ?>%
								<?php if ($dataType != "hidden") { ?>
                                    <div class="form-line %form_line_classes%" id="_%column_name%_row">
                                    <label for="%column_name%" class="%label_class%">%form_label%</label>
                                    %if:$thisColumn && $thisColumn->getControlValue('help_label')%
                                    <span class="help-label"></span>
                                    %endif%
								<?php } ?>
                                %input_control%
								<?php if ($dataType != "hidden") { ?>
                                    </div>
								<?php } ?>

                                <!-- End Chunk -->

								<?php
							}
							break;
						case "paragraph":
							?>

                            <!-- Chunk - Paragraph -->

							<?php
							$paragraph = makeHtml($formChunkInfo['paragraph_text']);
							if (substr($paragraph, 0, 3) != "<p>") {
								$paragraph = "<p>" . $paragraph;
							}
							if (substr($paragraph, -4) != "</p>") {
								$paragraph .= "</p>";
							}
							echo $paragraph;
							?>

                            <!-- End Chunk -->

							<?php
							break;
						case "header":
							$headerLevel = $formChunkInfo['header_level'];
							?>

                            <!-- Chunk - Header <?= $headerLevel ?> -->

                            <h<?= $headerLevel ?>><?= $formChunkInfo[$formChunkInfo['data_type'] . "_text"] ?></h<?= $headerLevel ?>>

                            <!-- End Chunk -->

							<?php
							break;
						case "html":
							?>

                            <!-- Chunk - HTML -->
                            <!-- Description - <?= $formChunkInfo['html_description'] ?> -->

							<?= $formChunkInfo["html_text"] ?>

                            <!-- End Chunk -->

							<?php
							break;
						case "image":
							?>

                            <!-- Chunk - Image -->

                            <p><img src="/getimage.php?id=<?= $formChunkInfo["image_id"] ?>" alt='image'></p>

                            <!-- End Chunk -->

							<?php
							break;
					}
				}
				$formContent .= ob_get_clean();
				$formContent .= "\n\n</div> <!-- maintenance_form -->\n";
				$returnArray['form_content'] = "";
				$formLines = getContentLines($formContent);
				foreach ($formLines as $thisLine) {
					$returnArray['form_content'] .= $thisLine . "\n";
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setAddUrl("formdefinitionmaintenance.php?url_page=new");
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("delete"));
		}
	}

	function massageDataSource() {
		$this->iDataSource->setFilterWhere("form_filename is null and (form_content is null or form_content like '<!-- Coreware Form Builder -->%')");
		$this->iDataSource->setSaveOnlyPresent(true);
	}

	function javascript() {
		?>
        <script>
            let formFieldTags = null;
            let formChunkNumber = 0;

            function generateFormContent() {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=generate_form", $("#_edit_form").serialize(), function(returnArray) {
                    if ("form_content" in returnArray) {
                        $("#form_content").val(returnArray['form_content']);
                    }
                });
            }

            function afterGetRecord(returnArray) {
                formFieldTags = returnArray['form_field_tags'];
                if (formFieldTags.length > 0) {
                    $("#form_field_tags").html("<h2>Field Tags</h2>");
                }
                for (const i in formFieldTags) {
                    $("#form_field_tags").append("<p><input class='form-field-tag' type='checkbox' id='form_field_tag_" + formFieldTags[i]['field_tag'] + "' value='1'><label class='checkbox-label' for='form_field_tag_" + formFieldTags[i]['field_tag'] + "'>" + formFieldTags[i]['field_tag'] + "</label></p>");
                }
                formChunkNumber = $("#form_chunk_number").val() - 0;
                if (empty($("#payment_description").html()) || "payment_exists" in returnArray) {
                    $("#payment_button").hide();
                } else {
                    $("#payment_button").show();
                }
            }

            function reorderFormChunks() {
                let sequenceNumber = 0;
                $("#form_builder_content").find(".form-chunk").each(function () {
                    sequenceNumber++;
                    $(this).find("input[data-field_name='sequence_number']").val(sequenceNumber);
                });
            }
        </script>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#view_form").click(function () {
                let formContent = $("#form_content").val().trim().replace( /<!--(.*?)-->/g, "");
                for (let x = 1; x < 5; x++) {
                    formContent = formContent.replace(/\n\n\n/g, '\n\n');
                }
                $("#form_html_content").val(formContent);
                $("#view_html_dialog").dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: {my: "center top", at: "center top+100px", of: window, collision: "none"},
                    width: 800,
                    title: 'Form HTML',
                    buttons: {
                        Cancel: function (event) {
                            $("#view_html_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
            $(document).on("click", ".delete-chunk", function (event) {
                if ($(this).closest(".form-chunk").find("input[data-field_name='payment_block']").length > 0) {
                    $("#payment_button").show();
                }
                $(this).closest(".form-chunk").remove();
                event.stopPropagation();
                generateFormContent();
            });
            $(document).on("click", ".move-chunk-top", function (event) {
                $("#form_builder_content").prepend($(this).closest(".form-chunk"));
                event.stopPropagation();
                reorderFormChunks();
                generateFormContent();
            });
            $(document).on("click", ".move-chunk-bottom", function (event) {
                $("#form_builder_content").append($(this).closest(".form-chunk"));
                event.stopPropagation();
                reorderFormChunks();
                generateFormContent();
            });
            $("#form_builder_content").sortable({
                update: function () {
                    reorderFormChunks();
                    generateFormContent();
                }
            });
            $(document).on("click", ".chunk-button,.form-chunk", function (event) {
                let dataType = "";
                const existingValues = {};
                if ($(this).is(".form-chunk")) {
                    dataType = $(this).find("input[data-field_name='data_type']").val();
                    $(this).find("input[type=hidden]").each(function () {
                        existingValues[$(this).data("field_name")] = $(this).val();
                    });
                    $("#form_chunk_row_number").val($(this).data("row_number"));
                } else {
                    dataType = $(this).data("chunk_type");
                    $("#form_chunk_row_number").val("");
                }
                if ($("#chunk_type_" + dataType + "_dialog").length > 0) {
                    $("#_" + dataType + "_form").clearForm().find("select").val("");
                    for (const i in existingValues) {
                        if ($("#_" + dataType + "_form").find("#" + i).length > 0) {
                            if ($("#_" + dataType + "_form").find("#" + i).is("input[type=checkbox]")) {
                                $("#_" + dataType + "_form").find("#" + i).prop("checked", (existingValues[i] === "1"));
                            } else {
                                if ($("#_" + dataType + "_form").find("#" + i).is("select")) {
                                    if ($("#_" + dataType + "_form").find("#" + i).find("option[value=" + existingValues[i] + "]").length === 0 && "description" in existingValues) {
                                        const thisOption = $("<option></option>").attr("value", existingValues[i]).text(existingValues['description']);
                                        $("#_" + dataType + "_form").find("#" + i).append(thisOption);
                                    }
                                }
                                $("#_" + dataType + "_form").find("#" + i).val(existingValues[i]);
                            }
                        }
                    }
                    $("#chunk_type_" + dataType + "_dialog").dialog({
                        closeOnEscape: true,
                        draggable: false,
                        modal: true,
                        resizable: false,
                        position: {my: "center top", at: "center top+100px", of: window, collision: "none"},
                        width: 800,
                        title: 'Add ' + dataType,
                        buttons: {
                            Insert: function (event) {
                                if ($("#_" + dataType + "_form").validationEngine("validate")) {
                                    const formChunkRowNumber = $("#form_chunk_row_number").val();
                                    let formChunk = $("#_form_chunk").html();
                                    let thisFormChunkNumber;
                                    if (empty(formChunkRowNumber)) {
                                        formChunkNumber++;
                                        $("#form_chunk_number").val(formChunkNumber);
                                        thisFormChunkNumber = formChunkNumber;
                                    } else {
                                        thisFormChunkNumber = formChunkRowNumber;
                                    }
                                    formChunk = formChunk.replace("%chunk_id%", "form_chunk_" + thisFormChunkNumber);
                                    formChunk = formChunk.replace("%form_chunk_number%", thisFormChunkNumber);
                                    const nameValues = {};
                                    nameValues['data_type'] = dataType;
                                    nameValues['sequence_number'] = thisFormChunkNumber;
                                    let textValue = "";
                                    switch (dataType) {
                                        case "field":
                                            textValue = $("#form_field_id option:selected").text();
                                            nameValues['form_field_id'] = $("#form_field_id").val();
                                            nameValues['required'] = ($("#required").prop("checked") ? "1" : "0");
                                            nameValues['form_label'] = $("#form_label").val();
                                            formChunk = formChunk.replace("%chunk_description%", "Form Field: " + textValue);
                                            $("#chunk_type_field_dialog").find(".form-field-tag").each(function () {
                                                const thisId = $(this).prop("id");
                                                nameValues[thisId] = $(this).prop("checked") ? 1 : 0;
                                            });
                                            break;
                                        case "paragraph":
                                            textValue = $("#paragraph_text").val().substring(0, 50).replace("<", " ").replace(">", " ");
                                            nameValues['paragraph_text'] = $("#paragraph_text").val();
                                            formChunk = formChunk.replace("%chunk_description%", "Paragraph: " + textValue);
                                            break;
                                        case "header":
                                            textValue = $("#header_text").val().substring(0, 50).replace("<", " ").replace(">", " ");
                                            nameValues["header_text"] = $("#header_text").val();
                                            nameValues['header_level'] = $("#header_level").val();
                                            formChunk = formChunk.replace("%chunk_description%", "Header " + $("#header_level").val() + ": " + textValue);
                                            break;
                                        case "html":
                                            nameValues['html_text'] = $("#html_text").val();
                                            nameValues['html_description'] = $("#html_description").val();
                                            formChunk = formChunk.replace("%chunk_description%", $("#html_description").val());
                                            break;
                                        case "payment":
                                            nameValues['payment_block'] = true;
                                            formChunk = formChunk.replace("%chunk_description%", "Payment fields for <span class='highlighted-text'>" + $("#payment_description").html() + "</span>");
                                            $("#payment_button").hide();
                                            break;
                                        case "image":
                                            const imageId = $("#image_id").val();
                                            nameValues['image_id'] = $("#image_id").val();
                                            const imageElement = $("<img>").attr("src", "/getimage.php?id=" + imageId + "&thumb=true").attr("alt", "Image");
                                            formChunk = formChunk.replace("%chunk_description%", "Image: " + imageElement.get(0).outerHTML);
                                            break;
                                    }
                                    if (empty(formChunkRowNumber)) {
                                        $("#form_builder_content").append(formChunk);
                                    } else {
                                        $("#form_chunk_" + formChunkRowNumber).replaceWith(formChunk);
                                    }
                                    let chunkInformation = "";
                                    let fieldNames = "";
                                    for (const i in nameValues) {
                                        fieldNames += (empty(fieldNames) ? "" : ",") + i;
                                        chunkInformation += "<input type='hidden' data-field_name='" + i + "' id='chunk_information_" + i + "_" + thisFormChunkNumber + "' name='chunk_information_" + i + "_" + thisFormChunkNumber + "'>";
                                    }
                                    chunkInformation += "<input type='hidden' data-field_name='field_names' id='chunk_information_field_names_" + thisFormChunkNumber + "' name='chunk_information_field_names_" + thisFormChunkNumber + "' value='" + fieldNames + "'>";
                                    $("#form_chunk_" + thisFormChunkNumber).find(".form-chunk-information").append(chunkInformation);
                                    for (const i in nameValues) {
                                        $("#chunk_information_" + i + "_" + thisFormChunkNumber).val(nameValues[i]);
                                    }
                                    $("#chunk_type_" + dataType + "_dialog").dialog('close');
                                    generateFormContent();
                                }
                            },
                            Cancel: function (event) {
                                $("#chunk_type_" + dataType + "_dialog").dialog('close');
                            }
                        }
                    });
                } else {
                    generateFormContent();
                }
                return false;
            });
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            .chunk-description img {
                max-height: 100px;
                max-width: 200px;
            }

            #_item_selectors {
                margin-bottom: 10px;
            }

            #form_builder_content {
                border: 1px solid rgb(100, 100, 100);
                border-radius: 5px;
                padding: 20px 20px 100px 20px;
                margin: 0 0 20px 0;
                min-height: 400px;
                position: relative;
            }

            .form-chunk {
                padding: 5px 40px;
                background-color: rgb(240, 240, 240);
                border-radius: 3px;
                border: 1px solid rgb(50, 50, 50);
                cursor: pointer;
                margin-bottom: 5px;
                position: relative;
                max-width: 750px;
            }

            .form-chunk .delete-chunk {
                display: block;
                font-size: 1rem;
                color: rgb(100, 100, 100);
                position: absolute;
                top: 6px;
                right: 10px;
                cursor: pointer;
            }

            .form-chunk .move-chunk-bottom {
                display: block;
                font-size: 1rem;
                color: rgb(100, 100, 100);
                position: absolute;
                top: 6px;
                right: 35px;
                cursor: pointer;
            }

            .form-chunk .move-chunk-top {
                display: block;
                font-size: 1rem;
                color: rgb(100, 100, 100);
                position: absolute;
                top: 6px;
                right: 60px;
                cursor: pointer;
            }

            .form-chunk p {
                font-size: 1.2rem;
                margin: 0;
                padding: 0;
            }

            .form-chunk p img {
                height: 100%;
                margin-left: 20px;
            }

            .form-chunk-information {
                display: none;
            }

            #view_form_paragraph {
                margin: 10px 0;
                padding: 0;
            }

            .dialog-box textarea {
                width: 100%;
                height: 300px;
            }

            .grip {
                position: absolute;
                left: 10px;
                top: 50%;
                transform: translate(0, -50%);
            }

            .grip span {
                margin-right: 2px;
                color: rgb(100, 100, 100);
            }

            #form_html_content {
                width: 100%;
                height: 600px;
            }

            .fa-ellipsis-v {
                margin: 0 -2px;
            }
        </style>
		<?php
	}

	/** @noinspection HtmlRequiredAltAttribute */
	function afterGetRecord(&$returnArray) {
		$formFieldTags = array();
		$resultSet = executeQuery("select * from form_field_tags where form_definition_id = ?", $returnArray['primary_id']['data_value']);
		while ($row = getNextRow($resultSet)) {
			$formFieldTags[] = $row;
		}
		$returnArray['form_field_tags'] = $formFieldTags;
		$returnArray['payment_description'] = array("data_value" => getFieldFromId("description", "designations", "designation_id", $returnArray['designation_id']['data_value']));
		if (!empty($returnArray['product_id'])) {
			$returnArray['payment_description'] = array("data_value" => getFieldFromId("description", "products", "product_id", $returnArray['product_id']['data_value']));
		}
		ob_start();
		$formContentLines = getContentLines($returnArray['form_content']['data_value']);
		$validChunkTypes = array("field", "paragraph", "header_1", "header_2", "header_3", "header_4", "header_5", "header_6", "header_7", "header_8", "header_9", "image", "html", "payment");
		$formChunkNumber = 0;
		foreach ($formContentLines as $lineIndex => $thisLine) {
			if (!startsWith($thisLine, "<!-- Chunk - ")) {
				continue;
			}
			$chunkType = str_replace(" ", "_", strtolower(str_replace(" -->", "", substr($thisLine, strlen("<!-- Chunk - ")))));
			if (!in_array($chunkType, $validChunkTypes)) {
				continue;
			}
			$formChunkNumber++;
			switch ($chunkType) {
				case "field":
					$thisIndex = $lineIndex;
					while (true) {
						$thisIndex++;
						if (!array_key_exists($thisIndex, $formContentLines)) {
							break 2;
						}
						$nextLine = $formContentLines[$thisIndex];
						if (startsWith($nextLine, "%field:")) {
							$formFieldCode = trim(str_replace("%", "", substr($nextLine, strlen("%field:"))));
							$formFieldId = getFieldFromId("form_field_id", "form_fields", "form_field_code", $formFieldCode);
							if (empty($formFieldId)) {
								break 2;
							}
							$description = getFieldFromId("description", "form_fields", "form_field_id", $formFieldId);
							$formLabel = getFieldFromId("control_value", "form_definition_controls", "form_definition_id", $returnArray['primary_id']['data_value'], "column_name = ? and control_name = 'form_label'", $formFieldCode);
							$required = getFieldFromId("control_value", "form_definition_controls", "form_definition_id", $returnArray['primary_id']['data_value'], "column_name = ? and control_name = 'not_null'", $formFieldCode);
							$formFieldTagValues = explode(",", getFieldFromId("control_value", "form_definition_controls", "form_definition_id", $returnArray['primary_id']['data_value'], "column_name = ? and control_name = 'form_field_tags'", $formFieldCode));
							?>
                            <div class="form-chunk" id="form_chunk_<?= $formChunkNumber ?>" data-row_number="<?= $formChunkNumber ?>">
                                <div class="grip"><span class="fa fa-ellipsis-v"></span><span class="fa fa-ellipsis-v"></span></div>
                                <div class="form-chunk-information">
                                    <input type="hidden" data-field_name="sequence_number" id="chunk_information_sequence_number_<?= $formChunkNumber ?>" name="chunk_information_sequence_number_<?= $formChunkNumber ?>" value="<?= $formChunkNumber ?>">
                                    <input type="hidden" data-field_name="data_type" id="chunk_information_data_type_<?= $formChunkNumber ?>" name="chunk_information_data_type_<?= $formChunkNumber ?>" value="<?= $chunkType ?>">
                                    <input type="hidden" data-field_name="form_field_id" id="chunk_information_form_field_id_<?= $formChunkNumber ?>" name="chunk_information_form_field_id_<?= $formChunkNumber ?>" value="<?= $formFieldId ?>">
                                    <input type="hidden" data-field_name="required" id="chunk_information_required_<?= $formChunkNumber ?>" name="chunk_information_required_<?= $formChunkNumber ?>" value="<?= ($required == "true" ? "1" : "0") ?>">
                                    <input type="hidden" data-field_name="form_label" id="chunk_information_form_label_<?= $formChunkNumber ?>" name="chunk_information_form_label_<?= $formChunkNumber ?>" value="<?= htmlText($formLabel) ?>">
									<?php
									$formFieldTagString = "";
									foreach ($formFieldTags as $formFieldTag) {
										$formFieldTagString .= (empty($formFieldTagString) ? "" : ",") . "form_field_tag_" . $formFieldTag['field_tag'];
										?>
                                        <input type="hidden" data-field_name="form_field_tag_<?= $formFieldTag['field_tag'] ?>" id="chunk_information_form_field_tag_<?= $formFieldTag['field_tag'] ?>_<?= $formChunkNumber ?>" name="chunk_information_form_field_tag_<?= $formFieldTag['field_tag'] ?>_<?= $formChunkNumber ?>" value="<?= (in_array($formFieldTag['field_tag'], $formFieldTagValues) ? "1" : "0") ?>">
									<?php } ?>
                                    <input type="hidden" data-field_name="field_names" id="chunk_information_field_names_<?= $formChunkNumber ?>" name="chunk_information_field_names_<?= $formChunkNumber ?>" value="sequence_number,data_type,form_field_id,required,form_label<?= (empty($formFieldTagString) ? "" : ",") . $formFieldTagString ?>">
                                </div>
                                <p class="chunk-description">Form Field: <?= htmlText($description) ?></p>
                                <span class="fad fa-up-to-line move-chunk-top"></span>
                                <span class="fad fa-down-to-line move-chunk-bottom"></span>
                                <span class="fas fa-times delete-chunk"></span>
                            </div>
							<?php
							break;
						}
					}
					break;
				case "paragraph":
					$thisIndex = $lineIndex;
					$paragraphText = "";
					while (true) {
						$thisIndex++;
						if (array_key_exists($thisIndex, $formContentLines)) {
							$nextLine = $formContentLines[$thisIndex];
						} else {
							$nextLine = "<!-- End Chunk -->";
						}
						if (startsWith($nextLine, "<!-- End Chunk")) {
							$paragraphText = trim(str_replace("</p>", "\n\n", str_replace("<p>", "", $paragraphText)));
							?>
                            <div class="form-chunk" id="form_chunk_<?= $formChunkNumber ?>" data-row_number="<?= $formChunkNumber ?>">
                                <div class="grip"><span class="fa fa-ellipsis-v"></span><span class="fa fa-ellipsis-v"></span></div>
                                <div class="form-chunk-information">
                                    <input type="hidden" data-field_name="sequence_number" id="chunk_information_sequence_number_<?= $formChunkNumber ?>" name="chunk_information_sequence_number_<?= $formChunkNumber ?>" value="<?= $formChunkNumber ?>">
                                    <input type="hidden" data-field_name="data_type" id="chunk_information_data_type_<?= $formChunkNumber ?>" name="chunk_information_data_type_<?= $formChunkNumber ?>" value="<?= $chunkType ?>">
                                    <input type="hidden" data-field_name="paragraph_text" id="chunk_information_paragraph_text_<?= $formChunkNumber ?>" name="chunk_information_paragraph_text_<?= $formChunkNumber ?>" value="<?= htmlText($paragraphText) ?>">
                                    <input type="hidden" data-field_name="field_names" id="chunk_information_field_names_<?= $formChunkNumber ?>" name="chunk_information_field_names_<?= $formChunkNumber ?>" value="sequence_number,data_type,paragraph_text">
                                </div>
                                <p class="chunk-description">Paragraph: <?= str_replace(">", " ", str_replace("<", " ", substr($paragraphText, 0, 50))) ?></p>
                                <span class="fad fa-up-to-line move-chunk-top"></span>
                                <span class="fad fa-down-to-line move-chunk-bottom"></span>
                                <span class="fas fa-times delete-chunk"></span>
                            </div>
							<?php
							break;
						} else {
							$paragraphText .= $nextLine;
						}
					}
					break;
				case "header_1":
				case "header_2":
				case "header_3":
				case "header_4":
				case "header_5":
				case "header_6":
				case "header_7":
				case "header_8":
				case "header_9":
					$headerLevel = substr($chunkType, -1);
					$chunkType = "header";
					$thisIndex = $lineIndex;
					$headerText = "";
					while (true) {
						$thisIndex++;
						if (array_key_exists($thisIndex, $formContentLines)) {
							$nextLine = $formContentLines[$thisIndex];
						} else {
							$nextLine = "<!-- End Chunk -->";
						}
						if (startsWith($nextLine, "<!-- End Chunk")) {
							$headerText = trim(str_replace("</h" . $headerLevel . ">", "", str_replace("<h" . $headerLevel . ">", "", $headerText)));
							?>
                            <div class="form-chunk" id="form_chunk_<?= $formChunkNumber ?>" data-row_number="<?= $formChunkNumber ?>">
                                <div class="grip"><span class="fa fa-ellipsis-v"></span><span class="fa fa-ellipsis-v"></span></div>
                                <div class="form-chunk-information">
                                    <input type="hidden" data-field_name="sequence_number" id="chunk_information_sequence_number_<?= $formChunkNumber ?>" name="chunk_information_sequence_number_<?= $formChunkNumber ?>" value="<?= $formChunkNumber ?>">
                                    <input type="hidden" data-field_name="data_type" id="chunk_information_data_type_<?= $formChunkNumber ?>" name="chunk_information_data_type_<?= $formChunkNumber ?>" value="<?= $chunkType ?>">
                                    <input type="hidden" data-field_name="header_text" id="chunk_information_header_text_<?= $formChunkNumber ?>" name="chunk_information_header_text_<?= $formChunkNumber ?>" value="<?= htmlText($headerText) ?>">
                                    <input type="hidden" data-field_name="header_level" id="chunk_information_header_level_<?= $formChunkNumber ?>" name="chunk_information_header_level_<?= $formChunkNumber ?>" value="<?= $headerLevel ?>">
                                    <input type="hidden" data-field_name="field_names" id="chunk_information_field_names_<?= $formChunkNumber ?>" name="chunk_information_field_names_<?= $formChunkNumber ?>" value="sequence_number,data_type,header_text,header_level">
                                </div>
                                <p class="chunk-description">Header <?= $headerLevel ?>: <?= htmlText($headerText) ?></p>
                                <span class="fad fa-up-to-line move-chunk-top"></span>
                                <span class="fad fa-down-to-line move-chunk-bottom"></span>
                                <span class="fas fa-times delete-chunk"></span>
                            </div>
							<?php
							break;
						} else {
							$headerText .= $nextLine;
						}
					}
					break;
				case "html":
					$thisIndex = $lineIndex;
					$htmlText = "";
                    $description = "";
					while (true) {
						$thisIndex++;
						if (array_key_exists($thisIndex, $formContentLines)) {
							$nextLine = $formContentLines[$thisIndex];
						} else {
							$nextLine = "<!-- End Chunk -->";
						}
						if (startsWith($nextLine, "<!-- Description - ")) {
                            $description = str_replace(" -->","",substr($nextLine,strlen("<!-- Description - ")));
                            continue;
						}
						if (startsWith($nextLine, "<!-- End Chunk")) {
							$htmlText = trim($htmlText);
                            if (empty($description)) {
	                            $description = "HTML Content";
                            }
                            $description = htmlText($description);
							?>
                            <div class="form-chunk" id="form_chunk_<?= $formChunkNumber ?>" data-row_number="<?= $formChunkNumber ?>">
                                <div class="grip"><span class="fa fa-ellipsis-v"></span><span class="fa fa-ellipsis-v"></span></div>
                                <div class="form-chunk-information">
                                    <input type="hidden" data-field_name="sequence_number" id="chunk_information_sequence_number_<?= $formChunkNumber ?>" name="chunk_information_sequence_number_<?= $formChunkNumber ?>" value="<?= $formChunkNumber ?>">
                                    <input type="hidden" data-field_name="data_type" id="chunk_information_data_type_<?= $formChunkNumber ?>" name="chunk_information_data_type_<?= $formChunkNumber ?>" value="<?= $chunkType ?>">
                                    <input type="hidden" data-field_name="html_text" id="chunk_information_html_text_<?= $formChunkNumber ?>" name="chunk_information_html_text_<?= $formChunkNumber ?>" value="<?= htmlText($htmlText) ?>">
                                    <input type="hidden" data-field_name="field_names" id="chunk_information_field_names_<?= $formChunkNumber ?>" name="chunk_information_field_names_<?= $formChunkNumber ?>" value="sequence_number,data_type,html_text,html_description">
                                    <input type="hidden" data-field_name="html_description" id="chunk_information_html_description_<?= $formChunkNumber ?>" name="chunk_information_html_description_<?= $formChunkNumber ?>" value="<?= htmlText($description) ?>">
                                </div>
                                <p class="chunk-description"><?= $description ?></p>
                                <span class="fad fa-up-to-line move-chunk-top"></span>
                                <span class="fad fa-down-to-line move-chunk-bottom"></span>
                                <span class="fas fa-times delete-chunk"></span>
                            </div>
							<?php
							break;
						} else {
							$htmlText .= $nextLine . "\n";
						}
					}
					break;
				case "image":
					$thisIndex = $lineIndex;
					while (true) {
						$thisIndex++;
						if (!array_key_exists($thisIndex, $formContentLines)) {
							break 2;
						}
						$nextLine = $formContentLines[$thisIndex];
						if (startsWith($nextLine, "<p><img src=\"/getimage")) {
							$imageId = trim(str_replace("></p>", "", substr($nextLine, strlen("<p><img src=\"/getimage.php?id="))));
							$imageId = getFieldFromId("image_id", "images", "image_id", $imageId);
							if (empty($imageId)) {
								break 2;
							}
							$description = getFieldFromId("description", "images", "image_id", $imageId);
							?>
                            <div class="form-chunk" id="form_chunk_<?= $formChunkNumber ?>" data-row_number="<?= $formChunkNumber ?>">
                                <div class="grip"><span class="fa fa-ellipsis-v"></span><span class="fa fa-ellipsis-v"></span></div>
                                <div class="form-chunk-information">
                                    <input type="hidden" data-field_name="sequence_number" id="chunk_information_sequence_number_<?= $formChunkNumber ?>" name="chunk_information_sequence_number_<?= $formChunkNumber ?>" value="<?= $formChunkNumber ?>">
                                    <input type="hidden" data-field_name="data_type" id="chunk_information_data_type_<?= $formChunkNumber ?>" name="chunk_information_data_type_<?= $formChunkNumber ?>" value="<?= $chunkType ?>">
                                    <input type="hidden" data-field_name="image_id" id="chunk_information_image_id_<?= $formChunkNumber ?>" name="chunk_information_image_id_<?= $formChunkNumber ?>" value="<?= $imageId ?>">
                                    <input type="hidden" data-field_name="description" id="chunk_information_description_<?= $formChunkNumber ?>" name="chunk_information_description_<?= $formChunkNumber ?>" value="<?= htmlText($description) ?>">
                                    <input type="hidden" data-field_name="field_names" id="chunk_information_field_names_<?= $formChunkNumber ?>" name="chunk_information_field_names_<?= $formChunkNumber ?>" value="sequence_number,data_type,image_id">
                                </div>
                                <p class="chunk-description">Image: <img src='/getimage.php?id=<?= $imageId ?>&thumb=true' alt='image'></p>
                                <span class="fad fa-up-to-line move-chunk-top"></span>
                                <span class="fad fa-down-to-line move-chunk-bottom"></span>
                                <span class="fas fa-times delete-chunk"></span>
                            </div>
							<?php
							break;
						}
					}
					break;
				case "payment":
					$headerLevel = substr($chunkType, -1);
					$chunkType = "payment";
					$thisIndex = $lineIndex;
					$headerText = "";
					while (true) {
						$thisIndex++;
						if (array_key_exists($thisIndex, $formContentLines)) {
							$nextLine = $formContentLines[$thisIndex];
						} else {
							$nextLine = "<!-- End Chunk -->";
						}
						if (startsWith($nextLine, "<!-- End Chunk")) {
							?>
                            <div class="form-chunk" id="form_chunk_<?= $formChunkNumber ?>" data-row_number="<?= $formChunkNumber ?>">
                                <div class="grip"><span class="fa fa-ellipsis-v"></span><span class="fa fa-ellipsis-v"></span></div>
                                <div class="form-chunk-information">
                                    <input type="hidden" data-field_name="sequence_number" id="chunk_information_sequence_number_<?= $formChunkNumber ?>" name="chunk_information_sequence_number_<?= $formChunkNumber ?>" value="<?= $formChunkNumber ?>">
                                    <input type="hidden" data-field_name="data_type" id="chunk_information_data_type_<?= $formChunkNumber ?>" name="chunk_information_data_type_<?= $formChunkNumber ?>" value="<?= $chunkType ?>">
                                    <input type="hidden" data-field_name="payment_block" id="chunk_information_payment_block_<?= $formChunkNumber ?>" name="chunk_information_payment_block_<?= $formChunkNumber ?>" value="true">
                                    <input type="hidden" data-field_name="field_names" id="chunk_information_field_names_<?= $formChunkNumber ?>" name="chunk_information_field_names_<?= $formChunkNumber ?>" value="sequence_number,data_type,payment_block">
                                </div>
                                <p class="chunk-description">Payment fields for <span class='highlighted-text'><?= $returnArray['payment_description']['data_value'] ?></span></p>
                                <span class="fad fa-up-to-line move-chunk-top"></span>
                                <span class="fad fa-down-to-line move-chunk-bottom"></span>
                                <span class="fas fa-times delete-chunk"></span>
                            </div>
							<?php
							$returnArray['payment_exists'] = true;
							break;
						}
					}
					break;
			}
		}
		$returnArray['form_builder_content'] = array("data_value" => ob_get_clean());
		$returnArray['form_chunk_number'] = array("data_value" => $formChunkNumber);
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		foreach ($nameValues as $fieldName => $fieldData) {
			if (startsWith($fieldName, "chunk_information_required_")) {
				$formChunkNumber = substr($fieldName, strlen("chunk_information_required_"));
				if (!is_numeric($formChunkNumber)) {
					continue;
				}
				$formFieldCode = getFieldFromId("form_field_code", "form_fields", "form_field_id", $nameValues['chunk_information_form_field_id_' . $formChunkNumber]);
				if (!empty($formFieldCode)) {
					executeQuery("delete from form_definition_controls where form_definition_id = ? and column_name = ? and control_name = 'not_null'", $nameValues['primary_id'], $formFieldCode);
					executeQuery("insert into form_definition_controls (form_definition_id,column_name,control_name,control_value) values (?,?,?,?)", $nameValues['primary_id'], $formFieldCode, 'not_null', ($fieldData == 1 ? "true" : "false"));
				}
			}
			if (startsWith($fieldName, "chunk_information_form_label_")) {
				$formChunkNumber = substr($fieldName, strlen("chunk_information_form_label_"));
				if (!is_numeric($formChunkNumber)) {
					continue;
				}
				$formFieldCode = getFieldFromId("form_field_code", "form_fields", "form_field_id", $nameValues['chunk_information_form_field_id_' . $formChunkNumber]);
				if (!empty($formFieldCode)) {
					executeQuery("delete from form_definition_controls where form_definition_id = ? and column_name = ? and control_name = 'form_label'", $nameValues['primary_id'], $formFieldCode);
					if (!empty($fieldData)) {
						executeQuery("insert into form_definition_controls (form_definition_id,column_name,control_name,control_value) values (?,?,?,?)", $nameValues['primary_id'], $formFieldCode, 'form_label', $fieldData);
					}
				}
			}
		}
		foreach ($nameValues as $fieldName => $fieldData) {
			if (startsWith($fieldName, "chunk_information_field_names_")) {
				$formChunkNumber = substr($fieldName, strlen("chunk_information_field_names_"));
				if (!is_numeric($formChunkNumber)) {
					continue;
				}
				$formFieldCode = getFieldFromId("form_field_code", "form_fields", "form_field_id", $nameValues['chunk_information_form_field_id_' . $formChunkNumber]);
				$fieldNames = explode(",", $fieldData);
				$formFieldTags = "";
				foreach ($fieldNames as $thisFieldName) {
					if (startsWith($thisFieldName, "form_field_tag_") && array_key_exists("chunk_information_" . $thisFieldName . "_" . $formChunkNumber, $nameValues) && !empty($nameValues["chunk_information_" . $thisFieldName . "_" . $formChunkNumber])) {
						$formFieldTags .= (empty($formFieldTags) ? "" : ",") . substr($thisFieldName, strlen("form_field_tag_"));
					}
				}
				executeQuery("delete from form_definition_controls where form_definition_id = ? and column_name = ? and control_name = 'form_field_tags'", $nameValues['primary_id'], $formFieldCode);
				if (!empty($formFieldTags)) {
					executeQuery("insert into form_definition_controls (form_definition_id,column_name,control_name,control_value) values (?,?,?,?)", $nameValues['primary_id'], $formFieldCode, 'form_field_tags', $formFieldTags);
				}
			}
		}
		return true;
	}

	function hiddenElements() {
		?>
        <div class="hidden" id="_form_chunk">
            <div class="form-chunk" id="%chunk_id%" data-row_number="%form_chunk_number%">
                <div class="grip"><span class="fa fa-ellipsis-v"></span><span class="fa fa-ellipsis-v"></span></div>
                <div class="form-chunk-information"></div>
                <p class="chunk-description">%chunk_description%</p>
                <span class="fad fa-up-to-line move-chunk-top"></span>
                <span class="fad fa-down-to-line move-chunk-bottom"></span>
                <span class="fas fa-times delete-chunk"></span>
            </div>
        </div>
        <input type="hidden" id="form_chunk_row_number" name="form_chunk_row_number">

        <div class="dialog-box" id="chunk_type_field_dialog">
            <form id="_field_form">
                <div class="basic-form-line" id="_form_field_id_row">
                    <label for="form_field_id" class="required-label">Form Field</label>
                    <select class="validate[required]" id="form_field_id" name="form_field_id">
                        <option value="">[Select]</option>
						<?php
						$resultSet = executeQuery("select * from form_fields where client_id = ? order by description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['form_field_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_form_label_row">
                    <label for="form_label" class="">Field Label</label>
                    <span class="help-label">Leave blank to use default</span>
                    <input type="text" size="60" id="form_label" name="form_label"/>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_required_row">
                    <input type="checkbox" id="required" name="required" value="1"><label for="required" class="checkbox-label">Field Required</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div id="form_field_tags">
                </div>

            </form>
        </div>

        <div class="dialog-box" id="chunk_type_paragraph_dialog">
            <form id="_paragraph_form">

                <div class="basic-form-line" id="_paragraph_text_row">
                    <label for="paragraph_text" class="required-label">Paragraph Content</label>
                    <textarea class="validate[required]" id="paragraph_text" name="paragraph_text"></textarea>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

            </form>
        </div>

        <div class="dialog-box" id="chunk_type_payment_dialog">
            <form id="_payment_form">

                <div class="basic-form-line" id="_payment_text_row">
                    <p>Process payment for <span class='highlighted-text' id="payment_description"></span></p>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

            </form>
        </div>

        <div class="dialog-box" id="chunk_type_header_dialog">
            <form id="_header_form">

                <div class="basic-form-line" id="_header_level_row">
                    <label for="header_level" class="required-label">Header Level</label>
                    <select class="validate[required]" id="header_level" name="header_level">
                        <option value="">[Select]</option>
                        <option value="1">H1</option>
                        <option value="2">H2</option>
                        <option value="3">H3</option>
                        <option value="4">H4</option>
                        <option value="5">H5</option>
                        <option value="6">H6</option>
                        <option value="7">H7</option>
                        <option value="8">H8</option>
                        <option value="9">H9</option>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_header_text_row">
                    <label for="header_text" class="required-label">Header Text</label>
                    <input type="text" size="60" class="validate[required]" id="header_text" name="header_text"/>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

            </form>
        </div>

        <div class="dialog-box" id="chunk_type_html_dialog">
            <form id="_html_form">
                <div class="basic-form-line" id="_html_description_row">
                    <label for="html_description" class="required-label">Description</label>
                    <input type='text' class="validate[required]" id="html_description" name="html_description">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
                <div class="basic-form-line" id="_html_text_row">
                    <label for="html_text" class="required-label">HTML Content</label>
                    <textarea class="validate[required]" id="html_text" name="html_text"></textarea>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
            </form>
        </div>

        <div class="dialog-box" id="chunk_type_image_dialog">
            <form id="_image_form">
                <div class="basic-form-line" id="_image_id_row">
                    <label for="image_id" class="">Image</label>
                    <select tabindex="10" class="image-picker-selector" id="image_id" name="image_id">
                        <option value="">No image selected. Click to choose -&gt;</option>
                    </select>
                    <button class="image-picker" data-column_name="image_id" tabindex="10">Choose</button>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
            </form>
        </div>

        <div class="dialog-box" id="view_html_dialog">
            <textarea id="form_html_content" name="form_html_content"></textarea>
        </div>

		<?php
	}
}

$pageObject = new FormBuilderPage("form_definitions");
$pageObject->displayPage();
