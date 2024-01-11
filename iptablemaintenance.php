<?php

/*      This software is the unpublished, confidential, proprietary, intellectual
        property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
        or used in any manner without expressed written consent from Kim David Software, LLC.
        Kim David Software, LLC owns all rights to this work and intends to keep this
        software confidential so as to maintain its value as a trade secret.

        Copyright 2004-Present, Kim David Software, LLC.
*/

$GLOBALS['gPageCode'] = "IPTABLEMAINT";
require_once "shared/startup.inc";

class ThisPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "getcommands":
				$primaryId = getFieldFromId("ip_table_content_id", "ip_table_contents", "ip_table_content_id", $_GET['primary_id']);
				if (!empty($primaryId)) {
					ob_start();
					?>
                    iptables -F
                    iptables -A INPUT -p tcp --tcp-flags ALL NONE -j DROP
                    iptables -A INPUT -p tcp ! --syn -m state --state NEW -j DROP
                    iptables -A INPUT -p tcp --tcp-flags ALL ALL -j DROP
					<?php
					$resultSet = executeQuery("select * from ip_table_blocked where ip_table_content_id = ?", $primaryId);
					while ($row = getNextRow($resultSet)) {
						?>
                        iptables -A INPUT -s <?= $row['ip_address'] ?> -j DROP
						<?php
					}
					echo "\n";
					$resultSet = executeQuery("select * from ip_table_ports where ip_table_content_id = ? and accept_input = 1", $primaryId);
					while ($row = getNextRow($resultSet)) {
						?>
                        iptables -A INPUT -p tcp -m tcp --dport <?= $row['port_number'] ?> -j ACCEPT
						<?php
					}
					echo "\n";
					$resultSet = executeQuery("select * from ip_table_ports where ip_table_content_id = ? and accept_output = 1", $primaryId);
					while ($row = getNextRow($resultSet)) {
						?>
                        iptables -A OUTPUT -p tcp -m tcp --dport <?= $row['port_number'] ?> -j ACCEPT
						<?php
					}
					echo "\n";
					?>
                    iptables -A OUTPUT -p tcp -m state --state NEW,ESTABLISHED -j ACCEPT
                    iptables -A INPUT -i lo -j ACCEPT
                    iptables -A INPUT -p tcp -m state --state NEW -j DROP

                    service iptables save
                    service iptables restart
					<?php
					$returnArray['ip_table_commands'] = ob_get_clean();
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function massageDataSource() {
		$this->iDataSource->getPrimaryTable()->setSubtables(array("ip_table_ports", "ip_table_blocked"));
	}

	function supplementaryContent() {
		?>
        <div class="basic-form-line">
            <button id="create_commands">Create Commands</button>
        </div>
        <div class="basic-form-line">
            <label>Commands</label>
            <textarea id="ip_table_commands" name="ip_table_commands"></textarea>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#create_commands").click(function () {
                if (changesMade()) {
                    displayErrorMessage("Save Changes First");
                    return false;
                }
                $("#ip_table_commands").val("");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?url_action=getcommands&primary_id=" + $("#primary_id").val(), function(returnArray) {
                    if ("ip_table_commands" in returnArray) {
                        $("#ip_table_commands").val(returnArray['ip_table_commands']);
                    }
                });
                return false;
            });
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #ip_table_commands {
                height: 600px;
                width: 800px;
            }
        </style>
		<?php
	}
}

$pageObject = new ThisPage("ip_table_contents");
$pageObject->displayPage();
