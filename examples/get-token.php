<?php
/**
 * @link https://developer.twitter.com/en/docs/authentication/oauth-1-0a
 * @link https://developer.twitter.com/en/portal/dashboard
 * @link https://developer.twitter.com/en/docs/twitter-api/getting-started/getting-access-to-the-twitter-api
 *
 * @link https://github.com/chillerlan/php-oauth-core
 * @link https://github.com/chillerlan/php-oauth-providers
 *
 * @created      25.09.2021
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2021 smiley
 * @license      MIT
 */

/**
 * @var \chillerlan\OAuth\Providers\Twitter\Twitter $twitter
 * @var \chillerlan\OAuth\Storage\OAuthStorageInterface $storage
 */

require_once __DIR__.'/common.php';

$servicename = $twitter->serviceName;

// step 2: redirect to the provider's login screen
if(isset($_GET['login']) && $_GET['login'] === $servicename){
	header('Location: '.$twitter->getAuthURL());
}
// step 3: receive the access token
elseif(isset($_GET['oauth_token']) && isset($_GET['oauth_verifier'])){
	$twitter->getAccessToken($_GET['oauth_token'], $_GET['oauth_verifier']);

	// access granted, redirect
	header('Location: ?granted='.$servicename);
}
// step 4: verify the token and use the API
elseif(isset($_GET['granted']) && $_GET['granted'] === $servicename){
	echo '<textarea cols="120" rows="3" onclick="this.select();">'.$storage->getAccessToken($servicename)->toJSON().'</textarea>';
}
// step 1 (optional): display a login link
else{
	echo '<a href="?login='.$servicename.'">connect with '.$servicename.'!</a>';
}

exit;
