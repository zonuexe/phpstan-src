<?php

namespace LowercaseStringReplace;

use function PHPStan\Testing\assertType;

class ReplaceStrings
{

	/**
	 * @param string $s
	 * @param lowercase-string $ls
	 */
	public function doFoo(string $s, string $ls): void
	{
		assertType('lowercase-string', str_replace($s, $ls, $ls));
		assertType('string', str_replace($s, $s, $ls));
		assertType('string', str_replace($s, $ls, $s));
		assertType('lowercase-string', str_replace($ls, $ls, $ls));
		assertType('string', str_replace($ls, $s, $ls));
		assertType('string', str_replace($ls, $ls, $s));

		assertType('lowercase-string', str_ireplace($s, $ls, $ls));
		assertType('string', str_ireplace($s, $s, $ls));
		assertType('string', str_ireplace($s, $ls, $s));
		assertType('lowercase-string', str_ireplace($ls, $ls, $ls));
		assertType('string', str_ireplace($ls, $s, $ls));
		assertType('string', str_ireplace($ls, $ls, $s));

		assertType('lowercase-string|null', preg_replace($s, $ls, $ls));
		assertType('string|null', preg_replace($s, $s, $ls));
		assertType('string|null', preg_replace($s, $ls, $s));
		assertType('lowercase-string|null', preg_replace($ls, $ls, $ls));
		assertType('string|null', preg_replace($ls, $s, $ls));
		assertType('string|null', preg_replace($ls, $ls, $s));

		assertType('lowercase-string', substr_replace($ls, $ls, 1));
		assertType('string', substr_replace($s, $ls, 1));
		assertType('string', substr_replace($ls, $s, 1));
		assertType('string', substr_replace($s, $s, 1));

		assertType('lowercase-string', strtr($ls, $s, $ls));
		assertType('string', strtr($ls, $s, $s));
		assertType('lowercase-string', strtr($ls, [$s => $ls]));
		assertType('string', strtr($ls, [$s => $s]));

		assertType('lowercase-string', mb_ereg_replace($s, $ls, $ls));
		assertType('string', mb_ereg_replace($s, $s, $ls));

		$f = function (array $matches): string {
			return strtolower($matches[0]);
		};
		assertType('lowercase-string|null', preg_replace_callback($s,$f, $ls));
		assertType('lowercase-string', mb_ereg_replace_callback($s, $f, $ls));
		assertType('lowercase-string|null', preg_replace_callback($s, 'strtolower', $ls));
		assertType('lowercase-string', mb_ereg_replace_callback($s, 'strtolower', $ls));
		assertType('lowercase-string|null', preg_replace_callback_array([$s => $f], $ls));
	}

}
