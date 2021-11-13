<?php
/**
 * @created      10.11.2021
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2021 smiley
 * @license      MIT
 */

/**
 * @var \codemasher\TERFBLOCKER5000\TERFBLOCKER5000 $terfblocker
 * @var \codemasher\TERFBLOCKER5000\TERFBLOCKER5000Options $options
 * @var \chillerlan\Database\Database $db
 * @var \Psr\Log\LoggerInterface $logger
 */

// receive a username to process from the command line
// the intend to run from CLI is to spawn multiple processes which we can't do from within vanilla php
preg_match('/^(?<screen_name>[a-z_\d]{1,20})$/i', $argv[1] ?? '', $matches);

if(empty($matches)){
	exit(1);
}

require_once __DIR__.'/common.php';

$terfblocker->setTokenFromScreenName($matches['screen_name']);
$terfblocker->block();
