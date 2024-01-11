<div id="_maintenance_form">
	<div id="_signup_process_wrapper">
		<section id="_signup_section_form" class='tabbed-content ignore-tab-clicks'>

			<ul id="progressbar" class='tabbed-content-nav'>
				<li class="tabbed-content-tab" id="_store_tab" data-section_id="store_information">Store Information</li>
				<li class="tabbed-content-tab" id="_user_tab" data-section_id="user_information">Primary User</li>
				<li class="tabbed-content-tab" id="_website_tab" data-section_id="website_information">Website</li>
				<li class="tabbed-content-tab" id="_payment_tab" data-section_id="payment_information">Finalize</li>
			</ul>

			<div class='tabbed-content-body' id="checkout_form_wrapper">
				<div class="tabbed-content-page" id="store_information">
					<h2>Store Information</h2>
					<div class="row">
						<div class="col">
							%field:business_name%
							%basic_form_line%
							%end repeat%
						</div>
					</div>

					<div class="row">
						<div class="col">
							%field:first_name%
							%basic_form_line%
							%end repeat%
						</div>
						<div class="col">
							%field:last_name%
							%basic_form_line%
							%end repeat%
						</div>
					</div>

					<div class="row">
						<div class="col">
							%field:address_1%
							%basic_form_line%
							%end repeat%
						</div>
						<div class="col">
							%field:address_2%
							%basic_form_line%
							%end repeat%
						</div>
					</div>

					<div class="row">
						<div class="col">
							%field:city,city_select,postal_code,country_id%
							%basic_form_line%
							%end repeat%
						</div>
						<div class="col">
							%field:state%
							%basic_form_line%
							%end repeat%
						</div>
					</div>

					<div class="row">
						<div class="col">
							%field:store_phone_number,web_page,client_timezone%
							%basic_form_line%
							%end repeat%
						</div>
						<div class="col">
							%field:email_address,logo_image_id,favicon_image_id,allow_pickup%
							%basic_form_line%
							%end repeat%
							<p id="_email_address_message"></p>
						</div>
					</div>
				</div>

				<div class="tabbed-content-page" id="user_information">
					<h2>Primary User</h2>
					<p>This is the primary user in your NEW system and will be used to login to your new website. We've prefilled your user name, thinking you probably want to use the same one on your site, but you can change it if you want. You can create additional users in your admin area once your site is created. This information is for the primary admin user.</p>

					%field:new_user_user_name%
					<div class="basic-form-line" id="_%column_name%_row">
						<label for="%column_name%" class="%label_class%">%form_label%</label>
						%input_control%
						<span class="extra-info" id="_user_name_message"></span>
						<div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
					</div>

					%method:newUserPassword%

					%field:new_user_password_confirm,new_user_email_address%
					%basic_form_line%
					%end repeat%

				</div>

				<div class="tabbed-content-page" id="website_information">
					<h2>Website Details</h2>

					%field:template_id%
					%basic_form_line%

					<p>Click image for larger view</p>
					<div id="template_image"></div>

					<div id="website_custom_fields"></div>

				</div>

				<div class="tabbed-content-page" id="payment_information">
					%method:paymentDetails%
				</div>
			</div>

			<p class='error-message'></p>
			<p>Building your site can take 6-8 minutes. Don't refresh or navigate away from this page.</p>
			<div class='tabbed-content-buttons'>
				<button class='tabbed-content-previous-page btn btn-secondary'>Previous</button>
				<button class='tabbed-content-next-page btn btn-secondary'>Next</button>
				<button class='tabbed-content-create-site disabled-button btn btn-primary' id="_create_site">Create Site</button>
			</div>

		</section>
	</div>

</div>
