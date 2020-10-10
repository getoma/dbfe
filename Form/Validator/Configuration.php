<?php namespace dbfe\Form\Validator;

require_once 'dbfe/Form/Validator/Profile.php';

/**
 * The Form\Validator\Configuration is a configuration of the Form Validator that
 * is easily processable by the Form\Validator class when checking form input.
 *
 * It is generated out of a Form\Validator\Profile object which is the prefered format
 * to set up the validator configuration
 */
class Configuration
{
   /** @var array[Field] */
   public $fields = [];
   /** @var array[string] */
   public $msgs   = [];

   public $dependencies = [];
   public $formcheck = [];
   public $hooks     = [];
   public $upload    = [];

   function __construct( Profile $profile )
   {
      $profile->check(true);

      /** collect ALL wherever listed fields that are expected */
      $field_list = array_merge( $profile->required, $profile->optional );
      foreach( $profile->dependencies as $group )
      {
         $field_list = array_merge( $field_list, $group );
      }

      foreach( $field_list as $field )
      {
         $config = new Field();
         /* detect 'required' fields */
         if( in_array($field, $profile->required) )
         {
            $config->needed = true;
         }
         /* detect 'array' fields */
         if( substr( $field, -2 ) === '[]' )
         {
            $config->array = true;
            $field = substr( $field, 0, -2 );
         }
         /* store back the name of the field */
         $config->name = $field;
         /* done, store field profile */
         $this->fields[$field] = $config;
      }

      /* copy field-specific configurations */
      foreach( $this->fields as $name => $field )
      {
         /* constraints */
         $field->constraints = $profile->constraints[$name]??[];

         /* default value */
         $field->defaults = $profile->defaults[$name]??null;

         /* filters */
         $field->filters = array_merge( $profile->globfilters, $profile->filters[$name]??[] );
      }

      /*** Assign dependency groups ***/
      foreach( $profile->dependencies as $idx => $group )
      {
         /* - trim the array suffix in the dependencies listing
          * - store a link to the dependency group in the corresponding fields
          * */
         $this->dependencies[] = array_map( function($field) use ($idx)
         {
            $field = trim($field, '[]');
            $this->fields[$field]->depgroup = $idx;
            return $field;
         }, $group );
      }

      /*** copy formcheck */
      $this->formcheck = $profile->formcheck;

      /*** copy upload checks */
      $this->upload = $profile->upload;

      /*** set default error message options ***/
      foreach( [ 'missing'      => 'Entry is missing.'
               , 'invalid'      => 'Invalid value.'
               , 'size'         => 'Input too large'
               , 'type'         => 'Wrong file type'
               , 'upload_fail'  => 'File upload failed'
               , 'dependencies' => 'Number of values do not match'
               , 'array'        => 'Only one value expected'
               , 'constraints'  => []
               , 'format'       => '%s' ] as   $key => $value )
      {
         $this->msgs[$key] = $profile->msgs[$key]??$value;
      }

      /*** copy hooks */
      $this->hooks = $profile->hooks;
   }
}

class Field
{
   public $constraints = [];
   public $defaults    = null;
   public $needed      = false;
   public $filters     = [];
   public $array       = false;
   public $depgroup    = null;
   public $name        = null;
}
