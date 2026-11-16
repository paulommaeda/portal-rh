<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

?>
    <nav class="portal-rh-bottom-nav">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="<?php echo $is_inicio ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
            Início
        </a>
        <a href="<?php echo esc_url( get_post_type_archive_link('aviso') ); ?>" class="<?php echo $is_avisos ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-3 12H7v-2h10v2zm0-3H7V9h10v2zm0-3H7V6h10v2z"/></svg>
            Avisos
        </a>
        <a href="<?php echo esc_url( home_url( '/enviar-atestado/' ) ); ?>" class="<?php echo $is_atestados ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-1.99.9-1.99 2L3 19c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-2 10h-3v3h-4v-3H7v-4h3V6h4v3h3v4z"/></svg>
            Atestados
        </a>
        <a href="<?php echo esc_url( home_url( '/meus-holerites/' ) ); ?>" class="<?php echo $is_holerites ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
            Holerites
        </a>
        <a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>">
            <svg viewBox="0 0 24 24"><path d="M10.09 15.59L11.5 17l5-5-5-5-1.41 1.41L12.67 11H3v2h9.67l-2.58 2.59zM19 3H5c-1.11 0-2 .9-2 2v4h2V5h14v14H5v-4H3v4c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>
            Sair
        </a>
    </nav>
    <?php

