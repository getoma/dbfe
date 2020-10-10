<?php namespace dbfe;

require_once 'dbfe/Frontend/if.php';
require_once 'dbfe/Util/LabelHandler.php'; 

/**
 * base class to implement a plain page, which may display database contents,
 * but does not provide the means to manipulate the database contents
 */
class plainPage implements dbfeIf
{   
   /******************************************************
    * MEMBER VARIABLES
    ******************************************************/
   /** @var \PDO */
   private $m_dbh;
   
   /** @var mixed */
   private $m_redirect_to = null;
   
   /** @var LabelHandlerIf */
   private $m_lblhdl;
   
   /** @var string */
   private $m_name;
   
   /** @var string */
   private $m_errmsg = null;
   
   /** @var string */
   private $m_selflink;
   
   /** @var array */
   static private $hooks;
   
   /******************************************************
    * INTERNAL INTERFACE FOR DERIVED CLASSES
    ******************************************************/
   protected function selflink()
   {
      return $this->m_selflink;
   }
   
   /**
    * @return \PDO
    */
   protected function getDbh()
   {
      return $this->m_dbh;
   }
   
   /**
    * @return \dbfe\LabelHandlerIf
    */
   protected function getLabelHdl()
   {
      return $this->m_lblhdl;
   }
   
   /**
    * @param string|int redirectTo
    */
   protected function redirectTo( $target )
   {
      $this->m_redirect_to = $target;
   }
   
   /******************************************************
    * CONSTRUCTOR
    ******************************************************/
   function __construct($options = [])
   {
      /* establish database connection */
      $this->m_dbh = $options['pdo'];
      /* store translator instance */
      $this->m_lblhdl = $options['label_hdl'] ?? new DummyLabelHandler();
      /* store name */
      $this->m_name = $options['name'] ?? get_class( $this );
      /* set self link */
      $this->m_selflink = $options['selflink'] ?? $_SERVER['SCRIPT_NAME'];
   }
   
   protected function callHook($hook, ...$params)
   {
      if( isset(self::$hooks[$hook]) )
      {
         foreach( self::$hooks[$hook] as $cbk )
         {
            $result = $cbk(...$params);
            if( isset($result) ) return $result;
         }
      }
      
      return null;
   }
   
   /******************************************************
    * interface
    ******************************************************/
   
   /**
    * {@inheritDoc}
    * @see dbfeIf::getTitle()
    */
   public function getTitle()
   {
      return $this->getLabelHdl()->get( $this->m_name );
   }
   
   /**
    * {@inheritDoc}
    * @see dbfeIf::getName()
    */
   public function getName()
   {
      return $this->m_name;
   }
   
   /**
    * {@inheritDoc}
    * @see dbfeIf::getErrorMessage()
    */
   public function getErrorMessage()
   {
      return $this->m_errmsg;
   }
   
   /**
    * set the error message
    */
   protected function setErrorMessage(string $err)
   {
      $this->m_errmsg = $err;
   }
   
   /**
    * {@inheritDoc}
    * @see dbfeIf::input()
    */
   public function input()
   {
      return null;
   }
   
   /**
    * {@inheritDoc}
    * @see dbfeIf::output()
    */
   public function output()
   {
   }
   
   /**
    * {@inheritDoc}
    * @see dbfeIf::printHeader()
    */
   public function printHeader()
   {
      $result = true;
      if( isset( $this->m_redirect_to ) )
      {
         if( $this->m_redirect_to[0] === '/' )
         {
            $link = $_SERVER['SCRIPT_NAME'] . $this->m_redirect_to;
         }
         else if(is_numeric($this->m_redirect_to))
         {
            $link = $this->selflink() . "?id=" . $this->m_redirect_to;
         }
         else
         {
            $link = $this->selflink() . $this->m_redirect_to;
         }
         header( 'Location: ' . $link );
         $result = false;
      }
      else
      {
         header("Content-type: text/html; charset=UTF-8");
      }
      return $result;
   }
   
   /**
    * {@inheritDoc}
    * @see dbfeIf::isAllowed()
    */
   public function isAllowed($action, $subject = null )
   {
      return $this->callHook('allowed', $action, $subject ) ?? true;
   }
   
   /**
    * public methods
    */
   static public function set_hook(string $hook, callable $callback)
   {
      self::$hooks[$hook] = array_merge( [ $callback ], self::$hooks[$hook]??[] );
   }
}

function set_hook( string $hook, callable $callback )
{
   plainPage::set_hook($hook, $callback);
}
