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

class SimpleImage {

	var $iImage;
	var $iMimeType;
	var $iExifData = array();
	var $iCompression = 70;
	var $iOriginalFile = "";

	public function __construct() {
		if (extension_loaded('gd')) {
			ini_set('gd.jpeg_ignore_warning', 1);
		} else {
			return false;
		}
	}

	public static function reduceImageSize($imageId, $parameters = array()) {
		$imageRow = $parameters['image_row'];
		if (empty($imageRow)) {
			$imageRow = getRowFromId("images", "image_id", $imageId);
		}
		if (empty($imageRow)) {
			return false;
		}
		$imageDataTypeId = getFieldFromId("image_data_type_id", "image_data_types", "image_data_type_code", "DO_NOT_AUTO_RESIZE");
		if (empty($imageDataTypeId)) {
			$resultSet = executeQuery("insert into image_data_types (client_id,image_data_type_code,description,data_type) values (?,'DO_NOT_AUTO_RESIZE','Do Not Auto Resize','tinyint')", $GLOBALS['gClientId']);
			$imageDataTypeId = $resultSet['insert_id'];
		}
		if (empty($parameters['max_image_dimension'])) {
			$parameters['max_image_dimension'] = 1600;
		}
		$imageFilename = getImageFilename($imageId);
		$extension = $imageRow['extension'];
		$filename = $GLOBALS['gDocumentRoot'] . $imageFilename;
		$originalSize = $imageRow['image_size'];
		$image = new SimpleImage();
		if (!empty($parameters['compression'])) {
			$image->iCompression = $parameters['compression'];
		}
		$image->loadImage($filename);
		$image->resizeMax($parameters['max_image_dimension'], $parameters['max_image_dimension']);
		$image->saveImage($filename, (empty($extension) || !empty($parameters['convert']) ? "jpg" : $extension));
		$fileContent = file_get_contents($filename);
		$imageRow['image_size'] = strlen($fileContent);
		$maxDBSize = getPreference("EXTERNAL_FILE_SIZE");
		$newExtension = (empty($extension) || !empty($parameters['convert']) ? "jpg" : $extension);
		if (empty($maxDBSize) || !is_numeric($maxDBSize)) {
			$maxDBSize = 1000000;
		}
		if ($imageRow['image_size'] < $maxDBSize) {
			$imageRow['os_filename'] = "";
			$imageRow['file_content'] = $fileContent;
			$updateSet = executeQuery("update images set hash_code = null,image_size = ?,os_filename = ?,file_content = ?,extension = ? where image_id = ?",
				$imageRow['image_size'], $imageRow['os_filename'], $imageRow['file_content'], $newExtension, $imageRow['image_id']);
		} else {
			$imageRow['file_content'] = "";
			$imageRow['os_filename'] = putExternalImageContents($imageRow['image_id'], $newExtension, $fileContent);
			$updateSet = executeQuery("update images set hash_code = null,image_size = ?,os_filename = ?,file_content = ?,extension = ? where image_id = ?",
				$imageRow['image_size'], $imageRow['os_filename'], $imageRow['file_content'], $newExtension, $imageRow['image_id']);
		}
		if (empty($updateSet['sql_error'])) {
			executeQuery("delete from image_data where image_id = ? and image_data_type_id = ?", $imageRow['image_id'], $imageDataTypeId);
			executeQuery("insert into image_data (image_id,image_data_type_id,text_data) values (?,?,'1')", $imageRow['image_id'], $imageDataTypeId);
		}
		$totalSavings = $originalSize - $imageRow['image_size'];
		getImageFilename($imageRow['image_id']);
		return $totalSavings;
	}

	public function loadImage($file) {
		$handle = @fopen($file, 'r');
		if ($handle === false) {
			return false;
		}
		fclose($handle);
		$this->iOriginalFile = $file;

		// Get image info
		$info = getimagesize($file);
		if ($info === false) {
			return false;
		}
		$this->iMimeType = $info['mime'];

		// Create image object from file
		switch ($this->iMimeType) {
			case 'image/gif':
				// Load the gif
				$gif = imagecreatefromgif($file);
				if ($gif) {
					// Copy the gif over to a true color image to preserve its transparency. This is a
					// workaround to prevent imagepalettetruecolor() from borking transparency.
					$width = imagesx($gif);
					$height = imagesy($gif);
					$this->iImage = imagecreatetruecolor($width, $height);
					$transparentColor = imagecolorallocatealpha($this->iImage, 0, 0, 0, 127);
					imagecolortransparent($this->iImage, $transparentColor);
					imagefill($this->iImage, 0, 0, $transparentColor);
					imagecopy($this->iImage, $gif, 0, 0, 0, 0, $width, $height);
					imagedestroy($gif);
				}
				break;
			case 'image/jpeg':
				$this->iImage = imagecreatefromjpeg($file);
				break;
			case 'image/png':
				$this->iImage = imagecreatefrompng($file);
				break;
		}
		if (!$this->iImage) {
			return false;
		}

		// Convert pallete images to true color images
		imagepalettetotruecolor($this->iImage);

		// Load exif data from JPEG images
		if ($this->iMimeType === 'image/jpeg' && function_exists('exif_read_data')) {
			try {
				$GLOBALS['gIgnoreError'] = true;
				$this->iExifData = @exif_read_data($file);
				$GLOBALS['gIgnoreError'] = false;
			} catch (Exception $e) {
				$this->iExifData = array();
			}
		}

		return $this;
	}

	public function resizeMax($maxWidth, $maxHeight) {
		if (empty($this->iImage)) {
			return false;
		}
		if (empty($maxWidth)) {
			$maxWidth = $this->getWidth();
		}
		if (empty($maxHeight)) {
			$maxHeight = $this->getHeight();
		}
		// If the image already fits, there's nothing to do
		if ($this->getWidth() <= $maxWidth && $this->getHeight() <= $maxHeight) {
			return $this;
		}

		// Calculate max width or height based on orientation
		if ($this->getOrientation() === 'portrait') {
			$height = $maxHeight;
			$width = $maxHeight * $this->getAspectRatio();
		} else {
			$width = $maxWidth;
			$height = $maxWidth / $this->getAspectRatio();
		}

		// Reduce to max width
		if ($width > $maxWidth) {
			$width = $maxWidth;
			$height = $width / $this->getAspectRatio();
		}

		// Reduce to max height
		if ($height > $maxHeight) {
			$height = $maxHeight;
			$width = $height * $this->getAspectRatio();
		}

		return $this->resize($width, $height);
	}

	public function getOrientation() {
		$width = $this->getWidth();
		$height = $this->getHeight();

		if ($width > $height) {
			return 'landscape';
		}
		if ($width < $height) {
			return 'portrait';
		}
		return 'square';
	}

	//
	// Ensures a numeric value is always within the min and max range.
	//
	//	$value* (int|float) - A numeric value to test.
	//	$min* (int|float) - The minimum allowed value.
	//	$max* (int|float) - The maximum allowed value.
	//
	// Returns an int|float value.
	//

	public function saveImage($file, $extension = null) {
		if (empty($this->iImage)) {
			if (!empty($this->iOriginalFile)) {
				$contents = file_get_contents($this->iOriginalFile);
				file_put_contents($file, $contents);
			}
			return true;
		}
		$mimeType = "";
		switch (strtolower($extension)) {
			case "png":
				$mimeType = "image/png";
				break;
			case "gif":
				$mimeType = "image/gif";
				break;
			case "jpg":
				$mimeType = "image/jpeg";
				break;
		}
		$image = $this->generate($mimeType);
		if ($image === false) {
			return false;
		}

		// Save the image to file
		if (!file_put_contents($file, $image['data'])) {
			return false;
		}

		return $this;
	}

	//
	// Gets the image's current aspect ratio.
	//
	// Returns the aspect ratio as a float.
	//

	private function generate($mimeType = null) {
		// Format defaults to the original mime type
		$mimeType = $mimeType ?: $this->iMimeType;

		// Ensure quality is a valid integer
		if ($this->iCompression === null) {
			$this->iCompression = 100;
		}
		$this->iCompression = self::keepWithin((int)$this->iCompression, 0, 100);

		// Capture output
		ob_start();

		// Generate the image
		switch ($mimeType) {
			case 'image/gif':
				imagesavealpha($this->iImage, true);
				imagegif($this->iImage, null);
				break;
			case 'image/jpeg':
				imageinterlace($this->iImage, true);
				imagejpeg($this->iImage, null, $this->iCompression);
				break;
			case 'image/png':
				imagesavealpha($this->iImage, true);
				imagepng($this->iImage, null, round(9 * $this->iCompression / 100));
				break;
			default:
				return false;
		}

		// Stop capturing
		$data = ob_get_contents();
		ob_end_clean();

		return [
			'data' => $data,
			'mimeType' => $mimeType
		];
	}

	//
	// Gets the image's exif data.
	//
	// Returns an array of exif data or null if no data is available.
	//

	public function __destruct() {
		if ($this->iImage !== null && is_resource($this->iImage) && gettype($this->iImage) == "object" && get_class($this->iImage) == "GdImage") {
			imagedestroy($this->iImage);
		}
	}

	//
	// Gets the image's current height.
	//
	// Returns the height as an integer.
	//

	public function getMimeType() {
		return $this->iMimeType;
	}

	//
	// Gets the image's current orientation.
	//
	// Returns a string: 'landscape', 'portrait', or 'square'
	//

	public function getExif() {
		return isset($this->iExifData) ? $this->iExifData : null;
	}

	private static function keepWithin($value, $min, $max) {
		if ($value < $min) {
			return $min;
		}
		if ($value > $max) {
			return $max;
		}
		return $value;
	}

	//
	// Proportionally resize the image to fit inside a specific width and height.
	//
	//	$maxWidth* (int) - The maximum width the image can be.
	//	$maxHeight* (int) - The maximum height the image can be.
	//
	// Returns a SimpleImage object.
	//

	function duotone($lightColor, $darkColor) {
		$lightColor = self::normalizeColor($lightColor);
		$darkColor = self::normalizeColor($darkColor);

		// Calculate averages between light and dark colors
		$redAvg = $lightColor['red'] - $darkColor['red'];
		$greenAvg = $lightColor['green'] - $darkColor['green'];
		$blueAvg = $lightColor['blue'] - $darkColor['blue'];

		// Create a matrix of all possible duotone colors based on gray values
		$pixels = [];
		for ($i = 0; $i <= 255; $i++) {
			$grayAvg = $i / 255;
			$pixels['red'][$i] = $darkColor['red'] + $grayAvg * $redAvg;
			$pixels['green'][$i] = $darkColor['green'] + $grayAvg * $greenAvg;
			$pixels['blue'][$i] = $darkColor['blue'] + $grayAvg * $blueAvg;
		}

		// Apply the filter pixel by pixel
		for ($x = 0; $x < $this->getWidth(); $x++) {
			for ($y = 0; $y < $this->getHeight(); $y++) {
				$rgb = $this->getColorAt($x, $y);
				$gray = min(255, round(0.299 * $rgb['red'] + 0.114 * $rgb['blue'] + 0.587 * $rgb['green']));
				$this->dot($x, $y, [
					'red' => $pixels['red'][$gray],
					'green' => $pixels['green'][$gray],
					'blue' => $pixels['blue'][$gray]
				]);
			}
		}

		return $this;
	}

	//
	// Crop the image.
	//
	//	$x1 - Top left x coordinate.
	//	$y1 - Top left y coordinate.
	//	$x2 - Bottom right x coordinate.
	//	$y2 - Bottom right x coordinate.
	//
	// Returns a SimpleImage object.
	//

	public function getWidth() {
		return (int)imagesx($this->iImage);
	}

	//
	// Applies a duotone filter to the image.
	//
	//	$lightColor* (string|array) - The lightest color in the duotone.
	//	$darkColor* (string|array) - The darkest color in the duotone.
	//
	// Returns a SimpleImage object.
	//

	public function getHeight() {
		return (int)imagesy($this->iImage);
	}

	//
	// Proportionally resize the image to a specific height.
	//
	//	$height* (int) - The height to resize the image to.
	//
	// Returns a SimpleImage object.
	//

	public function resizeToHeight($height) {
		return $this->resize(null, $height);
	}

	//
	// Proportionally resize the image to a specific width.
	//
	//	$width* (int) - The width to resize the image to.
	//
	// Returns a SimpleImage object.
	//

	public function resize($width = null, $height = null) {
		// No dimentions specified
		if (!$width && !$height) {
			return $this;
		}

		if ($width < 1 || $height < 1) {
			return $this;
		}

		// Resize to width
		if ($width && !$height) {
			$height = $width / $this->getAspectRatio();
		}

		// Resize to height
		if (!$width && $height) {
			$width = $height * $this->getAspectRatio();
		}

		// If the dimensions are the same, there's no need to resize
		if ($this->getWidth() === $width && $this->getHeight() === $height) {
			return $this;
		}

		// We can't use imagescale because it doesn't seem to preserve transparency properly. The
		// workaround is to create a new truecolor image, allocate a transparent color, and copy the
		// image over to it using imagecopyresampled.
		$newImage = imagecreatetruecolor($width, $height);
		$transparentColor = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
		imagecolortransparent($newImage, $transparentColor);
		imagefill($newImage, 0, 0, $transparentColor);
		imagecopyresampled(
			$newImage,
			$this->iImage,
			0, 0, 0, 0,
			$width,
			$height,
			$this->getWidth(),
			$this->getHeight()
		);

		// Swap out the new image
		$this->iImage = $newImage;

		return $this;
	}

	//
	// Flip the image horizontally or vertically.
	//
	//	$direction* (string) - The direction to flip: x|y|both
	//
	// Returns a SimpleImage object.
	//

	public function getAspectRatio() {
		return $this->getWidth() / $this->getHeight();
	}

	//
	// Place an image on top of the current image.
	//
	//	$overlay* (string|SimpleImage) - The image to overlay. This can be a filename, a data URI, or
	//		a SimpleImage object.
	//	$anchor (string) - The anchor point: 'center', 'top', 'bottom', 'left', 'right', 'top left',
	//		'top right', 'bottom left', 'bottom right' (default 'center')
	//	$opacity (float) - The opacity level of the overlay 0-1 (default 1).
	//	$xOffset (int) - Horizontal offset in pixels (default 0).
	//	$yOffset (int) - Vertical offset in pixels (default 0).
	//
	// Returns a SimpleImage object.
	//

	public function resizeToWidth($width) {
		return $this->resize($width, null);
	}

	//
	// Resize an image to the specified dimensions. If only one dimension is specified, the image will
	// be resized proportionally.
	//
	//	$width* (int) - The new image width.
	//	$height* (int) - The new image height.
	//
	// Returns a SimpleImage object.
	//

	public function overlay($overlay, $anchor = 'center', $opacity = 1, $xOffset = 0, $yOffset = 0) {
		// Load overlay image
		if (!($overlay instanceof SimpleImage)) {
			$overlay = new SimpleImage($overlay);
		}

		// Convert opacity
		$opacity = self::keepWithin($opacity, 0, 1) * 100;

		// Determine placement
		switch ($anchor) {
			case 'top left':
				$x = $xOffset;
				$y = $yOffset;
				break;
			case 'top right':
				$x = $this->getWidth() - $overlay->getWidth() + $xOffset;
				$y = $yOffset;
				break;
			case 'top':
				$x = ($this->getWidth() / 2) - ($overlay->getWidth() / 2) + $xOffset;
				$y = $yOffset;
				break;
			case 'bottom left':
				$x = $xOffset;
				$y = $this->getHeight() - $overlay->getHeight() + $yOffset;
				break;
			case 'bottom right':
				$x = $this->getWidth() - $overlay->getWidth() + $xOffset;
				$y = $this->getHeight() - $overlay->getHeight() + $yOffset;
				break;
			case 'bottom':
				$x = ($this->getWidth() / 2) - ($overlay->getWidth() / 2) + $xOffset;
				$y = $this->getHeight() - $overlay->getHeight() + $yOffset;
				break;
			case 'left':
				$x = $xOffset;
				$y = ($this->getHeight() / 2) - ($overlay->getHeight() / 2) + $yOffset;
				break;
			case 'right':
				$x = $this->getWidth() - $overlay->getWidth() + $xOffset;
				$y = ($this->getHeight() / 2) - ($overlay->getHeight() / 2) + $yOffset;
				break;
			default:
				$x = ($this->getWidth() / 2) - ($overlay->getWidth() / 2) + $xOffset;
				$y = ($this->getHeight() / 2) - ($overlay->getHeight() / 2) + $yOffset;
				break;
		}

		// Perform the overlay
		self::imageCopyMergeAlpha(
			$this->iImage,
			$overlay->image,
			$x, $y,
			0, 0,
			$overlay->getWidth(),
			$overlay->getHeight(),
			$opacity
		);

		return $this;
	}

	private static function imageCopyMergeAlpha($dstIm, $srcIm, $dstX, $dstY, $srcX, $srcY, $srcW, $srcH, $pct) {
		// Are we merging with transparency?
		if ($pct < 100) {
			// Disable alpha blending and "colorize" the image using a transparent color
			imagealphablending($srcIm, false);
			imagefilter($srcIm, IMG_FILTER_COLORIZE, 0, 0, 0, 127 * ((100 - $pct) / 100));
		}

		imagecopy($dstIm, $srcIm, $dstX, $dstY, $srcX, $srcY, $srcW, $srcH);

		return true;
	}

	//
	// Adds text to the image.
	//
	//	$text* (string) - The desired text.
	//	$options (array) - An array of options.
	//		- fontFile* (string) - The TrueType (or compatible) font file to use.
	//		- size (int) - The size of the font in pixels (default 12).
	//		- color (string|array) - The text color (default black).
	//		- anchor (string) - The anchor point: 'center', 'top', 'bottom', 'left', 'right',
	//			'top left', 'top right', 'bottom left', 'bottom right' (default 'center').
	//		- xOffset (int) - The horizontal offset in pixels (default 0).
	//		- yOffset (int) - The vertical offset in pixels (default 0).
	//		- shadow (array) - Text shadow params.
	//			- x* (int) - Horizontal offset in pixels.
	//			- y* (int) - Vertical offset in pixels.
	//			- color* (string|array) - The text shadow color.
	//	$boundary (array) - If passed, this variable will contain an array with coordinates that
	//		surround the text: [x1, y1, x2, y2, width, height]. This can be used for calculating the
	//		text's position after it gets added to the image.
	//
	// Returns a SimpleImage object.
	//

	public function text($text, $options, &$boundary = null) {
		// Check for freetype support
		if (!function_exists('imagettftext')) {
			return false;
		}

		// Default options
		$options = array_merge([
			'fontFile' => null,
			'size' => 12,
			'color' => 'black',
			'anchor' => 'center',
			'xOffset' => 0,
			'yOffset' => 0,
			'shadow' => null
		], $options);

		// Extract and normalize options
		$fontFile = $options['fontFile'];
		$size = ($options['size'] / 96) * 72; // Convert px to pt (72pt per inch, 96px per inch)
		$color = $this->allocateColor($options['color']);
		$anchor = $options['anchor'];
		$xOffset = $options['xOffset'];
		$yOffset = $options['yOffset'];
		$angle = 0;

		// Calculate the bounding box dimensions
		//
		// Since imagettfbox() returns a bounding box from the text's baseline, we can end up with
		// different heights for different strings of the same font size. For example, 'type' will often
		// be taller than 'text' because the former has a descending letter.
		//
		// To compensate for this, we create two bounding boxes: one to measure the cap height and
		// another to measure the descender height. Based on that, we can adjust the text vertically
		// to appear inside the box with a reasonable amount of consistency.
		//
		// See: https://github.com/claviska/SimpleImage/issues/165
		//
		$box = imagettfbbox($size, $angle, $fontFile, $text);
		if (!$box) {
			return false;
		}
		$boxWidth = abs($box[6] - $box[2]);
		$boxHeight = $options['size'];

		// Determine cap height
		$box = imagettfbbox($size, $angle, $fontFile, 'X');
		$capHeight = abs($box[7] - $box[1]);

		// Determine descender height
		$box = imagettfbbox($size, $angle, $fontFile, 'X Qgjpqy');
		$fullHeight = abs($box[7] - $box[1]);
		$descenderHeight = $fullHeight - $capHeight;

		// Determine position
		switch ($anchor) {
			case 'top left':
				$x = $xOffset;
				$y = $yOffset + $boxHeight;
				break;
			case 'top right':
				$x = $this->getWidth() - $boxWidth + $xOffset;
				$y = $yOffset + $boxHeight;
				break;
			case 'top':
				$x = ($this->getWidth() / 2) - ($boxWidth / 2) + $xOffset;
				$y = $yOffset + $boxHeight;
				break;
			case 'bottom left':
				$x = $xOffset;
				$y = $this->getHeight() - $boxHeight + $yOffset + $boxHeight;
				break;
			case 'bottom right':
				$x = $this->getWidth() - $boxWidth + $xOffset;
				$y = $this->getHeight() - $boxHeight + $yOffset + $boxHeight;
				break;
			case 'bottom':
				$x = ($this->getWidth() / 2) - ($boxWidth / 2) + $xOffset;
				$y = $this->getHeight() - $boxHeight + $yOffset + $boxHeight;
				break;
			case 'left':
				$x = $xOffset;
				$y = ($this->getHeight() / 2) - (($boxHeight / 2) - $boxHeight) + $yOffset;
				break;
			case 'right';
				$x = $this->getWidth() - $boxWidth + $xOffset;
				$y = ($this->getHeight() / 2) - (($boxHeight / 2) - $boxHeight) + $yOffset;
				break;
			default: // center
				$x = ($this->getWidth() / 2) - ($boxWidth / 2) + $xOffset;
				$y = ($this->getHeight() / 2) - (($boxHeight / 2) - $boxHeight) + $yOffset;
				break;
		}

		$x = (int)round($x);
		$y = (int)round($y);

		// Pass the boundary back by reference
		$boundary = [
			'x1' => $x,
			'y1' => $y - $boxHeight, // $y is the baseline, not the top!
			'x2' => $x + $boxWidth,
			'y2' => $y,
			'width' => $boxWidth,
			'height' => $boxHeight
		];

		// Text shadow
		if (is_array($options['shadow'])) {
			imagettftext(
				$this->iImage,
				$size,
				$angle,
				$x + $options['shadow']['x'],
				$y + $options['shadow']['y'] - $descenderHeight,
				$this->allocateColor($options['shadow']['color']),
				$fontFile,
				$text
			);
		}

		// Draw the text
		imagettftext($this->iImage, $size, $angle, $x, $y - $descenderHeight, $color, $fontFile, $text);

		return $this;
	}

	//
	// Creates a thumbnail image. This function attempts to get the image as close to the provided
	// dimensions as possible, then crops the remaining overflow to force the desired size. Useful
	// for generating thumbnail images.
	//
	//	$width* (int) - The thumbnail width.
	//	$height* (int) - The thumbnail height.
	//	$anchor (string) - The anchor point: 'center', 'top', 'bottom', 'left', 'right', 'top left',
	//		'top right', 'bottom left', 'bottom right' (default 'center').
	//
	// Returns a SimpleImage object.
	//
	public function thumbnail($width, $height, $anchor = 'center') {
		// Determine aspect ratios
		$currentRatio = $this->getHeight() / $this->getWidth();
		$targetRatio = $height / $width;

		// Fit to height/width
		if ($targetRatio > $currentRatio) {
			$this->resize(null, $height);
		} else {
			$this->resize($width, null);
		}

		switch ($anchor) {
			case 'top':
				$x1 = floor(($this->getWidth() / 2) - ($width / 2));
				$x2 = $width + $x1;
				$y1 = 0;
				$y2 = $height;
				break;
			case 'bottom':
				$x1 = floor(($this->getWidth() / 2) - ($width / 2));
				$x2 = $width + $x1;
				$y1 = $this->getHeight() - $height;
				$y2 = $this->getHeight();
				break;
			case 'left':
				$x1 = 0;
				$x2 = $width;
				$y1 = floor(($this->getHeight() / 2) - ($height / 2));
				$y2 = $height + $y1;
				break;
			case 'right':
				$x1 = $this->getWidth() - $width;
				$x2 = $this->getWidth();
				$y1 = floor(($this->getHeight() / 2) - ($height / 2));
				$y2 = $height + $y1;
				break;
			case 'top left':
				$x1 = 0;
				$x2 = $width;
				$y1 = 0;
				$y2 = $height;
				break;
			case 'top right':
				$x1 = $this->getWidth() - $width;
				$x2 = $this->getWidth();
				$y1 = 0;
				$y2 = $height;
				break;
			case 'bottom left':
				$x1 = 0;
				$x2 = $width;
				$y1 = $this->getHeight() - $height;
				$y2 = $this->getHeight();
				break;
			case 'bottom right':
				$x1 = $this->getWidth() - $width;
				$x2 = $this->getWidth();
				$y1 = $this->getHeight() - $height;
				$y2 = $this->getHeight();
				break;
			default:
				$x1 = floor(($this->getWidth() / 2) - ($width / 2));
				$x2 = $width + $x1;
				$y1 = floor(($this->getHeight() / 2) - ($height / 2));
				$y2 = $height + $y1;
				break;
		}

		// Return the cropped thumbnail image
		return $this->crop($x1, $y1, $x2, $y2);
	}

	public function crop($x1, $y1, $x2, $y2) {
		// Keep crop within image dimensions
		$x1 = self::keepWithin($x1, 0, $this->getWidth());
		$x2 = self::keepWithin($x2, 0, $this->getWidth());
		$y1 = self::keepWithin($y1, 0, $this->getHeight());
		$y2 = self::keepWithin($y2, 0, $this->getHeight());

		// Crop it
		$this->iImage = imagecrop($this->iImage, [
			'x' => min($x1, $x2),
			'y' => min($y1, $y2),
			'width' => abs($x2 - $x1),
			'height' => abs($y2 - $y1)
		]);

		return $this;
	}

	function getGPS() {
		if ($this->iImage == null) {
			return array("latitude" => null, "longitude" => null);
		}
		if ($this->iExifData && array_key_exists("GPS", $this->iExifData)) {
			$latitude = $this->iExifData['GPS']['GPSLatitude'];
			$longitude = $this->iExifData['GPS']['GPSLongitude'];
			if (!$latitude || !$longitude) {
				return array("latitude" => null, "longitude" => null);
			}

			// latitude values //
			$latitudeDegrees = $this->divide($latitude[0]);
			$latitudeMinutes = $this->divide($latitude[1]);
			$latitudeSeconds = $this->divide($latitude[2]);
			$latitudeHemisphere = $this->iExifData['GPS']['GPSLatitudeRef'];

			// longitude values //
			$longitudeDegrees = $this->divide($longitude[0]);
			$longitudeMinutes = $this->divide($longitude[1]);
			$longitudeSeconds = $this->divide($longitude[2]);
			$longitudeHemisphere = $this->iExifData['GPS']['GPSLongitudeRef'];

			$latitudeDecimal = $this->toDecimal($latitudeDegrees, $latitudeMinutes, $latitudeSeconds, $latitudeHemisphere);
			$longitudeDecimal = $this->toDecimal($longitudeDegrees, $longitudeMinutes, $longitudeSeconds, $longitudeHemisphere);

			return array("latitude" => $latitudeDecimal, "longitude" => $longitudeDecimal);
		} else {
			return array("latitude" => null, "longitude" => null);
		}
	}

	private function divide($a) {
		$e = explode('/', $a);
		if (!$e[0] || !$e[1]) {
			return 0;
		} else {
			return $e[0] / $e[1];
		}
	}

	private function toDecimal($degrees, $minutes, $seconds, $hemisphere) {
		$decimalValue = $degrees + $minutes / 60 + $seconds / 3600;
		return ($hemisphere == 'S' || $hemisphere == 'W') ? $decimalValue *= -1 : $decimalValue;
	}

	function getDateTaken() {
		$dateTaken = "";
		if (array_key_exists("IFD0", $this->iExifData) && array_key_exists("DateTime", $this->iExifData['IFD0'])) {
			$dateTaken = $this->iExifData['IFD0']['DateTime'];
		}
		if (array_key_exists("EXIF", $this->iExifData) && array_key_exists("DateTimeOriginal", $this->iExifData['EXIF'])) {
			$dateTaken = $this->iExifData['EXIF']['DateTimeOriginal'];
		}
		return $dateTaken;
	}

}
