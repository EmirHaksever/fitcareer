import { readFileSync } from 'node:fs';
import { extractCvImportPlan } from '../frontend/src/utils/cvImport.ts';

const parsed = JSON.parse(readFileSync('./scripts/cv-sample.json', 'utf8'));
console.log(JSON.stringify(extractCvImportPlan(parsed)));