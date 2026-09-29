<?php
/**
 * Botão "Área do Cliente", endereço amigável, links permanentes simples, páginas legais e migração de configurações.
 *
 * @package EBCR
 */

use EBCR\Domain\Consent;
use EBCR\Frontend\ClientArea;
use EBCR\Install\Migrator;
use EBCR\Support\Helpers;
use EBCR\Support\Options;

/**
 * 1.3.0.
 */
final class ClientAreaTest extends EBCR_TestCase {

	/**
	 * Páginas criadas.
	 *
	 * @var int[]
	 */
	private $pages = array();

	/**
	 * Estrutura de links permanentes original.
	 *
	 * @var string
	 */
	private $permalinks = '';

	/**
	 * Página do portal.
	 *
	 * @var int
	 */
	private $portal = 0;

	protected function setUp(): void {
		parent::setUp();
		$this->permalinks = (string) get_option( 'permalink_structure' );
		$this->portal     = $this->page( 'Área do produtor', 'area-do-produtor', '[ebcr_portal]' );
		Options::update( array( 'portal_page_id' => $this->portal ) );
		delete_transient( ClientArea::SLUG_TR );
		$this->reset_statics();
	}

	protected function tearDown(): void {
		foreach ( $this->pages as $id ) {
			wp_delete_post( $id, true );
		}
		$this->set_permalinks( $this->permalinks );
		delete_transient( ClientArea::SLUG_TR );
		delete_transient( 'ebcr_portal_page_auto' );
		remove_all_filters( 'ebcr_client_area_url' );
		remove_all_filters( 'ebcr_policy_page_slugs' );
		parent::tearDown();
	}

	/**
	 * Cria uma página publicada.
	 *
	 * @param string $title   Título.
	 * @param string $slug    Slug.
	 * @param string $content Conteúdo.
	 * @return int
	 */
	private function page( $title, $slug, $content = '' ) {
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_content' => $content,
			)
		);
		$this->assertIsInt( $id );
		$this->pages[] = $id;
		return $id;
	}

	/**
	 * Troca a estrutura de links permanentes.
	 *
	 * @param string $structure Estrutura ('' = simples).
	 * @return void
	 */
	private function set_permalinks( $structure ) {
		global $wp_rewrite;
		update_option( 'permalink_structure', $structure );
		$wp_rewrite->init();
	}

	/**
	 * Zera os estados estáticos da classe (uma vez por requisição).
	 *
	 * @return void
	 */
	private function reset_statics() {
		foreach ( array( 'nav_done', 'bar_done' ) as $prop ) {
			$r = new ReflectionProperty( ClientArea::class, $prop );
			$r->setAccessible( true );
			$r->setValue( null, false );
		}
	}

	public function test_shortcode_for_visitor_with_attributes(): void {
		$this->set_permalinks( '' );
		$html = do_shortcode( '[ebcr_client_area_button]' );
		$this->assertStringContainsString( 'Área do Cliente', $html );
		$this->assertStringContainsString( 'ebcr-ca-btn--primary', $html );
		$this->assertStringContainsString( 'entrar ou criar conta', $html, 'aria-label descritivo' );
		$this->assertStringContainsString( '<svg', $html );
		$this->assertStringContainsString( 'href="' . esc_url( get_permalink( $this->portal ) ) . '"', $html );
		$this->assertStringContainsString( 'page_id=' . $this->portal, $html, 'links permanentes simples: ?page_id=' );

		$html = do_shortcode( '[ebcr_client_area_button label="Entrar" style="outline" icon="no" class="minha classe"]' );
		$this->assertStringContainsString( '>Entrar<', $html );
		$this->assertStringContainsString( 'ebcr-ca-btn--outline', $html );
		$this->assertStringContainsString( 'minha', $html );
		$this->assertStringContainsString( 'classe', $html );
		$this->assertStringNotContainsString( '<svg', $html );
		$this->assertStringContainsString( 'ebcr-ca-btn--primary', do_shortcode( '[ebcr_client_area_button style="<script>"]' ), 'estilo inválido vira primary' );
		$this->assertTrue( wp_style_is( ClientArea::STYLE, 'enqueued' ) );
	}

	public function test_logged_in_shows_first_name_and_logged_label(): void {
		$uid = $this->make_user();
		update_user_meta( $uid, 'first_name', 'Maria' );
		wp_set_current_user( $uid );
		$html = do_shortcode( '[ebcr_client_area_button]' );
		$this->assertStringContainsString( 'Maria', $html );
		$this->assertStringContainsString( 'Minha área', $html );
		$this->assertStringContainsString( 'conectado como Maria', $html );
		$this->assertStringContainsString( 'Painel', do_shortcode( '[ebcr_client_area_button label_logged="Painel"]' ) );
		Options::update( array( 'client_area_label_logged' => 'Meu portal' ) );
		$this->assertStringContainsString( 'Meu portal', do_shortcode( '[ebcr_client_area_button]' ) );
		$data = ClientArea::js_data();
		$this->assertTrue( $data['loggedIn'] );
		$this->assertSame( 'Maria', $data['userFirstName'] );
		$this->assertSame( 'Meu portal', $data['labelLogged'] );
	}

	public function test_url_filter_and_helper_script(): void {
		add_filter(
			'ebcr_client_area_url',
			static function ( $url, $ctx ) {
				return add_query_arg( 'origem', 'botao', $url ) . ( $ctx['portal_page_id'] ? '' : '#sem-portal' );
			},
			10,
			2
		);
		$this->assertStringContainsString( 'origem=botao', ClientArea::url() );
		$this->assertStringContainsString( 'origem=botao', do_shortcode( '[ebcr_client_area_button]' ) );
		ob_start();
		ClientArea::footer();
		$out = (string) ob_get_clean();
		$this->assertStringContainsString( 'window.EBCR_CLIENT_AREA=', $out );
		$this->assertStringContainsString( 'a[data-ebcr-client-area]', $out );
		$this->assertStringContainsString( '"loggedIn":false', $out );
		$this->assertStringContainsString( 'origem=botao', str_replace( '&', '&', $out ) );
		$this->assertStringNotContainsString( 'ebcr-ca-bar', $out, 'barra desligada por padrão' );
	}

	public function test_block_is_registered_and_renders(): void {
		$type = WP_Block_Type_Registry::get_instance()->get_registered( ClientArea::BLOCK );
		$this->assertNotNull( $type );
		$this->assertTrue( $type->is_dynamic() );
		foreach ( array( 'label', 'labelLogged', 'buttonStyle', 'showIcon' ) as $attr ) {
			$this->assertArrayHasKey( $attr, $type->attributes );
		}
		$html = do_blocks( '<!-- wp:ebcr/client-area-button {"label":"Portal do cliente","buttonStyle":"ghost","showIcon":false} /-->' );
		$this->assertStringContainsString( 'Portal do cliente', $html );
		$this->assertStringContainsString( 'ebcr-ca-btn--ghost', $html );
		$this->assertStringContainsString( 'ebcr-ca', $html );
		$this->assertStringNotContainsString( '<svg', $html );
		$this->assertFileExists( EBCR_DIR . 'assets/js/client-area-block.js' );
	}

	public function test_menu_navigation_block_and_bar_injection(): void {
		$this->assertSame( '<li>a</li>', ClientArea::menu_items( '<li>a</li>', (object) array( 'theme_location' => 'primary' ) ), 'desligado por padrão' );
		Options::update(
			array(
				'client_area_menu_location' => 'primary',
				'client_area_menu_style'    => 'link',
				'client_area_nav_block'     => true,
				'client_area_bar'           => true,
				'client_area_bar_position'  => 'top-left',
				'client_area_bar_offset'    => 24,
			)
		);
		$items = ClientArea::menu_items( '<li>a</li>', (object) array( 'theme_location' => 'primary' ) );
		$this->assertStringContainsString( 'ebcr-ca-menu-item', $items );
		$this->assertStringContainsString( 'ebcr-ca-btn--link', $items );
		$this->assertSame( '<li>a</li>', ClientArea::menu_items( '<li>a</li>', (object) array( 'theme_location' => 'footer' ) ) );

		$nav = '<nav class="wp-block-navigation"><ul class="wp-block-navigation__container"><li class="wp-block-navigation-item">Início</li></ul></nav>';
		$out = ClientArea::navigation_block( $nav, array() );
		$this->assertMatchesRegularExpression( '#<li class="wp-block-navigation-item ebcr-ca-nav-item">.*</li></ul></nav>$#s', $out );
		$this->assertSame( $nav, ClientArea::navigation_block( $nav, array() ), 'só no primeiro bloco Navegação' );

		$bar = ClientArea::bar_html();
		$this->assertStringContainsString( 'ebcr-ca-bar--top-left', $bar );
		$this->assertStringContainsString( '--ebcr-ca-offset:24px', $bar );
		$this->assertStringContainsString( '<nav', $bar );
		ob_start();
		ClientArea::print_bar();
		$printed = (string) ob_get_clean();
		$this->assertStringContainsString( 'ebcr-ca-bar', $printed );
		ob_start();
		ClientArea::print_bar();
		$this->assertSame( '', (string) ob_get_clean(), 'impressa uma única vez' );
	}

	public function test_friendly_alias_rewrite_and_plain_permalinks(): void {
		global $wp_rewrite;
		$this->set_permalinks( '/%postname%/' );
		$this->assertTrue( ClientArea::alias_active() );
		ClientArea::add_rewrite_rules();
		$this->assertArrayHasKey( '^area-do-cliente/?$', $wp_rewrite->extra_rules_top );
		$this->assertSame( home_url( '/area-do-cliente/' ), ClientArea::alias_url() );
		$this->assertContains( ClientArea::QUERY_VAR, ClientArea::query_vars( array() ) );

		$this->set_permalinks( '' );
		$this->assertSame( add_query_arg( ClientArea::QUERY_VAR, '1', home_url( '/' ) ), ClientArea::alias_url() );
		$this->assertStringContainsString( 'page_id=' . $this->portal, Helpers::portal_url( array( 'ebcr_view' => 'perfil' ) ) );
		$this->assertStringContainsString( 'ebcr_view=perfil', Helpers::portal_url( array( 'ebcr_view' => 'perfil' ) ) );

		// Página própria com o mesmo slug: o alias não é usado.
		$own = $this->page( 'Área do Cliente', 'area-do-cliente' );
		delete_transient( ClientArea::SLUG_TR );
		$this->assertTrue( ClientArea::slug_page_exists() );
		$this->assertFalse( ClientArea::alias_active() );
		$this->assertSame( get_permalink( $own ), ClientArea::alias_url() );

		Options::update( array( 'client_area_alias' => false ) );
		wp_delete_post( $own, true );
		delete_transient( ClientArea::SLUG_TR );
		$this->assertFalse( ClientArea::alias_active() );
		$this->assertSame( ClientArea::url(), ClientArea::alias_url() );
		$info = ClientArea::info();
		$this->assertTrue( $info['plain'] );
		$this->assertSame( 'area-do-cliente', $info['slug'] );
	}

	public function test_policy_pages_resolve_by_candidate_slugs_and_settings(): void {
		$privacy_before = get_option( 'wp_page_for_privacy_policy' );
		$this->set_permalinks( '' );
		$old = $this->page( 'Política de Privacidade', 'politica-de-privacidade' );
		$this->assertSame( $old, Consent::policies()['privacidade']['page_id'], 'slug antigo continua funcionando' );
		$new = $this->page( 'Aviso de Privacidade', 'aviso-de-privacidade' );
		$this->assertSame( $new, Consent::policies()['privacidade']['page_id'], 'aviso-de-privacidade tem preferência' );
		$this->assertSame( get_permalink( $new ), Consent::url( 'privacidade' ) );
		$this->assertStringContainsString( 'page_id=' . $new, Consent::url( 'privacidade' ), 'links permanentes simples' );
		wp_delete_post( $new, true );
		wp_delete_post( $old, true );

		// Sem páginas pelos slugs: cai na página de privacidade do WordPress (se publicada).
		$wp_page = $this->page( 'Privacidade', 'privacidade-do-site' );
		update_option( 'wp_page_for_privacy_policy', $wp_page );
		$this->assertSame( $wp_page, Consent::policies()['privacidade']['page_id'] );
		$this->assertSame( 0, Consent::policies()['termos']['page_id'], 'termos não usa a página de privacidade' );

		// A página escolhida na configuração prevalece.
		$chosen = $this->page( 'Termos', 'meus-termos' );
		Options::update( array( 'policies' => array( 'termos' => array( 'page_id' => $chosen, 'version' => '1.0', 'text' => 'x', 'title' => 'Termos de uso', 'moment' => 'both' ) ) ) );
		$this->assertSame( $chosen, Consent::policies()['termos']['page_id'] );
		update_option( 'wp_page_for_privacy_policy', $privacy_before );

		// Páginas legais complementares.
		$this->assertSame( array(), Consent::legal_links() );
		$titular = $this->page( 'Portal do Titular', 'portal-do-titular' );
		$canal   = $this->page( 'Canal de Integridade', 'canal-de-integridade' );
		$links   = Consent::legal_links();
		$this->assertSame( $titular, $links['legal_page_titular']['page_id'] );
		$this->assertSame( get_permalink( $canal ), $links['legal_page_integridade']['url'] );
		$other = $this->page( 'Outro titular', 'outro-titular' );
		Options::update( array( 'legal_page_titular' => $other ) );
		$this->assertSame( $other, Consent::legal_links()['legal_page_titular']['page_id'], 'configuração prevalece sobre o slug' );
		add_filter(
			'ebcr_policy_page_slugs',
			static function ( $slugs, $key ) {
				return 'termos' === $key ? array( 'outro-titular' ) : $slugs;
			},
			10,
			2
		);
		Options::update( array( 'policies' => array() ) );
		$this->assertSame( $other, Consent::policies()['termos']['page_id'], 'filtro de slugs' );
	}

	public function test_settings_migration_preserves_legacy_operation_name(): void {
		$this->assertStringNotContainsString( 'Évellyn', Options::defaults()['operation_name'], 'novo padrão usa o nome do site' );
		$saved = get_option( Options::OPTION );
		unset( $saved['operation_name'] );
		update_option( Options::OPTION, $saved, false );
		Options::flush();
		delete_option( Migrator::SETTINGS_OPTION );
		Migrator::maybe_upgrade_settings();
		$this->assertSame( 'Crédito Rural — Évellyn Brandão', Options::get( 'operation_name' ), 'instalações existentes mantêm o nome antigo' );
		$this->assertSame( 'Sanclé Albuquerque', Options::get( 'dpo_name' ), 'e o DPO antigo' );
		$this->assertSame( '', Options::defaults()['dpo_name'], 'instalação nova: DPO a configurar' );
		$this->assertSame( Migrator::SETTINGS_VERSION, get_option( Migrator::SETTINGS_OPTION ) );
		Options::update( array( 'operation_name' => 'Outro' ) );
		Migrator::maybe_upgrade_settings();
		$this->assertSame( 'Outro', Options::get( 'operation_name' ), 'não roda de novo' );
	}
}
