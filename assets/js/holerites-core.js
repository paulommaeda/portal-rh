(function (root, factory) {
    'use strict';
    const api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    else root.PortalRHImport = api;
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';
    const months = ['JANEIRO', 'FEVEREIRO', 'MARCO', 'ABRIL', 'MAIO', 'JUNHO', 'JULHO', 'AGOSTO', 'SETEMBRO', 'OUTUBRO', 'NOVEMBRO', 'DEZEMBRO'];
    function fold(value) {
        return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toUpperCase();
    }
    function normalizeName(value) {
        return fold(value).replace(/[^A-Z0-9]+/g, ' ').trim().replace(/\s+/g, ' ');
    }
    function validPeriod(month, year) {
        return Number.isInteger(Number(month)) && Number(month) >= 1 && Number(month) <= 12
            && Number.isInteger(Number(year)) && Number(year) >= 1900 && Number(year) <= 2199;
    }
    function filename(name, month, year) {
        const normalized = normalizeName(name);
        if (!validPeriod(month, year) || normalized.length < 5 || normalized.length > 140 || !normalized.includes(' ')) {
            throw new Error('Informe o nome completo e uma competência válida.');
        }
        return normalized.replace(/ /g, '-') + '_' + months[Number(month) - 1] + '_DE_' + Number(year) + '.pdf';
    }
    function textLines(items) {
        const lines = [];
        for (const item of items) {
            if (!item.str || !item.transform) continue;
            const y = item.transform[5];
            let line = lines.find(current => Math.abs(current.y - y) < 2.5);
            if (!line) { line = {y, items: []}; lines.push(line); }
            line.items.push({x: item.transform[4], text: item.str});
        }
        return lines.sort((a, b) => b.y - a.y).map(line => line.items.sort((a, b) => a.x - b.x).map(item => item.text).join(' ')).join('\n');
    }
    function unique(values) { return [...new Set(values.filter(Boolean))]; }
    function aliases(employee) {
        return unique((employee.names || []).map(normalizeName)).filter(name => name.includes(' ') && name.length >= 5);
    }
    function matchEmployee(name, employees) {
        const normalized = normalizeName(name);
        const found = employees.filter(employee => aliases(employee).includes(normalized));
        return found.length === 1 ? found[0] : null;
    }
    function cleanName(value) {
        let name = fold(value).replace(/^\s*[\d./\-]+\s*/, '').trim();
        name = name.split(/\b(?:CPF|CBO|PIS|CTPS|REGISTRO|MATRICULA|CARGO|FUNCAO|ADMISSAO|DEPARTAMENTO|FILIAL|LOTACAO|CODIGO|SALARIO|REFERENCIA|COMPETENCIA)\b/)[0];
        name = name.split(/\d/)[0].replace(/[:;|]+/g, ' ').trim();
        if (!/^[A-Z][A-Z\s.'’\-]+$/.test(name)) return '';
        name = normalizeName(name);
        const words = name.split(' ');
        if (words.length < 2 || words.length > 12 || /\b(?:EMPRESA|RECIBO|DISCRIMINACAO|VENCIMENTOS|DESCONTOS|DEMONSTRATIVO|COLABORADOR|FUNCIONARIO|CODIGO|CBO|NOME|FOLHA)\b/.test(name)) return '';
        return name;
    }
    function namedFields(text) {
        const lines = fold(text).split(/\r?\n/).map(line => line.trim());
        const label = /(?:NOME\s+(?:(?:DO|DA|DE)\s+)?(?:FUNCIONARIO|EMPREGADO|COLABORADOR|TRABALHADOR)|(?:FUNCIONARIO|EMPREGADO|COLABORADOR)\s*:|NOME\s*(?=[:|]|\s+(?:CBO|CPF|MATRICULA)\b))/;
        const names = [];
        for (let i = 0; i < lines.length; i++) {
            const match = label.exec(lines[i]);
            if (!match) continue;
            let name = cleanName(lines[i].slice(match.index + match[0].length).replace(/^\s*[:|]\s*/, ''));
            for (let offset = 1; !name && offset <= 2 && i + offset < lines.length; offset++) {
                if (label.test(lines[i + offset])) break;
                name = cleanName(lines[i + offset]);
            }
            if (name) names.push(name);
        }
        return unique(names);
    }
    function periodsIn(value) {
        const found = [];
        const normalized = fold(value);
        const numeric = /(?:^|[^\d/])(0?[1-9]|1[0-2])\s*[/.-]\s*((?:19|20|21)\d{2})(?![\d/])/g;
        let match;
        while ((match = numeric.exec(normalized))) found.push({month: Number(match[1]), year: Number(match[2])});
        const words = new RegExp('\\b(' + months.join('|') + ')\\s*(?:DE\\s*|[/.-]\\s*)?((?:19|20|21)\\d{2})\\b', 'g');
        while ((match = words.exec(normalized))) found.push({month: months.indexOf(match[1]) + 1, year: Number(match[2])});
        return found;
    }
    function period(text, override) {
        if (override) {
            const match = /^(\d{4})-(\d{2})$/.exec(override);
            if (match && validPeriod(match[2], match[1])) return {month: Number(match[2]), year: Number(match[1])};
        }
        const lines = fold(text).split(/\r?\n/);
        let candidates = [];
        for (let i = 0; i < lines.length; i++) {
            if (/\b(?:COMPETENCIA|REFERENCIA|MES\s*[/.-]?\s*ANO)\b/.test(lines[i])) {
                candidates.push(...periodsIn(lines[i] + ' ' + (lines[i + 1] || '')));
            }
        }
        if (!candidates.length) {
            candidates = periodsIn(lines.slice(0, 12).filter(line => !/\b(?:ADMISSAO|NASCIMENTO|EMISSAO|PAGAMENTO)\b/.test(line)).join('\n'));
        }
        const keys = unique(candidates.map(candidate => candidate.year + '-' + candidate.month));
        return keys.length === 1 ? candidates[0] : {month: 0, year: 0};
    }
    function identify(text, employees, override) {
        const names = namedFields(text);
        const foundPeriod = period(text, override);
        let name = names.length === 1 ? names[0] : '';
        let employee = name ? matchEmployee(name, employees) : null;
        let ambiguous = false;
        if (!names.length) {
            const normalized = ' ' + normalizeName(text) + ' ';
            const found = employees.filter(user => aliases(user).some(alias => normalized.includes(' ' + alias + ' ')));
            if (found.length === 1) {
                employee = found[0];
                name = aliases(employee).filter(alias => normalized.includes(' ' + alias + ' ')).sort((a, b) => b.length - a.length)[0];
            } else ambiguous = found.length > 1;
        }
        return {name, employeeId: employee ? Number(employee.id) : 0, month: foundPeriod.month, year: foundPeriod.year, blocked: names.length > 1, ambiguous, names};
    }
    function groups(rows) {
        const grouped = new Map();
        const usedPages = new Set();
        for (const row of rows.filter(current => current.selected && !current.published)) {
            if (row.blocked) throw new Error('A página ' + row.page + ' contém funcionários diferentes e não pode ser publicada inteira.');
            if (row.manualRequired && !row.manualConfirmed) throw new Error('Confirme o vínculo manual da página ' + row.page + '.');
            if (!Number.isInteger(Number(row.employeeId)) || Number(row.employeeId) <= 0 || !Number.isInteger(row.page) || row.page <= 0) throw new Error('Selecione o funcionário da página ' + row.page + '.');
            const output = filename(row.name, row.month, row.year);
            if (usedPages.has(row.page)) throw new Error('A mesma página foi selecionada mais de uma vez.');
            usedPages.add(row.page);
            const key = row.employeeId + '-' + Number(row.year) + '-' + Number(row.month);
            if (!grouped.has(key)) grouped.set(key, {key, employeeId: Number(row.employeeId), name: row.name, month: Number(row.month), year: Number(row.year), filename: output, pages: [], rows: []});
            const group = grouped.get(key);
            if (normalizeName(group.name) !== normalizeName(row.name)) throw new Error('Há nomes diferentes vinculados ao mesmo funcionário e competência. Revise as páginas selecionadas.');
            group.pages.push(row.page);
            group.rows.push(row);
        }
        return [...grouped.values()].map(group => ({...group, pages: group.pages.sort((a, b) => a - b)}));
    }
    return {months, normalizeName, filename, validPeriod, textLines, aliases, matchEmployee, namedFields, period, identify, groups};
}));
