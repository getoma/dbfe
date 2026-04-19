<?php

namespace getoma\dbfe\Table\Column;

use getoma\dbfe\Form\Printer\Configuration\Configuration;
use getoma\dbfe\Table\Table;
use getoma\dbfe\Util\LabelHandler\LabelHandlerIf;
use getoma\dbfe\Util\QueryBuilder\SelectQuery;

/**
 * a db table column which references another table via foreign key constraint
 */
class ReferenceColumn extends PlainColumn implements ReferenceColumnIf
{
   /** @var Table */
   protected $refTable = null;
   /** @var mixed */
   protected $query    = null;
   /** @var mixed */
   protected $disabled = null;

   public function __construct($structure, string $table, Table $ref)
   {
      parent::__construct( $structure, $table, false );
      $this->refTable = $ref;
   }

   /**
    * {@inheritDoc}
    * @see \dbfe\ReferenceColumnIf::getTable()
    */
   public function getTable()
   {
      return $this->refTable;
   }

   /**
    * @return array
    */
   public function getReferenceData( array $filter = [], bool $addNA = true )
   {
      $query = $this->query;
      if( !isset($query) )
      {
         // get the content of the other table, select id and name col
         $query = new SelectQuery();
         $query->columns    = [ $this->refTable->getIdColumn()->getName(), $this->refTable->getNameColumn()->getName() ];
         $query->filter     = $filter;
      }

      if( $query instanceof SelectQuery )
      {
         $refData = $this->refTable->query( $query );

         $refValues = [];
         if( $addNA )
         {
            $refValues[''] = "N/A";
         }

         // add the content of the referenced column to the selectable data
         if( $refData )
         {
            while( $row = $refData->fetch( \PDO::FETCH_NUM ) )
            {
               $refValues[$row[0]] = $row[1];
            }
         }
         return $refValues;
      }
      else if( is_array($query) )
      {
         // data is given directly already
         return $query;
      }
      else
      {
         throw new \LogicException("unsupported type of reference query for column " . $this->getName() );
      }
   }

   /**
    * get a list of "disabled" selection keys
    */
   private function getDisabledKeys()
   {
      $query = $this->disabled;
      if( $query instanceof SelectQuery )
      {
         return $this->refTable->query( $query )->fetchAll(\PDO::FETCH_COLUMN);
      }
      else if( is_array($query) )
      {
         // data is given directly already
         return $query;
      }
      else
      {
         return [];
      }
   }

   /**
    * get a form specification that can be used as input to Form\Printer
    * A "Reference Column" is implemented by providing a select field which
    * allows to select an entry of the reference column.
    * The selectable values are taken from the "name column" of the other table.
    *
    * @return Form\Printer\Configuration
    */
   public function getFormDefinition(LabelHandlerIf $lblHdl, array $data = [], bool $as_array = false )
   {
      return new Configuration(
         [ 'name'  => $this->getAfixedName($as_array)
         , 'label' => $lblHdl->get( $this->getName(), $this->m_tablename )
         , 'required' => ($this->isRequired() && !$as_array)
         , 'fixed'    => $this->isFixed()
         , 'type'  => 'select', 'selection' => $this->getReferenceData(), 'disabled_keys' => $this->getDisabledKeys() ] );
   }

   /**
    * set a customized array to retrieve the selection data set
    * to set the reference content
    * @param SelectQuery|array $query
    * @param SelectQuery|array $disable_keys - any keys that shall no longer be selectable (unless they are already used for a specific field)
    */
   public function setReferenceQuery( $query, $disable_keys = null )
   {
      $this->query    = $query;
      $this->disabled = $disable_keys;
   }
}
