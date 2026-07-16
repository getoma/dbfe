<?php

namespace getoma\dbfe\Frontend;

use getoma\dbfe\Form\Printer\Configuration\ConfigurationListIf;
use getoma\dbfe\Util\ValidatedInput;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Validator as V;

/**
 * base class to implement a form page to manipulate the database contents
 */
abstract class FormPage extends PlainPage
{
   protected ?ValidatedInput $m_input = null;
   protected array $m_input_errors;
   protected ?array $m_received_input = null;

   /******************************************************
    * PROTECTED MEMBER VARIABLES, USED BY DERIVED CLASSES
    ******************************************************/
   protected $m_formparams = [];

   /******************************************************
    * DERIVED CLASSES INTERFACE (INTERNAL)
    ******************************************************/

   /**
      * return a list of respect validators keyed by request field name.
      *
      * @return array<string, \Respect\Validation\Validator>
    */
      abstract protected function getValidatorConfig(): array;

   /**
    * process the validated data in $this->fv
    */
   abstract protected function processInput(): void;

   /**
    * get complete block of data of the current page
    */
   abstract protected function getData( bool $refetch = false ): array;

   /**
    * return content of the form as Form\Printer structure
    * @param $values array the data that will be printed with this form
    * @return ConfigurationListIf
    */
   abstract protected function getFormDefinition(array $values): ConfigurationListIf;

   /**
    * set the error message - also in intput errors array
    */
   protected function setErrorMessage(string $err): void
   {
      parent::setErrorMessage($err);
      $this->m_input_errors['_global_'] = $err;
   }

   /******************************************************
    * interface
    ******************************************************/

   /**
    * {@inheritDoc}
    * @see plainPage::output()
    */
   public function output(): \getoma\dbfe\Util\HtmlElement\HtmlElementIf
   {
      $values = $this->m_received_input ?: $this->getData();

      $form_cfg = new \getoma\dbfe\Form\Printer\Configuration\Configuration(
         array_merge(
         [
            'values'  => $values,
            'errmsg'  => $this->m_input_errors,
            'invalid' => array_fill_keys(array_keys($this->m_input_errors), true),
            'name'    => $this->getName(),
            'content' => $this->getFormDefinition($values),
            'accept-charset' => 'UTF-8',
         ],
         $this->m_formparams
         ) );

      $form = new \getoma\dbfe\Form\Printer\Printer( $form_cfg );

      $this->callHook('printform', $this);

      return $form;
   }

   public function input(): ?bool
   {
      if( isset($this->m_input_errors) ) return !$this->m_input_errors;

      if( ($_SERVER['REQUEST_METHOD'] === 'POST') )
      {
         $validators = $this->getValidatorConfig();
         $this->m_received_input = $this->normalizeInput( $_REQUEST );
         $this->m_input_errors = [];

         foreach( $validators as $field => $validator )
         {
            if( !($validator instanceof V) )
            {
               throw new \LogicException( "validator for '$field' must implement Respect\\Validation\\Validator" );
            }

            try
            {
               $validator->assert( $this->m_received_input );
            }
            catch( NestedValidationException $e )
            {
               $this->m_input_errors += $e->getMessages();
            }
         }

         if( !$this->m_input_errors )
         {
            $validated = $this->postProcessInput( $this->m_received_input );
            $this->m_input = new ValidatedInput( $validated );

            try /* store data */
            {
               $this->getDbh()->beginTransaction();

               $this->processInput();

               $this->callHook('storeinput', $this);

               $this->getDbh()->commit(); /* commit all changes */

               $this->m_received_input = null; /* clean original input data */
            }
            catch( \RuntimeException $e )
            {
               /* an error occurred during updating the database */
               $this->getDbh()->rollBack();
               $this->setErrorMessage( $e->getMessage() );
            }
            catch( \Exception $e )
            {
               /* an error occurred during updating the database */
               $this->getDbh()->rollBack();
               $this->setErrorMessage( $e->getMessage() . $e->getTraceAsString() );
            }
         }

         return empty($this->m_input_errors);
      }
      else
      {
         $this->m_input_errors = [];
         return null;
      }
   }

   /**
    * normalize raw request input before validation.
    */
   protected function normalizeInput( array $data ): array
   {
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

      return $data;
   }

   /**
    * post-process validated input before storage.
    */
   protected function postProcessInput( array $data ): array
   {
      array_walk_recursive( $data, function( &$value ): void
      {
         if( is_string( $value ) )
         {
            $value = trim( $value );
         }
      } );

      return $data;
   }
}
