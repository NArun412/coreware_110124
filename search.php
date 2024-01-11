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

$GLOBALS['gPageCode'] = "SEARCH";
$GLOBALS['gEmbeddablePage'] = true;
require_once "shared/startup.inc";

class ThisPage extends Page {
	var $iSearchObject = "";
	var $iSearchCriteriaId = "";

	function setup() {
		$this->iSearchObject = new Search();
		$this->iSearchCriteriaId = getFieldFromId("search_criteria_id", "search_criteria", "search_criteria_code", $_GET['code']);
		if (empty($this->iSearchCriteriaId)) {
			$this->iSearchCriteriaId = getFieldFromId("search_criteria_id", "search_criteria", "search_criteria_code", $_POST['search_criteria_code']);
		}
	}

	function onLoadJavascript() {
		if (!empty($_POST["search_text"]) || !empty($_GET["search_text"])) {
			$searchText = strtolower($_POST['search_text']);
			if (empty($searchText)) {
				$searchText = strtolower($_GET['search_text']);
			}
		} else {
			$searchText = "";
		}
		?>
        $("#_search_again #search_text").keydown(function(event) {
        if (event.which == 13) {
        if (!empty($(this).val())) {
        $("#_edit_form").submit();
        return false;
        }
        }
        });
        $("#_search_again").find("#search_text").blur(function() {
        if (!empty($(this).val())) {
        $("#_edit_form").submit();
        return false;
        }
        });
        $(document).on("tap click","#search_again",function() {
        $("#_search_results").hide();
        $("#_search_again").show();
        $("#_search_again #search_text").focus();
        return false;
        });
		<?php if (empty($searchText)) { ?>
            $("#_search_again").show();
		<?php } else { ?>
            $("#_search_results").show();
		<?php } ?>
		<?php
	}

	function mainContent() {
		echo $this->getPageData("content");
		if (!empty($this->iSearchCriteriaId)) {
			$searchCriteriaCode = getFieldFromId("search_criteria_code", "search_criteria", "search_criteria_id", $this->iSearchCriteriaId);
		}
		$searchText = $_POST['search_text'];
		if (empty($searchText)) {
			$searchText = $_GET['search_text'];
		}
		$resultArray = $this->iSearchObject->getSearchResults($this->iSearchCriteriaId, $searchText);
		if (!$resultArray) {
			$resultArray = array();
		}
		# /product-search-results?search_text=surefire
		$pageId = getFieldFromId("page_id", "pages", "link_name", "product-search-results");
		$count = 0;
		if (!empty($pageId)) {
			$resultSet = executeQuery("select count(*) from products where client_id = ?", $GLOBALS['gClientId']);
			if ($row = getNextRow($resultSet)) {
				$count = $row['count(*)'];
			}
		}
		$resultCount = count($resultArray);
		?>
        <div id="_outer_search">
            <div id="_search_results">
                <p><?= $resultCount ?> result<?= ($resultCount == 1 ? "" : "s") ?> found for '<?= htmlText($searchText) ?>'</p>
				<?php if ($count > 0 && !empty($pageId)) { ?>
                    <p><a href='/product-search-results?search_text=<?= urlencode($searchText) ?>'>Search products for '<?= htmlText($searchText) ?>'</a></p>
				<?php } ?>
                <p><a href="#" id="search_again">Search Again</a></p>
				<?php
				foreach ($resultArray as $row) {

					$linkUrl = (substr($row['url'], 0, 4) == "http" ? "" : "http://" . $_SERVER['HTTP_HOST'] . (substr($row['url'], 0, 1) == "/" ? "" : "/")) . $row['url'];
					?>
                    <h2><a href="<?= $linkUrl ?>"><?= htmlText($row['description']) ?></a></h2>
                    <p class='result-content'><?= $row['content'] ?></p>
                    <hr>
					<?php
				}
				?>
            </div>
            <div id="_search_again">
                <form id="_edit_form" name="_edit_form" method="POST" action="<?= $GLOBALS['gLinkUrl'] ?>">
                    <input type="hidden" id="search_criteria_code" name="search_criteria_code" value="<?= $searchCriteriaCode ?>">
                    <p class="align-center"><input class="field-text" type="text" size="40" name="search_text" id="search_text" placeholder="Search Site"/></p>
                </form>
            </div>
        </div>
		<?php
		echo $this->getPageData("after_form_content");
		return true;
	}

	function internalCSS() {
		?>
        div#_search_results { display: none; }
        div#_search_again { display: none; }
        .found-word { font-weight: bold; font-size: 120%; color: rgb(0,0,0) !important; }
		<?php
	}
}

$pageObject = new ThisPage();
$pageObject->displayPage();
