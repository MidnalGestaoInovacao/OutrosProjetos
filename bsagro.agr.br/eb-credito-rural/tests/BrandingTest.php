<?php
/**
 * Identidade visual do painel: ícone do site, menu, login e barra.
 *
 * @package EBCR
 */

use EBCR\Abilities\Abilities;
use EBCR\Admin\Branding;
use EBCR\Admin\Settings\Settings;
use EBCR\Support\Options;

/**
 * Marca no wp-admin e no login.
 */
final class BrandingTest extends EBCR_TestCase {

	/**
	 * Anexos criados.
	 *
	 * @var int[]
	 */
	private $attachments = array();

	protected function setUp(): void {
		parent::setUp();
		Branding::flush( true );
	}

	protected function tearDown(): void {
		remove_all_filters( 'ebcr_brand_tokens' );
		Branding::flush( true );
		foreach ( $this->attachments as $id ) {
			wp_delete_attachment( $id, true );
		}
		delete_option( 'site_icon' );
		parent::tearDown();
	}

	/**
	 * Cria um PNG na biblioteca de mídia e devolve [id, url].
	 *
	 * @return array
	 */
	private function png_attachment() {
		$upload = wp_upload_dir();
		$name   = 'ebcr-icon-' . wp_generate_password( 6, false, false ) . '.png';
		$path   = trailingslashit( $upload['path'] ) . $name;
		$png    = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==' );
		file_put_contents( $path, $png ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$this->tmp[] = $path;
		$id          = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/png',
				'post_title'     => 'Ícone',
				'post_status'    => 'inherit',
			),
			$path
		);
		$this->assertIsInt( $id );
		$this->attachments[] = $id;
		return array( $id, trailingslashit( $upload['url'] ) . $name );
	}

	public function test_apply_site_icon_uses_media_library_attachment(): void {
		Options::update( array( 'admin_branding' => true, 'brand_icon_url' => '' ) );
		$r = Branding::apply_site_icon();
		$this->assertFalse( $r['applied'] );
		$this->assertSame( 'no_icon_url', $r['reason'] );

		Options::update( array( 'brand_icon_url' => 'https://example.com/nao-existe.png' ) );
		$r = Branding::apply_site_icon();
		$this->assertFalse( $r['applied'] );
		$this->assertSame( 'not_in_media_library', $r['reason'] );

		list( $id, $url ) = $this->png_attachment();
		Options::update( array( 'brand_icon_url' => $url ) );
		$r = Branding::apply_site_icon();
		$this->assertTrue( $r['applied'], $r['reason'] );
		$this->assertSame( $id, $r['attachment_id'] );
		$this->assertSame( $id, (int) get_option( 'site_icon' ) );
		$this->assertTrue( has_site_icon() );
	}

	public function test_menu_icon_and_texts_follow_settings(): void {
		Options::update( array( 'admin_branding' => false, 'brand_icon_url' => 'https://example.com/i.png' ) );
		$this->assertSame( 'dashicons-carrot', Branding::menu_icon(), 'desligado usa o dashicon' );
		Options::update( array( 'admin_branding' => true ) );
		$this->assertSame( 'https://example.com/i.png', Branding::menu_icon() );
		Options::update( array( 'brand_logo_url' => '', 'email_logo_url' => 'https://example.com/e.png' ) );
		$this->assertSame( 'https://example.com/e.png', Branding::logo_url(), 'reserva: logotipo dos e-mails' );
		Options::update( array( 'operation_name' => 'Operação X' ) );
		$this->assertSame( 'Operação X', Branding::login_text() );
		$this->assertSame( home_url( '/' ), Branding::login_url() );
		$this->assertStringContainsString( 'Operação X', Branding::footer_text( 'x' ) );
	}

	public function test_site_icon_fallback_only_in_admin_when_option_empty(): void {
		Options::update( array( 'admin_branding' => true, 'brand_icon_url' => 'https://example.com/i.png' ) );
		delete_option( 'site_icon' );
		// Fora do admin/login (contexto dos testes) o filtro não interfere.
		$this->assertSame( '', Branding::site_icon_fallback( '', 512, 0 ) );
		$this->assertSame( 'https://x/y.png', Branding::site_icon_fallback( 'https://x/y.png', 512, 0 ), 'ícone existente prevalece' );
		Options::update( array( 'admin_branding' => false ) );
		$this->assertSame( '', Branding::site_icon_fallback( '', 512, 0 ) );
	}

	// ------------------------------------------------------------------ 1.3.0: identidade da área do cliente

	public function test_default_identity_reproduces_original_look(): void {
		$t = Branding::tokens();
		$this->assertTrue( Branding::is_legacy_palette( $t ) );
		$this->assertSame( Branding::preset_values( 'evellyn' )['brand_primary'], $t['primary'] );
		$vars = Branding::css_vars( $t );
		$this->assertSame( '#d4af37', $vars['--ebcr-primary'] );
		$this->assertSame( '12px', $vars['--ebcr-radius'] );
		$this->assertSame( '999px', $vars['--ebcr-btn-radius'] );
		foreach ( array( '--ebcr-primary-bg', '--ebcr-dark', '--ebcr-link', '--ebcr-bg', '--ebcr-font-body', '--ebcr-font-heading' ) as $k ) {
			$this->assertArrayNotHasKey( $k, $vars, "{$k}: paleta original usa as reservas do CSS" );
		}
		$css = Branding::inline_css( $t );
		$this->assertStringStartsWith( Branding::SCOPE . '{', $css, 'variáveis limitadas aos invólucros do plugin' );
		$this->assertStringNotContainsString( 'font-family', $css );
		$this->assertStringNotContainsString( 'body{', $css );
		$this->assertSame(
			array(
				'header_bg' => '#0b0b0b',
				'header_fg' => '#d4af37',
				'link'      => '#8a6a1c',
			),
			Branding::email_colors()
		);
		// Os arquivos CSS só leem as variáveis públicas (nunca as declaram) e guardam os valores originais como reserva.
		$portal = (string) file_get_contents( EBCR_DIR . 'assets/css/portal.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->assertStringContainsString( 'var(--ebcr-primary-bg,linear-gradient(135deg,#f3d77c,#d4af37 45%,#8a6a1c))', $portal );
		foreach ( array_keys( Branding::css_vars( Branding::tokens_from( Branding::preset_values( 'bsagro' ) + array( 'brand_bg' => '#fbf9f4' ) ) ) ) as $public ) {
			foreach ( array( 'portal', 'simulator', 'esign', 'team', '2fa', 'client-area' ) as $file ) {
				$this->assertDoesNotMatchRegularExpression( '/[{;]\s*' . preg_quote( $public, '/' ) . '\s*:/', (string) file_get_contents( EBCR_DIR . 'assets/css/' . $file . '.css' ), "{$file}.css declara {$public}" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			}
		}
	}

	public function test_bsagro_preset_via_update_settings_is_accessible(): void {
		$r = Abilities::update_settings(
			array(
				'settings' => array(
					'brand_preset' => 'bsagro',
					'brand_name'   => 'BS Agro Capital',
				),
			)
		);
		$this->assertContains( 'brand_primary', $r['saved'], 'brand_preset expande os valores da predefinição' );
		$this->assertSame( array(), $r['warnings'], wp_json_encode( $r['warnings'] ) );
		$this->assertSame( '#0d3527', Options::get( 'brand_primary' ) );
		$this->assertSame( 'rgba(23,27,36,0.15)', Options::get( 'brand_border' ) );
		$this->assertSame( 'BS Agro Capital', Branding::brand_name() );
		$this->assertCount( 2, Branding::font_urls() );
		$t    = Branding::tokens();
		$vars = Branding::css_vars( $t );
		$this->assertFalse( Branding::is_legacy_palette( $t ) );
		$this->assertSame( '#0d3527', $vars['--ebcr-primary-bg'], 'botão sólido' );
		$this->assertSame( '#fbf9f4', $vars['--ebcr-bg'] );
		$this->assertSame( '#0d3527', $vars['--ebcr-link'], 'links: o dourado não tem contraste sobre branco, usa a primária' );
		$this->assertSame( '#0d3527', $vars['--ebcr-dark'] );
		$this->assertSame( '#d4af37', $vars['--ebcr-dark-accent'] );
		$this->assertSame( '#d4af37', $vars['--ebcr-cta-btn-bg'], 'CTA em fundo oliva usa o dourado' );
		$this->assertSame( '20px', $vars['--ebcr-radius'] );
		$this->assertSame( '600', $vars['--ebcr-heading-weight'] );
		$this->assertStringContainsString( 'Raleway', $vars['--ebcr-font-heading'] );
		$this->assertStringContainsString( 'Roboto', $vars['--ebcr-font-body'] );
		foreach ( Branding::contrast_checks( $t ) as $c ) {
			$this->assertTrue( $c['pass'], $c['key'] . ' ' . $c['ratio'] );
		}
		$css = Branding::inline_css( $t );
		$this->assertStringContainsString( '.ebcr-portal{background:var(--ebcr-bg)', $css );
		$this->assertStringContainsString( 'font-family:var(--ebcr-font-body)', $css );
		$this->assertStringContainsString( 'font-family:var(--ebcr-font-heading)', $css );
		$this->assertStringNotContainsString( '<', $css );
		$this->assertSame( '#0d3527', Branding::email_colors()['header_bg'] );
		$this->assertSame( '#d4af37', Branding::email_colors()['header_fg'] );
		$mail = \EBCR\Support\View::render( 'emails/layout', array( 'subject' => 'x', 'body' => '<p>y</p>', 'logo_url' => '', 'site' => Branding::brand_name(), 'home' => home_url( '/' ) ) );
		$this->assertStringContainsString( 'background:#0d3527', $mail );
		$this->assertStringContainsString( 'BS Agro Capital', $mail );
	}

	public function test_contrast_warnings_and_presets_tool(): void {
		list( $values ) = Settings::sanitize(
			array(
				'brand_primary'          => '#ffffff',
				'brand_primary_contrast' => '#eeeeee',
			)
		);
		$warnings = Settings::guard( $values );
		$this->assertNotEmpty( array_filter( $warnings, static function ( $w ) { return false !== strpos( $w, '4,5:1' ); } ), 'avisa contraste insuficiente' );
		// Paleta original: links dourados abaixo de AA são sinalizados (sem mudar o visual).
		$legacy = Branding::contrast_checks( Branding::tokens() );
		$link   = wp_list_filter( $legacy, array( 'key' => 'link_surface' ) );
		$this->assertFalse( reset( $link )['pass'] );
		$primary = wp_list_filter( $legacy, array( 'key' => 'primary' ) );
		$this->assertTrue( reset( $primary )['pass'] );

		$r = Abilities::run_tool( array( 'tool' => 'apply_brand_preset', 'preset' => 'neutro' ) );
		$this->assertIsArray( $r );
		$this->assertSame( '#1f2937', Options::get( 'brand_primary' ) );
		$this->assertSame( 'neutro', Options::get( 'brand_preset' ) );
		$this->assertInstanceOf( \WP_Error::class, Abilities::run_tool( array( 'tool' => 'apply_brand_preset', 'preset' => 'xyz' ) ) );
		$this->assertSame( array( 'custom', 'evellyn', 'bsagro', 'neutro' ), array_keys( Branding::preset_labels() ) );
	}

	public function test_logo_name_and_front_handle(): void {
		Options::update( array( 'brand_name' => '' ) );
		$this->assertSame( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), Branding::brand_name() );
		list( $id ) = $this->png_attachment();
		Options::update( array( 'brand_logo_id' => $id ) );
		$this->assertNotSame( '', Branding::front_logo_url() );
		$html = \EBCR\Support\View::render( 'portal/brand' );
		$this->assertStringContainsString( 'ebcr-brandbar', $html );
		$this->assertStringContainsString( '<img', $html );
		Options::update( array( 'brand_portal_header' => false ) );
		$this->assertSame( '', trim( \EBCR\Support\View::render( 'portal/brand' ) ) );
		Options::update( array( 'brand_font_urls' => "https://cdn.jsdelivr.net/npm/@fontsource/raleway@5/latin-600.css" ) );
		wp_deregister_style( Branding::HANDLE );
		$this->assertSame( Branding::HANDLE, Branding::style_handle() );
		$this->assertContains( 'ebcr-brand-font-0', wp_styles()->registered[ Branding::HANDLE ]->deps );
	}

	public function test_inherit_theme_merges_theme_values(): void {
		$theme = Branding::theme_tokens();
		Options::update( array( 'brand_inherit_theme' => true ) );
		$t = Branding::tokens();
		foreach ( $theme as $k => $v ) {
			$this->assertSame( $v, $t[ $k ], "token {$k} herdado do tema" );
		}
		foreach ( array( 'primary', 'primary_contrast', 'text', 'surface' ) as $k ) {
			$this->assertNotSame( '', (string) $t[ $k ], "{$k} sempre definido (tema ou manual)" );
		}
		add_filter(
			'ebcr_brand_tokens',
			static function ( $tokens ) {
				$tokens['primary'] = '#123456';
				return $tokens;
			}
		);
		Branding::flush();
		$this->assertSame( '#123456', Branding::tokens()['primary'] );
	}
}
