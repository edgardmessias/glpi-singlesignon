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

include(__DIR__ . '/../../../inc/includes.php');

Session::checkRight("config", UPDATE);

if ($_SESSION["glpiactiveprofile"]["interface"] == "central") {
    Html::header(__('Single Sign-on', 'singlesignon'), $_SERVER['PHP_SELF'], "config", Provider::class, "provider");
} else {
    Html::helpHeader(__('Single Sign-on', 'singlesignon'), $_SERVER['PHP_SELF']);
}

if (ucwords((string)ini_get('session.cookie_samesite')) === 'Strict') {
    echo "<div class='alert alert-important alert-danger m-3 d-flex' role='alert'>";
    echo "<i class='fa-fw ti ti-alert-triangle mt-1 me-2'></i>";
    echo "<div>";
    echo __("The PHP configuration <strong>session.cookie_samesite</strong> is set to <strong>Strict</strong>. SSO login may fail due to CSRF validation. Please edit your php.ini, change it to <strong>Lax</strong>, and restart PHP.", 'singlesignon');
    echo " <a href='https://github.com/edgardmessias/glpi-singlesignon/tree/main/docs/faq.md' target='_blank' class='text-white text-decoration-underline'>" . __('See documentation', 'singlesignon') . "</a>";
    echo "</div>";
    echo "</div>";
}


//checkTypeRight('PluginExampleExample',"r");

Search::show(Provider::class);

Html::footer();
