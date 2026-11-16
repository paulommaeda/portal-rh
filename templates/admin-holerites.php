<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Portal de RH - Holerites', 'portal-rh' ); ?></h1>
        <?php if ( $mensagem ) : ?><div class="notice <?php echo esc_attr( $tipo_mensagem ); ?> is-dismissible"><p><?php echo wp_kses_post( $mensagem ); ?></p></div><?php endif; ?>
        
        <h2><?php esc_html_e( 'Enviar novos holerites (PDF)', 'portal-rh' ); ?></h2>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field( 'portal_rh_upload', 'portal_rh_nonce' ); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="portal_rh_arquivos"><?php esc_html_e( 'Arquivos PDF', 'portal-rh' ); ?></label></th>
                    <td><input type="file" name="portal_rh_arquivos[]" id="portal_rh_arquivos" accept="application/pdf" multiple required /></td>
                </tr>
            </table>
            <p class="submit"><input type="submit" name="portal_rh_submit" class="button button-primary" value="<?php esc_attr_e( 'Enviar arquivos', 'portal-rh' ); ?>" /></p>
        </form>

        <h2><?php esc_html_e( 'Arquivos enviados', 'portal-rh' ); ?></h2>
        <?php if ( empty( $arquivos ) ) : ?>
            <p><?php esc_html_e( 'Nenhum arquivo encontrado.', 'portal-rh' ); ?></p>
        <?php else : ?>
            <form method="post" action="<?php echo esc_url( $url_admin ); ?>">
                <?php wp_nonce_field( 'portal_rh_bulk_delete', 'portal_rh_bulk_nonce' ); ?>
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <select name="portal_rh_acao_lote"><option value=""><?php esc_html_e( 'Ações em lote', 'portal-rh' ); ?></option><option value="excluir_lote"><?php esc_html_e( 'Excluir selecionados', 'portal-rh' ); ?></option></select>
                        <input type="submit" class="button action" value="<?php esc_attr_e( 'Aplicar', 'portal-rh' ); ?>" onclick="return confirm('Confirma a exclusão?');" />
                    </div><br class="clear" />
                </div>
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr><td class="manage-column column-cb check-column"><input type="checkbox" id="portal_rh_selecionar_todos" /></td><th scope="col"><?php esc_html_e( 'Arquivo', 'portal-rh' ); ?></th><th scope="col"><?php esc_html_e( 'Tamanho', 'portal-rh' ); ?></th><th scope="col"><?php esc_html_e( 'Ações', 'portal-rh' ); ?></th></tr></thead>
                    <tbody>
                        <?php foreach ( $arquivos as $arq ) : ?>
                            <?php
                            $caminho_arq = trailingslashit( $dir ) . $arq; $tamanho = filesize( $caminho_arq ); $url_download = portal_rh_get_download_url( $arq );
                            $link_excluir = wp_nonce_url( add_query_arg( array( 'page' => 'portal-rh-holerites', 'acao' => 'excluir', 'arquivo' => $arq ), admin_url( 'admin.php' ) ), 'portal_rh_excluir', 'portal_rh_nonce' );
                            ?>
                            <tr>
                                <th scope="row" class="check-column"><input type="checkbox" name="portal_rh_arquivos_lote[]" value="<?php echo esc_attr( $arq ); ?>" class="portal-rh-arquivo-cb" /></th>
                                <td><strong><?php echo esc_html( $arq ); ?></strong></td>
                                <td><?php echo esc_html( size_format( $tamanho ) ); ?></td>
                                <td>
                                    <a href="<?php echo esc_url( $url_download ); ?>" download="<?php echo esc_attr( $arq ); ?>" class="button button-small"><?php esc_html_e( 'Baixar', 'portal-rh' ); ?></a>
                                    <a href="<?php echo esc_url( $link_excluir ); ?>" class="button button-small" onclick="return confirm('Excluir este arquivo?');"><?php esc_html_e( 'Excluir', 'portal-rh' ); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
            <script type="text/javascript">
                document.addEventListener('DOMContentLoaded', function() {
                    var selecionarTodos = document.getElementById('portal_rh_selecionar_todos');
                    if (selecionarTodos) {
                        selecionarTodos.addEventListener('change', function() {
                            var checkboxes = document.querySelectorAll('.portal-rh-arquivo-cb');
                            for (var i = 0; i < checkboxes.length; i++) { checkboxes[i].checked = this.checked; }
                        });
                    }
                });
            </script>
        <?php endif; ?>
    </div>
    <?php

