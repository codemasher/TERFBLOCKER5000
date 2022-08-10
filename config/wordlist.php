<?php
/**
 * List of terms to match against.
 *
 * All terms will be lowercased internally and space-free "hashtag" like copies will be created.
 * The subject string will be filtered before matching, stripping whitespace and the following characters:
 *
 *     . , - / \ • " ' |    (subject to change)
 *
 * This means these characters should not appear in a search term. For terms containing the number sign (hash #),
 * an extra term will be created with the # removed if it's not the first character of the term.
 *
 * Simple strings will be considered as "any", which means that any occurence in either name, bio or location will match.
 * Arrays are considered as "all", meaning that the subject must contain all elements in any order to match.
 *
 * The format of the array is as follows:
 *
 *     // collection
 *     Array(
 *         // blocktype 0
 *         0 => Array(
 *             Array(all, ...),
 *             any,
 *             any,
 *         ),
 *         // blocktype 1
 *         1 => Array(
 *             ...
 *         ),
 *         ...
 *     )
 *
 * The numerical index of the collection will be used internally to assign the blocktypes
 * and is stored in the blocklist table (default: -1).
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
	/*
	 * TERFs
	 */
	0 => [
		// note: the heart emojis may cause too many false positives
		// former genderqueer flag, suffragette colours co-opted by terfs (green/white/purple)
#		['💚', '🤍', '💜'], // d83d dc9a, d83e dd0d, d83d dc9c (hearts)
		['💚', '💟', '💜'],
		['🟩', '⬜️', '🟪'], // d83d dfe9, 2b1c fe0f, d83d dfea (squares)
		['🟩', '⬜', '🟪'],
		['🟢', '⚪️', '🟣'], // d83d dfe2, 26aa fe0f, d83d dfe3 (circle)

		['🟥', '🦖'],
		['🔴', '🦖'],
		['🚩', '🦖'],

		// "superstraight" (black/orange)
		['🟧', '⬛️'], // d83d dfe7, 2b1b fe0f (squares)
		['🟠', '⚫️'], // d83d dfe0, 26ab fe0f (circle)

		'💚🤍💜',
		'💚💟💜',
		'🟢⚪️🟣',
		'🟩⬜️🟪',
		'🟪⬜🟩',
		'⬛🟧️',
		'⚫🟠️',
		// words/terms
		'GC Feminist',
		'rad fem',
		'radical feminist',
		'gender crit',
		'gen crit',
		'human female',
		'human male',
		'female human',
		'female woman',
		'shrill siren',
		'cis is a slur',
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
		'No To Self ID',
		'No Self ID',
		'stop Self ID',
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
		'LGB Terf',
		'Gender cynic',
		'Chromos',
		'Team TERF',
		'No to Stonewall',
		'Stonewall Out',
		'Defund Stonewall',
		'cis sexual',
		'cis romantic',
		'single sex space',
		'body with vagina',
		'Woman Is Not a Feeling',
		'Woman Is Not An Identity',
		'female is not a feeling',
		'Women Are Born Not Worn',
		'trans widow',
		'same sex attracted',
		'stop with the cis',
		'feminazi',
		'feminism that centers women',
		'Sexo No Es Genero',
		'Gay not CIS',
		'Lesbian not CIS',
		'Lesbian Not Queer',
		'Gay Not Queer',
		'Identifies as Attack Helicopter',
		'Trans Identified',
		'Sex based',
		'Sex is real',
		'They Call Me Terf',
		'Terfragette',
		'Erasing women',
		'Lesbian erasure',
		'Gender Woo Woo',
		'Febfem',
		'Troon',
		'Straight pride',
		'Drop The T',
		'Drop The L',
		'Drop The B',
		'LGB drop the T',
		'AFAB transwoman',
		'Fuck Your Pronouns',
		'against groomers',
		'Dont cis me',
		'Cult of Gender',
		'Gender Cult',
		'Feminista radical',
		'Contragender',
		'#LGBA',
		'The Earth is Round Fanclub',
		'Men Are Not Women',
		'cannot change sex',
		'genderist fundamentalism', // lol

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
		'Dr Kathleen Stock',

		// german TERFs
		'Team Biologie',
		'Team Realität',
		'Team Frauenrechte',
		'Frauen Nicht FLINTA',
		'Nicht Cis',
		'lesbisch nicht queer',
		'schwul nicht queer',
		'Radikalfeminist',
		'Bio Frau',
		'gender gaga',
		'Geschlecht', // should be enough
		'Faktenbasiert', // no normal person puts that in their bio
		'Genderideologie',
		'Gendern',
		'Trans Aktivismus',
		'Anti Gender',
		'Gefühle sind keine Fakten',
		'Weiblichkeit ist kein Gefühl',
		'Erwachsener Mensch weiblichen Geschlechts',

		// may cause too many false positives
#		['🧡', '🖤'], // d83e dde1, d83d dda4 (hearts)
#		'🦖', // the dinosaur emoji is trans https://twitter.com/courtneymilan/status/1450166274714062851
		'🥝', // now also the fucking kiwis?? poor new zealanders
#		'GC', // too ambiguous
#		'TERF', // too ambiguous
#		'RWDS', // too ambiguous
#		'Super Gay', // -> "Super gay trans woman", "super gay for pretty girls"
#		'Super Bi', // -> "Superbike"
		'Gender free',
		'No Thank You',
#		'Science matters',
#		'Believe in science',
#		'Kinderrechte',
	],

	/*
	 * Nazis, MAGA, GG, and other far-right goons.
	 */
	1 => [
		// throw in some magahats etc for good measure
		'#MAGA',
		'Make America Great Again',
		'America First',
		'Trump 202',
		'Trump Train',
		'Trump won',
		'Trump Lover', // eww
		'for Trump',
		'pro Trump',
		'@realDonaldTrump',
		'Trump Was Right',
		'Cult45',
		'Build The Wall',
		'Drain The Swamp',
		'deplorable',
		'China Lied People Died',
		'right winger',
		'right leaning',
		'alt right',
		'All Lives Matter',
		'Blue Lives Matter',
		'No White Guilt',
		'Its OK To Be White',
		'Save White Culture',
		'Patriotic Alternative',
		'White Lives Matter',
		'white genocide',
		'white guilt',
		'white sharia',
		'Trump wave',
		'Cultural Marxism',
		'Traditional Wife',
		'TradWife',
		'unite the right',
		'Not Woke',
		'Anti woke',
		'GETTR',
		'1776',
		'Ultra MAGA',
		'Dark MAGA',
		'Ammosexual',
		'globohomo',
		'Kekistan',
		'Meme War Veteran',

		// i think we can safely add brexiters here
		'GB News',
		'Pro Brexit',
		'Brexiteer',
		'Make Britain British Again',

		// german
		'nicht woke',
		'wertkonservativ',
		'Nationalkonservativ',

		// may cause too many false positives
#		'Love Trump', // -> "love trumps hate"
#		'Republican', // -> "former/ex republican", "Republicans are awful"
#		'Trump Supporter', // -> "former trump supporter"
#		'Trump Follower',
#		'Conservative', // -> "enemy of the Conservative state", "Anti Death Cultist (Nazi/Terf/Conservative)"
	],

	/*
	 * generally unpleasant: zionists, science deniers (WIP)
	 * for some reason there's a huge overlap with the previous list...
	 */
	2 => [
		// zionists
		['🇩🇪', '🇮🇱'],
		['🇺🇸', '🇮🇱'],
		'Zionist',

		// gun-toting hip gangster wannabes
		'pro gun',
		'pro 2A',
		'#2A',
		'2nd Amendment',
		'#NRA',
		'ΜΟΛΩΝ ΛΑΒΕ',

		// climate change deniers
		'Climate Hoax',

		// antivaxxers
		'Pure Blood',
		'unvaxxed',
		'unmasked',
		'anti mask',
		'No Vaccine Passport',

		// anti-abortion
		'Pro Life', // may cause false positive -> proliferation

		// anti sex work
		'anti sex work',
		'sex work critical',
		'anti porn',

		// german hohlnüsse
		'Gegen jeden Extremismus',
		'Sprachpolizei',
		'Verbotswahn',
		'multikulti',

	],

	/*
	 * crypto bros (because it's 2022 and i have no chill left anymore)
	 */
	3 => [
		'web 3',
		'blockchain',
		'bitcoin',
		'dogecoin',
		'dogebnb',
		'memecoin',
		'ethereum',
		'binance',
		'coinbase',
		'opensea',
		'.eth',
		'.btc',
		'.sol',
		'#NFT',
		'NFT advis',
		'NFT art',
		'NFT com',
		'NFT collect',
		'NFT creat',
		'NFT expert',
		'NFT game',
		'NFT music',
		'NFT project',
		'NFT tech',
		'music NFT',
		'women in crypto', // why??
		'women in web3',
		'crypto art',
		'crypto twitter',
		'crypto enthusiast',
		'crypto invest',
		'HODL',
		'BAYC',
		'bored ape',
		'$BTC',
		'$SOL',
		'$APE',
		'$HNT',
		'$FWB',
		'minted',
		'minting',

		// may cause too many false positives
#		'DAOs',
#		'crypto', // too ambiguous
#		'NFTs',
#		'BTC',
#		'ETH',
	],

];
