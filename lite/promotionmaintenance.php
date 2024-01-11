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

$GLOBALS['gPageCode'] = "PROMOTIONMAINT_LITE";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 150000;

class ThisPage extends Page {
	function massageDataSource() {
		$this->iDataSource->getPrimaryTable()->setSubtables(array("promotion_rewards_excluded_products", "promotion_terms_excluded_products",
			"promotion_rewards_excluded_product_categories", "promotion_terms_excluded_product_categories", "promotion_group_links", "promotion_terms_products",
			"promotion_terms_product_categories", "promotion_terms_countries", "promotion_terms_user_types", "promotion_terms_contact_types",
			"promotion_rewards_products", "promotion_rewards_product_categories", "promotion_rewards_shipping_charges", "promotion_files", "promotion_banners"));

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

		$this->iDataSource->addColumnControl("promotion_banners", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("promotion_banners", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_banners", "form_label", "Banners");
		$this->iDataSource->addColumnControl("promotion_banners", "list_table", "promotion_banners");

		$this->iDataSource->addColumnControl("promotion_files", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("promotion_files", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_files", "form_label", "Files");
		$this->iDataSource->addColumnControl("promotion_files", "list_table", "promotion_files");

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
		$this->iDataSource->addColumnControl("promotion_rewards_product_categories","list_table_controls","return array('apply_to_requirements'=>array('form_label'=>'Apply to<br>Requirements'))");

		$this->iDataSource->addColumnControl("promotion_rewards_product_category_groups", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("promotion_rewards_product_category_groups", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_rewards_product_category_groups", "form_label", "Category Groups");
		$this->iDataSource->addColumnControl("promotion_rewards_product_category_groups", "list_table", "promotion_rewards_product_category_groups");
		$this->iDataSource->addColumnControl("promotion_rewards_product_category_groups","list_table_controls","return array('apply_to_requirements'=>array('form_label'=>'Apply to<br>Requirements'))");

		$this->iDataSource->addColumnControl("promotion_rewards_product_departments", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("promotion_rewards_product_departments", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_rewards_product_departments", "form_label", "Department Discounts");
		$this->iDataSource->addColumnControl("promotion_rewards_product_departments", "list_table", "promotion_rewards_product_departments");
		$this->iDataSource->addColumnControl("promotion_rewards_product_departments","list_table_controls","return array('apply_to_requirements'=>array('form_label'=>'Apply to<br>Requirements'))");

		$this->iDataSource->addColumnControl("promotion_rewards_product_manufacturers", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("promotion_rewards_product_manufacturers", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_rewards_product_manufacturers", "form_label", "Product Manufacturer Discounts");
		$this->iDataSource->addColumnControl("promotion_rewards_product_manufacturers", "list_table", "promotion_rewards_product_manufacturers");
		$this->iDataSource->addColumnControl("promotion_rewards_product_manufacturers","list_table_controls","return array('apply_to_requirements'=>array('form_label'=>'Apply to<br>Requirements'))");

		$this->iDataSource->addColumnControl("promotion_rewards_product_tags", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("promotion_rewards_product_tags", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_rewards_product_tags", "form_label", "Product Tags");
		$this->iDataSource->addColumnControl("promotion_rewards_product_tags", "list_table", "promotion_rewards_product_tags");
		$this->iDataSource->addColumnControl("promotion_rewards_product_tags","list_table_controls","return array('apply_to_requirements'=>array('form_label'=>'Apply to<br>Requirements'))");

		$this->iDataSource->addColumnControl("promotion_rewards_product_types", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("promotion_rewards_product_types", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_rewards_product_types", "form_label", "Product Types");
		$this->iDataSource->addColumnControl("promotion_rewards_product_types", "list_table", "promotion_rewards_product_types");
		$this->iDataSource->addColumnControl("promotion_rewards_product_types","list_table_controls","return array('apply_to_requirements'=>array('form_label'=>'Apply to<br>Requirements'))");

		$this->iDataSource->addColumnControl("promotion_rewards_products", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("promotion_rewards_products", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_rewards_products", "form_label", "Product Discounts");
		$this->iDataSource->addColumnControl("promotion_rewards_products","list_table_controls","return array('product_id'=>array('inline-width'=>'400px','inline-max-width'=>'400px'),'maximum_quantity'=>array('form_label'=>'Max Qty','inline-width'=>'70px'),'amount'=>array('inline-width'=>'90px'),'add_to_cart'=>array('form_label'=>'Automatically<br>Add Product'),'apply_to_requirements'=>array('form_label'=>'Apply to<br>Requirements'))");
		$this->iDataSource->addColumnControl("promotion_rewards_products", "list_table", "promotion_rewards_products");

		$this->iDataSource->addColumnControl("maximum_per_email", "form_label", "Maximum per email/user");

		$this->iDataSource->addColumnControl("promotion_rewards_sets", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("promotion_rewards_sets", "data_type", "custom");
		$this->iDataSource->addColumnControl("promotion_rewards_sets", "form_label", "Promotion Set Discounts");
		$this->iDataSource->addColumnControl("promotion_rewards_sets", "list_table", "promotion_rewards_sets");
		$this->iDataSource->addColumnControl("promotion_rewards_sets","list_table_controls","return array('apply_to_requirements'=>array('form_label'=>'Apply to<br>Requirements'))");

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
	}

	function afterSaveDone($nameValues) {
		removeCachedData("promotion_row_data",$nameValues['primary_id']);
		removeCachedData("automatic_promotions", "");
	}
}

$pageObject = new ThisPage("promotions");
$pageObject->displayPage();
