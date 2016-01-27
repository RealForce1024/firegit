<?php

exec('whoami', $outputs);
$user = $outputs[0];
if ($user != 'root') {
    $binDir = realpath(__DIR__.'/../../../../bin/');
    $cmd = "{$binDir}/chother php ".__FILE__;
    exit(system($cmd));
}
require_once dirname(__DIR__).'/Manager.php';
\firegit\git\Manager::init('ronnie', 'firegit');
echo "\nok\n";
