<?php

namespace getoma\dbfe\Frontend;

use getoma\dbfe\Form\Generator\InputMask;
use getoma\dbfe\Util\LabelHandler\DummyLabelHandler;
use getoma\dbfe\Util\LabelHandler\LabelHandlerIf;

/**
 * base class to implement a plain page, which may display database contents,
 * but does not provide the means to manipulate the database contents
 */
abstract class PlainPage implements DbfeIf
{
   /******************************************************
    * MEMBER VARIABLES
    ******************************************************/
   /** @var \PDO */
   private readonly \PDO $m_dbh;

   /** @var mixed */
   private string|int|null $m_redirect_to = null;

   /** LabelHandlerIf */
   private readonly LabelHandlerIf $m_lblhdl;

   /** (internal) name of this page */
   private readonly string $m_name;

   /** current error message */
   private ?string $m_errmsg = null;

   /** url to this page */
   private readonly string $m_selflink;

   /** @var array */
   static private array $hooks;

   /******************************************************
    * INTERNAL INTERFACE FOR DERIVED CLASSES
    ******************************************************/
   protected function selflink(): string
   {
      return $this->m_selflink;
   }

   /**
    * @return \PDO
    */
   protected function getDbh(): \PDO
   {
      return $this->m_dbh;
   }

   /**
    */
   protected function getLabelHdl(): LabelHandlerIf
   {
      return $this->m_lblhdl;
   }

   /**
    * @param string|int $target
    */
   protected function redirectTo( string|int $target ): void
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
      $this->m_name = $options['name'] ?? substr(strrchr('\\' . get_class($this), '\\'), 1);
      /* set self link */
      $this->m_selflink = $options['selflink'] ?? $_SERVER['SCRIPT_NAME'];
   }

   protected function callHook(string $hook, mixed ...$params): mixed
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
   public function getTitle(): string
   {
      return $this->getLabelHdl()->get( $this->m_name );
   }

   /**
    * {@inheritDoc}
    * @see dbfeIf::getName()
    */
   public function getName(): string
   {
      return $this->m_name;
   }

   /**
    * {@inheritDoc}
    * @see dbfeIf::getErrorMessage()
    */
   public function getErrorMessage(): ?string
   {
      return $this->m_errmsg;
   }

   /**
    * set the error message
    */
   protected function setErrorMessage(string $err): void
   {
      $this->m_errmsg = $err;
   }

   /**
    * {@inheritDoc}
    * @see dbfeIf::input()
    */
   public function input(): ?bool
   {
      return null;
   }

   /**
    * {@inheritDoc}
    * @see dbfeIf::output()
    */
   abstract public function output(): \getoma\dbfe\Util\HtmlElement\HtmlElementIf;

   /**
    * {@inheritDoc}
    * @see dbfeIf::getInputMask()
    */
   public function getInputMask(): ?InputMask
   {
      return null;
   }

   /**
    * {@inheritDoc}
    * @see dbfeIf::getRedirect()
    */
   public function getRedirect(): ?string
   {
      $link = null;
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
      }
      return $link;
   }

   /**
    * {@inheritDoc}
    * @see dbfeIf::isAllowed()
    */
   public function isAllowed(string $action, ?string $subject = null): bool
   {
      return $this->callHook('allowed', $action, $subject ) ?? true;
   }

   /**
    * public methods
    */
   static public function set_hook(string $hook, callable $callback): void
   {
      self::$hooks[$hook] = array_merge( [ $callback ], self::$hooks[$hook]??[] );
   }
}
