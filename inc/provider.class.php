<?php

class PluginSinglesignonProvider extends CommonDBTM {

   // From CommonDBTM
   public $dohistory = true;
   static $rightname = 'config';

   /**
    * Provider instance
    * @var null|\League\OAuth2\Client\Provider\GenericProvider
    */
   protected $_provider = null;
   protected $_code = null;

   /**
    *
    * @var null|\League\OAuth2\Client\Token\AccessToken
    */
   protected $_token = null;

   /**
    *
    * @var null|\League\OAuth2\Client\Provider\ResourceOwnerInterface
    */
   protected $_resource_owner = null;

   public static function canCreate() {
      return static::canUpdate();
   }

   public static function canDelete() {
      return static::canUpdate();
   }

   public static function canPurge() {
      return static::canUpdate();
   }

   public static function canView() {
      return static::canUpdate();
   }

   // Should return the localized name of the type
   static function getTypeName($nb = 0) {
      return __sso('Single Sign-on Provider');
   }

   /**
    * @see CommonGLPI::getMenuName()
    * */
   static function getMenuName() {
      return __sso('Single Sign-on');
   }

   /**
    * @see CommonGLPI::getAdditionalMenuLinks()
    * */
   static function getAdditionalMenuLinks() {
      global $CFG_GLPI;
      $links = [];

      //      $links['add'] = '/plugins/singlesignon/front/provider.form.php';
      //      $links['config'] = '/plugins/singlesignon/index.php';
      $links["<img  src='" . $CFG_GLPI["root_doc"] . "/pics/menu_showall.png' title='" . __s('Show all') . "' alt='" . __s('Show all') . "'>"] = '/plugins/singlesignon/index.php';
      $links[__s('Test link', 'singlesignon')] = '/plugins/singlesignon/index.php';

      return $links;
   }

   function defineTabs($options = []) {

      $ong = [];
      $this->addDefaultFormTab($ong);
      $this->addStandardTab(__CLASS__, $ong, $options);
      $this->addStandardTab('Log', $ong, $options);

      return $ong;
   }

   function post_getEmpty() {
      $this->fields["type"] = 'generic';
      $this->fields["is_active"] = 1;
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
      Html::autocompletionTextField($this, "name");
      echo "</td>";
      echo "<td>" . __('Comments') . "</td>";
      echo "<td>";
      echo "<textarea name='comment' >" . $this->fields["comment"] . "</textarea>";
      echo "</td></tr>";

      $on_change = 'var _value = this.options[this.selectedIndex].value; $(".sso_url").toggle(_value == "generic");';

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __sso('SSO Type') . "</td><td>";
      self::dropdownType('type', ['value' => $this->fields["type"], 'on_change' => $on_change]);
      echo "<td>" . __('Active') . "</td>";
      echo "<td>";
      Dropdown::showYesNo("is_active", $this->fields["is_active"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __sso('Client ID') . "</td>";
      echo "<td><input type='text' style='width:96%' name='client_id' value='" . $this->fields["client_id"] . "'></td>";
      echo "<td>" . __sso('Client Secret') . "</td>";
      echo "<td><input type='text' style='width:96%' name='client_secret' value='" . $this->fields["client_secret"] . "'></td>";
      echo "</tr>\n";

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __sso('Scope') . "</td>";
      echo "<td><input type='text' style='width:96%' name='scope' value='" . $this->fields["scope"] . "'></td>";
      echo "<td>" . __sso('Extra Options') . "</td>";
      echo "<td><input type='text' style='width:96%' name='extra_options' value='" . $this->fields["extra_options"] . "'></td>";
      echo "</tr>\n";

      $url_style = "";

      if ($this->fields["type"] != 'generic') {
         $url_style = 'style="display: none;"';
      }

      echo "<tr class='tab_bg_1 sso_url' $url_style>";
      echo "<td>" . __sso('Authorize URL') . "</td>";
      echo "<td colspan='3'><input type='text' style='width:96%' name='url_authorize' value='" . $this->fields["url_authorize"] . "'></td>";
      echo "</tr>\n";

      echo "<tr class='tab_bg_1 sso_url' $url_style>";
      echo "<td>" . __sso('Access Token URL') . "</td>";
      echo "<td colspan='3'><input type='text' style='width:96%' name='url_access_token' value='" . $this->fields["url_access_token"] . "'></td>";
      echo "</tr>\n";

      echo "<tr class='tab_bg_1 sso_url' $url_style>";
      echo "<td>" . __sso('Resource Owner Details URL') . "</td>";
      echo "<td colspan='3'><input type='text' style='width:96%' name='url_resource_owner_details' value='" . $this->fields["url_resource_owner_details"] . "'></td>";
      echo "</tr>\n";

      $this->showFormButtons($options);

      return true;
   }

   function prepareInputForAdd($input) {
      return $this->prepareInput($input);
   }

   function prepareInputForUpdate($input) {
      return $this->prepareInput($input);
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
         $error_detected[] = __sso('A Name is required');
      }

      if (empty($type)) {
         $error_detected[] = __('An item type is required');
      } else if (!isset(static::getTypes()[$type])) {
         $error_detected[] = sprintf(__sso('The "%s" is a Invalid type'), $type);
      }

      if (!isset($input['client_id']) || empty($input['client_id'])) {
         $error_detected[] = __sso('A Client ID is required');
      }

      if (!isset($input['client_secret']) || empty($input['client_secret'])) {
         $error_detected[] = __sso('A Client Secret is required');
      }

      if ($type === 'generic') {
         if (!isset($input['url_authorize']) || empty($input['url_authorize'])) {
            $error_detected[] = __sso('An Authorize URL is required');
         } else if (!filter_var($input['url_authorize'], FILTER_VALIDATE_URL)) {
            $error_detected[] = __sso('The Authorize URL is invalid');
         }

         if (!isset($input['url_access_token']) || empty($input['url_access_token'])) {
            $error_detected[] = __sso('An Access Token URL is required');
         } else if (!filter_var($input['url_access_token'], FILTER_VALIDATE_URL)) {
            $error_detected[] = __sso('The Access Token URL is invalid');
         }

         if (!isset($input['url_resource_owner_details']) || empty($input['url_resource_owner_details'])) {
            $error_detected[] = __sso('A Resource Owner Details URL is required');
         } else if (!filter_var($input['url_resource_owner_details'], FILTER_VALIDATE_URL)) {
            $error_detected[] = __sso('The Resource Owner Details URL is invalid');
         }
      }

      if (count($error_detected)) {
         foreach ($error_detected as $error) {
            Session::addMessageAfterRedirect(
                  $error,
                  true,
                  ERROR
            );
         }
         return false;
      }

      return $input;
   }

   function getSearchOptions() {

      $tab = [];
      $tab['common'] = __('Characteristics');

      $tab[1]['table'] = $this->getTable();
      $tab[1]['field'] = 'type';
      $tab[1]['name'] = __('Type');
      $tab[1]['searchtype'] = 'equals';
      $tab[1]['datatype'] = 'specific';

      $tab[2]['table'] = $this->getTable();
      $tab[2]['field'] = 'name';
      $tab[2]['name'] = __('Name');
      $tab[2]['datatype'] = 'text';

      $tab[3]['table'] = $this->getTable();
      $tab[3]['field'] = 'client_id';
      $tab[3]['name'] = __sso('Client ID');
      $tab[3]['datatype'] = 'text';

      $tab[4]['table'] = $this->getTable();
      $tab[4]['field'] = 'client_secret';
      $tab[4]['name'] = __sso('Client Secret');
      $tab[4]['datatype'] = 'text';

      $tab[5]['table'] = $this->getTable();
      $tab[5]['field'] = 'scope';
      $tab[5]['name'] = __sso('Scope');
      $tab[5]['datatype'] = 'text';

      $tab[6]['table'] = $this->getTable();
      $tab[6]['field'] = 'extra_options';
      $tab[6]['name'] = __sso('Extra Options');
      $tab[6]['datatype'] = 'text';

      $tab[7]['table'] = $this->getTable();
      $tab[7]['field'] = 'url_authorize';
      $tab[7]['name'] = __sso('Authorize URL');
      $tab[7]['datatype'] = 'weblink';

      $tab[8]['table'] = $this->getTable();
      $tab[8]['field'] = 'url_access_token';
      $tab[8]['name'] = __sso('Access Token URL');
      $tab[8]['datatype'] = 'weblink';

      $tab[9]['table'] = $this->getTable();
      $tab[9]['field'] = 'url_resource_owner_details';
      $tab[9]['name'] = __sso('Resource Owner Details URL');
      $tab[9]['datatype'] = 'weblink';

      $tab[10]['table'] = $this->getTable();
      $tab[10]['field'] = 'is_active';
      $tab[10]['name'] = __('Active');
      $tab[10]['searchtype'] = 'equals';
      $tab[10]['datatype'] = 'bool';

      $tab[30]['table'] = $this->getTable();
      $tab[30]['field'] = 'id';
      $tab[30]['name'] = __('ID');

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

      $options['generic'] = __sso('Generic');
      $options['facebook'] = __sso('Facebook');
      $options['github'] = __sso('GitHub');
      $options['google'] = __sso('Google');
      $options['instagram'] = __sso('Instagram');
      $options['linkedin'] = __sso('LinkdeIn');

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

      return Dropdown::showFromArray($name, $items, $params);
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
   static function getHistoryEntry($data) {

      switch ($data['linked_action'] - Log::HISTORY_PLUGIN) {
         case 0:
            return __('History from plugin example', 'example');
      }

      return '';
   }

   //////////////////////////////
   ////// SPECIFIC MODIF MASSIVE FUNCTIONS ///////

   /**
    * @since version 0.85
    *
    * @see CommonDBTM::getSpecificMassiveActions()
    * */
   function getSpecificMassiveActions($checkitem = null) {

      $actions = parent::getSpecificMassiveActions($checkitem);

      $actions['Document_Item' . MassiveAction::CLASS_ACTION_SEPARATOR . 'add'] = _x('button', 'Add a document');         // GLPI core one
      $actions[__CLASS__ . MassiveAction::CLASS_ACTION_SEPARATOR . 'do_nothing'] = __('Do Nothing - just for fun', 'example');  // Specific one

      return $actions;
   }

   /**
    * @since version 0.85
    *
    * @see CommonDBTM::showMassiveActionsSubForm()
    * */
   static function showMassiveActionsSubForm(MassiveAction $ma) {

      switch ($ma->getAction()) {
         case 'DoIt':
            echo "&nbsp;<input type='hidden' name='toto' value='1'>" .
            Html::submit(_x('button', 'Post'), ['name' => 'massiveaction']) .
            " " . __('Write in item history', 'example');
            return true;
         case 'do_nothing' :
            echo "&nbsp;" . Html::submit(_x('button', 'Post'), ['name' => 'massiveaction']) .
            " " . __('but do nothing :)', 'example');
            return true;
      }
      return parent::showMassiveActionsSubForm($ma);
   }

   /**
    * @since version 0.85
    *
    * @see CommonDBTM::processMassiveActionsForOneItemtype()
    * */
   static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item, array $ids) {
      global $DB;

      switch ($ma->getAction()) {
         case 'DoIt' :
            if ($item->getType() == 'Computer') {
               Session::addMessageAfterRedirect(__("Right it is the type I want...", 'example'));
               Session::addMessageAfterRedirect(__('Write in item history', 'example'));
               $changes = [0, 'old value', 'new value'];
               foreach ($ids as $id) {
                  if ($item->getFromDB($id)) {
                     Session::addMessageAfterRedirect("- " . $item->getField("name"));
                     Log::history($id, 'Computer', $changes, 'PluginExampleExample', Log::HISTORY_PLUGIN);
                     $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                  } else {
                     // Example of ko count
                     $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                  }
               }
            } else {
               // When nothing is possible ...
               $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_KO);
            }
            return;

         case 'do_nothing' :
            If ($item->getType() == 'PluginExampleExample') {
               Session::addMessageAfterRedirect(__("Right it is the type I want...", 'example'));
               Session::addMessageAfterRedirect(__("But... I say I will do nothing for:", 'example'));
               foreach ($ids as $id) {
                  if ($item->getFromDB($id)) {
                     Session::addMessageAfterRedirect("- " . $item->getField("name"));
                     $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                  } else {
                     // Example for noright / Maybe do it with can function is better
                     $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                  }
               }
            } else {
               $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_KO);
            }
            Return;
      }
      parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
   }

   /**
    *
    * @param string $type
    * @param array $options
    * @param array $collaborators
    * @return \League\OAuth2\Client\Provider\AbstractProvider
    */
   public static function createInstance($type = 'generic', array $options = [], array $collaborators = []) {

      if (!isset($options['scope'])) {
         $options['scope'] = [];
      }

      switch ($type) {
         case 'facebook':
            if (!isset($options['graphApiVersion'])) {
               $options['graphApiVersion'] = 'v2.12';
            }
            return new League\OAuth2\Client\Provider\Facebook($options, $collaborators);
         case 'github':
            $options['scope'][] = 'user:email';
            return new League\OAuth2\Client\Provider\Github($options, $collaborators);
         case 'google':
            return new League\OAuth2\Client\Provider\Google($options, $collaborators);
         case 'instagram':
            return new League\OAuth2\Client\Provider\Instagram($options, $collaborators);
         case 'linkedin':
            $options['scope'][] = 'r_emailaddress';
            return new League\OAuth2\Client\Provider\LinkedIn($options, $collaborators);
         case 'generic':
         default:
            return new League\OAuth2\Client\Provider\GenericProvider($options, $collaborators);
      }
   }

   private function getCurrentURL() {
      $currentURL = (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on") ? "https://" : "http://";
      $currentURL .= $_SERVER["SERVER_NAME"];

      if ($_SERVER["SERVER_PORT"] != "80" && $_SERVER["SERVER_PORT"] != "443") {
         $currentURL .= ":" . $_SERVER["SERVER_PORT"];
      }

      $currentURL .= $_SERVER["REQUEST_URI"];
      return $currentURL;
   }

   public function prepareProviderInstance(array $options = [], array $collaborators = []) {
      global $CFG_GLPI;

      if ($this->_provider === null) {

         $redirect_uri = $this->getCurrentURL();

         $type = $this->fields['type'];
         $default = [
            'clientId' => $this->fields['client_id'],
            'clientSecret' => $this->fields['client_secret'],
            'redirectUri' => $redirect_uri,
         ];

         if ($type === 'generic') {
            $default['urlAuthorize'] = $this->fields['url_authorize'];
            $default['urlAccessToken'] = $this->fields['url_accessToken'];
            $default['urlResourceOwnerDetails'] = $this->fields['url_resource_owner_details'];
         }

         if (!empty($this->fields['extra_options'])) {
            try {
               $extra = json_decode($this->fields['extra_options'], true);
            } catch (Exception $ex) {
               $extra = [];
            }

            if (!empty($extra)) {
               $default = array_merge($default, $extra);
            }
         }
         $options = array_merge($default, $options);

         $this->_provider = self::createInstance($type, $options, $collaborators);
      }
      return $this->_provider;
   }

   /**
    *
    * @return boolean|string
    */
   public function checkAuthorization() {
      if ($this->_provider === null) {
         return false;
      }

      if (!isset($_GET['code'])) {

         $scope = [];
         if (!empty($this->fields['scope'])) {
            $scope = explode(',', $this->fields['scope']);
         }

         $options = [
            'scope' => $scope,
            'state' => Session::getNewCSRFToken(),
         ];

         $this->_provider->authorize($options);
      }

      // Check given state against previously stored one to mitigate CSRF attack
      $state = isset($_GET['state']) ? $_GET['state'] : '';

      Session::checkCSRF([
         '_glpi_csrf_token' => $state,
      ]);

      $this->_code = $_GET['code'];

      return $_GET['code'];
   }

   /**
    *
    * @return boolean|\League\OAuth2\Client\Token\AccessToken
    */
   public function getAccessToken() {
      if ($this->_token !== null) {
         return $this->_token;
      }

      if ($this->_provider === null || $this->_code === null) {
         return false;
      }

      $this->_token = $this->_provider->getAccessToken('authorization_code', [
         'code' => $this->_code
      ]);

      return $this->_token;
   }

   /**
    *
    * @return boolean|\League\OAuth2\Client\Provider\ResourceOwnerInterface
    */
   public function getResourceOwner() {
      if ($this->_resource_owner !== null) {
         return $this->_resource_owner;
      }

      $token = $this->getAccessToken();
      if (!$token) {
         return false;
      }

      $this->_resource_owner = $this->_provider->getResourceOwner($token);

      return $this->_resource_owner;
   }

   public function findUser() {
      $resource = $this->getResourceOwner();

      $resource_array = $resource->toArray();

      $user = new User();
      //First: check linked user

      $email = false;
      $email_fields = ['email', 'e-mail', 'email-address'];

      foreach ($email_fields as $field) {
         if (isset($resource_array[$field]) && is_string($resource_array[$field])) {
            $email = $resource_array[$field];
            break;
         }
      }

      $default_condition = '';

      if (version_compare(GLPI_VERSION, '9.3', '>=')) {
         $default_condition = [];
      }

      if ($email && $user->getFromDBbyEmail($email, $default_condition)) {
         return $user;
      }

      $login = false;
      $login_fields = ['login', 'username'];

      foreach ($login_fields as $field) {
         if (isset($resource_array[$field]) && is_string($resource_array[$field])) {
            $login = $resource_array[$field];
            break;
         }
      }

      if ($login && $user->getFromDBbyName($login)) {
         return $user;
      }

      return false;
   }

   public function login() {
      $user = $this->findUser();

      if (!$user) {
         return false;
      }

      //Create fake auth
      $auth = new Auth();
      $auth->user = $user;
      $auth->auth_succeded = true;
      $auth->extauth = 1;
      $auth->user_present = $auth->user->getFromDBbyName(addslashes($user->fields['name']));
      $auth->user->fields['authtype'] = Auth::DB_GLPI;

      Session::init($auth);

      return $auth->auth_succeded;
   }

}
