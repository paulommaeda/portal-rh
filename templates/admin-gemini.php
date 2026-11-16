<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="wrap">
    <h1>Gemini — identificação dos holerites</h1>
    <?php if ( $salvo ) : ?><div class="notice notice-success"><p>Configurações salvas.</p></div><?php endif; ?>
    <?php if ( $erro ) : ?><div class="notice notice-error"><p><?php echo esc_html( $erro ); ?></p></div><?php endif; ?>
    <p>O Gemini é opcional. O importador tenta identificar os dados pelo texto do PDF primeiro. A API é consultada somente ao usar o botão “Identificar pendentes com Gemini”.</p>
    <form method="post" autocomplete="off">
        <?php wp_nonce_field( 'portal_rh_gemini', 'portal_rh_gemini_nonce' ); ?>
        <table class="form-table" role="presentation">
            <tr><th><label for="prh-key">Chave da API Gemini</label></th><td>
                <?php if ( defined( 'PORTAL_RH_GEMINI_API_KEY' ) ) : ?>
                    <p>A chave está configurada no servidor por PORTAL_RH_GEMINI_API_KEY.</p>
                <?php else : ?>
                    <input id="prh-key" name="chave" type="password" class="regular-text" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( '' !== portal_rh_gemini_chave() ? 'Chave cadastrada. Deixe vazio para manter.' : 'Cole sua chave da API' ); ?>">
                    <p class="description">A chave fica no servidor e não é enviada ao navegador do funcionário.</p>
                    <?php if ( '' !== portal_rh_gemini_chave() ) : ?><p><label><input type="checkbox" name="remover_chave" value="1"> Remover a chave cadastrada</label></p><?php endif; ?>
                <?php endif; ?>
            </td></tr>
            <tr><th><label for="prh-model">Modelo</label></th><td><input id="prh-model" name="modelo" type="text" class="regular-text" value="<?php echo esc_attr( portal_rh_gemini_modelo() ); ?>" required><p class="description">Informe um modelo disponível na sua conta que aceite PDF e saída JSON estruturada.</p></td></tr>
        </table>
        <p>Ao solicitar identificação com IA, as páginas selecionadas são enviadas à API do Google usando esta chave. O uso segue os limites e a cobrança da sua conta.</p>
        <p class="submit"><button class="button button-primary" name="portal_rh_salvar_gemini" value="1" type="submit">Salvar configurações</button> <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=portal-rh-importar' ) ); ?>">Voltar à importação</a></p>
    </form>
</div>
