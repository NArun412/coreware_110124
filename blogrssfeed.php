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

$GLOBALS['gPageCode'] = "BLOGRSSFEED";
require_once "shared/startup.inc";

include_once "templates/class.blanktemplate.php";

class BlogRssFeedPage extends Page {
	function displayPage() {
		header('Content-type: text/xml');

		$today = date("D, j M Y G:i:s T");
		$blogUrl = getPreference("BLOG_URL");
		if (empty($blogUrl)) {
			$blogUrl = "http://" . str_replace("www", "blog", $_SERVER['HTTP_HOST']);
		}
		$urlAliasTypeCode = getUrlAliasTypeCode("posts","post_id", "id");
        if (empty($urlAliasTypeCode)) {
            $urlAliasTypeCode = "blog";
        }
        $linkBase = "https://" . $_SERVER['HTTP_HOST'] . "/" . $urlAliasTypeCode;

        ?>
        <rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/">
            <channel>
                <title>Blog RSS Feed</title>
                <link><?= $blogUrl ?></link>
                <description><?= $GLOBALS['gClientName'] ?> Blog</description>
                <language>en-us</language>
                <pubDate><?= $today ?></pubDate>
                <lastBuildDate><?= $today ?></lastBuildDate>
                <docs>http://blogs.law.harvard.edu/tech/rss</docs>
                <generator><?= $GLOBALS['gClientName'] ?></generator>
                <managingEditor><?= getFieldFromId("email_address", "contacts", "contact_id", getFieldFromId("contact_id", "clients", "client_id", $GLOBALS['gClientId'])) ?></managingEditor>
                <webMaster><?= getFieldFromId("email_address", "contacts", "contact_id", getFieldFromId("contact_id", "clients", "client_id", $GLOBALS['gClientId'])) ?></webMaster>
				<?php

				$resultSet = executeQuery("select * from posts where public_access = 1 and published = 1 and publish_time <= current_time and inactive = 0 order by publish_time desc");
				while ($row = getNextRow($resultSet)) {
					if (!empty($row['hide_in_lists'])) {
						continue;
					}
					$linkUrl = $linkBase . '/' . $row['link_name'];
					$linkGuid = $linkBase . "?post_id=" . $row['post_id'];
                    ?>
                    <item>
                        <title><?= $this->cleanUpAccents(htmlText($row['title_text'])) ?></title>
                        <link><?= $linkUrl ?></link>
                        <description><?= $this->cleanUpAccents(htmlText($this->returnSnippet(strip_tags($row['excerpt']), 10))) ?></description>
                        <pubDate><?= date("D, j M Y H:i:s T", strtotime($row['publish_time'])) ?></pubDate>
                        <author><?= getFieldFromId("email_address", "contacts", "contact_id", getFieldFromId("contact_id", "clients", "client_id", $GLOBALS['gClientId'])) ?></author>
                        <guid><?= $linkGuid ?></guid>
                    </item>
					<?php
				}
				?>
            </channel>
        </rss>
		<?php
		return true;
	}

	function cleanUpAccents($str) {
		$patterns = array("&aacute;", "&eacute;", "&iacute;", "&oacute;", "&uacute;", "&Aacute;", "&Eacute;", "&Iacute;", "&Oacute;", "&Uacute;");
		$replace = array("a", "e", "i", "o", "u", "A", "E", "I", "O", "U");
		return str_replace($patterns, $replace, $str);
	}

	function returnSnippet($string, $wordCount) {
		$currString = explode(" ", $string);
		for ($wordCounter = 0; $wordCounter < $wordCount; $wordCounter++) {
			echo $currString[$wordCounter] . " ";
		}
	}

}

$pageObject = new BlogRssFeedPage();
$pageObject->displayPage();
