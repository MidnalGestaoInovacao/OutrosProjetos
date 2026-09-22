<?php
/**
 * Funções globais (prefixo ebcr_) usadas pelos templates. Todas devolvem HTML já escapado pelos helpers.
 *
 * @package EBCR
 */

defined( 'ABSPATH' ) || exit;

/**
 * Campo de texto.
 *
 * @param string $name  Nome.
 * @param string $label Rótulo.
 * @param mixed  $value Valor.
 * @param array  $attrs Atributos.
 * @param string $error Erro.
 * @param string $help  Ajuda.
 * @return string
 */
function ebcr_input( $name, $label, $value = '', array $attrs = array(), $error = '', $help = '' ) {
	return \EBCR\Frontend\Fields::input( $name, $label, $value, $attrs, $error, $help );
}

/**
 * Textarea.
 *
 * @param string $name  Nome.
 * @param string $label Rótulo.
 * @param mixed  $value Valor.
 * @param array  $attrs Atributos.
 * @param string $error Erro.
 * @param string $help  Ajuda.
 * @return string
 */
function ebcr_textarea( $name, $label, $value = '', array $attrs = array(), $error = '', $help = '' ) {
	return \EBCR\Frontend\Fields::textarea( $name, $label, $value, $attrs, $error, $help );
}

/**
 * Select.
 *
 * @param string $name    Nome.
 * @param string $label   Rótulo.
 * @param array  $options Opções.
 * @param mixed  $value   Valor.
 * @param array  $attrs   Atributos.
 * @param string $error   Erro.
 * @param string $help    Ajuda.
 * @return string
 */
function ebcr_select( $name, $label, array $options, $value = '', array $attrs = array(), $error = '', $help = '' ) {
	return \EBCR\Frontend\Fields::select( $name, $label, $options, $value, $attrs, $error, $help );
}

/**
 * Grupo de rádio.
 *
 * @param string $name     Nome.
 * @param string $label    Rótulo.
 * @param array  $options  Opções.
 * @param mixed  $value    Valor.
 * @param string $error    Erro.
 * @param string $help     Ajuda.
 * @param bool   $required Obrigatório.
 * @return string
 */
function ebcr_radios( $name, $label, array $options, $value = '', $error = '', $help = '', $required = true ) {
	return \EBCR\Frontend\Fields::radios( $name, $label, $options, $value, $error, $help, $required );
}

/**
 * Checkboxes múltiplos.
 *
 * @param string $name    Nome.
 * @param string $label   Rótulo.
 * @param array  $options Opções.
 * @param array  $values  Marcados.
 * @param string $error   Erro.
 * @param string $help    Ajuda.
 * @return string
 */
function ebcr_checkboxes( $name, $label, array $options, $values = array(), $error = '', $help = '' ) {
	return \EBCR\Frontend\Fields::checkboxes( $name, $label, $options, $values, $error, $help );
}

/**
 * Checkbox de aceite.
 *
 * @param string $name     Nome.
 * @param string $label    Rótulo (HTML restrito).
 * @param bool   $required Obrigatório.
 * @param string $error    Erro.
 * @return string
 */
function ebcr_consent( $name, $label, $required = true, $error = '' ) {
	return \EBCR\Frontend\Fields::consent( $name, $label, $required, $error );
}

/**
 * Resumo de erros.
 *
 * @param array $errors Erros.
 * @return string
 */
function ebcr_error_summary( array $errors ) {
	return \EBCR\Frontend\Fields::error_summary( $errors );
}

/**
 * Campo de nonce.
 *
 * @param string $action Ação.
 * @return string
 */
function ebcr_nonce_field( $action ) {
	return \EBCR\Security\Nonces::field( $action );
}

/**
 * Campos anti-bot.
 *
 * @return string
 */
function ebcr_honeypot_fields() {
	return \EBCR\Security\Honeypot::fields();
}

/**
 * Badge de status.
 *
 * @param string $status Status.
 * @return string
 */
function ebcr_status_badge( $status ) {
	return \EBCR\Domain\Status::badge( $status );
}

/**
 * Campo de captcha do provedor.
 *
 * @param \EBCR\Security\CaptchaProvider $provider Provedor.
 * @return string
 */
function ebcr_captcha_field( \EBCR\Security\CaptchaProvider $provider ) {
	return $provider->field();
}
