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

$GLOBALS['gPageCode'] = "STYLEGUIDEMAINT";
require_once "shared/startup.inc";

class StyleGuideMaintenancePage extends Page {

	function massageDataSource() {
		$this->iDataSource->addColumnControl("html_content", "classes", "data-format-HTML");

		$this->iDataSource->addColumnControl("style_guide_options", "data_type", "custom");
		$this->iDataSource->addColumnControl("style_guide_options", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("style_guide_options", "list_table", "style_guide_options");
		$this->iDataSource->addColumnControl("style_guide_options", "form_label", "Alternate Options");
		$this->iDataSource->addColumnControl("style_guide_options", "list_table_controls",
				array("description" => array("classes" => "description"), "class_name" => array("classes" => "class-name"), "content" => array("classes" => "css-content", "form_label" => "Additional CSS Content")));
	}

	function onLoadJavascript() {
		?>
		<script>
			$(document).on("change", ".css-content", function () {
				generateVisuals();
			});
			$(document).on("change", ".description", function () {
				generateVisuals();
			});
			$(document).on("change", "#html_content", function () {
				generateVisuals();
			});
			$(document).on("change", "#css_content", function () {
				generateVisuals();
			});
			$(document).on("click", "#show_descriptions", function () {
				generateVisuals();
			});
			$(".ace-css-editor").each(function () {
				const elementId = $(this).data("element_id");
				if (empty(elementId)) {
					return true;
				}
				const javascriptEditor = ace.edit(elementId + "-ace_editor");
				javascriptEditor.on("blur", function () {
					generateVisuals();
				});
			});
			$(".ace-html-editor").each(function () {
				const elementId = $(this).data("element_id");
				if (empty(elementId)) {
					return true;
				}
				const javascriptEditor = ace.edit(elementId + "-ace_editor");
				javascriptEditor.on("blur", function () {
					generateVisuals();
				});
			});

		</script>
		<?php
	}

	function javascript() {
		?>
		<script>

			function afterGetRecord() {
				generateVisuals();
			}

			function generateVisuals() {
				$("#visual_elements").html("");
				$("#visual_elements").append("<style>" + $("#css_content").val() + "</style>");
				let htmlContent = $("#html_content").val();
				let newHtmlContent = htmlContent.replace(new RegExp('%alternate_class%', 'g'), "");
				newHtmlContent = newHtmlContent.replace(new RegExp('%description%', 'g'), "Basic Element");
				$("#visual_elements").append("<div class='element-wrapper'><div>" + newHtmlContent + "</div>" + ($("#show_descriptions").prop("checked") ? "<p class='element-description'>" + description + "</p>" : "") + "</div>");
				let divCounter = 0;
				let addClass = true;
				if (htmlContent.indexOf("%alternate_class%") > 0) {
					addClass = false;
				}
				$("#_style_guide_options_row").find(".editable-list-data-row").each(function () {
					divCounter++;
					let cssContent = $(this).find(".css-content").val();
					let className = $(this).find(".class-name").val();
					let description = $(this).find(".description").val();
					newHtmlContent = htmlContent.replace(new RegExp('%alternate_class%', 'g'), className);
					newHtmlContent = newHtmlContent.replace(new RegExp('%description%', 'g'), description);
					$("#visual_elements").append("<style>" + cssContent + "</style><div id='element_wrapper_" + divCounter + "' class='element-wrapper'><div class='element-content'>" + newHtmlContent + "</div>" +
							($("#show_descriptions").prop("checked") ? "<p class='element-description'>" + description + "</p>" : "") + "</div>");
					if (addClass) {
						setTimeout(function() {
							$("#element_wrapper_" + divCounter).find("div.element-content").children().first().addClass(className);
						},100);
					}
				});
			}

			function afterAddEditableRow(listName, rowNumber, rowData) {
				if (listName === "style_guide_options") {
					var elementId = "style_guide_options_content-" + rowNumber;
					var beforeElementId = elementId;
					$("#" + elementId).addClass("hidden");
					$("<div data-element_id='" + elementId + "' class='ace-css-editor' id='" + elementId + "-ace_editor'></div>").insertBefore("#" + beforeElementId);
					var javascriptEditor = ace.edit(elementId + "-ace_editor");
					javascriptEditor.setTheme("ace/theme/solarized_light");
					javascriptEditor.setShowPrintMargin(false);
					javascriptEditor.session.setMode("ace/mode/sass");
					javascriptEditor.textInput.getElement().tabIndex = 10;
					javascriptEditor.getSession().setUseWrapMode(true);
					javascriptEditor.on("blur", function () {
						$("#" + elementId).val(javascriptEditor.getValue());
						generateVisuals();
					});
				}
			}
		</script>
		<?php
	}

	function internalCSS() {
		?>
		<style>
			#visual_elements {
				margin: 20px 0;
				border: 1px solid rgb(200, 200, 200);
				padding: 20px;
				width: 95%;
				display: flex;
			}

			#visual_elements > div {
				flex: 0 0 auto;
			}

			.ace-html-editor {
				max-width: 800px;
				max-height: 300px;
			}

			.ace-css-editor {
				max-width: 800px;
				max-height: 300px;
			}

			.element-wrapper {
				margin-right: 20px;
			}

			.element-wrapper p.element-description {
				padding-top: 5px;
				text-align: center;
			}

			.editable-list .ace-css-editor {
				width: 500px;
				height: 300px;
			}
		</style>
		<?php
	}
}

$pageObject = new StyleGuideMaintenancePage("style_guides");
$pageObject->displayPage();
