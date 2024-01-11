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

/*
%module:contact_list:contact_type_code=DEALER:wrapper_element_id=element_id%

Options:
select_limit=20 - limit to this number of contacts. default is 20
wrapper_element_id=XXXXX - default is _contact_list_ + random string
fragment_code=XXXXXX - fragment to use for the contact results. see below for default html
*/

class ContactListPageModule extends PageModule {
    function createContent() {
        $selectLimit = $this->iParameters['select_limit'];
        if (empty($selectLimit)) {
            $selectLimit = 20;
        }
        $contactTypeId = getFieldFromId("contact_type_id","contact_types","contact_type_code", $this->iParameters['contact_type_code']);
        $resultSet = executeQuery("select * from contacts where deleted = 0 and contact_type_id = ? limit ?", $contactTypeId, $selectLimit);

        $wrapperElementId = $this->iParameters['wrapper_element_id'] ?: "_contact_list_" . getRandomString(12);

        $contactDetails = getFragment($this->iParameters['fragment_code']);
        if (empty($contactDetails)) {
            ob_start();
            ?>
            <div class="contact-details" id="contact_%contact_id%">
                %if_has_value:image_filename%
                <img src="%image_filename%" loading="lazy">
                %endif%
                <span class="email-address %hidden_if_empty:email_address%">%email_address%</span>
                <span class="display-name %hidden_if_empty:display_name%">%display_name%</span>
                <span class="job-title %hidden_if_empty:job_title%">%job_title%</span>
                <span class="web-page %hidden_if_empty:web_page%">%web_page%</span>
                <span class="phone-number %hidden_if_empty:phone_number%">%phone_number%</span>
                <div class="notes %hidden_if_empty:notes%">%notes%</div>
            </div>
            <?php
            $contactDetails = ob_get_clean();
        }
        ?>
        <div id="<?= $wrapperElementId ?>">
            <?php
            while ($row = getNextRow($resultSet)) {
                $substitutions = array();
                $substitutions['contact_id'] = $row['contact_id'];
                $substitutions['image_filename'] = getImageFilename($row['image_id']);
                $substitutions['email_address'] = $row['email_address'];
                $substitutions['display_name'] = getDisplayName($row['contact_id']);
                $substitutions['job_title'] = $row['job_title'];
                $substitutions['web_page'] = $row['web_page'];
                $substitutions['phone_number'] = Contact::getContactPhoneNumber($row['contact_id']);
                $substitutions['notes'] = $row['notes'];
                echo PlaceHolders::massageContent($contactDetails, $substitutions);
            }
            ?>
        </div>
        <?php
    }
}
