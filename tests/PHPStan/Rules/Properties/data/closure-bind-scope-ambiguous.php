<?php declare(strict_types = 1);

namespace ClosureBindScopeAmbiguousProperties;

use Closure;

class Base
{

}

class Foo extends Base
{

	/** @var int */
	protected static $sp = 1;

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
	Closure::bind(static fn () => [self::$sp, static::$sp, parent::$sp], null, $withAncestor);
	Closure::bind(static function (): void {
		self::$sp = 1;
		parent::$sp = 2;
	}, null, $plain);

	// 'static' binds to no class here
	Closure::bind(static fn () => self::$sp, null, 'static');
}
