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

require dirname(__FILE__) . '/printful/php-api-sdk.php';

use Printful\Exceptions\PrintfulApiException;
use Printful\Exceptions\PrintfulException;
use Printful\PrintfulApiClient;

class Printful extends ProductDistributor {
    const PRINTFUL_CI = 'app-3097228';
    const PRINTFUL_CS = 'OcztSyFuOuU7AXSouQADmpjwGABGwOC1p2jxf5UUSde2eZ5gd1wIb4xl6twkQZ5N';
	private $iApi = null;
	private $iErrorMessageStack = array();
	private $iUseOauth;
	private $iTokenExpires;
    private $iForceTokenRefresh = false;

	function __construct($locationId) {
		$this->iProductDistributorCode = 'PRINTFUL';
		parent::__construct($locationId);
		$this->iUseOauth = !empty(CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "USE_OAUTH", "PRODUCT_DISTRIBUTORS"));
	}

	function testCredentials() {
		if ($this->apiGet($this->iUseOauth ? '/stores' : '/store') === false) {
			$this->finalizeErrorMessage(); // Not sure about logging errors to database in this function
			return false;
		}

		return true;
	}

	function getErrorMessage() {
		if (!empty($this->iErrorMessageStack)) {
			$this->finalizeErrorMessage(false);
		}

		return $this->iErrorMessage;
	}

	private static function getRedirectUrl() {
		$linkName = getFieldFromId("link_name", "pages", "script_filename", "printfultoken.php", "client_id = ? or client_id = ?",
			$GLOBALS['gClientId'], $GLOBALS['gDefaultClientId']);
		return "https://" . $_SERVER['HTTP_HOST'] . "/" . $linkName;
	}

	public static function getAuthorizeUrl($locationId, $referrer) {
		$redirectUrl = self::getRedirectUrl();

		return sprintf("https://www.printful.com/oauth/authorize?client_id=%s&state=%s&redirect_url=%s",
			self::PRINTFUL_CI, urlencode(http_build_query(["location_id" => $locationId, "referrer" => $referrer])), $redirectUrl);
	}

	public function getAccessToken($authorizationCode) {
		$curl = curl_init();

		$data = array(
			'client_id' => self::PRINTFUL_CI,
			'client_secret' => self::PRINTFUL_CS,
			'grant_type' => 'authorization_code',
			'redirect_uri' => self::getRedirectUrl(),
			'code' => $authorizationCode
		);

		$headers = array(
			'Content-Type: application/x-www-form-urlencoded'
		);

		curl_setopt_array($curl, array(
			CURLOPT_URL => "https://www.printful.com/oauth/token",
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_POSTFIELDS => http_build_query($data),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_CONNECTTIMEOUT => $GLOBALS['gCurlTimeout'],
			CURLOPT_TIMEOUT => ($GLOBALS['gCurlTimeout'] * 4)
		));

		$result = curl_exec($curl);
		$info = curl_getinfo($curl);
		if ($result === false || ($info['http_code'] != 200 && $info['http_code'] != 202) && $info['http_code'] != 201) {
			return curl_error($curl) . ":" . jsonEncode($result) . ":" . jsonEncode($info);
		}
		curl_close($curl);
		$this->setAccessToken(json_decode($result, true));
		return true;
	}

	private function refreshToken() {
		$refreshToken = getPreference('PRINTFUL_REFRESH_TOKEN', $this->iLocationId);
		if (empty($refreshToken)) {
			return "Refresh token not found.  Re-authorize with Printful to get a new access token.";
		}
		$curl = curl_init();

		$data = array(
			'client_id' => self::PRINTFUL_CI,
			'client_secret' => self::PRINTFUL_CS,
			'grant_type' => 'refresh_token',
			'refresh_token' => $refreshToken
		);

		$headers = array(
			'Content-Type: application/x-www-form-urlencoded',
		);

		curl_setopt_array($curl, array(
			CURLOPT_URL => "https://www.printful.com/oauth/token",
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_POSTFIELDS => http_build_query($data),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_CONNECTTIMEOUT => $GLOBALS['gCurlTimeout'],
			CURLOPT_TIMEOUT => ($GLOBALS['gCurlTimeout'] * 4)
		));

		$result = curl_exec($curl);
		$info = curl_getinfo($curl);
		if ($result === false || ($info['http_code'] != 200 && $info['http_code'] != 202) && $info['http_code'] != 201) {
			return curl_error($curl) . ":" . jsonEncode($info);
		}
		curl_close($curl);
		$this->setAccessToken(json_decode($result, true));
        $this->iForceTokenRefresh = false;

		return true;
	}

	private function setAccessToken($tokenResult) {
		$this->iTokenExpires = $tokenResult['expires_at'];
		$preferenceArray = array(
			array('code' => 'PRINTFUL_ACCESS_TOKEN', 'description' => 'Printful Access Token', 'data_type' => 'varchar', 'value' => $tokenResult['access_token']),
			array('code' => 'PRINTFUL_REFRESH_TOKEN', 'description' => 'Printful Refresh Token', 'data_type' => 'varchar', 'value' => $tokenResult['refresh_token']),
			array('code' => 'PRINTFUL_TOKEN_EXPIRES', 'description' => 'Printful Token Expires', 'data_type' => 'int', 'value' => $this->iTokenExpires)
		);

		$preferenceQualifier = $this->iLocationRow['location_id'];
		foreach ($preferenceArray as $thisPreference) {
			$preferenceId = getFieldFromId("preference_id", "preferences", "preference_code", $thisPreference['code']);
			if (empty($preferenceId)) {
				$result = executeQuery("insert into preferences (preference_code,description, data_type, client_setable) values (?,?,?, 1)",
					$thisPreference['code'], $thisPreference['description'], $thisPreference['data_type']);
				$preferenceId = $result['insert_id'];
			}
			executeQuery("delete from client_preferences where client_id = ? and preference_id = ? and preference_qualifier = ?",
				$GLOBALS['gClientId'], $preferenceId, $preferenceQualifier);
			executeQuery("insert into client_preferences (client_id,preference_id,preference_qualifier,preference_value) values (?,?,?,?)",
				$GLOBALS['gClientId'], $preferenceId, $preferenceQualifier, $thisPreference['value']);
		}
		CustomField::setCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "USE_OAUTH", true, "PRODUCT_DISTRIBUTORS");
		executeQuery("update location_credentials set password = '(Saved Token)' where location_credential_id = ?", $this->iLocationCredentialRow['location_credential_id']);
	}

	function getManufacturers($parameters = array()) {
		if (array_key_exists('printful_products', $parameters)) {
			$printfulProducts = $parameters['printful_products'];
		} else {
			$printfulProducts = $this->getPrintfulProducts();

			if ($printfulProducts === false) {
				$this->pushErrorMessage("Failed to get manufacturers");
				return false;
			}
		}

		$productManufacturers = array();
		$productManufacturers["PRINTFUL"] = array("business_name" => "Printful",
			"web_page" => "https://www.printful.com/");

		foreach ($printfulProducts as $printfulProduct) {
			$manufacturer = $manufacturerCode = $printfulProduct['brand'];
			$manufacturerCode = makeCode($manufacturerCode);

			if (!empty($manufacturerCode) && !array_key_exists($manufacturerCode, $productManufacturers)) {
				$productManufacturers[$manufacturerCode] = array("business_name" => ucwords(strtolower($manufacturer)));
			}
		}

		uasort($productManufacturers, array($this, 'compareManufacturers'));
		return $productManufacturers;
	}

	function getCategories($parameters = array()) {
		$printfulSyncVariants = $this->getPrintfulSyncVariants();

		if ($printfulSyncVariants === false) {
			$this->pushErrorMessage("Failed to get categories");
			$this->finalizeErrorMessage(false);
			return false;
		}

		$printfulProductsMap = $this->getPrintfulProductsMapped();

		if ($printfulProductsMap === false) {
			$this->pushErrorMessage("Failed to get categories");
			$this->finalizeErrorMessage(false);
			return false;
		}

		$productCategories = array();

		foreach ($printfulSyncVariants as $printfulSyncVariant) {
			$printfulProductId = $printfulSyncVariant['product']['product_id'];
			$printfulProduct = $printfulProductsMap[$printfulProductId];

			if (!empty($printfulProduct['type'])) {
				$printfulProductType = $printfulProduct['type'];
				$productCategories[makeCode($printfulProductType)] =
					array("description" => ucwords(strtolower($printfulProductType)));
			}
		}

		uasort($productCategories, array($this, 'compareCategories'));
		return $productCategories;
	}

	function getFacets($parameters = array()) {
		return array();
	}

	function getPrintfulSyncVariants($parameters = array()) {
		$printfulProductsMap = $this->getPrintfulProductsMapped();
		$printfulSyncProducts = $this->getPrintfulSyncProducts();

		if ($printfulProductsMap === false || $printfulSyncProducts === false) {
			$this->pushErrorMessage("Failed to get Printful Sync Variants");
			return false;
		}

		$printfulSyncVariants = array();
		$includeUnsynced = !empty($parameters['include_unsynced']);

		foreach ($printfulSyncProducts as $printfulSyncProduct) {
			$result = $this->apiGet('sync/products/' . $printfulSyncProduct['id']);

			if ($result === false) {
				$this->pushErrorMessage("Failed to get Sync Variants of Printful Sync Product " .
					$printfulSyncProduct['id']);
				return false;
			}

			foreach ($result['sync_variants'] as $printfulSyncVariant) {
				// Some products have special legal restrictions that prevent it from being exposed in
				// the API. GET sync/products call return them, but we can validate which of them are allowed to be
				// ordered in API by comparing them to GET products response
				if (!array_key_exists($printfulSyncVariant['product']['product_id'], $printfulProductsMap)) {
					$this->pushErrorMessage("Variant skipped because of unavailable product: " . $printfulSyncVariant['product']['name']);
					continue;
				}

				if ($includeUnsynced || !empty($printfulSyncVariant['synced'])) {
					$printfulSyncVariant['sync_product'] =& $result['sync_product'];
					$printfulSyncVariants[] = $printfulSyncVariant;
				}
			}
		}

		if (!empty($parameters['expand_variants'])) {
			if (!$this->expandPrintfulVariants($printfulSyncVariants)) {
				return false;
			}
		}

		return $printfulSyncVariants;
	}

	function syncProducts($parameters = array()) {
		// Get Printful Sync Variants.
		// Coreware Product = Printful Sync Variant

		$printfulSyncVariants = $this->getPrintfulSyncVariants(array("expand_variants" => true));

		if ($printfulSyncVariants === false) {
			$this->finalizeErrorMessage();
			return false;
		}

		$processCount = $insertCount = $imageCount = $newImageCount = $foundCount = 0;
		$productDistributorId = $this->iLocationRow['product_distributor_id'];
		$printfulImageSizeCache = array();

		foreach ($printfulSyncVariants as $printfulSyncVariant) {
			$processCount++;
			$printfulSyncProduct = $printfulSyncVariant['sync_product'];
			$printfulVariant = $printfulSyncVariant['variant'];
			$printfulProduct = $printfulVariant['product'];
			if (empty($printfulVariant)) { // some variants may be missing due to lack of stock or discontinued
				continue;
			}

			// Use Sync Variant ID as the distributor product code.

			$distributorProductCode = $printfulSyncVariant['id'];

			// Generate product manufacturer code.

			$productManufacturerCode = makeCode($printfulProduct['brand']);

			// Find existing product ID.

			$productId = getFieldFromId("product_id", "distributor_product_codes", "product_distributor_id",
				$productDistributorId, "product_code = ? and product_id in (select product_id from products " .
				"where inactive = 0)", $distributorProductCode);

			$productIsNew = empty($productId);

			if ($productIsNew) {
				// Create new product.

				$productDescription = $printfulSyncVariant['name'];
				$productDetailedDescription = $printfulVariant['description'];

				// Use external Sync Variant ID as the initial value for the coreware-side product
				// code, which is stored in `products`, and not in `distributor_product_codes`.
				$productCode = self::createUniqueProductCode($printfulSyncVariant['external_id']);

				if (empty($productDescription)) {
					$productDescription = $printfulVariant['name'];

					if (empty($productDescription)) {
						if (strlen($productDetailedDescription) > 255) {
							$str = wordwrap($productDetailedDescription, 250);
							$productDescription = mb_substr($str, 0, strpos($str, "\n"));
						} else {
							$productDescription = $productDetailedDescription;
							$productDetailedDescription = "";
						}

						if (empty($productDescription)) {
							$productDescription = "Product " . $productCode;
						}
					}
				}

				$productBaseCost = self::calculateBaseCost($printfulSyncVariant);
				$productListPrice = (float)$printfulSyncVariant['retail_price'];

				// Create unique link name.

				$productLinkName = self::createUniqueLinkName($productDescription);

				// Get manufacturer ID, or "" if it doesn't exist yet.

				$productManufacturerId = $this->getManufacturer($productManufacturerCode);

				// Insert record.

				$GLOBALS['gPrimaryDatabase']->startTransaction();
				$productDescription = preg_replace('/\s+/', ' ', trim($productDescription));

				$insertSet = executeQuery("insert into products (client_id,product_code,description," .
					"detailed_description,link_name,product_manufacturer_id,base_cost,list_price,date_created," .
					"time_changed,reindex,internal_use_only) values (?,?,?,?,?,?,?,?,now(),now(),1,?)",
					$GLOBALS['gClientId'], $productCode, $productDescription, $productDetailedDescription,
					$productLinkName, $productManufacturerId, $productBaseCost, $productListPrice, 0);

				if (!empty($errorMessage = $insertSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$this->assignErrorMessage($errorMessage, "Error occurred while adding product to database");
					return false;
				}

				if (empty($productId = $insertSet['insert_id'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$this->assignErrorMessage("Failed to get product ID of new product after adding it to database.");
					return false;
				}

				executeQuery("insert into product_data (client_id,product_id) values (?,?)",$GLOBALS['gClientId'],$productId);

				// Immediately store product code to distributor_product_codes to prevent having
				// duplicate products in the next update if error occurs here.
				//
				// We don't have to do this in existing products since database guarantees product
				// code to be unique in every pair of client and distributor ID.  Having a matching
				// product in the earlier query means the existing product already has the right
				// distributor product code.
				//
				// We remove existing distributor product code entry that has the product code to
				// allow this new product to use it.  Products that use the existing entry are
				// likely the same Printful products that are only declared inactive (see earlier
				// query).  Otherwise, it would mean that Printful has changed its own product codes.
				// We can add a test and produce an error message for this inconsistency, but only by
				// comparing product details.  Comparing with external ID won't help because it would
				// also likely change if the Sync Variant ID changes as well.
				//
				// We also don't have to check if this new product has a previously assigned product
				// code entry simply because it's a new product.

				$deleteSet = executeQuery("delete from distributor_product_codes where client_id = ? and " .
					"product_distributor_id = ? and product_code = ?", $GLOBALS['gClientId'], $productDistributorId,
					$distributorProductCode);
				if ($deleteSet['affected_rows'] > 0) {
					addDebugLog("Printful deleted from distributor_product_codes: " . $productDistributorId . ":" . $distributorProductCode);
				}

				if (!empty($errorMessage = $deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$this->assignErrorMessage($errorMessage, "Error occurred while trying to remove possibly " .
						"existing distributor product code entry that has product code " . $distributorProductCode);
					return false;
				}

				// Create new distributor product code entry.

				$insertSet = executeQuery("insert into distributor_product_codes (client_id,product_distributor_id," .
					"product_id,product_code) values (?,?,?,?)", $GLOBALS['gClientId'], $productDistributorId,
					$productId, $distributorProductCode);

				if (!empty($errorMessage = $insertSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$this->assignErrorMessage($errorMessage, "Error occurred while adding new " .
						"distributor_product_codes entry");
					return false;
				}

				$GLOBALS['gPrimaryDatabase']->commitTransaction();

				// Add product to categories having 'add_new_product = 1'.

				executeQuery("insert ignore into product_category_links (product_category_id,product_id) " .
					"select product_category_id,? from product_categories where inactive = 0 and client_id = ? " .
					"and add_new_product = 1", $productId, $GLOBALS['gClientId']);

				$resultSet = executeQuery("select * from products left outer join product_data using (product_id) where products.product_id = ? and products.client_id = ?", $productId, $GLOBALS['gClientId']);
				$productRow = getNextRow($resultSet);
				$insertCount++;
			} else {
				$productRow = $this->getProductRow($productId);
				$foundCount++;
			}

			$productIdsProcessed[$productId] = $productId;

			if (empty($productRow)) {
				$this->assignErrorMessage(sprintf("Unable to get product %s's product row from database.", $productId));
				return false;
			}

			// Update product code if it doesn't begin with "PRINTFUL_" as previous code doesn't add it.
			// The prefix makes it easier to recognize Printful products.

			if (!$productIsNew && substr($productRow['product_code'], 0, strlen("PRINTFUL_")) !== "PRINTFUL_") {
				$productCode = self::createUniqueProductCode($printfulSyncVariant['external_id']);
				executeQuery("update products set time_changed = now(), product_code = ? where product_id = ?",
					$productCode, $productId);
			}

			// Update costs?
			if (!$productIsNew) {
				$productBaseCost = self::calculateBaseCost($printfulSyncVariant);
				$productListPrice = (float)$printfulSyncVariant['retail_price'];

				if (empty($productRow['base_cost']) || $productRow['base_cost'] != $productBaseCost) {
					executeQuery("update products set time_changed = now(), base_cost = ? where product_id = ?",
						$productBaseCost, $productId);
				}

				if (empty($productRow['list_price']) || $productRow['list_price'] != $productListPrice) {
					executeQuery("update products set time_changed = now(), list_price = ? where product_id = ?",
						$productListPrice, $productId);
				}
			}

			// Update manufacturer code.

			if (empty($productRow['product_manufacturer_id'])) {
				$this->updateManufacturer($productId, $productManufacturerCode);
			}

			// Update image.

			if (!empty($printfulSyncVariant['files'])) {
				$imageId = null;
				$printfulImageId = $printfulImageUrl = $printfulImageFilename = null;
				$printfulImageHashCode = null;
				$printfulImageSize = 0;

				foreach ($printfulSyncVariant['files'] as $file) {
					if ($file['type'] == 'preview') {
						$printfulImageId = $file['id'];
						$printfulImageUrl = $file['preview_url'];
						$printfulImageFilename = $file['filename'];
						$printfulImageHashCode = $file['hash'];

						if (empty($printfulImageSizeCache[$printfulImageId])) {
							$printfulImageSize = (int)self::getRemoteFileSize($printfulImageUrl);

							if ($printfulImageSize > 0) {
								$printfulImageSizeCache[$printfulImageId] = $printfulImageSize;
							}
						} else {
							$printfulImageSize = $printfulImageSizeCache[$printfulImageId];
						}

						break;
					}
				}

				if (!empty($printfulImageId) && !empty($printfulImageUrl) && !empty($printfulImageFilename) &&
					!empty($printfulImageHashCode) && $printfulImageSize > 0) {
					$createOrUpdateImage = true;

					if (!empty($productRow['image_id'])) {
						$imageId = getFieldFromId("image_id", "images", "client_id", $GLOBALS['gClientId'],
							"filename = ?", $printfulImageFilename);

						if (!empty($imageId)) {
							$createOrUpdateImage = false;
						}
					}

					if ($createOrUpdateImage) {
						$printfulImageContent = file_get_contents($printfulImageUrl);

						if (empty($printfulImageContent)) {
							self::logError("Failed to get content of " . $printfulImageUrl);
						} else {
							$printfulImageContentSize = strlen($printfulImageContent);

							if ($printfulImageSize != $printfulImageContentSize) {
								self::logError(sprintf("Declared size of '%s' which is %s does not match downloaded " .
									"content size (%s).", $printfulImageUrl, $printfulImageSize,
									$printfulImageContentSize));
							} else {
								// Use the original filename's extension to avoid storing fake PNG.

								$filenameParts = explode('.', $printfulImageFilename);
								$extension = strtolower($filenameParts[count($filenameParts) - 1]);

								$fileInformation = array(
									'name' => $printfulImageFilename,
									'extension' => $extension,
									'file_content' => $printfulImageContent
								);

								$parameters = array(
									'description' => $productRow['description'],
									'detailed_description' => $productRow['detailed_description']
								);

								$imageId = createImage($fileInformation, $parameters);

								if (empty($imageId)) {
									self::logError("Failed to create image from contents of '" . $printfulImageUrl . "'.");
								} else {
									$imageRow = getMultipleFieldsFromId(array("image_code", "hash_code"), "images",
										"image_id", $imageId);

									// Update image code and hash code.
									//
									// Only update image code if it's not set.  The image code tells us that image was
									// originally from Printful, and may be deleted during cleanup if it's no longer
									// used by any product.
									//
									// We should also update the filename, but createImage() already does it.

									if (empty($imageRow['image_code'])) {
										$imageCode = self::createUniqueImageCode($printfulImageId);
										$resultSet = executeQuery("update images set image_code = ? where image_id = ?",
											$imageCode, $imageId);

										if (!empty($errorMessage = $resultSet['sql_error'])) {
											self::logError($errorMessage,
												"Failed to update product image's image_code");
										}
									}

									if ($imageRow['hash_code'] != $printfulImageHashCode) {
										$resultSet = executeQuery("update images set hash_code = ? where image_id = ?",
											$printfulImageHashCode, $imageId);

										if (!empty($errorMessage = $resultSet['sql_error'])) {
											self::logError($errorMessage, "Failed to update product image's hash_code");
										}
									}

									$newImageCount++;
								}
							}
						}
					}
				}

				if (!empty($imageId) && (empty($productRow['image_id']) || $productRow['image_id'] != $imageId)) {
					$resultSet = executeQuery("update products set time_changed = now(), image_id = ? " .
						"where product_id = ?", $imageId, $productId);

					if (!empty($errorMessage = $resultSet['sql_error'])) {
						self::logError($errorMessage, "Failed to update product's image_id.");
					}

					$productRow['image_id'] = $imageId;
					$imageCount++;
				}
			}

			// Add link name if it doesn't exist.

			if (empty($productRow['link_name'])) {
				$productLinkName = self::createUniqueLinkName($productRow['description']);
				executeQuery("update products set time_changed = now(), link_name = ? where product_id = ?",
					$productLinkName, $productId);
			}

			// Printful now allows orders to made by specifying the sync variant ID so these extra data are no longer
			// needed to be stored. This will remove existing unneeded notes
			if (!empty($productRow['notes']) && substr($productRow['notes'], 0, 1) === "{") {
				executeQuery("update products set time_changed = now(), notes = NULL where product_id = ?", $productId);
			}

			// Update other data.

			$this->updateProductData($productId, $productRow, array('model' => $printfulProduct['model']));

			// Update category.

			if (!empty($printfulProduct['type'])) {
				$this->addProductCategories($productId, array(makeCode($printfulProduct['type'])));
			}

			// Add group variant and choices.

			$attributes = array();

			if (!empty($printfulVariant['size'])) {
				$attributes['size'] = $printfulVariant['size'];
			}

			if (!empty($printfulVariant['color'])) {
				$attributes['color'] = $printfulVariant['color'];
			}

			if (!empty($attributes)) {
				// Enabling $deassignInactiveOptions seems to significantly increase update time
				// and I'm not sure if it's worth enabling in Printful.  Need to check if existing
				// products in Printful can have one of their attributes removed or disabled.

				$productGroupCode = "PRINTFUL_" . strtoupper($printfulSyncProduct['external_id']);
				$productGroupDescription = $printfulSyncProduct['name'];
				$result = $this->addOrUpdateProductGroupVariant($productId, $productGroupCode, $productGroupDescription, $attributes);

				if ($result === false) {
					$this->finalizeErrorMessage();
					return false;
				}
			}
		}

		$this->finalizeErrorMessage(false);

		return $processCount . " processed, " . $insertCount . " inserted, " .
			$imageCount . " updated with images (" . $newImageCount . " new images added), " .
			$foundCount . " existing" . (!empty($this->iErrorMessage) ? ", Error(s) occurred: " . $this->iErrorMessage : "");
	}

	function updateProductData($productId, $productDataRow, $parameters) {
		if (empty($parameters['manufacturer_advertised_price']) || !is_numeric($parameters['manufacturer_advertised_price']) || $parameters['manufacturer_advertised_price'] < 1) {
			$parameters['manufacturer_advertised_price'] = "";
		}
		$thisDistributorPriority = $this->iProductDistributorRow['sort_order'];
		if ($thisDistributorPriority == 0 || empty($thisDistributorPriority)) {
			$thisDistributorPriority = 999999;
		}
		$currentDistributorPriority = ProductDistributor::$iLoadedValues['product_distributors'][$productDataRow['product_distributor_id']]['sort_order'];
		if ($currentDistributorPriority == 0 || empty($currentDistributorPriority)) {
			$currentDistributorPriority = 999999;
		}

		$GLOBALS['gChangeLogNotes'] = "Change (#598) by " . $this->iProductDistributorRow['description'];
		$dataTable = new DataTable("product_data");
		if (empty($productDataRow['product_data_id'])) {
			$valuesArray = array_merge($parameters, array("product_id" => $productId));
			$dataTable->saveRecord(array("name_values" => $valuesArray));
		} else {
			$numberValues = array("weight", "height", "length", "width");
			$dataTable->setSaveOnlyPresent(true);
			$nameValues = array();
			foreach ($parameters as $thisField => $fieldValue) {
				if (in_array($thisField, $numberValues) && $fieldValue <= 0) {
					continue;
				}
				if (in_array($thisField, $numberValues) && $productDataRow[$thisField] <= 0) {
					$productDataRow[$thisField] = "";
				}
				if (array_key_exists($thisField, $productDataRow) && !empty($fieldValue) && $fieldValue != $productDataRow[$thisField] &&
					(empty($productDataRow['product_distributor_id']) || $thisDistributorPriority <= $currentDistributorPriority || empty($productDataRow[$thisField]))) {
					$nameValues[$thisField] = $fieldValue;
				}
			}
			if (!empty($nameValues)) {
				$nameValues['product_distributor_id'] = $this->iProductDistributorRow['product_distributor_id'];
				$dataTable->saveRecord(array("name_values" => $nameValues, "primary_id" => $productDataRow['product_data_id']));
			}
		}
		$GLOBALS['gChangeLogNotes'] = "";
	}

	function syncInventory($parameters = array()) {
		$printfulSyncVariants = $this->getPrintfulSyncVariants(array("expand_variants" => true,
			"include_unsynced" => true));

		if ($printfulSyncVariants === false) {
			$this->finalizeErrorMessage();
			return false;
		}

		$productArray = array();
		$productIdArray = array();
		$processCount = 0;
		$skipCount = 0;

		foreach ($printfulSyncVariants as $printfulSyncVariant) {
			$distributorProductCode = $printfulSyncVariant['id'];

			if (array_key_exists($distributorProductCode, $productIdArray)) {
				$this->assignErrorMessage("Duplicate product code detected: " . $distributorProductCode);
				return false;
			}

			$productId = getFieldFromId("product_id", "distributor_product_codes", "product_distributor_id",
				$this->iLocationRow['product_distributor_id'], "product_code = ?", $distributorProductCode);

			if (empty($productId)) {
				$skipCount++;
			} else {
				$productArray[] = $printfulSyncVariant;
				$productIdArray[$distributorProductCode] = $productId;
			}
		}

		foreach ($productArray as $printfulSyncVariant) {
			$distributorProductCode = $printfulSyncVariant['id'];
			$productId = $productIdArray[$distributorProductCode];

			if (empty($productId)) {
				$skipCount++;
			} else {
				$processCount++;

				// Unsynced products are set with 0 stock quantity.  Not sure if they should be set as inactive instead.
				// Unsynced Printful Sync Variants can be caused by inactive Printful Products linked to them, and it
				// can be temporary or permanent.

				$syncedAndInStock = !empty($printfulSyncVariant['synced']) &&
					!empty($printfulSyncVariant['variant']['in_stock']);
				$productQty = $syncedAndInStock ? 9999 : 0;

				$productBaseCost = self::calculateBaseCost($printfulSyncVariant);

				$totalCost = $productBaseCost * $productQty;
				$this->updateProductInventory($productId, $productQty, $totalCost);
			}
		}

		executeQuery("update locations set has_allocated_inventory = 1 where location_id = ?", $this->iLocationRow['location_id']);

		return $processCount . " product quantities processed, " . $skipCount . " products skipped";
	}

	function placeOrder($orderId, $orderItems, $additionalParameters = array()) {
		$orderRow = getRowFromId("orders", "order_id", $orderId);
		$contactRow = Contact::getContact($orderRow['contact_id']);

		if (empty($orderRow['address_id'])) {
			$addressRow = $contactRow;
		} else {
			$addressRow = getRowFromId("addresses", "address_id", $orderRow['address_id']);
			if (empty($addressRow['address_1']) || empty($addressRow['city'])) {
				$addressRow = $contactRow;
			}
		}

		$orderParts = $this->splitOrder($orderId, $orderItems);

		if ($orderParts === false) {
			return false;
		}

		$customerOrderItemRows = $orderParts['customer_order_items'];
		$dealerOrderItemRows = $orderParts['dealer_order_items'];
		$fflOrderItemRows = $orderParts['ffl_order_items'];
		$customerPrintfulOrderId = $dealerPrintfulOrderId = null;

		if (!empty($fflOrderItemRows)) {
			$this->assignErrorMessage(sprintf("FFL orders are unexpected in Printful. Order ID is %s.", $orderId));
			return array('error_message' => $this->iErrorMessage);
		}

		if (!empty($customerOrderItemRows)) {
			$countryInfo = getMultipleFieldsFromId(array("country_code", "country_name"), "countries", "country_id",
				$addressRow['country_id']);

			$recipient = array(
				'name' => $orderRow['full_name'],
				'address1' => $addressRow['address_1'],
				'address2' => $addressRow['address_2'],
				'city' => $addressRow['city'],
				'state_code' => $addressRow['state'],
				'state_name' => null,
				'country_code' => $countryInfo['country_code'],
				'country_name' => $countryInfo['country_name'],
				'zip' => $addressRow['postal_code']
			);

			if (!empty($addressRow['phone_number'])) {
				$recipient['phone'] = $addressRow['phone_number'];
			}

			if (!empty($addressRow['email_address'])) {
				$recipient['email'] = $addressRow['email_address'];
			}

			$customerPrintfulOrderId = $this->createPrintfulOrder($orderId, $recipient, $customerOrderItemRows);

			if ($customerPrintfulOrderId === false) {
				$this->pushErrorMessage("Failed to create Printful order for customer orders of order " . $orderId);
				$this->finalizeErrorMessage();
				return array('error_message' => $this->iErrorMessage);
			}
		}

		if (!empty($dealerOrderItemRows)) {
			$phoneNumber = $this->iLocationContactRow['phone_number'];

			$emailAddress = $this->iLocationContactRow['email_address']; // Not sure where to get email address.
			$countryInfo = getMultipleFieldsFromId(array("country_code", "country_name"), "countries", "country_id",
				$this->iLocationContactRow['country_id']);

			$recipient = array(
				'name' => $orderRow['full_name'],
				'address1' => $this->iLocationContactRow['address_1'],
				'address2' => $this->iLocationContactRow['address_2'],
				'city' => $this->iLocationContactRow['city'],
				'state_code' => $this->iLocationContactRow['state'],
				'state_name' => null,
				'country_code' => $countryInfo['country_code'],
				'country_name' => $countryInfo['country_name'],
				'zip' => $addressRow['postal_code'],
				'phone' => $phoneNumber,
				'email' => $emailAddress
			);

			$dealerPrintfulOrderId = $this->createPrintfulOrder($orderId, $recipient, $dealerOrderItemRows);

			if ($dealerPrintfulOrderId === false) {
				$this->pushErrorMessage("Failed to create Printful order for dealer orders of order " . $orderId);
				$this->finalizeErrorMessage();
				$this->cancelPrintfulOrder($customerPrintfulOrderId);
				return array('error_message' => $this->iErrorMessage);
			}
		}

		// Submit order(s).

		$returnValues = array();

		if (!empty($customerPrintfulOrderId)) {
			if ($this->confirmPrintfulOrder($customerPrintfulOrderId) === false) {
				$this->pushErrorMessage("Failed to finalize customer orders of order " . $orderId);
				$this->finalizeErrorMessage();
				$this->cancelPrintfulOrder($customerPrintfulOrderId);
				$this->cancelPrintfulOrder($dealerPrintfulOrderId);
				return array('error_message' => $this->iErrorMessage);
			} else {
				$returnValues['customer'] = $this->saveOrderData('customer', $orderId, $customerOrderItemRows,
					$customerPrintfulOrderId, $orderRow['full_name']);
			}
		}

		if (!empty($dealerPrintfulOrderId)) {
			if ($this->confirmPrintfulOrder($dealerPrintfulOrderId) === false) {
				$this->pushErrorMessage("Failed to finalize dealer orders of order " . $orderId);
				$this->finalizeErrorMessage();
				$this->cancelPrintfulOrder($dealerPrintfulOrderId);
			} else {
				$returnValues['dealer'] = $this->saveOrderData('dealer', $orderId, $dealerOrderItemRows,
					$dealerPrintfulOrderId, $orderRow['full_name']);
			}
		}

		return $returnValues;
	}

	function placeDistributorOrder($productArray, $parameters = array()) {
		return array();
	}

	function getOrderTrackingData($orderShipmentId) {
		$orderShipmentRow = getRowFromId("order_shipments", "order_shipment_id", $orderShipmentId);

		if (empty($orderShipmentRow['remote_order_id'])) {
			return false;
		}

		$remoteOrderRow = getRowFromId("remote_orders", "remote_order_id", $orderShipmentRow['remote_order_id']);

		if (empty($remoteOrderRow['order_number'])) {
			return false;
		}

		$printfulOrderId = $remoteOrderRow['order_number'];
		$printfulOrderInfo = $this->getPrintfulOrderInfo($printfulOrderId);

		if ($printfulOrderInfo === false || empty($printfulOrderInfo['shipments']) ||
			empty($printfulOrderInfo['costs']['shipping'])) {
			return false;
		}

		$shippingCharge = $printfulOrderInfo['costs']['shipping'];

		unset($printfulOrderInfo['items']);
		unset($printfulOrderInfo['retail_costs']);
		unset($printfulOrderInfo['gift']);
		unset($printfulOrderInfo['packing_slip']);

		$shipments = $printfulOrderInfo['shipments'];
		$firstShipment = array_shift($shipments);

		if (empty($firstShipment['tracking_number'])) {
			return false;
		}

		$trackingIdentifier = $firstShipment['tracking_number'];
		$multipleTracking = false;

		$carrierDescription = "";
		foreach ($shipments as $shipment) {
			if (empty($shipment['tracking_number']) || $trackingIdentifier != $shipment['tracking_number']) {
				$multipleTracking = true;
				break;
			}
			$carrierDescription = $shipment['carrier'];
		}

		if ($multipleTracking) {
			executeQuery("update order_shipments set tracking_identifier = ?,shipping_charge = ?," .
				"shipping_carrier_id = NULL,carrier_description = NULL where order_shipment_id = ?",
				"Multiple (Please see Printful dashboard)", $shippingCharge, $orderShipmentId);
			return array($orderShipmentId);
		}

		$dateShipped = date("Y-m-d", $firstShipment['shipped_at']);

		$shippingCarrierCode = self::getShippingCarrierCode($carrierDescription, "");
		$shippingCarrierId = getFieldFromId("shipping_carrier_id", "shipping_carriers", "shipping_carrier_code", makeCode($shippingCarrierCode));

		if (empty($shippingCarrierId)) {
			$resultSet = executeQuery("update order_shipments set date_shipped = ?,tracking_identifier = ?," .
				"shipping_charge = ?,shipping_carrier_id = NULL,carrier_description = ? " .
				"where order_shipment_id = ?", $dateShipped, $trackingIdentifier, $shippingCharge,
				$carrierDescription, $orderShipmentId);
		} else {
			$resultSet = executeQuery("update order_shipments set date_shipped = ?,tracking_identifier = ?," .
				"shipping_charge = ?,shipping_carrier_id = ?,carrier_description = ? where order_shipment_id = ?",
				$dateShipped, $trackingIdentifier, $shippingCharge, $shippingCarrierId, $carrierDescription,
				$orderShipmentId);
		}

		if ($resultSet['affected_rows'] > 0) {
			Order::sendTrackingEmail($orderShipmentId);
			executeQuery("insert into change_log (client_id,user_id,table_name,primary_identifier,column_name,new_value, notes) values (?,?,?,?,?,?,?)",
				$GLOBALS['gClientId'], $GLOBALS['gUserId'], 'order_shipments', $orderShipmentId, 'tracking_identifier', $trackingIdentifier,
				"Tracking number added by " . $this->iProductDistributorRow['description']);
			return array($orderShipmentId);
		}

		return false;
	}

	//
	// Private Functions
	//

	private function api() {
		try {
			if ($this->iApi === null || ($this->iUseOauth && time() > $this->iTokenExpires)) {
				if ($this->iUseOauth) {
					$accessToken = getPreference("PRINTFUL_ACCESS_TOKEN", $this->iLocationId);
					$this->iTokenExpires = $this->iTokenExpires ?: getPreference("PRINTFUL_TOKEN_EXPIRES", $this->iLocationId);
					if ($this->iForceTokenRefresh || time() > $this->iTokenExpires) {
						$result = $this->refreshToken();
						if ($result === true) {
							$accessToken = getFieldFromId("preference_value", "client_preferences", "client_id", $GLOBALS['gClientId'],
								"preference_id = (select preference_id from preferences where preference_code = 'PRINTFUL_ACCESS_TOKEN') and preference_qualifier = ?", $this->iLocationId);
						}
					}
					$this->iApi = new PrintfulApiClient($accessToken, PrintfulApiClient::TYPE_OAUTH_TOKEN);
				} else {
					$apiKey = $this->iLocationCredentialRow['password'];
					$this->iApi = new PrintfulApiClient($apiKey, PrintfulApiClient::TYPE_LEGACY_STORE_KEY);
				}
			}
		} catch (Exception $e) {
            $this->pushErrorMessage($e, sprintf("Failed to connect to Printful API"));
            $this->iApi = new class() {
                public function get($target, $params = array()) {
                    return false;
                }
                public function post($target, $params = array()) {
                    return false;
                }
                public function delete($target, $params = array()) {
                    return false;
                }
            };
		}

		return $this->iApi;
	}

	private function apiGet($target, $params = array()) {
		try {
			return $this->api()->get($target, $params);
		} catch (PrintfulException $e) {
			if ($e instanceof PrintfulApiException && $e->getCode() == 429) { // Too many requests
				sleep(30);
				return $this->apiGet($target, $params);
			} elseif($e->getCode() == 401 && stristr($e->getMessage(),  "expired") && !$this->iForceTokenRefresh) {
                $this->iForceTokenRefresh = true;
                return $this->apiGet($target, $params);
            }
			$this->pushErrorMessage($e, sprintf("Failed to query '%s'", $target));
			return false;
		}
	}

	private static function composeErrorMessage($e, $prefix = null) {
		$errorMessage = "";
		if ($e instanceof PrintfulException) {
			if ($e instanceof PrintfulApiException) {
				$errorMessage = 'Printful API Exception: ' . $e->getCode() . ': ' . $e->getMessage();
				if ($e->getCode() == 429) { // Too many requests
					sleep(30);
				} elseif($e->getCode() == 401) {
                    sendCredentialsError(["integration_name"=>"Printful","error_message"=>$e->getMessage()]);
                }
			} else {
				$errorMessage = 'Printful Exception: ' . $e->getMessage();
			}

			if (!empty($e->rawResponse)) {
				$errorMessage .= PHP_EOL . "Raw Response: " . $e->rawResponse;
			}
		} elseif ($e instanceof Exception) {
			$errorMessage = $e->getMessage();
		} elseif (is_string($e)) {
			$errorMessage = $e;
		}

		if (is_string($prefix)) {
			$errorMessage = $prefix . ": " . $errorMessage;
		}

		return $errorMessage;
	}

	private function pushErrorMessage($e, $prefix = null) {
		$this->iErrorMessageStack[] = self::composeErrorMessage($e, $prefix);
	}

	private static function logError($e, $prefix = null) {
		$errorMessage = self::composeErrorMessage($e, $prefix);
		$GLOBALS['gPrimaryDatabase']->logError($errorMessage);
	}

	private function assignErrorMessage($e, $prefix = null, $logToDatabase = true) {
		$errorMessage = self::composeErrorMessage($e, $prefix);
		$this->iErrorMessage = $errorMessage;

		if ($logToDatabase) {
			$GLOBALS['gPrimaryDatabase']->logError($errorMessage);
		}
	}

	private function finalizeErrorMessage($logToDatabase = true) {
		$finalErrorMessage = array_pop($this->iErrorMessageStack);

		while (is_string($nextErrorMessage = array_pop($this->iErrorMessageStack))) {
			$finalErrorMessage .= ": " . $nextErrorMessage;
		}

		$this->iErrorMessageStack = array();
		$this->assignErrorMessage($finalErrorMessage, null, $logToDatabase);
	}

	private function compareManufacturers($a, $b) {
		$a = $a['business_name'];
		$b = $b['business_name'];
		return $a == $b ? 0 : ($a > $b ? 1 : -1);
	}

	private function compareCategories($a, $b) {
		$a = $a['description'];
		$b = $b['description'];
		return $a == $b ? 0 : ($a > $b ? 1 : -1);
	}

	private function getPrintfulProducts() {
		// Some of the products generated here are aliases.  I realized this after detecting
		// duplicate variants in getPrintfulVariantsMapped().  The real product can be extracted
		// by using products/ID or products/variant/ID.  We can add another function like
		// getRealPrintfulProducts() if it becomes needed.

		$products = $this->apiGet('products');

		if ($products === false) {
			$this->pushErrorMessage("Failed to get Printful products");
			return false;
		}

		return $products;
	}

	private function getPrintfulSyncProducts() {
		// Query results of sync products (not sync variants) are paged.
		// Documentation on the "limit" parameter: "Number of items per page (max 100)"

		$offset = 0;
		$limit = 100;
		$returnArray = array();
		do {
			$result = $this->apiGet('sync/products', array("limit" => $limit, "offset" => $offset));
			if ($result === false) {
				$this->pushErrorMessage("Failed to get Printful Sync Products");
				return false;
			}
			$returnArray = array_merge($returnArray, $result);
			$total = $this->iApi->getItemCount();
			$offset += $limit;
		} while (count($returnArray) < $total);

		return $returnArray;
	}

	private function expandPrintfulVariants(&$printfulSyncVariants) {
		// Gather all product IDs that will be used as targets for extracting variants.  I think
		// this strategy is faster than extracting each variant of a Sync Variant one by one
		// even if they are cached.

		// Unsynced sync variants are implicitly ignored here, and calling functions should be aware of them.
		// They cause no-variant or no-product errors.

		$printfulProductIds = array();

		foreach ($printfulSyncVariants as $printfulSyncVariant) {
			if (!empty($printfulSyncVariant['synced'])) {
				if (empty($printfulSyncVariant['product']['product_id'])) {
					$this->pushErrorMessage(sprintf("Sync Variant %s (ID: %s) is not provided with a Product ID.", $printfulSyncVariant['name'], $printfulSyncVariant['id']));
					return false;
				}

				$printfulProductId = $printfulSyncVariant['product']['product_id'];
				$printfulProductIds[$printfulProductId] = $printfulProductId;
			}
		}

		// Get mapped Printful Variants.

		$printfulVariantsMap = $this->getPrintfulVariantsMapped($printfulProductIds);

		if ($printfulVariantsMap === false) {
			$this->pushErrorMessage("Failed to expand Printful Variants");
			return false;
		}

		// Verify that all required variants were extracted.

		foreach ($printfulSyncVariants as &$printfulSyncVariant) {
			if (!empty($printfulSyncVariant['synced'])) {
				if (empty($printfulSyncVariant['variant_id'])) {
					$this->pushErrorMessage("Unable to extract 'variant_id' from Sync Variant " .
						$printfulSyncVariant['id'] . ".");
					continue;
				}

				$printfulVariantId = $printfulSyncVariant['variant_id'];

				if (!empty($printfulVariantsMap[$printfulVariantId])) {
					$printfulVariant = $printfulVariantsMap[$printfulVariantId];
				} else {
					$printfulVariant = $this->apiGet('products/variant/' . $printfulVariantId);

					if ($printfulVariant === false) {
						$this->pushErrorMessage("Failed to extract Printful Variant " . $printfulVariantId);
						continue;
					}

					if ($printfulVariant['id'] != $printfulVariantId) {
						$this->pushErrorMessage(sprintf("Variant IDs '%s' and '%s' don't match.",
							$printfulVariant['id'], $printfulVariantId));
						continue;
					}
				}

				$printfulSyncVariant['variant'] = $printfulVariant;
			}
		}

		return true;
	}

	private function getPrintfulVariantsMapped($printfulProductIds = null) {
		$printfulVariants = $this->getPrintfulVariants($printfulProductIds);

		if ($printfulVariants === false) {
			$this->pushErrorMessage("Failed to get mapped Printful Variants");
			return false;
		}

		$printfulVariantsMap = array();

		foreach ($printfulVariants as $printfulVariant) {
			$printfulVariantId = $printfulVariant['id'];

			if (array_key_exists($printfulVariantId, $printfulVariantsMap)) {
				$this->pushErrorMessage("Duplicate Variant ID detected: " . $printfulVariantId);
				return false;
			}

			$printfulVariantsMap[$printfulVariantId] = $printfulVariant;
		}

		return $printfulVariantsMap;
	}

	private function collectPrintfulProductIds($printfulProducts) {
		$printfulProductIds = array();

		foreach ($printfulProducts as $printfulProduct) {
			if (empty($printfulProduct['id'])) {
				$this->pushErrorMessage("A product data has no ID.");
				return false;
			}

			$printfulProductIds[] = $printfulProduct['id'];
		}

		return $printfulProductIds;
	}

	private function getPrintfulVariants($printfulProductIds = null) {
		if ($printfulProductIds === null) {
			$printfulProducts = $this->getPrintfulProducts();

			if ($printfulProducts === false) {
				$this->pushErrorMessage("Failed to get Printful Variants");
				return false;
			}

			$printfulProductIds = $this->collectPrintfulProductIds($printfulProducts);

			if ($printfulProductIds === false) {
				$this->pushErrorMessage("Failed to get Printful Variants");
				return false;
			}
		}

		$printfulVariants = array();
		$processed = array();

		foreach ($printfulProductIds as $printfulProductId) {
			$result = $this->apiGet('products/' . $printfulProductId);

			if ($result === false) {
				$this->pushErrorMessage("Failed to get product info");
				continue;
			}

			if (empty($result['product']['id'])) {
				$this->pushErrorMessage("Product data has no real ID.");
				continue;
			}

			$realPrintfulProduct = $result['product'];
			$realPrintfulProductId = $result['product']['id'];

			if (!array_key_exists($realPrintfulProductId, $processed)) {
				$processed[$realPrintfulProductId] = true;

				foreach ($result['variants'] as $printfulVariant) {
					$printfulVariant['product'] =& $realPrintfulProduct;
					$printfulVariants[] = $printfulVariant;
				}
			}
		}

		return $printfulVariants;
	}

	private function getPrintfulProductsMapped() {
		$printfulProducts = $this->getPrintfulProducts();

		if ($printfulProducts === false) {
			$this->pushErrorMessage("Failed to get mapped Printful products");
			return false;
		}

		$printfulProductsMap = array();

		foreach ($printfulProducts as $printfulProduct) {
			if (empty($printfulProduct['id'])) {
				$this->pushErrorMessage("Product data has no ID.");
				return false;
			}

			$printfulProductId = $printfulProduct['id'];

			if (array_key_exists($printfulProductId, $printfulProductsMap)) {
				$this->pushErrorMessage("Duplicate Product ID detected: " . $printfulProductId);
				return false;
			}

			$printfulProductsMap[$printfulProductId] = $printfulProduct;
		}

		return $printfulProductsMap;
	}

	private static function calculateBaseCost($printfulSyncVariant) {
		$printfulVariant = $printfulSyncVariant['variant'];
		$baseCost = (float)$printfulVariant['price'];
		$flags = array();

		foreach ($printfulSyncVariant['files'] as $file) {
			$fileType = $file['type'];
			$flags[$fileType] = true;
		}

		foreach ($printfulVariant['product']['files'] as $file) {
			$fileType = $file['type'];

			if (!empty($flags[$fileType]) && !empty($file['additional_price'])) {
				$baseCost += (float)$file['additional_price'];
			}
		}

		return round($baseCost, 2);
	}

	private static function createUniqueProductCode($printfulExternalSyncVariantId) {
		$productCode = $baseProductCode = "PRINTFUL_" . strtoupper($printfulExternalSyncVariantId);
		$suffix = 0;

		while (getFieldFromId("product_id", "products", "product_code", $productCode)) {
			$productCode = $baseProductCode . "_" . ++$suffix;
		}

		return $productCode;
	}

	private static function createUniqueLinkName($description) {
		$linkName = $baseLinkName = makeCode($description, array("use_dash" => true, "lowercase" => true));
		$suffix = 0;

		while (getFieldFromId("product_id", "products", "link_name", $linkName)) {
			$linkName = $baseLinkName . "-" . ++$suffix;
		}

		return $linkName;
	}

	private static function createUniqueImageCode($printfulImageId) {
		$imageCode = $baseImageCode = "PRINTFUL_" . $printfulImageId;
		$suffix = 0;

		while (getFieldFromId("image_id", "images", "image_code", $imageCode)) {
			$imageCode = $baseImageCode . "_" . ++$suffix;
		}

		return $imageCode;
	}

	private static function getRemoteFileSize($url) {
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_NOBODY, true);
		curl_setopt($curl, CURLOPT_HEADER, true);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		curl_exec($curl);
		$status = curl_getinfo($curl, CURLINFO_HTTP_CODE) ?: 0;
		$contentLength = curl_getinfo($curl, CURLINFO_CONTENT_LENGTH_DOWNLOAD) ?: 0;
		curl_close($curl);

		if ($status == 200 || ($status > 300 && $status <= 308)) {
			return $contentLength;
		}
		return false;
	}

	private function addOrUpdateProductGroupVariant($productId, $productGroupCode, $productGroupDescription, $attributes, $deassignInactiveOptions = true) {
		// Get product_group_id.

		$productGroupId = getFieldFromId('product_group_id', 'product_groups', 'product_group_code', $productGroupCode);

		if (empty($productGroupId)) {
			// Create new product group.

			$insertSet = executeQuery("insert ignore into product_groups (client_id,product_group_code,description) " .
				"values (?,?,?)", $GLOBALS['gClientId'], $productGroupCode, $productGroupDescription);

			if (!empty($errorMessage = $insertSet['sql_error'])) {
				$this->pushErrorMessage("Error occurred while adding product group: " . $errorMessage);
				return false;
			}

			$productGroupId = $insertSet['insert_id'];

			if (empty($productGroupId)) {
				$productGroupId = getFieldFromId('product_group_id', 'product_groups', 'product_group_code',
					$productGroupCode);
			}

			if (empty($productGroupId)) {
				$this->pushErrorMessage("Unable to get product group ID of just-created product group.");
				return false;
			}
		}

		// Get product_group_variant_id.

		$productGroupVariantId = getFieldFromId("product_group_variant_id", "product_group_variants", "product_id",
			$productId, "product_group_id = ?", $productGroupId);

		if (empty($productGroupVariantId)) {
			// Remove product's old group variant - including the choices.

			executeQuery("delete from product_group_variant_choices where product_group_variant_id in " .
				"(select product_group_variant_id from product_group_variants where product_id = ?)", $productId);
			executeQuery("delete from product_group_variants where product_id = ?", $productId);

			// Create new product group variant.

			$insertSet = executeQuery("insert into product_group_variants (product_group_id,product_id) values (?,?)",
				$productGroupId, $productId);

			if (!empty($errorMessage = $insertSet['sql_error'])) {
				$this->pushErrorMessage("Error occurred while adding product group variant: " . $errorMessage);
				return false;
			}

			if (empty($productGroupVariantId = $insertSet['insert_id'])) {
				$this->pushErrorMessage("Unable to get product group variant ID of just-created product group.");
				return false;
			}
		}

		// Process options and choices.

		$activeProductOptionsMap = array();

		foreach ($attributes as $attributeKey => $attributeValue) {
			// Get product_option_id.

			$productOptionCode = makeCode($attributeKey);
			$productOptionId = getFieldFromId("product_option_id", "product_options", "product_option_code",
				$productOptionCode, "client_id = ?", $GLOBALS['gClientId']);

			if (empty($productOptionId)) {
				// Create new product option.

				$insertSet = executeQuery("insert into product_options (client_id,product_option_code,description) " .
					"values (?,?,?)", $GLOBALS['gClientId'], $productOptionCode,
					ucwords(strtolower($attributeKey)));

				if (!empty($errorMessage = $insertSet['sql_error'])) {
					$this->pushErrorMessage("Error occurred while adding product option: " . $errorMessage);
					return false;
				}

				if (empty($productOptionId = $insertSet['insert_id'])) {
					$this->pushErrorMessage("Unable to get product option ID of just-created product option.");
					return false;
				}
			}

			// Get product_option_choice_id.

			$productOptionChoiceDescription = convertSmartQuotes($attributeValue);
			$productOptionChoiceId = getFieldFromId("product_option_choice_id",
				"product_option_choices", "product_option_id", $productOptionId, "description = ?",
				$productOptionChoiceDescription);

			if (empty($productOptionChoiceId)) {
				// Create new product option choice.

				$insertSet = executeQuery("insert into product_option_choices (product_option_id,description) " .
					"values (?,?)", $productOptionId, $productOptionChoiceDescription);

				if (!empty($errorMessage = $insertSet['sql_error'])) {
					$this->pushErrorMessage("Error occurred while adding product option choice: " . $errorMessage);
					return false;
				}

				if (empty($productOptionChoiceId = $insertSet['insert_id'])) {
					$this->pushErrorMessage("Unable to get product option choice ID of just-created product option " .
						"choice.");
					return false;
				}
			}

			// Get product_group_variant_choice_id and product_option_choice_id.

			$productGroupVariantChoiceInfo = getMultipleFieldsFromId(
				array("product_group_variant_choice_id", "product_option_choice_id"),
				"product_group_variant_choices", "product_group_variant_id", $productGroupVariantId,
				"product_option_id = ?", $productOptionId);

			if (empty($productGroupVariantChoiceInfo['product_group_variant_choice_id'])) {
				// Create new product group variant choice for this product.

				$insertSet = executeQuery("insert into product_group_variant_choices " .
					"(product_group_variant_id,product_option_id,product_option_choice_id) values (?,?,?)",
					$productGroupVariantId, $productOptionId, $productOptionChoiceId);

				if (!empty($errorMessage = $insertSet['sql_error'])) {
					$this->pushErrorMessage("Error occurred while adding product group variant choice: " .
						$errorMessage);
					return false;
				}
			} elseif (!($productGroupVariantChoiceInfo['product_option_choice_id'] == $productOptionChoiceId)) {
				// Change the product_option_choice of this product's variant.

				$resultSet = executeQuery("update product_group_variant_choices set product_option_choice_id = ? " .
					"where product_group_variant_choice_id = ?", $productOptionChoiceId,
					$productGroupVariantChoiceInfo['product_group_variant_choice_id']);

				if (!empty($errorMessage = $resultSet['sql_error'])) {
					$this->pushErrorMessage("Error occurred while updating product option choice: " . $errorMessage);
					return false;
				}
			}

			// Finally add a product group option for this product group if it doesn't exist yet.

			if (!getFieldFromId("product_group_option_id", "product_group_options", "product_group_id", $productGroupId,
				"product_option_id = ?", $productOptionId)) {
				$insertSet = executeQuery("insert ignore into product_group_options " .
					"(product_group_id,product_option_id) values (?,?)", $productGroupId, $productOptionId);

				if (!empty($errorMessage = $insertSet['sql_error'])) {
					$this->pushErrorMessage("Error occurred while adding product group option: " . $errorMessage);
					return false;
				}
			}

			$activeProductOptionsMap[$productOptionId] = true;
		}

		// Deassign inactive product options.

		if ($deassignInactiveOptions) {
			$resultSet = executeQuery("select product_group_variant_choice_id,product_option_id " .
				"from product_group_variant_choices where product_group_variant_id = ?", $productGroupVariantId);

			while ($row = getNextRow($resultSet)) {
				if (!array_key_exists($row['product_option_id'], $activeProductOptionsMap)) {
					executeQuery("delete from product_group_variant_choices where product_group_variant_choice_id = ?",
						$row['product_group_variant_choice_id']);
				}
			}

			freeResult($resultSet);
		}

		return true;
	}

	private function createPrintfulOrder($orderId, $recipient, $orderItemRows) {
		$requestBody = array();

		if (!$GLOBALS['gDevelopmentServer']) {
			$requestBody['external_id'] = $orderId;
		}

		$requestBody['recipient'] = $recipient;
		$requestBodyItems = array();

		foreach ($orderItemRows as $orderItemRow) {
			$productRow = $orderItemRow['product_row'];
			$printfulSyncVariantId = $orderItemRow['distributor_product_code'];
			$requestBodyItems[] = array(
				'sync_variant_id' => $printfulSyncVariantId,
				'quantity' => $orderItemRow['quantity'],
				'name' => $productRow['description'],
				'retail_price' => $orderItemRow['sale_price']
			);
		}

		$requestBody['items'] = $requestBodyItems;

		// From the data format description of 'retail_costs' in 'https://www.printful.com/docs/orders#actionCreate':
		//
		// "Retail costs that are to be displayed on the packing slip for international shipments. Retail costs are
		//  used only if every item in order contains the retail_price attribute."
		//
		// $requestBody['retail_costs']['shipping'] = ...;

		// TODO: Shipping needs to be thoroughly reviewed and its current strategy should be documented in detail in
		//       this implementation.  Ideally the shipping cost should be dynamically calculated before checkout
		//       using 'https://www.printful.com/docs/shipping#actionRates'.
		//
		//       Pricing strategies: https://www.printful.com/blog/ecommerce-shipping-pricing/
		//       Printful's documentation on shipping rates: https://www.printful.com/shipping

		try {
			$result = $this->api()->post('orders', $requestBody);
		} catch (PrintfulException $e) {
			$this->pushErrorMessage($e, "Request to create draft Printful order failed");
			return false;
		}

		if ($result['status'] != 'draft') {
			$this->pushErrorMessage("Status of created draft Printful order expected to be 'draft', but isn't: " .
				$result['status']);
			return false;
		}

		return $result['id'];
	}

	private function cancelPrintfulOrder($printfulOrderId) {
		if (!empty($printfulOrderId)) {
			try {
				$this->api()->delete('orders/' . $printfulOrderId);
			} catch (PrintfulException $e) {
				self::logError($e, "Failed to cancel remote Printful order " . $printfulOrderId);
				return false;
			}
		}

		return true;
	}

	private function confirmPrintfulOrder($printfulOrderId) {
		try {
			$result = $this->api()->post('orders/' . $printfulOrderId . '/confirm');
		} catch (PrintfulException $e) {
			$this->pushErrorMessage($e, "Failed to submit remote Printful order " . $printfulOrderId .
				" for fulfillment");
			return false;
		}

		return !empty($result['status']) && $result['status'] != 'failed' && $result['status'] != "canceled";
	}

	private function saveOrderData($orderType, $orderId, $orderItemRows, $remoteOrderId, $shipTo) {
		$orderSet = executeQuery("insert into remote_orders (order_id,order_number) values (?,?)", $orderId,
			$remoteOrderId);
		$remoteOrderId = $orderSet['insert_id'];

		foreach ($orderItemRows as $thisOrderItemRow) {
			executeQuery("insert into remote_order_items (remote_order_id,order_item_id,quantity) values (?,?,?)",
				$remoteOrderId, $thisOrderItemRow['order_item_id'], $thisOrderItemRow['quantity']);
		}

		return array("order_type" => $orderType, "remote_order_id" => $remoteOrderId, "order_number" => $remoteOrderId,
			"ship_to" => $shipTo);
	}

	private function getPrintfulOrderInfo($printfulOrderId) {
		$orderInfo = $this->apiGet('orders/' . $printfulOrderId);

		if ($orderInfo === false) {
			$this->pushErrorMessage("Failed to get information on remote Printful order " . $printfulOrderId);
			return false;
		}

		return $orderInfo;
	}

	private static function getShippingCarrierCode($carrier, $default = null) {
		// TODO: Take a look at other carrier services used by Printful.

		if (stripos($carrier, 'FedEx') || stripos($carrier, 'Federal Express')) {
			return "FEDEX";
		} elseif (stripos($carrier, 'USPS') || stripos($carrier, 'United States Postal Service')) {
			return "USPS";
		} elseif (stripos($carrier, 'UPS') || stripos($carrier, 'United Parcel Service')) {
			return "UPS";
		} else {
			return $default;
		}
	}
}
