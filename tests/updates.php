<?php

require __DIR__ . '/bootstrap.php';

$manifest = json_decode( file_get_contents( dirname( __DIR__ ) . '/updates.json' ), true );
$manifest['version'] = '1.6.3';
$GLOBALS['remote'] = array( 'response' => array( 'code' => 200 ), 'body' => json_encode( $manifest ) );
$plugin = plugin_basename( PORTAL_RH_FILE );

check( false === portal_rh_verificar_atualizacao( false, array(), 'other/plugin.php', array() ), 'Modified another plugin.' );
check( 0 === $GLOBALS['requests'], 'Unrelated plugin triggered HTTP.' );

$update = portal_rh_verificar_atualizacao( false, array(), $plugin, array() );
check( '1.6.3' === $update['version'], 'New version was not detected.' );
check( version_compare( $update['version'], PORTAL_RH_VERSION, '>' ), 'New version is not greater than installed version.' );
check( 'https://github.com/paulommaeda/portal-rh/releases/download/v1.6.3/portal-rh-1.6.3.zip' === $update['package'], 'Incorrect ZIP URL.' );
check( 'portal-rh' === $update['slug'], 'Incorrect update slug.' );
check( 'https://github.com/paulommaeda/portal-rh' === $update['id'], 'Incorrect update identity.' );
check( 3600 === $GLOBALS['expiration']['portal_rh_github_update'], 'Successful cache duration changed.' );
$requests_before = $GLOBALS['requests'];
portal_rh_verificar_atualizacao( false, array(), $plugin, array() );
check( $requests_before === $GLOBALS['requests'], 'Cache did not prevent a duplicate request.' );

$info = portal_rh_detalhes_atualizacao( false, 'plugin_information', (object) array( 'slug' => 'portal-rh' ) );
check( '1.6.3' === $info->version && $update['package'] === $info->download_link, 'Plugin details differ from update.' );
check( isset( $info->sections['description'], $info->sections['changelog'] ), 'Plugin details are incomplete.' );
check( 'existing' === portal_rh_detalhes_atualizacao( 'existing', 'plugin_information', (object) array( 'slug' => 'other' ) ), 'Changed another plugin details.' );
check( 'existing' === portal_rh_detalhes_atualizacao( 'existing', 'query_plugins', (object) array( 'slug' => 'portal-rh' ) ), 'Changed an unrelated API action.' );

$_GET['force-check'] = '1';
portal_rh_obter_atualizacao();
check( $requests_before + 1 === $GLOBALS['requests'], 'Manual check did not bypass cache.' );
$GLOBALS['can_update'] = false;
$requests_before = $GLOBALS['requests'];
portal_rh_obter_atualizacao();
check( $requests_before === $GLOBALS['requests'], 'Unauthorized manual check bypassed cache.' );
unset( $_GET['force-check'] );
$GLOBALS['can_update'] = true;

foreach ( array(
    new WP_Error( 'network_failure' ),
    array( 'response' => array( 'code' => 404 ), 'body' => '' ),
    array( 'response' => array( 'code' => 200 ), 'body' => '{invalid-json' ),
    array( 'response' => array( 'code' => 200 ), 'body' => '{"slug":"other","version":"1.6.3"}' ),
    array( 'response' => array( 'code' => 200 ), 'body' => '{"slug":"portal-rh","version":[]}' ),
    array( 'response' => array( 'code' => 200 ), 'body' => '{"slug":"portal-rh","version":"invalid"}' ),
) as $response ) {
    $GLOBALS['cache'] = array();
    $GLOBALS['remote'] = $response;
    check( false === portal_rh_obter_atualizacao(), 'Invalid response was accepted.' );
    check( 300 === $GLOBALS['expiration']['portal_rh_github_update'], 'Failure cache duration changed.' );
    $requests_before = $GLOBALS['requests'];
    check( false === portal_rh_obter_atualizacao(), 'Cached failure was accepted.' );
    check( $requests_before === $GLOBALS['requests'], 'Failure cache did not prevent repeated requests.' );
    check( 'existing' === portal_rh_verificar_atualizacao( 'existing', array(), $plugin, array() ), 'HTTP failure replaced another updater response.' );
}

$GLOBALS['cache'] = array();
$minimal = array( 'slug' => 'portal-rh', 'version' => '1.6.3', 'name' => array( 'invalid' ) );
$GLOBALS['remote'] = array( 'response' => array( 'code' => 200 ), 'body' => json_encode( $minimal ) );
$minimal = portal_rh_obter_atualizacao();
check( '' === $minimal['name'] && '' === $minimal['requires_php'], 'Optional metadata was not normalized.' );

$wp_filesystem = new Test_Filesystem();
$extra = array( 'plugin' => $plugin, 'type' => 'plugin', 'action' => 'update' );
$source = '/upgrade/download/portal-rh-main/';
$remote_source = '/upgrade/download/';
$destination = portal_rh_preparar_pasta_atualizacao( $source, $remote_source, null, $extra );
check( '/upgrade/download/portal-rh/' === $destination, 'GitHub directory was not normalized.' );
check( array( array( $source, $destination ) ) === $wp_filesystem->moves, 'Unexpected filesystem operation.' );
check( $source === portal_rh_preparar_pasta_atualizacao( $source, $remote_source, null, array( 'plugin' => 'other/plugin.php' ) ), 'Modified another plugin package.' );
check( $source === portal_rh_preparar_pasta_atualizacao( $source, $remote_source, null, array() ), 'Modified an install without matching plugin identity.' );
$error = new WP_Error( 'existing_error' );
check( $error === portal_rh_preparar_pasta_atualizacao( $error, $remote_source, null, $extra ), 'Discarded an existing upgrader error.' );
check( $destination === portal_rh_preparar_pasta_atualizacao( $destination, $remote_source, null, $extra ), 'Renamed an already correct package.' );

$GLOBALS['plugin_basename'] = 'custom-directory/portal-rh.php';
$extra['plugin'] = $GLOBALS['plugin_basename'];
check( '/upgrade/download/custom-directory/' === portal_rh_preparar_pasta_atualizacao( $source, $remote_source, null, $extra ), 'Changed an existing custom installation directory.' );
$GLOBALS['plugin_basename'] = $plugin;
$extra['plugin'] = $plugin;
$wp_filesystem->move_success = false;
check( is_wp_error( portal_rh_preparar_pasta_atualizacao( $source, $remote_source, null, $extra ) ), 'Ignored filesystem move failure.' );
$wp_filesystem->has_file = false;
check( is_wp_error( portal_rh_preparar_pasta_atualizacao( $source, $remote_source, null, $extra ) ), 'Accepted package without main PHP file.' );

$GLOBALS['cache']['portal_rh_github_update'] = $manifest;
portal_rh_limpar_cache_atualizacao( null, array( 'type' => 'theme', 'action' => 'update', 'plugins' => array( $plugin ) ) );
check( $manifest === $GLOBALS['cache']['portal_rh_github_update'], 'Theme update cleared plugin cache.' );
portal_rh_limpar_cache_atualizacao( null, array( 'type' => 'plugin', 'action' => 'update', 'plugins' => array( $plugin ) ) );
check( false === get_site_transient( 'portal_rh_github_update' ), 'Plugin update did not clear metadata cache.' );

echo 'PASS: updater cache, metadata, errors, isolation and installation directory (' . $GLOBALS['checks'] . " checks).\n";
