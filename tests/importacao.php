<?php
require __DIR__ . '/bootstrap.php';

class Import_Test_DB {
    public $prefix = 'wp_';
    public $users = 'wp_users';
    public $rows = array();
    public $insert_id = 0;
    public $fail_insert = false;
    public function prepare( $query, ...$args ) {
        $index = 0;
        return preg_replace_callback( '/%[ds]/', function ( $match ) use ( $args, &$index ) {
            $value = $args[ $index++ ];
            return '%d' === $match[0] ? (string) (int) $value : "'" . str_replace( "'", "''", $value ) . "'";
        }, $query );
    }
    public function get_results( $query ) {
        preg_match_all( '/\b(user_id|id|ano|mes) = (\d+)/', $query, $filters, PREG_SET_ORDER );
        $result = array();
        foreach ( $this->rows as $row ) {
            $accept = true;
            foreach ( $filters as $filter ) { if ( (int) $row[ $filter[1] ] !== (int) $filter[2] ) { $accept = false; } }
            if ( $accept ) { $result[] = (object) $row; }
        }
        return $result;
    }
    public function get_row( $query ) { $rows = $this->get_results( $query ); return isset( $rows[0] ) ? $rows[0] : null; }
    public function insert( $table, $data, $formats ) {
        if ( $this->fail_insert ) { return false; }
        foreach ( $this->rows as $row ) {
            if ( $row['user_id'] === $data['user_id'] && $row['ano'] === $data['ano'] && $row['mes'] === $data['mes'] ) { return false; }
        }
        $data['id'] = ++$this->insert_id;
        $this->rows[ $data['id'] ] = $data;
        return 1;
    }
    public function delete( $table, $where, $formats ) { unset( $this->rows[ $where['id'] ] ); return 1; }
}
function wp_verify_nonce( $nonce, $action ) { return 'test-nonce' === $nonce; }
function absint( $value ) { return abs( (int) $value ); }
function wp_die( $message, $title = '', $args = array() ) { throw new RuntimeException( $message, isset( $args['response'] ) ? $args['response'] : 500 ); }

$wpdb = new Import_Test_DB();
$GLOBALS['options']['portal_rh_importacao_db'] = 1;
$GLOBALS['users'] = array(
    12 => (object) array( 'ID' => 12, 'first_name' => 'Daniel', 'last_name' => 'Lima Cunha', 'display_name' => 'Daniel Lima Cunha' ),
    13 => (object) array( 'ID' => 13, 'first_name' => 'Maria', 'last_name' => 'da Silva', 'display_name' => 'Maria da Silva' ),
    14 => (object) array( 'ID' => 14, 'first_name' => 'Daniel', 'last_name' => 'Lima Cunha', 'display_name' => 'Daniel Lima Cunha' ),
    15 => (object) array( 'ID' => 15, 'first_name' => 'Daniel', 'last_name' => '', 'display_name' => 'Cadastro antigo' ),
);
check( 'DANIEL-LIMA-CUNHA_MARCO_DE_2026.pdf' === portal_rh_nome_arquivo_importado( 'Daniel Lima Cunha', 3, 2026 ), 'Filename differs from requested format.' );
check( 'JOAO-DA-SILVA_DEZEMBRO_DE_2026.pdf' === portal_rh_nome_arquivo_importado( 'João da Silva', 12, 2026 ), 'Accented filename is invalid.' );
foreach ( array( array( 'Daniel', 3, 2026 ), array( 'Daniel Cunha', 13, 2026 ), array( 'Daniel Cunha', 0, 2026 ), array( 'Daniel Cunha', 3, '2026x' ) ) as $input ) {
    check( is_wp_error( call_user_func_array( 'portal_rh_nome_arquivo_importado', $input ) ), 'Invalid filename input was accepted.' );
}
check( ! portal_rh_nome_corresponde_usuario( $GLOBALS['users'][12], 'Daniel' ), 'First-name-only matching was accepted.' );
check( portal_rh_nome_corresponde_usuario( $GLOBALS['users'][12], 'DANIEL-LIMA-CUNHA' ), 'Full name should match independently of separators.' );
$temp = tempnam( sys_get_temp_dir(), 'prh-' );
file_put_contents( $temp, "%PDF-1.7\nSynthetic payroll fixture\n" );
$wrong = portal_rh_publicar_importado( $temp, 12, 'Maria da Silva', 3, 2026 );
check( is_wp_error( $wrong ) && 'vinculo' === $wrong->code, 'A mismatched account was accepted without explicit manual confirmation.' );
$first = portal_rh_publicar_importado( $temp, 12, 'Daniel Lima Cunha', 3, 2026 );
check( ! is_wp_error( $first ) && 1 === $first->id, 'Valid payroll was not published.' );
check( is_file( portal_rh_caminho_importado( $first ) ), 'Published file is missing.' );
$second = portal_rh_publicar_importado( $temp, 13, 'Maria da Silva', 3, 2026 );
check( ! is_wp_error( $second ), 'Second employee payroll was not published.' );
$homonym = portal_rh_publicar_importado( $temp, 14, 'Daniel Lima Cunha', 3, 2026 );
check( ! is_wp_error( $homonym ) && $homonym->arquivo === $first->arquivo && $homonym->storage_key !== $first->storage_key, 'Homonyms cannot share a storage path.' );
$manual = portal_rh_publicar_importado( $temp, 15, 'Daniel Lima Cunha', 3, 2026, true );
check( ! is_wp_error( $manual ), 'An explicitly confirmed manual mapping was rejected.' );
$retry = portal_rh_publicar_importado( $temp, 12, 'Daniel Lima Cunha', 3, 2026 );
check( $retry->id === $first->id && count( $wpdb->rows ) === 4, 'A retried publication created a duplicate.' );
file_put_contents( $temp, "%PDF-1.7\nDifferent payroll fixture\n" );
$duplicate = portal_rh_publicar_importado( $temp, 12, 'Daniel Lima Cunha', 3, 2026 );
check( is_wp_error( $duplicate ) && 'duplicado' === $duplicate->code, 'Existing payroll was overwritten.' );
check( hash_file( 'sha256', portal_rh_caminho_importado( $first ) ) === $first->sha256, 'Original file contents changed.' );
$root = portal_rh_get_holerites_dir() . '/importados';
$before = count( glob( $root . '/*', GLOB_ONLYDIR ) );
$wpdb->fail_insert = true;
$failed = portal_rh_publicar_importado( $temp, 12, 'Daniel Lima Cunha', 4, 2026 );
check( is_wp_error( $failed ) && count( glob( $root . '/*', GLOB_ONLYDIR ) ) === $before, 'Failed database insert left an untracked PDF.' );
$wpdb->fail_insert = false;

$GLOBALS['can_manage'] = false;
$GLOBALS['user_id'] = 12;
check( portal_rh_importado_autorizado( $first->id )->user_id === 12, 'Owner cannot access their payroll.' );
check( null === portal_rh_importado_autorizado( $second->id ), 'A different employee can access this payroll.' );
check( null === portal_rh_importado_autorizado( $homonym->id ), 'A homonym can access another account payroll.' );
check( count( portal_rh_importados_para_usuario( 12 ) ) === 1, 'Portal query included another recipient.' );
check( is_wp_error( portal_rh_excluir_importado( $first->id ) ), 'Employee can delete payroll files.' );
check( is_wp_error( portal_rh_publicar_importado( $temp, 12, 'Daniel Lima Cunha', 4, 2026 ) ), 'Employee can publish payroll files.' );
$GLOBALS['users'][12]->first_name = '';
$GLOBALS['users'][12]->last_name = '';
$legacy = portal_rh_get_holerites_dir() . '/OUTRO_FUNCIONARIO_MARCO_DE_2026.pdf';
file_put_contents( $legacy, '%PDF-test' );
$html = portal_rh_shortcode_meus_holerites();
check( false !== strpos( $html, $first->arquivo ) && false === strpos( $html, $second->arquivo ), 'Portal did not isolate imported payroll by user ID.' );
check( false === strpos( $html, 'OUTRO_FUNCIONARIO' ), 'An empty first name exposed legacy payroll.' );
check( false !== strpos( $html, 'portal_rh_holerite' ) && false === strpos( $html, $first->storage_key ), 'Portal exposed an internal storage path.' );
unlink( $legacy );
$_GET = array( 'portal_rh_holerite' => $first->id );
try { portal_rh_download_importado(); check( false, 'Missing nonce was accepted.' ); }
catch ( RuntimeException $error ) { check( 403 === $error->getCode(), 'Missing nonce did not return 403.' ); }
$_GET = array( 'portal_rh_holerite' => $second->id, 'portal_rh_nonce' => 'test-nonce' );
try { portal_rh_download_importado(); check( false, 'Another account download was accepted.' ); }
catch ( RuntimeException $error ) { check( 404 === $error->getCode(), 'Unauthorized payroll should not be disclosed.' ); }
$GLOBALS['logged_in'] = false;
check( null === portal_rh_importado_autorizado( $first->id ), 'Unauthenticated user can resolve a payroll record.' );
try { portal_rh_download_importado(); check( false, 'Logged-out download was accepted.' ); }
catch ( RuntimeException $error ) { check( 403 === $error->getCode(), 'Logged-out access did not return 403.' ); }
$GLOBALS['logged_in'] = true;
$GLOBALS['can_manage'] = true;
check( portal_rh_importado_autorizado( $second->id )->user_id === 13, 'Admin cannot resolve another employee payroll.' );
$tampered = clone $first; $tampered->storage_key = '../../outside';
check( false === portal_rh_caminho_importado( $tampered ), 'Storage traversal was accepted.' );
$tampered = clone $first; $tampered->arquivo = '../outside.pdf';
check( false === portal_rh_caminho_importado( $tampered ), 'Filename traversal was accepted.' );
foreach ( array( $first, $second, $homonym, $manual ) as $record ) {
    $path = portal_rh_caminho_importado( $record );
    check( true === portal_rh_excluir_importado( $record->id ) && ! file_exists( $path ), 'Delete did not revoke the record and remove its file.' );
}
check( array() === $wpdb->rows, 'Deleted records remain available.' );
foreach ( array( '.htaccess', 'index.php', 'web.config' ) as $file ) { unlink( $root . '/' . $file ); }
rmdir( $root );
foreach ( array( '.htaccess', 'index.php' ) as $file ) { unlink( portal_rh_get_holerites_dir() . '/' . $file ); }
rmdir( portal_rh_get_holerites_dir() ); rmdir( $GLOBALS['upload_base'] ); unlink( $temp );
echo 'PASS: import filenames, manual mapping, homonyms, ownership, nonce, duplicate prevention, rollback and deletion (' . $GLOBALS['checks'] . " checks).\n";
