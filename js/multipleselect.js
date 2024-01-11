$(function () {
    $(document).on("click", ".add-new-multiple-select", function () {
        const linkUrl = $(this).closest("table.selection-control").data("link_url");
        $(this).closest("table.selection-control").addClass("option-being-added").val("");
        window.open(linkUrl);
        return false;
    });
    $(window).on("focus", function () {
        $("table.selection-control.option-being-added").each(function () {
            const thisElement = $(this);
            thisElement.removeClass("option-being-added");
            loadAjaxRequest(scriptFilename + "?ajax=true&url_action=get_control_table_options&control_code=" + $(this).data("control_code"), function(returnArray) {
                if ("options" in returnArray) {
                    thisElement.find(".selection-choices-div").find("ul").find("li").remove();
                    thisElement.find(".selection-chosen-div").find("ul").find("li").remove();
                    let count = 0;
                    const controlId = ("control_id" in returnArray ? returnArray['control_id'] : "");
                    for (var i in returnArray['options']) {
                        count++;
                        const liElement = "<li data-id='" + returnArray['options'][i]['key_value'] + "' data-sort_order='" + count + "'>" + returnArray['options'][i]['description'] + "</li>";
                        thisElement.find(".selection-choices-div").find("ul").append(liElement);
                    }
                    if ("control_id" in returnArray) {
                        var newList = thisElement.find(".selector-value-list").val();
                        newList += (empty(newList) ? "" : ",") + returnArray['control_id'];
                        thisElement.find(".selector-value-list").val(newList);
                    }
                    thisElement.find(".selector-value-list").trigger("change");
                }
            });
        });
    });
    $(document).on("click", ".select-all-multiple-select", function () {
        var allValues = $(this).closest(".selection-control").find(".selector-value-list").val();
        $(this).closest(".selection-control").find(".selection-choices-div").find("li").not(".hidden").each(function () {
            allValues += (allValues == "" ? "" : ",") + $(this).data("id");
        });
        $(this).closest(".selection-control").find(".selector-value-list").val(allValues).trigger("change");
        return false;
    });
    $(document).on("click", ".remove-all-multiple-select", function () {
        $(this).closest(".selection-control").find(".selector-value-list").val("").trigger("change");
        return false;
    });
    $(document).on("click", ".sort-multiple-select", function () {
        var items = $(this).closest(".selection-control").find(".selection-chosen-div").find("ul li").get();
        items.sort(function (a, b) {
            var keyA = $(a).text().toLowerCase();
            var keyB = $(b).text().toLowerCase();

            if (keyA < keyB) return -1;
            if (keyA > keyB) return 1;
            return 0;
        });
        var ul = $(this).closest(".selection-control").find(".selection-chosen-div").find("ul");
        $.each(items, function (i, li) {
            ul.append(li);
        });
        var newList = "";
        $(this).closest(".selection-control").find(".selection-chosen-div li").each(function () {
            if ($(this).data("id") != "") {
                if (newList != "") {
                    newList += ",";
                }
                newList += $(this).data("id");
            }
        });
        $(this).closest(".selection-control").find(".selector-value-list").val(newList);
        $(this).closest(".selection-control").find(".selector-value-list").trigger("change");
        return false;
    });
    $(document).on("click",".multiple-select-checkbox-option input",function() {
        $(this).closest(".selection-control").find(".selector-value-list").trigger("change");
    });
    $(document).on("click", ".selection-chosen-div ul li", function (event) {
        var parentOffset = $(this).parent().offset();
        var relX = event.pageX - parentOffset.left;
        if (relX > ($(this).width() - 30)) {
            var newList = "";
            var thisId = $(this).closest(".selection-control").attr("id");
            var userSetsOrder = ($(this).closest(".selection-control").data("user_order") == "yes");
            var selectedOption = $(this).remove();
            $("#" + thisId).find(".selection-choices-div ul").append(selectedOption);
            var thisList = $("#" + thisId + " .selection-choices-div ul");
            var listitems = thisList.children('li').get();
            listitems.sort(function (a, b) {
                var aValue = parseInt($(a).data("sort_order"));
                var bValue = parseInt($(b).data("sort_order"));
                return (aValue == bValue ? 0 : (aValue < bValue ? -1 : 1));
            });
            $.each(listitems, function (idx, itm) {
                thisList.append(itm);
            });
            if (!userSetsOrder) {
                var thisList = $("#" + thisId + " .selection-chosen-div ul");
                var listitems = thisList.children('li').get();
                listitems.sort(function (a, b) {
                    var aValue = parseInt($(a).data("sort_order"));
                    var bValue = parseInt($(b).data("sort_order"));
                    return (aValue == bValue ? 0 : (aValue < bValue ? -1 : 1));
                });
                $.each(listitems, function (idx, itm) {
                    thisList.append(itm);
                });
            }
            var newList = "";
            $("#" + thisId + " .selection-chosen-div li").each(function () {
                if ($(this).data("id") != "") {
                    if (newList != "") {
                        newList += ",";
                    }
                    newList += $(this).data("id");
                }
            });
            $("#" + thisId + " .selector-value-list").val(newList);
            $("#" + thisId + " .selector-value-list").trigger("change");
        }
    });
    $(document).on("dblclick", ".selection-choices-div ul li,.selection-chosen-div ul li", function () {
        var thisId = $(this).closest(".selection-control").attr("id");
        var userSetsOrder = ($(this).closest(".selection-control").data("user_order") == "yes");
        var optionSelected = $(this).closest("div").is(".selection-choices-div");
        var selectedOption = $(this).remove();
        if (optionSelected) {
            $("#" + thisId).find(".selection-chosen-div ul").append(selectedOption);
        } else {
            $("#" + thisId).find(".selection-choices-div ul").append(selectedOption);
        }
        var thisList = $("#" + thisId + " .selection-choices-div ul");
        var listitems = thisList.children('li').get();
        listitems.sort(function (a, b) {
            var aValue = parseInt($(a).data("sort_order"));
            var bValue = parseInt($(b).data("sort_order"));
            return (aValue == bValue ? 0 : (aValue < bValue ? -1 : 1));
        });
        $.each(listitems, function (idx, itm) {
            thisList.append(itm);
        });
        if (!userSetsOrder) {
            var thisList = $("#" + thisId + " .selection-chosen-div ul");
            var listitems = thisList.children('li').get();
            listitems.sort(function (a, b) {
                var aValue = parseInt($(a).data("sort_order"));
                var bValue = parseInt($(b).data("sort_order"));
                return (aValue == bValue ? 0 : (aValue < bValue ? -1 : 1));
            });
            $.each(listitems, function (idx, itm) {
                thisList.append(itm);
            });
        }
        var newList = "";
        $("#" + thisId + " .selection-chosen-div li").each(function () {
            if (!empty($(this).data("id"))) {
                if (newList != "") {
                    newList += ",";
                }
                newList += $(this).data("id");
            }
        });
        $("#" + thisId + " .selector-value-list").val(newList);
        $("#" + thisId + " .selector-value-list").trigger("change");
    });
    $(".selection-control-filter").keyup(function (event) {
        var filterValue = $(this).val().toLowerCase();
        var fieldName = $(this).data("field_name");
        $(".selection-choices-div ." + fieldName + "-connector li").each(function () {
            if ($(this).html().toLowerCase().indexOf(filterValue) >= 0) {
                $(this).removeClass("hidden");
            } else {
                $(this).addClass("hidden");
            }
        });
        if (event.which == 13 || event.which == 3) {
            $(".selection-choices-div ." + fieldName + "-connector li").not(".inactive-option").not(".hidden").first().trigger("dblclick");
        }
    });
    $(".selection-control").each(function () {
        createSelectionControl($(this));
    });
    $(document).on("setup",".selection-control", function() {
        var userSetsOrder = ($(this).data("user_order") == "yes");
        const thisId = $(this).attr("id");
        if (!userSetsOrder) {
            var thisList = $("#" + thisId + " .selection-chosen-div ul");
            var listitems = thisList.children('li').get();
            listitems.sort(function (a, b) {
                var aValue = parseInt($(a).data("sort_order"));
                var bValue = parseInt($(b).data("sort_order"));
                return (aValue == bValue ? 0 : (aValue < bValue ? -1 : 1));
            });
            $.each(listitems, function (idx, itm) {
                thisList.append(itm);
            });
        }
    });
    $(document).on("click",".selection-control-button-choice",function() {
        $(this).toggleClass("selected");
        let idList = "";
        $(this).closest(".selection-control-button-wrapper").find(".selection-control-button-choice").each(function() {
            if ($(this).hasClass("selected")) {
                idList += (empty(idList) ? "" : ",") + $(this).data("id");
            }
        });
        $(this).closest(".selection-control-button-wrapper").find(".selector-value-list").val(idList);
    });
    $(document).on("click",".multiple-select-checkbox-option input",function() {
        let idList = "";
        $(this).closest(".multiple-select-checkbox-wrapper").find(".multiple-select-checkbox-option input").each(function() {
            if ($(this).prop("checked")) {
                idList += (empty(idList) ? "" : ",") + $(this).data("id");
            }
        });
        $(this).closest(".multiple-select-checkbox-wrapper").find(".selector-value-list").val(idList).trigger("change");
    });
    $(document).on("change", ".selector-value-list", function () {
        let thisId = $(this).attr("id");
        let thisValue = $(this).val();
        let valuesArray = thisValue.split(",");
        $("#_delete_" + thisId).val("");
        if ($("#_" + thisId + "_selector").hasClass("selection-control-button-wrapper")) {
            $("#_" + thisId + "_selector").find(".selection-control-button-choice").removeClass("selected");
            for (let i in valuesArray) {
                $("#_" + thisId + "_selector .selection-control-button-choice").each(function () {
                    if ($(this).data("id") == valuesArray[i]) {
                        $(this).addClass("selected");
                    }
                });
            }
            let unusedIds = "";
            $("#_" + thisId + "_selector .selection-control-button-choice").each(function () {
                if (!isInArray($(this).data("id"), valuesArray)) {
                    unusedIds += (empty(unusedIds) ? "" : ",") + $(this).data("id");
                }
            });
            $("#_delete_" + thisId).val(unusedIds);
        } else if ($("#_" + thisId + "_wrapper").hasClass("multiple-select-checkbox-wrapper")) {
            let unusedIds = "";
            $("#_" + thisId + "_wrapper").find(".multiple-select-checkbox-option input").each(function() {
                $(this).prop("checked",false);
                if (!isInArray($(this).data("id"), valuesArray)) {
                    unusedIds += (empty(unusedIds) ? "" : ",") + $(this).data("id");
                }
            });
            for (let i in valuesArray) {
                $("#_" + thisId + "_wrapper .multiple-select-checkbox-option input").each(function () {
                    if ($(this).data("id") == valuesArray[i]) {
                        $(this).prop("checked",true);
                    }
                });
            }
            $("#_delete_" + thisId).val(unusedIds);
        } else {
            $("#_" + thisId + "_selector .selection-chosen-div ul li").remove().appendTo("#_" + thisId + "_selector .selection-choices-div ul");
            let thisList = $("#_" + thisId + "_selector .selection-choices-div ul");
            let listitems = thisList.children('li').get();
            listitems.sort(function (a, b) {
                let aValue = parseInt($(a).data("sort_order"));
                let bValue = parseInt($(b).data("sort_order"));
                return (aValue == bValue ? 0 : (aValue < bValue ? -1 : 1));
            });
            $.each(listitems, function (idx, itm) {
                thisList.append(itm);
            });
            for (let i in valuesArray) {
                $("#_" + thisId + "_selector .selection-choices-div ul li").each(function () {
                    if ($(this).data("id") == valuesArray[i]) {
                        $(this).remove().appendTo("#_" + thisId + "_selector .selection-chosen-div ul");
                    }
                });
            }
            let unusedIds = "";
            $("#_" + thisId + "_selector .selection-choices-div ul li").each(function () {
                if (!isInArray($(this).data("id"), valuesArray)) {
                    unusedIds += (empty(unusedIds) ? "" : ",") + $(this).data("id");
                }
            });
            $("#_delete_" + thisId).val(unusedIds);
        }
    });
});

function createSelectionControl(thisElement) {
    var connectsWith = thisElement.data("connector");
    var thisId = thisElement.attr("id");
    var userSetsOrder = (thisElement.data("user_order") == "yes");
    $("#" + thisId + " ul").sortable({
        tolerance: "pointer",
        connectWith: "." + connectsWith,
        update: function (e, ui) {
            var thisList = $("#" + thisId + " .selection-choices-div ul");
            var listitems = thisList.children('li').get();
            listitems.sort(function (a, b) {
                var aValue = parseInt($(a).data("sort_order"));
                var bValue = parseInt($(b).data("sort_order"));
                return (aValue == bValue ? 0 : (aValue < bValue ? -1 : 1));
            });
            $.each(listitems, function (idx, itm) {
                thisList.append(itm);
            });
            if (!userSetsOrder) {
                var thisList = $("#" + thisId + " .selection-chosen-div ul");
                var listitems = thisList.children('li').get();
                listitems.sort(function (a, b) {
                    var aValue = parseInt($(a).data("sort_order"));
                    var bValue = parseInt($(b).data("sort_order"));
                    return (aValue == bValue ? 0 : (aValue < bValue ? -1 : 1));
                });
                $.each(listitems, function (idx, itm) {
                    thisList.append(itm);
                });
            }
            var newList = "";
            $("#" + thisId + " .selection-chosen-div li").each(function () {
                if ($(this).data("id") != "") {
                    if (newList != "") {
                        newList += ",";
                    }
                    newList += $(this).data("id");
                }
            });
            $("#" + thisId + " .selector-value-list").val(newList);
            if ($(this).closest("div").hasClass("selection-chosen-div")) {
                $("#" + thisId + " .selector-value-list").trigger("change");
            }
        }
    }).disableSelection();
}