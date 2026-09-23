<?php
/**
 * Lista de solicitações (WP_List_Table).
 *
 * @package EBCR
 */

namespace EBCR\Admin;

use EBCR\Database\SubmissionRepository;
use EBCR\Domain\Status;
use EBCR\Roles\Capabilities;
use EBCR\Support\Helpers;
use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Busca, filtros, ordenação e ações em massa.
 */
// phpcs:disable WordPress.Security.NonceVerification -- todos os handlers verificam o nonce (Nonces::verify/check_admin_referer) antes de ler a entrada.
final class SubmissionsList extends \WP_List_Table {

	/**
	 * Ação em massa já processada nesta requisição (em current_screen, antes dos cabeçalhos).
	 *
	 * @var bool
	 */
	private static $bulk_done = false;

	/**
	 * Carteiras configuradas (chave => nome), carregadas uma vez por página.
	 *
	 * @var array|null
	 */
	private $funds = null;

	/**
	 * Construtor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'ebcr_submission',
				'plural'   => 'ebcr_submissions',
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
		if ( ! current_user_can( Capabilities::CAP_VIEW ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'eb-credito-rural' ), 403 );
		}
		$this->prepare_items();
		echo '<div class="wrap ebcr-admin"><h1 class="wp-heading-inline">' . esc_html__( 'Solicitações de crédito', 'eb-credito-rural' ) . '</h1>';
		if ( ! empty( $_GET['ebcr_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- mensagem informativa.
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', 'error' === sanitize_key( wp_unslash( $_GET['ebcr_type'] ?? 'success' ) ) ? 'error' : 'success', esc_html( sanitize_text_field( wp_unslash( $_GET['ebcr_notice'] ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- idem.
		}
		echo '<form method="get"><input type="hidden" name="page" value="ebcr-submissions">';
		$this->search_box( __( 'Buscar (nome, e-mail, CPF/CNPJ, protocolo)', 'eb-credito-rural' ), 'ebcr' );
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
			'protocol'         => __( 'Protocolo', 'eb-credito-rural' ),
			'user'             => __( 'Cliente', 'eb-credito-rural' ),
			'status'           => __( 'Status', 'eb-credito-rural' ),
			'fund'             => __( 'Carteira', 'eb-credito-rural' ),
			'requested_amount' => __( 'Valor', 'eb-credito-rural' ),
			'assigned'         => __( 'Analista', 'eb-credito-rural' ),
			'submitted_at'     => __( 'Enviada em', 'eb-credito-rural' ),
			'updated_at'       => __( 'Atualizada', 'eb-credito-rural' ),
		);
	}

	/**
	 * Ordenáveis.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'protocol'         => array( 'protocol', false ),
			'status'           => array( 'status', false ),
			'requested_amount' => array( 'requested_amount', false ),
			'submitted_at'     => array( 'submitted_at', true ),
			'updated_at'       => array( 'updated_at', false ),
		);
	}

	/**
	 * Ações em massa.
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		$a = array();
		if ( current_user_can( Capabilities::CAP_EDIT ) ) {
			$a['assign'] = __( 'Atribuir ao analista selecionado', 'eb-credito-rural' );
		}
		if ( current_user_can( Capabilities::CAP_EXPORT ) ) {
			$a['export'] = __( 'Exportar CSV', 'eb-credito-rural' );
		}
		return $a;
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
		$g = static function ( $k ) {
			return isset( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) : '';
		}; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filtros de listagem.
		echo '<div class="alignleft actions">';
		echo '<select name="status"><option value="">' . esc_html__( 'Todos os status', 'eb-credito-rural' ) . '</option>';
		foreach ( Status::all() as $k => $def ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $g( 'status' ), $k, false ), esc_html( $def['label'] ) );
		}
		echo '</select>';
		echo '<select name="fund"><option value="">' . esc_html__( 'Todas as carteiras', 'eb-credito-rural' ) . '</option>';
		foreach ( $this->funds() as $k => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $g( 'fund' ), $k, false ), esc_html( $label ) );
		}
		echo '</select>';
		$analysts = get_users(
			array(
				'capability' => Capabilities::CAP_VIEW,
				'fields'     => array( 'ID', 'display_name' ),
			)
		);
		echo '<select name="assigned_to"><option value="">' . esc_html__( 'Qualquer analista', 'eb-credito-rural' ) . '</option>';
		foreach ( $analysts as $a ) {
			printf( '<option value="%d"%s>%s</option>', (int) $a->ID, selected( (int) $g( 'assigned_to' ), (int) $a->ID, false ), esc_html( $a->display_name ) );
		}
		echo '</select>';
		printf( '<input type="date" name="date_from" value="%s" aria-label="%s"> <input type="date" name="date_to" value="%s" aria-label="%s">', esc_attr( $g( 'date_from' ) ), esc_attr__( 'De', 'eb-credito-rural' ), esc_attr( $g( 'date_to' ) ), esc_attr__( 'Até', 'eb-credito-rural' ) );
		printf( '<input type="number" name="amount_min" placeholder="%s" value="%s" style="width:110px"> <input type="number" name="amount_max" placeholder="%s" value="%s" style="width:110px">', esc_attr__( 'Valor mín.', 'eb-credito-rural' ), esc_attr( $g( 'amount_min' ) ), esc_attr__( 'Valor máx.', 'eb-credito-rural' ), esc_attr( $g( 'amount_max' ) ) );
		submit_button( __( 'Filtrar', 'eb-credito-rural' ), 'secondary', 'filter_action', false );
		if ( current_user_can( Capabilities::CAP_EDIT ) ) {
			echo ' <select name="bulk_analyst"><option value="">' . esc_html__( 'Analista (para ação em massa)', 'eb-credito-rural' ) . '</option>';
			foreach ( $analysts as $a ) {
				printf( '<option value="%d">%s</option>', (int) $a->ID, esc_html( $a->display_name ) );
			}
			echo '</select>';
		}
		echo '</div>';
	}

	/**
	 * Prepara itens (com filtros, ordenação, paginação e ações em massa).
	 *
	 * @return void
	 */
	public function prepare_items() {
		if ( ! self::$bulk_done ) {
			$this->process_bulk();
		}
		$per_page = (int) get_user_option( 'ebcr_per_page' );
		$per_page = $per_page > 0 ? $per_page : 20;
		$g        = static function ( $k ) {
			return isset( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) : '';
		}; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filtros de listagem.
		$args     = array(
			'status'      => sanitize_key( $g( 'status' ) ),
			'fund'        => sanitize_key( $g( 'fund' ) ),
			'assigned_to' => (int) $g( 'assigned_to' ),
			'search'      => $g( 's' ),
			'date_from'   => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $g( 'date_from' ) ) ? $g( 'date_from' ) : '',
			'date_to'     => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $g( 'date_to' ) ) ? $g( 'date_to' ) : '',
			'amount_min'  => $g( 'amount_min' ),
			'amount_max'  => $g( 'amount_max' ),
			'orderby'     => sanitize_key( $g( 'orderby' ) ),
			'order'       => $g( 'order' ),
			'per_page'    => $per_page,
			'page'        => $this->get_pagenum(),
		);
		if ( Options::bool( 'analyst_only_assigned' ) && ! current_user_can( Capabilities::CAP_FINAL_STATUS ) && ! current_user_can( Capabilities::CAP_SETTINGS ) ) {
			$args['only_assigned_to'] = get_current_user_id();
		}
		list( $items, $total ) = ( new SubmissionRepository() )->query( $args );
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
	 * Ações em massa (atribuir / exportar).
	 *
	 * @return void
	 */
	public function process_bulk() {
		self::$bulk_done = true;
		$action          = $this->current_action();
		if ( ! $action || empty( $_REQUEST['ebcr_submission'] ) ) {
			return;
		}
		check_admin_referer( 'bulk-' . $this->_args['plural'] );
		$ids  = array_map( 'sanitize_text_field', (array) wp_unslash( $_REQUEST['ebcr_submission'] ) );
		$repo = new SubmissionRepository();
		if ( 'assign' === $action && current_user_can( Capabilities::CAP_EDIT ) ) {
			$analyst = isset( $_REQUEST['bulk_analyst'] ) ? absint( $_REQUEST['bulk_analyst'] ) : 0;
			foreach ( $ids as $pid ) {
				$s = $repo->find_by_public_id( $pid );
				if ( $s ) {
					\EBCR\Forms\SubmissionService::assign( get_current_user_id(), $s, $analyst );
				}
			}
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'        => 'ebcr-submissions',
						'ebcr_notice' => rawurlencode( __( 'Atribuição concluída.', 'eb-credito-rural' ) ),
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}
		if ( 'export' === $action && current_user_can( Capabilities::CAP_EXPORT ) ) {
			Actions::export_csv( $ids );
		}
	}

	/**
	 * Checkbox.
	 *
	 * @param array $item Linha.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="ebcr_submission[]" value="%s">', esc_attr( $item['public_id'] ) );
	}

	/**
	 * Colunas padrão.
	 *
	 * @param array  $item   Linha.
	 * @param string $column Coluna.
	 * @return string
	 */
	public function column_default( $item, $column ) {
		switch ( $column ) {
			case 'protocol':
				$url = admin_url( 'admin.php?page=ebcr-submissions&view=' . rawurlencode( $item['public_id'] ) );
				return sprintf( '<strong><a href="%s">%s</a></strong><div class="row-actions"><span><a href="%s">%s</a></span></div>', esc_url( $url ), esc_html( $item['protocol'] ? $item['protocol'] : __( '(rascunho)', 'eb-credito-rural' ) ), esc_url( $url ), esc_html__( 'Abrir', 'eb-credito-rural' ) );
			case 'user':
				return esc_html( $item['user_name'] ) . '<br><span class="description">' . esc_html( $item['user_email'] ) . '</span>';
			case 'status':
				return Status::badge( $item['status'] );
			case 'fund':
				$funds = $this->funds();
				$fund  = isset( $item['fund'] ) ? (string) $item['fund'] : '';
				return esc_html( '' === $fund ? '—' : ( isset( $funds[ $fund ] ) ? $funds[ $fund ] : $fund ) );
			case 'requested_amount':
				return esc_html( Helpers::money( $item['requested_amount'] ) );
			case 'assigned':
				return esc_html( $item['assigned_to'] ? Helpers::user_name( (int) $item['assigned_to'] ) : '—' );
			case 'submitted_at':
				return esc_html( Helpers::date( $item['submitted_at'] ) );
			case 'updated_at':
				return esc_html( Helpers::date( $item['updated_at'] ) );
			default:
				return '';
		}
	}

	/**
	 * Carteiras configuradas (Configurações → Fundos/carteiras).
	 *
	 * @return array chave => nome.
	 */
	private function funds() {
		if ( null === $this->funds ) {
			$this->funds = Options::pairs( 'funds' );
		}
		return $this->funds;
	}

	/**
	 * Sem itens.
	 *
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'Nenhuma solicitação encontrada.', 'eb-credito-rural' );
	}
}
