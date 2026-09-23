<?php
/**
 * Ficha CRM do cliente.
 *
 * @package EBCR
 */

namespace EBCR\Database;

defined( 'ABSPATH' ) || exit;

/**
 * ebcr_crm_contacts.
 */
class CrmContactRepository extends Db {

	/**
	 * Ficha por usuário (cria se não existir).
	 *
	 * @param int   $user_id  Usuário.
	 * @param array $defaults Dados iniciais.
	 * @return array
	 */
	public function get_or_create( $user_id, array $defaults = array() ) {
		$row = $this->find_by_user( $user_id );
		if ( $row ) {
			return $row;
		}
		$now = self::now();
		$id  = self::insert_row(
			'crm_contacts',
			array_merge(
				array(
					'user_id'    => (int) $user_id,
					'stage'      => 'novo',
					'created_at' => $now,
					'updated_at' => $now,
				),
				$defaults
			)
		);
		return self::row_by_id( 'crm_contacts', $id );
	}

	/**
	 * Ficha por ID.
	 *
	 * @param int $id ID.
	 * @return array|null
	 */
	public function find( $id ) {
		return self::row_by_id( 'crm_contacts', $id );
	}

	/**
	 * Ficha por usuário (sem criar).
	 *
	 * @param int $user_id Usuário.
	 * @return array|null
	 */
	public function find_by_user( $user_id ) {
		global $wpdb;
		$t   = self::table( 'crm_contacts' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE user_id = %d", (int) $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
		return $row ? $row : null;
	}

	/**
	 * Atualiza.
	 *
	 * @param int   $id   ID.
	 * @param array $data Dados.
	 * @return bool
	 */
	public function update( $id, array $data ) {
		$data['updated_at'] = self::now();
		return self::update_row( 'crm_contacts', $id, $data );
	}

	/**
	 * Remove a ficha (usado em limpezas/testes; as atividades devem ser removidas à parte).
	 *
	 * @param int $id ID.
	 * @return bool
	 */
	public function delete( $id ) {
		global $wpdb;
		return false !== $wpdb->delete( self::table( 'crm_contacts' ), array( 'id' => (int) $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- tabela própria.
	}

	/**
	 * Entre os usuários informados, quais ainda não têm ficha.
	 *
	 * @param int[] $user_ids IDs de usuário.
	 * @return int[]
	 */
	public function missing_user_ids( array $user_ids ) {
		global $wpdb;
		$user_ids = array_values( array_unique( array_map( 'intval', $user_ids ) ) );
		if ( ! $user_ids ) {
			return array();
		}
		$t        = self::table( 'crm_contacts' );
		$in       = implode( ',', array_fill( 0, count( $user_ids ), '%d' ) );
		$existing = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM `{$t}` WHERE user_id IN ($in)", $user_ids ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- placeholders %d gerados dinamicamente para a lista IN().
		return array_values( array_diff( $user_ids, $existing ) );
	}

	/**
	 * Atribui responsável a várias fichas.
	 *
	 * @param int[] $ids      IDs das fichas.
	 * @param int   $owner_id Responsável (0 = nenhum).
	 * @return int Linhas afetadas.
	 */
	public function bulk_assign( array $ids, $owner_id ) {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		if ( ! $ids ) {
			return 0;
		}
		$t  = self::table( 'crm_contacts' );
		$in = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		if ( (int) $owner_id > 0 ) {
			$sql = $wpdb->prepare( "UPDATE `{$t}` SET owner_id = %d, updated_at = %s WHERE id IN ($in)", array_merge( array( (int) $owner_id, self::now() ), $ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- placeholders %d gerados dinamicamente para a lista IN().
		} else {
			$sql = $wpdb->prepare( "UPDATE `{$t}` SET owner_id = NULL, updated_at = %s WHERE id IN ($in)", array_merge( array( self::now() ), $ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- placeholders %d gerados dinamicamente para a lista IN().
		}
		return (int) $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- preparado acima.
	}

	/**
	 * Total de fichas por estágio.
	 *
	 * @return array estágio => total
	 */
	public function count_by_stage() {
		global $wpdb;
		$t    = self::table( 'crm_contacts' );
		$rows = (array) $wpdb->get_results( "SELECT stage, COUNT(*) AS n FROM `{$t}` GROUP BY stage", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
		$out  = array();
		foreach ( $rows as $r ) {
			$out[ $r['stage'] ] = (int) $r['n'];
		}
		return $out;
	}

	/**
	 * Listagem com busca, filtros, ordenação e paginação (compatível com MySQL e SQLite).
	 *
	 * Cada item traz, além das colunas da ficha: user_name, user_email, open_submissions (nº de solicitações
	 * em andamento, conforme $args['open_statuses']) e last_activity_at (data da última atividade).
	 *
	 * @param array $args search, stage, owner_id, lead_source, tag, open_statuses, orderby, order, per_page, page.
	 * @return array [items, total]
	 */
	public function query( array $args ) {
		global $wpdb;
		$t     = self::table( 'crm_contacts' );
		$ts    = self::table( 'submissions' );
		$ta    = self::table( 'crm_activities' );
		$where = array( '1 = 1' );
		$vals  = array();

		$open_statuses = isset( $args['open_statuses'] ) ? array_values( array_filter( array_map( 'sanitize_key', (array) $args['open_statuses'] ) ) ) : array();

		if ( ! empty( $args['stage'] ) ) {
			$where[] = 'c.stage = %s';
			$vals[]  = sanitize_key( $args['stage'] );
		}
		if ( isset( $args['owner_id'] ) && '' !== (string) $args['owner_id'] ) {
			if ( 'none' === $args['owner_id'] || 0 === (int) $args['owner_id'] ) {
				$where[] = 'c.owner_id IS NULL';
			} else {
				$where[] = 'c.owner_id = %d';
				$vals[]  = (int) $args['owner_id'];
			}
		}
		if ( ! empty( $args['lead_source'] ) ) {
			$where[] = 'c.lead_source = %s';
			$vals[]  = sanitize_key( $args['lead_source'] );
		}
		if ( ! empty( $args['tag'] ) ) {
			$tag     = $wpdb->esc_like( trim( (string) $args['tag'] ) );
			$where[] = '(c.tags = %s OR c.tags LIKE %s OR c.tags LIKE %s OR c.tags LIKE %s)';
			$vals[]  = $tag;
			$vals[]  = $tag . ',%';
			$vals[]  = '%,' . $tag;
			$vals[]  = '%,' . $tag . ',%';
		}
		if ( ! empty( $args['search'] ) ) {
			$term    = trim( (string) $args['search'] );
			$like    = '%' . $wpdb->esc_like( $term ) . '%';
			$digits  = preg_replace( '/\D+/', '', $term );
			$phone   = $digits ? '%' . $wpdb->esc_like( $digits ) . '%' : $like;
			$where[] = '(u.display_name LIKE %s OR u.user_email LIKE %s OR c.phone LIKE %s OR c.whatsapp LIKE %s)';
			$vals[]  = $like;
			$vals[]  = $like;
			$vals[]  = $phone;
			$vals[]  = $phone;
		}

		$allowed_order = array(
			'user_name'      => 'u.display_name',
			'stage'          => 'c.stage',
			'next_action_at' => 'c.next_action_at',
			'updated_at'     => 'c.updated_at',
			'created_at'     => 'c.created_at',
		);
		$computed      = array( 'open_submissions', 'last_activity_at' );
		$orderby_key   = isset( $args['orderby'] ) ? (string) $args['orderby'] : 'updated_at';
		$orderby       = isset( $allowed_order[ $orderby_key ] ) ? $allowed_order[ $orderby_key ] : 'c.updated_at';
		$order         = isset( $args['order'] ) && 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';
		$per_page      = max( 1, min( 1000, isset( $args['per_page'] ) ? (int) $args['per_page'] : 20 ) );
		$page          = max( 1, isset( $args['page'] ) ? (int) $args['page'] : 1 );
		$offset        = ( $page - 1 ) * $per_page;
		$sql_where     = implode( ' AND ', $where );
		$sql           = "SELECT c.*, u.display_name AS user_name, u.user_email FROM `{$t}` c LEFT JOIN {$wpdb->users} u ON u.ID = c.user_id WHERE {$sql_where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$count_sql     = "SELECT COUNT(*) FROM `{$t}` c LEFT JOIN {$wpdb->users} u ON u.ID = c.user_id WHERE {$sql_where}";
		$items         = (array) $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $vals, array( $per_page, $offset ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
		$total         = $vals ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $vals ) ) : (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- idem.
		// Agregados calculados em consultas separadas (subconsultas correlacionadas não são confiáveis em todos os bancos).
		$open_by_user = array();
		$last_by_id   = array();
		if ( $items ) {
			$user_ids    = array_values( array_unique( array_map( 'intval', wp_list_pluck( $items, 'user_id' ) ) ) );
			$contact_ids = array_values( array_unique( array_map( 'intval', wp_list_pluck( $items, 'id' ) ) ) );
			if ( $open_statuses && $user_ids ) {
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, COUNT(*) AS n FROM `{$ts}` WHERE deleted_at IS NULL AND user_id IN (" . implode( ',', array_fill( 0, count( $user_ids ), '%d' ) ) . ') AND status IN (' . self::in_placeholders( $open_statuses ) . ') GROUP BY user_id', array_merge( $user_ids, $open_statuses ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
				foreach ( (array) $rows as $r ) {
					$open_by_user[ (int) $r['user_id'] ] = (int) $r['n'];
				}
			}
			if ( $contact_ids ) {
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT contact_id, MAX(created_at) AS last_at FROM `{$ta}` WHERE contact_id IN (" . implode( ',', array_fill( 0, count( $contact_ids ), '%d' ) ) . ') GROUP BY contact_id', $contact_ids ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
				foreach ( (array) $rows as $r ) {
					$last_by_id[ (int) $r['contact_id'] ] = $r['last_at'];
				}
			}
			foreach ( $items as &$item ) {
				$item['open_submissions'] = isset( $open_by_user[ (int) $item['user_id'] ] ) ? $open_by_user[ (int) $item['user_id'] ] : 0;
				$item['last_activity_at'] = isset( $last_by_id[ (int) $item['id'] ] ) ? $last_by_id[ (int) $item['id'] ] : null;
			}
			unset( $item );
			if ( in_array( $orderby_key, $computed, true ) ) {
				usort(
					$items,
					static function ( $a, $b ) use ( $orderby_key, $order ) {
						$cmp = strcmp( (string) $a[ $orderby_key ], (string) $b[ $orderby_key ] );
						if ( 'open_submissions' === $orderby_key ) {
							$cmp = (int) $a[ $orderby_key ] <=> (int) $b[ $orderby_key ];
						}
						return 'ASC' === $order ? $cmp : -$cmp;
					}
				);
			}
		}
		return array( $items, $total );
	}
}
