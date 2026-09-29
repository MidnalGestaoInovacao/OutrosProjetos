<?php
/**
 * Validadores de formato.
 *
 * @package EBCR
 */

use EBCR\Forms\Validators\Rules;
use EBCR\Forms\Validators\Validator;

/**
 * CPF, CNPJ, CAR, CEP, telefone, datas, valores.
 */
final class ValidatorsTest extends EBCR_TestCase {

	public function test_cpf(): void {
		$this->assertTrue( Rules::cpf( '529.982.247-25' ) );
		$this->assertTrue( Rules::cpf( '52998224725' ) );
		$this->assertFalse( Rules::cpf( '111.111.111-11' ) );
		$this->assertFalse( Rules::cpf( '529.982.247-26' ) );
		$this->assertFalse( Rules::cpf( '1234567' ) );
	}

	public function test_cnpj(): void {
		$this->assertTrue( Rules::cnpj( '11.222.333/0001-81' ) );
		$this->assertTrue( Rules::cnpj( '11222333000181' ) );
		$this->assertFalse( Rules::cnpj( '11.111.111/1111-11' ) );
		$this->assertFalse( Rules::cnpj( '11.222.333/0001-82' ) );
	}

	public function test_car(): void {
		$this->assertTrue( Rules::car( 'GO-5201405-1A2B.3C4D.5E6F.7A8B.9C0D.1E2F.3A4B.5C6D' ) );
		$this->assertTrue( Rules::car( 'mg-3106200-abcd.abcd.abcd.abcd.abcd.abcd.abcd.abcd' ) );
		$this->assertFalse( Rules::car( 'XX-5201405-1A2B.3C4D.5E6F.7A8B.9C0D.1E2F.3A4B.5C6D' ) );
		$this->assertFalse( Rules::car( 'GO-520140-1A2B.3C4D.5E6F.7A8B.9C0D.1E2F.3A4B.5C6D' ) );
		$this->assertFalse( Rules::car( 'GO-5201405-1A2B.3C4D' ) );
	}

	public function test_cep_phone_email_uf(): void {
		$this->assertTrue( Rules::cep( '74000-000' ) );
		$this->assertFalse( Rules::cep( '7400' ) );
		$this->assertTrue( Rules::phone( '(62) 99923-2488' ) );
		$this->assertTrue( Rules::phone( '6233334444' ) );
		$this->assertFalse( Rules::phone( '999' ) );
		$this->assertFalse( Rules::phone( '11111111111' ) );
		$this->assertTrue( Rules::email( 'a@b.co' ) );
		$this->assertFalse( Rules::email( 'x' ) );
		$this->assertTrue( Rules::uf( 'go' ) );
		$this->assertFalse( Rules::uf( 'ZZ' ) );
	}

	public function test_dates_and_amounts(): void {
		$this->assertSame( '2000-02-29', Rules::date( '29/02/2000' ) );
		$this->assertNull( Rules::date( '31/02/2001' ) );
		$this->assertTrue( Rules::min_age( '1990-01-01', 18 ) );
		$this->assertFalse( Rules::min_age( gmdate( 'Y-m-d', strtotime( '-10 years' ) ), 18 ) );
		$this->assertTrue( Rules::future( gmdate( 'Y-m-d', strtotime( '+1 day' ) ) ) );
		$this->assertFalse( Rules::future( '2000-01-01' ) );
		$this->assertSame( 1234.56, Rules::amount( '1.234,56' ) );
		$this->assertSame( 1234.56, Rules::amount( '1234.56' ) );
		$this->assertNull( Rules::amount( '-5' ) );
		$this->assertNull( Rules::amount( '10', 20, null ) );
	}

	public function test_validator_engine(): void {
		list( $clean, $errors ) = Validator::run(
			array(
				'cpf'  => array( 'label' => 'CPF', 'rules' => array( 'required', 'cpf' ), 'type' => 'cpf' ),
				'nome' => array( 'label' => 'Nome', 'rules' => array( 'required', 'min:5' ) ),
				'uf'   => array( 'label' => 'UF', 'rules' => array( 'uf' ), 'type' => 'upper' ),
			),
			array( 'cpf' => '529.982.247-25', 'nome' => 'Jo', 'uf' => 'go' )
		);
		$this->assertSame( '52998224725', $clean['cpf'] );
		$this->assertArrayHasKey( 'nome', $errors );
		$this->assertSame( 'GO', $clean['uf'] );
	}
}
