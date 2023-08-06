<?php namespace dbfe;

require_once 'dbfe/Frontend/plainPage.php';
require_once 'dbfe/Form/Validator.php';
require_once 'dbfe/Form/Printer.php';

/**
 * base class to implement a form page to manipulate the database contents
 */
abstract class formPage extends plainPage
{
   protected $m_fv;
   protected $m_input_valid = null;

   /******************************************************
    * PROTECTED MEMBER VARIABLES, USED BY DERIVED CLASSES
    ******************************************************/
   protected $m_formparams = [];

   /******************************************************
    * DERIVED CLASSES INTERFACE (INTERNAL)
    ******************************************************/

   /**
    * return content of Form\Validator definition
    * @return Form\Validator\Profile
    */
   abstract protected function getValidatorConfig();

   /**
    * process the validated data in $this->fv
    */
   abstract protected function processInput();

   /**
    * get complete block of data of the current page
    * @return array
    */
   abstract protected function getData( bool $refetch = false );

   /**
    * return content of the form as Form\Printer structure
    * @param $values array the data that will be printed with this form
    * @return array
    */
   abstract protected function getFormDefinition(array $values);

   /******************************************************
    * interface
    ******************************************************/

   /**
    * {@inheritDoc}
    * @see plainPage::output()
    */
   public function output()
   {
      $values = $this->getData();

      $form_cfg = new Form\Printer\Configuration(
         array_merge(
         [
            'FormValidator' => $this->m_fv,
            'values' => ( ($this->m_input_valid !== false)? $values : null ),
            'name' => $this->getName(),
            'accept-charset' => 'UTF-8',
            'content' => $this->getFormDefinition($values),
         ],
         $this->m_formparams
         ) );

      $form = new Form\Printer( $form_cfg );

      $this->callHook('printform', $this);

      print $form->asHtml() . "\n";
   }

   public function input()
   {
      if( isset( $this->m_input_valid ) ) return $this->m_input_valid;

      if( ($_SERVER['REQUEST_METHOD'] === 'POST') )
      {
         /* create the validation profile */
         $validation = $this->getValidatorConfig();

         if( is_array($validation) )
         {
            $validation = new Form\Validator\Profile($validation);
         }

         $validation->merge([ 'globfilters' => [ 'trim' ],
                              'msgs'        => [ 'format'      => $this->getLabelHdl()->get('Error',   'form') . ': %s',
                                                 'missing'     => $this->getLabelHdl()->get('missing', 'form'),
                                                 'invalid'     => $this->getLabelHdl()->get('invalid', 'form'),
                                                 'constraints' => []               ]
                            ]);

         /* create the form validator */
         $this->m_fv = new Form\Validator( $validation );

         /* check the input */
         if( $this->m_input_valid = $this->m_fv->check() )
         {
            try /* store data */
            {
               $this->getDbh()->beginTransaction();

               $this->processInput();

               $this->callHook('storeinput', $this);

               $this->getDbh()->commit(); /* commit all changes */

               /* delete FormValidator object, whole form shall be restored from DB */
               $this->m_fv = null;
            }
            catch( \RuntimeException $e )
            {
               /* an error occurred during updating the database */
               $this->getDbh()->rollBack();
               $this->m_input_valid = false;
               $this->setErrorMessage( $e->getMessage() );
            }
            catch( \Exception $e )
            {
               /* an error occurred during updating the database */
               $this->getDbh()->rollBack();
               $this->m_input_valid = false;
               $this->setErrorMessage( $e->getMessage() . $e->getTraceAsString() );
            }
         }
      }
      return $this->m_input_valid;
   }
}
