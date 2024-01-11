<div id="_maintenance_form">

<div class="accordion-form">
	<ul class="tab-control-element">
		<li><a href="#tab_1">Details</a></li>
		<li><a href="#tab_2">Content</a></li>
		<li><a href="#tab_3">Links</a></li>
		<li><a href="#tab_4">Categories</a></li>
		<li><a href="#tab_5">Notifications</a></li>
		<li><a href="#tab_6">Custom</a></li>
	</ul>

<h3 class="accordion-control-element">Details</h3>
	<div id="tab_1">

%field:creator_user_id,contributor_id,date_created,link_name,post_status_id,public_access,user_type_id,allow_comments,comment_notification,author_notification,logged_in,approve_comments,publish_date,publish_time_part,published,hide_in_lists,internal_use_only,inactive%
%basic_form_line%
%end repeat%

	</div>

<h3 class="accordion-control-element">Content</h3>
	<div id="tab_2">

%field:title_text,content,excerpt,image_id%
%basic_form_line%
%end repeat%

	</div>

<h3 class="accordion-control-element">Links</h3>
	<div id="tab_3">

%field:post_links,related_posts%
%basic_form_line%
%end repeat%

	</div>

<h3 class="accordion-control-element">Categories</h3>
	<div id="tab_4">

%field:post_categories%
%basic_form_line%
	</div>

<h3 class="accordion-control-element">Notifications</h3>
	<div id="tab_5">

%field:post_notifications%
%basic_form_line%
	</div>

<h3 class="accordion-control-element">Custom</h3>
	<div id="tab_6">
%method:addCustomFields%
	</div>

</div>
</div>
