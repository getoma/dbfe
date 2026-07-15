<?php

namespace getoma\dbfe\Frontend;

use Aura\SqlQuery\Common\SelectInterface;

abstract class FullTablesFormPage extends TableFormPage
{
   /**
    * @var string[]
    */
   private array $m_table_selection = [];

   /**
    * @return string[]
    */
   abstract protected function configureTableSelection(): array;

   function __construct($options = [])
   {
      $this->m_table_selection = $this->configureTableSelection();
      parent::__construct($options);
      $this->m_as_array = true;
   }

   protected function getTableName(): ?string
   {
      return isset($this->m_entry_id)? $this->m_table_selection[$this->m_entry_id-1] : null;
   }

   protected function configureTableList(): array
   {
      return isset($this->m_entry_id)? [ $this->m_table_selection[$this->m_entry_id-1] ] : [];
   }

   protected function configureEntrySelection(): SelectInterface|array|null
   {
      $result = array_map( function($name)
      {
         return $this->getLabelHdl()->get($name);
      },
      $this->m_table_selection );

      /* shift array index to start with 1 */
      array_unshift($result, null);
      unset($result[0]);
      /* done */
      return $result;
   }

   protected function readEntryId(): ?int
   {
      $tabcnt = count($this->m_table_selection);

      if( $tabcnt  === 1 ) return 1;

      $id = parent::readEntryId();
      return ($id>0)&&($id<=$tabcnt)? $id : null;
   }

   public function getTitle(): string
   {
      if( isset($this->m_entry_id) )
      {
         return $this->getLabelHdl()->get( $this->m_table_selection[$this->m_entry_id-1] );
      }
      else
      {
         return parent::getTitle();
      }
   }

   public function isAllowed(string $action, ?string $subject = null): bool
   {
      if( ($action === 'delete') || ($action === 'create') ) return false;
      return parent::isAllowed($action);
   }
}
