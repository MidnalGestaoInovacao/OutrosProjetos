<?php
/**
 * Layout HTML dos e-mails. Variáveis: $subject, $body (HTML já filtrado), $logo_url, $site (nome da marca), $home,
 * $colors (header_bg, header_fg, link — Configurações → Identidade visual; padrão: preto e dourado originais).
 *
 * @package EBCR
 */

defined( 'ABSPATH' ) || exit;
$ebcr_colors = isset( $colors ) && is_array( $colors ) ? $colors : \EBCR\Admin\Branding::email_colors();
$ebcr_hbg    = sanitize_hex_color( isset( $ebcr_colors['header_bg'] ) ? $ebcr_colors['header_bg'] : '' );
$ebcr_hfg    = sanitize_hex_color( isset( $ebcr_colors['header_fg'] ) ? $ebcr_colors['header_fg'] : '' );
$ebcr_hbg    = $ebcr_hbg ? $ebcr_hbg : '#0b0b0b';
$ebcr_hfg    = $ebcr_hfg ? $ebcr_hfg : '#d4af37';
$ebcr_site   = isset( $site ) && '' !== (string) $site ? (string) $site : \EBCR\Admin\Branding::brand_name();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width"><title><?php echo esc_html( $subject ); ?></title></head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f4f5;padding:24px 12px;">
<tr><td align="center">
<table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;">
<tr><td style="background:<?php echo esc_attr( $ebcr_hbg ); ?>;padding:22px 28px;text-align:center;">
<?php if ( ! empty( $logo_url ) ) : ?>
<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $ebcr_site ); ?>" style="max-height:60px;max-width:280px;">
<?php else : ?>
<span style="color:<?php echo esc_attr( $ebcr_hfg ); ?>;font-size:20px;letter-spacing:.12em;text-transform:uppercase;"><?php echo esc_html( $ebcr_site ); ?></span>
<?php endif; ?>
</td></tr>
<tr><td style="padding:28px;font-size:15px;line-height:1.6;">
<?php echo wp_kses_post( $body ); ?>
</td></tr>
<tr><td style="padding:16px 28px;background:#fafafa;border-top:1px solid #e5e7eb;font-size:12px;color:#6b7280;">
<?php
/* translators: %s: nome do site */
printf( esc_html__( 'Mensagem automática de %s. Não responda a este e-mail; use a sua área no site para falar com a equipe.', 'eb-credito-rural' ), '<a href="' . esc_url( $home ) . '" style="color:#6b7280;">' . esc_html( $ebcr_site ) . '</a>' );
?>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
