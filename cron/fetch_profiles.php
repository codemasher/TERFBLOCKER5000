<?php
/**
 * This cron job fetches the user profile data (screen name, name, description, location, ...)
 * for profiles with "screen_name IS NULL" and saves it into the profile table.
 *
 * IDs for which no result is returned will be set to "screen_name  = ''" so that they
 * won't be included in future requests.
 *
 * @created      03.11.2021
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2021 smiley
 * @license      MIT
 */

/**
 * @var \codemasher\TERFBLOCKER5000\TERFBLOCKER5000 $terfblocker
 * @var \codemasher\TERFBLOCKER5000\TERFBLOCKER5000Options $options
 * @var \chillerlan\Database\Database $db
 * @var \Psr\Log\LoggerInterface $logger
 * @var string $CFGDIR
 */
require_once __DIR__.'/common.php';

$terfblocker->setTokenFromScreenName($options->cronUser);

while(true){

	$count = $db->select
		->cols(['id'])
		->from([$options->table_profiles])
		->where('screen_name', null, 'IS', false)
		->count();

	if($count === 0){
		$logger->info('0 rows found, going to sleep');

		sleep($options->sleepTimer);
		continue;
	}

	$logger->info($count.' rows left');

	// reload the wordlist on each run to allow changes during runtime
	$wordlist = require $CFGDIR.'/wordlist.php';

	$terfblocker
		->setWordlist($wordlist)
		->cronFetchProfiles();
}
