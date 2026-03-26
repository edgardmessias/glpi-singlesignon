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

use Glpi\Exception\Http\BadRequestHttpException;
use Glpi\Exception\Http\NotFoundHttpException;

include('../../../inc/includes.php');

$provider = new PluginSinglesignonProvider();
$path = false;

if (isset($_GET['id'])) { // docid for document
    if (!$provider->getFromDB($_GET['id'])) {
        $exception = new NotFoundHttpException();
        $exception->setMessageToDisplay(__('Unknown file'));
        throw $exception;
    }

    $path = $provider->fields['picture'];
} elseif (isset($_GET['path'])) {
    $path = $_GET['path'];
} else {
    $exception = new BadRequestHttpException();
    $exception->setMessageToDisplay(__('Invalid filename'));
    throw $exception;
}

$path = GLPI_PLUGIN_DOC_DIR . "/singlesignon/" . $path;

if (!file_exists($path)) {
    $exception = new NotFoundHttpException();
    $exception->setMessageToDisplay(__('File not found'));
    throw $exception;
}

// In GLPI 11, use getFileAsResponse() instead of deprecated sendFile()
Toolbox::getFileAsResponse($path, "logo.png", null, true)->send();
