<?php

function log_and_exec($cmd) {
   echo "Running: $cmd\n";
   return shell_exec($cmd);
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

include_once "$dir/setup.php";

$file_list = glob_recursive("*.php");

$file_list = array_filter($file_list, function($f) {
   return strpos($f, "vendor") === false;
});

$source_files = implode(" ", $file_list);

putenv("LANG=C");
log_and_exec("xgettext $source_files -D $dir -o $dir/locales/singlesignon.pot" .
      " -L PHP --add-comments=TRANS --from-code=UTF-8 --force-po --keyword=__sso" .
      " --package-name=singlesignon --package-version=" . PLUGIN_SINGLESIGNON_VERSION . 
      " --msgid-bugs-address=https://github.com/edgardmessias/glpi-singlesignon/issues");

// Replace some KEYWORDS
$pot_content = file_get_contents("$dir/locales/singlesignon.pot");
$pot_content = str_replace("CHARSET", "UTF-8", $pot_content);
file_put_contents("$dir/locales/singlesignon.pot", $pot_content);

log_and_exec("msginit --no-translator -i $dir/locales/singlesignon.pot -l en_GB.UTF8 -o $dir/locales/en_GB.po");

$files = glob("$dir/locales/*.po");

// Build .mo
foreach ($files as $file) {
   $lang = basename($file, ".po");

   if ($lang !== "en_GB") {
      log_and_exec("msgmerge --update $dir/locales/$lang.po $dir/locales/singlesignon.pot --lang=$lang --backup=off");
   }

   log_and_exec("msgfmt $dir/locales/$lang.po -o $dir/locales/$lang.mo");
}
