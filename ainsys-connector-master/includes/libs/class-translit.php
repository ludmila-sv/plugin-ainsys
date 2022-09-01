<?php

namespace Ainsys\Connector\Master\Libs;

/**
 * Not used yet, but you can use it if necessary.
 */
class Translit {
	/**
	 * Translation of russian letters into latin.
	 *
	 * @param string $str String to covert.
	 *
	 * @return string converted string.
	 */
	public static function cyr_to_lat( string $str ) {
		$str = (string) $str;
		$str = trim( $str );
		$str = function_exists( 'mb_strtolower' ) ? mb_strtolower( $str ) : strtolower( $str );

		return strtr( $str, array(
			'а' => 'a',
			'б' => 'b',
			'в' => 'v',
			'г' => 'g',
			'д' => 'd',
			'е' => 'e',
			'ё' => 'e',
			'ж' => 'j',
			'з' => 'z',
			'и' => 'i',
			'й' => 'y',
			'к' => 'k',
			'л' => 'l',
			'м' => 'm',
			'н' => 'n',
			'о' => 'o',
			'п' => 'p',
			'р' => 'r',
			'с' => 's',
			'т' => 't',
			'у' => 'u',
			'ф' => 'f',
			'х' => 'h',
			'ц' => 'c',
			'ч' => 'ch',
			'ш' => 'sh',
			'щ' => 'shch',
			'ы' => 'y',
			'э' => 'e',
			'ю' => 'yu',
			'я' => 'ya',
			'ъ' => '',
			'ь' => '',
			' ' => '_'
		) );
	}
}