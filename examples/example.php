<?php
/**
 * @created      26.09.2021
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2021 smiley
 * @license      MIT
 */

/**
 * @var \codemasher\TERFBLOCKER5000\TERFBLOCKER5000 $terfblocker
 */

require_once __DIR__.'/common.php';

$wordlist = require CFGDIR.'/wordlist.php';

$terfblocker
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
	// from the followers of the given account - API limits wil be automatically enforced if the follower count is > 50k
	->fromFollowers('https://twitter.com/HJoyceGender')
	// fetches the retweeters of the given tweet - note that the results of this endpoint
	// may not return *all* but only the *recent* retweeters - whatever that means...
	->fromRetweets('https://twitter.com/fairplaywomen/status/1388442839969931264')
	// saves the internal "positive"/"negative" lists in the given directory
	->save(__DIR__.'/../json/')
	// blocks all accounts from the internal "positive" list - or, if a json file is given, from that list
	->block(__DIR__.'/terfs.json')
;
