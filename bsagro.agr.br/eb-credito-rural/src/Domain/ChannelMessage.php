<?php
/**
 * Canais públicos do site (contato, titular/DPO — LGPD — e integridade/ouvidoria): validação do envio,
 * protocolo, gravação e avisos por e-mail pela fila do plugin (sem serviços de terceiros).
 *
 * @package EBCR
 */

namespace EBCR\Domain;

use EBCR\Admin\Branding;
use EBCR\Database\ChannelMessageRepository;
use EBCR\Mail\Mailer;
use EBCR\Support\Helpers;
use EBCR\Support\Ip;
use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * O IP nunca é gravado em claro (HMAC-SHA256 com wp_salt('auth')); relatos anônimos não guardam hash de IP,
 * user agent, usuário nem nome/e-mail/telefone.
 */
final class ChannelMessage {

	/**
	 * Protocolo: PREFIXO-AAAAMMDD-XXXX.
	 */
	const PROTOCOL_RE = '/^(CT|LGPD|OUV)-\d{8}-[A-Z0-9]{4}$/';

	/**
	 * Tamanho máximo de cada campo (caracteres).
	 */
	const MAX_LEN = 5000;

	/**
	 * Máximo de chaves no objeto "fields".
	 */
	const MAX_KEYS = 20;

	/**
	 * Status de atendimento.
	 */
	const STATUSES = array( 'novo', 'em_andamento', 'concluido' );

	/**
	 * Campos aceitos (os demais são ignorados).
	 */
	const FIELDS = array( 'nome', 'email', 'telefone', 'empresa', 'assunto', 'mensagem', 'relacao', 'direito', 'descricao', 'tipo', 'quando', 'onde', 'envolvidos', 'relato', 'evidencias', 'consentimento', 'declaracao_titular', 'boa_fe' );

	/**
	 * Campos de texto longo (sanitize_textarea_field).
	 */
	const TEXTAREAS = array( 'mensagem', 'descricao', 'relato', 'evidencias', 'envolvidos' );

	/**
	 * Caixas de aceite (normalizadas para "sim" ou vazio).
	 */
	const CHECKBOXES = array( 'consentimento', 'declaracao_titular', 'boa_fe' );

	/**
	 * Campos que identificam o remetente (descartados em relatos anônimos e no apagador de dados).
	 */
	const IDENTIFYING = array( 'nome', 'email', 'telefone', 'empresa' );

	/**
	 * Canais: chave => [rótulo, prefixo do protocolo, campos obrigatórios, opção de e-mail].
	 *
	 * @return array
	 */
	public static function channels() {
		return array(
			'contato'   => array(
				'label'    => __( 'Canal de contato', 'eb-credito-rural' ),
				'prefix'   => 'CT',
				'required' => array( 'nome', 'email', 'assunto', 'mensagem', 'consentimento' ),
				'option'   => 'channel_email_contato',
			),
			'dpo'       => array(
				'label'    => __( 'Portal do Titular (LGPD)', 'eb-credito-rural' ),
				'prefix'   => 'LGPD',
				'required' => array( 'nome', 'email', 'relacao', 'direito', 'descricao', 'declaracao_titular', 'consentimento' ),
				'option'   => 'channel_email_dpo',
			),
			'ouvidoria' => array(
				'label'    => __( 'Canal de Integridade', 'eb-credito-rural' ),
				'prefix'   => 'OUV',
				'required' => array( 'tipo', 'relato', 'boa_fe' ),
				'option'   => 'channel_email_ouvidoria',
			),
		);
	}

	/**
	 * Rótulos dos campos.
	 *
	 * @return array
	 */
	public static function field_labels() {
		return array(
			'nome'               => __( 'Nome', 'eb-credito-rural' ),
			'email'              => __( 'E-mail', 'eb-credito-rural' ),
			'telefone'           => __( 'Telefone', 'eb-credito-rural' ),
			'empresa'            => __( 'Empresa', 'eb-credito-rural' ),
			'assunto'            => __( 'Assunto', 'eb-credito-rural' ),
			'mensagem'           => __( 'Mensagem', 'eb-credito-rural' ),
			'relacao'            => __( 'Relação com a empresa', 'eb-credito-rural' ),
			'direito'            => __( 'Direito solicitado', 'eb-credito-rural' ),
			'descricao'          => __( 'Descrição do pedido', 'eb-credito-rural' ),
			'tipo'               => __( 'Tipo de relato', 'eb-credito-rural' ),
			'quando'             => __( 'Quando ocorreu', 'eb-credito-rural' ),
			'onde'               => __( 'Onde ocorreu', 'eb-credito-rural' ),
			'envolvidos'         => __( 'Pessoas envolvidas', 'eb-credito-rural' ),
			'relato'             => __( 'Relato', 'eb-credito-rural' ),
			'evidencias'         => __( 'Evidências', 'eb-credito-rural' ),
			'consentimento'      => __( 'Consentimento para tratamento dos dados', 'eb-credito-rural' ),
			'declaracao_titular' => __( 'Declaração de titularidade', 'eb-credito-rural' ),
			'boa_fe'             => __( 'Declaração de boa-fé', 'eb-credito-rural' ),
		);
	}

	/**
	 * Rótulos dos status.
	 *
	 * @return array
	 */
	public static function status_labels() {
		return array(
			'novo'         => __( 'Novo', 'eb-credito-rural' ),
			'em_andamento' => __( 'Em andamento', 'eb-credito-rural' ),
			'concluido'    => __( 'Concluído', 'eb-credito-rural' ),
		);
	}

	/**
	 * Valor verdadeiro de caixa/flag (true, 1, "1", "true", "on", "sim", "yes", "aceito").
	 *
	 * @param mixed $v Valor.
	 * @return bool
	 */
	public static function truthy( $v ) {
		if ( is_bool( $v ) ) {
			return $v;
		}
		if ( is_int( $v ) ) {
			return 1 === $v;
		}
		return is_string( $v ) && in_array( strtolower( trim( $v ) ), array( '1', 'true', 'on', 'sim', 'yes', 'aceito', 'aceite' ), true );
	}

	/**
	 * Caminho da página (sem query/fragmento), até 200 caracteres.
	 *
	 * @param mixed $raw Valor.
	 * @return string|null null se inválido.
	 */
	public static function clean_path( $raw ) {
		if ( null === $raw || '' === $raw ) {
			return '/';
		}
		if ( ! is_string( $raw ) || strlen( $raw ) > 2000 ) {
			return null;
		}
		$p = (string) wp_parse_url( $raw, PHP_URL_PATH );
		$p = preg_replace( '/[\x00-\x1F\x7F<>"\'\\\\]/', '', $p );
		return mb_substr( '/' . ltrim( (string) $p, '/' ), 0, 200 );
	}

	/**
	 * Valida e normaliza o envio. Retorna os dados limpos ou WP_Error (mensagem genérica; data.fields = nomes inválidos).
	 *
	 * @param mixed $payload Corpo decodificado.
	 * @return array|\WP_Error
	 */
	public static function validate( $payload ) {
		$generic = __( 'Não foi possível enviar: verifique os campos obrigatórios e tente novamente.', 'eb-credito-rural' );
		if ( ! is_array( $payload ) ) {
			return new \WP_Error( 'ebcr_channel_invalid', $generic, array( 'status' => 400 ) );
		}
		$errors   = array();
		$channels = self::channels();
		$channel  = isset( $payload['channel'] ) && is_string( $payload['channel'] ) ? $payload['channel'] : '';
		if ( ! isset( $channels[ $channel ] ) ) {
			return new \WP_Error(
				'ebcr_channel_invalid',
				$generic,
				array(
					'status' => 400,
					'fields' => array( 'channel' ),
				)
			);
		}
		// Honeypot: campo invisível que robôs preenchem.
		$honey = isset( $payload['_honey'] ) ? $payload['_honey'] : ( isset( $payload['fields']['_honey'] ) ? $payload['fields']['_honey'] : '' );
		if ( '' !== ( is_scalar( $honey ) ? trim( (string) $honey ) : 'x' ) ) {
			return new \WP_Error( 'ebcr_channel_spam', __( 'Não foi possível enviar. Tente novamente.', 'eb-credito-rural' ), array( 'status' => 400 ) );
		}
		$anonymous = 'ouvidoria' === $channel && isset( $payload['anonymous'] ) && self::truthy( $payload['anonymous'] );
		$page      = self::clean_path( isset( $payload['page'] ) ? $payload['page'] : null );
		if ( null === $page ) {
			$errors[] = 'page';
			$page     = '/';
		}
		$raw = isset( $payload['fields'] ) ? $payload['fields'] : null;
		if ( ! is_array( $raw ) || ( $raw && array_keys( $raw ) === range( 0, count( $raw ) - 1 ) ) || count( $raw ) > self::MAX_KEYS ) {
			return new \WP_Error(
				'ebcr_channel_invalid',
				$generic,
				array(
					'status' => 400,
					'fields' => array( 'fields' ),
				)
			);
		}
		$fields = array();
		foreach ( self::FIELDS as $key ) {
			if ( ! array_key_exists( $key, $raw ) || null === $raw[ $key ] ) {
				continue;
			}
			$v = $raw[ $key ];
			if ( in_array( $key, self::CHECKBOXES, true ) ) {
				if ( ! is_scalar( $v ) ) {
					$errors[] = $key;
					continue;
				}
				$fields[ $key ] = self::truthy( $v ) ? 'sim' : '';
				continue;
			}
			if ( is_bool( $v ) || ! is_scalar( $v ) ) {
				$errors[] = $key;
				continue;
			}
			$v = in_array( $key, self::TEXTAREAS, true ) ? sanitize_textarea_field( (string) $v ) : sanitize_text_field( (string) $v );
			if ( mb_strlen( $v ) > self::MAX_LEN ) {
				$errors[] = $key;
				continue;
			}
			$fields[ $key ] = $v;
		}
		if ( $anonymous ) {
			foreach ( array( 'nome', 'email', 'telefone' ) as $k ) {
				unset( $fields[ $k ] );
			}
		}
		if ( isset( $fields['email'] ) && '' !== $fields['email'] ) {
			$email = sanitize_email( $fields['email'] );
			if ( ! $email || ! is_email( $email ) || strlen( $email ) > 190 ) {
				$errors[] = 'email';
			} else {
				$fields['email'] = strtolower( $email );
			}
		}
		foreach ( $channels[ $channel ]['required'] as $req ) {
			if ( ! isset( $fields[ $req ] ) || '' === trim( (string) $fields[ $req ] ) ) {
				$errors[] = $req;
			}
		}
		$fields = array_filter(
			$fields,
			static function ( $v ) {
				return '' !== (string) $v;
			}
		);
		if ( $errors ) {
			return new \WP_Error(
				'ebcr_channel_invalid',
				$generic,
				array(
					'status' => 400,
					'fields' => array_values( array_unique( $errors ) ),
				)
			);
		}
		$protocol = isset( $payload['protocol'] ) && is_string( $payload['protocol'] ) ? strtoupper( trim( $payload['protocol'] ) ) : '';
		if ( ! preg_match( self::PROTOCOL_RE, $protocol ) || 0 !== strpos( $protocol, $channels[ $channel ]['prefix'] . '-' ) ) {
			$protocol = '';
		}
		return array(
			'channel'   => $channel,
			'protocol'  => $protocol,
			'anonymous' => $anonymous,
			'page'      => $page,
			'fields'    => $fields,
		);
	}

	/**
	 * Gera um protocolo novo para o canal (PREFIXO-AAAAMMDD-XXXX, data no fuso do site).
	 *
	 * @param string $channel Canal.
	 * @return string
	 */
	public static function generate_protocol( $channel ) {
		$prefix = self::channels()[ $channel ]['prefix'];
		$chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		$suffix = '';
		for ( $i = 0; $i < 4; $i++ ) {
			$suffix .= $chars[ random_int( 0, strlen( $chars ) - 1 ) ];
		}
		return $prefix . '-' . wp_date( 'Ymd' ) . '-' . $suffix;
	}

	/**
	 * Grava a mensagem validada (o protocolo do cliente é mantido se válido e livre; senão, gera outro) e
	 * enfileira os e-mails. Retorna o registro gravado (null se o banco recusar a gravação).
	 *
	 * @param array $clean Resultado de validate().
	 * @return array|null
	 */
	public static function record( array $clean ) {
		$repo     = new ChannelMessageRepository();
		$protocol = $clean['protocol'];
		$anon     = ! empty( $clean['anonymous'] );
		$uid      = get_current_user_id();
		$id       = 0;
		// Até 5 tentativas: protocolo do cliente já usado (ou colisão rara na gravação) gera um novo.
		for ( $attempt = 0; $attempt < 5 && ! $id; $attempt++ ) {
			for ( $i = 0; ( '' === $protocol || $repo->protocol_exists( $protocol ) ) && $i < 20; $i++ ) {
				$protocol = self::generate_protocol( $clean['channel'] );
			}
			$id = $repo->insert(
				array(
					'channel'    => $clean['channel'],
					'protocol'   => $protocol,
					'anonymous'  => $anon ? 1 : 0,
					'fields'     => (string) wp_json_encode( $clean['fields'], JSON_UNESCAPED_UNICODE ),
					'email'      => $anon || empty( $clean['fields']['email'] ) ? '' : (string) $clean['fields']['email'],
					'page'       => $clean['page'],
					'ip_hash'    => $anon ? null : CookieConsent::ip_hash( Ip::get() ),
					'user_agent' => $anon ? null : mb_substr( Ip::user_agent(), 0, 180 ),
					'user_id'    => ! $anon && $uid ? $uid : null,
				)
			);
			if ( ! $id ) {
				$protocol = '';
			}
		}
		$row = $id ? $repo->find( $id ) : null;
		if ( ! $row ) {
			return null;
		}
		self::notify( $row );
		return $row;
	}

	/**
	 * Destinatário do canal: e-mail configurado ou, se vazio, o e-mail do administrador do WordPress.
	 *
	 * @param string $channel Canal.
	 * @return string
	 */
	public static function recipient( $channel ) {
		$ch    = self::channels();
		$email = isset( $ch[ $channel ] ) ? sanitize_email( (string) Options::get( $ch[ $channel ]['option'], '' ) ) : '';
		return $email && is_email( $email ) ? $email : (string) get_option( 'admin_email' );
	}

	/**
	 * Campos decodificados de um registro.
	 *
	 * @param array $row Registro.
	 * @return array
	 */
	public static function fields_of( array $row ) {
		$f = json_decode( (string) $row['fields'], true );
		return is_array( $f ) ? $f : array();
	}

	/**
	 * Tabela HTML dos campos (escapada) para e-mails e telas.
	 *
	 * @param array $fields Campos.
	 * @return string
	 */
	public static function fields_html( array $fields ) {
		$labels = self::field_labels();
		$html   = '<table role="presentation" cellspacing="0" cellpadding="6" style="border-collapse:collapse;width:100%">';
		foreach ( $fields as $k => $v ) {
			$html .= '<tr><th align="left" valign="top" style="border-bottom:1px solid #e5e7eb;width:34%;font-weight:600">' . esc_html( isset( $labels[ $k ] ) ? $labels[ $k ] : $k ) . '</th><td valign="top" style="border-bottom:1px solid #e5e7eb">' . nl2br( esc_html( (string) $v ) ) . '</td></tr>';
		}
		return $html . '</table>';
	}

	/**
	 * Enfileira o aviso à equipe (Reply-To do remetente, se houver) e o recibo ao remetente (se configurado).
	 * Nunca inclui hash de IP.
	 *
	 * @param array $row Registro gravado.
	 * @return void
	 */
	public static function notify( array $row ) {
		$ch     = self::channels();
		$label  = isset( $ch[ $row['channel'] ] ) ? $ch[ $row['channel'] ]['label'] : $row['channel'];
		$site   = Branding::brand_name();
		$fields = self::fields_of( $row );
		$anon   = ! empty( $row['anonymous'] );
		/* translators: 1: canal, 2: protocolo, 3: nome do site */
		$subject = sprintf( __( '[%1$s] %2$s — %3$s', 'eb-credito-rural' ), $label, $row['protocol'], $site );
		$body    = '<p>' . sprintf(
			/* translators: 1: canal, 2: protocolo */
			esc_html__( 'Nova mensagem recebida pelo %1$s. Protocolo: %2$s.', 'eb-credito-rural' ),
			esc_html( $label ),
			'<strong>' . esc_html( $row['protocol'] ) . '</strong>'
		) . '</p>';
		if ( $anon ) {
			$body .= '<p><strong>' . esc_html__( 'Relato anônimo: não há dados de identificação do remetente.', 'eb-credito-rural' ) . '</strong></p>';
		}
		$body   .= self::fields_html( $fields );
		$body   .= '<p style="color:#6b7280;font-size:13px">' . esc_html(
			sprintf(
				/* translators: 1: data, 2: página */
				__( 'Recebido em %1$s pela página %2$s.', 'eb-credito-rural' ),
				Helpers::date( $row['created_at'] ),
				$row['page']
			)
		) . '</p>';
		$headers = array();
		$reply   = ! $anon && ! empty( $fields['email'] ) && is_email( $fields['email'] ) ? $fields['email'] : '';
		if ( $reply ) {
			$name      = isset( $fields['nome'] ) ? str_replace( array( "\r", "\n", '<', '>', '"' ), '', (string) $fields['nome'] ) : '';
			$headers[] = 'Reply-To: ' . ( '' !== $name ? '"' . $name . '" ' : '' ) . '<' . $reply . '>';
		}
		Mailer::send_raw( 'channel_message', self::recipient( $row['channel'] ), $subject, $body, $headers );
		if ( $reply && Options::bool( 'channel_send_receipt' ) ) {
			/* translators: 1: protocolo, 2: nome do site */
			$rsubject = sprintf( __( 'Recebemos sua mensagem — protocolo %1$s — %2$s', 'eb-credito-rural' ), $row['protocol'], $site );
			$rbody    = '<p>' . ( ! empty( $fields['nome'] ) ? esc_html( sprintf( /* translators: %s: nome */ __( 'Olá, %s.', 'eb-credito-rural' ), $fields['nome'] ) ) . ' ' : '' )
				. sprintf(
					/* translators: 1: canal, 2: protocolo */
					esc_html__( 'Recebemos sua mensagem pelo %1$s. Guarde o protocolo %2$s para acompanhar o atendimento.', 'eb-credito-rural' ),
					esc_html( $label ),
					'<strong>' . esc_html( $row['protocol'] ) . '</strong>'
				) . '</p><p>' . esc_html__( 'Se você não enviou esta mensagem, ignore este e-mail.', 'eb-credito-rural' ) . '</p>';
			Mailer::send_raw( 'channel_receipt', $reply, $rsubject, $rbody );
		}
	}
}
