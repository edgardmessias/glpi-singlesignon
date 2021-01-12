<?php

class PluginSinglesignonProvider extends CommonDBTM {

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

      if ($ID) {
         $url = self::getCallbackUrl($ID);
         $fullUrl = $this->getBaseURL() . $url;
         echo "<tr class='tab_bg_1'>";
         echo "<td>" . __sso('Callback URL') . "</td>";
         echo "<td colspan='3'><a id='singlesignon_callbackurl' href='$fullUrl' data-url='$url'>$fullUrl</a></td>";
         echo "</tr>\n";

         $options['addbuttons'] = ['test_singlesignon' => __sso('Test Single Sign-on')];
      }

      $this->showFormButtons($options);

      if ($ID) {
         echo '<script type="text/javascript">
         $("[name=test_singlesignon]").on("click", function (e) {
            e.preventDefault();

            var url   = $("#singlesignon_callbackurl").attr("data-url") + "/test/1";
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
      // For GLPI <= 9.2
      $options = [];
      foreach ($this->rawSearchOptions() as $opt) {
         if (!isset($opt['id'])) {
            continue;
         }
         $optid = $opt['id'];
         unset($opt['id']);
         if (isset($options[$optid])) {
            $message = "Duplicate key $optid ({$options[$optid]['name']}/{$opt['name']}) in " .
            get_class($this) . " searchOptions!";
            Toolbox::logDebug($message);
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
         'name' => __sso('Client ID'),
         'datatype' => 'text',
      ];

      $tab[] = [
         'id' => 4,
         'table' => $this->getTable(),
         'field' => 'client_secret',
         'name' => __sso('Client Secret'),
         'datatype' => 'text',
      ];

      $tab[] = [
         'id' => 5,
         'table' => $this->getTable(),
         'field' => 'scope',
         'name' => __sso('Scope'),
         'datatype' => 'text',
      ];

      $tab[] = [
         'id' => 6,
         'table' => $this->getTable(),
         'field' => 'extra_options',
         'name' => __sso('Extra Options'),
         'datatype' => 'specific',
      ];

      $tab[] = [
         'id' => 7,
         'table' => $this->getTable(),
         'field' => 'url_authorize',
         'name' => __sso('Authorize URL'),
         'datatype' => 'weblink',
      ];

      $tab[] = [
         'id' => 8,
         'table' => $this->getTable(),
         'field' => 'url_access_token',
         'name' => __sso('Access Token URL'),
         'datatype' => 'weblink',
      ];

      $tab[] = [
         'id' => 9,
         'table' => $this->getTable(),
         'field' => 'url_resource_owner_details',
         'name' => __sso('Resource Owner Details URL'),
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

      $fields = Plugin::doHookFunction("sso:scope", $fields);

      return $fields['scope'];
   }

   public function getAuthorizeUrl() {
      $type = $this->getClientType();

      $value = static::getDefault($type, "url_authorize");

      $fields = $this->fields;

      if (!isset($fields['url_authorize']) || empty($fields['url_authorize'])) {
         $fields['url_authorize'] = $value;
      }

      $fields = Plugin::doHookFunction("sso:url_authorize", $fields);

      return $fields['url_authorize'];
   }

   public function getAccessTokenUrl() {
      $type = $this->getClientType();

      $value = static::getDefault($type, "url_access_token");

      $fields = $this->fields;

      if (!isset($fields['url_access_token']) || empty($fields['url_access_token'])) {
         $fields['url_access_token'] = $value;
      }

      $fields = Plugin::doHookFunction("sso:url_access_token", $fields);

      return $fields['url_access_token'];
   }

   public function getResourceOwnerDetailsUrl($access_token) {
      $type = $this->getClientType();

      $value = static::getDefault($type, "url_resource_owner_details", "");

      $fields = $this->fields;
      $fields['access_token'] = $access_token;

      if (!isset($fields['url_resource_owner_details']) || empty($fields['url_resource_owner_details'])) {
         $fields['url_resource_owner_details'] = $value;
      }

      $fields = Plugin::doHookFunction("sso:url_resource_owner_details", $fields);

      $url = $fields['url_resource_owner_details'];

      $url = str_replace("<access_token>", $access_token, $url);
      $url = str_replace("<appsecret_proof>", hash_hmac('sha256', $access_token, $this->getClientSecret()), $url);

      return $url;
   }

   /**
    * Get current URL without query string
    * @return string
    */
   private function getBaseURL() {
      $baseURL = (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on") ? "https://" : "http://";
      $baseURL .= $_SERVER["SERVER_NAME"];

      if ($_SERVER["SERVER_PORT"] != "80" && $_SERVER["SERVER_PORT"] != "443") {
         $baseURL .= ":" . $_SERVER["SERVER_PORT"];
      }
      return $baseURL;
   }

   /**
    * Get current URL without query string
    * @return string
    */
   private function getCurrentURL() {
      $currentURL = $this->getBaseURL();

      // $currentURL .= $_SERVER["REQUEST_URI"];
      // Ignore Query String
      if (isset($_SERVER["SCRIPT_NAME"])) {
         $currentURL .= $_SERVER["SCRIPT_NAME"];
      }
      if (isset($_SERVER["PATH_INFO"])) {
         $currentURL .= $_SERVER["PATH_INFO"];
      }
      return $currentURL;
   }

   /**
    *
    * @return boolean|string
    */
   public function checkAuthorization() {

      if (isset($_GET['error'])) {

         $error_description = isset($_GET['error_description']) ? $_GET['error_description'] : __("The action you have requested is not allowed.");

         Html::displayErrorAndDie(__($error_description), true);
      }

      if (!isset($_GET['code'])) {

         $params = [
            'client_id' => $this->getClientId(),
            'scope' => $this->getScope(),
            'state' => Session::getNewCSRFToken(),
            'response_type' => 'code',
            'approval_prompt' => 'auto',
            'redirect_uri' => $this->getCurrentURL(),
         ];

         $params = Plugin::doHookFunction("sso:authorize_params", $params);

         $url = $this->getAuthorizeUrl();

         $glue = strstr($url, '?') === false ? '?' : '&';
         $url .= $glue . http_build_query($params);

         header('Location: ' . $url);
         exit;
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
    * @return boolean|string
    */
   public function getAccessToken() {
      if ($this->_token !== null) {
         return $this->_token;
      }

      if ($this->_code === null) {
         return false;
      }

      $params = [
         'client_id' => $this->getClientId(),
         'client_secret' => $this->getClientSecret(),
         'redirect_uri' => $this->getCurrentURL(),
         'grant_type' => 'authorization_code',
         'code' => $this->_code,
      ];

      $params = Plugin::doHookFunction("sso:access_token_params", $params);

      $url = $this->getAccessTokenUrl();

      $content = Toolbox::callCurl($url, [
               CURLOPT_HTTPHEADER => [
                  "Accept: application/json",
               ],
               CURLOPT_POST => true,
               CURLOPT_POSTFIELDS => http_build_query($params),
               CURLOPT_SSL_VERIFYHOST => false,
               CURLOPT_SSL_VERIFYPEER => false,
      ]);

      try {
         $data = json_decode($content, true);
         $this->_token = $data['access_token'];
      } catch (\Exception $ex) {
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

      $headers = Plugin::doHookFunction("sso:resource_owner_header", $headers);

      $content = Toolbox::callCurl($url, [
               CURLOPT_HTTPHEADER => $headers,
               CURLOPT_SSL_VERIFYHOST => false,
               CURLOPT_SSL_VERIFYPEER => false,
      ]);

      try {
         $data = json_decode($content, true);
         $this->_resource_owner = $data;
      } catch (\Exception $ex) {
         return false;
      }

      if ($this->getClientType() === "linkedin") {
         $email_url = "https://api.linkedin.com/v2/emailAddress?q=members&projection=(elements*(handle~))";
         $content = Toolbox::callCurl($email_url, [
                  CURLOPT_HTTPHEADER => $headers,
                  CURLOPT_SSL_VERIFYHOST => false,
                  CURLOPT_SSL_VERIFYPEER => false,
         ]);

         try {
            $data = json_decode($content, true);

            $this->_resource_owner['email-address'] = $data['elements'][0]['handle~']['emailAddress'];
         } catch (\Exception $ex) {
            return false;
         }
      }

      return $this->_resource_owner;
   }

   public function findUser() {
      $resource_array = $this->getResourceOwner();

      if (!$resource_array) {
         return false;
      }

      $user = new User();
      //First: check linked user

      $id = Plugin::doHookFunction("sso:find_user", $resource_array);

      if (is_numeric($id) && $user->getFromDB($id)) {
         return $user;
      }

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
      $login_fields = ['login', 'username', 'id'];

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

   /**
    * Generate a URL to callback
    * Some providers don't accept query string, it convert to PATH
    * @global array $CFG_GLPI
    * @param integer $id
    * @param array $query
    * @return string
    */
   public static function getCallbackUrl($id, $query = []) {
      global $CFG_GLPI;

      $url = $CFG_GLPI['root_doc'] . '/plugins/singlesignon/front/callback.php';

      $url .= "/provider/$id";

      if (!empty($query)) {
         $url .= "/q/" . base64_encode(http_build_query($query));
      }

      return $url;
   }

   public static function getCallbackParameters($name = null) {
      $data = [];

      if (isset($_SERVER['PATH_INFO'])) {
         $path_info = trim($_SERVER['PATH_INFO'], '/');

         $parts = explode('/', $path_info);

         $key = null;

         foreach ($parts as $part) {
            if ($key === null) {
               $key = $part;
            } else {
               if ($key === "provider" || $key === "test") {
                  $part = intval($part);
               } else {
                  $tmp = base64_decode($part);
                  parse_str($tmp, $part);
               }

               if ($key === $name) {
                  return $part;
               }

               $data[$key] = $part;
               $key = null;
            }
         }
      }

      if (!isset($data[$name])) {
         return null;
      }

      return $data;
   }

}
