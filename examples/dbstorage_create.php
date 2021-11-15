<?php
/**
 * @created      30.09.2021
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2021 smiley
 * @license      MIT
 *
 * @noinspection SqlResolve
 */

/**
 * @var \chillerlan\Database\Database $db
 * @var \codemasher\TERFBLOCKER5000\TERFBLOCKER5000Options $options
 * @var \Psr\Log\LoggerInterface $logger
 */

use Psr\Log\LogLevel;

require_once __DIR__.'/../cron/common.php';

$logger->info('creating dbstorage tables...');

$db->connect();

// token table
#$db->drop->table($options->table_token)->ifExists()->query();

$db->create
	->table($options->table_token)
	->ifNotExists()
	->primaryKey('user_id')
	->bigint('user_id', 20)
	->tinytext('screen_name', null, true)
	->text('token', null, true)
	->query();


// to-scan table
#$db->drop->table($options->table_scan_jobs)->ifExists()->query();

$db->create
	->table($options->table_scan_jobs)
	->ifNotExists()
	->primaryKey('scan_id')
	->int('scan_id', 10, null, false, 'UNSIGNED AUTO_INCREMENT')
	->varchar('screen_name', 32, null, true)
	->tinyint('finished', 1, 0, false, 'UNSIGNED')
	->query();

$db->raw(sprintf('ALTER TABLE `%s` ADD UNIQUE(`screen_name`);', $options->table_scan_jobs));


// profiles table
#$db->drop->table($options->table_profiles)->ifExists()->query();

$db->create
	->table($options->table_profiles)
	->ifNotExists()
	->primaryKey('id')
	->bigint('id', 20)
	->tinytext('screen_name', null, true)
	->text('name', null, true)
	->text('description', null, true)
	->text('location', null, true)
	->int('followers_count', 10, null, false, 'UNSIGNED')
	->int('friends_count', 10, null, false, 'UNSIGNED')
	->int('created_at', 10, null, false, 'UNSIGNED')
	->tinyint('verified', 1, 0, false, 'UNSIGNED')
	->field('updated', 'TIMESTAMP', null, 'ON UPDATE CURRENT_TIMESTAMP', null, null, 'CURRENT_TIMESTAMP')
	->query();


// block list
#$db->drop->table($options->table_blocklist)->ifExists()->query();

$db->create
	->table($options->table_blocklist)
	->ifNotExists()
	->primaryKey('id')
	->bigint('id', 20)
	->query();


// always block list
#$db->drop->table($options->table_block_always)->ifExists()->query();

$db->create
	->table($options->table_block_always)
	->ifNotExists()
	->primaryKey('id')
	->bigint('id', 20)
	->query();


// never block list
#$db->drop->table($options->table_block_never)->ifExists()->query();

$db->create
	->table($options->table_block_never)
	->ifNotExists()
	->primaryKey('id')
	->bigint('id', 20)
	->query();


// error log
#$db->drop->table($options->table_log)->ifExists()->query();

$db->create
	->table($options->table_log)
	->ifNotExists()
	->primaryKey('id')
	->bigint('id', 20, null, false, 'UNSIGNED AUTO_INCREMENT')
	->enum('level', [
		LogLevel::DEBUG,
		LogLevel::INFO,
		LogLevel::NOTICE,
		LogLevel::WARNING,
		LogLevel::ERROR,
		LogLevel::CRITICAL,
		LogLevel::ALERT,
		LogLevel::EMERGENCY,
	])
	->text('message', null, true)
	->text('context', null, true)
	->int('time', 10, null, false, 'UNSIGNED')
	->query();
