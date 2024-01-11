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

$GLOBALS['gPageCode'] = "SEARCHTERMSYNONYMMAINT";
require_once "shared/startup.inc";

class ThisPage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setFormSortOrder(array("search_term", "domain_name", "search_term_synonym_redirects", "search_term_synonym_product_categories", "search_term_synonym_product_departments", "search_term_synonym_product_manufacturers"));
			$this->iTemplateObject->getTableEditorObject()->addExcludeFormColumn(array("redirected_search_terms"));
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("redirected_search_terms", "select_value", "select group_concat(search_term) from search_term_synonym_redirects where search_term_synonym_id = search_term_synonyms.search_term_synonym_id");
		$this->iDataSource->addColumnControl("redirected_search_terms", "data_type", "varchar");
		$this->iDataSource->addColumnControl("redirected_search_terms", "form_label", "Redirected Search Terms");
		$this->iDataSource->addColumnControl("domain_name", "help_label", "Limit use of this search term to this domain. Leave blank to use on all domains.");

		$this->iDataSource->addColumnControl("search_term_synonym_product_categories", "data_type", "custom");
		$this->iDataSource->addColumnControl("search_term_synonym_product_categories", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("search_term_synonym_product_categories", "control_table", "product_categories");
		$this->iDataSource->addColumnControl("search_term_synonym_product_categories", "links_table", "search_term_synonym_product_categories");
		$this->iDataSource->addColumnControl("search_term_synonym_product_categories", "form_label", "Categories");
		$this->iDataSource->addColumnControl("search_term_synonym_product_categories", "help_label", "Search will be limited to these categories");

		$this->iDataSource->addColumnControl("search_term_synonym_product_departments", "data_type", "custom");
		$this->iDataSource->addColumnControl("search_term_synonym_product_departments", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("search_term_synonym_product_departments", "control_table", "product_departments");
		$this->iDataSource->addColumnControl("search_term_synonym_product_departments", "links_table", "search_term_synonym_product_departments");
		$this->iDataSource->addColumnControl("search_term_synonym_product_departments", "form_label", "Departments");
		$this->iDataSource->addColumnControl("search_term_synonym_product_departments", "help_label", "Search will be limited to these departments");

		$this->iDataSource->addColumnControl("search_term_synonym_product_manufacturers", "data_type", "custom");
		$this->iDataSource->addColumnControl("search_term_synonym_product_manufacturers", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("search_term_synonym_product_manufacturers", "list_table", "search_term_synonym_product_manufacturers");
		$this->iDataSource->addColumnControl("search_term_synonym_product_manufacturers", "form_label", "Manufacturers");
		$this->iDataSource->addColumnControl("search_term_synonym_product_manufacturers", "help_label", "Search will be limited to these manufacturers");

		$this->iDataSource->addColumnControl("search_term_synonym_redirects", "data_type", "custom");
		$this->iDataSource->addColumnControl("search_term_synonym_redirects", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("search_term_synonym_redirects", "list_table", "search_term_synonym_redirects");
		$this->iDataSource->addColumnControl("search_term_synonym_redirects", "form_label", "Redirected Terms");

		$this->iDataSource->addColumnControl("search_term_text", "data_type", "text");
		$this->iDataSource->addColumnControl("search_term_text", "form_label", "Text of Redirected Search Terms");
		$this->iDataSource->addColumnControl("search_term_text", "help_label", "Paste comma separated list of terms or a term on each line");
	}

	function afterGetRecord(&$returnArray) {
		$returnArray['search_term_text'] = array("data_value" => "");
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("blur","#search_term_text",function() {
                const newTerms = $(this).val().replace(new RegExp(",", "g"), "\n").split("\n");
                for (var i in newTerms) {
                    if (!empty(newTerms[i])) {
                        const thisRow = { search_term: { data_value: newTerms[i] } };
                        addEditableListRow("search_term_synonym_redirects",thisRow);
                    }
                }
                $(this).val("");
            });
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #search_term_text {
                width: 200px;
            }
        </style>
		<?php
	}
}

$pageObject = new ThisPage("search_term_synonyms");
$pageObject->displayPage();
