# Portal de RH — Holerites e Avisos

Versão **1.7.0**. Holerites, avisos e navegação inferior, com importação de um PDF único e atualização pelo painel do WordPress.

## Importar PDF único

1. Acesse **Holerites (RH) → Importar PDF único** e selecione a folha completa.
2. A competência do lote é opcional; quando preenchida, será aplicada a todas as páginas.
3. Clique em **Identificar e separar**.
4. Abra a prévia e confira nome, competência e conta do funcionário. Corrija os campos pendentes. Nomes diferentes do cadastro e homônimos exigem confirmação do vínculo manual.
5. Selecione as páginas, confirme os destinatários e clique em **Publicar holerites selecionados**.

Padrão: **`DANIEL-LIMA-CUNHA_MARCO_DE_2026.pdf`**. Páginas do mesmo funcionário e competência são reunidas em um PDF, na ordem original. O arquivo completo é lido no navegador; apenas os documentos separados são enviados ao publicar.

O original pode ter até 100 MB e 1.000 páginas. Cada arquivo publicado deve respeitar o limite do servidor e ter no máximo 10 MB. Use PDF sem senha. Páginas com funcionários diferentes são bloqueadas; exporte a folha com um funcionário por página. Duas vias do mesmo funcionário podem permanecer na mesma página.

## Gemini opcional

Em **Holerites (RH) → Gemini**, cadastre a chave da API e um modelo com entrada PDF e saída JSON estruturada. O padrão é `gemini-3.5-flash-lite`, configurável conforme sua conta. A constante `PORTAL_RH_GEMINI_API_KEY` em `wp-config.php` também é aceita.

A leitura inicial usa o texto do PDF. Para páginas digitalizadas ou pendentes, autorize o envio e clique em **Identificar pendentes com Gemini**. Somente essas páginas são enviadas à API do Google. O uso segue os limites e a cobrança da sua conta. A chave fica no servidor, sem aparecer no JavaScript nem nas URLs. A resposta contém nomes e competência e passa pela mesma revisão. Também é possível preencher os campos manualmente pela prévia.

## Arquivos e acesso

Os novos documentos aparecem no shortcode existente **`[meus_holerites]`**, com o mesmo botão de download. Cada registro pertence ao ID da conta, para isolar o acesso entre funcionários com nomes iguais. Uploads antigos continuam pelo mecanismo anterior.

Os novos arquivos ficam em diretórios individuais com identificadores aleatórios em `uploads/holerites/importados/`. O portal verifica login, nonce e proprietário ou permissão administrativa, sem expor caminhos físicos. A pasta inclui bloqueios Apache/IIS e índice fechado.

A lista de importados no final da nova tela tem filtro por competência, download e exclusão. Arquivos do mesmo funcionário e competência não são sobrescritos; confira e exclua o anterior para substituí-lo. Um envio idêntico repetido não cria duplicidade. Uploads antigos continuam na tela **Holerites (RH)**.

## Instalação e atualizações

WordPress 5.8+, PHP 7.0+ com Fileinfo e navegador atualizado. Instale o **ZIP da release**, que inclui as bibliotecas PDF. A pasta padrão é `wp-content/plugins/portal-rh/`. Faça backup antes de substituir. Se a versão anterior estiver em outra pasta ou for um PHP avulso, desative-a antes de ativar esta versão.

Quem usa a 1.6.2 pode atualizar em **Painel → Atualizações** ou **Plugins**. O manifesto da última release é consultado com cache de uma hora; **Verificar novamente** ignora esse cache. Atualização automática segue a preferência do WordPress.

A tabela da importação é criada na ativação ou na primeira visita administrativa após atualizar. Possui índices por funcionário e competência. Administração e Gemini são carregados no painel; bibliotecas PDF, somente na importação.

## Desenvolvimento

O código permanece separado em `includes/`, `admin/`, `templates/` e `assets/`. O módulo `includes/holerites-importados.php` gerencia arquivos e autorização; `includes/gemini.php` integra a API; `assets/js/holerites-core.js` identifica e agrupa; `assets/js/importacao.js` conduz a revisão.

O ZIP “Source code” contém o código-fonte. Execute `node scripts/build-pdf-assets.mjs` em ambiente com Node atualizado, rede, `tar` e `unzip` para preparar PDF.js 6.5.438 e pdf-lib 1.17.1. As bibliotecas são verificadas, acompanham licenças e são servidas pelo próprio site.

Para uma nova versão, altere o cabeçalho `Version`, a constante `PORTAL_RH_VERSION` e `updates.json`, e publique em `main`. O GitHub Actions testa, prepara as dependências e publica a release com ZIP e manifesto.

```sh
git show 3e80e256eb42e30fad826467ac9745d1abfeab28:portal-rh.php > /tmp/portal-rh-original.php
php tests/regression.php /tmp/portal-rh-original.php
php tests/updates.php
php tests/importacao.php
php tests/gemini.php
node tests/importacao-core.cjs
```

Os testes comparam as 15 funções originais preservadas e a saída do shortcode ampliado para arquivos antigos. Verificam nomes, competências, homônimos, autorização, nonce, duplicidade, falhas de gravação e Gemini simulado. O Chromium usa PDFs gerados para conferir leitura, agrupamento e isolamento dos documentos exportados. A API Gemini real e o layout específico da folha devem ser homologados com sua chave e seus documentos.
