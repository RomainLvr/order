<?php

/**
 * -------------------------------------------------------------------------
 * Order plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of Order.
 *
 * Order is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * Order is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Order. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2009-2023 by Order plugin team.
 * @license   GPLv3 https://www.gnu.org/licenses/gpl-3.0.html
 * @link      https://github.com/pluginsGLPI/order
 * -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginOrderProfile extends CommonDBTM
{
    public static $rightname = 'profile';


    public static function createFirstAccess($ID)
    {
        self::addDefaultProfileInfos($ID, [
            'plugin_order_order'     => PluginOrderOrder::ALLRIGHTS, // All rights : CREATE + READ + ...
            'plugin_order_bill'      => 127,
            'plugin_order_reference' => 127
        ], true);
    }


   /**
    * @param $profile
    * */
    public static function addDefaultProfileInfos($profiles_id, $rights, $drop_existing = false)
    {
        $profileRight = new ProfileRight();
        foreach ($rights as $right => $value) {
            if (
                countElementsInTable(
                    'glpi_profilerights',
                    ['profiles_id' => $profiles_id,
                        'name' => $right
                    ]
                ) && $drop_existing
            ) {
                $profileRight->deleteByCriteria([
                    'profiles_id' => $profiles_id,
                    'name'        => $right
                ]);
            }
            if (
                !countElementsInTable(
                    'glpi_profilerights',
                    ['profiles_id' => $profiles_id, 'name' => $right]
                )
            ) {
                $profileRight->add([
                    'profiles_id' => $profiles_id,
                    'name'        => $right,
                    'rights'      => $value,
                ]);

               //Add right to the current session
                $_SESSION['glpiactiveprofile'][$right] = $value;
            }
        }
    }

    public function showForm($ID, array $options = [])
    {

        echo "<div class='firstbloc'>";

        if ($canedit = Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE])) {
            $profile = new Profile();
            echo "<form method='post' action='" . $profile->getFormURL() . "'>";
        }

        $profile = new Profile();
        $profile->getFromDB($ID);

       //$rights = ['rights' => self::getRights($profile->getField('interface'),];
        $rights = [];
        if ($profile->getField('interface') == 'central') {
            $rights = $this->getAllRights();
        }

        $profile->displayRightsChoiceMatrix($rights, [
            'canedit'       => $canedit,
            'default_class' => 'tab_bg_2',
            'title'         => __('Orders', 'order'),
        ]);

        if ($canedit) {
            echo "<div class='center'>";
            echo Html::hidden('id', ['value' => $ID]);
            echo Html::submit(_sx('button', 'Save'), ['name' => 'update', 'class' => 'btn btn-primary']);
            echo "</div>";
            Html::closeForm();
        }
        echo "</div>";
        return true;
    }


    public static function install(Migration $migration)
    {
        /** @var \DBmysql $DB */
        global $DB;

        if (
            $DB->tableExists("glpi_plugin_order_profiles")
            && !$DB->fieldExists(
                "glpi_plugin_order_profiles",
                "plugin_order_generate_order_without_validation"
            )
        ) {
            $DB->query("ALTER TABLE `glpi_plugin_order_profiles`
                     ADD `plugin_order_generate_order_without_validation` char(1) default NULL;");
        }

        self::initProfile();
        self::createFirstAccess($_SESSION['glpiactiveprofile']['id']);

        $migration->dropTable('glpi_plugin_order_profiles');
    }


    public static function uninstall()
    {
        /** @var \DBmysql $DB */
        global $DB;
        $DB->query("DELETE FROM glpi_profilerights WHERE name LIKE 'plugin_order_%'");
        self::removeRightsFromSession();
    }

    /**
     * @return array|string
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Profile) {
            if ($item->getField('id') && $item->getField('interface') != 'helpdesk') {
                return [1 => __("Orders", "order")];
            }
        }
        return '';
    }


    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof Profile) {
            $prof = new self();
            self::addDefaultProfileInfos($item->getID(), [
                'plugin_order_order'     => 0, // All rights : CREATE + READ + ...
                'plugin_order_bill'      => 0,
                'plugin_order_reference' => 0
            ]);
            $prof->showForm($item->getID());
        }
        return true;
    }


    public static function getAllRights($all = false)
    {

        $rights = [[
            'itemtype' => 'PluginOrderOrder',
            'label'    => __("Orders", "order"),
            'field'    => 'plugin_order_order'
        ], [
            'itemtype' => 'PluginOrderReference',
            'label'    => __("Products references", "order"),
            'field'    => 'plugin_order_reference'
        ], [
            'itemtype' => 'PluginOrderBill',
            'label'    => __("Bills", "order"),
            'field'    => 'plugin_order_bill'
        ]
        ];

        return $rights;
    }


    public static function translateARight($old_right)
    {
        switch ($old_right) {
            case '':
                return 0;
            case 'r':
                return READ;
            case 'w':
                return PluginOrderOrder::ALLRIGHTS;
            case '0':
            case '1':
                return $old_right;

            default:
                return 0;
        }
    }


    public static function migrateOneProfile($profiles_id)
    {
        /** @var \DBmysql $DB */
        global $DB;
       //Cannot launch migration if there's nothing to migrate...
        if (!$DB->tableExists('glpi_plugin_order_profiles')) {
            return true;
        }

        foreach (
            $DB->request(
                'glpi_plugin_order_profiles',
                "`profiles_id`='$profiles_id'"
            ) as $profile_data
        ) {
            $matching = [
                'order'              => 'plugin_order_order',
                'bill'               => 'plugin_order_bill',
                'reference'          => 'plugin_order_reference',
                'validation'         => 'plugin_order_validation',
                'cancel'             => 'plugin_order_cancel',
                'undo_validation'    => 'plugin_order_undo_validation',
                'delivery'           => 'plugin_order_delivery',
                'generate_order_odt' => 'plugin_order_generate_order_odt',
                'open_ticket'        => 'plugin_order_open_ticket'
            ];
            $current_rights = ProfileRight::getProfileRights($profiles_id, array_values($matching));
            foreach ($matching as $old => $new) {
                if (!isset($current_rights[$old])) {
                    $right = self::translateARight($profile_data[$old]);
                    switch ($new) {
                        case 'plugin_order_delivery':
                        case 'plugin_order_validation':
                        case 'plugin_order_cancel':
                        case 'plugin_order_undo_validation':
                        case 'plugin_order_generate_order_without_validation':
                        case 'plugin_order_generate_order_odt':
                        case 'plugin_order_open_ticket':
                            $right = 0;
                            if ($profile_data[$old] == 'w') {
                                $right = 1;
                            }
                            break;
                    }
                    $query = "UPDATE `glpi_profilerights`
                         SET `rights`='" . $right . "'
                         WHERE `name`='$new' AND `profiles_id`='$profiles_id'";
                    $DB->query($query);
                }
            }
        }
    }


   /**
    * Initialize profiles, and migrate it necessary
    */
    public static function initProfile()
    {
        /** @var \DBmysql $DB */
        global $DB;
        $profile = new self();

       //Add new rights in glpi_profilerights table
        foreach ($profile->getAllRights(true) as $data) {
            if (!countElementsInTable("glpi_profilerights", ['name' => $data['field']])) {
                ProfileRight::addProfileRights([$data['field']]);
            }
        }

       //Migration old rights in new ones
        foreach ($DB->request("SELECT `id` FROM `glpi_profiles`") as $prof) {
            self::migrateOneProfile($prof['id']);
        }
        foreach (
            $DB->request("SELECT *
                             FROM `glpi_profilerights`
                             WHERE `profiles_id`='" . $_SESSION['glpiactiveprofile']['id'] . "'
                              AND `name` LIKE '%plugin_order%'") as $prof
        ) {
            $_SESSION['glpiactiveprofile'][$prof['name']] = $prof['rights'];
        }
    }


    public static function removeRightsFromSession()
    {
        foreach (self::getAllRights(true) as $right) {
            if (isset($_SESSION['glpiactiveprofile'][$right['field']])) {
                unset($_SESSION['glpiactiveprofile'][$right['field']]);
            }
        }
    }
}
