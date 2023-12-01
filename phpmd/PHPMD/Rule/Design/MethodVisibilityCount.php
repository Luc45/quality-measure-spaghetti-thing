<?php

namespace Spaghetti\PHPMD\Rule\Design;

use PHPMD\AbstractNode;
use PHPMD\AbstractRule;
use PHPMD\Node\AbstractTypeNode;
use PHPMD\Rule\ClassAware;

/**
 * This rule class counts public, protected, and private methods in a class.
 */
class MethodVisibilityCount extends AbstractRule implements ClassAware
{
	public function apply(AbstractNode $node)
	{
		/** @var AbstractTypeNode $node */
		list($publicMethods, $protectedMethods, $privateMethods) = $this->countMethods($node);

		$this->addViolation(
			$node,
			array(
				$node->getName(),      // {0}
				$publicMethods,        // {1}
				$protectedMethods,     // {2}
				$privateMethods        // {3}
			)
		);
	}

	protected function countMethods(AbstractTypeNode $node)
	{
		$publicCount = $protectedCount = $privateCount = 0;

		foreach ($node->getMethods() as $method) {
			if ($method->getNode()->isPublic()) {
				++$publicCount;
			} elseif ($method->getNode()->isProtected()) {
				++$protectedCount;
			} elseif ($method->getNode()->isPrivate()) {
				++$privateCount;
			}
		}

		return array($publicCount, $protectedCount, $privateCount);
	}
}
