<?php

namespace getoma\dbfe\Frontend;

use getoma\dbfe\Form\Printer\Configuration\ConfigurationListIf;
use getoma\dbfe\Table\Column\ReferenceColumn;
use getoma\dbfe\Table\Factory;
use getoma\dbfe\Table\Table;
use getoma\dbfe\Table\TableIf;
use getoma\dbfe\Table\TableReference;
use getoma\dbfe\Util\Exception\DatabaseStructureIssue;
use getoma\dbfe\Util\HtmlElement\HtmlElement;

use Aura\SqlQuery\Common\SelectInterface;
use Aura\SqlQuery\QueryFactory;

abstract class TableFormPage extends FormPage
{
   /** @var Table[] - list of contained tables */
   private array $m_table_list = [];
   /** also print main table as array already? */
   protected bool $m_as_array = false;
   /**  only provide required form fields when showing page for new entry */
   protected bool $m_required_only_on_new_entry = true;
   /** currently selected entry */
   protected ?int $m_entry_id = null;

   protected readonly QueryFactory $query_factory;

   /** @var array - lazy fetch table contents */
   private array $m_data;

   /**
    * protected getters
    **/

   /**
    * get a loaded table
    * @param string $name|null - name of table or null for main table
    */
   protected function getTable(?string $name = null): \getoma\dbfe\Table\Table
   {
      return is_null($name)? reset($this->m_table_list) : $this->m_table_list[$name];
   }

   protected function hasTable( string $name ): bool
   {
      return isset( $this->m_table_list[$name] );
   }

   /**
    * return list of table names to load for this page
    * @return string[]
    */
   abstract protected function configureTableList(): array;

   /**
    * perform any page-specific configurations of the loaded tables
    */
   abstract protected function configureTables(): void;

   /** get groups in form (optional)
    * @return string[]
    */
   protected function configureFormGroups(): array
   {
      return [];
   }

   /**
    * configure the entry selection for this page by providing a QueryBuilder
    * that returns a list of all Entries + any info needed to fine-tune the presentation
    *
    * if your table contains a single "unique" column which shall be used as page title,
    * you can skip this method (delete it or let it return null).
    *
    * The returned column needs to provide *at least* two columns. Layout:
    * [ 'primary key', 'entry title', ( 'group name', ('title_specifier', ('title_specifier', ...) ) ) ]
    *
    * primary key: primary key column of the primary table for this page
    * page title:  title that shall be used in the link to this entry.
    * group name:  entries can be visually grouped. Define groups by providing the group names
    *              If omitted, set to null, or the same group name on every entry is given, no grouping is done.
    * title_specifier: if there are any "page title" duplicates within a group, any additionally 'title_specifier'
    *                  columns will be added to the title in brackets, until a unique name is derived.
    *
    * Alternatively, you can return an array that describes the entry selection menu:
    *
    * with grouping:  [ 'group name' => [ 'primary key value' => 'entry title', ... ], ... ]
    * without groups: [ 'primary key value' => 'entry title', ... ]
    *
    */
   protected function configureEntrySelection(): SelectInterface|array|null
   {
      return null;
   }

   /**
    * get any views provided by this page (optional)
    */
   protected function configureViewList(): array
   {
      return [];
   }

   private function loadView( string $name, SelectInterface $spec ): TableIf
   {
      /* generate the view */
      $view = new \getoma\dbfe\Table\View( $name, $spec, $this->getDbh() );

      /* register the reference to this view */
      if( count($this->m_table_list) )
      {
         $parent      = $this->getTable();
         $viewColumns = $view->getColumns();
         $linkColName = reset($viewColumns)->getName();
         /* link is done via the first column of the view,
          * which is required to be of the same name as the referencing
          * column in the parent table
          */
         if( $parent->getColumn($linkColName) )
         {
            $ref = new TableReference($view, $linkColName, $linkColName);
            $parent->registerReference($ref);
         }
         else
         {
            throw new DatabaseStructureIssue('Table ' . $parent->getName() . " has no column $linkColName to attach view $name to.");
         }
      }
      return $view;
   }

   function __construct($options = [])
   {
      parent::__construct($options);

      $this->query_factory = new QueryFactory($this->getDbh()->getAttribute(\PDO::ATTR_DRIVER_NAME));

      $this->m_entry_id = $this->readEntryId();

      /* create tables for this page */
      $table_list = $this->configureTableList();
      if( isset($table_list) && is_array($table_list) && count($table_list) )
      {
         $fact  = $options['table_factory'] ?? new Factory( $this->getDbh(), $this->query_factory );
         $views = $this->configureViewList();
         foreach( $table_list as $tab_name )
         {
            /* views can also be listed in the table list to explicitly
             * set their position within the page:
             */
            if( isset($views[$tab_name]) )
            {
               /* load view */
               $this->m_table_list[$tab_name] = $this->loadView($tab_name, $views[$tab_name]);
            }
            else
            {
               /* load normal table */
               $this->m_table_list[$tab_name] = $fact->loadTable( $tab_name, true );
            }
         }
         /* add any unlisted views at the end */
         foreach( $views as $view_name => $spec )
         {
            if( !isset($this->m_table_list[$view_name] ) )
            {
               $this->m_table_list[$view_name] = $this->loadView( $view_name, $spec);
            }
         }
         $this->getTable()->useFieldsetsForReferences(true);
      }
      $this->configureTables();
   }

   /**
    * derive entry id from input. May be overridden by child class
    */
   protected function readEntryId(): ?int
   {
      /* get the entry id */
      $id = $_REQUEST['id'] ?? null;
      /* ok if valid id, return to page selection if not */
      if( isset($id) )
      {
         $id = preg_match( '/^\d+$/', $id )? $id+0 : null;
      }
      return $id;
   }

   /**
    * return the currently selected entry id of the form
    */
   public function getEntryId(): ?int
   {
      return $this->m_entry_id;
   }

   /**
    * return content of Form\Validator definition
    */
   protected function getValidatorConfig(): array
   {
      return $this->getTable()->getFormValidation( !$this->m_as_array&&($this->m_entry_id === 0), $this->m_as_array );
   }

   /**
    * process the validated data in $this->fv
    */
   protected function processInput(): void
   {
      if( $this->m_as_array )
      {
         $this->getTable()->insertData( $this->m_input, true );
         $this->getTable()->deleteRowsFromFv($this->m_input);
      }
      else if( $this->m_entry_id === 0 )
      {
         $this->getTable()->insertData( $this->m_input );
         $this->redirectTo( $this->getTable()->lastInsertId() );
      }
      else
      {
         $this->getTable()->updateRow( $this->m_input, $this->m_entry_id );
      }
   }

   /**
    */
   protected function getData( bool $refetch = false ): array
   {
      if( $refetch || !isset($this->m_data) )
      {
         $this->m_data = $this->getTable()->getFormData( $this->m_as_array? [] : $this->m_entry_id );
      }
      return $this->m_data;
   }

   /**
    */
   protected function getFormDefinition( array $values ): ConfigurationListIf
   {
      $formopt = [
         'as_array' => $this->m_as_array,
         'groups' => $this->configureFormGroups(),
         'required_only' => ($this->m_entry_id === 0)? $this->m_required_only_on_new_entry : false
      ];
      $formdef   = $this->getTable()->getFormDefinition( $this->getLabelHdl(), $values, $formopt );
      $formdef[] = [ 'type'    => 'buttonbox', 'class' => 'buttonbox'
                   , 'buttons' => [ 'submit' => [ 'save' => $this->getLabelHdl()->get('save') ] ] ];

      if( $this->getTable()->hasUploads() )
      {
         $this->m_formparams['enctype'] = 'multipart/form-data';
      }

      return $formdef;
   }

   /**
    */
   private function processSelectionQuery( SelectInterface $query ): array
   {
      /* fetch the selection data */
      $stmt = $this->getDbh()->prepare($query->getStatement());
      $stmt->execute($query->getBindValues());

      /* check if valid query */
      if( $stmt->columnCount() < 2 )
      {
         throw new \LogicException("invalid page selection array - needs to return at least 2 columns (id + naming)!");
      }

      /* prepare fetching of all data */
      $default_group = $this->getLabelHdl()->get( 'NO GROUP', $this->getTable()->getName() );

      /* sort result set into groups, detect multiple entries along the specifiers as well */
      $pages = [];
      $entry_count = [];
      while( $row = $stmt->fetch() )
      {
         $id = array_shift($row);
         $name = array_shift($row);
         $group = array_shift($row)??$default_group;

         $pages[$group][$id] = array_merge( [$name], $row );
         $keyList = [$group];
         foreach( $pages[$group][$id] as $specifier )
         {
            $keyList[] = $specifier;
            $key = join(",", $keyList);
            if( isset($entry_count[$key]) ) $entry_count[$key] += 1;
            else $entry_count[$key] = 1;
         }
      }

      /* go through pages again, resolve duplicate entries */
      foreach( $pages as $group_name => &$group )
      {
         foreach( $group as &$page )
         {
            $title = [];
            foreach( $page as $part )
            {
               $title[] = $part;
               if( $entry_count[$group_name.",".join(",", $title)] == 1 ) break;
            }
            $page = array_shift($title);
            if( count($title) )
            {
               $page .= " (" . join(", ", $title) . ")";
            }
         }
      }

      /* done */
      return $pages;
   }

   /* function to generate the entry selection */
   protected function printEntrySelection(): \getoma\dbfe\Util\HtmlElement\HtmlElementIf
   {
      $query = $this->configureEntrySelection();
      /* generate default query if none given */
      if( !isset($query) )
      {
         $idCol = $this->getTable()->getIdColumn() ?? throw new DatabaseStructureIssue("{$this->getTable()->getName()} does not have an ID column.");
         $nameCol = $this->getTable()->getNameColumn() ?? throw new DatabaseStructureIssue("{$this->getTable()->getName()} does not have a Name column.");
         $query = $this->query_factory->newSelect()
            ->cols([$idCol->getName(), $nameCol->getName()])
            ->from($this->getTable()->getName())
            ->orderBy([$nameCol->getName()]);
      }

      $entries = match(true)
      {
         ($query instanceof SelectInterface) => $this->processSelectionQuery($query),
         is_array($query)                    => $query,
         default => throw new \LogicException('invalid query type: ' . get_class($query))
      };

      $is_grouped = false;

      if( !empty($entries) )
      {
         /* check whether the page selection is grouped:
          * if first entry is no array, assume non-grouped selection
          * --> introduce dummy group to generalize further handling
          */
         if( !is_array(reset($entries)) )
         {
            $entries = [$entries];
         }

         /* check whether there is more than one group */
         $is_grouped = (count($entries) > 1);
      }

      /* generate the html code for the selection */
      $list = [];

      if( $this->isAllowed('create') )
      {
         $list[] = [ 'li', [], [ [ 'a', [ 'href' => $this->selflink() . "?id=0" ], $this->getLabelHdl()->get('Create entry') ] ] ];
      }
      $del = $this->isAllowed('delete');

      foreach( $entries as $group => $group_entries )
      {
         $sublist = [];
         foreach( $group_entries as $id => $name )
         {
            $entry = [ [ 'a', [ 'href' => $this->selflink() . "?id=$id" ], $name ] ];

            if( $del ) $entry[] = [ 'a', [ 'href' => $this->selflink() . "?id=$id&amp;delete=1", 'class' => 'delete' ], $this->getLabelHdl()->get('delete') ];

            $sublist[] = [ 'li', [], $entry ];
         }

         if( $is_grouped )
         {
            $list[] = [ 'li', [], [ [ 'p' , [], $group ]
               , [ 'ul', [ 'class' => 'PageSel' ], $sublist ]
            ] ];
         }
         else
         {
            $list = array_merge( $list, $sublist );
         }
      }

      $class = $is_grouped? 'PageMenu' : 'PageSel';

      $html = new HtmlElement( 'ul', [ 'class' => $class.($del?' delete':'') ], $list );
      return $html;
   }

   /**
    * {@inheritDoc}
    * @see dbfeIf::getTitle()
    */
   public function getTitle(): string
   {
      $result = parent::getTitle();
      if( isset($this->m_entry_id) )
      {
         if( $this->m_entry_id === 0 )
         {
            $result .= ' - ' . $this->getLabelHdl()->get('new entry');
         }
         else
         {
            $result .= ' - ' . $this->getLabelHdl()->get('edit entry');
         }
      }
      return $result;
   }

   /**
    * {@inheritDoc}
    * @see \dbfe\formPage::output()
    */
   public function output(): \getoma\dbfe\Util\HtmlElement\HtmlElementIf
   {
      if( is_null($this->m_entry_id) )
      {
         return $this->printEntrySelection();
      }
      else
      {
         $html = parent::output();
         $backlink = [ 'p', [ 'id' => 'backlink' ], [
            [ 'a', ['href' => $this->selflink() ], [ $this->getLabelHdl()->get('back') ] ]
         ]];

         return new HtmlElement('', content: [ $html, $backlink ] );
      }
   }

   public function input(): ?bool
   {
      if( !isset( $this->m_entry_id ) ) return null;

      if( $_REQUEST['delete'] ?? false )
      {
         /*
          * the entry shall be deleted: check permissions and try to delete
          * the entry
          */
         if( $this->isAllowed( 'delete' ) )
         {
            try
            {
               $this->getDbh()->beginTransaction();
               $this->getTable()->dropRow( $this->m_entry_id );
               $this->getDbh()->commit(); /* commit all changes */
            }
            catch( \RuntimeException $e )
            {
               $this->getDbh()->rollBack();
               $this->setErrorMessage( $e->getMessage() );
            }
            catch( \Exception $e )
            {
               $this->getDbh()->rollBack();
               $this->setErrorMessage( $e->getMessage() . $e->getTraceAsString() );
            }
         }
         else
         {
            $this->setErrorMessage( 'Delete entry not allowed' );
         }
         $this->m_entry_id = null;
         return empty($this->m_input_errors);
      }
      else
      {
         return parent::input();
      }
   }

   /**
    * get a reference column (helper function for derived classes)
    */
   protected function getReferenceColumn(string $table_name, string $column_name): ReferenceColumn
   {
      $column = $this->getTable($table_name)->getColumn($column_name);
      if( !($column instanceof ReferenceColumn) ) throw new \LogicException('not a reference column');
      return $column;
   }
}
