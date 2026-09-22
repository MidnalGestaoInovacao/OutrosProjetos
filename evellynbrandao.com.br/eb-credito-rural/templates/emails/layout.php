<?php
/**
 * Layout HTML dos e-mails. Variáveis: $subject, $body (HTML já filtrado), $logo_url, $site, $home.
 *
 * @package EBCR
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width"><title><?php echo esc_html( $subject ); ?></title></head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f4f5;padding:24px 12px;">
<tr><td align="center">
<table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;">
<tr><td style="background:#0b0b0b;padding:22px 28px;text-align:center;">
<?php if ( ! empty( $logo_url ) ) : ?>
<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site ); ?>" style="max-height:60px;max-width:280px;">
<?php else : ?>
<span style="color:#d4af37;font-size:20px;letter-spacing:.12em;text-transform:uppercase;"><?php echo esc_html( $site ); ?></span>
<?php endif; ?>
</td></tr>
<tr><td style="padding:28px;font-size:15px;line-height:1.6;">
<?php echo wp_kses_post( $body ); ?>
</td></tr>
<tr><td style="padding:16px 28px;background:#fafafa;border-top:1px solid #e5e7eb;font-size:12px;color:#6b7280;">
<?php
/* translators: %s: nome do site */
printf( esc_html__( 'Mensagem automática de %s. Não responda a este e-mail; use a sua área no site para falar com a equipe.', 'eb-credito-rural' ), '<a href="' . esc_url( $home ) . '" style="color:#6b7280;">' . esc_html( $site ) . '</a>' );
?>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
