<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function portal_rh_inject_bottom_nav_css() {
    if ( ! is_user_logged_in() || is_admin() ) return;
    require PORTAL_RH_PATH . 'templates/navegacao-css.php';
}
add_action( 'wp_head', 'portal_rh_inject_bottom_nav_css' );

function portal_rh_inject_bottom_nav_html() {
    if ( ! is_user_logged_in() || is_admin() ) return;

    global $wp;
    $current_url = home_url( add_query_arg( array(), $wp->request ) );
    $path = parse_url( $current_url, PHP_URL_PATH );
    $path = $path ? $path : '/';

    $is_avisos    = ( strpos( $path, 'aviso' ) !== false || is_singular( 'aviso' ) || is_post_type_archive( 'aviso' ) );
    $is_holerites = ( strpos( $path, 'meus-holerites' ) !== false );
    $is_atestados = ( strpos( $path, 'enviar-atestado' ) !== false );
    $is_inicio    = ( ! $is_avisos && ! $is_holerites && ! $is_atestados && $path === '/' );
    require PORTAL_RH_PATH . 'templates/navegacao.php';
}
add_action( 'wp_footer', 'portal_rh_inject_bottom_nav_html' );
