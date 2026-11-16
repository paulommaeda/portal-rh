# Portal de RH — Holerites e Avisos

Versão **1.6.2**. O arquivo original foi separado em módulos e templates, mantendo os nomes das funções, os shortcodes, os menus, as URLs, o HTML, o CSS, o JavaScript e as regras da versão 1.6.1.

## Organização dos arquivos

| Caminho | Responsabilidade |
| --- | --- |
| `portal-rh.php` | Cabeçalho e carregamento do plugin |
| `includes/avisos.php` | CPT, filtros e seleção das telas de avisos |
| `includes/holerites.php` | Diretório, nomes e download dos holerites |
| `includes/shortcodes.php` | `[lista_avisos]` e `[meus_holerites]` |
| `includes/navegacao.php` | Navegação inferior e seus hooks |
| `includes/atualizacoes.php` | Integração das atualizações com o GitHub |
| `admin/holerites.php` | Upload, listagem e exclusão de holerites |
| `templates/` | HTML, CSS e JavaScript extraídos das funções originais |
| `updates.json` | Metadados incluídos em cada release estável |

As telas são incluídas apenas quando utilizadas. O módulo administrativo é carregado somente no painel. A separação mantém os estilos na mesma posição e não altera a ordem de aplicação do CSS.

## Instalação e primeira atualização

Requisitos do atualizador: WordPress 5.8 ou superior e PHP 7.0 ou superior.

1. Faça backup do plugin atual.
2. Instale o ZIP completo em **Plugins → Adicionar plugin → Enviar plugin**. A pasta padrão é `wp-content/plugins/portal-rh/`, com `portal-rh.php` dentro dela.
3. Se o WordPress reconhecer a instalação existente, escolha substituir pela versão enviada. Se a versão anterior estiver em outra pasta ou for um PHP avulso na raiz de `plugins`, desative-a antes de ativar esta versão, para evitar duas cópias do mesmo plugin.

É necessário instalar esta versão completa uma vez, pois a versão original 1.6.1 não contém o atualizador. Os avisos continuam no banco do WordPress, e os holerites continuam em `uploads/holerites`.

## Atualizações pelo WordPress

O WordPress consulta o `updates.json` da última release estável do GitHub. Quando houver uma versão superior à instalada, ela aparece em **Painel → Atualizações** e em **Plugins**. O ZIP de cada versão é baixado diretamente da respectiva release pública, com a pasta `portal-rh/`. Não é necessário token do GitHub no site.

O resultado é guardado em cache por uma hora; falhas são guardadas por cinco minutos. **Painel → Atualizações → Verificar novamente** ignora esse cache. A preferência de atualizações automáticas é controlada pelo próprio WordPress.

Para publicar uma próxima versão:

1. Altere o cabeçalho `Version` e a constante `PORTAL_RH_VERSION` em `portal-rh.php`.
2. Altere `version` e `changelog` em `updates.json`, mantendo os requisitos coerentes com o código.
3. Publique todos os arquivos da versão em um mesmo commit na branch `main`. O GitHub Actions executa os testes, cria a tag `vX.Y.Z` e publica a release com o ZIP e o `updates.json`. A versão só é anunciada ao WordPress depois da publicação completa dos dois arquivos.

## Verificação

Os testes comparam os tokens PHP, o HTML, o CSS e o JavaScript das 17 funções originais com a versão dividida. Também verificam o carregamento sem saída antecipada e os principais fluxos do atualizador, incluindo cache, falhas de rede, isolamento entre plugins e preservação do diretório de instalação.

```sh
git show 3e80e256eb42e30fad826467ac9745d1abfeab28:portal-rh.php > /tmp/portal-rh-original.php
php tests/regression.php /tmp/portal-rh-original.php
php tests/updates.php
```

Os testes não substituem a homologação no WordPress do site.
