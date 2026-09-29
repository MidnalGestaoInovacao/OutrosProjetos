<?php
/**
 * Validadores de formato.
 *
 * @package EBCR
 */

use EBCR\Admin\Settings\Settings;
use EBCR\Forms\Steps;
use EBCR\Forms\Validators\Rules;
use EBCR\Forms\Validators\Validator;
use EBCR\Support\Color;
use EBCR\Support\Options;

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

	public function test_color_parse_sanitize_and_contrast(): void {
		$this->assertSame( '#d4af37', Color::sanitize( '#D4AF37' ) );
		$this->assertSame( '#aabbcc', Color::sanitize( '#abc' ) );
		$this->assertSame( 'rgba(23,27,36,0.15)', Color::sanitize( 'rgba(23,27,36,.15)' ) );
		$this->assertSame( 'rgba(23,27,36,0.15)', Color::sanitize( 'rgb(23 27 36 / 15%)' ) );
		$this->assertSame( '#0d3527', Color::sanitize( 'rgb(13, 53, 39)' ) );
		$this->assertSame( 'transparent', Color::sanitize( 'transparent' ) );
		foreach ( array( 'red;}body{', 'url(x)', '#12', 'var(--x)', 'expression(alert(1))', '' ) as $bad ) {
			$this->assertSame( '', Color::sanitize( $bad ), $bad );
		}
		$this->assertEqualsWithDelta( 21.0, Color::contrast( '#000000', '#ffffff' ), 0.01 );
		$this->assertEqualsWithDelta( 1.0, Color::contrast( '#ffffff', '#ffffff' ), 0.01 );
		$this->assertEqualsWithDelta( 4.54, Color::contrast( '#767676', '#ffffff' ), 0.02 );
		$this->assertLessThan( 4.5, Color::contrast( '#b8860b', '#ffffff' ), 'dourado #b8860b não atinge AA sobre branco' );
		$this->assertGreaterThan( 4.5, Color::contrast( '#fbf9f4', '#0d3527' ), 'papel sobre oliva (BS Agro)' );
		$this->assertTrue( Color::is_dark( '#0d3527' ) );
		$this->assertFalse( Color::is_dark( '#d4af37' ) );
		$this->assertSame( '#808080', Color::mix( '#000000', '#ffffff', 0.5 ) );
	}

	public function test_settings_sanitize_new_types(): void {
		list( $v, $e ) = Settings::sanitize(
			array(
				'brand_primary'                  => 'red;}body{',
				'brand_bg'                       => '',
				'brand_border'                   => 'rgba(0,0,0,.2)',
				'brand_font_body'                => 'Roboto; } body { color:red <script>',
				'brand_font_urls'                => "http://inseguro.example/a.css\nhttps://cdn.jsdelivr.net/npm/@fontsource/roboto@5/latin-400.css\njavascript:alert(1)",
				'brand_radius'                   => 500,
				'brand_preset'                   => 'inexistente',
				'guarantees_mode'                => 'xyz',
				'guarantees_required_modalities' => 'custeio, nao_existe',
				'assets_mode'                    => 'optional',
				'client_area_slug'               => 'Área do Cliente!',
				'client_area_menu_location'      => 'local-que-nao-existe',
				'legal_page_titular'             => '12abc',
				'document_matrix'                => array( array( 'key' => 'docs_garantia', 'label' => 'Docs', 'condition' => 'guarantee', 'required' => 'sim' ) ),
			)
		);
		$this->assertSame( '#d4af37', $v['brand_primary'], 'cor inválida mantém a atual' );
		$this->assertSame( '', $v['brand_bg'], 'fundo pode ficar vazio (transparente)' );
		$this->assertSame( 'rgba(0,0,0,0.2)', $v['brand_border'] );
		$this->assertStringNotContainsString( ';', $v['brand_font_body'] );
		$this->assertStringNotContainsString( '{', $v['brand_font_body'] );
		$this->assertStringNotContainsString( '<', $v['brand_font_body'] );
		$this->assertSame( 'https://cdn.jsdelivr.net/npm/@fontsource/roboto@5/latin-400.css', $v['brand_font_urls'], 'só https' );
		$this->assertSame( 40, $v['brand_radius'] );
		$this->assertSame( 'custom', $v['brand_preset'] );
		$this->assertSame( 'required', $v['guarantees_mode'] );
		$this->assertSame( array( 'custeio' ), $v['guarantees_required_modalities'] );
		$this->assertSame( 'optional', $v['assets_mode'] );
		$this->assertSame( 'area-do-cliente', $v['client_area_slug'] );
		$this->assertSame( '', $v['client_area_menu_location'] );
		$this->assertSame( 12, $v['legal_page_titular'] );
		$this->assertSame( 'guarantee', $v['document_matrix'][0]['condition'], 'matriz sanitizada (método estático) aceita a nova condição' );
		$this->assertNotEmpty( $e );
		$this->assertNotEmpty( array_filter( $e, static function ( $m ) { return false !== strpos( $m, 'brand_primary' ); } ) );
		$this->assertNotEmpty( array_filter( $e, static function ( $m ) { return false !== strpos( $m, 'nao_existe' ); } ) );
		// Modalidades aceitam lista.
		list( $v ) = Settings::sanitize( array( 'guarantees_required_modalities' => array( 'investimento', 'custeio' ) ) );
		$this->assertSame( array( 'investimento', 'custeio' ), $v['guarantees_required_modalities'] );
		$this->assertSame( array_keys( Steps::options( 'purposes' ) ), array_keys( Settings::fields( 'garantias' )['guarantees_required_modalities'][3] ) );
	}
}
