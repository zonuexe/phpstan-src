<?php // lint >= 8.1

declare(strict_types = 1);

namespace ClosureBindScopeAmbiguous;

use Closure;
use function PHPStan\Testing\assertType;

class Base
{

	public static function make(): static
	{
		return new static(); // @phpstan-ignore new.static
	}

	public static function base(): self
	{
		return new self();
	}

}

class Foo extends Base
{

	public const A = 'Foo';

}

class Bar extends Base
{

	public const A = 'Bar';

}

enum Suit: string
{

	case Hearts = 'H';

}

/**
 * @param class-string<Foo>|class-string<Bar> $withAncestor
 * @param class-string<Foo>|class-string<Suit> $noAncestor
 * @param class-string $plain
 */
function doFoo(string $withAncestor, string $noAncestor, string $plain, string $str): void
{
	// bound to one of several classes: self/static is the closest class they all extend
	assertType('static(ClosureBindScopeAmbiguous\Base)', Closure::bind(static fn () => static::make(), null, $withAncestor)());
	assertType('ClosureBindScopeAmbiguous\Base', Closure::bind(static fn () => self::make(), null, $withAncestor)());
	assertType('ClosureBindScopeAmbiguous\Base', Closure::bind(static fn () => self::base(), null, $withAncestor)());

	// ...and without one, or when the class is unknown, nothing is known about it
	assertType('*ERROR*', Closure::bind(static fn () => self::A, null, $withAncestor)());
	assertType('*ERROR*', Closure::bind(static fn () => self::Hearts, null, $noAncestor)());
	assertType('*ERROR*', Closure::bind(static fn () => self::A, null, $plain)());
	assertType('*ERROR*', Closure::bind(static fn () => self::A, null, $str)()); // @phpstan-ignore argument.type
}
