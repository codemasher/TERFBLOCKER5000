<?php
/**
 * Class TERFBLOCKER5000TokenStorage
 *
 * @created      30.09.2021
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2021 smiley
 * @license      MIT
 */

namespace codemasher\TERFBLOCKER5000;

use chillerlan\Database\Database;
use chillerlan\OAuth\Core\AccessToken;
use chillerlan\OAuth\Storage\OAuthStorageAbstract;
use chillerlan\OAuth\Storage\OAuthStorageException;
use chillerlan\Settings\SettingsContainerInterface;
use Psr\Log\LoggerInterface;
use function extension_loaded;
use function is_bool;
use function sodium_bin2hex;
use function sodium_crypto_secretbox;
use function sodium_crypto_secretbox_open;
use function sodium_hex2bin;

class TERFBLOCKER5000TokenStorage extends OAuthStorageAbstract{

	protected Database $db;
	protected string $userID     = '';
	protected string $screenName = '';

	/**
	 * TERFBLOCKER5000TokenStorage constructor.
	 *
	 * @throws \chillerlan\OAuth\Storage\OAuthStorageException
	 */
	public function __construct(Database $db, SettingsContainerInterface $options, LoggerInterface $logger = null){

		if(!extension_loaded('sodium')){
			throw new OAuthStorageException('sodium extension missing');
		}

		parent::__construct($options, $logger);

		if(!isset($this->options->table_token)){
			throw new OAuthStorageException('invalid table config');
		}

		$this->db = $db;
		$this->db->connect();
	}

	/** @inheritDoc */
	public function storeAccessToken(string $service, AccessToken $token):bool{

		$values = [
			'screen_name' => $this->screenName,
			'token'       => $this->toStorage($token),
		];

		if($this->hasAccessToken($service)){
			return (bool)$this->db->update
				->table($this->options->table_token)
				->set($values)
				->where('user_id', $this->userID)
				->query();
		}

		$values['user_id'] = $this->userID;

		return (bool)$this->db->insert
			->into($this->options->table_token)
			->values($values)
			->query();
	}

	/** @inheritDoc */
	public function getAccessToken(string $service):AccessToken{

		$r = $this->db->select
			->cols(['token'])
			->from([$this->options->table_token])
			->where('user_id', $this->userID)
			->limit(1)
			->query();

		if(is_bool($r) || $r->length < 1){
			throw new OAuthStorageException('token not found');
		}

		return $this->fromStorage($r[0]->token);
	}

	/** @inheritDoc */
	public function hasAccessToken(string $service):bool{
		return (bool)$this->db->select
			->cols(['token'])
			->from([$this->options->table_token])
			->where('user_id', $this->userID)
			->limit(1)
			->count();
	}

	/** @inheritDoc */
	public function clearAccessToken(string $service):bool{
		return $this->clearAllAccessTokens();
	}

	/** @inheritDoc */
	public function clearAllAccessTokens():bool{
		return (bool)$this->db->delete
			->from($this->options->table_token)
			->where('user_id', $this->userID)
			->query();
	}

	/** @inheritDoc */
	public function storeCSRFState(string $service, string $state):bool{
		throw new OAuthStorageException('not supported');
	}

	/** @inheritDoc */
	public function getCSRFState(string $service):string{
		throw new OAuthStorageException('not supported');
	}

	/** @inheritDoc */
	public function hasCSRFState(string $service):bool{
		throw new OAuthStorageException('not supported');
	}

	/** @inheritDoc */
	public function clearCSRFState(string $service):bool{
		throw new OAuthStorageException('not supported');
	}

	/** @inheritDoc */
	public function clearAllCSRFStates():bool{
		throw new OAuthStorageException('not supported');
	}

	/**
	 * @throws \Exception
	 */
	public function setUserID(string $userID, string $screenName):TERFBLOCKER5000TokenStorage{

		if(empty($userID) || empty($screenName)){
			throw new OAuthStorageException('invalid user id');
		}

		$this->userID     = $userID;
		$this->screenName = $screenName;

		return $this;
	}

	/** @inheritDoc */
	public function toStorage(AccessToken $token):string{
		$data = $token->toJSON();

		if($this->options->storageEncryption === true){
			return $this->encrypt($data);
		}

		return $data;
	}

	/** @inheritDoc */
	public function fromStorage($data):AccessToken{

		if($this->options->storageEncryption === true){
			$data = $this->decrypt($data);
		}

		/** @noinspection PhpIncompatibleReturnTypeInspection */
		return (new AccessToken)->fromJSON($data);
	}

	/**
	 *
	 */
	protected function encrypt(string $data):string {
		$box = sodium_crypto_secretbox($data, $this->options->storageCryptoNonce, $this->options->storageCryptoKey);

		return sodium_bin2hex($box);
	}

	/**
	 *
	 */
	protected function decrypt(string $box):string {
		return sodium_crypto_secretbox_open(sodium_hex2bin($box), $this->options->storageCryptoNonce, $this->options->storageCryptoKey);
	}

}
