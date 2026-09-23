<?php
/**
 * Menu, assets e avisos do painel administrativo.
 *
 * @package EBCR
 */

namespace EBCR\Admin;

use EBCR\Admin\Settings\Settings;
use EBCR\Files\FileGuard;
use EBCR\Files\UploadHandler;
use EBCR\Roles\Capabilities;
use EBCR\Security\Crypto;
use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Menu "Crédito Rural".
 */
final class Admin {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'current_screen', array( $this, 'early_bulk' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_notices', array( $this, 'notices' ) );
		( new Actions() )->register();
		( new Settings() )->register();
		add_filter( 'plugin_action_links_' . EBCR_BASENAME, array( $this, 'plugin_links' ) );
	}

	/**
	 * Links na lista de plugins.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public function plugin_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=ebcr-settings' ) ) . '">' . esc_html__( 'Configurações', 'eb-credito-rural' ) . '</a>', '<a href="' . esc_url( admin_url( 'admin.php?page=ebcr-help' ) ) . '">' . esc_html__( 'Ajuda', 'eb-credito-rural' ) . '</a>' );
		return $links;
	}

	/**
	 * Processa ações em massa da lista de solicitações antes de qualquer saída (redirecionamentos e CSV precisam de cabeçalhos livres).
	 *
	 * @param \WP_Screen $screen Tela atual.
	 * @return void
	 */
	public function early_bulk( $screen ) {
		if ( ! $screen || false === strpos( (string) $screen->id, 'ebcr-submissions' ) || empty( $_REQUEST['ebcr_submission'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verificado em process_bulk().
			return;
		}
		if ( ! current_user_can( Capabilities::CAP_VIEW ) ) {
			return;
		}
		( new SubmissionsList() )->process_bulk();
	}

	/**
	 * Menu.
	 *
	 * @return void
	 */
	public function menu() {
		$dash = new Dashboard();
		add_menu_page( __( 'Crédito Rural', 'eb-credito-rural' ), __( 'Crédito Rural', 'eb-credito-rural' ), Capabilities::CAP_VIEW, 'ebcr', array( $dash, 'render' ), 'dashicons-carrot', 26 );
		add_submenu_page( 'ebcr', __( 'Painel', 'eb-credito-rural' ), __( 'Painel', 'eb-credito-rural' ), Capabilities::CAP_VIEW, 'ebcr', array( $dash, 'render' ) );
		$hook = add_submenu_page( 'ebcr', __( 'Solicitações', 'eb-credito-rural' ), __( 'Solicitações', 'eb-credito-rural' ), Capabilities::CAP_VIEW, 'ebcr-submissions', array( $this, 'submissions' ) );
		add_action( 'load-' . $hook, array( $this, 'screen_options' ) );
		add_submenu_page( 'ebcr', __( 'CRM', 'eb-credito-rural' ), __( 'CRM', 'eb-credito-rural' ), Capabilities::CAP_CRM, 'ebcr-crm', array( new Crm(), 'render' ) );
		add_submenu_page( 'ebcr', __( 'Relatórios', 'eb-credito-rural' ), __( 'Relatórios', 'eb-credito-rural' ), Capabilities::CAP_DASHBOARD, 'ebcr-reports', array( new Reports(), 'render' ) );
		add_submenu_page( 'ebcr', __( 'Configurações', 'eb-credito-rural' ), __( 'Configurações', 'eb-credito-rural' ), Capabilities::CAP_SETTINGS, 'ebcr-settings', array( new Settings(), 'render' ) );
		add_submenu_page( 'ebcr', __( 'Log de auditoria', 'eb-credito-rural' ), __( 'Log de auditoria', 'eb-credito-rural' ), Capabilities::CAP_AUDIT, 'ebcr-audit', array( new AuditLogView(), 'render' ) );
		add_submenu_page( 'ebcr', __( 'Ajuda', 'eb-credito-rural' ), __( 'Ajuda', 'eb-credito-rural' ), Capabilities::CAP_VIEW, 'ebcr-help', array( new Help(), 'render' ) );
	}

	/**
	 * Opções de tela (itens por página).
	 *
	 * @return void
	 */
	public function screen_options() {
		add_screen_option(
			'per_page',
			array(
				'label'   => __( 'Solicitações por página', 'eb-credito-rural' ),
				'default' => 20,
				'option'  => 'ebcr_per_page',
			)
		);
		add_filter(
			'set-screen-option',
			static function ( $status, $option, $value ) {
				return 'ebcr_per_page' === $option ? (int) $value : $status;
			},
			10,
			3
		);
	}

	/**
	 * Lista ou detalhe.
	 *
	 * @return void
	 */
	public function submissions() {
		if ( ! empty( $_GET['view'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navegação; autorização no SubmissionView.
			( new SubmissionView() )->render( sanitize_text_field( wp_unslash( $_GET['view'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- idem.
			return;
		}
		( new SubmissionsList() )->render_page();
	}

	/**
	 * Assets nas telas do plugin.
	 *
	 * @param string $hook Hook.
	 * @return void
	 */
	public function assets( $hook ) {
		if ( false === strpos( $hook, 'ebcr' ) ) {
			return;
		}
		wp_enqueue_style( 'ebcr-admin', EBCR_URL . 'assets/css/admin.css', array(), EBCR_VERSION );
		wp_enqueue_style( 'ebcr-reports', EBCR_URL . 'assets/css/reports.css', array( 'ebcr-admin' ), EBCR_VERSION );
		wp_enqueue_script( 'ebcr-admin', EBCR_URL . 'assets/js/admin.js', array(), EBCR_VERSION, true );
		if ( 'toplevel_page_ebcr' === $hook ) {
			// Gráficos do painel: Chart.js empacotado (assets/vendor/chartjs, MIT) + dados injetados por Dashboard::render().
			wp_enqueue_script( 'ebcr-chartjs', EBCR_URL . 'assets/vendor/chartjs/chart.umd.js', array(), '4.4.4', true );
			wp_enqueue_script( 'ebcr-dashboard', EBCR_URL . 'assets/js/dashboard.js', array( 'ebcr-chartjs' ), EBCR_VERSION, true );
		}
	}

	/**
	 * Avisos: ativação, proteção de arquivos, HTTPS, limites do PHP, chave de criptografia, página do portal.
	 *
	 * @return void
	 */
	public function notices() {
		if ( ! current_user_can( Capabilities::CAP_SETTINGS ) ) {
			return;
		}
		$screen  = get_current_screen();
		$in_ebcr = $screen && false !== strpos( (string) $screen->id, 'ebcr' );
		$msgs    = array();
		if ( get_transient( 'ebcr_activation_notice' ) ) {
			delete_transient( 'ebcr_activation_notice' );
			$msgs[] = array( 'info', sprintf( /* translators: %s: link */ __( 'EB Crédito Rural ativado. Próximos passos: crie uma página com o shortcode [ebcr_portal], configure os e-mails e execute o teste de proteção de arquivos em %s.', 'eb-credito-rural' ), '<a href="' . esc_url( admin_url( 'admin.php?page=ebcr-settings&tab=seguranca' ) ) . '">' . esc_html__( 'Configurações → Segurança', 'eb-credito-rural' ) . '</a>' ) );
		}
		$last = Options::get( 'last_protection_test', array() );
		if ( is_array( $last ) && isset( $last['result'] ) && 'exposed' === $last['result'] ) {
			$msgs[] = array( 'error', sprintf( /* translators: %s: link */ __( 'ALERTA DE SEGURANÇA: a pasta de documentos está acessível por URL direta. Corrija a configuração do servidor ou mova a pasta para fora da raiz pública. Veja %s.', 'eb-credito-rural' ), '<a href="' . esc_url( admin_url( 'admin.php?page=ebcr-help#nginx' ) ) . '">' . esc_html__( 'Ajuda → Bloqueio no Nginx', 'eb-credito-rural' ) . '</a>' ) );
		} elseif ( $in_ebcr && ( ! is_array( $last ) || empty( $last['result'] ) ) ) {
			$msgs[] = array( 'warning', sprintf( /* translators: %s: link */ __( 'O teste de proteção da pasta de documentos ainda não foi executado. Faça-o em %s.', 'eb-credito-rural' ), '<a href="' . esc_url( admin_url( 'admin.php?page=ebcr-settings&tab=seguranca' ) ) . '">' . esc_html__( 'Configurações → Segurança', 'eb-credito-rural' ) . '</a>' ) );
		}
		if ( $in_ebcr ) {
			if ( Options::bool( 'require_https' ) && ! is_ssl() && 'https' !== wp_parse_url( home_url(), PHP_URL_SCHEME ) ) {
				$msgs[] = array( 'warning', __( 'O site não está em HTTPS. O portal do cliente trata dados pessoais e documentos: ative um certificado SSL antes de publicar.', 'eb-credito-rural' ) );
			}
			$limits = UploadHandler::php_limits();
			if ( ! $limits['ok'] ) {
				$msgs[] = array( 'warning', sprintf( /* translators: 1: upload_max_filesize, 2: post_max_size, 3: configurado */ __( 'Limites do PHP menores que o configurado: upload_max_filesize=%1$s, post_max_size=%2$s, tamanho máximo configurado=%3$s. Ajuste o php.ini ou reduza o limite em Configurações → Documentos.', 'eb-credito-rural' ), size_format( $limits['php_upload'] ), size_format( $limits['php_post'] ), size_format( $limits['configured'] ) ) );
			}
			if ( ( Options::bool( 'encrypt_files' ) || Options::bool( 'encrypt_fields' ) ) && ! Crypto::is_available() ) {
				$msgs[] = array( 'error', __( 'A criptografia está ligada, mas a chave EBCR_ENCRYPTION_KEY não está definida (ou é inválida) no wp-config.php. Novos dados NÃO estão sendo criptografados.', 'eb-credito-rural' ) );
			}
			if ( ! \EBCR\Support\Helpers::portal_page_id() ) {
				$msgs[] = array( 'warning', sprintf( /* translators: %s: shortcode */ __( 'Nenhuma página do portal configurada. Crie uma página com %s e selecione-a em Configurações → Geral.', 'eb-credito-rural' ), '<code>[ebcr_portal]</code>' ) );
			}
		}
		foreach ( $msgs as $m ) {
			printf(
				'<div class="notice notice-%s"><p>%s</p></div>',
				esc_attr( $m[0] ),
				wp_kses(
					$m[1],
					array(
						'a'      => array( 'href' => true ),
						'code'   => array(),
						'strong' => array(),
					)
				)
			);
		}
	}
}
