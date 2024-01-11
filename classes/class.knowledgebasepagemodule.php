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
%module:knowledge_base:knowledge_base_category_code%

Options:

element_id=xxxxxx - element ID of top level div
display_limit=99 - limit of how many knowledge base entries are displayed. Default is 10
sort_order=xxxxx - options are random, newest, oldest and title. Default is newest
hide_search=true - hide the search feature
*/

class KnowledgeBasePageModule extends PageModule {
	function createContent() {
		$knowledgeBaseCategoryCode = strtolower(array_shift($this->iParameters));
		$elementId = $this->iParameters['element_id'];
		if (empty($elementId)) {
			$elementId = "page_module_knowledge_base_category_" . $knowledgeBaseCategoryCode;
		}
		$displayLimit = $this->iParameters['display_limit'];
		if (empty($displayLimit)) {
			$displayLimit = 10;
		}

		$knowledgeBaseCategoryId = getFieldFromId("knowledge_base_category_id", "knowledge_base_categories", "knowledge_base_category_code", strtoupper($knowledgeBaseCategoryCode));
		if (empty($knowledgeBaseCategoryId)) {
			echo "<p>Invalid Knowledge Base Category: " . $knowledgeBaseCategoryCode . "</p>";
			return;
		}
		?>
        <div class='knowledge-base-wrapper'<?= (empty($elementId) ? "" : " id='" . $elementId . "'") ?>>

			<?php if (empty($this->iParameters['hide_search'])) { ?>
                <div class="form-line">
                    <label>Search Text</label>
                    <input tabindex="20" type="text" id="search_text_<?= $knowledgeBaseCategoryCode ?>" name="search_text_<?= $knowledgeBaseCategoryCode ?>" value="">
                    <div class='clear-div'></div>
                </div>
                <p>
                    <button id="search_button_<?= $knowledgeBaseCategoryCode ?>">Search Knowledgebase</button>
                </p>
			<?php } ?>
            <div class='knowledge-base-entries'>
				<?php
				switch ($this->iParameters['sort_order']) {
					case "random":
						$sortOrder = "rand()";
						break;
					case "oldest":
						$sortOrder = "date_entered,knowledge_base_id";
						break;
					case "title":
						$sortOrder = "title,knowledge_base_id";
						break;
					default:
						$sortOrder = "date_entered desc,knowledge_base_id desc";
						break;
				}
				$resultSet = executeQuery("select * from knowledge_base where knowledge_base_id in " .
					"(select knowledge_base_id from knowledge_base_category_links where knowledge_base_category_id = ?) and inactive = 0 " .
					($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " order by " . $sortOrder . " limit " . $displayLimit, $knowledgeBaseCategoryId);
				while ($row = getNextRow($resultSet)) {
					?>
                    <div class='knowledge-base-entry'>
                        <h3><?= htmlText($row['title_text']) ?></h3>
                        <h4><?= date("m/d/Y", strtotime($row['date_entered'])) ?></h4>
						<?= makeHtml($row['content']) ?>
                    </div>
					<?php
				}
				?>
            </div> <!-- knowledge-base-entries -->
        </div> <!-- knowledge-base-wrapper -->
		<?php if (empty($this->iParameters['hide_search'])) { ?>
            <script>
                $(function () {
                    $(document).on("keyup", "#search_text_<?= $knowledgeBaseCategoryCode ?>", function (event) {
                        if (event.which == 13 || event.which == 3) {
                            $("#search_button_<?= $knowledgeBaseCategoryCode ?>").trigger("click");
                        }
                        return false;
                    });
                    $(document).on("click", "#search_button_<?= $knowledgeBaseCategoryCode ?>", function () {
                        loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=page_module_search_knowledge_base", { display_limit: <?= $displayLimit ?>, knowledge_base_category_id: <?= $knowledgeBaseCategoryId ?>, search_text: $("#search_text_<?= $knowledgeBaseCategoryCode ?>").val() }, function(returnArray) {
                            if ("knowledge_base_entries" in returnArray) {
                                $("#<?= $elementId ?>").find(".knowledge-base-entries").html(returnArray['knowledge_base_entries']);
                            }
                        });
                    });
                });
            </script>
		<?php } ?>
		<?php
	}
}
