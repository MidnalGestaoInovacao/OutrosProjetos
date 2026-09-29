<?php
/**
 * CRM: ficha por cliente, atividades e tarefas, lista e quadro Kanban por estágio, exportação CSV. Entregue pelo módulo "CRM".
 *
 * @package EBCR
 */

namespace EBCR\Admin;

use EBCR\Crm\Service;
use EBCR\Database\CrmActivityRepository;
use EBCR\Database\CrmContactRepository;
use EBCR\Database\SubmissionRepository;
use EBCR\Roles\Capabilities;
use EBCR\Security\AuditLog;
use EBCR\Security\Nonces;
use EBCR\Support\View;

defined( 'ABSPATH' ) || exit;

/**
 * Tela Crédito Rural → CRM (?page=ebcr-crm&view=lista|quadro|contato).
 */
// phpcs:disable WordPress.Security.NonceVerification -- os handlers verificam nonce (Nonces::verify/verify_get) antes de ler a entrada; os parâmetros de navegação/filtro (GET) são apenas leitura.
final class Crm {

	const PAGE = 'ebcr-crm';

	/**
	 * Hooks (ações admin-post, lembretes, REST do Kanban, assets).
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_post_ebcr_admin_crm_contact', array( $this, 'handle_contact' ) );
		add_action( 'admin_post_ebcr_admin_crm_activity', array( $this, 'handle_activity' ) );
		add_action( 'admin_post_ebcr_admin_crm_task_done', array( $this, 'handle_task_done' ) );
		add_action( 'admin_post_ebcr_admin_crm_export', array( $this, 'handle_export' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'current_screen', array( $this, 'maybe_process_bulk' ) );
		add_action( 'ebcr_rest_routes', array( $this, 'rest_routes' ) );
		add_action( 'ebcr_daily', array( $this, 'daily' ) );
	}

	/**
	 * Processa a ação em massa da lista antes de qualquer saída (o redirecionamento precisa de cabeçalhos livres).
	 *
	 * @param \WP_Screen $screen Tela atual.
	 * @return void
	 */
	public function maybe_process_bulk( $screen ) {
		if ( ! $screen || false === strpos( (string) $screen->id, self::PAGE ) || empty( $_REQUEST['ebcr_crm_contact'] ) ) {
			return;
		}
		if ( ! current_user_can( Capabilities::CAP_CRM ) ) {
			return;
		}
		( new CrmList() )->process_bulk();
	}

	/**
	 * Lembretes diários de tarefas (ebcr_daily).
	 *
	 * @return void
	 */
	public function daily() {
		Service::send_reminders();
	}

	/**
	 * URL de uma visão do CRM.
	 *
	 * @param array $args Parâmetros (view, id, …).
	 * @return string
	 */
	public static function url( array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * URL da ficha de um contato.
	 *
	 * @param int $contact_id ID.
	 * @return string
	 */
	public static function contact_url( $contact_id ) {
		return self::url(
			array(
				'view' => 'contato',
				'id'   => (int) $contact_id,
			)
		);
	}

	/**
	 * Navegação entre lista e quadro (HTML escapado).
	 *
	 * @param string $current Visão atual.
	 * @return string
	 */
	public static function nav( $current ) {
		$items = array(
			'lista'  => __( 'Lista', 'eb-credito-rural' ),
			'quadro' => __( 'Quadro', 'eb-credito-rural' ),
		);
		$html  = '<nav class="ebcr-crm-nav" aria-label="' . esc_attr__( 'Visões do CRM', 'eb-credito-rural' ) . '">';
		foreach ( $items as $key => $label ) {
			$html .= sprintf( '<a class="button%s" href="%s"%s>%s</a> ', $key === $current ? ' button-primary' : '', esc_url( self::url( array( 'view' => $key ) ) ), $key === $current ? ' aria-current="page"' : '', esc_html( $label ) );
		}
		return $html . '</nav>';
	}

	/**
	 * Aviso vindo da query string (após redirecionamento).
	 *
	 * @return string HTML escapado ou vazio.
	 */
	public static function notice_html() {
		if ( empty( $_GET['ebcr_notice'] ) ) {
			return '';
		}
		$type = isset( $_GET['ebcr_type'] ) && 'error' === sanitize_key( wp_unslash( $_GET['ebcr_type'] ) ) ? 'error' : 'success';
		return sprintf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $type ), esc_html( sanitize_text_field( wp_unslash( $_GET['ebcr_notice'] ) ) ) );
	}

	/**
	 * Renderiza a tela (lista, quadro ou ficha conforme a query).
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( Capabilities::CAP_CRM ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'eb-credito-rural' ), 403 );
		}
		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'lista';
		if ( 'contato' === $view ) {
			$this->render_contact();
			return;
		}
		if ( 'quadro' === $view ) {
			$this->render_board();
			return;
		}
		Service::sync_clients();
		( new CrmList() )->render_page();
	}

	/**
	 * Ficha do cliente (por id da ficha ou por usuário, criando sob demanda).
	 *
	 * @return void
	 */
	private function render_contact() {
		$repo    = new CrmContactRepository();
		$contact = null;
		if ( ! empty( $_GET['id'] ) ) {
			$contact = $repo->find( absint( $_GET['id'] ) );
		} elseif ( ! empty( $_GET['user'] ) ) {
			$user_id = absint( $_GET['user'] );
			if ( user_can( $user_id, Capabilities::CAP_CLIENT ) || $repo->find_by_user( $user_id ) || ( new SubmissionRepository() )->for_user( $user_id ) ) {
				$contact = Service::contact_for_user( $user_id );
			}
		}
		if ( ! $contact ) {
			wp_die( esc_html__( 'Ficha não encontrada.', 'eb-credito-rural' ), 404 );
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
		View::show(
			'admin/crm-contact',
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
				'notice'      => self::notice_html(),
				'current_uid' => get_current_user_id(),
			)
		);
	}

	/**
	 * Quadro Kanban.
	 *
	 * @return void
	 */
	private function render_board() {
		Service::sync_clients();
		$owner = isset( $_GET['owner_id'] ) ? sanitize_text_field( wp_unslash( $_GET['owner_id'] ) ) : '';
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
		View::show(
			'admin/crm-board',
			array(
				'stages'  => $stages,
				'columns' => $columns,
				'others'  => $others,
				'team'    => Service::team_members(),
				'owner'   => $owner,
				'notice'  => self::notice_html(),
			)
		);
	}

	/**
	 * Volta à ficha com mensagem.
	 *
	 * @param int    $contact_id Ficha.
	 * @param string $msg        Mensagem.
	 * @param string $type       success|error.
	 * @param string $tab        Aba a abrir.
	 * @return void
	 */
	private function back( $contact_id, $msg, $type = 'success', $tab = '' ) {
		$args = array(
			'view'        => 'contato',
			'id'          => (int) $contact_id,
			'ebcr_notice' => rawurlencode( $msg ),
			'ebcr_type'   => $type,
		);
		if ( $tab ) {
			$args['tab'] = $tab;
		}
		wp_safe_redirect( self::url( $args ) );
		exit;
	}

	/**
	 * Lê a ficha do POST com verificação de nonce e capacidade.
	 *
	 * @param string $action Ação do nonce.
	 * @return array
	 */
	private function guard( $action ) {
		if ( ! current_user_can( Capabilities::CAP_CRM ) || ! Nonces::verify( $action ) ) {
			wp_die( esc_html__( 'Sem permissão ou sessão expirada.', 'eb-credito-rural' ), 403 );
		}
		$contact = ( new CrmContactRepository() )->find( isset( $_POST['contact_id'] ) ? absint( $_POST['contact_id'] ) : 0 );
		if ( ! $contact ) {
			wp_die( esc_html__( 'Ficha não encontrada.', 'eb-credito-rural' ), 404 );
		}
		return $contact;
	}

	/**
	 * Valor textual do POST.
	 *
	 * @param string $key Chave.
	 * @return string
	 */
	private function post( $key ) {
		return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
	}

	/**
	 * Salva os dados da ficha.
	 *
	 * @return void
	 */
	public function handle_contact() {
		$contact = $this->guard( 'crm_contact' );
		$r       = Service::update_contact(
			get_current_user_id(),
			(int) $contact['id'],
			array(
				'phone'          => $this->post( 'phone' ),
				'whatsapp'       => $this->post( 'whatsapp' ),
				'lead_source'    => $this->post( 'lead_source' ),
				'tags'           => $this->post( 'tags' ),
				'stage'          => $this->post( 'stage' ),
				'owner_id'       => (int) $this->post( 'owner_id' ),
				'next_action'    => $this->post( 'next_action' ),
				'next_action_at' => $this->post( 'next_action_at' ),
				'notes'          => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
			)
		);
		$this->back( $contact['id'], is_wp_error( $r ) ? $r->get_error_message() : __( 'Ficha atualizada.', 'eb-credito-rural' ), is_wp_error( $r ) ? 'error' : 'success' );
	}

	/**
	 * Registra atividade ou tarefa.
	 *
	 * @return void
	 */
	public function handle_activity() {
		$contact = $this->guard( 'crm_activity' );
		$r       = Service::add_activity(
			get_current_user_id(),
			(int) $contact['id'],
			array(
				'type'          => $this->post( 'type' ),
				'description'   => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
				'due_at'        => $this->post( 'due_at' ),
				'assignee_id'   => (int) $this->post( 'assignee_id' ),
				'submission_id' => (int) $this->post( 'submission_id' ),
			)
		);
		$is_task = Service::TYPE_TASK === $this->post( 'type' );
		$this->back( $contact['id'], is_wp_error( $r ) ? $r->get_error_message() : ( $is_task ? __( 'Tarefa criada.', 'eb-credito-rural' ) : __( 'Atividade registrada.', 'eb-credito-rural' ) ), is_wp_error( $r ) ? 'error' : 'success', 'atividades' );
	}

	/**
	 * Conclui tarefa.
	 *
	 * @return void
	 */
	public function handle_task_done() {
		$contact = $this->guard( 'crm_task' );
		$task    = ( new CrmActivityRepository() )->find( isset( $_POST['activity_id'] ) ? absint( $_POST['activity_id'] ) : 0 );
		if ( ! $task || (int) $task['contact_id'] !== (int) $contact['id'] ) {
			$this->back( $contact['id'], __( 'Tarefa não encontrada.', 'eb-credito-rural' ), 'error' );
		}
		$r = Service::complete_task( get_current_user_id(), (int) $task['id'] );
		$this->back( $contact['id'], is_wp_error( $r ) ? $r->get_error_message() : __( 'Tarefa concluída.', 'eb-credito-rural' ), is_wp_error( $r ) ? 'error' : 'success', 'atividades' );
	}

	/**
	 * Filtros da lista lidos da query string.
	 *
	 * @return array
	 */
	public static function list_args_from_request() {
		$g = static function ( $k ) {
			return isset( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) : '';
		};
		return array(
			'search'      => $g( 's' ),
			'stage'       => sanitize_key( $g( 'stage' ) ),
			'owner_id'    => $g( 'owner_id' ),
			'lead_source' => sanitize_key( $g( 'lead_source' ) ),
			'tag'         => $g( 'tag' ),
			'orderby'     => sanitize_key( $g( 'orderby' ) ),
			'order'       => $g( 'order' ),
		);
	}

	/**
	 * Exporta CSV da lista (GET com nonce; exige ebcr_export).
	 *
	 * @return void
	 */
	public function handle_export() {
		if ( ! current_user_can( Capabilities::CAP_CRM ) || ! current_user_can( Capabilities::CAP_EXPORT ) || ! Nonces::verify_get( 'crm_export' ) ) {
			wp_die( esc_html__( 'Sem permissão ou sessão expirada.', 'eb-credito-rural' ), 403 );
		}
		$rows = Service::export_rows( self::list_args_from_request() );
		AuditLog::log(
			'export',
			'crm_contact',
			'',
			array(
				'format' => 'csv',
				'count'  => count( $rows ) - 1,
			)
		);
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="crm-contatos-' . gmdate( 'Ymd-His' ) . '.csv"' );
		echo Service::csv_string( $rows ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV.
		exit;
	}

	/**
	 * Rotas REST do Kanban (namespace ebcr/v1).
	 *
	 * @param string $ns Namespace.
	 * @return void
	 */
	public function rest_routes( $ns ) {
		register_rest_route(
			$ns,
			'/crm/contacts/(?P<id>\d+)/stage',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_stage' ),
				'permission_callback' => static function () {
					return current_user_can( Capabilities::CAP_CRM );
				},
				'args'                => array(
					'stage' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
	}

	/**
	 * POST /crm/contacts/{id}/stage.
	 *
	 * @param \WP_REST_Request $request Requisição.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_stage( \WP_REST_Request $request ) {
		$r = Service::change_stage( get_current_user_id(), (int) $request['id'], (string) $request['stage'] );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return rest_ensure_response(
			array(
				'ok'    => true,
				'id'    => (int) $r['id'],
				'stage' => $r['stage'],
				'label' => Service::stage_label( $r['stage'] ),
			)
		);
	}

	/**
	 * Assets só nas telas do CRM.
	 *
	 * @param string $hook Hook da tela.
	 * @return void
	 */
	public function assets( $hook ) {
		if ( false === strpos( (string) $hook, self::PAGE ) ) {
			return;
		}
		wp_enqueue_style( 'ebcr-crm', EBCR_URL . 'assets/css/crm.css', array( 'ebcr-admin' ), EBCR_VERSION );
		wp_enqueue_script( 'ebcr-crm', EBCR_URL . 'assets/js/crm.js', array(), EBCR_VERSION, true );
		wp_localize_script(
			'ebcr-crm',
			'ebcrCrm',
			array(
				'root'  => esc_url_raw( rest_url( \EBCR\Rest\Routes::NS . '/' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'i18n'  => array(
					'moved'   => __( 'Estágio atualizado.', 'eb-credito-rural' ),
					'error'   => __( 'Não foi possível mover o contato. Tente novamente.', 'eb-credito-rural' ),
					'moveTo'  => __( 'Mover para', 'eb-credito-rural' ),
					'working' => __( 'Salvando…', 'eb-credito-rural' ),
				),
			)
		);
	}
}
