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

$GLOBALS['gPageCode'] = "CAPTCHAGENERATOR";
require_once "shared/startup.inc";

$captchaText = getFieldFromId("captcha_code", "captcha_codes", "captcha_code_id", $_GET['id']);

if (empty($captchaText)) {
	$captchaCodeId = createCaptchaCode();
	$captchaText = getFieldFromId("captcha_code", "captcha_codes", "captcha_code_id", $captchaCodeId);
}

header("Content-type: image/png"); // setting the content type as png
$captchaImage = imagecreatetruecolor(280, 80);

$captchaBackground = imagecolorallocate($captchaImage, rand(180,240), rand(180,240), rand(180,240)); //setting captcha background colour
$captchaTextColor = imagecolorallocate($captchaImage, rand(0, 100), rand(0, 100), rand(0, 100)); //setting cpatcha text colour

imagefilledrectangle($captchaImage, 0, 0, 280, 80, $captchaBackground); //creating the rectangle
$lineColor = imagecolorallocate($captchaImage, rand(200, 240), rand(60, 120), rand(60, 120));
imagesetthickness($captchaImage, rand(4, 12));
imageline($captchaImage, rand(1, 30), rand(1, 30), rand(200, 240), rand(40, 80), $lineColor);
$lineColor = imagecolorallocate($captchaImage, rand(130, 250), rand(130, 250), rand(40, 120));
imagesetthickness($captchaImage, rand(4, 12));
imageline($captchaImage, rand(1, 30), rand(40, 80), rand(200, 240), rand(1, 30), $lineColor);

$fonts = array();
for ($x=1;$x<=20;$x++) {
	if (file_exists("fonts/captcha" . $x . ".ttf")) {
		$fonts[] = "fonts/captcha" . $x . ".ttf";
	} else {
		break;
	}
}
$font = $fonts[array_rand($fonts)];

imagettftext($captchaImage, rand(28, 32), rand(-8, 8), rand(10, 40), rand(45, 60), $captchaTextColor, $font, $captchaText);
imagepng($captchaImage);
imagedestroy($captchaImage);
