<?php

error_reporting( E_ALL );
set_error_handler( function ( $severity, $message, $file, $line ) {
    if ( ! ( error_reporting() & $severity ) ) { return false; }
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
$GLOBALS['options'] = array();
$GLOBALS['logged_in'] = true;
$GLOBALS['user_id'] = 1;
$GLOBALS['can_manage'] = true;
$GLOBALS['users'] = array();
$GLOBALS['upload_base'] = sys_get_temp_dir() . '/portal-rh-test-' . uniqid();
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
function register_activation_hook( $file, $callback ) { $GLOBALS['activation_hook'] = $callback; }
function is_admin() { return true; }
function plugin_dir_path( $file ) { return dirname( $file ) . '/'; }
function plugin_basename( $file ) { return $GLOBALS['plugin_basename']; }
function current_user_can( $capability ) { return 'manage_options' === $capability ? $GLOBALS['can_manage'] : $GLOBALS['can_update']; }
function get_option( $key, $default = false ) { return isset( $GLOBALS['options'][ $key ] ) ? $GLOBALS['options'][ $key ] : $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][ $key ] = $value; return true; }
function delete_option( $key ) { unset( $GLOBALS['options'][ $key ] ); return true; }
function is_user_logged_in() { return $GLOBALS['logged_in']; }
function get_current_user_id() { return $GLOBALS['logged_in'] ? $GLOBALS['user_id'] : 0; }
function get_user_by( $field, $id ) { return isset( $GLOBALS['users'][ $id ] ) ? $GLOBALS['users'][ $id ] : false; }
function get_user_meta( $id, $key, $single = true ) { $user = get_user_by( 'id', $id ); return $user && isset( $user->$key ) ? $user->$key : ''; }
function remove_accents( $value ) { return iconv( 'UTF-8', 'ASCII//TRANSLIT', $value ); }
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
function sanitize_text_field( $value ) { return is_scalar( $value ) ? trim( strip_tags( (string) $value ) ) : ''; }
function wp_unslash( $value ) { return stripslashes( $value ); }
function sanitize_file_name( $value ) { return preg_replace( '/[^A-Za-z0-9_.-]/', '', basename( $value ) ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return esc_html( $value ); }
function esc_url( $value ) { return esc_html( $value ); }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
function get_permalink() { return 'https://example.test/meus-holerites/'; }
function wp_login_url( $redirect ) { return 'https://example.test/login/?redirect=' . rawurlencode( $redirect ); }
function wp_create_nonce( $action ) { return 'test-nonce'; }
function wp_nonce_field( $action, $name ) { echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="test-nonce">'; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function wp_upload_dir() { return array( 'basedir' => $GLOBALS['upload_base'], 'baseurl' => 'https://example.test/uploads' ); }
function wp_mkdir_p( $path ) { return is_dir( $path ) || mkdir( $path, 0777, true ); }
function current_time( $type, $gmt = false ) { return '2026-03-31 12:00:00'; }
function wp_generate_uuid4() { return bin2hex( random_bytes( 16 ) ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
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
    public $message;
    public function __construct( $code, $message = '' ) { $this->code = $code; $this->message = $message; }
    public function get_error_message() { return $this->message; }
    public function get_error_code() { return $this->code; }
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
