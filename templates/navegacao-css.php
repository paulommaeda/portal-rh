<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

?>
    <style>
        body { padding-bottom: 75px !important; }
        
        /* -----------------------------------------------------------
         * CORREÇÃO RESPONSIVA PARA TABELAS (Evita quebrar o Mobile)
         * Resolve o problema da tabela de atestados transbordando.
         * ----------------------------------------------------------- */
        @media (max-width: 768px) {
            table {
                display: block !important;
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch !important;
                white-space: nowrap !important;
                width: 100% !important;
                border-collapse: collapse;
                max-width: 100vw;
            }
            /* Garante que o container parente não expanda além da tela */
            .portal-rh-card, .entry-content, .fluentform {
                max-width: 100vw;
                overflow-x: hidden;
            }
        }

        /* Nav Bottom */
        .portal-rh-bottom-nav {
            position: fixed; bottom: 0; left: 0; width: 100%; background: #fff; border-top: 1px solid #eaeaea;
            display: flex; justify-content: space-around; padding: 10px 0; box-shadow: 0 -4px 15px rgba(0,0,0,0.05);
            z-index: 99999; padding-bottom: env(safe-area-inset-bottom, 10px); border-top-left-radius: 16px; border-top-right-radius: 16px;
            font-family: 'Nunito', sans-serif;
        }
        .portal-rh-bottom-nav a { text-decoration: none; color: #999; font-size: 10px; font-weight: 700; display: flex; flex-direction: column; align-items: center; flex: 1; gap: 4px; transition: color 0.2s; text-align: center; }
        .portal-rh-bottom-nav a.active, .portal-rh-bottom-nav a:hover { color: #3bc2a2; }
        .portal-rh-bottom-nav svg { width: 22px; height: 22px; fill: currentColor; }
        @media (min-width: 800px) {
            .portal-rh-bottom-nav { width: 800px; left: 50%; transform: translateX(-50%); border-left: 1px solid #eaeaea; border-right: 1px solid #eaeaea; }
        }
    </style>
    <?php

