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

$GLOBALS['gPageCode'] = "DISPLAYKNOWLEDGEBASE";
require_once "shared/startup.inc";

class DisplayKnowledgebasePage extends Page {

    function mainContent() {
        $_POST = array_merge($_POST, $_GET);
        $_POST['id'] = getFieldFromId("knowledge_base_id", "knowledge_base", "knowledge_base_id", $_POST['id']);
        $knowledgeBaseCategoryId = "";
        $resultSet = executeQuery("select * from knowledge_base_categories where knowledge_base_category_code = ? and (client_id = ? or client_id = ?) " .
            "order by client_id" . (strtoupper(substr($_POST['category'], 0, strlen("COREWARE_"))) == "COREWARE_" ? "" : " desc"),
            $_POST['category'], $GLOBALS['gClientId'], $GLOBALS['gDefaultClientId']);
        if ($row = getNextRow($resultSet)) {
            $knowledgeBaseCategoryId = $row['knowledge_base_category_id'];
            $_POST['category'] = $row['knowledge_base_category_code'];
        } else {
        	$_POST['category'] = "";
		}
        echo $this->iPageData['content'];
        if (empty($_POST['id']) && empty($knowledgeBaseCategoryId)) {
            ?>
            <p>Invalid Knowledge Base Category</p>
            <?php
            echo $this->iPageData['after_form_content'];
            return true;
        }
        ?>
        <?php if (empty($_POST['id'])) { ?>
            <form id="_edit_form" action="<?= $GLOBALS['gLinkUrl'] ?>" method="POST">
                <input type="hidden" id="category" name="category" value="<?= $_POST['category'] ?>">
                <input type="hidden" id="id" name="id" value="<?= $_POST['id'] ?>">
                <div class="basic-form-line search-control">
                    <label>Search Text</label>
                    <input tabindex="20" type="text" id="search_text" name="search_text" value="<?= htmlText($_POST['search_text']) ?>">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
                <p class='search-control'>
                    <button id="search_button">Search Knowledgebase</button>
                </p>
            </form>
        <?php } ?>
        <?php
        if (empty($_POST['id'])) {
            $resultSet = executeQuery("select * from knowledge_base where knowledge_base_id in " .
                "(select knowledge_base_id from knowledge_base_category_links where knowledge_base_category_id = ?) " .
                (empty($_POST['search_text']) ? "" : "and (title_text like " . makeParameter("%" . $_POST['search_text'] . "%") . " or content like " . makeParameter("%" . $_POST['search_text'] . "%") . ") ") .
                "order by date_entered desc,knowledge_base_id desc limit 20", $knowledgeBaseCategoryId);
        } else {
            $resultSet = executeQuery("select * from knowledge_base where knowledge_base_id = ? and client_id = ?", $_POST['id'], $GLOBALS['gClientId']);
        }
        $lastDate = "";
        while ($row = getNextRow($resultSet)) {
            if ($lastDate != $row['date_entered']) {
                ?>
                <h2><?= date("m/d/Y", strtotime($row['date_entered'])) ?></h2>
                <?php
                $lastDate = $row['date_entered'];
            }
            ?>
            <div class='knowledge-base-entry'>
                <h3><?= htmlText($row['title_text']) ?></h3>
                <div class='knowledge-base-entry-content'>
                    <?= makeHtml($row['content']) ?>
                </div>
                <div class="knowledge-base-images-wrapper">
                    <?php
                    $fileSet = executeQuery("select * from knowledge_base_images where knowledge_base_id = ?", $row['knowledge_base_id']);
                    while ($fileRow = getNextRow($fileSet)) {
                        ?>
                        <div class="knowledge-base-image"><img alt="<?= $fileRow['description'] ?>" src="<?= getImageFilename($fileRow['image_id'],array("use_cdn"=>true)) ?>"</div>
                        <?php
                    }
                    ?>
                </div>
                <div class="knowledge-base-files-wrapper">
                    <?php
                    $fileSet = executeQuery("select * from knowledge_base_files where knowledge_base_id = ?", $row['knowledge_base_id']);
                    while ($fileRow = getNextRow($fileSet)) {
                        ?>
                        <div class="knowledge-base-file"><a href="/download.php?id=<?= $fileRow['file_id'] ?>"><?= htmlText($fileRow['description']) ?></a></div>
                        <?php
                    }
                    ?>
                </div>
            </div>
            <?php
        }
        echo $this->iPageData['after_form_content'];
        return true;
    }

    function onLoadJavascript() {
        ?>
        <script>
            $("#search_text").keyup(function (event) {
                if (event.which === 13 || event.which === 3) {
                    $("#search_button").trigger("click");
                }
                return false;
            });
            setTimeout(function () {
                $("#search_text").focus();
            }, 200);
        </script>
        <?php
    }

    function internalCSS() {
        ?>
        <style>
            div.knowledge-base-entry {
                margin-bottom: 40px;
            }

            #_main_content ul {
                list-style: disc;
                margin: 20px 40px;
            }

            #_main_content ol {
                list-style: decimal;
                margin: 20px 40px;
            }

            #_main_content ul ol, #_main_content ul ul, #_main_content ol ul, #_main_content ol ol {
                margin-bottom: 0;
            }

            #_main_content li {
                padding-bottom: 10px;
            }

            #_main_content ul li a {
                font-size: 1.2rem;
            }
        </style>
        <?php
    }
}

$pageObject = new DisplayKnowledgebasePage();
$pageObject->displayPage();
