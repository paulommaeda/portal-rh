<?php

error_reporting( E_ALL );
set_error_handler( function ( $severity, $message, $file, $line ) {
    throw new ErrorException( $message, 0, $severity, $file, $line );
} );

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
$GLOBALS['checks'] = 0;
$GLOBALS['hooks'] = array();
$GLOBALS['cache'] = array();
$GLOBALS['requests'] = 0;
$GLOBALS['can_update'] = true;
$GLOBALS['plugin_basename'] = 'portal-rh/portal-rh.php';
$_GET = array();

function check( $condition, $message ) {
    if ( ! $condition ) {
        throw new RuntimeException( $message );
    }
    ++$GLOBALS['checks'];
}

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
    $GLOBALS['hooks'][] = array( 'action', $hook, $callback, $accepted_args );
}
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
    $GLOBALS['hooks'][] = array( 'filter', $hook, $callback, $accepted_args );
}
function add_shortcode( $hook, $callback ) {
    $GLOBALS['hooks'][] = array( 'shortcode', $hook, $callback, 1 );
}
function is_admin() { return true; }
function plugin_dir_path( $file ) { return dirname( $file ) . '/'; }
function plugin_basename( $file ) { return $GLOBALS['plugin_basename']; }
function current_user_can( $capability ) { return $GLOBALS['can_update']; }
function get_site_transient( $key ) { return isset( $GLOBALS['cache'][ $key ] ) ? $GLOBALS['cache'][ $key ] : false; }
function set_site_transient( $key, $value, $expiration ) {
    $GLOBALS['cache'][ $key ] = $value;
    $GLOBALS['expiration'][ $key ] = $expiration;
}
function delete_site_transient( $key ) { unset( $GLOBALS['cache'][ $key ] ); }
function wp_remote_get( $url, $args ) {
    check( 'https://github.com/paulommaeda/portal-rh/releases/latest/download/updates.json' === $url, 'Unexpected update endpoint.' );
    ++$GLOBALS['requests'];
    return $GLOBALS['remote'];
}
function wp_remote_retrieve_response_code( $response ) { return $response['response']['code']; }
function wp_remote_retrieve_body( $response ) { return $response['body']; }
function wp_kses_post( $value ) { return strip_tags( $value, '<p><h4><ul><li><strong>' ); }
function __( $value, $domain = null ) { return $value; }
function trailingslashit( $path ) { return rtrim( $path, '/\\' ) . '/'; }
function untrailingslashit( $path ) { return rtrim( $path, '/\\' ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }

class WP_Error {
    public $code;
    public function __construct( $code, $message = '' ) { $this->code = $code; }
}

class Test_Filesystem {
    public $has_file = true;
    public $move_success = true;
    public $moves = array();
    public function exists( $path ) { return $this->has_file; }
    public function move( $source, $destination ) {
        $this->moves[] = array( $source, $destination );
        return $this->move_success;
    }
}

ob_start();
require dirname( __DIR__ ) . '/portal-rh.php';
check( '' === ob_get_clean(), 'Plugin emitted output during bootstrap.' );
check( 0 === $GLOBALS['requests'], 'Plugin queried GitHub during bootstrap.' );
