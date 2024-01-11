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

var manualChangesMade = false;
var contactPickerFilterTextTimer = null;

function unloadChanges() {
    if (changesMade()) {
        return "Changes have not been saved. Stay on page to save changes. If you leave the page, the changes will be lost.";
    }
}

function createEditors() {
    $(".data-format-javascriptCode").each(function () {
        var elementId = $(this).attr("id");
        if (empty(elementId)) {
            return true;
        }
        if ($("#" + elementId + "-ace_editor").length > 0) {
            return true;
        }
        var beforeElementId = elementId;
        var labelText = "Javascript Code";
        if ($(this).closest("div.basic-form-line").length > 0) {
            $(this).closest("div.basic-form-line").addClass("hidden");
            beforeElementId = $(this).closest("div.basic-form-line").attr("id");
            labelText = $(this).closest("div.basic-form-line").find("label").html();
            if (empty(labelText)) {
                labelText = "";
            }
        } else {
            $(this).addClass("hidden");
        }
        $("<div class='basic-form-line no-bottom-margin'><label>" + labelText + "<a class='ace-editor-help iframe-link' data-window_title='Editor Keyboard Shortcuts' href='editor_shortcuts.html' target='_blank'><span class='fad fa-keyboard'></span></a></label></div><div data-element_id='" + elementId + "' class='ace-javascript-editor' id='" + elementId + "-ace_editor'></div>").insertBefore("#" + beforeElementId);
        var javascriptEditor = ace.edit(elementId + "-ace_editor");
        javascriptEditor.setTheme("ace/theme/chrome");
        javascriptEditor.container.style.background = "rgb(215,255,230)";
        javascriptEditor.setShowPrintMargin(false);
        javascriptEditor.session.setMode("ace/mode/javascript");
        javascriptEditor.textInput.getElement().tabIndex = 10;
        javascriptEditor.getSession().setUseWrapMode(true);
        javascriptEditor.on("blur", function () {
            $("#" + elementId).val(javascriptEditor.getValue());
        });
    });
    $(".data-format-CSSCode").each(function () {
        var elementId = $(this).attr("id");
        if (empty(elementId)) {
            return true;
        }
        if ($("#" + elementId + "-ace_editor").length > 0) {
            return true;
        }
        var beforeElementId = elementId;
        var labelText = "CSS/SASS Code";
        if ($(this).closest("div.basic-form-line").length > 0) {
            $(this).closest("div.basic-form-line").addClass("hidden");
            beforeElementId = $(this).closest("div.basic-form-line").attr("id");
            labelText = $(this).closest("div.basic-form-line").find("label").html();
        } else {
            $(this).addClass("hidden");
        }
        $("<div class='basic-form-line no-bottom-margin'><label>" + labelText + "<a class='ace-editor-help iframe-link' data-window_title='Editor Keyboard Shortcuts' href='/editor_shortcuts.html' target='_blank'><span class='fad fa-keyboard'></span></a></label></div><div data-element_id='" + elementId + "' class='ace-css-editor' id='" + elementId + "-ace_editor'></div>").insertBefore("#" + beforeElementId);
        var javascriptEditor = ace.edit(elementId + "-ace_editor");
        javascriptEditor.setTheme("ace/theme/solarized_light");
        javascriptEditor.setShowPrintMargin(false);
        javascriptEditor.session.setMode("ace/mode/sass");
        javascriptEditor.textInput.getElement().tabIndex = 10;
        javascriptEditor.getSession().setUseWrapMode(true);
        javascriptEditor.on("blur", function () {
            $("#" + elementId).val(javascriptEditor.getValue());
        });
    });
    $(".data-format-HTML").each(function () {
        var elementId = $(this).attr("id");
        if (empty(elementId)) {
            return true;
        }
        if ($("#" + elementId + "-ace_editor").length > 0) {
            return true;
        }
        var labelText = "HTML";
        if ($(this).closest("div.basic-form-line").length > 0) {
            labelText = $(this).closest("div.basic-form-line").find("label").append("<a class='ace-editor-help iframe-link' data-window_title='Editor Keyboard Shortcuts' href='/editor_shortcuts.html' target='_blank'><span class='fad fa-keyboard'></span></a>");
        }
        $(this).addClass("hidden");
        $("<div data-element_id='" + elementId + "' class='ace-html-editor' id='" + elementId + "-ace_editor'></div>").insertBefore("#" + elementId);
        var javascriptEditor = ace.edit(elementId + "-ace_editor");
        javascriptEditor.setTheme("ace/theme/xcode");
        javascriptEditor.container.style.background = "rgb(235,235,255)";
        javascriptEditor.session.setUseWrapMode(true);
        javascriptEditor.setShowPrintMargin(false);
        javascriptEditor.session.setMode("ace/mode/html");
        javascriptEditor.textInput.getElement().tabIndex = 10;
        javascriptEditor.getSession().setUseWrapMode(true);
        javascriptEditor.on("blur", function () {
            $("#" + elementId).val(javascriptEditor.getValue());
        });
        var session = javascriptEditor.getSession();
        session.on("changeAnnotation", function () {
            var annotations = session.getAnnotations() || [], i = len = annotations.length;
            while (i--) {
                if (/doctype first\. Expected/.test(annotations[i].text)) {
                    annotations.splice(i, 1);
                } else if (/Unexpected End of file\. Expected/.test(annotations[i].text)) {
                    annotations.splice(i, 1);
                }
            }
            if (len > annotations.length) {
                session.setAnnotations(annotations);
            }
        });
    });
}

$(function () {
    $(document).on("change", "select.add-new-option", function () {
        if ($(this).val() == "-9999") {
            const linkUrl = $(this).data("link_url");
            $(this).addClass("option-being-added").val("");
            window.open(linkUrl);
        }
    });
    $(window).on("focus", function () {
        $("select.option-being-added").each(function () {
            const thisElement = $(this);
            loadAjaxRequest(scriptFilename + "?ajax=true&url_action=get_control_table_options&control_code=" + $(this).data("control_code"), function(returnArray) {
                thisElement.removeClass("option-being-added");
                if ("options" in returnArray) {
                    thisElement.find("option[value!='']").each(function () {
                        if ($(this).val() != "-9999") {
                            $(this).remove();
                        }
                    });
                    for (var i in returnArray['options']) {
                        thisElement.append($("<option></option>").attr("value", returnArray['options'][i]['key_value']).text(returnArray['options'][i]['description']));
                    }
                    if ("control_id" in returnArray) {
                        thisElement.val(returnArray['control_id']).trigger("change");
                    }
                }
            });
        });
    });
    $(document).on("change", "#preset_dates", function () {
        if (empty($(this).val())) {
            $(".preset-date-custom").removeClass("hidden");
        } else {
            $(".preset-date-custom").addClass("hidden");
        }
    });
    $(document).on("change", "#_stored_report_id", function () {
        if (!empty($(this).val())) {
            $("#report_parameters").find("input[type=checkbox]").prop("checked",false);
            loadAjaxRequest(scriptFilename + "?ajax=true&url_action=load_stored_report&stored_report_id=" + $(this).val(), function (returnArray) {
                if ("parameters" in returnArray) {
                    for (var i in returnArray['parameters']) {
                        if ($("#" + i).is("input[type=checkbox]")) {
                            $("#" + i).prop("checked", (!empty(returnArray['parameters'][i])));
                        } else {
                            $("#" + i).val(returnArray['parameters'][i]);
                        }
                    }
                }
                $(".selector-value-list").trigger("change");
                $("#preset_dates").trigger("change");
            });
        }
    });

    $(document).on("resize", "#_iframe_link_dialog_wrapper", function () {
        $("#_iframe_link").css("min-height", "0px");
    });

    $(document).on("click", ".iframe-link", function () {
        if ($("#_iframe_link_dialog").length > 0) {
            var linkUrl = $(this).attr("href");
            var windowTitle = $(this).data("window_title");
            $("#_iframe_link").css("min-height", "600px");
            $("#_iframe_link").attr("src", linkUrl);
            $('#_iframe_link_dialog').dialog({
                closeOnEscape: true,
                draggable: true,
                modal: false,
                resizable: true,
                position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                width: 850,
                title: windowTitle,
                buttons: {
                    Close: function (event) {
                        $("#_iframe_link_dialog").dialog('close');
                    }
                }
            });
            $('#_iframe_link_dialog').dialog('widget').attr('id', '_iframe_link_dialog_wrapper');
            return false;
        } else {
            return true;
        }
    });

    createEditors();

// install function to run if window is closed

    window.onbeforeunload = unloadChanges;

// if CKEditor is installed, start the WYSIWYG editor

    $(document).on("tap click", ".content-builder", function (event) {
        var fieldId = $(this).data("id");
        if (typeof CKEDITOR !== "undefined") {
            if ($(this).data("checked") == "true") {
                $(this).data("checked", "false");
                $("#" + fieldId).removeClass("wysiwyg");
                CKEDITOR.instances[fieldId].destroy();
                if ($("#" + fieldId + "-ace_editor").length > 0) {
                    var javascriptEditor = ace.edit(fieldId + "-ace_editor");
                    if ($("#" + fieldId).length > 0 && !empty(javascriptEditor)) {
                        javascriptEditor.setValue($("#" + fieldId).val(), 1);
                    }
                    $("#" + fieldId + "-ace_editor").removeClass("hidden");
                }
                return false;
            }
        }
        if (event.altKey || $("#_build_content_dialog").length == 0 || $("#" + fieldId).hasClass("use-ck-editor")) {
            if (typeof CKEDITOR !== "undefined") {
                var fieldId = $(this).data("id");
                if ($(this).data("checked") != "true") {
                    $(this).data("checked", "true");
                    $("#" + fieldId).addClass("wysiwyg");
                    var contentsCss = $("#" + fieldId).data("contents_css");
                    if (empty(contentsCss)) {
                        contentsCss = "";
                    }
                    var stylesSet = $("#" + fieldId).data("styles_set");
                    if (empty(stylesSet)) {
                        stylesSet = new Array();
                    }
                    const width = "95%";
                    const editorConfig = {
                        resize_dir: 'both',
                        disableNativeSpellChecker: false,
                        scayt_autoStartup: true,
                        contentsCss: contentsCss,
                        width: width,
                        height: 300,
                        stylesSet: stylesSet,
                        allowedContent: true,
                        removePlugins: 'flash,image,maximize',
                        extraPlugins: 'uploadimage,fontawesome5',
                        fontawesome: {
                            'path': '/fontawesome-core/css/all.min.css',
                            'version': '5.15.1',
                            'edition': 'pro',
                            'element': 'i'
                        },
                        uploadUrl: '/ckeditorimageupload.php',
                        removeButtons: 'Select,HiddenField,ImageButton,Button,Textarea,TextField,Form,Radio,Checkbox,Iframe,PageBreak,InsertPre,Anchor,Language,BidiRtl,BidiLtr,ShowBlocks,Save,Print,Preview,NewPage,Templates',
                        smiley_path: '/ckeditor/plugins/smiley/images/',
                        coreStyles_bold: {
                            element: 'span',
                            attributes: { 'class': 'highlighted-text' },
                            styles: { 'font-weight': 'bold' }
                        },
                        coreStyles_italic: {
                            element: 'span',
                            attributes: { 'class': 'italic' },
                            styles: { 'font-style': 'bold' }
                        }
                    };
                    CKEDITOR.replace(fieldId, editorConfig);
                    CKEDITOR.dtd.$removeEmpty['span'] = false;
                    CKEDITOR.dtd.$removeEmpty['i'] = false;
                    if ($("#" + fieldId + "-ace_editor").length > 0) {
                        $("#" + fieldId + "-ace_editor").addClass("hidden");
                    }
                }
            }
            return false;
        }
        var contentId = $(this).data("id");
        var builderSource = $(this).data("builder");
        if (empty(builderSource)) {
            builderSource = "buildcontent.php";
        }
        $('#_build_content_dialog').dialog({
            closeOnEscape: true,
            draggable: true,
            modal: true,
            resizable: true,
            position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
            width: 1200,
            title: 'Build Content',
            close: function () {
                $("#_build_content_dialog").html("");
                $("#_build_content_dialog").closest(".ui-dialog").find(".ui-dialog-buttonset").removeClass("hidden");
                $("#_build_content_dialog").closest(".ui-dialog").find(".ui-dialog-buttonpane").find("#build_content_iframe_saving").remove();
            },
            open: function () {
                $("#_build_content_dialog").html("<iframe id='_build_content_iframe'></iframe>");
                $("#_build_content_iframe").attr("src", "/" + builderSource);
                $("#_build_content_iframe").load(function () {
                    $('#_build_content_iframe').get(0).contentWindow.setContent($("#" + contentId).val());
                });
            },
            buttons: {
                Save: function (event) {
                    if (typeof $("#_build_content_iframe").get(0).contentWindow.saveContentBuilder == 'function') {
                        $("#_build_content_dialog").closest(".ui-dialog").find(".ui-dialog-buttonset").addClass("hidden");
                        $("#_build_content_dialog").closest(".ui-dialog").find(".ui-dialog-buttonpane").prepend("<p id='build_content_iframe_saving' style='font-size: 24px; color: rgb(80,180,80); text-align: right;'>Hang on a moment while we construct your page...</p>");
                        $('#_build_content_iframe').get(0).contentWindow.saveContentBuilder(contentId);
                    } else {
                        $("#" + contentId).val($('#_build_content_iframe').get(0).contentWindow.getContent());
                        $("#_build_content_dialog").dialog('close');
                    }
                },
                Cancel: function (event) {
                    $("#_build_content_dialog").dialog('close');
                }
            }
        });
    });

    $(document).on("tap click", ".toggle-wysiwyg", function () {
        if (typeof CKEDITOR !== "undefined") {
            var fieldId = $(this).data("id");
            if ($(this).data("checked") != "true") {
                $(this).data("checked", "true");
                $("#" + fieldId).addClass("wysiwyg");
                var contentsCss = $("#" + fieldId).data("contents_css");
                var stylesSet = $("#" + fieldId).data("styles_set");
                if (empty(stylesSet)) {
                    stylesSet = new Array();
                }
                CKEDITOR.replace(fieldId, {
                    resize_dir: 'both',
                    contentsCss: contentsCss,
                    width: 800,
                    stylesSet: stylesSet,
                    allowedContent: true,
                    coreStyles_bold: {
                        element: 'span',
                        attributes: { 'class': 'highlighted-text' },
                        styles: { 'font-weight': 'bold' }
                    },
                    coreStyles_italic: {
                        element: 'span',
                        attributes: { 'class': 'italic' },
                        styles: { 'font-style': 'bold' }
                    }
                });
            } else {
                $(this).data("checked", "false");
                $("#" + fieldId).removeClass("wysiwyg");
                CKEDITOR.instances[fieldId].destroy();
            }
            return false;
        }
    });

// Open the image picker. There are three pickers defined here: images, contacts, & users. Some time, consolidate and make a general "Picker" control

    $(document).on("change", ".image-picker-selector", function () {
        var elementId = $(this).attr("id");
        if (empty($(this).val())) {
            $("#" + elementId + "_view").attr("href", "").hide();
        } else {
            $("#" + elementId + "_view").attr("href", $(this).find("option:selected").data("url")).show();
        }
    });

// an image was picked in the image picker

    $(document).on("tap click", ".image-picker-item", function () {
        var imageId = $(this).data("image_id");
        var description = $(this).find("tr").find(".image-picker-description").html();
        var columnName = $("#_image_picker_column_name").val();
        var url = $(this).find("tr").find(".image-picker-thumbnail").attr("src");
        $("#" + columnName).append($("<option></option>").attr("value", imageId).text(description).data("url", url)).val(imageId).trigger("change");
        $("#" + columnName + "_view").attr("href", url).show();
        $("#_image_picker_dialog").dialog("close");
        $("#" + columnName).focus();
        return false;
    });

// User wants to add a new image in image picker

    $(document).on("tap click", "#image_picker_new_image", function () {
        $("#image_picker_filter").val("");
        $("#image_picker_new_image_description").val("");
        $("#image_picker_file_content_file").val("");
        $('#_image_picker_new_image_dialog').dialog({
            closeOnEscape: true,
            draggable: false,
            modal: true,
            resizable: false,
            position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
            width: 600,
            title: 'Add new image',
            buttons: {
                Save: function (event) {
                    if ($("#_new_image").validationEngine('validate')) {
                        $("body").addClass("waiting-for-ajax");
                        $("#_new_image").attr("action", "/addimage.php?ajax=true").attr("method", "POST").attr("target", "post_iframe").submit();
                        $("#_post_iframe").off("load");
                        $("#_post_iframe").on("load", function () {
                            $("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
                            var returnText = $(this).contents().find("body").html();
                            const returnArray = processReturn(returnText);
                            if (returnArray === false) {
                                return;
                            }
                            if ("image" in returnArray) {
                                $("#_image_picker_list").prepend("<div class='image-picker-item' data-image_id='" + returnArray['image']['image_id'] +
                                    "'><table cellspacing='0' cellpadding='0'><tr><td>" +
                                    "<a class='image-picker-description' href='" + returnArray['image']['url'] + "' rel='prettyPhoto'>" + returnArray['image']['description'] +
                                    "</a></td><td><img id='_image_picker_choice_" + returnArray['image']['image_id'] + "' src='" + returnArray['image']['url'].replace("-full-", "-small-") + "' class='image-picker-thumbnail' /></td></tr></table></div>");
                                $("#_image_picker_list a[rel^='prettyPhoto']").prettyPhoto({
                                    social_tools: false,
                                    default_height: 480,
                                    default_width: 854,
                                    deeplinking: false
                                });
                                $("#_image_picker_choice_" + returnArray['image']['image_id']).trigger("click");
                            }
                        });
                    }
                    $("#_image_picker_new_image_dialog").dialog('close');
                },
                Cancel: function (event) {
                    $("#_image_picker_new_image_dialog").dialog('close');
                }
            }
        });
        return false;
    });

// User decided to remove image

    $(document).on("tap click", "#image_picker_no_image", function () {
        var columnName = $("#_image_picker_column_name").val();
        $("#" + columnName).val("");
        $("#" + columnName + "_view").attr("href", "").hide();
        $("#_image_picker_dialog").dialog("close");
    });

// Filter images

    $("#image_picker_filter").keydown(function (event) {
        if (event.which == 13 || event.which == 3) {
            getImagePicker();
        }
    });

// Show the image picker dialog

    $(document).on("tap click", ".image-picker", function () {
        var columnName = $(this).data("column_name");
        $("#_image_picker_column_name").val(columnName);
        if ($("#_image_picker_list .image-picker-item").length == 0) {
            getImagePicker();
        }
        $('#_image_picker_dialog').dialog({
            autoOpen: true,
            closeOnEscape: true,
            draggable: true,
            resizable: false,
            modal: true,
            position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
            width: 800,
            height: 560,
            title: 'Image Picker',
            buttons: {
                Close: function (event) {
                    $("#_image_picker_dialog").dialog("close");
                }
            }
        });
        return false;
    });

// Contact picker functions

    $(document).on("change", ".contact-picker-selector", function () {
        var columnName = $(this).data("column_name");
        $("#" + columnName).val($(this).val());
    });
    $(document).on("tap click", ".contact-picker-choice", function () {
        var contactId = $(this).data("contact_id");
        var description = $(this).closest("div").find(".contact-picker-description").text();
        var columnName = $("#_contact_picker_column_name").val();
        if ($("#" + columnName + "_selector").find("option[value=" + contactId + "]").length == 0) {
            $("#" + columnName + "_selector").append($("<option></option>").attr("value", contactId).text(description));
        }
        $("#" + columnName + "_selector").val(contactId);
        $("#" + columnName).val(contactId);
        $("#_contact_picker_dialog").dialog("close");
        $("#" + columnName).trigger("change")
        $("#" + columnName + "_selector").trigger("change").focus();
        return false;
    });
    $(document).on("tap click", "#contact_picker_no_contact", function () {
        var columnName = $("#_contact_picker_column_name").val();
        $("#" + columnName).val("");
        $("#" + columnName + "_selector").val("");
        $("#" + columnName + "_view").attr("href", "").hide();
        $("#" + columnName).trigger("change").focus();
        $("#_contact_picker_dialog").dialog("close");
        return false;
    });
    $('#contact_picker_filter_text').on('tap click', function () {
        var $this = $(this).one('mouseup.mouseupSelect', function () {
            $this.select();
            return false;
        }).one('mousedown', function () {
            $this.off('mouseup.mouseupSelect');
        }).select();
    });
    $(document).on("tap click", "#contact_picker_new_contact", function () {
        $("#contact_picker_filter_text").data("last_value", "");
        if (empty($(this).data("quick_add"))) {
            window.open("/contactmaintenance.php?url_page=new");
        } else {
            $('#_contact_picker_add_dialog').dialog({
                closeOnEscape: true,
                draggable: false,
                modal: true,
                resizable: false,
                position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                width: 600,
                title: 'Add Contact',
                buttons: {
                    Save: function (event) {
                        if ($("#_contact_picker_add_form").validationEngine('validate')) {
                            loadAjaxRequest(scriptFilename + "?ajax=true&url_action=contact_picker_add_contact", $("#_contact_picker_add_form").serialize(), function(returnArray) {
                                if (!("error_message" in returnArray)) {
                                    var contactId = returnArray['contact_id'];
                                    var description = returnArray['description'];
                                    var columnName = $("#_contact_picker_column_name").val();
                                    if ($("#" + columnName + "_selector").find("option[value=" + contactId + "]").length == 0) {
                                        $("#" + columnName + "_selector").append($("<option></option>").attr("value", contactId).text(description));
                                    }
                                    $("#" + columnName + "_selector").val(contactId);
                                    $("#" + columnName).val(contactId);
                                    $("#_contact_picker_add_dialog").dialog('close');
                                    $("#_contact_picker_dialog").dialog("close");
                                    $("#" + columnName).trigger("change")
                                    $("#" + columnName + "_selector").trigger("change").focus();
                                }
                            });
                        }
                    },
                    Cancel: function (event) {
                        $("#_contact_picker_add_dialog").dialog('close');
                    }
                }
            });
        }
        return false;
    });
    $("#contact_picker_contact_type_id").change(function () {
        getContactPickerList();
    });
    $("#contact_picker_filter_text").keydown(function (event) {
        if (!empty(contactPickerFilterTextTimer)) {
            clearTimeout(contactPickerFilterTextTimer);
            contactPickerFilterTextTimer = null;
        }
        if ($(this).data("last_value") == undefined) {
            $(this).data("last_value", "");
        }
        if ($(this).data("last_value") == $(this).val()) {
            if (event.which == 13) {
                if ($("#_contact_picker_list").find(".contact-picker-item.selected").length > 0) {
                    $("#_contact_picker_list").find(".contact-picker-item.selected").first().find(".contact-picker-choice").trigger("click");
                } else {
                    $("#_contact_picker_list").find(".contact-picker-item").first().find(".contact-picker-choice").trigger("click");
                }
            } else if (event.which == 40) {
                var currentIndex = $("#_contact_picker_list .contact-picker-item.selected").index();
                currentIndex++;
                if (currentIndex >= $("#_contact_picker_list .contact-picker-item").length) {
                    currentIndex = 0;
                }
                $("#_contact_picker_list .contact-picker-item.selected").removeClass("selected");
                $("#_contact_picker_list .contact-picker-item:eq(" + currentIndex + ")").addClass("selected");
                var maxScroll = ($("#_contact_picker_list .contact-picker-item").length * ($("#_contact_picker_list .contact-picker-item").height() + 1)) - $("#_contact_picker_list").height();
                if ($("#_contact_picker_list .contact-picker-item.selected").position().top > $("#_contact_picker_list").height() ||
                    $("#_contact_picker_list .contact-picker-item.selected").position().top < 0 ||
                    ($("#_contact_picker_list .contact-picker-item.selected").position().top + $("#_contact_picker_list .contact-picker-item").height()) > $("#_contact_picker_list").height()) {
                    var newScroll = $("#_contact_picker_list .contact-picker-item.selected").position().top;
                    if (newScroll > maxScroll) {
                        newScroll = maxScroll;
                    }
                    $("#_contact_picker_list").scrollTop(newScroll);
                }
                return false;
            } else if (event.which == 38) {
                var currentIndex = $("#_contact_picker_list .contact-picker-item.selected").index();
                currentIndex--;
                if (currentIndex < 0) {
                    currentIndex = $("#_contact_picker_list .contact-picker-item").length - 1;
                }
                $("#_contact_picker_list .contact-picker-item.selected").removeClass("selected");
                $("#_contact_picker_list .contact-picker-item:eq(" + currentIndex + ")").addClass("selected");
                var maxScroll = ($("#_contact_picker_list .contact-picker-item").length * ($("#_contact_picker_list .contact-picker-item").height() + 1)) - $("#_contact_picker_list").height();
                if ($("#_contact_picker_list .contact-picker-item.selected").position().top > $("#_contact_picker_list").height() ||
                    $("#_contact_picker_list .contact-picker-item.selected").position().top < 0 ||
                    ($("#_contact_picker_list .contact-picker-item.selected").position().top + $("#_contact_picker_list .contact-picker-item").height()) > $("#_contact_picker_list").height()) {
                    var newScroll = $("#_contact_picker_list .contact-picker-item.selected").position().top;
                    if (newScroll > maxScroll) {
                        newScroll = maxScroll;
                    }
                    $("#_contact_picker_list").scrollTop(newScroll);
                }
                return false;
            }
        } else {
            $(this).data("last_value", $(this).val());
            if (event.which == 13) {
                getContactPickerList();
                return false;
            }
        }
        contactPickerFilterTextTimer = setTimeout(function () {
            if ($("#contact_picker_filter_text").val() != $("#contact_picker_filter_text").data("last_value")) {
                $("#contact_picker_filter_text").data("last_value", $("#contact_picker_filter_text").val());
                getContactPickerList();
                return false;
            }
        }, 500);
    });
    $(document).on("change", ".contact-picker-value", function (event) {
        if (empty($(this).val())) {
            return;
        }
        var contactField = $(this);
        loadAjaxRequest("/getcontactpickerlist.php?ajax=true&contact_id=" + contactField.val(), function(returnArray) {
            if ("contact_info" in returnArray) {
                if ($("#" + contactField.attr("id") + "_selector").find("option[value=" + returnArray['contact_info']['contact_id'] + "]").length == 0) {
                    $("#" + contactField.attr("id") + "_selector").append($("<option></option>").attr("value", returnArray['contact_info']['contact_id']).text(returnArray['contact_info']['description']));
                }
                $("#" + contactField.attr("id") + "_selector").val(returnArray['contact_info']['contact_id']);
                var oldId = $("#" + contactField.attr("id")).val();
                $("#" + contactField.attr("id")).val(returnArray['contact_info']['contact_id']);
                if (oldId != returnArray['contact_info']['contact_id']) {
                    $("#" + contactField.attr("id")).trigger("change");
                }
            } else {
                contactField.val("");
                $("#" + contactField.attr("id") + "_selector").val("");
            }
        });
    });
    $(document).on("tap click", "#contact_picker_filter", function () {
        getContactPickerList();
        return false;
    });
    $(document).on("tap click", ".contact-picker", function () {
        var columnName = $(this).data("column_name");
        $("#" + columnName).trigger("keydown");
        $("#" + columnName + "_selector").trigger("keydown");
        var filterWhere = $(this).data("filter_where");
        if (filterWhere == undefined) {
            filterWhere = "";
        }
        if ($("#" + columnName + "_selector").val() != "") {
            var pieces = $("#" + columnName + "_selector option:selected").text().split("•");
            $("#contact_picker_filter_text").val(pieces[0].trim());
        }
        var contactTypeId = $("#" + columnName).data('contact_type_id');
        if (contactTypeId != "" && contactTypeId != undefined) {
            $("#contact_picker_contact_type_id").val(contactTypeId);
        }
        $("#_contact_picker_column_name").val(columnName);
        $("#_contact_picker_current_value").val($("#" + columnName).val());
        $("#_contact_picker_filter_where").val(filterWhere);
        getContactPickerList();
        return false;
    });

// User picker functions

    $(document).on("change", ".user-picker-selector", function () {
        var columnName = $(this).data("column_name");
        $("#" + columnName).val($(this).val());
    });
    $(document).on("tap click", ".user-picker-choice", function () {
        var userId = $(this).data("user_id");
        var description = $(this).closest("div").find(".user-picker-description").text();
        var columnName = $("#_user_picker_column_name").val();
        if ($("#" + columnName + "_selector").find("option[value=" + userId + "]").length == 0) {
            $("#" + columnName + "_selector").append($("<option></option>").attr("value", userId).text(description));
        }
        $("#" + columnName + "_selector").val(userId);
        $("#" + columnName).val(userId);
        $("#_user_picker_dialog").dialog("close");
        $("#" + columnName).trigger("change")
        $("#" + columnName + "_selector").trigger("change").focus();
        return false;
    });
    $(document).on("tap click", "#user_picker_no_user", function () {
        var columnName = $("#_user_picker_column_name").val();
        $("#" + columnName).val("");
        $("#" + columnName + "_selector").val("");
        $("#" + columnName + "_view").attr("href", "").hide();
        $("#" + columnName).trigger("change").focus();
        $("#_user_picker_dialog").dialog("close");
        return false;
    });
    $('#user_picker_filter_text').on('focus', function () {
        var $this = $(this).one('mouseup.mouseupSelect', function () {
            $this.select();
            return false;
        }).one('mousedown', function () {
            $this.off('mouseup.mouseupSelect');
        }).select();
    });
    $(document).on("tap click", "#user_picker_new_user", function () {
        $("#user_picker_filter_text").data("last_value", "");
        window.open("/usermaintenance.php?url_page=new");
        return false;
    });
    $("#user_picker_filter_text").keydown(function (event) {
        if ($(this).data("last_value") == undefined) {
            $(this).data("last_value", "");
        }
        if ($(this).data("last_value") == $(this).val()) {
            if (event.which == 13) {
                if ($("#_user_picker_list").find(".user-picker-item.selected").length > 0) {
                    $("#_user_picker_list").find(".user-picker-item.selected").find(".user-picker-choice").trigger("click");
                }
            } else if (event.which == 40) {
                var currentIndex = $("#_user_picker_list .user-picker-item.selected").index();
                currentIndex++;
                if (currentIndex >= $("#_user_picker_list .user-picker-item").length) {
                    currentIndex = 0;
                }
                $("#_user_picker_list .user-picker-item.selected").removeClass("selected");
                $("#_user_picker_list .user-picker-item:eq(" + currentIndex + ")").addClass("selected");
                var maxScroll = ($("#_user_picker_list .user-picker-item").length * ($("#_user_picker_list .user-picker-item").height() + 1)) - $("#_user_picker_list").height();
                if ($("#_user_picker_list .user-picker-item.selected").position().top > $("#_user_picker_list").height() ||
                    $("#_user_picker_list .user-picker-item.selected").position().top < 0 ||
                    ($("#_user_picker_list .user-picker-item.selected").position().top + $("#_user_picker_list .user-picker-item").height()) > $("#_user_picker_list").height()) {
                    var newScroll = $("#_user_picker_list .user-picker-item.selected").position().top;
                    if (newScroll > maxScroll) {
                        newScroll = maxScroll;
                    }
                    $("#_user_picker_list").scrollTop(newScroll);
                }
            } else if (event.which == 38) {
                var currentIndex = $("#_user_picker_list .user-picker-item.selected").index();
                currentIndex--;
                if (currentIndex < 0) {
                    currentIndex = $("#_user_picker_list .user-picker-item").length - 1;
                }
                $("#_user_picker_list .user-picker-item.selected").removeClass("selected");
                $("#_user_picker_list .user-picker-item:eq(" + currentIndex + ")").addClass("selected");
                var maxScroll = ($("#_user_picker_list .user-picker-item").length * ($("#_user_picker_list .user-picker-item").height() + 1)) - $("#_user_picker_list").height();
                if ($("#_user_picker_list .user-picker-item.selected").position().top > $("#_user_picker_list").height() ||
                    $("#_user_picker_list .user-picker-item.selected").position().top < 0 ||
                    ($("#_user_picker_list .user-picker-item.selected").position().top + $("#_user_picker_list .user-picker-item").height()) > $("#_user_picker_list").height()) {
                    var newScroll = $("#_user_picker_list .user-picker-item.selected").position().top;
                    if (newScroll > maxScroll) {
                        newScroll = maxScroll;
                    }
                    $("#_user_picker_list").scrollTop(newScroll);
                }
            }
        } else {
            $(this).data("last_value", $(this).val());
            if (event.which == 13) {
                getUserPickerList();
                return false;
            }
        }
    });
    $(document).on("change", ".user-picker-value", function (event) {
        if (empty($(this).val())) {
            return;
        }
        var userField = $(this);
        loadAjaxRequest("/getuserpickerlist.php?ajax=true&user_id=" + userField.val(), function(returnArray) {
            if ("user_info" in returnArray) {
                if ($("#" + userField.attr("id") + "_selector").find("option[value=" + returnArray['user_info']['user_id'] + "]").length == 0) {
                    $("#" + userField.attr("id") + "_selector").append($("<option></option>").attr("value", returnArray['user_info']['user_id']).text(returnArray['user_info']['description']));
                }
                $("#" + userField.attr("id") + "_selector").val(returnArray['user_info']['user_id']);
                var oldId = $("#" + userField.attr("id")).val();
                $("#" + userField.attr("id")).val(returnArray['user_info']['user_id']);
                if (oldId != returnArray['user_info']['user_id']) {
                    $("#" + userField.attr("id")).trigger("change");
                }
            } else {
                userField.val("");
                $("#" + userField.attr("id") + "_selector").val("");
            }
        });
    });
    $(document).on("tap click", "#user_picker_filter", function () {
        getUserPickerList();
        return false;
    });
    $(document).on("tap click", ".user-picker", function () {
        var columnName = $(this).data("column_name");
        $("#" + columnName).trigger("keydown");
        $("#" + columnName + "_selector").trigger("keydown");
        var filterWhere = $(this).data("filter_where");
        if (filterWhere == undefined) {
            filterWhere = "";
        }
        $("#_user_picker_column_name").val(columnName);
        $("#_user_picker_filter_where").val(filterWhere);
        getUserPickerList();
        return false;
    });

    $(document).on("tap click", ".page-logout", function () {
        return goToLink(null, "logout.php");
    });
    $(document).on("tap click", ".page-login", function () {
        return goToLink(null, "loginform.php");
    });

    $(document).on("change", "#preset_record_id", function () {
        const presetRecordId = $(this).val();
        if (!empty(presetRecordId)) {
            loadAjaxRequest(scriptFilename + "?ajax=true&url_action=get_preset_record&preset_record_id=" + presetRecordId, function(returnArray) {
                for (var i in returnArray) {
                    if ($("#" + i).length == 0) {
                        continue;
                    }
                    const $fieldControl = $("#" + i);
                    if ($fieldControl.is("input[type=checkbox]")) {
                        if (empty(returnArray[i])) {
                            $fieldControl.prop("checked", false);
                        } else {
                            $fieldControl.prop("checked", true);
                        }
                    } else {
                        $fieldControl.val(returnArray[i]).trigger("change").trigger("blur");
                    }
                }
            });
        }
    });

    if ($(".accordion-form").length > 0) {
        var windowWidth = $(window).width();
        var activeTab = ($("#_active_tab").length > 0 ? $("#_active_tab").val() - 0 : 0);
        if (windowWidth > 850 && $().tabs) {
            $(".accordion-control-element").remove();
            $(".accordion-form").tabs({
                active: activeTab,
                beforeActivate: function (event, ui) {
                    $("#_edit_form").validationEngine("hideAll");
                },
                activate: function (event, ui) {
                    if ("scriptFilename" in window) {
                        $("body").addClass("no-waiting-for-ajax");
                        loadAjaxRequest(scriptFilename + "?ajax=true&url_action=select_tab&tab_index=" + $(this).tabs("option", "active"));
                    }
                },
                cookie: { expires: 120 }
            });
        } else if ($().accordion) {
            $(".tab-control-element").remove();
            $(".accordion-form").accordion({
                collapsible: true,
                heightStyle: "content",
                active: activeTab,
                activate: function (event, ui) {
                    if ("scriptFilename" in window) {
                        $("body").addClass("no-waiting-for-ajax");
                        loadAjaxRequest(scriptFilename + "?ajax=true&url_action=select_tab&tab_index=" + $(this).accordion("option", "active"));
                    }
                },
            });
        }
    }
    addCKEditor();

});

function getImagePicker() {
    loadAjaxRequest("/getimagepickerlist.php?ajax=true&search_text=" + encodeURIComponent($("#image_picker_filter").val()), function(returnArray) {
        $("#_image_picker_list").html("");
        if ("images" in returnArray) {
            $.each(returnArray['images'], function (index, imageArray) {
                $("#_image_picker_list").append("<div class='image-picker-item' data-image_id='" + imageArray['image_id'] +
                    "'><table cellspacing='0' cellpadding='0'><tr><td>" +
                    "<a class='image-picker-description' href='" + imageArray['url'] + "' rel='prettyPhoto'>" + imageArray['description'] +
                    "</a></td><td><img src='" + imageArray['url'].replace("-full-", "-small-") +
                    "' class='image-picker-thumbnail' /></td></tr></table></div>");
            });
            $("#_image_picker_list a[rel^='prettyPhoto']").prettyPhoto({
                social_tools: false,
                default_height: 480,
                default_width: 854,
                deeplinking: false
            });
        }
    });
}

function getContactPickerList() {
    $("#_contact_picker_list").find("tr.selected").removeClass("selected");
    loadAjaxRequest("/getcontactpickerlist.php?ajax=true", $("#_contact_picker_filter_form").serialize(), function(returnArray) {
        $("#_contact_picker_list").html("");
        if ("contacts" in returnArray) {
            $.each(returnArray['contacts'], function (index, contactArray) {
                $("#_contact_picker_list").append("<div class='contact-picker-item" + (contactArray['contact_id'] == $("#_contact_picker_current_value").val() ? " current-value" : "") + "'>" +
                    "<a target='_blank' class='contact-picker-description' href='/contactmaintenance.php?url_page=show&clear_filter=true&primary_id=" + contactArray['contact_id'] + "'>" +
                    contactArray['description'] + "</a>" +
                    "<button accesskey='c' class='contact-picker-choice' data-contact_id='" + contactArray['contact_id'] +
                    "'>Choose</button></div>");
            });
            $("#_contact_picker_list table").attr("cellspacing", "0px").attr("cellpadding", "0px");
            $('#_contact_picker_dialog').dialog({
                autoOpen: true,
                closeOnEscape: true,
                draggable: true,
                resizable: true,
                modal: true,
                position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                width: 1000,
                height: 550,
                title: 'Find and Choose or Add a Contact',
                buttons: {
                    Close: function (event) {
                        $("#_contact_picker_dialog").dialog("close");
                    }
                }
            });
        }
    });
}

function getUserPickerList() {
    $("#_user_picker_list").find("tr.selected").removeClass("selected");
    loadAjaxRequest("/getuserpickerlist.php?ajax=true", $("#_user_picker_filter_form").serialize(), function(returnArray) {
        $("#_user_picker_list").html("");
        if ("users" in returnArray) {
            $.each(returnArray['users'], function (index, userArray) {
                $("#_user_picker_list").append("<div class='user-picker-item'>" +
                    "<a target='_blank' class='user-picker-description' href='/usermaintenance.php?url_page=show&clear_filter=true&primary_id=" + userArray['user_id'] + "'>" +
                    userArray['description'] + "</a><button accesskey='c' class='user-picker-choice' data-user_id='" + userArray['user_id'] +
                    "'>Choose</button></div>");
            });
            $("#_user_picker_list table").attr("cellspacing", "0px").attr("cellpadding", "0px");
            $('#_user_picker_dialog').dialog({
                autoOpen: true,
                closeOnEscape: true,
                draggable: true,
                resizable: false,
                modal: true,
                position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                width: 850,
                height: 550,
                title: 'Choose a user',
                buttons: {
                    Close: function (event) {
                        $("#_user_picker_dialog").dialog("close");
                    }
                }
            });
        }
    });
}

function showChanges(linkUrl, primaryId, tableName) {
    loadAjaxRequest(linkUrl + "?ajax=true&url_action=get_changes&table_name=" + tableName + "&primary_id=" + primaryId, function(returnArray) {
        if ('changes' in returnArray) {
            $("#_changes_table").html(returnArray['changes']);
            $('#_changes_dialog').dialog({
                closeOnEscape: true,
                draggable: true,
                modal: true,
                resizable: true,
                position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                width: 1200,
                title: 'Changes',
                buttons: {
                    Close: function (event) {
                        $("#_changes_dialog").dialog('close');
                    }
                }
            });
        }
    });
}

// Check to see if changes have been made in the page

function changesMade() {
    if ($("body").data("just_saved") == "true") {
        return false;
    }
    var returnValue = false;
    if (typeof CKEDITOR !== "undefined") {
        for (instance in CKEDITOR.instances) {
            CKEDITOR.instances[instance].updateElement();
        }
    }
    $(".editable-list").each(function () {
        if ($(this).hasClass("no-add")) {
            return true;
        }
        $(this).find(".editable-list-data-row").each(function () {
            var allEmpty = true;
            $(this).find("input,select,textarea").each(function () {
                if (!empty($(this).val())) {
                    allEmpty = false;
                    return true;
                }
            });
            if (allEmpty) {
                $(this).remove();
            }
        });
    });
    if ($("#_permission").val() == "1") {
        return false;
    }
    if ("manualChangesMade" in window && manualChangesMade) {
        console.log("Manual changes made");
        return true;
    }
    $("input").add("textarea").add("select").each(function () {
        if ($(this).hasClass("ignore-changes")) {
            return true;
        }
        if (($(this).data("check_crc_anyway") == undefined || $(this).data("check_crc_anyway") == "") && ($(this).prop("readonly") || $(this).prop("disabled") || $(this).hasClass("disabled-select"))) {
            return true;
        } else {
            if ($(this).data("field-changed")) {
                returnValue = true;
                return false;
            }
            var crcValue = $(this).data("crc_value");
            if (crcValue == "#00000000" && $(this).is("input[type=checkbox]")) {
                crcValue = getCrcValue("0");
            }
            if ((typeof crcValue != 'undefined') && !(crcValue === null) && crcValue.length > 0) {
                if ($(this).is("input[type=radio]")) {
                    var fieldName = $(this).attr("name");
                    var currentValue = ($("input[type=radio][name='" + fieldName + "']:checked").length == 0 ? "" : $("input[type=radio][name='" + fieldName + "']:checked").val());
                } else {
                    var currentValue = ($(this).attr("type") == "checkbox" ? ($(this).prop("checked") ? "1" : "0") : $(this).val());
                }
                if (currentValue === null) {
                    currentValue = "";
                }
                var currentCrcValue = getCrcValue(currentValue.trim());
                if (currentCrcValue != crcValue) {
                    if ($("#_superuser_logged_in").length > 0) {
                        console.log($(this).attr("id") + ":" + crcValue + ":" + currentCrcValue);
                    }
                    returnValue = true;
                    return false;
                }
            }
        }
    });
    return returnValue;
}

// Show dialog box to asked if the user wants to save changes

function askAboutChanges(afterFunction) {
    if (typeof dontAskAboutChanges == "function") {
        if (dontAskAboutChanges()) {
            afterFunction();
            return;
        }
    }
    $('#_save_changes_dialog').dialog({
        autoOpen: false,
        closeOnEscape: true,
        draggable: false,
        modal: true,
        resizable: false,
        width: 400,
        position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
        title: 'Save Changes?',
        buttons: {
            Yes: function (event) {
                if (!("saveChanges" in window)) {
                    displayErrorMessage("Save function does not exist");
                    $("#_save_changes_dialog").dialog('close');
                    return;
                }
                $("#_save_changes_dialog").dialog('close');
                saveChanges(function () {
                    afterFunction();
                }, function () {
                });
            },
            No: function (event) {
                if (!("ignoreChanges" in window)) {
                    displayErrorMessage("Ignore function does not exist");
                    $("#_save_changes_dialog").dialog('close');
                    return;
                }
                ignoreChanges(function () {
                    $("#_save_changes_dialog").dialog('close');
                    afterFunction();
                });
            },
            Cancel: function (event) {
                $("#_save_changes_dialog").dialog('close');
            }
        }
    });
    if ($("#_save_changes_dialog").data("keypress_added") != "true") {
        $('#_save_changes_dialog').closest(".ui-dialog").keypress(function (event) {
            if ($('#_save_changes_dialog').dialog('isOpen')) {
                switch (event.which) {
                    // "Y" for yes
                    case 121:
                    case 89:
                        if (!("saveChanges" in window)) {
                            displayErrorMessage("Save function does not exist");
                            $("#_save_changes_dialog").dialog('close');
                            break;
                        }
                        $('#_save_changes_dialog').dialog('close');
                        saveChanges(function () {
                            afterFunction();
                        });
                        break;
                    // "N" for no
                    case 110:
                    case 78:
                        if (!("ignoreChanges" in window)) {
                            displayErrorMessage("Ignore function does not exist");
                            $("#_save_changes_dialog").dialog('close');
                            break;
                        }
                        ignoreChanges(function () {
                            $('#_save_changes_dialog').dialog('close');
                            afterFunction();
                        });
                        break;
                }
            }
        })
        $("#_save_changes_dialog").data("keypress_added", "true");
    }
    $("#_save_changes_dialog").dialog("open");
}

function ignoreChanges(afterFunction) {
    afterFunction();
}

function closeBuildContentDialog(elementId) {
    $("#_build_content_dialog").dialog('close');
    if ($("#" + elementId + "-ace_editor").length > 0) {
        var javascriptEditor = ace.edit(elementId + "-ace_editor");
        if ($("#" + elementId).length > 0 && !empty(javascriptEditor)) {
            javascriptEditor.setValue($("#" + elementId).val(), 1);
        }
        $("#" + elementId + "-ace_editor").removeClass("hidden");
    }
}
