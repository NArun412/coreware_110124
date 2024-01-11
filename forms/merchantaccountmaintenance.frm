<div id="_maintenance_form">

%if:!$GLOBALS['gUserRow']['administrator_flag']%
%field:federal_firearms_licensee_id%
%basic_form_line%
%endif%

%field:merchant_account_code,description,merchant_service_id,account_login,account_key,merchant_identifier,link_url,sort_order,ach_merchant_account,no_customer_database,internal_use_only,inactive%
%basic_form_line%
%end repeat%

<div id="custom_data">
%method:addCustomFields%
</div>

</div> <!-- maintenance_form -->
