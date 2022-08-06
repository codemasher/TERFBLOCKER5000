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

require_once __DIR__.'/../cron/common.php';

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


// to-scan table
$db->drop->table($options->table_scan_jobs)->ifExists()->query();

$db->create
	->table($options->table_scan_jobs)
	->ifNotExists()
	->primaryKey('scan_id')
	->field('scan_id', 'SERIAL', 10, null, null, false)
	->bigint('id', 20)
	->varchar('screen_name', 32, null, true)
	->tinyint('finished', 1, 0, false)
	->query();

$db->raw(sprintf('CREATE UNIQUE INDEX id_u ON %s(id);', $options->table_scan_jobs));


// profiles table
$db->drop->table($options->table_profiles)->ifExists()->query();
$db->raw('DROP FUNCTION profiles_update_updated');

$db->create
	->table($options->table_profiles)
	->ifNotExists()
	->primaryKey('id')
	->bigint('id', 20)
	->tinytext('screen_name', null, true)
	->text('name', null, true)
	->text('description', null, true)
	->text('location', null, true)
	->int('followers_count', 10, 0, false)
	->int('friends_count', 10, 0, false)
	->int('created_at', 10, 0, false)
	->tinyint('verified', 1, 0, false)
	->field('updated', 'TIMESTAMP', null, null, null, null, 'CURRENT_TIMESTAMP')
	->query();

$db->raw('CREATE FUNCTION profiles_update_updated()
RETURNS TRIGGER LANGUAGE plpgsql AS
$$BEGIN
    NEW.updated = now();
    RETURN NEW;
END;$$;');

$db->raw('CREATE TRIGGER profiles_updated
BEFORE UPDATE
ON public.'.$options->table_profiles.'
FOR EACH ROW
EXECUTE FUNCTION public.profiles_update_updated();');

// full text search will slow down immensely at a certain table size for whatever reason
#$db->raw(sprintf('ALTER TABLE `%s` ADD FULLTEXT(`description`);', $options->table_profiles));


// block list
$db->drop->table($options->table_blocklist)->ifExists()->query();

$db->create
	->table($options->table_blocklist)
	->ifNotExists()
	->primaryKey('id')
	->bigint('id', 20)
	->query();


// always block list
$db->drop->table($options->table_block_always)->ifExists()->query();

$db->create
	->table($options->table_block_always)
	->ifNotExists()
	->primaryKey('id')
	->bigint('id', 20)
	->query();


// never block list
$db->drop->table($options->table_block_never)->ifExists()->query();

$db->create
	->table($options->table_block_never)
	->ifNotExists()
	->primaryKey('id')
	->bigint('id', 20)
	->query();


// error log
$db->drop->table($options->table_log)->ifExists()->query();
$db->raw('DROP TYPE loglevels;');

$db->raw('CREATE TYPE loglevels AS ENUM (\''.implode("','", ['DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY']).'\')');

$db->create
	->table($options->table_log)
	->ifNotExists()
	->primaryKey('id')
	->field('id', 'BIGSERIAL', 20, null, null, false)
	->tinytext('channel')
	->field('level_name', 'loglevels')
	->text('message')
	->text('context', null, true)
	->text('extra', null, true)
	->int('datetime', 10, null, false)
	->query();
