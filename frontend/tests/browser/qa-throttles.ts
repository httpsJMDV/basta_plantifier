import {execFileSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';
export function resetQaRateLimits(){execFileSync('php',['artisan','cache:clear','--env=amaru-qa','--no-interaction'],{cwd:fileURLToPath(new URL('../../../backend/',import.meta.url)),stdio:'pipe'})}