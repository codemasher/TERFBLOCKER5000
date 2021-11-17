<?php
/**
 * @created      25.09.2021
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2021 smiley
 * @license      MIT
 */

use chillerlan\Database\{Database, MonologHandler};
use chillerlan\Database\Drivers\MySQLiDrv;
use chillerlan\DotEnv\DotEnv;
use chillerlan\HTTP\Psr18\CurlClient;
use chillerlan\SimpleCache\MemoryCache;
use codemasher\TERFBLOCKER5000\TERFBLOCKER5000;
use codemasher\TERFBLOCKER5000\TERFBLOCKER5000Options;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LogLevel;

// please for the love of the php goddess, disable error reporting on a public server
#error_reporting(0);
#ini_set('display_errors', '0');

/**
 * these vars are supposed to be set/changed before this file is included
 *
 * @var string $AUTOLOADER - path to an alternate autoloader
 * @var string $ENVFILE    - the name of the .env file in case it differs from the default
 * @var string $CFGDIR     - the directory where configuration is stored (.env, cacert, tokens)
 * @var string $LOGLEVEL   - log level for the test logger, use 'none' to suppress logging
 */
$ENVFILE  ??= '.env';
$CFGDIR   ??= __DIR__.'/../config';
$LOGLEVEL ??= 'info';

require_once $AUTOLOADER ?? __DIR__.'/../vendor/autoload.php';

ini_set('date.timezone', 'Europe/Amsterdam');

$env = (new DotEnv($CFGDIR, $ENVFILE, false))->load();

$options = new TERFBLOCKER5000Options([
	// DatabaseOptionsTrait
	'driver'              => MySQLiDrv::class,
	'host'                => $env->DB_HOST ?? 'localhost',
	'port'                => (int)($env->DB_PORT ?? 3306),
	'socket'              => $env->DB_SOCKET ?? '',
	'database'            => $env->DB_DATABASE,
	'username'            => $env->DB_USERNAME,
	'password'            => $env->DB_PASSWORD ?? '',
	// OAuthOptionsTrait
	'key'                 => $env->TWITTER_KEY ?? '',
	'secret'              => $env->TWITTER_SECRET ?? '',
	'callbackURL'         => $env->TWITTER_CALLBACK_URL ?? '',
	'sessionStart'        => true,
	'sessionTokenVar'     => 'terfblocker5000-token',
	// HTTPOptionsTrait
	'ca_info'             => realpath($CFGDIR.'/cacert.pem'), // https://curl.haxx.se/ca/cacert.pem
	'user_agent'          => 'TERFBLOCKER/5.0.0.0 +https://github.com/codemasher/TERFBLOCKER5000',
	// TERFBLOCKER5000Options
	'storageEncryption'   => true,
	'storageCryptoKey'    => $env->DB_CRYPTO_KEY,
	'$storageCryptoNonce' => $env->DB_CRYPTO_NONCE,
	'loglevel'            => $LOGLEVEL,
]);

// a log handler for STDOUT (or STDERR if you prefer)
$logHandler  = (new StreamHandler('php://stdout', $options->loglevel))
	->setFormatter((new LineFormatter(null, 'Y-m-d H:i:s', true, true))->setJsonPrettyPrint(true));
// a logger instance
$logger      = new Logger('log', [$logHandler]); // PSR-3
// a db instance with a clone (!) of the logger instance - we don't want the DB logger here
$db          = new Database($options, new MemoryCache, clone $logger);
// add the DB logger
$logger->pushHandler(new MonologHandler($db, $options->table_log, LogLevel::ERROR));
// invoke the rest
$http        = new CurlClient($options, null, $logger); // PSR-18
$terfblocker = new TERFBLOCKER5000($http, $db, $options, $logger);
