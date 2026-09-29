<?php
/**
 * Exceção usada nos testes para capturar negativas do controlador de download.
 *
 * @package EBCR
 */

namespace EBCR\Files;

defined( 'ABSPATH' ) || exit;

/**
 * Código HTTP no getCode().
 */
final class DownloadDenied extends \RuntimeException {}
