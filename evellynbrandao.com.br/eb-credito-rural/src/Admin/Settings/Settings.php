<?php
/**
 * Configurações em abas, com texto de ajuda em cada campo, "Restaurar padrão" por aba e ferramentas.
 *
 * @package EBCR
 */

namespace EBCR\Admin\Settings;

use EBCR\Abilities\Abilities;
use EBCR\Database\DocumentRepository;
use EBCR\Database\MailQueueRepository;
use EBCR\Database\SubmissionDataRepository;
use EBCR\Domain\Consent;
use EBCR\Domain\Status;
use EBCR\Files\Antivirus;
use EBCR\Files\FileGuard;
use EBCR\Files\UploadHandler;
use EBCR\Forms\DocumentMatrix;
use EBCR\Mail\Events;
use EBCR\Mail\Mailer;
use EBCR\Roles\Capabilities;
use EBCR\Security\AuditLog;
use EBCR\Security\Crypto;
use EBCR\Security\Nonces;
use EBCR\Security\Retention;
use EBCR\Support\Options;
use EBCR\Support\View;

defined( 'ABSPATH' ) || exit;

/**
 * Salvamento via admin-post com nonce e capacidade ebcr_manage_settings.
 */
// phpcs:disable WordPress.Security.NonceVerification -- todos os handlers verificam o nonce (Nonces::verify/check_admin_referer) antes de ler a entrada.
final class Settings {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_post_ebcr_save_settings', array( $this, 'save' ) );
		add_action( 'admin_post_ebcr_reset_settings', array( $this, 'reset' ) );
		add_action( 'admin_post_ebcr_tool', array( $this, 'tool' ) );
	}

	/**
	 * Limites (mínimo, máximo|null) dos campos numéricos, aplicados na sanitização.
	 */
	const LIMITS = array(
		'min_amount'                => array( 0, null ),
		'max_amount'                => array( 0, null ),
		'min_term_months'           => array( 1, 600 ),
		'max_term_months'           => array( 1, 600 ),
		'max_area_ha'               => array( 0, null ),
		'max_file_size_mb'          => array( 1, 100 ),
		'max_files_per_submission'  => array( 1, 200 ),
		'user_quota_mb'             => array( 1, 10000 ),
		'min_interval_days'         => array( 0, 365 ),
		'pending_reminder_days'     => array( 0, 365 ),
		'pending_cancel_days'       => array( 0, 365 ),
		'login_max_attempts'        => array( 1, 50 ),
		'login_window_minutes'      => array( 1, 1440 ),
		'min_fill_seconds'          => array( 0, 600 ),
		'team_session_hours'        => array( 1, 168 ),
		'password_min_length'       => array( 8, 64 ),
		'retention_months_rejected' => array( 1, 600 ),
		'retention_months_approved' => array( 1, 600 ),
		'audit_retention_days'      => array( 30, 3650 ),
		'simulator_rate'            => array( 0, 200 ),
		'simulator_min_amount'      => array( 0, null ),
		'simulator_max_amount'      => array( 0, null ),
		'simulator_max_term'        => array( 1, 600 ),
		'simulator_grace_months'    => array( 0, 60 ),
	);

	/**
	 * Abas: chave => [rótulo, introdução, campos].
	 *
	 * @return array
	 */
	public static function tabs() {
		return array(
			'geral'       => array(
				'label' => __( 'Geral', 'eb-credito-rural' ),
				'intro' => __( 'Identidade da operação, quem recebe os avisos e onde fica a área do cliente. Comece por aqui após ativar o plugin.', 'eb-credito-rural' ),
			),
			'formulario'  => array(
				'label' => __( 'Formulário', 'eb-credito-rural' ),
				'intro' => __( 'Limites de valor e prazo aceitos, listas de atividades/finalidades e textos de ajuda mostrados no topo de cada etapa.', 'eb-credito-rural' ),
			),
			'documentos'  => array(
				'label' => __( 'Documentos e uploads', 'eb-credito-rural' ),
				'intro' => __( 'Regras de upload e a matriz de documentos: o que é pedido, quando, se é obrigatório e por quantos dias vale.', 'eb-credito-rural' ),
			),
			'regras'      => array(
				'label' => __( 'Regras de submissão', 'eb-credito-rural' ),
				'intro' => __( 'Quando o cliente pode iniciar uma nova solicitação e como o sistema lida com pendências sem resposta.', 'eb-credito-rural' ),
			),
			'seguranca'   => array(
				'label' => __( 'Segurança', 'eb-credito-rural' ),
				'intro' => __( 'Onde os documentos ficam guardados, criptografia, limites de login, captcha e sessão da equipe. Execute o teste de proteção após qualquer mudança de servidor.', 'eb-credito-rural' ),
			),
			'emails'      => array(
				'label' => __( 'E-mails', 'eb-credito-rural' ),
				'intro' => __( 'Remetente, templates por evento (com placeholders) e teste de envio. Recomendamos um plugin SMTP para garantir a entrega.', 'eb-credito-rural' ),
			),
			'privacidade' => array(
				'label' => __( 'Privacidade e compliance', 'eb-credito-rural' ),
				'intro' => __( 'Políticas exibidas para aceite, suas versões (alterar a versão exige novo aceite), prazos de retenção e contato do encarregado (DPO).', 'eb-credito-rural' ),
			),
			'status'      => array(
				'label' => __( 'Status', 'eb-credito-rural' ),
				'intro' => __( 'Nome, cor e texto padrão ao cliente de cada status do fluxo. As transições seguem o fluxo padrão do sistema.', 'eb-credito-rural' ),
			),
			'ferramentas' => array(
				'label' => __( 'Ferramentas', 'eb-credito-rural' ),
				'intro' => __( 'Verificação do ambiente, fila de e-mails, retenção e exportação/importação das configurações (sem segredos).', 'eb-credito-rural' ),
			),
			'crm'         => array(
				'label' => __( 'CRM', 'eb-credito-rural' ),
				'intro' => __( 'Estágios do funil, origens de lead e lembretes de tarefas da equipe comercial.', 'eb-credito-rural' ),
			),
			'integracoes' => array(
				'label' => __( 'Integrações', 'eb-credito-rural' ),
				'intro' => __( 'Consultas automáticas (CEP/CNPJ), captcha Turnstile, WhatsApp, assinatura eletrônica e simulador. Tudo opcional: o plugin funciona sem nenhuma integração externa.', 'eb-credito-rural' ),
			),
			'desinstalar' => array(
				'label' => __( 'Desinstalação', 'eb-credito-rural' ),
				'intro' => __( 'O que acontece com os dados se o plugin for removido.', 'eb-credito-rural' ),
			),
		);
	}

	/**
	 * Campos simples por aba: chave => [type, label, help, extra].
	 *
	 * @param string $tab Aba.
	 * @return array
	 */
	public static function fields( $tab ) {
		switch ( $tab ) {
			case 'geral':
				return array(
					'operation_name'          => array( 'text', __( 'Nome da operação', 'eb-credito-rural' ), __( 'Aparece em títulos e e-mails. Recomendado: nome da empresa/fundo. Impacto: apenas visual.', 'eb-credito-rural' ) ),
					'admin_emails'            => array( 'textarea', __( 'E-mails administrativos', 'eb-credito-rural' ), __( 'Um por linha. Recebem avisos de novas solicitações, mudanças de status, mensagens e pendências. Recomendado: caixa da equipe de crédito.', 'eb-credito-rural' ) ),
					'notify_assigned_analyst' => array( 'checkbox', __( 'Notificar também o analista atribuído', 'eb-credito-rural' ), __( 'Quando ligado, o analista responsável recebe cópia dos avisos da solicitação. Recomendado: ligado.', 'eb-credito-rural' ) ),
					'portal_page_id'          => array( 'page', __( 'Página do portal do cliente', 'eb-credito-rural' ), __( 'Página que contém o shortcode [ebcr_portal]. Todos os links dos e-mails apontam para ela. Obrigatório.', 'eb-credito-rural' ) ),
					'protocol_prefix'         => array( 'text', __( 'Prefixo do protocolo', 'eb-credito-rural' ), __( 'Ex.: EB gera EB-2026-000123. Só letras e números. Impacto: apenas exibição; os links usam identificadores não sequenciais.', 'eb-credito-rural' ) ),
					'email_logo_url'          => array( 'url', __( 'URL do logotipo para e-mails', 'eb-credito-rural' ), __( 'Imagem PNG/JPG (largura recomendada 280 px). Deixe em branco para usar o nome do site.', 'eb-credito-rural' ) ),
					'funds'                   => array( 'textarea', __( 'Fundos / carteiras', 'eb-credito-rural' ), __( 'Uma por linha no formato chave|Nome (ex.: fiagro|FIAGRO Safra). Cada solicitação pode ser vinculada a uma carteira na tela de detalhe; os Relatórios agrupam por carteira.', 'eb-credito-rural' ) ),
				);
			case 'formulario':
				return array(
					'min_amount'      => array( 'number', __( 'Valor mínimo (R$)', 'eb-credito-rural' ), __( 'Solicitações abaixo disso são rejeitadas na etapa 4. Recomendado: o ticket mínimo do fundo.', 'eb-credito-rural' ) ),
					'max_amount'      => array( 'number', __( 'Valor máximo (R$)', 'eb-credito-rural' ), __( 'Limite superior aceito. Impacto: bloqueio no formulário; não impede a análise manual de valores diferentes depois.', 'eb-credito-rural' ) ),
					'min_term_months' => array( 'number', __( 'Prazo mínimo (meses)', 'eb-credito-rural' ), __( 'Recomendado: 6.', 'eb-credito-rural' ) ),
					'max_term_months' => array( 'number', __( 'Prazo máximo (meses)', 'eb-credito-rural' ), __( 'Recomendado: 180 (15 anos, típico de investimento).', 'eb-credito-rural' ) ),
					'max_area_ha'     => array( 'number', __( 'Área máxima por imóvel (ha)', 'eb-credito-rural' ), __( 'Evita erros de digitação. Recomendado: 500.000.', 'eb-credito-rural' ) ),
					'activities'      => array( 'textarea', __( 'Atividades (chave|rótulo por linha)', 'eb-credito-rural' ), __( 'Mantenha as chaves pecuaria_corte e pecuaria_leite para que os documentos de rebanho sejam pedidos automaticamente. A chave "outras" habilita o campo de texto livre.', 'eb-credito-rural' ) ),
					'purposes'        => array( 'textarea', __( 'Finalidades (chave|rótulo por linha)', 'eb-credito-rural' ), __( 'Ex.: custeio|Custeio. Impacto: opções da etapa 4.', 'eb-credito-rural' ) ),
					'step_help'       => array( 'steps', __( 'Texto de ajuda por etapa', 'eb-credito-rural' ), __( 'Parágrafo curto exibido no topo de cada etapa para orientar o produtor.', 'eb-credito-rural' ) ),
				);
			case 'documentos':
				return array(
					'allowed_extensions'       => array( 'text', __( 'Extensões permitidas', 'eb-credito-rural' ), __( 'Separadas por vírgula. Padrão: pdf,jpg,jpeg,png. Por segurança, svg, html, php, js e zip nunca são aceitos mesmo que listados.', 'eb-credito-rural' ) ),
					'max_file_size_mb'         => array( 'number', __( 'Tamanho máximo por arquivo (MB)', 'eb-credito-rural' ), __( 'Deve ser menor ou igual aos limites do PHP (verifique em Ferramentas). Recomendado: 10.', 'eb-credito-rural' ) ),
					'max_files_per_submission' => array( 'number', __( 'Máximo de arquivos por solicitação', 'eb-credito-rural' ), __( 'Recomendado: 80 (vários imóveis geram muitos documentos).', 'eb-credito-rural' ) ),
					'user_quota_mb'            => array( 'number', __( 'Cota total por cliente (MB)', 'eb-credito-rural' ), __( 'Soma de todos os documentos do cliente. O uso aparece no painel dele. Recomendado: 200.', 'eb-credito-rural' ) ),
					'strip_exif'               => array( 'checkbox', __( 'Remover metadados EXIF de imagens', 'eb-credito-rural' ), __( 'Elimina geolocalização e dados da câmera de fotos enviadas (minimização de dados). Recomendado: ligado.', 'eb-credito-rural' ) ),
					'antivirus_enabled'        => array( 'checkbox', __( 'Verificar arquivos com antivírus (ClamAV)', 'eb-credito-rural' ), Antivirus::is_available() ? __( 'clamdscan encontrado no servidor. Arquivos suspeitos são rejeitados.', 'eb-credito-rural' ) : __( 'Comando não encontrado no servidor; se ligar, TODOS os uploads serão rejeitados até instalar o ClamAV.', 'eb-credito-rural' ) ),
					'antivirus_command'        => array( 'text', __( 'Comando do antivírus', 'eb-credito-rural' ), __( 'Padrão: clamdscan --no-summary --fdpass. O caminho do arquivo é acrescentado ao final.', 'eb-credito-rural' ) ),
					'document_matrix'          => array( 'matrix', __( 'Matriz de documentos', 'eb-credito-rural' ), __( 'Condição define quando o documento é pedido; Obrigatoriedade define se bloqueia o envio; Validade (dias) calcula o vencimento a partir do upload (0 = não vence).', 'eb-credito-rural' ) ),
				);
			case 'regras':
				return array(
					'min_interval_days'        => array( 'number', __( 'Intervalo mínimo entre solicitações (dias)', 'eb-credito-rural' ), __( 'Contado a partir do envio da última. O botão "Nova solicitação" mostra a contagem regressiva, e o servidor também bloqueia. Recomendado: 30.', 'eb-credito-rural' ) ),
					'block_if_in_progress'     => array( 'checkbox', __( 'Bloquear nova solicitação enquanto houver outra em andamento', 'eb-credito-rural' ), __( 'Evita duplicidade de análise. Recomendado: ligado.', 'eb-credito-rural' ) ),
					'allow_duplicate_previous' => array( 'checkbox', __( 'Permitir "aproveitar dados da solicitação anterior"', 'eb-credito-rural' ), __( 'Copia dados e imóveis (nunca documentos) para um novo rascunho.', 'eb-credito-rural' ) ),
					'pending_reminder_days'    => array( 'number', __( 'Lembrete de pendência após (dias)', 'eb-credito-rural' ), __( 'Cliente e equipe recebem lembrete quando um documento pedido fica esse tempo sem resposta. 0 desliga. Recomendado: 7.', 'eb-credito-rural' ) ),
					'pending_cancel_days'      => array( 'number', __( 'Cancelamento automático após (dias)', 'eb-credito-rural' ), __( 'Pendências sem resposta por esse período cancelam a solicitação (o cliente é avisado). 0 desliga. Use com cautela.', 'eb-credito-rural' ) ),
				);
			case 'seguranca':
				return array(
					'storage_path'          => array( 'text', __( 'Pasta privada de documentos (caminho absoluto)', 'eb-credito-rural' ), __( 'Recomendado: uma pasta FORA da raiz pública, ex.: /home/usuario/ebcr-private. Deve existir e ser gravável pelo PHP. Se vazio ou inválido, usa wp-content/uploads/ebcr-private com bloqueio por .htaccess (teste abaixo!).', 'eb-credito-rural' ) ),
					'encrypt_files'         => array( 'checkbox', __( 'Criptografar arquivos em repouso', 'eb-credito-rural' ), __( 'Usa libsodium com a chave EBCR_ENCRYPTION_KEY do wp-config.php. Perder a chave torna os arquivos irrecuperáveis. Não é possível desligar com arquivos já criptografados.', 'eb-credito-rural' ) ),
					'encrypt_fields'        => array( 'checkbox', __( 'Criptografar campos sensíveis (identificação e financeiro)', 'eb-credito-rural' ), __( 'CPF/CNPJ, renda e dívidas ficam cifrados no banco. A busca por CPF na lista deixa de funcionar para esses registros.', 'eb-credito-rural' ) ),
					'login_max_attempts'    => array( 'number', __( 'Tentativas de login antes do bloqueio', 'eb-credito-rural' ), __( 'Por IP e por usuário, dentro da janela abaixo. Bloqueios repetidos dobram a duração. Recomendado: 5.', 'eb-credito-rural' ) ),
					'login_window_minutes'  => array( 'number', __( 'Janela / duração do bloqueio (minutos)', 'eb-credito-rural' ), __( 'Recomendado: 15.', 'eb-credito-rural' ) ),
					'captcha_provider'      => array(
						'select',
						__( 'Provedor de captcha', 'eb-credito-rural' ),
						__( 'Matemático: gerado no servidor, sem serviços externos. Turnstile: preencha site key e secret key em Integrações; sem as chaves o plugin volta ao matemático.', 'eb-credito-rural' ),
						array(
							'math'      => __( 'Matemático (nativo)', 'eb-credito-rural' ),
							'turnstile' => __( 'Cloudflare Turnstile (chaves em Integrações)', 'eb-credito-rural' ),
						),
					),
					'captcha_difficulty'    => array(
						'select',
						__( 'Dificuldade do captcha', 'eb-credito-rural' ),
						__( 'Normal: soma/subtração até 10. Difícil: inclui multiplicação e números até 20.', 'eb-credito-rural' ),
						array(
							'normal'  => __( 'Normal', 'eb-credito-rural' ),
							'dificil' => __( 'Difícil', 'eb-credito-rural' ),
						),
					),
					'captcha_on_login'      => array( 'checkbox', __( 'Exigir captcha também no login', 'eb-credito-rural' ), __( 'Reduz ataques de força bruta; um pequeno atrito para o cliente. Recomendado: ligado.', 'eb-credito-rural' ) ),
					'honeypot'              => array( 'checkbox', __( 'Campo honeypot', 'eb-credito-rural' ), __( 'Campo invisível que robôs preenchem. Sem impacto para pessoas. Recomendado: ligado.', 'eb-credito-rural' ) ),
					'min_fill_seconds'      => array( 'number', __( 'Tempo mínimo de preenchimento (segundos)', 'eb-credito-rural' ), __( 'Envios mais rápidos que isso em login/cadastro/envio são rejeitados. Recomendado: 3.', 'eb-credito-rural' ) ),
					'team_session_hours'    => array( 'number', __( 'Expiração da sessão da equipe (horas)', 'eb-credito-rural' ), __( 'Analistas e gestores precisam entrar novamente após esse tempo. 0 = padrão do WordPress. Recomendado: 8.', 'eb-credito-rural' ) ),
					'team_2fa_mode'         => array(
						'select',
						__( 'Verificação em duas etapas (2FA) da equipe', 'eb-credito-rural' ),
						__( 'Opcional: cada analista/gestor ativa no próprio perfil (aplicativo autenticador ou código por e-mail). Obrigatório: a equipe só entra no painel depois de ativar. Desligado: ninguém é cobrado. Recomendado: obrigatório.', 'eb-credito-rural' ),
						array(
							'off'      => __( 'Desligado', 'eb-credito-rural' ),
							'optional' => __( 'Opcional (cada usuário decide)', 'eb-credito-rural' ),
							'required' => __( 'Obrigatório para analistas e gestores', 'eb-credito-rural' ),
						),
					),
					'analyst_only_assigned' => array( 'checkbox', __( 'Analista vê apenas as solicitações atribuídas a ele', 'eb-credito-rural' ), __( 'Gestores e administradores continuam vendo tudo. Útil com equipes maiores.', 'eb-credito-rural' ) ),
					'password_min_length'   => array( 'number', __( 'Tamanho mínimo da senha do cliente', 'eb-credito-rural' ), __( 'Além do tamanho, exige três tipos de caracteres. Recomendado: 10.', 'eb-credito-rural' ) ),
					'require_https'         => array( 'checkbox', __( 'Alertar quando o portal não estiver em HTTPS', 'eb-credito-rural' ), __( 'Mostra aviso ao cliente e ao administrador. Mantenha ligado.', 'eb-credito-rural' ) ),
					'trusted_proxy_header'  => array( 'text', __( 'Cabeçalho de IP do proxy confiável', 'eb-credito-rural' ), __( 'Só preencha se o site estiver atrás de proxy/CDN (ex.: CF-Connecting-IP ou X-Forwarded-For). Vazio = usa o IP da conexão.', 'eb-credito-rural' ) ),
				);
			case 'emails':
				return array(
					'from_name'                  => array( 'text', __( 'Nome do remetente', 'eb-credito-rural' ), __( 'Ex.: Évellyn Brandão — Crédito Rural.', 'eb-credito-rural' ) ),
					'from_email'                 => array( 'email', __( 'E-mail do remetente', 'eb-credito-rural' ), __( 'Use um endereço do próprio domínio (e configure SPF/DKIM no provedor) para não cair em spam.', 'eb-credito-rural' ) ),
					'notify_admin_status_change' => array( 'checkbox', __( 'Avisar administração a cada mudança de status', 'eb-credito-rural' ), __( 'Desligue se a equipe preferir acompanhar só pelo painel.', 'eb-credito-rural' ) ),
					'attach_documents_admin'     => array( 'checkbox', __( 'Anexar documentos ao e-mail do administrador (NÃO recomendado)', 'eb-credito-rural' ), __( 'E-mail não é canal seguro: os anexos ficam em caixas de terceiros (risco LGPD) e mensagens grandes são recusadas. Prefira o link para o painel. Só ligue se houver exigência do cliente do projeto.', 'eb-credito-rural' ) ),
					'email_templates'            => array( 'templates', __( 'Templates por evento', 'eb-credito-rural' ), __( 'Assunto e corpo (texto simples; quebras de linha viram parágrafos). Deixe em branco para usar o padrão.', 'eb-credito-rural' ) ),
				);
			case 'privacidade':
				return array(
					'dpo_name'                  => array( 'text', __( 'Nome do encarregado (DPO)', 'eb-credito-rural' ), __( 'Exibido na página de privacidade do cliente.', 'eb-credito-rural' ) ),
					'dpo_email'                 => array( 'email', __( 'E-mail do encarregado (DPO)', 'eb-credito-rural' ), __( 'Recebe cópia das solicitações de direitos do titular.', 'eb-credito-rural' ) ),
					'retention_months_rejected' => array( 'number', __( 'Retenção de reprovadas/canceladas (meses)', 'eb-credito-rural' ), __( 'Após esse prazo, documentos são apagados e dados pessoais anonimizados (estatísticas agregadas permanecem). Recomendado: 24, salvo orientação jurídica.', 'eb-credito-rural' ) ),
					'retention_months_approved' => array( 'number', __( 'Retenção de aprovadas/concluídas (meses)', 'eb-credito-rural' ), __( 'Operações de crédito têm prazos legais de guarda; defina com o jurídico. Padrão provisório: 120 (10 anos).', 'eb-credito-rural' ) ),
					'audit_retention_days'      => array( 'number', __( 'Retenção do log de auditoria (dias)', 'eb-credito-rural' ), __( 'Recomendado: 730 (2 anos).', 'eb-credito-rural' ) ),
					'policies'                  => array( 'policies', __( 'Políticas e declarações', 'eb-credito-rural' ), __( 'Para cada item: página com o texto completo (opcional), versão e texto curto do aceite. Ao mudar a versão, o cliente precisa aceitar novamente no próximo acesso.', 'eb-credito-rural' ) ),
				);
			case 'status':
				return array( 'statuses' => array( 'statuses', __( 'Fluxo de status', 'eb-credito-rural' ), __( 'Textos ao cliente não devem expor critérios internos.', 'eb-credito-rural' ) ) );
			case 'crm':
				return array(
					'crm_stages'         => array( 'textarea', __( 'Estágios do funil (Kanban)', 'eb-credito-rural' ), __( 'Um por linha no formato chave|Nome, na ordem das colunas do quadro. Ex.: novo|Novo lead. Não remova um estágio em uso sem antes mover os contatos.', 'eb-credito-rural' ) ),
					'lead_sources'       => array( 'textarea', __( 'Origens de lead', 'eb-credito-rural' ), __( 'Um por linha no formato chave|Nome (site, indicação, WhatsApp, evento…). Aparece na ficha do cliente e nos filtros.', 'eb-credito-rural' ) ),
					'crm_task_reminders' => array( 'checkbox', __( 'Lembrar tarefas por e-mail', 'eb-credito-rural' ), __( 'Na rotina diária, envia ao responsável a lista de tarefas que vencem hoje ou já venceram. Recomendado: ligado.', 'eb-credito-rural' ) ),
				);
			case 'integracoes':
				return array(
					'cep_lookup'             => array( 'checkbox', __( 'Preencher endereço pelo CEP', 'eb-credito-rural' ), __( 'Consulta ViaCEP/BrasilAPI pelo servidor ao digitar o CEP no formulário (com cache). Sem chave. Se a hospedagem bloquear saída HTTP, o campo continua manual.', 'eb-credito-rural' ) ),
					'cnpj_lookup'            => array( 'checkbox', __( 'Preencher razão social pelo CNPJ', 'eb-credito-rural' ), __( 'Consulta BrasilAPI (dados públicos da Receita) para pessoa jurídica. Sem chave.', 'eb-credito-rural' ) ),
					'turnstile_site_key'     => array( 'text', __( 'Turnstile — site key', 'eb-credito-rural' ), __( 'Chave pública do widget Cloudflare Turnstile. Só é usada se o provedor de captcha (Segurança) for Turnstile.', 'eb-credito-rural' ) ),
					'turnstile_secret_key'   => array( 'text', __( 'Turnstile — secret key', 'eb-credito-rural' ), __( 'Chave secreta para validar a resposta no servidor. Fica gravada no banco; restrinja o acesso a esta tela.', 'eb-credito-rural' ) ),
					'whatsapp_enabled'       => array( 'checkbox', __( 'Notificações por WhatsApp (Cloud API)', 'eb-credito-rural' ), __( 'Envia pelo WhatsApp Business Cloud API (Meta) uma mensagem em cada evento notificado por e-mail. Exige conta na Meta, número aprovado e template aprovado. Desligado por padrão.', 'eb-credito-rural' ) ),
					'whatsapp_token'         => array( 'text', __( 'WhatsApp — token de acesso', 'eb-credito-rural' ), __( 'Token permanente do app na Meta for Developers (usuário de sistema). Fica gravado no banco.', 'eb-credito-rural' ) ),
					'whatsapp_phone_id'      => array( 'text', __( 'WhatsApp — Phone Number ID', 'eb-credito-rural' ), __( 'ID do número remetente (não é o telefone), disponível no painel do WhatsApp na Meta.', 'eb-credito-rural' ) ),
					'whatsapp_template'      => array( 'text', __( 'WhatsApp — nome do template', 'eb-credito-rural' ), __( 'Template de utilidade aprovado, com uma variável {{1}} para o texto do aviso (ex.: ebcr_aviso). Vazio = mensagem de texto simples (só funciona dentro da janela de 24 h).', 'eb-credito-rural' ) ),
					'whatsapp_notify_client' => array( 'checkbox', __( 'Avisar o cliente pelo WhatsApp', 'eb-credito-rural' ), __( 'Usa o WhatsApp informado no cadastro/etapa 1. Continua enviando o e-mail.', 'eb-credito-rural' ) ),
					'whatsapp_notify_team'   => array( 'checkbox', __( 'Avisar a equipe pelo WhatsApp', 'eb-credito-rural' ), __( 'Envia ao número abaixo um resumo de cada nova solicitação e mensagem do cliente.', 'eb-credito-rural' ) ),
					'whatsapp_team_number'   => array( 'text', __( 'WhatsApp — número da equipe', 'eb-credito-rural' ), __( 'Com DDI e DDD, só dígitos (ex.: 5562999999999).', 'eb-credito-rural' ) ),
					'esign_enabled'          => array( 'checkbox', __( 'Assinatura eletrônica no portal', 'eb-credito-rural' ), __( 'O cliente assina eletronicamente (nome, CPF, código enviado por e-mail, IP, data/hora e hash) os documentos listados abaixo, gerando um PDF assinado que entra na lista de documentos. Assinatura eletrônica simples (Lei 14.063/2020); para exigir certificado ICP-Brasil, desligue e peça o arquivo assinado.', 'eb-credito-rural' ) ),
					'esign_doc_types'        => array( 'text', __( 'Documentos assináveis (chaves da matriz)', 'eb-credito-rural' ), __( 'Chaves separadas por vírgula, ex.: autorizacao_scr. Só documentos cujo texto está configurado abaixo.', 'eb-credito-rural' ) ),
					'esign_scr_text'         => array( 'textarea', __( 'Texto da autorização de consulta ao SCR', 'eb-credito-rural' ), __( 'Texto apresentado e assinado pelo cliente. Aceita {protocolo}, {nome}, {cpf}, {data}. Revise com o jurídico.', 'eb-credito-rural' ) ),
					'simulator_enabled'      => array( 'checkbox', __( 'Simulador de crédito ([ebcr_simulador])', 'eb-credito-rural' ), __( 'Shortcode para a página de captação: valor, prazo, carência e sistema de amortização, com tabela de parcelas e botão para a área do produtor. Cálculo ilustrativo, não é proposta.', 'eb-credito-rural' ) ),
					'simulator_rate'         => array( 'number', __( 'Simulador — taxa de referência (% ao ano)', 'eb-credito-rural' ), __( 'Taxa usada apenas para ilustrar. Ex.: 12 para 12% a.a.', 'eb-credito-rural' ) ),
					'simulator_system'       => array(
						'select',
						__( 'Simulador — sistema de amortização', 'eb-credito-rural' ),
						__( 'Price: parcelas iguais. SAC: amortização constante, parcelas decrescentes. O visitante pode alternar.', 'eb-credito-rural' ),
						array(
							'price' => __( 'Price (parcelas iguais)', 'eb-credito-rural' ),
							'sac'   => __( 'SAC (parcelas decrescentes)', 'eb-credito-rural' ),
						),
					),
					'simulator_min_amount'   => array( 'number', __( 'Simulador — valor mínimo (R$)', 'eb-credito-rural' ), __( 'Limite inferior do controle de valor.', 'eb-credito-rural' ) ),
					'simulator_max_amount'   => array( 'number', __( 'Simulador — valor máximo (R$)', 'eb-credito-rural' ), __( 'Limite superior do controle de valor.', 'eb-credito-rural' ) ),
					'simulator_max_term'     => array( 'number', __( 'Simulador — prazo máximo (meses)', 'eb-credito-rural' ), __( 'Ex.: 180.', 'eb-credito-rural' ) ),
					'simulator_grace_months' => array( 'number', __( 'Simulador — carência padrão (meses)', 'eb-credito-rural' ), __( 'Meses sem amortização no início (juros capitalizados). 0 = sem carência.', 'eb-credito-rural' ) ),
					'simulator_cta_url'      => array( 'url', __( 'Simulador — link do botão', 'eb-credito-rural' ), __( 'Para onde o botão "Solicitar" leva. Vazio = página do portal.', 'eb-credito-rural' ) ),
				);
			case 'desinstalar':
				return array( 'keep_data_on_uninstall' => array( 'checkbox', __( 'Manter dados ao desinstalar', 'eb-credito-rural' ), __( 'LIGADO (recomendado): tabelas, documentos e configurações permanecem se o plugin for excluído. DESLIGADO: tudo é apagado definitivamente, inclusive documentos dentro de uploads. Não há como desfazer.', 'eb-credito-rural' ) ) );
			default:
				return array();
		}
	}

	/**
	 * Renderiza a página.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( Capabilities::CAP_SETTINGS ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'eb-credito-rural' ), 403 );
		}
		$guard = new FileGuard();
		View::show(
			'admin/settings',
			array(
				'tabs'          => self::tabs(),
				'values'        => Options::all(),
				'fields'        => array_combine( array_keys( self::tabs() ), array_map( array( __CLASS__, 'fields' ), array_keys( self::tabs() ) ) ),
				'guard'         => $guard,
				'storage'       => $guard->status(),
				'crypto'        => Crypto::status(),
				'new_key'       => Crypto::generate_key(),
				'has_encrypted' => ( new DocumentRepository() )->has_encrypted() || ( new SubmissionDataRepository() )->has_encrypted(),
				'php_limits'    => UploadHandler::php_limits(),
				'events'        => Events::all(),
				'placeholders'  => Events::placeholders(),
				'matrix'        => DocumentMatrix::all(),
				'conditions'    => DocumentMatrix::conditions(),
				'levels'        => DocumentMatrix::levels(),
				'policies'      => Consent::policies(),
				'statuses'      => Status::all(),
				'mail_stats'    => ( new MailQueueRepository() )->stats(),
				'mail_failures' => ( new MailQueueRepository() )->failures( 10 ),
				'env'           => self::environment(),
				'last_daily'    => get_option( 'ebcr_last_daily', array() ),
				'mcp'           => Abilities::mcp_status(),
				'notice'        => isset( $_GET['ebcr_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['ebcr_notice'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- mensagem informativa.
				'notice_type'   => isset( $_GET['ebcr_type'] ) && 'error' === $_GET['ebcr_type'] ? 'error' : 'success', // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- idem.
				'tab'           => isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'geral', // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navegação.
			)
		);
	}

	/**
	 * Verificação do ambiente.
	 *
	 * @return array
	 */
	public static function environment() {
		global $wp_version;
		$limits = UploadHandler::php_limits();
		return array(
			array( __( 'Versão do PHP', 'eb-credito-rural' ), PHP_VERSION, version_compare( PHP_VERSION, '8.1', '>=' ) ),
			array( __( 'Versão do WordPress', 'eb-credito-rural' ), $wp_version, version_compare( $wp_version, '6.4', '>=' ) ),
			array( __( 'HTTPS', 'eb-credito-rural' ), 'https' === wp_parse_url( home_url(), PHP_URL_SCHEME ) ? __( 'sim', 'eb-credito-rural' ) : __( 'não', 'eb-credito-rural' ), 'https' === wp_parse_url( home_url(), PHP_URL_SCHEME ) ),
			array( __( 'Extensão sodium', 'eb-credito-rural' ), function_exists( 'sodium_crypto_secretbox' ) ? __( 'disponível', 'eb-credito-rural' ) : __( 'ausente', 'eb-credito-rural' ), function_exists( 'sodium_crypto_secretbox' ) ),
			array( __( 'Extensão fileinfo', 'eb-credito-rural' ), function_exists( 'finfo_open' ) ? __( 'disponível', 'eb-credito-rural' ) : __( 'ausente', 'eb-credito-rural' ), function_exists( 'finfo_open' ) ),
			array( __( 'GD/Imagick (EXIF)', 'eb-credito-rural' ), ( function_exists( 'imagecreatefromstring' ) || class_exists( '\Imagick' ) ) ? __( 'disponível', 'eb-credito-rural' ) : __( 'ausente', 'eb-credito-rural' ), function_exists( 'imagecreatefromstring' ) || class_exists( '\Imagick' ) ),
			array( 'upload_max_filesize / post_max_size', size_format( $limits['php_upload'] ) . ' / ' . size_format( $limits['php_post'] ), $limits['ok'] ),
			array( __( 'WP-Cron', 'eb-credito-rural' ), ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) ? __( 'desativado (use cron do sistema)', 'eb-credito-rural' ) : __( 'ativo', 'eb-credito-rural' ), (bool) wp_next_scheduled( 'ebcr_daily' ) ),
			array( __( 'Action Scheduler', 'eb-credito-rural' ), function_exists( 'as_enqueue_async_action' ) ? __( 'disponível', 'eb-credito-rural' ) : __( 'não (usa WP-Cron)', 'eb-credito-rural' ), true ),
			array( __( 'Plugin SMTP', 'eb-credito-rural' ), ( class_exists( 'WPMailSMTP\Core' ) || class_exists( 'FluentMail\App\Application' ) || defined( 'POST_SMTP_VERSION' ) ) ? __( 'detectado', 'eb-credito-rural' ) : __( 'não detectado (recomendado)', 'eb-credito-rural' ), class_exists( 'WPMailSMTP\Core' ) || class_exists( 'FluentMail\App\Application' ) || defined( 'POST_SMTP_VERSION' ) ),
			array( __( 'Chave de criptografia', 'eb-credito-rural' ), Crypto::status(), 'ok' === Crypto::status() ),
			array( __( 'Antivírus (clamdscan)', 'eb-credito-rural' ), Antivirus::is_available() ? __( 'disponível', 'eb-credito-rural' ) : __( 'não encontrado', 'eb-credito-rural' ), true ),
		);
	}

	/**
	 * Redireciona à aba com mensagem.
	 *
	 * @param string $tab  Aba.
	 * @param string $msg  Mensagem.
	 * @param string $type Tipo.
	 * @return void
	 */
	private function back( $tab, $msg, $type = 'success' ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => 'ebcr-settings',
					'tab'         => $tab,
					'ebcr_notice' => rawurlencode( $msg ),
					'ebcr_type'   => $type,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Todos os campos de todas as abas: chave => definição (+ 'tab').
	 *
	 * @return array
	 */
	public static function all_fields() {
		$out = array();
		foreach ( array_keys( self::tabs() ) as $tab ) {
			foreach ( self::fields( $tab ) as $key => $def ) {
				$def['tab']  = $tab;
				$out[ $key ] = $def;
			}
		}
		return $out;
	}

	/**
	 * Sanitiza um conjunto de valores (só chaves conhecidas). Retorna [valores, erros].
	 *
	 * @param array $input Entrada bruta (chave => valor).
	 * @return array
	 */
	public static function sanitize( array $input ) {
		$fields = self::all_fields();
		$values = array();
		$errors = array();
		foreach ( $input as $key => $raw ) {
			if ( ! isset( $fields[ $key ] ) ) {
				$errors[] = sprintf( /* translators: %s: chave */ __( 'Configuração desconhecida: %s.', 'eb-credito-rural' ), $key );
				continue;
			}
			$def = $fields[ $key ];
			switch ( $def[0] ) {
				case 'checkbox':
					$values[ $key ] = is_string( $raw ) ? in_array( strtolower( $raw ), array( '1', 'true', 'on', 'sim', 'yes' ), true ) : ! empty( $raw );
					break;
				case 'number':
					$num = is_numeric( $raw ) ? 0 + $raw : 0;
					if ( isset( self::LIMITS[ $key ] ) ) {
						list( $lo, $hi ) = self::LIMITS[ $key ];
						$clamped         = max( $lo, null === $hi ? $num : min( $hi, $num ) );
						if ( $clamped !== $num ) {
							$errors[] = sprintf( /* translators: 1: chave, 2: valor aplicado */ __( '%1$s: valor fora do intervalo permitido; ajustado para %2$s.', 'eb-credito-rural' ), $key, $clamped );
							$num      = $clamped;
						}
					}
					$values[ $key ] = $num;
					break;
				case 'email':
					$values[ $key ] = sanitize_email( (string) $raw );
					break;
				case 'url':
					$values[ $key ] = esc_url_raw( (string) $raw );
					break;
				case 'page':
					$values[ $key ] = absint( $raw );
					break;
				case 'textarea':
					$values[ $key ] = sanitize_textarea_field( is_array( $raw ) ? implode( "\n", array_map( 'strval', $raw ) ) : (string) $raw );
					break;
				case 'select':
					$values[ $key ] = isset( $def[3][ (string) $raw ] ) ? (string) $raw : key( $def[3] );
					break;
				case 'steps':
					$values[ $key ] = array();
					foreach ( (array) $raw as $n => $t ) {
						$values[ $key ][ (int) $n ] = sanitize_textarea_field( (string) $t );
					}
					break;
				case 'matrix':
					$values[ $key ] = self::sanitize_matrix( is_array( $raw ) ? $raw : array() );
					break;
				case 'templates':
					$values[ $key ] = array();
					foreach ( (array) $raw as $ev => $t ) {
						$ev = sanitize_key( $ev );
						if ( isset( Events::all()[ $ev ] ) && is_array( $t ) ) {
							$values[ $key ][ $ev ] = array(
								'subject' => sanitize_text_field( (string) ( $t['subject'] ?? '' ) ),
								'body'    => wp_kses(
									(string) ( $t['body'] ?? '' ),
									array(
										'a'      => array( 'href' => true ),
										'strong' => array(),
										'em'     => array(),
										'br'     => array(),
									)
								),
							);
						}
					}
					break;
				case 'policies':
					$values[ $key ] = array();
					foreach ( (array) $raw as $pk => $pol ) {
						$pk = sanitize_key( $pk );
						if ( isset( Consent::default_policies()[ $pk ] ) && is_array( $pol ) ) {
							$values[ $key ][ $pk ] = array(
								'page_id' => absint( $pol['page_id'] ?? 0 ),
								'version' => sanitize_text_field( (string) ( $pol['version'] ?? '1.0' ) ),
								'text'    => sanitize_textarea_field( (string) ( $pol['text'] ?? '' ) ),
								'title'   => sanitize_text_field( (string) ( $pol['title'] ?? '' ) ),
							);
						}
					}
					break;
				case 'statuses':
					$values[ $key ] = array();
					foreach ( (array) $raw as $sk => $st ) {
						$sk = sanitize_key( $sk );
						if ( Status::exists( $sk ) && is_array( $st ) ) {
							$color                 = sanitize_hex_color( (string) ( $st['color'] ?? '' ) );
							$values[ $key ][ $sk ] = array(
								'label'          => sanitize_text_field( (string) ( $st['label'] ?? '' ) ),
								'color'          => $color ? $color : '#6b7280',
								'client_visible' => ! empty( $st['client_visible'] ),
								'client_text'    => sanitize_textarea_field( (string) ( $st['client_text'] ?? '' ) ),
							);
						}
					}
					break;
				default:
					$values[ $key ] = sanitize_text_field( (string) $raw );
			}
		}
		return array( $values, $errors );
	}

	/**
	 * Regras especiais (criptografia não pode ser desligada com dados cifrados; chave ausente; pasta inválida; página sem shortcode).
	 * Ajusta $values quando necessário e devolve avisos.
	 *
	 * @param array $values Valores sanitizados (por referência).
	 * @return string[] Avisos.
	 */
	public static function guard( array &$values ) {
		$warnings = array();
		if ( array_key_exists( 'encrypt_files', $values ) || array_key_exists( 'encrypt_fields', $values ) ) {
			$has_enc = ( new DocumentRepository() )->has_encrypted() || ( new SubmissionDataRepository() )->has_encrypted();
			if ( $has_enc && ( ( Options::bool( 'encrypt_files' ) && array_key_exists( 'encrypt_files', $values ) && empty( $values['encrypt_files'] ) ) || ( Options::bool( 'encrypt_fields' ) && array_key_exists( 'encrypt_fields', $values ) && empty( $values['encrypt_fields'] ) ) ) ) {
				$warnings[]               = __( 'Não é possível desligar a criptografia: já existem dados criptografados. Seria necessária uma rotina de migração.', 'eb-credito-rural' );
				$values['encrypt_files']  = Options::bool( 'encrypt_files' );
				$values['encrypt_fields'] = Options::bool( 'encrypt_fields' );
			}
			if ( ( ! empty( $values['encrypt_files'] ) || ! empty( $values['encrypt_fields'] ) ) && ! Crypto::is_available() ) {
				$warnings[] = __( 'Criptografia ligada sem chave válida: defina EBCR_ENCRYPTION_KEY no wp-config.php. Até lá, os dados NÃO serão criptografados.', 'eb-credito-rural' );
			}
		}
		if ( ! empty( $values['storage_path'] ) && ( ! is_dir( $values['storage_path'] ) || ! wp_is_writable( $values['storage_path'] ) ) ) {
			$warnings[] = __( 'A pasta informada não existe ou não é gravável; o plugin continuará usando a pasta de fallback dentro de uploads.', 'eb-credito-rural' );
		}
		if ( ! empty( $values['portal_page_id'] ) && ! has_shortcode( (string) get_post_field( 'post_content', $values['portal_page_id'] ), 'ebcr_portal' ) ) {
			$warnings[] = __( 'A página escolhida não contém o shortcode [ebcr_portal].', 'eb-credito-rural' );
		}
		return $warnings;
	}

	/**
	 * Grava valores já sanitizados e registra auditoria.
	 *
	 * @param array  $values  Valores.
	 * @param string $context Origem (aba ou "mcp").
	 * @return void
	 */
	public static function persist( array $values, $context ) {
		Options::update( $values );
		if ( array_key_exists( 'storage_path', $values ) ) {
			( new FileGuard() )->ensure_base_dir();
		}
		if ( array_key_exists( 'portal_page_id', $values ) ) {
			delete_transient( 'ebcr_portal_page_auto' );
		}
		AuditLog::log( 'settings_updated', 'settings', $context, array( 'keys' => array_keys( $values ) ) );
	}

	/**
	 * Salva uma aba (admin-post).
	 *
	 * @return void
	 */
	public function save() {
		if ( ! current_user_can( Capabilities::CAP_SETTINGS ) || ! Nonces::verify( 'settings' ) ) {
			wp_die( esc_html__( 'Sem permissão ou sessão expirada.', 'eb-credito-rural' ), 403 );
		}
		$tab   = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'geral';
		$in    = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitizado por sanitize().
		$input = array();
		foreach ( self::fields( $tab ) as $key => $def ) {
			$input[ $key ] = isset( $in[ $key ] ) ? $in[ $key ] : ( 'checkbox' === $def[0] ? '' : null );
			if ( null === $input[ $key ] ) {
				unset( $input[ $key ] );
			}
		}
		list( $values, $errors ) = self::sanitize( $input );
		$warnings                = self::guard( $values );
		self::persist( $values, $tab );
		$msgs = array_merge( $errors, $warnings );
		$this->back( $tab, $msgs ? implode( ' ', $msgs ) : __( 'Configurações salvas.', 'eb-credito-rural' ), $msgs ? 'error' : 'success' );
	}

	/**
	 * Sanitiza a matriz de documentos.
	 *
	 * @param array $rows Linhas.
	 * @return array
	 */
	private function sanitize_matrix( array $rows ) {
		$out = array();
		foreach ( $rows as $r ) {
			if ( ! is_array( $r ) || empty( $r['key'] ) ) {
				continue;
			}
			$out[] = array(
				'key'           => sanitize_key( $r['key'] ),
				'label'         => sanitize_text_field( (string) ( $r['label'] ?? '' ) ),
				'condition'     => isset( DocumentMatrix::conditions()[ $r['condition'] ?? '' ] ) ? $r['condition'] : 'never',
				'required'      => isset( DocumentMatrix::levels()[ $r['required'] ?? '' ] ) ? $r['required'] : 'nao',
				'validity_days' => max( 0, (int) ( $r['validity_days'] ?? 0 ) ),
				'help'          => sanitize_text_field( (string) ( $r['help'] ?? '' ) ),
			);
		}
		return $out;
	}

	/**
	 * Restaura padrão de uma aba.
	 *
	 * @return void
	 */
	public function reset() {
		if ( ! current_user_can( Capabilities::CAP_SETTINGS ) || ! Nonces::verify( 'settings_reset' ) ) {
			wp_die( esc_html__( 'Sem permissão ou sessão expirada.', 'eb-credito-rural' ), 403 );
		}
		$tab  = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'geral';
		$keys = array_keys( self::fields( $tab ) );
		if ( 'seguranca' === $tab ) {
			$keys = array_diff( $keys, array( 'encrypt_files', 'encrypt_fields' ) ); // nunca desliga criptografia por reset.
		}
		Options::reset( $keys );
		AuditLog::log( 'settings_updated', 'settings', $tab, array( 'reset' => true ) );
		$this->back( $tab, __( 'Padrões restaurados.', 'eb-credito-rural' ) );
	}

	/**
	 * Ferramentas: testar proteção, e-mail de teste, reprocessar fila, rodar retenção, exportar/importar.
	 *
	 * @return void
	 */
	public function tool() {
		if ( ! current_user_can( Capabilities::CAP_SETTINGS ) || ! Nonces::verify( 'settings_tool' ) ) {
			wp_die( esc_html__( 'Sem permissão ou sessão expirada.', 'eb-credito-rural' ), 403 );
		}
		$tool = isset( $_POST['tool'] ) ? sanitize_key( wp_unslash( $_POST['tool'] ) ) : '';
		switch ( $tool ) {
			case 'test_protection':
				$r = ( new FileGuard() )->test_protection();
				$this->back( 'seguranca', $r['message'], 'exposed' === $r['result'] ? 'error' : 'success' );
				break;
			case 'test_email':
				$to = isset( $_POST['to'] ) ? sanitize_email( wp_unslash( $_POST['to'] ) ) : '';
				$ok = $to && Mailer::send_test( $to );
				$this->back( 'emails', $ok ? __( 'E-mail de teste enviado. Verifique a caixa (e o spam).', 'eb-credito-rural' ) : __( 'Falha ao enviar. Configure um plugin SMTP e verifique o remetente.', 'eb-credito-rural' ), $ok ? 'success' : 'error' );
				break;
			case 'retry_mail':
				$n = ( new MailQueueRepository() )->retry_failed();
				\EBCR\Mail\Queue::kick();
				$this->back( 'ferramentas', sprintf( /* translators: %d: quantidade */ __( '%d e-mail(s) reenfileirado(s).', 'eb-credito-rural' ), $n ) );
				break;
			case 'process_mail':
				$n = \EBCR\Mail\Queue::process( 50 );
				$this->back( 'ferramentas', sprintf( /* translators: %d: quantidade */ __( '%d e-mail(s) enviado(s).', 'eb-credito-rural' ), $n ) );
				break;
			case 'run_retention':
				$n = Retention::run();
				$this->back( 'ferramentas', sprintf( /* translators: %d: quantidade */ __( 'Rotina de retenção executada: %d solicitação(ões) anonimizada(s).', 'eb-credito-rural' ), $n ) );
				break;
			case 'run_daily':
				$r = \EBCR\Cron\Scheduler::daily();
				$this->back( 'ferramentas', sprintf( /* translators: 1: lembretes, 2: certidões, 3: anonimizadas */ __( 'Rotina diária executada: %1$d lembrete(s), %2$d certidão(ões) a vencer, %3$d anonimizada(s).', 'eb-credito-rural' ), $r['reminders'], $r['certificates'], $r['anonymized'] ) );
				break;
			case 'enable_mcp':
				$r = Abilities::enable_in_easy_mcp();
				if ( ! $r['enabled'] ) {
					$this->back( 'ferramentas', 'abilities_api_missing' === $r['reason'] ? __( 'Este WordPress não tem a Abilities API (atualize para a versão 6.9 ou superior).', 'eb-credito-rural' ) : __( 'O plugin Easy MCP AI não foi detectado. Instale e ative-o e tente novamente.', 'eb-credito-rural' ), 'error' );
				}
				$this->back( 'ferramentas', $r['added'] ? sprintf( /* translators: %d: quantidade */ __( '%d ability(ies) do plugin habilitada(s) no Easy MCP AI. Reconecte o agente para carregar as novas ferramentas.', 'eb-credito-rural' ), $r['added'] ) : __( 'Todas as abilities do plugin já estão habilitadas no Easy MCP AI.', 'eb-credito-rural' ) );
				break;
			case 'export_settings':
				$all = Options::all();
				unset( $all['sentinel_name'], $all['last_protection_test'], $all['storage_path'], $all['antivirus_command'], $all['trusted_proxy_header'] );
				AuditLog::log( 'export', 'settings', '', array() );
				nocache_headers();
				header( 'Content-Type: application/json; charset=utf-8' );
				header( 'Content-Disposition: attachment; filename="ebcr-configuracoes.json"' );
				echo wp_json_encode( $all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON.
				exit;
			case 'import_settings':
				$file = isset( $_FILES['settings_file']['tmp_name'] ) ? sanitize_text_field( $_FILES['settings_file']['tmp_name'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- caminho temporário.
				$data = $file && is_uploaded_file( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- upload temporário.
				if ( ! is_array( $data ) ) {
					$this->back( 'ferramentas', __( 'Arquivo inválido.', 'eb-credito-rural' ), 'error' );
				}
				$allowed = array_keys( Options::defaults() );
				$clean   = array();
				foreach ( $data as $k => $v ) {
					if ( in_array( $k, $allowed, true ) && ! in_array( $k, array( 'storage_path', 'sentinel_name', 'last_protection_test', 'encrypt_files', 'encrypt_fields', 'antivirus_command', 'trusted_proxy_header' ), true ) ) {
						$clean[ $k ] = is_string( $v ) ? sanitize_textarea_field( $v ) : $v;
					}
				}
				Options::update( $clean );
				AuditLog::log( 'settings_updated', 'settings', 'import', array( 'keys' => array_keys( $clean ) ) );
				$this->back( 'ferramentas', __( 'Configurações importadas (segredos e caminhos não são importados).', 'eb-credito-rural' ) );
				break;
			default:
				$this->back( 'ferramentas', __( 'Ferramenta desconhecida.', 'eb-credito-rural' ), 'error' );
		}
	}
}
