<?php
/**
 * Class TERFBLOCKER5000
 *
 * @created      25.09.2021
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2021 smiley
 * @license      MIT
 */

namespace codemasher\TERFBLOCKER5000;

use chillerlan\Database\{Database, ResultRow};
use chillerlan\HTTP\Utils\Query;
use chillerlan\OAuth\Core\AccessToken;
use Exception;
use chillerlan\OAuth\Providers\Twitter\{Twitter, TwitterCC};
use chillerlan\OAuth\Storage\MemoryStorage;
use chillerlan\Settings\SettingsContainerInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\{LoggerAwareInterface, LoggerAwareTrait, LoggerInterface, NullLogger};
use Closure, InvalidArgumentException, RuntimeException, Throwable;

use function chillerlan\HTTP\Utils\get_json;
use function array_column, array_diff, array_key_exists, array_merge, array_unique, array_values, count, date,
	file_exists, file_get_contents, file_put_contents, implode, in_array, is_array, is_dir, is_file, is_numeric,
	is_readable, is_string, is_writable, json_encode, json_decode, mb_strpos, mb_strtolower, preg_match,
	preg_replace, realpath, round, rtrim, sleep, sprintf, str_replace, strpos, strtolower, strtotime, time, trim, usleep;

use const DIRECTORY_SEPARATOR, JSON_BIGINT_AS_STRING, JSON_PRETTY_PRINT, JSON_THROW_ON_ERROR, JSON_UNESCAPED_SLASHES;

class TERFBLOCKER5000 implements LoggerAwareInterface{
	use LoggerAwareTrait;

	/** @var \codemasher\TERFBLOCKER5000\TERFBLOCKER5000Options */
	protected SettingsContainerInterface  $options;
	protected ClientInterface             $http;
	protected Database                    $db;
	protected TERFBLOCKER5000TokenStorage $storage;
	protected Twitter                     $twitter;
	protected TwitterCC                   $twitterCC;
	protected array                       $any      = [];
	protected array                       $all      = [];
	protected array                       $blockIDs = []; // needs to be reset before calling prepareUserValues()

	/**
	 * TERFBLOCKER5000 Constructor
	 */
	public function __construct(
		ClientInterface            $http,
		Database                   $db,
		SettingsContainerInterface $options,
		LoggerInterface            $logger = null
	){
		$this->http      = $http;
		$this->db        = $db;
		$this->options   = $options;
		$this->logger    = $logger ?? new NullLogger;

		$this->twitter   = new Twitter($this->http, new MemoryStorage, $this->options, $this->logger);
		$this->twitterCC = new TwitterCC($this->http, new MemoryStorage, $this->options, $this->logger);

		$this->db->connect();
		$this->twitterCC->getClientCredentialsToken();
	}

	/**
	 * Import a user token from an external source and store it in the database
	 */
	public function importUserToken(AccessToken $token):TERFBLOCKER5000{
		$user = $this->verifyToken($token);

		if($user === null){
			throw new InvalidArgumentException('invalid token');
		}

		$this->storage = new TERFBLOCKER5000TokenStorage($this->db, $this->options, $this->logger);
		$this->storage->setUserID($user->id, $user->screen_name);
		// store the token and switch to db storage
		$this->storage->storeAccessToken($this->twitter->serviceName, $token);
		$this->twitter->setStorage($this->storage);

		return $this;
	}

	/**
	 * Set the token from an existing user
	 */
	public function setTokenFromScreenName(string $screen_name):TERFBLOCKER5000{
		$this->storage = new TERFBLOCKER5000TokenStorage($this->db, $this->options, $this->logger);
		$this->storage->setUserFromScreenName($screen_name);

		if(!$this->verifyToken($this->storage->getAccessToken($this->twitter->serviceName))){
			throw new InvalidArgumentException('invalid token');
		}

		$this->twitter->setStorage($this->storage);

		return $this;
	}

	/**
	 * tries to verify a user token and returns the user object on success, null otherwise
	 */
	protected function verifyToken(AccessToken $token):?object{
		// use a temporary storage to verify the token
		$storage = new MemoryStorage;
		$storage->storeAccessToken($this->twitter->serviceName, $token);
		$this->twitter->setStorage($storage);

		while(true){

			try{
				$response = $this->twitter->verifyCredentials(['include_entities' => 'false', 'skip_status' => 'true']);
			}
			catch(Throwable $e){
				$this->logger->error(sprintf('http client error: %s', $e->getMessage()));

				continue;
			}

			$status = $response->getStatusCode();

			// yay
			if($status === 200){
				$user = get_json($response);

				if(isset($user->id, $user->screen_name)){
					return $user;
				}

				break;
			}
			// invalid
			elseif($status === 401){
				// @todo: remove token?
				$this->logger->notice(sprintf('invalid token for user: %s', $token->extraParams['screen_name'] ?? ''));

				break;
			}
			// request limit
			elseif($status === 429){
				$this->sleepOn429($response);
			}
			// nay
			else{
				$this->logger->error(sprintf('response error: HTTP/%s %s', $status, $response->getReasonPhrase()));

				break;
			}

		}

		return null;
	}

	/**
	 * imports/parses a list of terms to search for/match against
	 */
	public function setWordlist(array $wordlist):TERFBLOCKER5000{
		$this->all = [];
		$any       = [];

		foreach($wordlist as $item){

			if(is_array($item)){
				$this->all[] = $item;
			}
			elseif(is_string($item)){
				$item  = mb_strtolower($item);
				$any[] = $item;
				// remove number sign to avoid mid-sentence hashtags
				if(strpos($item, '#') > 1){
					$item  = str_replace('#', '', $item);
					$any[] = $item;
				}
				// remove spaces
				$any[] = str_replace(' ', '', $item);
			}

		}

		$this->any = array_unique($any);

		return $this;
	}

	/**
	 * fetches the @-mentions of the author for the given tweet via the v1 search API.
	 * Note that this will only fetch direct replies to the given tweet, not full conversations.
	 *
	 * caution: highly inefficient (but the only way with the v1 API)
	 */
	public function fromMentions(string $statusURL, string $blocktype = null):TERFBLOCKER5000{
		[$screen_name, $id] = $this::parseTwitterURL($statusURL);

		if(empty($screen_name)){
			throw new InvalidArgumentException('no screen_name given');
		}

		$params = [
			'q'                => 'to:'.$screen_name,
			'since_id'         => $id,
			'count'            => 100,
			'include_entities' => 'false',
			'result_type'      => 'mixed',
		];

		$this->getUsersFromV1SearchTweets($params, $id, $blocktype);

		return $this;
	}

	/**
	 * fetches authors of tweets that match the given search term
	 *
	 * @see https://developer.twitter.com/en/docs/twitter-api/v1/tweets/search/guides/standard-operators
	 */
	public function fromSearch(string $q, string $blocktype = null):TERFBLOCKER5000{

		$params = [
			'q'                => $q,
			'count'            => 100,
			'include_entities' => 'false',
			'result_type'      => 'mixed',
		];

		$this->getUsersFromV1SearchTweets($params, null, $blocktype);

		return $this;
	}

	/**
	 * fetches the follower IDs of the given profile
	 *
	 * caution: slow, depending on the number of followers
	 *
	 * @see https://developer.twitter.com/en/docs/twitter-api/v1/accounts-and-users/follow-search-get-users/api-reference/get-followers-ids
	 */
	public function fromFollowers(string $screen_name):TERFBLOCKER5000{
		$this->getIDs('followersIds', ['screen_name' => $screen_name], true);

		return $this;
	}

	/**
	 * fetches the account IDs the given profile is following (internally called "friends")
	 *
	 * caution: slow, depending on the number of followed accounts
	 *
	 * @see https://developer.twitter.com/en/docs/twitter-api/v1/accounts-and-users/follow-search-get-users/api-reference/get-friends-ids
	 */
	public function fromFollowing(string $screen_name):TERFBLOCKER5000{
		$this->getIDs('friendsIds', ['screen_name' => $screen_name], true);

		return $this;
	}

	/**
	 * Similar to fromFollowers() and fromFollowing() with the difference that the input parameter is a list of screen_names.
	 */
	public function fromFollowersAndFollowing(array $screen_names):TERFBLOCKER5000{

		foreach($screen_names as $screen_name){
			$this->getIDs('followersIds', ['screen_name' => $screen_name], true);
			$this->getIDs('friendsIds', ['screen_name' => $screen_name], true);
		}

		return $this;
	}

	/**
	 * fetches users who retweeted the given tweet.
	 * note: the number of profiles returned may differ from the RT count as twitter only returns "recent" RTs.
	 *
	 * @see https://developer.twitter.com/en/docs/twitter-api/v1/tweets/post-and-engage/api-reference/get-statuses-retweeters-ids
	 * @see https://developer.twitter.com/en/docs/twitter-api/tweets/retweets/api-reference/get-tweets-id-retweeted_by
	 */
	public function fromRetweets(string $statusURL):TERFBLOCKER5000{
		[, $id] = $this::parseTwitterURL($statusURL);

		if(empty($id)){
			throw new InvalidArgumentException('no snowflake id given');
		}

		$this->getIDs('statusesRetweetersIds', ['id' => $id, 'count' => 100], true);

		return $this;
	}

	/**
	 * Adds a list of accounts via screen name to the profile table.
	 * Additionally adds them to the block- or exclusion lists, indicated by the $blocktype parameter.
	 *
	 * Valid values for $blocktype are: "always", "block", "never"
	 *
	 * @see https://developer.twitter.com/en/docs/twitter-api/v1/data-dictionary/object-model/user
	 */
	public function fromScreenNames(array $screen_names, string $blocktype = null):TERFBLOCKER5000{
		$this->blockIDs = [];

		foreach(array_unique($screen_names) as $screen_name){
			$user = $this->getUserprofile($screen_name);

			if($user === null){
				continue;
			}

			if($blocktype !== 'never'){
				$this->blockIDs[$user->id] = ['id' => $user->id];
			}

		}

		$this->addBlockIDs($blocktype ?? 'block');

		return $this;
	}

	/**
	 * Fetches all users of a given (private) list from the currently authenticated usr's account, puts them in the profile table
	 * and runs the given block action (always, block, never) on them.
	 */
	public function fromList(string $listName = null, string $blocktype = null):TERFBLOCKER5000{
		$listName ??= 'TERFBLOCKER5000';
		$list       = $this->fetchList($listName);

		if($list === null){
			throw new Exception(sprintf('cannot find specified list "%s"', $listName));
		}

		$params = [
			'list_id'           => $list->id,
			'user_id'           => $this->storage->getUserID(),
			'include_entities'  => 'false',
			'skip_status'       => 'true',
			'count'             => 100,
			'cursor'            => -1,
		];

		while(true){

			try{
				$response = $this->twitter->listsMembers($params);
			}
			catch(Throwable $e){
				$this->logger->error(sprintf('http client error: %s', $e->getMessage()));

				continue;
			}

			$status = $response->getStatusCode();

			if($status === 429){
				$this->sleepOn429($response);

				continue;
			}
			elseif($status !== 200){
				$this->logger->error(sprintf('response error: HTTP/%s %s', $status, $response->getReasonPhrase()));

				break;
			}

			$json = get_json($response);

			if(isset($json->users) && !empty($json->users)){
				$this->blockIDs = [];
				$users          = [];

				foreach($json->users as $user){

					if($blocktype !== 'never'){
						$this->blockIDs[$user->id] = ['id' => $user->id];
					}

					$users[] = $this->prepareUserValues($user);

					$this->logger->info(sprintf('added: %s', $user->screen_name));
				}

				if(!empty($users)){
					$this->db->insert
						->into($this->options->table_profiles, 'REPLACE', 'id')
						->values($users)
						->multi();

					$this->addBlockIDs($blocktype ?? 'block');
				}
			}

			if(empty($json->next_cursor_str)){
				break;
			}

			$params['cursor'] = $json->next_cursor_str;
		}

		return $this;
	}

	/**
	 *
	 */
	protected function fetchList(string $listName):?object{

		while(true){

			try{
				$response = $this->twitter->lists([
					'user_id' => $this->storage->getUserID(),
					'reverse' => 'true',
				]);
			}
			catch(Throwable $e){
				$this->logger->error(sprintf('http client error: %s', $e->getMessage()));

				continue;
			}

			$status = $response->getStatusCode();

			if($status === 200){
				$json = get_json($response);

				if(!is_array($json) || empty($json)){
					break;
				}

				foreach($json as $list){
					// skip public lists
					if(strtolower($list->name) === strtolower($listName) && $list->mode === 'private'){
						return $list;
					}
				}

				break;
			}
			elseif($status === 429){
				$this->sleepOn429($response);
			}
			else{
				$this->logger->error(sprintf('response error: HTTP/%s %s', $status, $response->getReasonPhrase()));

				break;
			}

		}

		return null;
	}

	/**
	 * Fetches a list of blocked account IDs for the authenticated user (WIP)
	 *
	 * @see https://developer.twitter.com/en/docs/twitter-api/v1/accounts-and-users/mute-block-report-users/api-reference/get-blocks-ids
	 */
	public function fromBlocklist():TERFBLOCKER5000{
		$this->getIDs('blocksIds', [], true);

		return $this;
	}

	/**
	 * Adds a list of IDs from a json file to the profile table
	 *
	 * JSON format: [{"id": 123456, ...}, ...]
	 */
	public function fromJSON(string $file):TERFBLOCKER5000{
		$json = $this->parseJSONFile($file);
		$ids  = array_column($json, 'id');

		if(empty($ids)){
			throw new RuntimeException('no ids found');
		}

		$this->addIDs($ids);

		return $this;
	}

	/**
	 * Exports the block list to a human readable JSON file in the given $path
	 */
	public function exportBlocklist(string $path):TERFBLOCKER5000{
		$path = realpath(rtrim($path, '\\/')).DIRECTORY_SEPARATOR;

		if(!file_exists($path) || !is_dir($path) || !is_writable($path)){
			throw new InvalidArgumentException('invalid path given');
		}

		$result = $this->db->select
			->cols([
				'blocklist.id',
				'profile.screen_name',
				'profile.name',
				'profile.description',
				'profile.location',
			])
			->from([
				'blocklist' => $this->options->table_blocklist,
				'profile'   => $this->options->table_profiles,
			])
			->where('blocklist.id', 'profile.id', '=', false)
			->query();

		$data = str_replace('    ', "\t", json_encode($result, JSON_BIGINT_AS_STRING | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
		file_put_contents(sprintf('%sblocklist-%s.json', $path, date('Y.m.d-H.i.s')), $data);

		return $this;
	}

	/**
	 * loads a json file, does a bit of cleanup and returns the result array on success
	 */
	protected function parseJSONFile(string $file):array{
		$file = realpath($file);

		if(!is_readable($file) || !is_file($file)){
			throw new InvalidArgumentException(sprintf('invalid source file given: %s', $file));
		}

		$data = str_replace("\t", '    ', file_get_contents($file));
		$json = json_decode($data, false, 512, JSON_THROW_ON_ERROR);

		if(!is_array($json)){
			throw new InvalidArgumentException(sprintf('decoded source is not an array: %s', $file));
		}

		return $json;
	}

	/**
	 * parses a given sting and checks whether it's a snowflake id (64bit numerical) or a twitter profile URL
	 * with optional status and returns a 2-element array: [screen_name|null, id|null]
	 */
	public static function parseTwitterURL(string $str):array{

		if(is_numeric($str)){
			return [null, $str];
		}

		preg_match('/twitter\.com\/(?<screen_name>[a-z_\d]+)(\/status\/(?<id>\d+))?/i', $str, $matches);

		if(!empty($matches)){
			return [$matches['screen_name'], $matches['id'] ?? null];
		}

		return [null, null];
	}

	/**
	 * @see https://developer.twitter.com/en/docs/twitter-api/v1/tweets/search/api-reference/get-search-tweets
	 */
	protected function getUsersFromV1SearchTweets(array $params, string $statusID = null, $blocktype = null):void{

		while(true){

			try{
				$response = $this->twitterCC->searchTweets($params);
			}
			catch(Throwable $e){
				$this->logger->error(sprintf('http client error: %s', $e->getMessage()));

				continue;
			}

			$status = $response->getStatusCode();

			if($status === 429){
				$this->sleepOn429($response);

				continue;
			}
			elseif($status !== 200){
				break;
			}

			$json = get_json($response);

			if(!isset($json->statuses)){
				break;
			}

			$this->blockIDs = [];
			$values         = [];

			foreach($json->statuses as $tweet){

				if(!isset($tweet->user) || (!empty($statusID) && (!isset($tweet->in_reply_to_status_id) || $tweet->in_reply_to_status_id_str !== $statusID))){
					continue;
				}

				$values[$tweet->user->id_str] = $this->prepareUserValues($tweet->user);
			}

			if(!empty($values)){
				$this->logger->info(sprintf('%s profiles, %s filtered', count($values), count($this->blockIDs)));

				$this->db->insert
					->into($this->options->table_profiles, 'REPLACE', 'id')
					->values(array_values($values))
					->multi();

				$this->addBlockIDs($blocktype ?? 'block');
			}

			if(!isset($json->search_metadata, $json->search_metadata->next_results) || empty($json->search_metadata->next_results)){
				break;
			}

			$params = Query::parse($json->search_metadata->next_results);

			if($this->options->enforceRateLimit){
				// sleep for 2.1 seconds (API limit: 450requests/15min - too lazy to implement a token bucket...)
				usleep(2100000);
			}
		}

	}

	/**
	 * @see https://developer.twitter.com/en/docs/twitter-api/v1/data-dictionary/object-model/user
	 * @see https://developer.twitter.com/en/docs/twitter-api/data-dictionary/object-model/user
	 */
	protected function prepareUserValues(object $user, bool $match = true):array{
		$name        = preg_replace('/\s\s+/', ' ', $user->name ?? '');
		$description = preg_replace('/\s\s+/', ' ', $user->description ?? '');
		$location    = preg_replace('/\s\s+/', ' ', $user->location ?? '');

		if($match && $this->match($name, $description, $location)){
			$this->blockIDs[$user->id] = ['id' => $user->id];
		}

		return [
			'screen_name'     => $user->screen_name ?? $user->username,
			'name'            => $name,
			'description'     => $description,
			'location'        => $location,
			'followers_count' => $user->followers_count ?? $user->public_metrics->followers_count ?? 0,
			'friends_count'   => $user->friends_count ?? $user->public_metrics->following_count ?? 0,
			'created_at'      => strtotime($user->created_at ?? ''),
			'verified'        => (int)($user->verified ?? 0),
			// we put id as last field; in INSERTs it doesn't matter for the querybuilder as the fields are named.
			// in UPDATE queries it is required in the last position (WHERE condition) as the field names are ignored.
			'id'              => $user->id,
		];
	}

	/**
	 * needs to be called after collecting IDs via prepareUserValues()
	 *
	 * @see \codemasher\TERFBLOCKER5000\TERFBLOCKER5000::prepareUserValues()
	 */
	protected function addBlockIDs(string $blocktype):void{
		$blocktype = strtolower(trim($blocktype));

		if(empty($this->blockIDs)){
			$this->logger->info('blockIDs empty, nothing to add');

			return;
		}

		if(!in_array($blocktype, ['always', 'block', 'never']) || empty($this->blockIDs)){
			return;
		}

		$blockIDs = array_values($this->blockIDs);

		$this->logger->info(sprintf('filtered IDs (%s): %s', $blocktype, implode(', ', array_column($blockIDs, 'id'))));

		if($blocktype === 'never'){
			$this->db->insert
				->into($this->options->table_block_never, 'IGNORE', 'id')
				->values($blockIDs)
				->multi();

			return;
		}

		$this->db->insert
			->into($this->options->table_blocklist, 'IGNORE', 'id')
			->values($blockIDs)
			->multi();

		if($blocktype === 'always'){
			$this->db->insert
				->into($this->options->table_block_always, 'IGNORE', 'id')
				->values($blockIDs)
				->multi();
		}
	}

	/**
	 * simple and cheap matching against a list of terms - no regex cannons fired (WIP)
	 */
	protected function match(string $name, string $bio, string $location):bool{

		if(empty($this->any) && empty($this->all)){
			throw new InvalidArgumentException('no terms to match given');
		}

		foreach([$name, $bio, $location] as $str){

			$str = mb_strtolower(str_replace(
				['.', ',', '"', '\'', '-', '/', '\\', '|', '•'],
				[' ', ' ',  '',   '', ' ', ' ',  ' ',  '', ' '],
				$str
			));

			foreach($this->any as $term){
				if(mb_strpos($str, $term) !== false){
					return true;
				}
			}

			foreach($this->all as $arr){
				$check = [];

				foreach($arr as $term){
					if(mb_strpos($str, $term) !== false){
						$check[$term] = true;
					}
				}

				if(count($check) === count($arr)){
					return true;
				}
			}

		}

		return false;
	}

	/**
	 * fetches a list of IDs from the given endpoint
	 */
	protected function getIDs(string $endpointMethod, array $params, bool $insert = null):array{

		$endpoints = [
			'blocksIds'             => 61, // user/app 15/900s
			'followersIds'          => 61,
			'friendsIds'            => 61,
			'statusesRetweetersIds' => 3, // app, user: 12
		];

		if(!array_key_exists($endpointMethod, $endpoints)){
			throw new InvalidArgumentException(sprintf('invalid endpoint "%s"', $endpointMethod));
		}

		// use app auth on certain endpoints for improved request limits
		$client = in_array($endpointMethod, ['statusesRetweetersIds'/*, 'followersIds', 'friendsIds'*/]) ? 'twitterCC' : 'twitter';
		$params = array_merge(['cursor' => -1, 'stringify_ids' => 'false'], $params);
		$ids    = [];

		while(true){

			try{
				$response = $this->{$client}->{$endpointMethod}($params);
			}
			catch(Throwable $e){
				$this->logger->error(sprintf('http client error: %s', $e->getMessage()));

				continue;
			}

			$status = $response->getStatusCode();

			if($status === 429){
				$this->sleepOn429($response);

				continue;
			}
			elseif($status !== 200){
				$this->logger->error(sprintf('response error: HTTP/%s %s', $status, $response->getReasonPhrase()));

				break;
			}

			$json = get_json($response);

			if(isset($json->ids) && !empty($json->ids)){
				$ids = array_merge($ids, array_map('intval', $json->ids));

				if($insert){
					$this->addIDs($json->ids);
				}
			}

			if(empty($json->next_cursor_str)){
				break;
			}

			$params['cursor'] = $json->next_cursor_str;

			if($this->options->enforceRateLimit){
				$this->logger->info(sprintf(
					'enforcing limit for "%s": going to sleep for %ss',
					$endpointMethod,
					$endpoints[$endpointMethod]
				));

				sleep($endpoints[$endpointMethod]); // take a break
			}
		}

		return $ids;
	}

	/**
	 * fetches a single user profile from the given $profileURL and returns the user object
	 *
	 * @see https://developer.twitter.com/en/docs/twitter-api/v1/accounts-and-users/follow-search-get-users/api-reference/get-users-show
	 */
	protected function getUserprofile(string $screen_name):?object{

		if(empty($screen_name)){
			throw new InvalidArgumentException('no screen_name given');
		}

		while(true){

			try{
				$response = $this->twitter->usersShow(['screen_name' => $screen_name, 'include_entities' => 'false']);
			}
			catch(Throwable $e){
				$this->logger->error(sprintf('http client error: %s', $e->getMessage()));

				continue;
			}

			$status = $response->getStatusCode();

			if($status === 200){
				$user = get_json($response);

				$this->db->insert
					->into($this->options->table_profiles, 'REPLACE', 'id')
					->values($this->prepareUserValues($user, false))
					->query();

				$this->logger->info(sprintf('updated: %s', $screen_name));

				return $user;
			}
			elseif($status === 404){
				$this->logger->error(sprintf('user not found: "%s"', $screen_name));

				break;
			}
			elseif($status === 429){
				$this->sleepOn429($response);
			}
			else{
				$this->logger->error(sprintf('response error: HTTP/%s %s', $status, $response->getReasonPhrase()));

				break;
			}

		}

		return null;
	}

	/**
	 * feches the block list and runs the blocker for the currently authenticated user
	 */
	public function block():TERFBLOCKER5000{

		$result = $this->db->select
			->cols(['profiles.id', 'profiles.screen_name'])
			->from([
				'profiles'  => $this->options->table_profiles,
				'blocklist' => $this->options->table_blocklist,
			])
			->where('profiles.id', 'blocklist.id', '=', false)
			->where('profiles.screen_name', ['[NOT_FOUND]', '[SUSPENDED]'], 'NOT IN')
			->query()
		;

		if($result->count() > 0){
			$this->performBlock($result->toArray());
		}

		return $this;
	}

	/**
	 * performs the block operation, retries up to 3 times on non-200 responses
	 *
	 * @see https://developer.twitter.com/en/docs/twitter-api/v1/accounts-and-users/mute-block-report-users/api-reference/post-blocks-create
	 */
	protected function performBlock(array $blocklist):void{

		if(empty($blocklist)){
			throw new InvalidArgumentException('blocklist is empty');
		}

		$blockedIDs = $this->getIDs('blocksIds', []);
		$blocklist  = array_values($blocklist);
		$counter    = 0;

		$this->logger->info(sprintf('currently blocked users: %d, blocklist: %d', count($blockedIDs), count($blocklist)));

		while(!empty($blocklist)){
			$user = array_shift($blocklist);

			if(!isset($user['retry'])){
				$user['retry'] = 0;
			}

			if(in_array($user['id'], $blockedIDs, true)){
				continue;
			}

			$params = [
#				'screen_name'      => $user['screen_name'] ?? null,
				'user_id'          => $user['id'] ?? null,
				'include_entities' => 'false',
				'skip_status'      => 'true',
			];

			try{
				$response = $this->twitter->block($params);

				usleep(250000); // v1 API docs say the block endpoint has a limit, but apparently it doesn't :)
			}
			catch(Throwable $e){
				$this->logger->error(sprintf('http client error: %s', $e->getMessage()));

				continue;
			}

			$status = $response->getStatusCode();

			if($status === 200){
				$counter++;

				$this->logger->info(sprintf('blocked: %s [%s]', $user['screen_name'], $user['id']));
			}
			elseif($status === 429){
				$this->sleepOn429($response);
			}
			else{

				if($user['retry'] < 3){
					$user['retry']++;
					$blocklist[] = $user;

					$this->logger->info(sprintf('retry #%s: %s [%s]', $user['retry'], $user['screen_name'], $user['id']));

					continue;
				}

				$this->db->update
					->table($this->options->table_profiles)
					->set(['screen_name' => '[NOT_FOUND]'])
					->where('id', $user['id'])
					->query();

				// something something foreign keys...
				$this->db->delete
					->from($this->options->table_blocklist)
					->where('id', $user['id'])
					->query();

				break;
			}

		}

		$this->logger->info(sprintf('blocked users in this run: %d', $counter));
	}

	/**
	 * evaluates the rate limit header and sleeps until reset
	 */
	protected function sleepOn429(ResponseInterface $response):void{
		$reset = (int)$response->getHeaderLine('x-rate-limit-reset');
		$now   = time();

		// header might be not set - just pause for a bit
		if($reset < $now){
			sleep(5);

			return;
		}

		$sleep = $reset - $now + 5;

		$this->logger->info(sprintf('HTTP/429 - going to sleep for %d seconds', $sleep));

		sleep($sleep);
	}

	/**
	 * adds/inserts an array of IDs to the profile table
	 */
	protected function addIDs(array $ids):void{

		if(empty($ids)){
			return;
		}

		$start = microtime(true);

		$this->db->insert
			->into($this->options->table_profiles, 'IGNORE', 'id')
			->values([['id' => '?']])
			->callback($ids, fn($v):array => [(int)$v]);

		$this->logger->info(sprintf('added: %d IDs (%s seconds)', count($ids), round(microtime(true) - $start, 3)));
	}

	// cron methods

	/**
	 * Adds a list of screen names to the background scan jobs table (not to confuse with fromScreenNames()!)
	 */
	public function cronAddScreenNames(array $screen_names):TERFBLOCKER5000{
		$values = [];

		foreach(array_unique($screen_names) as $screen_name){
			$user = $this->getUserprofile($screen_name);

			if($user === null){
				continue;
			}

			$values[] = [
				'id'          => $user->id,
				'screen_name' => $user->screen_name,
				'finished'    => 0,
			];
		}

		if(empty($values)){
			$this->logger->info('empty list of screen_names');

			return $this;
		}

		$this->db->insert
			->into($this->options->table_scan_jobs, 'IGNORE', 'screen_name')
			->values($values)
			->multi();

		return $this;
	}

	/**
	 * Fetches the follower- and following ids for each screen name in the scan jobs table and stores them in the profile table.
	 */
	public function cronScanFollow():void{

		$result = $this->db->select
			->from([$this->options->table_scan_jobs])
			->where('finished', 0)
			->limit(1) // select count outside
			->query();

		if($result->count() === 0){
			$this->logger->error('invalid db query result/EOF');

			return;
		}

		$result->each(function(ResultRow $row){

			try{
				$user = $this->getUserprofile($row->screen_name);

				if($user === null){
					throw new Exception('user not found');
				}

				if($user->protected){
					throw new Exception('user profile protected');
				}

				foreach(['followersIds', 'friendsIds'] as $endpoint){
					$this->logger->info(sprintf('scanning: %s (%s)', $row->screen_name, $endpoint));

					$this->getIDs($endpoint, ['user_id' => $row->id, 'screen_name' => $row->screen_name], true);
				}

				sleep(60);
			}
			catch(Throwable $e){
				$this->logger->error(sprintf('%s: %s', $row->screen_name, $e->getMessage()));

				$this->db->update
					->table($this->options->table_scan_jobs)
					->set(['finished' => 2])
					->where('id', $row->id)
					->query();

				return;
			}

			$this->db->update
				->table($this->options->table_scan_jobs)
				->set(['finished' => 1])
				->where('id', $row->id)
				->query();
		});
	}

	/**
	 * Fetches up to 100 user profiles and updates the profile table with that data.
	 * Profiles will be scanned during the update and suspect IDs will be added to the block list.
	 *
	 * @see https://developer.twitter.com/en/docs/twitter-api/v1/accounts-and-users/follow-search-get-users/api-reference/get-users-lookup
	 * @see https://developer.twitter.com/en/docs/twitter-api/v1/data-dictionary/object-model/user
	 */
	public function cronFetchProfiles():void{

		// select up to 100 freshly inserted rows from the profile table
		$result = $this->db->select
			->cols(['id'])
			->from([$this->options->table_profiles])
			->where('screen_name', null, 'IS', false)
			->limit(100) // API limit
			->query();

		if($result->count() === 0){
			$this->logger->error('invalid db query result/EOF');

			return;
		}

		// save the IDs that are passed in the request
		$ids = array_column($result->toArray(), 'id');

		try{
			$response = $this->twitter->usersLookup(['user_id' => implode(',', $ids), 'include_entities' => 'false']);
		}
		catch(Throwable $e){
			$this->logger->error(sprintf('http client error: %s', $e->getMessage()));

			return;
		}

		$status = $response->getStatusCode();

		// a 404 means that none of the requested IDs could be found
		if($status === 404){

			$this->db->update
				->table($this->options->table_profiles)
				->set(['screen_name' => '[NOT_FOUND]'])
				->where('id', $ids, 'IN')
				->query();

			$this->logger->info(sprintf('invalid IDs: %s', implode(', ', $ids)));

			return;
		}
		// if we hit the request limit, go to sleep for a while
		elseif($status === 429){
			$this->sleepOn429($response);

			return;
		}
		// if the request fails for some reason, we'll just retry next time
		elseif($status !== 200){
			$this->logger->error(sprintf('HTTP/%s %s', $status, $response->getReasonPhrase()));

			return;
		}

		$users = get_json($response);

		if(!is_array($users) || empty($users)){
			$this->logger->info('response does not contain user data');

			return;
		}

		// diff the returned IDs against the requested ones
		$returned = array_column($users, 'id');
		$diff     = array_diff($ids, $returned);

		// exclude failed IDs
		if(!empty($diff)){

			$this->db->update
				->table($this->options->table_profiles)
				->set(['screen_name' => '[NOT_FOUND]'])
				->where('id', $diff, 'IN')
				->query();

			$this->logger->info(sprintf('invalid IDs: %s', implode(', ', $diff)));
		}

		$this->blockIDs = [];

		// dump the result into the DB
		$this->db->update
			->table($this->options->table_profiles)
			->set([
				'screen_name'     => '?',
				'name'            => '?',
				'description'     => '?',
				'location'        => '?',
				'followers_count' => '?',
				'friends_count'   => '?',
				'created_at'      => '?',
				'verified'        => '?',
			], false)
			->where('id', '?', '=', false)
			// @see https://wiki.php.net/rfc/consistent_callables
			->callback($users, Closure::fromCallable([$this, 'prepareUserValues']));

#		$this->logger->info(sprintf('updated IDs: %s', implode(', ', array_column($users, 'id'))));

		$this->addBlockIDs('block');
	}

	/**
	 * SELECT `id` FROM `terfblocker5000_profiles` WHERE ( LOWER(`name`) LIKE ? OR LOWER(`description`) LIKE ? OR LOWER(`location`) LIKE ? ) AND `id` NOT IN(SELECT `id` FROM `terfblocker5000_blocklist`)
	 */
	public function cronScanByWordlist():void{

		foreach($this->any as $term){

			$result = $this->db->select
				->cols(['id'])
				->from([$this->options->table_profiles])
				->openBracket()
				->where(['name', 'LOWER'], "%$term%", 'LIKE', true, 'OR')
				->where(['description', 'LOWER'], "%$term%", 'LIKE', true, 'OR')
				->where(['location', 'LOWER'], "%$term%", 'LIKE', true, 'OR')
				->closeBracket()
				->where('id', $this->db->select->cols(['id'])->from([$this->options->table_blocklist]), 'NOT IN')
				->query();

			if($result->count() === 0){
				$this->logger->info(sprintf('nothing found for "%s"', $term));

				continue;
			}

			$this->logger->info(sprintf('%d accounts found for "%s"', $result->count(), $term));

			$this->db->insert
				->into($this->options->table_blocklist, 'IGNORE', 'id')
				->values($result)
				->multi();
		}

	}

}
