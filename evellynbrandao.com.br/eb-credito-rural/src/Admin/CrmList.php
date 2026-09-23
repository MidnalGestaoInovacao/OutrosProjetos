<?php
/**
 * Lista de contatos do CRM (WP_List_Table).
 *
 * @package EBCR
 */

namespace EBCR\Admin;

use EBCR\Crm\Service;
use EBCR\Database\CrmContactRepository;
use EBCR\Roles\Capabilities;
use EBCR\Security\Nonces;
use EBCR\Support\Helpers;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Busca, filtros, ordenação, paginação, ação em massa (responsável) e exportação.
 */
// phpcs:disable WordPress.Security.NonceVerification -- a ação em massa verifica o nonce (check_admin_referer) antes de ler a entrada; os filtros (GET) são apenas leitura.
final class CrmList extends \WP_List_Table {

	/**
	 * Construtor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'ebcr_crm_contact',
				'plural'   => 'ebcr_crm_contacts',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Página completa.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( Capabilities::CAP_CRM ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'eb-credito-rural' ), 403 );
		}
		$this->prepare_items();
		echo '<div class="wrap ebcr-admin ebcr-crm"><h1 class="wp-heading-inline">' . esc_html__( 'CRM — contatos', 'eb-credito-rural' ) . '</h1>';
		echo Crm::nav( 'lista' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML escapado no helper.
		echo Crm::notice_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- idem.
		echo '<form method="get"><input type="hidden" name="page" value="' . esc_attr( Crm::PAGE ) . '"><input type="hidden" name="view" value="lista">';
		$this->search_box( __( 'Buscar (nome, e-mail, telefone)', 'eb-credito-rural' ), 'ebcr-crm' );
		$this->display();
		echo '</form></div>';
	}

	/**
	 * Colunas.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'               => '<input type="checkbox">',
			'user_name'        => __( 'Cliente', 'eb-credito-rural' ),
			'contact'          => __( 'Contato', 'eb-credito-rural' ),
			'stage'            => __( 'Estágio', 'eb-credito-rural' ),
			'owner'            => __( 'Responsável', 'eb-credito-rural' ),
			'next_action'      => __( 'Próxima ação', 'eb-credito-rural' ),
			'open_submissions' => __( 'Solicitações abertas', 'eb-credito-rural' ),
			'last_activity_at' => __( 'Última atividade', 'eb-credito-rural' ),
		);
	}

	/**
	 * Ordenáveis.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'user_name'        => array( 'user_name', false ),
			'stage'            => array( 'stage', false ),
			'next_action'      => array( 'next_action_at', false ),
			'open_submissions' => array( 'open_submissions', false ),
			'last_activity_at' => array( 'last_activity_at', false ),
		);
	}

	/**
	 * Ações em massa.
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		return array( 'assign_owner' => __( 'Atribuir ao responsável selecionado', 'eb-credito-rural' ) );
	}

	/**
	 * Filtros acima da tabela.
	 *
	 * @param string $which top|bottom.
	 * @return void
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		$args = Crm::list_args_from_request();
		$team = Service::team_members();
		echo '<div class="alignleft actions ebcr-crm-filters">';
		echo '<select name="stage"><option value="">' . esc_html__( 'Todos os estágios', 'eb-credito-rural' ) . '</option>';
		foreach ( Service::stages() as $k => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $args['stage'], $k, false ), esc_html( $label ) );
		}
		echo '</select>';
		echo '<select name="owner_id"><option value="">' . esc_html__( 'Qualquer responsável', 'eb-credito-rural' ) . '</option><option value="none"' . selected( $args['owner_id'], 'none', false ) . '>' . esc_html__( 'Sem responsável', 'eb-credito-rural' ) . '</option>';
		foreach ( $team as $u ) {
			printf( '<option value="%d"%s>%s</option>', (int) $u->ID, selected( (string) $args['owner_id'], (string) $u->ID, false ), esc_html( $u->display_name ) );
		}
		echo '</select>';
		echo '<select name="lead_source"><option value="">' . esc_html__( 'Todas as origens', 'eb-credito-rural' ) . '</option>';
		foreach ( Service::lead_sources() as $k => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $args['lead_source'], $k, false ), esc_html( $label ) );
		}
		echo '</select>';
		printf( '<input type="text" name="tag" value="%s" placeholder="%s" aria-label="%s" class="ebcr-crm-tag-filter">', esc_attr( $args['tag'] ), esc_attr__( 'Tag', 'eb-credito-rural' ), esc_attr__( 'Filtrar por tag', 'eb-credito-rural' ) );
		submit_button( __( 'Filtrar', 'eb-credito-rural' ), 'secondary', 'filter_action', false );
		echo ' <select name="bulk_owner"><option value="">' . esc_html__( 'Responsável (para ação em massa)', 'eb-credito-rural' ) . '</option><option value="0">' . esc_html__( '— Remover responsável —', 'eb-credito-rural' ) . '</option>';
		foreach ( $team as $u ) {
			printf( '<option value="%d">%s</option>', (int) $u->ID, esc_html( $u->display_name ) );
		}
		echo '</select>';
		if ( current_user_can( Capabilities::CAP_EXPORT ) ) {
			$export = array_filter( $args );
			unset( $export['orderby'], $export['order'] );
			if ( isset( $export['search'] ) ) {
				$export['s'] = $export['search'];
				unset( $export['search'] );
			}
			$url = Nonces::url( add_query_arg( array_merge( array( 'action' => 'ebcr_admin_crm_export' ), array_map( 'rawurlencode', $export ) ), admin_url( 'admin-post.php' ) ), 'crm_export' );
			printf( ' <a class="button" href="%s">%s</a>', esc_url( $url ), esc_html__( 'Exportar CSV', 'eb-credito-rural' ) );
		}
		echo '</div>';
	}

	/**
	 * Prepara itens (filtros, ordenação, paginação e ação em massa).
	 *
	 * @return void
	 */
	public function prepare_items() {
		$per_page              = (int) get_user_option( 'ebcr_per_page' );
		$per_page              = $per_page > 0 ? $per_page : 20;
		$args                  = Crm::list_args_from_request();
		$args['open_statuses'] = Service::open_statuses();
		$args['per_page']      = $per_page;
		$args['page']          = $this->get_pagenum();
		list( $items, $total ) = ( new CrmContactRepository() )->query( $args );
		$this->items           = $items;
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * Ação em massa: atribuir responsável. Chamada em current_screen (antes dos cabeçalhos) por Crm::maybe_process_bulk().
	 *
	 * @return void
	 */
	public function process_bulk() {
		if ( 'assign_owner' !== $this->current_action() || empty( $_REQUEST['ebcr_crm_contact'] ) ) {
			return;
		}
		check_admin_referer( 'bulk-' . $this->_args['plural'] );
		if ( ! isset( $_REQUEST['bulk_owner'] ) || '' === $_REQUEST['bulk_owner'] ) {
			$this->redirect( __( 'Selecione o responsável para a ação em massa.', 'eb-credito-rural' ), 'error' );
		}
		$ids = array_map( 'absint', (array) wp_unslash( $_REQUEST['ebcr_crm_contact'] ) );
		$r   = Service::bulk_assign( get_current_user_id(), $ids, absint( $_REQUEST['bulk_owner'] ) );
		if ( is_wp_error( $r ) ) {
			$this->redirect( $r->get_error_message(), 'error' );
		}
		/* translators: %d: quantidade de fichas */
		$this->redirect( sprintf( __( 'Responsável atribuído em %d ficha(s).', 'eb-credito-rural' ), (int) $r ) );
	}

	/**
	 * Redireciona à lista com aviso.
	 *
	 * @param string $msg  Mensagem.
	 * @param string $type success|error.
	 * @return void
	 */
	private function redirect( $msg, $type = 'success' ) {
		wp_safe_redirect(
			Crm::url(
				array(
					'view'        => 'lista',
					'ebcr_notice' => rawurlencode( $msg ),
					'ebcr_type'   => $type,
				)
			)
		);
		exit;
	}

	/**
	 * Checkbox.
	 *
	 * @param array $item Linha.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="ebcr_crm_contact[]" value="%d">', (int) $item['id'] );
	}

	/**
	 * Colunas.
	 *
	 * @param array  $item   Linha.
	 * @param string $column Coluna.
	 * @return string
	 */
	public function column_default( $item, $column ) {
		switch ( $column ) {
			case 'user_name':
				$url  = Crm::contact_url( (int) $item['id'] );
				$name = $item['user_name'] ? $item['user_name'] : __( '(usuário removido)', 'eb-credito-rural' );
				return sprintf( '<strong><a href="%s">%s</a></strong><br><span class="description">%s</span><div class="row-actions"><span><a href="%s">%s</a></span></div>', esc_url( $url ), esc_html( $name ), esc_html( (string) $item['user_email'] ), esc_url( $url ), esc_html__( 'Abrir ficha', 'eb-credito-rural' ) );
			case 'contact':
				$parts = array();
				if ( $item['phone'] ) {
					$parts[] = esc_html( Service::format_phone( $item['phone'] ) );
				}
				if ( $item['whatsapp'] ) {
					$link    = Service::whatsapp_link( $item['whatsapp'] );
					$parts[] = $link ? sprintf( '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>', esc_url( $link ), esc_html( 'WhatsApp ' . Service::format_phone( $item['whatsapp'] ) ) ) : esc_html( Service::format_phone( $item['whatsapp'] ) );
				}
				return $parts ? implode( '<br>', $parts ) : '—';
			case 'stage':
				return sprintf( '<span class="ebcr-badge" style="--ebcr-badge:%s">%s</span>', esc_attr( Service::stage_color( $item['stage'] ) ), esc_html( Service::stage_label( $item['stage'] ) ) );
			case 'owner':
				return esc_html( $item['owner_id'] ? Helpers::user_name( (int) $item['owner_id'] ) : '—' );
			case 'next_action':
				if ( ! $item['next_action'] && ! $item['next_action_at'] ) {
					return '—';
				}
				$late = $item['next_action_at'] && strtotime( $item['next_action_at'] . ' UTC' ) < time();
				return esc_html( $item['next_action'] ) . ( $item['next_action_at'] ? '<br><span class="' . ( $late ? 'ebcr-status-warn' : 'description' ) . '">' . esc_html( Helpers::date( $item['next_action_at'] ) ) . '</span>' : '' );
			case 'open_submissions':
				return (int) $item['open_submissions'] > 0 ? '<strong>' . (int) $item['open_submissions'] . '</strong>' : '0';
			case 'last_activity_at':
				return esc_html( Helpers::date( $item['last_activity_at'] ) );
			default:
				return '';
		}
	}

	/**
	 * Sem itens.
	 *
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'Nenhum contato encontrado.', 'eb-credito-rural' );
	}
}
