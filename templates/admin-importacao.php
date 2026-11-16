<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="wrap prh-import" id="prh-import-app">
    <h1>Importar holerites de um PDF único</h1>
    <?php if ( $mensagem ) : ?><div class="notice notice-info"><p><?php echo esc_html( $mensagem ); ?></p></div><?php endif; ?>
    <?php if ( ! $banco_pronto ) : ?>
        <div class="notice notice-error"><p>Não foi possível preparar o cadastro de holerites. Confira a permissão de criação de tabelas no banco e recarregue a página.</p></div>
    <?php else : ?>
        <p>Selecione o PDF com todos os holerites, confira as páginas e publique os arquivos para cada funcionário.</p>
        <section class="prh-panel" aria-labelledby="prh-select-title">
            <h2 id="prh-select-title">1. Selecionar o arquivo</h2>
            <div class="prh-fields">
                <label>PDF com todos os holerites<input id="prh-pdf" type="file" accept="application/pdf,.pdf"></label>
                <label>Competência do lote (opcional)<input id="prh-period" type="month" min="1900-01" max="2199-12"><span class="description">Se preencher, esta competência será aplicada a todas as páginas.</span></label>
            </div>
            <p><button type="button" class="button button-primary" id="prh-analyze">Identificar e separar</button> <span id="prh-file-summary"></span></p>
            <p class="description">A leitura inicial acontece no seu navegador. Os arquivos separados são enviados ao WordPress somente ao publicar.</p>
        </section>
        <div id="prh-message" class="prh-message" role="status" aria-live="polite"></div>
        <progress id="prh-progress" hidden max="100" value="0"></progress>
        <section class="prh-panel" id="prh-review" hidden aria-labelledby="prh-review-title">
            <h2 id="prh-review-title">2. Conferir os destinatários</h2>
            <p>Abra a prévia e confira o nome, a competência e a conta de cada funcionário. Páginas do mesmo funcionário e competência serão reunidas em um único PDF.</p>
            <div class="prh-toolbar">
                <button type="button" class="button" id="prh-select-valid">Selecionar páginas preenchidas</button>
                <span id="prh-selection-summary"></span>
            </div>
            <div class="prh-ai">
                <?php if ( '' !== portal_rh_gemini_chave() ) : ?>
                    <label><input type="checkbox" id="prh-ai-consent"> Autorizar o envio das páginas pendentes à API Gemini configurada para identificar nome e competência.</label>
                    <button type="button" class="button" id="prh-ai-identify">Identificar pendentes com Gemini</button>
                <?php else : ?>
                    <span>Se alguma página não for reconhecida, preencha os dados manualmente ou <a href="<?php echo esc_url( admin_url( 'admin.php?page=portal-rh-gemini' ) ); ?>" target="_blank" rel="noopener">configure o Gemini</a>.</span>
                <?php endif; ?>
            </div>
            <datalist id="prh-employees"></datalist>
            <div class="prh-table-scroll"><table class="widefat striped prh-review-table">
                <thead><tr><th>Publicar</th><th>Página</th><th>Nome no holerite</th><th>Competência</th><th>Conta do funcionário</th><th>Arquivo / situação</th></tr></thead>
                <tbody id="prh-rows"></tbody>
            </table></div>
            <div class="prh-publish">
                <label><input type="checkbox" id="prh-confirm"> Conferi os nomes, as competências e os destinatários das páginas selecionadas.</label>
                <p><button type="button" class="button button-primary button-hero" id="prh-publish">Publicar holerites selecionados</button></p>
                <p class="description">Os funcionários recebem acesso pelo mesmo botão de download em Meus Holerites. Arquivos existentes não são sobrescritos.</p>
            </div>
        </section>
        <section class="prh-panel" id="prh-results" hidden><h2>Resultado da publicação</h2><ul id="prh-result-list"></ul><p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=portal-rh-importar#prh-history' ) ); ?>">Atualizar lista de holerites</a></p></section>
        <?php portal_rh_historico_importados(); ?>
    <?php endif; ?>
</div>
