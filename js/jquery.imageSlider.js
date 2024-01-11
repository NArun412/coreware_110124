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
Options:
	loader - The program that the control calls to get the list of images (and titles and descriptions).
	height - default height of the control
	width - default width of the control
	part_width - the width of the individual image. If the control is wider than the image part, then the focused
				image will be centered and you'll be able to see the next and previous images
	show_title - true or false, should the title of the image be displayed below the image
	show_description - true or false, show the description of the image be displayed below the image
	text_height - the height of the section for text
	rotation - By default, rotation (moving from last image back to first) happens if the control fits exactly one image part.
				This allows rotation to be set to true or false.
	on_change - function to call when image is changed
*/

(function ($) {
    var imageSliderDefaults = {
        loader: '/imageslider.php', /* PHP code that provides the images from the album */
        width: "320",
        height: "240",
        part_width: "auto",
        part_height: "auto",
        show_title: false,
        show_description: false,
        text_height: "150px",
        rotation: "auto",
        current_image: 1,
        code: "",
        album_id: "",
        maintain_aspect: "auto",
        aspect_ratio: "auto",
        single_image: "auto",
        slide_direction: "left",
        orientation: "",
        image_click: "prettyPhoto",
        on_change: function () {
        }
    };

    var imageSliderMethods = {
        initialize: function (options) {
            imageSliderDefaults = $.extend({}, imageSliderDefaults, options);
            this.each(function () {
                if ($(this).data("original_width") == undefined) {
                    $(this).data("original_width", $(this).width());
                }
                if ($(this).data("original_height") == undefined) {
                    $(this).data("original_height", $(this).height());
                }
            });
            imageSliderMethods.build.apply(this);
        },
        build: function () {
            this.addClass("image-slider-outer").html("");
            this.each(function () {
                var thisDefaults = new Object();
                for (var i in imageSliderDefaults) {
                    thisDefaults[i] = imageSliderDefaults[i];
                    if ($(this).data(i) != undefined) {
                        thisDefaults[i] = $(this).data(i);
                        if (thisDefaults[i] === "true") {
                            thisDefaults[i] = true;
                        }
                        if (thisDefaults[i] === "false") {
                            thisDefaults[i] = false;
                        }
                    }
                }
                if ($(this).width() != 0) {
                    thisDefaults['width'] = $(this).width();
                } else if (thisDefaults['orientation'] == "") {
                    $(this).width(thisDefaults['width']);
                }
                if ($(this).height() != 0) {
                    thisDefaults['height'] = $(this).height();
                } else if (thisDefaults['orientation'] == "vertical") {
                    $(this).height(thisDefaults['height']);
                }
                if (thisDefaults['orientation'] == "" && thisDefaults['part_width'] == "auto") {
                    thisDefaults['part_width'] = thisDefaults['width'];
                    if (thisDefaults['single_image'] === "auto") {
                        thisDefaults['single_image'] = true;
                    }
                } else if (thisDefaults['orientation'] == "vertical" && thisDefaults['part_height'] == "auto") {
                    thisDefaults['part_height'] = thisDefaults['width'];
                    if (thisDefaults['single_image'] === "auto") {
                        thisDefaults['single_image'] = true;
                    }
                }
                if (thisDefaults['orientation'] == "" && thisDefaults['part_width'] != thisDefaults['width']) {
                    $(this).append("<div class='image-slider-block' style='display: none;'></div>");
                    thisDefaults['single_image'] = false;
                } else if (thisDefaults['orientation'] == "vertical" && thisDefaults['part_height'] != thisDefaults['height']) {
                    $(this).append("<div class='image-slider-block-vertical' style='display: none;'></div>");
                    thisDefaults['single_image'] = false;
                } else {
                    thisDefaults['single_image'] = true;
                }
                if (thisDefaults['orientation'] == "vertical") {
                    thisDefaults['show_title'] = false;
                    thisDefaults['show_description'] = false;
                }
                if (thisDefaults['single_image'] && (thisDefaults['show_title'] || thisDefaults['show_description'])) {
                    $(this).css("padding-bottom", thisDefaults['text_height']);
                }
                if (thisDefaults['maintain_aspect'] == "auto") {
                    thisDefaults['maintain_aspect'] = thisDefaults['single_image'];
                }
                if (thisDefaults['orientation'] == "" && thisDefaults['rotation'] == "auto") {
                    thisDefaults['rotation'] = (thisDefaults['part_width'] == thisDefaults['width']);
                } else if (thisDefaults['rotation'] == "auto") {
                    thisDefaults['rotation'] = (thisDefaults['part_height'] == thisDefaults['height']);
                }
                if (thisDefaults['maintain_aspect']) {
                    if (thisDefaults['aspect_ratio'] == "auto") {
                        var thisWidth = $(this).data("original_width");
                        if (thisWidth == undefined || thisWidth == 0) {
                            thisWidth = thisDefaults['width']
                        }
                        var thisHeight = $(this).data("original_height");
                        if (thisHeight == undefined || thisHeight == 0) {
                            thisHeight = thisDefaults['height']
                        }
                        thisDefaults['aspect_ratio'] = (thisHeight / thisWidth) * 100;
                    }
                    $(this).append("<div style='margin-top: " + thisDefaults['aspect_ratio'] + "%'></div>").css("height", "auto");
                }
                if (!thisDefaults['maintain_aspect']) {
                    $(this).height(thisDefaults['height']);
                }
                for (var i in thisDefaults) {
                    if (thisDefaults[i] === "true") {
                        thisDefaults[i] = true;
                    }
                    if (thisDefaults[i] === "false") {
                        thisDefaults[i] = false;
                    }
                    $(this).data(i, thisDefaults[i]);
                }
                if (thisDefaults['orientation'] == "") {
                    $(this).append("<div class='image-slider-left-control'><span class='fa fa-arrow-left'></span></div>");
                    $(this).append("<div class='image-slider-right-control'><span class='fa fa-arrow-right'></span></div>");
                } else {
                    $(this).append("<div class='image-slider-top-control'><span class='fa fa-arrow-up'></span></div>");
                    $(this).append("<div class='image-slider-bottom-control'><span class='fa fa-arrow-down'></span></div>");
                }

                $(this).off("tap click");
                $(this).on("tap click", ".image-slider-left-control", function (event) {
                    imageSliderMethods.previousImage.apply($(this).closest(".image-slider-outer"));
                    event.stopPropagation();
                });
                $(this).on("tap click", ".image-slider-right-control", function (event) {
                    imageSliderMethods.nextImage.apply($(this).closest(".image-slider-outer"));
                    event.stopPropagation();
                });
                $(this).on("tap click", ".image-slider-top-control", function (event) {
                    imageSliderMethods.previousImage.apply($(this).closest(".image-slider-outer"));
                    event.stopPropagation();
                });
                $(this).on("tap click", ".image-slider-bottom-control", function (event) {
                    imageSliderMethods.nextImage.apply($(this).closest(".image-slider-outer"));
                    event.stopPropagation();
                });
                $(this).off("swipeleft");
                $(this).on("swipeleft", function () {
                    imageSliderMethods.nextImage.apply($(this).closest(".image-slider-outer"));
                });
                $(this).off("swiperight");
                $(this).on("swiperight", function () {
                    imageSliderMethods.previousImage.apply($(this).closest(".image-slider-outer"));
                });
                $(this).on("tap click", ".image-slider-outer-mask", function () {
                    $(this).closest(".image-slider-outer").data("current_image", $(this).closest(".image-slider-part").data("image_number"));
                    imageSliderMethods.moveToImage.apply($(this).closest(".image-slider-outer"));
                });
                var thisElement = $(this).closest(".image-slider-outer");
                if (!$(this).data("single_image")) {
                    $(window).resize(function () {
                        clearTimeout(thisElement.data("image_resizer"));
                        var imageSliderResizer = setTimeout(function () {
                            imageSliderMethods.moveToImage.apply(thisElement);
                        }, 500);
                        thisElement.data("image_resizer", imageSliderResizer);
                    });
                }
                loadAjaxRequest(thisDefaults.loader + (thisDefaults.loader.indexOf("?") >= 0 ? "&" : "?") + "ajax=true&album_id=" + $(this).data("album_id") + "&code=" + $(this).data("code"), function(returnArray) {
                    if ("image_array" in returnArray && returnArray['image_array'].length > 0) {
                        var rotation = thisElement.data("rotation");
                        for (var i in returnArray['image_array']) {
                            var sliderPart = "<div class='image-slider-part image-slider-image-id-" + returnArray['image_array'][i]['image_id'] +
                                " image-number-" + (parseInt(i) + 1) + " " + (thisDefaults['single_image'] ? "single-image" : "") +
                                (thisDefaults['orientation'] == "" ? "" : " vertical-part") +
                                "' data-image_number='" + (parseInt(i) + 1) + "'>" +
                                (thisDefaults['single_image'] ? "" : "<div class='image-slider-outer-mask'></div>") +
                                (thisDefaults['image_click'] == "prettyPhoto" ?
                                    "<a rel='prettyPhoto[slider_" + thisElement.attr("id") + "]' href='" + returnArray['image_array'][i]['url'] +
                                    "' alt='" + returnArray['image_array'][i]['title'].replace("'", "`") +
                                    "' title='" + returnArray['image_array'][i]['description'].replace("'", "`") +
                                    "'>" : (thisDefaults['image_click'] == "linkUrl" ? "<a href='" + returnArray['image_array'][i]['link_url'] + "'>" : "")) +
                                "<img class='image-slider-image' alt='" + returnArray['image_array'][i]['title'].replace("'", "`") +
                                "' title='" + returnArray['image_array'][i]['description'].replace("'", "`") + "' src='" + returnArray['image_array'][i]['url'] +
                                "'></a>";
                            sliderPart += "<div class='image-slider-text' style='height: " + thisDefaults.text_height + ";" +
                                (thisDefaults.show_title || thisDefaults.show_description ? "" : " display: none;") + "'" +
                                "><p class='image-slider-title'" + (thisDefaults.show_title ? "" : " style='display: none;'") +
                                ">" + returnArray['image_array'][i]['title'] + "</p><div class='image-slider-description'" +
                                (thisDefaults.show_description ? "" : " style='display: none;'") + ">" + returnArray['image_array'][i]['description'] + "</div></div></div>"
                            if (thisDefaults['single_image']) {
                                thisElement.append(sliderPart);
                            } else {
                                thisElement.find(".image-slider-block" + (thisDefaults.orientation == "" ? "" : "-vertical")).append(sliderPart);
                            }
                            if ("data" in returnArray['image_array'][i]) {
                                for (var j in returnArray['image_array'][i]['data']) {
                                    $(".image-slider-image-id-" + returnArray['image_array'][i]['image_id']).data(j, returnArray['image_array'][i]['data'][j]);
                                }
                            }
                        }
                        if (!thisDefaults['single_image'] && thisDefaults['orientation'] == "") {
                            thisElement.find(".image-slider-part").width(thisDefaults['part_width']);
                        } else if (!thisDefaults['single_image'] && thisDefaults['orientation'] == "vertical") {
                            thisElement.find(".image-slider-part").height(thisDefaults['part_height']);
                        }
                        thisElement.data("current_image", thisDefaults.current_image);
                        thisElement.data('image_count', returnArray['image_array'].length);
                        thisElement.find("a[rel^='prettyPhoto']").prettyPhoto({ social_tools: false, default_height: 480, default_width: 854, deeplinking: false });
                        imageSliderMethods.moveToImage.apply(thisElement);
                    }
                });
            });
        },
        selectImageId: function (imageId) {
            if (this.find(".image-slider-image-id-" + imageId).length > 0) {
                if (!this.find(".image-slider-image-id-" + imageId).is(".selected-image")) {
                    var nextIndex = this.find(".image-slider-image-id-" + imageId).data("image_number");
                    this.data("current_image", nextIndex);
                    imageSliderMethods.moveToImage.apply(this);
                }
            }
        },
        selectImageNumber: function (imageNumber) {
            if (imageNumber > 0 && imageNumber <= this.find(".image-slider-part").length) {
                var currentIndex = this.find(".image-slider-part.selected-image").data("image_number");
                if (currentIndex != imageNumber) {
                    this.data("current_image", imageNumber);
                    imageSliderMethods.moveToImage.apply(this);
                }
            }
        },
        moveToImage: function () {
            var slideDirection = this.data("slide_direction");
            var orientation = this.data("orientation");
            var currentImage = this.data("current_image");
            if (this.find(".image-number-" + currentImage).length == 0) {
                currentImage = 1;
            }
            if (this.data("single_image")) {
                this.find(".selected-image").removeClass("selected-image");
                var thisElement = this;
                if (slideDirection === "left") {
                    if (orientation == "") {
                        this.find(".image-number-" + currentImage).addClass("selected-image").css({ left: "100%", right: "auto" }).animate({ left: "0px" }, function () {
                            thisElement.find(".image-slider-part").not(".selected-image").css("left", "100%");
                        });
                    } else {
                        this.find(".image-number-" + currentImage).addClass("selected-image").css({ top: "100%", bottom: "auto" }).animate({ top: "0px" }, function () {
                            thisElement.find(".image-slider-part").not(".selected-image").css("top", "100%");
                        });
                    }
                } else {
                    if (orientation == "") {
                        this.find(".image-number-" + currentImage).addClass("selected-image").css({ left: "auto", right: this.width() }).animate({ right: "0px" }, function () {
                            thisElement.find(".image-slider-part").not(".selected-image").css("left", "100%");
                        });
                    } else {
                        this.find(".image-number-" + currentImage).addClass("selected-image").css({ top: "auto", bottom: this.height() }).animate({ bottom: "0px" }, function () {
                            thisElement.find(".image-slider-part").not(".selected-image").css("top", "100%");
                        });
                    }
                }
            } else {
                var partPosition = 0;
                this.find(".image-slider-part").each(function () {
                    partPosition++;
                    if ($(this).is(".image-number-" + currentImage)) {
                        return false;
                    }
                });
                if (orientation == "") {
                    var margin = parseInt(this.find(".image-slider-part:first-child").css("margin-right"));
                    var partWidth = parseInt(this.data("part_width")) + margin;
                    var position = ((this.width() - parseInt(this.data("part_width"))) / 2) - ((parseInt(this.data("part_width")) + margin) * (partPosition - 1));
                    var thisElement = this;
                    this.find(".image-slider-block" + (orientation == "" ? "" : "-vertical")).animate({ left: position + "px" }, function () {
                        thisElement.find(".image-slider-block" + (orientation == "" ? "" : "-vertical")).show();
                        if (thisElement.data("rotation")) {
                            var centerPart = Math.round(thisElement.find(".image-slider-part").length / 2);
                            while (centerPart > thisElement.find(".image-slider-part.selected-image").index() + 1) {
                                thisElement.find(".image-slider-part:last-child").remove().prependTo(thisElement.find(".image-slider-block" + (orientation == "" ? "" : "-vertical")));
                                position = position - partWidth;
                                thisElement.find(".image-slider-block" + (orientation == "" ? "" : "-vertical")).css("left", position + "px");
                            }
                            while (centerPart < thisElement.find(".image-slider-part.selected-image").index() + 1) {
                                thisElement.find(".image-slider-part:first-child").remove().appendTo(thisElement.find(".image-slider-block" + (orientation == "" ? "" : "-vertical")));
                                position = position + partWidth;
                                thisElement.find(".image-slider-block" + (orientation == "" ? "" : "-vertical")).css("left", position + "px");
                            }
                        }
                    });
                } else {
                    var margin = parseInt(this.find(".image-slider-part:first-child").css("margin-bottom"));
                    var partHeight = parseInt(this.data("part_height")) + margin;
                    var position = ((this.height() - parseInt(this.data("part_height"))) / 2) - ((parseInt(this.data("part_height")) + margin) * (partPosition - 1));
                    var thisElement = this;
                    this.find(".image-slider-block" + (orientation == "" ? "" : "-vertical")).animate({ top: position + "px" }, function () {
                        thisElement.find(".image-slider-block" + (orientation == "" ? "" : "-vertical")).show();
                        if (thisElement.data("rotation")) {
                            var centerPart = Math.round(thisElement.find(".image-slider-part").length / 2);
                            while (centerPart > thisElement.find(".image-slider-part.selected-image").index() + 1) {
                                thisElement.find(".image-slider-part:last-child").remove().prependTo(thisElement.find(".image-slider-block" + (orientation == "" ? "" : "-vertical")));
                                position = position - partHeight;
                                thisElement.find(".image-slider-block" + (orientation == "" ? "" : "-vertical")).css("top", position + "px");
                            }
                            while (centerPart < thisElement.find(".image-slider-part.selected-image").index() + 1) {
                                thisElement.find(".image-slider-part:first-child").remove().appendTo(thisElement.find(".image-slider-block" + (orientation == "" ? "" : "-vertical")));
                                position = position + partHeight;
                                thisElement.find(".image-slider-block" + (orientation == "" ? "" : "-vertical")).css("top", position + "px");
                            }
                        }
                    });
                }
                this.find(".selected-image").removeClass("selected-image");
                this.find(".image-number-" + currentImage).addClass("selected-image");
            }
            this.find("a[rel^='prettyPhoto']").prettyPhoto({ social_tools: false, default_height: 480, default_width: 854, deeplinking: false });
            if (typeof this.data("on_change") == "function") {
                this.data("on_change")();
            }
        },
        nextImage: function () {
            var currentImage = this.data("current_image");
            currentImage++;
            if (this.find(".image-number-" + currentImage).length == 0) {
                if (this.data("rotation")) {
                    currentImage = 1;
                } else {
                    return;
                }
            }
            this.data("current_image", currentImage);
            this.data("slide_direction", "left");
            imageSliderMethods.moveToImage.apply(this);
        },
        previousImage: function () {
            var currentImage = this.data("current_image");
            currentImage--;
            if (this.find(".image-number-" + currentImage).length == 0) {
                if (this.data("rotation")) {
                    currentImage = this.data("image_count");
                } else {
                    return;
                }
            }
            this.data("current_image", currentImage);
            this.data("slide_direction", "right");
            imageSliderMethods.moveToImage.apply(this);
        }
    };

    $.fn.imageSlider = function (methodOrOptions) {
        if (imageSliderMethods[methodOrOptions]) {
            return imageSliderMethods[methodOrOptions].apply(this, Array.prototype.slice.call(arguments, 1));
        } else if (typeof methodOrOptions === 'object' || !methodOrOptions) {
            return imageSliderMethods.initialize.apply(this, arguments);
        } else {
            $.error('Method ' + methodOrOptions + ' does not exist on jQuery.imageSlider');
        }
    };
})(jQuery);
