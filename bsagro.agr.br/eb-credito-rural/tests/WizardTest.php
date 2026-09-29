<?php
/**
 * Validação das etapas e matriz de documentos.
 *
 * @package EBCR
 */

use EBCR\Domain\Status;
use EBCR\Forms\DocumentMatrix;
use EBCR\Forms\Steps;
use EBCR\Forms\SubmissionRules;
use EBCR\Forms\Wizard;
use EBCR\Security\MathCaptcha;
use EBCR\Support\Options;

/**
 * Fluxo completo até o envio.
 */
final class WizardTest extends EBCR_TestCase {

	/**
	 * Entrada válida da etapa 1 (PF casado).
	 *
	 * @return array
	 */
	private function step1_input() {
		return array( 'person_type' => 'PF', 'nome' => 'Maria da Silva', 'cpf' => '529.982.247-25', 'rg' => '123', 'rg_orgao' => 'SSP/GO', 'nascimento' => '1985-05-05', 'estado_civil' => 'casado', 'regime_bens' => 'comunhao_parcial', 'conjuge_nome' => 'João', 'conjuge_cpf' => '111.444.777-35', 'cep' => '74000-000', 'logradouro' => 'Rua A', 'numero' => '1', 'cidade' => 'Goiânia', 'uf' => 'GO', 'telefone' => '(62) 99999-9999', 'pep' => 'nao' );
	}

	public function test_step1_requires_spouse_when_married_and_validates_cpf(): void {
		list( , $errors ) = Steps::validate( 1, array( 'person_type' => 'PF', 'estado_civil' => 'casado', 'cpf' => '111.111.111-11', 'pep' => 'sim' ) );
		foreach ( array( 'nome', 'cpf', 'conjuge_cpf', 'regime_bens', 'cep', 'pep_detalhes', 'telefone' ) as $f ) {
			$this->assertArrayHasKey( $f, $errors, $f );
		}
		list( $clean, $errors ) = Steps::validate( 1, $this->step1_input() );
		$this->assertSame( array(), $errors );
		$this->assertTrue( $clean['married'] );
		$this->assertSame( '52998224725', $clean['cpf'] );
	}

	public function test_step2_lease_requires_future_end_date(): void {
		$row = array( 'name' => 'Fazenda', 'city' => 'Rio Verde', 'uf' => 'GO', 'registration_number' => '123', 'registry_office' => 'CRI', 'total_area' => '100', 'usable_area' => '80', 'car_code' => 'GO-5218805-1A2B.3C4D.5E6F.7A8B.9C0D.1E2F.3A4B.5C6D', 'tenure' => 'arrendada', 'lease_end' => '2000-01-01' );
		list( , $errors ) = Steps::validate( 2, array( 'imoveis' => array( $row ) ) );
		$this->assertArrayHasKey( 'imoveis.0.lease_end', $errors );
		$row['lease_end'] = gmdate( 'Y-m-d', strtotime( '+2 years' ) );
		$row['usable_area'] = '120';
		list( , $errors ) = Steps::validate( 2, array( 'imoveis' => array( $row ) ) );
		$this->assertArrayHasKey( 'imoveis.0.usable_area', $errors );
		list( , $errors ) = Steps::validate( 2, array( 'imoveis' => array() ) );
		$this->assertArrayHasKey( 'imoveis', $errors );
	}

	public function test_step4_limits_and_indicators(): void {
		Options::update( array( 'min_amount' => 1000, 'max_amount' => 5000, 'min_term_months' => 6, 'max_term_months' => 60 ) );
		list( , $errors ) = Steps::validate( 4, array( 'receita_ano1' => '100', 'valor_solicitado' => '9999', 'finalidade' => 'custeio', 'prazo_meses' => '3', 'epoca_pagamento' => 'safra' ) );
		$this->assertArrayHasKey( 'valor_solicitado', $errors );
		$this->assertArrayHasKey( 'prazo_meses', $errors );
		list( $clean, $errors ) = Steps::validate( 4, array( 'receita_ano1' => '100000', 'receita_ano2' => '50000', 'valor_solicitado' => '2000', 'finalidade' => 'custeio', 'prazo_meses' => '12', 'epoca_pagamento' => 'safra', 'dividas' => array( array( 'credor' => 'Banco', 'saldo' => '30000', 'parcela' => '1000', 'periodicidade' => 'mensal', 'vencimento' => '2030-01-01' ) ) ) );
		$this->assertSame( array(), $errors );
		$this->assertSame( 75000.0, $clean['_indicadores']['receita_media'] );
		$this->assertSame( 12000.0, $clean['_indicadores']['parcelas_anuais'] );
		$this->assertSame( 16.0, $clean['_indicadores']['comprometimento'] );
	}

	public function test_document_matrix_slots_follow_context(): void {
		$slots = DocumentMatrix::slots( array( 'person_type' => 'PF', 'married' => true, 'properties' => array( array( 'id' => 5, 'name' => 'A', 'tenure' => 'arrendada' ), array( 'id' => 6, 'name' => 'B', 'tenure' => 'propria' ) ), 'livestock' => true, 'insurance' => false, 'environmental' => false, 'real_guarantee' => true ) );
		$types = array_map( static function ( $s ) { return $s['type'] . '|' . $s['ref_key']; }, $slots );
		$this->assertContains( 'identidade|', $types );
		$this->assertContains( 'certidao_casamento|', $types );
		$this->assertContains( 'matricula|p:5', $types );
		$this->assertContains( 'matricula|p:6', $types );
		$this->assertContains( 'contrato_arrendamento|p:5', $types );
		$this->assertNotContains( 'contrato_arrendamento|p:6', $types );
		$this->assertContains( 'rebanho_gta|', $types );
		$this->assertNotContains( 'apolice_seguro|', $types );
		$this->assertContains( 'laudo_avaliacao|', $types );
		$this->assertNotContains( 'contrato_social|', $types );
		$pj = DocumentMatrix::slots( array( 'person_type' => 'PJ' ) );
		$this->assertContains( 'contrato_social', wp_list_pluck( $pj, 'type' ) );
		$this->assertNotContains( 'identidade', wp_list_pluck( $pj, 'type' ) );
	}

	public function test_full_flow_submit_requires_documents_and_consents(): void {
		$a = $this->make_user();
		$s = $this->make_submission( $a );
		$w = new Wizard();
		list( $ok ) = $w->handle_step( $a, $s, 1, $this->step1_input() );
		$this->assertTrue( $ok );
		list( $ok ) = $w->handle_step( $a, $s, 2, array( 'imoveis' => array( array( 'name' => 'Fazenda', 'city' => 'Rio Verde', 'uf' => 'GO', 'registration_number' => '123', 'registry_office' => 'CRI', 'total_area' => '100', 'usable_area' => '80', 'car_code' => 'GO-5218805-1A2B.3C4D.5E6F.7A8B.9C0D.1E2F.3A4B.5C6D', 'tenure' => 'propria' ) ) ) );
		$this->assertTrue( $ok );
		list( $ok, $errors ) = $w->handle_step( $a, $s, 3, array( 'atividades' => array( 'graos' ), 'plano_safra' => 'Soja 80 ha', 'contratos_venda' => 'nao', 'seguro_rural' => 'nao', 'irrigacao' => 'nao', 'licenca_ambiental' => 'na' ) );
		$this->assertTrue( $ok, wp_json_encode( $errors ) );
		list( $ok ) = $w->handle_step( $a, $s, 4, array( 'receita_ano1' => '900000', 'valor_solicitado' => '500000', 'finalidade' => 'custeio', 'prazo_meses' => '12', 'epoca_pagamento' => 'safra' ) );
		$this->assertTrue( $ok );
		list( $ok ) = $w->handle_step( $a, $s, 5, array( 'garantias' => array( array( 'type' => 'penhor_agricola', 'description' => 'Safra de soja', 'declared_value' => '800000' ) ) ) );
		$this->assertTrue( $ok );
		$s = ( new \EBCR\Database\SubmissionRepository() )->find( (int) $s['id'] );
		$this->assertSame( 'PF', $s['person_type'] );
		$this->assertSame( 500000.0, (float) $s['requested_amount'] );
		$errors = $w->validate_all( $s );
		$this->assertArrayHasKey( 6, $errors, 'documentos obrigatórios faltando' );
		// Envia todos os obrigatórios.
		foreach ( $w->document_slots( $s )['slots'] as $slot ) {
			if ( $slot['required'] ) {
				$path = $this->tmp_file( $this->pdf_bytes( wp_rand( 1, 9999 ) ) );
				$row  = ( new \EBCR\Files\UploadHandler() )->handle( $a, $s, $this->files_item( $path, 'doc.pdf' ), $slot['type'], $slot['ref_key'] );
				$this->assertIsArray( $row, is_wp_error( $row ) ? $row->get_error_message() : '' );
			}
		}
		$this->assertSame( array(), $w->validate_all( $s ) );
		// Sem aceites → erro; com aceites → enviada com protocolo.
		$r = $w->submit( $a, $s, array() );
		$this->assertSame( 'consents', $r->get_error_code() );
		$consents = array();
		foreach ( \EBCR\Domain\Consent::for_moment( 'submit' ) as $k => $p ) {
			$consents[ 'consent_' . $k ] = '1';
		}
		$r = $w->submit( $a, $s, $consents );
		$this->assertIsArray( $r, is_wp_error( $r ) ? $r->get_error_message() : '' );
		$this->assertSame( Status::SUBMITTED, $r['status'] );
		$this->assertMatchesRegularExpression( '/^EB-\d{4}-\d{6}$/', $r['protocol'] );
		$this->assertNotEmpty( ( new \EBCR\Database\ConsentRepository() )->for_submission( (int) $r['id'] ) );
		$this->assertGreaterThan( 0, ( new \EBCR\Database\MailQueueRepository() )->stats()['pending'] + ( new \EBCR\Database\MailQueueRepository() )->stats()['sent'] );
		// Depois de enviada, cliente não edita mais.
		list( $ok ) = $w->handle_step( $a, $r, 1, $this->step1_input() );
		$this->assertFalse( $ok );
	}

	// ------------------------------------------------------------------ 1.3.0: bens e garantias

	/**
	 * Imóvel válido.
	 *
	 * @return array
	 */
	private function property_row() {
		return array( 'name' => 'Fazenda', 'city' => 'Rio Verde', 'uf' => 'GO', 'registration_number' => '123', 'registry_office' => 'CRI', 'total_area' => '100', 'usable_area' => '80', 'car_code' => 'GO-5218805-1A2B.3C4D.5E6F.7A8B.9C0D.1E2F.3A4B.5C6D', 'tenure' => 'propria' );
	}

	/**
	 * Preenche as etapas 1, 3 e 4 (e a 2, se ativa) de uma solicitação.
	 *
	 * @param int    $user    Usuário.
	 * @param array  $s       Solicitação.
	 * @param string $purpose Finalidade.
	 * @return array Solicitação atualizada.
	 */
	private function fill_until_step4( $user, array $s, $purpose = 'custeio' ) {
		$w    = new Wizard();
		$repo = new \EBCR\Database\SubmissionRepository();
		list( $ok, $e ) = $w->handle_step( $user, $s, 1, $this->step1_input() );
		$this->assertTrue( $ok, wp_json_encode( $e ) );
		$s = $repo->find( (int) $s['id'] );
		if ( Steps::is_active( 2, $w->rules_context( $s ) ) ) {
			list( $ok, $e ) = $w->handle_step( $user, $s, 2, array( 'imoveis' => array( $this->property_row() ) ) );
			$this->assertTrue( $ok, wp_json_encode( $e ) );
		}
		list( $ok, $e ) = $w->handle_step( $user, $s, 3, array( 'atividades' => array( 'graos' ), 'plano_safra' => 'Soja 80 ha', 'contratos_venda' => 'nao', 'seguro_rural' => 'nao', 'irrigacao' => 'nao', 'licenca_ambiental' => 'na' ) );
		$this->assertTrue( $ok, wp_json_encode( $e ) );
		list( $ok, $e ) = $w->handle_step( $user, $s, 4, array( 'receita_ano1' => '900000', 'valor_solicitado' => '500000', 'finalidade' => $purpose, 'prazo_meses' => '12', 'epoca_pagamento' => 'safra' ) );
		$this->assertTrue( $ok, wp_json_encode( $e ) );
		return $repo->find( (int) $s['id'] );
	}

	public function test_guarantees_required_mode_is_the_default_and_rejects_no_guarantee(): void {
		$this->assertSame( 'required', SubmissionRules::guarantees_mode() );
		$this->assertSame( 'required', SubmissionRules::assets_mode() );
		$this->assertTrue( SubmissionRules::guarantees_required() );
		$this->assertTrue( Steps::is_active( 5 ) );
		list( , $errors ) = Steps::validate( 5, array() );
		$this->assertArrayHasKey( 'garantias', $errors );
		list( , $errors ) = Steps::validate( 5, array( 'oferece_garantia' => 'nao' ) );
		$this->assertArrayHasKey( 'garantias', $errors, 'no modo obrigatório "não tenho garantia" é recusado no servidor' );
		list( $clean, $errors ) = Steps::validate( 5, array( 'garantias' => array( array( 'type' => 'penhor_agricola', 'description' => 'Safra', 'declared_value' => '1000' ) ) ) );
		$this->assertSame( array(), $errors );
		$this->assertSame( 'sim', $clean['oferece_garantia'] );
		$this->assertSame( array( 1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7 ), Steps::numbers() );
	}

	public function test_guarantees_optional_mode_accepts_no_guarantee_and_skips_guarantee_documents(): void {
		Options::update( array( 'guarantees_mode' => 'optional' ) );
		$this->assertFalse( SubmissionRules::guarantees_required() );
		list( , $errors ) = Steps::validate( 5, array() );
		$this->assertArrayHasKey( 'oferece_garantia', $errors, 'a pergunta precisa ser respondida' );
		list( , $errors ) = Steps::validate( 5, array( 'oferece_garantia' => 'sim' ) );
		$this->assertArrayHasKey( 'garantias', $errors, '"sim" sem nenhuma garantia' );
		list( $clean, $errors ) = Steps::validate( 5, array( 'oferece_garantia' => 'nao', 'garantias' => array( array( 'type' => 'hipoteca', 'description' => '', 'declared_value' => '' ) ) ) );
		$this->assertSame( array(), $errors, '"não" descarta as linhas (mesmo incompletas)' );
		$this->assertSame( array(), $clean['garantias'] );
		$this->assertTrue( $clean['sem_garantia'] );

		$a = $this->make_user();
		$s = $this->fill_until_step4( $a, $this->make_submission( $a ) );
		$w = new Wizard();
		list( $ok, $errors ) = $w->handle_step( $a, $s, 5, array( 'oferece_garantia' => 'nao' ) );
		$this->assertTrue( $ok, wp_json_encode( $errors ) );
		$saved = $w->saved( $s );
		$this->assertSame( 'nao', $saved['garantias']['oferece_garantia'] );
		$this->assertSame( array(), $saved['garantias']['garantias'] );
		$types = wp_list_pluck( $w->document_slots( $s )['slots'], 'type' );
		$this->assertNotContains( 'laudo_avaliacao', $types, 'sem garantia real, sem laudo' );
		$this->assertArrayNotHasKey( 5, $w->validate_all( $s ) );
		$this->assertStringContainsString( 'não ter garantia', SubmissionRules::guarantees_empty_text( $saved['garantias'], $w->rules_context( $s ) ) );

		// Oferecendo garantia real, o laudo volta a ser pedido.
		list( $ok ) = $w->handle_step( $a, $s, 5, array( 'oferece_garantia' => 'sim', 'garantias' => array( array( 'type' => 'hipoteca', 'description' => 'Fazenda', 'declared_value' => '900000', 'property_id' => (string) $saved['imoveis']['imoveis'][0]['id'] ) ) ) );
		$this->assertTrue( $ok );
		$this->assertContains( 'laudo_avaliacao', wp_list_pluck( $w->document_slots( $s )['slots'], 'type' ) );
	}

	public function test_required_modalities_force_guarantee_in_optional_mode(): void {
		Options::update(
			array(
				'guarantees_mode'                => 'optional',
				'purposes'                       => "custeio|Custeio\ngarantia_imovel|Crédito com garantia de imóvel",
				'guarantees_required_modalities' => array( 'garantia_imovel' ),
			)
		);
		$this->assertFalse( SubmissionRules::guarantees_required( array( 'purpose' => 'custeio' ) ) );
		$this->assertTrue( SubmissionRules::guarantees_required( array( 'purpose' => 'garantia_imovel' ) ) );
		$this->assertSame( 'modality', SubmissionRules::guarantees_reason( array( 'purpose' => 'garantia_imovel' ) ) );
		list( , $errors ) = Steps::validate( 5, array( 'oferece_garantia' => 'nao' ), array( 'purpose' => 'garantia_imovel' ) );
		$this->assertArrayHasKey( 'garantias', $errors );

		$a = $this->make_user();
		$s = $this->fill_until_step4( $a, $this->make_submission( $a ), 'garantia_imovel' );
		$w = new Wizard();
		$this->assertSame( 'garantia_imovel', $w->rules_context( $s )['purpose'] );
		list( $ok, $errors ) = $w->handle_step( $a, $s, 5, array( 'oferece_garantia' => 'nao' ) );
		$this->assertFalse( $ok, 'finalidade exige garantia: requisição forjada com "não" é recusada' );
		$this->assertArrayHasKey( 'garantias', $errors );
	}

	public function test_guarantees_disabled_hides_step_documents_and_renumbers(): void {
		$a = $this->make_user();
		$s = $this->fill_until_step4( $a, $this->make_submission( $a ) );
		$w = new Wizard();
		// Em modo obrigatório, garantia real → laudo pedido.
		$saved = $w->saved( $s );
		list( $ok ) = $w->handle_step( $a, $s, 5, array( 'garantias' => array( array( 'type' => 'hipoteca', 'description' => 'Fazenda', 'declared_value' => '900000', 'property_id' => (string) $saved['imoveis']['imoveis'][0]['id'] ) ) ) );
		$this->assertTrue( $ok );
		$this->assertContains( 'laudo_avaliacao', wp_list_pluck( $w->document_slots( $s )['slots'], 'type' ) );

		Options::update( array( 'guarantees_mode' => 'disabled' ) );
		$ctx = $w->rules_context( $s );
		$this->assertFalse( Steps::is_active( 5, $ctx ) );
		$this->assertSame( 6, Steps::next( 4, $ctx ) );
		$this->assertSame( 4, Steps::prev( 6, $ctx ) );
		$this->assertSame( array( 1 => 1, 2 => 2, 3 => 3, 4 => 4, 6 => 5, 7 => 6 ), $w->step_numbers( $s ) );
		$this->assertNotContains( 'laudo_avaliacao', wp_list_pluck( $w->document_slots( $s )['slots'], 'type' ), 'desativado: documentos de garantia não são pedidos' );
		list( $ok, $errors ) = $w->handle_step( $a, $s, 5, array( 'garantias' => array() ) );
		$this->assertFalse( $ok, 'a etapa desativada não aceita envio' );
		$this->assertArrayHasKey( '_', $errors );
		$this->assertArrayNotHasKey( 5, $w->validate_all( $s ) );
		$this->assertStringContainsString( 'desativadas', SubmissionRules::guarantees_empty_text( array(), $ctx ) );

		// Nova solicitação: da etapa 4 vai direto à 6.
		$b  = $this->make_user();
		$s2 = $this->fill_until_step4( $b, $this->make_submission( $b ) );
		$this->assertSame( 6, (int) $s2['current_step'] );
		foreach ( $w->document_slots( $s2 )['slots'] as $slot ) {
			if ( $slot['required'] ) {
				$path = $this->tmp_file( $this->pdf_bytes( wp_rand( 1, 9999 ) ) );
				$row  = ( new \EBCR\Files\UploadHandler() )->handle( $b, $s2, $this->files_item( $path, 'doc.pdf' ), $slot['type'], $slot['ref_key'] );
				$this->assertIsArray( $row, is_wp_error( $row ) ? $row->get_error_message() : '' );
			}
		}
		$this->assertSame( array(), $w->validate_all( $s2 ) );
		$consents = array();
		foreach ( \EBCR\Domain\Consent::for_moment( 'submit' ) as $k => $p ) {
			$consents[ 'consent_' . $k ] = '1';
		}
		$r = $w->submit( $b, $s2, $consents );
		$this->assertIsArray( $r, is_wp_error( $r ) ? $r->get_error_message() : '' );
		$this->assertSame( Status::SUBMITTED, $r['status'] );
		// A ability de detalhe não quebra sem garantias.
		wp_set_current_user( $this->make_user( 'administrator' ) );
		$detail = \EBCR\Abilities\Abilities::get_submission( array( 'id' => $r['public_id'] ) );
		$this->assertIsArray( $detail );
		$this->assertSame( 0, $detail['guarantees']['count'] );
		$this->assertFalse( $detail['requirements']['guarantees_active'] );
		$this->assertSame( 'disabled', $detail['requirements']['guarantees_mode'] );
		$pdf = \EBCR\Reports\Dossier::build( $r );
		$this->assertStringStartsWith( '%PDF-', $pdf, 'dossiê gerado sem garantias' );
	}

	public function test_filter_ebcr_guarantees_required_overrides_configuration(): void {
		Options::update( array( 'guarantees_mode' => 'disabled' ) );
		$seen = array();
		$cb   = static function ( $required, $ctx ) use ( &$seen ) {
			$seen = $ctx;
			return isset( $ctx['amount'] ) && $ctx['amount'] >= 1000000 ? true : $required;
		};
		add_filter( 'ebcr_guarantees_required', $cb, 10, 2 );
		$this->assertFalse( SubmissionRules::guarantees_required( array( 'amount' => 500000.0 ) ) );
		$this->assertFalse( Steps::is_active( 5, array( 'amount' => 500000.0 ) ) );
		$this->assertTrue( SubmissionRules::guarantees_required( array( 'amount' => 2000000.0 ) ) );
		$this->assertTrue( Steps::is_active( 5, array( 'amount' => 2000000.0 ) ), 'o filtro reativa a etapa' );
		$this->assertSame( 'filter', SubmissionRules::guarantees_reason( array( 'amount' => 2000000.0 ) ) );
		$this->assertSame( 'disabled', $seen['mode'] );
		list( , $errors ) = Steps::validate( 5, array(), array( 'amount' => 2000000.0 ) );
		$this->assertArrayHasKey( 'garantias', $errors );
		remove_filter( 'ebcr_guarantees_required', $cb, 10 );
	}

	public function test_assets_modes_optional_and_disabled(): void {
		Options::update( array( 'assets_mode' => 'optional' ) );
		list( , $errors ) = Steps::validate( 2, array() );
		$this->assertArrayHasKey( 'possui_imoveis', $errors );
		list( $clean, $errors ) = Steps::validate( 2, array( 'possui_imoveis' => 'nao', 'imoveis' => array( array( 'tenure' => 'propria' ) ) ) );
		$this->assertSame( array(), $errors );
		$this->assertSame( array(), $clean['imoveis'] );
		list( , $errors ) = Steps::validate( 2, array( 'possui_imoveis' => 'sim' ) );
		$this->assertArrayHasKey( 'imoveis', $errors );

		Options::update( array( 'assets_mode' => 'disabled' ) );
		$this->assertFalse( Steps::is_active( 2 ) );
		// Sem imóveis, a garantia real não exige vínculo (descrição no texto).
		list( , $errors ) = Steps::validate( 5, array( 'garantias' => array( array( 'type' => 'hipoteca', 'description' => 'Matrícula 123, CRI de Rio Verde', 'declared_value' => '900000' ) ) ), array( 'properties_count' => 0 ) );
		$this->assertSame( array(), $errors );
		list( , $errors ) = Steps::validate( 5, array( 'garantias' => array( array( 'type' => 'hipoteca', 'description' => 'x', 'declared_value' => '900000' ) ) ) );
		$this->assertArrayHasKey( 'garantias.0.property_id', $errors, 'sem contexto, mantém a regra original' );

		$a = $this->make_user();
		$s = $this->make_submission( $a );
		$w = new Wizard();
		list( $ok ) = $w->handle_step( $a, $s, 1, $this->step1_input() );
		$this->assertTrue( $ok );
		$s = ( new \EBCR\Database\SubmissionRepository() )->find( (int) $s['id'] );
		$this->assertSame( 3, (int) $s['current_step'], 'pula a etapa de imóveis' );
		list( $ok, $errors ) = $w->handle_step( $a, $s, 2, array( 'imoveis' => array( $this->property_row() ) ) );
		$this->assertFalse( $ok );
		$types = wp_list_pluck( $w->document_slots( $s )['slots'], 'type' );
		$this->assertNotContains( 'matricula', $types );
		$this->assertNotContains( 'recibo_car', $types );
		$this->assertArrayNotHasKey( 2, $w->validate_all( $s ) );
	}

	public function test_document_matrix_guarantee_condition(): void {
		Options::update( array( 'document_matrix' => array_merge( DocumentMatrix::defaults(), array( array( 'key' => 'docs_garantia', 'label' => 'Documentos do bem em garantia', 'condition' => 'guarantee', 'required' => 'sim', 'validity_days' => 0, 'help' => '' ) ) ) ) );
		$this->assertArrayHasKey( 'guarantee', DocumentMatrix::conditions() );
		$this->assertContains( 'docs_garantia', wp_list_pluck( DocumentMatrix::slots( array( 'has_guarantee' => true ) ), 'type' ) );
		$this->assertNotContains( 'docs_garantia', wp_list_pluck( DocumentMatrix::slots( array( 'has_guarantee' => false ) ), 'type' ) );
		$this->assertContains( 'docs_garantia', wp_list_pluck( DocumentMatrix::slots( array( 'real_guarantee' => true ) ), 'type' ), 'garantia real implica garantia' );
	}
}
