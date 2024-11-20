<?php

namespace UppercaseStringStrRepeat;

use function PHPStan\Testing\assertType;

class Foo
{

	/**
	 * @param uppercase-string $uppercase
	 */
	public function doRepeat(string $string, string $uppercase): void
	{
		assertType('uppercase-string', str_repeat($uppercase, 5));
		assertType('string', str_repeat($string, 5));
	}

}
