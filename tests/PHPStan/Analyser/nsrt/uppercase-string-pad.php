<?php

namespace UppercaseStringStrPad;

use function PHPStan\Testing\assertType;

class Foo
{

	/**
	 * @param uppercase-string $uppercase
	 */
	public function doRepeat(string $string, string $uppercase): void
	{
		assertType('non-empty-string', str_pad($string, 5));
		assertType('non-empty-string', str_pad($string, 5, $uppercase));
		assertType('non-empty-string', str_pad($string, 5, $string));
		assertType('non-empty-string&uppercase-string', str_pad($uppercase, 5));
		assertType('non-empty-string&uppercase-string', str_pad($uppercase, 5, $uppercase));
		assertType('non-empty-string', str_pad($uppercase, 5, $string));
	}

}
