<div id="_maintenance_form">

%field:email_credential_code, description, user_id, full_name, email_address%
%basic_form_line%
%end repeat%

    <p><button id="suggest_server_settings">Suggest Server Settings</button></p>

%field:smtp_host, smtp_port, security_setting, smtp_authentication_type, smtp_user_name, smtp_password, pop_host, pop_port, pop_security_setting, pop_user_name, pop_password%
%basic_form_line%
%end repeat%

</div> <!-- maintenance_form -->
