<?php
/**
 *
 * @created      30.09.2021
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2021 smiley
 * @license      MIT
 */

/**
 * @var \chillerlan\Database\Database $db
 * @var \codemasher\TERFBLOCKER5000\TERFBLOCKER5000Options $options
 * @var \Psr\Log\LoggerInterface $logger
 */

require_once __DIR__.'/common.php';

$logger->info('creating dbstorage tables...');

$db->connect();

// token table
$db->drop->table($options->table_token)->ifExists()->query();

$db->create
	->table($options->table_token)
	->ifNotExists()
	->primaryKey('user_id')
	->bigint('user_id', 20)
	->tinytext('screen_name', null, true)
	->text('token', null, true)
	->query();
