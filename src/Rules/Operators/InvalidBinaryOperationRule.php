<?php declare(strict_types = 1);

namespace PHPStan\Rules\Operators;

use PhpParser\Node;
use PHPStan\Analyser\MutatingScope;
use PHPStan\Analyser\Scope;
use PHPStan\Node\Printer\ExprPrinter;
use PHPStan\Php\PhpVersion;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Rules\RuleLevelHelper;
use PHPStan\ShouldNotHappenException;
use PHPStan\TrinaryLogic;
use PHPStan\Type\ErrorType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\VerbosityLevel;
use function sprintf;
use function strlen;
use function substr;

/**
 * @implements Rule<Node\Expr>
 */
final class InvalidBinaryOperationRule implements Rule
{

	public function __construct(
		private ExprPrinter $exprPrinter,
		private PhpVersion $phpVersion,
		private RuleLevelHelper $ruleLevelHelper,
	)
	{
	}

	public function getNodeType(): string
	{
		return Node\Expr::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		if (
			!$node instanceof Node\Expr\BinaryOp
			&& !$node instanceof Node\Expr\AssignOp
		) {
			return [];
		}

		$leftName = '__PHPSTAN__LEFT__';
		$rightName = '__PHPSTAN__RIGHT__';
		$leftVariable = new Node\Expr\Variable($leftName);
		$rightVariable = new Node\Expr\Variable($rightName);
		if ($node instanceof Node\Expr\AssignOp) {
			$identifier = 'assignOp';
			$newNode = clone $node;
			$newNode->setAttribute('phpstan_cache_printer', null);
			$left = $node->var;
			$right = $node->expr;
			$newNode->var = $leftVariable;
			$newNode->expr = $rightVariable;
		} else {
			$identifier = 'binaryOp';
			$newNode = clone $node;
			$newNode->setAttribute('phpstan_cache_printer', null);
			$left = $node->left;
			$right = $node->right;
			$newNode->left = $leftVariable;
			$newNode->right = $rightVariable;
		}

		if ($node instanceof Node\Expr\AssignOp\Concat || $node instanceof Node\Expr\BinaryOp\Concat) {
			$callback = static fn (Type $type): bool => !$type->toString() instanceof ErrorType;
		} elseif ($node instanceof Node\Expr\AssignOp\Plus || $node instanceof Node\Expr\BinaryOp\Plus) {
			$callback = $this->phpVersion->supportsBcMathNumberOperatorOverloading()
				? static fn (Type $type): bool => !$type->toNumber() instanceof ErrorType || $type->isArray()->yes() || $type->isSuperTypeOf(new ObjectType('BcMath\Number'))->yes()
				: static fn (Type $type): bool => !$type->toNumber() instanceof ErrorType || $type->isArray()->yes();
		} else {
			$callback = $this->phpVersion->supportsBcMathNumberOperatorOverloading()
				? static fn (Type $type): bool => !$type->toNumber() instanceof ErrorType || $type->isSuperTypeOf(new ObjectType('BcMath\Number'))->yes()
				: static fn (Type $type): bool => !$type->toNumber() instanceof ErrorType;
		}

		$leftType = $this->ruleLevelHelper->findTypeToCheck(
			$scope,
			$left,
			'',
			$callback,
		)->getType();
		if ($leftType instanceof ErrorType) {
			return [];
		}

		$rightType = $this->ruleLevelHelper->findTypeToCheck(
			$scope,
			$right,
			'',
			$callback,
		)->getType();
		if ($rightType instanceof ErrorType) {
			return [];
		}

		if (!$scope instanceof MutatingScope) {
			throw new ShouldNotHappenException();
		}

		$scope = $scope
			->assignVariable($leftName, $leftType, $leftType, TrinaryLogic::createYes())
			->assignVariable($rightName, $rightType, $rightType, TrinaryLogic::createYes());

		if (!$scope->getType($newNode) instanceof ErrorType) {
			return [];
		}

		return [
			RuleErrorBuilder::message(sprintf(
				'Binary operation "%s" between %s and %s results in an error.',
				substr(substr($this->exprPrinter->printExpr($newNode), strlen($leftName) + 2), 0, -(strlen($rightName) + 2)),
				$scope->getType($left)->describe(VerbosityLevel::value()),
				$scope->getType($right)->describe(VerbosityLevel::value()),
			))
				->line($left->getStartLine())
				->identifier(sprintf('%s.invalid', $identifier))
				->build(),
		];
	}

}
