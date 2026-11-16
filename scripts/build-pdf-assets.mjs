import {createHash} from 'node:crypto';
import {mkdir, readFile, writeFile, copyFile, cp, access} from 'node:fs/promises';
import {fileURLToPath} from 'node:url';
import path from 'node:path';
import {execFileSync} from 'node:child_process';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const cache = path.join(root, '.pdf-build');
const vendor = path.join(root, 'assets/vendor');
const pdfjsVersion = '6.5.438';
const pdfjsHash = 'fcbe9ba21d9911843e3502adc178a7f549a524a4055f887bedc942e5490c6ba1';
const pdfLibVersion = '1.17.1';
await mkdir(cache, {recursive: true});
await mkdir(vendor, {recursive: true});

async function download(url) {
    const response = await fetch(url, {signal: AbortSignal.timeout(120000)});
    if (!response.ok) throw new Error('Dependency download failed: ' + response.status + ' ' + url);
    return Buffer.from(await response.arrayBuffer());
}
async function copyIfPresent(from, to) {
    try { await access(from); } catch { return; }
    await cp(from, to, {recursive: true});
}
const archive = await download('https://github.com/mozilla/pdf.js/releases/download/v' + pdfjsVersion + '/pdfjs-' + pdfjsVersion + '-dist.zip');
if (createHash('sha256').update(archive).digest('hex') !== pdfjsHash) throw new Error('PDF.js checksum differs from the pinned official release.');
const pdfjsZip = path.join(cache, 'pdfjs.zip');
const pdfjsExtract = path.join(cache, 'pdfjs');
await writeFile(pdfjsZip, archive);
await mkdir(pdfjsExtract, {recursive: true});
execFileSync('unzip', ['-q', '-o', pdfjsZip, '-d', pdfjsExtract]);
const pdfjsDestination = path.join(vendor, 'pdfjs');
await mkdir(path.join(pdfjsDestination, 'build'), {recursive: true});
// Extensão .js mantém o MIME JavaScript nos servidores que não mapeiam .mjs.
await copyFile(path.join(pdfjsExtract, 'build/pdf.mjs'), path.join(pdfjsDestination, 'build/pdf.js'));
await copyFile(path.join(pdfjsExtract, 'build/pdf.worker.mjs'), path.join(pdfjsDestination, 'build/pdf.worker.js'));
for (const directory of ['cmaps', 'standard_fonts', 'wasm']) await copyIfPresent(path.join(pdfjsExtract, directory), path.join(pdfjsDestination, directory));
await copyFile(path.join(pdfjsExtract, 'LICENSE'), path.join(pdfjsDestination, 'LICENSE'));

const metadata = JSON.parse((await download('https://registry.npmjs.org/pdf-lib/' + pdfLibVersion)).toString('utf8'));
if (metadata.version !== pdfLibVersion || !metadata.dist.integrity) throw new Error('Invalid pdf-lib release metadata.');
const libArchive = await download('https://registry.npmjs.org/pdf-lib/-/pdf-lib-' + pdfLibVersion + '.tgz');
const [algorithm, digest] = metadata.dist.integrity.split('-');
if (algorithm !== 'sha512' || createHash(algorithm).update(libArchive).digest('base64') !== digest) throw new Error('pdf-lib integrity check failed.');
const tarball = path.join(cache, 'pdf-lib.tgz');
const libExtract = path.join(cache, 'pdf-lib');
await writeFile(tarball, libArchive);
await mkdir(libExtract, {recursive: true});
execFileSync('tar', ['-xzf', tarball, '-C', libExtract]);
const libDestination = path.join(vendor, 'pdf-lib');
await mkdir(libDestination, {recursive: true});
await copyFile(path.join(libExtract, 'package/dist/pdf-lib.min.js'), path.join(libDestination, 'pdf-lib.min.js'));
await copyFile(path.join(libExtract, 'package/LICENSE.md'), path.join(libDestination, 'LICENSE.md'));
const versions = {pdfjs: {version: pdfjsVersion, sha256: pdfjsHash, license: 'Apache-2.0'}, pdfLib: {version: pdfLibVersion, integrity: metadata.dist.integrity, license: 'MIT'}};
await writeFile(path.join(vendor, 'versions.json'), JSON.stringify(versions, null, 2) + '\n');
console.log(JSON.stringify(versions));
