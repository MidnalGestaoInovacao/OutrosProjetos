<?php
/**
 * CoreCyber · E-mail via Resend
 *
 * Publicado no WordPress como snippet do plugin Code Snippets (tools/deploy_wp.py --only mailer).
 * A chave da API NÃO fica aqui: é gravada na opção "corecyber_resend_key" pela rota de administração
 * (ou na constante CORECYBER_RESEND_KEY do wp-config.php, que tem prioridade).
 *
 * Cada função tem a própria proteção function_exists (o Code Snippets valida o código novo com a versão
 * anterior ainda carregada).
 *
 * 1. Todo e-mail do WordPress (wp_mail) sai pela API do Resend, com remetente contato@corecyber.com.br.
 *    Se o Resend falhar, o WordPress usa o envio padrão do servidor.
 * 2. POST /?rest_route=/corecyber/v1/enviar recebe os formulários do site: valida, gera o protocolo,
 *    envia à caixa responsável e manda a confirmação ao visitante no idioma dele.
 *    Nada é gravado no banco além do contador de protocolos.
 * 3. GET/POST /?rest_route=/corecyber/v1/config e POST /corecyber/v1/teste (somente administradores).
 *    Modo de teste: com a opção "corecyber_resend_test_to" preenchida (config: test_to), as cópias da equipe
 *    vão para esse endereço, com [TESTE] no assunto, e o limite de envios por IP é suspenso.
 */


if ( ! function_exists( 'ccy_mail_cfg' ) ) {
	function ccy_mail_cfg() {
		$sales = 'contato@corecyber.com.br';
		return array(
			'from'  => 'CoreCyber <contato@corecyber.com.br>',
			'forms' => array(
				'contact'    => array( 'to' => $sales, 'p' => 'CTT', 'sla' => 'geral', 'pt' => 'Contato', 'en' => 'Contact', 'es' => 'Contacto' ),
				'demo'       => array( 'to' => $sales, 'p' => 'DEM', 'sla' => 'geral', 'pt' => 'Demonstração guiada', 'en' => 'Guided demo', 'es' => 'Demostración guiada' ),
				'poc'        => array( 'to' => $sales, 'p' => 'POC', 'sla' => 'geral', 'pt' => 'Prova de conceito de 30 dias', 'en' => '30-day proof of concept', 'es' => 'Prueba de concepto de 30 días' ),
				'partner'    => array( 'to' => $sales, 'p' => 'PAR', 'sla' => 'geral', 'pt' => 'Parceria / MSSP', 'en' => 'Partnership / MSSP', 'es' => 'Alianza / MSSP' ),
				'referral'   => array( 'to' => $sales, 'p' => 'IND', 'sla' => 'geral', 'pt' => 'Indicação de cliente', 'en' => 'Customer referral', 'es' => 'Recomendación de cliente' ),
				'diagnostic' => array( 'to' => $sales, 'p' => 'DGN', 'sla' => 'diagnostico', 'pt' => 'Diagnóstico gratuito de exposição', 'en' => 'Free exposure assessment', 'es' => 'Diagnóstico gratuito de exposición' ),
				'titular'    => array( 'to' => 'lgpd@corecyber.com.br', 'p' => 'LGPD', 'sla' => 'titular', 'pt' => 'Solicitação de titular (LGPD)', 'en' => 'Data subject request (LGPD)', 'es' => 'Solicitud del titular (LGPD)' ),
				'integrity'  => array( 'to' => 'ouvidoria@corecyber.com.br', 'p' => 'INT', 'sla' => 'integridade', 'pt' => 'Relato de integridade', 'en' => 'Integrity report', 'es' => 'Reporte de integridad' ),
				'disclosure' => array( 'to' => 'seguranca@corecyber.com.br', 'p' => 'VDP', 'sla' => 'vulnerabilidade', 'pt' => 'Relato de vulnerabilidade', 'en' => 'Vulnerability report', 'es' => 'Reporte de vulnerabilidad' ),
				'a11y'       => array( 'to' => 'suporte@corecyber.com.br', 'p' => 'ACS', 'sla' => 'acessibilidade', 'pt' => 'Acessibilidade', 'en' => 'Accessibility', 'es' => 'Accesibilidad' ),
			),
		);
	}
}

if ( ! function_exists( 'ccy_resend_key' ) ) {
	function ccy_resend_key() {
		if ( defined( 'CORECYBER_RESEND_KEY' ) && CORECYBER_RESEND_KEY ) {
			return (string) CORECYBER_RESEND_KEY;
		}
		return (string) get_option( 'corecyber_resend_key', '' );
	}
}

if ( ! function_exists( 'ccy_test_to' ) ) {
	function ccy_test_to() {
		$t = (string) get_option( 'corecyber_resend_test_to', '' );
		return is_email( $t ) ? $t : '';
	}
}

/** Envia pela API do Resend. Retorna o id da mensagem ou WP_Error. */
if ( ! function_exists( 'ccy_resend_send' ) ) {
	function ccy_resend_send( $payload, $idem = '' ) {
		$key = ccy_resend_key();
		if ( '' === $key ) {
			return new WP_Error( 'ccy_no_key', 'Chave do Resend não configurada.' );
		}
		$headers = array(
			'Authorization' => 'Bearer ' . $key,
			'Content-Type'  => 'application/json',
			'User-Agent'    => 'CoreCyber-WordPress/1.0',
		);
		if ( '' !== $idem ) {
			$headers['Idempotency-Key'] = substr( $idem, 0, 250 );
		}
		$res = wp_remote_post( 'https://api.resend.com/emails', array(
			'timeout' => 15,
			'headers' => $headers,
			'body'    => wp_json_encode( $payload ),
		) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( $code >= 200 && $code < 300 && is_array( $body ) && ! empty( $body['id'] ) ) {
			return (string) $body['id'];
		}
		$msg = ( is_array( $body ) && ! empty( $body['message'] ) ) ? (string) $body['message'] : 'Erro HTTP ' . $code;
		return new WP_Error( 'ccy_resend_' . $code, $msg );
	}
}

/* ------------------------------------------------------------ 1. wp_mail pelo Resend */
if ( ! function_exists( 'ccy_split_addresses' ) ) {
	function ccy_split_addresses( $v ) {
		if ( is_array( $v ) ) {
			$v = implode( ',', $v );
		}
		return array_values( array_filter( array_map( 'trim', explode( ',', (string) $v ) ) ) );
	}
}

if ( ! function_exists( 'ccy_pre_wp_mail' ) ) {
	function ccy_pre_wp_mail( $short, $atts ) {
		if ( null !== $short || '' === ccy_resend_key() ) {
			return $short;
		}
		$cfg = ccy_mail_cfg();
		$to  = ccy_split_addresses( isset( $atts['to'] ) ? $atts['to'] : '' );
		if ( ! $to ) {
			return $short;
		}
		$headers = isset( $atts['headers'] ) ? $atts['headers'] : array();
		if ( ! is_array( $headers ) ) {
			$headers = explode( "\n", str_replace( "\r\n", "\n", (string) $headers ) );
		}
		$ctype = 'text/plain';
		$reply = array();
		$cc    = array();
		$bcc   = array();
		foreach ( $headers as $h ) {
			if ( false === strpos( (string) $h, ':' ) ) {
				continue;
			}
			list( $name, $value ) = array_map( 'trim', explode( ':', (string) $h, 2 ) );
			switch ( strtolower( $name ) ) {
				case 'content-type':
					if ( false !== stripos( $value, 'text/html' ) ) {
						$ctype = 'text/html';
					}
					break;
				case 'reply-to':
					$reply = array_merge( $reply, ccy_split_addresses( $value ) );
					break;
				case 'cc':
					$cc = array_merge( $cc, ccy_split_addresses( $value ) );
					break;
				case 'bcc':
					$bcc = array_merge( $bcc, ccy_split_addresses( $value ) );
					break;
			}
		}
		$ctype   = apply_filters( 'wp_mail_content_type', $ctype );
		$message = isset( $atts['message'] ) ? (string) $atts['message'] : '';
		$payload = array(
			'from'    => $cfg['from'],
			'to'      => $to,
			'subject' => isset( $atts['subject'] ) ? (string) $atts['subject'] : '',
		);
		if ( 'text/html' === $ctype ) {
			$payload['html'] = $message;
		} else {
			$payload['text'] = $message;
		}
		if ( $reply ) {
			$payload['reply_to'] = $reply;
		}
		if ( $cc ) {
			$payload['cc'] = $cc;
		}
		if ( $bcc ) {
			$payload['bcc'] = $bcc;
		}
		$files = array();
		foreach ( (array) ( isset( $atts['attachments'] ) ? $atts['attachments'] : array() ) as $k => $file ) {
			if ( is_string( $file ) && is_readable( $file ) && filesize( $file ) <= 8 * 1024 * 1024 ) {
				$files[] = array(
					'filename' => is_string( $k ) ? $k : basename( $file ),
					'content'  => base64_encode( (string) file_get_contents( $file ) ),
				);
			}
		}
		if ( $files ) {
			$payload['attachments'] = $files;
		}
		$id = ccy_resend_send( $payload );
		if ( is_wp_error( $id ) ) {
			error_log( 'CoreCyber/Resend: wp_mail caiu no envio padrão — ' . $id->get_error_message() );
			return null;
		}
		do_action( 'wp_mail_succeeded', $atts );
		return true;
	}
}
add_filter( 'pre_wp_mail', 'ccy_pre_wp_mail', 10, 2 );

/* ------------------------------------------------------------ 2. formulários do site */
if ( ! function_exists( 'ccy_t' ) ) {
	function ccy_t( $lang, $key, $vars = array() ) {
		$s = array(
			'pt' => array(
				'subject'   => 'Recebemos sua mensagem · Protocolo {p}',
				'hello'     => 'Olá, {n}!',
				'hello0'    => 'Olá!',
				'intro'     => 'Recebemos o seu envio de <b>{f}</b> pelo site corecyber.com.br. Guarde este número de protocolo:',
				'protocol'  => 'Protocolo',
				'reply'     => 'Para complementar, basta responder a este e-mail.',
				'safety'    => 'A EBAEM nunca pede senha, código de 2º fator ou dados de cartão.',
				'why'       => 'Você recebeu esta mensagem porque preencheu um formulário em corecyber.com.br. Se não foi você, ignore este e-mail.',
				'geral'          => 'Nossa equipe retorna em horário comercial (segunda a sexta, das 8h às 18h, horário de Brasília).',
				'diagnostico'    => 'O relatório sai em até 2 dias úteis depois da comprovação de que o domínio é da sua organização.',
				'titular'        => 'A resposta completa sai em até 15 dias (LGPD, art. 19). Se for preciso confirmar sua identidade, pediremos só o mínimo necessário.',
				'integridade'    => 'O relato é triado pelo Comitê de Ética e Conformidade em até 5 dias úteis, com sigilo e proteção contra retaliação.',
				'vulnerabilidade' => 'Este e-mail confirma o recebimento. A triagem acontece em até 10 dias úteis e você será informado até a correção.',
				'acessibilidade' => 'Respondemos em até 5 dias úteis.',
			),
			'en' => array(
				'subject'   => 'We received your message · Reference {p}',
				'hello'     => 'Hello, {n}!',
				'hello0'    => 'Hello!',
				'intro'     => 'We received your <b>{f}</b> submission on corecyber.com.br. Please keep this reference number:',
				'protocol'  => 'Reference',
				'reply'     => 'To add anything, simply reply to this email.',
				'safety'    => 'EBAEM will never ask for your password, two-factor code or card details.',
				'why'       => 'You received this message because a form was filled in on corecyber.com.br. If it was not you, please ignore this email.',
				'geral'          => 'Our team will get back to you during business hours (Monday to Friday, 8am to 6pm, Brasília time).',
				'diagnostico'    => 'The report is delivered within 2 business days after you confirm the domain belongs to your organization.',
				'titular'        => 'You will receive the complete answer within 15 days (LGPD, art. 19). If we need to confirm your identity, we will ask only for the minimum necessary.',
				'integridade'    => 'Your report is triaged by the Ethics and Compliance Committee within 5 business days, confidentially and with protection against retaliation.',
				'vulnerabilidade' => 'This email confirms receipt. Triage takes place within 10 business days and we will keep you informed until the fix.',
				'acessibilidade' => 'We will reply within 5 business days.',
			),
			'es' => array(
				'subject'   => 'Recibimos su mensaje · Protocolo {p}',
				'hello'     => '¡Hola, {n}!',
				'hello0'    => '¡Hola!',
				'intro'     => 'Recibimos su envío de <b>{f}</b> en el sitio corecyber.com.br. Guarde este número de protocolo:',
				'protocol'  => 'Protocolo',
				'reply'     => 'Para complementar, basta con responder a este correo.',
				'safety'    => 'EBAEM nunca le pedirá contraseñas, códigos de segundo factor ni datos de tarjeta.',
				'why'       => 'Usted recibió este mensaje porque se completó un formulario en corecyber.com.br. Si no fue usted, ignore este correo.',
				'geral'          => 'Nuestro equipo le responderá en horario comercial (de lunes a viernes, de 8 a 18 h, hora de Brasilia).',
				'diagnostico'    => 'El informe se entrega en hasta 2 días hábiles después de comprobar que el dominio pertenece a su organización.',
				'titular'        => 'La respuesta completa se entrega en hasta 15 días (LGPD, art. 19). Si fuera necesario confirmar su identidad, le pediremos solo lo mínimo necesario.',
				'integridade'    => 'El reporte es evaluado por el Comité de Ética y Cumplimiento en hasta 5 días hábiles, con confidencialidad y protección contra represalias.',
				'vulnerabilidade' => 'Este correo confirma la recepción. La evaluación se realiza en hasta 10 días hábiles y le mantendremos informado hasta la corrección.',
				'acessibilidade' => 'Respondemos en hasta 5 días hábiles.',
			),
		);
		$txt = isset( $s[ $lang ][ $key ] ) ? $s[ $lang ][ $key ] : $s['pt'][ $key ];
		foreach ( $vars as $k => $v ) {
			$txt = str_replace( '{' . $k . '}', $v, $txt );
		}
		return $txt;
	}
}

if ( ! function_exists( 'ccy_mail_layout' ) ) {
	function ccy_mail_layout( $title, $inner ) {
		return '<!doctype html><html><body style="margin:0;padding:24px 12px;background:#f2f6f9">'
			. '<div style="max-width:600px;margin:0 auto;font-family:Arial,Helvetica,sans-serif;color:#0b2545;font-size:15px;line-height:1.55">'
			. '<div style="background:#002141;border-radius:14px 14px 0 0;padding:18px 24px"><b style="color:#ffffff;font-size:19px">CoreCyber</b>'
			. ' <span style="color:#19c3d6;font-size:13px">· um produto EBAEM</span></div>'
			. '<div style="background:#ffffff;border:1px solid #d6e2ec;border-top:0;border-radius:0 0 14px 14px;padding:24px">'
			. '<h1 style="margin:0 0 14px;font-size:19px;color:#002141">' . esc_html( $title ) . '</h1>' . $inner . '</div>'
			. '<p style="margin:14px 4px 0;font-size:12px;color:#6b7f95">CoreCyber · Segurança que se vê. Controle que se prova. · <a href="https://corecyber.com.br" style="color:#00708a">corecyber.com.br</a></p>'
			. '</div></body></html>';
	}
}

if ( ! function_exists( 'ccy_next_protocol' ) ) {
	function ccy_next_protocol( $prefix ) {
		$year = wp_date( 'Y' );
		$opt  = 'ccy_seq_' . strtolower( $prefix ) . '_' . $year;
		$n    = (int) get_option( $opt, 0 ) + 1;
		update_option( $opt, $n, false );
		return sprintf( '%s-%s-%06d', $prefix, $year, $n );
	}
}

if ( ! function_exists( 'ccy_rate_ok' ) ) {
	function ccy_rate_ok() {
		$ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '0';
		$k_ip   = 'ccy_rl_' . substr( hash( 'sha256', $ip . wp_salt( 'nonce' ) ), 0, 24 );
		$k_all  = 'ccy_rl_all_' . gmdate( 'YmdH' );
		$n_ip   = (int) get_transient( $k_ip );
		$n_all  = (int) get_transient( $k_all );
		if ( $n_ip >= 5 || $n_all >= 300 ) {
			return false;
		}
		set_transient( $k_ip, $n_ip + 1, 10 * MINUTE_IN_SECONDS );
		set_transient( $k_all, $n_all + 1, HOUR_IN_SECONDS );
		return true;
	}
}

if ( ! function_exists( 'ccy_rest_enviar' ) ) {
	function ccy_rest_enviar( $req ) {
		$cfg  = ccy_mail_cfg();
		$data = $req->get_json_params();
		if ( ! is_array( $data ) ) {
			$data = $req->get_body_params();
		}
		$kind = isset( $data['form'] ) ? sanitize_key( $data['form'] ) : '';
		if ( ! isset( $cfg['forms'][ $kind ] ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'code' => 'form' ), 400 );
		}
		$form = $cfg['forms'][ $kind ];

		/* origem: só aceita envios vindos do próprio site */
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$src  = $req->get_header( 'origin' );
		if ( ! $src ) {
			$src = $req->get_header( 'referer' );
		}
		if ( ! $src || wp_parse_url( $src, PHP_URL_HOST ) !== $host ) {
			return new WP_REST_Response( array( 'ok' => false, 'code' => 'origin' ), 403 );
		}

		/* armadilhas para robôs: responde como se tivesse dado certo, sem enviar nada */
		$elapsed = isset( $data['t'] ) ? (int) $data['t'] : 0;
		if ( ! empty( $data['website'] ) || $elapsed < 2500 ) {
			return new WP_REST_Response( array( 'ok' => true, 'protocol' => $form['p'] . '-' . wp_date( 'Y' ) . '-' . sprintf( '%06d', wp_rand( 1, 999999 ) ), 'confirm' => false ), 200 );
		}
		$test_to = ccy_test_to();
		if ( ! $test_to && ! ccy_rate_ok() ) {
			return new WP_REST_Response( array( 'ok' => false, 'code' => 'rate' ), 429 );
		}

		$lang  = ( isset( $data['lang'] ) && in_array( $data['lang'], array( 'pt', 'en', 'es' ), true ) ) ? $data['lang'] : 'pt';
		$page  = isset( $data['page'] ) ? esc_url_raw( substr( (string) $data['page'], 0, 300 ) ) : '';
		$anon  = false;
		$rows  = array();
		$total = 0;
		$raw   = ( isset( $data['fields'] ) && is_array( $data['fields'] ) ) ? $data['fields'] : array();
		foreach ( $raw as $f ) {
			if ( ! is_array( $f ) ) {
				continue;
			}
			if ( isset( $f['n'] ) && 'identificacao' === $f['n'] && isset( $f['r'] ) && 'sem-nome' === $f['r'] ) {
				$anon = true;
			}
		}
		$email = '';
		$name  = '';
		foreach ( $raw as $f ) {
			if ( count( $rows ) >= 40 || ! is_array( $f ) ) {
				continue;
			}
			$n = isset( $f['n'] ) ? sanitize_key( $f['n'] ) : '';
			$l = isset( $f['l'] ) ? sanitize_text_field( mb_substr( (string) $f['l'], 0, 120 ) ) : $n;
			$v = isset( $f['v'] ) ? trim( sanitize_textarea_field( mb_substr( (string) $f['v'], 0, 5000 ) ) ) : '';
			if ( '' === $n || '' === $v ) {
				continue;
			}
			if ( $anon && 'nome' === $n ) {
				continue;
			}
			$total += strlen( $v );
			if ( $total > 30000 ) {
				break;
			}
			$rows[] = array( $l ? $l : $n, $v );
			if ( ( 'email' === $n || 'contato' === $n ) && '' === $email && is_email( $v ) ) {
				$email = $v;
			}
			if ( 'nome' === $n ) {
				$name = mb_substr( $v, 0, 80 );
			}
		}
		if ( ! $rows ) {
			return new WP_REST_Response( array( 'ok' => false, 'code' => 'empty' ), 400 );
		}
		$consents = array();
		foreach ( ( isset( $data['consents'] ) && is_array( $data['consents'] ) ) ? array_slice( $data['consents'], 0, 6 ) : array() as $c ) {
			$c = sanitize_text_field( mb_substr( (string) $c, 0, 300 ) );
			if ( '' !== $c ) {
				$consents[] = $c;
			}
		}

		$protocol = ccy_next_protocol( $form['p'] );
		$when     = wp_date( 'd/m/Y H:i' );

		/* e-mail para a equipe */
		$table = '';
		$plain = array();
		foreach ( $rows as $r ) {
			$table  .= '<tr><th align="left" valign="top" style="padding:8px 12px 8px 0;border-bottom:1px solid #e3ebf2;color:#4a6580;font-weight:600;width:34%">' . esc_html( $r[0] ) . '</th>'
				. '<td style="padding:8px 0;border-bottom:1px solid #e3ebf2">' . nl2br( esc_html( $r[1] ) ) . '</td></tr>';
			$plain[] = $r[0] . ': ' . $r[1];
		}
		$meta  = '<p style="margin:0 0 16px;color:#4a6580;font-size:13px">' . esc_html( $form['pt'] ) . ' · <b style="color:#002141">' . esc_html( $protocol ) . '</b> · ' . esc_html( $when )
			. ' · idioma ' . esc_html( strtoupper( $lang ) ) . ( $page ? ' · <a href="' . esc_url( $page ) . '" style="color:#00708a">página</a>' : '' ) . '</p>';
		$extra = '';
		if ( $consents ) {
			$extra .= '<p style="margin:16px 0 4px;font-size:13px;color:#4a6580"><b>Confirmações marcadas pelo visitante</b></p><ul style="margin:0;padding-left:18px;font-size:13px;color:#4a6580">';
			foreach ( $consents as $c ) {
				$extra .= '<li>' . esc_html( $c ) . '</li>';
			}
			$extra .= '</ul>';
		}
		$extra .= '<p style="margin:16px 0 0;font-size:13px;color:#4a6580">'
			. ( $anon ? 'Relato sem identificação: o nome não foi informado e o IP não é registrado.' : ( $email ? 'Use “Responder” para falar direto com o visitante.' : 'O visitante não informou e-mail.' ) )
			. '</p>';
		$team = array(
			'from'    => $cfg['from'],
			'to'      => array( $test_to ? $test_to : $form['to'] ),
			'subject' => ( $test_to ? '[TESTE → ' . $form['to'] . '] ' : '[Site] ' ) . $form['pt'] . ' · ' . $protocol . ( $name ? ' · ' . $name : '' ),
			'html'    => ccy_mail_layout( 'Novo envio pelo site', $meta . '<table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;font-size:14px">' . $table . '</table>' . $extra ),
			'text'    => $form['pt'] . ' · ' . $protocol . ' · ' . $when . "\n\n" . implode( "\n", $plain ) . ( $consents ? "\n\nConfirmações: " . implode( ' | ', $consents ) : '' ) . ( $page ? "\n\nPágina: " . $page : '' ),
			'tags'    => array( array( 'name' => 'formulario', 'value' => $kind ) ),
		);
		if ( $email && ! $anon ) {
			$team['reply_to'] = array( $email );
		}
		$sent = ccy_resend_send( $team, $protocol . '-equipe' );
		if ( is_wp_error( $sent ) ) {
			error_log( 'CoreCyber/Resend: formulário ' . $kind . ' ' . $protocol . ' — ' . $sent->get_error_message() );
			return new WP_REST_Response( array( 'ok' => false, 'code' => 'send', 'protocol' => $protocol ), 502 );
		}

		/* confirmação para o visitante (sem repetir o texto enviado, para não servir de canal de spam) */
		$confirm = false;
		if ( $email ) {
			$label = $form[ $lang ];
			$hello = $name ? ccy_t( $lang, 'hello', array( 'n' => esc_html( $name ) ) ) : ccy_t( $lang, 'hello0' );
			$inner = '<p style="margin:0 0 12px">' . $hello . '</p>'
				. '<p style="margin:0 0 14px">' . ccy_t( $lang, 'intro', array( 'f' => esc_html( $label ) ) ) . '</p>'
				. '<p style="margin:0 0 16px;padding:14px 16px;background:#eef8fa;border:1px solid #bfe6ec;border-radius:10px;font-size:18px"><span style="font-size:12px;color:#00708a;letter-spacing:.08em;text-transform:uppercase">' . esc_html( ccy_t( $lang, 'protocol' ) ) . '</span><br><b style="color:#002141">' . esc_html( $protocol ) . '</b></p>'
				. '<p style="margin:0 0 12px">' . esc_html( ccy_t( $lang, $form['sla'] ) ) . '</p>'
				. '<p style="margin:0 0 12px">' . esc_html( ccy_t( $lang, 'reply' ) ) . ' ' . esc_html( ccy_t( $lang, 'safety' ) ) . '</p>'
				. '<p style="margin:18px 0 0;font-size:12px;color:#6b7f95">' . esc_html( ccy_t( $lang, 'why' ) ) . '</p>';
			$conf  = array(
				'from'     => $cfg['from'],
				'to'       => array( $email ),
				'reply_to' => array( $form['to'] ),
				'subject'  => ccy_t( $lang, 'subject', array( 'p' => $protocol ) ),
				'html'     => ccy_mail_layout( $label, $inner ),
				'text'     => wp_strip_all_tags( $hello ) . "\n\n" . wp_strip_all_tags( ccy_t( $lang, 'intro', array( 'f' => $label ) ) ) . ' ' . $protocol . "\n\n" . ccy_t( $lang, $form['sla'] ) . "\n\n" . ccy_t( $lang, 'reply' ) . ' ' . ccy_t( $lang, 'safety' ) . "\n\n" . ccy_t( $lang, 'why' ),
				'tags'     => array( array( 'name' => 'formulario', 'value' => $kind . '_confirmacao' ) ),
			);
			$confirm = ! is_wp_error( ccy_resend_send( $conf, $protocol . '-visitante' ) );
		}
		return new WP_REST_Response( array( 'ok' => true, 'protocol' => $protocol, 'confirm' => $confirm ), 200 );
	}
}

/* ------------------------------------------------------------ 3. administração */
if ( ! function_exists( 'ccy_can_manage' ) ) {
	function ccy_can_manage() {
		return current_user_can( 'manage_options' );
	}
}

if ( ! function_exists( 'ccy_key_hint' ) ) {
	function ccy_key_hint() {
		$k = ccy_resend_key();
		return '' === $k ? '' : substr( $k, 0, 3 ) . '…' . substr( $k, -4 );
	}
}

if ( ! function_exists( 'ccy_rest_config_get' ) ) {
	function ccy_rest_config_get() {
		$cfg   = ccy_mail_cfg();
		$forms = array();
		foreach ( $cfg['forms'] as $k => $f ) {
			$forms[ $k ] = $f['to'];
		}
		return array(
			'configured' => '' !== ccy_resend_key(),
			'key'        => ccy_key_hint(),
			'source'     => ( defined( 'CORECYBER_RESEND_KEY' ) && CORECYBER_RESEND_KEY ) ? 'wp-config' : 'option',
			'from'       => $cfg['from'],
			'test_to'    => ccy_test_to(),
			'forms'      => $forms,
		);
	}
}

if ( ! function_exists( 'ccy_rest_config_set' ) ) {
	function ccy_rest_config_set( $req ) {
		if ( null !== $req->get_param( 'test_to' ) ) {
			$t = sanitize_email( (string) $req->get_param( 'test_to' ) );
			update_option( 'corecyber_resend_test_to', is_email( $t ) ? $t : '', false );
			if ( null === $req->get_param( 'key' ) ) {
				return array( 'ok' => true, 'test_to' => ccy_test_to() );
			}
		}
		$key = trim( (string) $req->get_param( 'key' ) );
		if ( ! preg_match( '/^re_[A-Za-z0-9_]{10,}$/', $key ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'code' => 'formato' ), 400 );
		}
		update_option( 'corecyber_resend_key', $key, false );
		return array( 'ok' => true, 'key' => ccy_key_hint() );
	}
}

if ( ! function_exists( 'ccy_rest_teste' ) ) {
	function ccy_rest_teste( $req ) {
		$to = sanitize_email( (string) $req->get_param( 'to' ) );
		if ( ! is_email( $to ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'code' => 'email' ), 400 );
		}
		$via = (string) $req->get_param( 'via' );
		if ( 'wp_mail' === $via ) {
			$ok = wp_mail( $to, 'Teste do wp_mail · CoreCyber (Resend)', '<p>Este e-mail foi enviado pela função <b>wp_mail</b> do WordPress, pelo Resend.</p>', array( 'Content-Type: text/html; charset=UTF-8' ) );
			return array( 'ok' => (bool) $ok );
		}
		$id = ccy_resend_send( array(
			'from'    => ccy_mail_cfg()['from'],
			'to'      => array( $to ),
			'subject' => 'Teste do WordPress · CoreCyber (Resend)',
			'html'    => ccy_mail_layout( 'Teste de envio', '<p style="margin:0">O WordPress de corecyber.com.br está enviando pelo Resend.</p>' ),
		) );
		if ( is_wp_error( $id ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => $id->get_error_message() ), 502 );
		}
		return array( 'ok' => true, 'id' => $id );
	}
}

if ( ! function_exists( 'ccy_register_routes' ) ) {
	function ccy_register_routes() {
		register_rest_route( 'corecyber/v1', '/enviar', array(
			'methods'             => 'POST',
			'callback'            => 'ccy_rest_enviar',
			'permission_callback' => '__return_true',
		) );
		register_rest_route( 'corecyber/v1', '/config', array(
			array( 'methods' => 'GET', 'callback' => 'ccy_rest_config_get', 'permission_callback' => 'ccy_can_manage' ),
			array( 'methods' => 'POST', 'callback' => 'ccy_rest_config_set', 'permission_callback' => 'ccy_can_manage' ),
		) );
		register_rest_route( 'corecyber/v1', '/teste', array(
			'methods'             => 'POST',
			'callback'            => 'ccy_rest_teste',
			'permission_callback' => 'ccy_can_manage',
		) );
	}
}
add_action( 'rest_api_init', 'ccy_register_routes' );
