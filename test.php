<?php
require __DIR__ . '/src/AgentExchange.php';
use Msads\AgentSdk\AgentExchange;
$vf = file_exists(__DIR__ . '/test_vectors.json') ? __DIR__ . '/test_vectors.json' : __DIR__ . '/../test_vectors.json';
$v = json_decode(file_get_contents($vf), true);
foreach ($v['vectors'] as $t) {
    if (AgentExchange::sign($t['secret'], $t['timestamp'], $t['nonce'], $t['method'], $t['path'], $t['body']) !== $t['signature']) { fwrite(STDERR, "FAIL\n"); exit(1); }
}
echo 'php sdk: ' . count($v['vectors']) . " signature vectors OK\n";
