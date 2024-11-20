<?php // lint >= 8.0

namespace UppercaseStringSubstr;

use function PHPStan\Testing\assertType;

class Foo
{

	/**
	 * @param uppercase-string $uppercase
	 */
	public function doSubstr(string $uppercase): void
	{
		assertType('uppercase-string', substr($uppercase, 5));
		assertType('uppercase-string', substr($uppercase, -5));
		assertType('uppercase-string', substr($uppercase, 0, 5));
	}

	/**
	 * @param uppercase-string $uppercase
	 */
	public function doMbSubstr(string $uppercase): void
	{
		assertType('uppercase-string', mb_substr($uppercase, 5));
		assertType('uppercase-string', mb_substr($uppercase, -5));
		assertType('uppercase-string', mb_substr($uppercase, 0, 5));
	}

}
