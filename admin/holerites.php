<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function portal_rh_admin_menu() {
    add_menu_page( __( 'Holerites (RH)', 'portal-rh' ), __( 'Holerites (RH)', 'portal-rh' ), 'manage_options', 'portal-rh-holerites', 'portal_rh_admin_page', 'dashicons-media-document', 27 );
}
add_action( 'admin_menu', 'portal_rh_admin_menu' );

function portal_rh_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( __( 'Você não tem permissão para acessar esta página.', 'portal-rh' ) ); }
    portal_rh_ensure_holerites_dir();
    $mensagem = ''; $tipo_mensagem = 'updated';

    if ( isset( $_POST['portal_rh_submit'] ) ) {
        check_admin_referer( 'portal_rh_upload', 'portal_rh_nonce' );
        $sucessos = array(); $falhas = array();
        if ( ! empty( $_FILES['portal_rh_arquivos']['name'] ) && is_array( $_FILES['portal_rh_arquivos']['name'] ) ) {
            $arquivos = $_FILES['portal_rh_arquivos'];
            $total = count( $arquivos['name'] );
            for ( $i = 0; $i < $total; $i++ ) {
                if ( empty( $arquivos['name'][ $i ] ) ) { continue; }
                $nome_original = $arquivos['name'][ $i ]; $tmp_name = $arquivos['tmp_name'][ $i ]; $tamanho = $arquivos['size'][ $i ]; $erro = $arquivos['error'][ $i ];
                if ( UPLOAD_ERR_OK !== $erro ) { $falhas[] = sprintf( __( '%s (erro no upload)', 'portal-rh' ), esc_html( $nome_original ) ); continue; }
                $extensao = strtolower( pathinfo( $nome_original, PATHINFO_EXTENSION ) );
                if ( 'pdf' !== $extensao ) { $falhas[] = sprintf( __( '%s (apenas PDF)', 'portal-rh' ), esc_html( $nome_original ) ); continue; }
                if ( $tamanho > ( 10 * 1024 * 1024 ) ) { $falhas[] = sprintf( __( '%s (excede 10 MB)', 'portal-rh' ), esc_html( $nome_original ) ); continue; }
                $finfo = finfo_open( FILEINFO_MIME_TYPE ); $mime = finfo_file( $finfo, $tmp_name ); finfo_close( $finfo );
                if ( 'application/pdf' !== $mime ) { $falhas[] = sprintf( __( '%s (PDF inválido)', 'portal-rh' ), esc_html( $nome_original ) ); continue; }
                $nome_arquivo = sanitize_file_name( $nome_original );
                $destino = trailingslashit( portal_rh_get_holerites_dir() ) . $nome_arquivo;
                if ( file_exists( $destino ) ) { $falhas[] = sprintf( __( '%s (já existe)', 'portal-rh' ), esc_html( $nome_original ) ); continue; }
                if ( move_uploaded_file( $tmp_name, $destino ) ) { $sucessos[] = $nome_arquivo; } else { $falhas[] = sprintf( __( '%s (erro ao salvar)', 'portal-rh' ), esc_html( $nome_original ) ); }
            }
            $partes = array();
            if ( ! empty( $sucessos ) ) { $partes[] = '<strong>Enviados:</strong> ' . implode(', ', $sucessos); }
            if ( ! empty( $falhas ) ) { $tipo_mensagem = 'error'; $partes[] = '<strong>Falhas:</strong> ' . implode(', ', $falhas); }
            if ( empty( $sucessos ) && empty( $falhas ) ) { $mensagem = __( 'Nenhum arquivo válido.', 'portal-rh' ); $tipo_mensagem = 'error'; } 
            else { $mensagem = implode( '<br/>', $partes ); if ( ! empty( $sucessos ) && ! empty( $falhas ) ) { $tipo_mensagem = 'notice-warning'; } }
        } else { $mensagem = __( 'Nenhum arquivo selecionado.', 'portal-rh' ); $tipo_mensagem = 'error'; }
    }

    if ( isset( $_GET['acao'] ) && 'excluir' === $_GET['acao'] && isset( $_GET['arquivo'] ) ) {
        check_admin_referer( 'portal_rh_excluir', 'portal_rh_nonce' );
        $arquivo_excluir = sanitize_file_name( wp_unslash( $_GET['arquivo'] ) );
        $caminho = trailingslashit( portal_rh_get_holerites_dir() ) . $arquivo_excluir;
        if ( file_exists( $caminho ) && unlink( $caminho ) ) { $mensagem = sprintf( __( 'Arquivo "%s" excluído.', 'portal-rh' ), esc_html( $arquivo_excluir ) ); } 
        else { $mensagem = __( 'Erro ao excluir.', 'portal-rh' ); $tipo_mensagem = 'error'; }
    }

    if ( isset( $_POST['portal_rh_acao_lote'] ) && 'excluir_lote' === $_POST['portal_rh_acao_lote'] ) {
        check_admin_referer( 'portal_rh_bulk_delete', 'portal_rh_bulk_nonce' );
        $arquivos_lote = isset( $_POST['portal_rh_arquivos_lote'] ) ? (array) $_POST['portal_rh_arquivos_lote'] : array();
        $excluidos = array(); $erros_lote = array();
        foreach ( $arquivos_lote as $arq_lote ) {
            $arq_lote = sanitize_file_name( wp_unslash( $arq_lote ) );
            $caminho = trailingslashit( portal_rh_get_holerites_dir() ) . $arq_lote;
            if ( file_exists( $caminho ) && unlink( $caminho ) ) { $excluidos[] = $arq_lote; } else { $erros_lote[] = $arq_lote; }
        }
        $partes_lote = array();
        if ( ! empty( $excluidos ) ) { $partes_lote[] = sprintf( __( 'Excluídos (%d).', 'portal-rh' ), count( $excluidos ) ); }
        if ( ! empty( $erros_lote ) ) { $tipo_mensagem = 'error'; $partes_lote[] = sprintf( __( 'Erro ao excluir (%d).', 'portal-rh' ), count( $erros_lote ) ); }
        if ( empty( $excluidos ) && empty( $erros_lote ) ) { $mensagem = __( 'Nenhum arquivo selecionado.', 'portal-rh' ); $tipo_mensagem = 'error'; } 
        else { $mensagem = implode( ' ', $partes_lote ); if ( ! empty( $excluidos ) && ! empty( $erros_lote ) ) { $tipo_mensagem = 'notice-warning'; } }
    }

    $arquivos = array();
    $dir = portal_rh_get_holerites_dir();
    if ( is_dir( $dir ) ) {
        $itens = scandir( $dir );
        foreach ( $itens as $item ) {
            if ( '.' === $item || '..' === $item || '.htaccess' === $item || 'index.php' === $item ) { continue; }
            if ( is_file( trailingslashit( $dir ) . $item ) && 'pdf' === strtolower( pathinfo( $item, PATHINFO_EXTENSION ) ) ) { $arquivos[] = $item; }
        }
    }

    $url_admin = admin_url( 'admin.php?page=portal-rh-holerites' );
    require PORTAL_RH_PATH . 'templates/admin-holerites.php';
}
