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
%module:menu:menu_code%
%module:menu:menu_id:by_id%

Options:
by_id - if set, the menu is gotten by ID. This is useful if the menu is chosen as a page data
*/

class MenuPageModule extends PageModule {
	function massageParameters() {
		$this->iParameters['no_cache'] = true;
	}

	function createContent() {
		$menuCode = array_shift($this->iParameters);
		if ($this->iParameters[0] == "by_id") {
			echo getMenu($menuCode,$this->iParameters);
		} else {
			echo getMenuByCode($menuCode,$this->iParameters);
		}
	}
}
