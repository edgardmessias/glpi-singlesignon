<?php

function log_and_exec($cmd) {
   echo "Running: $cmd\n";
   return shell_exec($cmd);
}

$dir = dirname(dirname(__FILE__));

$files = glob("$dir/locales/*.po");

// Build .mo
foreach ($files as $file) {
   $lang = basename($file, ".po");

   log_and_exec("msgfmt $dir/locales/$lang.po -o $dir/locales/$lang.mo");
}
