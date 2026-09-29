<?php
/**
 * Identidade visual do painel: ícone do site, menu, login e barra.
 *
 * @package EBCR
 */

use EBCR\Admin\Branding;
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

	protected function tearDown(): void {
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
}
