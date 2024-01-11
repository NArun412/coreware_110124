<div id="_maintenance_form">

%field:designation_description,created_by,date_created,log_date,expiration_date,amount,notes%
%basic_form_line%
%end repeat%

<div class='divider'></div>
<h2>Add Use</h2>

%field:date_used,used_amount,used_notes%
%basic_form_line%
%end repeat%

<div class='divider'></div>

%field:expense_uses%
%basic_form_line%

</div> <!-- maintenance_form -->
