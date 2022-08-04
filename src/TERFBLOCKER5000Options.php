<?php
/**
 * Class TERFBLOCKER5000Options
 *
 * @created      30.09.2021
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2021 smiley
 * @license      MIT
 */

namespace codemasher\TERFBLOCKER5000;

use chillerlan\Database\DatabaseOptionsTrait;
use chillerlan\OAuth\OAuthException;
use chillerlan\OAuth\OAuthOptions;
use function preg_match;
use function sodium_hex2bin;
use function strlen;
use const SODIUM_CRYPTO_SECRETBOX_KEYBYTES;
use const SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;

/**
 * @property bool   $haltOnError
 * @property string $table_token
 * @property string $table_scan_jobs
 * @property string $table_profiles
 * @property string $table_blocklist
 * @property string $table_block_always
 * @property string $table_block_never
 *
 * @property string $table_log
 * @property string $loglevel
 * @property int    $storageCacheTTL
 * @property bool   $storageEncryption
 * @property string $storageCryptoKey
 * @property string $storageCryptoNonce
 */
class TERFBLOCKER5000Options extends OAuthOptions{
	use DatabaseOptionsTrait;

	protected bool   $haltOnError        = true;
	protected string $table_token        = 'terfblocker5000_tokens';
	protected string $table_scan_jobs    = 'terfblocker5000_scan_jobs';
	protected string $table_profiles     = 'terfblocker5000_profiles';
	protected string $table_blocklist    = 'terfblocker5000_blocklist';
	protected string $table_block_always = 'terfblocker5000_block_always';
	protected string $table_block_never  = 'terfblocker5000_block_never';

	protected string $table_log          = 'terfblocker5000_log';
	protected string $loglevel           = 'none';
	protected int    $storageCacheTTL    = 300;
	protected bool   $storageEncryption  = true;
	protected string $storageCryptoKey;
	protected string $storageCryptoNonce = "\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f\x10\x11\x12\x13\x14\x15\x16\x17\x18";

	protected string $cronUser           = '';
	protected int    $sleepTimer         = 60;

	/**
	 * @throws \chillerlan\OAuth\OAuthException
	 */
	protected function set_storageCryptoKey(string $key):void{

		if(preg_match('/[a-f\d]+/i', $key)){
			$key = sodium_hex2bin($key);
		}

		if(strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES){
			throw new OAuthException('invalid sodium cryptobox key');
		}

		$this->storageCryptoKey = $key;
	}

	/**
	 * @throws \chillerlan\OAuth\OAuthException
	 */
	protected function set_storageCryptoNonce(string $nonce):void{

		if(preg_match('/[a-f\d]+/i', $nonce)){
			$nonce = sodium_hex2bin($nonce);
		}

		if(strlen($nonce) !== SODIUM_CRYPTO_SECRETBOX_NONCEBYTES){
			throw new OAuthException('invalid sodium cryptobox nonce');
		}

		$this->storageCryptoKey = $nonce;
	}

}
