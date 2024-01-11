<div id="_maintenance_form">

%field:designation_code,description%
%basic_form_line%
%end repeat%

<p>The following two fields allow a fund account to be used for the remaining amount after all other funds are deducted. Both fields must have a value or it will be ignored.</p>

%field:remainder_fund_account_id,remainder_amount%
%basic_form_line%
%end repeat%

<p>Define the amount that is deducted each payroll for these funds. It can be a fixed amount plus a percent. Changes made just before the payroll date may not take effect until the next payroll.</p>

%method:fundDeductions%

</div> <!-- maintenance_form -->
