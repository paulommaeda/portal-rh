<?php
/**
 * Plugin Name: Portal de RH - Holerites e Avisos
 * Plugin URI: https://github.com/paulommaeda/portal-rh
 * Description: Sistema completo com Holerites, Avisos (CPT) com arquivo/paginação, e Menu Inferior Global (5 botões). Inclui correções responsivas para tabelas no mobile.
 * Version: 1.7.0
 * Author: Portal de RH
 * Text Domain: portal-rh
 * Requires at least: 5.8
 * Requires PHP: 7.0
 * Update URI: https://github.com/paulommaeda/portal-rh
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Acesso direto não permitido.
}

define( 'PORTAL_RH_VERSION', '1.7.0' );
define( 'PORTAL_RH_FILE', __FILE__ );
define( 'PORTAL_RH_PATH', plugin_dir_path( __FILE__ ) );

require_once PORTAL_RH_PATH . 'includes/avisos.php';
require_once PORTAL_RH_PATH . 'includes/holerites.php';
require_once PORTAL_RH_PATH . 'includes/holerites-importados.php';
require_once PORTAL_RH_PATH . 'includes/shortcodes.php';
require_once PORTAL_RH_PATH . 'includes/navegacao.php';
require_once PORTAL_RH_PATH . 'includes/atualizacoes.php';

if ( is_admin() ) {
    require_once PORTAL_RH_PATH . 'admin/holerites.php';
    require_once PORTAL_RH_PATH . 'admin/importacao.php';
}
