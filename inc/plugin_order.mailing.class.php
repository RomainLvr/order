<?php

/*----------------------------------------------------------------------
   GLPI - Gestionnaire Libre de Parc Informatique
   Copyright (C) 2003-2008 by the INDEPNET Development Team.

   http://indepnet.net/   http://glpi-project.org/
   ----------------------------------------------------------------------
   LICENSE

   This file is part of GLPI.

   GLPI is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation; either version 2 of the License, or
   (at your option) any later version.

   GLPI is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with GLPI; if not, write to the Free Software
   Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
   ----------------------------------------------------------------------*/
/*----------------------------------------------------------------------
    Original Author of file: Benjamin Fontan
    Purpose of file:
    ----------------------------------------------------------------------*/
class PluginOrderConfigMailing extends CommonDBTM {
	function __construct() {
		$this->table = "glpi_plugin_order_mailing";
	}

	function showMailingForm($target) {
		global $DB, $LANG, $CFG_GLPI;
		if (!haveRight("config", "w"))
			return false;

		echo "<form action=\"$target\" method=\"post\">";
		echo "<input type='hidden' name='ID' value='" . $CFG_GLPI["ID"] . "'>";

		$profiles = plugin_order_getMailingSenderList();

		echo "<div align='center'>";
		echo "<input type='hidden' name='update_notifications' value='1'>";
		// ADMIN
		echo "<table class='tab_cadre_fixe'>";
		echo "<tr><th colspan='3'>" . $LANG['plugin_order']['validation'][1] . "</th></tr>";
		echo "<tr class='tab_bg_2'>";
		plugin_order_showFormMailingType("ask", $profiles);
		echo "</tr>";

		echo "<tr><th colspan='3'>" . $LANG['plugin_order']['validation'][2] . "</th></tr>";
		echo "<tr class='tab_bg_2'>";
		plugin_order_showFormMailingType("validation", $profiles);
		echo "</tr>";

		echo "</table>";
		echo "</div>";

		echo "</form>";

	}
}

class PluginOrderMailing extends CommonDBTM {

	//! mailing type (contract,infocom,cartridge,consumable)
	var $orderID = 0;
	var $message = "";
	var $entity = "";

	/**
	 * Constructor
	 * @param $type mailing type (new,attrib,followup,finish)
	 * @param $message Message to send
	 * @return nothing 
	 */

	function __construct($orderID, $event, $entity = -1) {
		$this->orderID = $orderID;
		$this->entity = $entity;
	}

	/**
	 * Format the mail body to send
	 * @return mail body string
	 */
	function get_mail_body($format = "text") {
		global $CFG_GLPI, $LANG;

		// Create message body from Job and type
		$body = "";

		if ($format == "html") {
			$body .= "<html><head><style  type='text/css'>body {font-family: Verdana;font-size: 11px;text-align: left;} td {font-family: Verdana;font-size: 11px;text-align: left;}</style></head><body>";
			$body .= "<table class='tab_cadre' border='1' cellspacing='2' cellpadding='3'>";
			$body .= "<tr>";
			$body .= "<td bgcolor='#CCCCCC'>" . $LANG['common'][57] . "</th>";
			$body .= "<td bgcolor='#CCCCCC'>" . $LANG['search'][8] . "</td>";
			$body .= "<td bgcolor='#CCCCCC'>" . $LANG['common'][37] . "</td>";
			$body .= "<td bgcolor='#CCCCCC'>" . $LANG['joblist'][0] . "</th>";
			$body .= "<td bgcolor='#CCCCCC'>" . $LANG['reminder'][9] . "</td></tr>";
			$body .= $this->event;
			$body .= "</table>";
			$body .= "</body></html>";

		} else { // text format

			$body .= $this->event;
			$body = str_replace("<br />", "\n", $body);
			$body = str_replace("<br>", "\n", $body);
		}
		return $body;
	}
	/**
	 * Give mails to send the mail
	 * 
	 * Determine email to send mail using global config and Mailing type
	 *
	 * @return array containing email
	 */
	function get_users_to_send_mail() {
		global $DB, $CFG_GLPI;

		$emails = array ();

		$query = "SELECT * 
						FROM glpi_plugin_order_mailing 
						WHERE type='" . $this->type . "'";
		$result = $DB->query($query);
		if ($DB->numrows($result)) {
			while ($data = $DB->fetch_assoc($result)) {
				switch ($data["item_type"]) {
					case USER_MAILING_TYPE :
						switch ($data["FK_item"]) {
							// ADMIN SEND
							case ADMIN_MAILING :
								if (isValidEmail($CFG_GLPI["admin_email"]) && !in_array($CFG_GLPI["admin_email"], $emails))
									$emails[] = $CFG_GLPI["admin_email"];
								break;
								/*case TECH_MAILING :
									$query2 = "SELECT DISTINCT glpi_users.email AS EMAIL FROM glpi_users WHERE (glpi_users.ID = '".$this->job->fields["manager"]."')";
										if ($result2 = $DB->query($query2)) {
											if ($DB->numrows($result2)==1){
												$row = $DB->fetch_array($result2);
												if (isValidEmail($row['EMAIL'])&&!in_array($row['EMAIL'],$emails)){
													$emails[]=$row['EMAIL'];
												}
											}
										}
									break;	*/
						}
						break;
					case PROFILE_MAILING_TYPE :

						$query = "SELECT glpi_users.email as EMAIL 
														FROM glpi_users_profiles 
														INNER JOIN glpi_users ON (glpi_users_profiles.FK_users = glpi_users.ID) 
														WHERE glpi_users_profiles.FK_profiles='" . $data["FK_item"] . "' 
														" . getEntitiesRestrictRequest("AND", "glpi_users_profiles", "FK_entities", $this->entity, true);

						if ($result2 = $DB->query($query)) {
							if ($DB->numrows($result2))
								while ($data = $DB->fetch_assoc($result2)) {
									if (isValidEmail($data["EMAIL"]) && !in_array($data["EMAIL"], $emails)) {
										$emails[] = $data["EMAIL"];
									}
								}
						}
						break;
					case GROUP_MAILING_TYPE :
						$query = "SELECT glpi_users.email as EMAIL 
														FROM glpi_users_groups 
														INNER JOIN glpi_users ON (glpi_users_groups.FK_users = glpi_users.ID) 
														WHERE glpi_users_groups.FK_groups='" . $data["FK_item"] . "'";

						if ($result2 = $DB->query($query)) {
							if ($DB->numrows($result2))
								while ($data = $DB->fetch_assoc($result2)) {
									if (isValidEmail($data["EMAIL"]) && !in_array($data["EMAIL"], $emails)) {
										$emails[] = $data["EMAIL"];
									}
								}
						}
						break;
				}
			}
		}

		return $emails;
	}

	function mailing() {
		global $DB, $LANG, $CFG_GLPI;

		if ($CFG_GLPI["mailing"]) {
			// get users to send mail
			$users = $this->get_users_to_send_mail();

			$order = new PluginOrder;
			$order->getFromDB($this->orderID);
			
			if (isMultiEntitiesMode())
				$entity = getdropdownname("glpi_entities", $this->entity) .
				" | ";
			else
				$entity = "";

				for ($i = 0; $i < count($users); $i++) {

					$mail = new glpi_phpmailer();
					$mail->From = $CFG_GLPI["admin_email"];
					$mail->FromName = $CFG_GLPI["admin_email"];
					$mail->AddAddress($users[$i], "");
					$mail->Subject = $entity . $LANG['plugin_alerting']['alert'][0] . " " . $delay_tickets . " " . $LANG['plugin_alerting']['setup'][15];
					$mail->Body = $this->get_mail_body("html");
					$mail->isHTML(true);
					$mail->AltBody = $this->get_mail_body("text");

					if (!$mail->Send()) {
						addMessageAfterRedirect($LANG['mailing'][47], false, ERROR);
						return false;
					}
					$mail->ClearAddresses();
				}
			
		}
	}
}
?>