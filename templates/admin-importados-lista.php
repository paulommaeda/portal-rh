<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<section class="prh-panel" id="prh-history">
    <h2>Holerites importados</h2>
    <form method="get" class="prh-toolbar">
        <input type="hidden" name="page" value="portal-rh-importar">
        <label>Competência <input type="month" name="prh_competencia" value="<?php echo esc_attr( $filtro ); ?>"></label>
        <button class="button" type="submit">Filtrar</button>
        <?php if ( $filtro ) : ?><a href="<?php echo esc_url( admin_url( 'admin.php?page=portal-rh-importar#prh-history' ) ); ?>">Limpar</a><?php endif; ?>
        <span><?php echo esc_html( $total ); ?> arquivo(s)</span>
    </form>
    <div class="prh-table-scroll"><table class="widefat striped">
        <thead><tr><th>Arquivo</th><th>Funcionário</th><th>Competência</th><th>Ações</th></tr></thead>
        <tbody>
        <?php if ( ! $registros ) : ?><tr><td colspan="4">Nenhum holerite importado neste filtro.</td></tr><?php endif; ?>
        <?php foreach ( $registros as $registro ) : ?>
            <tr>
                <td><?php echo esc_html( $registro->arquivo ); ?></td>
                <td><?php echo esc_html( $registro->display_name ? $registro->display_name : 'Usuário removido' ); ?><?php if ( $registro->user_login ) : ?> <small>(<?php echo esc_html( $registro->user_login ); ?>)</small><?php endif; ?></td>
                <td><?php echo esc_html( sprintf( '%02d/%04d', $registro->mes, $registro->ano ) ); ?></td>
                <td class="prh-actions">
                    <a class="button button-small" href="<?php echo esc_url( portal_rh_url_importado( $registro->id ) ); ?>">Baixar</a>
                    <form method="post">
                        <?php wp_nonce_field( 'portal_rh_excluir_importado', 'portal_rh_delete_nonce' ); ?>
                        <button type="submit" class="button button-small" name="portal_rh_excluir_importado" value="<?php echo esc_attr( $registro->id ); ?>" onclick="return confirm('Excluir este holerite e remover o acesso do funcionário?');">Excluir</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php if ( $total > 30 ) : ?>
        <p><?php echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'prh_pagina', '%#%' ), 'format' => '', 'current' => $pagina, 'total' => (int) ceil( $total / 30 ) ) ) ); ?></p>
    <?php endif; ?>
</section>
