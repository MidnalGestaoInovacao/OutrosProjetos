<?php
/**
 * Telas do painel da equipe no portal: monta os dados de cada tela (reutilizando repositórios, Metrics e serviços)
 * e renderiza os templates de templates/team/. A autorização por solicitação é sempre de Authorization.
 *
 * @package EBCR
 */

namespace EBCR\Frontend\Team;

use EBCR\Admin\Reports;
use EBCR\Crm\Service;
use EBCR\Database\CheckRepository;
use EBCR\Database\ConsentRepository;
use EBCR\Database\CrmActivityRepository;
use EBCR\Database\CrmContactRepository;
use EBCR\Database\DocumentRepository;
use EBCR\Database\DocumentRequestRepository;
use EBCR\Database\MessageRepository;
use EBCR\Database\StatusHistoryRepository;
use EBCR\Database\SubmissionRepository;
use EBCR\Domain\Status;
use EBCR\Forms\DocumentMatrix;
use EBCR\Forms\Wizard;
use EBCR\Reports\Metrics;
use EBCR\Roles\Capabilities;
use EBCR\Security\AuditLog;
use EBCR\Security\Authorization;
use EBCR\Support\Helpers;
use EBCR\Support\Options;
use EBCR\Support\View;

defined( 'ABSPATH' ) || exit;

/**
 * Uma instância por requisição, para o usuário logado.
 */
// phpcs:disable WordPress.Security.NonceVerification -- filtros e navegação (GET) são apenas leitura; nenhuma ação é executada aqui.
final class Screens {

	/**
	 * Máximo de categorias por gráfico de barras (o restante vai para "Outras").
	 *
	 * @var int
	 */
	const MAX_BARS = 8;

	/**
	 * Itens por página nas listas.
	 *
	 * @var int
	 */
	const PER_PAGE = 20;

	/**
	 * Usuário.
	 *
	 * @var int
	 */
	private $uid;

	/**
	 * Construtor.
	 *
	 * @param int $user_id Usuário logado.
	 */
	public function __construct( $user_id ) {
		$this->uid = (int) $user_id;
	}

	/**
	 * Parâmetro GET textual.
	 *
	 * @param string $key Chave.
	 * @return string
	 */
	private static function get( $key ) {
		return isset( $_GET[ $key ] ) && is_scalar( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
	}

	/**
	 * "Analista vê só as atribuídas" se aplica a este usuário?
	 *
	 * @param int $user_id Usuário.
	 * @return bool
	 */
	public static function only_assigned( $user_id ) {
		return Options::bool( 'analyst_only_assigned' ) && ! user_can( $user_id, Capabilities::CAP_FINAL_STATUS ) && ! user_can( $user_id, Capabilities::CAP_SETTINGS );
	}

	/**
	 * Membros da equipe (para filtros e atribuição).
	 *
	 * @return \WP_User[]
	 */
	public static function analysts() {
		return get_users(
			array(
				'capability' => Capabilities::CAP_VIEW,
				'fields'     => array( 'ID', 'display_name' ),
				'orderby'    => 'display_name',
			)
		);
	}

	// ------------------------------------------------------------------ visão geral

	/**
	 * Cartões da visão geral, calculados sobre as solicitações visíveis ao usuário.
	 *
	 * @param int $user_id Usuário.
	 * @return array Lista de { label, value, sub }.
	 */
	public static function overview_cards( $user_id ) {
		$filters     = Metrics::normalize( array(), $user_id );
		$rows        = Metrics::rows( $filters );
		$week        = strtotime( '-7 days' );
		$month_start = strtotime( gmdate( 'Y-m-01 00:00:00' ) . ' UTC' );
		$analysis    = array( Status::PRE_ANALYSIS, Status::CREDIT, Status::COMMITTEE );
		$approved    = array( Status::APPROVED, Status::FORMALIZATION, Status::DONE );
		$open        = Status::in_progress();
		$n           = array(
			'new'      => 0,
			'analysis' => 0,
			'pending'  => 0,
			'approved' => 0,
		);
		$vol_open    = 0.0;
		$vol_month   = 0.0;
		foreach ( $rows as $r ) {
			$submitted = $r['submitted_at'] ? strtotime( $r['submitted_at'] . ' UTC' ) : 0;
			$updated   = $r['updated_at'] ? strtotime( $r['updated_at'] . ' UTC' ) : 0;
			if ( $submitted >= $week ) {
				++$n['new'];
			}
			if ( in_array( $r['status'], $analysis, true ) ) {
				++$n['analysis'];
			}
			if ( Status::PENDING_DOCS === $r['status'] ) {
				++$n['pending'];
			}
			if ( in_array( $r['status'], $approved, true ) && $updated >= $month_start ) {
				++$n['approved'];
				$vol_month += (float) $r['requested_amount'];
			}
			if ( in_array( $r['status'], $open, true ) ) {
				$vol_open += (float) $r['requested_amount'];
			}
		}
		$stages = Metrics::stage_durations( $filters );
		$by_key = array();
		foreach ( $stages as $st ) {
			$by_key[ $st['key'] ] = $st;
		}
		$days  = static function ( $key ) use ( $by_key ) {
			return isset( $by_key[ $key ] ) && null !== $by_key[ $key ]['days'] ? number_format_i18n( $by_key[ $key ]['days'], 1 ) . ' d' : '—';
		};
		$total = isset( $by_key['submitted_to_final'] ) ? $by_key['submitted_to_final']['days'] : null;
		return array(
			array(
				'label' => __( 'Novas (7 dias)', 'eb-credito-rural' ),
				'value' => (string) $n['new'],
				'sub'   => '',
			),
			array(
				'label' => __( 'Em análise', 'eb-credito-rural' ),
				'value' => (string) $n['analysis'],
				'sub'   => '',
			),
			array(
				'label' => __( 'Com pendência', 'eb-credito-rural' ),
				'value' => (string) $n['pending'],
				'sub'   => '',
			),
			array(
				'label' => __( 'Aprovadas no mês', 'eb-credito-rural' ),
				'value' => (string) $n['approved'],
				'sub'   => '',
			),
			array(
				'label' => __( 'Volume solicitado (em andamento)', 'eb-credito-rural' ),
				'value' => Helpers::money( $vol_open ),
				'sub'   => '',
			),
			array(
				'label' => __( 'Volume aprovado (mês)', 'eb-credito-rural' ),
				'value' => Helpers::money( $vol_month ),
				'sub'   => '',
			),
			array(
				'label' => __( 'Tempo médio (envio → decisão)', 'eb-credito-rural' ),
				'value' => null === $total ? '—' : sprintf( /* translators: %s: dias */ __( '%s dias', 'eb-credito-rural' ), number_format_i18n( $total, 1 ) ),
				'sub'   => sprintf( /* translators: 1: dias até a pré-análise, 2: dias até a análise de crédito, 3: dias até a decisão */ __( 'Pré-análise %1$s · Análise %2$s · Decisão %3$s', 'eb-credito-rural' ), $days( 'submitted_to_pre' ), $days( 'pre_to_credit' ), $days( 'credit_to_decision' ) ),
			),
		);
	}

	/**
	 * Série para gráfico de barras: até MAX_BARS categorias, o restante agrupado em "Outras".
	 *
	 * @param array $items Itens { label, count } já ordenados.
	 * @return array labels, values.
	 */
	private static function series( array $items ) {
		$labels = array();
		$values = array();
		$other  = 0;
		foreach ( array_values( $items ) as $i => $item ) {
			if ( $i < self::MAX_BARS ) {
				$labels[] = $item['label'];
				$values[] = (int) $item['count'];
			} else {
				$other += (int) $item['count'];
			}
		}
		if ( $other > 0 ) {
			$labels[] = __( 'Outras', 'eb-credito-rural' );
			$values[] = $other;
		}
		return array(
			'labels' => $labels,
			'values' => $values,
		);
	}

	/**
	 * Listas de acompanhamento (tarefas CRM, pendências paradas, certidões vencendo), só com o que o usuário pode ver.
	 *
	 * @return array tasks, stale, expiring, days.
	 */
	private function watchlists() {
		$subs       = new SubmissionRepository();
		$days_limit = max( 1, Options::int( 'pending_reminder_days' ) );
		$stale_rows = array();
		foreach ( ( new DocumentRequestRepository() )->stale_open( $days_limit ) as $r ) {
			$s = $subs->find( (int) $r['submission_id'] );
			if ( $s && ! Status::is_final( $s['status'] ) && Authorization::can_view_submission( $this->uid, $s ) ) {
				$stale_rows[ $s['public_id'] ] = array(
					's'     => $s,
					'label' => $r['label'],
					'since' => $r['requested_at'],
				);
			}
		}
		$expiring = array();
		foreach ( ( new DocumentRepository() )->expiring_until( gmdate( 'Y-m-d', strtotime( '+15 days' ) ), Status::in_progress() ) as $d ) {
			$s = $subs->find( (int) $d['submission_id'] );
			if ( $s && Authorization::can_view_submission( $this->uid, $s ) ) {
				$d['s']     = $s;
				$expiring[] = $d;
			}
		}
		$tasks = array();
		if ( user_can( $this->uid, Capabilities::CAP_CRM ) ) {
			$contacts = new CrmContactRepository();
			foreach ( ( new CrmActivityRepository() )->open_tasks( 10 ) as $t ) {
				$c            = $contacts->find( (int) $t['contact_id'] );
				$t['contact'] = $c;
				$t['name']    = $c ? Helpers::user_name( (int) $c['user_id'] ) : '';
				$tasks[]      = $t;
			}
		}
		return array(
			'tasks'    => $tasks,
			'stale'    => $stale_rows,
			'expiring' => $expiring,
			'days'     => $days_limit,
		);
	}

	/**
	 * Visão geral: cartões e gráficos (CAP_DASHBOARD) + listas de acompanhamento (todos da equipe).
	 *
	 * @return string
	 */
	public function overview() {
		$has_dash = user_can( $this->uid, Capabilities::CAP_DASHBOARD );
		$data     = array_merge(
			$this->watchlists(),
			array(
				'has_dashboard' => $has_dash,
				'cards'         => array(),
				'by_status'     => array(),
				'months'        => array(),
				'uf'            => array(),
				'activity'      => array(),
				'guarantee'     => array(),
				'stages'        => array(),
				'can_crm'       => user_can( $this->uid, Capabilities::CAP_CRM ),
			)
		);
		if ( $has_dash ) {
			$filters           = Metrics::normalize( array(), $this->uid );
			$data['cards']     = self::overview_cards( $this->uid );
			$data['by_status'] = Metrics::funnel( $filters );
			$data['months']    = Metrics::by_month( $filters, 12 );
			$data['uf']        = Metrics::by_uf( $filters );
			$data['activity']  = Metrics::by_activity( $filters );
			$data['guarantee'] = Metrics::by_guarantee_type( $filters );
			$data['stages']    = Metrics::stage_durations( $filters );
			Panel::chart_data(
				array(
					'funnel'    => array(
						'labels' => array_map( array( Status::class, 'label' ), array_keys( $data['by_status'] ) ),
						'values' => array_values( $data['by_status'] ),
					),
					'months'    => array(
						'labels'  => wp_list_pluck( $data['months'], 'label' ),
						'counts'  => array_map( 'intval', wp_list_pluck( $data['months'], 'count' ) ),
						'volumes' => array_map( 'floatval', wp_list_pluck( $data['months'], 'volume' ) ),
					),
					'uf'        => self::series( $data['uf'] ),
					'activity'  => self::series( $data['activity'] ),
					'guarantee' => self::series( $data['guarantee'] ),
				)
			);
		}
		return View::render( 'team/overview', $data );
	}

	// ------------------------------------------------------------------ solicitações

	/**
	 * Filtros da lista a partir da entrada (GET), já sanitizados. Chaves próprias para não colidir com as do WordPress.
	 *
	 * @param array $input Entrada (ex.: $_GET).
	 * @return array status, assigned_to, fund, date_from, date_to, search, orderby, order, page.
	 */
	public static function list_args( array $input ) {
		$g = static function ( $k ) use ( $input ) {
			return isset( $input[ $k ] ) && is_scalar( $input[ $k ] ) ? sanitize_text_field( wp_unslash( (string) $input[ $k ] ) ) : '';
		};
		return array(
			'status'      => Status::exists( sanitize_key( $g( 'status' ) ) ) ? sanitize_key( $g( 'status' ) ) : '',
			'assigned_to' => absint( $g( 'analista' ) ),
			'fund'        => sanitize_key( $g( 'carteira' ) ),
			'date_from'   => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $g( 'de' ) ) ? $g( 'de' ) : '',
			'date_to'     => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $g( 'ate' ) ) ? $g( 'ate' ) : '',
			'search'      => $g( 'busca' ),
			'orderby'     => in_array( $g( 'ordem' ), array( 'submitted_at', 'updated_at', 'requested_amount', 'status', 'protocol' ), true ) ? $g( 'ordem' ) : 'submitted_at',
			'order'       => 'ASC' === strtoupper( $g( 'dir' ) ) ? 'ASC' : 'DESC',
			'page'        => max( 1, absint( $g( 'pg' ) ) ),
		);
	}

	/**
	 * Consulta a lista respeitando "analista vê só as atribuídas".
	 *
	 * @param int   $user_id Usuário.
	 * @param array $args    Filtros (ver list_args()).
	 * @return array [items, total]
	 */
	public static function query_submissions( $user_id, array $args ) {
		$args['per_page'] = isset( $args['per_page'] ) ? (int) $args['per_page'] : self::PER_PAGE;
		if ( self::only_assigned( $user_id ) ) {
			$args['only_assigned_to'] = (int) $user_id;
		}
		return ( new SubmissionRepository() )->query( $args );
	}

	/**
	 * Parâmetros de URL correspondentes aos filtros (para links de ordenação/paginação/exportação).
	 *
	 * @param array $args Filtros normalizados.
	 * @return array
	 */
	public static function list_query_args( array $args ) {
		$out = array( 'tela' => 'solicitacoes' );
		$map = array(
			'status'      => 'status',
			'assigned_to' => 'analista',
			'fund'        => 'carteira',
			'date_from'   => 'de',
			'date_to'     => 'ate',
			'search'      => 'busca',
			'orderby'     => 'ordem',
			'order'       => 'dir',
		);
		foreach ( $map as $k => $param ) {
			if ( ! empty( $args[ $k ] ) ) {
				$out[ $param ] = (string) $args[ $k ];
			}
		}
		return $out;
	}

	/**
	 * Lista de solicitações.
	 *
	 * @return string
	 */
	public function submissions() {
		$args                  = self::list_args( $_GET );
		list( $items, $total ) = self::query_submissions( $this->uid, $args );
		return View::render(
			'team/submissions',
			array(
				'items'      => $items,
				'total'      => (int) $total,
				'args'       => $args,
				'per_page'   => self::PER_PAGE,
				'pages'      => (int) ceil( $total / self::PER_PAGE ),
				'base_args'  => self::list_query_args( $args ),
				'statuses'   => Status::all(),
				'funds'      => Options::pairs( 'funds' ),
				'analysts'   => self::analysts(),
				'can_export' => user_can( $this->uid, Capabilities::CAP_EXPORT ),
				'restricted' => self::only_assigned( $this->uid ),
			)
		);
	}

	// ------------------------------------------------------------------ detalhe

	/**
	 * Transições de status disponíveis ao usuário nesta solicitação (respeita papel e fluxo).
	 *
	 * @param int   $user_id    Usuário.
	 * @param array $submission Linha.
	 * @return array status => definição.
	 */
	public static function transitions( $user_id, array $submission ) {
		$out = array();
		foreach ( Status::all() as $k => $def ) {
			if ( Authorization::can_change_status( $user_id, $submission, $k ) ) {
				$out[ $k ] = $def;
			}
		}
		return $out;
	}

	/**
	 * Detalhe da solicitação (abas + ações).
	 *
	 * @param string $public_id UUID.
	 * @return string
	 */
	public function submission( $public_id ) {
		$s = ( new SubmissionRepository() )->find_by_public_id( $public_id );
		if ( ! $s || ! Authorization::can_view_submission( $this->uid, $s ) ) {
			AuditLog::log( 'access_denied', 'submission', $public_id, array( 'op' => 'team_view' ), $this->uid );
			return '<div class="ebcr-card"><div class="ebcr-alert ebcr-alert--error" role="alert">' . esc_html__( 'Solicitação não encontrada ou sem permissão.', 'eb-credito-rural' ) . '</div><p><a class="ebcr-btn" href="' . esc_url( Panel::url( array( 'tela' => 'solicitacoes' ) ) ) . '">' . esc_html__( 'Voltar à lista', 'eb-credito-rural' ) . '</a></p></div>';
		}
		( new MessageRepository() )->mark_read( (int) $s['id'], $this->uid );
		$wizard = new Wizard();
		$tab    = sanitize_key( self::get( 'tab' ) );
		return View::render(
			'team/submission',
			array(
				's'           => $s,
				'user'        => get_userdata( (int) $s['user_id'] ),
				'contact'     => ( new CrmContactRepository() )->get_or_create( (int) $s['user_id'] ),
				'saved'       => $wizard->saved( $s ),
				'slots'       => $wizard->document_slots( $s ),
				'documents'   => ( new DocumentRepository() )->for_submission( (int) $s['id'] ),
				'requests'    => ( new DocumentRequestRepository() )->for_submission( (int) $s['id'] ),
				'history'     => ( new StatusHistoryRepository() )->for_submission( (int) $s['id'] ),
				'messages'    => ( new MessageRepository() )->for_submission( (int) $s['id'] ),
				'checks'      => ( new CheckRepository() )->for_submission( (int) $s['id'] ),
				'check_items' => CheckRepository::defaults(),
				'consents'    => ( new ConsentRepository() )->for_submission( (int) $s['id'] ),
				'transitions' => self::transitions( $this->uid, $s ),
				'can_edit'    => Authorization::team_can_edit( $this->uid, $s ),
				'can_export'  => user_can( $this->uid, Capabilities::CAP_EXPORT ),
				'can_crm'     => user_can( $this->uid, Capabilities::CAP_CRM ),
				'analysts'    => self::analysts(),
				'funds'       => Options::pairs( 'funds' ),
				'matrix'      => DocumentMatrix::all(),
				'tab'         => $tab,
			)
		);
	}

	// ------------------------------------------------------------------ CRM

	/**
	 * Filtros da lista/quadro do CRM a partir da entrada (GET).
	 *
	 * @param array $input Entrada.
	 * @return array search, stage, owner_id, lead_source, tag, orderby, order, page.
	 */
	public static function crm_args( array $input ) {
		$g = static function ( $k ) use ( $input ) {
			return isset( $input[ $k ] ) && is_scalar( $input[ $k ] ) ? sanitize_text_field( wp_unslash( (string) $input[ $k ] ) ) : '';
		};
		return array(
			'search'      => $g( 'busca' ),
			'stage'       => sanitize_key( $g( 'estagio' ) ),
			'owner_id'    => 'none' === $g( 'resp' ) ? 'none' : ( '' === $g( 'resp' ) ? '' : (string) absint( $g( 'resp' ) ) ),
			'lead_source' => sanitize_key( $g( 'origem' ) ),
			'tag'         => $g( 'etiqueta' ),
			'orderby'     => in_array( $g( 'ordem' ), array( 'user_name', 'stage', 'next_action_at', 'updated_at', 'open_submissions', 'last_activity_at' ), true ) ? $g( 'ordem' ) : 'updated_at',
			'order'       => 'ASC' === strtoupper( $g( 'dir' ) ) ? 'ASC' : 'DESC',
			'page'        => max( 1, absint( $g( 'pg' ) ) ),
		);
	}

	/**
	 * Parâmetros de URL dos filtros do CRM.
	 *
	 * @param array  $args Filtros normalizados.
	 * @param string $sub  Subtela (lista|quadro).
	 * @return array
	 */
	public static function crm_query_args( array $args, $sub = 'lista' ) {
		$out = array(
			'tela' => 'crm',
			'sub'  => $sub,
		);
		$map = array(
			'search'      => 'busca',
			'stage'       => 'estagio',
			'owner_id'    => 'resp',
			'lead_source' => 'origem',
			'tag'         => 'etiqueta',
			'orderby'     => 'ordem',
			'order'       => 'dir',
		);
		foreach ( $map as $k => $param ) {
			if ( isset( $args[ $k ] ) && '' !== (string) $args[ $k ] ) {
				$out[ $param ] = (string) $args[ $k ];
			}
		}
		return $out;
	}

	/**
	 * CRM: lista (padrão), quadro Kanban ou ficha.
	 *
	 * @return string
	 */
	public function crm() {
		if ( ! user_can( $this->uid, Capabilities::CAP_CRM ) ) {
			return '';
		}
		$sub = sanitize_key( self::get( 'sub' ) );
		if ( 'contato' === $sub ) {
			return $this->crm_contact();
		}
		Service::sync_clients();
		if ( 'quadro' === $sub ) {
			return $this->crm_board();
		}
		$args                   = self::crm_args( $_GET );
		$query                  = $args;
		$query['open_statuses'] = Service::open_statuses();
		$query['per_page']      = self::PER_PAGE;
		list( $items, $total )  = ( new CrmContactRepository() )->query( $query );
		return View::render(
			'team/crm-list',
			array(
				'items'      => $items,
				'total'      => (int) $total,
				'args'       => $args,
				'pages'      => (int) ceil( $total / self::PER_PAGE ),
				'base_args'  => self::crm_query_args( $args, 'lista' ),
				'stages'     => Service::stages(),
				'sources'    => Service::lead_sources(),
				'team'       => Service::team_members(),
				'can_export' => user_can( $this->uid, Capabilities::CAP_EXPORT ),
			)
		);
	}

	/**
	 * Quadro Kanban.
	 *
	 * @return string
	 */
	private function crm_board() {
		$owner = self::get( 'resp' );
		$args  = array(
			'open_statuses' => Service::open_statuses(),
			'per_page'      => 500,
			'page'          => 1,
			'orderby'       => 'updated_at',
			'order'         => 'DESC',
		);
		if ( '' !== $owner ) {
			$args['owner_id'] = 'none' === $owner ? 'none' : (int) $owner;
		}
		list( $items ) = ( new CrmContactRepository() )->query( $args );
		$stages        = Service::stages();
		$columns       = array_fill_keys( array_keys( $stages ), array() );
		$others        = array();
		foreach ( $items as $c ) {
			if ( isset( $columns[ $c['stage'] ] ) ) {
				$columns[ $c['stage'] ][] = $c;
			} else {
				$others[] = $c;
			}
		}
		return View::render(
			'team/crm-board',
			array(
				'stages'  => $stages,
				'columns' => $columns,
				'others'  => $others,
				'team'    => Service::team_members(),
				'owner'   => $owner,
			)
		);
	}

	/**
	 * Ficha do cliente (por id da ficha ou por usuário, criando sob demanda).
	 *
	 * @return string
	 */
	private function crm_contact() {
		$repo    = new CrmContactRepository();
		$contact = null;
		$id      = absint( self::get( 'id' ) );
		$user_id = absint( self::get( 'usuario' ) );
		if ( $id ) {
			$contact = $repo->find( $id );
		} elseif ( $user_id && ( user_can( $user_id, Capabilities::CAP_CLIENT ) || $repo->find_by_user( $user_id ) || ( new SubmissionRepository() )->for_user( $user_id ) ) ) {
			$contact = Service::contact_for_user( $user_id );
		}
		if ( ! $contact ) {
			return '<div class="ebcr-card"><div class="ebcr-alert ebcr-alert--error" role="alert">' . esc_html__( 'Ficha não encontrada.', 'eb-credito-rural' ) . '</div></div>';
		}
		$activities = ( new CrmActivityRepository() )->for_contact( (int) $contact['id'] );
		$open_tasks = array();
		foreach ( $activities as $a ) {
			if ( Service::TYPE_TASK === $a['type'] && empty( $a['done_at'] ) ) {
				$open_tasks[] = $a;
			}
		}
		usort(
			$open_tasks,
			static function ( $a, $b ) {
				if ( empty( $a['due_at'] ) || empty( $b['due_at'] ) ) {
					return empty( $a['due_at'] ) <=> empty( $b['due_at'] );
				}
				return strcmp( $a['due_at'], $b['due_at'] );
			}
		);
		return View::render(
			'team/crm-contact',
			array(
				'contact'     => $contact,
				'user'        => get_userdata( (int) $contact['user_id'] ),
				'submissions' => ( new SubmissionRepository() )->for_user( (int) $contact['user_id'] ),
				'activities'  => $activities,
				'open_tasks'  => $open_tasks,
				'team'        => Service::team_members(),
				'stages'      => Service::stages(),
				'sources'     => Service::lead_sources(),
				'types'       => Service::activity_types(),
				'open_status' => Service::open_statuses(),
				'current_uid' => $this->uid,
				'tab'         => sanitize_key( self::get( 'tab' ) ),
			)
		);
	}

	// ------------------------------------------------------------------ relatórios

	/**
	 * Relatórios (filtros + tabelas + gráfico mensal).
	 *
	 * @return string
	 */
	public function reports() {
		if ( ! user_can( $this->uid, Capabilities::CAP_DASHBOARD ) ) {
			return '';
		}
		$filters  = Metrics::normalize( Reports::read_filters( $_GET ), $this->uid );
		$by_month = Metrics::by_month( $filters, 12 );
		Panel::chart_data(
			array(
				'months' => array(
					'labels'  => wp_list_pluck( $by_month, 'label' ),
					'counts'  => array_map( 'intval', wp_list_pluck( $by_month, 'count' ) ),
					'volumes' => array_map( 'floatval', wp_list_pluck( $by_month, 'volume' ) ),
				),
			)
		);
		return View::render(
			'team/reports',
			array(
				'filters'    => $filters,
				'funds'      => Options::pairs( 'funds' ),
				'statuses'   => Status::all(),
				'analysts'   => self::analysts(),
				'totals'     => Metrics::totals( $filters ),
				'by_fund'    => Metrics::by_fund( $filters ),
				'by_status'  => Metrics::by_status( $filters ),
				'by_month'   => $by_month,
				'by_analyst' => Metrics::by_analyst( $filters ),
				'stages'     => Metrics::stage_durations( $filters ),
				'can_export' => user_can( $this->uid, Capabilities::CAP_EXPORT ),
			)
		);
	}
}
