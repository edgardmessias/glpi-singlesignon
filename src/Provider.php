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

use RuleRight;
use Throwable;
use CommonDBTM;
use JsonPath\JsonObject;
use Location;
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
use DBmysql;
use Glpi\Application\View\TemplateRenderer;
use Html;
use GlpiPlugin\Singlesignon\Provider_Group;
use GlpiPlugin\Singlesignon\Provider_Role;

use function Safe\base64_decode;
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
    public const LOGIN_FAILURE = 0;
    public const LOGIN_SUCCESS = 1;
    public const LOGIN_REGISTRATION_PREVIEW = 2;

    public const PENDING_REGISTRATION_SESSION_KEY = 'glpi_singlesignon_pending_registration';

    public const PHOTO_SYNC_DISABLED = 0;
    public const PHOTO_SYNC_IF_EMPTY = 1;
    public const PHOTO_SYNC_ALWAYS = 2;

    public const AUTH_HEADER_BEARER = 'bearer';
    public const AUTH_HEADER_TOKEN = 'token';
    public const AUTH_HEADER_DISABLED = 'disabled';

    // From CommonDBTM
    public $dohistory = true;
    public static $rightname = 'config';

    private $toSessionSave = ['glpi_singlesignon_redirect'];

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
    protected $_id_token_payload = null;

    /**
     *
     * @var null|array
     */
    protected $_resource_owner = null;

    /**
     * Human-readable reason for the last LOGIN_FAILURE, populated by login() and performGlpiLogin().
     * Empty string when no failure has occurred yet.
     *
     * @var string
     */
    protected string $lastLoginError = '';

    public function getLastLoginError(): string
    {
        return $this->lastLoginError;
    }

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
        $this->addStandardTab(Provider_Role::class, $ong, $options);
        $this->addStandardTab('Log', $ong, $options);

        return $ong;
    }

    public function post_getEmpty()
    {
        $this->fields["type"] = 'generic';
        $this->fields["is_active"] = 1;
        $this->fields["user_photo_sync_mode"] = self::PHOTO_SYNC_DISABLED;
        $this->fields["resource_owner_auth_type"] = self::AUTH_HEADER_BEARER;
        $this->fields["resource_owner_picture_auth_type"] = self::AUTH_HEADER_BEARER;
        $this->fields['auto_register'] = 0;
        $this->fields['registration_preview'] = 0;
        $this->fields['default_entities_id'] = 0;
        $this->fields['default_entities_id_is_recursive'] = 0;
        $this->fields['default_profiles_id'] = 0;
        $this->fields['default_profiles_id_is_recursive'] = 0;
        $this->fields['ssl_verify_host'] = 1;
        $this->fields['ssl_verify_peer'] = 1;
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
        global $DB;

        Toolbox::deletePicture($this->fields['picture']);

        $providerId = $this->getID();
        if ($providerId > 0) {
            $rolesTable = Provider_Role::getTable();

            // Delete all role mappings for this provider.
            $DB->delete($rolesTable, ['plugin_singlesignon_providers_id' => $providerId]);
        }

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

        $input['user_photo_sync_mode'] = $this->sanitizePhotoSyncMode($input['user_photo_sync_mode'] ?? null);
        $input['resource_owner_auth_type'] = $this->sanitizeAuthorizationType($input['resource_owner_auth_type'] ?? null);
        $input['resource_owner_picture_auth_type'] = $this->sanitizeAuthorizationType($input['resource_owner_picture_auth_type'] ?? null);
        $input['resource_owner_custom_headers'] = trim((string) ($input['resource_owner_custom_headers'] ?? ''));
        $input['resource_owner_picture_custom_headers'] = trim((string) ($input['resource_owner_picture_custom_headers'] ?? ''));

        $input['auto_register'] = empty($input['auto_register']) ? 0 : 1;
        $input['registration_preview'] = empty($input['registration_preview']) ? 0 : 1;
        $input['default_entities_id'] = (int) ($input['default_entities_id'] ?? 0);
        $input['default_entities_id_is_recursive'] = empty($input['default_entities_id_is_recursive']) ? 0 : 1;
        $input['default_profiles_id'] = (int) ($input['default_profiles_id'] ?? 0);
        $input['default_profiles_id_is_recursive'] = empty($input['default_profiles_id_is_recursive']) ? 0 : 1;
        if (array_key_exists('ssl_verify_host', $input)) {
            $input['ssl_verify_host'] = empty($input['ssl_verify_host']) ? 0 : 1;
        } else {
            $input['ssl_verify_host'] = 1;
        }
        if (array_key_exists('ssl_verify_peer', $input)) {
            $input['ssl_verify_peer'] = empty($input['ssl_verify_peer']) ? 0 : 1;
        } else {
            $input['ssl_verify_peer'] = 1;
        }

        return $input;
    }

    public static function getPhotoSyncModes(): array
    {
        return [
            (string) self::PHOTO_SYNC_DISABLED => __('Disabled'),
            (string) self::PHOTO_SYNC_IF_EMPTY => __('Only if user has no photo', 'singlesignon'),
            (string) self::PHOTO_SYNC_ALWAYS   => __('Always on login', 'singlesignon'),
        ];
    }

    public static function getAuthorizationTypes(): array
    {
        return [
            self::AUTH_HEADER_BEARER  => __('Authorization: Bearer <token>', 'singlesignon'),
            self::AUTH_HEADER_TOKEN   => __('Authorization: <token>', 'singlesignon'),
            self::AUTH_HEADER_DISABLED => __('Disabled'),
        ];
    }

    private function sanitizePhotoSyncMode($value): int
    {
        $mode = (int) $value;
        if (!in_array($mode, [
            self::PHOTO_SYNC_DISABLED,
            self::PHOTO_SYNC_IF_EMPTY,
            self::PHOTO_SYNC_ALWAYS,
        ], true)) {
            return self::PHOTO_SYNC_DISABLED;
        }

        return $mode;
    }

    private function sanitizeAuthorizationType($value): string
    {
        $type = strtolower(trim((string) $value));
        if (!in_array($type, array_keys(self::getAuthorizationTypes()), true)) {
            return self::AUTH_HEADER_BEARER;
        }

        return $type;
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
            'id' => 11,
            'table' => $this->getTable(),
            'field' => 'is_active',
            'name' => __('Active'),
            'searchtype' => 'equals',
            'datatype' => 'bool',
        ];

        $tab[] = [
            'id' => 12,
            'table' => $this->getTable(),
            'field' => 'use_email_for_login',
            'name' => __('Use email field for login'),
            'searchtype' => 'equals',
            'datatype' => 'bool',
        ];

        $tab[] = [
            'id' => 13,
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

    /**
     * NOTE: changes to this menu are stored in the PHP session, so they only
     * take effect after the session is refreshed.  If the new menu entry does
     * not appear, log out and log back in, or disable/re-enable the plugin
     * through the GLPI Plugins page to force a session reset.
     */
    public static function getAdditionalMenuLinks()
    {
        $links = parent::getAdditionalMenuLinks() ?: [];

        if (RuleRight::canView()) {
            $label = __('Authorization assignment rules');
            $link = "<i class=\"ti ti-user-check\" title=\"$label\"></i><span class='d-none d-xxl-block'>$label</span>";
            $url = Toolbox::getItemTypeSearchURL('RuleRight');

            $links[$link] = $url;
        }

        return $links;
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
     * Handles the OAuth authorization callback from the identity provider.
     * This function validates the CSRF (state) token, extracts the authorization code,
     * and may perform a redirect depending on the authentication step.
     * If the authorization code is not present in the request, it may redirect the browser
     * to the provider's authorization page.
     *
     * @return string
     */
    public function checkAuthorization()
    {
        // If an OAuth error is present, show the error for processing
        if (isset($_GET['error'])) {
            // The error comes from OAuth, so display it for processing
            $error_description = $_GET['error_description'] ?? __("The action you have requested is not allowed.");
            $exception = new BadRequestHttpException();
            $exception->setMessageToDisplay(__($error_description));
            throw $exception;
        }

        // If there is no 'code' in the request, this is the initial (request) step: generate the CSRF token and save the redirect in the session.
        // When 'code' is present, this is the response step returned by the provider after authentication.
        if (!isset($_GET['code'])) {
            // Generate CSRF token for OAuth state to validate the response step
            $state = Session::getNewCSRFToken();

            if (isset($_REQUEST['redirect'])) {
                $_SESSION['glpi_singlesignon_redirect'] = $_REQUEST['redirect'];
            } else {
                unset($_SESSION['glpi_singlesignon_redirect']);
            }

            // Persist "remember me" intent for performGlpiLogin (same request lifecycle as redirect above).
            if (isset($_REQUEST['remember']) && (string) $_REQUEST['remember'] === '1') {
                $_SESSION['glpi_singlesignon_remember'] = 1;
            } else {
                unset($_SESSION['glpi_singlesignon_remember']);
            }

            // Build the callback URL for OAuth redirect without any query parameters
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

            $authorizeUrl = $this->getAuthorizeUrl();

            $glue = !str_contains($authorizeUrl, '?') ? '?' : '&';
            $authorizeUrl .= $glue . http_build_query($params);

            Html::redirect($authorizeUrl);
        }

        // Extract state parameter
        $code = $_GET['code'] ?? '';
        $state = $_GET['state'] ?? '';

        // Validate state against stored CSRF token
        Session::checkCSRF([
            '_glpi_csrf_token' => $state,
        ]);

        $this->_code = $code;

        return $code;
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
        $content = Toolbox::callCurl($url, $this->buildCurlOptions([
            CURLOPT_HTTPHEADER => [
                "Accept: application/json",
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
        ]), $msgerr);

        if ($msgerr) {
            return false;
        }

        try {
            $data = json_decode($content, true);
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

            if (isset($data['id_token'])) {
                $parts = explode('.', $data['id_token']);
                if (count($parts) >= 2) {
                    $payloadStr = base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1]));
                    $this->_id_token_payload = json_decode($payloadStr, true) ?: null;
                }
            }
        } catch (Exception) {
            return false;
        }

        return $this->_token;
    }

    /**
     * @return null|array
     */
    public function getIdTokenPayload()
    {
        return $this->_id_token_payload;
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

        $headers = $this->buildResourceOwnerHeaders($token);

        $content = Toolbox::callCurl($url, $this->buildCurlOptions([
            CURLOPT_HTTPHEADER => $headers,
        ]));

        try {
            $data = json_decode($content, true);
            $this->_resource_owner = $data;
        } catch (Exception) {
            return false;
        }

        if ($this->getClientType() === "linkedin") {
            $email_url = "https://api.linkedin.com/v2/emailAddress?q=members&projection=(elements*(handle~))";
            $content = Toolbox::callCurl($email_url, $this->buildCurlOptions([
                CURLOPT_HTTPHEADER => $headers,
            ]));

            try {
                $data = json_decode($content, true);
                $this->_resource_owner['email-address'] = $data['elements'][0]['handle~']['emailAddress'];
            } catch (Exception) {
                return false;
            }
        }

        return $this->_resource_owner;
    }

    private function getFallbackFieldMappingsByType(string $fieldType): array
    {
        $providerType = $this->getClientType();
        $providerDefaults = Provider_Field::getDefaultMappings($providerType);
        $genericDefaults = $providerType === 'generic'
            ? []
            : Provider_Field::getDefaultMappings('generic');

        $defaults = [];
        $seenKeys = [];
        foreach (array_merge($providerDefaults, $genericDefaults) as $row) {
            if ($row['field_type'] !== $fieldType || (int) $row['is_active'] !== 1) {
                continue;
            }

            $key = $row['field_type'] . '|' . $row['jsonpath'];
            if (isset($seenKeys[$key])) {
                continue;
            }

            $seenKeys[$key] = true;
            $defaults[] = $row;
        }

        return $defaults;
    }

    /**
     * Resolve all scalar values matching a JSONPath expression.
     *
     * @return list<string>
     */
    private function getResourceOwnerValuesByJsonPath(array $resourceArray, string $jsonPath): array
    {
        try {
            $json = new JsonObject($resourceArray);
            $result = $json->get($jsonPath);
        } catch (Throwable $e) {
            return [];
        }

        return $this->normalizeJsonPathResults($result);
    }

    /**
     * Resolve only the first scalar value matching a JSONPath expression.
     *
     * Use {@see getResourceOwnerValuesByJsonPath()} when all matching values are required.
     */
    private function getResourceOwnerValueByJsonPath(array $resourceArray, string $jsonPath): ?string
    {
        $values = $this->getResourceOwnerValuesByJsonPath($resourceArray, $jsonPath);

        return $values[0] ?? null;
    }

    /**
     * Recursively normalize a JSONPath result into a flat list of strings.
     *
     * @param mixed $result
     * @return list<string>
     */
    private function normalizeJsonPathResults(mixed $result): array
    {
        if (is_numeric($result)) {
            return [(string) $result];
        }

        if (is_string($result)) {
            $normalized = trim($result);
            return $normalized !== '' ? [$normalized] : [];
        }

        if (!is_array($result)) {
            return [];
        }

        $normalized = [];
        foreach ($result as $value) {
            foreach ($this->normalizeJsonPathResults($value) as $normalizedValue) {
                // Use the normalized value as the key to preserve order while deduplicating.
                $normalized[$normalizedValue] = $normalizedValue;
            }
        }

        return array_values($normalized);
    }

    /**
     * Resolve debug values for a mapped field.
     *
     * @return list<string>
     */
    private function getDebugFieldValuesByJsonPath(array $resourceArray, string $jsonPath, string $fieldType): array
    {
        if ($fieldType !== 'roles') {
            $value = $this->getResourceOwnerValueByJsonPath($resourceArray, $jsonPath);
            return $value !== null ? [$value] : [];
        }

        return $this->getResourceOwnerValuesByJsonPath($resourceArray, $jsonPath);
    }

    private function resolveFieldValueFromMappings(array $resourceArray, string $fieldType): ?string
    {
        $result = $this->resolveFieldDebugDetailsFromMappings($resourceArray, $fieldType);
        return $result['value'];
    }

    private function formatDebugFieldValue(array $values, string $fieldType): ?string
    {
        if ($values === []) {
            return null;
        }

        // Roles may legitimately contain multiple entries (e.g., groups claim), while other mapped
        // fields are expected to resolve to a single scalar value for login/profile mapping.
        // If a non-role JSONPath returns multiple values in edge cases (e.g. broad expressions),
        // only the first value is used.
        return $fieldType === 'roles' ? implode(', ', $values) : ($values[0] ?? null);
    }

    /**
     * @return array{value: ?string, values: list<string>, jsonpath: ?string, source: ?string}
     */
    private function resolveFieldDebugDetailsFromMappings(array $resourceArray, string $fieldType): array
    {
        $providerId = (int) ($this->fields['id'] ?? 0);
        $mappings = [];
        if ($providerId > 0) {
            $mappings = Provider_Field::getMappingsForProvider($providerId, $fieldType, true);
        }

        $idTokenPayload = $this->getIdTokenPayload();

        foreach ($mappings as $mapping) {
            $jsonPath = trim((string) ($mapping['jsonpath'] ?? ''));
            if ($jsonPath === '') {
                continue;
            }

            $values = $this->getDebugFieldValuesByJsonPath($resourceArray, $jsonPath, $fieldType);
            if ($values !== []) {
                return [
                    'value'    => $this->formatDebugFieldValue($values, $fieldType),
                    'values'   => $values,
                    'jsonpath' => $jsonPath,
                    'source'   => 'provider',
                ];
            }

            if (is_array($idTokenPayload)) {
                $valuesFromJwt = $this->getDebugFieldValuesByJsonPath($idTokenPayload, $jsonPath, $fieldType);
                if ($valuesFromJwt !== []) {
                    return [
                        'value'    => $this->formatDebugFieldValue($valuesFromJwt, $fieldType),
                        'values'   => $valuesFromJwt,
                        'jsonpath' => $jsonPath,
                        'source'   => 'provider (jwt)',
                    ];
                }
            }
        }

        // Fallback defaults are only used when active mappings do not return a value.
        foreach ($this->getFallbackFieldMappingsByType($fieldType) as $mapping) {
            $jsonPath = trim((string) ($mapping['jsonpath'] ?? ''));
            if ($jsonPath === '') {
                continue;
            }

            $values = $this->getDebugFieldValuesByJsonPath($resourceArray, $jsonPath, $fieldType);
            if ($values !== []) {
                return [
                    'value'    => $this->formatDebugFieldValue($values, $fieldType),
                    'values'   => $values,
                    'jsonpath' => $jsonPath,
                    'source'   => 'default',
                ];
            }

            if (is_array($idTokenPayload)) {
                $valuesFromJwt = $this->getDebugFieldValuesByJsonPath($idTokenPayload, $jsonPath, $fieldType);
                if ($valuesFromJwt !== []) {
                    return [
                        'value'    => $this->formatDebugFieldValue($valuesFromJwt, $fieldType),
                        'values'   => $valuesFromJwt,
                        'jsonpath' => $jsonPath,
                        'source'   => 'default (jwt)',
                    ];
                }
            }
        }

        return [
            'value'    => null,
            'values'   => [],
            'jsonpath' => null,
            'source'   => null,
        ];
    }

    /**
     * @return array<string, array{value: ?string, values: list<string>, jsonpath: ?string, source: ?string}>
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

    /**
     * @return array{firstname: string, realname: string}
     */
    public function resolveRegistrationNames(array $resource_array): array
    {
        $firstname = trim((string) ($this->resolveFieldValueFromMappings($resource_array, 'firstname') ?? ''));
        $lastname = trim((string) ($this->resolveFieldValueFromMappings($resource_array, 'lastname') ?? ''));
        $fullname = trim((string) ($this->resolveFieldValueFromMappings($resource_array, 'fullname') ?? ''));

        if ($firstname === '' && $lastname === '' && $fullname !== '') {
            $parts = preg_split('/\s+/u', $fullname, 2, PREG_SPLIT_NO_EMPTY) ?: [];
            $firstname = $parts[0] ?? '';
            $lastname = $parts[1] ?? '';
        } elseif ($firstname !== '' && $lastname === '' && $fullname !== '') {
            $parts = preg_split('/\s+/u', $fullname, 2, PREG_SPLIT_NO_EMPTY) ?: [];
            if (($parts[1] ?? '') !== '') {
                $lastname = $parts[1];
            }
        } elseif ($firstname === '' && $lastname !== '' && $fullname !== '') {
            $parts = preg_split('/\s+/u', $fullname, 2, PREG_SPLIT_NO_EMPTY) ?: [];
            if (($parts[0] ?? '') !== '') {
                $firstname = $parts[0];
            }
        }

        if ($firstname === '' && isset($resource_array['given_name'])) {
            $firstname = trim((string) $resource_array['given_name']);
        }
        if ($lastname === '' && isset($resource_array['family_name'])) {
            $lastname = trim((string) $resource_array['family_name']);
        }

        return [
            'firstname' => $firstname,
            'realname'  => $lastname,
        ];
    }

    /**
     * Resolve login name and email from resource (same rules as user lookup).
     *
     * @return array{login: string|false, email: ?string, authorized: bool}
     */
    private function resolveLoginAndEmailFromResource(array $resource_array): array
    {
        $split = (bool) ($this->fields['split_domain'] ?? false);
        $authorizedDomainsString = $this->fields['authorized_domains'] ?? null;
        $authorizedDomains = [];
        if (isset($authorizedDomainsString) && $authorizedDomainsString !== '') {
            $authorizedDomains = explode(',', (string) $authorizedDomainsString);
        }

        $emailRaw = $this->resolveFieldValueFromMappings($resource_array, 'email');
        $emailFull = null;
        if ($emailRaw !== null && $emailRaw !== '') {
            if (!$this->checkAuthorizedDomain((string) $emailRaw, $authorizedDomains)) {
                return ['login' => false, 'email' => null, 'authorized' => false];
            }
            $emailFull = (string) $emailRaw;
        }

        $use_email = !empty($this->fields['use_email_for_login']);
        $login = false;
        if ($emailFull && $use_email) {
            $login = $this->splitIdentifierByDomain($emailFull, $split);
        } else {
            $usernameRaw = $this->resolveFieldValueFromMappings($resource_array, 'username');
            if ($usernameRaw !== null && $usernameRaw !== '') {
                if (!$this->checkAuthorizedDomain((string) $usernameRaw, $authorizedDomains)) {
                    return ['login' => false, 'email' => $emailFull, 'authorized' => false];
                }
                $login = $this->splitIdentifierByDomain((string) $usernameRaw, $split);
            }
        }

        if ($emailFull === null && $login !== false && $login !== '') {
            $loginStr = (string) $login;
            if (str_contains($loginStr, '@')) {
                $emailFull = $loginStr;
            } else {
                $usernameRaw = $this->resolveFieldValueFromMappings($resource_array, 'username');
                if ($usernameRaw !== null && str_contains((string) $usernameRaw, '@')) {
                    $emailFull = (string) $usernameRaw;
                }
            }
        }

        return [
            'login'      => $login,
            'email'      => $emailFull,
            'authorized' => true,
        ];
    }

    private function resolveEntitiesIdForNewUser(array $resource_array): int
    {
        global $DB;

        if (isset($resource_array['officeLocation']) && is_string($resource_array['officeLocation']) && $resource_array['officeLocation'] !== '') {
            foreach ($DB->request([
                'FROM'  => 'glpi_entities',
                'WHERE' => ['name' => $resource_array['officeLocation']],
                'LIMIT' => 1,
            ]) as $entity) {
                return (int) $entity['id'];
            }
        }

        $default = (int) ($this->fields['default_entities_id'] ?? 0);

        return $default > 0 ? $default : 0;
    }

    private function ensureProfileForNewUser(User $user, int $entitiesId): bool
    {
        global $DB;

        $userId = (int) ($user->fields['id'] ?? 0);
        if ($userId <= 0) {
            $this->logFailure(__FUNCTION__, 'failed because user id is empty before Profile_User assignment', $user);
            return false;
        }

        $configuredProfile = (int) ($this->fields['default_profiles_id'] ?? 0);
        $glpiDefaultProfile = (int) Profile::getDefault();
        $profileId = 0;
        if ($configuredProfile > 0) {
            $profileId = $configuredProfile;
        } elseif ($glpiDefaultProfile > 0) {
            $profileId = $glpiDefaultProfile;
        } else {
            foreach ($DB->request([
                'SELECT' => ['id'],
                'FROM'   => 'glpi_profiles',
                'ORDER'  => ['id ASC'],
                'LIMIT'  => 1,
            ]) as $profile) {
                $profileId = (int) ($profile['id'] ?? 0);
                break;
            }
        }

        if ($profileId <= 0) {
            $this->logFailure(__FUNCTION__, 'failed because no GLPI profile could be resolved for Profile_User assignment', $user);
            return false;
        }

        $entityForProfile = $entitiesId;
        if ($entityForProfile <= 0) {
            foreach ($DB->request([
                'SELECT' => ['id'],
                'FROM'   => 'glpi_entities',
                'ORDER'  => ['id ASC'],
                'LIMIT'  => 1,
            ]) as $entity) {
                $entityForProfile = (int) ($entity['id'] ?? 0);
                break;
            }
        }

        if ($entityForProfile <= 0) {
            $this->logFailure(__FUNCTION__, 'failed because no GLPI entity could be resolved for Profile_User assignment', $user);
            return false;
        }

        $isRecursive = $configuredProfile > 0
            ? (int) ($this->fields['default_profiles_id_is_recursive'] ?? 0)
            : 0;

        $pu = new Profile_User();
        if ($pu->getFromDBByCrit([
            'users_id'    => $userId,
            'entities_id' => $entityForProfile,
            'profiles_id' => $profileId,
        ])) {
            return true;
        }

        $profileLinkId = $pu->add([
            'users_id'      => $userId,
            'entities_id'   => $entityForProfile,
            'is_recursive'  => $isRecursive,
            'profiles_id'   => $profileId,
        ]);

        if (!is_numeric($profileLinkId) || (int) $profileLinkId <= 0) {
            $this->logFailure(
                __FUNCTION__,
                sprintf(
                    'failed to create Profile_User authorization link for profiles_id=%d entities_id=%d',
                    $profileId,
                    $entityForProfile
                ),
                $user
            );
            return false;
        }

        return true;
    }

    /**
     * Update an existing GLPI user's profile fields from the OAuth resource-owner payload.
     *
     * Only non-empty values from the IdP response are written so that existing
     * GLPI data is preserved when the IdP does not supply a particular field.
     *
     * @param array<string, mixed> $resource_array
     */
    private function syncUserFieldsFromResource(User $user, array $resource_array): void
    {
        $userUpdate = [];

        // ── Name ─────────────────────────────────────────────────────────────
        $names = $this->resolveRegistrationNames($resource_array);
        if ($names['firstname'] !== '') {
            $userUpdate['firstname'] = $names['firstname'];
        }
        if ($names['realname'] !== '') {
            $userUpdate['realname'] = $names['realname'];
        }

        // ── Phone / mobile ───────────────────────────────────────────────────
        $phone  = $this->resolveFieldValueFromMappings($resource_array, 'phone');
        $phone2 = $this->resolveFieldValueFromMappings($resource_array, 'phone2');
        $mobile = $this->resolveFieldValueFromMappings($resource_array, 'mobile');

        if ($phone === null) {
            $businessPhones = $resource_array['businessPhones'] ?? null;
            if (is_array($businessPhones)) {
                $phone = isset($businessPhones[0]) && trim((string) $businessPhones[0]) !== ''
                    ? trim((string) $businessPhones[0]) : null;
            } elseif (is_string($businessPhones) && trim($businessPhones) !== '') {
                $phone = trim($businessPhones);
            }
        }
        if ($phone2 === null) {
            $businessPhones = $resource_array['businessPhones'] ?? null;
            if (is_array($businessPhones) && isset($businessPhones[1]) && trim((string) $businessPhones[1]) !== '') {
                $phone2 = trim((string) $businessPhones[1]);
            }
        }
        if ($mobile === null) {
            $mobileRaw = $resource_array['mobilePhone'] ?? null;
            if (is_string($mobileRaw) && trim($mobileRaw) !== '') {
                $mobile = trim($mobileRaw);
            }
        }

        if ($phone !== null && $phone !== '') {
            $userUpdate['phone'] = $phone;
        }
        if ($phone2 !== null && $phone2 !== '') {
            $userUpdate['phone2'] = $phone2;
        }
        if ($mobile !== null && $mobile !== '') {
            $userUpdate['mobile'] = $mobile;
        }

        // ── Location ─────────────────────────────────────────────────────────
        $locationName = $this->resolveFieldValueFromMappings($resource_array, 'location');
        if ($locationName === null) {
            $officeLocation = $resource_array['officeLocation'] ?? null;
            if (is_string($officeLocation) && trim($officeLocation) !== '') {
                $locationName = trim($officeLocation);
            }
        }
        if ($locationName !== null && $locationName !== '') {
            $loc = new Location();
            $existing = $loc->find(['name' => $locationName], '', 1);
            if ($existing !== []) {
                $userUpdate['locations_id'] = (int) array_key_first($existing);
            }
        }

        // ── Supervisor ───────────────────────────────────────────────────────
        $supervisorName = $this->resolveFieldValueFromMappings($resource_array, 'supervisor');
        if ($supervisorName !== null && $supervisorName !== '') {
            $supUser = new User();
            if ($supUser->getFromDBbyName($supervisorName)) {
                $userUpdate['users_id_supervisor'] = (int) $supUser->fields['id'];
            }
        }

        // ── E-mail ───────────────────────────────────────────────────────────
        $emailRaw = $this->resolveFieldValueFromMappings($resource_array, 'email');
        if ($emailRaw !== null && $emailRaw !== '') {
            global $DB;
            $emailExists = false;
            $existingEmails = $DB->request([
                'FROM'  => 'glpi_useremails',
                'WHERE' => [
                    'users_id' => (int) $user->fields['id'],
                    'email'    => $emailRaw,
                ],
                'LIMIT' => 1,
            ]);
            if (count($existingEmails) > 0) {
                $emailExists = true;
            }
            if (!$emailExists) {
                $userUpdate['_useremails'][-1] = $emailRaw;
            }
        }

        if ($userUpdate === []) {
            return;
        }

        $userUpdate['id'] = (int) $user->fields['id'];
        $user->update($userUpdate);
    }

    /**
     * Mapped values take priority; if mapping resolves an array from the
     * JSONPath expression, normalizeJsonPathResult() already returns the
     * first non-null scalar — no extra array-unpacking needed here.
     * Fallback to well-known IdP attributes when no mapping is configured.
     *
     * @param array<string, mixed> $overrides   name, firstname, realname, _email, remote_id,
     *                                           __registration_from_preview
     *
     * @return User|false
     */
    public function createUserFromOAuthResource(array $resource_array, array $overrides = [])
    {
        if (!empty($overrides['__registration_from_preview'])) {
            $login = trim((string) ($overrides['name'] ?? ''));
            $email = isset($overrides['_email']) ? trim((string) $overrides['_email']) : null;
            if ($email === '') {
                $email = null;
            }
            $remote_id = trim((string) ($overrides['remote_id'] ?? ''));
            if ($login === '' || $remote_id === '') {
                return false;
            }
            $names = [
                'firstname' => trim((string) ($overrides['firstname'] ?? '')),
                'realname'  => trim((string) ($overrides['realname'] ?? '')),
            ];
        } else {
            $resolved = $this->resolveLoginAndEmailFromResource($resource_array);
            if (!$resolved['authorized']) {
                return false;
            }

            // ── Login ────────────────────────────────────────────────────────────
            $login = $overrides['name'] ?? $resolved['login'];
            if ($login === false || $login === '' || $login === null) {
                return false;
            }
            $login = (string) $login;

            // ── E-mail ───────────────────────────────────────────────────────────
            $email = $resolved['email'];
            if (isset($overrides['_email'])) {
                $email = (string) $overrides['_email'];
            }

            // ── Name ─────────────────────────────────────────────────────────────
            $names = $this->resolveRegistrationNames($resource_array);
            if (isset($overrides['firstname'])) {
                $names['firstname'] = (string) $overrides['firstname'];
            }
            if (isset($overrides['realname'])) {
                $names['realname'] = (string) $overrides['realname'];
            }

            // ── ID ───────────────────────────────────────────────────────────────
            $remote_id = $this->resolveFieldValueFromMappings($resource_array, 'id');
            if ($remote_id === null || $remote_id === '') {
                return false;
            }
            $remote_id = (string) $remote_id;
        }

        $tokenAPI = base_convert(hash('sha256', time() . mt_rand()), 16, 36);
        $tokenPersonnel = base_convert(hash('sha256', time() . mt_rand()), 16, 36);

        $entitiesId = $this->resolveEntitiesIdForNewUser($resource_array);

        // ── Picture ──────────────────────────────────────────────────────────
        $picture = $this->resolveFieldValueFromMappings($resource_array, 'avatar_url');
        if ($picture === null && isset($resource_array['picture'])) {
            $picture = $resource_array['picture'];
        }

        // ── Phone / mobile ───────────────────────────────────────────────────
        $phone  = $this->resolveFieldValueFromMappings($resource_array, 'phone');
        $phone2 = $this->resolveFieldValueFromMappings($resource_array, 'phone2');
        $mobile = $this->resolveFieldValueFromMappings($resource_array, 'mobile');

        if ($phone === null) {
            // Fallback: businessPhones IdP attribute — take first element only
            $businessPhones = $resource_array['businessPhones'] ?? null;
            if (is_array($businessPhones)) {
                $phone = isset($businessPhones[0]) && trim((string) $businessPhones[0]) !== ''
                    ? trim((string) $businessPhones[0]) : null;
            } elseif (is_string($businessPhones) && trim($businessPhones) !== '') {
                $phone = trim($businessPhones);
            }
        }
        if ($phone2 === null) {
            // Fallback: second businessPhones element
            $businessPhones = $resource_array['businessPhones'] ?? null;
            if (is_array($businessPhones) && isset($businessPhones[1]) && trim((string) $businessPhones[1]) !== '') {
                $phone2 = trim((string) $businessPhones[1]);
            }
        }

        if ($mobile === null) {
            $mobileRaw = $resource_array['mobilePhone'] ?? null;
            if (is_string($mobileRaw) && trim($mobileRaw) !== '') {
                $mobile = trim($mobileRaw);
            }
        }

        // ── Location ─────────────────────────────────────────────────────────
        $locationName = $this->resolveFieldValueFromMappings($resource_array, 'location');
        if ($locationName === null) {
            $officeLocation = $resource_array['officeLocation'] ?? null;
            if (is_string($officeLocation) && trim($officeLocation) !== '') {
                $locationName = trim($officeLocation);
            }
        }
        $locationsId = 0;
        if ($locationName !== null && $locationName !== '') {
            $loc = new Location();
            $existing = $loc->find(['name' => $locationName], '', 1);
            if ($existing !== []) {
                $locationsId = (int) array_key_first($existing);
            }
        }

        // ── Supervisor ───────────────────────────────────────────────────────
        $supervisorName = $this->resolveFieldValueFromMappings($resource_array, 'supervisor');
        $supervisorId = 0;
        if ($supervisorName !== null && $supervisorName !== '') {
            $supUser = new User();
            if ($supUser->getFromDBbyName($supervisorName)) {
                $supervisorId = (int) $supUser->fields['id'];
            }
        }

        $userPost = [
            'name'             => $login,
            'add'              => 1,
            'password'         => '',
            'realname'         => $names['realname'],
            'firstname'        => $names['firstname'],
            'api_token'        => $tokenAPI,
            'api_token_date'   => date('Y-m-d H:i:s'),
            'personal_token'   => $tokenPersonnel,
            'is_active'        => 1,
        ];

        if ($entitiesId > 0) {
            $userPost['entities_id'] = $entitiesId;
        }
        if ($picture !== null && $picture !== '') {
            $userPost['picture'] = $picture;
        }
        if ($email !== null && $email !== '') {
            $userPost['_useremails'][-1] = $email;
        }
        if ($phone !== null && $phone !== '') {
            $userPost['phone'] = $phone;
        }
        if ($phone2 !== null && $phone2 !== '') {
            $userPost['phone2'] = $phone2;
        }
        if ($mobile !== null && $mobile !== '') {
            $userPost['mobile'] = $mobile;
        }
        if ($locationsId > 0) {
            $userPost['locations_id'] = $locationsId;
        }
        if ($supervisorId > 0) {
            $userPost['users_id_supervisor'] = $supervisorId;
        }

        try {
            $user = new User();
            $newId = $user->add($userPost);
            if (!$newId) {
                $this->logFailure(__FUNCTION__, sprintf('failed to create user from OAuth resource for login="%s"', $login), $user);
                return false;
            }

            if (!$this->ensureProfileForNewUser($user, $entitiesId)) {
                $this->logFailure(__FUNCTION__, 'failed because profile authorization assignment did not complete', $user);
                return false;
            }

            $this->linkRemoteUserToProvider((int) $user->fields['id'], (string) $remote_id);

            return $user;
        } catch (Exception $ex) {
            $this->logFailure(__FUNCTION__, 'exception during user creation: ' . $ex->getMessage());
            return false;
        }
    }

    private function linkRemoteUserToProvider(int $users_id, string $remote_id): void
    {
        $link = new Provider_User();
        $link->deleteByCriteria([
            'plugin_singlesignon_providers_id' => $this->fields['id'],
            'remote_id'                         => $remote_id,
        ]);
        $link->add([
            'plugin_singlesignon_providers_id' => $this->fields['id'],
            'users_id'                         => $users_id,
            'remote_id'                        => $remote_id,
        ]);
    }

    /**
     * @param array<string, mixed> $resource_array
     */
    public function storePendingRegistrationSession(array $resource_array): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            Session::start();
        }

        $resolved = $this->resolveLoginAndEmailFromResource($resource_array);
        $names = $this->resolveRegistrationNames($resource_array);
        $remote_id = $this->resolveFieldValueFromMappings($resource_array, 'id');

        $_SESSION[self::PENDING_REGISTRATION_SESSION_KEY] = [
            'provider_id' => (int) $this->fields['id'],
            'expires'     => time() + 900,
            'firstname'   => $names['firstname'],
            'realname'    => $names['realname'],
            'login'       => $resolved['login'] !== false ? (string) $resolved['login'] : '',
            'email'       => $resolved['email'] ?? '',
            'remote_id'   => $remote_id !== null ? (string) $remote_id : '',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getPendingRegistrationSession(): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            Session::start();
        }
        $data = $_SESSION[self::PENDING_REGISTRATION_SESSION_KEY] ?? null;
        if (!is_array($data)) {
            return null;
        }
        if (($data['expires'] ?? 0) < time()) {
            unset($_SESSION[self::PENDING_REGISTRATION_SESSION_KEY]);
            return null;
        }

        return $data;
    }

    public static function clearPendingRegistrationSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            Session::start();
        }
        unset($_SESSION[self::PENDING_REGISTRATION_SESSION_KEY]);
    }

    /**
     * Completes GLPI session login for a user already resolved by SSO (OAuth/OpenID).
     *
     * GLPI's rules engine (rights/profile assignments) is only triggered when login goes through
     * the standard {@see Auth::login} pipeline with real DB_GLPI authentication. Simulating
     * external/SSO authentication by manipulating $_SERVER or $_SESSION does not invoke the rules
     * engine and also causes spurious "Add/Delete link with provider" entries in the user history.
     * To ensure rules are applied on every SSO login, we temporarily set a random password and
     * authenticate via the local DB, which runs the full pipeline including rules, then restore
     * the original password hash.
     *
     * @param User $user User row (must include `name`, `id`, and `password` fields).
     *
     * @return bool True if {@see Auth::login} succeeded.
     */
    public function performGlpiLogin(User $user): bool
    {
        /** @var DBmysql $DB */
        global $CFG_GLPI, $DB;

        // --- 1. Pre-login: preserve keys Auth::login may wipe from the session ---
        $save = [];
        foreach ($this->toSessionSave as $key) {
            if (isset($_SESSION[$key])) {
                $save[$key] = $_SESSION[$key];
            }
        }

        // GLPI "remember me" cookie: only if session flag was set during OAuth authorize step and feature is enabled.
        $remember_me = !empty($_SESSION['glpi_singlesignon_remember'])
            && (int) ($CFG_GLPI['login_remember_time'] ?? 0) > 0;
        unset($_SESSION['glpi_singlesignon_remember']);

        try {
            if (!Provider_Group::syncRoleGroupsForUser($this, $user)) {
                $this->logFailure(__FUNCTION__, 'role mapping to GLPI group synchronization failed before login', $user);
            }
        } catch (Exception $ex) {
            $this->logFailure(__FUNCTION__, 'exception during role mapping to GLPI group synchronization: ' . $ex->getMessage(), $user);
        }

        // Optional photo sync
        try {
            $this->syncOAuthPhoto($user);
        } catch (Exception $ex) {
            $this->logFailure(__FUNCTION__, 'exception during OAuth photo synchronization: ' . $ex->getMessage(), $user);
        }

        // --- 2. Login ---
        // Use a temporary local password so that Auth::login runs the complete GLPI
        // authentication pipeline, including the rules engine (rights/profile assignments).
        // Simulating SSO/external auth (via $_SERVER or $_SESSION manipulation) does not
        // trigger the rules engine; local DB authentication does.
        $userId = (int) $user->fields['id'];
        $tempPassword = bin2hex(random_bytes(64));
        $DB->update('glpi_users', ['password' => Auth::getPasswordHash($tempPassword)], ['id' => $userId]);

        try {
            $auth = new Auth();
            // We intentionally do not call Session::init() directly because it does not execute
            // GLPI's rules engine; Auth::login is required to apply rules on login.
            $authResult = $auth->login($user->fields['name'], $tempPassword, false, $remember_me);
        } finally {
            // Restore the original password hash unconditionally to ensure it is never
            // left in a temporary state even if login throws an exception.
            $DB->update('glpi_users', ['password' => $user->fields['password']], ['id' => $userId]);
        }

        if (!$authResult) {
            $providerName = (string) ($this->fields['name'] ?? '');
            $userName = (string) ($user->fields['name'] ?? '');
            $this->lastLoginError = "SSO login failed for user '$userName' via provider '$providerName': User not authorized to connect in GLPI";
            return false;
        }

        // --- 3. Success: restore session snapshot ---
        foreach ($save as $key => $value) {
            $_SESSION[$key] = $value;
        }

        return true;
    }

    /**
     * @param array<string, mixed>|null $resource_array
     *
     * @return User|false
     */
    public function findUser(?array $resource_array = null)
    {
        if ($resource_array === null) {
            $resource_array = $this->getResourceOwner();
        }

        if (!$resource_array || !is_array($resource_array)) {
            return false;
        }

        $user = new User();
        $id = null;

        $hookId = Plugin::doHookFunction('sso:find_user', $resource_array);
        if (is_numeric($hookId) && $user->getFromDB((int) $hookId)) {
            return $user;
        }

        $remote_id = $this->resolveFieldValueFromMappings($resource_array, 'id');

        if ($remote_id !== null && $remote_id !== '') {
            $link = new Provider_User();
            $links = $link->find([
                'remote_id'                        => (string) $remote_id,
                'plugin_singlesignon_providers_id' => (int) $this->fields['id'],
            ]);
            if (!empty($links) && $first = reset($links)) {
                $id = $first['users_id'];
            }
        }

        if (is_numeric($id) && $user->getFromDB((int) $id)) {
            return $user;
        }

        $split = (bool) ($this->fields['split_domain'] ?? false);
        $authorizedDomainsString = $this->fields['authorized_domains'] ?? null;
        $authorizedDomains = [];
        if (isset($authorizedDomainsString) && $authorizedDomainsString !== '') {
            $authorizedDomains = explode(',', (string) $authorizedDomainsString);
        }

        $emailFull = null;
        $emailMapped = $this->resolveFieldValueFromMappings($resource_array, 'email');
        if ($emailMapped !== null && $emailMapped !== '') {
            if (!$this->checkAuthorizedDomain((string) $emailMapped, $authorizedDomains)) {
                return false;
            }
            $emailFull = (string) $emailMapped;
        }

        $login = false;
        $use_email = !empty($this->fields['use_email_for_login']);
        if ($emailFull && $use_email) {
            $login = $this->splitIdentifierByDomain($emailFull, $split);
        } else {
            $usernameVal = $this->resolveFieldValueFromMappings($resource_array, 'username');
            if ($usernameVal !== null && $usernameVal !== '') {
                if (!$this->checkAuthorizedDomain((string) $usernameVal, $authorizedDomains)) {
                    return false;
                }
                $login = $this->splitIdentifierByDomain((string) $usernameVal, $split);
            } else {
                $login = false;
            }
        }

        if ($login && $user->getFromDBbyName((string) $login)) {
            return $user;
        }

        $default_condition = version_compare(GLPI_VERSION, '9.3', '>=') ? [] : '';

        if ($emailFull === null) {
            if ($login !== false && $login !== '' && str_contains((string) $login, '@')) {
                $emailFull = (string) $login;
            } else {
                $usernameVal = $this->resolveFieldValueFromMappings($resource_array, 'username');
                if ($usernameVal !== null && str_contains((string) $usernameVal, '@')) {
                    $emailFull = (string) $usernameVal;
                }
            }
        }

        if ($emailFull && $user->getFromDBbyEmail($emailFull, $default_condition)) {
            return $user;
        }

        return false;
    }

    public function login(): int
    {
        $this->lastLoginError = '';
        $providerName = (string) ($this->fields['name'] ?? '');

        $resource_array = $this->getResourceOwner();
        if (!$resource_array) {
            $this->lastLoginError = "SSO login failed via provider '$providerName': Could not retrieve resource owner from identity provider";
            return self::LOGIN_FAILURE;
        }

        $user = $this->findUser($resource_array);
        if ($user) {
            $this->syncUserFieldsFromResource($user, $resource_array);
            return $this->performGlpiLogin($user) ? self::LOGIN_SUCCESS : self::LOGIN_FAILURE;
        }

        // ── New-user registration path ──────────────────────────────────────

        if (empty($this->fields['auto_register'])) {
            $this->lastLoginError = "SSO login failed via provider '$providerName': User not found and auto-registration is disabled";
            return self::LOGIN_FAILURE;
        }

        $gate = $this->resolveLoginAndEmailFromResource($resource_array);
        if (!$gate['authorized']) {
            $this->lastLoginError = "SSO login failed via provider '$providerName': User is not authorized by provider domain/email restrictions";
            return self::LOGIN_FAILURE;
        }

        if ($gate['login'] === false || (string) $gate['login'] === '') {
            $this->lastLoginError = "SSO login failed via provider '$providerName': Could not resolve a login name from the identity provider response";
            return self::LOGIN_FAILURE;
        }

        $remoteForReg = $this->resolveFieldValueFromMappings($resource_array, 'id');
        if ($remoteForReg === null || $remoteForReg === '') {
            $this->lastLoginError = "SSO login failed via provider '$providerName': Could not resolve remote user ID from the identity provider response";
            return self::LOGIN_FAILURE;
        }

        if ($this->fields['registration_preview']) {
            $this->storePendingRegistrationSession($resource_array);
            return self::LOGIN_REGISTRATION_PREVIEW;
        }

        $user = $this->createUserFromOAuthResource($resource_array);
        if (!$user || !$user->getID()) {
            $this->lastLoginError = "SSO login failed via provider '$providerName': User auto-registration failed";
            return self::LOGIN_FAILURE;
        }

        return $this->performGlpiLogin($user) ? self::LOGIN_SUCCESS : self::LOGIN_FAILURE;
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
        if (!$this->shouldSyncOAuthPhoto($user)) {
            return false;
        }

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
        $headers = $this->buildRequestHeaders(
            $token,
            "Accept:image/*",
            (string) ($this->fields['resource_owner_picture_auth_type'] ?? self::AUTH_HEADER_BEARER),
            (string) ($this->fields['resource_owner_picture_custom_headers'] ?? ''),
        );

        $headers = Plugin::doHookFunction("sso:resource_owner_picture", $headers);

        return $headers;
    }

    /**
     * Build headers for resource owner request.
     */
    private function buildResourceOwnerHeaders(string $token): array
    {
        $headers = $this->buildRequestHeaders(
            $token,
            "Accept:application/json",
            (string) ($this->fields['resource_owner_auth_type'] ?? self::AUTH_HEADER_BEARER),
            (string) ($this->fields['resource_owner_custom_headers'] ?? ''),
        );

        return Plugin::doHookFunction("sso:resource_owner_header", $headers);
    }

    /**
     * Build headers from configured auth mode and custom headers.
     * Placeholders: <token> and <access_token>.
     *
     * @return string[]
     */
    private function buildRequestHeaders(
        string $token,
        string $acceptHeader,
        string $authorizationType,
        string $customHeaders
    ): array {
        $headers = [$acceptHeader];
        $resolvedAuthType = $this->sanitizeAuthorizationType($authorizationType);

        if ($resolvedAuthType === self::AUTH_HEADER_BEARER) {
            $headers[] = "Authorization: Bearer {$token}";
        } elseif ($resolvedAuthType === self::AUTH_HEADER_TOKEN) {
            $headers[] = "Authorization: {$token}";
        }

        foreach ($this->parseCustomHeaders($customHeaders, $token) as $header) {
            $headers[] = $header;
        }

        return $headers;
    }

    /**
     * Parse custom headers from textarea (one header per line).
     *
     * @return string[]
     */
    private function parseCustomHeaders(string $raw, string $token): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $headers = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim((string) $line);
            if ($line === '' || !str_contains($line, ':')) {
                continue;
            }

            $headers[] = str_replace(
                ['<token>', '<access_token>'],
                [$token, $token],
                $line,
            );
        }

        return $headers;
    }

    /**
     * Merge CURLOPT_* options with TLS settings from this provider.
     * Uses array_replace (not array_merge) so numeric CURLOPT keys are preserved.
     *
     * @param array<int, mixed> $options
     *
     * @return array<int, mixed>
     */
    private function buildCurlOptions(array $options): array
    {
        $verifyHost = !isset($this->fields['ssl_verify_host']) || (int) $this->fields['ssl_verify_host'] !== 0;
        $verifyPeer = !isset($this->fields['ssl_verify_peer']) || (int) $this->fields['ssl_verify_peer'] !== 0;

        if (!isset($options[CURLOPT_SSL_VERIFYHOST])) {
            $options[CURLOPT_SSL_VERIFYHOST] = $verifyHost ? 2 : 0;
        }
        if (!isset($options[CURLOPT_SSL_VERIFYPEER])) {
            $options[CURLOPT_SSL_VERIFYPEER] = $verifyPeer;
        }
        return $options;
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
        $img = Toolbox::callCurl($url, $this->buildCurlOptions([
            CURLOPT_HTTPHEADER => $headers,
        ]));

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

    private function shouldSyncOAuthPhoto(User $user): bool
    {
        $mode = $this->sanitizePhotoSyncMode($this->fields['user_photo_sync_mode'] ?? self::PHOTO_SYNC_DISABLED);
        if ($mode === self::PHOTO_SYNC_DISABLED) {
            return false;
        }

        if ($mode === self::PHOTO_SYNC_ALWAYS) {
            return true;
        }

        $picture = (string) ($user->fields['picture'] ?? '');
        if ($picture === '') {
            return true;
        }

        return !file_exists(GLPI_PICTURE_DIR . '/' . $picture);
    }
}
