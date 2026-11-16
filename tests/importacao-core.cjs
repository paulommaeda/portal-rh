const assert = require('node:assert/strict');
const core = require('../assets/js/holerites-core.js');
const employees = [
    {id: 12, names: ['Daniel Lima Cunha', 'Daniel L. Cunha']},
    {id: 13, names: ['Maria da Silva']},
];
assert.equal(core.filename('Daniel Lima Cunha', 3, 2026), 'DANIEL-LIMA-CUNHA_MARCO_DE_2026.pdf');
assert.equal(core.filename('João da Silva', 12, 2026), 'JOAO-DA-SILVA_DEZEMBRO_DE_2026.pdf');
for (const values of [['Daniel', 3, 2026], ['Daniel Lima', 0, 2026], ['Daniel Lima', 13, 2026], ['Daniel Lima', 3, '2026x']]) {
    assert.throws(() => core.filename(...values));
}
const standard = 'RECIBO DE PAGAMENTO\nCompetência: Março de 2026\nCódigo Nome do Funcionário CBO\n001 DANIEL LIMA CUNHA 123456\nAdmissão: 05/01/2020';
const recognized = core.identify(standard, employees, '');
assert.equal(recognized.name, 'DANIEL LIMA CUNHA');
assert.equal(recognized.employeeId, 12);
assert.equal(recognized.month, 3);
assert.equal(recognized.year, 2026);
assert.equal(recognized.blocked, false);
assert.equal(core.identify(standard, employees, '2027-04').month, 4);
assert.equal(core.identify(standard, employees, '2027-04').year, 2027);
assert.equal(core.identify('DANIEL LIMA CUNHA\nReferência 03/2026', employees).employeeId, 12);
assert.equal(core.identify('Documento digitalizado sem texto', employees).employeeId, 0);
assert.equal(core.identify('Daniel\n03/2026', employees).employeeId, 0, 'A first name alone must never assign a recipient.');
assert.equal(core.identify('DANIEL LIMA CUNHA', [...employees, {id: 99, names: ['Daniel Lima Cunha']}]).employeeId, 0, 'Homonyms need an explicit choice.');
assert.deepEqual(core.period('Admissão: 01/03/2020\nPagamento: 05/04/2026'), {month: 0, year: 0});
assert.deepEqual(core.period('Competência: 03/2026\nCompetência: 04/2026'), {month: 0, year: 0});
assert.deepEqual(core.period('Referência: 03/2026\nData de pagamento: 05/04/2026'), {month: 3, year: 2026});
const twoEmployees = standard + '\nNome do Funcionário: MARIA DA SILVA\nCompetência: Março de 2026';
assert.equal(core.identify(twoEmployees, employees).blocked, true, 'Two employees on one page must not be distributed together.');
assert.equal(core.identify(standard + '\n' + standard, employees).blocked, false, 'Two copies for the same employee are one recipient.');
const row = {...recognized, page: 1, selected: true};
const grouped = core.groups([row, {...row, page: 2}, {...row, page: 3, selected: false}, {...row, page: 4, employeeId: 13, name: 'Maria da Silva'}]);
assert.equal(grouped.length, 2);
assert.deepEqual(grouped[0].pages, [1, 2]);
assert.equal(grouped[0].filename, 'DANIEL-LIMA-CUNHA_MARCO_DE_2026.pdf');
assert.throws(() => core.groups([row, {...row}]));
assert.throws(() => core.groups([{...row, blocked: true}]));
assert.throws(() => core.groups([{...row, manualRequired: true, manualConfirmed: false}]));
assert.throws(() => core.groups([row, {...row, page: 2, name: 'Nome Diferente'}]));
assert.equal(core.groups([{...row, published: true}]).length, 0);
const items = [{str: 'CUNHA', transform: [1,0,0,1,200,700]}, {str: 'Competência 03/2026', transform: [1,0,0,1,20,740]}, {str: 'DANIEL LIMA', transform: [1,0,0,1,20,700]}];
assert.equal(core.textLines(items), 'Competência 03/2026\nDANIEL LIMA CUNHA');
console.log('PASS: filenames, competency, name recognition, homonyms, mixed pages and page grouping.');
