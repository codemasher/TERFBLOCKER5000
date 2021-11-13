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
 * @var string $CFGDIR
 */

require_once __DIR__.'/../cron/common.php';

// @see https://github.com/chillerlan/php-oauth-providers/blob/main/examples/get-token/Twitter.php
$token    = (new AccessToken)->fromJSON(file_get_contents($CFGDIR.'/Twitter.token.json'));
$wordlist = require $CFGDIR.'/wordlist.php';

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
	// from followers AND following from a list of screen names ()
	->fromFollowersAndFollowing(['ALLIANCELGB', 'Transgendertrd', 'fairplaywomen'], true)
	// adds each of the given screen_names to the given block list (always, block, never)
	->fromScreenNames(['ALLIANCELGB', 'Transgendertrd', 'fairplaywomen'], 'always')
	// fetches the retweeters of the given tweet - note that the results of this endpoint
	// may not return *all* but only the *recent* retweeters - whatever that means...
	->fromRetweets('https://twitter.com/fairplaywomen/status/1388442839969931264')
	// fetches all users of the given (private) list
	->fromList('TERFBLOCKER5000', 'always')
	// adds the user IDs from the block list of the currently authenticated user to the profile table
	->fromBlocklist()
	// add user IDs found in the given JSON file
	->fromJSON(__DIR__.'/users.json')
	// exports the block list from the database into a JSON file in the given path
	->exportBlocklist(__DIR__.'/../json/')
;

/**
 * cron methods
 */

$terfblocker
	// adds a list of screen names to the "scan jobs" table
	->cronAddScreenNames(['ALLIANCELGB', 'Transgendertrd', 'fairplaywomen'])
	// scans followers/following for users in the scan jobs table, @see /cron/fetch_follow.php
	->cronScanFollow();

// the profile fetcher @see /cron/fetch_profiles.php
$terfblocker->cronFetchProfiles();

// rescans the profile table against the given wordlist
$terfblocker
	->setWordlist($wordlist)
	->cronScanByWordlist();

// performs blocks for the currently authenticated user, @see /cron/perform_block.php
$terfblocker->block();
