<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';

// start test server
execInBackground('php ' . __DIR__ . '/workspace/sampleapi.php');

function execInBackground($cmd)
{
    if (substr(php_uname(), 0, 7) === 'Windows') {
        pclose(popen('start /B ' . $cmd, 'r'));
    } else {
        exec($cmd . ' >/dev/null &');
    }
}

register_shutdown_function(function () {
    $ch = curl_init('http://localhost:1337/?exit=1');
    curl_exec($ch);
});
