<?php

function log_and_exec($cmd) {
   echo "CMD: $cmd\n";
   return shell_exec($cmd);
}

function remove_recursive($dir) {
   if (is_dir($dir)) {
      $objects = scandir($dir);
      foreach ($objects as $object) {
         if ($object != "." && $object != "..") {
            remove_recursive($dir . "/" . $object);
         }
      }
      rmdir($dir);
   } else if (is_file($dir)) {
      @unlink($dir);
   }
}

if (!function_exists('glob_recursive')) {

   // Does not support flag GLOB_BRACE
   function glob_recursive($pattern, $flags = 0) {
      $files = glob($pattern, $flags);
      foreach (glob(dirname($pattern) . '/*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
         $files = array_merge($files, glob_recursive($dir . '/' . basename($pattern), $flags));
      }
      return $files;
   }

}

$dir = dirname(dirname(__FILE__));

$tmp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "singlesignon/";

echo "Delete old release\n";
remove_recursive("$dir/singlesignon.zip");
remove_recursive("$dir/singlesignon.tar");
remove_recursive("$dir/singlesignon.tar.gz");

chdir($dir);

if (file_exists($tmp_dir)) {
   echo "Delete existing temp directory\n";
   remove_recursive($tmp_dir);
}

sleep(1);

echo "Copy to $tmp_dir\n";
log_and_exec("git checkout-index -a -f --prefix=" . $tmp_dir);

chdir($tmp_dir);

echo "Retrieve PHP vendor\n";
log_and_exec("composer install --no-dev --optimize-autoloader --prefer-dist");

echo "Compile locale files\n";
log_and_exec("php tools/build-locales-mo.php");

echo "Remove unused files\n";
$to_remove = [
   'screenshots',
   'tools',
   'composer.json',
   'composer.lock',
];

$to_remove = array_merge($to_remove, glob(".*"));

$to_remove = array_filter($to_remove, function ($t) {
   return $t && !in_array($t, [".", ".."]);
});

$to_remove = array_values($to_remove);

foreach ($to_remove as $r) {
   remove_recursive("$tmp_dir/$r");
}

echo "Zip files\n";
$zip = new ZipArchive();
$tar = new PharData("$dir/singlesignon.tar");


if (!$zip->open("$dir/singlesignon.zip", ZipArchive::CREATE)) {
   echo "Failed to create singlesignon.zip\n";
}

$zip->addEmptyDir("singlesignon");
$tar->addEmptyDir("singlesignon");

$current_dir = getcwd() . DIRECTORY_SEPARATOR;

$files = glob_recursive("*");

foreach ($files as $f) {
   $f = realpath($f);

   if (!is_file($f)) {
      continue;
   }

   // Relativer file only
   $f = str_replace($current_dir, '', $f);

   $zip->addFile($f, "singlesignon" . DIRECTORY_SEPARATOR . $f);
   $tar->addFile($f, "singlesignon" . DIRECTORY_SEPARATOR . $f);
}

$zip->close();
$tar->compress(Phar::GZ);

remove_recursive("$dir/singlesignon.tar");
