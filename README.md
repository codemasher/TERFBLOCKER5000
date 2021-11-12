# TERFBLOCKER5000

### Improve your twitter experience by mass blocking TERFs etc.

TERFs - Trans Exclusionary Radical "Feminists" - are a rather nasty, predominantly (but not limited to) british phenomenon,
a [fascist](https://twitter.com/evan_greer/status/1435270525249626123) [trend](https://www.theguardian.com/us-news/commentisfree/2021/oct/23/judith-butler-gender-ideology-backlash) as Judith Butler called it.
They pretend to "fight for the rights of woman and girls" but in reality they spread hate and misinformation about trans people.
They come in different flavors from "concerned mother" to [outright terrorist](https://twitter.com/christapeterso/status/1455574098717913096) and - much like their [alt-right buddies](https://rationalwiki.org/wiki/Alt-right_glossary) -
they use a lot of [dog whistles](https://rationalwiki.org/wiki/TERF_glossary), which is where *TERFBLOCKER5000* steps in.

*TERFBLOCKER5000* scans the user profiles (display name, bio and location) and filters them against a [word list](https://github.com/codemasher/TERFBLOCKER5000/blob/main/config/wordlist.php).
Of course it's not perfect, but it catches the majority of the most hateful accounts (~2% of the 1,8 million scanned accounts in the test environment - [get a taste of the vogon poetry](https://gist.github.com/codemasher/8e15e8238bd9e18230ff031a1e87ec8b)).

[![PHP Version Support][php-badge]][php]
[![version][packagist-badge]][packagist]
[![license][license-badge]][license]

[php-badge]: https://img.shields.io/packagist/php-v/codemasher/TERFBLOCKER5000?logo=php&color=8892BF
[php]: https://www.php.net/supported-versions.php
[packagist-badge]: https://img.shields.io/packagist/v/codemasher/TERFBLOCKER5000.svg?logo=packagist
[packagist]: https://packagist.org/packages/codemasher/TERFBLOCKER5000
[license-badge]: https://img.shields.io/github/license/codemasher/TERFBLOCKER5000.svg
[license]: https://github.com/codemasher/TERFBLOCKER5000/blob/main/LICENSE

## Documentation (WIP)
<!-- WIP -->
```php
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
```
