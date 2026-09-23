<?php
/**
 * Exportação CSV da lista de solicitações do painel da equipe (mesmas colunas da exportação do wp-admin).
 *
 * @package EBCR
 */

namespace EBCR\Frontend\Team;

use EBCR\Database\SubmissionDataRepository;
use EBCR\Domain\Status;
use EBCR\Security\Authorization;
use EBCR\Support\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Gera strings CSV (UTF-8 com BOM, separador ";", proteção contra fórmulas).
 */
final class Export {

	/**
	 * Cabeçalho da exportação de solicitações.
	 *
	 * @return string[]
	 */
	public static function submissions_header() {
		return array( 'protocolo', 'status', 'cliente', 'email', 'tipo', 'documento', 'valor', 'finalidade', 'prazo_meses', 'uf', 'carteira', 'analista', 'enviada_em', 'atualizada_em' );
	}

	/**
	 * Linhas (cabeçalho + dados) das solicitações que o usuário pode ver. CPF/CNPJ saem mascarados.
	 *
	 * @param int   $user_id Usuário.
	 * @param array $items   Linhas de SubmissionRepository::query().
	 * @return array
	 */
	public static function submissions_rows( $user_id, array $items ) {
		$data = new SubmissionDataRepository();
		$rows = array( self::submissions_header() );
		foreach ( $items as $s ) {
			if ( ! Authorization::can_view_submission( $user_id, $s ) ) {
				continue;
			}
			$u      = get_userdata( (int) $s['user_id'] );
			$ident  = $data->get( (int) $s['id'], 'identificacao' );
			$doc    = isset( $ident['cnpj'] ) && $ident['cnpj'] ? Helpers::mask_document( $ident['cnpj'] ) : ( isset( $ident['cpf'] ) && $ident['cpf'] ? Helpers::mask_document( $ident['cpf'] ) : '' );
			$rows[] = array(
				(string) $s['protocol'],
				Status::label( $s['status'] ),
				$u ? $u->display_name : '',
				$u ? $u->user_email : '',
				(string) $s['person_type'],
				$doc,
				(string) $s['requested_amount'],
				(string) $s['purpose'],
				(string) $s['term_months'],
				isset( $ident['uf'] ) ? (string) $ident['uf'] : '',
				isset( $s['fund'] ) ? (string) $s['fund'] : '',
				$s['assigned_to'] ? Helpers::user_name( (int) $s['assigned_to'] ) : '',
				(string) $s['submitted_at'],
				(string) $s['updated_at'],
			);
		}
		return $rows;
	}

	/**
	 * CSV das solicitações.
	 *
	 * @param int   $user_id Usuário.
	 * @param array $items   Linhas.
	 * @return string
	 */
	public static function submissions_csv( $user_id, array $items ) {
		return self::csv_string( self::submissions_rows( $user_id, $items ) );
	}

	/**
	 * Converte linhas em CSV.
	 *
	 * @param array $rows Linhas.
	 * @return string
	 */
	public static function csv_string( array $rows ) {
		$h = fopen( 'php://temp', 'w+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- buffer em memória.
		fwrite( $h, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- BOM para Excel.
		foreach ( $rows as $r ) {
			fputcsv(
				$h,
				array_map(
					static function ( $v ) {
						return is_string( $v ) && preg_match( '/^[=+\-@]/', $v ) ? "'" . $v : $v;
					},
					$r
				),
				';',
				'"',
				'\\'
			);
		}
		rewind( $h );
		$csv = (string) stream_get_contents( $h );
		fclose( $h ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- idem.
		return $csv;
	}
}
