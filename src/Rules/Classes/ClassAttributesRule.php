<?php declare(strict_types = 1);

namespace PHPStan\Rules\Classes;

use Attribute;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\AttributesCheck;
use PHPStan\Rules\Rule;

/**
 * @implements Rule<InClassNode>
 */
final class ClassAttributesRule implements Rule
{

	public function __construct(private AttributesCheck $attributesCheck)
	{
	}

	public function getNodeType(): string
	{
		return InClassNode::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		$classLikeNode = $node->getOriginalNode();

		return $this->attributesCheck->check(
			$scope,
			$classLikeNode->attrGroups,
			Attribute::TARGET_CLASS,
			'class',
		);
	}

}
