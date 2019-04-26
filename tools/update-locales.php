<?php

function log_and_exec($cmd) {
   echo "Running: $cmd\n";
   return shell_exec($cmd);
}

$dir = dirname(dirname(__FILE__));

log_and_exec("xgettext *.php */*.php -D $dir -o $dir/locales/singlesignon.pot -L PHP --add-comments=TRANS --from-code=UTF-8 --force-po --keyword=__sso");

log_and_exec("msginit --no-translator -i $dir/locales/singlesignon.pot -l en_GB -o $dir/locales/en_GB.po");

$files = glob("$dir/locales/*.po");

// Build .mo
foreach ($files as $file) {
   $lang = basename($file, ".po");

   if ($lang !== "en_GB") {
      log_and_exec("msgmerge --update $dir/locales/$lang.po $dir/locales/singlesignon.pot");
   }

   log_and_exec("msgfmt $dir/locales/$lang.po -o $dir/locales/$lang.mo");
}
