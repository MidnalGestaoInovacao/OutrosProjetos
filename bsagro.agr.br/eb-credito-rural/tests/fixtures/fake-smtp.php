<?php
/**
 * Servidor SMTP mínimo para os testes (sem TLS): EHLO, AUTH LOGIN/PLAIN, MAIL, RCPT, DATA, RSET, QUIT.
 * Uso: php fake-smtp.php <porta> <arquivo-json> <conexoes> <senha-esperada>
 * Grava cada sessão (credenciais recebidas, MAIL FROM, RCPT, DATA) em JSON e cria <arquivo-json>.ready ao abrir a porta.
 *
 * @package EBCR
 */

// phpcs:disable -- utilitário de teste fora do plugin distribuído.
$port     = (int) $argv[1];
$log      = $argv[2];
$max      = isset( $argv[3] ) ? (int) $argv[3] : 1;
$expected = isset( $argv[4] ) ? $argv[4] : '';
$srv      = @stream_socket_server( "tcp://127.0.0.1:$port", $errno, $errstr );
if ( ! $srv ) {
	fwrite( STDERR, "$errstr\n" );
	exit( 1 );
}
file_put_contents( $log . '.ready', '1' );
for ( $n = 0; $n < $max; $n++ ) {
	$c = @stream_socket_accept( $srv, 60 );
	if ( ! $c ) {
		break;
	}
	$s     = array( 'auth' => array(), 'mail_from' => '', 'rcpt' => array(), 'data' => '' );
	$state = '';
	fwrite( $c, "220 fake.local ESMTP\r\n" );
	while ( ( $line = fgets( $c ) ) !== false ) {
		$l = rtrim( $line, "\r\n" );
		if ( 'data' === $state ) {
			if ( '.' === $l ) {
				$state = '';
				fwrite( $c, "250 2.0.0 OK queued\r\n" );
			} else {
				$s['data'] .= $l . "\n";
			}
			continue;
		}
		if ( 'user' === $state ) {
			$s['auth'][] = base64_decode( $l );
			$state       = 'pass';
			fwrite( $c, "334 UGFzc3dvcmQ6\r\n" );
			continue;
		}
		if ( 'pass' === $state ) {
			$pass        = base64_decode( $l );
			$s['auth'][] = $pass;
			$state       = '';
			fwrite( $c, ( '' === $expected || $pass === $expected ) ? "235 2.7.0 Authentication successful\r\n" : "535 5.7.8 Authentication failed\r\n" );
			continue;
		}
		$u = strtoupper( $l );
		if ( 0 === strpos( $u, 'EHLO' ) ) {
			fwrite( $c, "250-fake.local\r\n250-AUTH LOGIN PLAIN\r\n250 8BITMIME\r\n" );
		} elseif ( 0 === strpos( $u, 'HELO' ) ) {
			fwrite( $c, "250 fake.local\r\n" );
		} elseif ( 0 === strpos( $u, 'AUTH LOGIN' ) ) {
			$state = 'user';
			fwrite( $c, "334 VXNlcm5hbWU6\r\n" );
		} elseif ( 0 === strpos( $u, 'MAIL FROM' ) ) {
			$s['mail_from'] = $l;
			fwrite( $c, "250 2.1.0 OK\r\n" );
		} elseif ( 0 === strpos( $u, 'RCPT TO' ) ) {
			$s['rcpt'][] = $l;
			fwrite( $c, "250 2.1.5 OK\r\n" );
		} elseif ( 'DATA' === $u ) {
			$state = 'data';
			fwrite( $c, "354 End data with <CR><LF>.<CR><LF>\r\n" );
		} elseif ( 'QUIT' === $u ) {
			fwrite( $c, "221 2.0.0 Bye\r\n" );
			break;
		} elseif ( 'RSET' === $u || 'NOOP' === $u ) {
			fwrite( $c, "250 OK\r\n" );
		} else {
			fwrite( $c, "502 5.5.2 Not implemented\r\n" );
		}
	}
	fclose( $c );
	$all   = is_file( $log ) ? (array) json_decode( (string) file_get_contents( $log ), true ) : array();
	$all[] = $s;
	file_put_contents( $log, json_encode( $all ) );
}
