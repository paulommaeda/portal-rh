<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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
    require PORTAL_RH_PATH . 'templates/avisos.php';
}
