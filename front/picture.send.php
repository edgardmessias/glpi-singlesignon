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

use Glpi\Exception\Http\BadRequestHttpException;
use Glpi\Exception\Http\NotFoundHttpException;
use GlpiPlugin\Singlesignon\Provider;

include(__DIR__ . '/../../../inc/includes.php');

$provider = new Provider();
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

$name = pathinfo($path, PATHINFO_BASENAME);

// Output the file directly so GLPI 11's LegacyFileLoadController output buffer
// is not closed prematurely (calling ->send() on a Symfony Response object
// flushes/closes the buffer that the controller owns, which triggers the
// "output buffer unexpectedly closed" warning).

// Validate MIME type against expected image types for the provider picture.
$detectedMime = mime_content_type($path);
$allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$mime = in_array($detectedMime, $allowedMimes, true) ? $detectedMime : 'application/octet-stream';

// Use RFC 5987 encoding for the filename in Content-Disposition to avoid header injection.
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename*=UTF-8\'\'' . rawurlencode($name));
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=86400');
readfile($path);
