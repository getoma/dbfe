<?php namespace dbfe;

require_once 'dbfe/Frontend/formPage.php';

require_once 'dbfe/Table/Factory.php';
require_once 'dbfe/Table/View.php';

require_once 'dbfe/Util/HtmlElement.php';

abstract class tableFormPage extends formPage
{
   /** @var array[Table] */
   private $m_table_list = [];
   /** @var bool - also print main table as array already */
   protected $m_as_array = false;
   /** @var bool - only provide required form fields when showing page for new entry */
   protected $m_required_only_on_new_entry = true;
   /** @var int selected entry */
   protected $m_entry_id = null;

   /**
    * protected getters
    **/

   /**
    * get a loaded table
    * @param string $name|null - name of table or null for main table
    * @return \dbfe\Table
    */
   protected function getTable(string $name = null)
   {
      return is_null($name)? reset($this->m_table_list) : $this->m_table_list[$name]??null;
   }

   protected function hasTable( string $name )
   {
      return isset( $this->m_table_list[$name] );
   }

   /**
    * return list of tables to load for this page,
    * as usable by table factory
    * @return array[string]
    */
   abstract protected function configureTableList();

   /**
    * perform any page-specific configurations of the loaded tables
    */
   abstract protected function configureTables();

   /** get groups in form (optional)
    * @return array[string]
    */
   protected function configureFormGroups()
   {
      return [];
   }

   /**
    * configure the entry selection for this page by providing a SelectQuery
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
    * @return \dbfe\SelectQuery|array
    */
   protected function configureEntrySelection()
   {
      return null;
   }

   /**
    * get any views provided by this page (optional)
    * @return array[string => SelectQuery]
    */
   protected function configureViewList()
   {
      return [];
   }

   private function loadView( string $name, SelectQuery $spec, Factory $fact )
   {
      /* check validity of the view specification */
      if( !is_array($spec) )
      {
         /* it's in "simplified" format (= only the query given) */
         if( $spec instanceof SelectQuery )
         {
            /* extend it to full format */
            $view_spec = [ 'query' => $spec ];
         }
         else /* it's something invalid */
         {
            throw new \LogicException("unknown input for view specification of $name");
         }
      }

      /* generate the view */
      $view = new View( $name, $view_spec['query'], $this->getDbh() );

      /* check if the spec specifies the parent
       * - use the main table otherwise (unless this view IS the main table)
       */
      if( !isset($view_spec['parent']) && count($this->m_table_list) )
      {
         $view_spec['parent'] = $this->getTable()->getName();
      }

      /* load the parent and register the reference to this view */
      if( isset($view_spec['parent']) )
      {
         $parent      = $fact->loadTable( $view_spec['parent'], true );
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
            throw new DatabaseError('Table ' . $parent->getName() . " has no column $linkColName to attach view $name to.");
         }
      }
   }

   function __construct($options = [])
   {
      parent::__construct($options);

      $this->m_entry_id = $this->readEntryId();

      /* create tables for this page */
      $table_list = $this->configureTableList();
      if( isset($table_list) && is_array($table_list) && count($table_list) )
      {
         $fact  = $options['table_factory'] ?? new Factory( $this->getDbh() );
         $views = $this->configureViewList();
         foreach( $table_list as $tab_name )
         {
            /* views can also be listed in the table list to explicitly
             * set their position within the page:
             */
            if( isset($views[$tab_name]) )
            {
               /* load view */
               $this->m_table_list[$tab_name] = $this->loadView($tab_name, $views[$tab_name], $fact);
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
               $this->m_table_list[$view_name] = $this->loadView( $view_name, $spec, $fact );
            }
         }
         $this->getTable()->useFieldsetsForReferences(true);
      }
      $this->configureTables();
   }

   /**
    * derive entry id from input. May be overridden by child class
    * @return integer
    */
   protected function readEntryId()
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
    * @return integer
    */
   public function getEntryId()
   {
      return $this->m_entry_id;
   }

   /**
    * return content of Form\Validator definition
    * {@inheritDoc}
    * @see \dbfe\formPage::getValidatorConfig()
    */
   protected function getValidatorConfig()
   {
      return $this->getTable()->getFormValidation( !$this->m_as_array&&($this->m_entry_id === 0), $this->m_as_array );
   }

   /**
    * process the validated data in $this->fv
    * {@inheritDoc}
    * @see \dbfe\formPage::processInput()
    */
   protected function processInput()
   {
      if( $this->m_as_array )
      {
         $this->getTable()->insertData( $this->m_fv->filtered, true );
         $this->getTable()->deleteRowsFromFv($this->m_fv->filtered);
      }
      else if( $this->m_entry_id === 0 )
      {
         $this->getTable()->insertData( $this->m_fv->filtered );
         $this->redirectTo( $this->getTable()->lastInsertId() );
      }
      else
      {
         $this->getTable()->updateRow( $this->m_fv->filtered, $this->m_entry_id );
      }
   }

   /**
    * {@inheritDoc}
    * @see \dbfe\formPage::getData()
    */
   protected function getData()
   {
      return $this->getTable()->getFormData( $this->m_as_array? [] : $this->m_entry_id );
   }

   /**
    * {@inheritDoc}
    * @see \dbfe\formPage::getFormDefinition()
    */
   protected function getFormDefinition( array $values )
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
   private function processSelectionQuery( SelectQuery $query )
   {
      /* fetch the selection data */
      $q_result = $this->getDbh()->query($query->asString());

      /* check if valid query */
      if( $q_result->columnCount() < 2 )
      {
         throw new \LogicException("invalid page selection array - needs to return at least 2 columns (id + naming)!");
      }

      /* prepare fetching of all data */
      $default_group = 'NO GROUP';

      /* sort result set into groups, detect multiple entries along the specifiers as well */
      $pages = [];
      $entry_count = [];
      while( $row = $q_result->fetch(\PDO::FETCH_NUM) )
      {
         $id = array_shift($row);
         $name = array_shift($row);
         $group = empty($row)? $default_group : array_shift($row);

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

   /* function to print the entry selection */
   protected function printEntrySelection()
   {
      $query = $this->configureEntrySelection();
      /* generate default query if none given */
      if( !isset($query) )
      {
         $query = new SelectQuery();
         $query->columns    = [ $this->getTable()->getIdColumn()->getName(), $this->getTable()->getNameColumn()->getName() ];
         $query->table_spec = $this->getTable()->getName();
         $query->order      = $this->getTable()->getNameColumn()->getName();
      }
      $entries = ($query instanceof SelectQuery)? $this->processSelectionQuery($query) : $query;

      if( !is_array($entries) )
      {
         throw new \LogicException("invalid entry selection, must be array or SelectQuery");
      }

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
         $list[] = [ 'li', [], [ [ 'a', [ 'href' => $this->selflink() . "/?id=0" ], $this->getLabelHdl()->get('Create entry') ] ] ];
      }
      $del = $this->isAllowed('delete');

      foreach( $entries as $group => $group_entries )
      {
         $sublist = [];
         foreach( $group_entries as $id => $name )
         {
            $entry = [ [ 'a', [ 'href' => $this->selflink() . "/?id=$id" ], $name ] ];

            if( $del ) $entry[] = [ 'a', [ 'href' => $this->selflink() . "/?id=$id&amp;delete=1", 'class' => 'delete' ], $this->getLabelHdl()->get('delete') ];

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

      /* print it */
      print $html->asHtml() . "\n";
   }

   /**
    * {@inheritDoc}
    * @see dbfeIf::getTitle()
    */
   public function getTitle()
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
   public function output()
   {
      if( is_null($this->m_entry_id) )
      {
         $this->printEntrySelection();
      }
      else
      {
         parent::output();

         print "\n" . '<p id="backlink"><a href="' . $this->selflink() . '">' . $this->getLabelHdl()->get('back') . '</a></p>' . "\n";
      }
   }

   public function input()
   {
      if( !isset( $this->m_entry_id ) ) return;

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
               $this->m_input_valid = true;
            }
            catch( \RuntimeException $e )
            {
               $this->getDbh()->rollBack();
               $this->setErrorMessage( $e->getMessage() );
               $this->m_input_valid = false;
            }
            catch( \Exception $e )
            {
               $this->getDbh()->rollBack();
               $this->setErrorMessage( $e->getMessage() . $e->getTraceAsString() );
               $this->m_input_valid = false;
            }
         }
         else
         {
            $this->m_input_valid = false;
            $this->setErrorMessage( 'Delete entry not allowed' );
         }
         $this->m_entry_id = null;
      }
      else
      {
         parent::input();
      }
      return $this->m_input_valid;
   }
}
