<?php declare(strict_types=1);

namespace getoma\dbfe\Form\Generator\Node;

/**
 * This class can be used to forward multiple elements as a single node,
 * when they are not supposed to be an actual group.
 * TransparentGroup children are supposed to be flattened into the parent group
 * by the receiver.
 *
 * It is currently not used by DBFE itself, but my be handy in specific applications
 */
final class TransparentGroup extends CompositeNode
{
   public function __construct()
   {
      return parent::__construct("");
   }

   public function infer(array $values, array $errors = [], string $field_id = ''): array
   {
      throw new \LogicException("Transparent Group not correctly resolved before inferrence");
   }
}
