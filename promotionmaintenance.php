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

$GLOBALS['gPageCode'] = "PROMOTIONMAINT";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 150000;

class ThisPage extends Page {
	function massageDataSource() {
		$this->iDataSource->getPrimaryTable()->setSubtables(array("promotion_banners", "promotion_files", "promotion_group_links", "promotion_purchased_product_categories", "promotion_purchased_product_category_groups", "promotion_purchased_product_departments",
			"promotion_purchased_product_manufacturers", "promotion_purchased_product_tags", "promotion_purchased_product_types", "promotion_purchased_products", "promotion_purchased_sets",
			"promotion_rewards_excluded_product_categories", "promotion_rewards_excluded_product_category_groups", "promotion_rewards_excluded_product_departments", "promotion_rewards_excluded_product_manufacturers",
			"promotion_rewards_excluded_product_tags", "promotion_rewards_excluded_product_types", "promotion_rewards_excluded_products", "promotion_rewards_excluded_sets", "promotion_rewards_product_categories",
			"promotion_rewards_product_category_groups", "promotion_rewards_product_departments", "promotion_rewards_product_manufacturers", "promotion_rewards_product_tags", "promotion_rewards_product_types",
			"promotion_rewards_products", "promotion_rewards_sets", "promotion_rewards_shipping_charges", "promotion_terms_contact_types", "promotion_terms_countries", "promotion_terms_excluded_product_categories",
			"promotion_terms_excluded_product_category_groups", "promotion_terms_excluded_product_departments", "promotion_terms_excluded_product_manufacturers", "promotion_terms_excluded_product_tags",
			"promotion_terms_excluded_product_types", "promotion_terms_excluded_products", "promotion_terms_excluded_sets", "promotion_terms_product_categories", "promotion_terms_product_category_groups",
			"promotion_terms_product_departments", "promotion_terms_product_manufacturers", "promotion_terms_product_tags", "promotion_terms_product_types", "promotion_terms_products", "promotion_terms_sets", "promotion_terms_user_types"));

		$this->iDataSource->addColumnControl("detailed_description", "wysiwyg", "true");
		$this->iDataSource->addColumnControl("link_url", "css-width", "500px");
		$this->iDataSource->addColumnControl("link_url", "data_type", "varchar");
		$this->iDataSource->addColumnControl("link_url", "help_label", "For an external link");

		$this->iDataSource->addColumnControl("product_manufacturer_id", "help_label", "Manufacturer associated with this promotion");

		$this->iDataSource->addColumnControl("start_date", "form_line_classes", "inline-block");
		$this->iDataSource->addColumnControl("expiration_date", "form_line_classes", "inline-block");
		$this->iDataSource->addColumnControl("publish_start_date", "form_line_classes", "inline-block");
		$this->iDataSource->addColumnControl("publish_end_date", "form_line_classes", "inline-block");
		$this->iDataSource->addColumnControl("event_start_date", "form_line_classes", "inline-block");
		$this->iDataSource->addColumnControl("event_start_date", "help_label", "Limit events to these dates");
		$this->iDataSource->addColumnControl("event_end_date", "form_line_classes", "inline-block");

		$this->iDataSource->addColumnControl("no_previous_orders", "form_label", "Can only be used if user and email address have no previous orders for physical products");

		$this->iDataSource->addColumnControl("promotion_banners", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("promotion_banners", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_banners", "form_label", "Banners");
		$this->iDataSource->addColumnControl("promotion_banners", "list_table", "promotion_banners");

		$this->iDataSource->addColumnControl("promotion_files", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("promotion_files", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_files", "form_label", "Files");
		$this->iDataSource->addColumnControl("promotion_files", "list_table", "promotion_files");

		$this->iDataSource->addColumnControl("one_time_use_promotion_codes", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("one_time_use_promotion_codes", "data_type", "custom");
		$this->iDataSource->addColumnControl("one_time_use_promotion_codes", "form_label", "One-time Use Code");
		$this->iDataSource->addColumnControl("one_time_use_promotion_codes", "list_table", "one_time_use_promotion_codes");
		$this->iDataSource->addColumnControl("one_time_use_promotion_codes", "filter_where", "order_id is null");
		$this->iDataSource->addColumnControl("one_time_use_promotion_codes", "column_list", "promotion_code");
		$this->iDataSource->addColumnControl("one_time_use_promotion_codes", "list_table_controls", array("promotion_code" => array("inline-width" => "400px", "inline-max-width" => "400px")));

		$this->iDataSource->addColumnControl("used_one_time_use_promotion_codes", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("used_one_time_use_promotion_codes", "data_type", "custom");
		$this->iDataSource->addColumnControl("used_one_time_use_promotion_codes", "form_label", "Used One-time Use Code");
		$this->iDataSource->addColumnControl("used_one_time_use_promotion_codes", "list_table", "one_time_use_promotion_codes");
		$this->iDataSource->addColumnControl("used_one_time_use_promotion_codes", "filter_where", "order_id is not null");
		$this->iDataSource->addColumnControl("used_one_time_use_promotion_codes", "column_list", "promotion_code,order_id");
		$this->iDataSource->addColumnControl("used_one_time_use_promotion_codes", "readonly", true);
		$this->iDataSource->addColumnControl("used_one_time_use_promotion_codes", "list_table_controls", array("promotion_code" => array("inline-width" => "400px", "inline-max-width" => "400px"), "order_id" => array("list_header" => "Order ID", "data_type" => "int", "subtype" => "int")));

		$this->iDataSource->addColumnControl("promotion_group_links", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("promotion_group_links", "control_table", "promotion_groups");
		$this->iDataSource->addColumnControl("promotion_group_links", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_group_links", "form_label", "Promotion Groups");
		$this->iDataSource->addColumnControl("promotion_group_links", "links_table", "promotion_group_links");

		$this->iDataSource->addColumnControl("promotion_rewards_excluded_product_categories", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("promotion_rewards_excluded_product_categories", "control_table", "product_categories");
		$this->iDataSource->addColumnControl("promotion_rewards_excluded_product_categories", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_rewards_excluded_product_categories", "form_label", "Excluded Product Categories");
		$this->iDataSource->addColumnControl("promotion_rewards_excluded_product_categories", "links_table", "promotion_rewards_excluded_product_categories");

		$this->iDataSource->addColumnControl("promotion_rewards_excluded_product_category_groups", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("promotion_rewards_excluded_product_category_groups", "control_table", "product_category_groups");
		$this->iDataSource->addColumnControl("promotion_rewards_excluded_product_category_groups", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_rewards_excluded_product_category_groups", "form_label", "Excluded Category Groups");
		$this->iDataSource->addColumnControl("promotion_rewards_excluded_product_category_groups", "links_table", "promotion_rewards_excluded_product_category_groups");

		$this->iDataSource->addColumnControl("promotion_rewards_excluded_product_departments", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("promotion_rewards_excluded_product_departments", "control_table", "product_departments");
		$this->iDataSource->addColumnControl("promotion_rewards_excluded_product_departments", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_rewards_excluded_product_departments", "form_label", "Excluded Departments");
		$this->iDataSource->addColumnControl("promotion_rewards_excluded_product_departments", "links_table", "promotion_rewards_excluded_product_departments");

		$this->iDataSource->addColumnControl("promotion_rewards_excluded_product_manufacturers", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("promotion_rewards_excluded_product_manufacturers", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_rewards_excluded_product_manufacturers", "form_label", "Excluded Product Manufacturers");
		$this->iDataSource->addColumnControl("promotion_rewards_excluded_product_manufacturers", "list_table", "promotion_rewards_excluded_product_manufacturers");

		$this->iDataSource->addColumnControl("promotion_rewards_excluded_product_tags", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("promotion_rewards_excluded_product_tags", "control_table", "product_tags");
		$this->iDataSource->addColumnControl("promotion_rewards_excluded_product_tags", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_rewards_excluded_product_tags", "form_label", "Excluded Product Tags");
		$this->iDataSource->addColumnControl("promotion_rewards_excluded_product_tags", "links_table", "promotion_rewards_excluded_product_tags");

		$this->iDataSource->addColumnControl("promotion_rewards_excluded_product_types", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("promotion_rewards_excluded_product_types", "control_table", "product_types");
		$this->iDataSource->addColumnControl("promotion_rewards_excluded_product_types", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_rewards_excluded_product_types", "form_label", "Excluded Product Types");
		$this->iDataSource->addColumnControl("promotion_rewards_excluded_product_types", "links_table", "promotion_rewards_excluded_product_types");

		$this->iDataSource->addColumnControl("promotion_rewards_excluded_products", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("promotion_rewards_excluded_products", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_rewards_excluded_products", "form_label", "Excluded Products");
		$this->iDataSource->addColumnControl("promotion_rewards_excluded_products", "list_table", "promotion_rewards_excluded_products");

		$this->iDataSource->addColumnControl("promotion_rewards_excluded_sets", "control_class", (array_key_exists("promotion_set_id", $GLOBALS['gAutocompleteFields']) ? "EditableList" : "MultipleSelect"));
		$this->iDataSource->addColumnControl("promotion_rewards_excluded_sets", "links_table", "promotion_rewards_excluded_sets");
		$this->iDataSource->addColumnControl("promotion_rewards_excluded_sets", "list_table", "promotion_rewards_excluded_sets");
		$this->iDataSource->addColumnControl("promotion_rewards_excluded_sets", "control_table", "promotion_sets");
		$this->iDataSource->addColumnControl("promotion_rewards_excluded_sets", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_rewards_excluded_sets", "form_label", "Excluded Promotion Sets");

		$this->iDataSource->addColumnControl("promotion_rewards_product_categories", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("promotion_rewards_product_categories", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_rewards_product_categories", "form_label", "Category Discounts");
		$this->iDataSource->addColumnControl("promotion_rewards_product_categories", "list_table", "promotion_rewards_product_categories");
		$this->iDataSource->addColumnControl("promotion_rewards_product_categories", "list_table_controls", "return array('apply_to_requirements'=>array('form_label'=>'Apply to<br>Requirements'))");

		$this->iDataSource->addColumnControl("promotion_rewards_product_category_groups", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("promotion_rewards_product_category_groups", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_rewards_product_category_groups", "form_label", "Category Groups");
		$this->iDataSource->addColumnControl("promotion_rewards_product_category_groups", "list_table", "promotion_rewards_product_category_groups");
		$this->iDataSource->addColumnControl("promotion_rewards_product_category_groups", "list_table_controls", "return array('apply_to_requirements'=>array('form_label'=>'Apply to<br>Requirements'))");

		$this->iDataSource->addColumnControl("promotion_rewards_product_departments", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("promotion_rewards_product_departments", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_rewards_product_departments", "form_label", "Department Discounts");
		$this->iDataSource->addColumnControl("promotion_rewards_product_departments", "list_table", "promotion_rewards_product_departments");
		$this->iDataSource->addColumnControl("promotion_rewards_product_departments", "list_table_controls", "return array('apply_to_requirements'=>array('form_label'=>'Apply to<br>Requirements'))");

		$this->iDataSource->addColumnControl("promotion_rewards_product_manufacturers", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("promotion_rewards_product_manufacturers", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_rewards_product_manufacturers", "form_label", "Product Manufacturer Discounts");
		$this->iDataSource->addColumnControl("promotion_rewards_product_manufacturers", "list_table", "promotion_rewards_product_manufacturers");
		$this->iDataSource->addColumnControl("promotion_rewards_product_manufacturers", "list_table_controls", "return array('apply_to_requirements'=>array('form_label'=>'Apply to<br>Requirements'))");

		$this->iDataSource->addColumnControl("promotion_rewards_product_tags", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("promotion_rewards_product_tags", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_rewards_product_tags", "form_label", "Product Tags");
		$this->iDataSource->addColumnControl("promotion_rewards_product_tags", "list_table", "promotion_rewards_product_tags");
		$this->iDataSource->addColumnControl("promotion_rewards_product_tags", "list_table_controls", "return array('apply_to_requirements'=>array('form_label'=>'Apply to<br>Requirements'))");

		$this->iDataSource->addColumnControl("promotion_rewards_product_types", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("promotion_rewards_product_types", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_rewards_product_types", "form_label", "Product Types");
		$this->iDataSource->addColumnControl("promotion_rewards_product_types", "list_table", "promotion_rewards_product_types");
		$this->iDataSource->addColumnControl("promotion_rewards_product_types", "list_table_controls", "return array('apply_to_requirements'=>array('form_label'=>'Apply to<br>Requirements'))");

		$this->iDataSource->addColumnControl("promotion_rewards_products", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("promotion_rewards_products", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_rewards_products", "form_label", "Product Discounts");
		$this->iDataSource->addColumnControl("promotion_rewards_products", "list_table_controls", "return array('product_id'=>array('inline-width'=>'400px','inline-max-width'=>'400px'),'maximum_quantity'=>array('form_label'=>'Max Qty','inline-width'=>'70px'),'amount'=>array('inline-width'=>'90px'),'add_to_cart'=>array('form_label'=>'Automatically<br>Add Product'),'apply_to_requirements'=>array('form_label'=>'Apply to<br>Requirements'))");
		$this->iDataSource->addColumnControl("promotion_rewards_products", "list_table", "promotion_rewards_products");

		$this->iDataSource->addColumnControl("maximum_per_email", "form_label", "Maximum per email/user");

		$this->iDataSource->addColumnControl("promotion_rewards_sets", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("promotion_rewards_sets", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_rewards_sets", "form_label", "Promotion Set Discounts");
		$this->iDataSource->addColumnControl("promotion_rewards_sets", "list_table", "promotion_rewards_sets");
		$this->iDataSource->addColumnControl("promotion_rewards_sets", "list_table_controls", "return array('apply_to_requirements'=>array('form_label'=>'Apply to<br>Requirements'))");

		$this->iDataSource->addColumnControl("promotion_rewards_shipping_charges", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("promotion_rewards_shipping_charges", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_rewards_shipping_charges", "form_label", "Shipping Charges");
		$this->iDataSource->addColumnControl("promotion_rewards_shipping_charges", "list_table", "promotion_rewards_shipping_charges");

		$this->iDataSource->addColumnControl("promotion_terms_contact_types", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("promotion_terms_contact_types", "control_table", "contact_types");
		$this->iDataSource->addColumnControl("promotion_terms_contact_types", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_terms_contact_types", "form_label", "Valid Contact Types");
		$this->iDataSource->addColumnControl("promotion_terms_contact_types", "links_table", "promotion_terms_contact_types");

		$this->iDataSource->addColumnControl("promotion_terms_countries", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("promotion_terms_countries", "control_description_field", "country_name");
		$this->iDataSource->addColumnControl("promotion_terms_countries", "control_table", "countries");
		$this->iDataSource->addColumnControl("promotion_terms_countries", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_terms_countries", "form_label", "Valid Countries");
		$this->iDataSource->addColumnControl("promotion_terms_countries", "links_table", "promotion_terms_countries");

		$this->iDataSource->addColumnControl("promotion_terms_excluded_product_categories", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("promotion_terms_excluded_product_categories", "control_table", "product_categories");
		$this->iDataSource->addColumnControl("promotion_terms_excluded_product_categories", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_terms_excluded_product_categories", "form_label", "Excluded Product Categories");
		$this->iDataSource->addColumnControl("promotion_terms_excluded_product_categories", "links_table", "promotion_terms_excluded_product_categories");

		$this->iDataSource->addColumnControl("promotion_terms_excluded_product_category_groups", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("promotion_terms_excluded_product_category_groups", "control_table", "product_category_groups");
		$this->iDataSource->addColumnControl("promotion_terms_excluded_product_category_groups", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_terms_excluded_product_category_groups", "form_label", "Excluded Category Groups");
		$this->iDataSource->addColumnControl("promotion_terms_excluded_product_category_groups", "links_table", "promotion_terms_excluded_product_category_groups");

		$this->iDataSource->addColumnControl("promotion_terms_excluded_product_departments", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("promotion_terms_excluded_product_departments", "control_table", "product_departments");
		$this->iDataSource->addColumnControl("promotion_terms_excluded_product_departments", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_terms_excluded_product_departments", "form_label", "Excluded Departments");
		$this->iDataSource->addColumnControl("promotion_terms_excluded_product_departments", "links_table", "promotion_terms_excluded_product_departments");

		$this->iDataSource->addColumnControl("promotion_terms_excluded_product_manufacturers", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("promotion_terms_excluded_product_manufacturers", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_terms_excluded_product_manufacturers", "form_label", "Excluded Product Manufacturers");
		$this->iDataSource->addColumnControl("promotion_terms_excluded_product_manufacturers", "list_table", "promotion_terms_excluded_product_manufacturers");

		$this->iDataSource->addColumnControl("promotion_terms_excluded_product_tags", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("promotion_terms_excluded_product_tags", "control_table", "product_tags");
		$this->iDataSource->addColumnControl("promotion_terms_excluded_product_tags", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_terms_excluded_product_tags", "form_label", "Excluded Product Tags");
		$this->iDataSource->addColumnControl("promotion_terms_excluded_product_tags", "links_table", "promotion_terms_excluded_product_tags");

		$this->iDataSource->addColumnControl("promotion_terms_excluded_product_types", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("promotion_terms_excluded_product_types", "control_table", "product_types");
		$this->iDataSource->addColumnControl("promotion_terms_excluded_product_types", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_terms_excluded_product_types", "form_label", "Excluded Product Types");
		$this->iDataSource->addColumnControl("promotion_terms_excluded_product_types", "links_table", "promotion_terms_excluded_product_types");

		$this->iDataSource->addColumnControl("promotion_terms_excluded_products", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("promotion_terms_excluded_products", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_terms_excluded_products", "form_label", "Excluded Products");
		$this->iDataSource->addColumnControl("promotion_terms_excluded_products", "list_table", "promotion_terms_excluded_products");

		$this->iDataSource->addColumnControl("promotion_terms_excluded_sets", "control_class", (array_key_exists("promotion_set_id", $GLOBALS['gAutocompleteFields']) ? "EditableList" : "MultipleSelect"));
		$this->iDataSource->addColumnControl("promotion_terms_excluded_sets", "control_table", "promotion_sets");
		$this->iDataSource->addColumnControl("promotion_terms_excluded_sets", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_terms_excluded_sets", "form_label", "Excluded Promotion Sets");
		$this->iDataSource->addColumnControl("promotion_terms_excluded_sets", "links_table", "promotion_terms_excluded_sets");
		$this->iDataSource->addColumnControl("promotion_terms_excluded_sets", "list_table", "promotion_terms_excluded_sets");

		$this->iDataSource->addColumnControl("promotion_terms_product_categories", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("promotion_terms_product_categories", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_terms_product_categories", "form_label", "Product Category Requirements");
		$this->iDataSource->addColumnControl("promotion_terms_product_categories", "list_table", "promotion_terms_product_categories");

		$this->iDataSource->addColumnControl("promotion_terms_product_category_groups", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("promotion_terms_product_category_groups", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_terms_product_category_groups", "form_label", "Category Groups");
		$this->iDataSource->addColumnControl("promotion_terms_product_category_groups", "list_table", "promotion_terms_product_category_groups");

		$this->iDataSource->addColumnControl("promotion_terms_product_departments", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("promotion_terms_product_departments", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_terms_product_departments", "form_label", "Departments");
		$this->iDataSource->addColumnControl("promotion_terms_product_departments", "list_table", "promotion_terms_product_departments");

		$this->iDataSource->addColumnControl("promotion_terms_product_manufacturers", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("promotion_terms_product_manufacturers", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_terms_product_manufacturers", "form_label", "Product Manufacturer Requirements");
		$this->iDataSource->addColumnControl("promotion_terms_product_manufacturers", "list_table", "promotion_terms_product_manufacturers");

		$this->iDataSource->addColumnControl("promotion_terms_product_tags", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("promotion_terms_product_tags", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_terms_product_tags", "form_label", "Product Tags");
		$this->iDataSource->addColumnControl("promotion_terms_product_tags", "list_table", "promotion_terms_product_tags");

		$this->iDataSource->addColumnControl("promotion_terms_product_types", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("promotion_terms_product_types", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_terms_product_types", "form_label", "Product Types");
		$this->iDataSource->addColumnControl("promotion_terms_product_types", "list_table", "promotion_terms_product_types");

		$this->iDataSource->addColumnControl("promotion_terms_products", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("promotion_terms_products", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_terms_products", "form_label", "Product Requirements");
		$this->iDataSource->addColumnControl("promotion_terms_products", "list_table_controls", "return array('product_id'=>array('inline-width'=>'400px','inline-max-width'=>'400px'))");
		$this->iDataSource->addColumnControl("promotion_terms_products", "list_table", "promotion_terms_products");

		$this->iDataSource->addColumnControl("promotion_terms_sets", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("promotion_terms_sets", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_terms_sets", "form_label", "Promotion Set Requirements");
		$this->iDataSource->addColumnControl("promotion_terms_sets", "list_table", "promotion_terms_sets");

		$this->iDataSource->addColumnControl("promotion_terms_user_types", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("promotion_terms_user_types", "control_table", "user_types");
		$this->iDataSource->addColumnControl("promotion_terms_user_types", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_terms_user_types", "form_label", "Valid User Types");
		$this->iDataSource->addColumnControl("promotion_terms_user_types", "links_table", "promotion_terms_user_types");

		$this->iDataSource->addColumnControl("promotion_purchased_product_categories", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("promotion_purchased_product_categories", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_purchased_product_categories", "form_label", "Product Category Requirements");
		$this->iDataSource->addColumnControl("promotion_purchased_product_categories", "list_table", "promotion_purchased_product_categories");

		$this->iDataSource->addColumnControl("promotion_purchased_product_category_groups", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("promotion_purchased_product_category_groups", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_purchased_product_category_groups", "form_label", "Category Groups");
		$this->iDataSource->addColumnControl("promotion_purchased_product_category_groups", "list_table", "promotion_purchased_product_category_groups");

		$this->iDataSource->addColumnControl("promotion_purchased_product_departments", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("promotion_purchased_product_departments", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_purchased_product_departments", "form_label", "Departments");
		$this->iDataSource->addColumnControl("promotion_purchased_product_departments", "list_table", "promotion_purchased_product_departments");

		$this->iDataSource->addColumnControl("promotion_purchased_product_manufacturers", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("promotion_purchased_product_manufacturers", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_purchased_product_manufacturers", "form_label", "Product Manufacturer Requirements");
		$this->iDataSource->addColumnControl("promotion_purchased_product_manufacturers", "list_table", "promotion_purchased_product_manufacturers");

		$this->iDataSource->addColumnControl("promotion_purchased_product_tags", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("promotion_purchased_product_tags", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_purchased_product_tags", "form_label", "Product Tags");
		$this->iDataSource->addColumnControl("promotion_purchased_product_tags", "list_table", "promotion_purchased_product_tags");

		$this->iDataSource->addColumnControl("promotion_purchased_product_types", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("promotion_purchased_product_types", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_purchased_product_types", "form_label", "Product Types");
		$this->iDataSource->addColumnControl("promotion_purchased_product_types", "list_table", "promotion_purchased_product_types");

		$this->iDataSource->addColumnControl("promotion_purchased_products", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("promotion_purchased_products", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_purchased_products", "form_label", "Product Requirements");
		$this->iDataSource->addColumnControl("promotion_purchased_products", "list_table_controls", "return array('product_id'=>array('inline-width'=>'400px','inline-max-width'=>'400px'))");
		$this->iDataSource->addColumnControl("promotion_purchased_products", "list_table", "promotion_purchased_products");

		$this->iDataSource->addColumnControl("promotion_purchased_sets", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("promotion_purchased_sets", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_purchased_sets", "form_label", "Promotion Set Requirements");
		$this->iDataSource->addColumnControl("promotion_purchased_sets", "list_table", "promotion_purchased_sets");

		$this->iDataSource->addColumnControl("start_date", "default_value", date('m/d/Y'));

		$this->iDataSource->addColumnControl("user_id", "data_type", "user_picker");
		$this->iDataSource->addColumnControl("user_id", "help_label", "Promotion only valid for THIS user");

		if ($_GET['url_page'] == "show" && $_GET['subaction'] == "duplicate" && $GLOBALS['gPermissionLevel'] > _READONLY) {
			$promotionId = getFieldFromId("promotion_id", "promotions", "promotion_id", $_GET['primary_id'], "client_id is not null");
			if (empty($promotionId)) {
				return;
			}
			$resultSet = executeQuery("select * from promotions where promotion_id = ?", $promotionId);
			$promotionRow = getNextRow($resultSet);
			$originalPromotionCode = $promotionRow['promotion_code'];
			$subNumber = 1;
			$queryString = "";
			foreach ($promotionRow as $fieldName => $fieldData) {
				if (empty($queryString)) {
					$promotionRow[$fieldName] = "";
				}
				if ($fieldName == "client_id") {
					$promotionRow[$fieldName] = $GLOBALS['gClientId'];
				}
				$queryString .= (empty($queryString) ? "" : ",") . "?";
			}
			$newPromotionId = "";
			$promotionRow['description'] .= " Copy";
			while (empty($newPromotionId)) {
				$promotionRow['promotion_code'] = $originalPromotionCode . "_" . $subNumber;
				$resultSet = executeQuery("select * from promotions where promotion_code = ?", $promotionRow['promotion_code']);
				if ($row = getNextRow($resultSet)) {
					$subNumber++;
					continue;
				}
				$resultSet = executeQuery("insert into promotions values (" . $queryString . ")", $promotionRow);
				if ($resultSet['sql_error_number'] == 1062) {
					$subNumber++;
					continue;
				}
				$newPromotionId = $resultSet['insert_id'];
			}
			$_GET['primary_id'] = $newPromotionId;
			$subTables = array("promotion_banners", "promotion_files", "promotion_group_links", "promotion_purchased_product_categories", "promotion_purchased_product_category_groups", "promotion_purchased_product_departments",
				"promotion_purchased_product_manufacturers", "promotion_purchased_product_tags", "promotion_purchased_product_types", "promotion_purchased_products", "promotion_purchased_sets",
				"promotion_rewards_excluded_product_categories", "promotion_rewards_excluded_product_category_groups", "promotion_rewards_excluded_product_departments", "promotion_rewards_excluded_product_manufacturers",
				"promotion_rewards_excluded_product_tags", "promotion_rewards_excluded_product_types", "promotion_rewards_excluded_products", "promotion_rewards_excluded_sets", "promotion_rewards_product_categories",
				"promotion_rewards_product_category_groups", "promotion_rewards_product_departments", "promotion_rewards_product_manufacturers", "promotion_rewards_product_tags", "promotion_rewards_product_types",
				"promotion_rewards_products", "promotion_rewards_sets", "promotion_rewards_shipping_charges", "promotion_terms_contact_types", "promotion_terms_countries", "promotion_terms_excluded_product_categories",
				"promotion_terms_excluded_product_category_groups", "promotion_terms_excluded_product_departments", "promotion_terms_excluded_product_manufacturers", "promotion_terms_excluded_product_tags",
				"promotion_terms_excluded_product_types", "promotion_terms_excluded_products", "promotion_terms_excluded_sets", "promotion_terms_product_categories", "promotion_terms_product_category_groups",
				"promotion_terms_product_departments", "promotion_terms_product_manufacturers", "promotion_terms_product_tags", "promotion_terms_product_types", "promotion_terms_products", "promotion_terms_sets", "promotion_terms_user_types");
			foreach ($subTables as $tableName) {
				$resultSet = executeQuery("select * from " . $tableName . " where promotion_id = ?", $promotionId);
				while ($row = getNextRow($resultSet)) {
					$queryString = "";
					foreach ($row as $fieldName => $fieldData) {
						if (empty($queryString)) {
							$row[$fieldName] = "";
						}
						$queryString .= (empty($queryString) ? "" : ",") . "?";
					}
					$row['promotion_id'] = $newPromotionId;
					executeQuery("insert into " . $tableName . " values (" . $queryString . ")", $row);
				}
			}
		}
	}

	function afterSaveDone($nameValues) {
		removeCachedData("promotion_row_data", $nameValues['primary_id']);
		removeCachedData("automatic_promotions", "");
	}

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$filters = array();
			$promotionGroups = array();
			$resultSet = executeQuery("select * from promotion_groups where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$promotionGroups[$row['promotion_group_id']] = $row['description'];
			}
			$filters['promotion_group_id'] = array("form_label" => "Promotion Group",
				"where" => "promotion_id in (select promotion_id from promotion_group_links where promotion_group_id = %key_value%)", "data_type" => "select", "choices" => $promotionGroups);
			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
			if ($GLOBALS['gPermissionLevel'] > _READONLY) {
				$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("duplicate" => array("icon" => "fad fa-copy", "label" => getLanguageText("Duplicate"),
					"disabled" => false)));
			}
		}
	}

	function onLoadJavascript() {
		?>
		<script>
			<?php if ($GLOBALS['gPermissionLevel'] > _READONLY) { ?>
			$(document).on("click", "#generate_one_time", function () {
				$('#_generate_codes_dialog').dialog({
					closeOnEscape: true,
					draggable: false,
					modal: true,
					resizable: false,
					position: {my: "center top", at: "center top+100px", of: window, collision: "none"},
					width: 600,
					title: 'Generate One-Time Codes',
					buttons: {
						Save: function (event) {
							if (!empty($("#code_count").val()) && !isNaN($("#code_count").val())) {
								for (let generateCount = 0; generateCount <= $("#code_count").val(); generateCount++) {
									const promotionCode = makeId(20);
									addEditableListRow("one_time_use_promotion_codes", {promotion_code: promotionCode});
								}
								manualChangesMade = true;
							}
							$("#_generate_codes_dialog").dialog('close');
						},
						Cancel: function (event) {
							$("#_generate_codes_dialog").dialog('close');
						}
					}
				});
				return false;
			});
			$(document).on("tap click", "#_duplicate_button", function () {
				const $primaryId = $("#primary_id");
				if (!empty($primaryId.val())) {
					if (changesMade()) {
						askAboutChanges(function () {
							$('body').data('just_saved', 'true');
							document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=show&subaction=duplicate&primary_id=" + $primaryId.val();
						});
					} else {
						document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=show&subaction=duplicate&primary_id=" + $primaryId.val();
					}
				}
				return false;
			});
			<?php } ?>
		</script>
		<?php
	}

	function javascript() {
		?>
		<script>
			function afterGetRecord(returnArray) {
				<?php if ($GLOBALS['gPermissionLevel'] > _READONLY) { ?>
				if (empty($("#primary_id").val())) {
					disableButtons($("#_duplicate_button"));
				} else {
					enableButtons($("#_duplicate_button"));
				}
				<?php } ?>
			}
		</script>
		<?php
	}

	function hiddenElements() {
		?>
		<div id="_generate_codes_dialog" class="dialog-box">
			<p>Generate One-Time Use Codes</p>
			<div class="basic-form-line" id="_code_count_row">
				<label for="code_count">Number to generate</label>
				<input type='text' id="code_count" name="code_count" class="align-right validate[required,custom[integer],min[1]]" size='10'>
				<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
				<div class='clear-div'></div>
			</div>
		</div>
		<?php
	}
}

$pageObject = new ThisPage("promotions");
$pageObject->displayPage();
