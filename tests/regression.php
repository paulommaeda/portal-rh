<?php

require __DIR__ . '/bootstrap.php';

$baseline = isset( $argv[1] ) ? $argv[1] : '';
if ( ! is_file( $baseline ) ) {
    fwrite( STDERR, "Usage: php tests/regression.php /path/to/original/portal-rh.php\n" );
    exit( 1 );
}

function normalize_source( $source ) {
    return str_replace( "\r\n", "\n", $source );
}

/** Extrai funções por tokens PHP, sem depender de regex para balancear chaves. */
function function_tokens( $source ) {
    $tokens = token_get_all( normalize_source( $source ) );
    $functions = array();
    $count = count( $tokens );
    for ( $i = 0; $i < $count; ++$i ) {
        if ( ! is_array( $tokens[ $i ] ) || T_FUNCTION !== $tokens[ $i ][0] ) {
            continue;
        }
        $name = null;
        $body = array();
        $depth = 0;
        $opened = false;
        for ( ; $i < $count; ++$i ) {
            $token = $tokens[ $i ];
            if ( is_array( $token ) && in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
                continue;
            }
            if ( null === $name && is_array( $token ) && T_STRING === $token[0] ) {
                $name = $token[1];
            }
            $body[] = is_array( $token ) ? array( $token[0], $token[1] ) : $token;
            if ( '{' === $token ) { ++$depth; $opened = true; }
            if ( '}' === $token && 0 === --$depth && $opened ) { break; }
        }
        $functions[ $name ] = $body;
    }
    return $functions;
}

function restore_extracted_templates( $source ) {
    return preg_replace_callback( "~require PORTAL_RH_PATH \\. '([^']+)';~", function ( $match ) {
        $template = normalize_source( file_get_contents( dirname( __DIR__ ) . '/' . $match[1] ) );
        $start = strpos( $template, '?>' );
        $end = strrpos( $template, '<?php' );
        check( false !== $start && $end > $start, 'Invalid template: ' . $match[1] );
        return '?>' . substr( $template, $start + 2, $end - $start - 2 ) . '<?php';
    }, normalize_source( $source ) );
}

function original_hooks( $source ) {
    preg_match_all( "~add_(action|filter|shortcode)\\(\\s*'([^']+)'\\s*,\\s*'([^']+)'\\s*\\);~", $source, $matches, PREG_SET_ORDER );
    $hooks = array();
    foreach ( $matches as $match ) { $hooks[] = $match[1] . ':' . $match[2] . ':' . $match[3]; }
    sort( $hooks );
    return $hooks;
}

$original_source = file_get_contents( $baseline );
$original = function_tokens( $original_source );
$separated = array();
foreach ( array( 'includes/avisos.php', 'includes/holerites.php', 'includes/shortcodes.php', 'includes/navegacao.php', 'admin/holerites.php' ) as $module ) {
    $source = restore_extracted_templates( file_get_contents( dirname( __DIR__ ) . '/' . $module ) );
    $separated = array_merge( $separated, function_tokens( $source ) );
}

check( 16 === count( $original ), 'Unexpected original function count.' );
check( count( $original ) === count( $separated ), 'An original function was added or removed.' );
foreach ( $original as $name => $tokens ) {
    check( isset( $separated[ $name ] ), 'Missing function: ' . $name );
    if ( 'portal_rh_shortcode_meus_holerites' !== $name ) {
        check( $tokens === $separated[ $name ], 'Behavior or markup changed: ' . $name );
    }
    check( function_exists( $name ), 'Function not loaded: ' . $name );
}

$runtime_hooks = array();
$original_names = array_keys( $original );
foreach ( $GLOBALS['hooks'] as $hook ) {
    if ( in_array( $hook[2], $original_names, true ) ) {
        $runtime_hooks[] = $hook[0] . ':' . $hook[1] . ':' . $hook[2];
    }
}
sort( $runtime_hooks );
check( original_hooks( $original_source ) === $runtime_hooks, 'Original hooks changed.' );

$manifest = json_decode( file_get_contents( dirname( __DIR__ ) . '/updates.json' ), true );
$main = file_get_contents( dirname( __DIR__ ) . '/portal-rh.php' );
preg_match( '/\* Version: ([^\r\n]+)/', $main, $version );
check( PORTAL_RH_VERSION === trim( $version[1] ), 'Header and constant versions differ.' );
check( PORTAL_RH_VERSION === $manifest['version'], 'Manifest version differs from plugin version.' );
check( false !== strpos( $main, 'Update URI: https://github.com/paulommaeda/portal-rh' ), 'Update URI is missing.' );

// A função ampliada deve manter a saída anterior quando não houver importados.
preg_match( '/function portal_rh_shortcode_meus_holerites\(\) \{[\s\S]*?\n\}(?=\r?\nadd_shortcode)/', normalize_source( $original_source ), $shortcode_match );
check( ! empty( $shortcode_match[0] ), 'Original shortcode was not found.' );
eval( str_replace( 'function portal_rh_shortcode_meus_holerites()', 'function original_shortcode_for_test()', $shortcode_match[0] ) );
$GLOBALS['logged_in'] = false;
check( original_shortcode_for_test() === portal_rh_shortcode_meus_holerites(), 'Logged-out behavior changed.' );
$GLOBALS['logged_in'] = true;
check( original_shortcode_for_test() === portal_rh_shortcode_meus_holerites(), 'Missing-profile behavior changed.' );
$GLOBALS['users'][1] = (object) array( 'first_name' => 'Daniel Lima Cunha', 'last_name' => '', 'display_name' => 'Daniel Lima Cunha' );
check( original_shortcode_for_test() === portal_rh_shortcode_meus_holerites(), 'Empty-list behavior changed.' );
$dir = portal_rh_get_holerites_dir();
wp_mkdir_p( $dir );
$fixtures = array( 'DANIEL_LIMA_CUNHA_MARCO_DE_2026.pdf', 'DANIEL_LIMA_CUNHA_FEVEREIRO_DE_2026.pdf', 'OUTRO_FUNCIONARIO_MARCO_DE_2026.pdf' );
foreach ( $fixtures as $fixture ) { file_put_contents( $dir . '/' . $fixture, '%PDF-test' ); }
check( original_shortcode_for_test() === portal_rh_shortcode_meus_holerites(), 'Legacy files or markup changed.' );
foreach ( $fixtures as $fixture ) { unlink( $dir . '/' . $fixture ); }
rmdir( $dir ); rmdir( $GLOBALS['upload_base'] );

echo 'PASS: original modules/hooks, legacy shortcode output and version metadata (' . $GLOBALS['checks'] . " checks).\n";
