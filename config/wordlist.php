<?php
/**
 * List of terms to match against.
 * All of the terms will be lowercased internally and space-free "hashtag" like copies will be created.
 *
 * Simple strings will be considered as "any", which means that any occurence in either name or bio will match.
 * Arrays are considered as "all", meaning that either name or bio must contain all of the elements in any order to match.
 *
 * @see \mb_strpos()
 * @see \mb_strtolower()
 *
 * @created      26.09.2021
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2021 smiley
 * @license      MIT
 */

return [
	// note: the heart emojis may cause too many false positives
	// former genderqueer flag, suffragette colours co-opted by terfs (green/white/purple)
#	['💚', '🤍', '💜'], // d83d dc9a, d83e dd0d, d83d dc9c (hearts)
	['🟩', '⬜️', '🟪'], // d83d dfe9, 2b1c fe0f, d83d dfea (squares)
	['🟢', '⚪️', '🟣'], // d83d dfe2, 26aa fe0f, d83d dfe3 (circle)

	// "superstraight" (orange/black)
#	['🧡', '🖤'], // d83e dde1, d83d dda4 (hearts)
	['🟧', '⬛️'], // d83d dfe7, 2b1b fe0f (squares)
	['🟠', '⚫️'], // d83d dfe0, 26ab fe0f (circle)

	// words/terms
	'GC Feminist',
	'rad fem',
	'radical feminist',
	'gender crit',
	'adult human female',
	'adult human male',
	'adult female human',
	'shrill siren',
	'cis is a slur',
	'Gender free',
	'LGB Alliance',
	'Save Womens Sports',
	'Women Wont Wheesht',
	'Women Wont Weesht',
	'Women Wont Weest',
	'Sex Not Gender',
	'Wrong Crowd',
	'Biology Is Not Bigotry',
	'Detrans',
	'Super Straight',
	'Super Lesbian',
	'Super LGB',
	'No GRA Reform',
	'Repeal The GRA',
	'No To SelfID',
	'Sex Matters',
	'Biology matters',
	'No Men In Womens Sport',
	'Sex is Observed Not Assigned',
	'Feminists are Female',
	'Feminist are Female',
	'terf club',
	'LGB without the T',
	'#ROGD',
	'Gender Atheist',
	'Gender Ideology',
	'Sex is a binary',
	'Gender identity is a lie',
	'Womanhood is not a feeling',
	'gender logical',

	// "i stand with" - turns out that prefix is unnecessary...
	'JKR',
	'JK Rowling',
	'Glinner',
	'Posie Parker',
	'Rosie Duffield',
	'Marion Mill', // er/ar
	'Allison Bailey',
	'Maya Forstater',
	'Jess De Wahls',
	'Keira Bell',

	// throw in some magahats etc for good measure
	'#MAGA',
	'Make America Great Again',
	'America First',
	'Trump 202',
	'Trump Train',
	'Trump won',
	'Trump Follower',
	'Trump Lover', // eww
	'for Trump',
	'pro Trump',
	'pro gun',
	'Pro Life',
	'GB News',
	'Pro Brexit',
	'Make Britain British Again',
	'All Lives Matter',
	'Blue Lives Matter',

	// may cause false positives
#	'GC', // too ambiguous
#	'Love Trump', // -> "love trumps hate"
#	'Republican', // -> "former/ex republican", "Republicans are awful"
#	'Trump Supporter', // -> "former trump supporter"
#	'Conservative', // -> "enemy of the Conservative state", "Anti Death Cultist (Nazi/Terf/Conservative)"
#	'Super Gay', // -> "Super gay trans woman", "super gay for pretty girls"
#	'Super Bi', // -> "Superbike"
#	'No Thank You',
];
