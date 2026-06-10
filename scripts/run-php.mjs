import { existsSync } from 'node:fs';
import { spawn } from 'node:child_process';
import path from 'node:path';

const args = process.argv.slice(2);

if (args.length === 0) {
  console.error('Missing PHP command arguments.');
  process.exit(1);
}

const candidates = [
  process.env.PHP_BIN,
  'php',
  'C:/Users/wajeh/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe/php.exe',
].filter(Boolean);

const firstArg = args[0];
const artisanPath = firstArg && firstArg.endsWith('artisan') ? firstArg : null;
const cwd = artisanPath ? path.resolve(path.dirname(artisanPath)) : process.cwd();
const normalizedArgs = artisanPath ? [path.basename(artisanPath), ...args.slice(1)] : args;

const runCandidate = (index) => {
  if (index >= candidates.length) {
    console.error('Unable to locate php.exe. Set PHP_BIN to a valid PHP executable path.');
    process.exit(1);
  }

  const candidate = candidates[index];

  if (candidate !== 'php' && !existsSync(candidate)) {
    runCandidate(index + 1);
    return;
  }

  const child = spawn(candidate, normalizedArgs, {
    cwd,
    stdio: 'inherit',
    shell: false,
  });

  child.on('error', () => runCandidate(index + 1));
  child.on('exit', (code) => process.exit(code ?? 0));
};

runCandidate(0);