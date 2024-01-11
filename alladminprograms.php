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

$GLOBALS['gPageCode'] = "ALLADMINPROGRAMS";
require_once "shared/startup.inc";

class AllAdminProgramPage extends Page {

	function onLoadJavascript() {
		?>
        <script>
            $("#text_filter").keyup(function (event) {
                const textFilter = $(this).val().toLowerCase();
                if (empty(textFilter)) {
                    $("ul#page_list li").removeClass("hidden");
                } else {
                    $("ul#page_list li").each(function () {
                        const description = $(this).find("a").html().toLowerCase();
                        if (description.indexOf(textFilter) >= 0) {
                            $(this).removeClass("hidden");
                        } else {
                            $(this).addClass("hidden");
                        }
                    });
                }
                if (event.which === 13 || event.which === 3) {
                    const $pageList = $("ul#page_list li");
                    if ($pageList.not(".hidden").length === 1) {
                        $pageList.not(".hidden").trigger("click");
                    }
                }
            }).focus();
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
			ul#page_list {
				column-count: 2;
			}

			ul#page_list li {
				line-height: 1.8;
			}

			ul#page_list li:hover {
				background-color: rgb(240, 240, 240);
			}

			#_text_filter_row {
				margin-bottom: 20px;
			}

			@media only screen and (max-width: 650px) {
				ul#page_list {
					column-count: 1;
				}
			}
        </style>
		<?php
	}

	function mainContent() {
		echo $this->iPageData['content'];
		$pages = array();
		$resultSet = executeQuery("select * from pages where template_id in (select template_id from templates where include_crud = 1) and ((client_id = " . $GLOBALS['gClientId'] . " and template_id in (select template_id from templates where client_id = " .
			$GLOBALS['gDefaultClientId'] . " and template_code = 'MANAGEMENT')) or " .
			"(client_id = " . $GLOBALS['gDefaultClientId'] . ($GLOBALS['gUserRow']['superuser_flag'] ? "" : " and page_id in (select page_id from page_access where all_client_access = 1)") . "))" .
			($GLOBALS['gUserRow']['superuser_flag'] || $GLOBALS['gUserRow']['full_client_access'] ? "" : " and page_id in (select page_id from menu_items)") .
			" and inactive = 0 and (publish_start_date is null or (publish_start_date is not null and current_date >= publish_start_date)) and (publish_end_date is null or (publish_end_date is not null and current_date <= publish_end_date)) order by description");
		while ($row = getNextRow($resultSet)) {
			$pageSubsystemId = $row['subsystem_id'];
			if (!empty($pageSubsystemId) && !$GLOBALS['gUserRow']['superuser_flag']) {
				if (!in_array($pageSubsystemId, $GLOBALS['gClientSubsystems'])) {
					continue;
				}
			}
			if (canAccessPage($row['page_id'])) {
				$pages[] = $row;
			}
		}
		?>
        <p>You can also access any Administrator page from anywhere in the system simply by hitting the shift key twice quickly.</p>
        <div class="basic-form-line shortest-label" id="_text_filter_row">
            <label for="text_filter">Filter</label>
            <input tabindex="10" type="text" size="40" id="text_filter" name="text_filter" placeholder="Filter">
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>
        <ul id="page_list">
			<?php
			foreach ($pages as $pageRow) {
                if (empty($pageRow['script_filename']) && empty($pageRow['link_name'])) {
                    continue;
                }
				$domainName = $pageRow['domain_name'];
				if (!empty($pageRow['link_name'])) {
					$linkUrl = ($domainName == $_SERVER['HTTP_HOST'] || empty($domainName) || $GLOBALS['gDevelopmentServer'] ? "" : "https://" . $domainName) . "/" . $pageRow['link_name'];
				} else {
					$linkUrl = ($domainName == $_SERVER['HTTP_HOST'] || empty($domainName) || $GLOBALS['gDevelopmentServer'] ? "" : "https://" . $domainName) . "/" . $pageRow['script_filename'] . (empty($pageRow['script_arguments']) ? "" : "?" . $pageRow['script_arguments']);
				}
				?>
                <li class='menu-item' data-script_filename='<?= $linkUrl ?>'><a href="<?= $linkUrl ?>"><?= $pageRow['description'] ?></a></li>
				<?php
			}
			?>
        </ul>
		<?php
		return true;
	}
}

$pageObject = new AllAdminProgramPage();
$pageObject->displayPage();
