<?php
/**
 * Regras de autorização (capacidade + propriedade). Ponto único para evitar IDOR.
 *
 * @package EBCR
 */

namespace EBCR\Security;

use EBCR\Database\SubmissionRepository;
use EBCR\Domain\Status;
use EBCR\Roles\Capabilities;
use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Todas as verificações recebem o usuário explicitamente (testável) e usam user_can() com capacidades próprias.
 */
final class Authorization {

	/**
	 * Usuário faz parte da equipe?
	 *
	 * @param int $user_id Usuário.
	 * @return bool
	 */
	public static function is_team( $user_id ) {
		return $user_id > 0 && user_can( $user_id, Capabilities::CAP_VIEW );
	}

	/**
	 * Equipe pode ver esta solicitação? (respeita "analista vê só as atribuídas").
	 *
	 * @param int   $user_id    Usuário.
	 * @param array $submission Linha.
	 * @return bool
	 */
	private static function team_can_access( $user_id, array $submission ) {
		if ( ! self::is_team( $user_id ) ) {
			return false;
		}
		if ( Options::bool( 'analyst_only_assigned' ) && ! user_can( $user_id, Capabilities::CAP_FINAL_STATUS ) && ! user_can( $user_id, Capabilities::CAP_SETTINGS ) ) {
			return (int) $submission['assigned_to'] === (int) $user_id;
		}
		return true;
	}

	/**
	 * É o dono?
	 *
	 * @param int   $user_id    Usuário.
	 * @param array $submission Linha.
	 * @return bool
	 */
	public static function owns( $user_id, array $submission ) {
		return $user_id > 0 && (int) $submission['user_id'] === (int) $user_id && empty( $submission['deleted_at'] );
	}

	/**
	 * Pode ver a solicitação.
	 *
	 * @param int   $user_id    Usuário.
	 * @param array $submission Linha.
	 * @return bool
	 */
	public static function can_view_submission( $user_id, array $submission ) {
		if ( ! empty( $submission['deleted_at'] ) ) {
			return user_can( $user_id, Capabilities::CAP_SETTINGS );
		}
		return self::owns( $user_id, $submission ) || self::team_can_access( $user_id, $submission );
	}

	/**
	 * Cliente pode editar dados/documentos (dono + status editável).
	 *
	 * @param int   $user_id    Usuário.
	 * @param array $submission Linha.
	 * @return bool
	 */
	public static function client_can_edit( $user_id, array $submission ) {
		return self::owns( $user_id, $submission ) && Status::client_can_edit( $submission['status'] ) && empty( $submission['anonymized_at'] );
	}

	/**
	 * Cliente pode enviar documentos (rascunho, pendência ou pedido aberto da equipe).
	 *
	 * @param int   $user_id    Usuário.
	 * @param array $submission Linha.
	 * @return bool
	 */
	public static function client_can_upload( $user_id, array $submission ) {
		if ( ! self::owns( $user_id, $submission ) || ! empty( $submission['anonymized_at'] ) ) {
			return false;
		}
		if ( Status::client_can_edit( $submission['status'] ) ) {
			return true;
		}
		// Em outros status, apenas se houver pedido de documento aberto (verificado no upload) e o status não for final.
		return ! Status::is_final( $submission['status'] );
	}

	/**
	 * Cliente pode cancelar.
	 *
	 * @param int   $user_id    Usuário.
	 * @param array $submission Linha.
	 * @return bool
	 */
	public static function client_can_cancel( $user_id, array $submission ) {
		return self::owns( $user_id, $submission ) && Status::client_can_cancel( $submission['status'] );
	}

	/**
	 * Equipe pode editar (status, atribuição, mensagens, revisão de documentos).
	 *
	 * @param int   $user_id    Usuário.
	 * @param array $submission Linha.
	 * @return bool
	 */
	public static function team_can_edit( $user_id, array $submission ) {
		return user_can( $user_id, Capabilities::CAP_EDIT ) && self::team_can_access( $user_id, $submission );
	}

	/**
	 * Pode mudar para o status informado.
	 *
	 * @param int    $user_id    Usuário.
	 * @param array  $submission Linha.
	 * @param string $to         Destino.
	 * @return bool
	 */
	public static function can_change_status( $user_id, array $submission, $to ) {
		if ( ! self::team_can_edit( $user_id, $submission ) ) {
			return false;
		}
		if ( ! Status::can_transition( $submission['status'], $to ) ) {
			return false;
		}
		if ( Status::requires_final_cap( $to ) && ! user_can( $user_id, Capabilities::CAP_FINAL_STATUS ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Pode acessar (baixar/visualizar) um documento: dono OU capacidade de download + acesso à solicitação.
	 *
	 * @param int   $user_id  Usuário.
	 * @param array $document Linha do documento.
	 * @return bool
	 */
	public static function can_access_document( $user_id, array $document ) {
		if ( $user_id <= 0 || ! empty( $document['deleted_at'] ) ) {
			return false;
		}
		$submission = ( new SubmissionRepository() )->find( (int) $document['submission_id'] );
		if ( ! $submission ) {
			return false;
		}
		if ( (int) $document['user_id'] === (int) $user_id && self::owns( $user_id, $submission ) ) {
			return true;
		}
		return user_can( $user_id, Capabilities::CAP_DOWNLOAD ) && self::team_can_access( $user_id, $submission );
	}

	/**
	 * Pode ver mensagens internas.
	 *
	 * @param int $user_id Usuário.
	 * @return bool
	 */
	public static function can_see_internal( $user_id ) {
		return self::is_team( $user_id );
	}
}
