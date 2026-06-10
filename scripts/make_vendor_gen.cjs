const fs = require('fs');
const zlib = require('zlib');
const path = require('path');

const base = path.join(__dirname, '../backend/vendor/composer');
const output = path.join(__dirname, '../vendor_gen.php');

const classmap = fs.readFileSync(path.join(base, 'autoload_classmap.php'));
const staticmap = fs.readFileSync(path.join(base, 'autoload_static.php'));
const json = fs.readFileSync(path.join(base, 'installed.json'));

const classmapB64 = zlib.gzipSync(classmap, {level: 9}).toString('base64');
const staticB64 = zlib.gzipSync(staticmap, {level: 9}).toString('base64');
const jsonB64 = zlib.gzipSync(json, {level: 9}).toString('base64');

const php = `<?php
// AUTO-GENERATED vendor file generator - DELETE after running!
// Run this by visiting: http://momars.xo.je/vendor_gen.php
$dir = __DIR__ . '/backend/vendor/composer/';
$files = [
    'autoload_classmap.php' => '${classmapB64}',
    'autoload_static.php' => '${staticB64}',
    'installed.json' => '${jsonB64}',
];
$ok = 0;
foreach ($files as $name => $b64) {
    $data = gzdecode(base64_decode($b64));
    if ($data === false) {
        echo 'DECODE_FAIL: ' . $name . PHP_EOL;
        continue;
    }
    $result = file_put_contents($dir . $name, $data);
    if ($result !== false) {
        echo 'OK: ' . $name . ' (' . strlen($data) . ' bytes)' . PHP_EOL;
        $ok++;
    } else {
        echo 'FAIL: ' . $name . ' (check permissions)' . PHP_EOL;
    }
}
echo 'Done: ' . $ok . '/3 files written.' . PHP_EOL;
echo 'REMEMBER: Delete this file! (vendor_gen.php)' . PHP_EOL;
`;

fs.writeFileSync(output, php);
const size = fs.statSync(output).size;
console.log('Created vendor_gen.php: ' + Math.round(size / 1024) + ' KB');
