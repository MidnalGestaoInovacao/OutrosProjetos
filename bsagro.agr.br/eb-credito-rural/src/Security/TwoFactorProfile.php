<?php
/**
 * Bloco "Verificação em duas etapas" no perfil do usuário (cadastro TOTP/e-mail, códigos de backup, dispositivos confiáveis,
 * desativação e desativação por administrador). Entregue pelo módulo "2FA".
 *
 * @package EBCR
 */

namespace EBCR\Security;

use EBCR\Support\Helpers;
use EBCR\Support\View;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks show_user_profile / edit_user_profile (exibição) e user_profile_update_errors (validação + gravação).
 */
final class TwoFactorProfile {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'show_user_profile', array( $this, 'render' ), 5 );
		add_action( 'edit_user_profile', array( $this, 'render' ), 5 );
		add_action( 'user_profile_update_errors', array( $this, 'save' ), 10, 3 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_notices', array( $this, 'notices' ) );
	}

	/**
	 * Guarda um aviso para exibir na próxima página do admin (o perfil redireciona após salvar).
	 *
	 * @param int      $user_id Usuário que verá o aviso.
	 * @param string   $type    success | error | warning | info.
	 * @param string   $text    Texto.
	 * @param string[] $codes   Códigos de backup a exibir uma única vez.
	 * @return void
	 */
	public static function notice( $user_id, $type, $text, array $codes = array() ) {
		set_transient(
			'ebcr_2fa_notice_' . (int) $user_id,
			array(
				'type'  => $type,
				'text'  => $text,
				'codes' => $codes,
			),
			5 * MINUTE_IN_SECONDS
		);
	}

	/**
	 * Scripts/estilos nas telas de perfil (QR code renderizado no navegador, sem CDN).
	 *
	 * @param string $hook Tela.
	 * @return void
	 */
	public function assets( $hook ) {
		if ( ! in_array( $hook, array( 'profile.php', 'user-edit.php' ), true ) ) {
			return;
		}
		wp_enqueue_style( 'ebcr-2fa', EBCR_URL . 'assets/css/2fa.css', array(), EBCR_VERSION );
		wp_enqueue_script( 'ebcr-qrcode', EBCR_URL . 'assets/vendor/qrcode-generator/qrcode.js', array(), '1.4.4', true );
		wp_enqueue_script( 'ebcr-2fa', EBCR_URL . 'assets/js/2fa.js', array( 'ebcr-qrcode' ), EBCR_VERSION, true );
		wp_localize_script(
			'ebcr-2fa',
			'ebcr2fa',
			array(
				'copied'  => __( 'Copiado!', 'eb-credito-rural' ),
				'copy'    => __( 'Copiar', 'eb-credito-rural' ),
				'qrAlt'   => __( 'QR code para cadastrar no aplicativo autenticador', 'eb-credito-rural' ),
				'confirm' => __( 'Tem certeza? Os códigos de backup atuais deixarão de valer.', 'eb-credito-rural' ),
			)
		);
	}

	/**
	 * Avisos: resultado da última ação (com códigos de backup) e configuração obrigatória.
	 *
	 * @return void
	 */
	public function notices() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}
		$screen = get_current_screen();
		$is_pro = $screen && in_array( $screen->id, array( 'profile', 'user-edit' ), true );
		$key    = 'ebcr_2fa_notice_' . $user_id;
		$notice = get_transient( $key );
		if ( is_array( $notice ) && $is_pro ) {
			delete_transient( $key );
			$type = in_array( $notice['type'], array( 'success', 'error', 'warning', 'info' ), true ) ? $notice['type'] : 'info';
			echo '<div class="notice notice-' . esc_attr( $type ) . ' ebcr-2fa-notice"><p>' . esc_html( $notice['text'] ) . '</p>';
			if ( ! empty( $notice['codes'] ) ) {
				echo '<p><strong>' . esc_html__( 'Códigos de backup (guarde em local seguro; cada um vale uma única vez e eles não serão mostrados de novo):', 'eb-credito-rural' ) . '</strong></p>';
				echo '<pre class="ebcr-2fa-codes" id="ebcr-2fa-codes">' . esc_html( implode( "\n", $notice['codes'] ) ) . '</pre>';
				echo '<p><button type="button" class="button" data-ebcr-copy="ebcr-2fa-codes">' . esc_html__( 'Copiar códigos', 'eb-credito-rural' ) . '</button></p>';
			}
			echo '</div>';
		}
		if ( 'profile' === ( $screen ? $screen->id : '' ) && 'setup' === TwoFactor::required_action( $user_id ) ) {
			echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'A verificação em duas etapas é obrigatória para a equipe.', 'eb-credito-rural' ) . '</strong> ' . esc_html__( 'Configure-a na seção "Verificação em duas etapas" abaixo para liberar o restante do painel.', 'eb-credito-rural' ) . ' <a href="#ebcr-2fa">' . esc_html__( 'Ir para a configuração', 'eb-credito-rural' ) . '</a></p></div>';
		}
	}

	/**
	 * Exibe o bloco no perfil (o próprio usuário) ou na edição de outro usuário (administrador: só status e desativar).
	 *
	 * @param \WP_User $user Usuário exibido.
	 * @return void
	 */
	public function render( $user ) {
		if ( ! ( $user instanceof \WP_User ) || ! TwoFactor::applies_to( $user->ID ) || ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}
		$is_self = get_current_user_id() === (int) $user->ID;
		$enabled = TwoFactor::is_enabled( $user->ID );
		$method  = TwoFactor::method( $user->ID );
		$data    = array(
			'user'           => $user,
			'is_self'        => $is_self,
			'mode'           => TwoFactor::mode(),
			'enabled'        => $enabled,
			'method'         => $method,
			'enabled_at'     => (int) get_user_meta( $user->ID, TwoFactor::META_ENABLED_AT, true ),
			'crypto_ok'      => Crypto::is_available(),
			'secret_broken'  => $enabled && 'totp' === $method && '' === TwoFactor::secret( $user->ID ),
			'backup_left'    => TwoFactor::backup_codes_left( $user->ID ),
			'trusted'        => TwoFactor::trusted_devices( $user->ID ),
			'setup_required' => $is_self && 'setup' === TwoFactor::required_action( $user->ID ),
			'email_masked'   => TwoFactor::mask_email( $user->user_email ),
			'secret'         => '',
			'secret_pretty'  => '',
			'otpauth'        => '',
			'send_url'       => '',
		);
		if ( $is_self && ! $enabled && 'off' !== $data['mode'] ) {
			$secret                = TwoFactor::pending_secret( $user->ID, true );
			$data['secret']        = $secret;
			$data['secret_pretty'] = Totp::format_secret( $secret );
			$data['otpauth']       = TwoFactor::otpauth_uri( $user, $secret );
			$data['send_url']      = Nonces::url(
				add_query_arg(
					array(
						TwoFactor::QUERY_VAR => '1',
						'ebcr_2fa_do'        => 'send_activation',
					),
					home_url( '/' )
				),
				'2fa_send_activation'
			);
		}
		View::show( 'two-factor/profile', $data );
	}

	/**
	 * Valida e aplica as ações do bloco ao salvar o perfil. Erros impedem a gravação e reexibem o formulário.
	 *
	 * @param \WP_Error $errors Erros (por referência).
	 * @param bool      $update É atualização (não criação).
	 * @param \stdClass $user   Dados enviados (com ID).
	 * @return void
	 */
	public function save( $errors, $update, $user ) {
		if ( ! $update || ! is_object( $user ) || empty( $user->ID ) || ! ( $errors instanceof \WP_Error ) ) {
			return;
		}
		$user_id = (int) $user->ID;
		if ( ! isset( $_POST['ebcr_2fa'] ) || ! is_array( $_POST['ebcr_2fa'] ) || ! Nonces::verify( '2fa_profile' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verificado por Nonces::verify na mesma condição.
			return;
		}
		if ( ! current_user_can( 'edit_user', $user_id ) || ! TwoFactor::applies_to( $user_id ) ) {
			return;
		}
		$in      = $this->input();
		$is_self = get_current_user_id() === $user_id;
		$enabled = TwoFactor::is_enabled( $user_id );

		if ( ! $is_self ) {
			// Administrador editando outro usuário: apenas desativar (auditado como feito por administrador).
			if ( $enabled && $in['admin_disable'] ) {
				TwoFactor::disable( $user_id, true );
				self::notice( get_current_user_id(), 'success', __( 'Verificação em duas etapas desativada para o usuário. Ele poderá configurar novamente no próprio perfil.', 'eb-credito-rural' ) );
			}
			return;
		}

		if ( $enabled ) {
			$this->save_enabled( $errors, $user_id, $in );
			return;
		}
		$this->save_activation( $errors, $user_id, $in );
	}

	/**
	 * Lê e sanitiza os campos ebcr_2fa[...] do POST.
	 *
	 * @return array
	 */
	private function input() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verificado em save() antes de chamar.
		$raw = isset( $_POST['ebcr_2fa'] ) && is_array( $_POST['ebcr_2fa'] ) ? wp_unslash( $_POST['ebcr_2fa'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cada campo é sanitizado abaixo.
		// phpcs:enable
		$revoke = array();
		if ( ! empty( $raw['revoke'] ) && is_array( $raw['revoke'] ) ) {
			foreach ( $raw['revoke'] as $id ) {
				$id = preg_replace( '/[^a-f0-9]/', '', (string) $id );
				if ( '' !== $id ) {
					$revoke[] = $id;
				}
			}
		}
		$method = isset( $raw['method'] ) ? sanitize_key( $raw['method'] ) : '';
		$code   = '';
		if ( in_array( $method, array( 'totp', 'email' ), true ) && isset( $raw[ 'code_' . $method ] ) ) {
			$code = sanitize_text_field( $raw[ 'code_' . $method ] );
		}
		return array(
			'method'        => $method,
			'code'          => $code,
			'confirm'       => isset( $raw['confirm'] ) ? (string) $raw['confirm'] : '', // senha/código: não alterar.
			'disable'       => ! empty( $raw['disable'] ),
			'regenerate'    => ! empty( $raw['regenerate'] ),
			'admin_disable' => ! empty( $raw['admin_disable'] ),
			'revoke_all'    => ! empty( $raw['revoke_all'] ),
			'revoke'        => $revoke,
		);
	}

	/**
	 * Ações com 2FA já ativo: desativar, gerar novos códigos, revogar dispositivos.
	 *
	 * @param \WP_Error $errors  Erros.
	 * @param int       $user_id Usuário.
	 * @param array     $in      Entrada.
	 * @return void
	 */
	private function save_enabled( $errors, $user_id, array $in ) {
		if ( $in['disable'] || $in['regenerate'] ) {
			if ( ! TwoFactor::confirm_identity( $user_id, $in['confirm'] ) ) {
				AuditLog::log( '2fa_failed', 'user', $user_id, array( 'reason' => $in['disable'] ? 'disable_confirm' : 'regenerate_confirm' ), $user_id );
				$errors->add( 'ebcr_2fa_confirm', __( 'Para desativar ou gerar novos códigos, confirme com sua senha atual, um código do aplicativo ou um código de backup.', 'eb-credito-rural' ) );
				return;
			}
			if ( $in['disable'] ) {
				TwoFactor::disable( $user_id, false );
				self::notice( $user_id, 'success', __( 'Verificação em duas etapas desativada.', 'eb-credito-rural' ) );
				return;
			}
			$codes = TwoFactor::generate_backup_codes( $user_id );
			AuditLog::log( '2fa_enabled', 'user', $user_id, array( 'event' => 'backup_regenerated' ), $user_id );
			self::notice( $user_id, 'success', __( 'Novos códigos de backup gerados. Os anteriores deixaram de valer.', 'eb-credito-rural' ), $codes );
			return;
		}
		if ( $in['revoke_all'] || $in['revoke'] ) {
			$n = TwoFactor::revoke_trusted( $user_id, $in['revoke_all'] ? array() : $in['revoke'] );
			self::notice(
				$user_id,
				'success',
				sprintf(
					/* translators: %d: quantidade */
					_n( '%d dispositivo confiável revogado.', '%d dispositivos confiáveis revogados.', $n, 'eb-credito-rural' ),
					$n
				)
			);
		}
	}

	/**
	 * Ativação (o código digitado é a intenção; sem código nada acontece e o perfil salva normalmente).
	 *
	 * @param \WP_Error $errors  Erros.
	 * @param int       $user_id Usuário.
	 * @param array     $in      Entrada.
	 * @return void
	 */
	private function save_activation( $errors, $user_id, array $in ) {
		if ( 'off' === TwoFactor::mode() || '' === trim( $in['code'] ) || ! in_array( $in['method'], array( 'totp', 'email' ), true ) ) {
			return;
		}
		$codes = 'totp' === $in['method'] ? TwoFactor::activate_totp( $user_id, $in['code'] ) : TwoFactor::activate_email( $user_id, $in['code'] );
		if ( is_wp_error( $codes ) ) {
			$errors->add( 'ebcr_2fa_code', $codes->get_error_message() );
			return;
		}
		// Quem acabou de provar posse do fator não precisa verificar de novo nesta sessão.
		TwoFactor::set_session_flag( $user_id, wp_get_session_token(), 'verified' );
		self::notice(
			$user_id,
			'success',
			'totp' === $in['method']
				? __( 'Verificação em duas etapas ativada com o aplicativo autenticador.', 'eb-credito-rural' )
				: __( 'Verificação em duas etapas ativada com código por e-mail.', 'eb-credito-rural' ),
			$codes
		);
	}

	/**
	 * Data formatada para o bloco.
	 *
	 * @param int $timestamp Unix time.
	 * @return string
	 */
	public static function date( $timestamp ) {
		return $timestamp ? Helpers::date( gmdate( 'Y-m-d H:i:s', (int) $timestamp ) ) : '—';
	}
}
