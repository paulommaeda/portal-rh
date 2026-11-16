<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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

function portal_rh_shortcode_meus_holerites() {
    if ( ! is_user_logged_in() ) {
        return '<div style="padding:15px;border:1px solid #e0e0e0;border-radius:12px;background:#fff;color:#333;">Você precisa estar logado. <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">Entrar</a></div>';
    }
    $nome = get_user_meta( get_current_user_id(), 'first_name', true );
    $nome_normalizado = portal_rh_normalizar_nome( $nome );
    $importados = portal_rh_importados_para_usuario( get_current_user_id() );

    $html = '<div style="font-family:\'Nunito\',sans-serif;max-width:800px;margin:20px auto;">';
    $html .= '<h2 style="color:#5e4c73;font-weight:800;font-size:20px;margin-bottom:15px;">Meus Holerites</h2>';

    if ( empty( $nome_normalizado ) && empty( $importados ) ) {
        $html .= '<p style="padding:15px;background:#fff;border-radius:12px;color:#555;box-shadow:0 4px 10px rgba(0,0,0,0.03);">Seu nome não está cadastrado no perfil.</p></div>';
        return $html;
    }

    $dir = portal_rh_get_holerites_dir();
    $arquivos_encontrados = array();
    if ( ! empty( $nome_normalizado ) && is_dir( $dir ) ) {
        $itens = scandir( $dir );
        foreach ( $itens as $item ) {
            if ( '.' === $item || '..' === $item || '.htaccess' === $item || 'index.php' === $item ) { continue; }
            if ( ! is_file( trailingslashit( $dir ) . $item ) || 'pdf' !== strtolower( pathinfo( $item, PATHINFO_EXTENSION ) ) ) { continue; }
            if ( false !== strpos( strtoupper( $item ), $nome_normalizado ) ) { $arquivos_encontrados[] = $item; }
        }
    }

    if ( empty( $arquivos_encontrados ) && empty( $importados ) ) {
        $html .= '<p style="padding:15px;background:#fff;border-radius:12px;color:#555;box-shadow:0 4px 10px rgba(0,0,0,0.03);">Nenhum holerite disponível.</p>';
    } else {
        rsort( $arquivos_encontrados );
        $html .= '<ul style="list-style:none;padding:0;margin:0;display:grid;gap:10px;">';
        foreach ( $importados as $registro ) {
            $html .= portal_rh_item_importado_html( $registro );
        }
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
