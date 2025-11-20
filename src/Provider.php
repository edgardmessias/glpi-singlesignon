<?php

declare(strict_types=1);

namespace GlpiPlugin\Singlesignon;

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

class Provider extends \CommonDBTM {

   // From CommonDBTM
   public $dohistory = true;
   static $rightname = 'config';

   /**
    * @var array
    */
   static $default = null;

   /**
    *
    * @var string
    */
   protected $_code = null;

   /**
    *
    * @var null|string
    */
   protected $_token = null;

   /**
    *
    * @var null|array
    */
   protected $_resource_owner = null;

   public $debug = false;

   public static function canCreate(): bool {
      return static::canUpdate();
   }

   public static function canDelete(): bool {
      return static::canUpdate();
   }

   public static function canPurge(): bool {
      return static::canUpdate();
   }

   public static function canView(): bool {
      return static::canUpdate();
   }

   // Should return the localized name of the type
   static function getTypeName($nb = 0) {
      return \__sso('Single Sign-on Provider');
   }

   /**
    * @see \CommonGLPI::getMenuName()
    * */
   static function getMenuName() {
      return \__sso('Single Sign-on');
   }

   function defineTabs($options = []) {

      $ong = [];
      $this->addDefaultFormTab($ong);
      $this->addStandardTab(__CLASS__, $ong, $options);
      $this->addStandardTab('Log', $ong, $options);

      return $ong;
   }

   function getTabNameForItem(\CommonGLPI $item, $withtemplate = 0) {
      $tabs = [];

      $debug_mode = ($_SESSION['glpi_use_mode'] == \Session::DEBUG_MODE);
      if ($debug_mode) {
         $tabs[1] =  __('Debug');
      }

      return $tabs;
   }

   public static function displayTabContentForItem(\CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      switch ($tabnum) {
         case 1:
            $item->showFormDebug($item);
            break;
      }
      return true;
   }

   function post_getEmpty() {
      $this->fields["type"] = 'generic';
      $this->fields["is_active"] = 1;
   }

   function showFormDebug($item, $options = []) {
      \Html::requireJS('clipboard');
      $item->fields['client_secret'] = substr($item->fields['client_secret'], 0, 3) . '... (' . strlen($item->fields['client_secret']) . ')';
      echo "<table class='tab_cadre_fixe'>";
      echo "<tr><th>" . \__sso('JSON SSO provider representation') . "</th></tr>";
      echo "<tr><td class='center'><button type='button' class='btn btn-secondary' onclick=\"document.getElementById('glpi-singlesignon-json-debug').click();flashIconButton(this, 'btn btn-success', 'ti ti-check', 1500);\"><i class='far fa-copy me-2'></i>" . \__sso('Copy provider information') . "</button></td></tr>";
      echo "<tr><td><div class='copy_to_clipboard_wrapper'>";
      echo "<textarea cols='132' rows='50' style='border:1' name='json' id='glpi-singlesignon-json-debug' class='form-control'>";
      echo str_replace('\/', '/', json_encode($item, JSON_PRETTY_PRINT));
      echo "</textarea></div></td></tr>";
      echo "</table>";
   }

   function showForm($ID, $options = []) {
      global $CFG_GLPI;

      $this->initForm($ID, $options);
      $this->showFormHeader($options);

      if (empty($this->fields["type"])) {
         $this->fields["type"] = 'generic';
      }

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Name') . "</td>";
      echo "<td>";
      echo \Html::input("name", ['value' => $this->fields["name"], 'class' => 'form-control']);
      echo "</td>";
      echo "<td>" . __('Comments') . "</td>";
      echo "<td>";
      echo "<textarea name='comment' class='form-control'>" . $this->fields["comment"] . "</textarea>";
      echo "</td></tr>";

      $on_change = 'var _value = this.options[this.selectedIndex].value; $(".sso_url").toggle(_value == "generic");';

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . \__sso('SSO Type') . "</td><td>";
      self::dropdownType('type', ['value' => $this->fields["type"], 'on_change' => $on_change, 'class' => 'form-control']);
      echo "<td>" . __('Active') . "</td>";
      echo "<td>";
      \Dropdown::showYesNo("is_active", $this->fields["is_active"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . \__sso('Client ID') . "</td>";
      echo "<td><input type='text' style='width:96%' name='client_id' value='" . $this->fields["client_id"] . "' class='form-control'></td>";
      echo "<td>" . \__sso('Client Secret') . "</td>";
      echo "<td><input type='text' style='width:96%' name='client_secret' value='" . $this->fields["client_secret"] . "' class='form-control'></td>";
      echo "</tr>\n";

      $url_style = "";

      if ($this->fields["type"] != 'generic') {
         $url_style = 'style="display: none;"';
      }

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . \__sso('Scope') . "</td>";
      echo "<td><input type='text' style='width:96%' name='scope' value='" . $this->getScope() . "' class='form-control'></td>";
      echo "<td>" . \__sso('Extra Options');
      echo "&nbsp;";
      \Html::showToolTip(nl2br(sprintf(\__sso('Allows you to specify custom parameters for the SSO provider %1$s. Example: %2$s to force login or %3$s to force account selection (supported URL settings may vary by provider). You can specify additional parameters with the "&" delimiter.'), '<strong>' . \__sso('Authorize URL') . '</strong>', '<code>prompt=login</code>', '<code>prompt=select_account</code>')));
      echo "</td>";
      echo "<td><input type='text' style='width:96%' name='extra_options' value='" . $this->fields["extra_options"] . "' class='form-control'>";
      echo "</td>";
      echo "</tr>\n";

      echo "<tr class='tab_bg_1 sso_url' $url_style>";
      echo "<td>" . \__sso('Authorize URL') . "</td>";
      echo "<td colspan='3'><input type='text' style='width:96%' name='url_authorize' value='" . $this->getAuthorizeUrl() . "' class='form-control'></td>";
      echo "</tr>\n";

      echo "<tr class='tab_bg_1 sso_url' $url_style>";
      echo "<td>" . \__sso('Access Token URL') . "</td>";
      echo "<td colspan='3'><input type='text' style='width:96%' name='url_access_token' value='" . $this->getAccessTokenUrl() . "' class='form-control'></td>";
      echo "</tr>\n";

      echo "<tr class='tab_bg_1 sso_url' $url_style>";
      echo "<td>" . \__sso('Resource Owner Details URL') . "</td>";
      echo "<td colspan='3'><input type='text' style='width:96%' name='url_resource_owner_details' value='" . $this->getResourceOwnerDetailsUrl() . "' class='form-control'></td>";
      echo "</tr>\n";

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('IsDefault', 'singlesignon') . "</td><td>";
      \Dropdown::showYesNo("is_default", $this->fields["is_default"]);
      echo "<td>" . \__sso('PopupAuth') . "</td>";
      echo "<td>";
      \Dropdown::showYesNo("popup", $this->fields["popup"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . \__sso('SplitDomain') . "</td>";
      echo "<td>";
      \Dropdown::showYesNo("split_domain", $this->fields["split_domain"]);
      echo "</td>";
      echo "<td>" . \__sso('AuthorizedDomains');
      echo "&nbsp;";
      \Html::showToolTip(nl2br(\__sso('Provide a list of domains allowed to log in through this provider (separated by commas, no spaces) (optional).')));
      echo "</td>";
      echo "<td><input type='text' style='width:96%' name='authorized_domains' value='" . $this->fields["authorized_domains"] . "' class='form-control'></td>";
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . \__sso('Allowed Groups');
      echo "&nbsp;";
      \Html::showToolTip(nl2br(\__sso('Restrict login to users in specific OAuth groups. Enter group names or IDs separated by commas (e.g., admin,developers,staff). Leave empty to allow all users. The user must belong to at least one of the specified groups.')));
      echo "</td>";
      echo "<td colspan='3'><input type='text' style='width:96%' name='allowed_groups' value='" . ($this->fields["allowed_groups"] ?? '') . "' class='form-control' placeholder='admin,developers,staff'></td>";
      echo "</tr>\n";

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . \__sso("Use Email as Login") . "<td>";
      \Dropdown::showYesNo("use_email_for_login", $this->fields["use_email_for_login"]);
      echo "</td>";
      echo "<td>" . \__sso('Split Name') . "<td>";
      \Dropdown::showYesNo("split_name", $this->fields["split_name"]);
      echo "</td>";

      echo "<tr class='tab_bg_1'>";
      echo "<th colspan='4'>" . \__sso('Advanced Settings') . "</th>";
      echo "</tr>\n";

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . \__sso('SSL Verify Host');
      echo "&nbsp;";
      \Html::showToolTip(nl2br(\__sso('Verify the SSL certificate hostname. Disable only for testing with self-signed certificates.')));
      echo "</td>";
      echo "<td>";
      \Dropdown::showYesNo("ssl_verifyhost", $this->fields["ssl_verifyhost"] ?? 1);
      echo "</td>";
      echo "<td>" . \__sso('SSL Verify Peer');
      echo "&nbsp;";
      \Html::showToolTip(nl2br(\__sso('Verify the SSL certificate authenticity. Disable only for testing with self-signed certificates.')));
      echo "</td>";
      echo "<td>";
      \Dropdown::showYesNo("ssl_verifypeer", $this->fields["ssl_verifypeer"] ?? 1);
      echo "</td>";
      echo "</tr>\n";

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . \__sso('Groups Claim Name');
      echo "&nbsp;";
      \Html::showToolTip(nl2br(\__sso('Override the claim name used to extract user groups from the OAuth response. Supports dot notation for nested fields (e.g., "realm_access.roles", "custom.groups"). Leave empty to use default claim names (groups, roles, realm_access.roles).')));
      echo "</td>";
      echo "<td colspan='3'><input type='text' style='width:96%' name='groups_claim' value='" . ($this->fields["groups_claim"] ?? '') . "' class='form-control' placeholder='groups'></td>";
      echo "</tr>\n";

      echo "<tr class='tab_bg_1'>";
      echo "<th colspan='4'>" . __('Personalization') . "</th>";
      echo "</tr>\n";

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Background color') . "</td>";
      echo "<td>";
      \Html::showColorField(
         'bgcolor',
         [
            'value'  => $this->fields['bgcolor'],
         ]
      );
      echo "&nbsp;";
      echo \Html::getCheckbox([
         'title' => __('Clear'),
         'name'  => '_blank_bgcolor',
         'checked' => empty($this->fields['bgcolor']),
      ]);
      echo "&nbsp;" . __('Clear');
      echo "</td>";
      echo "<td>" . __('Color') . "</td>";
      echo "<td>";
      \Html::showColorField(
         'color',
         [
            'value'  => $this->fields['color'],
         ]
      );
      echo "&nbsp;";
      echo \Html::getCheckbox([
         'title' => __('Clear'),
         'name'  => '_blank_color',
         'checked' => empty($this->fields['color']),
      ]);
      echo "&nbsp;" . __('Clear');
      echo "</td>";
      echo "</tr>\n";

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Picture') . "</td>";
      echo "<td colspan='3'>";
      if (!empty($this->fields['picture'])) {
         echo \Html::image(Toolbox::getPictureUrl($this->fields['picture']), [
            'style' => '
               max-width: 100px;
               max-height: 100px;
               background-image: linear-gradient(45deg, #b0b0b0 25%, transparent 25%), linear-gradient(-45deg, #b0b0b0 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #b0b0b0 75%), linear-gradient(-45deg, transparent 75%, #b0b0b0 75%);
               background-size: 10px 10px;
               background-position: 0 0, 0 5px, 5px -5px, -5px 0px;',
            'class' => 'picture_square'
         ]);
         echo "&nbsp;";
         echo \Html::getCheckbox([
            'title' => __('Clear'),
            'name'  => '_blank_picture'
         ]);
         echo "&nbsp;" . __('Clear');
      } else {
         echo \Html::file([
            'name'       => 'picture',
            'onlyimages' => true,
         ]);
      }
      echo "</td>";
      echo "</tr>\n";

      echo '<script type="text/javascript">
      $("[name=bgcolor]").on("change", function (e) {
         $("[name=_blank_bgcolor]").prop("checked", false).attr("checked", false);
      });
      $("[name=color]").on("change", function (e) {
         $("[name=_blank_color]").prop("checked", false).attr("checked", false);
      });
      </script>';

      if ($ID) {
         echo "<tr class='tab_bg_1'>";
         echo "<th colspan='4'>" . __('Test') . "</th>";
         echo "</tr>\n";

         $url = Toolbox::getCallbackUrl($ID);
         $fullUrl = Toolbox::getBaseURL() . $url;
         echo "<tr class='tab_bg_1'>";
         echo "<td>" . \__sso('Callback URL') . "</td>";
         echo "<td colspan='3'><a id='singlesignon_callbackurl' href='$fullUrl' data-url='$url'>$fullUrl</a></td>";
         echo "</tr>\n";

         $options['addbuttons'] = ['test_singlesignon' => \__sso('Test Single Sign-on')];
      }

      $this->showFormButtons($options);

      if ($ID) {
         echo '<script type="text/javascript">
         $("[name=test_singlesignon]").on("click", function (e) {
            e.preventDefault();

            // Im not sure why /test/1 is added here, I got a problem with the redirect_uri because its added after /provider/id
            var url   = $("#singlesignon_callbackurl").attr("data-url"); // + "/test/1";
            var left  = ($(window).width()/2)-(600/2);
            var top   = ($(window).height()/2)-(800/2);
            var newWindow = window.open(url, "singlesignon", "width=600,height=800,left=" + left + ",top=" + top);
            if (window.focus) {
               newWindow.focus();
            }
         });
         </script>';
      }

      return true;
   }

   public function getEmpty() {
      parent::getEmpty();

      // Set secure defaults for new providers
      $this->fields['ssl_verifyhost'] = 1;
      $this->fields['ssl_verifypeer'] = 1;
   }

   function prepareInputForAdd($input) {
      return $this->prepareInput($input);
   }

   function prepareInputForUpdate($input) {
      return $this->prepareInput($input);
   }

   function cleanDBonPurge() {
      Toolbox::deletePicture($this->fields['picture']);
      $this->deleteChildrenAndRelationsFromDb(
         [
            'PluginSinglesignonProvider_User',
         ]
      );
   }

   /**
    * Prepares input (for update and add)
    *
    * @param array $input Input data
    *
    * @return array
    */
   private function prepareInput($input) {
      $error_detected = [];

      $type = '';
      //check for requirements
      if (isset($input['type'])) {
         $type = $input['type'];
      }

      if (!isset($input['name']) || empty($input['name'])) {
         $error_detected[] = \__sso('A Name is required');
      }

      if (empty($type)) {
         $error_detected[] = __('An item type is required');
      } else if (!isset(static::getTypes()[$type])) {
         $error_detected[] = sprintf(\__sso('The "%s" is a Invalid type'), $type);
      }

      if (!isset($input['client_id']) || empty($input['client_id'])) {
         $error_detected[] = \__sso('A Client ID is required');
      }

      if (!isset($input['client_secret']) || empty($input['client_secret'])) {
         $error_detected[] = \__sso('A Client Secret is required');
      }

      if ($type === 'generic') {
         if (!isset($input['url_authorize']) || empty($input['url_authorize'])) {
            $error_detected[] = \__sso('An Authorize URL is required');
         } else if (!filter_var($input['url_authorize'], FILTER_VALIDATE_URL)) {
            $error_detected[] = \__sso('The Authorize URL is invalid');
         }

         if (!isset($input['url_access_token']) || empty($input['url_access_token'])) {
            $error_detected[] = \__sso('An Access Token URL is required');
         } else if (!filter_var($input['url_access_token'], FILTER_VALIDATE_URL)) {
            $error_detected[] = \__sso('The Access Token URL is invalid');
         }

         if (!isset($input['url_resource_owner_details']) || empty($input['url_resource_owner_details'])) {
            $error_detected[] = \__sso('A Resource Owner Details URL is required');
         } else if (!filter_var($input['url_resource_owner_details'], FILTER_VALIDATE_URL)) {
            $error_detected[] = \__sso('The Resource Owner Details URL is invalid');
         }
      }

      if (count($error_detected)) {
         foreach ($error_detected as $error) {
            \Session::addMessageAfterRedirect(
               $error,
               true,
               ERROR
            );
         }
         return false;
      }

      if (isset($input["_blank_bgcolor"]) && $input["_blank_bgcolor"]) {
         $input['bgcolor'] = '';
      }

      if (isset($input["_blank_color"]) && $input["_blank_color"]) {
         $input['color'] = '';
      }

      if (isset($input["_blank_picture"]) && $input["_blank_picture"]) {
         $input['picture'] = '';

         if (array_key_exists('picture', $this->fields)) {
            Toolbox::deletePicture($this->fields['picture']);
         }
      }

      if (isset($input["_picture"])) {
         $picture = array_shift($input["_picture"]);

         if ($dest = Toolbox::savePicture(GLPI_TMP_DIR . '/' . $picture)) {
            $input['picture'] = $dest;
         } else {
            \Session::addMessageAfterRedirect(__('Unable to save picture file.'), true, ERROR);
         }

         if (array_key_exists('picture', $this->fields)) {
            Toolbox::deletePicture($this->fields['picture']);
         }
      }

      return $input;
   }

   function getSearchOptions() {
      // For GLPI <= 9.2
      $options = [];
      foreach ($this->rawSearchOptions() as $opt) {
         if (!isset($opt['id'])) {
            continue;
         }
         $optid = $opt['id'];
         unset($opt['id']);
         if (isset($options[$optid])) {
            $message = "Duplicate key $optid ({$options[$optid]['name']}/{$opt['name']}) in " . get_class($this) . " searchOptions!";
            \Toolbox::logDebug($message);
         }
         foreach ($opt as $k => $v) {
            $options[$optid][$k] = $v;
         }
      }
      return $options;
   }

   function rawSearchOptions() {
      $tab = [];

      $tab[] = [
         'id' => 'common',
         'name' => __('Characteristics'),
      ];

      $tab[] = [
         'id' => 1,
         'table' => $this->getTable(),
         'field' => 'name',
         'name' => __('Name'),
         'datatype' => 'itemlink',
      ];

      $tab[] = [
         'id' => 2,
         'table' => $this->getTable(),
         'field' => 'type',
         'name' => __('Type'),
         'searchtype' => 'equals',
         'datatype' => 'specific',
      ];

      $tab[] = [
         'id' => 3,
         'table' => $this->getTable(),
         'field' => 'client_id',
         'name' => \__sso('Client ID'),
         'datatype' => 'text',
      ];

      $tab[] = [
         'id' => 4,
         'table' => $this->getTable(),
         'field' => 'client_secret',
         'name' => \__sso('Client Secret'),
         'datatype' => 'text',
      ];

      $tab[] = [
         'id' => 5,
         'table' => $this->getTable(),
         'field' => 'scope',
         'name' => \__sso('Scope'),
         'datatype' => 'text',
      ];

      $tab[] = [
         'id' => 6,
         'table' => $this->getTable(),
         'field' => 'extra_options',
         'name' => \__sso('Extra Options'),
         'datatype' => 'specific',
      ];

      $tab[] = [
         'id' => 7,
         'table' => $this->getTable(),
         'field' => 'url_authorize',
         'name' => \__sso('Authorize URL'),
         'datatype' => 'weblink',
      ];

      $tab[] = [
         'id' => 8,
         'table' => $this->getTable(),
         'field' => 'url_access_token',
         'name' => \__sso('Access Token URL'),
         'datatype' => 'weblink',
      ];

      $tab[] = [
         'id' => 9,
         'table' => $this->getTable(),
         'field' => 'url_resource_owner_details',
         'name' => \__sso('Resource Owner Details URL'),
         'datatype' => 'weblink',
      ];

      $tab[] = [
         'id' => 10,
         'table' => $this->getTable(),
         'field' => 'is_active',
         'name' => __('Active'),
         'searchtype' => 'equals',
         'datatype' => 'bool',
      ];

      $tab[] = [
         'id' => 11,
         'table' => $this->getTable(),
         'field' => 'use_email_for_login',
         'name' => __('Use email field for login'),
         'searchtype' => 'equals',
         'datatype' => 'bool',
      ];

      $tab[] = [
         'id' => 12,
         'table' => $this->getTable(),
         'field' => 'split_name',
         'name' => __('Split name field for First & Last Name'),
         'searchtype' => 'equals',
         'datatype' => 'bool',
      ];

      $tab[] = [
         'id' => 30,
         'table' => $this->getTable(),
         'field' => 'id',
         'name' => __('ID'),
         'datatype' => 'itemlink',
      ];

      return $tab;
   }

   static function getSpecificValueToDisplay($field, $values, array $options = []) {

      if (!is_array($values)) {
         $values = [$field => $values];
      }
      switch ($field) {
         case 'type':
            return self::getTicketTypeName($values[$field]);
         case 'extra_options':
            return '<pre>' . $values[$field] . '</pre>';
      }
      return '';
   }

   static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = []) {

      if (!is_array($values)) {
         $values = [$field => $values];
      }
      $options['display'] = false;
      switch ($field) {
         case 'type':
            $options['value'] = $values[$field];
            return self::dropdownType($name, $options);
      }
      return parent::getSpecificValueToSelect($field, $name, $values, $options);
   }

   /**
    * Get ticket types
    *
    * @return array of types
    * */
   static function getTypes() {

      $options['generic'] = \__sso('Generic');
      $options['azure'] = \__sso('Azure');
      $options['facebook'] = \__sso('Facebook');
      $options['github'] = \__sso('GitHub');
      $options['google'] = \__sso('Google');
      $options['instagram'] = \__sso('Instagram');
      $options['linkedin'] = \__sso('LinkdeIn');

      return $options;
   }

   /**
    * Get ticket type Name
    *
    * @param $value type ID
    * */
   static function getTicketTypeName($value) {
      $tab = static::getTypes();
      // Return $value if not defined
      return (isset($tab[$value]) ? $tab[$value] : $value);
   }

   /**
    * Dropdown of ticket type
    *
    * @param $name            select name
    * @param $options   array of options:
    *    - value     : integer / preselected value (default 0)
    *    - toadd     : array / array of specific values to add at the begining
    *    - on_change : string / value to transmit to "onChange"
    *    - display   : boolean / display or get string (default true)
    *
    * @return string id of the select
    * */
   static function dropdownType($name, $options = []) {

      $params['value'] = 0;
      $params['toadd'] = [];
      $params['on_change'] = '';
      $params['display'] = true;

      if (is_array($options) && count($options)) {
         foreach ($options as $key => $val) {
            $params[$key] = $val;
         }
      }

      $items = [];
      if (count($params['toadd']) > 0) {
         $items = $params['toadd'];
      }

      $items += self::getTypes();

      return \Dropdown::showFromArray($name, $items, $params);
   }

   /**
    * Get an history entry message
    *
    * @param $data Array from glpi_logs table
    *
    * @since GLPI version 0.84
    *
    * @return string
    * */
   // phpcs:disable
   /* static function getHistoryEntry($data) {

      switch ($data['linked_action'] - \Log::HISTORY_PLUGIN) {
         case 0:
            return __('History from plugin example', 'example');
      }

      return '';
   } */
   // phpcs:enable

   //////////////////////////////
   ////// SPECIFIC MODIF MASSIVE FUNCTIONS ///////

   /**
    * @since version 0.85
    *
    * @see \CommonDBTM::getSpecificMassiveActions()
    * */
   // phpcs:disable
   /* function getSpecificMassiveActions($checkitem = null) {

      $actions = parent::getSpecificMassiveActions($checkitem);

      $actions['Document_Item' . \MassiveAction::CLASS_ACTION_SEPARATOR . 'add'] = _x('button', 'Add a document');         // GLPI core one
      $actions[__CLASS__ . \MassiveAction::CLASS_ACTION_SEPARATOR . 'do_nothing'] = __('Do Nothing - just for fun', 'example');  // Specific one

      return $actions;
   } */
   // phpcs:enable

   /**
    * @since version 0.85
    *
    * @see \CommonDBTM::showMassiveActionsSubForm()
    * */
   // phpcs:disable
   /* static function showMassiveActionsSubForm(MassiveAction $ma) {

      switch ($ma->getAction()) {
         case 'DoIt':
            echo "&nbsp;<input type='hidden' name='toto' value='1'>" . \Html::submit(_x('button', 'Post'), ['name' => 'massiveaction']) . " " . __('Write in item history', 'example');
            return true;
         case 'do_nothing':
            echo "&nbsp;" . \Html::submit(_x('button', 'Post'), ['name' => 'massiveaction']) . " " . __('but do nothing :)', 'example');
            return true;
      }
      return parent::showMassiveActionsSubForm($ma);
   } */
   // phpcs:enable

   /**
    * @since version 0.85
    *
    * @see \CommonDBTM::processMassiveActionsForOneItemtype()
    * */
   // phpcs:disable
   /* static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item, array $ids) {
      global $DB;

      switch ($ma->getAction()) {
         case 'DoIt':
            if ($item->getType() == 'Computer') {
               \Session::addMessageAfterRedirect(__("Right it is the type I want...", 'example'));
               \Session::addMessageAfterRedirect(__('Write in item history', 'example'));
               $changes = [0, 'old value', 'new value'];
               foreach ($ids as $id) {
                  if ($item->getFromDB($id)) {
                     \Session::addMessageAfterRedirect("- " . $item->getField("name"));
                     \Log::history($id, 'Computer', $changes, 'PluginExampleExample', \Log::HISTORY_PLUGIN);
                     $ma->itemDone($item->getType(), $id, \MassiveAction::ACTION_OK);
                  } else {
                     // Example of ko count
                     $ma->itemDone($item->getType(), $id, \MassiveAction::ACTION_KO);
                  }
               }
            } else {
               // When nothing is possible ...
               $ma->itemDone($item->getType(), $ids, \MassiveAction::ACTION_KO);
            }
            return;

         case 'do_nothing':
            if ($item->getType() == 'PluginExampleExample') {
               \Session::addMessageAfterRedirect(__("Right it is the type I want...", 'example'));
               \Session::addMessageAfterRedirect(__("But... I say I will do nothing for:", 'example'));
               foreach ($ids as $id) {
                  if ($item->getFromDB($id)) {
                     \Session::addMessageAfterRedirect("- " . $item->getField("name"));
                     $ma->itemDone($item->getType(), $id, \MassiveAction::ACTION_OK);
                  } else {
                     // Example for noright / Maybe do it with can function is better
                     $ma->itemDone($item->getType(), $id, \MassiveAction::ACTION_KO);
                  }
               }
            } else {
               $ma->itemDone($item->getType(), $ids, \MassiveAction::ACTION_KO);
            }
            return;
      }
      parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
   } */
   // phpcs:enable

   static function getIcon() {
      return "fas fa-user-lock";
   }

   public static function getDefault($type, $key, $default = null) {
      if (static::$default === null) {
         $content = file_get_contents(dirname(__FILE__) . '/../providers.json');
         static::$default = json_decode($content, true);
      }

      if (isset(static::$default[$type]) && static::$default[$type][$key]) {
         return static::$default[$type][$key];
      }

      return $default;
   }

   public function getClientType() {
      $value = "generic";

      if (isset($this->fields['type']) && !empty($this->fields['type'])) {
         $value = $this->fields['type'];
      }

      return $value;
   }

   public function getClientId() {
      $value = "";

      if (isset($this->fields['client_id']) && !empty($this->fields['client_id'])) {
         $value = $this->fields['client_id'];
      }

      return $value;
   }

   public function getClientSecret() {
      $value = "";

      if (isset($this->fields['client_secret']) && !empty($this->fields['client_secret'])) {
         $value = $this->fields['client_secret'];
      }

      return $value;
   }

   public function getScope() {
      $type = $this->getClientType();

      $value = static::getDefault($type, "scope");

      $fields = $this->fields;

      if (!isset($fields['scope']) || empty($fields['scope'])) {
         $fields['scope'] = $value;
      }

      // For generic OAuth providers, ensure 'openid' scope is included
      // This is required for the userinfo endpoint to work properly
      if ($type === 'generic' && !empty($fields['scope'])) {
         $scopes = explode(' ', $fields['scope']);
         if (!in_array('openid', $scopes)) {
            array_unshift($scopes, 'openid');
            $fields['scope'] = implode(' ', $scopes);
         }
      }

      $fields = \Plugin::doHookFunction("sso:scope", $fields);

      return $fields['scope'];
   }

   public function getExtraOptions() {
      if (isset($this->fields['extra_options']) && !empty($this->fields['extra_options'])) {
         // e.g. 'response_type=code&approval_prompt=auto'
         parse_str($this->fields['extra_options'], $value);
         // $value['response_type'] = 'code'
      } else {
         return false;
      }

      return $value;
   }

   public function getAuthorizeUrl() {
      $type = $this->getClientType();

      $value = static::getDefault($type, "url_authorize");

      $fields = $this->fields;

      if (!isset($fields['url_authorize']) || empty($fields['url_authorize'])) {
         $fields['url_authorize'] = $value;
      }

      $fields = \Plugin::doHookFunction("sso:url_authorize", $fields);

      return $fields['url_authorize'];
   }

   public function getAccessTokenUrl() {
      $type = $this->getClientType();

      $value = static::getDefault($type, "url_access_token");

      $fields = $this->fields;

      if (!isset($fields['url_access_token']) || empty($fields['url_access_token'])) {
         $fields['url_access_token'] = $value;
      }

      $fields = \Plugin::doHookFunction("sso:url_access_token", $fields);

      return $fields['url_access_token'];
   }

   public function getResourceOwnerDetailsUrl($access_token = null) {
      $type = $this->getClientType();

      $value = static::getDefault($type, "url_resource_owner_details", "");

      $fields = $this->fields;
      $fields['access_token'] = $access_token;

      if (!isset($fields['url_resource_owner_details']) || empty($fields['url_resource_owner_details'])) {
         $fields['url_resource_owner_details'] = $value;
      }

      $fields = \Plugin::doHookFunction("sso:url_resource_owner_details", $fields);

      $url = $fields['url_resource_owner_details'];

      if (!IS_NULL($access_token)) {
         $url = str_replace("<access_token>", $access_token, $url);
         $url = str_replace("<appsecret_proof>", hash_hmac('sha256', $access_token, $this->getClientSecret()), $url);
      }

      return $url;
   }

   /**
    *
    * @return boolean|string
    */
   public function checkAuthorization() {

      if (isset($_GET['error'])) {

         $error_description = isset($_GET['error_description']) ? $_GET['error_description'] : __("The action you have requested is not allowed.");

         $exception = new \Glpi\Exception\Http\BadRequestHttpException();
         $exception->setMessageToDisplay(__($error_description));
         throw $exception;
      }

      if (!isset($_GET['code'])) {
         if (session_status() === PHP_SESSION_NONE) {
            \Session::start();
         }

         // Generate CSRF token for OAuth state parameter and remember redirect in session
         $state = \Session::getNewCSRFToken();

         if (isset($_SESSION['redirect'])) {
            $_SESSION['glpi_singlesignon_redirect'] = $_SESSION['redirect'];
         } elseif (isset($_GET['redirect'])) {
            $_SESSION['glpi_singlesignon_redirect'] = $_GET['redirect'];
         }
         
         // Build the callback URL for OAuth redirect
         $callback_url = Toolbox::getBaseURL() . Toolbox::getCallbackUrl($this->fields['id']);
         
         $params = [
            'client_id' => $this->getClientId(),
            'scope' => $this->getScope(),
            'state' => $state,
            'response_type' => 'code',
            'approval_prompt' => 'auto',
            'redirect_uri' => $callback_url,
         ];
         $extra_options = $this->getExtraOptions();
         if (is_array($extra_options)) {
            $params = array_merge($params, $extra_options);
         }

         $params = \Plugin::doHookFunction("sso:authorize_params", $params);

         $url = $this->getAuthorizeUrl();

         $glue = strstr($url, '?') === false ? '?' : '&';
         $url .= $glue . http_build_query($params);

         header('Location: ' . $url);
         exit;
      }

      // Extract state parameter
      $state = isset($_GET['state']) ? $_GET['state'] : '';

      // Validate state against stored CSRF token
      \Session::checkCSRF([
         '_glpi_csrf_token' => $state,
      ]);

      $this->_code = $_GET['code'];

      return $_GET['code'];
   }

   /**
    *
    * @return boolean|string
    */
   public function getAccessToken() {
      if ($this->_token !== null) {
         return $this->_token;
      }

      if ($this->_code === null) {
         return false;
      }

      // Build the callback URL for OAuth redirect (must match the one sent in authorization request)
      $callback_url = Toolbox::getBaseURL() . Toolbox::getCallbackUrl($this->fields['id']);
      
      $params = [
         'client_id' => $this->getClientId(),
         'client_secret' => $this->getClientSecret(),
         'redirect_uri' => $callback_url,
         'grant_type' => 'authorization_code',
         'code' => $this->_code,
      ];

      $params = \Plugin::doHookFunction("sso:access_token_params", $params);

      $url = $this->getAccessTokenUrl();

      $content = \Toolbox::callCurl($url, [
         CURLOPT_HTTPHEADER => [
            "Accept: application/json",
         ],
         CURLOPT_POST => true,
         CURLOPT_POSTFIELDS => http_build_query($params),
         CURLOPT_SSL_VERIFYHOST => (bool)($this->fields['ssl_verifyhost'] ?? true),
         CURLOPT_SSL_VERIFYPEER => (bool)($this->fields['ssl_verifypeer'] ?? true),
      ]);

      if ($this->debug) {
         print_r("\ngetAccessToken:\n");
      }

      try {
         $data = json_decode($content, true);
         if ($this->debug) {
            print_r($data);
         }
         if (isset($data['error_description'])) {
            echo '<style>#page .center small { font-weight: normal; }</style>
            <script type="text/javascript">
            window.onload = function() {
               $("#page .center").append("<br><br><small>' . $data['error_description'] . '</small>");
            };
            </script>';
         }
         if (!isset($data['access_token'])) {
            return false;
         }
         $this->_token = $data['access_token'];
      } catch (\Exception $ex) {
         if ($this->debug) {
            print_r($content);
         }
         return false;
      }

      return $this->_token;
   }

   /**
    *
    * @return boolean|array
    */
   public function getResourceOwner() {
      if ($this->_resource_owner !== null) {
         return $this->_resource_owner;
      }

      $token = $this->getAccessToken();
      if (!$token) {
         return false;
      }

      $url = $this->getResourceOwnerDetailsUrl($token);

      $headers = [
         "Accept:application/json",
         "Authorization:Bearer $token",
      ];

      $headers = \Plugin::doHookFunction("sso:resource_owner_header", $headers);

      if ($this->debug) {
         print_r("Headers:\n");
         print_r($headers);
         print_r("\n");
      }

      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, (bool)($this->fields['ssl_verifyhost'] ?? true));
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, (bool)($this->fields['ssl_verifypeer'] ?? true));
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

      $content = curl_exec($ch);
      $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $curl_error = curl_error($ch);
      // curl_close() is deprecated in PHP 8.0+ - handles are auto-closed

      if ($this->debug) {
         print_r("\ngetResourceOwner:\n");
         print_r("\nHTTP Status Code: " . $http_code . "\n");
         if ($curl_error) {
            print_r("CURL Error: " . $curl_error . "\n");
         }
         print_r("Raw content from userinfo endpoint:\n");
         print_r($content);
         print_r("\n");
         print_r("Content length: " . strlen($content) . "\n");
      }

      try {
         $data = json_decode($content, true);
         if ($this->debug) {
            print_r("\nDecoded data:\n");
            print_r($data);
         }

         // Check if json_decode failed
         if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            if ($this->debug) {
               print_r("\nJSON decode error: " . json_last_error_msg() . "\n");
            }
            return false;
         }

         $this->_resource_owner = $data;
      } catch (\Exception $ex) {
         if ($this->debug) {
            print_r("\nException occurred:\n");
            print_r($ex->getMessage());
            print_r("\n");
         }
         return false;
      }

      if ($this->getClientType() === "linkedin") {
         if ($this->debug) {
            print_r("\nlinkedin:\n");
         }
         $email_url = "https://api.linkedin.com/v2/emailAddress?q=members&projection=(elements*(handle~))";
         $content = \Toolbox::callCurl($email_url, [
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYHOST => (bool)($this->fields['ssl_verifyhost'] ?? true),
            CURLOPT_SSL_VERIFYPEER => (bool)($this->fields['ssl_verifypeer'] ?? true),
         ]);

         try {
            $data = json_decode($content, true);
            if ($this->debug) {
               print_r($content);
            }

            $this->_resource_owner['email-address'] = $data['elements'][0]['handle~']['emailAddress'];
         } catch (\Exception $ex) {
            return false;
         }
      }

      return $this->_resource_owner;
   }

   /**
    * Extract user groups from OAuth resource response
    * Supports custom claim names with dot notation
    *
    * @param array $resource_array The OAuth resource owner data
    * @return array Array of group names
    */
   protected function extractUserGroups($resource_array) {
      $groups = [];

      // Check if a custom groups claim is configured
      if (!empty($this->fields['groups_claim'])) {
         $claim_path = $this->fields['groups_claim'];

         if ($this->debug) {
            print_r("\nUsing custom groups claim: $claim_path\n");
         }

         // Support dot notation for nested fields (e.g., "realm_access.roles")
         $keys = explode('.', $claim_path);
         $value = $resource_array;

         foreach ($keys as $key) {
            if (isset($value[$key])) {
               $value = $value[$key];
            } else {
               $value = null;
               break;
            }
         }

         if ($value !== null) {
            if (is_array($value)) {
               $groups = array_merge($groups, $value);
            } elseif (is_string($value)) {
               // Support comma or space-separated strings
               $groups = array_merge($groups, preg_split('/[,\s]+/', $value));
            }
         }

         if ($this->debug) {
            print_r("Groups from custom claim: ");
            var_dump($groups);
         }

         // If custom claim is configured, only use that claim
         if (!empty($groups)) {
            return array_values(array_filter(array_unique($groups), function($group) {
               return !empty($group);
            }));
         }
      }

      // Fall back to checking standard group field names
      $group_fields = ['groups', 'roles', 'group', 'role'];

      foreach ($group_fields as $field) {
         if (isset($resource_array[$field])) {
            if (is_array($resource_array[$field])) {
               $groups = array_merge($groups, $resource_array[$field]);
            } elseif (is_string($resource_array[$field])) {
               $groups = array_merge($groups, preg_split('/[,\s]+/', $resource_array[$field]));
            }
         }
      }

      // Keycloak-specific: realm_access.roles
      if (isset($resource_array['realm_access']['roles']) && is_array($resource_array['realm_access']['roles'])) {
         $groups = array_merge($groups, $resource_array['realm_access']['roles']);
      }

      // Keycloak-specific: resource_access.{client_id}.roles
      if (isset($resource_array['resource_access']) && is_array($resource_array['resource_access'])) {
         foreach ($resource_array['resource_access'] as $client_roles) {
            if (isset($client_roles['roles']) && is_array($client_roles['roles'])) {
               $groups = array_merge($groups, $client_roles['roles']);
            }
         }
      }

      // Remove duplicates, empty values, and reindex array
      return array_values(array_filter(array_unique($groups), function($group) {
         return !empty($group);
      }));
   }

   public function findUser() {
      $resource_array = $this->getResourceOwner();

      if (!$resource_array) {
         return false;
      }

      // Check group-based access control
      if (!empty($this->fields['allowed_groups'])) {
         $allowed_groups = array_map('trim', explode(',', $this->fields['allowed_groups']));
         $user_groups = $this->extractUserGroups($resource_array);

         if ($this->debug) {
            print_r("\nGroup-based access control enabled\n");
            print_r("Allowed groups: ");
            var_dump($allowed_groups);
            print_r("User groups: ");
            var_dump($user_groups);
         }

         $has_access = false;
         foreach ($user_groups as $user_group) {
            if (in_array($user_group, $allowed_groups)) {
               $has_access = true;
               if ($this->debug) {
                  print_r("Access granted - user is in group: $user_group\n");
               }
               break;
            }
         }

         if (!$has_access) {
            if ($this->debug) {
               print_r("Access denied - user is not in any allowed group\n");
            }
            return false;
         }
      }

      $user = new \User();
      //First: check linked user
      $id = \Plugin::doHookFunction("sso:find_user", $resource_array);

      if (is_numeric($id) && $user->getFromDB($id)) {
         return $user;
      }

      $remote_id = false;
      $remote_id_fields = ['id', 'username', 'sub'];

      foreach ($remote_id_fields as $field) {
         if (isset($resource_array[$field]) && !empty($resource_array[$field])) {
            $remote_id = $resource_array[$field];
            break;
         }
      }

      if ($remote_id) {
         try {
            $link = new \PluginSinglesignonProvider_User();
            $condition = "`remote_id` = '{$remote_id}' AND `plugin_singlesignon_providers_id` = {$this->fields['id']}";

            if (version_compare(GLPI_VERSION, '9.4', '>=')) {
               $condition = [$condition];
            }

            $links = $link->find($condition);

            if (!empty($links) && $first = reset($links)) {
               $id = $first['users_id'];
               if ($this->debug) {
                  print_r("Found user by remote_id link: $id\n");
               }
            }
         } catch (\Exception $ex) {
            if ($this->debug) {
               print_r("\nException during remote_id lookup:\n");
               print_r($ex->getMessage());
               print_r("\n");
            }
         }
      }

      if (is_numeric($id) && $user->getFromDB($id)) {
         if ($this->debug) {
            print_r("User found by ID, returning\n");
         }
         return $user;
      }

      $split = $this->fields['split_domain'];
      $authorizedDomainsString = $this->fields['authorized_domains'];
      $authorizedDomains = [];
      if (isset($authorizedDomainsString)) {
         $authorizedDomains = explode(',', $authorizedDomainsString);
      }

      // check email first
      $email = false;
      $email_fields = ['email', 'e-mail', 'email-address', 'mail'];

      foreach ($email_fields as $field) {
         if (isset($resource_array[$field]) && is_string($resource_array[$field])) {
            $email = $resource_array[$field];
            $isAuthorized = empty($authorizedDomains);
            foreach ($authorizedDomains as $authorizedDomain) {
               if (preg_match("/{$authorizedDomain}$/i", $email)) {
                  $isAuthorized = true;
               }
            }
            if (!$isAuthorized) {
               return false;
            }
            if ($split) {
               $emailSplit = explode("@", $email);
               $email = $emailSplit[0];
            }
            break;
         }
      }

      $login = false;
      $use_email = $this->fields['use_email_for_login'];
      if ($email && $use_email) {
         $login = $email;
      } else {
         $login_fields = ['userPrincipalName', 'login', 'username', 'id', 'name', 'displayName'];

         foreach ($login_fields as $field) {
            if (isset($resource_array[$field]) && is_string($resource_array[$field])) {
               $login = $resource_array[$field];
               $isAuthorized = empty($authorizedDomains);
               foreach ($authorizedDomains as $authorizedDomain) {
                  if (preg_match("/{$authorizedDomain}$/i", $login)) {
                     $isAuthorized = true;
                  }
               }

               if (!$isAuthorized) {
                  if ($this->debug) {
                     print_r("\nLogin not authorized by domain restriction\n");
                  }
                  return false;
               }
               if ($split) {
                  $loginSplit = explode("@", $login);
                  $login = $loginSplit[0];
               }
               break;
            }
         }
      }

      if ($login && $user->getFromDBbyName($login)) {
         if ($this->debug) {
            print_r("User found by login name, returning\n");
         }
         return $user;
      }

      $default_condition = '';

      if (version_compare(GLPI_VERSION, '9.3', '>=')) {
         $default_condition = [];
      }

      $bOk = true;
      if ($email && $user->getFromDBbyEmail($email, $default_condition)) {
         if ($this->debug) {
            print_r("User found by email, returning\n");
         }
         return $user;
      } else {
         $bOk = false;
      }

      // If the user does not exist in the database and the provider is google
      if (static::getClientType() == "google" && !$bOk) {
         if ($this->debug) {
            print_r("\nAttempting to create user for Google provider\n");
         }
         // Generates an api token and a personal token... probably not necessary
         $tokenAPI = base_convert(hash('sha256', time() . mt_rand()), 16, 36);
         $tokenPersonnel = base_convert(hash('sha256', time() . mt_rand()), 16, 36);

         $realname = '';
         if (isset($resource_array['family_name'])) {
            $realname = $resource_array['family_name'];
         }
         $firstname = '';
         if (isset($resource_array['given_name'])) {
            $firstname = $resource_array['given_name'];
         }
         $useremail = $email;
         if (isset($resource_array['email'])) {
            $useremail = $resource_array['email'];
         }

         $userPost = [
            'name' => $login,
            'add' => 1,
            'password' => '',
            'realname' => $realname,
            'firstname' => $firstname,
            //'picture' => $resource_array['picture'] ?? '',
            'picture' => $resource_array['picture'],
            'api_token' => $tokenAPI,
            'api_token_date' => date("Y-m-d H:i:s"),
            'personal_token' => $tokenPersonnel,
            'is_active' => 1
         ];
         $userPost['_useremails'][-1] = $useremail;
         $user->add($userPost);
         return $user;
      }

      // If the user does not exist in the database and the provider is generic (Ex: azure ad without common tenant)
      if (static::getClientType() == "generic" && !$bOk) {
         if ($this->debug) {
            print_r("\nAttempting to create user for Generic provider\n");
         }
         try {
            // Generates an api token and a personal token... probably not necessary
            $tokenAPI = base_convert(hash('sha256', time() . mt_rand()), 16, 36);
            $tokenPersonnel = base_convert(hash('sha256', time() . mt_rand()), 16, 36);

            $splitname = $this->fields['split_name'];
            $firstname = '';
            $realname = '';

            // Try to get name from various fields
            if ($splitname && isset($resource_array['name']) && !empty($resource_array['name'])) {
               $firstLastArray = preg_split('/ /', $resource_array['name'], 2);
               $firstname = $firstLastArray[0];
               $realname = isset($firstLastArray[1]) ? $firstLastArray[1] : '';
            } else if (isset($resource_array['displayName']) && !empty($resource_array['displayName'])) {
               $firstLastArray = preg_split('/ /', $resource_array['displayName'], 2);
               $firstname = $firstLastArray[0];
               $realname = isset($firstLastArray[1]) ? $firstLastArray[1] : '';
            } else if (isset($resource_array['given_name']) && isset($resource_array['family_name'])) {
               // OpenID Connect standard claims
               $firstname = $resource_array['given_name'];
               $realname = $resource_array['family_name'];
            } else if (isset($resource_array['preferred_username'])) {
               // Fallback: use preferred_username as firstname
               $firstname = $resource_array['preferred_username'];
               $realname = '';
            } else if ($login) {
               // The user will have empty realname if no other info is available
            }

            if ($this->debug) {
               print_r("\nUser creation - firstname: $firstname, realname: $realname\n");
            }

            $userPost = [
               'name' => $login,
               'add' => 1,
               'password' => '',
               'realname' => $realname,
               'firstname' => $firstname,
               'api_token' => $tokenAPI,
               'api_token_date' => date("Y-m-d H:i:s"),
               'personal_token' => $tokenPersonnel,
               'is_active' => 1
            ];

            // Set the office location from Office 365 user as entity for the GLPI new user if they names match
            if (isset($resource_array['officeLocation'])) {
               global $DB;
               foreach ($DB->request('glpi_entities') as $entity) {
                  if ($entity['name'] == $resource_array['officeLocation']) {
                     $userPost['entities_id'] = $entity['id'];
                     break;
                  }
               }
            }

            if ($email) {
               $userPost['_useremails'][-1] = $email;
            }

            //$user->check(-1, CREATE, $userPost);
            $newID = $user->add($userPost);

            if (!$newID) {
               if ($this->debug) {
                  print_r("\nUser creation failed!\n");
               }
               return false;
            }

            $profils = 0;
            // Verification default profiles exist in the entity
            // If no default profile exists, the user will not be able to log in.
            // In this case, we retrieve a profile and an entity and assign these values ​​to it.
            // The administrator can change these values ​​later.
            if (0 == \Profile::getDefault()) {
               if ($this->debug) {
                  print_r("\nNo default profile found, assigning first available profile\n");
               }
               // No default profiles
               // Profile recovery and assignment
               global $DB;

               $datasProfiles = [];
               foreach ($DB->request('glpi_profiles') as $data) {
                  array_push($datasProfiles, $data);
               }
               $datasEntities = [];
               foreach ($DB->request('glpi_entities') as $data) {
                  array_push($datasEntities, $data);
               }

               if ($this->debug) {
                  print_r("Available profiles: " . count($datasProfiles) . "\n");
                  print_r("Available entities: " . count($datasEntities) . "\n");
               }

               if (count($datasProfiles) > 0 && count($datasEntities) > 0) {
                  $profils = $datasProfiles[0]['id'];
                  $entitie = $datasEntities[0]['id'];

                  if ($this->debug) {
                     print_r("Assigning profile ID: $profils, entity ID: $entitie\n");
                  }

                  $profile   = new \Profile_User();
                  $userProfile['users_id'] = intval($user->fields['id']);
                  $userProfile['entities_id'] = intval($entitie);
                  $userProfile['is_recursive'] = 0;
                  $userProfile['profiles_id'] = intval($profils);
                  $userProfile['add'] = "Ajouter";
                  $profileResult = $profile->add($userProfile);

                  if ($this->debug) {
                     print_r("Profile assignment result: ");
                     var_dump($profileResult);
                  }

                  if (!$profileResult) {
                     if ($this->debug) {
                        print_r("Profile assignment failed!\n");
                     }
                     return false;
                  }
               } else {
                  if ($this->debug) {
                     print_r("No profiles or entities available!\n");
                  }
                  return false;
               }
            } else {
               if ($this->debug) {
                  print_r("\nDefault profile exists, will be assigned automatically\n");
               }
            }

            return $user;
         } catch (\Exception $ex) {
            if ($this->debug) {
               print_r("\nException during user creation:\n");
               print_r($ex->getMessage());
               print_r("\n");
               print_r($ex->getTraceAsString());
               print_r("\n");
            }
            return false;
         }
      }

      if ($this->debug) {
         print_r("\nReached end of findUser() - no user found or created\n");
      }

      return false;
   }

   public function login() {
      $user = $this->findUser();

      if (!$user) {
         return false;
      }

      $this->syncOAuthPhoto($user);

      // Create fake auth
      // phpcs:disable
      /* $auth = new Auth();
      $auth->user = $user;
      $auth->auth_succeded = true;
      $auth->extauth = 1;
      $auth->user_present = 1;
      $auth->user->fields['authtype'] = \Auth::DB_GLPI;

      \Session::init($auth);

      // Return false if the profile is not defined in \Session::init($auth)
      return $auth->auth_succeded; */
      // phpcs:enable

      global $DB;

      $userId = $user->fields['id'];

      // Set a random password for the current user
      $tempPassword = bin2hex(random_bytes(64));
      $DB->update('glpi_users', ['password' => \Auth::getPasswordHash($tempPassword)], ['id' => $userId]);

      // Log-in using the generated password as if you were logging in using the login form
      $auth = new \Auth();
      $authResult = $auth->login($user->fields['name'], $tempPassword);

      // Rollback password change
      $DB->update('glpi_users', ['password' => $user->fields['password']], ['id' => $userId]);

      return $authResult;
   }

   public function linkUser($user_id) {
      $user = new \User();

      if (!$user->getFromDB($user_id)) {
         return false;
      }

      $resource_array = $this->getResourceOwner();

      if (!$resource_array) {
         return false;
      }

      $remote_id = false;
      $id_fields = ['id', 'sub', 'username'];

      foreach ($id_fields as $field) {
         if (isset($resource_array[$field]) && !empty($resource_array[$field])) {
            $remote_id = $resource_array[$field];
            break;
         }
      }

      if (!$remote_id) {
         return false;
      }

      $link = new \PluginSinglesignonProvider_User();

      // Unlink from another user
      $link->deleteByCriteria([
         'plugin_singlesignon_providers_id' => $this->fields['id'],
         'remote_id' => $remote_id,
      ]);

      return $link->add([
         'plugin_singlesignon_providers_id' => $this->fields['id'],
         'users_id' => $user_id,
         'remote_id' => $remote_id,
      ]);
   }


   /**
    * Synchronize picture (photo) of the user.
    *
    * @return string|boolean Filename to be stored in user picture field, false if no picture found
    */
   public function syncOAuthPhoto($user) {
      $token = $this->getAccessToken();
      if (!$token) {
         return false;
      }

      $url = $this->getResourceOwnerDetailsUrl($token);

      $headers = [
         "Authorization:Bearer $token"
      ];

      $headers = \Plugin::doHookFunction("sso:resource_owner_picture", $headers);

      if ($this->debug) {
         print_r("\nsyncOAuthPhoto:\n");
      }

      //get picture content (base64) in Azure
      if (preg_match("/^(?:https?:\/\/)?(?:[^.]+\.)?graph\.microsoft\.com(\/.*)?$/", $url)) {
         array_push($headers, "Content-Type:image/jpeg; charset=utf-8");

         $photo_url = "https://graph.microsoft.com/v1.0/me/photo/\$value";
         $img = \Toolbox::callCurl($photo_url, [
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYHOST => (bool)($this->fields['ssl_verifyhost'] ?? true),
            CURLOPT_SSL_VERIFYPEER => (bool)($this->fields['ssl_verifypeer'] ?? true),
         ]);
         if (!empty($img)) {
            /* if ($this->debug) {
            print_r($content);
            } */

            //prepare paths
            $filename  = uniqid($user->fields['id'] . '_');
            $sub       = substr($filename, -2); /* 2 hex digit */
            $file      = GLPI_PICTURE_DIR . "/{$sub}/{$filename}.jpg";

            if (array_key_exists('picture', $user->fields)) {
               $oldfile = GLPI_PICTURE_DIR . "/" . $user->fields["picture"];
            } else {
               $oldfile = null;
            }

            //update picture if not exist or changed
            if (empty($user->fields["picture"])
               || !file_exists($oldfile)
               || sha1_file($oldfile) !== sha1($img)
            ) {

               if (!is_dir(GLPI_PICTURE_DIR . "/$sub")) {
                  mkdir(GLPI_PICTURE_DIR . "/$sub");
               }

               //save picture
               $outjpeg = fopen($file, 'wb');
               fwrite($outjpeg, $img);
               fclose($outjpeg);

               //save thumbnail
               $thumb = GLPI_PICTURE_DIR . "/{$sub}/{$filename}_min.jpg";
               \Toolbox::resizePicture($file, $thumb);

               $user->fields['picture'] = "{$sub}/{$filename}.jpg";
               $success = $user->updateInDB(['picture']);
               if ($this->debug) {
                  print_r(['id' => $user->getId(),
                           'picture' => "{$sub}/{$filename}.jpg",
                           'success' => $success
                  ]);
               }

               if (!$success) {
                  if ($this->debug) {
                     print_r(false);
                  }
                  return false;
               }

               if ($this->debug) {
                  print_r("{$sub}/{$filename}.jpg");
               }
               return "{$sub}/{$filename}.jpg";
            }
            if ($this->debug) {
               print_r($user->fields["picture"]);
            }
            return $user->fields["picture"];
         }
      }

      if ($this->debug) {
         print_r(false);
      }
      return false;
   }
}
