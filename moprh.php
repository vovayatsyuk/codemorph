<?php

$args = array_slice($argv, 1);
if (count($args) === 1 && $args[0] === 'help') {
    echo '
Example of usage:
  php -f path/to/morph.php "/source/folder" "/destination/folder" demo,trial

  Remove the code in demo version, leave in trial:
    // {{morph}}
    // {{demo}}:{{}}
    $foo = "bar";
    // {{morph}}

  Replace the code for demo version and remove for trial:
    // {{morph}}
    // {{demo}}:{{
    // $foo = "foo";
    // }}
    // {{trial}}:{{}}
    $foo = "bar";
    // {{morph}}

  Add the code in demo version:
    // {{morph}}
    // {{demo}}:{{$foo = "bar";}}
    // {{morph}}

';
    exit;
}

if (count($args) < 3) {
    echo '  Error: Source, destination directories and build mode are missing';
    echo "\n";
    echo '  Example:
    php -f morph.php "/source/folder" "/destination/folder" demo
    php -f morph.php help
    ';
    exit;
}

require "src/CodeMorph.php";

$morph = new CodeMorph();
$morph->setSourcePath($args[0])
    ->setDestinationPath($args[1]);

$modes = explode(',', $args[2]);
foreach ($modes as $mode) {
    $morph->setMode($mode)->run();
}
