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

$GLOBALS['gPageCode'] = "MENUDISPLAY";
require_once "shared/startup.inc";

class ThisPage extends Page {

	function setup() {
		if (empty($_GET['menu']) && !empty($_GET['code'])) {
			$_GET['menu'] = $_GET['code'];
		}
		$menuDescription = getFieldFromId("description","menus","menu_code",$_GET['menu']);
		$GLOBALS['gPageRow']['description'] = $menuDescription;
	}

	function mainContent() {
		if (empty($_GET['menu']) && !empty($_GET['code'])) {
			$_GET['menu'] = $_GET['code'];
		}
		$menuId = getFieldFromId("menu_id","menus","menu_code",$_GET['menu']);
		if (!empty($menuId)) {
			$menuData = getMenu($menuId,array("raw_data"=>true));

?>
<div id="_menu_column">
<?php
			if (array_key_exists("menu_items",$menuData)) {
				$menuItems = array();
				foreach ($menuData['menu_items'] as $index => $menuItem) {
					if (!empty($menuItem['href'])) {
						$menuItems[] = $menuItem;
					}
				}
?>
<ul id="_page_menu">
<?php
				foreach ($menuItems as $menuItemRow) {
?>
	<li class='menu-item' data-script_filename='<?= $menuItemRow['href'] ?>' data-separate_window='<?= ($menuItemRow['separate_window'] == 1 ? "YES" : "") ?>'><a href="<?= $menuItemRow['href'] ?>"><?= $menuItemRow['link_title'] ?></a></li>
<?php
				}
?>
</ul>
</div>
<?php
			}
		}
		return true;
	}

	function internalCSS() {
?>
#_page_menu { column-count: 3; column-gap: 40px; }
#_page_menu li { line-height: 2.5; }
@media only screen and (max-width: 850px) {
	#_page_menu { column-count: 2; }
}
@media only screen and (max-width: 400px) {
	#_page_menu { column-count: 1; }
}
<?php
	}
}

$pageObject = new ThisPage();
$pageObject->displayPage();
