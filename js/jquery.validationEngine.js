/*
 * Inline Form Validation Engine 2.6.1, jQuery plugin
 *
 * Copyright(c) 2010, Cedric Dugas
 * http://www.position-absolute.com
 *
 * 2.0 Rewrite by Olivier Refalo
 * http://www.crionics.com
 *
 * Form validation engine allowing custom regex rules to be added.
 * Licensed under the MIT License
 */
 (function($) {

	"use strict";

	var methods = {

		/**
		* Kind of the constructor, called before any action
		* @param {Map} user options
		*/
		init: function(options) {
			var form = this;
			if (!form.data('jqv') || form.data('jqv') == null ) {
				options = methods._saveOptions(form, options);
				// bind all formError elements to close on click
				$(".formError").on("tap click", function() {
					$(this).fadeOut(150, function() {
						// remove prompt once invisible
						$(this).parent('.formErrorOuter').remove();
						$(this).remove();
					});
				});
			}
			return this;
		},
		/**
		* Attachs jQuery.validationEngine to form.submit and field.blur events
		* Takes an optional params: a list of options
		* ie. jQuery("#formID1").validationEngine('attach', {promptPosition : "centerRight"});
		*/
		attach: function(userOptions) {

//			if(!$(this).is("form")) {
//				console.log("Sorry, jqv.attach() only applies to a form");
//				return this;
//			}

			var form = this;
			this.addClass("validated-wrapper-element");
			var options;

			if(userOptions)
				options = methods._saveOptions(form, userOptions);
			else
				options = form.data('jqv');

			options.validateAttribute = (form.find("[data-validation-engine*=validate]").length) ? "data-validation-engine" : "class";
			if (options.binded) {

// allow binding by live - KDG
				$(form).on(options.validationEventTrigger,"["+options.validateAttribute+"*=validate]:not(.datepicker)",methods._onFieldEvent);
				$(form).on("tap click","["+options.validateAttribute+"*=validate][type=checkbox],["+options.validateAttribute+"*=validate][type=radio]",methods._onFieldEvent);
				$(form).on(options.validationEventTrigger,"["+options.validateAttribute+"*=validate][class*=datepicker]",methods._onFieldEvent);
			}
			if (options.autoPositionUpdate) {
				$(window).on("resize", {
					"noAnimation": true,
					"formElem": form
				}, methods.updatePromptsPosition);
			}
			// bind form.submit
// Remove code to automatically validate on submit - KDG
//			form.on("submit", methods._onSubmitEvent);
			return this;
		},
		/**
		* Unregisters any bindings that may point to jQuery.validationEngine
		*/
		detach: function() {

			if(!$(this).is('.validated-wrapper-element')) {
				console.log("Sorry, jqv.detach() only applies to a form");
				return this;
			}

			var form = this;
			var options = form.data('jqv');

			// unbind fields
			form.find("["+options.validateAttribute+"*=validate]").not("[type=checkbox]").off(options.validationEventTrigger, methods._onFieldEvent);
			form.find("["+options.validateAttribute+"*=validate][type=checkbox],[class*=validate][type=radio]").off("click", methods._onFieldEvent);

			// unbind form.submit
			form.off("submit", methods.onAjaxFormComplete);

			// unbind live fields (kill)
			form.find("["+options.validateAttribute+"*=validate]").not("[type=checkbox]").die(options.validationEventTrigger, methods._onFieldEvent);
			form.find("["+options.validateAttribute+"*=validate][type=checkbox]").die("click", methods._onFieldEvent);

			// unbind form.submit
			form.die("submit", methods.onAjaxFormComplete);
			form.removeData('jqv');

			if (options.autoPositionUpdate)
				$(window).off("resize", methods.updatePromptsPosition);

			return this;
		},
		/**
		* Validates either a form or a list of fields, shows prompts accordingly.
		* Note: There is no ajax form validation with this method, only field ajax validation are evaluated
		*
		* @return true if the form validates, false if it fails
		*/
		// allow function to specify skipping ajax validation - KDG
		validate: function() {
// Clear autohidetimer - KDG
			var options = $(this).data('jqv');
			if (options.autoHideTimer) {
				clearTimeout(options.autoHideTimer);
			}
			var element = $(this);
			var valid = null;
			if (element.is("form") && !element.hasClass("validated-wrapper-element")) {
				element.addClass("validated-wrapper-element");
			} else if (element.is("div") && !element.hasClass("validated-wrapper-element")) {
				element.addClass("validated-wrapper-element");
			}
			if (element.is(".validated-wrapper-element") && !element.hasClass('validating')) {
				element.addClass('validating');
				var options = element.data('jqv');
				valid = methods._validateFields(this);

				// If the form doesn't validate, clear the 'validating' class before the user has a chance to submit again
				setTimeout(function(){
					element.removeClass('validating');
				}, 100);
				if (valid && options.onSuccess) {
					options.onSuccess();
				} else if (!valid && options.onFailure) {
					options.onFailure();
				}
			} else if (element.is('.validated-wrapper-element')) {
				element.removeClass('validating');
			} else {
				// field validation
				var form = element.closest('.validated-wrapper-element');
				var options = (form.data('jqv')) ? form.data('jqv') : $.validationEngine.defaults;
				valid = methods._validateField(element, options);

				if (valid && options.onFieldSuccess)
					options.onFieldSuccess();
				else if (options.onFieldFailure && options.InvalidFields.length > 0) {
					options.onFieldFailure();
				}
			}
			return valid;
		},
		/**
		* Redraw prompts position, useful when you change the DOM state when validating
		*/
		updatePromptsPosition: function(event) {

			if (event && this == window) {
				var form = event.data.formElem;
				var noAnimation = event.data.noAnimation;
			}
			else
				var form = $(this.closest('.validated-wrapper-element'));

			var options = form.data('jqv');
			// No option, take default one
			form.find('['+options.validateAttribute+'*=validate]').not(":disabled").each(function(){
				var field = $(this);
				if (options.prettySelect && field.is(":hidden"))
					field = form.find("#" + options.usePrefix + field.attr('id') + options.useSuffix);
				var prompt = methods._getPrompt(field);
				var promptText = $(prompt).find(".formErrorContent").html();

				if(prompt)
					methods._updatePrompt(field, $(prompt), promptText, undefined, false, options, noAnimation);
			});
			return this;
		},
		/**
		* Displays a prompt on a element.
		* Note that the element needs an id!
		*
		* @param {String} promptText html text to display type
		* @param {String} type the type of bubble: 'pass' (green), 'load' (black) anything else (red)
		* @param {String} possible values topLeft, topRight, bottomLeft, centerRight, bottomRight
		*/
		showPrompt: function(promptText, type, promptPosition) {

			var form = this.closest('.validated-wrapper-element');
			var options = form.data('jqv');
			// No option, take default one
			if(!options)
				options = methods._saveOptions(this, options);
			if(promptPosition)
				options.promptPosition=promptPosition;

			methods._showPrompt(this, promptText, type, false, options);
			return this;
		},
		/**
		* Closes form error prompts, CAN be individual
		*/
		hide: function() {
			var form = $(this).closest('.validated-wrapper-element');
			var options = form.data('jqv');
			var fadeDuration = (options && options.fadeDuration) ? options.fadeDuration : 0.3;
			var closingtag;

			if($(this).is(".validated-wrapper-element")) {
				closingtag = "parentForm"+methods._getClassName($(this).attr("id"));
			} else {
				closingtag = methods._getClassName($(this).attr("id")) +"formError";
			}
			$('.'+closingtag).fadeTo(fadeDuration, 0.3, function() {
				$(this).parent('.formErrorOuter').remove();
				$(this).remove();
			});
			return this;
		},
		/**
		* Closes all error prompts on the page
		*/
		hideAll: function() {

			var form = this;
			var options = form.data('jqv');
			var duration = options ? options.fadeDuration:0.3;
			$('.formError').fadeTo(duration, 0.3, function() {
				$(this).parent('.formErrorOuter').remove();
				$(this).remove();
			});
			return this;
		},
		/**
		* Typically called when user exists a field using tab or a mouse click, triggers a field
		* validation
		*/
		_onFieldEvent: function(event) {
			var field = $(this);
			if (empty(field.val())) {
				var defaultValue = field.data("default_value");
				if (!empty(defaultValue)) {
					field.val(defaultValue);
				}
			}
			var form = field.closest('.validated-wrapper-element');
			var options = form.data('jqv');
			options.eventTrigger = "field";
			// validate the current field
			window.setTimeout(function() {
				methods._validateField(field, options);
// Allow success function to be specified as at data of the field - KDG

				if (options.InvalidFields.length == 0) {
					if (options.onFieldSuccess) {
						options.onFieldSuccess();
					}
					var successFunction = field.data("success-function");
					if (successFunction != undefined) {
						successFunction += "('" + field.attr("id") + "')";
						eval(successFunction)
					}
				} else if (options.InvalidFields.length > 0) {
					if (options.onFieldFailure) {
						options.onFieldFailure();
					}
					var failureFunction = field.data("failure-function");
					if (failureFunction != undefined) {
						failureFunction += "('" + field.attr("id") + "')";
						eval(failureFunction)
					}
				}

			}, (event.data) ? event.data.delay : 0);

		},
		/**
		* Called when the form is submited, shows prompts accordingly
		*
		* @param {jqObject}
		*			form
		* @return false if form submission needs to be cancelled
		*/
		_onSubmitEvent: function() {
			var form = $(this);
			var options = form.data('jqv');
			options.eventTrigger = "submit";

			// validate each field
			// (- skip field ajax validation, not necessary IF we will perform an ajax form validation)
			var r=methods._validateFields(form);

			if (r && options.ajaxFormValidation) {
				methods._validateFormWithAjax(form, options);
				// cancel form auto-submission - process with async call onAjaxFormComplete
				return false;
			}

			if(options.onValidationComplete) {
				// !! ensures that an undefined return is interpreted as return false but allows a onValidationComplete() to possibly return true and have form continue processing
				return !!options.onValidationComplete(form, r);
			}
			return r;
		},
		/**
		* Return true if the ajax field validations passed so far
		* @param {Object} options
		* @return true, is all ajax validation passed so far (remember ajax is async)
		*/
		_checkAjaxStatus: function(options) {
			var status = true;
			$.each(options.ajaxValidCache, function(key, value) {
				if (!value) {
					status = false;
					// break the each
					return false;
				}
			});
			return status;
		},

		/**
		* Return true if the ajax field is validated
		* @param {String} fieldid
		* @param {Object} options
		* @return true, if validation passed, false if false or doesn't exist
		*/
		_checkAjaxFieldStatus: function(fieldid, options) {
			return options.ajaxValidCache[fieldid] == true;
		},
		/**
		* Validates form fields, shows prompts accordingly
		*
		* @param {jqObject}
		*			form
		*
		* @return true if form is valid, false if not, undefined if ajax form validation is done
		*/
		option: function(optionName, optionValue) {
			var form = this.closest('.validated-wrapper-element');
			var options = form.data('jqv');
			options[optionName] = optionValue;
			form.data("jpv",options);
		},
		_validateFields: function(form) {
			var options = form.data('jqv');
			// this variable is set to true if an error is found
			var errorFound = false;

			// Trigger hook, start validation
			form.trigger("jqv.form.validating");
			// first, evaluate status of non ajax fields
			var first_err=null;
			form.find('['+options.validateAttribute+'*=validate]').not(":disabled").each( function() {
				var field = $(this);
				var names = [];
				if ($.inArray(field.attr('name'), names) < 0) {
					errorFound |= methods._validateField(field, options);
					if (errorFound && first_err==null) {
// If using tabs, go to that tab - KDG
						if (options.focusFirstField && field.closest(".ui-tabs-panel").length > 0) {
							var panelId = field.closest(".ui-tabs-panel").attr("id");
							$("a[href='#" + panelId + "']").trigger("click");
						}
						if (field.is(":hidden") && options.prettySelect)
							first_err = field = form.find("#" + options.usePrefix + methods._jqSelector(field.attr('id')) + options.useSuffix);
						else
							first_err=field;
					}
					if (options.doNotShowAllErrorsOnSubmit)
						return false;
					names.push(field.attr('name'));

					//if option set, stop checking validation rules after one error is found
					if(options.showOneMessage == true && errorFound){
						return false;
					}
				}
			});

			// second, check to see if all ajax calls completed ok
			// errorFound |= !methods._checkAjaxStatus(options);

			// third, check status and scroll the container accordingly
			form.trigger("jqv.form.result", [errorFound]);

			if (errorFound) {
				if (options.scroll) {
					var destination=first_err.offset().top;
					var fixleft = first_err.offset().left;

					//prompt positioning adjustment support. Usage: positionType:Xshift,Yshift (for ex.: bottomLeft:+20 or bottomLeft:-20,+10)
					var positionType=options.promptPosition;
					if (typeof(positionType)=='string' && positionType.indexOf(":")!=-1)
						positionType=positionType.substring(0,positionType.indexOf(":"));

					if (positionType!="bottomRight" && positionType!="bottomLeft") {
						var prompt_err= methods._getPrompt(first_err);
						if (prompt_err) {
							destination=prompt_err.offset().top;
						}
					}

					// get the position of the first error, there should be at least one, no need to check this
					//var destination = form.find(".formError:not('.greenPopup'):first").offset().top;
					if (options.isOverflown) {
						var overflowDIV = $(options.overflownDIV);
						if(!overflowDIV.length) {
							return false;
						}
						var scrollContainerScroll = overflowDIV.scrollTop();
						var scrollContainerPos = -parseInt(overflowDIV.offset().top);

						destination += scrollContainerScroll + scrollContainerPos - 5;
						var scrollContainer = $(options.overflownDIV + ":not(:animated)");

						scrollContainer.animate({ scrollTop: destination }, 1100, function(){
							if(options.focusFirstField) first_err.focus();
						});

					} else {
						$("html, body").animate({
							scrollTop: destination
						}, 1100, function(){
							if(options.focusFirstField) first_err.focus();
						});
						$("html, body").animate({scrollLeft: fixleft},1100)
					}

				} else if(options.focusFirstField) {
					first_err.focus();
				}
				return false;
			}
// add additionalValidation - KDG
			if (typeof additionalValidation == "function") {
				return additionalValidation();
			}
			return true;
		},
		/**
		* This method is called to perform an ajax form validation.
		* During this process all the (field, value) pairs are sent to the server which returns a list of invalid fields or true
		*
		* @param {jqObject} form
		* @param {Map} options
		*/
		_validateFormWithAjax: function(form, options) {

			var data = form.serialize();
									var type = (options.ajaxFormValidationMethod) ? options.ajaxFormValidationMethod : "GET";
			var url = (options.ajaxFormValidationURL) ? options.ajaxFormValidationURL : form.attr("action");
									var dataType = (options.dataType) ? options.dataType : "json";
			$.ajax({
				type: type,
				url: url,
				cache: false,
				dataType: dataType,
				data: data,
				form: form,
				methods: methods,
				options: options,
				beforeSend: function() {
					return options.onBeforeAjaxFormValidation(form, options);
				},
				error: function(data, transport) {
					methods._ajaxError(data, transport);
				},
				success: function(json) {
					if ((dataType == "json") && (json !== true)) {
						// getting to this case doesn't necessary means that the form is invalid
						// the server may return green or closing prompt actions
						// this flag helps figuring it out
						var errorInForm=false;
						for (var i = 0; i < json.length; i++) {
							var value = json[i];

							var errorFieldId = value[0];
							var errorField = $($("#" + errorFieldId)[0]);

							// make sure we found the element
							if (errorField.length == 1) {

								// promptText or selector
								var msg = value[2];
								// if the field is valid
								if (value[1] == true) {

									if (msg == "" || !msg){
										// if for some reason, status==true and error="", just close the prompt
										methods._closePrompt(errorField);
									} else {
										// the field is valid, but we are displaying a green prompt
										if (options.allrules[msg]) {
											var txt = options.allrules[msg].alertTextOk;
											if (txt)
												msg = txt;
										}
										if (options.showPrompts) methods._showPrompt(errorField, msg, "pass", false, options, true);
									}
								} else {
									// the field is invalid, show the red error prompt
									errorInForm|=true;
									if (options.allrules[msg]) {
										var txt = options.allrules[msg].alertText;
										if (txt)
											msg = txt;
									}
									if(options.showPrompts) methods._showPrompt(errorField, msg, "", false, options, true);
								}
							}
						}
						options.onAjaxFormComplete(!errorInForm, form, json, options);
					} else
						options.onAjaxFormComplete(true, form, json, options);

				}
			});

		},
		/**
		* Validates field, shows prompts accordingly
		*
		* @param {jqObject}
		*			field
		* @param {Array[String]}
		*			field's validation rules
		* @param {Map}
		*			user options
		* @return false if field is valid (It is inversed for *fields*, it return false on validate and true on errors.)
		*/
		_validateField: function(field, options) {
			if (!field.attr("id")) {
				field.attr("id", "form-validation-field-" + $.validationEngine.fieldIdCounter);
				++$.validationEngine.fieldIdCounter;
			}

			const fieldValidateHidden = field.hasClass("validate-hidden");
			if ((field.closest(".tabbed-form").length == 0 || options.ignoreTabs) &&
				(field.closest(".accordion-form").length == 0 || options.ignoreTabs) &&
				((field.is(":hidden") && !options.prettySelect) || field.parent().is(":hidden")) &&
				!options.validateHidden && !fieldValidateHidden) {
				return false;
			}
			if (!options.validateHidden && !fieldValidateHidden) {
				if (field.hasClass("hidden")) {
					return false;
				}
				if (field.closest(".form-line").length > 0 && field.closest(".form-line").hasClass("hidden")) {
					return false;
				}
				if (field.closest(".form-section-wrapper").length > 0 && field.closest(".form-section-wrapper").hasClass("hidden")) {
					return false;
				}
			}

// Custom code added - KDG

			// format according to class setting
			// lowercase
			if (field.hasClass("lowercase")) {
				field.val(field.val().toLowerCase());
			}

			// uppercase
			if (field.hasClass("uppercase")) {
				field.val(field.val().toUpperCase());
			}

			if (field.hasClass("capitalize")) {
				var originalValue = field.val();
				var lowercaseValue = field.val().toLowerCase();
				if (originalValue == lowercaseValue) {
					field.val(field.val().replace(/\w\S*/g, function(txt) {
						return txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase();
					}));
					var words = field.val().split(" ");
					var newValue = ""
					for (var x in words) {
						if (newValue != "") {
							newValue += " ";
						}
						if (words[x].toUpperCase() == "APO" || words[x].toUpperCase() == "PO" || words[x].toUpperCase() == "RR" || words[x].toUpperCase() == "SE" || words[x].toUpperCase() == "NE" || words[x].toUpperCase() == "SW" || words[x].toUpperCase() == "NW") {
							words[x] = words[x].toUpperCase();
						}
						if (words[x].toUpperCase() == "AND" || words[x].toUpperCase() == "OR") {
							words[x] = words[x].toLowerCase();
						}
						newValue += words[x];
					}
					field.val(newValue);
				}
			}

			// code-value
			if (field.hasClass("code-value")) {
				var tempValue = $.trim(field.val());
				var newValue = "";
				for(var x=0;x<tempValue.length;x++) {
					var thisChar = tempValue.charAt(x);

					// Only allow letters & numbers
					if (field.hasClass("letters-numbers-only")) {
						if (thisChar.search(/[A-Za-z0-9]/) >= 0) {
							newValue = newValue + thisChar;
						}
						continue;
					}
					if (thisChar.search(/[A-Za-z@0-9_\.]/) >= 0) {
						newValue = newValue + thisChar;
					}
					if (thisChar == " ") {
						newValue = newValue + "_";
					}
					if (thisChar == "-") {
						if (field.hasClass("allow-dash")) {
							newValue = newValue + "-";
						} else {
							newValue = newValue + "_";
						}
					}
				}
				newValue = newValue.replace(/_+/g,"_");
				field.val(newValue);
			}

			// code-value
			if (field.hasClass("url-link")) {
				var tempValue = $.trim(field.val());
				var reservedWords = ["ace","actions","admin","background","blog_templates","cache","ckeditor","classes","css","favicon","fontawesome","fonts","forms","imagess","innovastudio","jpgraph","js","retailstore","shared","templates"];
				if (isInArray(tempValue,reservedWords)) {
					methods._showPrompt(field, "This is a reserved link name","red",false,options);
					field.val("");
					return false;
				}
				var newValue = "";
				for(var x=0;x<tempValue.length;x++) {
					var thisChar = tempValue.charAt(x);
					if (thisChar.search(/[A-Za-z0-9\-\.]/) >= 0) {
						newValue = newValue + thisChar;
					}
					if (!empty(field.data("allow_slash")) && thisChar == "/") {
						newValue = newValue + thisChar;
					}
					if (thisChar == " " || thisChar == "_") {
						newValue = newValue + "-";
					}
				}
				newValue = newValue.replace(/-+/g,"-");
				field.val(newValue);
			}

// End of Custom Code

			var rulesParsing = field.attr(options.validateAttribute);
			var getRules = /validate\[(.*)\]/.exec(rulesParsing);

			if (!getRules) {
				return false;
			}
			var str = getRules[1];
			var rules = str.split(/\[|,|\]/);

			// true if we ran the ajax validation, tells the logic to stop messing with prompts
			var isAjaxValidator = false;
			var fieldName = field.attr("name");
			var promptText = "";
			var promptType = "";
			var required = false;
			var limitErrors = false;
			options.isError = false;

			// If the programmer wants to limit the amount of error messages per field,
			if (options.maxErrorsPerField > 0) {
				limitErrors = true;
			}

			var form = $(field.closest(".validated-wrapper-element"));
			// Fix for adding spaces in the rules

			for (var i = 0; i < rules.length; i++) {
				rules[i] = rules[i].replace(" ", "");
				// Remove any parsing errors
				if (rules[i] === '') {
					delete rules[i];
				}
			}

			for (var i = 0, field_errors = 0; i < rules.length; i++) {
				// If we are limiting errors, and have hit the max, break
				if (limitErrors && field_errors >= options.maxErrorsPerField) {
					// If we haven't hit a required yet, check to see if there is one in the validation rules for this
					// field and that it's index is greater or equal to our current index
					if (!required) {
						var have_required = $.inArray('required', rules);
						required = (have_required != -1 && have_required >= i);
					}
					break;
				}

				var errorMsg = undefined;
				switch (rules[i]) {
					case "required":
						required = true;
						errorMsg = methods._getErrorMessage(form, field, rules[i], rules, i, options, methods._required);
						break;
                    case "custom":
                        errorMsg = methods._getErrorMessage(form, field, rules[i], rules, i, options, methods._custom);
                        break;
					case "groupRequired":
						// Check is its the first of group, if not, reload validation with first field
						// AND continue normal validation on present field
						var classGroup = "["+options.validateAttribute+"*=" +rules[i + 1] +"]";
						var firstOfGroup = form.find(classGroup).eq(0);
						if(firstOfGroup[0] != field[0]){

							methods._validateField(firstOfGroup, options);

						}
						errorMsg = methods._getErrorMessage(form, field, rules[i], rules, i, options, methods._groupRequired);
						if(errorMsg) required = true;
						break;
					case "ajax":
						// AJAX defaults to returning it's loading message
						errorMsg = methods._ajax(field, rules, i, options);
						if (errorMsg) {
							promptType = "load";
						}
						break;
					case "minSize":
						errorMsg = methods._getErrorMessage(form, field, rules[i], rules, i, options, methods._minSize);
						break;
					case "maxSize":
						errorMsg = methods._getErrorMessage(form, field, rules[i], rules, i, options, methods._maxSize);
						break;
					case "min":
						errorMsg = methods._getErrorMessage(form, field, rules[i], rules, i, options, methods._min);
						break;
					case "max":
						errorMsg = methods._getErrorMessage(form, field, rules[i], rules, i, options, methods._max);
						break;
					case "past":
						errorMsg = methods._getErrorMessage(form, field,rules[i], rules, i, options, methods._past);
						break;
					case "future":
						errorMsg = methods._getErrorMessage(form, field,rules[i], rules, i, options, methods._future);
						break;
					case "dateRange":
						var classGroup = "["+options.validateAttribute+"*=" + rules[i + 1] + "]";
						options.firstOfGroup = form.find(classGroup).eq(0);
						options.secondOfGroup = form.find(classGroup).eq(1);

						//if one entry out of the pair has value then proceed to run through validation
						if (options.firstOfGroup[0].value || options.secondOfGroup[0].value) {
							errorMsg = methods._getErrorMessage(form, field,rules[i], rules, i, options, methods._dateRange);
						}
						if (errorMsg) required = true;
						break;

					case "dateTimeRange":
						var classGroup = "["+options.validateAttribute+"*=" + rules[i + 1] + "]";
						options.firstOfGroup = form.find(classGroup).eq(0);
						options.secondOfGroup = form.find(classGroup).eq(1);

						//if one entry out of the pair has value then proceed to run through validation
						if (options.firstOfGroup[0].value || options.secondOfGroup[0].value) {
							errorMsg = methods._getErrorMessage(form, field,rules[i], rules, i, options, methods._dateTimeRange);
						}
						if (errorMsg) required = true;
						break;
					case "maxCheckbox":
						field = $(form.find("input[name='" + fieldName + "']"));
						errorMsg = methods._getErrorMessage(form, field, rules[i], rules, i, options, methods._maxCheckbox);
						break;
					case "minCheckbox":
// Change so that checkboxes are grouped by rel attribute to deal with same name issue - KDG
						var relName = $(form.find("input[name='" + fieldName + "']")).attr("rel");
						field = $(form.find("input[rel='" + relName + "']"));
						errorMsg = methods._getErrorMessage(form, field, rules[i], rules, i, options, methods._minCheckbox);
						break;
					case "equals":
						errorMsg = methods._getErrorMessage(form, field, rules[i], rules, i, options, methods._equals);
						break;
					case "funcCall":
						errorMsg = methods._getErrorMessage(form, field, rules[i], rules, i, options, methods._funcCall);
						break;
					case "creditCard":
						errorMsg = methods._getErrorMessage(form, field, rules[i], rules, i, options, methods._creditCard);
						break;
					case "condRequired":
						errorMsg = methods._getErrorMessage(form, field, rules[i], rules, i, options, methods._condRequired);
						if (errorMsg !== undefined) {
							required = true;
						}
						break;

					default:
						break;
				}

				var end_validation = false;

				// If we were passed back an message object, check what the status was to determine what to do
				if (typeof errorMsg == "object") {
					switch (errorMsg.status) {
						case "_break":
							end_validation = true;
							break;
						// If we have an error message, set errorMsg to the error message
						case "_error":
							errorMsg = errorMsg.message;
							break;
						// If we want to throw an error, but not show a prompt, return early with true
						case "_error_no_prompt":
							return true;
							break;
						// Anything else we continue on
						default:
							break;
					}
				}

				// If it has been specified that validation should end now, break
				if (end_validation) {
					break;
				}

				// If we have a string, that means that we have an error, so add it to the error message.
				if (typeof errorMsg == 'string') {
					promptText += errorMsg + "<br/>";
					options.isError = true;
					field_errors++;
				}
			}
			// If the rules required is not added, an empty field is not validated
			if(!required && field.val() && field.val().length < 1) options.isError = false;

			// Hack for radio/checkbox group button, the validation go into the
			// first radio/checkbox of the group
			var fieldType = field.prop("type");

			if ((fieldType == "radio" || fieldType == "checkbox") && form.find("input[name='" + fieldName + "']").length > 1) {
				field = $(form.find("input[name='" + fieldName + "'][type!=hidden]:first"));
			}

			if(field.is(":hidden") && options.prettySelect) {
				field = form.find("#" + options.usePrefix + methods._jqSelector(field.attr('id')) + options.useSuffix);
			}

			if (options.isError && options.showPrompts){
				if (field.attr("type") != "hidden") {
					methods._showPrompt(field, promptText, promptType, false, options);
				}
			}else{
				if (!isAjaxValidator) methods._closePrompt(field);
			}

			if (!isAjaxValidator) {
				field.trigger("jqv.field.result", [field, options.isError, promptText]);
			}

			/* Record error */
			var errindex = $.inArray(field[0], options.InvalidFields);
			if (errindex == -1) {
				if (options.isError)
				options.InvalidFields.push(field[0]);
			} else if (!options.isError) {
				options.InvalidFields.splice(errindex, 1);
			}

			methods._handleStatusCssClasses(field, options);

			return options.isError;
		},
		/**
		* Handling css classes of fields indicating result of validation
		*
		* @param {jqObject}
		*			field
		* @param {Array[String]}
		*			field's validation rules
		* @private
		*/
		_handleStatusCssClasses: function(field, options) {
			/* remove all classes */
			if(options.addSuccessCssClassToField)
				field.removeClass(options.addSuccessCssClassToField);

			if(options.addFailureCssClassToField)
				field.removeClass(options.addFailureCssClassToField);

			/* Add classes */
			if (options.addSuccessCssClassToField && !options.isError)
				field.addClass(options.addSuccessCssClassToField);

			if (options.addFailureCssClassToField && options.isError)
				field.addClass(options.addFailureCssClassToField);
		},

		/********************
		* _getErrorMessage
		*
		* @param form
		* @param field
		* @param rule
		* @param rules
		* @param i
		* @param options
		* @param originalValidationMethod
		* @return {*}
		* @private
		*/
		_getErrorMessage:function (form, field, rule, rules, i, options, originalValidationMethod) {
			// If we are using the custon validation type, build the index for the rule.
			// Otherwise if we are doing a function call, make the call and return the object
			// that is passed back.
			var beforeChangeRule = rule;
			if (rule == "custom") {
				var custom_validation_type_index = jQuery.inArray(rule, rules)+ 1;
				var custom_validation_type = rules[custom_validation_type_index];
				rule = "custom[" + custom_validation_type + "]";
			}
			var element_classes = (field.attr("data-validation-engine")) ? field.attr("data-validation-engine") : field.attr("class");
			var element_classes_array = element_classes.split(" ");

			// Call the original validation method. If we are dealing with dates or checkboxes, also pass the form
			var errorMsg;
			if (rule == "future" || rule == "past" || rule == "maxCheckbox" || rule == "minCheckbox") {
				errorMsg = originalValidationMethod(form, field, rules, i, options);
			} else {
				errorMsg = originalValidationMethod(field, rules, i, options);
			}

			// If the original validation method returned an error and we have a custom error message,
			// return the custom message instead. Otherwise return the original error message.
			if (errorMsg != undefined) {
				var custom_message = methods._getCustomErrorMessage($(field), element_classes_array, beforeChangeRule, options);
				if (custom_message) errorMsg = custom_message;
			}
			return errorMsg;

		},
		_getCustomErrorMessage:function (field, classes, rule, options) {
			var custom_message = false;
			var validityProp = methods._validityProp[rule];
			// If there is a validityProp for this rule, check to see if the field has an attribute for it
			if (validityProp != undefined) {
				custom_message = field.attr("data-errormessage-"+validityProp);
				// If there was an error message for it, return the message
				if (custom_message != undefined)
					return custom_message;
			}
			custom_message = field.attr("data-errormessage");
			// If there is an inline custom error message, return it
			if (custom_message != undefined)
				return custom_message;
			var id = '#' + field.attr("id");
			// If we have custom messages for the element's id, get the message for the rule from the id.
			// Otherwise, if we have custom messages for the element's classes, use the first class message we find instead.
			if (typeof options.custom_error_messages[id] != "undefined" &&
				typeof options.custom_error_messages[id][rule] != "undefined" ) {
						custom_message = options.custom_error_messages[id][rule]['message'];
			} else if (classes.length > 0) {
				for (var i = 0; i < classes.length && classes.length > 0; i++) {
					var element_class = "." + classes[i];
					if (typeof options.custom_error_messages[element_class] != "undefined" &&
						typeof options.custom_error_messages[element_class][rule] != "undefined") {
							custom_message = options.custom_error_messages[element_class][rule]['message'];
							break;
					}
				}
			}
			if (!custom_message &&
				typeof options.custom_error_messages[rule] != "undefined" &&
				typeof options.custom_error_messages[rule]['message'] != "undefined"){
					custom_message = options.custom_error_messages[rule]['message'];
			}
			return custom_message;
		},
		_validityProp: {
			"required": "value-missing",
			"custom": "custom-error",
			"groupRequired": "value-missing",
			"ajax": "custom-error",
			"minSize": "range-underflow",
			"maxSize": "range-overflow",
			"min": "range-underflow",
			"max": "range-overflow",
			"past": "type-mismatch",
			"future": "type-mismatch",
			"dateRange": "type-mismatch",
			"dateTimeRange": "type-mismatch",
			"maxCheckbox": "range-overflow",
			"minCheckbox": "range-underflow",
			"equals": "pattern-mismatch",
			"funcCall": "custom-error",
			"creditCard": "pattern-mismatch",
			"condRequired": "value-missing"
		},
		/**
		* Required validation
		*
		* @param {jqObject} field
		* @param {Array[String]} rules
		* @param {int} i rules index
		* @param {Map}
		*			user options
		* @param {bool} condRequired flag when method is used for internal purpose in condRequired check
		* @return an error string if validation failed
		*/
		_required: function(field, rules, i, options, condRequired) {

// Custom code added - KDG

			if (field.data("conditional-required") != null) {
				var functionName = field.data("conditional-required");
				if (typeof(functionName) == "function") {
					var fn = functionName;
					if (!fn()) {
						return;
					}
				} else {
					if (!evalInContext(functionName,field)) {
						return;
					}
				}
			}

			var requiredMessage = "";
			if (field.data("required-message") != "" && field.data("required-message") != undefined) {
				requiredMessage = field.data("required-message");
			}

// End Custom Code - below, add requiredMessage

			switch (field.prop("type")) {
				case "text":
				case "password":
				case "textarea":
				case "file":
				case "select-one":
				case "select-multiple":
				default:

					if (! $.trim(field.val()) || field.val() == field.attr("data-validation-placeholder") || field.val() == field.attr("placeholder"))
						return (requiredMessage == "" ? options.allrules[rules[i]].alertText : requiredMessage);
					break;
				case "radio":
				case "checkbox":
					// new validation style to only check dependent field
					if (condRequired) {
						if (!field.attr('checked')) {
							return (requiredMessage == "" ? options.allrules[rules[i]].alertTextCheckboxMultiple : requiredMessage);
						}
						break;
					}
					// old validation style
					var form = field.closest(".validated-wrapper-element");
					var name = field.attr("name");
					if (form.find("input[name='" + name + "']:checked").length == 0) {
						if (form.find("input[name='" + name + "']:visible").length == 1)
							return (requiredMessage == "" ? options.allrules[rules[i]].alertTextCheckbox : requiredMessage);
						else
							return (requiredMessage == "" ? options.allrules[rules[i]].alertTextCheckboxMultiple : requiredMessage);
					}
					break;
			}
		},
		/**
		* Validate that 1 from the group field is required
		*
		* @param {jqObject} field
		* @param {Array[String]} rules
		* @param {int} i rules index
		* @param {Map}
		*			user options
		* @return an error string if validation failed
		*/
		_groupRequired: function(field, rules, i, options) {
			var classGroup = "["+options.validateAttribute+"*=" +rules[i + 1] +"]";
			var isValid = false;
			// Check all fields from the group
			field.closest(".validated-wrapper-element").find(classGroup).each(function(){
				if(!methods._required($(this), rules, i, options)){
					isValid = true;
					return false;
				}
			});

			if(!isValid) {
		return options.allrules[rules[i]].alertText;
		}
		},
		/**
		* Validate rules
		*
		* @param {jqObject} field
		* @param {Array[String]} rules
		* @param {int} i rules index
		* @param {Map}
		*			user options
		* @return an error string if validation failed
		*/
		_custom: function(field, rules, i, options) {
			var customRule = rules[i + 1];
			var rule = options.allrules[customRule];
			var fn;
			if(!rule) {
				console.log("jqv:custom rule not found - "+customRule);
				return;
			}

// Custom formatting code added - KDG

			var functionName = false;
			if (rule["formatFunction"]) {
				functionName = rule.formatFunction;
				fn = methods[functionName];
				if (typeof(fn) == "function") {
					if (!fn(field,options)) {
						return options.allrules[customRule].alertText;
					}
				}
				var repRegex=rule.formatRegex;
				var repValue=rule.formatValue;
				if (repRegex) {
					field.val(field.val().replace(repRegex,repValue));
				}
			}

// End of custom code

			if(rule["regex"]) {
				var ex=rule.regex;
					if(!ex) {
						console.log("jqv:custom regex not found - "+customRule);
						return;
					}
					var pattern = new RegExp(ex);

// Change next line to not check regex if field is empty -- KDG

					field.val(field.val().trim());
					if (field.val() != "" && !pattern.test(field.val()))
						return options.allrules[customRule].alertText;

			} else if(rule["func"]) {
				fn = rule["func"];

				if (typeof(fn) !== "function") {
					console.log("jqv:custom parameter 'function' is no function - "+customRule);
						return;
				}

				if (!fn(field, rules, i, options))
					return options.allrules[customRule].alertText;
			} else if (!functionName) {
				console.log("jqv:custom type not allowed "+customRule);
					return;
			}
		},

// Custom formatting code added - KDG

		/**
		*
		* Format a phone Number
		*
		* @param {jqObject} field
		*
		* @return nothing
		*/
		_formatPhone: function(field,options) {
			var countryFieldName = options.countryFieldId;
			if (field.data("country_field") != "" && field.data("country_field") != undefined) {
				countryFieldName = field.data("country_field");
			}
			const countryId = (empty(field.data("country_id")) ? $("#" + countryFieldName).val() : field.data("country_id"));
			if (countryId != "1000" && countryId != "1001") {
				return true;
			}
			if (field.val() == "" || field.is(".ignore-format")) {
				return true;
			}
			var tempNum = "";
			for (var x=0;x<field.val().length;x++) {
				var thisChar = field.val().charAt(x);
				if (thisChar >= "0" && thisChar <= "9" && (x>0 || thisChar != "1")) {
					tempNum = tempNum + field.val().charAt(x);
				}
			}
			if (tempNum.length >= 10) {
				var tempPhone=tempNum.replace(/(\d{3})(\d{3})(\d{4})/,'('+'$1'+') '+'$2'+'-'+'$3'+' x');
				if (tempPhone.charAt((tempPhone.length - 1)) == "x") {
					tempPhone = tempPhone.substr(0,tempPhone.length - 2);
				}
				field.val(tempPhone);
			} else {
				return false;
			}
			return true;
		},

		/**
		*
		* Format a date
		*
		* @param {jqObject} field
		*
		* @return nothing
		*/
		_formatDate: function(field,options) {
			if (field.is(".monthpicker")) {
				return true;
			}
			var dateFormat = "MM/dd/yyyy";
			if (field.data("date-format") != null) {
				dateFormat = field.data("date-format");
			}
			var todayDate = new Date();
			var dateString = field.val();
			if (dateString.length == 0) {
				return true;
			}
			if (dateString.slice(-1) == "/") {
				dateString = dateString.substr(0,dateString.length - 1);
			}
			if (dateString.length == 3 && !isNaN(dateString)) {
				dateString = "0" + dateString + todayDate.getFullYear();
			}
			if (dateString.length == 3 && isNaN(dateString)) {
				dateString = "0" + dateString.substring(0,1) + "0" + dateString.substring(2,3);
			}
			if (dateString.length == 4 && !isNaN(dateString)) {
				dateString = dateString + todayDate.getFullYear();
			}
			if (dateString.length == 5 && !isNaN(dateString)) {
				dateString = "0" + dateString;
			}
			if (dateString.length == 4 || dateString.length == 5) {
				dateString = dateString + "/" + todayDate.getFullYear();
			}
			if (dateString.length == 6 && !isNaN(dateString)) {
				dateString = dateString.substring(0,2) + "/" + dateString.substring(2,4) + "/" + dateString.substring(4,6);
			}
			if (dateString.length == 8 && !isNaN(dateString)) {
				dateString = dateString.substring(0,2) + "/" + dateString.substring(2,4) + "/" + dateString.substring(4,8);
			}
			if (dateString.length == 10 && dateString.substring(4,5) == "-" && dateString.substring(7,8) == "-") {
				dateString = dateString.substring(5,7) + "/" + dateString.substring(8,10) + "/" + dateString.substring(0,4);
			}
			dateString = dateString.replace(/-/g, "/");
			var pos1=dateString.indexOf("/");
			var pos2=dateString.indexOf("/",pos1+1);
			var strMonth=dateString.substring(0,pos1);
			var strDay=dateString.substring(pos1+1,pos2);
			var strYear=dateString.substring(pos2+1);
			if (strYear.length==2) {
				if (strYear > 50) {
					strYear = "19" + strYear;
				} else {
					strYear = "20" + strYear;
				}
			}
			if (strDay.charAt(0) == "0" && strDay.length > 1) strDay=strDay.substring(1);
			if (strMonth.charAt(0) == "0" && strMonth.length > 1) strMonth=strMonth.substring(1);
			var month=parseInt(strMonth);
			var day=parseInt(strDay);
			var year=parseInt(strYear);
			if (pos1==-1 || pos2==-1){
				return false;
			}
			if (strMonth.length < 1 || month<1 || month>12){
				return false;
			}
			var daysInMonth = 31;
			if (month==4 || month==6 || month==9 || month==11) {daysInMonth = 30};
			if (month==2) {daysInMonth = (((year % 4 == 0) && ( (!(year % 100 == 0)) || (year % 400 == 0))) ? 29 : 28 )};
			if (strDay.length < 1 || day < 1 || day > daysInMonth){
				return false;
			}
			if (strYear.length != 4 || year==0){
				return false;
			}
			var thisDate = new Date(year,month - 1,day);
			var newValue = $.formatDate(thisDate,dateFormat);
			field.val(newValue);
			return true;
		},

		/**
		*
		* validate a password
		*
		* @param {jqObject} field
		*
		* @return boolean
		*/
		_pciPassword: function(field,options) {
			var passwordString = field.val();
			if (passwordString.length == 0) {
				return true;
			}
			if (field.hasClass("no-password-requirements")) {
				return true;
			}
			var foundNumber = false;
			var foundLowercase = false;
			var foundUppercase = false;
			for (var x=0;x<passwordString.length;x++) {
				var thisChar = passwordString.charAt(x);
				if (thisChar >= '0' && thisChar <= '9') {
					foundNumber = true;
				} else if (thisChar >= 'A' && thisChar <= 'Z') {
					foundUppercase = true;
				} else if (thisChar >= 'a' && thisChar <= 'z') {
					foundLowercase = true;
				}
			}
			if (!foundNumber || !foundLowercase || !foundUppercase) {
				return false;
			}
			return true;
		},

		/**
		*
		* Format a time
		*
		* @param {jqObject} field
		*
		* @return nothing
		*/
		_formatTime: function(field,options) {
			var timeString = field.val();
			if (timeString.length == 0) {
				return true;
			}
			var timeParts = new Array();
			timeParts[0] = "";
			timeParts[1] = "";
			timeParts[2] = "";
			var partNumber = 0;
			for (var x=0;x<timeString.length;x++) {
				var thisChar = timeString.charAt(x);
				if (partNumber < 2 && thisChar >= '0' && thisChar <= '9') {
					timeParts[partNumber] += "" + thisChar;
				} else if (partNumber < 2 && (thisChar < '0' || thisChar > '9')) {
					partNumber++;
				}
				if (partNumber > 0) {
					if (thisChar == 'p') {
						timeParts[2] = "pm";
						break;
					} else if (thisChar == 'a') {
						timeParts[2] = "am";
						break;
					}
				}
			}
			if (timeParts[1] == "") {
				timeParts[1] = "0";
			}
			if (timeParts[0] == "" || timeParts[1] == "") {
				return false;
			}
			timeParts[0] = timeParts[0] - 0;
			timeParts[1] = timeParts[1] - 0;
			if (timeParts[0] > 23 || timeParts[1] > 59) {
				return false;
			}
			if (timeParts[0] > 12) {
				timeParts[0] = timeParts[0] - 12;
				timeParts[2] = "pm";
			}
			if (timeParts[2] == "") {
				if (timeParts[0] == 12 || timeParts[0] < 8) {
					timeParts[2] = "pm";
				} else {
					timeParts[2] = "am";
				}
			}
			var newValue = timeParts[0] + ":" + (timeParts[1] < 10 ? "0" : "") + timeParts[1] + " " + timeParts[2];
			field.val(newValue);
			return true;
		},

		/**
		*
		* Format a number
		*
		* @param {jqObject} field
		*
		* @return nothing
		*/
		_formatNumber: function(field,options) {
			var decimalPlaces = 0;
			if (field.data("decimal-places") != null) {
				var decimalPlaces = field.data("decimal-places");
			}
			var onlySignificant = field.data("only-significant") == 1;
			var strString = field.val();
			var strValidChars = "-$0123456789,.";
			var strChar;

			if (strString.length == 0) {
				return true;
			}

			var newString = "";
			var foundDecimal = false;
			var totalDecimals = 0;

			// if there are no decimal points
			// if there is only one comma
			// if the comma is in the last 3 places of the value entere
			// then change the comma to a period

			if (strString.indexOf(".") < 0) {
				const count = (strString.match(/,/g) || []).length;
				if (count == 1 && strString.indexOf(",") >= strString.length - 3) {
					strString = strString.replace(",", ".");
				}
			}

			for (var i = 0; i < strString.length; i++) {
				strChar = strString.charAt(i);
				if (strValidChars.indexOf(strChar) == -1) {
					return false;
				}
				if (strChar == "-" && newString.length == 0) {
					newString += "-";
				}
				if ((strChar >= "0" && strChar <= "9") || (strChar == "." && !foundDecimal)) {
					if (strChar == ".") {
						foundDecimal = true;
						newString += strChar;
					} else if ((foundDecimal && totalDecimals < decimalPlaces) || !foundDecimal) {
						if (foundDecimal) {
							totalDecimals++;
						}
						newString += strChar;
					}
				}
			}
			if (newString == "-") {
				newString = "0";
			}
			newString = newString - 0;
			if (field.data("only-significant") == "1") {
				while (newString.length > 0 && newString.charAt(0) == "0") {
					newString = newString.substring(1);
				}
				while (foundDecimal) {
					if (newString.length > 0 && newString.charAt(newString.length - 1) == "0") {
						newString = newString.substring(0,newString.length - 1);
					} else if (newString.length > 0 && newString.charAt(newString.length - 1) == ".") {
						newString = newString.substring(0,newString.length - 1);
						foundDecimal = false;
					} else {
						break;
					}
				}
			}
			var commasNeeded = field.data("add-commas") == "1";
			if (commasNeeded) {
				newString += '';
				var x = newString.split('.');
				var x1 = x[0];
				var x2 = x.length > 1 ? '.' + x[1] : '';
				var rgx = /(\d+)(\d{3})/;
				while (rgx.test(x1)) {
					x1 = x1.replace(rgx, '$1' + ',' + '$2');
				}
				newString = x1 + x2;
			}
			if (decimalPlaces > 0) {
				newString = "" + newString;
				if ((newString).indexOf(".") == -1) {
					newString += ".";
				}
				var maxDecimals = 0;
				while (newString.substring(newString.length - decimalPlaces - 1,newString.length - decimalPlaces) != "." && maxDecimals < decimalPlaces) {
					newString += "0";
					maxDecimals++;
				}
			}
			field.val(newString);
			return true;
		},

// End Custom Code

		/**
		* Validate custom function outside of the engine scope
		*
		* @param {jqObject} field
		* @param {Array[String]} rules
		* @param {int} i rules index
		* @param {Map}
		*			user options
		* @return an error string if validation failed
		*/
		_funcCall: function(field, rules, i, options) {
			var functionName = rules[i + 1];
			var fn;
			if(functionName.indexOf('.') >-1)
			{
				var namespaces = functionName.split('.');
				var scope = window;
				while(namespaces.length)
				{
					scope = scope[namespaces.shift()];
				}
				fn = scope;
			}
			else
				fn = window[functionName] || options.customFunctions[functionName];
			if (typeof(fn) == 'function')
				return fn(field, rules, i, options);

		},
		/**
		* Field match
		*
		* @param {jqObject} field
		* @param {Array[String]} rules
		* @param {int} i rules index
		* @param {Map}
		*			user options
		* @return an error string if validation failed
		*/
		_equals: function(field, rules, i, options) {
			var equalsField = rules[i + 1];

// Close prompt on the other field - KDG
			if (field.val() != $("#" + equalsField).val()) {
				return options.allrules.equals.alertText;
			} else {
				methods.closePrompt($("#" + equalsField));
			}
		},
		/**
		* Check the maximum size (in characters)
		*
		* @param {jqObject} field
		* @param {Array[String]} rules
		* @param {int} i rules index
		* @param {Map}
		*			user options
		* @return an error string if validation failed
		*/
		_maxSize: function(field, rules, i, options) {
			var max = rules[i + 1];
			var len = field.val().length;

			if (len > max) {
				var rule = options.allrules.maxSize;
				return rule.alertText + max + rule.alertText2;
			}
		},
		/**
		* Check the minimum size (in characters)
		*
		* @param {jqObject} field
		* @param {Array[String]} rules
		* @param {int} i rules index
		* @param {Map}
		*			user options
		* @return an error string if validation failed
		*/
		_minSize: function(field, rules, i, options) {
			var min = rules[i + 1];
			var len = field.val().length;

			if (len > 0 && len < min) {
				var rule = options.allrules.minSize;
				return rule.alertText + min + rule.alertText2;
			}
		},
		/**
		* Check number minimum value
		*
		* @param {jqObject} field
		* @param {Array[String]} rules
		* @param {int} i rules index
		* @param {Map}
		*			user options
		* @return an error string if validation failed
		*/
		_min: function(field, rules, i, options) {
// Extended minimum checking - KDG

			if (field.data("minimum-value") != null && field.data("minimum-value") != undefined) {
				var minimumValue = field.data("minimum-value");
			} else {
				var minimumValue = rules[i + 1];
			}
			if (in_array("number",rules) || in_array("integer",rules) || field.hasClass("is-number")) {
				var min = parseFloat(minimumValue);
				var currentValue = parseFloat(field.val());
			} else if (in_array("date",rules)) {
				var min = $.formatDate(new Date(minimumValue), "yyyy-MM-dd");
				var currentValue = (empty(field.val()) ? "" : $.formatDate(new Date(field.val()), "yyyy-MM-dd"));
			} else {
				var min = minimumValue;
				var currentValue = field.val();
			}
			if (field.data("minimum-value-variable") != null) {
				var varName = field.data("minimum-value-variable");
				try {
					var min = eval(varName);
				} catch (e) {
					$.error("Minimum value variable doesn't exist");
				}
			}

// End Custom Code

			if ((currentValue + "").length > 0 && currentValue < min) {
				var rule = options.allrules.min;
				if (rule.alertText2) return rule.alertText + minimumValue + rule.alertText2;
				return rule.alertText + minimumValue;
			}
		},
		/**
		* Check number maximum value
		*
		* @param {jqObject} field
		* @param {Array[String]} rules
		* @param {int} i rules index
		* @param {Map}
		*			user options
		* @return an error string if validation failed
		*/
		_max: function(field, rules, i, options) {

// Extended Maximum checking - KDG

			if (field.data("maximum-value") != null) {
				var maximumValue = field.data("maximum-value");
			} else {
				var maximumValue = rules[i + 1];
			}
			if (in_array("number",rules) || in_array("integer",rules) || field.hasClass("is-number")) {
				var max = parseFloat(maximumValue);
				var len = parseFloat(field.val());
			} else if (in_array("date",rules)) {
				var max = $.formatDate(new Date(maximumValue), "yyyy-MM-dd");
				var len = (empty(field.val()) ? "" : $.formatDate(new Date(field.val()), "yyyy-MM-dd"));
			} else {
				var max = maximumValue;
				var len = field.val();
			}
			if (field.data("maximum-value-variable") != null) {
				var varName = field.data("maximum-value-variable");
				try {
					var max = eval(varName);
				} catch (e) {
					$.error("Maximum value variable doesn't exist");
				}
			}

// End Custom Code

//			var max = parseFloat(rules[i + 1]);
//			var len = parseFloat(field.val());

			if (len > max) {
				var rule = options.allrules.max;
				if (rule.alertText2) return rule.alertText + max + rule.alertText2;
				//orefalo: to review, also do the translations
				return rule.alertText + max;
			}
		},
		/**
		* Checks date is in the past
		*
		* @param {jqObject} field
		* @param {Array[String]} rules
		* @param {int} i rules index
		* @param {Map}
		*			user options
		* @return an error string if validation failed
		*/
		_past: function(form, field, rules, i, options) {

			var pdate = methods._parseDate(field.val());
			var vdate = new Date((new Date()).getFullYear(),(new Date()).getMonth(),(new Date()).getDate());
			var daysAhead = field.data("days_ahead");
			if (daysAhead == undefined || daysAhead == "") {
				daysAhead = 0;
			}
			if (daysAhead > 0) {
				vdate.setDate(vdate.getDate() + daysAhead);
			}
			if (pdate > vdate) {
				var rule = options.allrules.past;
				return rule.alertText;
			}
		},
		/**
		* Checks date is in the future
		*
		* @param {jqObject} field
		* @param {Array[String]} rules
		* @param {int} i rules index
		* @param {Map}
		*			user options
		* @return an error string if validation failed
		*/
		_future: function(form, field, rules, i, options) {
			var pdate = methods._parseDate(field.val());
			var vdate = new Date((new Date()).getFullYear(),(new Date()).getMonth(),(new Date()).getDate());
			if (pdate < vdate) {
				var rule = options.allrules.future;
				return rule.alertText;
			}
		},
		/**
		* Checks if valid date
		*
		* @param {string} date string
		* @return a bool based on determination of valid date
		*/
		_isDate: function (value) {
			var dateRegEx = new RegExp(/^\d{4}[\/\-](0?[1-9]|1[012])[\/\-](0?[1-9]|[12][0-9]|3[01])$|^(?:(?:(?:0?[13578]|1[02])(\/|-)31)|(?:(?:0?[1,3-9]|1[0-2])(\/|-)(?:29|30)))(\/|-)(?:[1-9]\d\d\d|\d[1-9]\d\d|\d\d[1-9]\d|\d\d\d[1-9])$|^(?:(?:0?[1-9]|1[0-2])(\/|-)(?:0?[1-9]|1\d|2[0-8]))(\/|-)(?:[1-9]\d\d\d|\d[1-9]\d\d|\d\d[1-9]\d|\d\d\d[1-9])$|^(0?2(\/|-)29)(\/|-)(?:(?:0[48]00|[13579][26]00|[2468][048]00)|(?:\d\d)?(?:0[48]|[2468][048]|[13579][26]))$/);
			return dateRegEx.test(value);
		},
		/**
		* Checks if valid date time
		*
		* @param {string} date string
		* @return a bool based on determination of valid date time
		*/
		_isDateTime: function (value){
			var dateTimeRegEx = new RegExp(/^\d{4}[\/\-](0?[1-9]|1[012])[\/\-](0?[1-9]|[12][0-9]|3[01])\s+(1[012]|0?[1-9]){1}:(0?[1-5]|[0-6][0-9]){1}:(0?[0-6]|[0-6][0-9]){1}\s+(am|pm|AM|PM){1}$|^(?:(?:(?:0?[13578]|1[02])(\/|-)31)|(?:(?:0?[1,3-9]|1[0-2])(\/|-)(?:29|30)))(\/|-)(?:[1-9]\d\d\d|\d[1-9]\d\d|\d\d[1-9]\d|\d\d\d[1-9])$|^((1[012]|0?[1-9]){1}\/(0?[1-9]|[12][0-9]|3[01]){1}\/\d{2,4}\s+(1[012]|0?[1-9]){1}:(0?[1-5]|[0-6][0-9]){1}:(0?[0-6]|[0-6][0-9]){1}\s+(am|pm|AM|PM){1})$/);
			return dateTimeRegEx.test(value);
		},
		//Checks if the start date is before the end date
		//returns true if end is later than start
		_dateCompare: function (start, end) {
			return (new Date(start.toString()) < new Date(end.toString()));
		},
		/**
		* Checks date range
		*
		* @param {jqObject} first field name
		* @param {jqObject} second field name
		* @return an error string if validation failed
		*/
		_dateRange: function (field, rules, i, options) {
			//are not both populated
			if ((!options.firstOfGroup[0].value && options.secondOfGroup[0].value) || (options.firstOfGroup[0].value && !options.secondOfGroup[0].value)) {
				return options.allrules[rules[i]].alertText + options.allrules[rules[i]].alertText2;
			}

			//are not both dates
			if (!methods._isDate(options.firstOfGroup[0].value) || !methods._isDate(options.secondOfGroup[0].value)) {
				return options.allrules[rules[i]].alertText + options.allrules[rules[i]].alertText2;
			}

			//are both dates but range is off
			if (!methods._dateCompare(options.firstOfGroup[0].value, options.secondOfGroup[0].value)) {
				return options.allrules[rules[i]].alertText + options.allrules[rules[i]].alertText2;
			}
		},
		/**
		* Checks date time range
		*
		* @param {jqObject} first field name
		* @param {jqObject} second field name
		* @return an error string if validation failed
		*/
		_dateTimeRange: function (field, rules, i, options) {
			//are not both populated
			if ((!options.firstOfGroup[0].value && options.secondOfGroup[0].value) || (options.firstOfGroup[0].value && !options.secondOfGroup[0].value)) {
				return options.allrules[rules[i]].alertText + options.allrules[rules[i]].alertText2;
			}
			//are not both dates
			if (!methods._isDateTime(options.firstOfGroup[0].value) || !methods._isDateTime(options.secondOfGroup[0].value)) {
				return options.allrules[rules[i]].alertText + options.allrules[rules[i]].alertText2;
			}
			//are both dates but range is off
			if (!methods._dateCompare(options.firstOfGroup[0].value, options.secondOfGroup[0].value)) {
				return options.allrules[rules[i]].alertText + options.allrules[rules[i]].alertText2;
			}
		},
		/**
		* Max number of checkbox selected
		*
		* @param {jqObject} field
		* @param {Array[String]} rules
		* @param {int} i rules index
		* @param {Map}
		*			user options
		* @return an error string if validation failed
		*/
		_maxCheckbox: function(form, field, rules, i, options) {

			var nbCheck = rules[i + 1];
			var groupname = field.attr("name");
			var groupSize = form.find("input[name='" + groupname + "']:checked").length;
			if (groupSize > nbCheck) {
				if (options.allrules.maxCheckbox.alertText2)
					return options.allrules.maxCheckbox.alertText + " " + nbCheck + " " + options.allrules.maxCheckbox.alertText2;
				return options.allrules.maxCheckbox.alertText;
			}
		},
		/**
		* Min number of checkbox selected
		*
		* @param {jqObject} field
		* @param {Array[String]} rules
		* @param {int} i rules index
		* @param {Map}
		*			user options
		* @return an error string if validation failed
		*/
		_minCheckbox: function(form, field, rules, i, options) {

			var nbCheck = rules[i + 1];
// Change so checkboxes are grouped by rel attribute - KDG
			var groupname = field.attr("rel");
			var groupSize = form.find("input[rel='" + groupname + "']:checked").length;
			if (groupSize < nbCheck) {
				return options.allrules.minCheckbox.alertText + " " + nbCheck + " " + options.allrules.minCheckbox.alertText2;
			}
		},
		/**
		* Checks that it is a valid credit card number according to the
		* Luhn checksum algorithm.
		*
		* @param {jqObject} field
		* @param {Array[String]} rules
		* @param {int} i rules index
		* @param {Map}
		*			user options
		* @return an error string if validation failed
		*/
		_creditCard: function(field, rules, i, options) {
			//spaces and dashes may be valid characters, but must be stripped to calculate the checksum.
			var valid = false, cardNumber = field.val().replace(/ +/g, '').replace(/-+/g, '');

			var numDigits = cardNumber.length;
			if (numDigits >= 14 && numDigits <= 16 && parseInt(cardNumber) > 0) {

				var sum = 0, i = numDigits - 1, pos = 1, digit, luhn = new String();
				do {
					digit = parseInt(cardNumber.charAt(i));
					luhn += (pos++ % 2 == 0) ? digit * 2 : digit;
				} while (--i >= 0)

				for (i = 0; i < luhn.length; i++) {
					sum += parseInt(luhn.charAt(i));
				}
				valid = sum % 10 == 0;
			}
			if (!valid) return options.allrules.creditCard.alertText;
		},
		/**
		* Ajax field validation
		*
		* @param {jqObject} field
		* @param {Array[String]} rules
		* @param {int} i rules index
		* @param {Map}
		*			user options
		* @return nothing! the ajax validator handles the prompts itself
		*/
		_ajax: function(field, rules, i, options) {

			var errorSelector = rules[i + 1];
			var rule = options.allrules[errorSelector];
			var extraData = rule.extraData;
			var extraDataDynamic = rule.extraDataDynamic;
			var data = {
				"fieldId" : field.attr("id"),
				"fieldValue" : field.val()
			};

			if (typeof extraData === "object") {
				$.extend(data, extraData);
			} else if (typeof extraData === "string") {
				var tempData = extraData.split("&");
				for(var i = 0; i < tempData.length; i++) {
					var values = tempData[i].split("=");
					if (values[0] && values[0]) {
						data[values[0]] = values[1];
					}
				}
			}

			if (extraDataDynamic) {
				var tmpData = [];
				var domIds = String(extraDataDynamic).split(",");
				for (var i = 0; i < domIds.length; i++) {
					var id = domIds[i];
					if ($(id).length) {
						var inputValue = field.closest(".validated-wrapper-element").find(id).val();
						var keyValue = id.replace('#', '') + '=' + escape(inputValue);
						data[id.replace('#', '')] = inputValue;
					}
				}
			}

			// If a field change event triggered this we want to clear the cache for this ID
			if (options.eventTrigger == "field") {
				delete(options.ajaxValidCache[field.attr("id")]);
			}

			// If there is an error or if the the field is already validated, do not re-execute AJAX
			if (!options.isError && !methods._checkAjaxFieldStatus(field.attr("id"), options)) {
				$.ajax({
					type: options.ajaxFormValidationMethod,
					url: rule.url,
					cache: false,
					dataType: "json",
					data: data,
					field: field,
					rule: rule,
					methods: methods,
					options: options,
					beforeSend: function() {},
					error: function(data, transport) {
						methods._ajaxError(data, transport);
					},
					success: function(json) {

						// asynchronously called on success, data is the json answer from the server
						var errorFieldId = json[0];
						//var errorField = $($("#" + errorFieldId)[0]);
						var errorField = $("#"+ errorFieldId).eq(0);

						// make sure we found the element
						if (errorField.length == 1) {
							var status = json[1];
							// read the optional msg from the server
							var msg = json[2];
							if (!status) {
								// Houston we got a problem - display an red prompt
								options.ajaxValidCache[errorFieldId] = false;
								options.isError = true;

								// resolve the msg prompt
								if(msg) {
									if (options.allrules[msg]) {
										var txt = options.allrules[msg].alertText;
										if (txt) {
											msg = txt;
							}
									}
								}
								else
									msg = rule.alertText;

								if (options.showPrompts) methods._showPrompt(errorField, msg, "", true, options);
							} else {
								options.ajaxValidCache[errorFieldId] = true;

								// resolves the msg prompt
								if(msg) {
									if (options.allrules[msg]) {
										var txt = options.allrules[msg].alertTextOk;
										if (txt) {
											msg = txt;
							}
									}
								}
								else
								msg = rule.alertTextOk;

								if (options.showPrompts) {
									// see if we should display a green prompt
									if (msg)
										methods._showPrompt(errorField, msg, "pass", true, options);
									else
										methods._closePrompt(errorField);
								}

								// If a submit form triggered this, we want to re-submit the form
								if (options.eventTrigger == "submit")
									field.closest("form").submit();
							}
						}
						errorField.trigger("jqv.field.result", [errorField, options.isError, msg]);
					}
				});

				return rule.alertTextLoad;
			}
		},
		/**
		* Common method to handle ajax errors
		*
		* @param {Object} data
		* @param {Object} transport
		*/
		_ajaxError: function(data, transport) {
			if(data.status == 0 && transport == null)
				console.log("The page is not served from a server! ajax call failed");
			else if(typeof console != "undefined")
				console.log("Ajax error: " + data.status + " " + transport);
		},
		/**
		* date -> string
		*
		* @param {Object} date
		*/
		_dateToString: function(date) {
			return date.getFullYear()+"-"+(date.getMonth()+1)+"-"+date.getDate();
		},
		/**
		* Parses an ISO date
		* @param {String} d
		*/
		_parseDate: function(d) {

			var dateParts = d.split("/");
			return new Date(dateParts[2], (dateParts[0] - 1) ,dateParts[1]);
		},
		/**
		* Builds or updates a prompt with the given information
		*
		* @param {jqObject} field
		* @param {String} promptText html text to display type
		* @param {String} type the type of bubble: 'pass' (green), 'load' (black) anything else (red)
		* @param {boolean} ajaxed - use to mark fields than being validated with ajax
		* @param {Map} options user options
		*/
		_showPrompt: function(field, promptText, type, ajaxed, options, ajaxform) {
			var prompt = methods._getPrompt(field);
			var customPrompt = $(field).data("alert_text");
			if (customPrompt != "" && customPrompt != undefined) {
				promptText = customPrompt;
			}
			// The ajax submit errors are not see has an error in the form,
			// When the form errors are returned, the engine see 2 bubbles, but those are ebing closed by the engine at the same time
			// Because no error was found befor submitting
			if(ajaxform) prompt = false;
			// Check that there is indded text
			if($.trim(promptText)){
				if (prompt)
					methods._updatePrompt(field, prompt, promptText, type, ajaxed, options);
				else
					methods._buildPrompt(field, promptText, type, ajaxed, options);
			}
		},
		/**
		* Builds and shades a prompt for the given field.
		*
		* @param {jqObject} field
		* @param {String} promptText html text to display type
		* @param {String} type the type of bubble: 'pass' (green), 'load' (black) anything else (red)
		* @param {boolean} ajaxed - use to mark fields than being validated with ajax
		* @param {Map} options user options
		*/
		_buildPrompt: function(field, promptText, type, ajaxed, options) {

			// create the prompt
			var prompt = $('<div>');
			prompt.addClass(methods._getClassName(field.attr("id")) + "formError");
			// add a class name to identify the parent form of the prompt
			prompt.addClass("parentForm"+methods._getClassName(field.parents('.validated-wrapper-element').attr("id")));
			prompt.addClass("formError");

			switch (type) {
				case "pass":
					prompt.addClass("greenPopup");
					break;
				case "load":
					prompt.addClass("blackPopup");
					break;
				default:
					/* it has error */
// Add form field error class - KDG
					if (field.parent(".basic-form-line").length == 0) {
						field.addClass("formFieldError");
					} else {
						field.parent(".basic-form-line").addClass("field-error");
					}
			}
			if (field.parent(".basic-form-line").length > 0) {
				field.parent(".basic-form-line").find(".field-error-text").html(promptText);
				return;
			}
			if (ajaxed)
				prompt.addClass("ajaxed");

			// create the prompt content
			var promptContent = $('<div>').addClass("formErrorContent").html(promptText).appendTo(prompt);
			// Add custom prompt class
			if (options.addPromptClass)
				prompt.addClass(options.addPromptClass);

			prompt.css({
				"opacity": 0,
				'position':'absolute'
			});
			field.before(prompt);

			var pos = methods._calculatePosition(field, prompt, options);
			prompt.css({
				"top": pos.callerTopPosition,
				"left": pos.callerleftPosition,
				"marginTop": pos.marginTopSize,
				"opacity": 0
			}).data("callerField", field);

			if (options.autoHidePrompt) {
// Save autohidetimer so that it can be cancelled if needed - KDG
				options.autoHideTimer = setTimeout(function(){
					prompt.animate({
						"opacity": 0
					},function(){
						prompt.closest('.formErrorOuter').remove();
						prompt.remove();
					});
				}, options.autoHideDelay);
			}
			return prompt.animate({
				"opacity": .9
			});
		},
		/**
		* Updates the prompt text field - the field for which the prompt
		* @param {jqObject} field
		* @param {String} promptText html text to display type
		* @param {String} type the type of bubble: 'pass' (green), 'load' (black) anything else (red)
		* @param {boolean} ajaxed - use to mark fields than being validated with ajax
		* @param {Map} options user options
		*/
		_updatePrompt: function(field, prompt, promptText, type, ajaxed, options, noAnimation) {

			if (prompt) {
				if (typeof type !== "undefined") {
					if (type == "pass")
						prompt.addClass("greenPopup");
					else
						prompt.removeClass("greenPopup");

					if (type == "load")
						prompt.addClass("blackPopup");
					else
						prompt.removeClass("blackPopup");

// default to form field error - KDG
					if (type == "")
						if (field.parent(".basic-form-line").length > 0) {
							field.parent(".basic-form-line").removeClass("field-error");
						} else {
							field.addClass("formFieldError");
						}
					else
						field.removeClass("formFieldError");
					if (field.parent(".basic-form-line").length > 0) {
						field.parent(".basic-form-line").removeClass("field-error");
					}
				}
				if (ajaxed)
					prompt.addClass("ajaxed");
				else
					prompt.removeClass("ajaxed");

				prompt.find(".formErrorContent").html(promptText);

				var pos = methods._calculatePosition(field, prompt, options);
				var css = {"top": pos.callerTopPosition,
				"left": pos.callerleftPosition,
				"marginTop": pos.marginTopSize};

				if (noAnimation)
					prompt.css(css);
				else
					prompt.animate(css);
			}
		},
		/**
		* Closes the prompt associated with the given field
		*
		* @param {jqObject}
		*			field
		*/
		_closePrompt: function(field) {
// Remove formFieldError - KDG
			field.removeClass("formFieldError");
			if (field.parent(".basic-form-line").length > 0) {
				field.parent(".basic-form-line").removeClass("field-error");
			}
			var prompt = methods._getPrompt(field);
			if (prompt)
				prompt.fadeTo("fast", 0, function() {
					prompt.parent('.formErrorOuter').remove();
					prompt.remove();
				});
		},
		closePrompt: function(field) {
			return methods._closePrompt(field);
		},
		/**
		* Returns the error prompt matching the field if any
		*
		* @param {jqObject}
		*			field
		* @return undefined or the error prompt (jqObject)
		*/
		_getPrompt: function(field) {
				var formId = $(field).closest('.validated-wrapper-element').attr('id');
			var className = methods._getClassName(field.attr("id")) + "formError";
				var match = $("." + methods._escapeExpression(className) + '.parentForm' + formId)[0];
			if (match)
			return $(match);
		},
		/**
		* Returns the escapade classname
		*
		* @param {selector}
		*			className
		*/
		_escapeExpression: function (selector) {
			return selector.replace(/([#;&,\.\+\*\~':"\!\^$\[\]\(\)=>\|])/g, "\\$1");
		},
		/**
		* Calculates prompt position
		*
		* @param {jqObject}
		*			field
		* @param {jqObject}
		*			the prompt
		* @param {Map}
		*			options
		* @return positions
		*/
		_calculatePosition: function (field, promptElmt, options) {

			var promptTopPosition, promptleftPosition, marginTopSize;
			var fieldWidth = field.width();
			var fieldLeft = field.position().left;
			var fieldTop = field.position().top;
			var fieldHeight = field.height();
			var promptHeight = promptElmt.height();

			// is the form contained in an overflown container?
			promptTopPosition = promptleftPosition = 0;
			marginTopSize = -promptHeight;


			//prompt positioning adjustment support
			//now you can adjust prompt position
			//usage: positionType:Xshift,Yshift
			//for example:
			// bottomLeft:+20 means bottomLeft position shifted by 20 pixels right horizontally
			// topRight:20, -15 means topRight position shifted by 20 pixels to right and 15 pixels to top
			//You can use +pixels, - pixels. If no sign is provided than + is default.
			var positionType=field.data("promptPosition") || options.promptPosition;
			var shift1="";
			var shift2="";
			var shiftX=0;
			var shiftY=0;
			if (typeof(positionType)=='string') {
				//do we have any position adjustments ?
				if (positionType.indexOf(":")!=-1) {
					shift1=positionType.substring(positionType.indexOf(":")+1);
					positionType=positionType.substring(0,positionType.indexOf(":"));

					//if any advanced positioning will be needed (percents or something else) - parser should be added here
					//for now we use simple parseInt()

					//do we have second parameter?
					if (shift1.indexOf(",") !=-1) {
						shift2=shift1.substring(shift1.indexOf(",") +1);
						shift1=shift1.substring(0,shift1.indexOf(","));
						shiftY=parseInt(shift2);
						if (isNaN(shiftY)) shiftY=0;
					};

					shiftX=parseInt(shift1);
					if (isNaN(shift1)) shift1=0;

				};
			};


			switch (positionType) {
				default:
				case "topRight":
					if (field.is("input[type=checkbox]")) {
						promptleftPosition += fieldLeft + fieldWidth;
						promptTopPosition += fieldTop;
					} else {
						promptleftPosition += fieldLeft + fieldWidth - 20;
						promptTopPosition += fieldTop + 8;
					}
					break;

				case "topCenter":
					promptleftPosition += fieldLeft + (fieldWidth / 2) - 20;
					promptTopPosition += fieldTop + 5;
					break;

				case "topLeft":
					promptTopPosition += fieldTop + 5;
					promptleftPosition += fieldLeft + 10;
					break;

				case "centerRight":
					promptTopPosition = fieldTop+4;
					marginTopSize = 0;
					promptleftPosition= fieldLeft + field.outerWidth(true)+5;
					break;

				case "centerLeft":
					promptleftPosition = fieldLeft - (promptElmt.width() + 2);
					promptTopPosition = fieldTop+4;
					marginTopSize = 0;
					break;

				case "bottomLeft":
					promptTopPosition = fieldTop + field.height() + 5;
					marginTopSize = 0;
					promptleftPosition = fieldLeft;
					break;

				case "bottomRight":
					promptleftPosition = fieldLeft + fieldWidth - 30;
					promptTopPosition = fieldTop + field.height() + 5;
					marginTopSize = 0;
			};



			//apply adjusments if any
			promptleftPosition += shiftX;
			promptTopPosition += shiftY;

			return {
				"callerTopPosition": promptTopPosition + "px",
				"callerleftPosition": promptleftPosition + "px",
				"marginTopSize": marginTopSize + "px"
			};
		},
		/**
		* Saves the user options and variables in the form.data
		*
		* @param {jqObject}
		*			form - the form where the user option should be saved
		* @param {Map}
		*			options - the user options
		* @return the user options (extended from the defaults)
		*/
		_saveOptions: function(form, options) {

			// is there a language localisation ?
			if ($.validationEngineLanguage)
			var allRules = $.validationEngineLanguage.allRules;
			else
			$.error("jQuery.validationEngine rules are not loaded, plz add localization files to the page");
			// --- Internals DO NOT TOUCH or OVERLOAD ---
			// validation rules and i18
			$.validationEngine.defaults.allrules = allRules;

			var userOptions = $.extend(true,{},$.validationEngine.defaults,options);

			form.data('jqv', userOptions);
			return userOptions;
		},

		/**
		* Removes forbidden characters from class name
		* @param {String} className
		*/
		_getClassName: function(className) {
			if(className)
				return className.replace(/:/g, "_").replace(/\./g, "_");
					},
		/**
		* Escape special character for jQuery selector
		* http://totaldev.com/content/escaping-characters-get-valid-jquery-id
		* @param {String} selector
		*/
		_jqSelector: function(str){
			return str.replace(/([;&,\.\+\*\~':"\!\^#$%@\[\]\(\)=>\|])/g, '\\$1');
		},
		/**
		* Conditionally required field
		*
		* @param {jqObject} field
		* @param {Array[String]} rules
		* @param {int} i rules index
		* @param {Map}
		* user options
		* @return an error string if validation failed
		*/
		_condRequired: function(field, rules, i, options) {
			var idx, dependingField;

			for(idx = (i + 1); idx < rules.length; idx++) {
				dependingField = jQuery("#" + rules[idx]).first();

				/* Use _required for determining wether dependingField has a value.
				* There is logic there for handling all field types, and default value; so we won't replicate that here
				* Indicate this special use by setting the last parameter to true so we only validate the dependingField on chackboxes and radio buttons (#462)
				*/
				if (dependingField.length && methods._required(dependingField, ["required"], 0, options, true) == undefined) {
					/* We now know any of the depending fields has a value,
					* so we can validate this field as per normal required code
					*/
					return methods._required(field, ["required"], 0, options);
				}
			}
		}
		};

	/**
	* Plugin entry point.
	* You may pass an action as a parameter or a list of options.
	* if none, the init and attach methods are being called.
	* Remember: if you pass options, the attached method is NOT called automatically
	*
	* @param {String}
	*			method (optional) action
	*/
	$.fn.validationEngine = function(method) {

		var form = $(this);
		if(!form[0]) return form; // stop here if the form does not exist

		if (typeof(method) == 'string' && method.charAt(0) != '_' && methods[method]) {

			// make sure init is called once
			if(method != "showPrompt" && method != "hide" && method != "hideAll")
			methods.init.apply(form);

			return methods[method].apply(form, Array.prototype.slice.call(arguments, 1));
		} else if (typeof method == 'object' || !method) {

			// default constructor with or without arguments
			methods.init.apply(form, arguments);
			return methods.attach.apply(form);
		} else {
			$.error('Method ' + method + ' does not exist in jQuery.validationEngine');
		}
	};



	// LEAK GLOBAL OPTIONS
	$.validationEngine= {fieldIdCounter: 0,defaults:{

		// id of the country id field - KDG
		countryFieldId: "country_id",
		// Name of the event triggering field validation
		validationEventTrigger: "blur",
		// Automatically scroll viewport to the first error. Change Default - KDG
		scroll: false,
		// Focus on the first input
		focusFirstField:true,
		// Show prompts, set to false to disable prompts
		showPrompts: true,
		// Opening box position, possible locations are: topLeft,
		// topRight, bottomLeft, centerRight, bottomRight
		promptPosition: "topRight",
		// Change default bind to live - KDG
		inlineAjax: false,
		// if set to true, the form data is sent asynchronously via ajax to the form.action url (get)
		ajaxFormValidation: false,
		// The url to send the submit ajax validation (default to action)
		ajaxFormValidationURL: false,
		// HTTP method used for ajax validation
		ajaxFormValidationMethod: 'get',
		// Ajax form validation callback method: boolean onComplete(form, status, errors, options)
		// retuns false if the form.submit event needs to be canceled.
		onAjaxFormComplete: $.noop,
		// called right before the ajax call, may return false to cancel
		onBeforeAjaxFormValidation: $.noop,
		// Stops form from submitting and execute function assiciated with it
		onValidationComplete: false,

		// Used when you have a form fields too close and the errors messages are on top of other disturbing viewing messages
		doNotShowAllErrorsOnSubmit: false,
		// Object where you store custom messages to override the default error messages
		ignoreTabs: false,
		custom_error_messages:{},
		// true if you want to vind the input fields
		binded: true,
		// did one of the validation fail ? kept global to stop further ajax validations
		isError: false,
		// Limit how many displayed errors a field can have
		maxErrorsPerField: false,

		// Caches field validation status, typically only bad status are created.
		// the array is used during ajax form validation to detect issues early and prevent an expensive submit
		ajaxValidCache: {},
		// Auto update prompt position after window resize
		autoPositionUpdate: false,

		InvalidFields: [],
		onFieldSuccess: false,
		onFieldFailure: false,
		onSuccess: false,
		onFailure: false,
		validateAttribute: "class",
		addSuccessCssClassToField: false,
		addFailureCssClassToField: false,

		// Auto-hide prompt - Change default - KDG
		autoHidePrompt: true,
		// Time for autohide - KDG
		autoHideTimer: false,
		// Delay before auto-hide
		autoHideDelay: 10000,
		// Fade out duration while hiding the validations
		fadeDuration: 0.3,
	// Use Prettify select library
	prettySelect: false,
	// Add css class on prompt
	addPromptClass : "",
	// Custom ID uses prefix
	usePrefix: "",
	// Custom ID uses suffix
	useSuffix: "",
	// Validate Hidden Fields
	validateHidden: false,
	// Only show one message per error prompt
	showOneMessage: false
	}};
	$(document).on("keydown",".formFieldError",function() {
		$(this).removeClass("formFieldError");
		$(this).validationEngine("hide");
	})
})(jQuery);

// Add function used by some custom code - KDG
function in_array (needle, haystack, argStrict) {
	var key = '',
		strict = !! argStrict;

	if (strict) {
		for (key in haystack) {
			if (haystack[key] === needle) {
				return true;
			}
		}
	} else {
		for (key in haystack) {
			if (haystack[key] == needle) {
				return true;
			}
		}
	}
	return false;
}

function evalInContext(js, context) {
	return function() { return eval(js); }.call(context);
}
