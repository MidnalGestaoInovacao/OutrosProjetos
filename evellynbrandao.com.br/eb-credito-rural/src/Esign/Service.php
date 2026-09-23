<?php
/**
 * Regras da assinatura eletrônica simples: elegibilidade, texto do documento, código de uso único por e-mail,
 * verificação, geração do PDF com evidências, gravação do documento e da assinatura, cancelamento.
 *
 * Regra de repetição: uma nova assinatura do mesmo tipo SUBSTITUI o PDF gerado anteriormente pela assinatura
 * eletrônica no mesmo slot (o antigo é removido/soft-delete e a linha antiga em signatures continua como histórico),
 * salvo se o PDF anterior já foi ACEITO pela equipe — nesse caso a nova assinatura é recusada. Arquivos enviados
 * manualmente para o mesmo tipo nunca são tocados (a assinatura SOMA a eles).
 *
 * @package EBCR
 */

namespace EBCR\Esign;

use EBCR\Database\DocumentRepository;
use EBCR\Database\DocumentRequestRepository;
use EBCR\Database\SignatureRepository;
use EBCR\Database\SubmissionDataRepository;
use EBCR\Domain\Status;
use EBCR\Files\Storage;
use EBCR\Files\UploadHandler;
use EBCR\Forms\DocumentMatrix;
use EBCR\Mail\Mailer;
use EBCR\Mail\Notifier;
use EBCR\Pdf\Writer;
use EBCR\Security\AuditLog;
use EBCR\Security\Authorization;
use EBCR\Security\RateLimiter;
use EBCR\Support\Helpers;
use EBCR\Support\Ip;
use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Serviço sem estado; recebe o usuário explicitamente (testável).
 */
final class Service {

	/**
	 * Validade do código (segundos).
	 */
	const CODE_TTL = 600;

	/**
	 * Tentativas de código por emissão.
	 */
	const MAX_ATTEMPTS = 5;

	/**
	 * Reenvios permitidos por usuário/solicitação/tipo dentro de CODE_TTL.
	 */
	const MAX_SENDS = 3;

	/**
	 * Bloqueio após exceder as tentativas (segundos, progressivo).
	 */
	const LOCK_SECONDS = 900;

	/**
	 * Método registrado na assinatura.
	 */
	const METHOD = 'email_otp';

	// ------------------------------------------------------------------ configuração

	/**
	 * Módulo ligado?
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return Options::bool( 'esign_enabled' );
	}

	/**
	 * Tipos configurados como assináveis (chaves da matriz).
	 *
	 * @return string[]
	 */
	public static function configured_types() {
		$raw = (string) Options::get( 'esign_doc_types', '' );
		$out = array();
		foreach ( explode( ',', $raw ) as $k ) {
			$k = sanitize_key( trim( $k ) );
			if ( '' !== $k ) {
				$out[] = $k;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Modelo de texto (com placeholders) de um tipo. Só `autorizacao_scr` tem texto configurável por enquanto;
	 * outros tipos podem ser atendidos pelo filtro `ebcr_esign_text`.
	 *
	 * @param string $doc_type Tipo.
	 * @return string
	 */
	public static function text_template( $doc_type ) {
		$doc_type = sanitize_key( $doc_type );
		if ( 'autorizacao_scr' === $doc_type ) {
			return trim( (string) Options::get( 'esign_scr_text', '' ) );
		}
		return '';
	}

	/**
	 * Título do documento (sem o sufixo "assinatura eletrônica").
	 *
	 * @param string $doc_type Tipo.
	 * @return string
	 */
	public static function title( $doc_type ) {
		$doc_type = sanitize_key( $doc_type );
		$title    = 'autorizacao_scr' === $doc_type ? __( 'Autorização de consulta ao SCR', 'eb-credito-rural' ) : DocumentMatrix::label( $doc_type );
		/**
		 * Título do documento assinado eletronicamente.
		 *
		 * @param string $title    Título.
		 * @param string $doc_type Tipo da matriz.
		 */
		return (string) apply_filters( 'ebcr_esign_title', $title, $doc_type );
	}

	/**
	 * O tipo pode ser assinado eletronicamente nesta solicitação? (módulo ligado, tipo configurado e válido, texto existente)
	 *
	 * @param string $doc_type   Tipo.
	 * @param array  $submission Solicitação.
	 * @return bool
	 */
	public static function is_signable( $doc_type, array $submission ) {
		$doc_type = sanitize_key( $doc_type );
		if ( ! self::is_enabled() || ! in_array( $doc_type, self::configured_types(), true ) || ! DocumentMatrix::is_valid_type( $doc_type ) ) {
			return false;
		}
		return '' !== self::text( $doc_type, $submission );
	}

	// ------------------------------------------------------------------ assinante e texto

	/**
	 * Dados do assinante a partir da etapa 1. Null se a etapa não foi preenchida com nome/CPF (ou razão social, CNPJ e representante).
	 *
	 * @param array $submission Solicitação.
	 * @return array|null person_type, name, document, signer_name, signer_document, company, company_document, email, user_id, user_login.
	 */
	public static function signer( array $submission ) {
		$ident = ( new SubmissionDataRepository() )->get( (int) $submission['id'], 'identificacao' );
		if ( ! $ident || isset( $ident['_error'] ) ) {
			return null;
		}
		$user = get_userdata( (int) $submission['user_id'] );
		if ( ! $user ) {
			return null;
		}
		$base = array(
			'email'      => $user->user_email,
			'user_id'    => (int) $user->ID,
			'user_login' => $user->user_login,
		);
		$type = isset( $ident['person_type'] ) && 'PJ' === $ident['person_type'] ? 'PJ' : 'PF';
		if ( 'PF' === $type ) {
			$name = isset( $ident['nome'] ) ? trim( sanitize_text_field( (string) $ident['nome'] ) ) : '';
			$cpf  = isset( $ident['cpf'] ) ? Helpers::digits( $ident['cpf'] ) : '';
			if ( mb_strlen( $name ) < 5 || 11 !== strlen( $cpf ) ) {
				return null;
			}
			return array_merge(
				$base,
				array(
					'person_type'      => 'PF',
					'name'             => $name,
					'document'         => $cpf,
					'signer_name'      => $name,
					'signer_document'  => $cpf,
					'company'          => '',
					'company_document' => '',
				)
			);
		}
		$company = isset( $ident['razao_social'] ) ? trim( sanitize_text_field( (string) $ident['razao_social'] ) ) : '';
		$cnpj    = isset( $ident['cnpj'] ) ? Helpers::digits( $ident['cnpj'] ) : '';
		$rep     = null;
		foreach ( isset( $ident['representantes'] ) && is_array( $ident['representantes'] ) ? $ident['representantes'] : array() as $r ) {
			$rn = isset( $r['nome'] ) ? trim( sanitize_text_field( (string) $r['nome'] ) ) : '';
			$rc = isset( $r['cpf'] ) ? Helpers::digits( $r['cpf'] ) : '';
			if ( mb_strlen( $rn ) >= 5 && 11 === strlen( $rc ) ) {
				$rep = array( $rn, $rc );
				break;
			}
		}
		if ( mb_strlen( $company ) < 3 || 14 !== strlen( $cnpj ) || ! $rep ) {
			return null;
		}
		return array_merge(
			$base,
			array(
				'person_type'      => 'PJ',
				'name'             => $company,
				'document'         => $cnpj,
				'signer_name'      => $rep[0],
				'signer_document'  => $rep[1],
				'company'          => $company,
				'company_document' => $cnpj,
			)
		);
	}

	/**
	 * Texto final do documento (placeholders preenchidos, sem HTML). Vazio se o tipo não tem texto.
	 *
	 * @param string $doc_type   Tipo.
	 * @param array  $submission Solicitação.
	 * @return string
	 */
	public static function text( $doc_type, array $submission ) {
		$doc_type = sanitize_key( $doc_type );
		$signer   = self::signer( $submission );
		$vars     = array(
			'{protocolo}' => self::protocol_text( $submission ),
			'{nome}'      => $signer ? $signer['name'] : '',
			'{cpf}'       => $signer ? Helpers::format_document( $signer['document'] ) : '',
			'{data}'      => self::sao_paulo_date( time(), 'd/m/Y' ),
		);
		$text     = strtr( self::text_template( $doc_type ), $vars );
		/**
		 * Texto a ser assinado eletronicamente. Permite atender outros tipos de documento além da autorização SCR.
		 *
		 * @param string $text       Texto (placeholders já substituídos; vazio = tipo sem texto).
		 * @param string $doc_type   Tipo da matriz.
		 * @param array  $submission Solicitação.
		 */
		$text = (string) apply_filters( 'ebcr_esign_text', $text, $doc_type, $submission );
		$text = str_replace( array( "\r\n", "\r" ), "\n", wp_strip_all_tags( $text ) );
		return trim( $text );
	}

	/**
	 * Hash do texto (SHA-256, hex).
	 *
	 * @param string $text Texto.
	 * @return string
	 */
	public static function text_hash( $text ) {
		return hash( 'sha256', (string) $text );
	}

	/**
	 * Protocolo para exibição (rascunhos ainda não têm protocolo).
	 *
	 * @param array $submission Solicitação.
	 * @return string
	 */
	public static function protocol_text( array $submission ) {
		if ( ! empty( $submission['protocol'] ) ) {
			return (string) $submission['protocol'];
		}
		return sprintf( /* translators: %s: identificador da solicitação */ __( 'a ser gerado no envio (identificador %s)', 'eb-credito-rural' ), (string) $submission['public_id'] );
	}

	/**
	 * Normaliza nome para comparação (sem acentos, caixa ou espaços duplicados).
	 *
	 * @param string $name Nome.
	 * @return string
	 */
	public static function normalize_name( $name ) {
		$n = remove_accents( trim( (string) $name ) );
		$n = preg_replace( '/[^a-zA-Z\s]/', '', $n );
		$n = preg_replace( '/\s+/', ' ', $n );
		return strtolower( trim( $n ) );
	}

	// ------------------------------------------------------------------ elegibilidade

	/**
	 * Pode assinar este tipo agora?
	 *
	 * @param int    $user_id    Usuário.
	 * @param array  $submission Solicitação.
	 * @param string $doc_type   Tipo.
	 * @return true|\WP_Error
	 */
	public static function eligibility( $user_id, array $submission, $doc_type ) {
		$doc_type = sanitize_key( $doc_type );
		if ( ! self::is_enabled() ) {
			return new \WP_Error( 'disabled', __( 'A assinatura eletrônica não está disponível no momento. Envie o arquivo assinado.', 'eb-credito-rural' ), array( 'status' => 404 ) );
		}
		if ( ! Authorization::owns( $user_id, $submission ) || ! empty( $submission['anonymized_at'] ) ) {
			AuditLog::log( 'access_denied', 'submission', $submission['public_id'], array( 'op' => 'esign' ), $user_id );
			return new \WP_Error( 'forbidden', __( 'Você não pode assinar documentos desta solicitação.', 'eb-credito-rural' ), array( 'status' => 403 ) );
		}
		if ( ! self::is_signable( $doc_type, $submission ) ) {
			return new \WP_Error( 'not_signable', __( 'Este documento não pode ser assinado eletronicamente. Envie o arquivo assinado.', 'eb-credito-rural' ), array( 'status' => 400 ) );
		}
		if ( ! Authorization::client_can_upload( $user_id, $submission ) || ( ! Status::client_can_edit( $submission['status'] ) && ! self::open_request( $submission, $doc_type ) ) ) {
			return new \WP_Error( 'locked', __( 'Esta solicitação não aceita novos documentos no momento.', 'eb-credito-rural' ), array( 'status' => 403 ) );
		}
		if ( ! get_user_meta( $user_id, 'ebcr_email_verified', true ) ) {
			return new \WP_Error( 'email_unverified', __( 'Confirme seu e-mail antes de assinar: o código de assinatura é enviado para ele.', 'eb-credito-rural' ), array( 'status' => 403 ) );
		}
		if ( ! self::signer( $submission ) ) {
			return new \WP_Error( 'missing_identification', __( 'Preencha a etapa 1 (identificação) com nome completo e CPF — ou razão social, CNPJ e representante legal — antes de assinar.', 'eb-credito-rural' ), array( 'status' => 422 ) );
		}
		return true;
	}

	/**
	 * Pedido de documento aberto da equipe para este tipo (atendido pela assinatura).
	 *
	 * @param array  $submission Solicitação.
	 * @param string $doc_type   Tipo.
	 * @return array|null
	 */
	public static function open_request( array $submission, $doc_type ) {
		foreach ( ( new DocumentRequestRepository() )->for_submission( (int) $submission['id'], true ) as $r ) {
			if ( $r['doc_type'] === $doc_type && empty( $r['fulfilled_at'] ) ) {
				return $r;
			}
		}
		return null;
	}

	/**
	 * Documentos (ativos) gerados por assinatura eletrônica para o tipo, na solicitação.
	 *
	 * @param array  $submission Solicitação.
	 * @param string $doc_type   Tipo.
	 * @return array Documentos com a chave extra `signature` (linha da assinatura).
	 */
	public static function signed_documents( array $submission, $doc_type ) {
		$doc_type = sanitize_key( $doc_type );
		$sigs     = ( new SignatureRepository() )->for_submission( (int) $submission['id'], true );
		$by_doc   = array();
		foreach ( $sigs as $sig ) {
			if ( $sig['document_id'] && $sig['doc_type'] === $doc_type ) {
				$by_doc[ (int) $sig['document_id'] ] = $sig;
			}
		}
		if ( ! $by_doc ) {
			return array();
		}
		$out = array();
		foreach ( ( new DocumentRepository() )->for_submission( (int) $submission['id'] ) as $d ) {
			if ( isset( $by_doc[ (int) $d['id'] ] ) && $d['doc_type'] === $doc_type ) {
				$d['signature'] = $by_doc[ (int) $d['id'] ];
				$out[]          = $d;
			}
		}
		return $out;
	}

	// ------------------------------------------------------------------ código por e-mail

	/**
	 * Estado da emissão pendente (para a tela): null se não há código válido.
	 *
	 * @param int    $user_id    Usuário.
	 * @param array  $submission Solicitação.
	 * @param string $doc_type   Tipo.
	 * @return array|null expires (timestamp), issued_at, attempts, text_hash, signature (linha).
	 */
	public static function pending( $user_id, array $submission, $doc_type ) {
		$state = get_transient( self::transient_key( $user_id, $submission, $doc_type ) );
		if ( ! is_array( $state ) || empty( $state['expires'] ) || (int) $state['expires'] <= time() ) {
			return null;
		}
		$sig = ( new SignatureRepository() )->find( (int) $state['signature_id'] );
		if ( ! $sig || SignatureRepository::STATUS_PENDING !== $sig['status'] ) {
			return null;
		}
		return array(
			'expires'   => (int) $state['expires'],
			'issued_at' => (int) $state['issued_at'],
			'attempts'  => (int) $state['attempts'],
			'text_hash' => (string) $state['text_hash'],
			'signature' => $sig,
		);
	}

	/**
	 * Gera e envia um código de 6 dígitos (hash em transient), abrindo uma assinatura pendente.
	 *
	 * @param int    $user_id    Usuário.
	 * @param array  $submission Solicitação.
	 * @param string $doc_type   Tipo.
	 * @return array|\WP_Error signature, expires, email (mascarado) e, apenas em EBCR_TESTING, code.
	 */
	public static function issue_code( $user_id, array $submission, $doc_type ) {
		$doc_type = sanitize_key( $doc_type );
		$ok       = self::eligibility( $user_id, $submission, $doc_type );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$key     = self::limit_key( $user_id, $submission, $doc_type );
		$blocked = RateLimiter::blocked_for( 'esign_verify', $key );
		if ( $blocked ) {
			return new \WP_Error( 'locked_attempts', sprintf( /* translators: %d: minutos */ __( 'Muitas tentativas incorretas. Aguarde %d minutos para pedir um novo código.', 'eb-credito-rural' ), max( 1, (int) ceil( $blocked / 60 ) ) ), array( 'status' => 429 ) );
		}
		if ( ! RateLimiter::hit( 'esign_send', $key, self::MAX_SENDS, self::CODE_TTL ) || ! RateLimiter::hit( 'esign_send_user', (string) $user_id, 12, HOUR_IN_SECONDS ) ) {
			AuditLog::log( 'esign_send_limited', 'submission', $submission['public_id'], array( 'type' => $doc_type ), $user_id );
			return new \WP_Error( 'too_many_sends', __( 'Você já pediu vários códigos. Verifique sua caixa de entrada (e o spam) ou aguarde alguns minutos para pedir outro.', 'eb-credito-rural' ), array( 'status' => 429 ) );
		}
		$signer    = self::signer( $submission );
		$text      = self::text( $doc_type, $submission );
		$text_hash = self::text_hash( $text );
		$repo      = new SignatureRepository();
		$repo->cancel_pending( (int) $submission['id'], $user_id, $doc_type );
		$sig  = $repo->create(
			array(
				'submission_id'   => (int) $submission['id'],
				'user_id'         => (int) $user_id,
				'doc_type'        => $doc_type,
				'signer_name'     => mb_substr( $signer['signer_name'], 0, 190 ),
				'signer_document' => $signer['signer_document'],
				'signer_email'    => mb_substr( $signer['email'], 0, 190 ),
				'text_hash'       => $text_hash,
				'ip'              => Ip::get(),
				'user_agent'      => mb_substr( Ip::user_agent(), 0, 255 ),
				'method'          => self::METHOD,
			)
		);
		$code = str_pad( (string) random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT );
		$now  = time();
		set_transient(
			self::transient_key( $user_id, $submission, $doc_type ),
			array(
				'hash'         => self::code_hash( $code, $sig['public_id'] ),
				'text_hash'    => $text_hash,
				'signature_id' => (int) $sig['id'],
				'issued_at'    => $now,
				'expires'      => $now + self::CODE_TTL,
				'attempts'     => 0,
			),
			self::CODE_TTL
		);
		Mailer::send_event(
			'esign_code',
			$signer['email'],
			array(
				'nome'        => $signer['signer_name'],
				'protocolo'   => ! empty( $submission['protocol'] ) ? $submission['protocol'] : __( 'em preenchimento', 'eb-credito-rural' ),
				'documento'   => self::title( $doc_type ),
				'codigo'      => $code,
				'link_portal' => Esign::sign_url( $submission, $doc_type ),
			)
		);
		AuditLog::log(
			'esign_code_sent',
			'signature',
			$sig['public_id'],
			array(
				'submission' => $submission['public_id'],
				'type'       => $doc_type,
			),
			$user_id
		);
		$out = array(
			'signature' => $sig,
			'expires'   => $now + self::CODE_TTL,
			'email'     => self::mask_email( $signer['email'] ),
		);
		if ( defined( 'EBCR_TESTING' ) && EBCR_TESTING ) {
			$out['code'] = $code;
		}
		return $out;
	}

	/**
	 * Verifica código, nome e aceite; gera o PDF assinado e o registra como documento da solicitação.
	 *
	 * @param int    $user_id    Usuário.
	 * @param array  $submission Solicitação.
	 * @param string $doc_type   Tipo.
	 * @param array  $input      codigo, nome_confirmacao, aceite.
	 * @return array|\WP_Error signature, document. Erros de campo em get_error_data()['errors'].
	 */
	public static function sign( $user_id, array $submission, $doc_type, array $input ) {
		$doc_type = sanitize_key( $doc_type );
		$ok       = self::eligibility( $user_id, $submission, $doc_type );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$key     = self::limit_key( $user_id, $submission, $doc_type );
		$blocked = RateLimiter::blocked_for( 'esign_verify', $key );
		if ( $blocked ) {
			return self::field_error( 'locked_attempts', 'codigo', sprintf( /* translators: %d: minutos */ __( 'Muitas tentativas incorretas. Aguarde %d minutos e peça um novo código.', 'eb-credito-rural' ), max( 1, (int) ceil( $blocked / 60 ) ) ), 429 );
		}
		$tkey  = self::transient_key( $user_id, $submission, $doc_type );
		$state = get_transient( $tkey );
		$repo  = new SignatureRepository();
		$sig   = is_array( $state ) && ! empty( $state['signature_id'] ) ? $repo->find( (int) $state['signature_id'] ) : null;
		if ( ! is_array( $state ) || empty( $state['expires'] ) || (int) $state['expires'] <= time() || ! $sig || SignatureRepository::STATUS_PENDING !== $sig['status'] ) {
			delete_transient( $tkey );
			$repo->cancel_pending( (int) $submission['id'], $user_id, $doc_type );
			return self::field_error( 'expired', 'codigo', __( 'Código expirado ou não solicitado. Peça um novo código.', 'eb-credito-rural' ), 410 );
		}
		$signer = self::signer( $submission );
		$errors = array();
		if ( empty( $input['aceite'] ) ) {
			$errors['aceite'] = __( 'Marque a declaração para assinar.', 'eb-credito-rural' );
		}
		$typed = isset( $input['nome_confirmacao'] ) ? sanitize_text_field( (string) $input['nome_confirmacao'] ) : '';
		if ( '' === self::normalize_name( $typed ) || self::normalize_name( $typed ) !== self::normalize_name( $signer['signer_name'] ) ) {
			$errors['nome_confirmacao'] = sprintf( /* translators: %s: nome da etapa 1 */ __( 'Digite o nome completo exatamente como informado na etapa 1 (%s).', 'eb-credito-rural' ), $signer['signer_name'] );
		}
		$code = isset( $input['codigo'] ) ? Helpers::digits( $input['codigo'] ) : '';
		if ( 6 !== strlen( $code ) ) {
			$errors['codigo'] = __( 'Informe o código de 6 dígitos recebido por e-mail.', 'eb-credito-rural' );
		}
		if ( $errors ) {
			return new \WP_Error(
				'validation',
				__( 'Verifique os campos.', 'eb-credito-rural' ),
				array(
					'errors' => $errors,
					'status' => 422,
				)
			);
		}
		if ( ! hash_equals( (string) $state['hash'], self::code_hash( $code, $sig['public_id'] ) ) ) {
			$state['attempts'] = (int) $state['attempts'] + 1;
			$allowed           = RateLimiter::hit( 'esign_verify', $key, self::MAX_ATTEMPTS, self::CODE_TTL );
			if ( ! $allowed || $state['attempts'] >= self::MAX_ATTEMPTS ) {
				delete_transient( $tkey );
				$repo->cancel_pending( (int) $submission['id'], $user_id, $doc_type );
				RateLimiter::lock( 'esign_verify', $key, self::LOCK_SECONDS );
				AuditLog::log(
					'esign_locked',
					'signature',
					$sig['public_id'],
					array(
						'submission' => $submission['public_id'],
						'type'       => $doc_type,
					),
					$user_id
				);
				return self::field_error( 'too_many_attempts', 'codigo', __( 'Número máximo de tentativas atingido. O código foi invalidado; aguarde 15 minutos e peça um novo.', 'eb-credito-rural' ), 429 );
			}
			set_transient( $tkey, $state, max( 1, (int) $state['expires'] - time() ) );
			AuditLog::log(
				'esign_code_failed',
				'signature',
				$sig['public_id'],
				array(
					'submission' => $submission['public_id'],
					'attempts'   => $state['attempts'],
				),
				$user_id
			);
			$left = self::MAX_ATTEMPTS - (int) $state['attempts'];
			return self::field_error( 'bad_code', 'codigo', sprintf( /* translators: %d: tentativas restantes */ _n( 'Código incorreto. Resta %d tentativa.', 'Código incorreto. Restam %d tentativas.', $left, 'eb-credito-rural' ), $left ), 422 );
		}
		$text      = self::text( $doc_type, $submission );
		$text_hash = self::text_hash( $text );
		if ( ! hash_equals( (string) $state['text_hash'], $text_hash ) ) {
			delete_transient( $tkey );
			$repo->cancel_pending( (int) $submission['id'], $user_id, $doc_type );
			return self::field_error( 'text_changed', '_', __( 'O texto do documento mudou desde o envio do código. Leia o novo texto e peça outro código.', 'eb-credito-rural' ), 409 );
		}
		$previous = self::signed_documents( $submission, $doc_type );
		foreach ( $previous as $old ) {
			if ( 'aceito' === $old['review_status'] ) {
				return self::field_error( 'already_accepted', '_', __( 'Este documento já foi assinado e aceito pela equipe; não é necessário assinar de novo.', 'eb-credito-rural' ), 409 );
			}
		}
		$verified_at   = time();
		$ip            = Ip::get();
		$ua            = mb_substr( Ip::user_agent(), 0, 255 );
		$evidence      = array(
			'signature_id'    => $sig['public_id'],
			'submission_id'   => $submission['public_id'],
			'protocol'        => (string) $submission['protocol'],
			'doc_type'        => $doc_type,
			'signer_name'     => $signer['signer_name'],
			'signer_document' => $signer['signer_document'],
			'signer_email'    => $signer['email'],
			'user_id'         => (int) $user_id,
			'user_login'      => $signer['user_login'],
			'company'         => $signer['company'],
			'company_doc'     => $signer['company_document'],
			'method'          => self::METHOD,
			'code_sent_at'    => gmdate( 'Y-m-d\TH:i:s\Z', (int) $state['issued_at'] ),
			'verified_at'     => gmdate( 'Y-m-d\TH:i:s\Z', $verified_at ),
			'ip'              => $ip,
			'user_agent'      => $ua,
			'text_hash'       => $text_hash,
		);
		$evidence_hash = hash( 'sha256', (string) wp_json_encode( $evidence, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
		$pdf           = self::build_pdf( $doc_type, $submission, $signer, $text, $evidence, $evidence_hash, (int) $state['issued_at'], $verified_at );
		$tmp           = tempnam( get_temp_dir(), 'ebcr-esign' );
		if ( ! $tmp || false === file_put_contents( $tmp, $pdf ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- arquivo temporário local.
			return self::field_error( 'storage', '_', __( 'Não foi possível gerar o PDF. Tente novamente.', 'eb-credito-rural' ), 500 );
		}
		$request = Status::client_can_edit( $submission['status'] ) ? null : self::open_request( $submission, $doc_type );
		$row     = ( new UploadHandler() )->register_generated(
			$user_id,
			$submission,
			$tmp,
			$doc_type,
			self::file_name( $doc_type, $submission ),
			'application/pdf',
			'',
			$request ? (int) $request['id'] : 0,
			array(
				'source'    => 'esign',
				'signature' => $sig['public_id'],
			)
		);
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		// Substitui o PDF gerado anteriormente (pendente/recusado) no mesmo slot; uploads manuais não são tocados.
		$storage = new Storage();
		$docs    = new DocumentRepository();
		foreach ( $previous as $old ) {
			$storage->delete( $old );
			$docs->soft_delete( (int) $old['id'] );
			AuditLog::log(
				'document_deleted',
				'document',
				$old['public_id'],
				array(
					'reason'      => 'esign_replaced',
					'replaced_by' => $row['public_id'],
				),
				$user_id
			);
		}
		$repo->update(
			(int) $sig['id'],
			array(
				'document_id'     => (int) $row['id'],
				'signer_name'     => mb_substr( $signer['signer_name'], 0, 190 ),
				'signer_document' => $signer['signer_document'],
				'signer_email'    => mb_substr( $signer['email'], 0, 190 ),
				'text_hash'       => $text_hash,
				'evidence_hash'   => $evidence_hash,
				'ip'              => $ip,
				'user_agent'      => $ua,
				'method'          => self::METHOD,
				'status'          => SignatureRepository::STATUS_SIGNED,
				'signed_at'       => gmdate( 'Y-m-d H:i:s', $verified_at ),
			)
		);
		$signature = $repo->find( (int) $sig['id'] );
		delete_transient( $tkey );
		RateLimiter::clear( 'esign_verify', $key );
		RateLimiter::clear( 'esign_send', $key );
		AuditLog::log(
			'esign_signed',
			'signature',
			$signature['public_id'],
			array(
				'submission'    => $submission['public_id'],
				'document'      => $row['public_id'],
				'type'          => $doc_type,
				'text_hash'     => $text_hash,
				'evidence_hash' => $evidence_hash,
			),
			$user_id
		);
		if ( ! empty( $row['request_id'] ) ) {
			Notifier::client_responded( $submission, $row );
		}
		/**
		 * Documento assinado eletronicamente.
		 *
		 * @param array $signature  Linha da assinatura.
		 * @param array $document   Documento gerado.
		 * @param array $submission Solicitação.
		 */
		do_action( 'ebcr_esign_signed', $signature, $row, $submission );
		return array(
			'signature' => $signature,
			'document'  => $row,
		);
	}

	/**
	 * Cancela a emissão pendente (código e assinatura pendente).
	 *
	 * @param int    $user_id    Usuário.
	 * @param array  $submission Solicitação.
	 * @param string $doc_type   Tipo.
	 * @return bool Havia algo pendente.
	 */
	public static function cancel( $user_id, array $submission, $doc_type ) {
		$doc_type = sanitize_key( $doc_type );
		if ( ! Authorization::owns( $user_id, $submission ) ) {
			return false;
		}
		$tkey = self::transient_key( $user_id, $submission, $doc_type );
		$had  = (bool) get_transient( $tkey );
		delete_transient( $tkey );
		$n = ( new SignatureRepository() )->cancel_pending( (int) $submission['id'], $user_id, $doc_type );
		if ( $had || $n ) {
			AuditLog::log(
				'esign_cancelled',
				'submission',
				$submission['public_id'],
				array( 'type' => $doc_type ),
				$user_id
			);
		}
		return $had || $n > 0;
	}

	// ------------------------------------------------------------------ PDF e formatação

	/**
	 * Monta o PDF assinado com texto, assinante e evidências.
	 *
	 * @param string $doc_type      Tipo.
	 * @param array  $submission    Solicitação.
	 * @param array  $signer        Assinante.
	 * @param string $text          Texto assinado.
	 * @param array  $evidence      Evidências (payload).
	 * @param string $evidence_hash Hash das evidências.
	 * @param int    $sent_at       Timestamp do envio do código.
	 * @param int    $verified_at   Timestamp da verificação.
	 * @return string Bytes do PDF.
	 */
	private static function build_pdf( $doc_type, array $submission, array $signer, $text, array $evidence, $evidence_hash, $sent_at, $verified_at ) {
		$title = sprintf( /* translators: %s: título do documento */ __( '%s — assinatura eletrônica', 'eb-credito-rural' ), self::title( $doc_type ) );
		$w     = new Writer( $title );
		$w->h1( $title );
		$w->kv(
			array(
				__( 'Protocolo', 'eb-credito-rural' )   => self::protocol_text( $submission ),
				__( 'Solicitação', 'eb-credito-rural' ) => (string) $submission['public_id'],
				__( 'Emitido em', 'eb-credito-rural' )  => self::sao_paulo_date( $verified_at ),
			)
		);
		$w->h2( __( 'Texto assinado', 'eb-credito-rural' ) );
		foreach ( preg_split( '/\n{2,}/', $text ) as $para ) {
			$para = trim( str_replace( "\n", ' ', $para ) );
			if ( '' !== $para ) {
				$w->p( $para );
			}
		}
		$w->h2( __( 'Assinante', 'eb-credito-rural' ) );
		$signer_rows = array(
			__( 'Nome', 'eb-credito-rural' ) => $signer['signer_name'],
			__( 'CPF', 'eb-credito-rural' )  => Helpers::format_document( $signer['signer_document'] ),
		);
		if ( 'PJ' === $signer['person_type'] ) {
			$signer_rows[ __( 'Representando', 'eb-credito-rural' ) ] = $signer['company'] . ' — CNPJ ' . Helpers::format_document( $signer['company_document'] );
		}
		$signer_rows[ __( 'E-mail', 'eb-credito-rural' ) ]  = $signer['email'];
		$signer_rows[ __( 'Usuário', 'eb-credito-rural' ) ] = sprintf( '%s (#%d)', $signer['user_login'], (int) $signer['user_id'] );
		$w->kv( $signer_rows );
		$w->h2( __( 'Evidências', 'eb-credito-rural' ) );
		$w->kv(
			array(
				__( 'Data/hora (UTC)', 'eb-credito-rural' ) => gmdate( 'd/m/Y H:i:s', $verified_at ) . ' UTC',
				__( 'Data/hora (São Paulo)', 'eb-credito-rural' ) => self::sao_paulo_date( $verified_at ),
				__( 'Endereço IP', 'eb-credito-rural' ) => $evidence['ip'],
				__( 'Navegador (user agent)', 'eb-credito-rural' ) => $evidence['user_agent'],
				__( 'Método', 'eb-credito-rural' )      => sprintf( /* translators: 1: e-mail, 2: data de envio, 3: data de verificação */ __( 'código de uso único enviado ao e-mail %1$s em %2$s e verificado em %3$s', 'eb-credito-rural' ), self::mask_email( $signer['email'] ), self::sao_paulo_date( $sent_at ), self::sao_paulo_date( $verified_at ) ),
				__( 'Protocolo', 'eb-credito-rural' )   => self::protocol_text( $submission ),
				__( 'Hash SHA-256 do texto', 'eb-credito-rural' ) => $evidence['text_hash'],
				__( 'Hash SHA-256 das evidências', 'eb-credito-rural' ) => $evidence_hash,
				__( 'Identificador público da assinatura', 'eb-credito-rural' ) => $evidence['signature_id'],
			)
		);
		$w->p( __( 'Assinatura eletrônica simples nos termos da Lei 14.063/2020 e da MP 2.200-2/2001; a integridade pode ser conferida pelos hashes acima.', 'eb-credito-rural' ) );
		return $w->output();
	}

	/**
	 * Nome do arquivo gerado (ex.: autorizacao-scr-assinada-EB-2026-000012.pdf).
	 *
	 * @param string $doc_type   Tipo.
	 * @param array  $submission Solicitação.
	 * @return string
	 */
	public static function file_name( $doc_type, array $submission ) {
		$slug = str_replace( '_', '-', sanitize_key( $doc_type ) );
		$ref  = ! empty( $submission['protocol'] ) ? (string) $submission['protocol'] : substr( (string) $submission['public_id'], 0, 8 );
		return sanitize_file_name( $slug . '-assinada-' . $ref . '.pdf' );
	}

	/**
	 * Data/hora no fuso de São Paulo.
	 *
	 * @param int    $timestamp Timestamp.
	 * @param string $format    Formato.
	 * @return string
	 */
	public static function sao_paulo_date( $timestamp, $format = 'd/m/Y H:i:s' ) {
		try {
			$dt = ( new \DateTimeImmutable( '@' . (int) $timestamp ) )->setTimezone( new \DateTimeZone( 'America/Sao_Paulo' ) );
			return $dt->format( $format ) . ( 'd/m/Y' === $format ? '' : ' (Brasília)' );
		} catch ( \Exception $e ) {
			return gmdate( $format, (int) $timestamp );
		}
	}

	/**
	 * Mascara e-mail para exibição (ma***@dominio).
	 *
	 * @param string $email E-mail.
	 * @return string
	 */
	public static function mask_email( $email ) {
		$email = (string) $email;
		$at    = strpos( $email, '@' );
		if ( false === $at ) {
			return '***';
		}
		$local = substr( $email, 0, $at );
		return substr( $local, 0, min( 2, strlen( $local ) ) ) . '***' . substr( $email, $at );
	}

	// ------------------------------------------------------------------ internos

	/**
	 * Erro com mapa de campo.
	 *
	 * @param string $code    Código.
	 * @param string $field   Campo ('_' = geral).
	 * @param string $message Mensagem.
	 * @param int    $status  HTTP.
	 * @return \WP_Error
	 */
	private static function field_error( $code, $field, $message, $status ) {
		return new \WP_Error(
			$code,
			$message,
			array(
				'errors' => array( $field => $message ),
				'status' => (int) $status,
			)
		);
	}

	/**
	 * Chave do transient do código.
	 *
	 * @param int    $user_id    Usuário.
	 * @param array  $submission Solicitação.
	 * @param string $doc_type   Tipo.
	 * @return string
	 */
	private static function transient_key( $user_id, array $submission, $doc_type ) {
		return 'ebcr_esign_' . md5( (int) $user_id . '|' . (int) $submission['id'] . '|' . sanitize_key( $doc_type ) );
	}

	/**
	 * Chave do rate limit.
	 *
	 * @param int    $user_id    Usuário.
	 * @param array  $submission Solicitação.
	 * @param string $doc_type   Tipo.
	 * @return string
	 */
	private static function limit_key( $user_id, array $submission, $doc_type ) {
		return (int) $user_id . ':' . (int) $submission['id'] . ':' . sanitize_key( $doc_type );
	}

	/**
	 * Hash do código (HMAC com sal do site + id da assinatura).
	 *
	 * @param string $code         Código.
	 * @param string $signature_id UUID da assinatura pendente.
	 * @return string
	 */
	private static function code_hash( $code, $signature_id ) {
		return hash_hmac( 'sha256', (string) $code . '|' . (string) $signature_id, wp_salt( 'nonce' ) );
	}
}
