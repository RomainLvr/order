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
   ----------------------------------------------------------------------
/*----------------------------------------------------------------------
    Original Author of file: 
    Purpose of file:
    ----------------------------------------------------------------------*/

foreach (glob(GLPI_ROOT . '/plugins/order/inc/*.php') as $file)
	include_once ($file);

function plugin_order_install(){
	global $DB, $LANG, $CFG_GLPI;
		include_once (GLPI_ROOT."/inc/profile.class.php");
		
		if(!TableExists("glpi_plugin_order") ){
			plugin_order_installing("1.0.0");
		}
		plugin_order_createfirstaccess($_SESSION['glpiactiveprofile']['ID']);
		return true;
}

function plugin_order_uninstall(){
	global $DB;
	
		/* drop all the plugin tables */
		$tables = array("glpi_plugin_order",
					"glpi_plugin_order_detail",
					"glpi_plugin_order_device",
					"glpi_plugin_order_profiles",
					"glpi_dropdown_plugin_order_status",
					"glpi_dropdown_plugin_order_taxes",
					"glpi_dropdown_plugin_order_payment",
					"glpi_plugin_order_references",
					"glpi_plugin_order_references_manufacturers",
					"glpi_plugin_order_config");
					
	foreach($tables as $table)				
		$DB->query("DROP TABLE `$table`;");

		/* clean glpi_display */
		$query="DELETE FROM glpi_display WHERE type='".PLUGIN_ORDER_TYPE."' OR type='".PLUGIN_ORDER_REFERENCE_TYPE."';";
		$DB->query($query);
		/* clean glpi_doc_device */
		$query="DELETE FROM glpi_doc_device WHERE device_type='".PLUGIN_ORDER_TYPE."' OR device_type='".PLUGIN_ORDER_REFERENCE_TYPE."';";
		$DB->query($query);
		/* clean glpi_bookmark */
		$query="DELETE FROM glpi_bookmark WHERE device_type='".PLUGIN_ORDER_TYPE."' OR device_type='".PLUGIN_ORDER_REFERENCE_TYPE."';";
		$DB->query($query);
		/* clean glpi_history */
		$query="DELETE FROM glpi_history WHERE device_type='".PLUGIN_ORDER_TYPE."' OR device_type='".PLUGIN_ORDER_REFERENCE_TYPE."';";
		$DB->query($query);
		
		if (TableExists("glpi_plugin_data_injection_models"))
			$DB->query("DELETE FROM glpi_plugin_data_injection_models, glpi_plugin_data_injection_mappings, glpi_plugin_data_injection_infos USING glpi_plugin_data_injection_models, glpi_plugin_data_injection_mappings, glpi_plugin_data_injection_infos
			WHERE glpi_plugin_data_injection_models.device_type=".PLUGIN_ORDER_TYPE."
			AND glpi_plugin_data_injection_mappings.model_id=glpi_plugin_data_injection_models.ID
			AND glpi_plugin_data_injection_infos.model_id=glpi_plugin_data_injection_models.ID");
		plugin_init_order();
		cleanCache("GLPI_HEADER_".$_SESSION["glpiID"]);
		return true;
}

/* define dropdown relations */
function plugin_order_getDatabaseRelations(){
	$plugin = new Plugin();
	if ($plugin->isInstalled("order") && $plugin->isActivated("order"))
	return array("glpi_dropdown_plugin_order_status"=>array("glpi_plugin_order"=>"status"),
						"glpi_dropdown_plugin_order_payment"=>array("glpi_plugin_order"=>"payment"),
						"glpi_dropdown_plugin_order_taxes"=>array("glpi_plugin_order"=>"taxes"),
						"glpi_entities"=>array("glpi_plugin_order"=>"FK_entities"));
	else
		return array();
}

/* define dropdown tables to be manage in GLPI : */
function plugin_order_getDropdown(){
	/* table => name */
	global $LANG;
	
	$plugin = new Plugin();
	if ($plugin->isInstalled("order") && $plugin->isActivated("order"))
		return array("glpi_dropdown_plugin_order_status"=>$LANG['plugin_order']['status'][0],"glpi_dropdown_plugin_order_taxes"=>$LANG['plugin_order'][25],"glpi_dropdown_plugin_order_payment"=>$LANG['plugin_order'][32]);
	else
		return array();
}

/* ------ SEARCH FUNCTIONS ------ (){ */
/* define search option for types of the plugins */
function plugin_order_getSearchOption(){
	global $LANG;
	
		$sopt=array();
		if (plugin_order_haveRight("order","r")){
			/* part header */
			$sopt[PLUGIN_ORDER_TYPE]['common']=$LANG['plugin_order'][4];
			/* order number */
			$sopt[PLUGIN_ORDER_TYPE][1]['table']='glpi_plugin_order';
			$sopt[PLUGIN_ORDER_TYPE][1]['field']='name';
			$sopt[PLUGIN_ORDER_TYPE][1]['linkfield']='name';
			$sopt[PLUGIN_ORDER_TYPE][1]['name']=$LANG['plugin_order'][0];
			$sopt[PLUGIN_ORDER_TYPE][1]['datatype']='itemlink';
			/* date */
			$sopt[PLUGIN_ORDER_TYPE][2]['table']='glpi_plugin_order';
			$sopt[PLUGIN_ORDER_TYPE][2]['field']='date';
			$sopt[PLUGIN_ORDER_TYPE][2]['linkfield']='date';
			$sopt[PLUGIN_ORDER_TYPE][2]['name']=$LANG['plugin_order'][1];
			/* budget */
			$sopt[PLUGIN_ORDER_TYPE][3]['table']='`glpi_dropdown_budget`';
			$sopt[PLUGIN_ORDER_TYPE][3]['field']='name';
			$sopt[PLUGIN_ORDER_TYPE][3]['linkfield']='budget';
			$sopt[PLUGIN_ORDER_TYPE][3]['name']=$LANG['plugin_order'][3];
			/* location */
			$sopt[PLUGIN_ORDER_TYPE][4]['table']='glpi_dropdown_locations';
			$sopt[PLUGIN_ORDER_TYPE][4]['field']='name';
			$sopt[PLUGIN_ORDER_TYPE][4]['linkfield']='location';
			$sopt[PLUGIN_ORDER_TYPE][4]['name']=$LANG['plugin_order'][40];
			/* status */
			$sopt[PLUGIN_ORDER_TYPE][5]['table']='glpi_dropdown_plugin_order_status';
			$sopt[PLUGIN_ORDER_TYPE][5]['field']='name';
			$sopt[PLUGIN_ORDER_TYPE][5]['linkfield']='status';
			$sopt[PLUGIN_ORDER_TYPE][5]['name']=$LANG['plugin_order']['status'][0];
			/* supplier */
			$sopt[PLUGIN_ORDER_TYPE][6]['table']='glpi_enterprises';
			$sopt[PLUGIN_ORDER_TYPE][6]['field']='name';
			$sopt[PLUGIN_ORDER_TYPE][6]['linkfield']='FK_enterprise';
			$sopt[PLUGIN_ORDER_TYPE][6]['name']=$LANG['plugin_order']['setup'][14];
			/* payment */
			$sopt[PLUGIN_ORDER_TYPE][7]['table']='glpi_dropdown_plugin_order_payment';
			$sopt[PLUGIN_ORDER_TYPE][7]['field']='name';
			$sopt[PLUGIN_ORDER_TYPE][7]['linkfield']='payment';
			$sopt[PLUGIN_ORDER_TYPE][7]['name']=$LANG['plugin_order'][32];
			/* delivery num */
			$sopt[PLUGIN_ORDER_TYPE][8]['table']='glpi_plugin_order';
			$sopt[PLUGIN_ORDER_TYPE][8]['field']='deliverynum';
			$sopt[PLUGIN_ORDER_TYPE][8]['linkfield']='deliverynum';
			$sopt[PLUGIN_ORDER_TYPE][8]['name']=$LANG['plugin_order'][12];
			/* bill number */
			$sopt[PLUGIN_ORDER_TYPE][9]['table']='glpi_plugin_order';
			$sopt[PLUGIN_ORDER_TYPE][9]['field']='numbill';
			$sopt[PLUGIN_ORDER_TYPE][9]['linkfield']='numbill';
			$sopt[PLUGIN_ORDER_TYPE][9]['name']=$LANG['plugin_order'][28];
			/* title */
			$sopt[PLUGIN_ORDER_TYPE][10]['table']='glpi_plugin_order';
			$sopt[PLUGIN_ORDER_TYPE][10]['field']='title';
			$sopt[PLUGIN_ORDER_TYPE][10]['linkfield']='title';
			$sopt[PLUGIN_ORDER_TYPE][10]['name']=$LANG['plugin_order'][39];
			/* comments */
			$sopt[PLUGIN_ORDER_TYPE][16]['table']='glpi_plugin_order';
			$sopt[PLUGIN_ORDER_TYPE][16]['field']='comment';
			$sopt[PLUGIN_ORDER_TYPE][16]['linkfield']='comment';
			$sopt[PLUGIN_ORDER_TYPE][16]['name']=$LANG['plugin_order'][2];
			$sopt[PLUGIN_ORDER_TYPE][16]['datatype']='text';
			/* ID */
			$sopt[PLUGIN_ORDER_TYPE][30]['table']='glpi_plugin_order';
			$sopt[PLUGIN_ORDER_TYPE][30]['field']='ID';
			$sopt[PLUGIN_ORDER_TYPE][30]['linkfield']='';
			$sopt[PLUGIN_ORDER_TYPE][30]['name']=$LANG['common'][2];
			/* entity */
			$sopt[PLUGIN_ORDER_TYPE][80]['table']='glpi_entities';
			$sopt[PLUGIN_ORDER_TYPE][80]['field']='completename';
			$sopt[PLUGIN_ORDER_TYPE][80]['linkfield']='FK_entities';
			$sopt[PLUGIN_ORDER_TYPE][80]['name']=$LANG['entity'][0];


			$sopt[PLUGIN_ORDER_REFERENCE_TYPE]['common']=$LANG['plugin_order']['reference'][1];

			$sopt[PLUGIN_ORDER_REFERENCE_TYPE][1]['table']='glpi_plugin_order_references';
			$sopt[PLUGIN_ORDER_REFERENCE_TYPE][1]['field']='ID';
			$sopt[PLUGIN_ORDER_REFERENCE_TYPE][1]['linkfield']='ID';
			$sopt[PLUGIN_ORDER_REFERENCE_TYPE][1]['name']='ID';
			$sopt[PLUGIN_ORDER_REFERENCE_TYPE][1]['datatype']='itemlink';
			
			$sopt[PLUGIN_ORDER_REFERENCE_TYPE][2]['table']='glpi_plugin_order_references';
			$sopt[PLUGIN_ORDER_REFERENCE_TYPE][2]['field']='name';
			$sopt[PLUGIN_ORDER_REFERENCE_TYPE][2]['linkfield']='name';
			$sopt[PLUGIN_ORDER_REFERENCE_TYPE][2]['name']=$LANG['plugin_order']['detail'][2];
			$sopt[PLUGIN_ORDER_REFERENCE_TYPE][2]['datatype']='itemlink';
/*			
			$sopt[PLUGIN_ORDER_REFERENCE_TYPE][3]['table']='glpi_plugin_order_references';
			$sopt[PLUGIN_ORDER_REFERENCE_TYPE][3]['field']='price';
			$sopt[PLUGIN_ORDER_REFERENCE_TYPE][3]['linkfield']='price';
			$sopt[PLUGIN_ORDER_REFERENCE_TYPE][3]['name']=$LANG['plugin_order'][13];
			$sopt[PLUGIN_ORDER_REFERENCE_TYPE][1]['datatype']='float';
			
			$sopt[PLUGIN_ORDER_REFERENCE_TYPE][4]['table']='glpi_enterprises';
			$sopt[PLUGIN_ORDER_REFERENCE_TYPE][4]['field']='name';
			$sopt[PLUGIN_ORDER_REFERENCE_TYPE][4]['linkfield']='FK_enterprise';
			$sopt[PLUGIN_ORDER_REFERENCE_TYPE][4]['name']=$LANG['financial'][26];
*/

			/* entity */
			$sopt[PLUGIN_ORDER_REFERENCE_TYPE][80]['table']='glpi_entities';
			$sopt[PLUGIN_ORDER_REFERENCE_TYPE][80]['field']='completename';
			$sopt[PLUGIN_ORDER_REFERENCE_TYPE][80]['linkfield']='FK_entities';
			$sopt[PLUGIN_ORDER_REFERENCE_TYPE][80]['name']=$LANG['entity'][0];

		}
		return $sopt;
}

/* for search */
function plugin_order_addLeftJoin($type,$ref_table,$new_table,$linkfield,&$already_link_tables){
	switch ($new_table){
		case "glpi_plugin_order_device" : /* from order list */
			return " LEFT JOIN $new_table ON ($ref_table.ID = $new_table.FK_order) ";
			break;
		case "glpi_plugin_order" : /* from items */
			$out= " LEFT JOIN glpi_plugin_order_detail ON ($ref_table.ID = glpi_plugin_order_detail.FK_device) ";
			$out.= " LEFT JOIN glpi_plugin_order ON (glpi_plugin_order.ID = glpi_plugin_order_device.FK_order AND glpi_plugin_order_device.device_type=$type) ";
			return $out;
			break;
		case "glpi_dropdown_plugin_order_type" : /* from items */
			$out=addLeftJoin($type,$ref_table,$already_link_tables,"glpi_plugin_order",$linkfield);
			$out.= " LEFT JOIN glpi_dropdown_plugin_order_type ON (glpi_dropdown_plugin_order_type.ID = glpi_plugin_order.type) ";
			return $out;
			break; 
	}
	return "";
}

/* force groupby for multible links to items */
function plugin_order_forceGroupBy($type){
	return true;
	switch ($type){
		case PLUGIN_ORDER_TYPE:
			return true;
			break;
	}
	return false;
}

/* display custom fields in the search */
function plugin_order_giveItem($type,$ID,$data,$num){
	global $CFG_GLPI, $INFOFORM_PAGES, $LANG,$SEARCH_OPTION,$LINK_ID_TABLE,$DB;
	$table=$SEARCH_OPTION[$type][$ID]["table"];
	$field=$SEARCH_OPTION[$type][$ID]["field"];
	switch ($table.'.'.$field){
		/* display associated items with order */
		case "glpi_plugin_order_device.FK_device" :
			$query_device = "SELECT DISTINCT device_type 
							FROM glpi_plugin_order_device 
							WHERE FK_order = '".$data['ID']."' 
							ORDER BY device_type";
			$result_device = $DB->query($query_device);
			$number_device = $DB->numrows($result_device);
			$y = 0;
			$out='';
			$order=$data['ID'];
			if ($number_device>0){
				$ci=new CommonItem();
				while ($y < $number_device) {
					$column="name";
					if ($type==TRACKING_TYPE) $column="ID";
					$type=$DB->result($result_device, $y, "device_type");
					if (!empty($LINK_ID_TABLE[$type])){
						$query = "SELECT ".$LINK_ID_TABLE[$type].".*, glpi_plugin_order_device.ID AS IDD, glpi_entities.ID AS entity "
						." FROM glpi_plugin_order_device, ".$LINK_ID_TABLE[$type]
						." LEFT JOIN glpi_entities ON (glpi_entities.ID=".$LINK_ID_TABLE[$type].".FK_entities) "
						." WHERE ".$LINK_ID_TABLE[$type].".ID = glpi_plugin_order_device.FK_device 
							AND glpi_plugin_order_device.device_type='$type' 
							AND glpi_plugin_order_device.FK_order = '".$order."' "
						. getEntitiesRestrictRequest(" AND ",$LINK_ID_TABLE[$type],'','',isset($CFG_GLPI["recursive_type"][$type])); 
						if (in_array($LINK_ID_TABLE[$type],$CFG_GLPI["template_tables"])){
							$query.=" AND ".$LINK_ID_TABLE[$type].".is_template='0'";
						}
						$query.=" ORDER BY glpi_entities.completename, ".$LINK_ID_TABLE[$type].".$column";
						if ($result_linked=$DB->query($query))
							if ($DB->numrows($result_linked)){
								$ci->setType($type);
								while ($data=$DB->fetch_assoc($result_linked)){
									$out.=$ci->getType()." - ";
									$ID="";
									if($_SESSION["glpiview_ID"]||empty($data["name"])) $ID= " (".$data["ID"].")";
									$name= "<a href=\"".$CFG_GLPI["root_doc"]."/".$INFOFORM_PAGES[$type]."?ID=".$data["ID"]."\">"
									.$data["name"]."$ID</a>";
									$out.=$name."<br>";
								}
							}else
								$out.=' ';
						}else
							$out.=' ';
					$y++;
				}
			}
		return $out;
		break;
	}
	return "";
}

/* ----- SPECIFIC MODIF MASSIVE FUNCTIONS ----- */
function plugin_order_MassiveActions($type){
	global $LANG;
	
	switch ($type){
		case PLUGIN_ORDER_TYPE:
			return array(
				/* GLPI core one */
				"add_document"=>$LANG['document'][16],
				/* association with glpi items */
				"plugin_order_install"=>$LANG['plugin_order']['item'][1],
				"plugin_order_desinstall"=>$LANG['plugin_order']['item'][0],
				/* tranfer order to another entity */
				"plugin_order_transfert"=>$LANG['buttons'][48],
				);
		break;
	}
	/* adding order from items lists */
		if (in_array($type,array(COMPUTER_TYPE,
				MONITOR_TYPE,NETWORKING_TYPE,PERIPHERAL_TYPE,PHONE_TYPE,PRINTER_TYPE,SOFTWARE_TYPE,TRACKING_TYPE,CONTRACT_TYPE))){
			return array("plugin_order_add_item"=>$LANG['plugin_order']['setup'][25]);
		}
	return array();
}

function plugin_order_MassiveActionsDisplay($type,$action){
	global $LANG,$CFG_GLPI;
	
	switch ($type){
		case PLUGIN_ORDER_TYPE:
			switch ($action){
				/* no case for add_document : use GLPI core one */
				case "plugin_order_install":
					echo "<input type=\"submit\" name=\"massiveaction\" class=\"submit\" value=\"".$LANG['plugin_order']['item'][1]."\" >";
				break;
				case "plugin_order_desinstall":
					$types=$CFG_GLPI["state_types"];
					$plugin = new Plugin();
					if ($plugin->isInstalled("applicatifs") && $plugin->isActivated("applicatifs"))
						$types[]=PLUGIN_APPLICATIFS_TYPE;
					dropdownAllItems("item_item",0,0,-1,$types);
				echo "<input type=\"submit\" name=\"massiveaction\" class=\"submit\" value=\"".$LANG['buttons'][2]."\" >";
				break;
				case "plugin_order_transfert":
					dropdownValue("glpi_entities", "FK_entities", '');
				echo "&nbsp;<input type=\"submit\" name=\"massiveaction\" class=\"submit\" value=\"".$LANG['buttons'][2]."\" >";
				break;
			}
		break;
	}
	if (in_array($type,array(COMPUTER_TYPE,
				MONITOR_TYPE,NETWORKING_TYPE,PERIPHERAL_TYPE,PHONE_TYPE,PRINTER_TYPE,SOFTWARE_TYPE,TRACKING_TYPE,CONTRACT_TYPE))){
				plugin_order_dropdownorder("conID");
				echo "<input type=\"submit\" name=\"massiveaction\" class=\"submit\" value=\"".$LANG['buttons'][2]."\" >";
		}
	return "";
}

function plugin_order_MassiveActionsProcess($data){
	global $LANG,$DB;
	
	switch ($data['action']){
		case "plugin_order_add_item":
			$plugin_order=new plugin_order();
			$ci2=new CommonItem();
			if ($plugin_order->getFromDB($data['conID'])){
				foreach ($data["item"] as $key => $val){
					if ($val==1) {
						/* items exists ? */
						if ($ci2->getFromDB($data["device_type"],$key)){
							/* entity security */
							if (!isset($plugin_order->obj->fields["FK_entities"])
								||$ci2->obj->fields["FK_entities"]==$plugin_order->obj->fields["FK_entities"]
								||($ci2->obj->fields["recursive"] && in_array($ci2->obj->fields["FK_entities"], getEntityAncestors($plugin_order->obj->fields["FK_entities"])))){
								plugin_order_linkdevice($data["conID"],$key,$data['device_type']);
							}
						}
					}
				}
			}
		break;
		case "plugin_order_install":
		break;
		case "plugin_order_desinstall":
				if ($data['device_type']==PLUGIN_ORDER_TYPE){
					foreach ($data["item"] as $key => $val){
						if ($val==1){
							$query="DELETE FROM 
									glpi_plugin_order_device 
									WHERE device_type='".$data['type']."' 
									AND FK_device='".$data['item_item']."' 
									AND FK_order = '$key'";
							$DB->query($query);
					}
				}
			}
		break;
		case "plugin_order_transfert":
		if ($data['device_type']==PLUGIN_ORDER_TYPE){
			foreach ($data["item"] as $key => $val){
				if ($val==1){
					$plugin_order=new plugin_order;
					$plugin_order->getFromDB($key);
					$query="UPDATE `glpi_plugin_order` 
							SET `FK_entities` = '".$data['FK_entities']."' 
							WHERE `ID` ='$key'";
					$DB->query($query);
				}
			}
		}	
		break;
	}
}

/* hook done on delete item case */
function plugin_pre_item_delete_order($input){
	if (isset($input["_item_type_"]))
		switch ($input["_item_type_"]){
			case PROFILE_TYPE :
				/* manipulate data if needed */
				$plugin_order_Profile=new plugin_order_Profile;
				$plugin_order_Profile->cleanProfiles($input["ID"]);
				break;
		}
	return $input;
}
/*
function plugin_item_delete_order($parm){
		if (isset($parm["type"]))
			switch ($parm["type"]){
				case TRACKING_TYPE :
					$plugin_order=new plugin_order;
					$plugin_order->cleanItems($parm['ID'], $parm['type']);
					return true;
					break;
			}
	return false;
}*/

/* hook done on purge item case */
function plugin_item_purge_order($parm){
	if (in_array($parm["type"],array(COMPUTER_TYPE,
			MONITOR_TYPE,NETWORKING_TYPE,PERIPHERAL_TYPE,PHONE_TYPE,PRINTER_TYPE,SOFTWARE_TYPE,CONTRACT_TYPE,PROFILE_TYPE))){
		$plugin_order=new plugin_order;
		$plugin_order->cleanItems($parm["ID"],$parm["type"]);
		return true;
	}elseif (in_array($parm["type"],array(DOCUMENT_TYPE))){
		$plugin_order=new plugin_order;
		$plugin_order->cleanDocuments($parm["ID"]);
		return true;
	}else
		return false;
}

/* define headings added by the plugin */
function plugin_get_headings_order($type,$withtemplate=''){
	global $LANG;
	
	if (in_array($type,array(COMPUTER_TYPE,
			MONITOR_TYPE,NETWORKING_TYPE,PERIPHERAL_TYPE,PHONE_TYPE,PRINTER_TYPE,SOFTWARE_TYPE,TRACKING_TYPE,ENTERPRISE_TYPE,CONTRACT_TYPE,PROFILE_TYPE))){
		/* template case */
		if ($withtemplate='')
			return array();
		/* non template case */
		else 
			return array(
					1 => $LANG['plugin_order'][4],
					);
	}else
		return false;
}

/* define headings actions added by the plugin */
function plugin_headings_actions_order($type){	

	if (in_array($type,array(COMPUTER_TYPE,
			MONITOR_TYPE,NETWORKING_TYPE,PERIPHERAL_TYPE,PHONE_TYPE,PRINTER_TYPE,SOFTWARE_TYPE,TRACKING_TYPE,ENTERPRISE_TYPE,CONTRACT_TYPE,PROFILE_TYPE))){
		return array(
					1 => "plugin_headings_order",
					);
	}else
		return false;
}

/* action heading */
function plugin_headings_order($type,$ID,$withtemplate=0){
	global $CFG_GLPI,$LANG;
	
		switch ($type){
			case COMPUTER_TYPE :
			case MONITOR_TYPE :
			case NETWORKING_TYPE :
			case PERIPHERAL_TYPE :
			case PHONE_TYPE :
			case PRINTER_TYPE :
			case SOFTWARE_TYPE :
			case CONTRACT_TYPE :
			case TRACKING_TYPE :
				echo "<div align='center'>";
				echo plugin_order_showAssociated($type,$ID);
				echo "</div>";
			break;
			case ENTERPRISE_TYPE :
				echo "<div align='center'>";
				plugin_order_showReferencesBySupplierID($ID);
				echo "</div>";
			break;
			case PROFILE_TYPE :
				$profile=new profile;
				$profile->GetfromDB($ID);
				if ($profile->fields["interface"]!="helpdesk"){
					$prof=new plugin_order_Profile();	
					if (!$prof->GetfromDB($ID))
						plugin_order_createaccess($ID);				
					$prof->showForm($CFG_GLPI["root_doc"]."/plugins/order/front/plugin_order.profile.php",$ID);
				}else{
					echo "<table class='tab_cadre_fixe'><tr class='tab_bg_2'><td align='center'>";
					echo $LANG['plugin_order']['setup'][2];
					echo "</td></tr></table>";
				}
			break;
			default :
			break;
		}
}
?>