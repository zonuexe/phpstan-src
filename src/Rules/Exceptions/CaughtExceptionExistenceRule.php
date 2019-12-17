<?php declare(strict_types = 1);

namespace PHPStan\Rules\Exceptions;

use PhpParser\Node;
use PhpParser\Node\Stmt\Catch_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\ClassCaseSensitivityCheck;
use PHPStan\Rules\ClassNameNodePair;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements \PHPStan\Rules\Rule<\PhpParser\Node\Stmt\Catch_>
 */
class CaughtExceptionExistenceRule implements \PHPStan\Rules\Rule
{

	/** @var \PHPStan\Reflection\ReflectionProvider */
	private $reflectionProvider;

	/** @var \PHPStan\Rules\ClassCaseSensitivityCheck */
	private $classCaseSensitivityCheck;

	/** @var bool */
	private $checkClassCaseSensitivity;

	public function __construct(
		ReflectionProvider $reflectionProvider,
		ClassCaseSensitivityCheck $classCaseSensitivityCheck,
		bool $checkClassCaseSensitivity
	)
	{
		$this->reflectionProvider = $reflectionProvider;
		$this->classCaseSensitivityCheck = $classCaseSensitivityCheck;
		$this->checkClassCaseSensitivity = $checkClassCaseSensitivity;
	}

	public function getNodeType(): string
	{
		return Catch_::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		$errors = [];
		foreach ($node->types as $class) {
			$className = (string) $class;
			if (!$this->reflectionProvider->hasClass($className)) {
				$errors[] = RuleErrorBuilder::message(sprintf('Caught class %s not found.', $className))->line($class->getLine())->build();
				continue;
			}

			$classReflection = $this->reflectionProvider->getClass($className);
			if (!$classReflection->isInterface() && !$classReflection->getNativeReflection()->implementsInterface(\Throwable::class)) {
				$errors[] = RuleErrorBuilder::message(sprintf('Caught class %s is not an exception.', $classReflection->getDisplayName()))->line($class->getLine())->build();
			}

			if (!$this->checkClassCaseSensitivity) {
				continue;
			}

			$errors = array_merge(
				$errors,
				$this->classCaseSensitivityCheck->checkClassNames([new ClassNameNodePair($className, $class)])
			);
		}

		return $errors;
	}

}
