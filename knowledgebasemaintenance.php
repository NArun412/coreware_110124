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

$GLOBALS['gPageCode'] = "KNOWLEDGEBASEMAINT";
require_once "shared/startup.inc";

class KnowledgeBaseMaintenancePage extends Page {

	function setup() {
		if (array_key_exists("help_desk_entry_id", $_GET)) {
			$_SESSION['help_desk_entry_id'] = $_GET['help_desk_entry_id'];
			saveSessionData();
		}
		$filters = array();
		$resultSet = executeQuery("select * from knowledge_base_categories where client_id = ? order by description", $GLOBALS['gClientId']);
		$filters['category_header'] = array("form_label" => "Categories", "data_type" => "header");
		while ($row = getNextRow($resultSet)) {
			$filters['knowledge_base_category_' . $row['knowledge_base_category_id']] = array("form_label" => $row['description'],
				"where" => "knowledge_base.knowledge_base_id in (select knowledge_base_id from knowledge_base_category_links where knowledge_base_category_id = " . $row['knowledge_base_category_id'] . ")",
				"data_type" => "tinyint");
		}
		$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("view" => array("label" => getLanguageText("View"),
			"disabled" => false)));
		$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
		$this->iTemplateObject->getTableEditorObject()->setFileUpload(true);
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("content", "classes", "ck-editor");
        $this->iDataSource->addColumnControl("content", "no_editor", true);

        $this->iDataSource->addColumnControl("knowledge_base_category_links", "data_type", "custom");
        $this->iDataSource->addColumnControl("knowledge_base_category_links", "form_label", "Categories");
        $this->iDataSource->addColumnControl("knowledge_base_category_links", "control_table", "knowledge_base_categories");
        $this->iDataSource->addColumnControl("knowledge_base_category_links", "links_table", "knowledge_base_category_links");
		$this->iDataSource->addColumnControl("knowledge_base_category_links", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("knowledge_base_category_links", "get_choices", "knowledgeBaseCategoryChoices");

        $this->iDataSource->addColumnControl("knowledge_base_tag_links", "data_type", "custom");
        $this->iDataSource->addColumnControl("knowledge_base_tag_links", "form_label", "Tags");
        $this->iDataSource->addColumnControl("knowledge_base_tag_links", "control_table", "knowledge_base_tags");
        $this->iDataSource->addColumnControl("knowledge_base_tag_links", "links_table", "knowledge_base_tag_links");
        $this->iDataSource->addColumnControl("knowledge_base_tag_links", "control_class", "MultipleSelect");

        $this->iDataSource->addColumnControl("link_url", "data_type", "varchar");
        $this->iDataSource->addColumnControl("link_url", "inline-width", "500px");
        $this->iDataSource->addColumnControl("title_text", "data_type", "varchar");
        $this->iDataSource->addColumnControl("title_text", "inline-width", "500px");
        $this->iDataSource->addColumnControl("notes", "inline-height", "100px");

        $this->iDataSource->addColumnControl("knowledge_base_images", "data_type", "custom");
        $this->iDataSource->addColumnControl("knowledge_base_images", "form_label", "Images");
        $this->iDataSource->addColumnControl("knowledge_base_images", "list_table", "knowledge_base_images");
        $this->iDataSource->addColumnControl("knowledge_base_images", "control_class", "EditableList");
        $this->iDataSource->addColumnControl("knowledge_base_images", "list_table_controls", array("image_id"=>array("data_type"=>"image_input")));

        $this->iDataSource->addColumnControl("knowledge_base_files", "data_type", "custom");
        $this->iDataSource->addColumnControl("knowledge_base_files", "form_label", "Files");
        $this->iDataSource->addColumnControl("knowledge_base_files", "list_table", "knowledge_base_files");
        $this->iDataSource->addColumnControl("knowledge_base_files", "control_class", "EditableList");
    }

	function knowledgeBaseCategoryChoices($showInactive = false) {
		$knowledgeBaseCategoryChoices = array();
		$resultSet = executeQuery("select *,(select description from knowledge_base_categories kbc where knowledge_base_category_id = knowledge_base_categories.parent_knowledge_base_category_id) as parent_description " .
            "from knowledge_base_categories where client_id = ? order by parent_description,sort_order,description",$GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			if (empty($row['inactive']) || $showInactive) {
				$knowledgeBaseCategoryChoices[$row['knowledge_base_category_id']] = array("key_value" => $row['knowledge_base_category_id'], "description" => (empty($row['parent_description']) ? "" : $row['parent_description'] . "->") . $row['description'], "inactive" => $row['inactive'] == 1);
			}
		}
		freeResult($resultSet);
		return $knowledgeBaseCategoryChoices;
	}

	function afterGetRecord(&$returnArray) {
		if (empty($returnArray['primary_id']['data_value']) && !empty($_SESSION['help_desk_entry_id'])) {
			$returnArray['title_text']['data_value'] = getFieldFromId("description", "help_desk_entries", "help_desk_entry_id", $_SESSION['help_desk_entry_id']);
			$content = getFieldFromId("content", "help_desk_entries", "help_desk_entry_id", $_SESSION['help_desk_entry_id']);
			$resultSet = executeQuery("select * from help_desk_public_notes where help_desk_entry_id = ? order by time_submitted", $_SESSION['help_desk_entry_id']);
			while ($row = getNextRow($resultSet)) {
				$content .= (empty($content) ? "" : "\n\n") . $row['content'];
			}
			$returnArray['content']['data_value'] = $content;
			$_SESSION['help_desk_entry_id'] = "";
			saveSessionData();
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", "#_view_button", function () {
                if (empty($("#primary_id").val())) {
                    displayErrorMessage("Save first");
                    return;
                }
                window.open("/displayknowledgebase.php?id=" + $("#primary_id").val());
                return false;
            });
        </script>
		<?php
	}
}

$pageObject = new KnowledgeBaseMaintenancePage("knowledge_base");
$pageObject->displayPage();
