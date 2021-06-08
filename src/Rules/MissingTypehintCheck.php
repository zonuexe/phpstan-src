<?php declare(strict_types = 1);

namespace PHPStan\Rules;

use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\CallableType;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\Generic\TemplateType;
use PHPStan\Type\Generic\TemplateTypeHelper;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeTraverser;
use PHPStan\Type\TypeWithClassName;

class MissingTypehintCheck
{

	public const TURN_OFF_MISSING_ITERABLE_VALUE_TYPE_TIP = 'See: https://phpstan.org/blog/solving-phpstan-no-value-type-specified-in-iterable-type';

	public const TURN_OFF_NON_GENERIC_CHECK_TIP = 'You can turn this off by setting <fg=cyan>checkGenericClassInNonGenericObjectType: false</> in your <fg=cyan>%configurationFile%</>.';

	private const ITERABLE_GENERIC_CLASS_NAMES = [
		\Traversable::class,
		\Iterator::class,
		\IteratorAggregate::class,
		\Generator::class,
	];

	private \PHPStan\Reflection\ReflectionProvider $reflectionProvider;

	private bool $checkMissingIterableValueType;

	private bool $checkGenericClassInNonGenericObjectType;

	private bool $checkMissingCallableSignature;

	/** @var string[] */
	private array $skipCheckGenericClasses;

	private bool $deepInspectTypes;

	/**
	 * @param string[] $skipCheckGenericClasses
	 */
	public function __construct(
		ReflectionProvider $reflectionProvider,
		bool $checkMissingIterableValueType,
		bool $checkGenericClassInNonGenericObjectType,
		bool $checkMissingCallableSignature,
		array $skipCheckGenericClasses = [],
		bool $deepInspectTypes = false
	)
	{
		$this->reflectionProvider = $reflectionProvider;
		$this->checkMissingIterableValueType = $checkMissingIterableValueType;
		$this->checkGenericClassInNonGenericObjectType = $checkGenericClassInNonGenericObjectType;
		$this->checkMissingCallableSignature = $checkMissingCallableSignature;
		$this->skipCheckGenericClasses = $skipCheckGenericClasses;
		$this->deepInspectTypes = $deepInspectTypes;
	}

	/**
	 * @param \PHPStan\Type\Type $type
	 * @return \PHPStan\Type\Type[]
	 */
	public function getIterableTypesWithMissingValueTypehint(Type $type): array
	{
		if (!$this->checkMissingIterableValueType) {
			return [];
		}

		$iterablesWithMissingValueTypehint = [];
		TypeTraverser::map($type, function (Type $type, callable $traverse) use (&$iterablesWithMissingValueTypehint): Type {
			if ($type instanceof TemplateType) {
				return $type;
			}
			if ($type->isIterable()->yes()) {
				$iterableValue = $type->getIterableValueType();
				if ($iterableValue instanceof MixedType && !$iterableValue->isExplicitMixed()) {
					if (
						$type instanceof TypeWithClassName
						&& !in_array($type->getClassName(), self::ITERABLE_GENERIC_CLASS_NAMES, true)
						&& $this->reflectionProvider->hasClass($type->getClassName())
					) {
						$classReflection = $this->reflectionProvider->getClass($type->getClassName());
						if ($classReflection->isGeneric()) {
							return $type;
						}
					}
					$iterablesWithMissingValueTypehint[] = $type;
				}
				if ($this->deepInspectTypes) {
					return $traverse($type);
				}

				return $type;
			}
			return $traverse($type);
		});

		return $iterablesWithMissingValueTypehint;
	}

	/**
	 * @param \PHPStan\Type\Type $type
	 * @return array<int, array{string, string[]}>
	 */
	public function getNonGenericObjectTypesWithGenericClass(Type $type): array
	{
		if (!$this->checkGenericClassInNonGenericObjectType) {
			return [];
		}

		$objectTypes = [];
		TypeTraverser::map($type, function (Type $type, callable $traverse) use (&$objectTypes): Type {
			if ($type instanceof GenericObjectType) {
				$traverse($type);
				return $type;
			}
			if ($type instanceof TemplateType) {
				return $type;
			}
			if ($type instanceof ObjectType) {
				$classReflection = $type->getClassReflection();
				if ($classReflection === null) {
					return $type;
				}
				if (in_array($classReflection->getName(), self::ITERABLE_GENERIC_CLASS_NAMES, true)) {
					// checked by getIterableTypesWithMissingValueTypehint() already
					return $type;
				}
				if (in_array($classReflection->getName(), $this->skipCheckGenericClasses, true)) {
					return $type;
				}
				if ($classReflection->isTrait()) {
					return $type;
				}
				if (!$classReflection->isGeneric()) {
					return $type;
				}

				$resolvedType = TemplateTypeHelper::resolveToBounds($type);
				if (!$resolvedType instanceof ObjectType) {
					throw new \PHPStan\ShouldNotHappenException();
				}
				$objectTypes[] = [
					sprintf('%s %s', $classReflection->isInterface() ? 'interface' : 'class', $classReflection->getDisplayName(false)),
					array_keys($classReflection->getTemplateTypeMap()->getTypes()),
				];
				return $type;
			}

			return $traverse($type);
		});

		return $objectTypes;
	}

	/**
	 * @param \PHPStan\Type\Type $type
	 * @return \PHPStan\Type\Type[]
	 */
	public function getCallablesWithMissingSignature(Type $type): array
	{
		if (!$this->checkMissingCallableSignature) {
			return [];
		}

		$result = [];
		TypeTraverser::map($type, static function (Type $type, callable $traverse) use (&$result): Type {
			if (
				($type instanceof CallableType && $type->isCommonCallable()) ||
				($type instanceof ObjectType && $type->getClassName() === \Closure::class)) {
				$result[] = $type;
			}
			return $traverse($type);
		});

		return $result;
	}

}
