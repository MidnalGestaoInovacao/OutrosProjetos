<?php
/**
 * Gerador de PDF (Writer) e dossiê do comitê.
 *
 * Como não há ferramenta externa de PDF garantida no ambiente, os testes incluem um parser mínimo que confere a
 * estrutura do arquivo (cabeçalho, xref/startxref, offsets dos objetos, /Length dos streams) e descomprime os
 * streams para procurar os textos (em Windows-1252, como o Writer grava).
 *
 * @package EBCR
 */

use EBCR\Database\CheckRepository;
use EBCR\Database\MessageRepository;
use EBCR\Database\StatusHistoryRepository;
use EBCR\Database\SubmissionRepository;
use EBCR\Domain\Consent;
use EBCR\Domain\Status;
use EBCR\Forms\DocumentMatrix;
use EBCR\Forms\Wizard;
use EBCR\Pdf\Writer;
use EBCR\Reports\Dossier;
use EBCR\Roles\Capabilities;

/**
 * Writer e Dossier.
 */
final class DossierTest extends EBCR_TestCase {

	/**
	 * Converte UTF-8 em Windows-1252 (codificação usada nos streams).
	 *
	 * @param string $utf8 Texto.
	 * @return string
	 */
	private function cp1252( $utf8 ) {
		return iconv( 'UTF-8', 'Windows-1252', $utf8 );
	}

	/**
	 * Parser mínimo: valida cabeçalho, trailer, xref/startxref, offsets e /Length; devolve os streams descomprimidos
	 * concatenados na ordem dos objetos (que é a ordem das páginas).
	 *
	 * @param string $pdf Bytes.
	 * @return string Conteúdo dos streams.
	 */
	private function parse_pdf( $pdf ) {
		$this->assertStringStartsWith( '%PDF-', $pdf );
		$this->assertSame( '%%EOF', substr( rtrim( $pdf ), -5 ) );
		$this->assertSame( 1, preg_match( '/startxref\s+(\d+)\s+%%EOF\s*$/', $pdf, $m ), 'startxref presente' );
		$xref = (int) $m[1];
		$this->assertSame( 1, preg_match( '/^xref\n0 (\d+)\n/', substr( $pdf, $xref, 40 ), $h ), 'startxref aponta para a tabela xref' );
		$count   = (int) $h[1];
		$start   = $xref + strlen( $h[0] );
		$objects = 0;
		for ( $i = 0; $i < $count; $i++ ) {
			$entry = substr( $pdf, $start + 20 * $i, 20 );
			$this->assertSame( 1, preg_match( '/^(\d{10}) (\d{5}) ([nf]) \n$/', $entry, $e ), "entrada xref {$i} com 20 bytes" );
			if ( 'n' === $e[3] ) {
				$this->assertSame( "{$i} 0 obj", substr( $pdf, (int) $e[1], strlen( "{$i} 0 obj" ) ), "offset do objeto {$i}" );
				++$objects;
			} else {
				$this->assertSame( 0, $i, 'apenas o objeto 0 é livre' );
			}
		}
		$this->assertGreaterThanOrEqual( 7, $objects );
		$this->assertSame( 1, preg_match( '/trailer << (.*?) >>\nstartxref/s', $pdf, $t ) );
		$this->assertStringContainsString( '/Root 1 0 R', $t[1] );
		$this->assertStringContainsString( '/Info 5 0 R', $t[1] );
		$this->assertStringContainsString( '/Size ' . $count, $t[1] );
		// Streams: recorta exatamente /Length bytes e confere o "endstream".
		$this->assertSame( 1, preg_match_all( '/<< \/Length (\d+)( \/Filter \/FlateDecode)? >>\nstream\n/', $pdf, $streams, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) >= 1 ? 1 : 0, 'há streams' );
		$text = '';
		foreach ( $streams as $s ) {
			$len     = (int) $s[1][0];
			$offset  = $s[0][1] + strlen( $s[0][0] );
			$content = substr( $pdf, $offset, $len );
			$this->assertSame( "\nendstream", substr( $pdf, $offset + $len, 10 ), '/Length confere com o fim do stream' );
			if ( ! empty( $s[2][0] ) ) {
				$content = gzuncompress( $content );
				$this->assertNotFalse( $content, 'stream FlateDecode descomprime' );
			}
			$text .= $content . "\n";
		}
		return $text;
	}

	/**
	 * Extrai os literais dos operadores Tj (sem escapes), um por linha.
	 *
	 * @param string $streams Conteúdo dos streams.
	 * @return string[]
	 */
	private function tj_lines( $streams ) {
		preg_match_all( '/\(((?:[^()\\\\]|\\\\.)*)\) Tj/s', $streams, $m );
		return array_map(
			static function ( $s ) {
				return preg_replace( '/\\\\(.)/s', '$1', $s );
			},
			$m[1]
		);
	}

	/**
	 * Quantidade de páginas (objetos /Type /Page).
	 *
	 * @param string $pdf Bytes.
	 * @return int
	 */
	private function page_count( $pdf ) {
		$this->assertSame( 1, preg_match( '/\/Type \/Pages \/Kids \[[^\]]*\] \/Count (\d+)/', $pdf, $m ) );
		$this->assertSame( (int) $m[1], preg_match_all( '/\/Type \/Page \/Parent 2 0 R/', $pdf ) );
		return (int) $m[1];
	}

	public function test_writer_generates_valid_pdf_with_accents_fonts_and_metadata(): void {
		$pdf = ( new Writer( 'Título — Dossiê' ) )
			->h1( 'Relatório' )
			->h2( 'Seção' )
			->p( 'Parágrafo com ação, coração e ç; (parênteses) e \\ barra invertida.' )
			->kv(
				array(
					'Chave'  => 'Valor com ç',
					'Lista'  => array( 'primeiro', 'segundo' ),
					'Vazio'  => '',
				)
			)
			->page_break()
			->p( 'Segunda página' )
			->output();
		$streams = $this->parse_pdf( $pdf );
		$lines   = $this->tj_lines( $streams );
		$joined  = implode( "\n", $lines );

		// Fontes padrão sem embutir, com WinAnsiEncoding; A4; metadados Unicode.
		$this->assertStringContainsString( '/BaseFont /Helvetica /Encoding /WinAnsiEncoding', $pdf );
		$this->assertStringContainsString( '/BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding', $pdf );
		$this->assertStringNotContainsString( '/FontFile', $pdf );
		$this->assertStringContainsString( '/MediaBox [0 0 595.28 841.89]', $pdf );
		$this->assertSame( 1, preg_match( '/\/Title <FEFF([0-9A-F]+)>/', $pdf, $m ) );
		$this->assertSame( 'Título — Dossiê', mb_convert_encoding( hex2bin( $m[1] ), 'UTF-8', 'UTF-16BE' ) );
		$this->assertSame( 1, preg_match( '/\/Producer <FEFF([0-9A-F]+)>/', $pdf, $m ) );
		$this->assertSame( 'EB Crédito Rural', mb_convert_encoding( hex2bin( $m[1] ), 'UTF-8', 'UTF-16BE' ) );
		$this->assertSame( 1, preg_match( '/\/CreationDate \(D:\d{14}Z\)/', $pdf ) );
		$this->assertStringContainsString( '/Filter /FlateDecode', $pdf );

		// Textos com acentos em Windows-1252 e escapes de ( ) \ no stream bruto.
		$this->assertStringContainsString( $this->cp1252( 'Relatório' ), $joined );
		$this->assertStringContainsString( $this->cp1252( 'Seção' ), $joined );
		$this->assertStringContainsString( $this->cp1252( 'coração' ), $joined );
		$this->assertStringContainsString( $this->cp1252( 'Valor com ç' ), $joined );
		$this->assertStringContainsString( "primeiro\nsegundo", $joined, 'array vira linhas' );
		$this->assertStringContainsString( $this->cp1252( '—' ), $joined, 'valor vazio vira travessão' );
		$this->assertStringContainsString( '\\(par', $streams );
		$this->assertStringContainsString( 'nteses\\)', $streams );
		$this->assertStringContainsString( '\\\\ barra', $streams );

		// Cabeçalho com o título e rodapé com paginação, data e confidencialidade em todas as páginas.
		$this->assertSame( 2, $this->page_count( $pdf ) );
		$this->assertSame( 2, substr_count( $joined, $this->cp1252( 'Título — Dossiê' ) ) );
		$this->assertStringContainsString( $this->cp1252( 'Página 1 de 2' ), $joined );
		$this->assertStringContainsString( $this->cp1252( 'Página 2 de 2' ), $joined );
		$this->assertSame( 2, substr_count( $joined, 'Confidencial' ) );
		$this->assertSame( 2, preg_match_all( '/Gerado em \d{2}\/\d{2}\/\d{4} \d{2}:\d{2}/', $joined ) );
		$this->assertStringContainsString( $this->cp1252( 'Segunda página' ), $joined );
	}

	public function test_writer_long_table_spans_pages_and_repeats_header(): void {
		$rows = array();
		for ( $i = 1; $i <= 150; $i++ ) {
			$rows[] = array( "Linha {$i}", 'Descrição ' . str_repeat( 'palavra ', $i % 9 ), 'R$ ' . number_format( $i * 1000, 2, ',', '.' ), 0 === $i % 10 ? "várias\nlinhas" : 'ok' );
		}
		$pdf     = ( new Writer( 'Tabela longa' ) )->h1( 'Tabela' )->table( array( 'Coluna Alfa', 'Coluna Beta', 'Valor', 'Obs' ), $rows )->output();
		$streams = $this->parse_pdf( $pdf );
		$lines   = $this->tj_lines( $streams );
		$joined  = implode( "\n", $lines );
		$pages   = $this->page_count( $pdf );

		$this->assertGreaterThanOrEqual( 3, $pages );
		$this->assertStringContainsString( $this->cp1252( 'Página 2 de ' . $pages ), $joined );
		$this->assertStringContainsString( $this->cp1252( 'Página ' . $pages . ' de ' . $pages ), $joined );
		$this->assertSame( $pages, substr_count( $joined, 'Coluna Alfa' ), 'cabeçalho da tabela repetido em cada página' );
		$this->assertSame( 150, preg_match_all( '/^Linha \d+$/m', $joined ), 'todas as linhas presentes' );
		$this->assertStringContainsString( "Linha 150", $joined );
		$this->assertStringContainsString( $this->cp1252( "várias\nlinhas" ), $joined, 'quebra de linha dentro da célula' );
		// Ordem preservada: Linha 1 antes de Linha 150.
		$this->assertLessThan( strpos( $joined, "Linha 150\n" ), strpos( $joined, "Linha 1\n" ) );
	}

	public function test_writer_wraps_long_words_and_drops_characters_outside_cp1252(): void {
		$code = str_repeat( 'GO-5218805-1A2B.3C4D.5E6F.7A8B.', 6 );
		$pdf  = ( new Writer( 'Quebra' ) )->p( 'Início 漢字 ✓ fim' )->table( array( 'A', 'B' ), array( array( 'x', $code ) ) )->output();
		$lines = $this->tj_lines( $this->parse_pdf( $pdf ) );
		$joined = implode( "\n", $lines );
		$this->assertStringContainsString( $this->cp1252( 'Início' ), $joined );
		$this->assertStringContainsString( 'fim', $joined );
		$this->assertStringNotContainsString( "\xE6\xBC\xA2", $joined, 'UTF-8 cru não vai para o stream' );
		// Palavra sem espaços maior que a coluna é partida em várias linhas, sem perder caracteres.
		$parts = array_filter(
			$lines,
			static function ( $l ) {
				return 0 === strpos( $l, 'GO-5218805' ) || false !== strpos( $l, '.3C4D.' ) || false !== strpos( $l, '7A8B.GO' );
			}
		);
		$this->assertGreaterThan( 1, count( $parts ) );
		$this->assertStringContainsString( $code, str_replace( "\n", '', $joined ) );
		foreach ( $lines as $l ) {
			$this->assertLessThan( 140, strlen( $l ), 'nenhuma linha ultrapassa a largura útil' );
		}
	}

	public function test_dossier_contains_protocol_client_document_label_and_internal_data(): void {
		$client  = $this->make_user();
		$analyst = $this->make_user( Capabilities::ROLE_ANALYST );
		$s       = $this->make_submission( $client );
		$w       = new Wizard();
		list( $ok, $errors ) = $w->handle_step( $client, $s, 1, array( 'person_type' => 'PF', 'nome' => 'Maria da Silva', 'cpf' => '529.982.247-25', 'rg' => '123', 'rg_orgao' => 'SSP/GO', 'nascimento' => '1985-05-05', 'estado_civil' => 'casado', 'regime_bens' => 'comunhao_parcial', 'conjuge_nome' => 'João Pereira', 'conjuge_cpf' => '111.444.777-35', 'cep' => '74000-000', 'logradouro' => 'Rua das Flores', 'numero' => '10', 'cidade' => 'Goiânia', 'uf' => 'GO', 'telefone' => '(62) 99999-9999', 'pep' => 'nao' ) );
		$this->assertTrue( $ok, wp_json_encode( $errors ) );
		list( $ok, $errors ) = $w->handle_step( $client, $s, 2, array( 'imoveis' => array( array( 'name' => 'Fazenda Boa Vista', 'city' => 'Rio Verde', 'uf' => 'GO', 'registration_number' => '12345', 'registry_office' => 'CRI de Rio Verde', 'total_area' => '350', 'usable_area' => '300', 'car_code' => 'GO-5218805-1A2B.3C4D.5E6F.7A8B.9C0D.1E2F.3A4B.5C6D', 'tenure' => 'propria' ) ) ) );
		$this->assertTrue( $ok, wp_json_encode( $errors ) );
		list( $ok, $errors ) = $w->handle_step( $client, $s, 3, array( 'atividades' => array( 'graos', 'pecuaria_corte' ), 'plano_safra' => 'Soja 200 ha e milho safrinha', 'contratos_venda' => 'nao', 'seguro_rural' => 'sim', 'seguro_tipo' => 'Seguro de produtividade', 'irrigacao' => 'nao', 'licenca_ambiental' => 'na', 'area_cultura' => array( array( 'cultura' => 'Soja', 'area_ha' => '200' ) ), 'produtividade' => array( array( 'safra' => '2024/25', 'cultura' => 'Soja', 'valor' => '62', 'unidade' => 'sc/ha' ) ), 'rebanho' => array( array( 'categoria' => 'matrizes', 'quantidade' => '120' ) ) ) );
		$this->assertTrue( $ok, wp_json_encode( $errors ) );
		list( $ok, $errors ) = $w->handle_step( $client, $s, 4, array( 'receita_ano1' => '900000', 'receita_ano2' => '700000', 'valor_solicitado' => '500000', 'finalidade' => 'custeio', 'prazo_meses' => '12', 'epoca_pagamento' => 'safra', 'dividas' => array( array( 'credor' => 'Banco Rural', 'saldo' => '150000', 'parcela' => '5000', 'periodicidade' => 'mensal', 'vencimento' => '2030-01-01', 'garantia' => 'Penhor' ) ) ) );
		$this->assertTrue( $ok, wp_json_encode( $errors ) );
		list( $ok, $errors ) = $w->handle_step( $client, $s, 5, array( 'garantias' => array( array( 'type' => 'penhor_agricola', 'description' => 'Safra de soja 2025/26', 'declared_value' => '800000' ) ) ) );
		$this->assertTrue( $ok, wp_json_encode( $errors ) );
		$doc = $this->upload_pdf( $client, $s, 'comprovante_residencia' );

		// Protocolo único por execução (a coluna é UNIQUE e o banco de testes não é limpo entre execuções).
		$protocol = 'DS-' . strtoupper( wp_generate_password( 8, false, false ) );
		$repo     = new SubmissionRepository();
		$this->assertTrue( $repo->update( (int) $s['id'], array( 'status' => Status::CREDIT, 'protocol' => $protocol, 'submitted_at' => current_time( 'mysql', true ), 'assigned_to' => $analyst, 'fund' => 'geral' ) ) );
		( new StatusHistoryRepository() )->add( (int) $s['id'], Status::SUBMITTED, Status::PRE_ANALYSIS, $analyst, 'Parecer interno: documentação consistente.', 'Sua solicitação avançou.' );
		( new CheckRepository() )->save( (int) $s['id'], 'area', 'ok', 'Confere com o CAR e a matrícula', $analyst );
		( new CheckRepository() )->save( (int) $s['id'], 'dividas', 'alerta', 'SCR pendente', $analyst );
		( new MessageRepository() )->add( (int) $s['id'], $analyst, 'Nota interna: verificar matrícula atualizada.', 'interno' );
		( new MessageRepository() )->add( (int) $s['id'], $analyst, 'Mensagem enviada ao cliente.', 'cliente' );
		Consent::record( $client, array( 'privacidade', 'scr' ), (int) $s['id'] );
		$s = $repo->find( (int) $s['id'] );

		$pdf = Dossier::build( $s );
		if ( getenv( 'EBCR_DOSSIER_OUT' ) ) {
			file_put_contents( getenv( 'EBCR_DOSSIER_OUT' ), $pdf ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		$streams = $this->parse_pdf( $pdf );
		$lines   = $this->tj_lines( $streams );
		$flat    = implode( ' ', $lines );

		$this->assertGreaterThanOrEqual( 2, $this->page_count( $pdf ), 'capa + seções' );
		$this->assertStringContainsString( $protocol, $flat );
		$this->assertSame( 1, preg_match( '/\/Title <FEFF([0-9A-F]+)>/', $pdf, $m ) );
		$this->assertStringContainsString( $protocol . ' — Maria da Silva', mb_convert_encoding( hex2bin( $m[1] ), 'UTF-8', 'UTF-16BE' ) );
		$this->assertStringContainsString( 'Maria da Silva', $flat );
		$this->assertStringContainsString( '529.982.247-25', $flat, 'CPF completo no dossiê' );
		$this->assertStringContainsString( $this->cp1252( 'João Pereira' ), $flat );
		$this->assertStringContainsString( $this->cp1252( Status::label( Status::CREDIT ) ), $flat );
		$this->assertStringContainsString( $this->cp1252( DocumentMatrix::label( 'comprovante_residencia' ) ), $flat, 'rótulo do documento' );
		$this->assertStringContainsString( 'comprovante.pdf', $flat, 'nome original do arquivo' );
		$this->assertStringNotContainsString( $doc['stored_name'], $flat, 'sem caminhos internos' );
		$this->assertStringNotContainsString( $doc['storage_dir'], $flat, 'sem caminhos internos' );
		$this->assertStringContainsString( 'Fazenda Boa Vista', $flat );
		$this->assertStringContainsString( 'Rio Verde/GO', $flat );
		$this->assertStringContainsString( 'GO-5218805-1A2B.3C4D.5E6F.7A8B.9C0D.1E2F.3A4B.5C6D', str_replace( ' ', '', $flat ) );
		$this->assertStringContainsString( 'R$ 500.000,00', $flat );
		$this->assertStringContainsString( 'R$ 800.000,00', $flat );
		$this->assertStringContainsString( '1,60x', $flat, 'relação garantia/valor solicitado' );
		$this->assertStringContainsString( 'R$ 800.000,00', $flat, 'receita média (900k + 700k) / 2' );
		$this->assertStringContainsString( '7,5%', $flat, 'comprometimento = 60.000 / 800.000' );
		$this->assertStringContainsString( 'Banco Rural', $flat );
		$this->assertStringContainsString( $this->cp1252( 'Pecuária de corte' ), $flat );
		$this->assertStringContainsString( 'Matrizes', $flat );
		$this->assertStringContainsString( 'Seguro de produtividade', $flat );
		$this->assertStringContainsString( $this->cp1252( 'Confere com o CAR e a matrícula' ), $flat );
		$this->assertStringContainsString( 'SCR pendente', $flat );
		$this->assertStringContainsString( $this->cp1252( 'Parecer interno: documentação consistente.' ), $flat );
		$this->assertStringContainsString( $this->cp1252( 'Nota interna: verificar matrícula atualizada.' ), $flat );
		$this->assertStringNotContainsString( 'Mensagem enviada ao cliente.', $flat, 'mensagens ao cliente ficam fora do dossiê' );
		$this->assertStringContainsString( $this->cp1252( Consent::policies()['scr']['title'] ), $flat );
		$this->assertStringContainsString( 'Carteira geral', $flat );
		$this->assertStringContainsString( $this->cp1252( 'Uso interno — comitê de crédito' ), $flat );
		$this->assertStringContainsString( $this->cp1252( get_userdata( $analyst )->display_name ), $flat );
		$this->assertStringContainsString( '(62) 99999-9999', $flat );
		$this->assertStringContainsString( 'CEP 74000-000', $flat );
		$this->assertStringContainsString( 'Enviada ' . $this->cp1252( '»' ) . ' ' . $this->cp1252( Status::label( Status::PRE_ANALYSIS ) ), $flat );
		$this->assertStringNotContainsString( 'FALTAN DO', $flat, 'colunas estreitas não partem palavras' );
		$this->assertStringContainsString( 'FALTANDO', $flat );
	}

	public function test_dossier_of_empty_draft_is_still_a_valid_pdf(): void {
		$client = $this->make_user();
		$s      = $this->make_submission( $client );
		$pdf    = Dossier::build( $s );
		$flat   = implode( ' ', $this->tj_lines( $this->parse_pdf( $pdf ) ) );
		$this->assertStringContainsString( 'Rascunho', $flat );
		$this->assertStringContainsString( $this->cp1252( 'Nenhum imóvel informado.' ), $flat );
		$this->assertStringContainsString( $this->cp1252( CheckRepository::defaults()['area'] ), $flat );
	}
}
