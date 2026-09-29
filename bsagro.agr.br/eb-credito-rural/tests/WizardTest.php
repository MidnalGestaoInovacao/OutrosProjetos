<?php
/**
 * Validação das etapas e matriz de documentos.
 *
 * @package EBCR
 */

use EBCR\Domain\Status;
use EBCR\Forms\DocumentMatrix;
use EBCR\Forms\Steps;
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
}
