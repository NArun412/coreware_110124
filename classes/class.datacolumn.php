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

/**
 * class DataColumn
 *
 * Class that represents a data column. This data column will usually come from the database, but it can also be an
 * arbitrarily created column. The column can have any of a number of control values set and can create an HTML control.
 *
 * Control Names:
 *
 * add_button_label - only used for the FormList custom control. Sets the label on the add button.
 * before_save_record - Used by custom controls. Specifies a function to run before saving changes
 * builder_source - the javascript source code to use for the content builder
 * builder - true or false. For a text field, true means that ContentBuilder can be activated on the field
 * button_label - The label that goes on a button
 * choice_query - Used in multipleSelect class for choosing options
 * choice_where - custom where statement for a multipleSelect control
 * choices - Array of choices for a select control
 * classes - classes the control will use
 * code_value - true of a field should be treated as a code
 * column_list - used in the EditableList class for columns included in the control
 * column_name - The simple column name without the table name
 * contact_presets - Function to call to get contacts that should appear in the contact picker dropdown
 * control_class - for a custom control, this is the class of the control
 * control_description_field - used by custom control classes to specify the field used for the control description
 * control_key - for the MultipleSelect class, if the control table key is different than the default
 * control_table - for the MultipleSelect class, the control table that the choices come from
 * create_control_method - Used to specify a method that will be used to create the column control instead of the standard creation logic
 * css- - add this CSS to the page for this control
 * data_format - format used by this field. Could be things like email, phone, etc
 * data_type - The data type of the field. This is the data type used by the column class to construct the control
 * data- - Set a data value for the control
 * date_format - specify the format used by a date column
 * decimal_places - number of decimal places
 * default_value - default value assigned to the field if no value is assigned
 * dont_escape - Don't escape the contents of this control. This could result in the data of the control showing HTML
 * empty_text - Change the text of the empty value option of a select element
 * exact_search - For this column, only do an exact search, which is much faster
 * filter_where - for the EditableList class, a where statement with which to filter the list. For a select control, filters the control table
 * foreign_key - true or false. Indicates that the column is a foreign key
 * foreign_key_field - for the EditableList class, the column that is the foreign key, if different than the primary key
 * form_label - label on the form for this field
 * form_line_classes - classes the control will use for the whole form line
 * full_column_name - The full qualified name of the column, which includes the table name
 * get_choices - function to call to get the choices for a select control
 * help_label - text of help label for control
 * hide_selector - For a contact or user picker control, hide the dropdown
 * ignore_crc - Ignore the CRC value for this field. The result of this is that the system won't ask to save the record when this field is changed.
 * include_default_client - Include default client values when showing options for this field
 * initial_value_display - For a span or literal data type, the initial text displayed
 * initial_value - set the initial value of the control. This is different than default value in that it is only set on the control and not when inserting the column into the database
 * letter_case - Letter case of the field
 * links_table - for the MultipleSelect class, the table that links the control table and the primary table
 * list_header - Header used for the maintenance page list
 * list_table - for the EditableList class, the list table
 * list_table_controls - for the EditableList class, controls on the columns within the control
 * maximum_length - maximum number of characters allowed
 * maximum_value - maximum value allowed
 * minimum_size - minimum length of data in the field
 * minimum_value - minimum value allowed
 * mysql_type - the original mysql type of the column
 * no_add - No add ability on custom controls
 * no_datepicker - Don't use datepicker on date field
 * no_delete - No delete ability on custom controls
 * no_description - On a radio control, the description for the "NO" value
 * no_download - On a file or image control, remove the download option
 * no_editor - Do not use the Ace editor even if WYSIWYG
 * no_empty_option - For a select control, do not include an empty option
 * no_first - for a yes/no dropdown control, make "No" the first option
 * no_remove - On a file or image control, do not include the "Remove" checkbox
 * no_required_label - For a control that is required, setting this to true will remove the red asterisk
 * no_timepicker - For a time control, don't show the time picker
 * no_view - for an image control, remove the link to view the image
 * normal_label - For a checkbox or radio button, this is the label that is displayed above the control
 * not_editable - the field can be set on a new record, but not editable on existing records
 * not_null - true or false. required or not.
 * password_strength - Password strength required
 * placeholder - placeholder for the field
 * primary_key - true or false. Set if column is primary key of table
 * primary_key_field - the primary key field for a custom control, if different than default
 * primary_table - for custom control class, the primary table
 * readonly - true or false.
 * reverse_sort - Set the reverse sort for a custom control or select
 * save_datetime - Generally, date time fields are not saved. This allows them to be
 * select_value - for an additional column, the select value of the column
 * selected_values - for MultipleSelect class, array of preselected values
 * selected_values_query - for MultipleSelect class, query to get preselected values
 * show_data - For password fields, for the data to be shown
 * show_id_field - For a contact or user picker control, show the ID field
 * size - size of the form field
 * sort_order - for EditableList class, the order of the items in the list
 * subtype - subtype of the column. Currently can only have a value of "image" or "file"
 * supplement_get_record - For a FormList custom control, an additional function that is run after the get record function
 * tabindex - tabindex for the field. Generally, tabindex is set to 10. This can override it.
 * title_generator - For a FormList control, function to generate the title
 * use_description - For a select control, use the description as the value
 * user_presets - For user picker control, function to return users that will be in dropdown
 * user_sets_order - for MultipleSelect class, true or false, indicates whether the user sets the order of the items
 * valid_values - valid values for a field
 * validation_classes - classes inserted into the validate[] class structure
 * wysiwyg - true or false. For a text field, true means CKEditor can be activated on the field
 * yes_description - On a radio control, the description for the "YES" value
 *
 * @author Kim D Geiger
 */
class DataColumn {
    private static $iAllColumnMetadata = false;
    private static $iAllColumnDefinitions = false;
    private static $iAllTableColumnInformation = false;
    private static $iAllChoices = false;
    private static $iPresetReferencedColumns = array("contacts" => array("first_name", "last_name", "business_name"));
    private $iDatabase = null;
    private $iTableName = "";
    private $iColumnName = "";
    private $iColumnMetadata = array();
    private $iDataValue = "";
    private $iErrorMessage = "";
    private $iReferencedTableName = "";
    private $iReferencedColumnName = "";
    private $iReferencedDescriptionColumns = array();
    private $iSearchable = false;
    private $iSearchExact = false;

    /**
     * Construct - When creating the class, you must set the column and table name. The constructor can pass in the column
     * name, table name and, optionally, the database name. It can also pass in the column name and an array of control
     * values.
     * @param
     *    $columnName - name of the column for which this data source is being constructed
     *    $tableName - name of the table for which this data source is being constructed
     *    $database - database object of the data containing the table. This will default to the primary database
     * @return
     *    none
     */
    function __construct($columnName, $tableName = "", $database = "") {
        if (self::$iAllColumnMetadata === false) {
            self::$iAllColumnMetadata = array();
        }
        if (self::$iAllColumnDefinitions === false) {
            self::$iAllColumnDefinitions = array();
        }
        if (self::$iAllTableColumnInformation === false) {
            self::$iAllTableColumnInformation = array();
        }
        $this->iColumnName = $columnName;
        $this->iColumnMetadata['column_name'] = $columnName;
        if (empty($database)) {
            $this->iDatabase = $GLOBALS['gPrimaryDatabase'];
        } else {
            $this->iDatabase = $database;
        }
        if (is_array($tableName)) {
            $this->iColumnMetadata = $tableName;
        } else if (!empty($tableName)) {
            $this->iTableName = $tableName;
            $metaKey = $this->iDatabase->getName() . "." . $tableName . "." . $columnName;
            if (array_key_exists($metaKey, self::$iAllColumnMetadata)) {
                $this->iColumnMetadata = self::$iAllColumnMetadata[$metaKey]['column_meta_data'];
                $this->iReferencedTableName = self::$iAllColumnMetadata[$metaKey]['referenced_table_name'];
                $this->iReferencedColumnName = self::$iAllColumnMetadata[$metaKey]['referenced_column_name'];
                $this->iReferencedDescriptionColumns = self::$iAllColumnMetadata[$metaKey]['description_columns'];
            } else {
                self::$iAllColumnMetadata[$metaKey] = getCachedData("all_column_metadata", $metaKey . "_" . $GLOBALS['gLanguageId'], true);
                if (!is_array(self::$iAllColumnMetadata[$metaKey])) {
                    self::$iAllColumnMetadata[$metaKey] = array();
                }
                if (empty(self::$iAllColumnMetadata[$metaKey])) {
                    $this->getInformation();
                    self::$iAllColumnMetadata[$metaKey] = array();
                    self::$iAllColumnMetadata[$metaKey]['column_meta_data'] = $this->iColumnMetadata;
                    self::$iAllColumnMetadata[$metaKey]['referenced_table_name'] = $this->iReferencedTableName;
                    self::$iAllColumnMetadata[$metaKey]['referenced_column_name'] = $this->iReferencedColumnName;
                    self::$iAllColumnMetadata[$metaKey]['description_columns'] = $this->iReferencedDescriptionColumns;
                    setCachedData("all_column_metadata", $metaKey . "_" . $GLOBALS['gLanguageId'], self::$iAllColumnMetadata[$metaKey], 24, true);
                } else {
                    $this->iColumnMetadata = self::$iAllColumnMetadata[$metaKey]['column_meta_data'];
                    $this->iReferencedTableName = self::$iAllColumnMetadata[$metaKey]['referenced_table_name'];
                    $this->iReferencedColumnName = self::$iAllColumnMetadata[$metaKey]['referenced_column_name'];
                    $this->iReferencedDescriptionColumns = self::$iAllColumnMetadata[$metaKey]['description_columns'];
                }
            }
            switch ($this->iColumnMetadata['data_type']) {
                case "date":
                case "int":
                case "integer":
                case "decimal":
                    $this->iSearchExact = true;
                case "text":
                case "varchar":
                    $this->iSearchable = false;
                    break;
            }
        }
    }

    /**
     * getInformation - Private method that gathers data about the column
     *
     */
    private function getInformation() {
        $this->iColumnMetadata = array();
        $row = $this->iDatabase->getColumnInformation($this->iTableName, $this->iColumnName);
        if (!$row) {
            return;
        }
        $typeParts = explode("(", str_replace(")", "", $row['COLUMN_TYPE']));
        if (count($typeParts) == 1) {
            $typeParts[] = 0;
        }
        $sizeParts = explode(",", $typeParts[1]);
        $this->iColumnMetadata['full_column_name'] = $this->iTableName . "." . $row['COLUMN_NAME'];
        $this->iColumnMetadata['column_name'] = $row['COLUMN_NAME'];
        $this->iColumnMetadata['form_label'] = ucwords(str_replace("_", " ", $row['COLUMN_NAME']));
        $this->iColumnMetadata['data_type'] = $this->iColumnMetadata['mysql_type'] = $typeParts[0];
        $this->iColumnMetadata['subtype'] = "";
        $this->iColumnMetadata['maximum_length'] = $sizeParts[0];
        $this->iColumnMetadata['decimal_places'] = $sizeParts[1];
        $this->iColumnMetadata['not_null'] = ($row['IS_NULLABLE'] != "YES" && $typeParts[0] != "tinyint");
        $this->iColumnMetadata['default_value'] = ($row['COLUMN_DEFAULT'] == "NULL" ? "" : $row['COLUMN_DEFAULT']);
        $this->iColumnMetadata['foreign_key'] = false;
        $this->iColumnMetadata['database_metadata'] = $row;
        if (!array_key_exists($row['COLUMN_NAME'], self::$iAllColumnDefinitions)) {
            self::$iAllColumnDefinitions[$row['COLUMN_NAME']] = getCachedData("all_column_definitions", $this->iDatabase->getName() . "-" . $row['COLUMN_NAME'] . "-" . $GLOBALS['gLanguageId'], true);
        }
        if (!array_key_exists($row['COLUMN_NAME'], self::$iAllColumnDefinitions) || self::$iAllColumnDefinitions[$row['COLUMN_NAME']] === false) {
            if ($this->iTableName) {
                $this->getColumnDefinitions($this->iTableName);
            }
            if (!array_key_exists($row['COLUMN_NAME'], self::$iAllColumnDefinitions)) {
                $columnSet = executeReadQuery("select * from column_definitions where column_name = ?", $row['COLUMN_NAME']);
                if ($columnRow = $this->iDatabase->getNextRow($columnSet)) {
                    self::$iAllColumnDefinitions[$row['COLUMN_NAME']] = $columnRow;
                    setCachedData("all_column_definitions", $this->iDatabase->getName() . "-" . $row['COLUMN_NAME'] . "-" . $GLOBALS['gLanguageId'], $columnRow, 24, true);
                }
                freeResult($columnSet);
            }
        }
        $this->iColumnMetadata['minimum_value'] = self::$iAllColumnDefinitions[$row['COLUMN_NAME']]['minimum_value'];
        $this->iColumnMetadata['maximum_value'] = self::$iAllColumnDefinitions[$row['COLUMN_NAME']]['maximum_value'];
        $this->iColumnMetadata['valid_values'] = self::$iAllColumnDefinitions[$row['COLUMN_NAME']]['valid_values'];
        $this->iColumnMetadata['code_value'] = self::$iAllColumnDefinitions[$row['COLUMN_NAME']]['code_value'];
        $this->iColumnMetadata['letter_case'] = self::$iAllColumnDefinitions[$row['COLUMN_NAME']]['letter_case'];
        $this->iColumnMetadata['data_format'] = self::$iAllColumnDefinitions[$row['COLUMN_NAME']]['data_format'];
        if (!empty(self::$iAllColumnDefinitions[$row['COLUMN_NAME']]['default_value'])) {
            $this->iColumnMetadata['default_value'] = self::$iAllColumnDefinitions[$row['COLUMN_NAME']]['default_value'];
        }
        $this->iColumnMetadata['maximum_length'] = self::$iAllColumnDefinitions[$row['COLUMN_NAME']]['data_size'];
        $this->iColumnMetadata['decimal_places'] = self::$iAllColumnDefinitions[$row['COLUMN_NAME']]['decimal_places'];

        $columnRow = $this->getTableColumnInformation($this->iTableName, self::$iAllColumnDefinitions[$row['COLUMN_NAME']]['column_definition_id']);
        if ($columnRow) {
            if ($this->iColumnMetadata['data_type'] != "tinyint") {
                $this->iColumnMetadata['not_null'] = ($this->iColumnMetadata['not_null'] || $columnRow['not_null'] == 1);
            }
            $this->iColumnMetadata['form_label'] = $columnRow['description'];
            if (!empty($columnRow['default_value'])) {
                $this->iColumnMetadata['default_value'] = $columnRow['default_value'];
            }
        }
        if (substr($this->iColumnName, -3) == "_id") {
            $row = $this->getKeyColumnUsage($this->iTableName, $this->iColumnName);
            if ($row) {
                $this->iReferencedTableName = $row['REFERENCED_TABLE_NAME'];
                $this->iReferencedColumnName = $row['REFERENCED_COLUMN_NAME'];
                $this->iColumnMetadata['foreign_key'] = true;
                if ($row['REFERENCED_TABLE_NAME'] == "images") {
                    $this->iColumnMetadata['subtype'] = "image";
                }
                if ($row['REFERENCED_TABLE_NAME'] == "files") {
                    $this->iColumnMetadata['subtype'] = "file";
                }
                $this->iReferencedDescriptionColumns = array();
                if ($this->iDatabase->fieldExists($this->iReferencedTableName, "description")) {
                    $this->iReferencedDescriptionColumns[] = "description";
                } else if (array_key_exists($this->iReferencedTableName, self::$iPresetReferencedColumns)) {
                    foreach (self::$iPresetReferencedColumns[$this->iReferencedTableName] as $thisColumn) {
                        $this->iReferencedDescriptionColumns[] = $thisColumn;
                    }
                } else {
                    $columnInformation = $this->iDatabase->getTableColumns($this->iReferencedTableName);
                    foreach ($columnInformation as $row) {
                        if (substr($row['COLUMN_TYPE'], 0, 7) == "varchar" && substr($row['COLUMN_NAME'], -5) != "_code") {
                            $this->iReferencedDescriptionColumns[] = $row['COLUMN_NAME'];
                            break;
                        }
                    }
                    if (empty($this->iReferencedDescriptionColumns)) {
                        foreach ($columnInformation as $row) {
                            if (substr($row['COLUMN_TYPE'], 0, 7) == "varchar") {
                                $this->iReferencedDescriptionColumns[] = $row['COLUMN_NAME'];
                                break;
                            }
                        }
                    }
                }
            }
        }
    }

    function getColumnDefinitions($tableName) {
        $columnSet = executeReadQuery("select * from column_definitions where column_definition_id in " .
            "(select column_definition_id from table_columns where table_id = (select table_id from tables where table_name = ? " .
            "and database_definition_id = (select database_definition_id from database_definitions where database_name = ?)))",
            $tableName, $this->iDatabase->getName());
        while ($columnRow = $this->iDatabase->getNextRow($columnSet)) {
            self::$iAllColumnDefinitions[$columnRow['column_name']] = $columnRow;
            setCachedData("all_column_definitions", $this->iDatabase->getName() . "-" . $columnRow['COLUMN_NAME'] . "-" . $GLOBALS['gLanguageId'], $columnRow, 24, true);
        }
        freeResult($columnSet);
    }

    function getTableColumnInformation($tableName, $columnDefinitionId) {
        if (!array_key_exists($tableName, self::$iAllTableColumnInformation)) {
            self::$iAllTableColumnInformation[$tableName] = getCachedData("all_table_column_information", $tableName . "_" . $GLOBALS['gLanguageId'], true);
        }
        if (!array_key_exists($tableName, self::$iAllTableColumnInformation) || self::$iAllTableColumnInformation[$tableName] === false) {
            self::$iAllTableColumnInformation[$tableName] = array();
            $columnSet = executeReadQuery("select * from table_columns where table_id = (select table_id from tables where table_name = ? " .
                "and database_definition_id = (select database_definition_id from database_definitions where database_name = ?))",
                $tableName, $this->iDatabase->getName());
            while ($columnRow = getNextRow($columnSet)) {
                self::$iAllTableColumnInformation[$tableName][$columnRow['column_definition_id']] = $columnRow;
            }
            freeResult($columnSet);
            setCachedData("all_table_column_information", $tableName . "_" . $GLOBALS['gLanguageId'], self::$iAllTableColumnInformation[$tableName], 24, true);
        }
        if (!array_key_exists($columnDefinitionId, self::$iAllTableColumnInformation[$tableName])) {
            return false;
        } else {
            return self::$iAllTableColumnInformation[$tableName][$columnDefinitionId];
        }
    }

    function getKeyColumnUsage($tableName, $columnName) {
		if (empty($GLOBALS['gKeyColumnUsage']) || !is_array($GLOBALS['gKeyColumnUsage'])) {
			$GLOBALS['gKeyColumnUsage'] = array();
		}
		$tableKey = $this->iDatabase->getName() . "." . $tableName;
	    if (!array_key_exists($tableKey,$GLOBALS['gKeyColumnUsage'])) {
		    $GLOBALS['gKeyColumnUsage'][$tableKey] = getCachedData("all_key_column_usage", $tableKey, true);
		    if (empty($GLOBALS['gKeyColumnUsage'][$tableKey]) || !is_array($GLOBALS['gKeyColumnUsage'][$tableKey])) {
			    $GLOBALS['gKeyColumnUsage'][$tableKey] = array();
			    $resultSet = executeReadQuery("select * from information_schema.KEY_COLUMN_USAGE where table_schema = ? and table_name = ? order by ordinal_position", $this->iDatabase->getName(), $this->iTableName);
			    while ($row = getNextRow($resultSet)) {
				    if (!array_key_exists($row['COLUMN_NAME'], $GLOBALS['gKeyColumnUsage'][$tableKey])) {
					    $GLOBALS['gKeyColumnUsage'][$tableKey][$row['COLUMN_NAME']] = array();
				    }
				    $GLOBALS['gKeyColumnUsage'][$tableKey][$row['COLUMN_NAME']][] = $row;
			    }
			    freeResult($resultSet);
			    setCachedData("all_key_column_usage", $tableKey, $GLOBALS['gKeyColumnUsage'][$tableKey], 168, true);
		    }
	    }
        if (!array_key_exists($columnName, $GLOBALS['gKeyColumnUsage'][$tableKey])) {
            return false;
        } else {
			foreach ($GLOBALS['gKeyColumnUsage'][$tableKey][$columnName] as $row) {
				if (!empty($row['REFERENCED_TABLE_SCHEMA'])) {
					return $row;
				}
			}
			return false;
        }
    }

    /**
     * setSearchable - Flag whether this column is searchable. This property is used by the template.
     *
     * @param $searchable - true or false whether the column is searchable
     */
    function setSearchable($searchable) {
        $this->iSearchable = $searchable;
    }

    /**
     * isSearchable - discover whether this column is searchable or not
     *
     * @return true or false whether the column is searchable or not
     */
    function isSearchable() {
        return $this->iSearchable;
    }

    /**
     * isSearchExact - generally, when columns are searchable, the column can be searched using the database "like", so that
     * the search string can appear anywhere in the field. For some types of columns, this doesn't make sense. This flag
     * indicates whether the column requires an exact match or not. Date and numeric columns require an exact match.
     *
     * @return true or false whether the column is searchable or not
     */
    function isSearchExact() {
        return $this->iSearchExact;
    }

    /**
     * setReferencedDescriptionColumns - The system tries to find the field that would be used for the display value of foreign keys. In cases where
     *    the system cannot determine the right field, this allows the developer to set the field. It can be one field or a concatenation of fields.
     * @param
     *    either a string of comma separated field names or an array of field names. If there are more than one, they will be concatenated with a space
     *        between them
     * @return
     *    none
     */
    function setReferencedDescriptionColumns($fieldNames) {
        if (is_array($fieldNames)) {
            $this->iReferencedDescriptionColumns = $fieldNames;
        } else {
            $this->iReferencedDescriptionColumns = explode(",", $fieldNames);
        }
    }

    /**
     * getErrorMessage - return the error message from the most recent error
     * @param
     *    none
     * @return
     *    error message
     */
    function getErrorMessage() {
        return $this->iErrorMessage;
    }

    /**
     * getControl - Create the HTML element that will serve as the data entry device or control for the data of this column.
     *    This is a relatively complicated process to construct the control, when many control values affecting the outcome.
     *
     * @param pageObject - the page the is serving the control. This is used, when needed, for methods to get choices for select columns
     */
    function getControl($pageObject = false) {
        if (!empty($pageObject) && array_key_exists("create_control_method", $this->iColumnMetadata) && !empty($this->iColumnMetadata['create_control_method']) && method_exists($pageObject, $this->iColumnMetadata['create_control_method'])) {
            $createControlMethod = $this->iColumnMetadata['create_control_method'];
            return $pageObject->$createControlMethod();
        }
        $controlElement = "";
        $validationNeeded = false;
        if (array_key_exists("validation_classes", $this->iColumnMetadata)) {
            if (is_array($this->iColumnMetadata['validation_classes'])) {
                $validationClasses = $this->iColumnMetadata['validation_classes'];
            } else {
                $validationClasses = explode(" ", str_replace(",", " ", $this->iColumnMetadata['validation_classes']));
            }
        } else {
            $validationClasses = array();
        }
        if (array_key_exists("classes", $this->iColumnMetadata)) {
            if (is_array($this->iColumnMetadata['classes'])) {
                $classes = $this->iColumnMetadata['classes'];
            } else {
                $classes = explode(" ", str_replace(",", " ", $this->iColumnMetadata['classes']));
            }
        } else {
            $classes = array();
        }
        if (empty($classes) && $this->iColumnName == "link_name") {
        	$classes[] = "url-link";
        }
        if ($this->iColumnName == "display_color" && !in_array("minicolors", $classes)) {
            $classes[] = "minicolors";
        }
        if (!empty($this->iColumnMetadata['code_value'])) {
            $classes[] = "code-value";
            $validationNeeded = true;
        }
        if (!empty($this->iColumnMetadata['not_editable'])) {
            $classes[] = "not-editable";
        }
        if (strlen($this->iColumnMetadata['minimum_value']) > 0) {
            $validationClasses[] = "min[" . $this->iColumnMetadata['minimum_value'] . "]";
        }
        if (strlen($this->iColumnMetadata['minimum_size']) > 0) {
            $validationClasses[] = "minSize[" . $this->iColumnMetadata['minimum_size'] . "]";
        }
        if (strlen($this->iColumnMetadata['maximum_value']) > 0) {
            $validationClasses[] = "max[" . $this->iColumnMetadata['maximum_value'] . "]";
        }
        if (!empty($this->iColumnMetadata['letter_case'])) {
            $validationNeeded = true;
        }
	    if ($this->iColumnMetadata['data_type'] != "select") {
		    if ($this->iColumnMetadata['letter_case'] == "U") {
			    $classes[] = "uppercase";
		    } else if ($this->iColumnMetadata['letter_case'] == "L") {
			    $classes[] = "lowercase";
		    } else if ($this->iColumnMetadata['letter_case'] == "C" && getPreference("USE_FIELD_CAPITALIZATION")) {
			    $classes[] = "capitalize";
		    }
	    }
        if ($this->iColumnMetadata['not_null'] || !empty($this->iColumnMetadata['data-conditional-required'])) {
            $validationClasses[] = "required";
        }
		$attributes = "";
        $dataSettings = "";
        $styleSettings = "";
        foreach ($this->iColumnMetadata as $controlName => $controlData) {
            if (substr($controlName, 0, strlen("data-")) == "data-") {
                if (!empty($dataSettings)) {
                    $dataSettings .= " ";
                }
                $dataSettings .= $controlName . "='" . $controlData . "'";
            }
	        if (substr($controlName, 0, strlen("inline-")) == "inline-") {
		        $styleSettings .= substr($controlName, strlen("inline-")) . ": " . $controlData . ";";
	        }
	        if (substr($controlName, 0, strlen("attribute-")) == "attribute-") {
		        $attributes .= (empty($attributes) ? "" : " ") . substr($controlName, strlen("attribute-")) . "='" . $controlData . "'";
	        }
        }
        if (!empty($styleSettings)) {
            $styleSettings = "style=\"" . $styleSettings . "\"";
        }
        if (array_key_exists("tabindex", $this->iColumnMetadata)) {
            if (empty($this->iColumnMetadata['tabindex'])) {
                $tabindex = "";
            } else {
                $tabindex = "tabindex='" . $this->iColumnMetadata['tabindex'] . "'";
            }
        } else {
            $tabindex = "tabindex='10'";
        }
        switch ($this->iColumnMetadata['data_type']) {
            case "button":
                $controlElement = "<button " . $tabindex . " id='" . $this->iColumnMetadata['column_name'] . "' class='%classString%'>" . htmlText($this->iColumnMetadata['button_label']) . "</button>";
                break;
            case "custom_control":
            case "custom":
                $controlClass = $this->iColumnMetadata['control_class'];
                $customControl = new $controlClass($this, $pageObject);
                $controlElement = $customControl->getControl();
                break;
            case "datetime":
                $controlElement = "<input $attributes $styleSettings " . $tabindex . " readonly='readonly' class='borderless %classString%' type='text' value='" .
                    (empty($this->iColumnMetadata['initial_value']) ? "" : date((empty($this->iColumnMetadata['date_format']) ? "m/d/Y g:ia" : $this->iColumnMetadata['date_format']), strtotime($this->iColumnMetadata['initial_value']))) .
                    "' size='25' maxlength='25' id='" . $this->iColumnMetadata['column_name'] . "'" .
                    ($this->iColumnMetadata['save_datetime'] ? " name='" . $this->iColumnMetadata['column_name'] . "'" : "") . " />";
                break;
            case "image_input":
                $controlElement = ($this->iColumnMetadata['readonly'] ? "" : "<input $attributes $styleSettings class='%classString%' type='file' accept='image/*' data-conditional-required='" .
                        (empty($this->iColumnMetadata['data-conditional-required']) ? "$(\"#" . $this->iColumnMetadata['column_name'] .
                            "\").val() == \"\"" : $this->iColumnMetadata['data-conditional-required']) . "' name='" . $this->iColumnMetadata['column_name'] .
                        "_file' id='" . $this->iColumnMetadata['column_name'] . "_file' " . $tabindex . " />") . "<span class='file-info'>" .
                    ($this->iColumnMetadata['no_view'] ? "" : "&nbsp;<a href='" . (empty($this->iColumnMetadata['initial_value']) ? "" : getImageFilename($this->iColumnMetadata['initial_value'])) . "' class='pretty-photo view-image-link' id='" . $this->iColumnMetadata['column_name'] .
                        "_view'><span class='fad fa-eye'></span> <span id='" . $this->iColumnMetadata['column_name'] . "_filename'></span></a>") .
                    ($this->iColumnMetadata['readonly'] || $this->iColumnMetadata['not_null'] || $this->iColumnMetadata['no_remove'] ? "" : "&nbsp;<input " . $tabindex . " type='checkbox' name='remove_" .
                        $this->iColumnMetadata['column_name'] . "' class='remove-image-checkbox' id='remove_" .
                        $this->iColumnMetadata['column_name'] . "' value='1' /><label class='checkbox-label' for='remove_" . $this->iColumnMetadata['column_name'] .
                        "'>" . getLanguageText("Remove Image") . "</label>") . "<input type='hidden' id='" . $this->iColumnMetadata['column_name'] .
                    "' name='" . $this->iColumnMetadata['column_name'] . "' value='" . $this->iColumnMetadata['initial_value'] . "' /></span>";
                break;
            case "image":
            case "image_picker":
                $controlElement = "<select " . $tabindex . " class='image-picker-selector field-text" . ($this->iColumnMetadata['not_null'] ? " validate[required]" : "") .
                    "' id='" . $this->iColumnMetadata['column_name'] . "' name='" . $this->iColumnMetadata['column_name'] . "'><option value=''>" . getLanguageText("No image selected. Click to choose") . " -></option></select>" .
                    "<button class='image-picker' data-column_name='" .
                    $this->iColumnMetadata['column_name'] . "' " . $tabindex . ">" . getLanguageText("Choose") . "</button>&nbsp;<a href='' class='pretty-photo view-image-link' id='" .
                    $this->iColumnMetadata['column_name'] . "_view'>" . getLanguageText("View") . " <span id='" . $this->iColumnMetadata['column_name'] . "_filename'></span></a>";
                break;
            case "contact_picker":
                $classes[] = "contact-picker-field";
                $contactPresets = "";
                $contactPresetsArray = array();
                if ($this->iColumnMetadata['contact_presets'] && method_exists($pageObject, $this->iColumnMetadata['contact_presets'])) {
                    $presetFunction = $this->iColumnMetadata['contact_presets'];
                    $contactPresetsArray = $pageObject->$presetFunction();
                    foreach ($contactPresetsArray as $contactId => $description) {
                        $contactPresets .= "<option" . ($contactId == $this->iColumnMetadata['initial_value'] ? " selected='selected'" : "") . " value='" . $contactId . "'>" . $description . "</option>";
                    }
                }
                if (!empty($this->iColumnMetadata['initial_value']) && !array_key_exists($this->iColumnMetadata['initial_value'], $contactPresetsArray)) {
                    $contactPresets .= "<option selected='selected' value='" . $this->iColumnMetadata['initial_value'] . "'>" . getDisplayName($this->iColumnMetadata['initial_value']) . "</option>";
                }
                if ($this->iColumnMetadata['readonly']) {
                    $classes[] = "disabled-select";
                    $tabindex = "";
                }
                $controlElement = ($this->iColumnMetadata['show_id_field'] ? "<input $attributes $styleSettings " . $tabindex . ($this->iColumnMetadata['readonly'] ? " readonly='readonly'" : "") .
                        " class='contact-picker-value %classString%' type='text' value='" . htmlText($this->iColumnMetadata['initial_value']) .
                        "' size='8' maxlength='10' name='" . $this->iColumnMetadata['column_name'] . "' id='" .
                        $this->iColumnMetadata['column_name'] . "' " . $dataSettings . " />" : "<input type='hidden' class='contact-picker-value' value='" . htmlText($this->iColumnMetadata['initial_value']) .
                        "' name='" . $this->iColumnMetadata['column_name'] . "' id='" . $this->iColumnMetadata['column_name'] . "' " . $dataSettings . " />") .
                    ($this->iColumnMetadata['hide_selector'] ? "" : "<select " . ($this->iColumnMetadata['show_id_field'] ? "" : $attributes . " " . $styleSettings . " ") . $tabindex . " class='contact-picker-selector field-text %classString%' data-column_name='" .
                        $this->iColumnMetadata['column_name'] . "' id='" . $this->iColumnMetadata['column_name'] .
                        "_selector' name='" . $this->iColumnMetadata['column_name'] . "_selector'" .
                        "><option value=''>" . getLanguageText("No contact selected. Click to choose") . " -></option>" . $contactPresets . "</select>") .
                    ($this->iColumnMetadata['readonly'] ? "" : "<button class='contact-picker" . ($this->iColumnMetadata['not_editable'] ? " not-editable" : "") . "' data-column_name='" .
                        $this->iColumnMetadata['column_name'] . "' " . $tabindex . "><span class='fad fa-search'></span></button>") .
                    "<button class='contact-picker-open-contact' data-field_name='" . $this->iColumnMetadata['column_name'] . "'><span class='fad fa-folder-open'></span></button>";
                break;
            case "user_picker":
                $classes[] = "user-picker-field";
                $userPresets = "";
                $userPresetsArray = array();
                if ($this->iColumnMetadata['user_presets'] && method_exists($pageObject, $this->iColumnMetadata['user_presets'])) {
                    $presetFunction = $this->iColumnMetadata['user_presets'];
                    $userPresetsArray = $pageObject->$presetFunction();
                    foreach ($userPresetsArray as $userId => $description) {
                        $userPresets .= "<option" . ($userId == $this->iColumnMetadata['initial_value'] ? " selected='selected'" : "") . " value='" . $userId . "'>" . $description . "</option>";
                    }
                }
                if (!empty($this->iColumnMetadata['initial_value']) && !array_key_exists($this->iColumnMetadata['initial_value'], $userPresetsArray)) {
                    $userPresets .= "<option selected='selected' value='" . $this->iColumnMetadata['initial_value'] . "'>" . getUserDisplayName($this->iColumnMetadata['initial_value']) . "</option>";
                }
                if ($this->iColumnMetadata['readonly']) {
                    $classes[] = "disabled-select";
                    $tabindex = "";
                }
                $controlElement = ($this->iColumnMetadata['show_id_field'] ? "<input $attributes $styleSettings " . $tabindex . ($this->iColumnMetadata['readonly'] ? " readonly='readonly'" : "") .
                        " class='user-picker-value %classString%' type='text' value='" . htmlText($this->iColumnMetadata['initial_value']) .
                        "' size='8' maxlength='10' name='" . $this->iColumnMetadata['column_name'] . "' id='" .
                        $this->iColumnMetadata['column_name'] . "' " . $dataSettings . " />" : "<input type='hidden' class='user-picker-value' value='" . htmlText($this->iColumnMetadata['initial_value']) .
                        "' name='" . $this->iColumnMetadata['column_name'] . "' id='" . $this->iColumnMetadata['column_name'] . "' " . $dataSettings . " />") .
                    ($this->iColumnMetadata['hide_selector'] ? "" : "<select " . ($this->iColumnMetadata['show_id_field'] ? "" : $attributes . " " . $styleSettings . " ") . $tabindex . " class='user-picker-selector field-text %classString%' data-column_name='" .
                        $this->iColumnMetadata['column_name'] . "' id='" . $this->iColumnMetadata['column_name'] .
                        "_selector' name='" . $this->iColumnMetadata['column_name'] . "_selector'" .
                        "><option value=''>" . getLanguageText("No user selected. Click to choose") . " -></option>" . $userPresets . "</select>") .
                    ($this->iColumnMetadata['readonly'] ? "" : "<button class='user-picker" . ($this->iColumnMetadata['not_editable'] ? " not-editable" : "") . "' data-column_name='" .
                        $this->iColumnMetadata['column_name'] . "' " . $tabindex . "><span class='fa fa-search'></span></button>");
                break;
            case "file":
                $controlElement = ($this->iColumnMetadata['readonly'] ? "" : "<input $attributes $styleSettings class='%classString%' type='file' data-conditional-required='" .
                        (empty($this->iColumnMetadata['data-conditional-required']) ? "$(\"#" . $this->iColumnMetadata['column_name'] .
                            "\").val() == \"\"" : $this->iColumnMetadata['data-conditional-required']) . "' name='" . $this->iColumnMetadata['column_name'] .
                        "_file' id='" . $this->iColumnMetadata['column_name'] . "_file' " . $tabindex . " />") . ($this->iColumnMetadata['no_download'] ? "" : "&nbsp;<span class='file-info'><a href='' class='download-file-link' id='" . $this->iColumnMetadata['column_name'] .
                        "_download'>" . getLanguageText("Download") . " <span id='" . $this->iColumnMetadata['column_name'] . "_filename'></span></a>") . ($this->iColumnMetadata['readonly'] || $this->iColumnMetadata['not_null'] || $this->iColumnMetadata['no_remove'] ? "" : "&nbsp;<input " . $tabindex . " type='checkbox' name='remove_" . $this->iColumnMetadata['column_name'] . "' id='remove_" .
                        $this->iColumnMetadata['column_name'] . "' value='1' /><label class='checkbox-label' for='remove_" . $this->iColumnMetadata['column_name'] .
                        "'>" . getLanguageText("Remove") . "</label>") . "<input type='hidden' id='" . $this->iColumnMetadata['column_name'] .
                    "' name='" . $this->iColumnMetadata['column_name'] . "' value='' /></span>";
                break;
            case "longblob":
                $controlElement = ($this->iColumnMetadata['readonly'] ? "" : "<input $attributes $styleSettings class='%classString%' type='file' data-conditional-required='$(\"#" . $this->iColumnMetadata['column_name'] .
                        "\").val() == \"\"' name='" . $this->iColumnMetadata['column_name'] .
                        "_file' id='" . $this->iColumnMetadata['column_name'] . "_file' " . $tabindex . " />") . "<span class='file-info'>&nbsp;" .
                    ($this->iColumnMetadata['subtype'] == "file" ? "<a href='' class='view-image-link' id='" . $this->iColumnMetadata['column_name'] .
                        "_download'>" . getLanguageText("Download") . "</a>" : "<a href='' class='pretty-photo download-file-link' id='" . $this->iColumnMetadata['column_name'] .
                        "_view'>" . getLanguageText("View") . " <span id='" . $this->iColumnMetadata['column_name'] . "_filename'></span></a>") . ($this->iColumnMetadata['readonly'] || $this->iColumnMetadata['not_null'] ? "" : "&nbsp;<input " . $tabindex . " type='checkbox' name='remove_" . $this->iColumnMetadata['column_name'] . "' id='remove_" .
                        $this->iColumnMetadata['column_name'] . "' value='1' /><label class='checkbox-label' for='remove_" . $this->iColumnMetadata['column_name'] .
                        "'>" . getLanguageText("Remove") . "</label>") . "<input type='hidden' id='" . $this->iColumnMetadata['column_name'] .
                    "' name='" . $this->iColumnMetadata['column_name'] . "' value='' /></span>";
                break;
            case "html":
            case "text":
            case "mediumtext":
                if (!empty($this->iColumnMetadata['data_format'])) {
                    $validationClasses[] = "custom[" . $this->iColumnMetadata['data_format'] . "]";
                    $classes[] = "data-format-" . $this->iColumnMetadata['data_format'];
                }
                if (array_key_exists("wysiwyg", $this->iColumnMetadata) && $this->iColumnMetadata['wysiwyg'] && !$this->iColumnMetadata['readonly'] && !$this->iColumnMetadata['no_editor']) {
                    $classes[] = "data-format-HTML";
                }
                $maxlength = $this->iColumnMetadata['maximum_length'];
                $controlElement = "<textarea $attributes $styleSettings " . $tabindex . ($this->iColumnMetadata['readonly'] ? " readonly='readonly'" : "") . (empty($maxlength) ? "" : " maxlength='" . $maxlength . "'") .
                    " class='%classString%' " . $dataSettings . (empty($this->iColumnMetadata['placeholder']) ? "" : " placeholder='" . $this->iColumnMetadata['placeholder'] . "'") .
                    " name='" . $this->iColumnMetadata['column_name'] . "' id='" .
                    $this->iColumnMetadata['column_name'] . "'>" . htmlText($this->iColumnMetadata['initial_value']) . "</textarea>\n";
                if (($this->iColumnMetadata['data_type'] == "html" || (array_key_exists("wysiwyg", $this->iColumnMetadata) && $this->iColumnMetadata['wysiwyg'])) && !$this->iColumnMetadata['readonly']) {
                    $controlElement = "<div class='textarea-wrapper'>" . $controlElement . "<div class='content-builder' data-id='" . $this->iColumnMetadata['column_name'] . "'" .
                        (array_key_exists("builder_source", $this->iColumnMetadata) ? "data-builder='" . $this->iColumnMetadata['builder_source'] . "'" : "") . "></div></div>\n";
                } else if (($this->iColumnMetadata['data_type'] == "html" || (array_key_exists("builder", $this->iColumnMetadata) && $this->iColumnMetadata['builder'])) && !$this->iColumnMetadata['readonly']) {
                    $controlElement = "<div class='textarea-wrapper'>" . $controlElement . "<div class='content-builder' data-id='" . $this->iColumnMetadata['column_name'] . "'" .
                        (array_key_exists("builder_source", $this->iColumnMetadata) ? "data-builder='" . $this->iColumnMetadata['builder_source'] . "'" : "") . "></div></div>\n";
                }
                break;
            case "autocomplete":
                if (array_key_exists("size", $this->iColumnMetadata)) {
                    $size = $this->iColumnMetadata['size'];
                } else {
                    $size = "50";
                }
                $controlElement = "<input class='" . implode(" ", $classes) . "' type='hidden' id='" . $this->iColumnMetadata['column_name'] . "' name='" .
                    $this->iColumnMetadata['column_name'] . "' value='" . htmlText($this->iColumnMetadata['initial_value']) .
                    "'><input autocomplete='chrome-off' autocomplete='off' $attributes $styleSettings " . $tabindex . ($this->iColumnMetadata['readonly'] ? " readonly='readonly'" : "") .
                    " class='%classString%' type='text' " . (empty($size) ? "" : "size='$size'") .
                    " name='" . $this->iColumnMetadata['column_name'] . "_autocomplete_text' id='" . $this->iColumnMetadata['column_name'] . "_autocomplete_text' " . $dataSettings .
                    (empty($this->iColumnMetadata['placeholder']) ? "" : " placeholder='" . $this->iColumnMetadata['placeholder'] . "'") . " />";
                $classes[] = "autocomplete-field";
                break;
            case "select":
                if (!empty($this->iColumnMetadata['get_choices']) && !method_exists($GLOBALS['gPageObject'],"customGetControlRecords")) {
                    $addNewInfo = array();
                } else {
                    $addNewInfo = $GLOBALS['gPrimaryDatabase']->getAddNewInfo($this->iReferencedTableName);
                }
                $addNewOption = (!empty($addNewInfo) && !empty($addNewInfo['table_name']) && empty($this->iColumnMetadata['remove_add_new']) && empty(getPreference("NO_ADD_NEW_OPTION")));
                if ($addNewOption) {
                    $classes[] = "add-new-option";
                }
                if ($this->iColumnMetadata['readonly']) {
                    $classes[] = "disabled-select";
                    $tabindex = "";
                }
                $controlElement = "<select " . ($addNewOption ? "data-link_url='" . $addNewInfo['link_url'] . "' data-control_code='" . $addNewInfo['table_name'] . "' " : "") .
	                $attributes . " " . $styleSettings . $tabindex . " class='%classString%' name='" . $this->iColumnMetadata['column_name'] . "' id='" .
                    $this->iColumnMetadata['column_name'] . "' " . $dataSettings . ">\n";
                $choices = $this->getChoices($pageObject, (array_key_exists("initial_value", $this->iColumnMetadata)));
                if (!isset($choices['']) && !$this->iColumnMetadata['no_empty_option']) {
                    $emptyText = (array_key_exists("empty_text", $this->iColumnMetadata) ? $this->iColumnMetadata['empty_text'] : "[" . ($this->iColumnMetadata['not_null'] ? getLanguageText("Select") : getLanguageText("None")) . "]");
                    $controlElement .= "<option value=''>" . $emptyText . "</option>\n";
                }
                if ($addNewOption) {
                    $controlElement .= "<option value='-9999'>[Add New]</option>\n";
                }
                $optgroup = "";
                foreach ($choices as $index => $controlInfo) {
                    if (is_array($controlInfo)) {
                        if (!empty($controlInfo['optgroup'])) {
                            if ($optgroup != $controlInfo['optgroup']) {
                                if (!empty($optgroup)) {
                                    $controlElement .= "</optgroup>\n";
                                }
                                $optgroup = $controlInfo['optgroup'];
                                $controlElement .= "<optgroup label='" . $optgroup . "'>\n";
                            }
                        } else {
                            if (!empty($optgroup)) {
                                $controlElement .= "</optgroup>\n";
                            }
                            $optgroup = "";
                        }
                        $description = $controlInfo['description'];
                        $keyValue = $controlInfo['key_value'];
                        $inactive = $controlInfo['inactive'];
                    } else {
                        $description = $controlInfo;
                        $keyValue = $index;
                        $inactive = false;
                    }
                    $dataValues = "";
                    foreach ($controlInfo as $controlInfoKey => $controlInfoData) {
                        if (substr($controlInfoKey, 0, 5) == "data-") {
                            $dataValues .= $controlInfoKey . "='" . $controlInfoData . "' ";
                        }
                    }
                    if (!empty($keyValue) || !empty($description)) {
                        if (!$inactive || $keyValue == $this->iColumnMetadata['initial_value']) {
                            $controlElement .= "<option " . ($inactive ? "data-inactive='1' " : "") . $dataValues .
                                (is_scalar($this->iColumnMetadata['initial_value']) && strlen($this->iColumnMetadata['initial_value']) > 0 && $keyValue == $this->iColumnMetadata['initial_value'] ? "selected " : "") .
                                "value='" . $keyValue . "'>" . htmlText($description) . ($inactive ? " (" . getLanguageText("Inactive") . ")" : "") . "</option>\n";
                        }
                    }
                }
                if (!empty($optgroup)) {
                    $controlElement .= "</optgroup>\n";
                }
                $controlElement .= "</select>\n";
                break;
            case "radio":
                if ($this->iColumnMetadata['mysql_type'] == "tinyint") {
                    if (!empty($this->iColumnMetadata['yes_description'])) {
                        $yesChoice = $this->iColumnMetadata['yes_description'];
                    } else {
                        $yesChoice = "Yes";
                    }
                    if (!empty($this->iColumnMetadata['no_description'])) {
                        $noChoice = $this->iColumnMetadata['no_description'];
                    } else {
                        $noChoice = "No";
                    }
                    if ($this->iColumnMetadata['no_first']) {
                        $choices = array(array("key_value" => "0", "description" => $noChoice), array("key_value" => "1", "description" => $yesChoice));
                    } else {
                        $choices = array(array("key_value" => "1", "description" => $yesChoice), array("key_value" => "0", "description" => $noChoice));
                    }
                } else {
                    $choices = $this->getChoices($pageObject);
                }
                $radioIndex = 0;
                foreach ($choices as $index => $controlInfo) {
                    if (is_array($controlInfo)) {
                        $description = $controlInfo['description'];
                        $keyValue = $controlInfo['key_value'];
                        $inactive = $controlInfo['inactive'];
                    } else {
                        $description = $controlInfo;
                        $keyValue = $index;
                        $inactive = false;
                    }
                    if (!empty($description)) {
                        $radioIndex++;
                        $controlElement .= "<input $attributes $styleSettings type='radio' " . $tabindex . ($this->iColumnMetadata['readonly'] ? " disabled='disabled'" : "") .
                            " class='%classString%' name='" . $this->iColumnMetadata['column_name'] . "' id='" . $this->iColumnMetadata['column_name'] . "_" .
                            $radioIndex . "' value='" . htmlText($keyValue) . "'" . ($keyValue == $this->iColumnMetadata['initial_value'] ? " checked" : "") .
                            " /><label class='checkbox-label' for='" .
                            $this->iColumnMetadata['column_name'] . "_" . $radioIndex . "'>" .
                            htmlText($description) . "</label>" . ($radioIndex < count($choices) ? "<br/>" : "") . "\n";
                    }
                }
                break;
            case "password":
                $passwordField = true;
            case "varchar":
                $maxlength = $this->iColumnMetadata['maximum_length'];
                if (empty($maxlength)) {
                    $maxlength = 255;
                }
                $maximumFieldSize = getPreference("MAINTENANCE_MAXIMUM_FIELD_SIZE");
                if (empty($maximumFieldSize)) {
                    $maximumFieldSize = 50;
                }
                if (array_key_exists("size", $this->iColumnMetadata)) {
                    $size = $this->iColumnMetadata['size'];
                } else {
                    $size = min($maxlength + 1, $maximumFieldSize);
                    if (empty($size)) {
                        $size = $maximumFieldSize;
                    }
                }
                if (!empty($this->iColumnMetadata['data_format'])) {
                    $validationClasses[] = "custom[" . $this->iColumnMetadata['data_format'] . "]";
                    $classes[] = "data-format-" . $this->iColumnMetadata['data_format'];
                }
                $controlElement = "<input autocomplete='chrome-off' autocomplete='off' $attributes $styleSettings" . ($passwordField ? " type='password'" : " type='text'") . " " . $tabindex .
                    ($this->iColumnMetadata['readonly'] ? " readonly='readonly'" : "") . " class='" .
                    ($this->iColumnMetadata['password_strength'] ? "password-strength " : "") . "%classString%' value='" .
                    htmlText($this->iColumnMetadata['initial_value']) . "' " . (empty($size) ? "" : "size='$size'") .
                    (empty($maxlength) ? "" : " maxlength='$maxlength'") . " name='" . $this->iColumnMetadata['column_name'] .
                    "' id='" . $this->iColumnMetadata['column_name'] . "' " . $dataSettings .
                    (empty($this->iColumnMetadata['placeholder']) ? "" : " placeholder='" . $this->iColumnMetadata['placeholder'] . "'") . " />" .
	                ($passwordField && !empty($this->iColumnMetadata['show_password']) ? "<span class='show-password fad fa-eye'></span>" : "");
                if ($passwordField && $this->iColumnMetadata['password_strength']) {
                    $controlElement .= "<div class='strength-bar-div hidden' id='" . $this->iColumnMetadata['column_name'] . "_strength_bar_div'>" .
                        "<p class='strength-bar-label' id='" . $this->iColumnMetadata['column_name'] . "_strength_bar_label'></p>" .
                        "<div class='strength-bar' id='" . $this->iColumnMetadata['column_name'] . "_strength_bar'>" .
                        "</div></div>";
                }
                break;
            case "signature":
	            $classes[] = "signature-field";
                $controlElement = "<input type='hidden' name='" . $this->iColumnMetadata['column_name'] . "' data-required='" . (empty($this->iColumnMetadata['not_null']) ? "" : "1") .
                    "' id='" . $this->iColumnMetadata['column_name'] . "' class='%classString%'><div class='signature-palette-parent' id='_signature_palette_parent'><div id='" . $this->iColumnMetadata['column_name'] . "_palette' " . $attributes . " " . $styleSettings .
                    " " . $tabindex . " class='signature-palette' data-column_name='" . $this->iColumnMetadata['column_name'] . "'></div></div>";
                break;
            case "hidden":
                $controlElement = "<input $attributes $styleSettings" . " " . $tabindex .
                    " class='%classString%' type='hidden' value='" . htmlText($this->iColumnMetadata['initial_value']) . "' " .
                    "name='" . $this->iColumnMetadata['column_name'] . "' id='" . $this->iColumnMetadata['column_name'] . "' " . $dataSettings . " />";
                break;
            case "date":
                $validationClasses[] = "custom[date]";
                if (!$this->iColumnMetadata['readonly'] && !$this->iColumnMetadata['no_datepicker']) {
                    $classes[] = "datepicker";
                }
                if (array_key_exists("size", $this->iColumnMetadata)) {
                    $size = $this->iColumnMetadata['size'];
                } else {
                    $size = "10";
                }
                $controlElement = "<input $attributes $styleSettings " . $tabindex . ($this->iColumnMetadata['readonly'] ? " readonly='readonly'" : "") .
                    " class='%classString%' type='text' value='" . (empty($this->iColumnMetadata['initial_value']) ? "" : date((empty($this->iColumnMetadata['date_format']) ? "m/d/Y" : $this->iColumnMetadata['date_format']), strtotime($this->iColumnMetadata['initial_value']))) .
                    "' size='" . $size . "' maxlength='10' name='" . $this->iColumnMetadata['column_name'] . "' id='" . $this->iColumnMetadata['column_name'] . "' " . $dataSettings . " />";
                break;
            case "time":
                $validationClasses[] = "custom[time]";
                if (!$this->iColumnMetadata['readonly'] && !$this->iColumnMetadata['no_timepicker']) {
                    $classes[] = "timepicker";
                }
                if (array_key_exists("size", $this->iColumnMetadata)) {
                    $size = $this->iColumnMetadata['size'];
                } else {
                    $size = "10";
                }
                $controlElement = "<input $attributes $styleSettings " . $tabindex . ($this->iColumnMetadata['readonly'] ? " readonly='readonly'" : "") .
                    " class='%classString%' type='text' value='" . (empty($this->iColumnMetadata['initial_value']) ? "" : date("g:ia", strtotime($this->iColumnMetadata['initial_value']))) .
                    "' size='" . $size . "' maxlength='10' name='" . $this->iColumnMetadata['column_name'] . "' id='" . $this->iColumnMetadata['column_name'] . "' " . $dataSettings . " />";
                break;
            case "int":
            case "integer":
                $validationClasses[] = "custom[integer]";
                $classes[] = "align-right";
                $controlElement = "<input $attributes $styleSettings " . $tabindex . ($this->iColumnMetadata['readonly'] ? " readonly='readonly'" : "") . " class='%classString%' type='text' value='" . htmlText($this->iColumnMetadata['initial_value']) . "' size='10' name='" . $this->iColumnMetadata['column_name'] . "' id='" . $this->iColumnMetadata['column_name'] . "' " . $dataSettings . " />";
                break;
            case "decimal":
                $validationClasses[] = "custom[number]";
                $classes[] = "align-right";
                if (array_key_exists("size", $this->iColumnMetadata)) {
                    $size = $this->iColumnMetadata['size'];
                } else {
                    $size = $this->iColumnMetadata['maximum_length'];
                }
                if (empty($size) && !empty($this->iColumnMetadata['data_size'])) {
                    $size = $this->iColumnMetadata['data_size'] + 2;
                }
                if (empty($size)) {
                    $size = 14;
                }
                if ($size < 8) {
                    $size++;
                }
                $controlElement = "<input $attributes $styleSettings " . $tabindex . (!empty($this->iColumnMetadata['decimal_places']) ? " data-decimal-places='" . $this->iColumnMetadata['decimal_places'] . "'" : "") . ($this->iColumnMetadata['readonly'] ? " readonly='readonly'" : "") . " class='%classString%' type='text' value='" . htmlText($this->iColumnMetadata['initial_value']) . "' size='" . $size . "' name='" . $this->iColumnMetadata['column_name'] . "' id='" . $this->iColumnMetadata['column_name'] . "' " . $dataSettings . " />";
                break;
            case "tinyint":
                $controlElement = "<input type='hidden' name='" . $this->iColumnMetadata['column_name'] . "' value='0' " . $dataSettings . " />" .
                    "<input $attributes $styleSettings " . $tabindex . ($this->iColumnMetadata['readonly'] ? " disabled='disabled' " : "") . $dataSettings . " class='%classString%' type='checkbox' value='1' name='" . $this->iColumnMetadata['column_name'] . "' id='" . $this->iColumnMetadata['column_name'] . "'" . (empty($this->iColumnMetadata['initial_value']) ? "" : " checked") . " />" .
                    ($this->iColumnMetadata['form_label'] && !$this->iColumnMetadata['normal_label'] ? "<label class='checkbox-label' for='" . $this->iColumnMetadata['column_name'] . "'>" . $this->iColumnMetadata['form_label'] . "</label>" : "");
                break;
	        case "checkbox_choices":
		        $choices = $this->getChoices($pageObject, (array_key_exists("initial_value", $this->iColumnMetadata)));
				$controlElement = "";
				foreach ($choices as $thisChoice) {
					$controlElement .= (empty($controlElement) ? "" : "<br>") . "<input type='hidden' name='" . $this->iColumnMetadata['column_name'] . "-" . $thisChoice['key_value'] . "' value='0' " . $dataSettings . " />" .
						"<input $attributes $styleSettings " . $tabindex . ($this->iColumnMetadata['readonly'] ? " disabled='disabled' " : "") . $dataSettings . " class='%classString%' type='checkbox' value='1' name='" . $this->iColumnMetadata['column_name'] . "-" . $thisChoice['key_value'] . "' id='" . $this->iColumnMetadata['column_name'] . "-" . $thisChoice['key_value'] . "'" . (empty($this->iColumnMetadata['initial_value']) ? "" : " checked") . " />" .
						($thisChoice['description'] && !$this->iColumnMetadata['normal_label'] ? "<label class='checkbox-label' for='" . $this->iColumnMetadata['column_name'] . "-" . $thisChoice['key_value'] . "'>" . $thisChoice['description'] . "</label>" : "");
				}
		        break;
            case "span":
            case "literal":
                $initialValue = $this->iColumnMetadata['initial_value_display'];
                if (empty($initialValue)) {
                    $initialValue = $this->iColumnMetadata['initial_value'];
                }
                $controlElement = "<div class='%classString%' id='" . $this->iColumnMetadata['column_name'] . "'>" . $initialValue . "</div>";
                break;
        }
        if (!in_array($this->iColumnMetadata['data_type'], array("html", "span", "literal"))) {
            $validationClassString = implode(",", $validationClasses);
            if ((!empty($validationClassString) || $validationNeeded) && (!$this->iColumnMetadata['readonly'] || $this->iColumnMetadata['force_validation_classes'])) {
                $validationClassString = "validate[" . $validationClassString . "]";
                $classes[] = $validationClassString;
            }
        }
        $classString = implode(" ", $classes);
        $controlElement = str_replace("%classString%", $classString, $controlElement);
        return $controlElement;
    }

    /**
     * getChoices
     *
     * @param pageObject - the page calling which could contain the get_choices method
     * @param includeInactive - flag indicating whether to include inactive records
     * @return array
     */
    function getChoices($pageObject, $includeInactive = false) {
        if (!self::$iAllChoices) {
            self::$iAllChoices = array();
        }
        if (array_key_exists($this->iColumnName, self::$iAllChoices) && empty($this->iColumnMetadata['get_choices']) && empty($this->iColumnMetadata['filter_where'])) {
            return self::$iAllChoices[$this->iColumnName];
        }
        $choices = $this->iColumnMetadata['choices'];
	 	if (!is_array($choices) && substr($choices, 0, 1) == "{" && substr($choices, -1) == "}") {
			$choices = json_decode($choices, true);
		}
        if (!is_array($choices) && empty($choices)) {
			if (!empty($this->iColumnMetadata['get_choices'])) {
				$choiceFunction = $this->iColumnMetadata['get_choices'];
				if ($pageObject && method_exists($pageObject, $choiceFunction)) {
					$choices = $pageObject->$choiceFunction($includeInactive);
				} else if (function_exists($this->iColumnMetadata['get_choices'])) {
					$choices = $choiceFunction($includeInactive);
				}
			} else if (!empty($this->iReferencedTableName)) {
				$parameters = array("table_name" => $this->iReferencedTableName, "description_field" => (empty($this->iColumnMetadata['control_description_field']) ? $this->iReferencedDescriptionColumns[0] : $this->iColumnMetadata['control_description_field']),
					"show_inactive" => $includeInactive);
				if (!empty($this->iColumnMetadata['filter_where'])) {
					$parameters['where_statement'] = $this->iColumnMetadata['filter_where'];
				}
				if (array_key_exists("include_default_client", $this->iColumnMetadata)) {
					$parameters['include_default_client'] = $this->iColumnMetadata['include_default_client'];
				}
				$choices = $this->iDatabase->getControlRecords($parameters);
            } else {
                $choices = array();

                # get all IDs & descriptions for the current table

                if (!empty($this->iTableName)) {
                    $dataTable = new DataTable($this->iTableName);
                    if ($this->iColumnName == $dataTable->getPrimaryKey()) {
                        $choices = $this->iDatabase->getControlRecords(array("table_name" => $this->iTableName));
                    }
                }
            }
        }
        if (!is_array($choices)) {
            $choices = getContentLines($choices);
        }
        foreach ($choices as $thisKey => $thisChoice) {
            if (!is_array($thisChoice)) {
                $choices[$thisKey] = array("key_value" => ($this->iColumnMetadata['use_description'] ? $thisChoice : $thisKey), "description" => $thisChoice, "inactive" => false);
            }
        }
        $this->iColumnMetadata['choices'] = $choices;
        self::$iAllChoices[$this->iColumnName] = $choices;
        return $choices;
    }

    /**
     * setData - set the data associated with this column
     *
     * @param value of the data
     */
    function setData($value) {
        switch ($this->iColumnMetadata['data_type']) {
            case "date":
                $this->iDataValue = (empty($value) ? "" : date("Y-m-d", strtotime($value)));
                break;
            case "time":
                $this->iDataValue = (empty($value) ? "" : date("H:i:s", strtotime($value)));
                break;
            case "datetime":
                $this->iDataValue = (empty($value) ? "" : date("Y-m-d H:i:s", strtotime($value)));
                break;
            case "tinyint":
                $this->iDataValue = (empty($value) ? 0 : 1);
                break;
            case "int":
            case "integer":
            case "decimal":
                if (is_numeric($value)) {
                    $this->iDataValue = $value;
                } else {
                    $this->iDataValue = "";
                }
                break;
            default:
                $this->iDataValue = $value;
                break;
        }
    }

    /**
     * getData - get the data associated with this column
     *
     * @return the data value of this column
     */
    function getData() {
        return $this->iDataValue;
    }

    /**
     * isForeignKey
     *
     * @return true or false whether the column is a foreign key or not
     */
    function isForeignKey() {
        return $this->iColumnMetadata['foreign_key'];
    }

    function setReferencedColumn($tableName, $columnName, $descriptionFields) {
        $this->iReferencedTableName = $tableName;
        $this->iReferencedColumnName = $columnName;
        $this->iReferencedDescriptionColumns = $descriptionFields;
    }

    /**
     * getReferencedTable
     *
     * @return If the column is a foreign key, return the name of the table it references
     */
    function getReferencedTable() {
        return $this->iReferencedTableName;
    }

    /**
     * getReferencedColumn
     *
     * @return If the column is a foreign key, return the name of the column it references
     */
    function getReferencedColumn() {
        return $this->iReferencedColumnName;
    }

    /**
     * getReferencedDescriptionColumns
     *
     * @return If the column is a foreign key, return the name of the description column(s) used in the referenced table
     */
    function getReferencedDescriptionColumns() {
        return $this->iReferencedDescriptionColumns;
    }

    /**
     * getName
     *
     * @return the simple name of the column
     */
    function getName() {
        return $this->iColumnName;
    }

    /**
     * getFullName
     *
     * @return The full name of the column, including the name of the table
     */
    function getFullName() {
        return (empty($this->iTableName) ? "" : $this->iTableName . ".") . $this->iColumnName;
    }

    /**
     * setControlValue - set a control value for this column
     *
     * @param
     *    controlName - the name of the control
     *    controlValue - the value of the control
     */
    function setControlValue($controlName, $controlValue) {
        if ($controlName == "choices") {
            self::$iAllChoices = false;
        }
        if (is_array($controlName)) {
            foreach ($controlName as $fieldName => $fieldValue) {
                $this->iColumnMetadata[$fieldName] = DataSource::massageControlValue($controlName, $fieldValue);
            }
        } else {
            $this->iColumnMetadata[$controlName] = DataSource::massageControlValue($controlName, $controlValue);
        }
    }

    /**
     * @param $controlName
     * @return mixed
     */
    function getControlValue($controlName) {
        return (array_key_exists($controlName, $this->iColumnMetadata) ? $this->iColumnMetadata[$controlName] : false);
    }

    /**
     * controlValueExists
     *
     * @param This name of the control
     * @return whether the control value exists
     */
    function controlValueExists($controlName) {
        return array_key_exists($controlName, $this->iColumnMetadata);
    }

    /**
     * getAllControlValues
     *
     * @return An array of all the control values
     */
    function getAllControlValues() {
        return $this->iColumnMetadata;
    }

    /**
     * getDatabase
     *
     * @return The database used for this column
     */
    function getDatabase() {
        return $this->iDatabase;
    }
}
