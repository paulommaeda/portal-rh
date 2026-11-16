(function () {
    'use strict';
    const config = window.PortalRHImportConfig;
    const core = window.PortalRHImport;
    const app = document.getElementById('prh-import-app');
    if (!config || !core || !app || !document.getElementById('prh-analyze')) return;
    const byId = id => document.getElementById(id);
    const state = {rows: [], employees: [], source: null, reader: null, loading: null, busy: false, urls: [], cache: new Map(), override: '', date: null};
    function message(text, error = false) {
        byId('prh-message').textContent = text;
        byId('prh-message').dataset.error = String(error);
    }
    function busy(value) {
        state.busy = value;
        for (const id of ['prh-analyze', 'prh-publish', 'prh-select-valid', 'prh-ai-identify', 'prh-pdf', 'prh-period']) {
            if (byId(id)) byId(id).disabled = value;
        }
        for (const input of byId('prh-rows').querySelectorAll('input,button')) input.disabled = value;
        if (!value) state.rows.forEach(updateRow);
    }
    async function post(action, values = {}, pdf = null) {
        const form = new FormData();
        form.append('action', action);
        form.append('nonce', config.nonce);
        Object.entries(values).forEach(([key, value]) => form.append(key, String(value)));
        if (pdf) form.append('pdf', new Blob([pdf.bytes], {type: 'application/pdf'}), pdf.name);
        const response = await fetch(config.ajaxUrl, {method: 'POST', credentials: 'same-origin', body: form});
        let body;
        try { body = await response.json(); } catch (error) { throw new Error('A resposta do servidor não pôde ser lida. Confira sua sessão e o limite de upload.'); }
        if (!response.ok || !body.success) throw new Error(body.data && body.data.message ? body.data.message : 'Não foi possível concluir a operação.');
        return body.data;
    }
    function valid(row) {
        try {
            core.filename(row.name, row.month, row.year);
            return !row.blocked && row.employeeId > 0 && (!row.manualRequired || row.manualConfirmed);
        } catch (error) { return false; }
    }
    function summary() {
        const selected = state.rows.filter(row => row.selected && !row.published).length;
        byId('prh-selection-summary').textContent = selected + ' página(s) selecionada(s) de ' + state.rows.length + '.';
    }
    function updateRow(row) {
        if (!row.element) return;
        const element = row.element;
        const matched = core.matchEmployee(row.name, state.employees);
        row.manualRequired = Boolean(row.name && row.employeeId && (!matched || Number(matched.id) !== Number(row.employeeId)));
        element.querySelector('.prh-manual-label').hidden = !row.manualRequired;
        element.querySelector('.prh-manual').checked = row.manualConfirmed;
        const ready = valid(row);
        if (!ready || row.published) row.selected = false;
        const checkbox = element.querySelector('.prh-pick');
        checkbox.checked = row.selected;
        checkbox.disabled = state.busy || !ready || row.published;
        element.querySelector('.prh-name').value = row.name;
        element.querySelector('.prh-month').value = core.validPeriod(row.month, row.year) ? row.year + '-' + String(row.month).padStart(2, '0') : '';
        const employee = state.employees.find(person => Number(person.id) === Number(row.employeeId));
        if (document.activeElement !== element.querySelector('.prh-person')) element.querySelector('.prh-person').value = employee ? employee.label : '';
        let note = 'Pronto para revisar.';
        if (row.published) note = 'Publicado.';
        else if (row.blocked) note = 'Há funcionários diferentes nesta página. Exporte um PDF com um funcionário por página.';
        else if (!row.name) note = 'Informe o nome completo ou use o Gemini.';
        else if (!core.validPeriod(row.month, row.year)) note = 'Informe a competência.';
        else if (!row.employeeId) note = 'Selecione a conta do funcionário.';
        else if (row.manualRequired && !row.manualConfirmed) note = 'Nome diferente ou ambíguo no cadastro. Confira a conta e confirme o vínculo manual.';
        else if (!ready) note = 'Confira o nome completo.';
        if (row.error) note += ' ' + row.error;
        const status = element.querySelector('.prh-row-status');
        status.textContent = note;
        status.dataset.ready = String(ready || row.published);
        try { element.querySelector('code').textContent = core.filename(row.name, row.month, row.year); }
        catch (error) { element.querySelector('code').textContent = 'Nome e competência pendentes'; }
        for (const input of element.querySelectorAll('input:not(.prh-pick),button')) input.disabled = state.busy || row.published;
        summary();
    }
    function resetConfirmation() { byId('prh-confirm').checked = false; }
    function renderRow(row) {
        const element = document.createElement('tr');
        element.innerHTML = '<td><input type="checkbox" class="prh-pick" aria-label="Selecionar página"></td><td><span class="prh-page"></span><br><button type="button" class="button button-small prh-preview">Prévia</button></td><td><input type="text" class="prh-name" maxlength="140" aria-label="Nome completo no holerite"></td><td><input type="month" class="prh-month" min="1900-01" max="2199-12" aria-label="Competência"></td><td><input type="text" class="prh-person" list="prh-employees" autocomplete="off" placeholder="Digite e selecione o funcionário" aria-label="Conta do funcionário"><label class="prh-manual-label" hidden><input type="checkbox" class="prh-manual"> Confirmo este vínculo manual</label></td><td><code></code><span class="prh-row-status"></span></td>';
        row.element = element;
        element.querySelector('.prh-page').textContent = row.page;
        element.querySelector('.prh-pick').addEventListener('change', event => { row.selected = event.target.checked; resetConfirmation(); summary(); });
        element.querySelector('.prh-name').addEventListener('input', event => {
            row.name = event.target.value; row.nameEdited = true; row.manualConfirmed = false;
            if (!row.employeeEdited) { const user = core.matchEmployee(row.name, state.employees); row.employeeId = user ? Number(user.id) : 0; }
            resetConfirmation(); updateRow(row);
        });
        element.querySelector('.prh-month').addEventListener('change', event => {
            const parts = event.target.value.split('-'); row.year = Number(parts[0]); row.month = Number(parts[1]); row.periodEdited = true;
            resetConfirmation(); updateRow(row);
        });
        element.querySelector('.prh-person').addEventListener('input', event => {
            const user = state.employees.find(person => person.label === event.target.value);
            row.employeeId = user ? Number(user.id) : 0; row.employeeEdited = true; row.manualConfirmed = false;
            resetConfirmation(); updateRow(row);
        });
        element.querySelector('.prh-manual').addEventListener('change', event => { row.manualConfirmed = event.target.checked; resetConfirmation(); updateRow(row); });
        element.querySelector('.prh-preview').addEventListener('click', async () => {
            const preview = window.open('about:blank', '_blank');
            if (preview) { preview.opener = null; preview.document.body.textContent = 'Preparando a prévia…'; }
            try {
                const bytes = await slice([row.page]);
                const url = URL.createObjectURL(new Blob([bytes], {type: 'application/pdf'}));
                state.urls.push(url);
                if (preview) preview.location.href = url;
                else { URL.revokeObjectURL(url); message('Permita a abertura de uma nova aba para visualizar esta página.', true); }
            } catch (error) { if (preview) preview.close(); message(error.message, true); }
        });
        byId('prh-rows').appendChild(element);
        updateRow(row);
    }
    async function slice(pages) {
        const key = pages.join(',');
        if (state.cache.has(key)) return state.cache.get(key);
        const document = await window.PDFLib.PDFDocument.create();
        document.setCreationDate(state.date); document.setModificationDate(state.date);
        const copied = await document.copyPages(state.source, pages.map(page => page - 1));
        copied.forEach(page => document.addPage(page));
        const bytes = await document.save({useObjectStreams: true});
        while (state.cache.size && (state.cache.size >= 20 || [...state.cache.values()].reduce((sum, data) => sum + data.length, bytes.length) > 32 * 1024 * 1024)) state.cache.delete(state.cache.keys().next().value);
        state.cache.set(key, bytes);
        return bytes;
    }
    async function analyze() {
        if (state.busy) return;
        const file = byId('prh-pdf').files[0];
        if (!file || !/\.pdf$/i.test(file.name)) { message('Selecione o PDF com todos os holerites.', true); return; }
        if (file.size > 100 * 1024 * 1024) { message('Selecione um PDF de até 100 MB. Divida lotes maiores antes de importar.', true); return; }
        busy(true); resetConfirmation();
        byId('prh-review').hidden = true; byId('prh-results').hidden = true; byId('prh-result-list').replaceChildren();
        byId('prh-rows').replaceChildren(); state.rows = []; state.cache.clear();
        state.urls.forEach(url => URL.revokeObjectURL(url)); state.urls = [];
        state.override = byId('prh-period').value; state.date = new Date();
        const progress = byId('prh-progress'); progress.hidden = false; progress.value = 0;
        message('Abrindo o PDF e consultando o cadastro de funcionários…');
        try {
            if (!window.PDFLib) throw new Error('Os arquivos de leitura do PDF não foram carregados. Reinstale o ZIP da release do plugin.');
            const [employees, pdfjs, buffer] = await Promise.all([post('portal_rh_importar_funcionarios'), import(config.pdfjsUrl), file.arrayBuffer()]);
            state.employees = employees;
            byId('prh-employees').replaceChildren(...employees.map(person => { const option = document.createElement('option'); option.value = person.label; return option; }));
            const bytes = new Uint8Array(buffer);
            state.source = await window.PDFLib.PDFDocument.load(bytes, {updateMetadata: false});
            pdfjs.GlobalWorkerOptions.workerSrc = config.workerUrl;
            if (state.loading) await state.loading.destroy();
            state.loading = pdfjs.getDocument({data: bytes.slice(), isEvalSupported: false, enableXfa: false, cMapUrl: config.cMapUrl, cMapPacked: true, standardFontDataUrl: config.fontUrl, wasmUrl: config.wasmUrl});
            state.reader = await state.loading.promise;
            const count = state.reader.numPages;
            if (!count || count > 1000 || count !== state.source.getPageCount()) throw new Error('Use um PDF válido de até 1.000 páginas, sem senha e com a mesma contagem de páginas na leitura.');
            progress.max = count;
            byId('prh-file-summary').textContent = file.name + ' — ' + count + ' página(s)';
            for (let page = 1; page <= count; page++) {
                message('Identificando página ' + page + ' de ' + count + '…');
                let text = ''; let error = '';
                try { const documentPage = await state.reader.getPage(page); const content = await documentPage.getTextContent(); text = core.textLines(content.items); documentPage.cleanup(); }
                catch (failure) { error = 'A leitura do texto falhou; revise a prévia ou use o Gemini.'; }
                const row = {...core.identify(text, employees, state.override), page, selected: false, published: false, manualConfirmed: false, error};
                const user = core.matchEmployee(row.name, employees);
                row.manualRequired = Boolean(row.name && row.employeeId && (!user || Number(user.id) !== row.employeeId));
                row.selected = valid(row);
                state.rows.push(row); renderRow(row); progress.value = page;
                await new Promise(resolve => setTimeout(resolve, 0));
            }
            byId('prh-review').hidden = false;
            const pending = state.rows.filter(row => !valid(row)).length;
            message('Leitura concluída. ' + (pending ? pending + ' página(s) precisam de revisão.' : 'Confira a prévia e confirme os destinatários antes de publicar.'));
        } catch (error) {
            state.source = null;
            message(/encrypt|password|senha/i.test(error.message) ? 'Este PDF está protegido por senha. Exporte uma cópia sem senha e tente novamente.' : error.message, true);
        } finally { busy(false); progress.hidden = true; }
    }
    async function identifyWithGemini() {
        if (state.busy) return;
        if (!byId('prh-ai-consent') || !byId('prh-ai-consent').checked) { message('Autorize o envio das páginas pendentes ao Gemini antes de continuar.', true); return; }
        const pending = state.rows.filter(row => !row.published && (!row.name || !core.validPeriod(row.month, row.year) || row.blocked || row.ambiguous || row.error));
        if (!pending.length) { message('Não há páginas pendentes de leitura. Confira e selecione as contas dos funcionários na tabela.'); return; }
        busy(true); resetConfirmation();
        const progress = byId('prh-progress'); progress.hidden = false; progress.max = pending.length; progress.value = 0;
        let failures = 0;
        try {
            for (const row of pending) {
                message('Consultando o Gemini para a página ' + row.page + '…');
                try {
                    const bytes = await slice([row.page]);
                    if (bytes.length > config.maxUpload) throw new Error('Esta página excede o limite de upload.');
                    const result = await post('portal_rh_importar_gemini', {autorizar_gemini: '1'}, {bytes, name: 'pagina-' + row.page + '.pdf'});
                    row.blocked = result.nomes.length > 1; row.ambiguous = false; row.error = '';
                    if (!row.nameEdited && result.nomes.length === 1) row.name = result.nomes[0];
                    if (!row.periodEdited && !state.override && core.validPeriod(result.mes, result.ano)) { row.month = result.mes; row.year = result.ano; }
                    if (!row.employeeEdited) { const user = core.matchEmployee(row.name, state.employees); row.employeeId = user ? Number(user.id) : 0; }
                    row.manualConfirmed = false;
                    updateRow(row); row.selected = valid(row); updateRow(row);
                } catch (error) { row.error = error.message; failures++; updateRow(row); }
                progress.value++;
            }
            message('Identificação com Gemini concluída. Revise as páginas e os destinatários.' + (failures ? ' ' + failures + ' página(s) não puderam ser identificadas.' : ''), failures > 0);
        } finally { busy(false); progress.hidden = true; }
    }
    async function publish() {
        if (state.busy) return;
        if (!byId('prh-confirm').checked) { message('Confira as páginas selecionadas e marque a confirmação dos destinatários.', true); return; }
        let groups;
        try { groups = core.groups(state.rows); } catch (error) { message(error.message, true); return; }
        if (!groups.length) { message('Selecione pelo menos uma página preenchida para publicar.', true); return; }
        busy(true);
        byId('prh-results').hidden = false;
        const progress = byId('prh-progress'); progress.hidden = false; progress.max = groups.length; progress.value = 0;
        let successes = 0; let failures = 0;
        try {
            for (const group of groups) {
                message('Publicando ' + group.filename + '…');
                const item = document.createElement('li');
                try {
                    const bytes = await slice(group.pages);
                    if (bytes.length > config.maxUpload) throw new Error('O PDF separado excede o limite de upload do servidor.');
                    const result = await post('portal_rh_importar_publicar', {user_id: group.employeeId, nome: group.name, mes: group.month, ano: group.year, confirmado: '1', vinculo_manual: group.rows.some(row => row.manualConfirmed) ? '1' : '0'}, {bytes, name: group.filename});
                    const link = document.createElement('a'); link.href = result.url; link.textContent = result.arquivo; item.appendChild(link);
                    item.appendChild(document.createTextNode(' — publicado.')); item.className = 'prh-result-success';
                    group.rows.forEach(row => { row.published = true; row.selected = false; row.error = ''; updateRow(row); }); successes++;
                    state.cache.delete(group.pages.join(','));
                } catch (error) {
                    item.textContent = group.filename + ' — ' + error.message; item.className = 'prh-result-error'; failures++;
                }
                byId('prh-result-list').appendChild(item); progress.value++;
            }
            message(successes + ' holerite(s) publicado(s).' + (failures ? ' ' + failures + ' falha(s); confira o resultado e tente novamente nas páginas restantes.' : ' Os arquivos já estão disponíveis em Meus Holerites.'), failures > 0);
        } finally { busy(false); progress.hidden = true; resetConfirmation(); }
    }
    byId('prh-analyze').addEventListener('click', analyze);
    byId('prh-publish').addEventListener('click', publish);
    byId('prh-select-valid').addEventListener('click', () => { state.rows.forEach(row => { row.selected = valid(row) && !row.published; updateRow(row); }); resetConfirmation(); });
    if (byId('prh-ai-identify')) byId('prh-ai-identify').addEventListener('click', identifyWithGemini);
    byId('prh-period').addEventListener('change', () => { if (state.rows.length) message('Clique em “Identificar e separar” para aplicar a competência do lote a todas as páginas novamente.'); });
    window.addEventListener('beforeunload', event => { if (state.busy) { event.preventDefault(); event.returnValue = ''; } });
}());
