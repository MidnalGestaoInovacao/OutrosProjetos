<?php
/**
 * Trilha de auditoria.
 *
 * @package EBCR
 */

namespace EBCR\Security;

use EBCR\Database\AuditLogRepository;
use EBCR\Support\Ip;

defined( 'ABSPATH' ) || exit;

/**
 * Registro de ações relevantes.
 */
final class AuditLog {

	/**
	 * Hooks de login/logout.
	 *
	 * @return void
	 */
	public function register() {
		add_action(
			'wp_login',
			static function ( $login, $user ) {
				self::log( 'login', 'user', $user->ID, array( 'login' => $login ), $user->ID );
			},
			10,
			2
		);
		add_action(
			'wp_logout',
			static function ( $user_id ) {
				self::log( 'logout', 'user', $user_id, array(), $user_id );
			}
		);
		add_action(
			'wp_login_failed',
			static function ( $login ) {
				self::log( 'login_failed', 'user', '', array( 'login' => mb_substr( (string) $login, 0, 100 ) ), 0 );
			}
		);
	}

	/**
	 * Registra uma ação.
	 *
	 * @param string          $action      Ação (snake_case).
	 * @param string          $object_type Tipo do objeto.
	 * @param string|int      $object_id   ID (preferir public_id).
	 * @param array           $meta        Dados extras (sem dados sensíveis).
	 * @param int|null        $actor_id    Autor (padrão: usuário atual).
	 * @return void
	 */
	public static function log( $action, $object_type = '', $object_id = '', array $meta = array(), $actor_id = null ) {
		try {
			( new AuditLogRepository() )->insert(
				array(
					'actor_id'    => null === $actor_id ? ( get_current_user_id() ? get_current_user_id() : null ) : ( $actor_id ? (int) $actor_id : null ),
					'action'      => sanitize_key( $action ),
					'object_type' => sanitize_key( $object_type ),
					'object_id'   => mb_substr( (string) $object_id, 0, 60 ),
					'ip'          => Ip::get(),
					'meta'        => wp_json_encode( $meta, JSON_UNESCAPED_UNICODE ),
					'created_at'  => current_time( 'mysql', true ),
				)
			);
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- auditoria nunca deve derrubar a ação principal.
			// Silencioso.
		}
	}

	/**
	 * Rótulos das ações conhecidas.
	 *
	 * @return array
	 */
	public static function labels() {
		return array(
			'login'                => __( 'Login', 'eb-credito-rural' ),
			'logout'               => __( 'Logout', 'eb-credito-rural' ),
			'login_failed'         => __( 'Falha de login', 'eb-credito-rural' ),
			'login_blocked'        => __( 'Login bloqueado (limite)', 'eb-credito-rural' ),
			'register'             => __( 'Cadastro', 'eb-credito-rural' ),
			'email_confirmed'      => __( 'E-mail confirmado', 'eb-credito-rural' ),
			'submission_created'   => __( 'Solicitação criada', 'eb-credito-rural' ),
			'submission_submitted' => __( 'Solicitação enviada', 'eb-credito-rural' ),
			'submission_status'    => __( 'Mudança de status', 'eb-credito-rural' ),
			'submission_assigned'  => __( 'Analista atribuído', 'eb-credito-rural' ),
			'submission_cancelled' => __( 'Solicitação cancelada', 'eb-credito-rural' ),
			'document_uploaded'    => __( 'Upload de documento', 'eb-credito-rural' ),
			'document_downloaded'  => __( 'Download de documento', 'eb-credito-rural' ),
			'document_viewed'      => __( 'Visualização de documento', 'eb-credito-rural' ),
			'document_reviewed'    => __( 'Revisão de documento', 'eb-credito-rural' ),
			'document_requested'   => __( 'Documento solicitado', 'eb-credito-rural' ),
			'document_deleted'     => __( 'Documento removido', 'eb-credito-rural' ),
			'access_denied'        => __( 'Acesso negado', 'eb-credito-rural' ),
			'settings_updated'     => __( 'Configurações alteradas', 'eb-credito-rural' ),
			'export'               => __( 'Exportação', 'eb-credito-rural' ),
			'lgpd_request'         => __( 'Solicitação LGPD', 'eb-credito-rural' ),
			'lgpd_export'          => __( 'Cópia de dados (LGPD)', 'eb-credito-rural' ),
			'message_sent'         => __( 'Mensagem enviada', 'eb-credito-rural' ),
			'retention_anonymized' => __( 'Anonimização (retenção)', 'eb-credito-rural' ),
			'protection_test'      => __( 'Teste de proteção de arquivos', 'eb-credito-rural' ),
		);
	}
}
