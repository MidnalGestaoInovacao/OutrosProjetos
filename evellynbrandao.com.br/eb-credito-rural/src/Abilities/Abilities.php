<?php
/**
 * Abilities (WordPress Abilities API) para configurar e operar o plugin por agentes de IA — expostas pelo Easy MCP AI como wp_ability_ebcr_*.
 *
 * @package EBCR
 */

namespace EBCR\Abilities;

use EBCR\Admin\Settings\Settings;
use EBCR\Cron\Scheduler;
use EBCR\Database\DocumentRepository;
use EBCR\Database\DocumentRequestRepository;
use EBCR\Database\MailQueueRepository;
use EBCR\Database\MessageRepository;
use EBCR\Database\StatusHistoryRepository;
use EBCR\Database\SubmissionRepository;
use EBCR\Domain\Status;
use EBCR\Files\FileGuard;
use EBCR\Files\UploadHandler;
use EBCR\Forms\DocumentMatrix;
use EBCR\Forms\SubmissionService;
use EBCR\Forms\Wizard;
use EBCR\Mail\Mailer;
use EBCR\Mail\Queue;
use EBCR\Roles\Capabilities;
use EBCR\Security\AuditLog;
use EBCR\Security\Authorization;
use EBCR\Security\Crypto;
use EBCR\Security\Retention;
use EBCR\Support\Helpers;
use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Cada ability tem permission_callback por capacidade e passa pelos mesmos serviços/regras da interface.
 */
final class Abilities {

	const NS       = 'ebcr';
	const CATEGORY = 'ebcr';
	const EMCP_OPT = 'easy_mcp_ai_enabled_abilities';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * API de Abilities disponível?
	 *
	 * @return bool
	 */
	public static function is_available() {
		return function_exists( 'wp_register_ability' );
	}

	/**
	 * Categoria.
	 *
	 * @return void
	 */
	public function register_category() {
		if ( function_exists( 'wp_register_ability_category' ) ) {
			wp_register_ability_category(
				self::CATEGORY,
				array(
					'label'       => __( 'EB Crédito Rural', 'eb-credito-rural' ),
					'description' => __( 'Configuração e operação do plugin de solicitações de crédito rural.', 'eb-credito-rural' ),
				)
			);
		}
	}

	/**
	 * Definições: slug => [label, description, input_schema, execute, cap, annotations].
	 *
	 * @return array
	 */
	public static function definitions() {
		$id_prop = array(
			'type'        => 'string',
			'description' => 'UUID público da solicitação ou protocolo (ex.: EB-2026-000012).',
		);
		return array(
			'get-settings'      => array(
				'label'       => __( 'Ler configurações do EB Crédito Rural', 'eb-credito-rural' ),
				'description' => 'Retorna as configurações do plugin EB Crédito Rural (todas ou de uma aba) com rótulo, ajuda e tipo de cada campo. Abas: geral, formulario, documentos, regras, seguranca, emails, privacidade, status, desinstalar.',
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'tab'  => array(
							'type'        => 'string',
							'description' => 'Aba (opcional).',
						),
						'keys' => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => 'Chaves específicas (opcional).',
						),
					),
				),
				'execute'     => array( __CLASS__, 'get_settings' ),
				'cap'         => Capabilities::CAP_SETTINGS,
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
			'update-settings'   => array(
				'label'       => __( 'Atualizar configurações do EB Crédito Rural', 'eb-credito-rural' ),
				'description' => 'Atualiza uma ou mais configurações do plugin EB Crédito Rural (mesma validação da tela de configurações). Envie um objeto "settings" com chave => valor; use get-settings para conhecer chaves e tipos. Campos compostos: document_matrix (lista de {key,label,condition,required,validity_days,help}), email_templates ({evento:{subject,body}}), policies ({chave:{page_id,version,text}}), statuses ({chave:{label,color,client_visible,client_text}}), step_help ({1..7: texto}).',
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'settings' => array(
							'type'                 => 'object',
							'description'          => 'Pares chave => valor.',
							'additionalProperties' => true,
						),
					),
					'required'   => array( 'settings' ),
				),
				'execute'     => array( __CLASS__, 'update_settings' ),
				'cap'         => Capabilities::CAP_SETTINGS,
				'annotations' => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
			'reset-settings'    => array(
				'label'       => __( 'Restaurar padrões de uma aba', 'eb-credito-rural' ),
				'description' => 'Restaura os valores padrão de uma aba de configurações do EB Crédito Rural (a criptografia nunca é desligada por reset).',
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'tab' => array(
							'type'        => 'string',
							'description' => 'Aba.',
						),
					),
					'required'   => array( 'tab' ),
				),
				'execute'     => array( __CLASS__, 'reset_settings' ),
				'cap'         => Capabilities::CAP_SETTINGS,
				'annotations' => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
				),
			),
			'get-status'        => array(
				'label'       => __( 'Diagnóstico do EB Crédito Rural', 'eb-credito-rural' ),
				'description' => 'Diagnóstico completo do plugin: versão, verificação do ambiente (PHP, HTTPS, sodium, limites de upload, cron, SMTP), pasta privada e último teste de proteção, chave de criptografia, página do portal, fila de e-mails e falhas, totais por status, equipe e pendências de configuração.',
				'input'       => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
				'execute'     => array( __CLASS__, 'get_status' ),
				'cap'         => Capabilities::CAP_SETTINGS,
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
			'run-tool'          => array(
				'label'       => __( 'Executar ferramenta do EB Crédito Rural', 'eb-credito-rural' ),
				'description' => 'Executa uma ferramenta: test_protection (testa se a pasta de documentos está exposta por URL), test_email (envia e-mail de teste para "to"), process_mail (processa a fila de e-mails), retry_mail (reenfileira falhas), run_daily (rotina diária: lembretes, certidões, retenção, limpezas), run_retention (anonimiza solicitações finalizadas fora do prazo de retenção) ou enable_mcp (habilita estas abilities no Easy MCP AI), send_crm_reminders (envia os lembretes de tarefas do CRM agora), apply_site_icon (define o ícone do site do WordPress a partir do ícone da marca configurado em Geral).',
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'tool' => array(
							'type' => 'string',
							'enum' => array( 'test_protection', 'test_email', 'process_mail', 'retry_mail', 'run_daily', 'run_retention', 'enable_mcp', 'send_crm_reminders', 'apply_site_icon' ),
						),
						'to'   => array(
							'type'        => 'string',
							'description' => 'Destinatário do e-mail de teste.',
						),
					),
					'required'   => array( 'tool' ),
				),
				'execute'     => array( __CLASS__, 'run_tool' ),
				'cap'         => Capabilities::CAP_SETTINGS,
				'annotations' => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => false,
				),
			),
			'list-team'         => array(
				'label'       => __( 'Listar equipe do EB Crédito Rural', 'eb-credito-rural' ),
				'description' => 'Lista a equipe (analistas, gestores e administradores) com ID, nome, e-mail e papéis. Clientes só aparecem se role=ebcr_cliente for informado.',
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'role' => array(
							'type'        => 'string',
							'description' => 'ebcr_cliente | ebcr_analista | ebcr_gestor | administrator (opcional).',
						),
					),
				),
				'execute'     => array( __CLASS__, 'list_team' ),
				'cap'         => Capabilities::CAP_FINAL_STATUS,
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
			'set-team-member'   => array(
				'label'       => __( 'Criar ou promover membro da equipe', 'eb-credito-rural' ),
				'description' => 'Cria um usuário (se o e-mail não existir) ou atribui a um usuário existente o papel ebcr_analista ou ebcr_gestor; "remover" tira o usuário da equipe (mantém a conta como assinante). Novos usuários recebem o e-mail padrão do WordPress para definir a senha.',
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'email' => array( 'type' => 'string' ),
						'name'  => array(
							'type'        => 'string',
							'description' => 'Nome de exibição (para novos usuários).',
						),
						'role'  => array(
							'type' => 'string',
							'enum' => array( 'ebcr_analista', 'ebcr_gestor', 'remover' ),
						),
					),
					'required'   => array( 'email', 'role' ),
				),
				'execute'     => array( __CLASS__, 'set_team_member' ),
				'cap'         => 'promote_users',
				'annotations' => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
			'list-submissions'  => array(
				'label'       => __( 'Listar solicitações de crédito', 'eb-credito-rural' ),
				'description' => 'Lista solicitações com filtros (status, busca por nome/e-mail/protocolo, analista, período, paginação). Retorna protocolo, UUID, status, cliente, valor, analista e datas.',
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'status'         => array( 'type' => 'string' ),
						'search'         => array( 'type' => 'string' ),
						'assigned_to'    => array( 'type' => 'integer' ),
						'date_from'      => array(
							'type'        => 'string',
							'description' => 'AAAA-MM-DD',
						),
						'date_to'        => array(
							'type'        => 'string',
							'description' => 'AAAA-MM-DD',
						),
						'include_drafts' => array( 'type' => 'boolean' ),
						'per_page'       => array( 'type' => 'integer' ),
						'page'           => array( 'type' => 'integer' ),
					),
				),
				'execute'     => array( __CLASS__, 'list_submissions' ),
				'cap'         => Capabilities::CAP_VIEW,
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
			'get-submission'    => array(
				'label'       => __( 'Detalhar solicitação de crédito', 'eb-credito-rural' ),
				'description' => 'Detalhe de uma solicitação: dados por etapa (CPF/CNPJ mascarados, salvo include_sensitive=true), imóveis, garantias, indicadores, documentos (sem caminhos), pedidos de documento, histórico e mensagens.',
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'                => $id_prop,
						'include_sensitive' => array( 'type' => 'boolean' ),
					),
					'required'   => array( 'id' ),
				),
				'execute'     => array( __CLASS__, 'get_submission' ),
				'cap'         => Capabilities::CAP_VIEW,
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
			'change-status'     => array(
				'label'       => __( 'Alterar status de solicitação', 'eb-credito-rural' ),
				'description' => 'Muda o status de uma solicitação seguindo o fluxo (aprovada/reprovada exigem a capacidade de status final e comentário interno). Notifica o cliente quando o status é visível.',
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'               => $id_prop,
						'status'           => array(
							'type'        => 'string',
							'description' => 'Chave do status (ex.: pre_analise, pendencia_documental, analise_credito, comite, aprovada, reprovada, formalizacao, concluida, cancelada).',
						),
						'comment_internal' => array( 'type' => 'string' ),
						'comment_client'   => array( 'type' => 'string' ),
					),
					'required'   => array( 'id', 'status' ),
				),
				'execute'     => array( __CLASS__, 'change_status' ),
				'cap'         => Capabilities::CAP_EDIT,
				'annotations' => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
				),
			),
			'assign-submission' => array(
				'label'       => __( 'Atribuir analista', 'eb-credito-rural' ),
				'description' => 'Atribui (ou remove, com analyst=0) o analista responsável por uma solicitação; aceita ID ou e-mail do usuário.',
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'      => $id_prop,
						'analyst' => array(
							'type'        => 'string',
							'description' => 'ID numérico, e-mail ou 0.',
						),
					),
					'required'   => array( 'id', 'analyst' ),
				),
				'execute'     => array( __CLASS__, 'assign' ),
				'cap'         => Capabilities::CAP_EDIT,
				'annotations' => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
			'request-document'  => array(
				'label'       => __( 'Solicitar documento ao cliente', 'eb-credito-rural' ),
				'description' => 'Abre uma pendência de documento para o cliente (tipo da matriz ou "outro" com rótulo), com instruções; opcionalmente muda o status para pendência documental. O cliente é notificado.',
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'         => $id_prop,
						'doc_type'   => array( 'type' => 'string' ),
						'label'      => array( 'type' => 'string' ),
						'note'       => array( 'type' => 'string' ),
						'set_status' => array( 'type' => 'boolean' ),
					),
					'required'   => array( 'id', 'doc_type' ),
				),
				'execute'     => array( __CLASS__, 'request_document' ),
				'cap'         => Capabilities::CAP_EDIT,
				'annotations' => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
				),
			),
			'send-message'      => array(
				'label'       => __( 'Enviar mensagem na solicitação', 'eb-credito-rural' ),
				'description' => 'Registra uma nota interna (visibility=interno) ou uma mensagem ao cliente (visibility=cliente, notificada por e-mail) em uma solicitação.',
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'         => $id_prop,
						'body'       => array( 'type' => 'string' ),
						'visibility' => array(
							'type' => 'string',
							'enum' => array( 'interno', 'cliente' ),
						),
					),
					'required'   => array( 'id', 'body' ),
				),
				'execute'     => array( __CLASS__, 'send_message' ),
				'cap'         => Capabilities::CAP_EDIT,
				'annotations' => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
				),
			),
			'review-document'   => array(
				'label'       => __( 'Revisar documento enviado', 'eb-credito-rural' ),
				'description' => 'Aceita, recusa (motivo obrigatório; cliente notificado) ou devolve a pendente um documento, pelo UUID do documento (veja get-submission).',
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'document_id' => array(
							'type'        => 'string',
							'description' => 'UUID do documento.',
						),
						'result'      => array(
							'type' => 'string',
							'enum' => array( 'aceito', 'recusado', 'pendente' ),
						),
						'note'        => array( 'type' => 'string' ),
					),
					'required'   => array( 'document_id', 'result' ),
				),
				'execute'     => array( __CLASS__, 'review_document' ),
				'cap'         => Capabilities::CAP_EDIT,
				'annotations' => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
			'get-report'        => array(
				'label'       => __( 'Relatório de solicitações', 'eb-credito-rural' ),
				'description' => 'Totais e quebras (por carteira/fundo, status, mês, analista e tempo médio por etapa) com filtros de período (date_from/date_to AAAA-MM-DD), fund (chave da carteira), status e assigned_to (ID do analista). Respeita "analista vê só as atribuídas".',
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'date_from'   => array( 'type' => 'string' ),
						'date_to'     => array( 'type' => 'string' ),
						'fund'        => array( 'type' => 'string' ),
						'status'      => array( 'type' => 'string' ),
						'assigned_to' => array( 'type' => 'integer' ),
					),
				),
				'execute'     => array( __CLASS__, 'get_report' ),
				'cap'         => Capabilities::CAP_DASHBOARD,
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
			'list-contacts'     => array(
				'label'       => __( 'Listar contatos do CRM', 'eb-credito-rural' ),
				'description' => 'Lista fichas do CRM (clientes) com estágio, responsável, próxima ação, tags, nº de solicitações abertas e última atividade. Filtros: search (nome/e-mail/telefone), stage, owner_id, lead_source, tag, page, per_page.',
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'search'      => array( 'type' => 'string' ),
						'stage'       => array( 'type' => 'string' ),
						'owner_id'    => array( 'type' => 'integer' ),
						'lead_source' => array( 'type' => 'string' ),
						'tag'         => array( 'type' => 'string' ),
						'page'        => array( 'type' => 'integer' ),
						'per_page'    => array( 'type' => 'integer' ),
					),
				),
				'execute'     => array( __CLASS__, 'list_contacts' ),
				'cap'         => Capabilities::CAP_CRM,
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
			'update-contact'    => array(
				'label'       => __( 'Atualizar ficha do CRM', 'eb-credito-rural' ),
				'description' => 'Atualiza a ficha de um cliente no CRM (por contact_id ou user_id): stage (chave do estágio), owner_id (0 = ninguém), lead_source, tags (separadas por vírgula), next_action, next_action_at (AAAA-MM-DD HH:MM, hora do site), notes, phone, whatsapp. Só os campos informados mudam.',
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'contact_id'     => array( 'type' => 'integer' ),
						'user_id'        => array( 'type' => 'integer' ),
						'stage'          => array( 'type' => 'string' ),
						'owner_id'       => array( 'type' => 'integer' ),
						'lead_source'    => array( 'type' => 'string' ),
						'tags'           => array( 'type' => 'string' ),
						'next_action'    => array( 'type' => 'string' ),
						'next_action_at' => array( 'type' => 'string' ),
						'notes'          => array( 'type' => 'string' ),
						'phone'          => array( 'type' => 'string' ),
						'whatsapp'       => array( 'type' => 'string' ),
					),
				),
				'execute'     => array( __CLASS__, 'update_contact' ),
				'cap'         => Capabilities::CAP_CRM,
				'annotations' => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
			'add-activity'      => array(
				'label'       => __( 'Registrar atividade ou tarefa no CRM', 'eb-credito-rural' ),
				'description' => 'Registra na ficha do cliente (contact_id ou user_id) uma atividade: type = ligacao | reuniao | visita | email | nota | tarefa; description; para tarefas, due_at (AAAA-MM-DD HH:MM) e assignee_id (responsável; padrão: quem registra); opcionalmente id da solicitação (UUID ou protocolo).',
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'contact_id'  => array( 'type' => 'integer' ),
						'user_id'     => array( 'type' => 'integer' ),
						'type'        => array( 'type' => 'string' ),
						'description' => array( 'type' => 'string' ),
						'due_at'      => array( 'type' => 'string' ),
						'assignee_id' => array( 'type' => 'integer' ),
						'id'          => $id_prop,
					),
					'required'   => array( 'type', 'description' ),
				),
				'execute'     => array( __CLASS__, 'add_activity' ),
				'cap'         => Capabilities::CAP_CRM,
				'annotations' => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
				),
			),
		);
	}

	/**
	 * Slugs completos (ns/nome).
	 *
	 * @return string[]
	 */
	public static function slugs() {
		return array_map(
			static function ( $k ) {
				return self::NS . '/' . $k;
			},
			array_keys( self::definitions() )
		);
	}

	/**
	 * Registra as abilities.
	 *
	 * @return void
	 */
	public function register_abilities() {
		foreach ( self::definitions() as $key => $def ) {
			$cap = $def['cap'];
			wp_register_ability(
				self::NS . '/' . $key,
				array(
					'label'               => $def['label'],
					'description'         => $def['description'],
					'category'            => self::CATEGORY,
					'input_schema'        => $def['input'],
					'execute_callback'    => static function ( $input = null ) use ( $def ) {
						try {
							return call_user_func( $def['execute'], is_array( $input ) ? $input : array() );
						} catch ( \Throwable $e ) {
							return new \WP_Error( 'ebcr_ability_error', $e->getMessage() );
						}
					},
					'permission_callback' => static function () use ( $cap ) {
						return current_user_can( $cap ) ? true : new \WP_Error( 'ebcr_forbidden', __( 'Sem permissão para esta operação.', 'eb-credito-rural' ) );
					},
					'meta'                => array(
						'public'       => true,
						'show_in_rest' => true,
						'annotations'  => $def['annotations'],
					),
				)
			);
		}
	}

	/**
	 * Habilita as abilities do plugin no Easy MCP AI (opção do conector), sem remover outras.
	 *
	 * @return array{enabled:bool,added:int,reason:string}
	 */
	public static function enable_in_easy_mcp() {
		if ( ! self::is_available() ) {
			return array(
				'enabled' => false,
				'added'   => 0,
				'reason'  => 'abilities_api_missing',
			);
		}
		if ( ! defined( 'EASY_MCP_AI_VERSION' ) && ! class_exists( '\Easy_MCP_AI\Tools\Dynamic_Tool_Registrar' ) && false === get_option( self::EMCP_OPT, false ) ) {
			return array(
				'enabled' => false,
				'added'   => 0,
				'reason'  => 'easy_mcp_ai_missing',
			);
		}
		$current = (array) get_option( self::EMCP_OPT, array() );
		$merged  = array_values( array_unique( array_merge( array_map( 'strval', $current ), self::slugs() ) ) );
		$added   = count( $merged ) - count( array_unique( array_map( 'strval', $current ) ) );
		if ( $added > 0 ) {
			update_option( self::EMCP_OPT, $merged );
		}
		return array(
			'enabled' => true,
			'added'   => $added,
			'reason'  => $added ? 'added' : 'already',
		);
	}

	/**
	 * Estado da exposição no Easy MCP AI.
	 *
	 * @return array
	 */
	public static function mcp_status() {
		$enabled = array_map( 'strval', (array) get_option( self::EMCP_OPT, array() ) );
		$ours    = self::slugs();
		return array(
			'abilities_api'      => self::is_available(),
			'easy_mcp_ai_active' => false !== get_option( self::EMCP_OPT, false ) || class_exists( '\Easy_MCP_AI\Tools\Dynamic_Tool_Registrar' ),
			'enabled'            => array_values( array_intersect( $ours, $enabled ) ),
			'missing'            => array_values( array_diff( $ours, $enabled ) ),
			'tool_names'         => array_map(
				static function ( $s ) {
					return 'wp_ability_' . preg_replace( '/[^a-z0-9]+/', '_', strtolower( $s ) ); },
				$ours
			),
		);
	}

	// ------------------------------------------------------------------ execuções

	/**
	 * Localiza solicitação por UUID ou protocolo.
	 *
	 * @param string $id Identificador.
	 * @return array|\WP_Error
	 */
	private static function find_submission( $id ) {
		$repo = new SubmissionRepository();
		$id   = trim( (string) $id );
		$s    = $repo->find_by_public_id( $id );
		if ( ! $s ) {
			$s = $repo->find_by_protocol( $id );
		}
		if ( ! $s ) {
			return new \WP_Error( 'not_found', __( 'Solicitação não encontrada.', 'eb-credito-rural' ) );
		}
		if ( ! Authorization::can_view_submission( get_current_user_id(), $s ) ) {
			return new \WP_Error( 'forbidden', __( 'Sem acesso a esta solicitação.', 'eb-credito-rural' ) );
		}
		return $s;
	}

	/**
	 * get-settings.
	 *
	 * @param array $in Entrada.
	 * @return array
	 */
	public static function get_settings( array $in ) {
		$tab    = isset( $in['tab'] ) ? sanitize_key( $in['tab'] ) : '';
		$keys   = isset( $in['keys'] ) && is_array( $in['keys'] ) ? array_map( 'sanitize_key', $in['keys'] ) : array();
		$all    = Options::all();
		$fields = Settings::all_fields();
		$out    = array(
			'tabs'     => array(),
			'fields'   => array(),
			'settings' => array(),
		);
		foreach ( Settings::tabs() as $k => $t ) {
			$out['tabs'][ $k ] = $t['label'];
		}
		foreach ( $fields as $key => $def ) {
			if ( ( $tab && $def['tab'] !== $tab ) || ( $keys && ! in_array( $key, $keys, true ) ) ) {
				continue;
			}
			$out['fields'][ $key ] = array(
				'tab'   => $def['tab'],
				'type'  => $def[0],
				'label' => $def[1],
				'help'  => $def[2],
			);
			if ( 'select' === $def[0] ) {
				$out['fields'][ $key ]['options'] = $def[3];
			}
			$out['settings'][ $key ] = isset( $all[ $key ] ) ? $all[ $key ] : null;
		}
		if ( ! $tab && ! $keys ) {
			$out['settings']['document_matrix'] = DocumentMatrix::all();
			$out['settings']['statuses']        = Status::all();
			$out['settings']['policies']        = \EBCR\Domain\Consent::policies();
			$out['portal_page_id_effective']    = Helpers::portal_page_id();
			$out['portal_url']                  = Helpers::portal_url();
		}
		return $out;
	}

	/**
	 * update-settings.
	 *
	 * @param array $in Entrada.
	 * @return array|\WP_Error
	 */
	public static function update_settings( array $in ) {
		$settings = isset( $in['settings'] ) && is_array( $in['settings'] ) ? $in['settings'] : array();
		if ( ! $settings ) {
			return new \WP_Error( 'empty', __( 'Informe "settings" com pelo menos uma chave.', 'eb-credito-rural' ) );
		}
		list( $values, $errors ) = Settings::sanitize( $settings );
		$warnings                = Settings::guard( $values );
		if ( $values ) {
			Settings::persist( $values, 'mcp' );
		}
		return array(
			'saved'    => array_keys( $values ),
			'values'   => $values,
			'errors'   => $errors,
			'warnings' => $warnings,
		);
	}

	/**
	 * reset-settings.
	 *
	 * @param array $in Entrada.
	 * @return array|\WP_Error
	 */
	public static function reset_settings( array $in ) {
		$tab = isset( $in['tab'] ) ? sanitize_key( $in['tab'] ) : '';
		if ( ! isset( Settings::tabs()[ $tab ] ) ) {
			return new \WP_Error( 'bad_tab', __( 'Aba inválida.', 'eb-credito-rural' ) );
		}
		$keys = array_keys( Settings::fields( $tab ) );
		if ( 'seguranca' === $tab ) {
			$keys = array_diff( $keys, array( 'encrypt_files', 'encrypt_fields' ) );
		}
		Options::reset( $keys );
		AuditLog::log(
			'settings_updated',
			'settings',
			$tab,
			array(
				'reset' => true,
				'via'   => 'mcp',
			)
		);
		return array( 'reset' => array_values( $keys ) );
	}

	/**
	 * get-status.
	 *
	 * @return array
	 */
	public static function get_status() {
		$guard = new FileGuard();
		$queue = new MailQueueRepository();
		$subs  = new SubmissionRepository();
		$env   = array();
		foreach ( Settings::environment() as $row ) {
			$env[] = array(
				'item'  => $row[0],
				'value' => (string) $row[1],
				'ok'    => (bool) $row[2],
			);
		}
		$todo   = array();
		$portal = Helpers::portal_page_id();
		if ( ! $portal ) {
			$todo[] = 'Criar uma página com [ebcr_portal] (ou definir portal_page_id).';
		}
		$last = Options::get( 'last_protection_test', array() );
		if ( ! is_array( $last ) || empty( $last['result'] ) ) {
			$todo[] = 'Executar run-tool test_protection para confirmar que a pasta de documentos não é acessível por URL.';
		} elseif ( 'exposed' === $last['result'] ) {
			$todo[] = 'ALERTA: pasta de documentos exposta. Configurar storage_path fora da raiz pública ou bloquear no servidor (ver Ajuda).';
		}
		if ( 'ok' !== Crypto::status() ) {
			$todo[] = 'Definir EBCR_ENCRYPTION_KEY no wp-config.php se desejar criptografia em repouso (opcional, recomendada).';
		}
		if ( ! ( class_exists( 'WPMailSMTP\Core' ) || class_exists( 'FluentMail\App\Application' ) || defined( 'POST_SMTP_VERSION' ) ) ) {
			$todo[] = 'Instalar e configurar um plugin SMTP e testar com run-tool test_email.';
		}
		if ( ! Options::admin_emails() ) {
			$todo[] = 'Definir admin_emails.';
		}
		if ( ! get_users(
			array(
				'role__in' => array( Capabilities::ROLE_ANALYST, Capabilities::ROLE_MANAGER ),
				'number'   => 1,
				'fields'   => 'ID',
			)
		) ) {
			$todo[] = 'Criar ao menos um analista/gestor (set-team-member).';
		}
		$limits = UploadHandler::php_limits();
		if ( ! $limits['ok'] ) {
			$todo[] = sprintf( 'Limites do PHP (%s/%s) menores que max_file_size_mb (%s).', size_format( $limits['php_upload'] ), size_format( $limits['php_post'] ), size_format( $limits['configured'] ) );
		}
		return array(
			'version'         => EBCR_VERSION,
			'environment'     => $env,
			'storage'         => $guard->status(),
			'protection_test' => $last,
			'crypto'          => Crypto::status(),
			'portal_page_id'  => $portal,
			'portal_url'      => $portal ? get_permalink( $portal ) : '',
			'mail_queue'      => $queue->stats(),
			'mail_failures'   => $queue->failures( 5 ),
			'submissions'     => $subs->count_by_status(),
			'team'            => count(
				get_users(
					array(
						'role__in' => array( Capabilities::ROLE_ANALYST, Capabilities::ROLE_MANAGER ),
						'fields'   => 'ID',
					)
				)
			),
			'clients'         => count(
				get_users(
					array(
						'role'   => Capabilities::ROLE_CLIENT,
						'fields' => 'ID',
					)
				)
			),
			'cron_daily_next' => wp_next_scheduled( 'ebcr_daily' ) ? gmdate( 'c', wp_next_scheduled( 'ebcr_daily' ) ) : null,
			'modules'         => array(
				'team_portal_mode'   => Options::get( 'team_portal_mode', 'both' ),
				'admin_branding'     => Options::bool( 'admin_branding' ),
				'site_icon_id'       => (int) get_option( 'site_icon', 0 ),
				'team_2fa_mode'      => Options::get( 'team_2fa_mode', 'optional' ),
				'cep_lookup'         => Options::bool( 'cep_lookup' ),
				'cnpj_lookup'        => Options::bool( 'cnpj_lookup' ),
				'captcha_provider'   => Options::get( 'captcha_provider', 'math' ),
				'turnstile_ready'    => '' !== (string) Options::get( 'turnstile_site_key' ) && '' !== (string) Options::get( 'turnstile_secret_key' ),
				'whatsapp_enabled'   => Options::bool( 'whatsapp_enabled' ),
				'whatsapp_ready'     => '' !== (string) Options::get( 'whatsapp_token' ) && '' !== (string) Options::get( 'whatsapp_phone_id' ),
				'esign_enabled'      => Options::bool( 'esign_enabled' ),
				'simulator_enabled'  => Options::bool( 'simulator_enabled' ),
				'crm_task_reminders' => Options::bool( 'crm_task_reminders' ),
				'funds'              => Options::pairs( 'funds' ),
			),
			'mcp'             => self::mcp_status(),
			'todo'            => $todo,
		);
	}

	/**
	 * run-tool.
	 *
	 * @param array $in Entrada.
	 * @return array|\WP_Error
	 */
	public static function run_tool( array $in ) {
		$tool = isset( $in['tool'] ) ? sanitize_key( $in['tool'] ) : '';
		switch ( $tool ) {
			case 'test_protection':
				return ( new FileGuard() )->test_protection();
			case 'test_email':
				$to = isset( $in['to'] ) ? sanitize_email( $in['to'] ) : '';
				if ( ! $to ) {
					return new \WP_Error( 'bad_to', __( 'Informe "to".', 'eb-credito-rural' ) );
				}
				return array(
					'sent' => Mailer::send_test( $to ),
					'to'   => $to,
				);
			case 'process_mail':
				return array(
					'sent'  => Queue::process( 50 ),
					'stats' => ( new MailQueueRepository() )->stats(),
				);
			case 'retry_mail':
				$n = ( new MailQueueRepository() )->retry_failed();
				Queue::kick();
				return array( 'requeued' => $n );
			case 'run_daily':
				return Scheduler::daily();
			case 'run_retention':
				return array( 'anonymized' => Retention::run() );
			case 'apply_site_icon':
				return \EBCR\Admin\Branding::apply_site_icon();
			case 'send_crm_reminders':
				return \EBCR\Crm\Service::send_reminders();
			case 'enable_mcp':
				return self::enable_in_easy_mcp();
			default:
				return new \WP_Error( 'bad_tool', __( 'Ferramenta desconhecida.', 'eb-credito-rural' ) );
		}
	}

	/**
	 * list-team.
	 *
	 * @param array $in Entrada.
	 * @return array
	 */
	public static function list_team( array $in ) {
		$role  = isset( $in['role'] ) ? sanitize_key( $in['role'] ) : '';
		$roles = $role ? array( $role ) : array( Capabilities::ROLE_ANALYST, Capabilities::ROLE_MANAGER, 'administrator' );
		$users = get_users(
			array(
				'role__in' => $roles,
				'number'   => 500,
				'orderby'  => 'display_name',
			)
		);
		return array(
			'users' => array_map(
				static function ( $u ) {
					return array(
						'id'             => $u->ID,
						'name'           => $u->display_name,
						'email'          => $u->user_email,
						'roles'          => $u->roles,
						'email_verified' => (bool) get_user_meta( $u->ID, 'ebcr_email_verified', true ),
					);
				},
				$users
			),
		);
	}

	/**
	 * set-team-member.
	 *
	 * @param array $in Entrada.
	 * @return array|\WP_Error
	 */
	public static function set_team_member( array $in ) {
		$email = sanitize_email( isset( $in['email'] ) ? $in['email'] : '' );
		$role  = isset( $in['role'] ) ? sanitize_key( $in['role'] ) : '';
		if ( ! is_email( $email ) || ! in_array( $role, array( Capabilities::ROLE_ANALYST, Capabilities::ROLE_MANAGER, 'remover' ), true ) ) {
			return new \WP_Error( 'bad_input', __( 'E-mail ou papel inválido.', 'eb-credito-rural' ) );
		}
		$user    = get_user_by( 'email', $email );
		$created = false;
		if ( 'remover' === $role ) {
			if ( ! $user ) {
				return new \WP_Error( 'not_found', __( 'Usuário não encontrado.', 'eb-credito-rural' ) );
			}
			if ( in_array( 'administrator', (array) $user->roles, true ) ) {
				return new \WP_Error( 'is_admin', __( 'Administradores não são removidos por aqui.', 'eb-credito-rural' ) );
			}
			$user->remove_role( Capabilities::ROLE_ANALYST );
			$user->remove_role( Capabilities::ROLE_MANAGER );
			if ( ! $user->roles ) {
				$user->add_role( 'subscriber' );
			}
			AuditLog::log(
				'team_member_set',
				'user',
				$user->ID,
				array(
					'role' => 'remover',
					'via'  => 'mcp',
				)
			);
			return array(
				'id'      => $user->ID,
				'email'   => $user->user_email,
				'role'    => 'remover',
				'created' => false,
			);
		}
		if ( ! $user ) {
			if ( ! current_user_can( 'create_users' ) ) {
				return new \WP_Error( 'forbidden', __( 'Sem permissão para criar usuários.', 'eb-credito-rural' ) );
			}
			$name  = sanitize_text_field( isset( $in['name'] ) ? $in['name'] : '' );
			$login = sanitize_user( strstr( $email, '@', true ), true );
			if ( username_exists( $login ) ) {
				$login .= '_' . wp_generate_password( 4, false, false );
			}
			$uid = wp_insert_user(
				array(
					'user_login'   => $login,
					'user_email'   => $email,
					'user_pass'    => wp_generate_password( 24, true ),
					'display_name' => $name ? $name : $login,
					'role'         => $role,
				)
			);
			if ( is_wp_error( $uid ) ) {
				return $uid;
			}
			wp_new_user_notification( $uid, null, 'user' );
			$user    = get_userdata( $uid );
			$created = true;
		} else {
			if ( in_array( 'administrator', (array) $user->roles, true ) ) {
				return new \WP_Error( 'is_admin', __( 'Administradores já têm todas as capacidades; não é preciso atribuir papel.', 'eb-credito-rural' ) );
			}
			$user->remove_role( Capabilities::ROLE_ANALYST );
			$user->remove_role( Capabilities::ROLE_MANAGER );
			$user->add_role( $role );
		}
		AuditLog::log(
			'team_member_set',
			'user',
			$user->ID,
			array(
				'role'    => $role,
				'created' => $created,
				'via'     => 'mcp',
			)
		);
		return array(
			'id'      => $user->ID,
			'email'   => $user->user_email,
			'role'    => $role,
			'created' => $created,
		);
	}

	/**
	 * Linha resumida de solicitação.
	 *
	 * @param array $s Linha.
	 * @return array
	 */
	private static function summary( array $s ) {
		$u = get_userdata( (int) $s['user_id'] );
		return array(
			'id'            => $s['public_id'],
			'protocol'      => $s['protocol'],
			'status'        => $s['status'],
			'status_label'  => Status::label( $s['status'] ),
			'client'        => $u ? $u->display_name : '',
			'client_email'  => $u ? $u->user_email : '',
			'person_type'   => $s['person_type'],
			'amount'        => $s['requested_amount'],
			'purpose'       => $s['purpose'],
			'term_months'   => $s['term_months'],
			'assigned_to'   => $s['assigned_to'] ? (int) $s['assigned_to'] : null,
			'assigned_name' => $s['assigned_to'] ? Helpers::user_name( (int) $s['assigned_to'] ) : null,
			'submitted_at'  => $s['submitted_at'],
			'updated_at'    => $s['updated_at'],
			'admin_url'     => admin_url( 'admin.php?page=ebcr-submissions&view=' . rawurlencode( $s['public_id'] ) ),
		);
	}

	/**
	 * list-submissions.
	 *
	 * @param array $in Entrada.
	 * @return array
	 */
	public static function list_submissions( array $in ) {
		$args = array(
			'status'         => isset( $in['status'] ) ? sanitize_key( $in['status'] ) : '',
			'search'         => isset( $in['search'] ) ? sanitize_text_field( $in['search'] ) : '',
			'assigned_to'    => isset( $in['assigned_to'] ) ? (int) $in['assigned_to'] : 0,
			'date_from'      => isset( $in['date_from'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $in['date_from'] ) ? $in['date_from'] : '',
			'date_to'        => isset( $in['date_to'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $in['date_to'] ) ? $in['date_to'] : '',
			'include_drafts' => ! empty( $in['include_drafts'] ),
			'per_page'       => isset( $in['per_page'] ) ? (int) $in['per_page'] : 20,
			'page'           => isset( $in['page'] ) ? (int) $in['page'] : 1,
		);
		if ( Options::bool( 'analyst_only_assigned' ) && ! current_user_can( Capabilities::CAP_FINAL_STATUS ) && ! current_user_can( Capabilities::CAP_SETTINGS ) ) {
			$args['only_assigned_to'] = get_current_user_id();
		}
		list( $items, $total ) = ( new SubmissionRepository() )->query( $args );
		return array(
			'total' => $total,
			'page'  => $args['page'],
			'items' => array_map( array( __CLASS__, 'summary' ), $items ),
		);
	}

	/**
	 * Mascara documentos em uma seção.
	 *
	 * @param array $data Dados.
	 * @return array
	 */
	private static function mask( array $data ) {
		foreach ( array( 'cpf', 'cnpj', 'conjuge_cpf' ) as $k ) {
			if ( ! empty( $data[ $k ] ) ) {
				$data[ $k ] = Helpers::mask_document( $data[ $k ] );
			}
		}
		if ( ! empty( $data['representantes'] ) && is_array( $data['representantes'] ) ) {
			foreach ( $data['representantes'] as &$r ) {
				if ( ! empty( $r['cpf'] ) ) {
					$r['cpf'] = Helpers::mask_document( $r['cpf'] );
				}
			}
		}
		return $data;
	}

	/**
	 * get-submission.
	 *
	 * @param array $in Entrada.
	 * @return array|\WP_Error
	 */
	public static function get_submission( array $in ) {
		$s = self::find_submission( isset( $in['id'] ) ? $in['id'] : '' );
		if ( is_wp_error( $s ) ) {
			return $s;
		}
		$wizard = new Wizard();
		$saved  = $wizard->saved( $s );
		if ( empty( $in['include_sensitive'] ) && isset( $saved['identificacao'] ) ) {
			$saved['identificacao'] = self::mask( $saved['identificacao'] );
		}
		$slots = $wizard->document_slots( $s );
		AuditLog::log(
			'submission_viewed',
			'submission',
			$s['public_id'],
			array(
				'via'       => 'mcp',
				'sensitive' => ! empty( $in['include_sensitive'] ),
			)
		);
		return array(
			'submission'  => self::summary( $s ),
			'data'        => $saved,
			'documents'   => array_map(
				static function ( $d ) {
					return array(
						'id'            => $d['public_id'],
						'type'          => $d['doc_type'],
						'type_label'    => DocumentMatrix::label( $d['doc_type'] ),
						'ref_key'       => $d['ref_key'],
						'name'          => $d['original_name'],
						'size'          => (int) $d['size'],
						'mime'          => $d['mime'],
						'review_status' => $d['review_status'],
						'review_note'   => $d['review_note'],
						'expires_at'    => $d['expires_at'],
						'uploaded_at'   => $d['uploaded_at'],
					);
				},
				( new DocumentRepository() )->for_submission( (int) $s['id'] )
			),
			'checklist'   => array(
				'required_total' => $slots['required_total'],
				'required_done'  => $slots['required_done'],
			),
			'requests'    => ( new DocumentRequestRepository() )->for_submission( (int) $s['id'] ),
			'history'     => ( new StatusHistoryRepository() )->for_submission( (int) $s['id'] ),
			'messages'    => ( new MessageRepository() )->for_submission( (int) $s['id'] ),
			'transitions' => array_values(
				array_filter(
					array_keys( Status::all() ),
					static function ( $k ) use ( $s ) {
						return Authorization::can_change_status( get_current_user_id(), $s, $k ); }
				)
			),
		);
	}

	/**
	 * change-status.
	 *
	 * @param array $in Entrada.
	 * @return array|\WP_Error
	 */
	public static function change_status( array $in ) {
		$s = self::find_submission( isset( $in['id'] ) ? $in['id'] : '' );
		if ( is_wp_error( $s ) ) {
			return $s;
		}
		$r = SubmissionService::change_status( get_current_user_id(), $s, isset( $in['status'] ) ? $in['status'] : '', isset( $in['comment_internal'] ) ? sanitize_textarea_field( $in['comment_internal'] ) : '', isset( $in['comment_client'] ) ? sanitize_textarea_field( $in['comment_client'] ) : '' );
		return is_wp_error( $r ) ? $r : self::summary( $r );
	}

	/**
	 * assign-submission.
	 *
	 * @param array $in Entrada.
	 * @return array|\WP_Error
	 */
	public static function assign( array $in ) {
		$s = self::find_submission( isset( $in['id'] ) ? $in['id'] : '' );
		if ( is_wp_error( $s ) ) {
			return $s;
		}
		$analyst = isset( $in['analyst'] ) ? trim( (string) $in['analyst'] ) : '';
		$id      = 0;
		if ( is_numeric( $analyst ) ) {
			$id = (int) $analyst;
		} elseif ( $analyst ) {
			$u  = get_user_by( 'email', $analyst );
			$id = $u ? $u->ID : -1;
		}
		if ( -1 === $id ) {
			return new \WP_Error( 'not_found', __( 'Usuário não encontrado.', 'eb-credito-rural' ) );
		}
		$r = SubmissionService::assign( get_current_user_id(), $s, $id );
		return is_wp_error( $r ) ? $r : self::summary( $r );
	}

	/**
	 * request-document.
	 *
	 * @param array $in Entrada.
	 * @return array|\WP_Error
	 */
	public static function request_document( array $in ) {
		$s = self::find_submission( isset( $in['id'] ) ? $in['id'] : '' );
		if ( is_wp_error( $s ) ) {
			return $s;
		}
		$r = SubmissionService::request_document( get_current_user_id(), $s, isset( $in['doc_type'] ) ? $in['doc_type'] : 'outro', isset( $in['label'] ) ? $in['label'] : '', isset( $in['note'] ) ? $in['note'] : '', ! array_key_exists( 'set_status', $in ) || ! empty( $in['set_status'] ) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return array(
			'request'    => $r['request'],
			'submission' => self::summary( $r['submission'] ),
		);
	}

	/**
	 * send-message.
	 *
	 * @param array $in Entrada.
	 * @return array|\WP_Error
	 */
	public static function send_message( array $in ) {
		$s = self::find_submission( isset( $in['id'] ) ? $in['id'] : '' );
		if ( is_wp_error( $s ) ) {
			return $s;
		}
		return SubmissionService::send_message( get_current_user_id(), $s, isset( $in['body'] ) ? $in['body'] : '', isset( $in['visibility'] ) ? $in['visibility'] : 'interno' );
	}

	/**
	 * review-document.
	 *
	 * @param array $in Entrada.
	 * @return array|\WP_Error
	 */
	public static function review_document( array $in ) {
		$doc = ( new DocumentRepository() )->find_by_public_id( isset( $in['document_id'] ) ? $in['document_id'] : '' );
		if ( ! $doc ) {
			return new \WP_Error( 'not_found', __( 'Documento não encontrado.', 'eb-credito-rural' ) );
		}
		$r = SubmissionService::review_document( get_current_user_id(), $doc, isset( $in['result'] ) ? $in['result'] : '', isset( $in['note'] ) ? $in['note'] : '' );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		unset( $r['stored_name'], $r['storage_dir'] );
		return $r;
	}
	/**
	 * get-report.
	 *
	 * @param array $in Entrada.
	 * @return array
	 */
	public static function get_report( array $in ) {
		$f = \EBCR\Reports\Metrics::normalize( $in, get_current_user_id() );
		return array(
			'filters'         => $f,
			'totals'          => \EBCR\Reports\Metrics::totals( $f ),
			'by_fund'         => \EBCR\Reports\Metrics::by_fund( $f ),
			'by_status'       => \EBCR\Reports\Metrics::by_status( $f ),
			'by_month'        => \EBCR\Reports\Metrics::by_month( $f ),
			'by_analyst'      => \EBCR\Reports\Metrics::by_analyst( $f ),
			'stage_durations' => \EBCR\Reports\Metrics::stage_durations( $f ),
		);
	}

	/**
	 * Localiza a ficha do CRM por contact_id ou user_id.
	 *
	 * @param array $in Entrada.
	 * @return array|\WP_Error
	 */
	private static function find_contact( array $in ) {
		if ( ! empty( $in['contact_id'] ) ) {
			$c = ( new \EBCR\Database\CrmContactRepository() )->find( (int) $in['contact_id'] );
		} elseif ( ! empty( $in['user_id'] ) ) {
			$c = \EBCR\Crm\Service::contact_for_user( (int) $in['user_id'] );
		} else {
			return new \WP_Error( 'bad_input', __( 'Informe contact_id ou user_id.', 'eb-credito-rural' ) );
		}
		return $c ? $c : new \WP_Error( 'not_found', __( 'Ficha não encontrada.', 'eb-credito-rural' ) );
	}

	/**
	 * Resumo de uma ficha.
	 *
	 * @param array $c Linha.
	 * @return array
	 */
	private static function contact_summary( array $c ) {
		$u = get_userdata( (int) $c['user_id'] );
		return array(
			'contact_id'       => (int) $c['id'],
			'user_id'          => (int) $c['user_id'],
			'name'             => $u ? $u->display_name : '',
			'email'            => $u ? $u->user_email : '',
			'phone'            => $c['phone'],
			'whatsapp'         => $c['whatsapp'],
			'lead_source'      => $c['lead_source'],
			'tags'             => $c['tags'],
			'stage'            => $c['stage'],
			'owner_id'         => $c['owner_id'] ? (int) $c['owner_id'] : null,
			'owner_name'       => $c['owner_id'] ? Helpers::user_name( (int) $c['owner_id'] ) : null,
			'next_action'      => $c['next_action'],
			'next_action_at'   => $c['next_action_at'],
			'notes'            => $c['notes'],
			'open_submissions' => isset( $c['open_submissions'] ) ? (int) $c['open_submissions'] : null,
			'last_activity_at' => isset( $c['last_activity_at'] ) ? $c['last_activity_at'] : null,
			'updated_at'       => $c['updated_at'],
		);
	}

	/**
	 * list-contacts.
	 *
	 * @param array $in Entrada.
	 * @return array
	 */
	public static function list_contacts( array $in ) {
		\EBCR\Crm\Service::sync_clients();
		$args = array(
			'open_statuses' => \EBCR\Crm\Service::open_statuses(),
			'per_page'      => isset( $in['per_page'] ) ? max( 1, min( 200, (int) $in['per_page'] ) ) : 50,
			'page'          => isset( $in['page'] ) ? max( 1, (int) $in['page'] ) : 1,
		);
		foreach ( array( 'search', 'stage', 'lead_source', 'tag' ) as $k ) {
			if ( isset( $in[ $k ] ) && '' !== (string) $in[ $k ] ) {
				$args[ $k ] = sanitize_text_field( (string) $in[ $k ] );
			}
		}
		if ( isset( $in['owner_id'] ) ) {
			$args['owner_id'] = (int) $in['owner_id'];
		}
		list( $items, $total ) = ( new \EBCR\Database\CrmContactRepository() )->query( $args );
		return array(
			'total'  => (int) $total,
			'page'   => $args['page'],
			'stages' => \EBCR\Crm\Service::stages(),
			'items'  => array_map( array( __CLASS__, 'contact_summary' ), $items ),
		);
	}

	/**
	 * update-contact.
	 *
	 * @param array $in Entrada.
	 * @return array|\WP_Error
	 */
	public static function update_contact( array $in ) {
		$c = self::find_contact( $in );
		if ( is_wp_error( $c ) ) {
			return $c;
		}
		$input = array();
		foreach ( array( 'stage', 'owner_id', 'lead_source', 'tags', 'next_action', 'next_action_at', 'notes', 'phone', 'whatsapp' ) as $k ) {
			if ( array_key_exists( $k, $in ) ) {
				$input[ $k ] = $in[ $k ];
			}
		}
		if ( ! $input ) {
			return new \WP_Error( 'empty', __( 'Informe pelo menos um campo para atualizar.', 'eb-credito-rural' ) );
		}
		$r = \EBCR\Crm\Service::update_contact( get_current_user_id(), (int) $c['id'], $input );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$fresh = ( new \EBCR\Database\CrmContactRepository() )->find( (int) $c['id'] );
		return self::contact_summary( $fresh ? $fresh : $c );
	}

	/**
	 * add-activity.
	 *
	 * @param array $in Entrada.
	 * @return array|\WP_Error
	 */
	public static function add_activity( array $in ) {
		$c = self::find_contact( $in );
		if ( is_wp_error( $c ) ) {
			return $c;
		}
		$input = array(
			'type'        => isset( $in['type'] ) ? $in['type'] : '',
			'description' => isset( $in['description'] ) ? $in['description'] : '',
		);
		if ( ! empty( $in['due_at'] ) ) {
			$input['due_at'] = $in['due_at'];
		}
		if ( ! empty( $in['assignee_id'] ) ) {
			$input['assignee_id'] = (int) $in['assignee_id'];
		}
		if ( ! empty( $in['id'] ) ) {
			$s = self::find_submission( $in['id'] );
			if ( is_wp_error( $s ) ) {
				return $s;
			}
			$input['submission_id'] = (int) $s['id'];
		}
		return \EBCR\Crm\Service::add_activity( get_current_user_id(), (int) $c['id'], $input );
	}
}
