<?php

namespace Spaghetti\PHPMD\Rule\Design;

use PHPMD\AbstractNode;
use PHPMD\AbstractRule;
use PHPMD\Node\AbstractTypeNode;
use PHPMD\Rule\ClassAware;

/**
 * This rule class counts the number of static methods in a class.
 */
class StaticMethodCount extends AbstractRule implements ClassAware {
	public function apply( AbstractNode $node ) {
		/** @var AbstractTypeNode $node */
		$staticMethodsCount = $this->countStaticMethods( $node );

		$this->addViolation(
			$node,
			array(
				$node->getName(),      // {0}
				$staticMethodsCount    // {1}
			)
		);
	}

	protected function countStaticMethods( AbstractTypeNode $node ) {
		$count = 0;
		foreach ( $node->getMethods() as $method ) {
			if ( $method->getNode()->isStatic() ) {
				++ $count;
			}
		}

		return $count;
	}
}
