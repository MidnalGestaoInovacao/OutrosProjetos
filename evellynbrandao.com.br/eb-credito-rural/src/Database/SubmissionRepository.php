<?php
/**
 * Repositório de solicitações.
 *
 * @package EBCR
 */

namespace EBCR\Database;

use EBCR\Domain\Status;
use EBCR\Support\Uuid;

defined( 'ABSPATH' ) || exit;

/**
 * CRUD e consultas de ebcr_submissions.
 */
class SubmissionRepository extends Db {

	/**
	 * Cria um rascunho para o usuário.
	 *
	 * @param int   $user_id Usuário.
	 * @param array $extra   Campos extras.
	 * @return array Linha criada.
	 */
	public function create_draft( $user_id, array $extra = array() ) {
		$now  = self::now();
		$data = array_merge(
			array(
				'public_id'    => Uuid::v4(),
				'user_id'      => (int) $user_id,
				'status'       => Status::DRAFT,
				'current_step' => 1,
				'created_at'   => $now,
				'updated_at'   => $now,
			),
			$extra
		);
		$id   = self::insert_row( 'submissions', $data );
		return $this->find( $id );
	}

	/**
	 * Por ID.
	 *
	 * @param int $id ID.
	 * @return array|null
	 */
	public function find( $id ) {
		return self::row_by_id( 'submissions', $id );
	}

	/**
	 * Por UUID público (ignora excluídas).
	 *
	 * @param string $public_id UUID.
	 * @return array|null
	 */
	public function find_by_public_id( $public_id ) {
		global $wpdb;
		$public_id = Uuid::sanitize( $public_id );
		if ( ! $public_id ) {
			return null;
		}
		$t   = self::table( 'submissions' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE public_id = %s AND deleted_at IS NULL", $public_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
		return $row ? $row : null;
	}

	/**
	 * Por protocolo.
	 *
	 * @param string $protocol Protocolo.
	 * @return array|null
	 */
	public function find_by_protocol( $protocol ) {
		global $wpdb;
		$t   = self::table( 'submissions' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE protocol = %s AND deleted_at IS NULL", strtoupper( trim( $protocol ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
		return $row ? $row : null;
	}

	/**
	 * Atualiza campos.
	 *
	 * @param int   $id   ID.
	 * @param array $data Dados.
	 * @return bool
	 */
	public function update( $id, array $data ) {
		$data['updated_at'] = self::now();
		return self::update_row( 'submissions', $id, $data );
	}

	/**
	 * Solicitações de um usuário (não excluídas), mais recentes primeiro.
	 *
	 * @param int $user_id Usuário.
	 * @return array
	 */
	public function for_user( $user_id ) {
		global $wpdb;
		$t = self::table( 'submissions' );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE user_id = %d AND deleted_at IS NULL ORDER BY created_at DESC", (int) $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
	}

	/**
	 * Última solicitação enviada do usuário.
	 *
	 * @param int $user_id Usuário.
	 * @return array|null
	 */
	public function last_submitted( $user_id ) {
		global $wpdb;
		$t   = self::table( 'submissions' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE user_id = %d AND submitted_at IS NOT NULL AND deleted_at IS NULL ORDER BY submitted_at DESC LIMIT 1", (int) $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
		return $row ? $row : null;
	}

	/**
	 * Solicitações em andamento do usuário.
	 *
	 * @param int $user_id Usuário.
	 * @return array
	 */
	public function in_progress_for_user( $user_id ) {
		global $wpdb;
		$statuses = Status::in_progress();
		if ( ! $statuses ) {
			return array();
		}
		$t  = self::table( 'submissions' );
		$in = self::in_placeholders( $statuses );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE user_id = %d AND deleted_at IS NULL AND status IN ($in)", array_merge( array( (int) $user_id ), $statuses ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders gerados por in_placeholders.
	}

	/**
	 * Rascunho aberto do usuário (o mais recente).
	 *
	 * @param int $user_id Usuário.
	 * @return array|null
	 */
	public function open_draft( $user_id ) {
		global $wpdb;
		$t   = self::table( 'submissions' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE user_id = %d AND status = %s AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 1", (int) $user_id, Status::DRAFT ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
		return $row ? $row : null;
	}

	/**
	 * Listagem administrativa com filtros e paginação.
	 *
	 * @param array $args status, assigned_to, search, uf, activity, date_from, date_to, amount_min, amount_max, orderby, order, per_page, page, only_assigned_to.
	 * @return array [items, total]
	 */
	public function query( array $args ) {
		global $wpdb;
		$t     = self::table( 'submissions' );
		$where = array( 's.deleted_at IS NULL', "s.status <> 'rascunho'" );
		$vals  = array();
		if ( ! empty( $args['include_drafts'] ) ) {
			$where = array( 's.deleted_at IS NULL' );
		}
		if ( ! empty( $args['status'] ) ) {
			$where[] = 's.status = %s';
			$vals[]  = $args['status'];
		}
		if ( ! empty( $args['assigned_to'] ) ) {
			$where[] = 's.assigned_to = %d';
			$vals[]  = (int) $args['assigned_to'];
		}
		if ( ! empty( $args['only_assigned_to'] ) ) {
			$where[] = 's.assigned_to = %d';
			$vals[]  = (int) $args['only_assigned_to'];
		}
		if ( ! empty( $args['user_id'] ) ) {
			$where[] = 's.user_id = %d';
			$vals[]  = (int) $args['user_id'];
		}
		if ( ! empty( $args['date_from'] ) ) {
			$where[] = 's.submitted_at >= %s';
			$vals[]  = $args['date_from'] . ' 00:00:00';
		}
		if ( ! empty( $args['date_to'] ) ) {
			$where[] = 's.submitted_at <= %s';
			$vals[]  = $args['date_to'] . ' 23:59:59';
		}
		if ( isset( $args['amount_min'] ) && '' !== $args['amount_min'] ) {
			$where[] = 's.requested_amount >= %f';
			$vals[]  = (float) $args['amount_min'];
		}
		if ( isset( $args['amount_max'] ) && '' !== $args['amount_max'] ) {
			$where[] = 's.requested_amount <= %f';
			$vals[]  = (float) $args['amount_max'];
		}
		if ( ! empty( $args['search'] ) ) {
			$s       = '%' . $wpdb->esc_like( trim( $args['search'] ) ) . '%';
			$digits  = preg_replace( '/\D+/', '', $args['search'] );
			$where[] = '(s.protocol LIKE %s OR u.display_name LIKE %s OR u.user_email LIKE %s OR s.public_id = %s' . ( $digits ? ' OR EXISTS (SELECT 1 FROM ' . self::table( 'submission_data' ) . ' d WHERE d.submission_id = s.id AND d.encrypted = 0 AND d.data LIKE %s)' : '' ) . ')';
			$vals[]  = $s;
			$vals[]  = $s;
			$vals[]  = $s;
			$vals[]  = trim( $args['search'] );
			if ( $digits ) {
				$vals[] = '%' . $wpdb->esc_like( $digits ) . '%';
			}
		}
		$allowed_order = array( 'submitted_at', 'created_at', 'updated_at', 'requested_amount', 'status', 'protocol' );
		$orderby       = isset( $args['orderby'] ) && in_array( $args['orderby'], $allowed_order, true ) ? $args['orderby'] : 'submitted_at';
		$order         = isset( $args['order'] ) && 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$per_page      = max( 1, min( 200, isset( $args['per_page'] ) ? (int) $args['per_page'] : 20 ) );
		$page          = max( 1, isset( $args['page'] ) ? (int) $args['page'] : 1 );
		$offset        = ( $page - 1 ) * $per_page;
		$sql_where     = implode( ' AND ', $where );
		$sql           = "SELECT s.*, u.display_name AS user_name, u.user_email FROM `{$t}` s LEFT JOIN {$wpdb->users} u ON u.ID = s.user_id WHERE {$sql_where} ORDER BY s.{$orderby} {$order} LIMIT %d OFFSET %d";
		$count_sql     = "SELECT COUNT(*) FROM `{$t}` s LEFT JOIN {$wpdb->users} u ON u.ID = s.user_id WHERE {$sql_where}";
		$items         = (array) $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $vals, array( $per_page, $offset ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- SQL montado com placeholders e listas brancas.
		$total         = $vals ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $vals ) ) : (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- idem.
		return array( $items, $total );
	}

	/**
	 * Contagem por status (para dashboard/filtros).
	 *
	 * @return array status => total
	 */
	public function count_by_status() {
		global $wpdb;
		$t    = self::table( 'submissions' );
		$rows = (array) $wpdb->get_results( "SELECT status, COUNT(*) AS n FROM `{$t}` WHERE deleted_at IS NULL GROUP BY status", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
		$out  = array();
		foreach ( $rows as $r ) {
			$out[ $r['status'] ] = (int) $r['n'];
		}
		return $out;
	}

	/**
	 * Exclusão lógica.
	 *
	 * @param int $id ID.
	 * @return bool
	 */
	public function soft_delete( $id ) {
		return $this->update( $id, array( 'deleted_at' => self::now() ) );
	}

	/**
	 * Solicitações com status final há mais de N meses (para retenção).
	 *
	 * @param string[] $statuses Status.
	 * @param int      $months   Meses.
	 * @return array
	 */
	public function finished_before( array $statuses, $months ) {
		global $wpdb;
		if ( ! $statuses || $months < 1 ) {
			return array();
		}
		$t     = self::table( 'submissions' );
		$in    = self::in_placeholders( $statuses );
		$limit = gmdate( 'Y-m-d H:i:s', strtotime( "-{$months} months" ) );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE status IN ($in) AND updated_at < %s AND anonymized_at IS NULL", array_merge( $statuses, array( $limit ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders gerados.
	}
}
