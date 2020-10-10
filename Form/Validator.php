<?php namespace dbfe\Form;

require_once 'dbfe/Form/Validator/Constraint.php';
require_once 'dbfe/Form/Validator/Filter.php';
require_once 'dbfe/Form/Validator/Configuration.php';

class Validator
{
   /** @var array[bool] stores validity status for each field */
   public $is_valid  = [];
   /** @var array[mixed] the unprocessed input data as received */
   public $data     = [];
   /** @var array[mixed] the processed/filtered data after validation */
   public $filtered = [];
   /** @var string any global status message to display to the user */
   public $form_msg = '';
   /** @var array[string] any field specific error messages to display to the user */
   public $msg      = [];

   /** @var Validator\Configuration */ 
   protected $profile;

   function __construct($profile)
   {
      if( is_array($profile) ) $profile = new Validator\Profile($profile);
      $this->profile = new Validator\Configuration($profile);
   }

   /* returns the maximum field length (of selected columns) */
   public function maxCount($columns)
   {
      if( !isset( $columns ) ) $columns = array_keys( $this->filtered );

      $length = 0;

      foreach( $columns as $col )
      {
         $thislength = is_array( $this->filtered[$col] ) ? count( $this->filtered[$col] ) : 1;
         if( $length < $thislength ) $length = $thislength;
      }

      return $length;
   }

   public function check($data = null)
   {
      $ok = true;

      if( !isset( $data ) ) $data = $_REQUEST;

      /*
       * walk through all fields. If they are arrays, delete any last empty
       * entry
       */
      foreach( $data as &$value )
      {
         if( is_array( $value ) )
         {
            while( (count( $value ) > 0) && ($value[count($value)-1] === '') )
            {
               array_pop( $value );
            }
         }
      }

      /* copy data into internal buffer */
      $this->data = $data;

      $this->hook( 'PreProcess' );

      foreach( $this->profile->fields as $field => $config )
      {
         /* preset field if not in list (could be optional) */
         if( !array_key_exists( $field, $this->data ) )
         {
            $this->data[$field] = null;
         }

         /* check the field */
         if( $cname = $this->checkField( $config, $this->data[$field] ) )
         {
            /* Invalid field entry. Store the error message */
            $this->msg[$field] =  $this->profile->msgs[$cname]
                               ?? $this->profile->msgs['constraints'][$cname]
                               ?? $this->profile->msgs['invalid'];
            $this->is_valid[$field] = false;
            $ok = false;
         }
         else
         {
            /* Field successfully checked. */
            $this->is_valid[$field] = true;
         }
      }

      if( !$this->checkDependencies( $this->data ) ) $ok = false;

      /* check the file upload fields if any */
      foreach( $this->profile->upload as $config )
      {
         $errmsg = $this->checkUpload( $config );
         if( $errmsg )
         {
            $this->is_valid[$config['name']] = false;
            if( array_key_exists( $errmsg, $this->profile->msgs ) )
            {
               $this->msg[$config['name']] = $this->profile->msgs[$errmsg];
            }
            else
            {
               $this->msg[$config['name']] = $this->profile->msgs['invalid'];
            }
            $ok = false;
            break;
         }
         else
         {
            $this->is_valid[$config['name']] = true;
         }
      }

      /* if input data is fully valid, filter it */
      if( $ok )
      {
         foreach( $this->profile->fields as $field => $config )
         {
            $this->filtered[$field] = $this->filterField( $config, $this->data[$field] );
         }

         /* at last: perform form-wide checks */
         $ok = $this->formCheck( $this->filtered );
      }

      if( !$ok )
      {
         foreach( $this->msg as &$msg )
         {
            $msg = sprintf( $this->profile->msgs['format'], $msg );
         }
      }

      $this->hook( 'PostProcess' );

      return ($ok);
   }

   protected function hook($key)
   {
      if( isset( $this->profile->hooks[$key] ) )
      {
         $this->profile->hooks[$key]( $this );
      }
   }

   protected function checkDependencies($data)
   {
      $ok = true;
      foreach( $this->profile->dependencies as $depgroup )
      {
         $length = null;
         foreach( $depgroup as $field )
         {
            /* get number of values for current field */
            $thislength = !isset( $data[$field] ) || ($data[$field] === '') ? 0 : (!is_array( $data[$field] ) ? 1 : count( $data[$field] ));

            if( !isset( $length ) )
            { /* first field: store length */
               $length = $thislength;
            }
            /* compare field lengths */
            elseif( $length != $thislength )
            {
               $ok = false;
               $this->is_valid[$field] = false;
               $this->msg[$field] = sprintf( $this->profile->msgs['format'], $this->profile->msgs['dependencies'] );
               break 2;
            }
            else
            {
               /* ok */
            }
         }
      }
      return $ok;
   }

   /**
    *
    * @param Validator\Field $profile
    * @param mixed $data
    * @return string
    */
   protected function checkField( Validator\Field $profile, $data)
   {
      $errmsg = '';

      /* check if it is an array although it shouldn't */
      if( !$profile->array && is_array( $data ) )
      {
         $errmsg = 'array';
      }
      else
      { /* check all constraints */

         /* for every value of this field (field may be an array): */
         foreach( (is_array( $data ) ? $data : [ $data ]) as $value )
         {
            if( $value != '' )
            {
               /* for every constraint defined for this field: */
               foreach( $profile->constraints as $constraint )
               {
                  if( !$constraint->validate( $value, $profile->name ) )
                  {
                     $errmsg = $constraint->name();
                     break 2;
                  }
               }
            }
            elseif( $profile->needed )
            {
               $errmsg = 'missing';
               break;
            }
            else
            {
               /* missing optional field entry, which is no error */
            }
         }
      }
      return $errmsg;
   }

   /**
    * @param Validator\Field $profile
    * @param mixed $data
    * @return array|array[]|Validator\Field
    */
   protected function filterField( Validator\Field $profile, $data)
   {
      /*
       * if data is unset and shall be an array:
       * make it an empty array
       */
      if( !isset( $data ) && $profile->array )
      {
         $data = [];
      }

      /* turn $data into array if needed for eased further processing */
      if( !is_array( $data ) )
      {
         $data = [ $data ];
      }

      /* set default value */
      foreach( $data as &$value )
      {
         if( !isset($value) || ($value === '') ) $value = $profile->defaults;
      }

      /* apply all defined filters to all entries of this field */
      foreach( $profile->filters as $filter )
      {
         foreach( $data as &$value )
         {
            /* only perform filtering if value is not string-empty */
            if( isset($value) && ($value !== '') )
            {
               $value = $filter->execute( $value );
            }
         }
      }

      return $profile->array ? $data : $data[0];
   }

   protected function checkUpload($profile)
   {
      $msg = '';

      $name = $profile['name'];

      if( $profile['needed'] && (count( $_FILES[$name]['error'] ) === 0) )
      {
         $msg = 'missing';
      }
      else
      {
         foreach( $_FILES[$name]['error'] as $idx => $error )
         {
            /* check uploaded file for correct type */
            if( $error !== UPLOAD_ERR_OK ) $msg = 'upload_fail';
            else if( $_FILES[$name]['size'] > $profile['maxsize'] ) $msg = 'size';
            else if( !preg_match( $profile['type'], $_FILES[$name]['type'][$idx] ) ) $msg = 'type';
         }
      }

      return $msg;
   }

   protected function formCheck($data)
   {
      $msg = true;

      foreach( $this->profile->formcheck as $check )
      {
         $msg = $check( $data );
         if( $msg !== true ) break;
      }

      if( $msg !== true )
      {
         if( is_array( $msg ) )
         {
            $this->msg = array_merge( $this->msg, $msg );
            foreach( array_keys( $msg ) as $field )
            {
               $this->is_valid[$field] = false;
            }
         }
         else
         {
            $this->form_msg = $msg ? $msg : 'Invalid data entered';
         }
      }

      return $msg === true;
   }
}
