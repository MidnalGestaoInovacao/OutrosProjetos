<?php
/**
 * Crédito Rural → Mensagens dos canais: lista com filtros, detalhe, mudança de status e exportação CSV.
 *
 * @package EBCR
 */

namespace EBCR\Admin;

use EBCR\Database\ChannelMessageRepository;
use EBCR\Domain\ChannelMessage;
use EBCR\Roles\Capabilities;
use EBCR\Security\AuditLog;
use EBCR\Support\View;

defined( 'ABSPATH' ) || exit;

/**
 * Acesso: capacidade ebcr_manage_channels (gestores e administradores; pode ser dada ao encarregado/DPO).
 */
final class ChannelMessagesView {

	const PER_PAGE = 50;
	const PAGE     = 'ebcr-channels';

	/**
	 * Hooks (ações via admin-post, antes de qualquer saída HTML).
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_post_ebcr_channel_messages_csv', array( $this, 'export' ) );
		add_action( 'admin_post_ebcr_channel_status', array( $this, 'change_status' ) );
	}

	/**
	 * Filtros da requisição.
	 *
	 * @return array
	 */
	public static function args() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- filtros de listagem (somente leitura); ações verificam nonce.
		$g = static function ( $k ) {
			return isset( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) : '';
		};
		// phpcs:enable
		return array(
			'channel'   => isset( ChannelMessage::channels()[ $g( 'channel' ) ] ) ? $g( 'channel' ) : '',
			'status'    => in_array( $g( 'status' ), ChannelMessage::STATUSES, true ) ? $g( 'status' ) : '',
			'date_from' => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $g( 'date_from' ) ) ? $g( 'date_from' ) : '',
			'date_to'   => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $g( 'date_to' ) ) ? $g( 'date_to' ) : '',
			'per_page'  => self::PER_PAGE,
			'page'      => max( 1, (int) $g( 'paged' ) ),
		);
	}

	/**
	 * Lista ou detalhe.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( Capabilities::CAP_CHANNELS ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'eb-credito-rural' ), 403 );
		}
		$repo   = new ChannelMessageRepository();
		$notice = isset( $_GET['ebcr_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['ebcr_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- mensagem informativa.
		$view   = isset( $_GET['view'] ) ? absint( $_GET['view'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navegação.
		if ( $view ) {
			$row = $repo->find( $view );
			if ( ! $row ) {
				wp_die( esc_html__( 'Mensagem não encontrada.', 'eb-credito-rural' ), 404 );
			}
			AuditLog::log( 'channel_message_viewed', 'channel_message', $row['protocol'], array( 'channel' => $row['channel'] ) );
			View::show(
				'admin/channel-message',
				array(
					'row'      => $row,
					'fields'   => ChannelMessage::fields_of( $row ),
					'labels'   => ChannelMessage::field_labels(),
					'channels' => ChannelMessage::channels(),
					'statuses' => ChannelMessage::status_labels(),
					'notice'   => $notice,
				)
			);
			return;
		}
		$args                  = self::args();
		list( $items, $total ) = $repo->query( $args );
		View::show(
			'admin/channel-messages',
			array(
				'items'    => $items,
				'total'    => $total,
				'args'     => $args,
				'pages'    => (int) ceil( $total / self::PER_PAGE ),
				'channels' => ChannelMessage::channels(),
				'statuses' => ChannelMessage::status_labels(),
				'notice'   => $notice,
			)
		);
	}

	/**
	 * Muda o status (admin-post com nonce).
	 *
	 * @return void
	 */
	public function change_status() {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verificado abaixo.
		if ( ! current_user_can( Capabilities::CAP_CHANNELS ) || ! $id || ! check_admin_referer( 'ebcr_channel_status_' . $id ) ) {
			wp_die( esc_html__( 'Sem permissão ou sessão expirada.', 'eb-credito-rural' ), 403 );
		}
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		$repo   = new ChannelMessageRepository();
		$row    = $repo->find( $id );
		$ok     = $row && in_array( $status, ChannelMessage::STATUSES, true );
		if ( $ok && $status !== $row['status'] ) {
			$repo->set_status( $id, $status );
			AuditLog::log(
				'channel_status_changed',
				'channel_message',
				$row['protocol'],
				array(
					'from' => $row['status'],
					'to'   => $status,
				)
			);
		}
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => self::PAGE,
					'view'        => $id,
					'ebcr_notice' => rawurlencode( $ok ? __( 'Status atualizado.', 'eb-credito-rural' ) : __( 'Status inválido.', 'eb-credito-rural' ) ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Exporta CSV (admin-post, nonce + ebcr_export). Sem hash de IP nem user agent.
	 *
	 * @return void
	 */
	public function export() {
		if ( ! current_user_can( Capabilities::CAP_CHANNELS ) || ! current_user_can( Capabilities::CAP_EXPORT ) || ! check_admin_referer( 'ebcr_channel_messages_csv' ) ) {
			wp_die( esc_html__( 'Sem permissão ou sessão expirada.', 'eb-credito-rural' ), 403 );
		}
		$args             = self::args();
		$args['per_page'] = 10000;
		$args['page']     = 1;
		list( $items )    = ( new ChannelMessageRepository() )->query( $args );
		AuditLog::log(
			'export',
			'channel_messages',
			'',
			array(
				'count'   => count( $items ),
				'channel' => $args['channel'],
			)
		);
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="mensagens-canais-' . gmdate( 'Ymd-His' ) . '.csv"' );
		header( 'X-Content-Type-Options: nosniff' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- saída.
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- BOM para o Excel.
		fputcsv( $out, self::csv_header(), ';' );
		foreach ( $items as $i ) {
			fputcsv( $out, self::csv_row( $i ), ';' );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- idem.
		exit;
	}

	/**
	 * Cabeçalho do CSV.
	 *
	 * @return string[]
	 */
	public static function csv_header() {
		return array_merge( array( 'data_utc', 'protocolo', 'canal', 'status', 'anonimo', 'pagina', 'usuario_id' ), ChannelMessage::FIELDS );
	}

	/**
	 * Linha do CSV (valores que começam com = + - @ tab/CR são neutralizados contra injeção de fórmulas).
	 *
	 * @param array $i Registro.
	 * @return array
	 */
	public static function csv_row( array $i ) {
		$fields = ChannelMessage::fields_of( $i );
		$row    = array( $i['created_at'], $i['protocol'], $i['channel'], $i['status'], ! empty( $i['anonymous'] ) ? '1' : '0', $i['page'], ! empty( $i['user_id'] ) ? (int) $i['user_id'] : '' );
		foreach ( ChannelMessage::FIELDS as $k ) {
			$row[] = isset( $fields[ $k ] ) ? (string) $fields[ $k ] : '';
		}
		return array_map(
			static function ( $v ) {
				$v = (string) $v;
				return preg_match( '/^[=+\-@\t\r]/', $v ) ? "'" . $v : $v;
			},
			$row
		);
	}
}
