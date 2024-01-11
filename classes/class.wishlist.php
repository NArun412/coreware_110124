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

class WishList {

	private $iWishListId = "";
	private $iUserId = "";
	private $iWishList = array();
	private $iWishListItems = array();
	private $iErrorMessage = "";

/**
 * Construct - When creating the class, you must set the client for the wish list.
 * You could also set the user to whom the wish list belongs
 * @param
 *	$userId - the id of the user who owns the wish list
 * @return
 *	false if anything goes wrong
 */
	function __construct($userId = "",$wishListId = "") {
		if (empty($userId)) {
			$this->iUserId = $GLOBALS['gUserId'];
		} else {
			$this->iUserId = getFieldFromId("user_id","users","user_id",$userId);
		}
		if (empty($this->iUserId)) {
			throw new Exception('Invalid User ID');
		}
		$this->iWishListId = $wishListId;
		if (!empty($this->iWishListId)) {
			$this->iWishListId = getFieldFromId("wish_list_id","wish_lists","wish_list_id",$wishListId,"user_id = ?",$this->iUserId);
		}
		if (empty($this->iWishListId)) {
			$this->iWishListId = getFieldFromId("wish_list_id","wish_lists","user_id",$this->iUserId);
		}
		if (!$this->loadWishList()) {
			throw new Exception('Unable to create Wish List');
		}
	}

	function setDescription($description) {
	    $dataTable = new DataTable('wish_lists');
	    $dataTable->setSaveOnlyPresent(true);
		if (!empty($description)) {
			$this->iWishList['description'] = $description;
			$dataTable->saveRecord(array('primary_id'=>$this->iWishListId, "name_values"=>array("description"=>$description)));
		}
	}

	function getDescription() {
		return $this->iWishList['description'];
	}

	function loadWishList() {
		if (!empty($this->iWishListId)) {
			$resultSet = executeQuery("select * from wish_lists where wish_list_id = ?",$this->iWishListId);
			if ($row = getNextRow($resultSet)) {
				$this->iWishList = $row;
				$this->iWishListId = $row['wish_list_id'];
				$this->iWishListItems = array();
				$resultSet = executeQuery("select * from wish_list_items where wish_list_id = ?",$this->iWishListId);
				while ($row = getNextRow($resultSet)) {
					$this->iWishListItems[$row['wish_list_item_id']] = $row;
				}
			} else {
				$this->iWishListId = "";
			}
		}
		$dataTable = new DataTable('wish_lists');
		$dataTable->setSaveOnlyPresent(true);
		if (empty($this->iWishListId)) {
		    $wishlistId = $dataTable->saveRecord(array("name_values"=>array("user_id"=>$this->iUserId,"description"=>"Wish List","date_created"=>date("Y-m-d"))));
            if(!empty($dataTable->getErrorMessage())) {
				return false;
			} else {
				$this->iWishListId = $wishlistId; //$resultSet['insert_id'];
				$this->iWishList = array("wish_list_id"=>$this->iWishListId,"user_id"=>$this->iUserId,"description"=>"Wish List","date_created"=>date("Y-m-d"));
				$this->iWishListItems = array();
			}
		}
		return true;
	}

/**
 * getUser - return the user who owns this wish list.
 * @param
 *	none
 * @return
 *	user ID
 */
	function getUser() {
		return $this->iUserId;
	}

/**
 * addItem - add this product to the wish list
 * @param
 *	product ID
 *	priority
 * @return
 *	none
 */
	function addItem($parameters) {
		$productId = getFieldFromId("product_id","products","product_id",$parameters['product_id'],"inactive = 0 and client_id = ?",$GLOBALS['gClientId']);
		if (empty($productId)) {
			$this->iErrorMessage = "Invalid product";
			return false;
		}
		if (!is_numeric($parameters['priority']) || empty($parameters['priority'])) {
			$parameters['priority'] = 1;
		}
		if (empty($parameters['notify_when_in_stock'])) {
			$parameters['notify_when_in_stock'] = 0;
		} else {
			$parameters['notify_when_in_stock'] = 1;
		}
		if (!empty($parameters['notify_when_in_stock'])) {
			$productCatalog = new ProductCatalog();
			$totalInventory = $productCatalog->getInventoryCounts(true,$productId);
			if ($totalInventory[$productId] > 0) {
				$parameters['notify_when_in_stock'] = 0;
			}
		}
		$wishListItemId = "";
		foreach ($this->iWishListItems as $thisItem) {
			if ($productId != $thisItem['product_id']) {
				continue;
			}
			$wishListItemId = $thisItem['wish_list_item_id'];
			break;
		}
		$dataTable = new DataTable('wish_list_items');
		$dataTable->setSaveOnlyPresent(true);
		if (empty($wishListItemId)) {
            $wishListItemId = $dataTable->saveRecord(array("name_values" => array("wish_list_id" => $this->iWishListId, "product_id" => $productId,
                "priority" => $parameters['priority'], "notify_when_in_stock"=>$parameters['notify_when_in_stock'])));
			$this->iWishListItems[$wishListItemId] = array("wish_list_item_id"=>$wishListItemId,
				"product_id"=>$productId,"priority"=>$parameters['priority'],"notify_when_in_stock"=>$parameters['notify_when_in_stock']);
		} else {
		    $dataTable->saveRecord(array("primary_id"=>$wishListItemId, "name_values"=>array("priority" => $parameters['priority'],
                "notify_when_in_stock"=>$parameters['notify_when_in_stock'])));
		}
		return true;
	}

/**
 * removeItem - remove this wish list item from the wish list
 * @param
 *	Wish List Item ID
 * @return
 *	none
 */
	function removeItem($wishListItemId) {
	    $dataTable = new DataTable("wish_list_items");
	    $dataTable->deleteRecord(array("primary_id"=>$wishListItemId));
		unset($this->iWishListItems[$wishListItemId]);
		return true;
	}

/**
 * removeProduct - remove this product from the wish list
 * @param
 *	product ID
 * @return
 *	none
 */
	function removeProduct($productId) {
        $dataTable = new DataTable("wish_list_items");
		foreach ($this->iWishListItems as $index => $thisItem) {
			if ($thisItem['product_id'] != $productId) {
				continue;
			}
            $dataTable->deleteRecord(array("primary_id"=>$thisItem['wish_list_item_id']));
			unset($this->iWishListItems[$index]);
		}
	}

/**
 * removeAllItems - remove all items from the wish list
 * @param
 *	none
 * @return
 *	none
 */
	function removeAllItems() {
        $dataTable = new DataTable("wish_list_items");
        foreach($this->iWishListItems as $thisItem) {
            $dataTable->deleteRecord(array("primary_id"=>$thisItem['wish_list_item_id']));
        }
		$this->iWishListItems = array();
		return true;
	}

/**
 * updateItems - update the internal Wish List Items array
 * @param
 *	none
 * @return
 *	none
 */
	function updateItems() {
		$this->iWishListItems = array();
		$resultSet = executeQuery("select * from wish_list_items where wish_list_id = ?",$this->iWishListId);
		while ($row = getNextRow($resultSet)) {
			$this->iWishListItems[] = $row;
		}
	}

/**
 * getWishListItems - Return an array of the items in the wish list. The array will contain one row for each product id,
 * along with the description, quantity, list price, discount rate and weight of that item in the wish list
 * @param
 *	none
 * @return
 *	array of items in the wish list
 */
	function getWishListItems() {
		$returnArray = array();
		foreach ($this->iWishListItems as $wishListItem) {
			$productId = $wishListItem['product_id'];
			$returnArray[$wishListItem['wish_list_item_id']] = array("wish_list_item_id"=>$wishListItem['wish_list_item_id'],
				"product_id"=>$wishListItem['product_id'],"priority"=>$wishListItem['priority'],
				"notify_when_in_stock"=>$wishListItem['notify_when_in_stock']);
		}
		return $returnArray;
	}

/**
 * getWishListItemsTotal - Return the number of items in the wish list.
 * @param
 *	none
 * @return
 *	count of items in wish list
 */
	function getWishListItemsCount() {
		return count($this->iWishListItems);
	}


/**
 * getErrorMessage - return the error message from the most recent error
 * @param
 *	none
 * @return
 *	error message
 */
	function getErrorMessage() {
		return $this->iErrorMessage;
	}

/**
 * isInList - searches the wish list for a product_id
 * @param
 *	product ID
 * @return
 *	true if product ID is in array
 */
	function isInList($productId) {
		$found = false;
		foreach ($this->iWishListItems as $wishListItem) {
			if ($wishListItem['product_id'] == $productId) {
				$found = true;
				break;
			}
		}
		return $found;
	}

}
