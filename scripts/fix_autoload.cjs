const fs = require('fs');
let content = fs.readFileSync('backend/vendor/composer/autoload_real.php', 'utf8');
const oldLine = '        $filesToLoad = \\Composer\\Autoload\\ComposerStaticInit07c1e0803ec42eb9d2090dac7b243627::$files;';
const newLine = "        $filesToLoad = require __DIR__ . '/autoload_files.php';";
const fixed = content.replace(oldLine, newLine);
if (fixed === content) {
  console.log('NOT FOUND. Searching for the line...');
  const idx = content.indexOf('filesToLoad');
  if (idx !== -1) console.log('Found at:', content.substring(idx-5, idx+100));
} else {
  fs.writeFileSync('autoload_real_fixed.php', fixed, 'utf8');
  console.log('OK. Fixed line:');
  fixed.split('\n').filter(l => l.includes('filesToLoad')).forEach(l => console.log(l));
}
