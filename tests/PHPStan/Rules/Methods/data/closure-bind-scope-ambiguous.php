<?php // lint >= 8.1

declare(strict_types = 1);

namespace ClosureBindScopeAmbiguousMethods;

use Closure;

class Base
{

}

class Foo extends Base
{

	protected static function sm(): void
	{
	}

}

class Bar extends Base
{

}

/**
 * @param class-string<Foo>|class-string<Bar> $withAncestor
 * @param class-string $plain
 */
function doFoo(string $withAncestor, string $plain): void
{
	// bound to a class that is not exactly one known class: nothing to check
	Closure::bind(static fn () => [self::sm(), static::sm(), parent::sm(), self::sm(...)], null, $withAncestor);
	Closure::bind(static fn () => [self::sm(), parent::sm(), self::sm(...)], null, $plain);

	// 'static' binds to no class here
	Closure::bind(static fn () => [self::sm(), self::sm(...)], null, 'static');
}
