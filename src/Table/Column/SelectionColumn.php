<?php

namespace getoma\dbfe\Table\Column;

use getoma\dbfe\Form\Generator\Node\NodeInterface;
use getoma\dbfe\Form\Generator\Field\SelectField;

/**
 * a db table column which allows a application defined set of valid values
 */
class SelectionColumn extends PlainColumn
{
   public function __construct(
      PlainColumn|array $structure,
      string $table,
      protected readonly array $selection,
   )
   {
      parent::__construct( $structure, $table, false );
   }

   public function getFormGeneratorDefinition(): NodeInterface
   {
      return new SelectField(
         $this->getAffixedName(),
         $this->selection,
         required: $this->isRequired(),
         fixed:    $this->isFixed(),
      );
   }
}
