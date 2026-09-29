<?php
/**
 * Regras de negócio do CRM: fichas, estágios, atividades/tarefas, lembretes e exportação (testável sem HTTP).
 *
 * @package EBCR
 */

namespace EBCR\Crm;

use EBCR\Database\CrmActivityRepository;
use EBCR\Database\CrmContactRepository;
use EBCR\Database\SubmissionDataRepository;
use EBCR\Database\SubmissionRepository;
use EBCR\Domain\Status;
use EBCR\Mail\Mailer;
use EBCR\Roles\Capabilities;
use EBCR\Security\AuditLog;
use EBCR\Support\Helpers;
use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Todas as operações verificam a capacidade do autor (CAP_CRM) e registram auditoria.
 */
final class Service {

	const TYPE_TASK      = 'tarefa';
	const REMINDER_EVENT = 'crm_task_reminder';
	const REMINDER_META  = 'ebcr_crm_reminder_sent';

	/**
	 * O usuário pode ver/operar o CRM?
	 *
	 * @param int|null $user_id ID (padrão: usuário atual).
	 * @return bool
	 */
	public static function can_manage( $user_id = null ) {
		return null === $user_id ? current_user_can( Capabilities::CAP_CRM ) : user_can( (int) $user_id, Capabilities::CAP_CRM );
	}

	/**
	 * O usuário pode exportar?
	 *
	 * @param int|null $user_id ID (padrão: usuário atual).
	 * @return bool
	 */
	public static function can_export( $user_id = null ) {
		return null === $user_id ? current_user_can( Capabilities::CAP_EXPORT ) : user_can( (int) $user_id, Capabilities::CAP_EXPORT );
	}

	/**
	 * Estágios do funil (chave => nome), na ordem do quadro.
	 *
	 * @return array
	 */
	public static function stages() {
		$stages = Options::pairs( 'crm_stages' );
		return $stages ? $stages : array( 'novo' => __( 'Novo lead', 'eb-credito-rural' ) );
	}

	/**
	 * Nome do estágio.
	 *
	 * @param string $key Chave.
	 * @return string
	 */
	public static function stage_label( $key ) {
		$stages = self::stages();
		return isset( $stages[ $key ] ) ? $stages[ $key ] : ( $key ? (string) $key : __( 'Sem estágio', 'eb-credito-rural' ) );
	}

	/**
	 * Cor do estágio (paleta fixa pela posição na lista).
	 *
	 * @param string $key Chave.
	 * @return string Cor hexadecimal.
	 */
	public static function stage_color( $key ) {
		$palette = array( '#2563eb', '#0891b2', '#d97706', '#7c3aed', '#16a34a', '#dc2626', '#db2777', '#4b5563' );
		$index   = array_search( $key, array_keys( self::stages() ), true );
		if ( false === $index ) {
			return '#6b7280';
		}
		return $palette[ $index % count( $palette ) ];
	}

	/**
	 * Origens de lead (chave => nome).
	 *
	 * @return array
	 */
	public static function lead_sources() {
		return Options::pairs( 'lead_sources' );
	}

	/**
	 * Tipos de atividade (chave => nome).
	 *
	 * @return array
	 */
	public static function activity_types() {
		return array(
			'ligacao'       => __( 'Ligação', 'eb-credito-rural' ),
			'reuniao'       => __( 'Reunião', 'eb-credito-rural' ),
			'visita'        => __( 'Visita técnica', 'eb-credito-rural' ),
			'email'         => __( 'E-mail', 'eb-credito-rural' ),
			'nota'          => __( 'Nota', 'eb-credito-rural' ),
			self::TYPE_TASK => __( 'Tarefa', 'eb-credito-rural' ),
		);
	}

	/**
	 * Nome do tipo de atividade.
	 *
	 * @param string $type Tipo.
	 * @return string
	 */
	public static function type_label( $type ) {
		$types = self::activity_types();
		return isset( $types[ $type ] ) ? $types[ $type ] : (string) $type;
	}

	/**
	 * Membros da equipe que podem ser responsáveis (analistas, gestores, administradores).
	 *
	 * @return \WP_User[]
	 */
	public static function team_members() {
		return get_users(
			array(
				'capability' => Capabilities::CAP_CRM,
				'fields'     => array( 'ID', 'display_name', 'user_email' ),
				'orderby'    => 'display_name',
				'order'      => 'ASC',
			)
		);
	}

	/**
	 * Status de solicitação considerados "em aberto" (nem rascunho nem finais).
	 *
	 * @return string[]
	 */
	public static function open_statuses() {
		return Status::in_progress();
	}

	/**
	 * Normaliza tags: "a, B ,,a" => "a,B".
	 *
	 * @param string|array $tags Entrada.
	 * @return string
	 */
	public static function normalize_tags( $tags ) {
		$list = is_array( $tags ) ? $tags : explode( ',', (string) $tags );
		$out  = array();
		$seen = array();
		foreach ( $list as $tag ) {
			$tag = trim( sanitize_text_field( (string) $tag ) );
			$tag = mb_substr( preg_replace( '/\s+/', ' ', $tag ), 0, 40 );
			$key = mb_strtolower( $tag );
			if ( '' === $tag || isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = $tag;
		}
		return mb_substr( implode( ',', $out ), 0, 255 );
	}

	/**
	 * Lista de tags a partir do valor salvo.
	 *
	 * @param string $tags Valor salvo.
	 * @return string[]
	 */
	public static function tags_list( $tags ) {
		return array_values( array_filter( array_map( 'trim', explode( ',', (string) $tags ) ) ) );
	}

	/**
	 * Formata telefone brasileiro (somente dígitos) para exibição.
	 *
	 * @param string $digits Dígitos.
	 * @return string
	 */
	public static function format_phone( $digits ) {
		$d = Helpers::digits( $digits );
		if ( 11 === strlen( $d ) ) {
			return '(' . substr( $d, 0, 2 ) . ') ' . substr( $d, 2, 5 ) . '-' . substr( $d, 7 );
		}
		if ( 10 === strlen( $d ) ) {
			return '(' . substr( $d, 0, 2 ) . ') ' . substr( $d, 2, 4 ) . '-' . substr( $d, 6 );
		}
		return $d;
	}

	/**
	 * Link wa.me para um número (assume DDI 55 quando ausente).
	 *
	 * @param string $digits Dígitos.
	 * @return string URL ou vazio.
	 */
	public static function whatsapp_link( $digits ) {
		$d = Helpers::digits( $digits );
		if ( strlen( $d ) < 10 ) {
			return '';
		}
		if ( strlen( $d ) <= 11 ) {
			$d = '55' . $d;
		}
		return 'https://wa.me/' . $d;
	}

	/**
	 * Converte data/hora local do formulário (Y-m-d\TH:i ou Y-m-d H:i[:s] ou Y-m-d) para UTC MySQL.
	 *
	 * @param string $value Entrada.
	 * @return string|null|false Data UTC, null se vazio, false se inválida.
	 */
	public static function local_to_utc( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return null;
		}
		if ( preg_match( '/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})(?::(\d{2}))?$/', $value, $m ) ) {
			$local = $m[1] . ' ' . $m[2] . ':' . ( isset( $m[3] ) ? $m[3] : '00' );
		} elseif ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			$local = $value . ' 09:00:00';
		} else {
			return false;
		}
		if ( ! strtotime( $local ) ) {
			return false;
		}
		return get_gmt_from_date( $local, 'Y-m-d H:i:s' );
	}

	/**
	 * Converte data UTC do banco para o valor de um campo datetime-local.
	 *
	 * @param string|null $utc Data UTC.
	 * @return string
	 */
	public static function utc_to_input( $utc ) {
		if ( empty( $utc ) || '0000-00-00 00:00:00' === $utc ) {
			return '';
		}
		return get_date_from_gmt( $utc, 'Y-m-d\TH:i' );
	}

	/**
	 * Ficha do usuário (cria sob demanda com telefone/WhatsApp do cadastro ou da etapa 1 da solicitação).
	 *
	 * @param int $user_id Usuário.
	 * @return array|null Ficha ou null se o usuário não existir.
	 */
	public static function contact_for_user( $user_id ) {
		$user = get_userdata( (int) $user_id );
		if ( ! $user ) {
			return null;
		}
		$repo     = new CrmContactRepository();
		$existing = $repo->find_by_user( $user->ID );
		if ( $existing ) {
			return $existing;
		}
		$phone    = Helpers::digits( get_user_meta( $user->ID, 'ebcr_phone', true ) );
		$whatsapp = Helpers::digits( get_user_meta( $user->ID, 'ebcr_whatsapp', true ) );
		if ( '' === $phone || '' === $whatsapp ) {
			$last = ( new SubmissionRepository() )->last_submitted( $user->ID );
			if ( $last ) {
				$ident    = ( new SubmissionDataRepository() )->get( (int) $last['id'], 'identificacao' );
				$phone    = '' === $phone && ! empty( $ident['telefone'] ) ? Helpers::digits( $ident['telefone'] ) : $phone;
				$whatsapp = '' === $whatsapp && ! empty( $ident['whatsapp'] ) ? Helpers::digits( $ident['whatsapp'] ) : $whatsapp;
			}
		}
		$stages  = array_keys( self::stages() );
		$sources = self::lead_sources();
		return $repo->get_or_create(
			$user->ID,
			array(
				'phone'       => mb_substr( $phone, 0, 30 ),
				'whatsapp'    => mb_substr( $whatsapp, 0, 30 ),
				'lead_source' => isset( $sources['site'] ) ? 'site' : '',
				'stage'       => $stages ? $stages[0] : 'novo',
			)
		);
	}

	/**
	 * Garante ficha para todos os clientes (até o limite), para que apareçam na lista e no quadro.
	 *
	 * @param int $limit Máximo de usuários verificados.
	 * @return int Fichas criadas.
	 */
	public static function sync_clients( $limit = 500 ) {
		$ids = get_users(
			array(
				'role'    => Capabilities::ROLE_CLIENT,
				'fields'  => 'ID',
				'number'  => max( 1, (int) $limit ),
				'orderby' => 'ID',
				'order'   => 'DESC',
			)
		);
		$n   = 0;
		foreach ( ( new CrmContactRepository() )->missing_user_ids( array_map( 'intval', $ids ) ) as $uid ) {
			if ( self::contact_for_user( $uid ) ) {
				++$n;
			}
		}
		return $n;
	}

	/**
	 * Atualiza os dados da ficha (contato, origem, tags, estágio, responsável, próxima ação, notas).
	 *
	 * @param int   $actor_id   Autor.
	 * @param int   $contact_id Ficha.
	 * @param array $input      Campos: phone, whatsapp, lead_source, tags, stage, owner_id, next_action, next_action_at (local), notes.
	 * @return array|\WP_Error Ficha atualizada.
	 */
	public static function update_contact( $actor_id, $contact_id, array $input ) {
		if ( ! self::can_manage( $actor_id ) ) {
			return new \WP_Error( 'forbidden', __( 'Sem permissão.', 'eb-credito-rural' ), array( 'status' => 403 ) );
		}
		$repo    = new CrmContactRepository();
		$contact = $repo->find( (int) $contact_id );
		if ( ! $contact ) {
			return new \WP_Error( 'not_found', __( 'Ficha não encontrada.', 'eb-credito-rural' ), array( 'status' => 404 ) );
		}
		$data = array();
		if ( array_key_exists( 'phone', $input ) ) {
			$data['phone'] = mb_substr( Helpers::digits( $input['phone'] ), 0, 30 );
		}
		if ( array_key_exists( 'whatsapp', $input ) ) {
			$data['whatsapp'] = mb_substr( Helpers::digits( $input['whatsapp'] ), 0, 30 );
		}
		if ( array_key_exists( 'lead_source', $input ) ) {
			$source = sanitize_key( (string) $input['lead_source'] );
			if ( '' !== $source && ! isset( self::lead_sources()[ $source ] ) ) {
				return new \WP_Error( 'invalid_source', __( 'Origem de lead inválida.', 'eb-credito-rural' ), array( 'status' => 400 ) );
			}
			$data['lead_source'] = $source;
		}
		if ( array_key_exists( 'tags', $input ) ) {
			$data['tags'] = self::normalize_tags( $input['tags'] );
		}
		if ( array_key_exists( 'stage', $input ) ) {
			$stage = sanitize_key( (string) $input['stage'] );
			if ( ! isset( self::stages()[ $stage ] ) ) {
				return new \WP_Error( 'invalid_stage', __( 'Estágio inválido.', 'eb-credito-rural' ), array( 'status' => 400 ) );
			}
			$data['stage'] = $stage;
		}
		if ( array_key_exists( 'owner_id', $input ) ) {
			$owner = (int) $input['owner_id'];
			if ( $owner > 0 && ! self::can_manage( $owner ) ) {
				return new \WP_Error( 'invalid_owner', __( 'O responsável precisa ser um membro da equipe.', 'eb-credito-rural' ), array( 'status' => 400 ) );
			}
			$data['owner_id'] = $owner > 0 ? $owner : null;
		}
		if ( array_key_exists( 'next_action', $input ) ) {
			$data['next_action'] = mb_substr( sanitize_text_field( (string) $input['next_action'] ), 0, 255 );
		}
		if ( array_key_exists( 'next_action_at', $input ) ) {
			$at = self::local_to_utc( $input['next_action_at'] );
			if ( false === $at ) {
				return new \WP_Error( 'invalid_date', __( 'Data da próxima ação inválida.', 'eb-credito-rural' ), array( 'status' => 400 ) );
			}
			$data['next_action_at'] = $at;
		}
		if ( array_key_exists( 'notes', $input ) ) {
			$data['notes'] = sanitize_textarea_field( (string) $input['notes'] );
		}
		if ( ! $data ) {
			return $contact;
		}
		$repo->update( (int) $contact['id'], $data );
		$changed = array_keys( $data );
		AuditLog::log( 'crm_contact_updated', 'crm_contact', (int) $contact['id'], array( 'fields' => $changed ), $actor_id );
		if ( isset( $data['stage'] ) && $data['stage'] !== $contact['stage'] ) {
			AuditLog::log(
				'crm_stage_changed',
				'crm_contact',
				(int) $contact['id'],
				array(
					'from' => $contact['stage'],
					'to'   => $data['stage'],
				),
				$actor_id
			);
		}
		return $repo->find( (int) $contact['id'] );
	}

	/**
	 * Muda o estágio do funil (usado pelo Kanban e pelo fallback sem JS).
	 *
	 * @param int    $actor_id   Autor.
	 * @param int    $contact_id Ficha.
	 * @param string $stage      Novo estágio.
	 * @return array|\WP_Error Ficha atualizada.
	 */
	public static function change_stage( $actor_id, $contact_id, $stage ) {
		if ( ! self::can_manage( $actor_id ) ) {
			return new \WP_Error( 'forbidden', __( 'Sem permissão.', 'eb-credito-rural' ), array( 'status' => 403 ) );
		}
		$stage = sanitize_key( (string) $stage );
		if ( ! isset( self::stages()[ $stage ] ) ) {
			return new \WP_Error( 'invalid_stage', __( 'Estágio inválido.', 'eb-credito-rural' ), array( 'status' => 400 ) );
		}
		$repo    = new CrmContactRepository();
		$contact = $repo->find( (int) $contact_id );
		if ( ! $contact ) {
			return new \WP_Error( 'not_found', __( 'Ficha não encontrada.', 'eb-credito-rural' ), array( 'status' => 404 ) );
		}
		if ( $contact['stage'] === $stage ) {
			return $contact;
		}
		$repo->update( (int) $contact['id'], array( 'stage' => $stage ) );
		AuditLog::log(
			'crm_stage_changed',
			'crm_contact',
			(int) $contact['id'],
			array(
				'from' => $contact['stage'],
				'to'   => $stage,
			),
			$actor_id
		);
		return $repo->find( (int) $contact['id'] );
	}

	/**
	 * Atribui responsável a várias fichas (ação em massa).
	 *
	 * @param int   $actor_id Autor.
	 * @param int[] $ids      Fichas.
	 * @param int   $owner_id Responsável (0 remove).
	 * @return int|\WP_Error Fichas afetadas.
	 */
	public static function bulk_assign( $actor_id, array $ids, $owner_id ) {
		if ( ! self::can_manage( $actor_id ) ) {
			return new \WP_Error( 'forbidden', __( 'Sem permissão.', 'eb-credito-rural' ), array( 'status' => 403 ) );
		}
		$owner_id = (int) $owner_id;
		if ( $owner_id > 0 && ! self::can_manage( $owner_id ) ) {
			return new \WP_Error( 'invalid_owner', __( 'O responsável precisa ser um membro da equipe.', 'eb-credito-rural' ), array( 'status' => 400 ) );
		}
		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		if ( ! $ids ) {
			return 0;
		}
		$n = ( new CrmContactRepository() )->bulk_assign( $ids, $owner_id );
		AuditLog::log(
			'crm_owner_assigned',
			'crm_contact',
			'',
			array(
				'owner_id' => $owner_id,
				'ids'      => $ids,
			),
			$actor_id
		);
		return $n;
	}

	/**
	 * Registra atividade (ligação, reunião, visita, e-mail, nota) ou tarefa.
	 *
	 * Para tarefas, o responsável fica em created_by (padrão: o autor); o lembrete diário usa esse campo e,
	 * se vazio, o responsável da ficha.
	 *
	 * @param int   $actor_id   Autor.
	 * @param int   $contact_id Ficha.
	 * @param array $input      type, description, due_at (data/hora local, só tarefas), assignee_id (só tarefas), submission_id.
	 * @return array|\WP_Error Atividade criada.
	 */
	public static function add_activity( $actor_id, $contact_id, array $input ) {
		if ( ! self::can_manage( $actor_id ) ) {
			return new \WP_Error( 'forbidden', __( 'Sem permissão.', 'eb-credito-rural' ), array( 'status' => 403 ) );
		}
		$contact = ( new CrmContactRepository() )->find( (int) $contact_id );
		if ( ! $contact ) {
			return new \WP_Error( 'not_found', __( 'Ficha não encontrada.', 'eb-credito-rural' ), array( 'status' => 404 ) );
		}
		$type = isset( $input['type'] ) ? sanitize_key( (string) $input['type'] ) : '';
		if ( ! isset( self::activity_types()[ $type ] ) ) {
			return new \WP_Error( 'invalid_type', __( 'Tipo de atividade inválido.', 'eb-credito-rural' ), array( 'status' => 400 ) );
		}
		$description = isset( $input['description'] ) ? trim( sanitize_textarea_field( (string) $input['description'] ) ) : '';
		if ( '' === $description ) {
			return new \WP_Error( 'empty_description', __( 'Descreva a atividade.', 'eb-credito-rural' ), array( 'status' => 400 ) );
		}
		$data = array(
			'contact_id'  => (int) $contact['id'],
			'type'        => $type,
			'description' => mb_substr( $description, 0, 5000 ),
			'created_by'  => (int) $actor_id,
		);
		if ( ! empty( $input['submission_id'] ) ) {
			$submission = ( new SubmissionRepository() )->find( (int) $input['submission_id'] );
			if ( ! $submission || (int) $submission['user_id'] !== (int) $contact['user_id'] ) {
				return new \WP_Error( 'invalid_submission', __( 'A solicitação não pertence a este cliente.', 'eb-credito-rural' ), array( 'status' => 400 ) );
			}
			$data['submission_id'] = (int) $submission['id'];
		}
		if ( self::TYPE_TASK === $type ) {
			$due = self::local_to_utc( isset( $input['due_at'] ) ? $input['due_at'] : '' );
			if ( false === $due ) {
				return new \WP_Error( 'invalid_date', __( 'Data de vencimento inválida.', 'eb-credito-rural' ), array( 'status' => 400 ) );
			}
			$data['due_at'] = $due;
			$assignee       = isset( $input['assignee_id'] ) ? (int) $input['assignee_id'] : 0;
			if ( $assignee > 0 ) {
				if ( ! self::can_manage( $assignee ) ) {
					return new \WP_Error( 'invalid_owner', __( 'O responsável precisa ser um membro da equipe.', 'eb-credito-rural' ), array( 'status' => 400 ) );
				}
				$data['created_by'] = $assignee;
			}
		}
		$repo = new CrmActivityRepository();
		$id   = $repo->add( $data );
		AuditLog::log(
			'crm_activity_added',
			'crm_contact',
			(int) $contact['id'],
			array(
				'activity_id' => $id,
				'type'        => $type,
			),
			$actor_id
		);
		return $repo->find( $id );
	}

	/**
	 * Conclui uma tarefa.
	 *
	 * @param int $actor_id    Autor.
	 * @param int $activity_id Tarefa.
	 * @return array|\WP_Error Tarefa atualizada.
	 */
	public static function complete_task( $actor_id, $activity_id ) {
		if ( ! self::can_manage( $actor_id ) ) {
			return new \WP_Error( 'forbidden', __( 'Sem permissão.', 'eb-credito-rural' ), array( 'status' => 403 ) );
		}
		$repo = new CrmActivityRepository();
		$task = $repo->find( (int) $activity_id );
		if ( ! $task ) {
			return new \WP_Error( 'not_found', __( 'Tarefa não encontrada.', 'eb-credito-rural' ), array( 'status' => 404 ) );
		}
		if ( ! empty( $task['done_at'] ) ) {
			return $task;
		}
		$repo->complete( (int) $task['id'] );
		AuditLog::log( 'crm_task_done', 'crm_contact', (int) $task['contact_id'], array( 'activity_id' => (int) $task['id'] ), $actor_id );
		return $repo->find( (int) $task['id'] );
	}

	/**
	 * Tarefas a lembrar (vencem no dia informado ou já venceram, não concluídas), agrupadas por responsável.
	 *
	 * @param string|null $day Dia local (Y-m-d); padrão hoje.
	 * @return array user_id => lista de tarefas (com contact_id, user_id do cliente, due_at, description).
	 */
	public static function tasks_to_remind( $day = null ) {
		$day = $day ? $day : wp_date( 'Y-m-d' );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) ) {
			return array();
		}
		$until  = get_gmt_from_date( $day . ' 23:59:59', 'Y-m-d H:i:s' );
		$groups = array();
		foreach ( ( new CrmActivityRepository() )->due_tasks( $until ) as $task ) {
			$responsible = (int) $task['created_by'] > 0 ? (int) $task['created_by'] : (int) $task['owner_id'];
			if ( $responsible <= 0 || ! self::can_manage( $responsible ) ) {
				continue;
			}
			$groups[ $responsible ][] = $task;
		}
		return $groups;
	}

	/**
	 * Envia o lembrete diário (uma vez por dia por responsável). Enganchado em ebcr_daily.
	 *
	 * @param string|null $day Dia local (Y-m-d); padrão hoje.
	 * @return array user_id => IDs na fila de e-mails.
	 */
	public static function send_reminders( $day = null ) {
		if ( ! Options::bool( 'crm_task_reminders' ) ) {
			return array();
		}
		$day  = $day && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $day ) ? (string) $day : wp_date( 'Y-m-d' );
		$sent = array();
		foreach ( self::tasks_to_remind( $day ) as $uid => $tasks ) {
			$user = get_userdata( $uid );
			if ( ! $user || ! is_email( $user->user_email ) ) {
				continue;
			}
			if ( (string) get_user_meta( $uid, self::REMINDER_META, true ) === $day ) {
				continue;
			}
			$lines = array();
			foreach ( $tasks as $task ) {
				$client  = Helpers::user_name( (int) $task['user_id'] );
				$lines[] = sprintf( '• %s — %s (%s)', Helpers::date( $task['due_at'] ), mb_substr( $task['description'], 0, 140 ), $client );
			}
			$ids = Mailer::send_event(
				self::REMINDER_EVENT,
				$user->user_email,
				array(
					'nome'        => $user->display_name,
					'pendencias'  => implode( "\n", $lines ),
					'link_portal' => admin_url( 'admin.php?page=ebcr-crm' ),
				)
			);
			if ( ! $ids ) {
				continue;
			}
			update_user_meta( $uid, self::REMINDER_META, $day );
			$sent[ $uid ] = $ids;
			AuditLog::log( 'crm_reminder_sent', 'user', $uid, array( 'tasks' => count( $tasks ) ), 0 );
		}
		return $sent;
	}

	/**
	 * Linhas da exportação (cabeçalho + dados) conforme os filtros da lista.
	 *
	 * @param array $args Filtros (search, stage, owner_id, lead_source, tag, orderby, order).
	 * @return array
	 */
	public static function export_rows( array $args = array() ) {
		$args['open_statuses'] = self::open_statuses();
		$args['per_page']      = 5000;
		$args['page']          = 1;
		list( $items )         = ( new CrmContactRepository() )->query( $args );
		$rows                  = array( array( 'id', 'cliente', 'email', 'telefone', 'whatsapp', 'origem', 'tags', 'estagio', 'responsavel', 'proxima_acao', 'proxima_acao_em', 'solicitacoes_abertas', 'ultima_atividade', 'criado_em', 'atualizado_em' ) );
		$sources               = self::lead_sources();
		foreach ( $items as $c ) {
			$rows[] = array(
				(int) $c['id'],
				(string) $c['user_name'],
				(string) $c['user_email'],
				self::format_phone( $c['phone'] ),
				self::format_phone( $c['whatsapp'] ),
				isset( $sources[ $c['lead_source'] ] ) ? $sources[ $c['lead_source'] ] : (string) $c['lead_source'],
				(string) $c['tags'],
				self::stage_label( $c['stage'] ),
				$c['owner_id'] ? Helpers::user_name( (int) $c['owner_id'] ) : '',
				(string) $c['next_action'],
				$c['next_action_at'] ? get_date_from_gmt( $c['next_action_at'], 'Y-m-d H:i' ) : '',
				(int) $c['open_submissions'],
				$c['last_activity_at'] ? get_date_from_gmt( $c['last_activity_at'], 'Y-m-d H:i' ) : '',
				get_date_from_gmt( $c['created_at'], 'Y-m-d H:i' ),
				get_date_from_gmt( $c['updated_at'], 'Y-m-d H:i' ),
			);
		}
		return $rows;
	}

	/**
	 * Converte linhas em CSV (UTF-8 com BOM, separador ";", proteção contra fórmulas).
	 *
	 * @param array $rows Linhas.
	 * @return string
	 */
	public static function csv_string( array $rows ) {
		$out = fopen( 'php://temp', 'w+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- buffer em memória.
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- BOM para Excel.
		foreach ( $rows as $r ) {
			fputcsv(
				$out,
				array_map(
					static function ( $v ) {
						return is_string( $v ) && preg_match( '/^[=+\-@]/', $v ) ? "'" . $v : $v;
					},
					$r
				),
				';',
				'"',
				'\\'
			);
		}
		rewind( $out );
		$csv = (string) stream_get_contents( $out );
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- idem.
		return $csv;
	}

	/**
	 * Exportação completa como string CSV.
	 *
	 * @param array $args Filtros.
	 * @return string
	 */
	public static function export_csv( array $args = array() ) {
		return self::csv_string( self::export_rows( $args ) );
	}
}
