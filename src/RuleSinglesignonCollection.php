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

/**
 * Rule collection for the SSO rules engine.
 *
 * All rules in this collection are evaluated (stop_on_first_match = false) so
 * that a user can be assigned to multiple GLPI groups by separate rules and
 * so that a "default" catch-all rule can be overridden by more specific ones.
 * A rule may set the `_stop_rules_processing` action to terminate evaluation
 * early, mimicking the native RuleRight behaviour.
 *
 * The caller passes a params array to prepareInputDataForProcess().  Supported
 * params keys are:
 *   - 'sso_groups'     => string[]   raw IdP group names from the token claim
 *   - 'login'          => string     GLPI login name
 *   - 'email'          => string     full e-mail address
 *   - 'location'       => string     IdP location claim value (officeLocation fallback)
 *   - 'supervisor'     => string     supervisor user name (for criterion matching)
 *   - 'provider_id'    => int        ID of the SSO provider that triggered the login
 */
class RuleSinglesignonCollection extends \RuleCollection {
    public static $rightname = 'config';

    /** Evaluate ALL matching rules so a user can receive multiple actions. */
    // No type annotation: parent RuleCollection declares this property untyped,
    // so PHP requires the child to omit the type as well.
    public $stop_on_first_match = false;

    public $menu_option = 'singlesignon';

    public function getTitle(): string {
        // Reuse the native GLPI translation for "Authorization assignment rules".
        return __('Authorization assignment rules');
    }

    public static function canCreate(): bool {
        return static::canUpdate();
    }

    public static function canPurge(): bool {
        return static::canUpdate();
    }

    /**
     * Override to return a lowercase plugin path so the GLPI router can find
     * the test page.  The default implementation derives the path from the
     * class namespace and produces a capitalised URL
     * (/plugins/Singlesignon/…) that does not exist.
     */
    public static function getRulesTestURL(): string {
        return '/plugins/singlesignon/front/rulesengine.test.php';
    }

    /**
     * Override to return the correct URL for the rules list page.
     *
     * @param bool $full When true, appends the root_doc.
     */
    public static function getSearchURL($full = true): string {
        global $CFG_GLPI;
        $url = '/plugins/singlesignon/front/rulesinglesignon.php';
        return $full ? $CFG_GLPI['root_doc'] . $url : $url;
    }

    /**
     * Override GLPI's default list renderer to keep massive-actions working for
     * namespaced plugin rule collections.
     *
     * Core `RuleCollection::showListRules()` uses `static::class` inside the
     * massive-action container id. For namespaced classes this injects `\` in
     * the id, which breaks the jQuery selector used by the Actions modal to
     * collect checked rows, producing "No selected items" even when rows are
     * selected. This override keeps the core behavior but forces a safe
     * container slug for selector compatibility.
     *
     * @param string $target
     * @param array<string, mixed> $options
     */
    public function showListRules($target, $options = []): void
    {
        global $CFG_GLPI;

        $p['inherited'] = 1;
        $p['childrens'] = 0;
        $p['active']    = false;
        $p['condition'] = 0;
        $p['_glpi_tab'] = $options['_glpi_tab'];
        $p['display_criterias'] = false;
        $p['display_actions']   = false;

        foreach (['inherited', 'childrens', 'condition'] as $param) {
            if (isset($options[$param]) && $this->isRuleRecursive()) {
                $p[$param] = (int) $options[$param];
            }
        }

        foreach (['display_criterias', 'display_actions'] as $param) {
            if (isset($options[$param])) {
                $p[$param] = (bool) $options[$param];
            }
        }

        $rule              = $this->getRuleClass();
        $display_entities  = ($this->isRuleRecursive() && ($p['inherited'] || $p['childrens']));
        $display_criterias = $p['display_criterias'];
        $display_actions   = $p['display_actions'];

        $canedit = self::canUpdate() && !$display_entities;

        $use_conditions = false;
        if ($rule->useConditions()) {
            $p['condition'] = (int) \Session::getSavedOption(static::class, 'condition', 0);
            if ($p['condition'] === 0) {
                $p['condition'] = $this->getDefaultRuleConditionForList();
            }
            $use_conditions = true;
            $twig_params = [
                'label' => __('Rules used for'),
                'conditions' => $rule::getConditionsArray(),
                'p' => $p,
            ];
            echo \Glpi\Application\View\TemplateRenderer::getInstance()->renderFromStringTemplate(<<<TWIG
                {% import 'components/form/fields_macros.html.twig' as fields %}
                <div class="d-flex justify-content-center">
                    {{ fields.dropdownArrayField('condition', p.condition, conditions, label, {
                        on_change: 'reloadTab("start=0&inherited=' ~ p.inherited ~ '&childrens=' ~ p.childrens ~ '&condition=" + this.value)'
                    }) }}
                </div>
TWIG, $twig_params);
        }

        $nb         = $this->getCollectionSize((bool) $p['inherited'], $p['condition'], $p['childrens']);
        $p['start'] = $options['start'] ?? 0;

        if ($p['start'] >= $nb) {
            $p['start'] = 0;
        }

        $p['limit'] = $_SESSION['glpilist_limit'];
        $this->getCollectionPart($p);

        $ruletype = static::getRuleClassName();

        \Session::initNavigateListItems($ruletype);
        $entries = [];
        for ($i = $p['start'], $j = 0; isset($this->RuleList->list[$j]); $i++, $j++) {
            $entries[] = [
                'itemtype' => $ruletype,
                'id'       => $this->RuleList->list[$j]->fields['id'],
            ] + $this->RuleList->list[$j]->getDataForList($display_criterias, $display_actions, $display_entities, $canedit);
            \Session::addToNavigateListItems($ruletype, $this->RuleList->list[$j]->fields['id']);
        }

        $columns = [
            'name' => __('Name'),
            'description' => __('Description'),
        ];
        if ($use_conditions) {
            $columns['condition'] = __('Use rule for');
        }
        if ($display_criterias) {
            $columns['criteria'] = \RuleCriteria::getTypeName(\Session::getPluralNumber());
        }
        if ($display_actions) {
            $columns['actions'] = \RuleAction::getTypeName(\Session::getPluralNumber());
        }
        $columns['is_active'] = __('Active');
        if ($display_entities) {
            $columns['entity'] = \Entity::getTypeName(1);
        }
        $columns['rank'] = __('Position');
        $columns['sort'] = '';

        $safeContainerClass = preg_replace('/[^A-Za-z0-9_-]/', '_', static::class);
        if (!is_string($safeContainerClass) || $safeContainerClass === '') {
            $classParts = explode('\\', static::class);
            $safeContainerClass = end($classParts) ?: 'RuleCollection';
        }

        \Glpi\Application\View\TemplateRenderer::getInstance()->display('components/datatable.html.twig', [
            'datatable_id' => 'rulelist',
            'table_class_style' => 'table-striped table-hover card-table',
            'is_tab' => true,
            'start' => $p['start'],
            'limit' => $p['limit'],
            'nofilter' => true,
            'nosort' => true,
            'super_header' => $this->getTitle(),
            'columns' => $columns,
            'formatters' => [
                'rank' => 'raw_html',
                'name' => 'raw_html',
                'criteria' => 'raw_html',
                'actions' => 'raw_html',
                'entity' => 'raw_html',
                'is_active' => 'raw_html',
                'sort' => 'raw_html',
            ],
            'entries' => $entries,
            'total_number' => $nb,
            'showmassiveactions' => true,
            'massiveactionparams' => [
                'num_displayed' => count($entries),
                'container'     => 'mass' . $safeContainerClass . mt_rand(),
                'extraparams'   => [
                    'entity' => $this->entity,
                    'condition' => $p['condition'],
                    'rule_class_name' => static::getRuleClassName(),
                ],
                'item'          => $this,
            ],
        ]);
        $collection_classname = jsescape(static::class);
        echo <<<HTML
            <script>
                $(() => {
                    sortable('#rulelist tbody', {
                        handle: '.grip-rule',
                        placeholder: '<tr><td colspan="8" class="sortable-placeholder">&nbsp;</td></tr>'
                    })[0].addEventListener('sortupdate', (e) => {
                       const sort_detail = e.detail;
                       const new_index = sort_detail.destination.index;
                       const old_index = sort_detail.origin.index;

                       $.post(CFG_GLPI['root_doc'] + '/ajax/rule.php', {
                          'action': 'move_rule',
                          'rule_id': sort_detail.item.dataset.id,
                          'collection_classname':  "{$collection_classname}",
                          'sort_action': (old_index > new_index) ? 'before' : 'after',
                          'ref_id': sort_detail.destination.itemsBeforeUpdate[new_index].dataset.id,
                       });

                       displayAjaxMessageAfterRedirect();
                    });
                });
            </script>
HTML;

        $url = $CFG_GLPI["root_doc"];
        $url .= static::getRulesTestURL();

        $twig_params = [
            'rule_class' => $rule::class,
            'can_reset' => $rule instanceof \Rule && $rule::hasDefaultRules() && \Config::canUpdate()
                && \Session::getActiveEntity() === 0 && \Session::getIsActiveEntityRecursive(),
            'can_replay' => $this->can_replay_rules,
            'reset_label' => __('Reset rules'),
            'reset_warning' => __('Rules will be erased and recreated from defaults. All existing rules will be lost.'),
            'test_label' => __('Test rules engine'),
            'replay_label' => __('Replay the dictionary rules'),
            'test_url' => $url . "?sub_type=" . $rule::class . "&condition={$p['condition']}",
        ];
        echo \Glpi\Application\View\TemplateRenderer::getInstance()->renderFromStringTemplate(<<<TWIG
            <div class="d-flex justify-content-center">
                {% if can_reset %}
                    <button type="button" class="btn btn-ghost-danger mx-1" data-bs-toggle="modal" data-bs-target="#reset_rules">
                        {{ reset_label }}
                    </button>

                    {% set reset_btn %}
                        <a class="btn btn-danger w-100" role="button" href="{{ rule_class|itemtype_search_path }}?reinit=true&amp;subtype={{ rule_class|url_encode }}">
                            {{ reset_label }}
                        </a>
                    {% endset %}

                    {{ include('components/danger_modal.html.twig', {
                        'modal_id': 'reset_rules',
                        'confirm_btn': reset_btn,
                        'content': reset_warning
                    }) }}
                {% endif %}
                <button type="button" class="btn btn-primary mx-1" data-bs-toggle="modal" data-bs-target="#allruletest">{{ test_label }}</button>
                {% do call('Ajax::createIframeModalWindow', ['allruletest', test_url, {title: test_label}]) %}
                {% if can_replay %}
                    <a class="btn btn-primary mx-1" role="button" href="{{ rule_class|itemtype_search_path }}?replay_rule=replay_rule">{{ replay_label }}</a>
                {% endif %}
            </div>
TWIG, $twig_params);

        echo "<div class='mb-2'>";
        $this->showAdditionalInformationsInForm($target);
        echo "</div>";
    }

    public static function getAdditionalMenuOptions() {
        $options = parent::getAdditionalMenuOptions();
        if (!is_array($options)) {
            $options = [];
        }

        $ruleClass = static::getRuleClassName();
        if ($ruleClass !== '' && $ruleClass::canCreate()) {
            $label = _x('button', 'Add');
            $link = "<i class=\"ti ti-plus\" title=\"$label\"></i><span class='d-none d-xxl-block'>$label</span>";
            $options[strtolower(static::class)]['links'][$link] = $ruleClass::getFormURL();
        }

        return $options;
    }



    /**
     * Populate the criteria input from the params array supplied by Provider.
     *
     * @param array<string, mixed> $input
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function prepareInputDataForProcess($input, $params): array {
        if (isset($params['sso_groups']) && is_array($params['sso_groups'])) {
            $input['SSO_GROUPS'] = $params['sso_groups'];
        }
        if (isset($params['login']) && is_string($params['login'])) {
            $input['login'] = $params['login'];
        }
        if (isset($params['email']) && is_string($params['email'])) {
            $input['email'] = $params['email'];
        }
        if (isset($params['location']) && is_string($params['location'])) {
            $input['location'] = $params['location'];
        }
        if (isset($params['supervisor']) && is_string($params['supervisor'])) {
            $input['supervisor'] = $params['supervisor'];
        }
        if (isset($params['provider_id'])) {
            $input['provider_id'] = (int) $params['provider_id'];
        }

        return $input;
    }

    /**
     * Evaluate all rules for a user and return a structured result.
     *
     * The returned array always contains every key with a safe default so
     * callers do not have to guard against missing keys.
     *
     * @param string   $login        GLPI login name.
     * @param ?string  $email        Full e-mail address, or null if not known.
     * @param string[] $ssoGroups    Raw IdP group-name strings from the token.
     * @param bool     $isNewUser    True when the user does not yet exist in GLPI.
     * @param array    $resourceArray Full OAuth resource-owner data array.
     * @param int      $providerId   ID of the SSO provider.
     *
     * @return array{
     *   _entities_id_default: int,
     *   is_recursive: bool,
     *   is_active: bool|null,
     *   profiles_id: int,
     *   _profiles_id_default: int,
     *   specific_groups_id: int[],
     *   groups_id: int,
     *   timezone: string,
     *   language: string,
     *   _ignore_user_import: bool,
     *   _deny_login: bool,
     *   _stop_rules_processing: bool
     * }
     */
    public function evaluateForUser(
        string $login,
        ?string $email,
        array $ssoGroups = [],
        bool $isNewUser = false,
        array $resourceArray = [],
        int $providerId = 0
    ): array {
        $result = [
            '_entities_id_default'   => 0,
            'is_recursive'           => false,
            'is_active'              => null,  // null = do not change
            'profiles_id'            => 0,
            '_profiles_id_default'   => 0,
            'specific_groups_id'     => [],
            'groups_id'              => 0,
            'timezone'               => '',
            'language'               => '',
            '_ignore_user_import'    => false,
            '_deny_login'            => false,
            '_stop_rules_processing' => false,
        ];

        $actions = $this->testAllRules(
            [],
            [],
            [
                'login'        => $login,
                'email'        => $email ?? '',
                'sso_groups'   => $ssoGroups,
                'location'     => (string) ($resourceArray['location'] ?? $resourceArray['officeLocation'] ?? ''),
                'supervisor'   => (string) ($resourceArray['supervisor'] ?? $resourceArray['manager'] ?? ''),
                'provider_id'  => $providerId,
            ]
        );

        if (!is_array($actions)) {
            return $result;
        }

        foreach ($actions as $field => $value) {
            if (is_array($value)) {
                continue;
            }
            switch ($field) {
                case '_entities_id_default':
                    $result['_entities_id_default'] = (int) $value;
                    break;
                case 'is_recursive':
                    $result['is_recursive'] = (bool) $value;
                    break;
                case 'is_active':
                    $result['is_active'] = (bool) $value;
                    break;
                case 'profiles_id':
                    $result['profiles_id'] = (int) $value;
                    break;
                case '_profiles_id_default':
                    $result['_profiles_id_default'] = (int) $value;
                    break;
                case 'specific_groups_id':
                    if (is_numeric($value) && (int) $value > 0) {
                        $result['specific_groups_id'][] = (int) $value;
                    }
                    break;
                case 'groups_id':
                    if (is_numeric($value) && (int) $value > 0) {
                        $result['groups_id'] = (int) $value;
                    }
                    break;
                case 'timezone':
                    if (is_string($value) && $value !== '') {
                        $result['timezone'] = $value;
                    }
                    break;
                case 'language':
                    if (is_string($value) && $value !== '') {
                        $result['language'] = $value;
                    }
                    break;
                case '_ignore_user_import':
                    $result['_ignore_user_import'] = (bool) $value;
                    break;
                case '_deny_login':
                    $result['_deny_login'] = (bool) $value;
                    break;
                case '_stop_rules_processing':
                    if ((bool) $value) {
                        $result['_stop_rules_processing'] = true;
                        // Stop processing further actions from this point.
                        break 2;
                    }
                    break;
            }
        }

        $result['specific_groups_id'] = array_values(array_unique($result['specific_groups_id']));
        return $result;
    }
}
