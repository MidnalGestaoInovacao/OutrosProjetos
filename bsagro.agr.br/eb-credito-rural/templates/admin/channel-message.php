<?php
/**
 * Detalhe de uma mensagem de canal. Variáveis: $row, $fields, $labels, $channels, $statuses, $notice.
 *
 * @package EBCR
 */

use EBCR\Admin\ChannelMessagesView;
use EBCR\Support\Helpers;

defined( 'ABSPATH' ) || exit;
$ebcr_id      = (int) $row['id'];
$ebcr_channel = isset( $channels[ $row['channel'] ] ) ? $channels[ $row['channel'] ]['label'] : $row['channel'];
$ebcr_back    = add_query_arg( 'page', ChannelMessagesView::PAGE, admin_url( 'admin.php' ) );
$ebcr_anon    = ! empty( $row['anonymous'] );
$ebcr_reply   = ! $ebcr_anon && ! empty( $fields['email'] ) && is_email( $fields['email'] ) ? $fields['email'] : '';
?>
<div class="wrap ebcr-admin">
	<h1><?php /* translators: %s: protocolo */ printf( esc_html__( 'Mensagem %s', 'eb-credito-rural' ), '<code>' . esc_html( $row['protocol'] ) . '</code>' ); ?></h1>
	<p><a href="<?php echo esc_url( $ebcr_back ); ?>">&larr; <?php esc_html_e( 'Voltar para a lista', 'eb-credito-rural' ); ?></a></p>
	<?php if ( $notice ) : ?>
		<div class="notice notice-info is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
	<?php endif; ?>
	<table class="widefat striped ebcr-t" style="max-width:900px">
		<tbody>
			<tr><th scope="row" style="width:220px"><?php esc_html_e( 'Protocolo', 'eb-credito-rural' ); ?></th><td><code><?php echo esc_html( $row['protocol'] ); ?></code></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Canal', 'eb-credito-rural' ); ?></th><td><?php echo esc_html( $ebcr_channel ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Status', 'eb-credito-rural' ); ?></th><td><?php echo esc_html( isset( $statuses[ $row['status'] ] ) ? $statuses[ $row['status'] ] : $row['status'] ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Anônimo', 'eb-credito-rural' ); ?></th><td><?php echo $ebcr_anon ? esc_html__( 'Sim — sem dados de identificação, IP ou navegador.', 'eb-credito-rural' ) : esc_html__( 'Não', 'eb-credito-rural' ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Recebida em', 'eb-credito-rural' ); ?></th><td><?php echo esc_html( Helpers::date( $row['created_at'] ) ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Atualizada em', 'eb-credito-rural' ); ?></th><td><?php echo esc_html( Helpers::date( $row['updated_at'] ) ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Página de origem', 'eb-credito-rural' ); ?></th><td><code><?php echo esc_html( '' !== $row['page'] ? $row['page'] : '—' ); ?></code></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Usuário logado', 'eb-credito-rural' ); ?></th><td><?php echo ! empty( $row['user_id'] ) ? esc_html( Helpers::user_name( (int) $row['user_id'] ) . ' (#' . (int) $row['user_id'] . ')' ) : '—'; ?></td></tr>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'Conteúdo', 'eb-credito-rural' ); ?></h2>
	<table class="widefat striped ebcr-t" style="max-width:900px">
		<tbody>
		<?php foreach ( $fields as $ebcr_k => $ebcr_v ) : ?>
			<tr><th scope="row" style="width:220px"><?php echo esc_html( isset( $labels[ $ebcr_k ] ) ? $labels[ $ebcr_k ] : $ebcr_k ); ?></th><td><?php echo nl2br( esc_html( (string) $ebcr_v ) ); ?></td></tr>
		<?php endforeach; ?>
		<?php if ( ! $fields ) : ?>
			<tr><td colspan="2"><?php esc_html_e( 'Sem campos.', 'eb-credito-rural' ); ?></td></tr>
		<?php endif; ?>
		</tbody>
	</table>
	<?php if ( $ebcr_reply ) : ?>
		<p><a class="button" href="<?php echo esc_url( 'mailto:' . $ebcr_reply . '?subject=' . rawurlencode( 'Re: ' . $row['protocol'] ) ); ?>"><?php esc_html_e( 'Responder por e-mail', 'eb-credito-rural' ); ?></a></p>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Alterar status', 'eb-credito-rural' ); ?></h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="ebcr_channel_status">
		<input type="hidden" name="id" value="<?php echo esc_attr( (string) $ebcr_id ); ?>">
		<?php wp_nonce_field( 'ebcr_channel_status_' . $ebcr_id ); ?>
		<select name="status" aria-label="<?php esc_attr_e( 'Status', 'eb-credito-rural' ); ?>">
		<?php foreach ( $statuses as $ebcr_k => $ebcr_l ) : ?>
			<option value="<?php echo esc_attr( $ebcr_k ); ?>" <?php selected( $row['status'], $ebcr_k ); ?>><?php echo esc_html( $ebcr_l ); ?></option>
		<?php endforeach; ?>
		</select>
		<button class="button button-primary"><?php esc_html_e( 'Salvar status', 'eb-credito-rural' ); ?></button>
	</form>
	<p class="description"><?php esc_html_e( 'A visualização e a mudança de status ficam registradas no log de auditoria.', 'eb-credito-rural' ); ?></p>
</div>
