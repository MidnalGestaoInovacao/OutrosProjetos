<?php
/**
 * Eventos de e-mail e templates padrão (assunto + corpo com placeholders).
 *
 * @package EBCR
 */

namespace EBCR\Mail;

defined( 'ABSPATH' ) || exit;

/**
 * Placeholders: {nome}, {protocolo}, {status}, {comentario}, {link_portal}, {pendencias}, {data}, {site}, {link_confirmacao}, {valor}, {documento}, {mensagem}.
 */
final class Events {

	/**
	 * Definições: chave => [label, audience (cliente|admin), subject, body].
	 *
	 * @return array
	 */
	public static function all() {
		return array(
			'register_confirm'        => array(
				'label'    => __( 'Cadastro — confirmação de e-mail (cliente)', 'eb-credito-rural' ),
				'audience' => 'cliente',
				'subject'  => 'Confirme seu e-mail — {site}',
				'body'     => "Olá, {nome}.\n\nPara ativar seu cadastro e enviar solicitações de crédito, confirme seu e-mail clicando no link abaixo (válido por 24 horas):\n\n{link_confirmacao}\n\nSe você não fez este cadastro, ignore esta mensagem.",
			),
			'submission_client'       => array(
				'label'    => __( 'Solicitação enviada (cliente)', 'eb-credito-rural' ),
				'audience' => 'cliente',
				'subject'  => 'Recebemos sua solicitação {protocolo}',
				'body'     => "Olá, {nome}.\n\nSua solicitação de crédito rural foi recebida em {data} e registrada com o protocolo {protocolo}.\n\nValor solicitado: {valor}\nStatus atual: {status}\n\nAcompanhe o andamento, responda pendências e fale com a equipe pela sua área:\n{link_portal}\n\nObrigado pela confiança.",
			),
			'submission_admin'        => array(
				'label'    => __( 'Solicitação enviada (administração)', 'eb-credito-rural' ),
				'audience' => 'admin',
				'subject'  => 'Nova solicitação {protocolo} — {nome}',
				'body'     => "Nova solicitação enviada em {data}.\n\nProtocolo: {protocolo}\nCliente: {nome}\nValor solicitado: {valor}\n\nAbra no painel:\n{link_portal}",
			),
			'status_client'           => array(
				'label'    => __( 'Mudança de status (cliente)', 'eb-credito-rural' ),
				'audience' => 'cliente',
				'subject'  => 'Atualização da solicitação {protocolo}: {status}',
				'body'     => "Olá, {nome}.\n\nSua solicitação {protocolo} teve o status atualizado para: {status}.\n\n{comentario}\n\nVeja os detalhes na sua área:\n{link_portal}",
			),
			'status_admin'            => array(
				'label'    => __( 'Mudança de status (administração)', 'eb-credito-rural' ),
				'audience' => 'admin',
				'subject'  => 'Solicitação {protocolo}: {status}',
				'body'     => "A solicitação {protocolo} ({nome}) mudou para: {status}.\n\n{comentario}\n\nPainel: {link_portal}",
			),
			'document_requested'      => array(
				'label'    => __( 'Pendência / documento solicitado (cliente)', 'eb-credito-rural' ),
				'audience' => 'cliente',
				'subject'  => 'Documento solicitado — {protocolo}',
				'body'     => "Olá, {nome}.\n\nA equipe solicitou o seguinte documento para a solicitação {protocolo}:\n\n{documento}\n\n{comentario}\n\nEnvie pela sua área:\n{link_portal}",
			),
			'document_rejected'       => array(
				'label'    => __( 'Documento recusado (cliente)', 'eb-credito-rural' ),
				'audience' => 'cliente',
				'subject'  => 'Documento precisa ser reenviado — {protocolo}',
				'body'     => "Olá, {nome}.\n\nO documento \"{documento}\" da solicitação {protocolo} foi recusado pelo motivo:\n\n{comentario}\n\nReenvie apenas esse item pela sua área:\n{link_portal}",
			),
			'message_client'          => array(
				'label'    => __( 'Nova mensagem da equipe (cliente)', 'eb-credito-rural' ),
				'audience' => 'cliente',
				'subject'  => 'Nova mensagem — {protocolo}',
				'body'     => "Olá, {nome}.\n\nVocê recebeu uma nova mensagem sobre a solicitação {protocolo}:\n\n{mensagem}\n\nResponda pela sua área:\n{link_portal}",
			),
			'message_admin'           => array(
				'label'    => __( 'Nova mensagem do cliente (administração)', 'eb-credito-rural' ),
				'audience' => 'admin',
				'subject'  => 'Mensagem do cliente — {protocolo}',
				'body'     => "{nome} enviou uma mensagem na solicitação {protocolo}:\n\n{mensagem}\n\nPainel: {link_portal}",
			),
			'client_responded'        => array(
				'label'    => __( 'Cliente respondeu pendência (administração)', 'eb-credito-rural' ),
				'audience' => 'admin',
				'subject'  => 'Pendência respondida — {protocolo}',
				'body'     => "{nome} enviou o documento \"{documento}\" na solicitação {protocolo}.\n\nPainel: {link_portal}",
			),
			'pending_reminder_client' => array(
				'label'    => __( 'Lembrete de pendência (cliente)', 'eb-credito-rural' ),
				'audience' => 'cliente',
				'subject'  => 'Lembrete: pendências na solicitação {protocolo}',
				'body'     => "Olá, {nome}.\n\nAinda há itens pendentes na sua solicitação {protocolo}:\n\n{pendencias}\n\nEnvie pela sua área para não atrasar a análise:\n{link_portal}",
			),
			'pending_reminder_admin'  => array(
				'label'    => __( 'Pendências sem resposta (administração)', 'eb-credito-rural' ),
				'audience' => 'admin',
				'subject'  => 'Pendências paradas — {protocolo}',
				'body'     => "A solicitação {protocolo} ({nome}) tem pendências sem resposta:\n\n{pendencias}\n\nPainel: {link_portal}",
			),
			'certificates_expiring'   => array(
				'label'    => __( 'Certidões a vencer (administração)', 'eb-credito-rural' ),
				'audience' => 'admin',
				'subject'  => 'Certidões a vencer nos próximos dias',
				'body'     => "Os documentos abaixo vencem em breve:\n\n{pendencias}\n\nPainel: {link_portal}",
			),
			'assigned'                => array(
				'label'    => __( 'Solicitação atribuída ao analista', 'eb-credito-rural' ),
				'audience' => 'admin',
				'subject'  => 'Solicitação {protocolo} atribuída a você',
				'body'     => "A solicitação {protocolo} ({nome}) foi atribuída a você.\n\nPainel: {link_portal}",
			),
			'lgpd_request'            => array(
				'label'    => __( 'Solicitação LGPD do titular (administração)', 'eb-credito-rural' ),
				'audience' => 'admin',
				'subject'  => 'Solicitação LGPD — {nome}',
				'body'     => "{nome} registrou uma solicitação de direitos do titular:\n\n{mensagem}\n\nAtenda dentro do prazo legal. Painel: {link_portal}",
			),
			'password_reset'          => array(
				'label'    => __( 'Recuperação de senha (cliente)', 'eb-credito-rural' ),
				'audience' => 'cliente',
				'subject'  => 'Redefinição de senha — {site}',
				'body'     => "Olá, {nome}.\n\nRecebemos um pedido para redefinir a senha da sua conta. Para criar uma nova senha, acesse:\n\n{link_confirmacao}\n\nSe você não fez este pedido, ignore esta mensagem; sua senha continuará a mesma.",
			),
			'esign_code'              => array(
				'label'    => __( 'Assinatura eletrônica — código de confirmação (cliente); use {codigo}', 'eb-credito-rural' ),
				'audience' => 'cliente',
				'subject'  => 'Seu código para assinar: {documento} — {protocolo}',
				'body'     => "Olá, {nome}.\n\nPara concluir a assinatura eletrônica do documento \"{documento}\" da solicitação {protocolo}, informe o código abaixo na sua área do produtor:\n\n{codigo}\n\nO código vale por 10 minutos e só pode ser usado uma vez. Se você não pediu esta assinatura, ignore esta mensagem e, se preferir, altere sua senha.",
			),
			'crm_task_reminder'       => array(
				'label'    => __( 'Lembrete de tarefas do CRM (equipe)', 'eb-credito-rural' ),
				'audience' => 'admin',
				'subject'  => 'Suas tarefas do CRM para hoje — {site}',
				'body'     => "Olá, {nome}.\n\nEstas tarefas do CRM vencem hoje ou já venceram e ainda não foram concluídas:\n\n{pendencias}\n\nAbra o CRM para concluir ou reagendar:\n{link_portal}",
			),
			'two_factor_code'         => array(
				'label'    => __( 'Verificação em duas etapas — código de acesso (equipe); use {codigo}', 'eb-credito-rural' ),
				'audience' => 'admin',
				'subject'  => 'Seu código de verificação — {site}',
				'body'     => "Olá, {nome}.\n\nSeu código para confirmar o acesso ao painel é:\n\n{codigo}\n\nEle vale por 10 minutos e só pode ser usado uma vez. Se você não tentou entrar agora, troque sua senha e avise o administrador do site.",
			),
		);
	}

	/**
	 * Placeholders documentados.
	 *
	 * @return array
	 */
	public static function placeholders() {
		return array(
			'{codigo}'           => __( 'Código numérico (verificação em duas etapas / assinatura eletrônica)', 'eb-credito-rural' ),
			'{nome}'             => __( 'Nome do cliente', 'eb-credito-rural' ),
			'{protocolo}'        => __( 'Protocolo da solicitação', 'eb-credito-rural' ),
			'{status}'           => __( 'Status atual', 'eb-credito-rural' ),
			'{comentario}'       => __( 'Comentário visível ao cliente', 'eb-credito-rural' ),
			'{link_portal}'      => __( 'Link para a área do cliente (ou painel, nos e-mails da administração)', 'eb-credito-rural' ),
			'{pendencias}'       => __( 'Lista de pendências', 'eb-credito-rural' ),
			'{data}'             => __( 'Data/hora do evento', 'eb-credito-rural' ),
			'{site}'             => __( 'Nome do site', 'eb-credito-rural' ),
			'{valor}'            => __( 'Valor solicitado', 'eb-credito-rural' ),
			'{documento}'        => __( 'Nome do documento', 'eb-credito-rural' ),
			'{mensagem}'         => __( 'Texto da mensagem', 'eb-credito-rural' ),
			'{link_confirmacao}' => __( 'Link de confirmação/redefinição', 'eb-credito-rural' ),
		);
	}
}
