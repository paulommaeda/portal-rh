<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once PORTAL_RH_PATH . 'includes/gemini.php';

function portal_rh_menu_importacao() {
    add_submenu_page( 'portal-rh-holerites', 'Importar PDF único', 'Importar PDF único', 'manage_options', 'portal-rh-importar', 'portal_rh_pagina_importacao' );
    add_submenu_page( 'portal-rh-holerites', 'Gemini', 'Gemini', 'manage_options', 'portal-rh-gemini', 'portal_rh_pagina_gemini' );
}
add_action( 'admin_menu', 'portal_rh_menu_importacao' );

function portal_rh_importacao_assets( $hook ) {
    if ( ! current_user_can( 'manage_options' ) || empty( $_GET['page'] ) || 'portal-rh-importar' !== $_GET['page'] ) { return; }
    $base = plugin_dir_url( PORTAL_RH_FILE );
    wp_enqueue_style( 'portal-rh-importacao', $base . 'assets/css/importacao.css', array(), PORTAL_RH_VERSION );
    wp_enqueue_script( 'portal-rh-pdf-lib', $base . 'assets/vendor/pdf-lib/pdf-lib.min.js', array(), '1.17.1', true );
    wp_enqueue_script( 'portal-rh-importacao-core', $base . 'assets/js/holerites-core.js', array(), PORTAL_RH_VERSION, true );
    wp_enqueue_script( 'portal-rh-importacao', $base . 'assets/js/importacao.js', array( 'portal-rh-pdf-lib', 'portal-rh-importacao-core' ), PORTAL_RH_VERSION, true );
    $config = array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'portal_rh_importacao' ),
        'pdfjsUrl' => $base . 'assets/vendor/pdfjs/build/pdf.js', 'workerUrl' => $base . 'assets/vendor/pdfjs/build/pdf.worker.js',
        'cMapUrl' => $base . 'assets/vendor/pdfjs/cmaps/', 'fontUrl' => $base . 'assets/vendor/pdfjs/standard_fonts/', 'wasmUrl' => $base . 'assets/vendor/pdfjs/wasm/',
        'gemini' => '' !== portal_rh_gemini_chave(), 'maxUpload' => min( 10 * 1024 * 1024, wp_max_upload_size() ),
    );
    wp_add_inline_script( 'portal-rh-importacao', 'window.PortalRHImportConfig = ' . wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';', 'before' );
}
add_action( 'admin_enqueue_scripts', 'portal_rh_importacao_assets' );

function portal_rh_validar_ajax_importacao() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Acesso negado.' ), 403 ); }
    check_ajax_referer( 'portal_rh_importacao', 'nonce' );
}

function portal_rh_ajax_funcionarios_importacao() {
    portal_rh_validar_ajax_importacao();
    $usuarios = get_users( array( 'orderby' => 'display_name', 'order' => 'ASC' ) );
    update_meta_cache( 'user', wp_list_pluck( $usuarios, 'ID' ) );
    $lista = array();
    foreach ( $usuarios as $usuario ) {
        $completo = trim( $usuario->first_name . ' ' . $usuario->last_name );
        $nomes = array_values( array_unique( array_filter( array( $completo, trim( $usuario->first_name ), trim( $usuario->display_name ) ) ) ) );
        $lista[] = array( 'id' => (int) $usuario->ID, 'label' => ( $completo ? $completo : $usuario->display_name ) . ' (' . $usuario->user_login . ')', 'names' => $nomes );
    }
    wp_send_json_success( $lista );
}
add_action( 'wp_ajax_portal_rh_importar_funcionarios', 'portal_rh_ajax_funcionarios_importacao' );

function portal_rh_validar_upload_importacao() {
    if ( empty( $_FILES['pdf'] ) || ! is_array( $_FILES['pdf'] ) ) { return new WP_Error( 'upload', 'Selecione um PDF.' ); }
    $arquivo = $_FILES['pdf'];
    if ( ! isset( $arquivo['error'], $arquivo['tmp_name'], $arquivo['size'], $arquivo['name'] )
        || ! is_int( $arquivo['error'] ) || UPLOAD_ERR_OK !== $arquivo['error'] || ! is_string( $arquivo['tmp_name'] )
        || ! is_uploaded_file( $arquivo['tmp_name'] ) || $arquivo['size'] <= 0 || $arquivo['size'] > min( 10 * 1024 * 1024, wp_max_upload_size() )
    ) { return new WP_Error( 'upload', 'Falha no envio. Cada holerite separado deve respeitar o limite de upload do servidor e ter no máximo 10 MB.' ); }
    $finfo = finfo_open( FILEINFO_MIME_TYPE );
    $mime = finfo_file( $finfo, $arquivo['tmp_name'] );
    finfo_close( $finfo );
    if ( 'application/pdf' !== $mime ) { return new WP_Error( 'pdf', 'O arquivo recebido não é um PDF.' ); }
    return $arquivo['tmp_name'];
}

function portal_rh_ajax_publicar_importacao() {
    portal_rh_validar_ajax_importacao();
    if ( empty( $_POST['confirmado'] ) || '1' !== $_POST['confirmado'] ) { wp_send_json_error( array( 'message' => 'Confirme a revisão dos destinatários antes de publicar.' ), 400 ); }
    $temporario = portal_rh_validar_upload_importacao();
    if ( is_wp_error( $temporario ) ) { wp_send_json_error( array( 'message' => $temporario->get_error_message() ), 400 ); }
    $nome = isset( $_POST['nome'] ) ? sanitize_text_field( wp_unslash( $_POST['nome'] ) ) : '';
    $mes = isset( $_POST['mes'] ) && is_scalar( $_POST['mes'] ) ? (string) $_POST['mes'] : '';
    $ano = isset( $_POST['ano'] ) && is_scalar( $_POST['ano'] ) ? (string) $_POST['ano'] : '';
    $user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
    if ( ! portal_rh_competencia_valida( $mes, $ano ) ) { wp_send_json_error( array( 'message' => 'Informe uma competência válida.' ), 400 ); }
    $vinculo_manual = isset( $_POST['vinculo_manual'] ) && '1' === $_POST['vinculo_manual'];
    $resultado = portal_rh_publicar_importado( $temporario, $user_id, $nome, $mes, $ano, $vinculo_manual );
    if ( is_wp_error( $resultado ) ) { wp_send_json_error( array( 'message' => $resultado->get_error_message() ), 400 ); }
    wp_send_json_success( array( 'id' => (int) $resultado->id, 'arquivo' => $resultado->arquivo, 'url' => portal_rh_url_importado( $resultado->id ) ) );
}
add_action( 'wp_ajax_portal_rh_importar_publicar', 'portal_rh_ajax_publicar_importacao' );

function portal_rh_ajax_gemini_importacao() {
    portal_rh_validar_ajax_importacao();
    if ( empty( $_POST['autorizar_gemini'] ) || '1' !== $_POST['autorizar_gemini'] ) { wp_send_json_error( array( 'message' => 'Autorize o envio desta página ao Gemini.' ), 400 ); }
    $temporario = portal_rh_validar_upload_importacao();
    if ( is_wp_error( $temporario ) ) { wp_send_json_error( array( 'message' => $temporario->get_error_message() ), 400 ); }
    $resultado = portal_rh_gemini_identificar( $temporario );
    if ( is_wp_error( $resultado ) ) { wp_send_json_error( array( 'message' => $resultado->get_error_message() ), 400 ); }
    wp_send_json_success( $resultado );
}
add_action( 'wp_ajax_portal_rh_importar_gemini', 'portal_rh_ajax_gemini_importacao' );

function portal_rh_pagina_importacao() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Acesso negado.' ); }
    $mensagem = '';
    if ( isset( $_POST['portal_rh_excluir_importado'] ) ) {
        check_admin_referer( 'portal_rh_excluir_importado', 'portal_rh_delete_nonce' );
        $resultado = portal_rh_excluir_importado( absint( $_POST['portal_rh_excluir_importado'] ) );
        $mensagem = is_wp_error( $resultado ) ? $resultado->get_error_message() : 'Holerite excluído.';
    }
    $banco_pronto = 1 === (int) get_option( 'portal_rh_importacao_db', 0 );
    require PORTAL_RH_PATH . 'templates/admin-importacao.php';
}

function portal_rh_historico_importados() {
    if ( 1 !== (int) get_option( 'portal_rh_importacao_db', 0 ) ) { return; }
    global $wpdb;
    $pagina = isset( $_GET['prh_pagina'] ) ? max( 1, absint( $_GET['prh_pagina'] ) ) : 1;
    $filtro = isset( $_GET['prh_competencia'] ) ? sanitize_text_field( wp_unslash( $_GET['prh_competencia'] ) ) : '';
    $tabela = portal_rh_importados_tabela();
    $where = '1=1';
    if ( preg_match( '/^(\d{4})-(\d{2})$/', $filtro, $partes ) && portal_rh_competencia_valida( $partes[2], $partes[1] ) ) {
        $where = $wpdb->prepare( 'h.ano = %d AND h.mes = %d', $partes[1], $partes[2] );
    }
    $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $tabela h WHERE $where" );
    $registros = $wpdb->get_results( $wpdb->prepare( "SELECT h.*, u.display_name, u.user_login FROM $tabela h LEFT JOIN {$wpdb->users} u ON u.ID = h.user_id WHERE $where ORDER BY h.ano DESC, h.mes DESC, h.id DESC LIMIT %d OFFSET %d", 30, ( $pagina - 1 ) * 30 ) );
    require PORTAL_RH_PATH . 'templates/admin-importados-lista.php';
}

function portal_rh_pagina_gemini() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Acesso negado.' ); }
    $salvo = false;
    $erro = '';
    if ( isset( $_POST['portal_rh_salvar_gemini'] ) ) {
        check_admin_referer( 'portal_rh_gemini', 'portal_rh_gemini_nonce' );
        $modelo = isset( $_POST['modelo'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['modelo'] ) ) ) : '';
        if ( ! preg_match( '/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,99}$/', $modelo ) ) { $erro = 'Informe um código de modelo válido, por exemplo gemini-3.5-flash-lite.'; }
        else {
            update_option( 'portal_rh_gemini_model', $modelo, false );
            if ( ! defined( 'PORTAL_RH_GEMINI_API_KEY' ) ) {
                if ( ! empty( $_POST['remover_chave'] ) ) { delete_option( 'portal_rh_gemini_key' ); }
                elseif ( ! empty( $_POST['chave'] ) ) { update_option( 'portal_rh_gemini_key', trim( sanitize_text_field( wp_unslash( $_POST['chave'] ) ) ), false ); }
            }
            $salvo = true;
        }
    }
    require PORTAL_RH_PATH . 'templates/admin-gemini.php';
}
