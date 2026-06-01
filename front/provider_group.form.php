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

use GlpiPlugin\Singlesignon\Provider;
use GlpiPlugin\Singlesignon\Provider_Role;
use Glpi\Exception\Http\BadRequestHttpException;
use Glpi\Exception\Http\AccessDeniedHttpException;

include(__DIR__ . '/../../../inc/includes.php');

Session::checkRight('config', UPDATE);

// Render a new empty table row with a GLPI Group dropdown (called via AJAX when clicking Add).
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'new_row') {
    $idx = max(0, (int) ($_GET['idx'] ?? 0));
    $providerId = (int) ($_GET['provider_id'] ?? 0);

    // Validate that the provider exists and the current user can update it.
    if ($providerId <= 0) {
        throw new BadRequestHttpException();
    }
    $providerCheck = new Provider();
    if (!$providerCheck->getFromDB($providerId) || !$providerCheck->can($providerId, UPDATE)) {
        throw new AccessDeniedHttpException();
    }

    $groupDropdown = Dropdown::show('Group', [
        'name'                => "_role_mappings[{$idx}][groups_id]",
        'value'               => 0,
        'display'             => false,
        'width'               => '100%',
        'rand'                => 'sg_' . $idx,
        'display_emptychoice' => true,
        'emptylabel'          => __('Select a group'),
    ]);

    $removeLabel = htmlspecialchars(__('Remove'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $activeLabel = htmlspecialchars(__('Active'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    echo '<tr data-row data-saved="0">';
    echo '<td>';
    echo '<input type="hidden" name="_role_mappings[' . $idx . '][id]" value="0"/>';
    echo '<input type="text" class="form-control" name="_role_mappings[' . $idx . '][remote_id]"';
    echo ' placeholder="' . htmlspecialchars(__('admin'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"/>';
    echo '</td>';
    echo '<td>' . $groupDropdown . '</td>';
    echo '<td class="text-center">';
    echo '<input type="checkbox" class="form-check-input"';
    echo ' name="_role_mappings[' . $idx . '][is_active]" value="1" checked';
    echo ' title="' . $activeLabel . '"/>';
    echo '</td>';
    echo '<td class="text-center">';
    echo '<button type="button" class="btn btn-outline-danger btn-sm" data-remove-row>';
    echo '<i class="ti ti-trash"></i> ' . $removeLabel;
    echo '</button>';
    echo '</td>';
    echo '</tr>';
    return;
}

$mapping = new Provider_Role();
if (isset($_POST['update_role_mappings'])) {
    $mapping->executeFormAction($_POST);
    return;
}

Html::back();
