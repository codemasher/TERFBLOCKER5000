<?php
/**
 * @created      25.09.2021
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2021 smiley
 * @license      MIT
 */

use chillerlan\Database\Database;
use chillerlan\Database\Drivers\MySQLiDrv;
use chillerlan\DotEnv\DotEnv;
use chillerlan\HTTP\Psr18\CurlClient;
use chillerlan\OAuthTest\OAuthTestLogger;
use chillerlan\SimpleCache\MemoryCache;
use codemasher\TERFBLOCKER5000\TERFBLOCKER5000;
use codemasher\TERFBLOCKER5000\TERFBLOCKER5000Options;

require_once __DIR__.'/../vendor/autoload.php';

const CFGDIR = __DIR__.'/../config';

// please for the love of the php goddess, disable error reporting on a public server
#error_reporting(0);
#ini_set('display_errors', '0');

ini_set('date.timezone', 'Europe/Amsterdam');

$env = (new DotEnv(CFGDIR, '.env', false))->load();

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
	'ca_info'             => realpath(CFGDIR.'/cacert.pem'), // https://curl.haxx.se/ca/cacert.pem
	'user_agent'          => 'TERFBLOCKER/5.0.0.0 +https://github.com/codemasher/TERFBLOCKER5000',
	// TERFBLOCKER5000Options
	'storageEncryption'   => true,
	'storageCryptoKey'    => $env->DB_CRYPTO_KEY,
	'$storageCryptoNonce' => $env->DB_CRYPTO_NONCE,
]);

$logger      = new OAuthTestLogger('info'); // PSR-3
$http        = new CurlClient($options, null, $logger); // PSR-18
$db          = new Database($options, new MemoryCache, $logger);
$terfblocker = new TERFBLOCKER5000($http, $db, $options, $logger);
