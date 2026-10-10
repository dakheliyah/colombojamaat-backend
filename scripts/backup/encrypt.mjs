#!/usr/bin/env node
/**
 * Encrypts stdin to stdout for the off-server DB backup (runs as root on ls5, called by
 * /usr/local/sbin/colombojamaat-api-offsite.sh). Uses only the PUBLIC key, so the server
 * can write backups but never read them. Same scheme as Sehat's offbox.mjs.
 *
 *   node encrypt.mjs <public.pem> < in.sql.gz > out.sql.gz.enc
 *
 * "CJAPIBK1" | u16 wrapped-key length | RSA-OAEP(SHA-256) wrapped AES key | iv(12) | tag(16) | AES-256-GCM ciphertext
 */
import crypto from 'node:crypto';
import fs from 'node:fs';

const publicKey = fs.readFileSync(process.argv[2]);
const plain = fs.readFileSync(0);

const key = crypto.randomBytes(32);
const iv = crypto.randomBytes(12);
const c = crypto.createCipheriv('aes-256-gcm', key, iv);
const body = Buffer.concat([c.update(plain), c.final()]);
const wrapped = crypto.publicEncrypt({ key: publicKey, padding: crypto.constants.RSA_PKCS1_OAEP_PADDING, oaepHash: 'sha256' }, key);
const len = Buffer.alloc(2);
len.writeUInt16BE(wrapped.length);

fs.writeFileSync(1, Buffer.concat([Buffer.from('CJAPIBK1'), len, wrapped, iv, c.getAuthTag(), body]));
