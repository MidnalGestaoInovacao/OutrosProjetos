<?php
/**
 * Assinatura eletrônica simples no portal: texto do documento, confirmação de nome/CPF, código por e-mail, PDF assinado
 * com evidências (IP, data/hora, hash) que entra na lista de documentos. Entregue pelo módulo "Assinatura eletrônica".
 *
 * @package EBCR
 */

namespace EBCR\Esign;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks do portal (etapa 6), REST e admin (evidências).
 */
final class Esign {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {}
}
