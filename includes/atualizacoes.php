<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Consulta apenas metadados. O ZIP é instalado pelo atualizador do WordPress.
 * Não há requisições ao GitHub durante o carregamento normal do plugin.
 */
function portal_rh_obter_atualizacao() {
    $cache_key = 'portal_rh_github_update';
    $forcar = isset( $_GET['force-check'] ) && current_user_can( 'update_plugins' );
    $dados = $forcar ? false : get_site_transient( $cache_key );

    if ( false !== $dados ) {
        return empty( $dados ) ? false : $dados;
    }

    $resposta = wp_remote_get(
        'https://github.com/paulommaeda/portal-rh/releases/latest/download/updates.json',
        array( 'timeout' => 8, 'limit_response_size' => 65536 )
    );

    if ( is_wp_error( $resposta ) || 200 !== wp_remote_retrieve_response_code( $resposta ) ) {
        set_site_transient( $cache_key, array(), 5 * MINUTE_IN_SECONDS );
        return false;
    }

    $dados = json_decode( wp_remote_retrieve_body( $resposta ), true );
    if ( ! is_array( $dados )
        || ! isset( $dados['slug'], $dados['version'] )
        || 'portal-rh' !== $dados['slug']
        || ! is_string( $dados['version'] )
        || ! preg_match( '/^\d+\.\d+(?:\.\d+)*$/', $dados['version'] )
    ) {
        set_site_transient( $cache_key, array(), 5 * MINUTE_IN_SECONDS );
        return false;
    }

    // Somente os campos de texto esperados são utilizados no painel.
    foreach ( array( 'name', 'requires', 'requires_php', 'description', 'changelog' ) as $campo ) {
        $dados[ $campo ] = isset( $dados[ $campo ] ) && is_string( $dados[ $campo ] ) ? $dados[ $campo ] : '';
    }

    set_site_transient( $cache_key, $dados, HOUR_IN_SECONDS );
    return $dados;
}

/** Integração nativa com a tela Plugins e com Painel > Atualizações (WP 5.8+). */
function portal_rh_verificar_atualizacao( $atualizacao, $plugin_data, $plugin_file, $locales ) {
    if ( plugin_basename( PORTAL_RH_FILE ) !== $plugin_file ) {
        return $atualizacao;
    }

    $dados = portal_rh_obter_atualizacao();
    if ( ! $dados ) {
        return $atualizacao;
    }

    return array(
        'id'           => 'https://github.com/paulommaeda/portal-rh',
        'slug'         => 'portal-rh',
        'version'      => $dados['version'],
        'url'          => 'https://github.com/paulommaeda/portal-rh',
        'package'      => 'https://github.com/paulommaeda/portal-rh/releases/download/v' . $dados['version'] . '/portal-rh-' . $dados['version'] . '.zip',
        'requires'     => $dados['requires'],
        'requires_php' => $dados['requires_php'],
    );
}
add_filter( 'update_plugins_github.com', 'portal_rh_verificar_atualizacao', 10, 4 );

/** Preenche o modal "Ver detalhes" sem procurar um plugin homônimo no WordPress.org. */
function portal_rh_detalhes_atualizacao( $informacoes, $acao, $args ) {
    if ( 'plugin_information' !== $acao || empty( $args->slug ) || 'portal-rh' !== $args->slug ) {
        return $informacoes;
    }

    $dados = portal_rh_obter_atualizacao();
    if ( ! $dados ) {
        return $informacoes;
    }

    return (object) array(
        'name'          => $dados['name'],
        'slug'          => 'portal-rh',
        'version'       => $dados['version'],
        'author'        => 'Portal de RH',
        'homepage'      => 'https://github.com/paulommaeda/portal-rh',
        'requires'      => $dados['requires'],
        'requires_php'  => $dados['requires_php'],
        'download_link' => 'https://github.com/paulommaeda/portal-rh/releases/download/v' . $dados['version'] . '/portal-rh-' . $dados['version'] . '.zip',
        'sections'      => array(
            'description' => wp_kses_post( $dados['description'] ),
            'changelog'   => wp_kses_post( $dados['changelog'] ),
        ),
    );
}
add_filter( 'plugins_api', 'portal_rh_detalhes_atualizacao', 10, 3 );

/**
 * Mantém o diretório já instalado, inclusive ao usar um ZIP gerado pelo GitHub,
 * para que atualizações individuais, em lote e automáticas preservem a ativação.
 */
function portal_rh_preparar_pasta_atualizacao( $source, $remote_source, $upgrader, $hook_extra ) {
    if ( is_wp_error( $source ) || empty( $hook_extra['plugin'] )
        || plugin_basename( PORTAL_RH_FILE ) !== $hook_extra['plugin']
    ) {
        return $source;
    }

    global $wp_filesystem;

    if ( ! $wp_filesystem || ! $wp_filesystem->exists( trailingslashit( $source ) . 'portal-rh.php' ) ) {
        return new WP_Error( 'portal_rh_pacote_invalido', __( 'O pacote de atualização do Portal de RH é inválido.', 'portal-rh' ) );
    }

    $pasta = dirname( plugin_basename( PORTAL_RH_FILE ) );
    if ( '.' === $pasta ) {
        return new WP_Error( 'portal_rh_instalacao_avulsa', __( 'Instale o Portal de RH na pasta portal-rh antes de atualizar pelo painel.', 'portal-rh' ) );
    }

    if ( basename( untrailingslashit( $source ) ) === $pasta ) {
        return $source;
    }

    $destino = trailingslashit( $remote_source ) . $pasta . '/';
    if ( ! $wp_filesystem->move( $source, $destino ) ) {
        return new WP_Error( 'portal_rh_erro_atualizacao', __( 'Não foi possível preparar a pasta de atualização do Portal de RH.', 'portal-rh' ) );
    }

    return $destino;
}
add_filter( 'upgrader_source_selection', 'portal_rh_preparar_pasta_atualizacao', 10, 4 );

/** A próxima verificação deve usar os metadados da versão recém-instalada. */
function portal_rh_limpar_cache_atualizacao( $upgrader, $hook_extra ) {
    if ( isset( $hook_extra['type'], $hook_extra['action'] )
        && 'plugin' === $hook_extra['type'] && 'update' === $hook_extra['action']
        && in_array( plugin_basename( PORTAL_RH_FILE ), (array) ( $hook_extra['plugins'] ?? array() ), true )
    ) {
        delete_site_transient( 'portal_rh_github_update' );
    }
}
add_action( 'upgrader_process_complete', 'portal_rh_limpar_cache_atualizacao', 10, 2 );
