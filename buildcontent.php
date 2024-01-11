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

$GLOBALS['gPageCode'] = "BUILDCONTENT";
require_once "shared/startup.inc";

?>
<!DOCTYPE HTML>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Content Builder</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">

    <link href="/innovastudio/components/minimalist-blocks/content.css" rel="stylesheet" type="text/css"/>
    <link href="/innovastudio/contentbuilder/contentbuilder.css" rel="stylesheet" type="text/css"/>

    <style>
        body {
            position: relative;
        }

        #contentarea {
            width: 90%;
            margin: 20px auto;
            border: 1px dashed rgb(180, 180, 180);
        }

    </style>
</head>
<body>
<div id="contentarea">
</div>

<script src="/innovastudio/contentbuilder/jquery.min.js" type="text/javascript"></script>
<script src="/innovastudio/contentbuilder/contentbuilder.min.js" type="text/javascript"></script>
<script src="/innovastudio/contentbuilder/saveimages.js" type="text/javascript"></script>

<script type="text/javascript">
    let contentBuilderObject = null;

    $(function () {
        contentBuilderObject = new ContentBuilder({
            container: '#contentarea',
            modulePath: "/innovastudio/components/modules/",
            assetPath: "/innovastudio/components/",
            fontAssetPath: "/innovastudio/components/fonts/",
            scriptPath: "/innovastudio/contentbuilder/",
            pluginPath: "/innovastudio/contentbuilder/",
            snippetUrl: '/innovastudio/components/minimalist-blocks/content.js',
            snippetPath: '/innovastudio/components/minimalist-blocks/',
            snippetPathReplace: ['assets', 'innovastudio/components'],
            imageselect: '/innovastudio/selectimages.php',
            iconselect: '/innovastudio/components/ionicons/selecticon.html',
            sidePanel: 'right'
        });
        contentBuilderObject.loadSnippets('/innovastudio/components/minimalist-blocks/content.js');

    });

    function saveContentBuilder(contentId) {
        console.log(contentId);
        const $contentArea = $("#contentarea");
        $contentArea.saveimages({
            handler: '/innovastudio/saveimage.php', /* handler for base64 image saving */
            onComplete: function () {
                //Get html
                var myHtml = contentBuilderObject.html();
                var newElement = $("<div id='_cbhtml' class=\"content-builder-block\">" + myHtml + "</div>");

                // const html = contentBuilderObject.html().replace(/<div class="row clearfix /g, ">\n\n<div class=\"content-builder-block "); //Get content
                const html = newElement[0].outerHTML;
                $("#" + contentId, window.parent.document).val(html);
                parent.closeBuildContentDialog(contentId);
            }
        });
        $contentArea.data('saveimages').save();
    }

    function getContent() {
        return contentBuilderObject.html().replace(/><div class="row clearfix /g, ">\n\n<div class=\"row clearfix ");
    }

    function setContent(htmlContent) {
        let hasClass = $(htmlContent).hasClass('content-builder-block');
        if (hasClass) {
            htmlContent = $(htmlContent)[0].innerHTML;
        }

        if (contentBuilderObject == null) {
            setTimeout(function () {
                setContent(htmlContent);
            }, 100);
            return;
        }
        contentBuilderObject.loadHtml(htmlContent);
    }

</script>

</body>
</html>
