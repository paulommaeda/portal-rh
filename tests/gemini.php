<?php
require __DIR__ . '/bootstrap.php';

$GLOBALS['gemini_requests'] = 0;
function wp_remote_post( $url, $args ) {
    ++$GLOBALS['gemini_requests'];
    check( false !== strpos( $url, 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent' ), 'Unexpected Gemini endpoint.' );
    check( false === strpos( $url, 'TEST-NOT-REAL' ), 'API key was included in a URL.' );
    check( 'TEST-NOT-REAL' === $args['headers']['x-goog-api-key'], 'API key is missing from the request header.' );
    $body = json_decode( $args['body'], true );
    check( 'application/pdf' === $body['contents'][0]['parts'][1]['inline_data']['mime_type'], 'PDF part is missing.' );
    check( 0 === strpos( base64_decode( $body['contents'][0]['parts'][1]['inline_data']['data'] ), '%PDF-' ), 'Inline PDF payload is invalid.' );
    check( 'application/json' === $body['generationConfig']['responseMimeType'] && isset( $body['generationConfig']['responseJsonSchema'] ), 'Structured response schema is missing.' );
    return $GLOBALS['gemini_response'];
}
$temp = tempnam( sys_get_temp_dir(), 'prh-' );
file_put_contents( $temp, '%PDF-synthetic-fixture' );
check( is_wp_error( portal_rh_gemini_identificar( $temp ) ) && 0 === $GLOBALS['gemini_requests'], 'Gemini was queried without a key.' );
$GLOBALS['options']['portal_rh_gemini_key'] = 'TEST-NOT-REAL';
$GLOBALS['gemini_response'] = array( 'response' => array( 'code' => 200 ), 'body' => json_encode( array( 'candidates' => array( array( 'finishReason' => 'STOP', 'content' => array( 'parts' => array( array( 'text' => '{"nomes":["Daniel Lima Cunha"],"mes":3,"ano":2026}' ) ) ) ) ) ) ) );
$result = portal_rh_gemini_identificar( $temp );
check( ! is_wp_error( $result ) && $result['nomes'] === array( 'Daniel Lima Cunha' ) && 3 === $result['mes'], 'Valid structured response was rejected.' );
$two = portal_rh_gemini_validar_resposta( array( 'nomes' => array( 'Daniel Lima Cunha', 'Maria da Silva' ), 'mes' => 3, 'ano' => 2026 ) );
check( count( $two['nomes'] ) === 2, 'Distinct recipients were collapsed into one.' );
$twice = portal_rh_gemini_validar_resposta( array( 'nomes' => array( 'Daniel Lima Cunha', 'DANIEL LIMA CUNHA' ), 'mes' => 3, 'ano' => 2026 ) );
check( count( $twice['nomes'] ) === 1, 'Two copies of the same recipient were not deduplicated.' );
foreach ( array( null, array(), array( 'nomes' => 'Daniel', 'mes' => 3, 'ano' => 2026 ), array( 'nomes' => array(), 'mes' => '3', 'ano' => 2026 ), array( 'nomes' => array(), 'mes' => 13, 'ano' => 2026 ) ) as $invalid ) {
    check( is_wp_error( portal_rh_gemini_validar_resposta( $invalid ) ), 'Malformed AI metadata was accepted.' );
}
foreach ( array( 400, 401, 403, 404, 429, 500 ) as $status ) {
    $GLOBALS['gemini_response'] = array( 'response' => array( 'code' => $status ), 'body' => 'TEST-NOT-REAL provider diagnostic' );
    $error = portal_rh_gemini_identificar( $temp );
    check( is_wp_error( $error ) && false === strpos( $error->get_error_message(), 'TEST-NOT-REAL' ), 'Provider error leaked credentials or was accepted.' );
}
$GLOBALS['can_manage'] = false;
$before = $GLOBALS['gemini_requests'];
check( is_wp_error( portal_rh_gemini_identificar( $temp ) ) && $before === $GLOBALS['gemini_requests'], 'Non-admin can invoke Gemini.' );
unlink( $temp );
echo 'PASS: Gemini schema, optional key, server-side credentials, error handling and recipient ambiguity (' . $GLOBALS['checks'] . " checks).\n";
