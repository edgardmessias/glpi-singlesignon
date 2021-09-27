<?php

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

      $url = $CFG_GLPI['root_doc'] . '/plugins/singlesignon/front/callback.php';

      $url .= "/provider/".$row['id'];

      if (!empty($query) && $row['type'] != 'google') {
         $url .= "/q/" . base64_encode(http_build_query($query));
      } else if(!empty($query) && $row['type'] == 'google'){
         $_SESSION['redirect'] = $query['redirect'];
      }

      return $url;
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

   static public function startsWith($haystack, $needle) {
      $length = strlen($needle);
      return (substr($haystack, 0, $length) === $needle);
   }

   static function getPictureUrl($path) {
      global $CFG_GLPI;

      $path = Html::cleanInputText($path); // prevent xss

      if (empty($path)) {
         return null;
      }

      return $CFG_GLPI['root_doc'] . '/plugins/singlesignon/front/picture.send.php?path=' . $path;
   }

   static public function savePicture($src, $uniq_prefix = null) {

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
      $btn = '<span><a href="' . $url . '" class="singlesignon vsubmit ' . $class . '"';

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
               'style' => 'max-height: 20px;',
            ]
         );
         $btn .= ' ';
      }

      $btn .= sprintf(__sso('Login with %s'), $data['name']);
      $btn .= '</a></span>';
      return $btn;
   }
}
