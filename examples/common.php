<?php
/**
 * @created      25.09.2021
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2021 smiley
 * @license      MIT
 */

use chillerlan\DotEnv\DotEnv;
use chillerlan\HTTP\Psr18\CurlClient;
use chillerlan\OAuth\Core\AccessToken;
use chillerlan\OAuth\OAuthOptions;
use chillerlan\OAuth\Providers\Twitter\Twitter;
use chillerlan\OAuth\Storage\SessionStorage;
use codemasher\TERFBLOCKER5000\TERFBLOCKER5000;
use Psr\Http\Message\{RequestInterface, ResponseInterface};
use Psr\Log\AbstractLogger;
use function chillerlan\HTTP\Utils\message_to_string;

require_once __DIR__.'/../vendor/autoload.php';

const CFGDIR = __DIR__.'/../config';

// please for the love of the php goddess, disable error reporting on a public server
#error_reporting(0);
#ini_set('display_errors', '0');

ini_set('date.timezone', 'Europe/Amsterdam');

$env = (new DotEnv(CFGDIR, '.env', false))->load();

$options = new OAuthOptions([
	// OAuthOptionsTrait
	'key'             => $env->get('TWITTER_KEY') ?? '',
	'secret'          => $env->get('TWITTER_SECRET') ?? '',
	'callbackURL'     => $env->get('TWITTER_CALLBACK_URL') ?? '',
	'sessionStart'    => true,
	'sessionTokenVar' => 'terfblocker5000-token',
	// HTTPOptionsTrait
	'ca_info'         => realpath(CFGDIR.'/cacert.pem'), // https://curl.haxx.se/ca/cacert.pem
	'user_agent'      => 'TERFBLOCKER/5.0.0.0 +https://github.com/codemasher/TERFBLOCKER5000',
]);

$logger = new class() extends AbstractLogger{

	public function log($level, $message, array $context = []){
		echo sprintf('[%s][%s] %s', date('Y-m-d H:i:s'), str_pad($level, 9), trim($message))."\n";
	}

};

// uncomment to disable logging
#$logger = null;

$http = new class($options, null, $logger) extends CurlClient{

	public function sendRequest(RequestInterface $request):ResponseInterface{
		$this->logger->debug("\n----HTTP-REQUEST----\n".message_to_string($request));

		try{
			$response = parent::sendRequest($request);
		}
		catch(Throwable $e){
			$this->logger->debug("\n----HTTP-ERROR------");
			$this->logger->error($e->getMessage());
			$this->logger->error($e->getTraceAsString());

#			throw $e;
			exit; // don't throw, just exit
		}

		$this->logger->debug("\n----HTTP-RESPONSE---\n".message_to_string($response));

		return $response;
	}

};

$storage     = new SessionStorage($options);
$twitter     = new Twitter($http, $storage, $options, $logger);
$terfblocker = new TERFBLOCKER5000($twitter, $logger);

// @see https://github.com/chillerlan/php-oauth-providers/blob/main/examples/get-token/Twitter.php
/** @var \chillerlan\OAuth\Core\AccessToken $token */
$token = (new AccessToken)->fromJSON(file_get_contents(CFGDIR.'/Twitter.token.json'));

$storage->storeAccessToken($twitter->serviceName, $token);
