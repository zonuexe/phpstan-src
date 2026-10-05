<?php declare(strict_types = 1);

namespace PHPStan\Rules;

use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use function strtolower;

/**
 * Finds the class `self`, `static` and `parent` are relative to: the class a
 * `Closure::bind()` / `Closure::call()` scoped the closure to, or the enclosing class.
 */
final class SelfClassResolver
{

	/**
	 * Null when there is no such class (outside a class, and not inside a closure bound to
	 * a known class).
	 */
	public static function resolve(Scope $scope, ReflectionProvider $reflectionProvider): ?ClassReflection
	{
		if ($scope->isInClosureBind()) {
			// Scope::resolveName() follows the bound class, inside a class and outside one
			$className = $scope->resolveName(new Name('self'));
			if (strtolower($className) !== 'self' && $reflectionProvider->hasClass($className)) {
				return $reflectionProvider->getClass($className);
			}
		}

		return $scope->isInClass() ? $scope->getClassReflection() : null;
	}

}
