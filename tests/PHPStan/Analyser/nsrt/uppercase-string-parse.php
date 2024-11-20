<?php

namespace UppercaseStringParseStr;

use function PHPStan\Testing\assertType;

class Foo
{

	/**
	 * @param uppercase-string $uppercase
	 */
	public function parse(string $uppercase, string $string): void
	{
		$a = [];
		parse_str($uppercase, $a);

		if (array_key_exists('foo', $a)) {
			$value = $a['foo'];
			if (\is_string($value)) {
				assertType('uppercase-string', $value);
			}
		}

		$b = [];
		parse_str($string, $b);

		if (array_key_exists('foo', $b)) {
			$value = $b['foo'];
			if (\is_string($value)) {
				assertType('string', $value);
			}
		}
	}

}
