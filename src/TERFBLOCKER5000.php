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

use chillerlan\HTTP\Utils\Query;
use chillerlan\OAuth\Providers\Twitter\Twitter;
use chillerlan\Settings\SettingsContainerInterface;
use Psr\Log\{LoggerAwareInterface, LoggerAwareTrait, LoggerInterface, NullLogger};
use InvalidArgumentException, RuntimeException;
use function chillerlan\HTTP\Utils\get_json;
use function array_chunk, array_merge, array_unique, array_values, count, date, file_exists,
	file_get_contents, file_put_contents, implode, in_array, is_array, is_dir, is_file,
	is_numeric, is_readable, is_string, is_writable, json_decode, json_encode, mb_strpos,
	mb_strtolower, preg_match, realpath, rtrim, sleep, sprintf, str_replace, usleep;
use const DIRECTORY_SEPARATOR, JSON_BIGINT_AS_STRING, JSON_PRETTY_PRINT, JSON_THROW_ON_ERROR, JSON_UNESCAPED_SLASHES;

class TERFBLOCKER5000 implements LoggerAwareInterface{
	use LoggerAwareTrait;

	protected Twitter                    $twitter;
	protected SettingsContainerInterface $options;
	protected array                      $any      = [];
	protected array                      $all      = [];
	protected array                      $positive = [];
	protected array                      $negative = [];

	/**
	 * TERFBLOCKER5000 Constructor
	 */
	public function __construct(Twitter $twitter, LoggerInterface $logger = null){
		$this->twitter        = $twitter;
		$this->logger         = $logger ?? new NullLogger;
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
				$item = mb_strtolower($item);

				$any[] = $item;
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
	public function fromMentions(string $statusURL, bool $enforceLimit = null):TERFBLOCKER5000{
		[$screen_name, $id] = $this::parseTwitterURL($statusURL);

		if(empty($screen_name)){
			throw new InvalidArgumentException;
		}

		$params = [
			'q'                => 'to:'.$screen_name,
			'since_id'         => $id,
			'count'            => 100,
			'include_entities' => 'false',
			'result_type'      => 'mixed',
		];

		$this->getUsersFromV1SearchTweets($params, $id, $enforceLimit);

		return $this;
	}

	/**
	 * fetches authors of tweets that match the given search term
	 *
	 * @see https://developer.twitter.com/en/docs/twitter-api/v1/tweets/search/guides/standard-operators
	 */
	public function fromSearch(string $q, bool $enforceLimit = null):TERFBLOCKER5000{

		$params = [
			'q'                => $q,
			'count'            => 100,
			'include_entities' => 'false',
			'result_type'      => 'mixed',
		];

		$this->getUsersFromV1SearchTweets($params, null, $enforceLimit);

		return $this;
	}

	/**
	 * fetches the followers of the given profile
	 *
	 * caution: slow, depending on the number of followers
	 *
	 * @see https://developer.twitter.com/en/docs/twitter-api/v1/accounts-and-users/follow-search-get-users/api-reference/get-users-show
	 * @see https://developer.twitter.com/en/docs/twitter-api/v1/accounts-and-users/follow-search-get-users/api-reference/get-followers-ids
	 */
	public function fromFollowers(string $profileURL):TERFBLOCKER5000{
		[$screen_name,] = $this::parseTwitterURL($profileURL);

		if(empty($screen_name)){
			throw new InvalidArgumentException;
		}

		$userResponse = $this->twitter->usersShow(['screen_name' => $screen_name, 'include_entities' => 'false']);

		if($userResponse->getStatusCode() !== 200){
			throw new RuntimeException;
		}

		$user        = get_json($userResponse);
		$followerIDs = $this->getIDs('followersIds', ['screen_name' => $screen_name], $user->followers_count > 50000);

		$this->detect($this->getUserProfiles($followerIDs));

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
			throw new InvalidArgumentException;
		}

		$statusResponse = $this->twitter->statusesShowId($id);
		$enforceLimit   = false;

		if($statusResponse->getStatusCode() === 200){
			$status       = get_json($statusResponse);
			$enforceLimit = $status->retweet_count > 50000;
		}

		$ids = $this->getIDs('statusesRetweetersIds', ['id' => $id, 'count' => 100], $enforceLimit);

		$this->detect($this->getUserProfiles($ids));

		return $this;
	}

	/**
	 * blocks the users from the internal "positive" list or from a given .json file.
	 * the json has to be an array of objects similar to a twitter user object with at least a "screen_name" or "id" element.
	 */
	public function block(string $fromJSON = null):TERFBLOCKER5000{

		if($fromJSON !== null && file_exists($fromJSON) && is_file($fromJSON) && is_readable($fromJSON)){
			// for some reason there are sometimes nasty tab characters (\u0009) in the json that may prevent proper decode
			$data = str_replace("\x09", '', file_get_contents($fromJSON));

			$fromJSON = json_decode($data, true, JSON_THROW_ON_ERROR);
		}

		$this->performBlock($fromJSON ?? $this->positive);

		return $this;
	}

	/**
	 * Fetches a list of blocked accounts for the authenticated user (WIP)
	 *
	 * @see https://developer.twitter.com/en/docs/twitter-api/v1/accounts-and-users/mute-block-report-users/api-reference/get-blocks-ids
	 */
	public function getBlocklist(bool $enforceLimit = null):array{
		return $this->getUserProfiles($this->getIDs('blocksIds', [], $enforceLimit));
	}

	/**
	 * saves the internal positive/negative lists to a .json file in the given path
	 */
	public function save(string $path):TERFBLOCKER5000{
		$path = realpath(rtrim($path, '\\/')).DIRECTORY_SEPARATOR;

		if(!file_exists($path) || !is_dir($path) || !is_writable($path)){
			throw new InvalidArgumentException;
		}

		$date = date('Y.m.d-H.i.s');

		foreach(['positive', 'negative'] as $v){
			$this->saveToJson($this->{$v}, sprintf('%s%s-%s.json', $path, $v, $date));
		}

		return $this;
	}

	/**
	 * fetch users from a conversation via "early access" v2 API (WIP)
	 */
/*	public function fromConversation(string $statusURL, bool $enforceLimit = null):TERFBLOCKER5000{
		[$screen_name, $id] = $this::parseTwitterURL($statusURL);

		// temp workaround as the v2 endpoints are not yet implemented in oauth-providers
		$r1 = $this->twitter->sendRequest($this->requestFactory->createRequest('GET', 'https://api.twitter.com/2/tweets?ids='.$id.'&tweet.fields=conversation_id'));

		if($r1->getStatusCode() === 200){
			$json = get_json($r1);

			if(isset($json->data[0])){
				$r2 = $this->twitter->sendRequest($this->requestFactory->createRequest('GET', 'https://api.twitter.com/2/tweets/search/recent?query=conversation_id:'.$json->data[0]->conversation_id));

				// -> unauthorized (waiting for v2 developer account approval)
				var_dump(get_json($r2));

			}
		}

		return $this;
	}
*/

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
	protected function getUsersFromV1SearchTweets(array $params, string $statusID = null, bool $enforceLimit = null):void{

		while(true){
			$response = $this->twitter->searchTweets($params);

			if($response->getStatusCode() !== 200){
				break;
			}

			$json = get_json($response);

			if(!isset($json->statuses)){
				break;
			}

			$users = [];

			foreach($json->statuses as $tweet){

				if(!isset($tweet->user) || (!empty($statusID) && (!isset($tweet->in_reply_to_status_id) || $tweet->in_reply_to_status_id_str !== $statusID))){
					continue;
				}

				$users[$tweet->user->id_str] = $tweet->user;
			}

			$this->detect($users);

			if(!isset($json->search_metadata, $json->search_metadata->next_results) || empty($json->search_metadata->next_results)){
				break;
			}

			$params = Query::parse($json->search_metadata->next_results);

			if($enforceLimit){
				// sleep for 5.1 seconds (API limit: 180requests/15min - too lazy to implement a token bucket...)
				usleep(5100000);
			}
		}

	}

	/**
	 * the detector executes the matcher(s) and assigns the matches to positive/negative lists
	 */
	protected function detect(array $users):void{

		foreach($users as $user){
			if($this->match($user->name, $user->description)){
				$this->positive[$user->id] = $user;
			}
			else{
				$this->negative[$user->id] = $user;
			}
		}

	}

	/**
	 * simple and cheap matching against a list of terms - no regex cannons fired (WIP)
	 */
	protected function match(string $name, string $bio):bool{

		if(empty($this->any) && empty($this->all)){
			throw new InvalidArgumentException;
		}

		$s = ['"', '\'', '-', '/', '\\'];
		$r = [ '',   '', ' ', ' ',  ' '];

		$name = str_replace($s, $r, $name);
		$bio  = str_replace($s, $r, $bio);

		foreach($this->any as $term){
			if(mb_strpos($name."\x00\x00\x00\x00".$bio, $term) !== false){
				return true;
			}
		}

		foreach([$name, $bio] as $str){

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
	protected function getIDs(string $endpointMethod, array $params, bool $enforceLimit = null):array{

		if(!in_array($endpointMethod, ['blocksIds', 'followersIds', 'statusesRetweetersIds'])){
			throw new InvalidArgumentException;
		}

		$params = array_merge(['cursor' => -1, 'stringify_ids' => 'true'], $params);
		$ids    = [];

		while(true){
			$response = $this->twitter->{$endpointMethod}($params);

			if($response->getStatusCode() !== 200){
				break;
			}

			$json = get_json($response);

			if(isset($json->ids)){
				$ids = array_merge($ids, $json->ids);
			}

			if(empty($json->next_cursor_str)){
				break;
			}

			$params['cursor'] = $json->next_cursor_str;

			if($enforceLimit){
				sleep(61); // take a looong break (15requests/15min)
			}
		}

		return $ids;
	}

	/**
	 * fetches user profiles from the given list of IDs and runs the detector on them
	 *
	 * @see https://developer.twitter.com/en/docs/twitter-api/v1/accounts-and-users/follow-search-get-users/api-reference/get-users-lookup
	 */
	protected function getUserProfiles(array $ids):array{
		$chunks = array_chunk($ids, 100);
		$users  = [];

		foreach($chunks as $chunk){

			$params = [
				'user_id'          => implode(',', $chunk),
				'include_entities' => 'false',
			];

			$response = $this->twitter->usersLookup($params);

			if($response->getStatusCode() !== 200){
				continue; // i don't care
			}

			$users = array_merge($users, get_json($response));

			usleep(1100000); // sleep for 1.1 seconds (900requests/15min)
		}

		return $users;
	}

	/**
	 * performs the block operation, retries up to 3 times on non-200 responses
	 *
	 * @see https://developer.twitter.com/en/docs/twitter-api/v1/accounts-and-users/mute-block-report-users/api-reference/post-blocks-create
	 */
	protected function performBlock(array $blocklist):void{

		if(empty($blocklist)){
			throw new InvalidArgumentException;
		}

		$blocklist = array_values($blocklist);

		while(!empty($blocklist)){
			$user          = array_shift($blocklist);
			$user['retry'] = 0;

			if(!isset($user['screen_name']) || !isset($user['id'])){
				continue;
			}

			$params = [
				'screen_name'      => $user['screen_name'] ?? null,
				'user_id'          => $user['id'] ?? null,
				'include_entities' => 'false',
				'skip_status'      => 'true',
			];

			$response = $this->twitter->block($params);

			if($response->getStatusCode() !== 200){
				$user['retry']++;

				if($user['retry'] < 3){
					$blocklist[] = $user;
				}

				continue;
			}

			// since the API doesn't reurn a success, just the user object (which contains "muting" but not "blocked")
			// there's no point in doing anything with the response...

			usleep(250000); // v1 API docs say the block endpoint has a limit, but apparently it doesn't :)
#			sleep(20); // v2: 50requests/15min - for serious, twitter??? (hope the same as v1)
		}

	}

	/**
	 * minifies the user objects and saves the data to the given file
	 */
	protected function saveToJson(array $users, string $filename):void{
		$data = [];

		foreach($users as $user){
			$data[] = [
				'screen_name'     => $user->screen_name,
				'id'              => $user->id,
				'name'            => $user->name,
				'description'     => $user->description,
				'location'        => $user->location,
				'followers_count' => $user->followers_count,
				'created_at'      => $user->created_at,
			];
		}

		$data = str_replace('    ', "\t", json_encode($data, JSON_BIGINT_AS_STRING | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
		file_put_contents($filename, $data);
	}

}
