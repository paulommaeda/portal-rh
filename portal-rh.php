<?php
/**
 * Plugin Name: Portal de RH - Holerites e Avisos
 * Description: Sistema completo com Holerites, Avisos (CPT) com arquivo/paginação, e Menu Inferior Global (5 botões). Inclui correções responsivas para tabelas no mobile.
 * Version: 1.6.1
 * Author: Portal de RH
 * Text Domain: portal-rh
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Acesso direto não permitido.
}

/* ==========================================================================
 * 1. REGISTRO DO CUSTOM POST TYPE (AVISOS)
 * ========================================================================== */

function portal_rh_registrar_cpt_aviso() {
    $labels = array(
        'name'                  => _x( 'Avisos RH', 'Post type general name', 'portal-rh' ),
        'singular_name'         => _x( 'Aviso', 'Post type singular name', 'portal-rh' ),
        'menu_name'             => _x( 'Avisos RH', 'Admin Menu text', 'portal-rh' ),
        'all_items'             => __( 'Todos os Avisos', 'portal-rh' ),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'avisos' ),
        'capability_type'    => 'post',
        'has_archive'        => true, // Permite /avisos/ funcionar como listagem
        'hierarchical'       => false,
        'menu_position'      => 26,
        'menu_icon'          => 'dashicons-megaphone',
        'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
        'show_in_rest'       => true,
    );

    register_post_type( 'aviso', $args );
}
add_action( 'init', 'portal_rh_registrar_cpt_aviso' );


/* ==========================================================================
 * 2. MODIFICADOR DA QUERY DO ARQUIVO (PAGINAÇÃO 5 ITENS E FILTRO)
 * ========================================================================== */
function portal_rh_aviso_archive_query( $query ) {
    if ( ! is_admin() && $query->is_main_query() && is_post_type_archive( 'aviso' ) ) {
        // Limita a 5 posts por página
        $query->set( 'posts_per_page', 5 );

        // Filtro por Mês/Ano (via GET)
        if ( ! empty( $_GET['mes_ano'] ) ) {
            $parts = explode( '-', sanitize_text_field( wp_unslash( $_GET['mes_ano'] ) ) );
            if ( count( $parts ) === 2 ) {
                $query->set( 'year', $parts[0] );
                $query->set( 'monthnum', $parts[1] );
            }
        }
    }
}
add_action( 'pre_get_posts', 'portal_rh_aviso_archive_query' );


/* ==========================================================================
 * 3. SHORTCODE PARA LISTAR AVISOS (EX: NA HOME DO PAINEL)
 * ========================================================================== */

function portal_rh_shortcode_lista_avisos( $atts ) {
    $atts = shortcode_atts( array( 'quantidade' => 3 ), $atts, 'lista_avisos' );
    $args = array( 'post_type' => 'aviso', 'posts_per_page' => intval( $atts['quantidade'] ), 'post_status' => 'publish' );
    $query = new WP_Query( $args );

    $html = '<style>
        .prh-avisos-grid { display: grid; gap: 15px; grid-template-columns: 1fr; }
        .prh-aviso-card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.03); border-left: 4px solid #3bc2a2; text-decoration: none; display: block; transition: transform 0.2s; }
        .prh-aviso-card:hover { transform: translateY(-2px); }
        .prh-aviso-meta { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; font-size: 12px; }
        .prh-aviso-data { background: #fceea7; color: #5a4b75; padding: 4px 10px; border-radius: 20px; font-weight: 700; }
        .prh-aviso-title { color: #5a4b75; font-size: 16px; font-weight: 700; margin: 0 0 8px 0; }
        .prh-aviso-excerpt { color: #777; font-size: 14px; line-height: 1.5; margin: 0 0 15px 0; }
        .prh-aviso-ler-mais { color: #3bc2a2; font-weight: 600; font-size: 13px; }
    </style>';

    $html .= '<div class="prh-avisos-grid">';
    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $excerpt = get_the_excerpt() ? get_the_excerpt() : wp_trim_words( get_the_content(), 15, '...' );
            $html .= '<a href="' . get_permalink() . '" class="prh-aviso-card">';
            $html .= '<div class="prh-aviso-meta"><span class="prh-aviso-data">' . get_the_date('d M') . '</span></div>';
            $html .= '<h3 class="prh-aviso-title">' . get_the_title() . '</h3>';
            $html .= '<p class="prh-aviso-excerpt">' . $excerpt . '</p>';
            $html .= '<span class="prh-aviso-ler-mais">Ler mais &rarr;</span></a>';
        }
        wp_reset_postdata();
    } else {
        $html .= '<p style="color:#777;">Nenhum aviso publicado.</p>';
    }
    $html .= '</div>';
    return $html;
}
add_shortcode( 'lista_avisos', 'portal_rh_shortcode_lista_avisos' );


/* ==========================================================================
 * 4. INTERCEPTADOR DE TEMPLATE: SINGLE E ARCHIVE (MOBILE-FIRST)
 * ========================================================================== */

function portal_rh_template_aviso( $template ) {
    if ( is_singular( 'aviso' ) ) {
        portal_rh_renderizar_tela( 'single' );
        exit;
    }
    if ( is_post_type_archive( 'aviso' ) ) {
        portal_rh_renderizar_tela( 'archive' );
        exit;
    }
    return $template;
}
add_filter( 'template_include', 'portal_rh_template_aviso' );

function portal_rh_renderizar_tela( $tipo_tela ) {
    if ( ! is_user_logged_in() ) {
        wp_redirect( wp_login_url( get_permalink() ) );
        exit;
    }
    
    $current_user = wp_get_current_user();
    $user_name = $current_user->first_name ? $current_user->first_name : $current_user->display_name;
    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo( 'charset' ); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <title><?php wp_title('|', true, 'right'); bloginfo('name'); ?></title>
        <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
        <?php wp_head(); ?>
        
        <style>
            :root {
                --prh-purple: #5e4c73;
                --prh-teal: #3bc2a2;
                --prh-bg: #f4f5f8;
                --prh-text: #4a4a4a;
                --prh-yellow: #fceea7;
            }
            html, body {
                margin: 0; padding: 0; background: var(--prh-bg); font-family: 'Nunito', sans-serif; color: var(--prh-text); -webkit-font-smoothing: antialiased;
            }
            .portal-rh-topbar {
                background: var(--prh-purple); color: #fff; padding: 15px 20px; display: flex; flex-direction: column;
                position: sticky; top: 0; z-index: 100; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px; box-shadow: 0 4px 12px rgba(94,76,115,0.15);
            }
            .prh-top-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
            .prh-brand { display: flex; align-items: center; gap: 10px; text-decoration: none; color: #fff; }
            .prh-logo-icon { background: var(--prh-teal); color: #fff; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; font-weight: 800; font-size: 16px; }
            .prh-brand-text { font-size: 18px; font-weight: 700; }
            .prh-brand-text span { color: var(--prh-yellow); }
            .prh-voltar { color: #fff; text-decoration: none; background: rgba(255,255,255,0.1); padding: 6px 12px; border-radius: 6px; font-size: 14px; font-weight: 600; }
            .prh-user-greeting { font-size: 13px; color: rgba(255,255,255,0.8); }
            .prh-user-greeting strong { color: #fff; }
            
            .portal-rh-container { padding: 20px 20px 100px 20px; max-width: 800px; margin: 0 auto; }
            
            /* Cartões Single/Archive */
            .portal-rh-card { background: #fff; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border-left: 4px solid var(--prh-teal); margin-bottom:15px; }
            .portal-rh-card-link { text-decoration: none; color: inherit; display: block; }
            
            .portal-rh-badge { background: var(--prh-yellow); color: var(--prh-purple); padding: 4px 10px; border-radius: 20px; font-weight: 700; font-size: 12px; display: inline-block; margin-bottom:10px; }
            .portal-rh-aviso-title { margin: 0 0 10px 0; font-size: 20px; color: var(--prh-purple); font-weight: 800; }
            .portal-rh-aviso-content { font-size: 15px; line-height: 1.7; color: #555; }
            .portal-rh-aviso-content img { max-width: 100%; height: auto; border-radius: 8px; margin: 15px 0; }
            
            /* Formulário Filtro */
            .prh-filter-form { display:flex; gap:10px; margin-bottom: 20px; align-items:center; }
            .prh-input { padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-family:inherit; flex:1; }
            .prh-btn { padding: 10px 15px; background: var(--prh-teal); color: #fff; border:none; border-radius:8px; font-weight:700; font-family:inherit; cursor:pointer; }
            .prh-btn-clear { padding: 10px 15px; background: #eee; color: #555; border:none; border-radius:8px; font-weight:700; text-decoration:none; font-family:inherit; }
            
            /* Paginação */
            .prh-pagination { margin-top: 20px; display: flex; justify-content: center; gap: 8px; }
            .prh-pagination .page-numbers { display:inline-block; padding:8px 14px; background:#fff; border-radius:8px; color:var(--prh-purple); text-decoration:none; font-weight:700; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
            .prh-pagination .page-numbers.current { background:var(--prh-teal); color:#fff; }
        </style>
    </head>
    <body <?php body_class( 'portal-rh-mobile-app' ); ?>>
        <header class="portal-rh-topbar">
            <div class="prh-top-row">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="prh-brand">
                    <div class="prh-logo-icon">J</div>
                    <div class="prh-brand-text">Portal <span>Journey</span></div>
                </a>
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="prh-voltar">&larr; Início</a>
            </div>
            <div class="prh-user-greeting">
                Olá, <strong><?php echo esc_html($user_name); ?></strong>! <?php echo $tipo_tela === 'single' ? 'Lendo aviso.' : 'Mural de Avisos.'; ?>
            </div>
        </header>

        <div class="portal-rh-container">
            <?php if ( $tipo_tela === 'archive' ) : ?>
                
                <!-- Filtro de Data -->
                <form method="GET" action="<?php echo esc_url( get_post_type_archive_link('aviso') ); ?>" class="prh-filter-form">
                    <input type="month" name="mes_ano" value="<?php echo esc_attr($_GET['mes_ano'] ?? ''); ?>" class="prh-input">
                    <button type="submit" class="prh-btn">Filtrar</button>
                    <?php if(!empty($_GET['mes_ano'])): ?>
                        <a href="<?php echo esc_url( get_post_type_archive_link('aviso') ); ?>" class="prh-btn-clear">Limpar</a>
                    <?php endif; ?>
                </form>

                <!-- Listagem (Archive) -->
                <?php if ( have_posts() ) : ?>
                    <?php while ( have_posts() ) : the_post(); ?>
                        <div class="portal-rh-card">
                            <a href="<?php the_permalink(); ?>" class="portal-rh-card-link">
                                <span class="portal-rh-badge"><?php echo get_the_date('d M Y'); ?></span>
                                <h2 class="portal-rh-aviso-title"><?php the_title(); ?></h2>
                                <div class="portal-rh-aviso-content">
                                    <?php echo get_the_excerpt() ? get_the_excerpt() : wp_trim_words( get_the_content(), 20, '...' ); ?>
                                </div>
                            </a>
                        </div>
                    <?php endwhile; ?>
                    
                    <div class="prh-pagination">
                        <?php 
                        echo paginate_links( array(
                            'prev_text' => '&larr;',
                            'next_text' => '&rarr;',
                        )); 
                        ?>
                    </div>
                <?php else: ?>
                    <div class="portal-rh-card">
                        <p style="text-align:center; color:#777; margin:0;">Nenhum aviso encontrado para este período.</p>
                    </div>
                <?php endif; ?>

            <?php else : ?>

                <!-- Leitura do Aviso (Single) -->
                <div class="portal-rh-card">
                    <?php
                    if ( have_posts() ) :
                        while ( have_posts() ) : the_post();
                            ?>
                            <span class="portal-rh-badge"><?php echo get_the_date('d M Y'); ?></span>
                            <h1 class="portal-rh-aviso-title" style="font-size:24px;"><?php the_title(); ?></h1>
                            <div class="portal-rh-aviso-content">
                                <?php the_content(); ?>
                            </div>
                            <?php
                        endwhile;
                    endif;
                    ?>
                </div>

            <?php endif; ?>
        </div>
        
        <?php wp_footer(); ?>
    </body>
    </html>
    <?php
}


/* ==========================================================================
 * 5. NAVEGAÇÃO INFERIOR GLOBAL E CSS RESPONSIVO (CORREÇÃO DE TABELAS)
 * ========================================================================== */

function portal_rh_inject_bottom_nav_css() {
    if ( ! is_user_logged_in() || is_admin() ) return;
    ?>
    <style>
        body { padding-bottom: 75px !important; }
        
        /* -----------------------------------------------------------
         * CORREÇÃO RESPONSIVA PARA TABELAS (Evita quebrar o Mobile)
         * Resolve o problema da tabela de atestados transbordando.
         * ----------------------------------------------------------- */
        @media (max-width: 768px) {
            table {
                display: block !important;
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch !important;
                white-space: nowrap !important;
                width: 100% !important;
                border-collapse: collapse;
                max-width: 100vw;
            }
            /* Garante que o container parente não expanda além da tela */
            .portal-rh-card, .entry-content, .fluentform {
                max-width: 100vw;
                overflow-x: hidden;
            }
        }

        /* Nav Bottom */
        .portal-rh-bottom-nav {
            position: fixed; bottom: 0; left: 0; width: 100%; background: #fff; border-top: 1px solid #eaeaea;
            display: flex; justify-content: space-around; padding: 10px 0; box-shadow: 0 -4px 15px rgba(0,0,0,0.05);
            z-index: 99999; padding-bottom: env(safe-area-inset-bottom, 10px); border-top-left-radius: 16px; border-top-right-radius: 16px;
            font-family: 'Nunito', sans-serif;
        }
        .portal-rh-bottom-nav a { text-decoration: none; color: #999; font-size: 10px; font-weight: 700; display: flex; flex-direction: column; align-items: center; flex: 1; gap: 4px; transition: color 0.2s; text-align: center; }
        .portal-rh-bottom-nav a.active, .portal-rh-bottom-nav a:hover { color: #3bc2a2; }
        .portal-rh-bottom-nav svg { width: 22px; height: 22px; fill: currentColor; }
        @media (min-width: 800px) {
            .portal-rh-bottom-nav { width: 800px; left: 50%; transform: translateX(-50%); border-left: 1px solid #eaeaea; border-right: 1px solid #eaeaea; }
        }
    </style>
    <?php
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
}
add_action( 'wp_footer', 'portal_rh_inject_bottom_nav_html' );


/* ==========================================================================
 * 6. GESTÃO DE HOLERITES E ARQUIVOS (SISTEMA INALTERADO)
 * ========================================================================== */

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
}

function portal_rh_shortcode_meus_holerites() {
    if ( ! is_user_logged_in() ) {
        return '<div style="padding:15px;border:1px solid #e0e0e0;border-radius:12px;background:#fff;color:#333;">Você precisa estar logado. <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">Entrar</a></div>';
    }
    $nome = get_user_meta( get_current_user_id(), 'first_name', true );
    $nome_normalizado = portal_rh_normalizar_nome( $nome );

    $html = '<div style="font-family:\'Nunito\',sans-serif;max-width:800px;margin:20px auto;">';
    $html .= '<h2 style="color:#5e4c73;font-weight:800;font-size:20px;margin-bottom:15px;">Meus Holerites</h2>';

    if ( empty( $nome_normalizado ) ) {
        $html .= '<p style="padding:15px;background:#fff;border-radius:12px;color:#555;box-shadow:0 4px 10px rgba(0,0,0,0.03);">Seu nome não está cadastrado no perfil.</p></div>';
        return $html;
    }

    $dir = portal_rh_get_holerites_dir();
    $arquivos_encontrados = array();
    if ( is_dir( $dir ) ) {
        $itens = scandir( $dir );
        foreach ( $itens as $item ) {
            if ( '.' === $item || '..' === $item || '.htaccess' === $item || 'index.php' === $item ) { continue; }
            if ( ! is_file( trailingslashit( $dir ) . $item ) || 'pdf' !== strtolower( pathinfo( $item, PATHINFO_EXTENSION ) ) ) { continue; }
            if ( false !== strpos( strtoupper( $item ), $nome_normalizado ) ) { $arquivos_encontrados[] = $item; }
        }
    }

    if ( empty( $arquivos_encontrados ) ) {
        $html .= '<p style="padding:15px;background:#fff;border-radius:12px;color:#555;box-shadow:0 4px 10px rgba(0,0,0,0.03);">Nenhum holerite disponível.</p>';
    } else {
        rsort( $arquivos_encontrados );
        $html .= '<ul style="list-style:none;padding:0;margin:0;display:grid;gap:10px;">';
        foreach ( $arquivos_encontrados as $arq ) {
            $url_download = portal_rh_get_download_url( $arq );
            $html .= '<li style="display:flex;align-items:center;justify-content:space-between;padding:15px;background:#fff;border-radius:12px;box-shadow:0 4px 10px rgba(0,0,0,0.03);border-left:4px solid #5e4c73;">';
            $html .= '<span style="display:flex;align-items:center;color:#4a4a4a;font-weight:700;"><span style="font-size:20px;margin-right:10px;">📄</span>' . esc_html( $arq ) . '</span>';
            $html .= '<a href="' . esc_url( $url_download ) . '" download="' . esc_attr( $arq ) . '" style="padding:8px 16px;background:#3bc2a2;color:#fff;text-decoration:none;border-radius:6px;font-size:13px;font-weight:700;">Baixar</a>';
            $html .= '</li>';
        }
        $html .= '</ul>';
    }
    $html .= '</div>';
    return $html;
}
add_shortcode( 'meus_holerites', 'portal_rh_shortcode_meus_holerites' );