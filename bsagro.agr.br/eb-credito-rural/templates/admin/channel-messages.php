<?php
/**
 * Mensagens dos canais (somente leitura). Variáveis: $items, $total, $args, $pages, $channels, $statuses, $notice.
 *
 * @package EBCR
 */

use EBCR\Admin\ChannelMessagesView;
use EBCR\Domain\ChannelMessage;
use EBCR\Roles\Capabilities;
use EBCR\Support\Helpers;

defined( 'ABSPATH' ) || exit;
$ebcr_filters = array_filter(
	array(
		'channel'   => $args['channel'],
		'status'    => $args['status'],
		'date_from' => $args['date_from'],
		'date_to'   => $args['date_to'],
	)
);
?>
<div class="wrap ebcr-admin">
	<h1><?php esc_html_e( 'Mensagens dos canais', 'eb-credito-rural' ); ?></h1>
	<?php if ( $notice ) : ?>
		<div class="notice notice-info is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
	<?php endif; ?>
	<p class="description"><?php esc_html_e( 'Mensagens enviadas pelos formulários públicos do site (canal de contato, Portal do Titular/LGPD e Canal de Integridade). Cada mensagem recebe um protocolo e é enviada por e-mail ao destinatário definido em Configurações → Canais de atendimento. Relatos anônimos do Canal de Integridade não guardam nome, e-mail, telefone, IP nem navegador. Os registros são apagados pela rotina de retenção após o prazo configurado.', 'eb-credito-rural' ); ?></p>
	<p class="description"><?php esc_html_e( 'Endpoint para os formulários:', 'eb-credito-rural' ); ?> <code><?php echo esc_html( rest_url( \EBCR\Rest\Routes::NS . '/channel-message' ) ); ?></code> — <?php esc_html_e( 'também disponível no front-end em window.EBCR_CLIENT_AREA.channelEndpoint.', 'eb-credito-rural' ); ?></p>
	<form method="get">
		<input type="hidden" name="page" value="<?php echo esc_attr( ChannelMessagesView::PAGE ); ?>">
		<p>
			<select name="channel" aria-label="<?php esc_attr_e( 'Canal', 'eb-credito-rural' ); ?>"><option value=""><?php esc_html_e( 'Todos os canais', 'eb-credito-rural' ); ?></option>
			<?php foreach ( $channels as $ebcr_k => $ebcr_c ) : ?>
				<option value="<?php echo esc_attr( $ebcr_k ); ?>" <?php selected( $args['channel'], $ebcr_k ); ?>><?php echo esc_html( $ebcr_c['label'] ); ?></option>
			<?php endforeach; ?>
			</select>
			<select name="status" aria-label="<?php esc_attr_e( 'Status', 'eb-credito-rural' ); ?>"><option value=""><?php esc_html_e( 'Todos os status', 'eb-credito-rural' ); ?></option>
			<?php foreach ( $statuses as $ebcr_k => $ebcr_l ) : ?>
				<option value="<?php echo esc_attr( $ebcr_k ); ?>" <?php selected( $args['status'], $ebcr_k ); ?>><?php echo esc_html( $ebcr_l ); ?></option>
			<?php endforeach; ?>
			</select>
			<input type="date" name="date_from" value="<?php echo esc_attr( $args['date_from'] ); ?>" aria-label="<?php esc_attr_e( 'De', 'eb-credito-rural' ); ?>"> <input type="date" name="date_to" value="<?php echo esc_attr( $args['date_to'] ); ?>" aria-label="<?php esc_attr_e( 'Até', 'eb-credito-rural' ); ?>">
			<button class="button"><?php esc_html_e( 'Filtrar', 'eb-credito-rural' ); ?></button>
			<?php if ( current_user_can( Capabilities::CAP_EXPORT ) ) : ?>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array_merge( $ebcr_filters, array( 'action' => 'ebcr_channel_messages_csv' ) ), admin_url( 'admin-post.php' ) ), 'ebcr_channel_messages_csv' ) ); ?>"><?php esc_html_e( 'Exportar CSV', 'eb-credito-rural' ); ?></a>
			<?php endif; ?>
		</p>
	</form>
	<p class="description"><?php /* translators: %d: total */ printf( esc_html__( '%d mensagem(ns).', 'eb-credito-rural' ), (int) $total ); ?></p>
	<table class="widefat striped ebcr-t">
		<thead><tr><th><?php esc_html_e( 'Data', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Protocolo', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Canal', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Status', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Remetente', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Assunto / tipo', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Página', 'eb-credito-rural' ); ?></th></tr></thead>
		<tbody>
		<?php
		foreach ( $items as $ebcr_i ) :
			$ebcr_f     = ChannelMessage::fields_of( $ebcr_i );
			$ebcr_link  = add_query_arg(
				array(
					'page' => ChannelMessagesView::PAGE,
					'view' => (int) $ebcr_i['id'],
				),
				admin_url( 'admin.php' )
			);
			$ebcr_topic = '';
			foreach ( array( 'assunto', 'direito', 'tipo' ) as $ebcr_key ) {
				if ( ! empty( $ebcr_f[ $ebcr_key ] ) ) {
					$ebcr_topic = (string) $ebcr_f[ $ebcr_key ];
					break;
				}
			}
			?>
			<tr>
				<td><?php echo esc_html( Helpers::date( $ebcr_i['created_at'] ) ); ?></td>
				<td><a href="<?php echo esc_url( $ebcr_link ); ?>"><code><?php echo esc_html( $ebcr_i['protocol'] ); ?></code></a></td>
				<td><?php echo esc_html( isset( $channels[ $ebcr_i['channel'] ] ) ? $channels[ $ebcr_i['channel'] ]['label'] : $ebcr_i['channel'] ); ?></td>
				<td><?php echo esc_html( isset( $statuses[ $ebcr_i['status'] ] ) ? $statuses[ $ebcr_i['status'] ] : $ebcr_i['status'] ); ?></td>
				<td>
					<?php
					if ( ! empty( $ebcr_i['anonymous'] ) ) {
						echo '<em>' . esc_html__( 'Anônimo', 'eb-credito-rural' ) . '</em>';
					} else {
						$ebcr_who = trim( ( isset( $ebcr_f['nome'] ) ? $ebcr_f['nome'] : '' ) . ( ! empty( $ebcr_f['email'] ) ? ' <' . $ebcr_f['email'] . '>' : '' ) );
						echo esc_html( '' !== $ebcr_who ? $ebcr_who : '—' );
					}
					?>
				</td>
				<td><?php echo esc_html( '' !== $ebcr_topic ? wp_trim_words( $ebcr_topic, 12 ) : '—' ); ?></td>
				<td><code><?php echo esc_html( '' !== $ebcr_i['page'] ? $ebcr_i['page'] : '—' ); ?></code></td>
			</tr>
		<?php endforeach; ?>
		<?php if ( ! $items ) : ?>
			<tr><td colspan="7"><?php esc_html_e( 'Nenhuma mensagem.', 'eb-credito-rural' ); ?></td></tr>
		<?php endif; ?>
		</tbody>
	</table>
	<?php if ( $pages > 1 ) : ?>
		<p class="tablenav-pages">
		<?php
		echo wp_kses_post(
			paginate_links(
				array(
					'base'    => add_query_arg( 'paged', '%#%' ),
					'format'  => '',
					'current' => (int) $args['page'],
					'total'   => (int) $pages,
				)
			)
		);
		?>
		</p>
	<?php endif; ?>
</div>
