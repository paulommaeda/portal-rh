<?php
require __DIR__ . '/bootstrap.php';
$GLOBALS['options']['portal_rh_gemini_key'] = 'SERVER-ONLY-TEST-KEY';
$banco_pronto = true;
$mensagem = '';
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Teste do importador</title><link rel="stylesheet" href="/assets/css/importacao.css"></head><body>
<?php require dirname( __DIR__ ) . '/templates/admin-importacao.php'; ?>
<script src="/assets/vendor/pdf-lib/pdf-lib.min.js"></script>
<script src="/assets/js/holerites-core.js"></script>
<script>window.PortalRHImportConfig={ajaxUrl:'/admin-ajax.php',nonce:'test-nonce',pdfjsUrl:'/assets/vendor/pdfjs/build/pdf.js',workerUrl:'/assets/vendor/pdfjs/build/pdf.worker.js',cMapUrl:'/assets/vendor/pdfjs/cmaps/',fontUrl:'/assets/vendor/pdfjs/standard_fonts/',wasmUrl:'/assets/vendor/pdfjs/wasm/',gemini:true,maxUpload:10485760};</script>
<script src="/assets/js/importacao.js"></script></body></html>
