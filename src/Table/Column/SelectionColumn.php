<?php

namespace getoma\dbfe\Table\Column;

use getoma\dbfe\Form\Printer\Configuration\ConfigurationIf;
use getoma\dbfe\Form\Printer\Configuration\ConfigurationListIf;
use getoma\dbfe\Util\LabelHandler\LabelHandlerIf;

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

   /**
    * get a form specification that can be used as input to Form\Printer
    */
   public function getFormDefinition(LabelHandlerIf $lblHdl, array $data = [], bool $as_array = false ): ConfigurationIf|ConfigurationListIf
   {
      /* first get the list of already stored values */
      $selection = array_filter( $data[$this->getAfixedName()]??[] );
      $selection = array_combine( $selection, $selection );

      /* overwrite it by the given list of allowed values */
      $selection = array_replace( $selection, $this->selection );

      /* add N/A value if valid */
      if( !$this->isRequired() )
      {
         $selection = array_replace( ['' => 'N/A'], $selection );
      }

      return new \getoma\dbfe\Form\Printer\Configuration\Configuration(
         [ 'name'  => $this->getAfixedName($as_array),
           'label' => $lblHdl->get( $this->getName(), $this->tablename ),
           'fixed' => $this->isFixed(),
           'type'  => 'select', 'selection' => $selection ] );
   }
}
