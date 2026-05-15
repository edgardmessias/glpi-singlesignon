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
use GlpiPlugin\Singlesignon\RuleSinglesignonCollection;

include(__DIR__ . '/../../../inc/includes.php');

Session::checkRight("config", UPDATE);

if ($_SESSION["glpiactiveprofile"]["interface"] == "central") {
    Html::header(__('Single Sign-on', 'singlesignon'), $_SERVER['PHP_SELF'], "config", Provider::class, "");
} else {
    Html::helpHeader(__('Single Sign-on', 'singlesignon'), $_SERVER['PHP_SELF']);
}

// Add a shortcut button to the SSO rules page alongside the standard search UI.
$rulesUrl = RuleSinglesignonCollection::getSearchURL();
echo '<div class="d-flex justify-content-end mb-2 px-3">';
echo '<a href="' . htmlspecialchars($rulesUrl, ENT_QUOTES, 'UTF-8') . '" class="btn btn-sm btn-outline-secondary">';
echo '<i class="ti ti-list-check me-1"></i>';
echo htmlspecialchars(__('Authorization assignment rules'), ENT_QUOTES, 'UTF-8');
echo '</a>';
echo '</div>';

Search::show(Provider::class);

Html::footer();
