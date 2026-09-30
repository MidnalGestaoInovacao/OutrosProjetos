<?php
/**
 * Complemento de bens e garantias depois do envio (modo "Opcional" em Configurações → Bens e garantias).
 *
 * O cliente inclui ou corrige imóveis (etapa 2) e garantias (etapa 5) pela área dele, com as mesmas regras de
 * validação e sanitização do formulário (Steps/Validator — o navegador nunca é a fonte da verdade), enquanto a
 * solicitação estiver em um dos status de "complement_statuses". Cada complemento grava histórico e auditoria,
 * avisa a equipe (evento complement_added) e abre pedidos para os documentos que os novos itens passam a exigir.
 *
 * @package EBCR
 */

namespace EBCR\Forms;

use EBCR\Database\DocumentRequestRepository;
use EBCR\Database\GuaranteeRepository;
use EBCR\Database\PropertyRepository;
use EBCR\Database\StatusHistoryRepository;
use EBCR\Database\SubmissionDataRepository;
use EBCR\Database\SubmissionRepository;
use EBCR\Domain\Status;
use EBCR\Mail\Notifier;
use EBCR\Security\AuditLog;
use EBCR\Security\Authorization;
use EBCR\Support\Helpers;
use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Regras, gravação e lembretes do complemento.
 */
final class Complement {

	/**
	 * Partes complementáveis => etapa do formulário.
	 */
	const PARTS = array(
		'imoveis'   => 2,
		'garantias' => 5,
	);

	/**
	 * Níveis da matriz que geram pedido de documento quando um item novo passa a exigi-lo.
	 */
	const REQUEST_LEVELS = array( 'sim', 'recomendado' );

	/**
	 * Origem gravada nos pedidos de documento abertos por complemento.
	 */
	const ORIGIN = 'complemento';

	/**
	 * Janela (dias) além do prazo do lembrete: solicitações enviadas há mais tempo não recebem o lembrete
	 * (evita um envio em lote para solicitações antigas logo após a atualização).
	 */
	const REMINDER_WINDOW_DAYS = 30;

	/**
	 * Status padrão em que o complemento é aceito: todos os em andamento, exceto aprovada (e nunca rascunho,
	 * que é editado pelo próprio formulário, nem os finais).
	 *
	 * @return string[]
	 */
	public static function default_statuses() {
		return array( Status::SUBMITTED, Status::PRE_ANALYSIS, Status::PENDING_DOCS, Status::CREDIT, Status::COMMITTEE, Status::FORMALIZATION );
	}

	/**
	 * Opções da configuração (todos os status, menos rascunho).
	 *
	 * @return array status => rótulo
	 */
	public static function status_options() {
		$out = array();
		foreach ( Status::all() as $key => $def ) {
			if ( Status::DRAFT !== $key ) {
				$out[ $key ] = (string) $def['label'];
			}
		}
		return $out;
	}

	/**
	 * Status configurados.
	 *
	 * @return string[]
	 */
	public static function statuses() {
		$raw = Options::get( 'complement_statuses', self::default_statuses() );
		if ( is_string( $raw ) ) {
			$raw = preg_split( '/[\s,;|]+/', $raw );
		}
		return array_values( array_intersect( array_map( 'sanitize_key', array_map( 'strval', (array) $raw ) ), array_keys( self::status_options() ) ) );
	}

	/**
	 * O status aceita complemento?
	 *
	 * @param string $status Status.
	 * @return bool
	 */
	public static function status_allows( $status ) {
		return Status::DRAFT !== $status && in_array( (string) $status, self::statuses(), true );
	}

	/**
	 * Partes em modo opcional (as únicas complementáveis depois do envio).
	 *
	 * @param array $context Contexto das regras (Wizard::rules_context()).
	 * @return array{imoveis:bool,garantias:bool}
	 */
	public static function parts( array $context = array() ) {
		return array(
			'imoveis'   => SubmissionRules::MODE_OPTIONAL === SubmissionRules::assets_mode() && SubmissionRules::assets_active( $context ),
			'garantias' => SubmissionRules::MODE_OPTIONAL === SubmissionRules::guarantees_mode() && SubmissionRules::guarantees_active( $context ),
		);
	}

	/**
	 * Alguma parte em modo opcional?
	 *
	 * @param array $context Contexto.
	 * @return bool
	 */
	public static function enabled( array $context = array() ) {
		return (bool) array_filter( self::parts( $context ) );
	}

	/**
	 * Texto de orientação (configurável) de uma parte.
	 *
	 * @param string $part imoveis | garantias.
	 * @return string
	 */
	public static function notice( $part ) {
		$key = 'garantias' === $part ? 'guarantees_optional_notice' : 'assets_optional_notice';
		$txt = trim( (string) Options::get( $key, '' ) );
		if ( '' === $txt ) {
			$def = Options::defaults();
			$txt = isset( $def[ $key ] ) ? (string) $def[ $key ] : '';
		}
		return $txt;
	}

	/**
	 * Rótulo de uma parte.
	 *
	 * @param string $part Parte.
	 * @return string
	 */
	public static function part_label( $part ) {
		return 'garantias' === $part ? __( 'Garantias', 'eb-credito-rural' ) : __( 'Imóveis rurais / bens', 'eb-credito-rural' );
	}

	/**
	 * "bens e garantias" / "imóveis/bens" / "garantias" para frases.
	 *
	 * @param string[] $parts Partes.
	 * @return string
	 */
	public static function parts_phrase( array $parts ) {
		$parts = array_values( array_intersect( array_keys( self::PARTS ), $parts ) );
		if ( 2 === count( $parts ) ) {
			return __( 'bens e garantias', 'eb-credito-rural' );
		}
		return array( 'garantias' ) === $parts ? __( 'garantias', 'eb-credito-rural' ) : __( 'imóveis/bens', 'eb-credito-rural' );
	}

	/**
	 * Chamada do aviso no painel do cliente ("Sua solicitação … está sem … informados — completar agora").
	 *
	 * @param string   $protocol Protocolo.
	 * @param string[] $parts    Partes em falta.
	 * @return string
	 */
	public static function missing_title( $protocol, array $parts ) {
		$parts = array_values( array_intersect( array_keys( self::PARTS ), $parts ) );
		if ( 2 === count( $parts ) ) {
			/* translators: %s: protocolo */
			return sprintf( __( 'Sua solicitação %s está sem bens e garantias informados — completar agora', 'eb-credito-rural' ), $protocol );
		}
		if ( array( 'garantias' ) === $parts ) {
			/* translators: %s: protocolo */
			return sprintf( __( 'Sua solicitação %s está sem garantias informadas — completar agora', 'eb-credito-rural' ), $protocol );
		}
		/* translators: %s: protocolo */
		return sprintf( __( 'Sua solicitação %s está sem imóveis/bens informados — completar agora', 'eb-credito-rural' ), $protocol );
	}

	/**
	 * O cliente pode complementar esta solicitação (e esta parte) agora?
	 *
	 * @param int    $user_id    Usuário.
	 * @param array  $submission Linha.
	 * @param string $part       Parte ('' = qualquer).
	 * @return bool
	 */
	public static function can_complement( $user_id, array $submission, $part = '' ) {
		if ( ! Authorization::owns( $user_id, $submission ) || ! empty( $submission['anonymized_at'] ) || ! self::status_allows( $submission['status'] ) ) {
			return false;
		}
		$parts = self::parts( ( new Wizard() )->rules_context( $submission ) );
		return '' === $part ? (bool) array_filter( $parts ) : ! empty( $parts[ $part ] );
	}

	/**
	 * Partes opcionais sem nenhum item informado (enviada sem imóveis e/ou garantias).
	 *
	 * @param array      $submission Linha.
	 * @param array|null $saved      Dados já carregados (Wizard::saved()).
	 * @return string[]
	 */
	public static function missing( array $submission, $saved = null ) {
		if ( Status::DRAFT === $submission['status'] ) {
			return array();
		}
		$wizard = new Wizard();
		$saved  = is_array( $saved ) ? $saved : $wizard->saved( $submission );
		$parts  = self::parts( $wizard->rules_context( $submission, $saved ) );
		$out    = array();
		if ( $parts['imoveis'] && ! $saved['imoveis']['imoveis'] ) {
			$out[] = 'imoveis';
		}
		if ( $parts['garantias'] && ! $saved['garantias']['garantias'] ) {
			$out[] = 'garantias';
		}
		return $out;
	}

	/**
	 * Item incluído depois do envio?
	 *
	 * @param array $item       Imóvel/garantia.
	 * @param array $submission Solicitação.
	 * @return bool
	 */
	public static function added_after_submission( array $item, array $submission ) {
		return ! empty( $submission['submitted_at'] ) && ! empty( $item['created_at'] ) && (string) $item['created_at'] > (string) $submission['submitted_at'];
	}

	/**
	 * Item alterado depois do envio (sem ter sido incluído depois)?
	 *
	 * @param array $item       Imóvel/garantia.
	 * @param array $submission Solicitação.
	 * @return bool
	 */
	public static function changed_after_submission( array $item, array $submission ) {
		return ! empty( $submission['submitted_at'] ) && ! empty( $item['updated_at'] ) && (string) $item['updated_at'] > (string) $submission['submitted_at'] && ! self::added_after_submission( $item, $submission );
	}

	/**
	 * Observação exibida nas telas ("Incluído pelo cliente em … depois do envio").
	 *
	 * @param array $item       Imóvel/garantia.
	 * @param array $submission Solicitação.
	 * @return string
	 */
	public static function item_note( array $item, array $submission ) {
		if ( self::added_after_submission( $item, $submission ) ) {
			/* translators: %s: data */
			return sprintf( __( 'Incluído pelo cliente em %s, depois do envio.', 'eb-credito-rural' ), Helpers::date( $item['created_at'] ) );
		}
		if ( self::changed_after_submission( $item, $submission ) ) {
			/* translators: %s: data */
			return sprintf( __( 'Alterado pelo cliente em %s, depois do envio.', 'eb-credito-rural' ), Helpers::date( $item['updated_at'] ) );
		}
		return '';
	}

	/**
	 * Datas de um item (abilities/relatórios).
	 *
	 * @param array $item       Imóvel/garantia.
	 * @param array $submission Solicitação.
	 * @return array
	 */
	public static function item_info( array $item, array $submission ) {
		return array(
			'id'                       => (int) $item['id'],
			'created_at'               => isset( $item['created_at'] ) ? $item['created_at'] : null,
			'updated_at'               => isset( $item['updated_at'] ) ? $item['updated_at'] : null,
			'added_after_submission'   => self::added_after_submission( $item, $submission ),
			'changed_after_submission' => self::changed_after_submission( $item, $submission ),
		);
	}

	/**
	 * Resumo curto de um item (e-mail/histórico).
	 *
	 * @param string $part Parte.
	 * @param array  $item Item.
	 * @return string
	 */
	public static function item_text( $part, array $item ) {
		if ( 'garantias' === $part ) {
			$types = Steps::options( 'guarantee_types' );
			$type  = isset( $types[ $item['type'] ] ) ? $types[ $item['type'] ] : (string) $item['type'];
			return $type . ' — ' . Helpers::money( $item['declared_value'] );
		}
		return trim( (string) $item['name'] ) . ' — ' . trim( (string) $item['city'] ) . '/' . (string) $item['uf'];
	}

	/**
	 * Grava o complemento de uma parte. Retorna [ok, erros (campo => mensagem), resultado|null].
	 * Resultado: added, updated, requests (pedidos abertos), submission (linha atualizada).
	 *
	 * Itens já informados não são removidos pelo cliente depois do envio (a equipe pode tê-los avaliado): uma linha
	 * existente ausente no envio é mantida como está.
	 *
	 * @param int    $user_id    Usuário (dono).
	 * @param array  $submission Linha.
	 * @param string $part       imoveis | garantias.
	 * @param array  $input      Entrada bruta (wp_unslash($_POST)).
	 * @return array
	 */
	public static function save( $user_id, array $submission, $part, array $input ) {
		$part = sanitize_key( (string) $part );
		if ( ! isset( self::PARTS[ $part ] ) || ! self::can_complement( $user_id, $submission, $part ) ) {
			AuditLog::log(
				'access_denied',
				'submission',
				$submission['public_id'],
				array(
					'op'   => 'complement',
					'part' => $part,
				),
				$user_id
			);
			return array( false, array( '_' => __( 'Não é possível completar bens e garantias desta solicitação agora.', 'eb-credito-rural' ) ), null );
		}
		$sid     = (int) $submission['id'];
		$wizard  = new Wizard();
		$saved   = $wizard->saved( $submission );
		$context = $wizard->rules_context( $submission, $saved );
		$before  = self::slot_keys( $wizard->document_slots( $submission ) );
		$rows    = isset( $input[ $part ] ) && is_array( $input[ $part ] ) ? $input[ $part ] : array();
		if ( 'imoveis' === $part ) {
			$current_rows           = $saved['imoveis']['imoveis'];
			$fields                 = PropertyRepository::CLIENT_FIELDS;
			list( $clean, $errors ) = Steps::validate(
				2,
				array(
					'imoveis'        => $rows,
					'possui_imoveis' => 'sim',
				),
				$context
			);
			$items                  = $clean['imoveis'];
		} else {
			$current_rows           = $saved['garantias']['garantias'];
			$fields                 = GuaranteeRepository::CLIENT_FIELDS;
			list( $clean, $errors ) = Steps::validate(
				5,
				array(
					'garantias'        => $rows,
					'oferece_garantia' => 'sim',
				),
				$context
			);
			$items                  = $clean['garantias'];
		}
		unset( $errors['possui_imoveis'], $errors['oferece_garantia'] );
		if ( isset( $errors[ $part ] ) ) {
			$errors[ $part ] = 'imoveis' === $part ? __( 'Informe pelo menos um imóvel rural.', 'eb-credito-rural' ) : __( 'Informe pelo menos uma garantia.', 'eb-credito-rural' );
		}
		if ( $errors ) {
			return array( false, $errors, null );
		}
		$current = array();
		foreach ( $current_rows as $r ) {
			$current[ (int) $r['id'] ] = $r;
		}
		$by_id   = array();
		$new     = array();
		$updated = 0;
		foreach ( $items as $it ) {
			$id = isset( $it['id'] ) ? (int) $it['id'] : 0;
			if ( $id && isset( $current[ $id ] ) && ! isset( $by_id[ $id ] ) ) {
				$by_id[ $id ] = $it;
				if ( PropertyRepository::changed( $current[ $id ], $it, $fields ) ) {
					++$updated;
				}
				continue;
			}
			$it['id'] = 0; // ID desconhecido (ou repetido) = item novo desta solicitação.
			$new[]    = $it;
		}
		if ( ! $new && ! $updated ) {
			return array( false, array( '_' => __( 'Nenhuma alteração para salvar: inclua um item novo ou altere um dos já informados.', 'eb-credito-rural' ) ), null );
		}
		$final = array();
		foreach ( $current as $id => $row ) {
			$final[] = isset( $by_id[ $id ] ) ? $by_id[ $id ] : $row;
		}
		$final = array_merge( $final, $new );
		$data  = new SubmissionDataRepository();
		if ( 'imoveis' === $part ) {
			$ids = ( new PropertyRepository() )->replace_all( $sid, $final );
			$data->save(
				$sid,
				'imoveis',
				array(
					'_incomplete'    => false,
					'count'          => count( $ids ),
					'possui_imoveis' => 'sim',
				)
			);
		} else {
			( new GuaranteeRepository() )->replace_all( $sid, $final );
			$real = Steps::options( 'real_guarantees' );
			$data->save(
				$sid,
				'garantias',
				array(
					'_incomplete'      => false,
					'real_guarantee'   => (bool) array_filter(
						$final,
						static function ( $g ) use ( $real ) {
							return in_array( $g['type'], $real, true );
						}
					),
					'count'            => count( $final ),
					'oferece_garantia' => 'sim',
					'sem_garantia'     => false,
				)
			);
		}
		$subs = new SubmissionRepository();
		$subs->update( $sid, array( 'complemented_at' => current_time( 'mysql', true ) ) );
		$fresh    = $subs->find( $sid );
		$requests = self::open_requests( $fresh, $before );
		$added    = count( $new );
		$docs     = wp_list_pluck( $requests, 'label' );
		$label    = 'imoveis' === $part ? __( 'imóveis/bens', 'eb-credito-rural' ) : __( 'garantias', 'eb-credito-rural' );
		( new StatusHistoryRepository() )->add(
			$sid,
			$fresh['status'],
			$fresh['status'],
			$user_id,
			/* translators: 1: parte, 2: incluídos, 3: alterados, 4: documentos pedidos */
			sprintf( __( 'Cliente complementou %1$s depois do envio: %2$d incluído(s), %3$d alterado(s). Documentos pedidos: %4$s.', 'eb-credito-rural' ), $label, $added, $updated, $docs ? implode( '; ', $docs ) : __( 'nenhum', 'eb-credito-rural' ) ),
			/* translators: 1: parte, 2: incluídos, 3: alterados */
			sprintf( __( 'Você complementou %1$s: %2$d incluído(s), %3$d alterado(s).', 'eb-credito-rural' ), $label, $added, $updated )
		);
		AuditLog::log(
			'complement_added',
			'submission',
			$fresh['public_id'],
			array(
				'part'     => $part,
				'added'    => $added,
				'updated'  => $updated,
				'requests' => count( $requests ),
			),
			$user_id
		);
		$lines = array();
		foreach ( $new as $it ) {
			/* translators: %s: item */
			$lines[] = '• ' . sprintf( __( 'Novo: %s', 'eb-credito-rural' ), self::item_text( $part, $it ) );
		}
		if ( $updated ) {
			/* translators: %d: quantidade */
			$lines[] = '• ' . sprintf( _n( '%d item já informado foi alterado.', '%d itens já informados foram alterados.', $updated, 'eb-credito-rural' ), $updated );
		}
		foreach ( $docs as $d ) {
			/* translators: %s: documento */
			$lines[] = '• ' . sprintf( __( 'Documento pedido ao cliente: %s', 'eb-credito-rural' ), $d );
		}
		Notifier::complement_added( $fresh, $lines );
		return array(
			true,
			array(),
			array(
				'added'      => $added,
				'updated'    => $updated,
				'requests'   => $requests,
				'submission' => $fresh,
			),
		);
	}

	/**
	 * Chaves "tipo|referência" dos slots da matriz.
	 *
	 * @param array $slots Resultado de Wizard::document_slots().
	 * @return string[]
	 */
	private static function slot_keys( array $slots ) {
		$out = array();
		foreach ( $slots['slots'] as $slot ) {
			$out[] = $slot['type'] . '|' . $slot['ref_key'];
		}
		return $out;
	}

	/**
	 * Abre pedidos para os documentos que passaram a ser exigidos (obrigatórios ou recomendados) e ainda não foram
	 * enviados nem pedidos.
	 *
	 * @param array    $submission Linha (já atualizada).
	 * @param string[] $before     Slots antes do complemento.
	 * @return array Pedidos criados.
	 */
	private static function open_requests( array $submission, array $before ) {
		$repo    = new DocumentRequestRepository();
		$created = array();
		foreach ( ( new Wizard() )->document_slots( $submission )['slots'] as $slot ) {
			$key = $slot['type'] . '|' . $slot['ref_key'];
			if ( in_array( $key, $before, true ) || ! in_array( $slot['level'], self::REQUEST_LEVELS, true ) || $slot['satisfied'] || $repo->has_open( (int) $submission['id'], $slot['type'], $slot['ref_key'] ) ) {
				continue;
			}
			$note = trim( (string) $slot['help'] );
			if ( 'recomendado' === $slot['level'] ) {
				$note = trim( $note . ' ' . __( '(Recomendado: envie se tiver; não impede a análise.)', 'eb-credito-rural' ) );
			}
			$created[] = $repo->create(
				array(
					'submission_id' => (int) $submission['id'],
					'doc_type'      => $slot['type'],
					'ref_key'       => (string) $slot['ref_key'],
					'origin'        => self::ORIGIN,
					'label'         => mb_substr( $slot['label'] . ( $slot['ref_label'] ? ' — ' . $slot['ref_label'] : '' ), 0, 190 ),
					'note'          => $note,
					'requested_by'  => 0,
				)
			);
		}
		return array_values( array_filter( $created ) );
	}

	/**
	 * Lembrete diário (cron): um e-mail por solicitação enviada sem imóveis/garantias (modo opcional), N dias depois do
	 * envio (e no máximo N + 30), enquanto o status aceitar complemento. Marca complement_reminded_at.
	 *
	 * @return int E-mails enviados.
	 */
	public static function send_reminders() {
		$days  = Options::int( 'complement_reminder_days' );
		$parts = self::parts();
		if ( $days < 1 || ! array_filter( $parts ) ) {
			return 0;
		}
		$subs   = new SubmissionRepository();
		$before = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$after  = gmdate( 'Y-m-d H:i:s', time() - ( $days + self::REMINDER_WINDOW_DAYS ) * DAY_IN_SECONDS );
		$n      = 0;
		foreach ( $subs->complement_reminder_candidates( self::statuses(), $before, $after, $parts['imoveis'], $parts['garantias'] ) as $s ) {
			$missing = self::missing( $s );
			if ( ! $missing || ! get_userdata( (int) $s['user_id'] ) ) {
				continue;
			}
			Notifier::complement_reminder( $s, $missing );
			$subs->update( (int) $s['id'], array( 'complement_reminded_at' => current_time( 'mysql', true ) ) );
			AuditLog::log( 'complement_reminder_sent', 'submission', $s['public_id'], array( 'parts' => $missing ), 0 );
			++$n;
		}
		return $n;
	}
}
