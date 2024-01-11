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
Generates a list of manufacturer images suitable for a carousel or scrolling list. Only manufacturers with the given tag are listed.

%module:manufacturer_list:product_manufacturer_tag_code=premier_brand:wrapper_element_id=element_id%

Options:
manufacturer_website=true - default is to go to a list of manufacturer products on the dealer site. This will go to manufacturer website instead
select_limit=20 - limit to this number of products
random=true - randomly sort the manufacturers. Default sort is sort_order of product manufacturers.
alphabetical=true - alphabetically sort the manufacturers. Default sort is sort_order of product manufacturers.
product_category_code=XXXXX - limit products results to this category. Only valid if manufacturer_website is false
product_category_group_code=XXXXX - limit products results to this category group
product_department_code=XXXXX - limit products results to this department
image_code=XXXXX - use the alternate manufacturer image with code (if available)
use_name=true - use name when image doesn't exist
always_use_name=true - never use image... always use the manufacturer name
*/

class ManufacturerListPageModule extends PageModule {
	function createContent() {
		$wrapperElementId = $this->iParameters['wrapper_element_id'];
		if (empty($wrapperElementId)) {
			$wrapperElementId = "manufacturer_list_wrapper";
		}
		$selectLimit = $this->iParameters['select_limit'];
		$productManufacturerTagId = getFieldFromId("product_manufacturer_tag_id","product_manufacturer_tags","product_manufacturer_tag_code",$this->iParameters['product_manufacturer_tag_code']);
		$resultSet = executeQuery("select * from product_manufacturers join contacts using (contact_id) where inactive = 0 and internal_use_only = 0 and " .
            "product_manufacturer_id in (select product_manufacturer_id from product_manufacturer_tag_links where product_manufacturer_tag_id = ?) and " .
			"cannot_sell = 0 " . (empty($this->iParameters['use_name']) && empty($this->iParameters['always_use_name']) ? "and image_id is not null " : "") . (empty($this->iParameters['random']) ? (empty($this->iParameters['alphabetical']) ? "order by sort_order" : "order by description") : "order by rand()") . (empty($selectLimit) || !is_numeric($selectLimit) ? "" : " limit " . $selectLimit), $productManufacturerTagId);
		?>
        <div id="<?= $wrapperElementId ?>">
			<?php
			while ($row = getNextRow($resultSet)) {
			    if ($this->iParameters['manufacturer_website']) {
			        if (empty($row['web_page'])) {
			            continue;
                    }
				    $linkUrl = (substr($row['web_page'],0,4) == "http" || substr($row['web_page'],0,1) == "/" ? "" : "http://") . $row['web_page'];
			    } else {
			        if (empty($this->iParameters['product_category_code']) && empty($this->iParameters['product_category_group_code']) && empty($this->iParameters['product_department_code']) && !empty($row['link_name'])) {
				        $linkUrl = "/product-manufacturer/" . $row['link_name'];
			        } else {
			            $parameters = array();
			            $parameters['product_manufacturer_id'] = $row['product_manufacturer_id'];
				        if (!empty($this->iParameters['product_category_code'])) {
					        $parameters['product_category_code'] = (is_array($this->iParameters['product_category_code']) ? implode("|",$this->iParameters['product_category_code']) : $this->iParameters['product_category_code']);
				        }
				        if (!empty($this->iParameters['product_category_group_code'])) {
					        $parameters['product_category_group_code'] = (is_array($this->iParameters['product_category_group_code']) ? implode("|",$this->iParameters['product_category_group_code']) : $this->iParameters['product_category_group_code']);
				        }
				        if (!empty($this->iParameters['product_department_code'])) {
					        $parameters['product_department_code'] = (is_array($this->iParameters['product_department_code']) ? implode("|",$this->iParameters['product_department_code']) : $this->iParameters['product_department_code']);
				        }
				        ksort($parameters);
				        $parameterString = "";
				        foreach ($parameters as $parameterName => $parameterValue) {
					        $parameterString .= (empty($parameterString) ? "" : ",") . $parameterName . "=" . $parameterValue;
				        }
                        $manufacturerLinkName = $row['link_name'];
                        if (empty($manufacturerLinkName)) {
                            $manufacturerLinkName = makeCode($row['description'], array("use_dash" => true, "lowercase" => true));
                        }
                        $linkName = $manufacturerLinkName;
                        if (!empty($this->iParameters['product_category_code'])) {
                            if (is_array($this->iParameters['product_category_code'])) {
	                            $linkCodes = $this->iParameters['product_category_code'];
                            } else {
	                            $linkCodes = $this->iParameters['product_category_code'];
                            }
                            foreach ($linkCodes as $thisCode) {
	                            $thisLinkName = getFieldFromId("link_name", "product_categories", "product_category_code", $thisCode);
	                            if (empty($thisLinkName)) {
		                            $thisLinkName = makeCode($thisCode, array("use_dash" => true, "lowercase" => true));
	                            }
	                            $linkName .= "-category-" . $thisLinkName;
                            }
                        }
                        if (!empty($this->iParameters['product_category_group_code'])) {
	                        if (is_array($this->iParameters['product_category_group_code'])) {
		                        $linkCodes = $this->iParameters['product_category_group_code'];
	                        } else {
		                        $linkCodes = $this->iParameters['product_category_group_code'];
	                        }
	                        foreach ($linkCodes as $thisCode) {
		                        $thisLinkName = getFieldFromId("link_name", "product_category_groups", "product_category_group_code", $thisCode);
		                        if (empty($thisLinkName)) {
			                        $thisLinkName = makeCode($thisCode, array("use_dash" => true, "lowercase" => true));
		                        }
		                        $linkName .= "-category-group-" . $thisLinkName;
	                        }
                        }
                        if (!empty($this->iParameters['product_department_code'])) {
	                        if (is_array($this->iParameters['product_department_code'])) {
		                        $linkCodes = $this->iParameters['product_department_code'];
	                        } else {
		                        $linkCodes = $this->iParameters['product_department_code'];
	                        }
	                        foreach ($linkCodes as $thisCode) {
		                        $thisLinkName = getFieldFromId("link_name", "product_departments", "product_department_code", $thisCode);
		                        if (empty($thisLinkName)) {
			                        $thisLinkName = makeCode($thisCode, array("use_dash" => true, "lowercase" => true));
		                        }
		                        $linkName .= "-department-" . $thisLinkName;
	                        }
                        }
                        $searchParameterGroupId = "";
				        $linkSet = executeQuery("select search_parameter_group_id,(select group_concat(concat_ws('=',parameter_name,parameter_value)) from search_parameter_group_details where " .
                            "search_parameter_group_id = search_parameter_groups.search_parameter_group_id order by parameter_name) as parameters from search_parameter_groups where client_id = ? having parameters = ?",$GLOBALS['gClientId'],$parameterString);
                        if ($linkRow = getNextRow($linkSet)) {
                            $searchParameterGroupId = $linkRow['search_parameter_group_id'];
                        }
                        if (empty($searchParameterGroupId)) {
	                        $linkNumber = 0;
	                        do {
		                        $linkNumber++;
		                        $searchParameterGroupId = getFieldFromId("search_parameter_group_id", "search_parameter_groups", "link_name", $linkName . ($linkNumber <= 1 ? "" : "-" . $linkNumber));
	                        } while (!empty($searchParameterGroupId));
	                        $linkName = $linkName . ($linkNumber <= 1 ? "" : "-" . $linkNumber);
	                        $insertSet = executeQuery("insert into search_parameter_groups (client_id,description,link_name) values (?,'Manufacturer List Page Module Link',?)", $GLOBALS['gClientId'], $linkName);
	                        $searchParameterGroupId = $insertSet['insert_id'];
	                        foreach ($parameters as $parameterName => $parameterValue) {
		                        executeQuery("insert into search_parameter_group_details (search_parameter_group_id,parameter_name,parameter_value) values (?,?,?)",$searchParameterGroupId,$parameterName,$parameterValue);
	                        }
                        } else {
	                        $linkName = getFieldFromId("link_name", "search_parameter_groups", "search_parameter_group_id", $searchParameterGroupId);
                        }
				        $linkUrl = "/product-search" . (substr($linkName,0,1) == "/" ? "" : "/") . $linkName;
			        }
                }
			    if (!empty($this->iParameters['image_code'])) {
			        $alternateImageId = getFieldFromId("image_id","product_manufacturer_images","product_manufacturer_id",$row['product_manufacturer_id'],
                        "image_code = ?",$this->iParameters['image_code']);
			        if (!empty($alternateImageId)) {
			            $row['image_id'] = $alternateImageId;
                    }
                }
                if (empty($row['image_id']) || !empty($this->iParameters['always_use_name'])) {
                    $linkName = $row['description'];
                } else {
                    $linkName = '<img alt="Manufacturer Logo" src="' . getImageFilename($row['image_id'],array("use_cdn"=>true)) . '">';
                }
				?>
                <div class='manufacturer-list-item clickable' data-script_filename="<?= $linkUrl ?>"><a href='<?= $linkUrl ?>'><?= $linkName ?></a></div>
				<?php
			}
			?>
        </div>
		<?php
	}
}
