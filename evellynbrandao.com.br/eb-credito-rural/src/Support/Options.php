<?php
/**
 * Configurações do plugin (opção única `ebcr_settings`) com valores padrão e textos de ajuda.
 *
 * @package EBCR
 */

namespace EBCR\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Acesso centralizado às configurações.
 */
final class Options {

	const OPTION = 'ebcr_settings';

	/**
	 * Cache em memória.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Valores padrão de todas as configurações, agrupados por aba.
	 *
	 * @return array
	 */
	public static function defaults() {
		$admin_email = get_option( 'admin_email' );
		return array(
			// Geral.
			'operation_name'             => 'Crédito Rural — Évellyn Brandão',
			'admin_emails'               => self::default_contact_email( $admin_email ),
			'notify_assigned_analyst'    => true,
			'portal_page_id'             => 0,
			'protocol_prefix'            => 'EB',
			'email_logo_url'             => '',
			'admin_branding'             => true,
			'brand_logo_url'             => '',
			'brand_icon_url'             => '',
			// Formulário.
			'min_amount'                 => 50000,
			'max_amount'                 => 50000000,
			'min_term_months'            => 6,
			'max_term_months'            => 180,
			'max_area_ha'                => 500000,
			'activities'                 => "graos|Grãos (soja, milho, trigo, etc.)\ncafe|Café\ncana|Cana-de-açúcar\nfruticultura|Fruticultura\nhortalicas|Hortaliças\npecuaria_corte|Pecuária de corte\npecuaria_leite|Pecuária de leite\nflorestal|Silvicultura / florestal\noutras|Outras",
			'purposes'                   => "custeio|Custeio\ninvestimento|Investimento\ncomercializacao|Comercialização\ncapital_giro|Capital de giro",
			'step_help'                  => array(),
			'enable_optional_fields'     => true,
			// Documentos e uploads.
			'allowed_extensions'         => 'pdf,jpg,jpeg,png',
			'max_file_size_mb'           => 10,
			'max_files_per_submission'   => 80,
			'user_quota_mb'              => 200,
			'strip_exif'                 => true,
			'antivirus_enabled'          => false,
			'antivirus_command'          => 'clamdscan --no-summary --fdpass',
			'document_matrix'            => \EBCR\Forms\DocumentMatrix::defaults(),
			// Regras de submissão.
			'min_interval_days'          => 30,
			'block_if_in_progress'       => true,
			'pending_reminder_days'      => 7,
			'pending_cancel_days'        => 0,
			'allow_duplicate_previous'   => true,
			// Segurança.
			'storage_path'               => '',
			'encrypt_files'              => false,
			'encrypt_fields'             => false,
			'login_max_attempts'         => 5,
			'login_window_minutes'       => 15,
			'captcha_provider'           => 'math',
			'captcha_difficulty'         => 'normal',
			'captcha_on_login'           => true,
			'honeypot'                   => true,
			'min_fill_seconds'           => 3,
			'team_session_hours'         => 8,
			'analyst_only_assigned'      => false,
			'require_https'              => true,
			'password_min_length'        => 10,
			'trusted_proxy_header'       => '',
			// E-mails.
			'from_name'                  => get_bloginfo( 'name' ) . ' — Crédito Rural',
			'from_email'                 => self::default_contact_email( $admin_email ),
			'email_templates'            => array(),
			'attach_documents_admin'     => false,
			'notify_admin_status_change' => true,
			// Privacidade e compliance.
			'policies'                   => \EBCR\Domain\Consent::default_policies(),
			'retention_months_rejected'  => 24,
			'retention_months_approved'  => 120,
			'dpo_name'                   => 'Sanclé Albuquerque',
			'dpo_email'                  => 'dpo@' . self::site_domain(),
			'audit_retention_days'       => 730,
			// Status.
			'statuses'                   => \EBCR\Domain\Status::defaults(),
			// Desinstalação.
			'keep_data_on_uninstall'     => true,
			// Painel da equipe no site.
			'team_portal_mode'           => 'both',
			// Fundos / carteiras (relatórios).
			'funds'                      => 'geral|Carteira geral',
			// CRM.
			'crm_stages'                 => "novo|Novo lead\ncontato|Em contato\nproposta|Proposta em andamento\nanalise|Em análise\naprovado|Aprovado\nperdido|Perdido",
			'lead_sources'               => "site|Site\nindicacao|Indicação\nwhatsapp|WhatsApp\nevento|Evento\noutro|Outro",
			'crm_task_reminders'         => true,
			// Segurança da equipe (2FA).
			'team_2fa_mode'              => 'optional',
			// Integrações.
			'cep_lookup'                 => true,
			'cnpj_lookup'                => true,
			'turnstile_site_key'         => '',
			'turnstile_secret_key'       => '',
			'whatsapp_enabled'           => false,
			'whatsapp_token'             => '',
			'whatsapp_phone_id'          => '',
			'whatsapp_template'          => '',
			'whatsapp_notify_client'     => true,
			'whatsapp_notify_team'       => false,
			'whatsapp_team_number'       => '',
			// Assinatura eletrônica.
			'esign_enabled'              => true,
			'esign_doc_types'            => 'autorizacao_scr',
			'esign_scr_text'             => 'Autorizo a consulta das minhas informações no Sistema de Informações de Crédito do Banco Central (SCR) e em birôs de crédito (Serasa, SPC e similares), bem como o registro de dados desta operação nesses sistemas, para fins de análise da solicitação de crédito rural identificada pelo protocolo {protocolo}.',
			// Simulador.
			'simulator_enabled'          => true,
			'simulator_rate'             => 12,
			'simulator_system'           => 'price',
			'simulator_min_amount'       => 50000,
			'simulator_max_amount'       => 50000000,
			'simulator_max_term'         => 180,
			'simulator_grace_months'     => 0,
			'simulator_cta_url'          => '',
			// Interno.
			'sentinel_name'              => '',
			'last_protection_test'       => array(),
		);
	}

	/**
	 * E-mail de contato padrão (contato@dominio), com fallback para o e-mail do administrador.
	 *
	 * @param string $admin_email E-mail do administrador do WordPress.
	 * @return string
	 */
	private static function default_contact_email( $admin_email ) {
		$domain = self::site_domain();
		if ( 'example.com' === $domain || '' === $domain ) {
			return (string) $admin_email;
		}
		return 'contato@' . $domain;
	}

	/**
	 * Domínio do site (para e-mails padrão).
	 *
	 * @return string
	 */
	private static function site_domain() {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		if ( '' === $host || 'localhost' === $host || filter_var( $host, FILTER_VALIDATE_IP ) || false === strpos( $host, '.' ) ) {
			return 'example.com';
		}
		return preg_replace( '/^www\./', '', $host );
	}

	/**
	 * Todas as configurações (padrões mesclados com os salvos).
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
		}
		return self::$cache;
	}

	/**
	 * Uma configuração.
	 *
	 * @param string $key     Chave.
	 * @param mixed  $fallback Padrão se inexistente.
	 * @return mixed
	 */
	public static function get( $key, $fallback = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}

	/**
	 * Booleano.
	 *
	 * @param string $key Chave.
	 * @return bool
	 */
	public static function bool( $key ) {
		return (bool) self::get( $key, false );
	}

	/**
	 * Inteiro.
	 *
	 * @param string $key Chave.
	 * @return int
	 */
	public static function int( $key ) {
		return (int) self::get( $key, 0 );
	}

	/**
	 * Lista "chave|rótulo" por linha => array associativo.
	 *
	 * @param string $key Chave da configuração.
	 * @return array
	 */
	public static function pairs( $key ) {
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) self::get( $key, '' ) ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$parts                            = array_map( 'trim', explode( '|', $line, 2 ) );
			$out[ sanitize_key( $parts[0] ) ] = isset( $parts[1] ) ? $parts[1] : $parts[0];
		}
		return $out;
	}

	/**
	 * Lista de e-mails administrativos válidos.
	 *
	 * @return string[]
	 */
	public static function admin_emails() {
		$raw = preg_split( '/[\s,;]+/', (string) self::get( 'admin_emails', '' ) );
		$out = array();
		foreach ( $raw as $email ) {
			$email = sanitize_email( $email );
			if ( $email && is_email( $email ) ) {
				$out[] = $email;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Extensões permitidas normalizadas.
	 *
	 * @return string[]
	 */
	public static function allowed_extensions() {
		$list  = array_filter( array_map( 'trim', explode( ',', strtolower( (string) self::get( 'allowed_extensions', 'pdf,jpg,jpeg,png' ) ) ) ) );
		$never = array( 'svg', 'html', 'htm', 'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'js', 'zip', 'exe', 'sh', 'bat', 'cmd', 'com', 'msi', 'jar', 'xml', 'svgz', 'phar' );
		return array_values( array_diff( array_map( 'sanitize_key', $list ), $never ) );
	}

	/**
	 * Salva um conjunto de chaves (mescla com o existente).
	 *
	 * @param array $values Pares chave => valor.
	 * @return void
	 */
	public static function update( array $values ) {
		$saved = get_option( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		foreach ( $values as $k => $v ) {
			$saved[ $k ] = $v;
		}
		update_option( self::OPTION, $saved, false );
		self::$cache = null;
	}

	/**
	 * Restaura os padrões de um conjunto de chaves.
	 *
	 * @param string[] $keys Chaves.
	 * @return void
	 */
	public static function reset( array $keys ) {
		$saved = get_option( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		foreach ( $keys as $k ) {
			unset( $saved[ $k ] );
		}
		update_option( self::OPTION, $saved, false );
		self::$cache = null;
	}

	/**
	 * Limpa o cache (testes).
	 *
	 * @return void
	 */
	public static function flush() {
		self::$cache = null;
	}
}
