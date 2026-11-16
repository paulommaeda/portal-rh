<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo( 'charset' ); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <title><?php wp_title('|', true, 'right'); bloginfo('name'); ?></title>
        <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
        <?php wp_head(); ?>
        
        <style>
            :root {
                --prh-purple: #5e4c73;
                --prh-teal: #3bc2a2;
                --prh-bg: #f4f5f8;
                --prh-text: #4a4a4a;
                --prh-yellow: #fceea7;
            }
            html, body {
                margin: 0; padding: 0; background: var(--prh-bg); font-family: 'Nunito', sans-serif; color: var(--prh-text); -webkit-font-smoothing: antialiased;
            }
            .portal-rh-topbar {
                background: var(--prh-purple); color: #fff; padding: 15px 20px; display: flex; flex-direction: column;
                position: sticky; top: 0; z-index: 100; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px; box-shadow: 0 4px 12px rgba(94,76,115,0.15);
            }
            .prh-top-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
            .prh-brand { display: flex; align-items: center; gap: 10px; text-decoration: none; color: #fff; }
            .prh-logo-icon { background: var(--prh-teal); color: #fff; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; font-weight: 800; font-size: 16px; }
            .prh-brand-text { font-size: 18px; font-weight: 700; }
            .prh-brand-text span { color: var(--prh-yellow); }
            .prh-voltar { color: #fff; text-decoration: none; background: rgba(255,255,255,0.1); padding: 6px 12px; border-radius: 6px; font-size: 14px; font-weight: 600; }
            .prh-user-greeting { font-size: 13px; color: rgba(255,255,255,0.8); }
            .prh-user-greeting strong { color: #fff; }
            
            .portal-rh-container { padding: 20px 20px 100px 20px; max-width: 800px; margin: 0 auto; }
            
            /* Cartões Single/Archive */
            .portal-rh-card { background: #fff; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border-left: 4px solid var(--prh-teal); margin-bottom:15px; }
            .portal-rh-card-link { text-decoration: none; color: inherit; display: block; }
            
            .portal-rh-badge { background: var(--prh-yellow); color: var(--prh-purple); padding: 4px 10px; border-radius: 20px; font-weight: 700; font-size: 12px; display: inline-block; margin-bottom:10px; }
            .portal-rh-aviso-title { margin: 0 0 10px 0; font-size: 20px; color: var(--prh-purple); font-weight: 800; }
            .portal-rh-aviso-content { font-size: 15px; line-height: 1.7; color: #555; }
            .portal-rh-aviso-content img { max-width: 100%; height: auto; border-radius: 8px; margin: 15px 0; }
            
            /* Formulário Filtro */
            .prh-filter-form { display:flex; gap:10px; margin-bottom: 20px; align-items:center; }
            .prh-input { padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-family:inherit; flex:1; }
            .prh-btn { padding: 10px 15px; background: var(--prh-teal); color: #fff; border:none; border-radius:8px; font-weight:700; font-family:inherit; cursor:pointer; }
            .prh-btn-clear { padding: 10px 15px; background: #eee; color: #555; border:none; border-radius:8px; font-weight:700; text-decoration:none; font-family:inherit; }
            
            /* Paginação */
            .prh-pagination { margin-top: 20px; display: flex; justify-content: center; gap: 8px; }
            .prh-pagination .page-numbers { display:inline-block; padding:8px 14px; background:#fff; border-radius:8px; color:var(--prh-purple); text-decoration:none; font-weight:700; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
            .prh-pagination .page-numbers.current { background:var(--prh-teal); color:#fff; }
        </style>
    </head>
    <body <?php body_class( 'portal-rh-mobile-app' ); ?>>
        <header class="portal-rh-topbar">
            <div class="prh-top-row">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="prh-brand">
                    <div class="prh-logo-icon">J</div>
                    <div class="prh-brand-text">Portal <span>Journey</span></div>
                </a>
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="prh-voltar">&larr; Início</a>
            </div>
            <div class="prh-user-greeting">
                Olá, <strong><?php echo esc_html($user_name); ?></strong>! <?php echo $tipo_tela === 'single' ? 'Lendo aviso.' : 'Mural de Avisos.'; ?>
            </div>
        </header>

        <div class="portal-rh-container">
            <?php if ( $tipo_tela === 'archive' ) : ?>
                
                <!-- Filtro de Data -->
                <form method="GET" action="<?php echo esc_url( get_post_type_archive_link('aviso') ); ?>" class="prh-filter-form">
                    <input type="month" name="mes_ano" value="<?php echo esc_attr($_GET['mes_ano'] ?? ''); ?>" class="prh-input">
                    <button type="submit" class="prh-btn">Filtrar</button>
                    <?php if(!empty($_GET['mes_ano'])): ?>
                        <a href="<?php echo esc_url( get_post_type_archive_link('aviso') ); ?>" class="prh-btn-clear">Limpar</a>
                    <?php endif; ?>
                </form>

                <!-- Listagem (Archive) -->
                <?php if ( have_posts() ) : ?>
                    <?php while ( have_posts() ) : the_post(); ?>
                        <div class="portal-rh-card">
                            <a href="<?php the_permalink(); ?>" class="portal-rh-card-link">
                                <span class="portal-rh-badge"><?php echo get_the_date('d M Y'); ?></span>
                                <h2 class="portal-rh-aviso-title"><?php the_title(); ?></h2>
                                <div class="portal-rh-aviso-content">
                                    <?php echo get_the_excerpt() ? get_the_excerpt() : wp_trim_words( get_the_content(), 20, '...' ); ?>
                                </div>
                            </a>
                        </div>
                    <?php endwhile; ?>
                    
                    <div class="prh-pagination">
                        <?php 
                        echo paginate_links( array(
                            'prev_text' => '&larr;',
                            'next_text' => '&rarr;',
                        )); 
                        ?>
                    </div>
                <?php else: ?>
                    <div class="portal-rh-card">
                        <p style="text-align:center; color:#777; margin:0;">Nenhum aviso encontrado para este período.</p>
                    </div>
                <?php endif; ?>

            <?php else : ?>

                <!-- Leitura do Aviso (Single) -->
                <div class="portal-rh-card">
                    <?php
                    if ( have_posts() ) :
                        while ( have_posts() ) : the_post();
                            ?>
                            <span class="portal-rh-badge"><?php echo get_the_date('d M Y'); ?></span>
                            <h1 class="portal-rh-aviso-title" style="font-size:24px;"><?php the_title(); ?></h1>
                            <div class="portal-rh-aviso-content">
                                <?php the_content(); ?>
                            </div>
                            <?php
                        endwhile;
                    endif;
                    ?>
                </div>

            <?php endif; ?>
        </div>
        
        <?php wp_footer(); ?>
    </body>
    </html>
    <?php

