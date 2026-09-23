<?php
/**
 * Consultas automáticas de CEP (ViaCEP/BrasilAPI) e CNPJ (BrasilAPI) via REST do plugin, com cache. Entregue pelo módulo "Integrações".
 *
 * @package EBCR
 */

namespace EBCR\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Rotas ebcr/v1/lookup/cep/{cep} e ebcr/v1/lookup/cnpj/{cnpj}.
 */
final class Lookup {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {}
}
