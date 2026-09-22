<?php
/**
 * Esquema das tabelas próprias (dbDelta).
 *
 * @package EBCR
 */

namespace EBCR\Install;

use EBCR\Database\Db;

defined( 'ABSPATH' ) || exit;

/**
 * Definição das tabelas.
 */
final class Schema {

	/**
	 * Instruções CREATE TABLE no formato aceito pelo dbDelta.
	 *
	 * @return string[]
	 */
	public static function statements() {
		global $wpdb;
		$c   = $wpdb->get_charset_collate();
		$t   = static function ( $name ) {
			return Db::table( $name );
		};
		$sql = array();

		$sql[] = "CREATE TABLE {$t('submissions')} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  public_id char(36) NOT NULL,
  protocol varchar(24) DEFAULT NULL,
  user_id bigint(20) unsigned NOT NULL,
  status varchar(40) NOT NULL DEFAULT 'rascunho',
  assigned_to bigint(20) unsigned DEFAULT NULL,
  person_type varchar(2) DEFAULT NULL,
  requested_amount decimal(15,2) DEFAULT NULL,
  purpose varchar(40) DEFAULT NULL,
  term_months int(11) DEFAULT NULL,
  current_step tinyint(4) NOT NULL DEFAULT 1,
  duplicated_from bigint(20) unsigned DEFAULT NULL,
  submitted_at datetime DEFAULT NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  deleted_at datetime DEFAULT NULL,
  anonymized_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY public_id (public_id),
  UNIQUE KEY protocol (protocol),
  KEY user_id (user_id),
  KEY status (status),
  KEY assigned_to (assigned_to),
  KEY submitted_at (submitted_at)
) $c;";

		$sql[] = "CREATE TABLE {$t('submission_data')} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  submission_id bigint(20) unsigned NOT NULL,
  section varchar(40) NOT NULL,
  data longtext NOT NULL,
  encrypted tinyint(1) NOT NULL DEFAULT 0,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY submission_section (submission_id,section)
) $c;";

		$sql[] = "CREATE TABLE {$t('properties')} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  submission_id bigint(20) unsigned NOT NULL,
  name varchar(190) NOT NULL DEFAULT '',
  city varchar(120) NOT NULL DEFAULT '',
  uf char(2) NOT NULL DEFAULT '',
  registration_number varchar(60) NOT NULL DEFAULT '',
  registry_office varchar(190) NOT NULL DEFAULT '',
  total_area decimal(12,2) DEFAULT NULL,
  usable_area decimal(12,2) DEFAULT NULL,
  car_code varchar(60) NOT NULL DEFAULT '',
  ccir varchar(60) NOT NULL DEFAULT '',
  nirf varchar(60) NOT NULL DEFAULT '',
  sigef varchar(80) NOT NULL DEFAULT '',
  tenure varchar(20) NOT NULL DEFAULT 'propria',
  lease_end date DEFAULT NULL,
  sort_order int(11) NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY submission_id (submission_id)
) $c;";

		$sql[] = "CREATE TABLE {$t('guarantees')} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  submission_id bigint(20) unsigned NOT NULL,
  type varchar(40) NOT NULL,
  description text,
  property_id bigint(20) unsigned DEFAULT NULL,
  declared_value decimal(15,2) DEFAULT NULL,
  appraised_value decimal(15,2) DEFAULT NULL,
  ltv decimal(6,2) DEFAULT NULL,
  formalization_status varchar(40) NOT NULL DEFAULT 'pendente',
  extra longtext,
  sort_order int(11) NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY submission_id (submission_id)
) $c;";

		$sql[] = "CREATE TABLE {$t('documents')} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  public_id char(36) NOT NULL,
  submission_id bigint(20) unsigned NOT NULL,
  user_id bigint(20) unsigned NOT NULL,
  doc_type varchar(60) NOT NULL,
  ref_key varchar(60) NOT NULL DEFAULT '',
  request_id bigint(20) unsigned DEFAULT NULL,
  original_name varchar(255) NOT NULL,
  stored_name varchar(80) NOT NULL,
  storage_dir varchar(80) NOT NULL,
  mime varchar(100) NOT NULL,
  size bigint(20) unsigned NOT NULL DEFAULT 0,
  sha256 char(64) NOT NULL DEFAULT '',
  encrypted tinyint(1) NOT NULL DEFAULT 0,
  review_status varchar(20) NOT NULL DEFAULT 'pendente',
  review_note text,
  reviewed_by bigint(20) unsigned DEFAULT NULL,
  reviewed_at datetime DEFAULT NULL,
  expires_at date DEFAULT NULL,
  uploaded_at datetime NOT NULL,
  deleted_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY public_id (public_id),
  KEY submission_id (submission_id),
  KEY user_id (user_id),
  KEY sha256 (sha256),
  KEY expires_at (expires_at)
) $c;";

		$sql[] = "CREATE TABLE {$t('document_requests')} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  submission_id bigint(20) unsigned NOT NULL,
  doc_type varchar(60) NOT NULL,
  label varchar(190) NOT NULL,
  note text,
  requested_by bigint(20) unsigned NOT NULL,
  requested_at datetime NOT NULL,
  fulfilled_document_id bigint(20) unsigned DEFAULT NULL,
  fulfilled_at datetime DEFAULT NULL,
  reminded_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY submission_id (submission_id)
) $c;";

		$sql[] = "CREATE TABLE {$t('status_history')} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  submission_id bigint(20) unsigned NOT NULL,
  from_status varchar(40) DEFAULT NULL,
  to_status varchar(40) NOT NULL,
  changed_by bigint(20) unsigned DEFAULT NULL,
  comment_internal text,
  comment_client text,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY submission_id (submission_id)
) $c;";

		$sql[] = "CREATE TABLE {$t('messages')} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  submission_id bigint(20) unsigned NOT NULL,
  author_id bigint(20) unsigned NOT NULL,
  visibility varchar(10) NOT NULL DEFAULT 'cliente',
  body text NOT NULL,
  read_at datetime DEFAULT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY submission_id (submission_id)
) $c;";

		$sql[] = "CREATE TABLE {$t('crm_contacts')} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  phone varchar(30) NOT NULL DEFAULT '',
  whatsapp varchar(30) NOT NULL DEFAULT '',
  lead_source varchar(60) NOT NULL DEFAULT '',
  tags varchar(255) NOT NULL DEFAULT '',
  owner_id bigint(20) unsigned DEFAULT NULL,
  stage varchar(40) NOT NULL DEFAULT 'novo',
  next_action varchar(255) NOT NULL DEFAULT '',
  next_action_at datetime DEFAULT NULL,
  notes text,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY user_id (user_id),
  KEY owner_id (owner_id),
  KEY stage (stage)
) $c;";

		$sql[] = "CREATE TABLE {$t('crm_activities')} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  contact_id bigint(20) unsigned NOT NULL,
  submission_id bigint(20) unsigned DEFAULT NULL,
  type varchar(30) NOT NULL DEFAULT 'tarefa',
  description text NOT NULL,
  due_at datetime DEFAULT NULL,
  done_at datetime DEFAULT NULL,
  created_by bigint(20) unsigned DEFAULT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY contact_id (contact_id),
  KEY due_at (due_at)
) $c;";

		$sql[] = "CREATE TABLE {$t('consents')} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  submission_id bigint(20) unsigned DEFAULT NULL,
  policy_key varchar(40) NOT NULL,
  policy_version varchar(20) NOT NULL,
  policy_hash char(64) NOT NULL,
  accepted_at datetime NOT NULL,
  ip varchar(45) NOT NULL DEFAULT '',
  user_agent varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  KEY user_id (user_id),
  KEY submission_id (submission_id)
) $c;";

		$sql[] = "CREATE TABLE {$t('audit_log')} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  actor_id bigint(20) unsigned DEFAULT NULL,
  action varchar(60) NOT NULL,
  object_type varchar(40) NOT NULL DEFAULT '',
  object_id varchar(60) NOT NULL DEFAULT '',
  ip varchar(45) NOT NULL DEFAULT '',
  meta longtext,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY actor_id (actor_id),
  KEY action (action),
  KEY created_at (created_at)
) $c;";

		$sql[] = "CREATE TABLE {$t('checks')} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  submission_id bigint(20) unsigned NOT NULL,
  check_key varchar(60) NOT NULL,
  result varchar(12) NOT NULL DEFAULT 'na',
  note text,
  checked_by bigint(20) unsigned DEFAULT NULL,
  checked_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY submission_check (submission_id,check_key)
) $c;";

		$sql[] = "CREATE TABLE {$t('mail_queue')} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  event varchar(60) NOT NULL DEFAULT '',
  recipient varchar(190) NOT NULL,
  subject varchar(255) NOT NULL,
  body longtext NOT NULL,
  headers text,
  attachments text,
  attempts tinyint(4) NOT NULL DEFAULT 0,
  status varchar(12) NOT NULL DEFAULT 'pending',
  last_error text,
  scheduled_at datetime NOT NULL,
  sent_at datetime DEFAULT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY status_scheduled (status,scheduled_at)
) $c;";

		return $sql;
	}

	/**
	 * Nomes (sufixos) de todas as tabelas.
	 *
	 * @return string[]
	 */
	public static function tables() {
		return array( 'submissions', 'submission_data', 'properties', 'guarantees', 'documents', 'document_requests', 'status_history', 'messages', 'crm_contacts', 'crm_activities', 'consents', 'audit_log', 'checks', 'mail_queue' );
	}

	/**
	 * Cria/atualiza as tabelas.
	 *
	 * @return void
	 */
	public static function install() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( self::statements() as $statement ) {
			dbDelta( $statement );
		}
	}
}
