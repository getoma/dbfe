<?php namespace dbfe;

require_once 'dbfe/Frontend/tableFormPage.php';

abstract class fullTablesFormPage extends \dbfe\tableFormPage
{
   /**
    * @var array[string]
    */
   private $m_table_selection = [];
   
   /**
    * @return array[String]
    */
   abstract protected function configureTableSelection();
   
   function __construct($options = [])
   {
      $this->m_table_selection = $this->configureTableSelection();
      parent::__construct($options);
      $this->m_as_array = true;
   }
   
   protected function getTableName()
   {
      return isset($this->m_entry_id)? $this->m_table_selection[$this->m_entry_id-1] : null;
   }
   
   protected function configureTableList()
   {
      return isset($this->m_entry_id)? [ $this->m_table_selection[$this->m_entry_id-1] ] : [];
   }
   
   protected function configureEntrySelection()
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
   
   protected function readEntryId()
   {
      $tabcnt = count($this->m_table_selection);
      
      if( $tabcnt  === 1 ) return 1;
      
      $id = parent::readEntryId();
      return ($id>0)&&($id<=$tabcnt)? $id : null;
   }
   
   public function getTitle()
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
   
   public function isAllowed($action, $subject = null)
   {
      if( ($action === 'delete') || ($action === 'create') ) return false;
      return parent::isAllowed($action);
   }
}
