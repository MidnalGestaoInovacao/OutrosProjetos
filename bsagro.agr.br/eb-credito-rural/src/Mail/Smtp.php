<?php
/**
 * Envio autenticado por SMTP (PHPMailer do WordPress), remetente do envelope (Return-Path), registro de falhas e
 * teste com transcrição da conversa SMTP (credenciais mascaradas).
 *
 * Senha: a constante EBCR_SMTP_PASSWORD no wp-config.php tem prioridade e é o recomendado. Sem ela, a senha salva nas
 * configurações fica cifrada (libsodium): com a chave EBCR_ENCRYPTION_KEY, quando definida; sem essa chave, com uma
 * chave derivada de wp_salt('secure_auth'). A segunda forma protege contra vazamento só do banco de dados (o sal fica
 * no wp-config.php), mas não contra quem lê o wp-config.php — por isso a tela recomenda a constante.
 * A senha nunca é devolvida por telas, abilities, auditoria ou transcrições.
 *
 * @package EBCR
 */

namespace EBCR\Mail;

use EBCR\Security\Crypto;
use EBCR\Support\Options;

// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode,WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- base64 só serializa a cifra e mascara credenciais na transcrição.
// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- propriedades do PHPMailer.

defined( 'ABSPATH' ) || exit;

/**
 * Configuração do PHPMailer (hook phpmailer_init) e diagnóstico.
 */
final class Smtp {

	const PASSWORD_CONSTANT = 'EBCR_SMTP_PASSWORD';
	const FAILURES_OPTION   = 'ebcr_mail_failures';
	const MAX_FAILURES      = 20;
	const SALT_PREFIX       = 'ebcr1s:';
	const TRANSCRIPT_MAX    = 4000;
	const MASK              = '[credencial ocultada]';

	/**
	 * Profundidade de envios do próprio plugin (fila, teste): > 0 durante wp_mail() do plugin.
	 *
	 * @var int
	 */
	private static $own = 0;

	/**
	 * Linhas da transcrição durante o teste (null fora do teste).
	 *
	 * @var string[]|null
	 */
	private static $debug = null;

	/**
	 * Estado da autenticação na transcrição (linhas do cliente depois de AUTH são mascaradas até 235/5xx).
	 *
	 * @var bool
	 */
	private static $in_auth = false;

	/**
	 * Remetente de envelope definido pelo plugin na última mensagem (para não vazar para mensagens de terceiros).
	 *
	 * @var string
	 */
	private static $sender = '';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'phpmailer_init', array( __CLASS__, 'configure' ), 20 );
		add_action( 'wp_mail_failed', array( __CLASS__, 'record_failure' ) );
		add_filter( 'wp_mail_from', array( __CLASS__, 'filter_from' ), 20 );
		add_filter( 'wp_mail_from_name', array( __CLASS__, 'filter_from_name' ), 20 );
	}

	/**
	 * SMTP ligado e com servidor informado?
	 *
	 * @return bool
	 */
	public static function enabled() {
		return Options::bool( 'smtp_enabled' ) && '' !== self::host();
	}

	/**
	 * Servidor.
	 *
	 * @return string
	 */
	public static function host() {
		return trim( (string) Options::get( 'smtp_host', '' ) );
	}

	/**
	 * Porta (1–65535; padrão 465).
	 *
	 * @return int
	 */
	public static function port() {
		$p = Options::int( 'smtp_port' );
		return $p > 0 && $p < 65536 ? $p : 465;
	}

	/**
	 * Criptografia: ssl | tls | none.
	 *
	 * @return string
	 */
	public static function secure() {
		$s = (string) Options::get( 'smtp_secure', 'ssl' );
		return in_array( $s, array( 'ssl', 'tls', 'none' ), true ) ? $s : 'ssl';
	}

	/**
	 * E-mail do remetente do SMTP (vazio = "E-mail do remetente" do plugin).
	 *
	 * @return string
	 */
	public static function from_email() {
		$e = sanitize_email( (string) Options::get( 'smtp_from_email', '' ) );
		if ( ! $e || ! is_email( $e ) ) {
			$e = sanitize_email( (string) Options::get( 'from_email', '' ) );
		}
		return $e && is_email( $e ) ? $e : '';
	}

	/**
	 * Nome do remetente do SMTP (vazio = "Nome do remetente" do plugin).
	 *
	 * @return string
	 */
	public static function from_name() {
		$n = trim( (string) Options::get( 'smtp_from_name', '' ) );
		if ( '' === $n ) {
			$n = trim( (string) Options::get( 'from_name', '' ) );
		}
		return str_replace( array( "\r", "\n", '<', '>', '"' ), '', $n );
	}

	/**
	 * Origem da senha: constant (wp-config) | option (salva cifrada) | none.
	 *
	 * @return string
	 */
	public static function password_source() {
		if ( defined( self::PASSWORD_CONSTANT ) && '' !== (string) constant( self::PASSWORD_CONSTANT ) ) {
			return 'constant';
		}
		return '' !== (string) Options::get( 'smtp_password', '' ) ? 'option' : 'none';
	}

	/**
	 * Cifra da senha salva: "key" (EBCR_ENCRYPTION_KEY), "salt" (derivada do wp_salt) ou "" (sem senha salva).
	 *
	 * @return string
	 */
	public static function password_storage() {
		$v = (string) Options::get( 'smtp_password', '' );
		if ( '' === $v ) {
			return '';
		}
		return 0 === strpos( $v, self::SALT_PREFIX ) ? 'salt' : 'key';
	}

	/**
	 * Senha em claro (só para o PHPMailer; nunca exibir nem registrar).
	 *
	 * @return string
	 */
	private static function password() {
		if ( 'constant' === self::password_source() ) {
			return (string) constant( self::PASSWORD_CONSTANT );
		}
		$plain = self::open( (string) Options::get( 'smtp_password', '' ) );
		return null === $plain ? '' : $plain;
	}

	/**
	 * Chave derivada do sal do WordPress (quando não há EBCR_ENCRYPTION_KEY).
	 *
	 * @return string
	 */
	private static function salt_key() {
		return sodium_crypto_generichash( 'ebcr-smtp|' . wp_salt( 'secure_auth' ), '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}

	/**
	 * Cifra a senha para gravar na opção.
	 *
	 * @param string $plain Senha.
	 * @return string
	 */
	public static function seal( $plain ) {
		$plain = (string) $plain;
		if ( '' === $plain ) {
			return '';
		}
		if ( Crypto::is_available() ) {
			return Crypto::encrypt( $plain );
		}
		$key   = self::salt_key();
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$out   = self::SALT_PREFIX . base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, $key ) );
		sodium_memzero( $key );
		return $out;
	}

	/**
	 * Decifra a senha salva (null se ilegível, ex.: chave/sal trocados).
	 *
	 * @param string $payload Valor salvo.
	 * @return string|null
	 */
	public static function open( $payload ) {
		$payload = (string) $payload;
		if ( '' === $payload ) {
			return null;
		}
		if ( 0 !== strpos( $payload, self::SALT_PREFIX ) ) {
			return Crypto::decrypt( $payload );
		}
		$bin = base64_decode( substr( $payload, strlen( self::SALT_PREFIX ) ), true );
		if ( false === $bin || strlen( $bin ) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES ) {
			return null;
		}
		$key   = self::salt_key();
		$plain = sodium_crypto_secretbox_open( substr( $bin, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), substr( $bin, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $key );
		sodium_memzero( $key );
		return false === $plain ? null : $plain;
	}

	/**
	 * Executa um envio do próprio plugin (as regras "só mensagens do plugin" valem dentro do callback).
	 *
	 * @param callable $send Função que chama wp_mail().
	 * @return mixed Retorno do callback.
	 */
	public static function own( callable $send ) {
		++self::$own;
		try {
			return $send();
		} finally {
			--self::$own;
		}
	}

	/**
	 * Configura o PHPMailer (phpmailer_init).
	 *
	 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer Instância.
	 * @return void
	 */
	public static function configure( $phpmailer ) {
		$own = self::$own > 0;
		if ( null !== self::$debug ) {
			$phpmailer->SMTPDebug   = 2;
			$phpmailer->Debugoutput = array( __CLASS__, 'debug_line' );
		} elseif ( $own || self::enabled() ) {
			$phpmailer->SMTPDebug = 0;
		}
		if ( self::enabled() && ( $own || Options::bool( 'smtp_apply_all' ) ) ) {
			$secure = self::secure();
			$phpmailer->isSMTP();
			$phpmailer->Host        = self::host();
			$phpmailer->Port        = self::port();
			$phpmailer->SMTPSecure  = 'none' === $secure ? '' : $secure;
			$phpmailer->SMTPAutoTLS = 'none' !== $secure;
			$phpmailer->SMTPAuth    = Options::bool( 'smtp_auth' );
			$phpmailer->Username    = $phpmailer->SMTPAuth ? (string) Options::get( 'smtp_username', '' ) : '';
			$phpmailer->Password    = $phpmailer->SMTPAuth ? self::password() : '';
			$phpmailer->Timeout     = max( 5, min( 120, Options::int( 'smtp_timeout' ) ) );
			$phpmailer->SMTPOptions = Options::bool( 'smtp_verify_peer' ) ? array() : array(
				'ssl' => array(
					'verify_peer'       => false,
					'verify_peer_name'  => false,
					'allow_self_signed' => true,
				),
			);
			$from = self::from_email();
			if ( $from ) {
				$phpmailer->setFrom( $from, self::from_name(), false );
				$phpmailer->Sender = $from;
				self::$sender      = $from;
			}
			return;
		}
		if ( $own ) {
			// Sem SMTP: define ao menos o remetente do envelope (Return-Path) das mensagens do plugin (alinhamento SPF).
			$from = sanitize_email( (string) Options::get( 'from_email', '' ) );
			if ( $from && is_email( $from ) ) {
				$phpmailer->Sender = $from;
				self::$sender      = $from;
			}
			return;
		}
		if ( '' !== self::$sender && $phpmailer->Sender === self::$sender ) {
			$phpmailer->Sender = ''; // não herda o envelope da mensagem anterior do plugin.
		}
		self::$sender = '';
	}

	/**
	 * Remetente das mensagens do WordPress (e de outros plugins) quando o SMTP vale para todo o site.
	 *
	 * @param string $email E-mail.
	 * @return string
	 */
	public static function filter_from( $email ) {
		if ( self::enabled() && Options::bool( 'smtp_apply_all' ) && self::from_email() ) {
			return self::from_email();
		}
		return $email;
	}

	/**
	 * Nome do remetente quando o SMTP vale para todo o site.
	 *
	 * @param string $name Nome.
	 * @return string
	 */
	public static function filter_from_name( $name ) {
		if ( self::enabled() && Options::bool( 'smtp_apply_all' ) && '' !== self::from_name() ) {
			return self::from_name();
		}
		return $name;
	}

	/**
	 * Mascara credenciais em uma linha de diagnóstico/erro.
	 *
	 * @param string $line Linha.
	 * @return string
	 */
	public static function mask( $line ) {
		$line   = (string) $line;
		$user   = (string) Options::get( 'smtp_username', '' );
		$pass   = self::password();
		$needle = array();
		if ( strlen( $pass ) >= 3 ) {
			$needle[] = $pass;
			$needle[] = base64_encode( $pass );
		}
		if ( strlen( $user ) >= 3 ) {
			$needle[] = base64_encode( $user ); // o usuário em claro não é segredo; só a forma usada no AUTH LOGIN.
		}
		if ( strlen( $pass ) >= 1 ) {
			$needle[] = base64_encode( "\0" . $user . "\0" . $pass );
		}
		foreach ( $needle as $n ) {
			$line = str_replace( $n, self::MASK, $line );
		}
		return preg_replace( '/^(\s*CLIENT -> SERVER:\s*AUTH\s+(?:PLAIN|LOGIN|CRAM-MD5|XOAUTH2)\s+)\S+/i', '$1' . self::MASK, $line );
	}

	/**
	 * Saída de diagnóstico do PHPMailer (SMTPDebug) durante o teste: linhas do cliente depois de AUTH são ocultadas
	 * até o servidor aceitar (235) ou recusar (4xx/5xx) a autenticação.
	 *
	 * @param string $str   Linha.
	 * @param int    $level Nível.
	 * @return void
	 */
	public static function debug_line( $str, $level = 0 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- assinatura do PHPMailer.
		if ( null === self::$debug ) {
			return;
		}
		$str = rtrim( (string) $str );
		if ( preg_match( '/^\s*CLIENT -> SERVER:\s*AUTH\b/i', $str ) ) {
			self::$in_auth = true;
			$str           = self::mask( $str );
		} elseif ( self::$in_auth && preg_match( '/^\s*CLIENT -> SERVER:/i', $str ) ) {
			$str = preg_replace( '/^(\s*CLIENT -> SERVER:).*$/i', '$1 ' . self::MASK, $str );
		} elseif ( self::$in_auth && preg_match( '/^\s*SERVER -> CLIENT:\s*(235|[45]\d\d)/i', $str ) ) {
			self::$in_auth = false;
		}
		self::$debug[] = self::mask( $str );
	}

	/**
	 * Registra falhas do wp_mail (qualquer origem): data, mensagem (sem credenciais), transporte e só o domínio dos
	 * destinatários.
	 *
	 * @param \WP_Error $error Erro.
	 * @return void
	 */
	public static function record_failure( $error ) {
		if ( ! $error instanceof \WP_Error ) {
			return;
		}
		$data    = (array) $error->get_error_data();
		$domains = array();
		foreach ( isset( $data['to'] ) ? (array) $data['to'] : array() as $to ) {
			if ( preg_match( '/@([A-Za-z0-9.-]+)/', (string) $to, $m ) ) {
				$domains[] = strtolower( $m[1] );
			}
		}
		$list   = self::failures();
		$list[] = array(
			'time'      => current_time( 'mysql', true ),
			'message'   => mb_substr( self::mask( wp_strip_all_tags( $error->get_error_message() ) ), 0, 300 ),
			'domains'   => array_values( array_unique( $domains ) ),
			'transport' => self::enabled() && ( self::$own > 0 || Options::bool( 'smtp_apply_all' ) ) ? 'smtp' : 'mail',
		);
		update_option( self::FAILURES_OPTION, array_slice( $list, -self::MAX_FAILURES ), false );
	}

	/**
	 * Últimas falhas registradas (mais antigas primeiro).
	 *
	 * @return array
	 */
	public static function failures() {
		$list = get_option( self::FAILURES_OPTION, array() );
		return is_array( $list ) ? array_values( $list ) : array();
	}

	/**
	 * Estado para get-status e para a tela (sem senha).
	 *
	 * @return array
	 */
	public static function status() {
		return array(
			'enabled'          => self::enabled(),
			'configured'       => Options::bool( 'smtp_enabled' ),
			'host'             => self::host(),
			'port'             => self::port(),
			'secure'           => self::secure(),
			'auth'             => Options::bool( 'smtp_auth' ),
			'username'         => (string) Options::get( 'smtp_username', '' ),
			'password_source'  => self::password_source(),
			'password_storage' => self::password_storage(),
			'password_readable' => 'option' !== self::password_source() || null !== self::open( (string) Options::get( 'smtp_password', '' ) ),
			'from_email'       => self::from_email(),
			'from_name'        => self::from_name(),
			'apply_all'        => Options::bool( 'smtp_apply_all' ),
			'verify_peer'      => Options::bool( 'smtp_verify_peer' ),
			'timeout'          => max( 5, min( 120, Options::int( 'smtp_timeout' ) ) ),
		);
	}

	/**
	 * Envio de teste com transcrição (SMTPDebug=2, credenciais mascaradas, até ~4000 caracteres).
	 * Funciona com o SMTP ligado ou desligado (informa o transporte usado).
	 *
	 * @param string $to Destinatário.
	 * @return array|\WP_Error {sent, error, transport, host, port, secure, from, sender, transcript}
	 */
	public static function test( $to ) {
		$to = sanitize_email( (string) $to );
		if ( ! $to || ! is_email( $to ) ) {
			return new \WP_Error( 'bad_to', __( 'Informe um destinatário válido em "to".', 'eb-credito-rural' ) );
		}
		$error         = '';
		$catch         = static function ( $e ) use ( &$error ) {
			if ( $e instanceof \WP_Error ) {
				$error = self::mask( wp_strip_all_tags( $e->get_error_message() ) );
			}
		};
		self::$debug   = array();
		self::$in_auth = false;
		add_action( 'wp_mail_failed', $catch, 5 );
		$subject = __( 'Teste de envio (SMTP) — EB Crédito Rural', 'eb-credito-rural' );
		$html    = Mailer::layout(
			$subject,
			'<p>' . esc_html__( 'Mensagem de teste do envio de e-mails do site. Se você a recebeu, o envio autenticado está funcionando.', 'eb-credito-rural' ) . '</p>'
		);
		$sent    = (bool) self::own(
			static function () use ( $to, $subject, $html ) {
				return wp_mail( $to, $subject, $html, Mailer::headers() );
			}
		);
		remove_action( 'wp_mail_failed', $catch, 5 );
		$lines       = self::$debug;
		self::$debug = null;
		global $phpmailer;
		$transport = is_object( $phpmailer ) && isset( $phpmailer->Mailer ) ? (string) $phpmailer->Mailer : ( self::enabled() ? 'smtp' : 'mail' );
		$sender    = is_object( $phpmailer ) && isset( $phpmailer->Sender ) ? (string) $phpmailer->Sender : '';
		if ( is_object( $phpmailer ) ) {
			$phpmailer->SMTPDebug = 0;
		}
		$transcript = implode( "\n", (array) $lines );
		if ( strlen( $transcript ) > self::TRANSCRIPT_MAX ) {
			$transcript = substr( $transcript, 0, self::TRANSCRIPT_MAX ) . "\n[…]";
		}
		return array(
			'sent'       => $sent,
			'error'      => $error,
			'transport'  => $transport,
			'host'       => 'smtp' === $transport ? self::host() : '',
			'port'       => 'smtp' === $transport ? self::port() : null,
			'secure'     => 'smtp' === $transport ? self::secure() : '',
			'from'       => 'smtp' === $transport ? self::from_email() : sanitize_email( (string) Options::get( 'from_email', '' ) ),
			'sender'     => $sender,
			'to'         => $to,
			'transcript' => '' !== $transcript ? $transcript : ( 'smtp' === $transport ? '' : __( 'Envio pela função mail() do PHP (SMTP desligado): não há conversa SMTP para mostrar.', 'eb-credito-rural' ) ),
		);
	}
}
