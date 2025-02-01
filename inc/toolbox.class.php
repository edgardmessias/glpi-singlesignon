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

class PluginSinglesignonToolbox {
   /**
    * Generate a URL to callback
    * Some providers don't accept query string, it convert to PATH
    * @global array $CFG_GLPI
    * @param integer $id
    * @param array $query
    * @return string
    */
   public static function getCallbackUrl($row, $query = []) {
      global $CFG_GLPI;

      $url = Plugin::getPhpDir("singlesignon", false) . '/front/callback.php';

      $url .= "/provider/".$row;

      if (!empty($query)) {
         $_SESSION['redirect'] = $query['redirect'];
      }

      return $url;
   }

   public static function isDefault($row, $query = []) {

      if ($row['is_default'] == 1) {
         return true;
      }
      return false;
   }

   public static function getCallbackParameters($name = null) {
      $data = [];

      if (isset($_SERVER['PATH_INFO'])) {
         $path_info = trim($_SERVER['PATH_INFO'], '/');

         $parts = explode('/', $path_info);

         $key = null;

         foreach ($parts as $part) {
            if ($key === null) {
               $key = $part;
            } else {
               if ($key === "provider" || $key === "test") {
                  $part = intval($part);
               } else {
                  $tmp = base64_decode($part);
                  parse_str($tmp, $part);
               }

               if ($key === $name) {
                  return $part;
               }

               $data[$key] = $part;
               $key = null;
            }
         }
      }

      if (!isset($data[$name])) {
         return null;
      }

      return $data;
   }

   public static function startsWith($haystack, $needle) {
      $length = strlen($needle);
      return (substr($haystack, 0, $length) === $needle);
   }

   static function getPictureUrl($path) {
      global $CFG_GLPI;

      $path = Html::cleanInputText($path); // prevent xss

      if (empty($path)) {
         return null;
      }

      return PluginSinglesignonToolbox::getBaseURL() . Plugin::getPhpDir("singlesignon", false) . '/front/picture.send.php?path=' . $path;
   }

   public static function savePicture($src, $uniq_prefix = "") {

      if (function_exists('Document::isImage') && !Document::isImage($src)) {
         return false;
      }

      $filename     = uniqid($uniq_prefix);
      $ext          = pathinfo($src, PATHINFO_EXTENSION);
      $subdirectory = substr($filename, -2); // subdirectory based on last 2 hex digit

      $basePath = GLPI_PLUGIN_DOC_DIR . "/singlesignon";
      $i = 0;
      do {
         // Iterate on possible suffix while dest exists.
         // This case will almost never exists as dest is based on an unique id.
         $dest = $basePath
         . '/' . $subdirectory
         . '/' . $filename . ($i > 0 ? '_' . $i : '') . '.' . $ext;
         $i++;
      } while (file_exists($dest));
      // If the base directory does not exists, create it
      if (!is_dir($basePath) && !mkdir($basePath)) {
         return false;
      }
      // If the sub directory does not exists, create the sub directory
      if (!is_dir($basePath . '/' . $subdirectory) && !mkdir($basePath . '/' . $subdirectory)) {
         return false;
      }

      if (!rename($src, $dest)) {
         return false;
      }

      return substr($dest, strlen($basePath . '/')); // Return dest relative to GLPI_PICTURE_DIR
   }

   public static function deletePicture($path) {
      $basePath = GLPI_PLUGIN_DOC_DIR . "/singlesignon";
      $fullpath = $basePath . '/' . $path;

      if (!file_exists($fullpath)) {
         return false;
      }

      $fullpath = realpath($fullpath);
      if (!static::startsWith($fullpath, realpath($basePath))) {
         return false;
      }

      return @unlink($fullpath);
   }

   public static function renderButton($url, $data, $class = 'oauth-login') {
      $popupClass = "";
      if (isset($data['popup']) && $data['popup'] == 1) {
         $popupClass = "popup";
      }
      $btn = '<span><a href="' . $url . '" class="singlesignon vsubmit ' . $class . ' ' . $popupClass . '"';

      $style = '';
      if ((isset($data['bgcolor']) && $data['bgcolor'])) {
         $style .= 'background-color: ' . $data['bgcolor'] . ';';
      }
      if ((isset($data['color']) && $data['color'])) {
         $style .= 'color: ' . $data['color'] . ';';
      }
      if ($style) {
         $btn .= ' style="' . $style . '"';
      }
      $btn .= '>';

      if (isset($data['picture']) && $data['picture']) {
         $btn .= Html::image(
            static::getPictureUrl($data['picture']),
            [
               'style' => 'max-height: 20px;margin-right: 4px',
            ]
         );
         $btn .= ' ';
      }

      $btn .= sprintf(__sso('Login with %s'), $data['name']);
      $btn .= '</a></span>';
      return $btn;
   }

   /**
    * Get base URL without query string
    * @return string
    */
   public static function getBaseURL() {
      global $CFG_GLPI;

      if (!empty($CFG_GLPI['url_base'])) {
         return $CFG_GLPI['url_base'];
      }

      $baseURL = "";
      if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
         $baseURL = ($_SERVER["HTTP_X_FORWARDED_PROTO"] == "https") ? "https://" : "http://";
      } else if (isset($_SERVER["HTTPS"])) {
         $baseURL = ($_SERVER["HTTPS"] == "on") ? "https://" : "http://";
      } else {
         $baseURL = "http://";
      }

      if (isset($_SERVER["HTTP_X_FORWARDED_HOST"])) {
         $baseURL .= $_SERVER["HTTP_X_FORWARDED_HOST"];
      } else if (isset($_SERVER["HTTP_X_FORWARDED_HOST"])) {
         $baseURL .= $_SERVER["HTTP_X_FORWARDED_HOST"];
      } else {
         $baseURL .= $_SERVER["SERVER_NAME"];
      }

      $port = $_SERVER["SERVER_PORT"];
      if (isset($_SERVER["HTTP_X_FORWARDED_PORT"])) {
         $port = $_SERVER["HTTP_X_FORWARDED_PORT"];
      }

      if ($port != "80" && $port != "443") {
         $baseURL .= ":" . $_SERVER["SERVER_PORT"];
      }
      return $baseURL;
   }

   /**
    * Get current URL without query string
    * @return string
    */
   public static function getCurrentURL() {
      $currentURL = PluginSinglesignonToolbox::getBaseURL();

      // $currentURL .= $_SERVER["REQUEST_URI"];
      // Ignore Query String
      if (isset($_SERVER["SCRIPT_NAME"])) {
         $currentURL .= $_SERVER["SCRIPT_NAME"];
      }
      if (isset($_SERVER["PATH_INFO"])) {
         $currentURL .= $_SERVER["PATH_INFO"];
      }
      return $currentURL;
   }
}
