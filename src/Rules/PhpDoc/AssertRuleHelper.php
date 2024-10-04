<?php declare(strict_types = 1);

namespace PHPStan\Rules\PhpDoc;

use PhpParser\NodeAbstract;
use PHPStan\PhpDoc\Tag\AssertTag;
use PHPStan\Node\Expr\TypeExpr;
use PHPStan\Internal\SprintfHelper;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Reflection\InitializerExprContext;
use PHPStan\Reflection\InitializerExprTypeResolver;
use PHPStan\Reflection\ParametersAcceptor;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\ClassNameCheck;
use PHPStan\Rules\ClassNameNodePair;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ErrorType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\VerbosityLevel;
use function array_key_exists;
use function sprintf;
use function substr;

final class AssertRuleHelper
{

	public function __construct(
		private InitializerExprTypeResolver $initializerExprTypeResolver,
		private UnresolvableTypeHelper $unresolvableTypeHelper,
		private ClassNameCheck $classCheck,
		private ReflectionProvider $reflectionProvider,
	)
	{
	}

	/**
	 * @return list<IdentifierRuleError>
	 */
	public function check(ExtendedMethodReflection|FunctionReflection $reflection, ParametersAcceptor $acceptor): array
	{
		$parametersByName = [];
		foreach ($acceptor->getParameters() as $parameter) {
			$parametersByName[$parameter->getName()] = $parameter->getType();
		}

		if ($reflection instanceof ExtendedMethodReflection) {
			$class = $reflection->getDeclaringClass();
			$parametersByName['this'] = new ObjectType($class->getName(), null, $class);
		}

		$context = InitializerExprContext::createEmpty();

		$errors = [];
		foreach ($reflection->getAsserts()->getAll() as $assert) {
			$parameterName = substr($assert->getParameter()->getParameterName(), 1);
			if (!array_key_exists($parameterName, $parametersByName)) {
				$errors[] = RuleErrorBuilder::message(sprintf('Assert references unknown parameter $%s.', $parameterName))
					->identifier('parameter.notFound')
					->build();
				continue;
			}

			if (!$assert->isExplicit()) {
				continue;
			}

			$assertType = $assert->getType();
			$tagName = [
				AssertTag::NULL => '@phpstan-assert',
				AssertTag::IF_TRUE => '@phpstan-assert-if-true',
				AssertTag::IF_FALSE => '@phpstan-assert-if-false',
			][$assert->getIf()];

			if ($this->unresolvableTypeHelper->containsUnresolvableType($assertType)) {
				$errors[] = RuleErrorBuilder::message(sprintf(
					'PHPDoc tag %s for parameter $%s contains unresolvable type.',
					$tagName,
					$parameterName,
				))->identifier('parameter.unresolvableType')->build();

				continue;
			}

			foreach ($assertType->getReferencedClasses() as $class) {
				if (!$this->reflectionProvider->hasClass($class)) {
					$errors[] = RuleErrorBuilder::message(sprintf('PHP tag %s for parameter $%s of contains invalid type %s', $tagName, $parameterName, $class))
						->identifier('class.notFound')
						->build();
					continue 2;
				}

				$classReflection = $this->reflectionProvider->getClass($class);
				if ($classReflection->isTrait()) {
					$errors[] = RuleErrorBuilder::message(sprintf('PHP tag %s for parameter $%s of contains invalid type %s', $tagName, $parameterName, $class))
						->identifier('parameter.trait')
						->build();
					continue 2;
				}

				$errors = array_merge(
					$errors,
					$this->classCheck->checkClassNames([
						new ClassNameNodePair($class, new class () extends NodeAbstract {
							public function __construct()
							{
								parent::__construct();
							}

							public function getType() : string
							{
								return 'Dummy';
							}

							/** @return array<mixed> */
							public function getSubNodeNames() : array
							{
								return [];
							}
						}),
					], true),
				);
			}

			$assertedExpr = $assert->getParameter()->getExpr(new TypeExpr($parametersByName[$parameterName]));
			$assertedExprType = $this->initializerExprTypeResolver->getType($assertedExpr, $context);
			if ($this->unresolvableTypeHelper->containsUnresolvableType($assertedExprType)) {
				continue;
			}

			$assertedType = $assert->getType();

			$isSuperType = $assertedType->isSuperTypeOf($assertedExprType);
			if ($isSuperType->maybe()) {
				continue;
			}

			$assertedExprString = $assert->getParameter()->describe();

			if ($assert->isNegated() ? $isSuperType->yes() : $isSuperType->no()) {
				$errors[] = RuleErrorBuilder::message(sprintf(
					'Asserted %stype %s for %s with type %s can never happen.',
					$assert->isNegated() ? 'negated ' : '',
					$assertedType->describe(VerbosityLevel::precise()),
					$assertedExprString,
					$assertedExprType->describe(VerbosityLevel::precise()),
				))->identifier('assert.impossibleType')->build();
			} elseif ($assert->isNegated() ? $isSuperType->no() : $isSuperType->yes()) {
				$errors[] = RuleErrorBuilder::message(sprintf(
					'Asserted %stype %s for %s with type %s does not narrow down the type.',
					$assert->isNegated() ? 'negated ' : '',
					$assertedType->describe(VerbosityLevel::precise()),
					$assertedExprString,
					$assertedExprType->describe(VerbosityLevel::precise()),
				))->identifier('assert.alreadyNarrowedType')->build();
			}
		}

		return $errors;
	}

}
