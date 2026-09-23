<?php
/**
 * Painel de operações da equipe dentro do portal (front-end): visão geral, solicitações, CRM e relatórios,
 * sem depender do wp-admin. Entregue pelo módulo "Painel da equipe no site".
 *
 * Rota: página do portal com ?ebcr_view=equipe&tela=visao|solicitacoes|solicitacao|crm|relatorios.
 * Os formulários enviam para a própria página do portal (action ebcr_team_*), despachados por FormRouter.
 *
 * @package EBCR
 */

namespace EBCR\Frontend\Team;

use EBCR\Roles\Capabilities;
use EBCR\Support\Helpers;
use EBCR\Support\View;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks, views e ações do painel da equipe.
 */
// phpcs:disable WordPress.Security.NonceVerification -- parâmetros de navegação (GET) são apenas leitura; os handlers verificam nonce em Actions.
final class Panel {

	const VIEW = 'equipe';

	/**
	 * Telas conhecidas.
	 *
	 * @var string[]
	 */
	const SCREENS = array( 'visao', 'solicitacoes', 'solicitacao', 'crm', 'relatorios' );

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		( new Actions() )->register();
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 20 );
		add_action( 'admin_init', array( $this, 'admin_gate' ), 2 );
		add_filter( 'show_admin_bar', array( $this, 'show_admin_bar' ), 20 );
		add_filter( 'login_redirect', array( $this, 'login_redirect' ), 20, 3 );
	}

	/**
	 * URL de uma tela do painel da equipe.
	 *
	 * @param array $args Parâmetros (tela, id, filtros…).
	 * @return string
	 */
	public static function url( array $args = array() ) {
		return Helpers::portal_url( array_merge( array( 'ebcr_view' => self::VIEW ), $args ) );
	}

	/**
	 * URL do detalhe de uma solicitação.
	 *
	 * @param array  $submission Linha (public_id).
	 * @param string $tab        Aba inicial (opcional).
	 * @return string
	 */
	public static function submission_url( array $submission, $tab = '' ) {
		$args = array(
			'tela' => 'solicitacao',
			'id'   => $submission['public_id'],
		);
		if ( $tab ) {
			$args['tab'] = $tab;
		}
		return self::url( $args );
	}

	/**
	 * URL da ficha de um contato do CRM.
	 *
	 * @param int $contact_id Ficha.
	 * @return string
	 */
	public static function contact_url( $contact_id ) {
		return self::url(
			array(
				'tela' => 'crm',
				'sub'  => 'contato',
				'id'   => (int) $contact_id,
			)
		);
	}

	/**
	 * O usuário pode abrir o painel da equipe?
	 *
	 * @param int $user_id Usuário.
	 * @return bool
	 */
	public static function can_access( $user_id ) {
		return $user_id > 0 && user_can( (int) $user_id, Capabilities::CAP_VIEW );
	}

	/**
	 * Itens de navegação para o usuário (chave => rótulo, url).
	 *
	 * @param \WP_User $user Usuário.
	 * @return array
	 */
	public static function nav( \WP_User $user ) {
		$items = array(
			'visao'        => array(
				'label' => __( 'Visão geral', 'eb-credito-rural' ),
				'url'   => self::url( array( 'tela' => 'visao' ) ),
			),
			'solicitacoes' => array(
				'label' => __( 'Solicitações', 'eb-credito-rural' ),
				'url'   => self::url( array( 'tela' => 'solicitacoes' ) ),
			),
		);
		if ( user_can( $user, Capabilities::CAP_CRM ) ) {
			$items['crm'] = array(
				'label' => __( 'CRM', 'eb-credito-rural' ),
				'url'   => self::url( array( 'tela' => 'crm' ) ),
			);
		}
		if ( user_can( $user, Capabilities::CAP_DASHBOARD ) ) {
			$items['relatorios'] = array(
				'label' => __( 'Relatórios', 'eb-credito-rural' ),
				'url'   => self::url( array( 'tela' => 'relatorios' ) ),
			);
		}
		return $items;
	}

	/**
	 * Renderiza o painel para um membro da equipe (chamado pelo Portal).
	 *
	 * @param \WP_User $user Usuário logado.
	 * @return string
	 */
	public static function render( $user ) {
		if ( ! $user instanceof \WP_User || ! self::can_access( (int) $user->ID ) ) {
			return '<div class="ebcr-alert ebcr-alert--error" role="alert">' . esc_html__( 'Sem permissão para acessar o painel da equipe.', 'eb-credito-rural' ) . '</div>';
		}
		$screen = isset( $_GET['tela'] ) ? sanitize_key( wp_unslash( $_GET['tela'] ) ) : 'visao';
		if ( ! in_array( $screen, self::SCREENS, true ) ) {
			$screen = 'visao';
		}
		if ( 'crm' === $screen && ! user_can( $user, Capabilities::CAP_CRM ) ) {
			$screen = 'visao';
		}
		if ( 'relatorios' === $screen && ! user_can( $user, Capabilities::CAP_DASHBOARD ) ) {
			$screen = 'visao';
		}
		self::enqueue( $screen );

		$out  = '<div class="ebcr-team" data-screen="' . esc_attr( $screen ) . '">';
		$out .= View::render(
			'team/header',
			array(
				'user'       => $user,
				'screen'     => $screen,
				'nav'        => self::nav( $user ),
				'show_admin' => ! Access::portal_only() || Access::is_admin_user( (int) $user->ID ),
				'admin_url'  => admin_url( 'admin.php?page=ebcr' ),
				'logout_url' => wp_logout_url( Helpers::portal_url( array( 'ebcr_msg' => 'logged_out' ) ) ),
			)
		);
		$out .= self::notice_html();
		$s    = new Screens( (int) $user->ID );
		switch ( $screen ) {
			case 'solicitacoes':
				$out .= $s->submissions();
				break;
			case 'solicitacao':
				$out .= $s->submission( isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '' );
				break;
			case 'crm':
				$out .= $s->crm();
				break;
			case 'relatorios':
				$out .= $s->reports();
				break;
			default:
				$out .= $s->overview();
		}
		return $out . '</div>';
	}

	/**
	 * Aviso vindo da query string (após redirecionamento de uma ação), já escapado.
	 *
	 * @return string
	 */
	public static function notice_html() {
		if ( empty( $_GET['ebcr_notice'] ) ) {
			return '';
		}
		$type = isset( $_GET['ebcr_type'] ) && 'error' === sanitize_key( wp_unslash( $_GET['ebcr_type'] ) ) ? 'error' : 'success';
		return sprintf( '<div class="ebcr-alert ebcr-alert--%s" role="status">%s</div>', esc_attr( $type ), esc_html( sanitize_text_field( wp_unslash( $_GET['ebcr_notice'] ) ) ) );
	}

	/**
	 * Registra os assets do painel; o CSS entra no <head> quando a página do portal é aberta por alguém da equipe.
	 *
	 * @return void
	 */
	public function register_assets() {
		self::register_handles();
		global $post;
		if ( is_user_logged_in() && current_user_can( Capabilities::CAP_VIEW ) && $post instanceof \WP_Post && has_shortcode( $post->post_content, 'ebcr_portal' ) ) {
			wp_enqueue_style( 'ebcr-team' );
		}
	}

	/**
	 * Registra os handles (idempotente; também é chamado tarde, durante o render).
	 *
	 * @return void
	 */
	private static function register_handles() {
		if ( ! wp_style_is( 'ebcr-team', 'registered' ) ) {
			wp_register_style( 'ebcr-team', EBCR_URL . 'assets/css/team.css', array( 'ebcr-portal' ), EBCR_VERSION );
		}
		if ( ! wp_script_is( 'ebcr-team-chartjs', 'registered' ) ) {
			// Chart.js empacotado (assets/vendor/chartjs, MIT); handle próprio do front-end.
			wp_register_script( 'ebcr-team-chartjs', EBCR_URL . 'assets/vendor/chartjs/chart.umd.js', array(), '4.4.4', true );
		}
	}

	/**
	 * Enfileira CSS/JS do painel para a tela informada (gráficos só em visão geral e relatórios).
	 *
	 * @param string $screen Tela.
	 * @return void
	 */
	public static function enqueue( $screen ) {
		self::register_handles();
		$charts = in_array( $screen, array( 'visao', 'relatorios' ), true );
		$deps   = array( 'ebcr-portal' );
		if ( $charts ) {
			$deps[] = 'ebcr-team-chartjs';
		}
		if ( ! wp_script_is( 'ebcr-team', 'registered' ) ) {
			wp_register_script( 'ebcr-team', EBCR_URL . 'assets/js/team.js', $deps, EBCR_VERSION, true );
		}
		wp_enqueue_style( 'ebcr-team' );
		\EBCR\Esign\Esign::enqueue();
		wp_enqueue_script( 'ebcr-team' );
		wp_localize_script(
			'ebcr-team',
			'ebcrTeam',
			array(
				'rest'   => esc_url_raw( rest_url( \EBCR\Rest\Routes::NS . '/' ) ),
				'nonce'  => wp_create_nonce( 'wp_rest' ),
				'locale' => str_replace( '_', '-', get_locale() ),
				'i18n'   => array(
					'moved'       => __( 'Estágio atualizado.', 'eb-credito-rural' ),
					'error'       => __( 'Não foi possível salvar. Tente novamente.', 'eb-credito-rural' ),
					'working'     => __( 'Salvando…', 'eb-credito-rural' ),
					'submissions' => __( 'Solicitações', 'eb-credito-rural' ),
					'guarantees'  => __( 'Garantias', 'eb-credito-rural' ),
					'volume'      => __( 'Volume solicitado', 'eb-credito-rural' ),
					'empty'       => __( 'Sem dados para exibir.', 'eb-credito-rural' ),
					'confirm'     => __( 'Confirmar a mudança de status? O cliente será notificado se o status for visível.', 'eb-credito-rural' ),
				),
			)
		);
	}

	/**
	 * Injeta os dados dos gráficos (lidos por assets/js/team.js).
	 *
	 * @param array $data Séries.
	 * @return void
	 */
	public static function chart_data( array $data ) {
		wp_add_inline_script( 'ebcr-team', 'window.ebcrTeamCharts = ' . wp_json_encode( $data ) . ';', 'before' );
	}

	// ------------------------------------------------------------------ modo "só no site"

	/**
	 * admin_init: no modo portal_only, analistas e gestores (não administradores) são levados ao painel do portal.
	 *
	 * @return void
	 */
	public function admin_gate() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		$url = Access::admin_redirect_url(
			get_current_user_id(),
			Access::mode(),
			array(
				'pagenow' => isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '',
				'ajax'    => wp_doing_ajax(),
				'rest'    => defined( 'REST_REQUEST' ) && REST_REQUEST,
			)
		);
		if ( '' === $url ) {
			return;
		}
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Barra de administração oculta para a equipe no modo portal_only.
	 *
	 * @param bool $show Valor atual.
	 * @return bool
	 */
	public function show_admin_bar( $show ) {
		if ( ! is_user_logged_in() ) {
			return $show;
		}
		return Access::show_admin_bar( $show, get_current_user_id(), Access::mode() );
	}

	/**
	 * Após o login pelo wp-login.php, a equipe vai ao painel do portal no modo portal_only.
	 *
	 * @param string             $redirect_to Destino.
	 * @param string             $requested   Destino pedido.
	 * @param \WP_User|\WP_Error $user        Usuário.
	 * @return string
	 */
	public function login_redirect( $redirect_to, $requested, $user ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- assinatura do filtro.
		if ( ! $user instanceof \WP_User ) {
			return $redirect_to;
		}
		return Access::login_destination( $redirect_to, (int) $user->ID, Access::mode() );
	}
}
