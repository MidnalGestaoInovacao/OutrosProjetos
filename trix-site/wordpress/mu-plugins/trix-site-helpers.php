<?php
/**
 * Plugin Name: Trix Site Helpers
 * Description: Complementos do novo site Trix: SEO no servidor (title, description, Open Graph, canonical e JSON-LD a partir do JSON #trix-seo de cada página) e proteção dos formulários (mensagens dos canais nunca são publicadas, IP/user-agent não são gravados nos relatos e as notificações vão para as caixas corretas).
 * Version: 1.0.0
 * Author: Trix Tecnologia Inteligente
 *
 * Instalação: copie este arquivo para wp-content/mu-plugins/ (crie a pasta se não existir). Plugins em mu-plugins são ativados automaticamente.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ------------------------------------------------------------------ SEO */
function trix_seo_parse( $html ) {
	if ( $html && preg_match( '#<script type="application/json" id="trix-seo">(.*?)</script>#s', $html, $m ) ) {
		$d = json_decode( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ), true ); return is_array( $d ) ? $d : null;
	}
	return null;
}
function trix_seo_data( $post = null ) {
	static $cache = array();
	if ( ! $post && ( is_front_page() || is_home() ) && 'posts' === get_option( 'show_on_front' ) ) {
		// A home do site vive no template "home" do tema.
		if ( ! isset( $cache['home'] ) ) { $t = function_exists( 'get_block_template' ) ? get_block_template( get_stylesheet() . '//home' ) : null; $cache['home'] = trix_seo_parse( $t ? $t->content : '' ); }
		return $cache['home'];
	}
	$post = $post ? get_post( $post ) : get_queried_object();
	if ( ! $post instanceof WP_Post ) { return null; }
	if ( ! isset( $cache[ $post->ID ] ) ) { $cache[ $post->ID ] = trix_seo_parse( $post->post_content ); }
	return $cache[ $post->ID ];
}
function trix_seo_abs( $u ) { return ( $u && $u[0] === '/' ) ? home_url( $u ) : $u; }
add_filter( 'pre_get_document_title', function ( $title ) {
	$d = trix_seo_data(); return ( $d && ! empty( $d['title'] ) ) ? $d['title'] : $title;
}, 20 );
add_filter( 'wp_robots', function ( $robots ) {
	$d = trix_seo_data();
	if ( $d && ! empty( $d['robots'] ) && strpos( $d['robots'], 'noindex' ) !== false ) { $robots['noindex'] = true; $robots['nofollow'] = true; }
	return $robots;
} );
add_filter( 'get_canonical_url', function ( $url, $post ) {
	$d = trix_seo_data( $post ); return ( $d && ! empty( $d['canonical'] ) ) ? home_url( $d['canonical'] ) : $url;
}, 10, 2 );
// Classe usada pelos estilos do site já no HTML do servidor (sem depender do JavaScript) e esquema de cores claro
// (impede o modo escuro forçado do Samsung Internet/Chrome Android de recolorir a identidade visual).
add_filter( 'body_class', function ( $c ) { $c[] = 'trix'; return $c; } );
// Emojis do WordPress: o script e as imagens vêm de s.w.org (terceiro recebe o IP do visitante, sem consentimento) e o site não precisa deles.
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
remove_action( 'admin_print_styles', 'print_emoji_styles' );
remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
add_filter( 'emoji_svg_url', '__return_false' );
add_action( 'wp_head', function () { echo '<meta name="color-scheme" content="only light">' . "\n"; }, 1 );
add_action( 'wp_head', function () {
	$d = trix_seo_data(); if ( ! $d ) { return; }
	$title = $d['title'] ?? wp_get_document_title();
	$desc  = $d['description'] ?? '';
	$img   = trix_seo_abs( ! empty( $d['image'] ) ? $d['image'] : '/wp-content/uploads/2026/09/trix-empresa.jpg' );
	$url   = ! empty( $d['canonical'] ) ? home_url( $d['canonical'] ) : ( ( is_front_page() || is_home() ) ? home_url( '/' ) : get_permalink() );
	echo "\n<!-- Trix SEO -->\n";
	if ( $desc ) { printf( '<meta name="description" content="%s">' . "\n", esc_attr( $desc ) ); }
	if ( ( is_front_page() || is_home() ) && ! ( 'page' === get_option( 'show_on_front' ) ) ) { printf( '<link rel="canonical" href="%s">' . "\n", esc_url( home_url( '/' ) ) ); }
	printf( '<meta property="og:site_name" content="%s">' . "\n", esc_attr( get_bloginfo( 'name' ) ) );
	printf( '<meta property="og:type" content="website"><meta property="og:locale" content="pt_BR">' . "\n" );
	printf( '<meta property="og:title" content="%s"><meta property="og:description" content="%s"><meta property="og:url" content="%s">' . "\n", esc_attr( $title ), esc_attr( $desc ), esc_url( $url ) );
	if ( $img ) { printf( '<meta property="og:image" content="%s"><meta name="twitter:card" content="summary_large_image"><meta name="twitter:image" content="%s">' . "\n", esc_url( $img ), esc_url( $img ) ); }
	else { echo '<meta name="twitter:card" content="summary">' . "\n"; }
	printf( '<meta name="twitter:title" content="%s"><meta name="twitter:description" content="%s">' . "\n", esc_attr( $title ), esc_attr( $desc ) );
	// JSON-LD mínimo no servidor (o script do site complementa com o grafo completo)
	$graph = array(
		array( '@type' => 'Organization', '@id' => home_url( '/#organization' ), 'name' => get_bloginfo( 'name' ), 'url' => home_url( '/' ), 'logo' => trix_seo_abs( '/wp-content/uploads/2026/09/trix-logo-512.png' ) ),
		array( '@type' => 'WebPage', '@id' => $url . '#webpage', 'url' => $url, 'name' => $title, 'description' => $desc, 'inLanguage' => 'pt-BR', 'isPartOf' => array( '@id' => home_url( '/#website' ) ) ),
	);
	if ( ! empty( $d['breadcrumb'] ) ) {
		$items = array(); $i = 1;
		foreach ( $d['breadcrumb'] as $b ) { $items[] = array( '@type' => 'ListItem', 'position' => $i++, 'name' => $b['name'], 'item' => home_url( $b['url'] ) ); }
		$graph[] = array( '@type' => 'BreadcrumbList', 'itemListElement' => $items );
	}
	if ( ! empty( $d['faq'] ) ) {
		$q = array();
		foreach ( $d['faq'] as $f ) { $q[] = array( '@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $f['a'] ) ); }
		$graph[] = array( '@type' => 'FAQPage', 'mainEntity' => $q );
	}
	echo '<script type="application/ld+json" id="trix-seo-server">' . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
}, 1 );
// Evita duplicar as tags quando o script do site também as injeta.
add_action( 'wp_footer', function () { echo "<script>window.TRIX_SERVER_SEO=true;</script>\n"; }, 1 );

/* ---------------------------------------------- Formulários (canais) */
function trix_form_pages() {
	$slugs = array( 'contato' => 'falecom@trixti.com.br', 'canal-lgpd' => 'dpo@trixti.com.br', 'canal-de-compliance' => 'ouvidoria@trixti.com.br' );
	$map = array();
	foreach ( $slugs as $slug => $email ) { $p = get_page_by_path( $slug ); if ( $p ) { $map[ $p->ID ] = $email; } }
	return $map;
}
// Nunca aprovar automaticamente (nem por autor previamente aprovado, nem por usuário logado): fica sempre "pendente".
add_filter( 'pre_comment_approved', function ( $approved, $commentdata ) {
	$pages = trix_form_pages();
	return isset( $pages[ (int) $commentdata['comment_post_ID'] ] ) ? 0 : $approved;
}, 99, 2 );
// Relatos: não gravar IP nem user-agent (anonimato efetivo).
add_filter( 'preprocess_comment', function ( $c ) {
	$pages = trix_form_pages();
	if ( isset( $pages[ (int) $c['comment_post_ID'] ] ) ) { $c['comment_author_IP'] = ''; $c['comment_agent'] = 'trix-form'; }
	return $c;
} );
add_filter( 'pre_comment_user_ip', function ( $ip ) {
	$pid = isset( $_POST['comment_post_ID'] ) ? (int) $_POST['comment_post_ID'] : 0; $pages = trix_form_pages();
	return isset( $pages[ $pid ] ) ? '' : $ip;
} );
// Notificação de moderação para a caixa do canal (DPO, ouvidoria ou comercial).
add_filter( 'comment_moderation_recipients', function ( $emails, $comment_id ) {
	$c = get_comment( $comment_id ); $pages = trix_form_pages();
	return ( $c && isset( $pages[ (int) $c->comment_post_ID ] ) ) ? array( $pages[ (int) $c->comment_post_ID ] ) : $emails;
}, 10, 2 );
add_filter( 'comment_notification_recipients', function ( $emails, $comment_id ) {
	$c = get_comment( $comment_id ); $pages = trix_form_pages();
	return ( $c && isset( $pages[ (int) $c->comment_post_ID ] ) ) ? array( $pages[ (int) $c->comment_post_ID ] ) : $emails;
}, 10, 2 );
// Mensagens dos canais nunca aparecem na API pública nem nos feeds, mesmo que alguém as aprove por engano.
add_filter( 'rest_comment_query', function ( $args ) { $ids = array_keys( trix_form_pages() ); if ( $ids ) { $args['post__not_in'] = array_merge( (array) ( $args['post__not_in'] ?? array() ), $ids ); } return $args; } );
add_filter( 'comment_feed_where', function ( $where ) { $ids = array_keys( trix_form_pages() ); return $ids ? $where . ' AND comment_post_ID NOT IN (' . implode( ',', array_map( 'intval', $ids ) ) . ')' : $where; } );
add_filter( 'comments_open', function ( $open, $post_id ) { return isset( trix_form_pages()[ (int) $post_id ] ) ? true : $open; }, 10, 2 );

/* ------------------------------------------------ Privacidade do login */
// Não publicar o sitemap de autores (exporia o nome de usuário do administrador).
// Páginas com canonical apontando para outra URL (ex.: Portal Operadora -> SAW) ficam fora do sitemap.
add_filter( 'wp_sitemaps_posts_query_args', function ( $args, $post_type ) {
	if ( 'page' !== $post_type ) { return $args; }
	$p = get_page_by_path( 'produtos/portal-operadora' );
	if ( $p ) { $args['post__not_in'] = array_merge( (array) ( $args['post__not_in'] ?? array() ), array( $p->ID ) ); }
	return $args;
}, 10, 2 );
add_filter( 'wp_sitemaps_add_provider', function ( $provider, $name ) { return 'users' === $name ? false : $provider; }, 10, 2 );
// Arquivos de autor redirecionam para a página inicial (sem enumeração de usuários).
// ?author=N e /author/<login>/ revelariam o login do administrador: redireciona antes do core (prioridade 1).
add_action( 'template_redirect', function () { if ( is_author() || isset( $_GET['author'] ) ) { wp_safe_redirect( home_url( '/' ), 301 ); exit; } }, 1 );
add_filter( 'redirect_canonical', function ( $redirect, $requested ) { return ( isset( $_GET['author'] ) ) ? false : $redirect; }, 10, 2 );
add_filter( 'oembed_response_data', function ( $data ) { unset( $data['author_name'], $data['author_url'] ); return $data; } );
// Usuários fora da API REST pública.
add_filter( 'rest_endpoints', function ( $e ) { if ( ! is_user_logged_in() ) { unset( $e['/wp/v2/users'], $e['/wp/v2/users/(?P<id>[\d]+)'] ); } return $e; } );
