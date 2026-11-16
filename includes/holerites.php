<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function portal_rh_get_holerites_dir() {
    $upload_dir = wp_upload_dir();
    return trailingslashit( $upload_dir['basedir'] ) . 'holerites';
}

function portal_rh_get_holerites_url() {
    $upload_dir = wp_upload_dir();
    return trailingslashit( $upload_dir['baseurl'] ) . 'holerites';
}

function portal_rh_ensure_holerites_dir() {
    $dir = portal_rh_get_holerites_dir();
    if ( ! file_exists( $dir ) ) { wp_mkdir_p( $dir ); }
    $htaccess = trailingslashit( $dir ) . '.htaccess';
    if ( ! file_exists( $htaccess ) ) { @file_put_contents( $htaccess, "Order Deny,Allow\nDeny from all\nOptions -Indexes\n" ); }
    $index_php = trailingslashit( $dir ) . 'index.php';
    if ( ! file_exists( $index_php ) ) { @file_put_contents( $index_php, "<?php\n// Silent is golden.\n" ); }
}

function portal_rh_normalizar_nome( $nome ) {
    if ( empty( $nome ) ) { return ''; }
    $nome = trim( strip_tags( $nome ) );
    $mapa = array(
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c',
        'Á' => 'A', 'À' => 'A', 'Ã' => 'A', 'Â' => 'A', 'Ä' => 'A', 'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ó' => 'O', 'Ò' => 'O', 'Õ' => 'O', 'Ô' => 'O', 'Ö' => 'O',
        'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ç' => 'C', 'ñ' => 'n', 'Ñ' => 'N',
    );
    $nome = strtr( $nome, $mapa );
    $nome = preg_replace( '/\s+/', '_', $nome );
    $nome = preg_replace( '/[^A-Za-z0-9_]/', '', $nome );
    return strtoupper( $nome );
}

function portal_rh_get_download_url( $arquivo ) {
    $arquivo = sanitize_file_name( $arquivo );
    return add_query_arg(
        array( 'portal_rh_download' => rawurlencode( $arquivo ), 'portal_rh_nonce' => wp_create_nonce( 'portal_rh_download' ) ),
        home_url( '/' )
    );
}

function portal_rh_proxy_download() {
    if ( ! isset( $_GET['portal_rh_download'] ) ) { return; }
    if ( ! is_user_logged_in() ) { wp_die( __( 'Você precisa estar logado para baixar este arquivo.', 'portal-rh' ), __( 'Acesso negado', 'portal-rh' ), array( 'response' => 403 ) ); }
    if ( ! isset( $_GET['portal_rh_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['portal_rh_nonce'] ) ), 'portal_rh_download' ) ) {
        wp_die( __( 'Link de download inválido ou expirado.', 'portal-rh' ), __( 'Erro de segurança', 'portal-rh' ), array( 'response' => 403 ) );
    }
    $arquivo = sanitize_file_name( wp_unslash( $_GET['portal_rh_download'] ) );
    $arquivo = rawurldecode( $arquivo );
    $extensao = strtolower( pathinfo( $arquivo, PATHINFO_EXTENSION ) );
    if ( 'pdf' !== $extensao ) { wp_die( __( 'Tipo de arquivo não permitido.', 'portal-rh' ), __( 'Erro', 'portal-rh' ), array( 'response' => 403 ) ); }
    
    if ( ! current_user_can( 'manage_options' ) ) {
        $nome = get_user_meta( get_current_user_id(), 'first_name', true );
        $nome_normalizado = portal_rh_normalizar_nome( $nome );
        if ( empty( $nome_normalizado ) || false === strpos( strtoupper( $arquivo ), $nome_normalizado ) ) {
            wp_die( __( 'Você não tem permissão para baixar este arquivo.', 'portal-rh' ), __( 'Acesso negado', 'portal-rh' ), array( 'response' => 403 ) );
        }
    }
    $caminho = trailingslashit( portal_rh_get_holerites_dir() ) . $arquivo;
    $dir_real = realpath( portal_rh_get_holerites_dir() );
    $arquivo_real = realpath( $caminho );
    if ( false === $arquivo_real || false === $dir_real || strpos( $arquivo_real, $dir_real ) !== 0 ) {
        wp_die( __( 'Arquivo não encontrado.', 'portal-rh' ), __( 'Erro', 'portal-rh' ), array( 'response' => 404 ) );
    }
    if ( ! file_exists( $arquivo_real ) || ! is_readable( $arquivo_real ) ) {
        wp_die( __( 'Arquivo não encontrado.', 'portal-rh' ), __( 'Erro', 'portal-rh' ), array( 'response' => 404 ) );
    }
    nocache_headers();
    header( 'Content-Description: File Transfer' );
    header( 'Content-Type: application/pdf' );
    header( 'Content-Disposition: attachment; filename="' . $arquivo . '"' );
    header( 'Content-Transfer-Encoding: binary' );
    header( 'Content-Length: ' . filesize( $arquivo_real ) );
    header( 'Expires: 0' );
    header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
    header( 'Pragma: public' );
    while ( ob_get_level() ) { ob_end_clean(); }
    readfile( $arquivo_real );
    exit;
}
add_action( 'template_redirect', 'portal_rh_proxy_download' );
