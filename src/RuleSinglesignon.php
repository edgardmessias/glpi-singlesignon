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
 * Rule class for the SSO rules engine.
 *
 * Criteria mirror the user attributes available from the IdP token; actions
 * mirror GLPI's native RuleRight actions so that existing GLPI translations
 * are reused and the UI feels familiar to administrators.
 */
class RuleSinglesignon extends \Rule {
    // Use the standard 'config' right so that GLPI administrators can manage
    // these rules through the normal Rules interface.
    public static $rightname = 'config';

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

    public static function getIcon() {
        return 'ti ti-user-check';
    }

    /**
     * Override to return the correct test URL for this plugin rule class.
     * GLPI's default implementation derives the URL from the class namespace,
     * producing a capitalised path (/plugins/Singlesignon/...) that does not exist.
     *
     * @param bool $full Unused – kept for signature compatibility.
     */
    public static function getTestURL($full = true): string {
        global $CFG_GLPI;
        return $CFG_GLPI['root_doc'] . '/plugins/singlesignon/front/rule.test.php';
    }

    public function showForm($ID, array $options = []) {
        $newItem = static::isNewID($ID);
        if (!$newItem) {
            $this->check($ID, READ);
        } else {
            $this->checkGlobal(UPDATE);
        }

        $canedit = $this->canEdit($ID);
        $addButtons = [];
        if (!$newItem && $canedit) {
            $addButtons = [
                [
                    'text'    => _x('button', 'Test'),
                    'type'    => 'button',
                    'onclick' => "$('#ruletestmodal').modal('show');",
                ],
            ];
        }

        $twigParams = array_merge_recursive([
            'item'            => $this,
            'match_operators' => $this->getRulesMatch(),
            'conditions'      => static::getConditionsArray(),
            'rand'            => mt_rand(),
            'test_url'        => static::getTestURL(),
            'params'          => [
                'canedit'    => $canedit,
                'addbuttons' => $addButtons,
            ],
        ], $options);

        \Glpi\Application\View\TemplateRenderer::getInstance()->display('pages/admin/rules/form.html.twig', $twigParams);

        return true;
    }

    public function showRulePreviewCriteriasForm($rules_id) {
        $criteria = $this->getAllCriteria();
        if (!$this->getRuleWithCriteriasAndActions($rules_id, true, false)) {
            return;
        }

        $criteriaNames = [];
        foreach ($criteria as $key => $value) {
            $criteriaNames[$key] = $value['name'] ?? '';
        }

        $alreadyAddedCriterias = [];
        $uniqueCriterias = [];
        foreach ($this->criterias as $criterion) {
            if (!in_array($criterion->fields['criteria'], $alreadyAddedCriterias, true)) {
                $uniqueCriterias[] = $criterion;
                $alreadyAddedCriterias[] = $criterion->fields['criteria'];
            }
        }

        \Glpi\Application\View\TemplateRenderer::getInstance()->display('pages/admin/rules/preview_criteria.html.twig', [
            'criterias'       => $uniqueCriterias,
            'criteria_names'  => $criteriaNames,
            'item'            => $this,
            'target'          => static::getTestURL(),
            'rules_id'        => $rules_id,
            'rules_id_field'  => $this->rules_id_field,
        ]);
    }

    public function getCriterias(): array {
        return [
            __('Global criteria'),
            'login' => [
                'name'    => __('Login'),
                'type'    => 'text',
                'table'   => '',
                'field'   => 'login',
                'virtual' => true,
            ],
            'email' => [
                'name'    => __('Email'),
                'type'    => 'text',
                'table'   => '',
                'field'   => 'email',
                'virtual' => true,
            ],
            'location' => [
                'name'    => __('Location'),
                'type'    => 'text',
                'table'   => '',
                'field'   => 'location',
                'virtual' => true,
            ],
            'SSO_GROUPS' => [
                'name'    => __('Role', 'singlesignon'),
                'type'    => 'text',
                'table'   => '',
                'field'   => 'SSO_GROUPS',
                'virtual' => true,
            ],
            'supervisor' => [
                'name'    => __('Supervisor'),
                'type'    => 'text',
                'table'   => '',
                'field'   => 'supervisor',
                'virtual' => true,
            ],
            'provider_id' => [
                'name'  => __('SSO Provider', 'singlesignon'),
                'type'  => 'dropdown',
                'table' => 'glpi_plugin_singlesignon_providers',
                'field' => 'name',
            ],
        ];
    }

    public function getActions(): array {
        return [
            // ── Flow control ─────────────────────────────────────────────────
            '_stop_rules_processing' => [
                'name'          => __('Skip remaining rules'),
                'type'          => 'yesonly',
                'force_actions' => ['assign'],
            ],
            // ── Profile / entity / recursive ─────────────────────────────────
            'profiles_id' => [
                'name'          => __('Profiles'),
                'type'          => 'dropdown',
                'table'         => 'glpi_profiles',
                'field'         => 'name',
                'force_actions' => ['assign'],
            ],
            'is_recursive' => [
                'name'          => __('Recursive'),
                'type'          => 'yesno',
                'force_actions' => ['assign'],
            ],
            'is_active' => [
                'name'          => __('Active'),
                'type'          => 'yesno',
                'force_actions' => ['assign'],
            ],
            '_ignore_user_import' => [
                'name'          => __('Ignore import'),
                'type'          => 'yesonly',
                'force_actions' => ['assign'],
            ],
            '_entities_id_default' => [
                'name'          => __('Default entity'),
                'type'          => 'dropdown',
                'table'         => 'glpi_entities',
                'field'         => 'completename',
                'force_actions' => ['assign'],
            ],
            'specific_groups_id' => [
                'name'          => __('Groups'),
                'type'          => 'dropdown',
                'table'         => 'glpi_groups',
                'field'         => 'completename',
                'force_actions' => ['assign'],
            ],
            'groups_id' => [
                'name'          => __('Default group'),
                'type'          => 'dropdown',
                'table'         => 'glpi_groups',
                'field'         => 'completename',
                'force_actions' => ['assign'],
            ],
            '_profiles_id_default' => [
                'name'          => __('Default profile'),
                'type'          => 'dropdown',
                'table'         => 'glpi_profiles',
                'field'         => 'name',
                'force_actions' => ['assign'],
            ],
            'timezone' => [
                'name'          => __('Time zone'),
                'type'          => 'timezone',
                'force_actions' => ['assign'],
            ],
            'language' => [
                'name'          => __('Language'),
                'type'          => 'language',
                'force_actions' => ['assign'],
            ],
            '_deny_login' => [
                'name'          => __('Deny login'),
                'type'          => 'yesonly',
                'force_actions' => ['assign'],
            ],
        ];
    }
}
