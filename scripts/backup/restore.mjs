#!/usr/bin/env node
/**
 * Decrypts an off-server DB backup (.sql.gz.enc from R2 bucket colombojamaat-api-backups/db/).
 * Runs on your own computer, never on ls5. See scripts/backup/README.md.
 *
 *   node scripts/backup/restore.mjs <file.sql.gz.enc> [--key <private.pem>]
 *
 * Writes <file>.sql.gz next to the input (default key: .backup-keys/colombojamaat-api-backup-private.pem).
 */
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const args = process.argv.slice(2);
const keyAt = args.indexOf('--key');
const keyPath = keyAt >= 0 ? args[keyAt + 1] : path.join(ROOT, '.backup-keys/colombojamaat-api-backup-private.pem');
const input = args.find((a, i) => !a.startsWith('--') && (keyAt < 0 || i !== keyAt + 1));

if (!input || !input.endsWith('.enc')) {
  console.error('usage: node scripts/backup/restore.mjs <file.sql.gz.enc> [--key <private.pem>]');
  process.exit(1);
}

const blob = fs.readFileSync(input);
if (blob.subarray(0, 8).toString() !== 'CJAPIBK1') throw new Error('not a colombojamaat-api backup object');
const n = blob.readUInt16BE(8);
const key = crypto.privateDecrypt({ key: fs.readFileSync(keyPath), padding: crypto.constants.RSA_PKCS1_OAEP_PADDING, oaepHash: 'sha256' }, blob.subarray(10, 10 + n));
const d = crypto.createDecipheriv('aes-256-gcm', key, blob.subarray(10 + n, 22 + n));
d.setAuthTag(blob.subarray(22 + n, 38 + n));
const plain = Buffer.concat([d.update(blob.subarray(38 + n)), d.final()]);

const out = input.slice(0, -'.enc'.length);
fs.writeFileSync(out, plain, { mode: 0o600 });
console.log(`Decrypted ${path.basename(input)} -> ${out} (${plain.length} bytes)`);
