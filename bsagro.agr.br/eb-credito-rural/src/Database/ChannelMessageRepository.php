<?php
/**
 * Mensagens dos canais públicos (contato, titular/DPO, integridade).
 *
 * @package EBCR
 */

namespace EBCR\Database;

defined( 'ABSPATH' ) || exit;

/**
 * ebcr_channel_messages.
 */
class ChannelMessageRepository extends Db {

	/**
	 * Insere.
	 *
	 * @param array $data Dados já validados.
	 * @return int
	 */
	public function insert( array $data ) {
		$now = self::now();
		return self::insert_row(
			'channel_messages',
			array_merge(
				array(
					'status'     => 'novo',
					'created_at' => $now,
					'updated_at' => $now,
				),
				$data
			)
		);
	}

	/**
	 * Registro por ID.
	 *
	 * @param int $id ID.
	 * @return array|null
	 */
	public function find( $id ) {
		return self::row_by_id( 'channel_messages', (int) $id );
	}

	/**
	 * Registro por protocolo.
	 *
	 * @param string $protocol Protocolo.
	 * @return array|null
	 */
	public function find_by_protocol( $protocol ) {
		global $wpdb;
		$t   = self::table( 'channel_messages' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE protocol = %s", (string) $protocol ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
		return $row ? $row : null;
	}

	/**
	 * Protocolo já usado?
	 *
	 * @param string $protocol Protocolo.
	 * @return bool
	 */
	public function protocol_exists( $protocol ) {
		return null !== $this->find_by_protocol( $protocol );
	}

	/**
	 * Atualiza o status.
	 *
	 * @param int    $id     ID.
	 * @param string $status Status.
	 * @return bool
	 */
	public function set_status( $id, $status ) {
		return self::update_row(
			'channel_messages',
			(int) $id,
			array(
				'status'     => (string) $status,
				'updated_at' => self::now(),
			)
		);
	}

	/**
	 * Consulta paginada (channel, status, date_from, date_to, email, protocol).
	 *
	 * @param array $args Filtros.
	 * @return array [itens, total]
	 */
	public function query( array $args = array() ) {
		global $wpdb;
		$t      = self::table( 'channel_messages' );
		$where  = array( '1=1' );
		$params = array();
		foreach ( array( 'channel', 'status', 'email', 'protocol' ) as $col ) {
			if ( ! empty( $args[ $col ] ) ) {
				$where[]  = "{$col} = %s";
				$params[] = (string) $args[ $col ];
			}
		}
		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = 'created_at >= %s';
			$params[] = $args['date_from'] . ' 00:00:00';
		}
		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = 'created_at <= %s';
			$params[] = $args['date_to'] . ' 23:59:59';
		}
		$per_page = max( 1, min( 10000, isset( $args['per_page'] ) ? (int) $args['per_page'] : 50 ) );
		$page     = max( 1, isset( $args['page'] ) ? (int) $args['page'] : 1 );
		$sql      = implode( ' AND ', $where );
		$count    = "SELECT COUNT(*) FROM `{$t}` WHERE {$sql}";
		$list     = "SELECT * FROM `{$t}` WHERE {$sql} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d";
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- tabela interna; valores via prepare().
		$total = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count, $params ) ) : $wpdb->get_var( $count ) );
		$items = (array) $wpdb->get_results( $wpdb->prepare( $list, array_merge( $params, array( $per_page, ( $page - 1 ) * $per_page ) ) ), ARRAY_A );
		// phpcs:enable
		return array( $items, $total );
	}

	/**
	 * Mensagens identificadas de um e-mail (exportador de dados pessoais; anônimas nunca entram).
	 *
	 * @param string $email E-mail.
	 * @return array
	 */
	public function for_email( $email ) {
		global $wpdb;
		$email = strtolower( trim( (string) $email ) );
		if ( '' === $email ) {
			return array();
		}
		$t = self::table( 'channel_messages' );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE email = %s AND anonymous = 0 ORDER BY created_at ASC, id ASC", $email ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
	}

	/**
	 * Substitui os campos e limpa os identificadores de uma mensagem (apagador de dados pessoais).
	 *
	 * @param int   $id     ID.
	 * @param array $fields Campos restantes.
	 * @return bool
	 */
	public function anonymize( $id, array $fields ) {
		global $wpdb;
		$r = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- tabela própria.
			self::table( 'channel_messages' ),
			array(
				'fields'     => (string) wp_json_encode( $fields, JSON_UNESCAPED_UNICODE ),
				'email'      => '',
				'ip_hash'    => null,
				'user_agent' => null,
				'user_id'    => null,
				'updated_at' => self::now(),
			),
			array( 'id' => (int) $id )
		);
		return false !== $r;
	}

	/**
	 * Expurgo por idade (rotina de retenção).
	 *
	 * @param int $days Dias.
	 * @return int Registros apagados.
	 */
	public function purge_older_than( $days ) {
		global $wpdb;
		$t = self::table( 'channel_messages' );
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM `{$t}` WHERE created_at < %s", gmdate( 'Y-m-d H:i:s', time() - max( 1, (int) $days ) * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
	}
}
