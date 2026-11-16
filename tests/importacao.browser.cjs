const {test, expect} = require(process.env.PRH_PLAYWRIGHT_PATH || '@playwright/test');
const assert = require('node:assert/strict');
const {PDFDocument, StandardFonts} = require('../assets/vendor/pdf-lib/pdf-lib.min.js');

async function makePdf(page, entries) {
    const document = await PDFDocument.create();
    const font = await document.embedFont(StandardFonts.Helvetica);
    for (const entry of entries) {
        const sheet = document.addPage([595, 842]);
        if (entry.scan) {
            const png = await page.evaluate(() => {
                const canvas = window.document.createElement('canvas'); canvas.width = 1190; canvas.height = 1684;
                const context = canvas.getContext('2d'); context.fillStyle = '#fff'; context.fillRect(0, 0, canvas.width, canvas.height);
                context.fillStyle = '#000'; context.font = '28px sans-serif';
                context.fillText('Nome do Funcionário: João Pereira dos Santos', 50, 120);
                context.fillText('Competência: Março de 2026', 50, 170);
                return canvas.toDataURL('image/png').split(',')[1];
            });
            const image = await document.embedPng(Buffer.from(png, 'base64'));
            sheet.drawImage(image, {x: 0, y: 0, width: 595, height: 842});
        } else {
            const lines = ['RECIBO DE PAGAMENTO — EXEMPLO'.replace('—', '-'), 'Competência: Março de 2026', 'Código Nome do Funcionário CBO', '001 ' + entry.name + ' 123456'];
            if (entry.other) lines.push('Nome do Funcionário: ' + entry.other);
            lines.forEach((line, index) => sheet.drawText(line, {x: 30, y: 800 - index * 24, size: 12, font}));
        }
    }
    return Buffer.from(await document.save());
}
async function routeApi(page, employees) {
    const captured = {published: [], gemini: 0};
    await page.route('**/admin-ajax.php', async route => {
        const request = route.request();
        const data = await new Request(request.url(), {method: 'POST', headers: {'Content-Type': request.headers()['content-type']}, body: request.postDataBuffer()}).formData();
        assert.equal(data.get('nonce'), 'test-nonce');
        const action = data.get('action');
        let result;
        if (action === 'portal_rh_importar_funcionarios') result = employees;
        else {
            const upload = data.get('pdf');
            const bytes = Buffer.from(await upload.arrayBuffer());
            const pdf = await PDFDocument.load(bytes);
            if (action === 'portal_rh_importar_gemini') {
                assert.equal(data.get('autorizar_gemini'), '1'); assert.equal(pdf.getPageCount(), 1);
                captured.gemini++;
                result = {nomes: captured.gemini === 1 ? ['João Pereira dos Santos'] : ['Daniel Lima Cunha', 'Maria da Silva'], mes: 3, ano: 2026};
            } else {
                assert.equal(action, 'portal_rh_importar_publicar'); assert.equal(data.get('confirmado'), '1');
                assert.equal(data.get('mes'), '3'); assert.equal(data.get('ano'), '2026');
                const item = {userId: Number(data.get('user_id')), name: upload.name, count: pdf.getPageCount(), bytes, manual: data.get('vinculo_manual')};
                captured.published.push(item);
                result = {id: captured.published.length, arquivo: upload.name, url: '/?portal_rh_holerite=' + captured.published.length};
            }
        }
        await route.fulfill({status: 200, contentType: 'application/json', body: JSON.stringify({success: true, data: result})});
    });
    return captured;
}
async function textOf(page, bytes) {
    return page.evaluate(async base64 => {
        const pdfjs = await import('/assets/vendor/pdfjs/build/pdf.js');
        pdfjs.GlobalWorkerOptions.workerSrc = '/assets/vendor/pdfjs/build/pdf.worker.js';
        const task = pdfjs.getDocument({data: Uint8Array.from(atob(base64), character => character.charCodeAt(0)), isEvalSupported: false, standardFontDataUrl: '/assets/vendor/pdfjs/standard_fonts/'});
        const pdf = await task.promise;
        const result = [];
        for (let i = 1; i <= pdf.numPages; i++) result.push((await (await pdf.getPage(i)).getTextContent()).items.map(item => item.str).join(' '));
        await task.destroy(); return result.join('\n');
    }, bytes.toString('base64'));
}

test('separa páginas reais, agrupa por funcionário e competência e usa Gemini somente mediante solicitação', async ({page}) => {
    const errors = []; page.on('pageerror', error => errors.push(error.message));
    const employees = [{id: 12, label: 'Daniel Lima Cunha (daniel)', names: ['Daniel Lima Cunha']}, {id: 13, label: 'Maria da Silva (maria)', names: ['Maria da Silva']}, {id: 14, label: 'João Pereira dos Santos (joao)', names: ['João Pereira dos Santos']}];
    const captured = await routeApi(page, employees);
    await page.goto('/tests/browser-fixture.php');
    expect(await page.content()).not.toContain('SERVER-ONLY-TEST-KEY');
    const file = await makePdf(page, [{name: 'DANIEL LIMA CUNHA'}, {name: 'MARIA DA SILVA'}, {name: 'DANIEL LIMA CUNHA'}, {scan: true}, {name: 'DANIEL LIMA CUNHA', other: 'MARIA DA SILVA'}]);
    await page.locator('#prh-pdf').setInputFiles({name: 'folha-completa.pdf', mimeType: 'application/pdf', buffer: file});
    await page.locator('#prh-analyze').click();
    await expect(page.locator('#prh-message')).toContainText('Leitura concluída', {timeout: 60000});
    await expect(page.locator('#prh-rows tr')).toHaveCount(5);
    await page.locator('#prh-analyze').click();
    await expect(page.locator('#prh-message')).toContainText('Leitura concluída');
    await expect(page.locator('#prh-rows tr')).toHaveCount(5);
    await expect(page.locator('#prh-rows .prh-pick:checked')).toHaveCount(3);
    await expect(page.locator('#prh-rows tr').nth(4).locator('.prh-pick')).toBeDisabled();
    assert.equal(captured.gemini, 0);
    await page.locator('#prh-publish').click();
    await expect(page.locator('#prh-message')).toContainText('confirmação');
    assert.equal(captured.published.length, 0);
    await page.locator('#prh-ai-identify').click();
    await expect(page.locator('#prh-message')).toContainText('Autorize');
    assert.equal(captured.gemini, 0);
    await page.locator('#prh-ai-consent').check();
    await page.locator('#prh-ai-identify').click();
    await expect(page.locator('#prh-message')).toContainText('Identificação com Gemini concluída');
    assert.equal(captured.gemini, 2);
    await expect(page.locator('#prh-rows .prh-pick:checked')).toHaveCount(4);
    await expect(page.locator('#prh-rows tr').nth(4).locator('.prh-pick')).toBeDisabled();
    await page.locator('#prh-confirm').check();
    await page.locator('#prh-publish').click();
    await expect(page.locator('#prh-message')).toContainText('3 holerite(s) publicado(s).');
    assert.equal(captured.published.length, 3);
    const daniel = captured.published.find(item => item.userId === 12);
    const maria = captured.published.find(item => item.userId === 13);
    const joao = captured.published.find(item => item.userId === 14);
    assert.equal(daniel.name, 'DANIEL-LIMA-CUNHA_MARCO_DE_2026.pdf'); assert.equal(daniel.count, 2);
    assert.equal(maria.count, 1); assert.equal(joao.count, 1);
    const danielText = await textOf(page, daniel.bytes); const mariaText = await textOf(page, maria.bytes);
    assert.match(danielText, /DANIEL LIMA CUNHA/); assert.doesNotMatch(danielText, /MARIA DA SILVA/);
    assert.match(mariaText, /MARIA DA SILVA/); assert.doesNotMatch(mariaText, /DANIEL LIMA CUNHA/);
    assert.deepEqual(errors, []);
});

test('nomes iguais exigem seleção e confirmação do vínculo manual', async ({page}) => {
    const employees = [{id: 12, label: 'Daniel Lima Cunha (daniel1)', names: ['Daniel Lima Cunha']}, {id: 99, label: 'Daniel Lima Cunha (daniel2)', names: ['Daniel Lima Cunha']}];
    const captured = await routeApi(page, employees);
    await page.goto('/tests/browser-fixture.php');
    const file = await makePdf(page, [{name: 'DANIEL LIMA CUNHA'}]);
    await page.locator('#prh-pdf').setInputFiles({name: 'homonimo.pdf', mimeType: 'application/pdf', buffer: file});
    await page.locator('#prh-analyze').click();
    await expect(page.locator('#prh-message')).toContainText('Leitura concluída');
    const row = page.locator('#prh-rows tr').first();
    await expect(row.locator('.prh-pick')).toBeDisabled();
    await row.locator('.prh-person').fill(employees[0].label);
    await expect(row.locator('.prh-manual-label')).toBeVisible();
    await expect(row.locator('.prh-pick')).toBeDisabled();
    await row.locator('.prh-manual').check(); await row.locator('.prh-pick').check();
    await page.locator('#prh-confirm').check(); await page.locator('#prh-publish').click();
    await expect(page.locator('#prh-message')).toContainText('1 holerite(s) publicado(s).');
    assert.equal(captured.published[0].userId, 12); assert.equal(captured.published[0].manual, '1');
});
