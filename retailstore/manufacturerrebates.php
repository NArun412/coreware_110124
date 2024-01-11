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

$GLOBALS['gPageCode'] = "RETAILSTOREPRODUCTMANUFACTURERREBATES";
$GLOBALS['gCacheProhibited'] = true;
require_once "shared/startup.inc";

class ThisPage extends Page {

    var $iProductManufacturerRebateId = "";

    function setup() {
        $this->iProductManufacturerRebateId = getFieldFromId("product_manufacturer_rebate_id", "product_manufacturer_rebates", "product_manufacturer_rebate_id",
            $_GET['id'], "start_date <= current_date and expiration_date >= current_date");
    }

    function massageDataSource() {
        $this->iDataSource->setFilterWhere("product_manufacturer_id in (select product_manufacturer_id from product_manufacturers where client_id = " . $GLOBALS['gClientId'] . ")");
    }

    function onLoadJavascript() {
        ?>
        <script>
            $("#filter_text").keyup(function(event) {
                if (empty($(this).val())) {
                    $(".manufacturer-rebate").removeClass("hidden");
                } else {
                    const filterText = $(this).val().toLowerCase();
                    $(".manufacturer-rebate").addClass("hidden");
                    $(".manufacturer-rebate").each(function() {
                        if ($(this).find(".description").html().toLowerCase().indexOf(filterText) >= 0) {
                            $(this).removeClass("hidden");
                        }
                    });
                }
            });
        </script>
        <?php
    }

    function javascript() {
        ?>
        <script>
        </script>
        <?php
    }

    function internalCSS() {
        ?>
        <style>
            #product_manufacturer_rebate_wrapper {
                max-width: 1000px;
                margin: 0 auto;
                display: flex;
                flex-wrap: wrap;
            }

            .manufacturer-rebate {
                width: 350px;
                padding: 40px;
                position: relative;
            }

            .manufacturer-rebate img {
                max-width: 100%;
                margin-bottom: 20px;
            }

            .manufacturer-rebate .description {
                font-size: 1.4rem;
                text-align: center;
            }

            .rebate-expiration {
                text-align: center;
            }

            .manufacturer-rebate-details {
                width: 100%;
                position: relative;
            }

            .manufacturer-rebate-details img {
                max-width: 100%;
            }

            #click_paragraph {
                text-align: center;
            }

            #filter_text {
                width: 300px;
            }

            #_return_list {
                font-size: .8rem;
                margin: 0;
                padding: 0;
                position: absolute;
                top: 20px;
                right: 0;
            }
        </style>
        <?php
    }

    function mainContent() {
        echo $this->iPageData['content'];
        if (empty($this->iProductManufacturerRebateId)) {
            ?>
            <p id="click_paragraph">Click image for details.</p>
            <p id="click_paragraph"><input type='text' id='filter_text' placeholder='Filter Rebates'></p>
        <?php } ?>
        <div id="product_manufacturer_rebate_wrapper">
            <?php
            if (empty($this->iProductManufacturerRebateId)) {
                $resultSet = executeQuery("select * from product_manufacturers join product_manufacturer_rebates using (product_manufacturer_id) where " .
                    "client_id = ? and start_date <= current_date and expiration_date >= current_date order by expiration_date", $GLOBALS['gClientId']);
                while ($row = getNextRow($resultSet)) {
                    ?>
                    <div class="manufacturer-rebate">
                        <a href="<?php echo $GLOBALS['gLinkUrl'] ?>?id=<?= $row['product_manufacturer_rebate_id'] ?>">
                            <?php if (!empty($row['image_id'])) { ?>
                                <img src="<?= getImageFilename($row['image_id'],array("use_cdn"=>true)) ?>">
                            <?php } ?>
                            <div class="description"><?= htmlText($row['description']) ?></div>
                            <p class='rebate-expiration'>Expires <?= date("F j, Y",strtotime($row['expiration_date'])) ?></p>
                        </a>
                        <div class='clear-div'></div>
                    </div>
                    <?php
                }
            } else {
                $resultSet = executeQuery("select *,product_manufacturers.description as manufacturer_description," .
                    "(select search_group_code from search_groups where search_group_id = product_manufacturer_rebates.search_group_id) as search_group_code from product_manufacturers join product_manufacturer_rebates using (product_manufacturer_id) where " .
                    "client_id = ? and product_manufacturer_rebate_id = ? and start_date <= current_date and expiration_date >= current_date order by expiration_date",
                    $GLOBALS['gClientId'], $this->iProductManufacturerRebateId);
                if ($row = getNextRow($resultSet)) {
                    ?>
                    <div class="manufacturer-rebate-details">
                        <div id="_return_list"><a href='<?= $GLOBALS['gLinkUrl'] ?>'>return to list</a></div>
                        <h2>Rebate offer from <?= $row['manufacturer_description'] ?></h2>
                        <h3><?= $row['description'] ?></h3>
                        <p><img src="<?= getImageFilename($row['image_id'],array("use_cdn"=>true)) ?>"></p>
                        <?= makeHtml($row['detailed_description']) ?>
                        <p>Rebate available from <?= date("m/d/Y", strtotime($row['start_date'])) ?> through <?= date("m/d/Y", strtotime($row['expiration_date'])) ?></p>
                        <?php if (!empty($row['link_url'])) { ?>
                            <p>Manufacturer Link: <a href="<?= (substr($row['link_url'], 0, 4) == "http" ? "" : "http://") . $row['link_url'] ?>">click here</a></p>
                        <?php } ?>
                        <?php if (!empty($row['amount'])) { ?>
                            <p>Estimated Rebate Value: <?= number_format($row['amount'], 2, '.', ',') ?></p>
                        <?php } ?>
                        <?php if (!empty($row['file_id'])) { ?>
                            <p><a href="/download.php?id=<?= $row['file_id'] ?>">Download Rebate Form</a></p>
                        <?php } ?>
                        <?php if (!empty($row['search_group_code'])) { ?>
                            <p><a target="_blank" href="/product-search-results?search_group=<?= $row['search_group_code'] ?>">View products related to this rebate</a></p>
                        <?php } ?>
                        <div class='clear-div'></div>
                    </div>
                    <?php
                }
            }
            ?>
        </div>
        <?php
        echo $this->iPageData['after_form_content'];
        return true;
    }
}

$pageObject = new ThisPage("product_manufacturer_rebates");
$pageObject->displayPage();
