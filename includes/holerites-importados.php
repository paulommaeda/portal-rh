<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function portal_rh_importados_tabela() {
    global $wpdb;
    return $wpdb->prefix . 'portal_rh_holerites';
}

function portal_rh_instalar_importacao() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $tabela = portal_rh_importados_tabela();
    $charset = $wpdb->get_charset_collate();
    dbDelta( "CREATE TABLE $tabela (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        arquivo varchar(191) NOT NULL,
        storage_key char(32) NOT NULL,
        mes tinyint(3) unsigned NOT NULL,
        ano smallint(5) unsigned NOT NULL,
        sha256 char(64) NOT NULL,
        criado_em datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY usuario_competencia (user_id,ano,mes),
        UNIQUE KEY storage_key (storage_key)
    ) $charset;" );
    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $tabela ) ) ) === $tabela ) {
        update_option( 'portal_rh_importacao_db', 1, false );
    }
}
register_activation_hook( PORTAL_RH_FILE, 'portal_rh_instalar_importacao' );

function portal_rh_atualizar_importacao() {
    if ( current_user_can( 'manage_options' ) && 1 !== (int) get_option( 'portal_rh_importacao_db', 0 ) ) {
        portal_rh_instalar_importacao();
    }
}
add_action( 'admin_init', 'portal_rh_atualizar_importacao' );

function portal_rh_competencia_valida( $mes, $ano ) {
    return ctype_digit( (string) $mes ) && ctype_digit( (string) $ano )
        && (int) $mes >= 1 && (int) $mes <= 12 && (int) $ano >= 1900 && (int) $ano <= 2199;
}

function portal_rh_nome_arquivo_importado( $nome, $mes, $ano ) {
    $meses = array( 1 => 'JANEIRO', 'FEVEREIRO', 'MARCO', 'ABRIL', 'MAIO', 'JUNHO', 'JULHO', 'AGOSTO', 'SETEMBRO', 'OUTUBRO', 'NOVEMBRO', 'DEZEMBRO' );
    $nome = strtoupper( remove_accents( wp_strip_all_tags( (string) $nome ) ) );
    $nome = trim( preg_replace( '/[^A-Z0-9]+/', '-', $nome ), '-' );
    if ( ! portal_rh_competencia_valida( $mes, $ano ) || strlen( $nome ) < 5 || strlen( $nome ) > 140 || false === strpos( $nome, '-' ) ) {
        return new WP_Error( 'dados_invalidos', __( 'Informe o nome completo e uma competência válida.', 'portal-rh' ) );
    }
    return $nome . '_' . $meses[ (int) $mes ] . '_DE_' . (int) $ano . '.pdf';
}

function portal_rh_importados_para_usuario( $user_id ) {
    if ( (int) $user_id <= 0 || 1 !== (int) get_option( 'portal_rh_importacao_db', 0 ) ) { return array(); }
    global $wpdb;
    $tabela = portal_rh_importados_tabela();
    return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $tabela WHERE user_id = %d ORDER BY ano DESC, mes DESC, id DESC", $user_id ) );
}

/** A autorização é feita no banco, antes de consultar qualquer caminho no disco. */
function portal_rh_importado_autorizado( $id ) {
    if ( ! is_user_logged_in() || (int) $id <= 0 || 1 !== (int) get_option( 'portal_rh_importacao_db', 0 ) ) { return null; }
    global $wpdb;
    $tabela = portal_rh_importados_tabela();
    if ( current_user_can( 'manage_options' ) ) {
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $tabela WHERE id = %d", $id ) );
    }
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $tabela WHERE id = %d AND user_id = %d", $id, get_current_user_id() ) );
}

function portal_rh_url_importado( $id ) {
    return add_query_arg( array( 'portal_rh_holerite' => (int) $id, 'portal_rh_nonce' => wp_create_nonce( 'portal_rh_download' ) ), home_url( '/' ) );
}

function portal_rh_caminho_importado( $registro ) {
    if ( ! $registro || ! preg_match( '/^[a-f0-9]{32}$/', $registro->storage_key )
        || basename( $registro->arquivo ) !== $registro->arquivo || 'pdf' !== strtolower( pathinfo( $registro->arquivo, PATHINFO_EXTENSION ) )
    ) { return false; }
    $raiz = realpath( trailingslashit( portal_rh_get_holerites_dir() ) . 'importados' );
    $arquivo = realpath( trailingslashit( portal_rh_get_holerites_dir() ) . 'importados/' . $registro->storage_key . '/' . $registro->arquivo );
    if ( false === $raiz || false === $arquivo || 0 !== strpos( $arquivo, $raiz . DIRECTORY_SEPARATOR ) || ! is_file( $arquivo ) || ! is_readable( $arquivo ) ) { return false; }
    return $arquivo;
}

function portal_rh_download_importado() {
    if ( ! isset( $_GET['portal_rh_holerite'] ) ) { return; }
    if ( ! is_user_logged_in() || ! isset( $_GET['portal_rh_nonce'] )
        || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['portal_rh_nonce'] ) ), 'portal_rh_download' )
    ) { wp_die( __( 'Link inválido ou acesso não autorizado.', 'portal-rh' ), '', array( 'response' => 403 ) ); }
    $registro = portal_rh_importado_autorizado( absint( $_GET['portal_rh_holerite'] ) );
    $caminho = portal_rh_caminho_importado( $registro );
    if ( ! $caminho ) { wp_die( __( 'Holerite não encontrado.', 'portal-rh' ), '', array( 'response' => 404 ) ); }
    nocache_headers();
    header( 'Content-Type: application/pdf' );
    header( 'X-Content-Type-Options: nosniff' );
    header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $registro->arquivo ) . '"' );
    header( 'Content-Length: ' . filesize( $caminho ) );
    while ( ob_get_level() ) { ob_end_clean(); }
    readfile( $caminho );
    exit;
}
add_action( 'template_redirect', 'portal_rh_download_importado', 5 );

function portal_rh_item_importado_html( $registro ) {
    $arq = $registro->arquivo;
    $url_download = portal_rh_url_importado( $registro->id );
    $html = '<li style="display:flex;align-items:center;justify-content:space-between;padding:15px;background:#fff;border-radius:12px;box-shadow:0 4px 10px rgba(0,0,0,0.03);border-left:4px solid #5e4c73;">';
    $html .= '<span style="display:flex;align-items:center;color:#4a4a4a;font-weight:700;"><span style="font-size:20px;margin-right:10px;">📄</span>' . esc_html( $arq ) . '</span>';
    $html .= '<a href="' . esc_url( $url_download ) . '" download="' . esc_attr( $arq ) . '" style="padding:8px 16px;background:#3bc2a2;color:#fff;text-decoration:none;border-radius:6px;font-size:13px;font-weight:700;">Baixar</a></li>';
    return $html;
}

/** Arquivos importados usam diretórios individuais, sem expor o caminho no portal. */
function portal_rh_preparar_pasta_importados() {
    portal_rh_ensure_holerites_dir();
    $raiz = trailingslashit( portal_rh_get_holerites_dir() ) . 'importados';
    if ( ! wp_mkdir_p( $raiz ) ) { return new WP_Error( 'pasta', __( 'Não foi possível preparar a pasta dos holerites.', 'portal-rh' ) ); }
    $protecao = "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder Deny,Allow\nDeny from all\n</IfModule>\nOptions -Indexes\n";
    foreach ( array( '.htaccess' => $protecao, 'index.php' => "<?php\nexit;\n", 'web.config' => '<?xml version="1.0"?><configuration><system.webServer><security><authorization><remove users="*" roles="" verbs=""/><add accessType="Deny" users="*"/></authorization></security><directoryBrowse enabled="false"/></system.webServer></configuration>' ) as $nome => $conteudo ) {
        $destino = trailingslashit( $raiz ) . $nome;
        if ( ! file_exists( $destino ) && false === file_put_contents( $destino, $conteudo ) ) {
            return new WP_Error( 'protecao', __( 'Não foi possível proteger a pasta dos holerites.', 'portal-rh' ) );
        }
    }
    return $raiz;
}

/** O chamador valida o upload; esta função publica uma cópia e registra o dono. */
function portal_rh_nome_corresponde_usuario( $usuario, $nome ) {
    $normalizar = function ( $valor ) { return trim( preg_replace( '/[^A-Z0-9]+/', ' ', strtoupper( remove_accents( (string) $valor ) ) ) ); };
    $esperado = $normalizar( $nome );
    $nomes = array( trim( $usuario->first_name . ' ' . $usuario->last_name ), $usuario->first_name, $usuario->display_name );
    foreach ( $nomes as $candidato ) {
        $candidato = $normalizar( $candidato );
        if ( false !== strpos( $candidato, ' ' ) && $esperado === $candidato ) { return true; }
    }
    return false;
}

function portal_rh_publicar_importado( $temporario, $user_id, $nome, $mes, $ano, $vinculo_manual = false ) {
    if ( ! current_user_can( 'manage_options' ) ) { return new WP_Error( 'permissao', 'Acesso negado.' ); }
    $usuario = get_user_by( 'id', (int) $user_id );
    if ( 1 !== (int) get_option( 'portal_rh_importacao_db', 0 ) || ! $usuario ) {
        return new WP_Error( 'usuario', __( 'O funcionário selecionado não existe ou a importação não está pronta.', 'portal-rh' ) );
    }
    $arquivo = portal_rh_nome_arquivo_importado( $nome, $mes, $ano );
    if ( is_wp_error( $arquivo ) ) { return $arquivo; }
    if ( ! $vinculo_manual && ! portal_rh_nome_corresponde_usuario( $usuario, $nome ) ) {
        return new WP_Error( 'vinculo', __( 'O nome não corresponde ao cadastro selecionado. Revise e confirme o vínculo manual.', 'portal-rh' ) );
    }
    if ( ! is_file( $temporario ) || 0 !== strpos( file_get_contents( $temporario, false, null, 0, 5 ), '%PDF-' ) ) {
        return new WP_Error( 'pdf', __( 'O arquivo recebido não é um PDF válido.', 'portal-rh' ) );
    }
    global $wpdb;
    $tabela = portal_rh_importados_tabela();
    $hash = hash_file( 'sha256', $temporario );
    $existente = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $tabela WHERE user_id = %d AND ano = %d AND mes = %d", $user_id, $ano, $mes ) );
    if ( $existente ) {
        if ( hash_equals( $existente->sha256, $hash ) && $existente->arquivo === $arquivo ) { return $existente; }
        return new WP_Error( 'duplicado', __( 'Já existe um holerite para este funcionário e competência. Confira ou exclua o anterior na lista de importados.', 'portal-rh' ) );
    }
    $raiz = portal_rh_preparar_pasta_importados();
    if ( is_wp_error( $raiz ) ) { return $raiz; }
    $chave = str_replace( '-', '', wp_generate_uuid4() );
    $pasta = trailingslashit( $raiz ) . $chave;
    if ( ! wp_mkdir_p( $pasta ) ) { return new WP_Error( 'pasta', __( 'Não foi possível criar a pasta do arquivo.', 'portal-rh' ) ); }
    $destino = trailingslashit( $pasta ) . $arquivo;
    if ( ! copy( $temporario, $destino ) ) {
        @rmdir( $pasta );
        return new WP_Error( 'salvar', __( 'Não foi possível salvar o holerite.', 'portal-rh' ) );
    }
    $dados = array( 'user_id' => (int) $user_id, 'arquivo' => $arquivo, 'storage_key' => $chave, 'mes' => (int) $mes, 'ano' => (int) $ano, 'sha256' => $hash, 'criado_em' => current_time( 'mysql', true ) );
    if ( false === $wpdb->insert( $tabela, $dados, array( '%d', '%s', '%s', '%d', '%d', '%s', '%s' ) ) ) {
        @unlink( $destino );
        @rmdir( $pasta );
        return new WP_Error( 'registro', __( 'Não foi possível registrar o holerite. Verifique se ele já foi publicado.', 'portal-rh' ) );
    }
    $dados['id'] = (int) $wpdb->insert_id;
    return (object) $dados;
}

function portal_rh_excluir_importado( $id ) {
    if ( ! current_user_can( 'manage_options' ) ) { return new WP_Error( 'permissao', 'Acesso negado.' ); }
    $registro = portal_rh_importado_autorizado( $id );
    if ( ! $registro ) { return new WP_Error( 'inexistente', 'Holerite não encontrado.' ); }
    $caminho = portal_rh_caminho_importado( $registro );
    if ( $caminho && ! unlink( $caminho ) ) { return new WP_Error( 'excluir', 'Não foi possível excluir o arquivo.' ); }
    global $wpdb;
    if ( false === $wpdb->delete( portal_rh_importados_tabela(), array( 'id' => (int) $id ), array( '%d' ) ) ) {
        return new WP_Error( 'registro', 'Não foi possível remover o registro. Tente novamente.' );
    }
    if ( $caminho ) { @rmdir( dirname( $caminho ) ); }
    return true;
}
