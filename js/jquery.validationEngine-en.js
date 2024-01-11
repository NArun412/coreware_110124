(function($){
    $.fn.validationEngineLanguage = function(){
    };
    $.validationEngineLanguage = {
        newLang: function(){
            $.validationEngineLanguage.allRules = {
                "required": { // Add your regex rules here, you can take telephone as an example
                    "regex": "none",
                    "alertText": "<span class='fad fa-triangle-exclamation'></span> Required",
                    "alertTextCheckboxMultiple": "Please select an option",
                    "alertTextCheckbox": "This checkbox is required",
                    "alertTextDateRange": "Both date range fields are required"
                },
                "requiredInFunction": {
                    "func": function(field, rules, i, options){
                        return (field.val() == "test") ? true : false;
                    },
                    "alertText": "<span class='fad fa-triangle-exclamation'></span> Field must equal test"
                },
                "dateRange": {
                    "regex": "none",
                    "alertText": "<span class='fad fa-triangle-exclamation'></span> Invalid ",
                    "alertText2": "Date Range"
                },
                "dateTimeRange": {
                    "regex": "none",
                    "alertText": "<span class='fad fa-triangle-exclamation'></span> Invalid ",
                    "alertText2": "Date Time Range"
                },
                "minSize": {
                    "regex": "none",
                    "alertText": "<span class='fad fa-triangle-exclamation'></span> Minimum ",
                    "alertText2": " characters allowed"
                },
                "maxSize": {
                    "regex": "none",
                    "alertText": "<span class='fad fa-triangle-exclamation'></span> Maximum ",
                    "alertText2": " characters allowed"
                },
				"groupRequired": {
                    "regex": "none",
                    "alertText": "<span class='fad fa-triangle-exclamation'></span> You must fill one of the following fields"
                },
                "min": {
                    "regex": "none",
                    "alertText": "<span class='fad fa-triangle-exclamation'></span> Minimum value is "
                },
                "max": {
                    "regex": "none",
                    "alertText": "<span class='fad fa-triangle-exclamation'></span> Maximum value is "
                },
                "past": {
                    "regex": "none",
                    "alertText": "Date cannot be in the future"
                },
                "future": {
                    "regex": "none",
                    "alertText": "Date cannot be in the past"
                },
                "maxCheckbox": {
                    "regex": "none",
                    "alertText": "<span class='fad fa-triangle-exclamation'></span> Maximum ",
                    "alertText2": " option(s) allowed"
                },
                "minCheckbox": {
                    "regex": "none",
                    "alertText": "<span class='fad fa-triangle-exclamation'></span> Please select at least ",
                    "alertText2": " option(s)"
                },
                "equals": {
                    "regex": "none",
                    // change alert - KDG
                    "alertText": "<span class='fad fa-triangle-exclamation'></span> Field values do not match"
                },
// Custom Regex added - KDG
                "color": {
                	"regex": /^[A-Fa-f0-9]{6}$/,
                	"alertText": "Invalid color value"
                },
				"ssn": {
					"regex": /^(?!\b(\d)1+-(\d)1+-(\d)1+\b)(?!219-09-9999|078-05-1120)(?!666|000|9\d{2})\d{3}-(?!00)\d{2}-(?!0{4})\d{4}$/,
					"alertText": "Invalid Social Security Number, must be in 999-99-9999 format",
					"formatRegex": /(\d{3})(\d{2})(\d{4})/,
					"formatValue": "$1-$2-$3"
				},
                "routingNumber": {
                    "regex": /^\d{9}$/,
                    "alertText": "<span class='fad fa-triangle-exclamation'></span> Invalid routing number, must be 9 digits"
                },
// End Custom Code
                "creditCard": {
                    "regex": "none",
                    "alertText": "<span class='fad fa-triangle-exclamation'></span> Invalid credit card number"
                },
                "phone": {
                    "alertText": "<span class='fad fa-triangle-exclamation'></span> Invalid phone number",
// Add format function - KDG
                    "formatFunction": "_formatPhone"
                },
                "email": {
                    // HTML5 compatible email regex ( http://www.whatwg.org/specs/web-apps/current-work/multipage/states-of-the-type-attribute.html#    e-mail-state-%28type=email%29 )
                    "regex": /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/,
                    "alertText": "<span class='fad fa-triangle-exclamation'></span> Invalid email address"
                },
                "integer": {
// Format function added - KDG
                    "formatFunction": "_formatNumber",
                    "regex": /^[-,0-9]+$/,
                    "alertText": "<span class='fad fa-triangle-exclamation'></span> Not a valid integer"
                },
                "number": {
// Format function added - KDG
                    "formatFunction": "_formatNumber",
                    // Number, including positive, negative, and floating decimal. credit: orefalo
                    "regex": /^[\-\+]?((([0-9]{1,3})([,][0-9]{3})*)|([0-9]+))?([\.]([0-9]+))?$/,
// change alert text - KDG
                    "alertText": "<span class='fad fa-triangle-exclamation'></span> Not a valid number"
                },
                "date": {
// Date changed - KDG
                	"formatFunction": "_formatDate",
                    "alertText": "<span class='fad fa-triangle-exclamation'></span> Invalid date, must be in mm/dd/yyyy format"
//                    "regex": /^\d{4}[\/\-](0?[1-9]|1[012])[\/\-](0?[1-9]|[12][0-9]|3[01])$/,
//                    "alertText": "* Invalid date, must be in YYYY-MM-DD format"
                },
                "pciPassword": {
// PCI Password - KDG
                	"formatFunction": "_pciPassword",
                    "alertText": "<span class='fad fa-triangle-exclamation'></span> Password must meet minimum length requirements and include a number, lowercase letter and uppercase letter"
//                    "regex": /^\d{4}[\/\-](0?[1-9]|1[012])[\/\-](0?[1-9]|[12][0-9]|3[01])$/,
//                    "alertText": "* Invalid date, must be in YYYY-MM-DD format"
                },
                "time": {
// Time added - KDG
                	"formatFunction": "_formatTime",
                    "alertText": "<span class='fad fa-triangle-exclamation'></span> Invalid time, must be in hh:mm pm/am format"
                },
                "ipv4": {
                    "regex": /^((([01]?[0-9]{1,2})|(2[0-4][0-9])|(25[0-5]))[.]){3}(([0-1]?[0-9]{1,2})|(2[0-4][0-9])|(25[0-5]))$/,
                    "alertText": "<span class='fad fa-triangle-exclamation'></span> Invalid IP address"
                },
                "url": {
                    "regex": /^(https?|ftp):\/\/(((([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&'\(\)\*\+,;=]|:)*@)?(((\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\.(\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\.(\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\.(\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5]))|((([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.)+(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.?)(:\d*)?)(\/((([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&'\(\)\*\+,;=]|:|@)+(\/(([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&'\(\)\*\+,;=]|:|@)*)*)?)?(\?((([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&'\(\)\*\+,;=]|:|@)|[\uE000-\uF8FF]|\/|\?)*)?(\#((([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&'\(\)\*\+,;=]|:|@)|\/|\?)*)?$/i,
                    "alertText": "<span class='fad fa-triangle-exclamation'></span> Invalid URL"
                },
                "onlyNumberSp": {
                    "regex": /^[0-9\ ]+$/,
                    "alertText": "<span class='fad fa-triangle-exclamation'></span> Numbers only"
                },
                "onlyLetterSp": {
                    "regex": /^[a-zA-Z\ \']+$/,
                    "alertText": "<span class='fad fa-triangle-exclamation'></span> Letters only"
                },
                "onlyLetterNumber": {
                    "regex": /^[0-9a-zA-Z]+$/,
                    "alertText": "<span class='fad fa-triangle-exclamation'></span> No special characters allowed"
                },
                "dateFormat":{
                    "regex": /^\d{4}[\/\-](0?[1-9]|1[012])[\/\-](0?[1-9]|[12][0-9]|3[01])$|^(?:(?:(?:0?[13578]|1[02])(\/|-)31)|(?:(?:0?[1,3-9]|1[0-2])(\/|-)(?:29|30)))(\/|-)(?:[1-9]\d\d\d|\d[1-9]\d\d|\d\d[1-9]\d|\d\d\d[1-9])$|^(?:(?:0?[1-9]|1[0-2])(\/|-)(?:0?[1-9]|1\d|2[0-8]))(\/|-)(?:[1-9]\d\d\d|\d[1-9]\d\d|\d\d[1-9]\d|\d\d\d[1-9])$|^(0?2(\/|-)29)(\/|-)(?:(?:0[48]00|[13579][26]00|[2468][048]00)|(?:\d\d)?(?:0[48]|[2468][048]|[13579][26]))$/,
                    "alertText": "<span class='fad fa-triangle-exclamation'></span> Invalid Date"
                },
                //tls warning:homegrown not fielded
				"dateTimeFormat": {
	                "regex": /^\d{4}[\/\-](0?[1-9]|1[012])[\/\-](0?[1-9]|[12][0-9]|3[01])\s+(1[012]|0?[1-9]){1}:(0?[1-5]|[0-6][0-9]){1}:(0?[0-6]|[0-6][0-9]){1}\s+(am|pm|AM|PM){1}$|^(?:(?:(?:0?[13578]|1[02])(\/|-)31)|(?:(?:0?[1,3-9]|1[0-2])(\/|-)(?:29|30)))(\/|-)(?:[1-9]\d\d\d|\d[1-9]\d\d|\d\d[1-9]\d|\d\d\d[1-9])$|^((1[012]|0?[1-9]){1}\/(0?[1-9]|[12][0-9]|3[01]){1}\/\d{2,4}\s+(1[012]|0?[1-9]){1}:(0?[1-5]|[0-6][0-9]){1}:(0?[0-6]|[0-6][0-9]){1}\s+(am|pm|AM|PM){1})$/,
                    "alertText": "<span class='fad fa-triangle-exclamation'></span> Invalid Date or Date Format",
                    "alertText2": "Expected Format: ",
                    "alertText3": "mm/dd/yyyy hh:mm:ss AM|PM or ",
                    "alertText4": "yyyy-mm-dd hh:mm:ss AM|PM"
	            }
            };

        }
    };

    $.validationEngineLanguage.newLang();

})(jQuery);
