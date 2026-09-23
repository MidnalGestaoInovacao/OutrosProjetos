<?php
/**
 * Métricas do painel e dos relatórios: funil por status, série mensal, distribuição por UF, atividade e tipo de
 * garantia, tempo médio por etapa do fluxo e totais por fundo/carteira, por status e por analista.
 *
 * Todas as funções recebem o mesmo conjunto de filtros (período de envio, fundo, status, analista) e devolvem
 * arrays prontos para a tela ou para o CSV. As consultas evitam funções de data específicas do MySQL: as linhas
 * são buscadas com filtros simples e agrupadas em PHP, o que funciona tanto em MySQL quanto em SQLite.
 *
 * @package EBCR
 */

namespace EBCR\Reports;

use EBCR\Database\Db;
use EBCR\Domain\Status;
use EBCR\Forms\Steps;
use EBCR\Roles\Capabilities;
use EBCR\Security\Crypto;
use EBCR\Support\Helpers;
use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Funções de agregação (sem estado).
 */
final class Metrics {

	/**
	 * Status que contam como "aprovada" para volume aprovado e taxa de aprovação.
	 *
	 * @var string[]
	 */
	const APPROVED_STATUSES = array( Status::APPROVED, Status::FORMALIZATION, Status::DONE );

	/**
	 * Tamanho dos lotes nas cláusulas IN().
	 *
	 * @var int
	 */
	const CHUNK = 500;

	/**
	 * Máximo de meses na série mensal.
	 *
	 * @var int
	 */
	const MAX_MONTHS = 36;

	/**
	 * Normaliza os filtros e aplica a regra "analista vê só as atribuídas" para o usuário informado.
	 *
	 * @param array    $filters date_from, date_to (Y-m-d), fund, status, assigned_to, only_assigned_to.
	 * @param int|null $user_id Usuário que consulta (null = sem restrição por usuário).
	 * @return array Filtros normalizados (sempre com todas as chaves).
	 */
	public static function normalize( array $filters, $user_id = null ) {
		$status = isset( $filters['status'] ) ? sanitize_key( (string) $filters['status'] ) : '';
		$out    = array(
			'date_from'        => self::valid_date( isset( $filters['date_from'] ) ? $filters['date_from'] : '' ),
			'date_to'          => self::valid_date( isset( $filters['date_to'] ) ? $filters['date_to'] : '' ),
			'fund'             => isset( $filters['fund'] ) ? sanitize_key( (string) $filters['fund'] ) : '',
			'status'           => Status::exists( $status ) ? $status : '',
			'assigned_to'      => isset( $filters['assigned_to'] ) ? absint( $filters['assigned_to'] ) : 0,
			'only_assigned_to' => isset( $filters['only_assigned_to'] ) ? absint( $filters['only_assigned_to'] ) : 0,
		);
		if ( null !== $user_id ) {
			$user_id = (int) $user_id;
			if ( Options::bool( 'analyst_only_assigned' ) && ! user_can( $user_id, Capabilities::CAP_FINAL_STATUS ) && ! user_can( $user_id, Capabilities::CAP_SETTINGS ) ) {
				$out['only_assigned_to'] = $user_id;
			}
		}
		return $out;
	}

	/**
	 * Linhas de solicitações (enviadas, não excluídas) que atendem aos filtros.
	 *
	 * @param array $filters Filtros (ver normalize()).
	 * @return array Linhas com id, public_id, protocol, status, fund, assigned_to, requested_amount, submitted_at, created_at, updated_at.
	 */
	public static function rows( array $filters ) {
		global $wpdb;
		$f     = self::normalize( $filters );
		$t     = Db::table( 'submissions' );
		$where = array( 'deleted_at IS NULL', 'status <> %s' );
		$vals  = array( Status::DRAFT );
		if ( $f['date_from'] ) {
			$where[] = 'submitted_at >= %s';
			$vals[]  = $f['date_from'] . ' 00:00:00';
		}
		if ( $f['date_to'] ) {
			$where[] = 'submitted_at <= %s';
			$vals[]  = $f['date_to'] . ' 23:59:59';
		}
		if ( $f['fund'] ) {
			$where[] = 'fund = %s';
			$vals[]  = $f['fund'];
		}
		if ( $f['status'] ) {
			$where[] = 'status = %s';
			$vals[]  = $f['status'];
		}
		if ( $f['assigned_to'] ) {
			$where[] = 'assigned_to = %d';
			$vals[]  = $f['assigned_to'];
		}
		if ( $f['only_assigned_to'] ) {
			$where[] = 'assigned_to = %d';
			$vals[]  = $f['only_assigned_to'];
		}
		$sql = "SELECT id, public_id, protocol, status, fund, assigned_to, requested_amount, submitted_at, created_at, updated_at FROM `{$t}` WHERE " . implode( ' AND ', $where ) . ' ORDER BY submitted_at ASC, id ASC';
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, $vals ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- SQL montado com placeholders e nome de tabela interno.
	}

	/**
	 * Totais gerais do conjunto filtrado.
	 *
	 * @param array $filters Filtros.
	 * @return array count, requested, approved_count, approved_volume, rejected_count, in_progress, ticket, approval_rate (0-100 ou null).
	 */
	public static function totals( array $filters ) {
		return self::aggregate( self::rows( $filters ) );
	}

	/**
	 * Funil: quantidade por status, na ordem do fluxo (inclui zeros).
	 *
	 * @param array $filters Filtros.
	 * @return array status => quantidade.
	 */
	public static function funnel( array $filters ) {
		$out = array();
		foreach ( Status::all() as $key => $def ) {
			if ( Status::DRAFT !== $key ) {
				$out[ $key ] = 0;
			}
		}
		foreach ( self::rows( $filters ) as $r ) {
			if ( isset( $out[ $r['status'] ] ) ) {
				++$out[ $r['status'] ];
			} else {
				$out[ $r['status'] ] = 1;
			}
		}
		return $out;
	}

	/**
	 * Por status: quantidade e volume solicitado (ordem do fluxo, inclui zeros).
	 *
	 * @param array $filters Filtros.
	 * @return array Lista de { key, label, count, requested }.
	 */
	public static function by_status( array $filters ) {
		$out = array();
		foreach ( Status::all() as $key => $def ) {
			if ( Status::DRAFT !== $key ) {
				$out[ $key ] = array(
					'key'       => $key,
					'label'     => $def['label'],
					'count'     => 0,
					'requested' => 0.0,
				);
			}
		}
		foreach ( self::rows( $filters ) as $r ) {
			if ( ! isset( $out[ $r['status'] ] ) ) {
				$out[ $r['status'] ] = array(
					'key'       => $r['status'],
					'label'     => Status::label( $r['status'] ),
					'count'     => 0,
					'requested' => 0.0,
				);
			}
			++$out[ $r['status'] ]['count'];
			$out[ $r['status'] ]['requested'] += (float) $r['requested_amount'];
		}
		return array_values( $out );
	}

	/**
	 * Série mensal (mês de envio, no fuso do site): quantidade, volume solicitado e aprovadas.
	 *
	 * Sem período nos filtros, cobre os últimos N meses até o mês atual; com período, cobre os meses entre
	 * date_from e date_to (limitado a MAX_MONTHS).
	 *
	 * @param array $filters Filtros.
	 * @param int   $months  Quantidade de meses (padrão 12).
	 * @return array Lista de { month (Y-m), label, count, volume, approved_count, approved_volume }.
	 */
	public static function by_month( array $filters, $months = 12 ) {
		$f      = self::normalize( $filters );
		$months = max( 1, min( self::MAX_MONTHS, (int) $months ) );
		if ( $f['date_from'] && $f['date_to'] && $f['date_from'] <= $f['date_to'] ) {
			$end    = gmmktime( 12, 0, 0, (int) substr( $f['date_to'], 5, 2 ), 15, (int) substr( $f['date_to'], 0, 4 ) );
			$start  = gmmktime( 12, 0, 0, (int) substr( $f['date_from'], 5, 2 ), 15, (int) substr( $f['date_from'], 0, 4 ) );
			$span   = ( (int) gmdate( 'Y', $end ) - (int) gmdate( 'Y', $start ) ) * 12 + ( (int) gmdate( 'n', $end ) - (int) gmdate( 'n', $start ) ) + 1;
			$months = max( 1, min( self::MAX_MONTHS, $span ) );
		} else {
			$end = gmmktime( 12, 0, 0, (int) wp_date( 'n' ), 15, (int) wp_date( 'Y' ) );
		}
		$buckets = array();
		for ( $i = $months - 1; $i >= 0; $i-- ) {
			$ts              = gmmktime( 12, 0, 0, (int) gmdate( 'n', $end ) - $i, 15, (int) gmdate( 'Y', $end ) );
			$key             = gmdate( 'Y-m', $ts );
			$buckets[ $key ] = array(
				'month'           => $key,
				'label'           => wp_date( 'M/y', $ts ),
				'count'           => 0,
				'volume'          => 0.0,
				'approved_count'  => 0,
				'approved_volume' => 0.0,
			);
		}
		foreach ( self::rows( $f ) as $r ) {
			$when = $r['submitted_at'] ? $r['submitted_at'] : $r['created_at'];
			$key  = wp_date( 'Y-m', strtotime( $when . ' UTC' ) );
			if ( ! isset( $buckets[ $key ] ) ) {
				continue;
			}
			++$buckets[ $key ]['count'];
			$buckets[ $key ]['volume'] += (float) $r['requested_amount'];
			if ( in_array( $r['status'], self::APPROVED_STATUSES, true ) ) {
				++$buckets[ $key ]['approved_count'];
				$buckets[ $key ]['approved_volume'] += (float) $r['requested_amount'];
			}
		}
		return array_values( $buckets );
	}

	/**
	 * Por UF do tomador (etapa 1), da maior para a menor quantidade.
	 *
	 * @param array $filters Filtros.
	 * @return array Lista de { key, label, name, count }.
	 */
	public static function by_uf( array $filters ) {
		$rows   = self::rows( $filters );
		$data   = self::section_data( wp_list_pluck( $rows, 'id' ), 'identificacao' );
		$ufs    = Helpers::ufs();
		$counts = array();
		foreach ( $rows as $r ) {
			$uf = isset( $data[ (int) $r['id'] ]['uf'] ) ? strtoupper( substr( (string) $data[ (int) $r['id'] ]['uf'], 0, 2 ) ) : '';
			if ( '' === $uf || ! isset( $ufs[ $uf ] ) ) {
				$uf = '';
			}
			$counts[ $uf ] = isset( $counts[ $uf ] ) ? $counts[ $uf ] + 1 : 1;
		}
		$out = array();
		foreach ( $counts as $uf => $n ) {
			$out[] = array(
				'key'   => $uf,
				'label' => '' === $uf ? __( 'Não informada', 'eb-credito-rural' ) : $uf,
				'name'  => '' === $uf ? __( 'UF não informada', 'eb-credito-rural' ) : $ufs[ $uf ],
				'count' => $n,
			);
		}
		return self::sort_desc( $out );
	}

	/**
	 * Por atividade produtiva (etapa 3; uma solicitação pode ter várias atividades).
	 *
	 * @param array $filters Filtros.
	 * @return array Lista de { key, label, count }.
	 */
	public static function by_activity( array $filters ) {
		$rows   = self::rows( $filters );
		$data   = self::section_data( wp_list_pluck( $rows, 'id' ), 'producao' );
		$labels = Steps::options( 'activities' );
		$counts = array();
		foreach ( $rows as $r ) {
			$acts = isset( $data[ (int) $r['id'] ]['atividades'] ) ? (array) $data[ (int) $r['id'] ]['atividades'] : array();
			$acts = array_unique( array_filter( array_map( 'sanitize_key', $acts ) ) );
			if ( ! $acts ) {
				$acts = array( '' );
			}
			foreach ( $acts as $a ) {
				$counts[ $a ] = isset( $counts[ $a ] ) ? $counts[ $a ] + 1 : 1;
			}
		}
		$out = array();
		foreach ( $counts as $a => $n ) {
			$out[] = array(
				'key'   => $a,
				'label' => '' === $a ? __( 'Não informada', 'eb-credito-rural' ) : ( isset( $labels[ $a ] ) ? $labels[ $a ] : $a ),
				'count' => $n,
			);
		}
		return self::sort_desc( $out );
	}

	/**
	 * Por tipo de garantia oferecida (etapa 5; conta garantias, não solicitações).
	 *
	 * @param array $filters Filtros.
	 * @return array Lista de { key, label, count }.
	 */
	public static function by_guarantee_type( array $filters ) {
		global $wpdb;
		$ids    = array_map( 'intval', wp_list_pluck( self::rows( $filters ), 'id' ) );
		$labels = Steps::options( 'guarantee_types' );
		$t      = Db::table( 'guarantees' );
		$counts = array();
		foreach ( array_chunk( $ids, self::CHUNK ) as $chunk ) {
			$in   = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
			$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT type, COUNT(*) AS n FROM `{$t}` WHERE submission_id IN ($in) GROUP BY type", $chunk ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- placeholders %d gerados em $in e nome de tabela interno.
			foreach ( $rows as $r ) {
				$k            = sanitize_key( $r['type'] );
				$counts[ $k ] = ( isset( $counts[ $k ] ) ? $counts[ $k ] : 0 ) + (int) $r['n'];
			}
		}
		$out = array();
		foreach ( $counts as $k => $n ) {
			$out[] = array(
				'key'   => $k,
				'label' => isset( $labels[ $k ] ) ? $labels[ $k ] : $k,
				'count' => $n,
			);
		}
		return self::sort_desc( $out );
	}

	/**
	 * Tempo médio (dias) por etapa do fluxo, a partir do histórico de status. Considera a primeira chegada a cada
	 * marco: enviada, pré-análise, análise de crédito e decisão final (aprovada ou não aprovada).
	 *
	 * @param array $filters Filtros.
	 * @return array Lista de { key, label, days (float|null), n } — n é a quantidade de solicitações com as duas datas.
	 */
	public static function stage_durations( array $filters ) {
		global $wpdb;
		$rows  = self::rows( $filters );
		$marks = array();
		foreach ( $rows as $r ) {
			$marks[ (int) $r['id'] ] = array(
				'submitted' => $r['submitted_at'] ? strtotime( $r['submitted_at'] . ' UTC' ) : null,
				'pre'       => null,
				'credit'    => null,
				'decision'  => null,
			);
		}
		$t = Db::table( 'status_history' );
		foreach ( array_chunk( array_keys( $marks ), self::CHUNK ) as $chunk ) {
			$in      = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
			$history = (array) $wpdb->get_results( $wpdb->prepare( "SELECT submission_id, to_status, created_at FROM `{$t}` WHERE submission_id IN ($in) ORDER BY submission_id ASC, created_at ASC, id ASC", $chunk ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- placeholders %d gerados em $in e nome de tabela interno.
			foreach ( $history as $h ) {
				$sid = (int) $h['submission_id'];
				$ts  = strtotime( $h['created_at'] . ' UTC' );
				if ( ! isset( $marks[ $sid ] ) || false === $ts ) {
					continue;
				}
				$slot = self::milestone( $h['to_status'] );
				if ( $slot && null === $marks[ $sid ][ $slot ] ) {
					$marks[ $sid ][ $slot ] = $ts;
				}
			}
		}
		$stages = array(
			'submitted_to_pre'   => array( 'submitted', 'pre', __( 'Enviada → Pré-análise', 'eb-credito-rural' ) ),
			'pre_to_credit'      => array( 'pre', 'credit', __( 'Pré-análise → Análise de crédito', 'eb-credito-rural' ) ),
			'credit_to_decision' => array( 'credit', 'decision', __( 'Análise de crédito → Decisão final', 'eb-credito-rural' ) ),
			'submitted_to_final' => array( 'submitted', 'decision', __( 'Enviada → Decisão final (total)', 'eb-credito-rural' ) ),
		);
		$out    = array();
		foreach ( $stages as $key => $def ) {
			$sum = 0.0;
			$n   = 0;
			foreach ( $marks as $m ) {
				if ( null !== $m[ $def[0] ] && null !== $m[ $def[1] ] && $m[ $def[1] ] >= $m[ $def[0] ] ) {
					$sum += ( $m[ $def[1] ] - $m[ $def[0] ] ) / DAY_IN_SECONDS;
					++$n;
				}
			}
			$out[] = array(
				'key'   => $key,
				'label' => $def[2],
				'days'  => $n ? round( $sum / $n, 1 ) : null,
				'n'     => $n,
			);
		}
		return $out;
	}

	/**
	 * Totais por fundo/carteira: carteiras configuradas (mesmo com zero), depois chaves não configuradas presentes
	 * nos dados e, por fim, "Sem carteira".
	 *
	 * @param array $filters Filtros.
	 * @return array Lista de { key, label } + campos de aggregate().
	 */
	public static function by_fund( array $filters ) {
		$funds  = Options::pairs( 'funds' );
		$groups = array();
		foreach ( $funds as $k => $label ) {
			$groups[ $k ] = array();
		}
		$none = array();
		foreach ( self::rows( $filters ) as $r ) {
			$k = sanitize_key( (string) $r['fund'] );
			if ( '' === $k ) {
				$none[] = $r;
				continue;
			}
			$groups[ $k ][] = $r;
		}
		$out = array();
		foreach ( $groups as $k => $rows ) {
			$out[] = array_merge(
				array(
					'key'   => $k,
					'label' => isset( $funds[ $k ] ) ? $funds[ $k ] : $k,
				),
				self::aggregate( $rows )
			);
		}
		if ( $none ) {
			$out[] = array_merge(
				array(
					'key'   => '',
					'label' => __( 'Sem carteira', 'eb-credito-rural' ),
				),
				self::aggregate( $none )
			);
		}
		return $out;
	}

	/**
	 * Totais por analista responsável (da maior para a menor quantidade; "Sem responsável" por último).
	 *
	 * @param array $filters Filtros.
	 * @return array Lista de { key (ID), label } + campos de aggregate().
	 */
	public static function by_analyst( array $filters ) {
		$groups = array();
		foreach ( self::rows( $filters ) as $r ) {
			$groups[ (int) $r['assigned_to'] ][] = $r;
		}
		$out = array();
		foreach ( $groups as $uid => $rows ) {
			if ( ! $uid ) {
				$label = __( 'Sem responsável', 'eb-credito-rural' );
			} elseif ( get_userdata( $uid ) ) {
				$label = Helpers::user_name( $uid );
			} else {
				/* translators: %d: ID do usuário */
				$label = sprintf( __( 'Usuário removido (#%d)', 'eb-credito-rural' ), $uid );
			}
			$out[] = array_merge(
				array(
					'key'   => $uid,
					'label' => $label,
				),
				self::aggregate( $rows )
			);
		}
		usort(
			$out,
			static function ( $a, $b ) {
				if ( ( 0 === $a['key'] ) !== ( 0 === $b['key'] ) ) {
					return 0 === $a['key'] ? 1 : -1;
				}
				if ( $a['count'] !== $b['count'] ) {
					return $b['count'] - $a['count'];
				}
				return strcmp( $a['label'], $b['label'] );
			}
		);
		return $out;
	}

	/**
	 * Agrega um conjunto de linhas.
	 *
	 * @param array $rows Linhas de rows().
	 * @return array count, requested, approved_count, approved_volume, rejected_count, in_progress, ticket, approval_rate.
	 */
	private static function aggregate( array $rows ) {
		$out = array(
			'count'           => 0,
			'requested'       => 0.0,
			'approved_count'  => 0,
			'approved_volume' => 0.0,
			'rejected_count'  => 0,
			'in_progress'     => 0,
			'ticket'          => 0.0,
			'approval_rate'   => null,
		);
		foreach ( $rows as $r ) {
			$amount = (float) $r['requested_amount'];
			++$out['count'];
			$out['requested'] += $amount;
			if ( in_array( $r['status'], self::APPROVED_STATUSES, true ) ) {
				++$out['approved_count'];
				$out['approved_volume'] += $amount;
			} elseif ( Status::REJECTED === $r['status'] ) {
				++$out['rejected_count'];
			} elseif ( ! Status::is_final( $r['status'] ) ) {
				++$out['in_progress'];
			}
		}
		$decided              = $out['approved_count'] + $out['rejected_count'];
		$out['ticket']        = $out['count'] ? $out['requested'] / $out['count'] : 0.0;
		$out['approval_rate'] = $decided ? round( 100 * $out['approved_count'] / $decided, 1 ) : null;
		return $out;
	}

	/**
	 * Marco do fluxo correspondente a um status de destino.
	 *
	 * @param string $status Status.
	 * @return string|null submitted|pre|credit|decision.
	 */
	private static function milestone( $status ) {
		switch ( $status ) {
			case Status::SUBMITTED:
				return 'submitted';
			case Status::PRE_ANALYSIS:
				return 'pre';
			case Status::CREDIT:
				return 'credit';
			case Status::APPROVED:
			case Status::REJECTED:
				return 'decision';
			default:
				return null;
		}
	}

	/**
	 * Dados de uma seção do formulário para várias solicitações (descriptografa quando necessário).
	 *
	 * @param array  $ids     IDs das solicitações.
	 * @param string $section Seção (identificacao, producao…).
	 * @return array submission_id => dados.
	 */
	private static function section_data( array $ids, $section ) {
		global $wpdb;
		$t   = Db::table( 'submission_data' );
		$out = array();
		foreach ( array_chunk( array_map( 'intval', $ids ), self::CHUNK ) as $chunk ) {
			$in   = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
			$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT submission_id, data, encrypted FROM `{$t}` WHERE section = %s AND submission_id IN ($in)", array_merge( array( sanitize_key( $section ) ), $chunk ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders gerados e nome de tabela interno.
			foreach ( $rows as $r ) {
				$json = (string) $r['data'];
				if ( ! empty( $r['encrypted'] ) ) {
					$json = Crypto::decrypt( $json );
					if ( null === $json ) {
						continue;
					}
				}
				$data = json_decode( $json, true );
				if ( is_array( $data ) ) {
					$out[ (int) $r['submission_id'] ] = $data;
				}
			}
		}
		return $out;
	}

	/**
	 * Ordena por quantidade (desc) e rótulo (asc).
	 *
	 * @param array $items Itens com count e label.
	 * @return array
	 */
	private static function sort_desc( array $items ) {
		usort(
			$items,
			static function ( $a, $b ) {
				if ( $a['count'] !== $b['count'] ) {
					return $b['count'] - $a['count'];
				}
				return strcmp( $a['label'], $b['label'] );
			}
		);
		return $items;
	}

	/**
	 * Data Y-m-d válida ou string vazia.
	 *
	 * @param mixed $value Entrada.
	 * @return string
	 */
	private static function valid_date( $value ) {
		$value = trim( (string) $value );
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return '';
		}
		return $value;
	}
}
