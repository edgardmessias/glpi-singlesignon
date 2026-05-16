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

use GlpiPlugin\Singlesignon\RuleSinglesignon;
use GlpiPlugin\Singlesignon\RuleSinglesignonCollection;

include '../../../inc/includes.php';

$rulecollection = new RuleSinglesignonCollection();

// Fix for GLPI's AJAX navigation appending to the pagination list infinitely.
// GLPI core's RuleCollection::showListRules calls Session::initNavigateListItems
// without a URL. On AJAX navigation, this causes it to return early without
// clearing the session array, causing the list to grow on every visit.
// By explicitly calling it here WITH a URL, we force it to initialize correctly.
Session::initNavigateListItems(RuleSinglesignon::class, '', RuleSinglesignonCollection::getSearchURL());

include(GLPI_ROOT . '/front/rule.common.php');
