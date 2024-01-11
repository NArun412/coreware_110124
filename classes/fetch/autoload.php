<?php

/*
 * This file is part of the Fetch package.
 *
 * (c) Robert Hafner <tedivm@tedivm.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

spl_autoload_register(function ($className) {
	if (file_exists($GLOBALS['gDocumentRoot'] . "/classes/fetch/src/class." . strtolower($className) . ".php")) {
		require_once $GLOBALS['gDocumentRoot'] . "/classes/fetch/src/class." . strtolower($className) . ".php";
		return true;
    }
});
