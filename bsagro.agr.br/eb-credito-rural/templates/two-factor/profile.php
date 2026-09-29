<?php
/**
 * Bloco "Verificação em duas etapas" no perfil.
 * Variáveis: $user, $is_self, $mode, $enabled, $method, $enabled_at, $crypto_ok, $secret_broken, $backup_left, $trusted,
 * $setup_required, $email_masked, $secret, $secret_pretty, $otpauth, $send_url.
 *
 * @package EBCR
 */

use EBCR\Security\TwoFactorProfile;

defined( 'ABSPATH' ) || exit;
$ebcr_method_labels = array(
	'totp'  => __( 'Aplicativo autenticador (TOTP)', 'eb-credito-rural' ),
	'email' => __( 'Código por e-mail', 'eb-credito-rural' ),
);
?>
<div class="ebcr-2fa-section<?php echo $setup_required ? ' ebcr-2fa-section--required' : ''; ?>" id="ebcr-2fa">
<h2><?php esc_html_e( 'Verificação em duas etapas (2FA)', 'eb-credito-rural' ); ?></h2>
<?php if ( 'off' === $mode && ! $enabled ) : ?>
	<p class="description"><?php esc_html_e( 'A verificação em duas etapas está desligada nas configurações do plugin (Crédito Rural → Configurações → Segurança).', 'eb-credito-rural' ); ?></p>
</div>
	<?php return; ?>
<?php endif; ?>
<?php echo ebcr_nonce_field( '2fa_profile' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- campo de nonce gerado pelo WordPress. ?>
<table class="form-table ebcr-2fa-table" role="presentation">
<tr>
	<th scope="row"><?php esc_html_e( 'Situação', 'eb-credito-rural' ); ?></th>
	<td>
		<?php if ( $enabled ) : ?>
			<p><span class="ebcr-2fa-badge ebcr-2fa-badge--on"><?php esc_html_e( 'Ativa', 'eb-credito-rural' ); ?></span>
			<?php
			/* translators: 1: método, 2: data */
			printf( esc_html__( 'Método: %1$s. Ativada em %2$s.', 'eb-credito-rural' ), '<strong>' . esc_html( isset( $ebcr_method_labels[ $method ] ) ? $ebcr_method_labels[ $method ] : $method ) . '</strong>', esc_html( TwoFactorProfile::date( $enabled_at ) ) );
			?>
			</p>
			<p class="description">
			<?php
			/* translators: %d: quantidade */
			printf( esc_html( _n( '%d código de backup restante.', '%d códigos de backup restantes.', (int) $backup_left, 'eb-credito-rural' ) ), (int) $backup_left );
			?>
			</p>
			<?php if ( $secret_broken ) : ?>
				<div class="notice notice-error inline"><p><?php esc_html_e( 'O segredo do aplicativo não pôde ser lido (a chave de criptografia mudou ou foi removida). Use um código de backup para entrar, desative e configure novamente.', 'eb-credito-rural' ); ?></p></div>
			<?php endif; ?>
		<?php else : ?>
			<p><span class="ebcr-2fa-badge ebcr-2fa-badge--off"><?php esc_html_e( 'Inativa', 'eb-credito-rural' ); ?></span>
			<?php echo 'required' === $mode ? esc_html__( 'Obrigatória para a equipe: configure abaixo.', 'eb-credito-rural' ) : esc_html__( 'Recomendada para toda a equipe: protege o painel mesmo que a senha vaze.', 'eb-credito-rural' ); ?></p>
		<?php endif; ?>
	</td>
</tr>
<?php if ( ! $is_self ) : ?>
	<?php if ( $enabled ) : ?>
	<tr>
		<th scope="row"><?php esc_html_e( 'Desativar (administrador)', 'eb-credito-rural' ); ?></th>
		<td>
			<label><input type="checkbox" name="ebcr_2fa[admin_disable]" value="1"> <?php esc_html_e( 'Desativar a verificação em duas etapas deste usuário', 'eb-credito-rural' ); ?></label>
			<p class="description"><?php esc_html_e( 'Use quando o usuário perdeu o celular e os códigos de backup. A ação fica registrada no log de auditoria; o usuário poderá configurar de novo no perfil dele.', 'eb-credito-rural' ); ?></p>
		</td>
	</tr>
	<?php else : ?>
	<tr>
		<th scope="row"></th>
		<td><p class="description"><?php esc_html_e( 'Somente o próprio usuário pode ativar a verificação em duas etapas, no perfil dele.', 'eb-credito-rural' ); ?></p></td>
	</tr>
	<?php endif; ?>
<?php elseif ( ! $enabled ) : ?>
<tr>
	<th scope="row"><?php esc_html_e( 'Método', 'eb-credito-rural' ); ?></th>
	<td>
		<fieldset>
			<legend class="screen-reader-text"><?php esc_html_e( 'Escolha o método', 'eb-credito-rural' ); ?></legend>
			<label class="ebcr-2fa-radio"><input type="radio" name="ebcr_2fa[method]" value="totp" checked> <strong><?php echo esc_html( $ebcr_method_labels['totp'] ); ?></strong> — <?php esc_html_e( 'Google Authenticator, Microsoft Authenticator, Authy, 1Password etc. Recomendado.', 'eb-credito-rural' ); ?></label>
			<label class="ebcr-2fa-radio"><input type="radio" name="ebcr_2fa[method]" value="email"> <strong><?php echo esc_html( $ebcr_method_labels['email'] ); ?></strong> — <?php esc_html_e( 'um código de 6 dígitos é enviado ao e-mail da sua conta a cada login.', 'eb-credito-rural' ); ?></label>
		</fieldset>
	</td>
</tr>
<tr id="ebcr-2fa-panel-totp" class="ebcr-2fa-panel">
	<th scope="row"><?php esc_html_e( 'Aplicativo autenticador', 'eb-credito-rural' ); ?></th>
	<td>
		<ol class="ebcr-2fa-steps">
			<li><?php esc_html_e( 'Abra o aplicativo e escolha "Adicionar conta" / "Ler QR code".', 'eb-credito-rural' ); ?></li>
			<li><?php esc_html_e( 'Aponte a câmera para o código abaixo (ou digite o segredo manualmente).', 'eb-credito-rural' ); ?></li>
			<li><?php esc_html_e( 'Digite o código de 6 dígitos mostrado pelo aplicativo e clique em "Atualizar perfil".', 'eb-credito-rural' ); ?></li>
		</ol>
		<div class="ebcr-2fa-setup">
			<div class="ebcr-2fa-qr" data-ebcr-otpauth="<?php echo esc_attr( $otpauth ); ?>" role="img" aria-label="<?php esc_attr_e( 'QR code para cadastrar no aplicativo autenticador', 'eb-credito-rural' ); ?>"><noscript><?php esc_html_e( 'Ative o JavaScript para ver o QR code ou use o segredo ao lado.', 'eb-credito-rural' ); ?></noscript></div>
			<div class="ebcr-2fa-manual">
				<p><strong><?php esc_html_e( 'Segredo (cadastro manual):', 'eb-credito-rural' ); ?></strong><br>
				<code class="ebcr-2fa-secret" id="ebcr-2fa-secret"><?php echo esc_html( $secret_pretty ); ?></code>
				<button type="button" class="button button-small" data-ebcr-copy="ebcr-2fa-secret" data-ebcr-copy-text="<?php echo esc_attr( $secret ); ?>"><?php esc_html_e( 'Copiar', 'eb-credito-rural' ); ?></button></p>
				<p class="description"><?php esc_html_e( 'Tipo: baseado em tempo (TOTP), SHA1, 6 dígitos, 30 segundos.', 'eb-credito-rural' ); ?></p>
				<p><strong><?php esc_html_e( 'URI otpauth:', 'eb-credito-rural' ); ?></strong><br>
				<input type="text" readonly class="regular-text code ebcr-2fa-uri" id="ebcr-2fa-uri" value="<?php echo esc_attr( $otpauth ); ?>">
				<button type="button" class="button button-small" data-ebcr-copy="ebcr-2fa-uri"><?php esc_html_e( 'Copiar', 'eb-credito-rural' ); ?></button></p>
				<?php if ( ! $crypto_ok ) : ?>
					<p class="ebcr-2fa-warn"><?php esc_html_e( 'Aviso: a chave EBCR_ENCRYPTION_KEY não está definida no wp-config.php, então o segredo será guardado sem criptografia no banco de dados. Defina a chave (Configurações → Segurança) para protegê-lo.', 'eb-credito-rural' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<p>
			<label for="ebcr-2fa-code-totp"><strong><?php esc_html_e( 'Código do aplicativo', 'eb-credito-rural' ); ?></strong></label><br>
			<input type="text" id="ebcr-2fa-code-totp" name="ebcr_2fa[code_totp]" class="regular-text ebcr-2fa-code-input" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" placeholder="000000">
		</p>
		<p class="description"><?php esc_html_e( 'A ativação só acontece com um código válido. Depois, você receberá 10 códigos de backup para guardar.', 'eb-credito-rural' ); ?></p>
	</td>
</tr>
<tr id="ebcr-2fa-panel-email" class="ebcr-2fa-panel">
	<th scope="row"><?php esc_html_e( 'Código por e-mail', 'eb-credito-rural' ); ?></th>
	<td>
		<p>
		<?php
		/* translators: %s: e-mail mascarado */
		printf( esc_html__( 'Os códigos serão enviados para %s. Para ativar, peça um código de ativação, digite-o abaixo e clique em "Atualizar perfil".', 'eb-credito-rural' ), '<strong>' . esc_html( $email_masked ) . '</strong>' );
		?>
		</p>
		<p><a class="button" href="<?php echo esc_url( $send_url ); ?>"><?php esc_html_e( 'Enviar código de ativação por e-mail', 'eb-credito-rural' ); ?></a></p>
		<p>
			<label for="ebcr-2fa-code-email"><strong><?php esc_html_e( 'Código recebido', 'eb-credito-rural' ); ?></strong></label><br>
			<input type="text" id="ebcr-2fa-code-email" name="ebcr_2fa[code_email]" class="regular-text ebcr-2fa-code-input" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" placeholder="000000">
		</p>
		<p class="description"><?php esc_html_e( 'Se o e-mail não chegar, verifique o spam e se o SMTP do site está configurado (Configurações → E-mails → Enviar e-mail de teste).', 'eb-credito-rural' ); ?></p>
	</td>
</tr>
<?php else : ?>
<tr>
	<th scope="row"><?php esc_html_e( 'Dispositivos confiáveis', 'eb-credito-rural' ); ?></th>
	<td>
		<?php if ( $trusted ) : ?>
			<table class="widefat striped ebcr-2fa-devices">
				<thead><tr><th><?php esc_html_e( 'Revogar', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Confiado em', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Expira em', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Último uso', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Navegador / IP', 'eb-credito-rural' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $trusted as $ebcr_d ) : ?>
					<tr>
						<td><input type="checkbox" name="ebcr_2fa[revoke][]" value="<?php echo esc_attr( $ebcr_d['id'] ); ?>" aria-label="<?php esc_attr_e( 'Revogar este dispositivo', 'eb-credito-rural' ); ?>"></td>
						<td><?php echo esc_html( TwoFactorProfile::date( (int) $ebcr_d['created'] ) ); ?></td>
						<td><?php echo esc_html( TwoFactorProfile::date( (int) $ebcr_d['expires'] ) ); ?></td>
						<td><?php echo esc_html( TwoFactorProfile::date( (int) $ebcr_d['last_used'] ) ); ?></td>
						<td><span class="ebcr-2fa-ua"><?php echo esc_html( $ebcr_d['ua'] ? $ebcr_d['ua'] : '—' ); ?></span><br><code><?php echo esc_html( $ebcr_d['ip'] ); ?></code></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p><label><input type="checkbox" name="ebcr_2fa[revoke_all]" value="1"> <?php esc_html_e( 'Revogar todos os dispositivos confiáveis', 'eb-credito-rural' ); ?></label></p>
			<p class="description"><?php esc_html_e( 'Marque e clique em "Atualizar perfil". O navegador revogado voltará a pedir o código no próximo login.', 'eb-credito-rural' ); ?></p>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'Nenhum dispositivo confiável. Ao verificar o código, marque "Confiar neste dispositivo por 30 dias" para não repetir a verificação neste navegador.', 'eb-credito-rural' ); ?></p>
		<?php endif; ?>
	</td>
</tr>
<tr>
	<th scope="row"><?php esc_html_e( 'Códigos de backup', 'eb-credito-rural' ); ?></th>
	<td>
		<label><input type="checkbox" name="ebcr_2fa[regenerate]" value="1" data-ebcr-needs-confirm> <?php esc_html_e( 'Gerar 10 novos códigos de backup (os atuais deixam de valer)', 'eb-credito-rural' ); ?></label>
	</td>
</tr>
<tr>
	<th scope="row"><?php esc_html_e( 'Desativar', 'eb-credito-rural' ); ?></th>
	<td>
		<label><input type="checkbox" name="ebcr_2fa[disable]" value="1" data-ebcr-needs-confirm> <?php esc_html_e( 'Desativar a verificação em duas etapas desta conta', 'eb-credito-rural' ); ?></label>
		<?php if ( 'required' === $mode ) : ?>
			<p class="description"><?php esc_html_e( 'Atenção: o 2FA é obrigatório para a equipe. Ao desativar, você precisará configurar de novo antes de usar o painel.', 'eb-credito-rural' ); ?></p>
		<?php endif; ?>
	</td>
</tr>
<tr id="ebcr-2fa-confirm-row">
	<th scope="row"><label for="ebcr-2fa-confirm"><?php esc_html_e( 'Confirmação', 'eb-credito-rural' ); ?></label></th>
	<td>
		<input type="password" id="ebcr-2fa-confirm" name="ebcr_2fa[confirm]" class="regular-text" autocomplete="off">
		<p class="description"><?php esc_html_e( 'Para desativar ou gerar novos códigos: digite sua senha atual, um código do aplicativo ou um código de backup, e clique em "Atualizar perfil".', 'eb-credito-rural' ); ?></p>
	</td>
</tr>
<?php endif; ?>
</table>
</div>
