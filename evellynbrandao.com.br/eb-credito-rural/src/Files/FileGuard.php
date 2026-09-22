<?php
/**
 * Pasta privada de arquivos: localização, proteção (.htaccess/index.php) e teste de acesso direto.
 *
 * @package EBCR
 */

namespace EBCR\Files;

use EBCR\Security\AuditLog;
use EBCR\Support\Helpers;
use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Preferência: caminho configurado fora da pasta pública; fallback: uploads/ebcr-private com bloqueio.
 */
final class FileGuard {

	/**
	 * Diretório base efetivo (com barra final).
	 *
	 * @return string
	 */
	public function base_dir() {
		$custom = trim( (string) Options::get( 'storage_path', '' ) );
		if ( $custom ) {
			$custom = wp_normalize_path( untrailingslashit( $custom ) );
			if ( is_dir( $custom ) && wp_is_writable( $custom ) ) {
				return trailingslashit( $custom );
			}
		}
		return $this->fallback_dir();
	}

	/**
	 * Diretório de fallback dentro de uploads.
	 *
	 * @return string
	 */
	public function fallback_dir() {
		$uploads = wp_upload_dir( null, false );
		return trailingslashit( wp_normalize_path( $uploads['basedir'] ) ) . 'ebcr-private/';
	}

	/**
	 * O diretório efetivo está dentro da pasta pública (uploads)?
	 *
	 * @return bool
	 */
	public function is_inside_public() {
		$base    = $this->base_dir();
		$uploads = trailingslashit( wp_normalize_path( wp_upload_dir( null, false )['basedir'] ) );
		$root    = trailingslashit( wp_normalize_path( ABSPATH ) );
		return 0 === strpos( $base, $uploads ) || 0 === strpos( $base, $root );
	}

	/**
	 * Estado do caminho configurado.
	 *
	 * @return array{path:string,exists:bool,writable:bool,used:string,inside_public:bool}
	 */
	public function status() {
		$custom = trim( (string) Options::get( 'storage_path', '' ) );
		return array(
			'path'          => $custom,
			'exists'        => $custom ? is_dir( $custom ) : false,
			'writable'      => $custom ? ( is_dir( $custom ) && wp_is_writable( $custom ) ) : false,
			'used'          => $this->base_dir(),
			'inside_public' => $this->is_inside_public(),
		);
	}

	/**
	 * Garante que o diretório base exista com os arquivos de proteção.
	 *
	 * @return bool
	 */
	public function ensure_base_dir() {
		$base = $this->base_dir();
		if ( ! is_dir( $base ) ) {
			wp_mkdir_p( $base );
		}
		if ( ! is_dir( $base ) ) {
			return false;
		}
		$this->protect_dir( $base );
		$this->ensure_sentinel( $base );
		return true;
	}

	/**
	 * Escreve .htaccess, web.config e index.php em um diretório.
	 *
	 * @param string $dir Diretório.
	 * @return void
	 */
	public function protect_dir( $dir ) {
		$dir = trailingslashit( $dir );
		$ht  = "# EB Crédito Rural — arquivos privados: acesso somente pelo controlador PHP autenticado.\n"
			. "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
			. "<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\n"
			. "Options -Indexes\n";
		if ( ! file_exists( $dir . '.htaccess' ) || file_get_contents( $dir . '.htaccess' ) !== $ht ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- arquivo local do plugin.
			file_put_contents( $dir . '.htaccess', $ht ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- arquivo local de proteção.
		}
		if ( ! file_exists( $dir . 'index.php' ) ) {
			file_put_contents( $dir . 'index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- idem.
		}
		if ( ! file_exists( $dir . 'web.config' ) ) {
			file_put_contents( $dir . 'web.config', "<?xml version=\"1.0\"?>\n<configuration><system.webServer><security><authorization><remove users=\"*\" roles=\"\" verbs=\"\" /><add accessType=\"Deny\" users=\"*\" /></authorization></security></system.webServer></configuration>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- idem.
		}
	}

	/**
	 * Cria o arquivo sentinela usado no teste de acesso direto.
	 *
	 * @param string $base Diretório base.
	 * @return string Nome do sentinela.
	 */
	public function ensure_sentinel( $base ) {
		$name = (string) Options::get( 'sentinel_name', '' );
		if ( ! $name || ! file_exists( $base . $name ) ) {
			$name = 'sentinel-' . Helpers::random_hex( 12 ) . '.txt';
			file_put_contents( $base . $name, 'EBCR-SENTINEL ' . wp_hash( $name ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- arquivo de teste.
			Options::update( array( 'sentinel_name' => $name ) );
		}
		return $name;
	}

	/**
	 * URL pública que o sentinela teria se a pasta estivesse exposta (só faz sentido dentro de uploads).
	 *
	 * @return string
	 */
	public function sentinel_url() {
		$base    = $this->base_dir();
		$uploads = wp_upload_dir( null, false );
		$basedir = trailingslashit( wp_normalize_path( $uploads['basedir'] ) );
		$name    = $this->ensure_sentinel( $base );
		if ( 0 === strpos( $base, $basedir ) ) {
			return trailingslashit( $uploads['baseurl'] ) . substr( $base, strlen( $basedir ) ) . $name;
		}
		$root = trailingslashit( wp_normalize_path( ABSPATH ) );
		if ( 0 === strpos( $base, $root ) ) {
			return home_url( '/' . substr( $base, strlen( $root ) ) . $name );
		}
		return '';
	}

	/**
	 * Testa se o sentinela é baixável por URL direta.
	 *
	 * @return array{result:string,code:int,url:string,tested_at:string,message:string}
	 */
	public function test_protection() {
		$this->ensure_base_dir();
		$url = $this->sentinel_url();
		$out = array(
			'url'       => $url,
			'tested_at' => current_time( 'mysql', true ),
			'code'      => 0,
		);
		if ( '' === $url ) {
			$out['result']  = 'external';
			$out['message'] = __( 'A pasta está fora da raiz pública do site: não há URL direta para os arquivos. Configuração recomendada.', 'eb-credito-rural' );
		} else {
			$resp = wp_remote_get(
				add_query_arg( 'ebcr_t', time(), $url ),
				array(
					'timeout'     => 15,
					'redirection' => 0,
					'sslverify'   => apply_filters( 'ebcr_protection_test_sslverify', true ),
					'headers'     => array( 'Cache-Control' => 'no-cache' ),
				)
			);
			if ( is_wp_error( $resp ) ) {
				$out['result']  = 'unknown';
				$out['message'] = sprintf( /* translators: %s: erro */ __( 'Não foi possível testar (%s). Verifique manualmente abrindo a URL do sentinela no navegador: deve retornar 403 ou 404.', 'eb-credito-rural' ), $resp->get_error_message() );
			} else {
				$code        = (int) wp_remote_retrieve_response_code( $resp );
				$body        = (string) wp_remote_retrieve_body( $resp );
				$out['code'] = $code;
				if ( 200 === $code && false !== strpos( $body, 'EBCR-SENTINEL' ) ) {
					$out['result']  = 'exposed';
					$out['message'] = __( 'ALERTA: o arquivo sentinela pôde ser baixado por URL direta. O servidor não está respeitando o bloqueio (.htaccess ignorado, típico de Nginx). Aplique o bloco de configuração indicado na Ajuda ou configure um caminho fora da pasta pública.', 'eb-credito-rural' );
				} else {
					$out['result']  = 'protected';
					$out['message'] = sprintf( /* translators: %d: código HTTP */ __( 'Protegido: a URL direta retornou HTTP %d.', 'eb-credito-rural' ), $code );
				}
			}
		}
		Options::update( array( 'last_protection_test' => $out ) );
		AuditLog::log(
			'protection_test',
			'storage',
			'',
			array(
				'result' => $out['result'],
				'code'   => $out['code'],
			)
		);
		return $out;
	}

	/**
	 * Bloco Nginx sugerido.
	 *
	 * @return string
	 */
	public function nginx_snippet() {
		$base    = $this->base_dir();
		$uploads = trailingslashit( wp_normalize_path( wp_upload_dir( null, false )['basedir'] ) );
		$path    = 0 === strpos( $base, $uploads ) ? '/wp-content/uploads/' . substr( $base, strlen( $uploads ) ) : '/wp-content/uploads/ebcr-private/';
		return "location ^~ {$path} {\n    deny all;\n    return 403;\n}";
	}
}
