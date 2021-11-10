<?php
/**
 * @created      26.09.2021
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2021 smiley
 * @license      MIT
 */

use chillerlan\OAuth\Core\AccessToken;

/**
 * @var \codemasher\TERFBLOCKER5000\TERFBLOCKER5000 $terfblocker
 * @var \chillerlan\OAuth\Core\AccessToken $token
 */

require_once __DIR__.'/../cron/common.php';

// @see https://github.com/chillerlan/php-oauth-providers/blob/main/examples/get-token/Twitter.php
$token    = (new AccessToken)->fromJSON(file_get_contents(CFGDIR.'/Twitter.token.json'));
$wordlist = require CFGDIR.'/wordlist.php';

$terfblocker
	// import a new twitter oauth token if needed
	->importUserToken($token)
	// set the list of terms to match against (required)
	->setWordlist($wordlist)
	// fetch from replies to a given tweet
	// the 2nd parameter toggles the API request limit enforcement
	->fromMentions('https://twitter.com/Nigella_Lawson/status/1441121776780464132', true)
	// or just the @-mentions of a user
	->fromMentions('https://twitter.com/Lenniesaurus', true)
	// from an advanced search
	// @see https://developer.twitter.com/en/docs/twitter-api/v1/tweets/search/guides/standard-operators
	->fromSearch('#IStandWithJKRowling', true)
	// from the followers of the given account
	->fromFollowers('https://twitter.com/HJoyceGender', true)
	// from the accounts the given account follows
	->fromFollowing('https://twitter.com/HJoyceGender', true)
	// from followers AND following from a list of screen names
	->fromFollowersAndFollowing(['ALLIANCELGB', 'Transgendertrd', 'fairplaywomen'], true)
	// fetches the retweeters of the given tweet - note that the results of this endpoint
	// may not return *all* but only the *recent* retweeters - whatever that means...
	->fromRetweets('https://twitter.com/fairplaywomen/status/1388442839969931264')
	// add user IDs found in the given JSON file
	->fromJSON(__DIR__.'/users.json')
	// blocks all accounts from the database block list list
	->block()
;
