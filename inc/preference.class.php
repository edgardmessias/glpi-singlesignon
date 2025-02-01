<?php

/**
 * ---------------------------------------------------------------------
 * SingleSignOn is a plugin which allows to use SSO for auth
 * ---------------------------------------------------------------------
 * Copyright (C) 2022 Edgard
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 * @copyright Copyright © 2021 - 2022 Edgard
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @link      https://github.com/edgardmessias/glpi-singlesignon/
 * ---------------------------------------------------------------------
 */

class PluginSinglesignonPreference extends CommonDBTM {

   static protected $notable = true;
   static $rightname = '';

   // Provider data
   public $user_id = null;
   public $providers = [];
   public $providers_users = [];

   public function __construct($user_id = null) {
      parent::__construct();

      $this->user_id = $user_id;
   }

   public function loadProviders() {
      $signon_provider = new PluginSinglesignonProvider();

      $condition = '`is_active` = 1';
      if (version_compare(GLPI_VERSION, '9.4', '>=')) {
         $condition = [$condition];
      }
      $this->providers = $signon_provider->find($condition);

      $provider_user = new PluginSinglesignonProvider_User();

      $condition = "`users_id` = {$this->user_id}";
      if (version_compare(GLPI_VERSION, '9.4', '>=')) {
         $condition = [$condition];
      }
      $this->providers_users = $provider_user->find($condition);
   }

   public function update(array $input, $history = 1, $options = []) {
      if (!isset($input['_remove_sso']) || !is_array($input['_remove_sso'])) {
         return false;
      }

      $ids = $input['_remove_sso'];
      if (empty($ids)) {
         return false;
      }

      $provider_user = new PluginSinglesignonProvider_User();
      $condition = "`users_id` = {$this->user_id} AND `id` IN (" . implode(',', $ids) . ")";
      if (version_compare(GLPI_VERSION, '9.4', '>=')) {
         $condition = [$condition];
      }

      $providers_users = $provider_user->find($condition);

      foreach ($providers_users as $pu) {
         $provider_user->delete($pu);
      }
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      switch (get_class($item)) {
         case 'Preference':
         case 'User':
            return [1 => __sso('Single Sign-on')];
         default:
            return '';
      }
   }

   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      switch (get_class($item)) {
         case 'User':
            $prefer = new self($item->fields['id']);
            $prefer->loadProviders();
            $prefer->showFormUser($item);
            break;
         case 'Preference':
            $prefer = new self(Session::getLoginUserID());
            $prefer->loadProviders();
            $prefer->showFormPreference($item);
            break;
      }
      return true;
   }

   function showFormUser(CommonGLPI $item) {
      global $CFG_GLPI;

      if (!User::canView()) {
         return false;
      }
      $canedit = Session::haveRight(User::$rightname, UPDATE);
      if ($canedit) {
         echo "<form name='form' action=\"" . PluginSinglesignonToolbox::getBaseURL() . Plugin::getPhpDir("singlesignon", false) . "/front/user.form.php\" method='post'>";
      }
      echo Html::hidden('user_id', ['value' => $this->user_id]);

      echo "<div class='center' id='tabsbody'>";
      echo "<table class='tab_cadre_fixe'>";

      echo "<tr><th colspan='4'>" . __('Settings') . "</th></tr>";

      $this->showFormDefault($item);

      if ($canedit) {
         echo "<tr class='tab_bg_2'>";
         echo "<td colspan='4' class='center'>";
         echo "<input type='submit' name='update' class='submit' value=\"" . _sx('button', 'Save') . "\">";
         echo "</td></tr>";
      }

      echo "</table></div>";
      Html::closeForm();
   }

   function showFormPreference(CommonGLPI $item) {
      $user = new User();
      if (!$user->can($this->user_id, READ) && ($this->user_id != Session::getLoginUserID())) {
         return false;
      }
      $canedit = $this->user_id == Session::getLoginUserID();

      if ($canedit) {
         echo "<form name='form' action=\"" . Toolbox::getItemTypeFormURL(__CLASS__) . "\" method='post'>";
      }

      echo "<div class='center' id='tabsbody'>";
      echo "<table class='tab_cadre_fixe'>";

      echo "<tr><th colspan='4'>" . __('Settings') . "</th></tr>";

      $this->showFormDefault($item);

      if ($canedit) {
         echo "<tr class='tab_bg_2'>";
         echo "<td colspan='4' class='center'>";
         echo "<input type='submit' name='update' class='submit' value=\"" . _sx('button', 'Save') . "\">";
         echo "</td></tr>";
      }

      echo "</table></div>";
      Html::closeForm();
   }

   function showFormDefault(CommonGLPI $item) {
      echo "<tr class='tab_bg_2'>";
      echo "<td> " . __sso('Single Sign-on Provider') . "</td><td>";

      foreach ($this->providers as $p) {
         switch (get_class($item)) {
            case 'User':
               $redirect = $item->getFormURLWithID($this->user_id, true);
               break;
            case 'Preference':
               $redirect = $item->getSearchURL(false);
               break;
            default:
               $redirect = '';
         }

         $url = PluginSinglesignonToolbox::getCallbackUrl($p['id'], ['redirect' => $redirect]);

         echo PluginSinglesignonToolbox::renderButton($url, $p);
         echo " ";
      }

      echo "</td></tr>";

      echo "<tr class='tab_bg_2'>";

      if (!empty($this->providers_users)) {
         echo "<tr><th colspan='2'>" . __sso('Linked accounts') . "</th></tr>";

         foreach ($this->providers_users as $pu) {
            /** @var PluginSinglesignonProvider */
            $provider = PluginSinglesignonProvider::getById($pu['plugin_singlesignon_providers_id']);

            echo "<tr><td>";
            echo $provider->fields['name'] . ' (ID:' . $pu['remote_id'] . ')';
            echo "</td><td>";
            echo Html::getCheckbox([
               'title' => __('Clear'),
               'name'  => "_remove_sso[]",
               'value'  => $pu['id'],
            ]);
            echo "&nbsp;" . __('Clear');
            echo "</td></tr>";
         }
      }

      ?>
      <script type="text/javascript">
         $(document).ready(function() {

            // On click, open a popup
            $(document).on("click", ".singlesignon.oauth-login", function(e) {
               e.preventDefault();

               var url = $(this).attr("href");
               var left = ($(window).width() / 2) - (600 / 2);
               var top = ($(window).height() / 2) - (800 / 2);
               var newWindow = window.open(url, "singlesignon", "width=600,height=800,left=" + left + ",top=" + top);
               if (window.focus) {
                  newWindow.focus();
               }
            });
         });
      </script>
      <?php
   }
}
