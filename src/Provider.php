<?php

/**
 * ---------------------------------------------------------------------
 * SingleSignOn is a plugin which allows to use SSO for auth
 * ---------------------------------------------------------------------
 * Copyright (C) 2026 Edgard
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 * @copyright Copyright © 2021 - 2026 Edgard
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @link      https://github.com/edgardmessias/glpi-singlesignon/
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\Singlesignon;

use Throwable;
use CommonDBTM;
use JsonPath\JsonObject;
use Session;
use Dropdown;
use Toolbox;
use Plugin;
use Glpi\Exception\Http\BadRequestHttpException;
use Exception;
use User;
use Profile;
use Profile_User;
use Auth;
use Glpi\Application\View\TemplateRenderer;

use function Safe\file_get_contents;
use function Safe\fclose;
use function Safe\fopen;
use function Safe\fwrite;
use function Safe\json_decode;
use function Safe\mkdir;
use function Safe\parse_url;
use function Safe\preg_match;
use function Safe\preg_split;
use function Safe\sha1_file;

class Provider extends CommonDBTM
{
    // From CommonDBTM
    public $dohistory = true;
    public static $rightname = 'config';

    /**
     * @var array
     */
    public static $default = null;

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

    public static function canCreate(): bool
    {
        return static::canUpdate();
    }

    public static function canDelete(): bool
    {
        return static::canUpdate();
    }

    public static function canPurge(): bool
    {
        return static::canUpdate();
    }

    public static function canView(): bool
    {
        return static::canUpdate();
    }

    // Should return the localized name of the type
    public static function getTypeName($nb = 0)
    {
        return __('Single Sign-on Provider', 'singlesignon');
    }

    /**
     * @see \CommonGLPI::getMenuName()
     * */
    public static function getMenuName()
    {
        return __('Single Sign-on', 'singlesignon');
    }

    public function defineTabs($options = [])
    {

        $ong = [];
        $this->addDefaultFormTab($ong);
        $this->addStandardTab(self::class, $ong, $options);
        $this->addStandardTab(Provider_Field::class, $ong, $options);
        $this->addStandardTab('Log', $ong, $options);

        return $ong;
    }

    public function post_getEmpty()
    {
        $this->fields["type"] = 'generic';
        $this->fields["is_active"] = 1;
    }

    public function showForm($ID, $options = [])
    {
        $this->initForm($ID, $options);

        if (empty($this->fields["type"])) {
            $this->fields["type"] = 'generic';
        }

        $renderer = TemplateRenderer::getInstance();
        echo $renderer->render('@singlesignon/provider/show_form.html.twig', [
            'provider'     => $this,
            'provider_id'  => (int) $ID,
            'form_action'  => $this->getFormURL(),
        ]);

        if ($ID) {
            echo $renderer->render('@singlesignon/provider/show_form_test_script.html.twig', []);
        }

        return true;
    }

    public function prepareInputForAdd($input)
    {
        return $this->prepareInput($input);
    }

    public function prepareInputForUpdate($input)
    {
        return $this->prepareInput($input);
    }

    public function post_addItem()
    {
        if (($this->fields['type'] ?? '') !== 'generic') {
            return;
        }

        $providerId = (int) ($this->fields['id'] ?? 0);
        if ($providerId <= 0) {
            return;
        }

        $existing = Provider_Field::getMappingsForProvider($providerId);
        if ($existing !== []) {
            return;
        }

        $mapping = new Provider_Field();
        foreach (Provider_Field::getDefaultMappings('generic') as $default) {
            $mapping->add([
                'plugin_singlesignon_providers_id' => $providerId,
                'field_type'                       => $default['field_type'],
                'jsonpath'                         => $default['jsonpath'],
                'is_active'                        => (int) $default['is_active'],
                'sort_order'                       => (int) $default['sort_order'],
            ]);
        }
    }

    public function cleanDBonPurge()
    {
        Toolbox::deletePicture($this->fields['picture']);
        $this->deleteChildrenAndRelationsFromDb(
            [
                'PluginSinglesignonProvider_User',
                'PluginSinglesignonProvider_Field',
            ],
        );
    }

    /**
     * Prepares input (for update and add)
     *
     * @param array $input Input data
     *
     * @return array|bool
     */
    private function prepareInput($input)
    {
        $error_detected = [];

        $type = '';
        //check for requirements
        if (isset($input['type'])) {
            $type = $input['type'];
        }

        if (!isset($input['name']) || empty($input['name'])) {
            $error_detected[] = __s('A Name is required', 'singlesignon');
        }

        if (empty($type)) {
            $error_detected[] = __s('An item type is required');
        } elseif (!isset(static::getTypes()[$type])) {
            $error_detected[] = sprintf(__s('The "%s" is a Invalid type', 'singlesignon'), $type);
        }

        if (!isset($input['client_id']) || empty($input['client_id'])) {
            $error_detected[] = __s('A Client ID is required', 'singlesignon');
        }

        if (!isset($input['client_secret']) || empty($input['client_secret'])) {
            $error_detected[] = __s('A Client Secret is required', 'singlesignon');
        }

        if ($type === 'generic') {
            if (!isset($input['url_authorize']) || empty($input['url_authorize'])) {
                $error_detected[] = __s('An Authorize URL is required', 'singlesignon');
            } elseif (!filter_var($input['url_authorize'], FILTER_VALIDATE_URL)) {
                $error_detected[] = __s('The Authorize URL is invalid', 'singlesignon');
            }

            if (!isset($input['url_access_token']) || empty($input['url_access_token'])) {
                $error_detected[] = __s('An Access Token URL is required', 'singlesignon');
            } elseif (!filter_var($input['url_access_token'], FILTER_VALIDATE_URL)) {
                $error_detected[] = __s('The Access Token URL is invalid', 'singlesignon');
            }

            if (!isset($input['url_resource_owner_details']) || empty($input['url_resource_owner_details'])) {
                $error_detected[] = __s('A Resource Owner Details URL is required', 'singlesignon');
            } elseif (!filter_var($input['url_resource_owner_details'], FILTER_VALIDATE_URL)) {
                $error_detected[] = __s('The Resource Owner Details URL is invalid', 'singlesignon');
            }
        }

        if (count($error_detected)) {
            foreach ($error_detected as $error) {
                Session::addMessageAfterRedirect(
                    $error,
                    true,
                    ERROR,
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
                ToolboxPlugin::deletePicture($this->fields['picture']);
            }
        }

        if (isset($input["_picture"])) {
            $picture = array_shift($input["_picture"]);

            if ($dest = ToolboxPlugin::savePicture(GLPI_TMP_DIR . '/' . $picture)) {
                $input['picture'] = $dest;
            } else {
                Session::addMessageAfterRedirect(__s('Unable to save picture file.'), true, ERROR);
            }

            if (array_key_exists('picture', $this->fields)) {
                ToolboxPlugin::deletePicture($this->fields['picture']);
            }
        }

        return $input;
    }

    public function getSearchOptions()
    {
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
                Toolbox::logDebug($message);
            }
            foreach ($opt as $k => $v) {
                $options[$optid][$k] = $v;
            }
        }
        return $options;
    }

    public function rawSearchOptions()
    {
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
            'name' => __('Client ID', 'singlesignon'),
            'datatype' => 'text',
        ];

        $tab[] = [
            'id' => 4,
            'table' => $this->getTable(),
            'field' => 'client_secret',
            'name' => __('Client Secret', 'singlesignon'),
            'datatype' => 'text',
        ];

        $tab[] = [
            'id' => 5,
            'table' => $this->getTable(),
            'field' => 'scope',
            'name' => __('Scope', 'singlesignon'),
            'datatype' => 'text',
        ];

        $tab[] = [
            'id' => 6,
            'table' => $this->getTable(),
            'field' => 'extra_options',
            'name' => __('Extra Options', 'singlesignon'),
            'datatype' => 'specific',
        ];

        $tab[] = [
            'id' => 7,
            'table' => $this->getTable(),
            'field' => 'url_authorize',
            'name' => __('Authorize URL', 'singlesignon'),
            'datatype' => 'weblink',
        ];

        $tab[] = [
            'id' => 8,
            'table' => $this->getTable(),
            'field' => 'url_access_token',
            'name' => __('Access Token URL', 'singlesignon'),
            'datatype' => 'weblink',
        ];

        $tab[] = [
            'id' => 9,
            'table' => $this->getTable(),
            'field' => 'url_resource_owner_details',
            'name' => __('Resource Owner Details URL', 'singlesignon'),
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

    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {

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

    public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = [])
    {

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
    public static function getTypes()
    {

        $options['generic'] = __('Generic', 'singlesignon');
        $options['azure'] = __('Azure', 'singlesignon');
        $options['facebook'] = __('Facebook', 'singlesignon');
        $options['github'] = __('GitHub', 'singlesignon');
        $options['google'] = __('Google', 'singlesignon');
        $options['instagram'] = __('Instagram', 'singlesignon');
        $options['linkedin'] = __('LinkdeIn', 'singlesignon');

        return $options;
    }

    /**
     * Get ticket type Name
     *
     * @param $value type ID
     * */
    public static function getTicketTypeName($value)
    {
        $tab = static::getTypes();
        // Return $value if not defined
        return ($tab[$value] ?? $value);
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
    public static function dropdownType($name, $options = [])
    {

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
             echo "&nbsp;<input type='hidden' name='toto' value='1'>" . \Html::submit(_x('button', 'Post'), ['name' => 'massiveaction']) . " " . __s('Write in item history', 'example');
             return true;
          case 'do_nothing':
             echo "&nbsp;" . \Html::submit(_x('button', 'Post'), ['name' => 'massiveaction']) . " " . __s('but do nothing :)', 'example');
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

    public static function getIcon()
    {
        return 'ti ti-lock';
    }

    public static function getDefault($type, $key, $default = null)
    {
        if (static::$default === null) {
            $content = file_get_contents(__DIR__ . '/../providers.json');
            static::$default = json_decode($content, true);
        }

        if (isset(static::$default[$type]) && array_key_exists($key, static::$default[$type])) {
            return static::$default[$type][$key];
        }

        return $default;
    }

    public function getClientType()
    {
        $value = "generic";

        if (isset($this->fields['type']) && !empty($this->fields['type'])) {
            $value = $this->fields['type'];
        }

        return $value;
    }

    public function getClientId()
    {
        $value = "";

        if (isset($this->fields['client_id']) && !empty($this->fields['client_id'])) {
            $value = $this->fields['client_id'];
        }

        return $value;
    }

    public function getClientSecret()
    {
        $value = "";

        if (isset($this->fields['client_secret']) && !empty($this->fields['client_secret'])) {
            $value = $this->fields['client_secret'];
        }

        return $value;
    }

    public function getScope()
    {
        $type = $this->getClientType();

        $value = static::getDefault($type, "scope");

        $fields = $this->fields;

        if (!isset($fields['scope']) || empty($fields['scope'])) {
            $fields['scope'] = $value;
        }

        $fields = Plugin::doHookFunction("sso:scope", $fields);

        return $fields['scope'];
    }

    public function getExtraOptions()
    {
        if (isset($this->fields['extra_options']) && !empty($this->fields['extra_options'])) {
            // e.g. 'response_type=code&approval_prompt=auto'
            parse_str($this->fields['extra_options'], $value);
            // $value['response_type'] = 'code'
        } else {
            return false;
        }

        return $value;
    }

    public function getAuthorizeUrl()
    {
        $type = $this->getClientType();

        $value = static::getDefault($type, "url_authorize");

        $fields = $this->fields;

        if (!isset($fields['url_authorize']) || empty($fields['url_authorize'])) {
            $fields['url_authorize'] = $value;
        }

        $fields = Plugin::doHookFunction("sso:url_authorize", $fields);

        return $fields['url_authorize'];
    }

    public function getAccessTokenUrl()
    {
        $type = $this->getClientType();

        $value = static::getDefault($type, "url_access_token");

        $fields = $this->fields;

        if (!isset($fields['url_access_token']) || empty($fields['url_access_token'])) {
            $fields['url_access_token'] = $value;
        }

        $fields = Plugin::doHookFunction("sso:url_access_token", $fields);

        return $fields['url_access_token'];
    }

    public function getResourceOwnerDetailsUrl($access_token = null)
    {
        $type = $this->getClientType();

        $value = static::getDefault($type, "url_resource_owner_details", "");

        $fields = $this->fields;
        $fields['access_token'] = $access_token;

        if (!isset($fields['url_resource_owner_details']) || empty($fields['url_resource_owner_details'])) {
            $fields['url_resource_owner_details'] = $value;
        }

        $fields = Plugin::doHookFunction("sso:url_resource_owner_details", $fields);

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
    public function checkAuthorization()
    {

        if (isset($_GET['error'])) {

            $error_description = $_GET['error_description'] ?? __("The action you have requested is not allowed.");

            $exception = new BadRequestHttpException();
            $exception->setMessageToDisplay(__($error_description));
            throw $exception;
        }

        if (!isset($_GET['code'])) {
            if (session_status() === PHP_SESSION_NONE) {
                Session::start();
            }

            // Generate CSRF token for OAuth state parameter and remember redirect in session
            $state = Session::getNewCSRFToken();

            if (isset($_SESSION['redirect'])) {
                $_SESSION['glpi_singlesignon_redirect'] = $_SESSION['redirect'];
            } elseif (isset($_GET['redirect'])) {
                $_SESSION['glpi_singlesignon_redirect'] = $_GET['redirect'];
            }

            // Build the callback URL for OAuth redirect
            $callback_url = ToolboxPlugin::getBaseURL() . ToolboxPlugin::getCallbackUrl($this->fields['id']);

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

            $params = Plugin::doHookFunction("sso:authorize_params", $params);

            $url = $this->getAuthorizeUrl();

            $glue = !str_contains($url, '?') ? '?' : '&';
            $url .= $glue . http_build_query($params);

            header('Location: ' . $url);
            return false;
        }

        // Extract state parameter
        $state = $_GET['state'] ?? '';

        // Validate state against stored CSRF token
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
    public function getAccessToken()
    {
        if ($this->_token !== null) {
            return $this->_token;
        }

        if ($this->_code === null) {
            return false;
        }

        // Build the callback URL for OAuth redirect (must match the one sent in authorization request)
        $callback_url = ToolboxPlugin::getBaseURL() . ToolboxPlugin::getCallbackUrl($this->fields['id']);

        $params = [
            'client_id' => $this->getClientId(),
            'client_secret' => $this->getClientSecret(),
            'redirect_uri' => $callback_url,
            'grant_type' => 'authorization_code',
            'code' => $this->_code,
        ];

        $params = Plugin::doHookFunction("sso:access_token_params", $params);

        $url = $this->getAccessTokenUrl();

        $msgerr = null;
        $content = Toolbox::callCurl($url, [
            CURLOPT_HTTPHEADER => [
                "Accept: application/json",
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
        ], $msgerr);

        if ($msgerr) {
            print_r("\ngetAccessToken error: " . $msgerr . "\n");
            return false;
        }

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
        } catch (Exception $ex) {
            if ($this->debug) {
                print_r("\ngetAccessToken exception: " . $ex->getMessage() . "\n");
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
    public function getResourceOwner()
    {
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

        if ($this->debug) {
            print_r("\ngetResourceOwner:\n");
        }

        try {
            $data = json_decode($content, true);
            if ($this->debug) {
                print_r($data);
            }
            $this->_resource_owner = $data;
        } catch (Exception $ex) {
            if ($this->debug) {
                print_r($content);
            }
            return false;
        }

        if ($this->getClientType() === "linkedin") {
            if ($this->debug) {
                print_r("\nlinkedin:\n");
            }
            $email_url = "https://api.linkedin.com/v2/emailAddress?q=members&projection=(elements*(handle~))";
            $content = Toolbox::callCurl($email_url, [
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);

            try {
                $data = json_decode($content, true);
                if ($this->debug) {
                    print_r($content);
                }

                $this->_resource_owner['email-address'] = $data['elements'][0]['handle~']['emailAddress'];
            } catch (Exception $ex) {
                return false;
            }
        }

        return $this->_resource_owner;
    }

    private function getFallbackFieldMappingsByType(string $fieldType): array
    {
        $providerType = $this->getClientType();
        $defaults = Provider_Field::getDefaultMappings($providerType);
        if ($defaults === []) {
            $defaults = Provider_Field::getDefaultMappings('generic');
        }

        return array_values(array_filter(
            $defaults,
            static fn(array $row): bool => $row['field_type'] === $fieldType && $row['is_active'] === 1,
        ));
    }

    private function getResourceOwnerValueByJsonPath(array $resourceArray, string $jsonPath): ?string
    {
        try {
            $json = new JsonObject($resourceArray);
            $result = $json->get($jsonPath);
        } catch (Throwable) {
            return null;
        }

        return $this->normalizeJsonPathResult($result);
    }

    private function normalizeJsonPathResult($result): ?string
    {
        if (is_string($result)) {
            return trim($result) !== '' ? $result : null;
        }

        if (is_numeric($result)) {
            return (string) $result;
        }

        if (!is_array($result)) {
            return null;
        }

        foreach ($result as $value) {
            $normalized = $this->normalizeJsonPathResult($value);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    private function resolveFieldValueFromMappings(array $resourceArray, string $fieldType): ?string
    {
        $result = $this->resolveFieldDebugDetailsFromMappings($resourceArray, $fieldType);
        return $result['value'];
    }

    /**
     * @return array{value: ?string, jsonpath: ?string, source: ?string}
     */
    private function resolveFieldDebugDetailsFromMappings(array $resourceArray, string $fieldType): array
    {
        $providerId = (int) ($this->fields['id'] ?? 0);
        $mappings = [];
        if ($providerId > 0) {
            $mappings = Provider_Field::getMappingsForProvider($providerId, $fieldType, true);
        }

        foreach ($mappings as $mapping) {
            $jsonPath = trim((string) ($mapping['jsonpath'] ?? ''));
            if ($jsonPath === '') {
                continue;
            }

            $value = $this->getResourceOwnerValueByJsonPath($resourceArray, $jsonPath);
            if ($value !== null) {
                return [
                    'value'    => $value,
                    'jsonpath' => $jsonPath,
                    'source'   => 'provider',
                ];
            }
        }

        // Fallback defaults are only used when active mappings do not return a value.
        foreach ($this->getFallbackFieldMappingsByType($fieldType) as $mapping) {
            $jsonPath = trim((string) ($mapping['jsonpath'] ?? ''));
            if ($jsonPath === '') {
                continue;
            }

            $value = $this->getResourceOwnerValueByJsonPath($resourceArray, $jsonPath);
            if ($value !== null) {
                return [
                    'value'    => $value,
                    'jsonpath' => $jsonPath,
                    'source'   => 'default',
                ];
            }
        }

        return [
            'value'    => null,
            'jsonpath' => null,
            'source'   => null,
        ];
    }

    /**
     * @return array<string, array{value: ?string, jsonpath: ?string, source: ?string}>
     */
    public function getResolvedFieldsForDebug(array $resourceArray): array
    {
        $resolved = [];
        foreach (array_keys(Provider_Field::getFieldTypes()) as $fieldType) {
            $resolved[$fieldType] = $this->resolveFieldDebugDetailsFromMappings($resourceArray, $fieldType);
        }

        return $resolved;
    }

    private function checkAuthorizedDomain(string $value, array $authorizedDomains): bool
    {
        if ($authorizedDomains === []) {
            return true;
        }

        foreach ($authorizedDomains as $authorizedDomain) {
            if (preg_match("/{$authorizedDomain}$/i", $value) !== 0) {
                return true;
            }
        }

        return false;
    }

    private function splitIdentifierByDomain(string $value, bool $split): string
    {
        if (!$split) {
            return $value;
        }

        $parts = explode("@", $value);
        return $parts[0] ?? $value;
    }

    public function findUser()
    {
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

        $remote_id = $this->resolveFieldValueFromMappings($resource_array, 'id');

        if ($remote_id) {
            $link = new Provider_User();
            $condition = "`remote_id` = '{$remote_id}' AND `plugin_singlesignon_providers_id` = {$this->fields['id']}";
            if (version_compare(GLPI_VERSION, '9.4', '>=')) {
                $condition = [$condition];
            }
            $links = $link->find($condition);
            if (!empty($links) && $first = reset($links)) {
                $id = $first['users_id'];
            }
        }

        if (is_numeric($id) && $user->getFromDB($id)) {
            return $user;
        }

        $split = $this->fields['split_domain'];
        $authorizedDomainsString = $this->fields['authorized_domains'];
        $authorizedDomains = [];
        if (isset($authorizedDomainsString)) {
            $authorizedDomains = explode(',', $authorizedDomainsString);
        }

        // check email first
        $email = $this->resolveFieldValueFromMappings($resource_array, 'email');
        if ($email !== null) {
            if (!$this->checkAuthorizedDomain($email, $authorizedDomains)) {
                return false;
            }
            $email = $this->splitIdentifierByDomain($email, (bool) $split);
        }

        $login = false;
        $use_email = $this->fields['use_email_for_login'];
        if ($email && $use_email) {
            $login = $email;
        } else {
            $login = $this->resolveFieldValueFromMappings($resource_array, 'username');
            if ($login !== null) {
                if (!$this->checkAuthorizedDomain($login, $authorizedDomains)) {
                    return false;
                }
                $login = $this->splitIdentifierByDomain($login, (bool) $split);
            }
        }

        if ($login && $user->getFromDBbyName($login)) {
            return $user;
        }

        $default_condition = '';

        if (version_compare(GLPI_VERSION, '9.3', '>=')) {
            $default_condition = [];
        }

        $bOk = true;
        if ($email && $user->getFromDBbyEmail($email, $default_condition)) {
            return $user;
        } else {
            $bOk = false;
        }

        // var_dump($bOk);
        // die();

        // If the user does not exist in the database and the provider is google
        if (static::getClientType() == "google" && !$bOk) {
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
                'is_active' => 1,
            ];
            $userPost['_useremails'][-1] = $useremail;
            $user->add($userPost);
            return $user;
        }

        // If the user does not exist in the database and the provider is generic (Ex: azure ad without common tenant)
        if (static::getClientType() == "generic" && !$bOk) {
            try {
                // Generates an api token and a personal token... probably not necessary
                $tokenAPI = base_convert(hash('sha256', time() . mt_rand()), 16, 36);
                $tokenPersonnel = base_convert(hash('sha256', time() . mt_rand()), 16, 36);

                $splitname = $this->fields['split_name'];
                $firstLastArray = ($splitname) ? preg_split('/ /', $resource_array['name'], 2) : preg_split('/ /', $resource_array['displayName'], 2);

                $userPost = [
                    'name' => $login,
                    'add' => 1,
                    'password' => '',
                    'realname' => $firstLastArray[1],
                    'firstname' => $firstLastArray[0],
                    'api_token' => $tokenAPI,
                    'api_token_date' => date("Y-m-d H:i:s"),
                    'personal_token' => $tokenPersonnel,
                    'is_active' => 1,
                ];

                // Set the office location from Office 365 user as entity for the GLPI new user if they names match
                if (isset($resource_array['officeLocation'])) {
                    global $DB;
                    foreach ($DB->request(['FROM' => 'glpi_entities']) as $entity) {
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

                // var_dump($newID);

                $profils = 0;
                // Verification default profiles exist in the entity
                // If no default profile exists, the user will not be able to log in.
                // In this case, we retrieve a profile and an entity and assign these values ​​to it.
                // The administrator can change these values ​​later.
                if (0 == Profile::getDefault()) {
                    // No default profiles
                    // Profile recovery and assignment
                    global $DB;

                    $datasProfiles = [];
                    foreach ($DB->request(['FROM' => 'glpi_profiles']) as $data) {
                        $datasProfiles[] = $data;
                    }
                    $datasEntities = [];
                    foreach ($DB->request(['FROM' => 'glpi_entities']) as $data) {
                        $datasEntities[] = $data;
                    }
                    if (count($datasProfiles) > 0 && count($datasEntities) > 0) {
                        $profils = $datasProfiles[0]['id'];
                        $entitie = $datasEntities[0]['id'];

                        $profile   = new Profile_User();
                        $userProfile['users_id'] = intval($user->fields['id']);
                        $userProfile['entities_id'] = intval($entitie);
                        $userProfile['is_recursive'] = 0;
                        $userProfile['profiles_id'] = intval($profils);
                        $userProfile['add'] = "Ajouter";
                        $profile->add($userProfile);
                    } else {
                        return false;
                    }
                }

                return $user;
            } catch (Exception $ex) {
                return false;
            }
        }

        return false;
    }

    public function login()
    {
        $user = $this->findUser();

        if (!$user) {
            return false;
        }

        try {
            $this->syncOAuthPhoto($user);
        } catch (Exception $ex) {
            if ($this->debug) {
                print_r("\nsyncOAuthPhoto exception: " . $ex->getMessage() . "\n");
            }
        }

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
        $DB->update('glpi_users', ['password' => Auth::getPasswordHash($tempPassword)], ['id' => $userId]);

        // Log-in using the generated password as if you were logging in using the login form
        $auth = new Auth();
        $authResult = $auth->login($user->fields['name'], $tempPassword);

        // Rollback password change
        $DB->update('glpi_users', ['password' => $user->fields['password']], ['id' => $userId]);

        return $authResult;
    }

    public function linkUser($user_id)
    {
        $user = new User();

        if (!$user->getFromDB($user_id)) {
            return false;
        }

        $resource_array = $this->getResourceOwner();

        if (!$resource_array) {
            return false;
        }

        $remote_id = $this->resolveFieldValueFromMappings($resource_array, 'id');

        if (!$remote_id) {
            return false;
        }

        $link = new Provider_User();

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
    public function syncOAuthPhoto($user)
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return false;
        }

        $resourceOwner = $this->getResourceOwner();

        if (!$resourceOwner) {
            return false;
        }

        // 1) Prefer dynamic avatar_url mapping (provider mapping + defaults).
        $avatarUrl = $this->resolveFieldValueFromMappings($resourceOwner, 'avatar_url');

        if ($avatarUrl !== null) {
            $avatarUrl = trim($avatarUrl);
        }

        $img = null;
        if (!empty($avatarUrl) && filter_var($avatarUrl, FILTER_VALIDATE_URL)) {
            $img = $this->fetchOAuthPhotoContent(
                $avatarUrl,
                $this->buildOAuthPhotoHeaders($token),
            );
        }

        // 2) Fallback for Microsoft Graph resource owner when avatar_url is not mapped.
        $resourceOwnerUrl = $this->getResourceOwnerDetailsUrl($token);
        if (empty($img) && empty($avatarUrl) && $this->isMicrosoftGraphResourceOwnerUrl($resourceOwnerUrl)) {
            $graphPhotoUrl = $this->buildMicrosoftGraphPhotoUrl($resourceOwnerUrl);
            if ($graphPhotoUrl !== null) {
                $img = $this->fetchOAuthPhotoContent(
                    $graphPhotoUrl,
                    $this->buildOAuthPhotoHeaders($token),
                );
            }
        }

        if (empty($img)) {
            return false;
        }

        $picture = $this->storeOAuthPhoto($user, $img);
        if ($this->debug) {
            print_r($picture ?: false);
        }
        return $picture;
    }

    /**
     * Build standard headers used to fetch avatar binary content from OAuth providers.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9110.html#name-field-accept
     * @see https://www.rfc-editor.org/rfc/rfc6750
     */
    private function buildOAuthPhotoHeaders(string $token): array
    {
        $headers = [
            "Accept:image/*",
            "Authorization:Bearer $token",
        ];

        $headers = Plugin::doHookFunction("sso:resource_owner_picture", $headers);

        return $headers;
    }

    /**
     * Fetch remote avatar content and ensure the response payload is really an image.
     * Some providers return JSON on auth/scope errors, which must be ignored here.
     *
     * @see https://www.php.net/manual/en/function.getimagesizefromstring.php
     * @see https://learn.microsoft.com/graph/api/profilephoto-get
     */
    private function fetchOAuthPhotoContent(string $url, array $headers): ?string
    {
        $img = Toolbox::callCurl($url, [
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        if (!is_string($img) || $img === '') {
            return null;
        }

        // Some providers may return JSON (errors, missing scope, etc.). Only accept valid image payloads.
        if (@getimagesizefromstring($img) === false) {
            return null;
        }

        return $img;
    }

    /**
     * Check whether the resource owner endpoint belongs to Microsoft Graph.
     *
     * @see https://learn.microsoft.com/graph/overview
     */
    private function isMicrosoftGraphResourceOwnerUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        return preg_match("/^(?:https?:\/\/)?(?:[^.]+\.)?graph\.microsoft\.com(\/.*)?$/", $url) !== 0;
    }

    /**
     * Build Microsoft Graph profile photo endpoint from the configured resource owner URL.
     * Example: /v1.0/me -> /v1.0/me/photo/$value, /v1.0/users/{id} -> /v1.0/users/{id}/photo/$value
     *
     * @see https://learn.microsoft.com/graph/api/profilephoto-get
     */
    private function buildMicrosoftGraphPhotoUrl(string $resourceOwnerUrl): ?string
    {
        $parts = parse_url($resourceOwnerUrl);
        if (!is_array($parts) || !isset($parts['host'])) {
            return null;
        }

        $host = strtolower((string) $parts['host']);
        if ($host === '' || !str_ends_with($host, 'graph.microsoft.com')) {
            return null;
        }

        $scheme = (string) ($parts['scheme'] ?? 'https');
        $path = (string) ($parts['path'] ?? '/v1.0/me');
        $path = rtrim($path, '/');
        if ($path === '') {
            $path = '/v1.0/me';
        }

        if (!str_ends_with($path, '/photo/$value')) {
            $path .= '/photo/$value';
        }

        return sprintf('%s://%s%s', $scheme, $host, $path);
    }

    /**
     * Save picture and update user.picture according to GLPI flow.
     *
     * - writes file in GLPI picture directory
     * - generates thumbnail with `_min` suffix
     * - updates `glpi_users.picture`
     * - removes previous picture after successful update
     *
     * @see https://github.com/glpi-project/glpi/blob/master/src/User.php
     * @return string|bool
     */
    private function storeOAuthPhoto(User $user, string $img)
    {
        $oldPicture = (string) ($user->fields['picture'] ?? '');
        $oldFile = $oldPicture !== '' ? GLPI_PICTURE_DIR . '/' . $oldPicture : null;

        if (
            $oldPicture !== ''
            && is_string($oldFile)
            && file_exists($oldFile)
            && sha1_file($oldFile) === sha1($img)
        ) {
            return $oldPicture;
        }

        $filename = uniqid($user->fields['id'] . '_');
        $sub = substr($filename, -2); /* 2 hex digit */
        $file = GLPI_PICTURE_DIR . "/{$sub}/{$filename}.jpg";
        $thumb = GLPI_PICTURE_DIR . "/{$sub}/{$filename}_min.jpg";
        $newPicture = "{$sub}/{$filename}.jpg";

        if (!is_dir(GLPI_PICTURE_DIR . "/$sub")) {
            mkdir(GLPI_PICTURE_DIR . "/$sub");
        }

        $outjpeg = fopen($file, 'wb');
        fwrite($outjpeg, $img);
        fclose($outjpeg);
        Toolbox::resizePicture($file, $thumb);

        $user->fields['picture'] = $newPicture;
        $success = $user->updateInDB(['picture']);

        if (!$success) {
            User::dropPictureFiles($newPicture);
            return false;
        }

        if ($oldPicture !== '' && $oldPicture !== $newPicture) {
            User::dropPictureFiles($oldPicture);
        }

        return $newPicture;
    }
}
