<?php namespace dbfe\Form\Validator;

require_once 'dbfe/Util/Exception.php';

/**
 * The Form\Validator\Profile is a configuration of the Form Validator that
 * - is human-readable
 * - has low to no redundancies
 * - is easily mergeable
 */
class Profile
{
   public $required     = [];
   public $optional     = [];
   public $dependencies = [];
   public $constraints  = [];
   public $defaults     = [];
   public $filters      = [];
   public $globfilters  = [];
   public $msgs         = [];
   public $hooks        = [];
   public $formcheck    = [];
   public $upload       = [];

   public function __construct( array $profile = [] )
   {
      /** @var mixed $value */
      foreach( $this as $key => $value )
      {
         $this->$key = $profile[$key]??[];
      }
   }

   public function merge( $other )
   {
      /* allow $other to be null */
      if( !isset($other) ) return;

      if( is_array($other) ) $other = new Profile($other);

      if( !($other instanceof Profile ) ) throw new \LogicException('invalid type of variable to merge');

      foreach( $other as $key => $value )
      {
         $this->$key = array_merge_recursive( $this->$key, $value );
      }
   }

   /**
    * check this Profile for validity
    * @return bool
    */
   public function check( $throw = true )
   {
      $error = function( $msg ) use ($throw)
      {
         if( $throw ) throw new \LogicException($msg);
         return false;
      };

      /* all properties shall be arrays */
      foreach( $this as $p => $val )
      {
         if( !is_array($val) )
         {
            return $error("$p must be an array");
         }
      }

      /* all entries in dependencies shall be arrays */
      /* trim potential '[]' suffixes */
      foreach( $this->dependencies as &$val )
      {
         if( !is_array($val) )
         {
            return $error("dependencies must be array of arrays");
         }
      }

      /* constraints need to be array of array of constraints - enforce this for convinience */
      foreach( $this->constraints as &$val )
      {
         if( !is_array($val) ) $val = [ $val ];
         foreach( $val as &$c )
         {
            try
            {
               if( !is_object($c) ) $c = new Constraint($c);
            }
            catch( \Exception $e )
            {
               return $error( $e->getMessage() );
            }
         }
      }

      /* filter check */
      $check_filter = function( &$val, $idx ) use ($error)
      {
         if( !($val instanceof Filter) )
         {
            try
            {
               $val = Filter::create($val);
            }
            catch (\Exception $e)
            {
               return $error( $e->getMessage() );
            }
            if( is_null($val) ) return $error( "invalid filter at $idx" );
         }
         return true;
      };

      /* filters (-> field specific): array of array of filters */
      /** @var mixed $field */
      foreach( $this->filters as $field => &$f )
      {
         if( !is_array($f) ) $f = [$f];
         foreach( $f as $idx => &$filter )
         {
            if( !$check_filter($filter, $idx) ) return false;
         }
      }

      /* glob filters: array of filters */
      array_walk($this->globfilters, $check_filter );

      /* formcheck: list of callableo */
      foreach( $this->formcheck as $idx => $val )
      {
         if( !is_callable($val) )
         {
            return $error( "invalid formcheck at $idx" );
         }
      }

      /*** check the file upload definitions ***/
      $upload_opt = [ 'name'    => null
                    , 'type'    => '/.*/'
                    , 'maxsize' => -1
                    , 'needed'  => false ];

      foreach( $this->upload as &$prof )
      {
         if( !is_array( $prof ) )
         {
            return $error('upload field definition shall be an array');
         }

         foreach( $upload_opt as $key => $value )
         {
            /* put default value if field is not given */
            $prof[$key] = $prof[$key]??$value;
            /* check if valid value given */
            if( !isset( $prof[$key] ) )
            {
               return $error("option '$key' is not given for file upload check");
            }
         }
      }

      /*** check processing hooks to be callable ***/
      foreach( [ 'PreProcess', 'PostProcess' ] as $key )
      {
         if( isset( $this->hooks[$key] ) )
         {
            if( !is_callable( $this->hooks[$key] ) )
            {
               return $error("hook '$key' is no function!");
            }
         }
         else
         {
            /* preset to empty function for convinience */
            $this->hooks[$key] = function(){};
         }
      }

      return true;
   }
}
