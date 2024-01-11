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
%module:product_menu:product_departments:submenu=product_category_groups%
%module:product_menu:product_departments:submenu=product_categories%
%module:product_menu:product_category_groups:submenu=product_categories%
%module:product_menu:product_departments%
%module:product_menu:product_category_groups%
%module:product_menu:product_categories%
%module:product_menu:product_manufacturers%
%module:product_menu:product_types%
%module:product_menu:product_tags%
%module:product_menu:literal:text=Literal Text:submenu=product_departments%
%module:product_menu:literal:text=Literal Text:submenu=product_category_groups%
%module:product_menu:literal:text=Literal Text:submenu=product_categories%
%module:product_menu:literal:text=Literal Text:submenu=product_manufacturers%
%module:product_menu:literal:text=Literal Text:submenu=product_types%
%module:product_menu:literal:text=Literal Text:submenu=product_tags%
%module:product_menu:product_departments:submenu=product_category_groups:tertiary_menu=product_categories:simple_tertiary_menu%

Options:

element_id=xxxxxx - element ID of top level UL
classes=xxxxxx - classes of top level UL
dropdown=true - use a select drop down instead of UL structure. Will only display the top level
top_level_text_only=true - this will make the top level, typically product departments, not clickable, but text only
allow_department_links=true - this will add department links, which are excluded by default
*/

class ProductMenuPageModule extends PageModule {
	function createContent() {
		ksort($this->iParameters);
		$cacheKey = md5(jsonEncode($this->iParameters));
		$cachedMenu = getCachedData("product_menu_page_module", $cacheKey);
		if (!empty($cachedMenu)) {
			echo $cachedMenu;
			return;
		}
		ob_start();
		$topLevelMenu = array_shift($this->iParameters);
		$urlAliasTypeCodes = array();

		$urlAliasTypeCode = getUrlAliasTypeCode("product_departments", "product_department_id");
		if (!empty($urlAliasTypeCode)) {
			$urlAliasTypeCodes['product_departments'] = $urlAliasTypeCode;
		}

		$urlAliasTypeCode = getUrlAliasTypeCode("product_category_groups", "product_category_group_id");
		if (!empty($urlAliasTypeCode)) {
			$urlAliasTypeCodes['product_category_groups'] = $urlAliasTypeCode;
		}

		$urlAliasTypeCode = getUrlAliasTypeCode("product_categories", "product_category_id");
		if (!empty($urlAliasTypeCode)) {
			$urlAliasTypeCodes['product_categories'] = $urlAliasTypeCode;
		}

		$urlAliasTypeCode = getUrlAliasTypeCode("product_manufacturers", "product_manufacturer_id");
		if (!empty($urlAliasTypeCode)) {
			$urlAliasTypeCodes['product_manufacturers'] = $urlAliasTypeCode;
		}

		$urlAliasTypeCode = getUrlAliasTypeCode("product_tags", "product_tag_id");
		if (!empty($urlAliasTypeCode)) {
			$urlAliasTypeCodes['product_tags'] = $urlAliasTypeCode;
		}

		$urlAliasTypeCode = getUrlAliasTypeCode("product_types", "product_type_id");
		if (!empty($urlAliasTypeCode)) {
			$urlAliasTypeCodes['product_types'] = $urlAliasTypeCode;
		}

		$literalText = false;
		$menuArray = array();
		if ($topLevelMenu == "literal") {
			$literalText = $this->iParameters['text'];
			$topLevelMenu = $this->iParameters['submenu'];
			$this->iParameters['submenu'] = "";
		}
		switch ($topLevelMenu) {
			case "product_departments":
				$resultSet = executeQuery("select * from product_departments where client_id = ? and inactive = 0 and internal_use_only = 0 order by sort_order,description", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					if ($this->iParameters['top_level_text_only']) {
						$scriptFilename = "";
					} else {
						$scriptFilename = (empty($row['link_name']) || empty($urlAliasTypeCodes['product_departments']) ? "/product-search-results?product_department_id=" . $row['product_department_id'] : "/" . $urlAliasTypeCodes['product_departments'] . "/" . $row['link_name']);
					}
					$thisMenu = array("department" => true, "description" => $row['description'], "link_url" => $scriptFilename, "image_id" => $row['image_id']);
					$submenus = array();
					switch ($this->iParameters['submenu']) {
						case "product_category_groups":
							$productCategoryGroupLinks = array();
							if ($this->iParameters['tertiary_menu'] == "product_categories") {
								$tertiarySet = executeQuery("select product_category_group_id,link_name,product_categories.product_category_id,description from product_category_group_links join product_categories using (product_category_id) where " .
								    "inactive = 0 and internal_use_only = 0 and product_categories.product_category_id in (select product_category_id from product_category_links where product_id in (select product_id from products where inactive = 0 and " .
								    "internal_use_only = 0 and product_id not in (select product_id from product_category_links where product_category_id in (select product_category_id from product_categories where product_category_code in " .
								    "('INACTIVE','INTERNAL_USE_ONLY','DISCONTINUED'))))) and client_id = ? order by product_category_group_id,sequence_number",$GLOBALS['gClientId']);
								while ($tertiaryRow = getNextRow($tertiarySet)) {
									if (!array_key_exists($tertiaryRow['product_category_group_id'], $productCategoryGroupLinks)) {
										$productCategoryGroupLinks[$tertiaryRow['product_category_group_id']] = array();
									}
									$productCategoryGroupLinks[$tertiaryRow['product_category_group_id']][] = $tertiaryRow;
								}
							}
							$categoryGroupSet = executeQuery("select * from product_category_groups join product_category_group_departments using (product_category_group_id) where inactive = 0 and internal_use_only = 0 and client_id = ? and " .
								"product_department_id = ?" . ($GLOBALS['gDevelopmentServer'] ? "" : " and product_category_group_id in (select product_category_group_id from product_category_group_links where product_category_id in " .
									"(select product_category_id from product_categories where inactive = 0 and product_category_id in (select product_category_id from product_category_links)))") . " order by sequence_number,description", $GLOBALS['gClientId'], $row['product_department_id']);
							while ($categoryGroupRow = getNextRow($categoryGroupSet)) {
								$scriptFilename = (empty($categoryGroupRow['link_name']) || empty($urlAliasTypeCodes['product_category_groups']) ? "/product-search-results?product_category_group_id=" . $categoryGroupRow['product_category_group_id'] : "/" . $urlAliasTypeCodes['product_category_groups'] . "/" . $categoryGroupRow['link_name']);
								$tertiaryMenu = array();
								if ($this->iParameters['tertiary_menu'] == "product_categories") {
									if (array_key_exists($categoryGroupRow['product_category_group_id'], $productCategoryGroupLinks)) {
										foreach ($productCategoryGroupLinks[$categoryGroupRow['product_category_group_id']] as $tertiaryRow) {
											$tertiaryScriptFilename = (empty($tertiaryRow['link_name']) || empty($urlAliasTypeCodes['product_categories']) ? "/product-search-results?product_category_id=" . $tertiaryRow['product_category_id'] : "/" . $urlAliasTypeCodes['product_categories'] . "/" . $tertiaryRow['link_name']);
											$tertiaryMenu[] = array("description" => $tertiaryRow['description'], "link_url" => $tertiaryScriptFilename);
										}
									}
								}
								$submenus[] = array("description" => $categoryGroupRow['description'], "link_url" => $scriptFilename, "tertiary_menu" => $tertiaryMenu);
							}
							break;
						case "product_categories":
							$categorySet = executeQuery("select * from product_categories join product_category_departments using (product_category_id) where inactive = 0 and internal_use_only = 0 and client_id = ? and " .
								"product_department_id = ? and product_category_id in (select product_category_id from product_category_links) order by sequence_number,description", $GLOBALS['gClientId'], $row['product_department_id']);
							while ($categoryRow = getNextRow($categorySet)) {
								$scriptFilename = (empty($categoryRow['link_name']) || empty($urlAliasTypeCodes['product_categories']) ? "/product-search-results?product_category_id=" . $categoryRow['product_category_id'] : "/" . $urlAliasTypeCodes['product_categories'] . "/" . $categoryRow['link_name']);
								$submenus[] = array("description" => $categoryRow['description'], "link_url" => $scriptFilename);
							}
							break;
					}
					if (!empty($submenus)) {
						$thisMenu['submenu'] = $submenus;
					}
					$menuArray[] = $thisMenu;
				}
				break;
			case "product_category_groups":
				$resultSet = executeQuery("select * from product_category_groups where client_id = ? and inactive = 0 and internal_use_only = 0 and product_category_group_id in (select product_category_group_id from product_category_group_links where product_category_id in " .
					"(select product_category_id from product_categories where inactive = 0 and internal_use_only = 0 and product_category_id in (select product_category_id from product_category_links))) order by sort_order,description", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					if ($this->iParameters['top_level_text_only']) {
						$scriptFilename = "";
					} else {
						$scriptFilename = (empty($row['link_name']) || empty($urlAliasTypeCodes['product_category_groups']) ? "/product-search-results?product_category_group_id=" . $row['product_category_group_id'] : "/" . $urlAliasTypeCodes['product_category_groups'] . "/" . $row['link_name']);
					}
					$thisMenu = array("description" => $row['description'], "link_url" => $scriptFilename);
					$submenus = array();
					switch ($this->iParameters['submenu']) {
						case "product_categories":
							$categorySet = executeQuery("select * from product_categories join product_category_group_links using (product_category_id) where inactive = 0 and internal_use_only = 0 and client_id = ? and " .
								"product_category_group_id = ? order by sequence_number,description", $GLOBALS['gClientId'], $row['product_category_group_id']);
							while ($categoryRow = getNextRow($categorySet)) {
								$scriptFilename = (empty($categoryRow['link_name']) || empty($urlAliasTypeCodes['product_categories']) ? "/product-search-results?product_category_id=" . $categoryRow['product_category_id'] : "/" . $urlAliasTypeCodes['product_categories'] . "/" . $categoryRow['link_name']);
								$submenus[] = array("description" => $categoryRow['description'], "link_url" => $scriptFilename);
							}
							break;
					}
					if (!empty($submenus)) {
						$thisMenu['submenu'] = $submenus;
					}
					$menuArray[] = $thisMenu;
				}
				break;
			case "product_categories":
				$resultSet = executeQuery("select * from product_categories where inactive = 0 and internal_use_only = 0 and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$scriptFilename = (empty($row['link_name']) || empty($urlAliasTypeCodes['product_categories']) ? "/product-search-results?product_category_id=" . $row['product_category_id'] : "/" . $urlAliasTypeCodes['product_categories'] . "/" . $row['link_name']);
					$thisMenu = array("description" => $row['description'], "link_url" => $scriptFilename);
					$menuArray[] = $thisMenu;
				}
				break;
			case "product_manufacturers":
				$resultSet = executeQuery("select * from product_manufacturers where inactive = 0 and internal_use_only = 0 and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$scriptFilename = (empty($row['link_name']) || empty($urlAliasTypeCodes['product_manufacturers']) ? "/product-search-results?product_manufacturer_id=" . $row['product_manufacturer_id'] : "/" . $urlAliasTypeCodes['product_manufacturers'] . "/" . $row['link_name']);
					$thisMenu = array("description" => $row['description'], "link_url" => $scriptFilename);
					$menuArray[] = $thisMenu;
				}
				break;
			case "product_types":
				$resultSet = executeQuery("select * from product_types where inactive = 0 and internal_use_only = 0 and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$scriptFilename = (empty($row['link_name']) || empty($urlAliasTypeCodes['product_types']) ? "/product-search-results?product_type_id=" . $row['product_type_id'] : "/" . $urlAliasTypeCodes['product_types'] . "/" . $row['link_name']);
					$thisMenu = array("description" => $row['description'], "link_url" => $scriptFilename);
					$menuArray[] = $thisMenu;
				}
				break;
			case "product_tags":
				$resultSet = executeQuery("select * from product_tags where inactive = 0 and internal_use_only = 0 and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$scriptFilename = (empty($row['link_name']) || empty($urlAliasTypeCodes['product_tags']) ? "/product-search-results?product_tag_id=" . $row['product_tag_id'] : "/" . $urlAliasTypeCodes['product_tags'] . "/" . $row['link_name']);
					$thisMenu = array("description" => $row['description'], "link_url" => $scriptFilename);
					$menuArray[] = $thisMenu;
				}
				break;
		}
		if (!empty($this->iParameters['include_top_level_subpointer'])) {
			if (empty($this->iParameters['top_level_subpointer'])) {
				$this->iParameters['top_level_subpointer'] = "<span class='submenu-pointer fas fa-chevron-right'></span>";
			}
		}
		if ($this->iParameters['dropdown']) {
			?>
            <select<?= (empty($this->iParameters['element_id']) ? "" : " id='" . $this->iParameters['element_id'] . "'") ?><?= (empty($this->iParameters['classes']) ? "" : " class='" . $this->iParameters['classes'] . "'") ?>>
				<?php if (!empty($literalText)) { ?>
                    <option value=""><?= htmlText($literalText) ?></option>
				<?php } ?>
				<?php foreach ($menuArray as $index => $thisMenu) { ?>
                    <option data-script_filename="<?= $thisMenu['link_url'] ?>" value="<?= $index ?>"><?= htmlText($thisMenu['description']) ?></option>
				<?php } ?>
            </select>
			<?php
		} else {
			if (!empty($literalText)) {
				$menuArray = array(array("description" => $literalText, "link_url" => "", "submenu" => $menuArray));
			}
			?>
            <ul<?= (empty($this->iParameters['element_id']) ? "" : " id='" . $this->iParameters['element_id'] . "'") ?><?= (empty($this->iParameters['classes']) ? "" : " class='" . $this->iParameters['classes'] . "'") ?>>
				<?php
				foreach ($menuArray as $thisMenu) {
					$notClickable = false;
					$productCount = 0;
                    if (!empty($this->iParameters['allow_department_links'])) {
                        $productSet = executeQuery("select count(*) from products where client_id = ?",$GLOBALS['gClientId']);
                        if ($productRow = getNextRow($productSet)) {
	                        $productCount = $productRow['count(*)'];
                        }
                    }
					if ((($GLOBALS['gClientCount'] > 20 && $productCount > 2000) || empty($this->iParameters['allow_department_links'])) && !empty($thisMenu['department']) && !empty($thisMenu['submenu'])) {
						$notClickable = true;
					}
					?>
                    <li class="menu-item" data-script_filename="<?= ($notClickable ? "" : $thisMenu['link_url']) ?>">
						<?php if (!empty($thisMenu['link_url'])) { ?>
                        <a class="menu-item-link<?= ($notClickable ? " not-clickable" : "") ?>" href="<?= $thisMenu['link_url'] ?>"<?= ($notClickable ? " rel='nofollow' onclick='return false;'" : "") ?>>
							<?php } ?>
                            <span class="menu-text"><?= htmlText($thisMenu['description']) ?></span>
							<?php if (!empty($thisMenu['link_url'])) { ?>
                        </a>
					<?php } ?>
						<?php
						if (!empty($thisMenu['submenu'])) {
							echo $this->iParameters['top_level_subpointer'];
							if (!empty($this->iParameters['tertiary_menu']) && !array_key_exists("simple_tertiary_menu", $this->iParameters)) {
								?>
                                <div class="menu-item-div">
                                <p class="menu-item-div-header"><?= htmlText($thisMenu['description']) ?></p>
								<?php
								if (!empty($thisMenu['image_id'])) {
									$imageLink = getFieldFromId("link_url", "images", "image_id", $thisMenu['image_id']);
									if (!empty($imageLink)) {
										?>
                                        <a href='<?= $imageLink ?>'><img class='menu-item-image' src='<?= getImageFilename($thisMenu['image_id'],array("use_cdn"=>true)) ?>'></a>
										<?php
									} else {
										?>
                                        <img class="menu-item-image" src="<?= getImageFilename($thisMenu['image_id'],array("use_cdn"=>true)) ?>">
									<?php } ?>
								<?php } ?>
								<?php
							}
							?>
                            <ul>
								<?php
								foreach ($thisMenu['submenu'] as $thisSubmenu) {
									?>
                                    <li class="menu-item" data-script_filename="<?= $thisSubmenu['link_url'] ?>">
										<?php if (!empty($thisSubmenu['link_url'])) { ?>
                                        <a class="menu-item-link" href="<?= $thisSubmenu['link_url'] ?>">
											<?php } ?>
                                            <span class="menu-text"><?= htmlText($thisSubmenu['description']) ?></span>
											<?php if (!empty($thisSubmenu['link_url'])) { ?>
                                        </a>
									<?php } ?>
										<?php if (!empty($thisSubmenu['tertiary_menu'])) { ?>
                                            <ul>
												<?php foreach ($thisSubmenu['tertiary_menu'] as $thisTertiaryMenu) { ?>
                                                    <li class="menu-item" data-script_filename="<?= $thisTertiaryMenu['link_url'] ?>">
														<?php if (!empty($thisTertiaryMenu['link_url'])) { ?>
                                                        <a class="menu-item-link" href="<?= $thisTertiaryMenu['link_url'] ?>">
															<?php } ?>
                                                            <span class="menu-text"><?= htmlText($thisTertiaryMenu['description']) ?></span>
															<?php if (!empty($thisTertiaryMenu['link_url'])) { ?>
                                                        </a>
													<?php } ?>
                                                    </li>
												<?php } ?>
                                            </ul>
										<?php } ?>
                                    </li>
									<?php
								}
								?>
                            </ul>
							<?php
							if (!empty($this->iParameters['tertiary_menu']) && !array_key_exists("simple_tertiary_menu", $this->iParameters)) {
								?>
                                </div>
								<?php
							}
						}
						?>
                    </li>
					<?php
				}
				?>
            </ul>
			<?php
		}
		$fullMenu = ob_get_clean();
		setCachedData("product_menu_page_module", $cacheKey, $fullMenu);
		echo $fullMenu;
	}
}
