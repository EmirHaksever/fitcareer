import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const logoPath = path.join(__dirname, '../public/assets/fitcareer-logo.png');
const png = fs.readFileSync(logoPath);

const signature = png.subarray(0, 8).toString('hex');
if (signature !== '89504e470d0a1a0a') {
  throw new Error('FitCareer logo asset must be a real PNG.');
}

const width = png.readUInt32BE(16);
const height = png.readUInt32BE(20);
const colorType = png[25];

if (width / height < 2.5) {
  throw new Error(`FitCareer navbar logo must be horizontal; received ${width}x${height}.`);
}

if (colorType !== 4 && colorType !== 6) {
  throw new Error('FitCareer logo PNG must contain an alpha channel.');
}

console.log(`FitCareer logo verified: ${width}x${height}, color type ${colorType}.`);
