<?php

/**
 * ---------------------------------------------------------------------
 * SingleSignOn is a plugin which allows to use SSO for auth
 * ---------------------------------------------------------------------
 * Copyright (C) 2022 Edgard
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 * @copyright Copyright © 2021 - 2022 Edgard
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @link      https://github.com/edgardmessias/glpi-singlesignon/
 * ---------------------------------------------------------------------
 */

include('../../../inc/includes.php');

$provider = new PluginSinglesignonProvider();
$path = false;

if (isset($_GET['id'])) { // docid for document
   if (!$provider->getFromDB($_GET['id'])) {
      Html::displayErrorAndDie(__('Unknown file'), true);
   }

   $path = $provider->fields['picture'];
} else if (isset($_GET['path'])) {
   $path = $_GET['path'];
} else {
   Html::displayErrorAndDie(__('Invalid filename'), true);
}

$path = GLPI_PLUGIN_DOC_DIR . "/singlesignon/" . $path;

if (!file_exists($path)) {
   Html::displayErrorAndDie(__('File not found'), true); // Not found
}

Toolbox::sendFile($path, "logo.png", null, true);
