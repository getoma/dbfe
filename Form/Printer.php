<?php namespace dbfe\Form;
use dbfe\Form\Printer\ConfigurationIf;
use dbfe\Form\Printer\Configuration;
use dbfe\HtmlElement;

/******************************************************************************************************
 *
 *      CLASS INHERITANCE STRUCTURE
 *
 *  +------+       +-----------+         +---------+
 *  | Base |<--+---| Container |<--+-----| Printer |
 *  +------+   |   +-----------+   |     +---------+
 *             |                   |
 *             |                   |     +----------+
 *             |                   +-----| Fieldset |
 *             |   +---------+           +----------+
 *             +---| Element |
 *             |   +---------+
 *             |
 *             |
 *             |   +--------+            +-----------------+
 *             +---| Atomic |<-----+-----| AtomicContainer |
 *             |   +--------+      |     +-----------------+
 *             |                   |
 *             |                   |     +--------+
 *             |   +-----------+   +-----| Hidden |
 *             +---| ButtonBox |         +--------+
 *                 +-----------+
 *
 * Base
 * ----
 *  This class contains the whole basic infrastructure every element needs, that is
 *  - constructor which parses the element parameter structure and performs basic plausibility checks
 *  - autogeneration of commenly used html parameters: id, label (generated from name)
 *  - printing algorithm
 *
 * Container
 * ---------
 *  This class extends the Base definitions with the capability to deal with an undefined number of
 *  sub elements. This baseclass is used for container html-elements with undefined deepness of the
 *  sub structure.
 *  For every configured sub element the according class is selected via the following rules:
 *  1. if a class with the same name as in the 'type' option exists, use this class
 *  2. else, use the Element class.
 *
 * Printer
 * -------
 *  This is the root class the user shall intially create. It afterwards prints the <form></form> element.
 *  The subelements of the form are created by the constructor via the Container inheritance.
 *
 * Fieldset
 * --------
 *  This class is used whenever a 'fieldset' element is detected in the configuration structure. It provides
 *  the basic 'Container' mechanism and prints the <fieldset><legend></legend></fieldset> elements.
 *
 * Element
 * -------
 *  This class is a generic definition of any form entry. By definition every form entry is printed as a
 *  <p>
 *    <label></label>
 *    <input></input>
 *  </p>
 *  construct. The Element class transparently handles the creation of this structure without the need
 *  to explicitly define it in the configuration. The class of each sub element is selected by the following
 *  rules:
 *  1. if the 'content' option has more than one entry, then select the AtomicContainer class
 *  2. else, select the Atomic class.
 *  For the printing algorithm in the base class, the objects of this class mark themselfs as the <p> elements.
 *
 *  Atomic
 *  ------
 *   This class handles any really atomic form element, e.g. <input></input>.
 *   In this class the really needed html parameters are handled. Also, any pre-defined value is retrieved
 *   from the assign lists.
 *
 *  AtomicContainer
 *  ---------------
 *   This class handles any really atomic form element, which have one additional level of sub elements.
 *   In current html rules these are only the <select> fields.
 *   This class extends the Atomic definitions with the capability to create the <option> elements from
 *   the 'content' option.
 *
 *  Hidden
 *  ------
 *   This class provides only an alias for the Atomic class. It is defined to force the Container class to
 *   generate any <input type="hidden" /> element directly as Atomic object, instead of using the Element
 *   class for it.
 *
 *  Buttonbox
 *  ---------
 *   This class generates the 'submit', 'reset' etc. buttons needed in every form.
 *
 *    ************************
 *      CONFIGURATION FORMAT
 *    ************************
 *<?php
 * $form = new Form\Printer(
 *   array( 'FormValidator' => $formvalObject       // if given, values and error stati are used transparently
 *        , 'values'        => array( field1  => 'value' // preset values are assigned transparently at
 *                                  , select1 => 'b'     // the generation of the form
 *                                  , Id      => $id     //
 *                                  )
 *        , accept-charset  => 'UTF-8' // example for any parameter within the <form /> tag
 *        , 'content'       => array( array( 'type'  => 'hidden'
 *                                         , 'name'  => 'Id'
 *                                         )
 *                                  , array( 'type'    => 'fieldset'
 *                                         , 'label'   => 'Example Group'
 *                                         , 'name'    => 'ExGroup'
 *                                         , 'content' => array( array( 'label' => 'Example Text'
 *                                                                    , 'name' => 'field1'
 *                                                                    , 'type'  => 'text'
 *                                                                    , 'maxlength' => '40' // any html parameter can be added everywhere
 *                                                                    )
 *                                                             , array( 'name'    => 'select1'
 *                                                                    , 'type' => 'select'
 *                                                                    , 'content' => array( 'a' => 'erster', 'b' => 'zweiter', 'c' => 'dritter' )
 *                                                                    // explicit definition of key not necessary for select content
 *                                                                    // if not defined 0,1,2,etc. are used as indexes.
 *                                                                    //
 *                                                                    )
 *                                                             )
 *                                         )
 *                                  , array( 'type'    => 'buttonbox'
 *                                         , 'buttons' => array( 'submit' => 'Absenden', 'reset' => 'Reset' )
 *                                         )
 *                                  )
 *        )
 * );
 *
 * $form->output();
 *?>
 *
 ******************************************************************************************************/

require_once 'dbfe/Form/Validator.php';
require_once 'dbfe/Form/Printer/Configuration.php';
require_once 'dbfe/Util/HtmlElement.php';
require_once 'dbfe/Util/Exception.php';


class PrinterException extends \LogicException {}

/******************************************************************************************************
 * BASE CLASS
 *****************************************************************************************************/
abstract class Base extends \dbfe\HtmlElement
{
   protected $params           = [];
   protected static $paramlist = ['name', 'type', 'values', 'invalid', 'errmsg', 'label', 'idmanager', 'fscollect'];
   protected static $htmlsort  = ['type', 'name', 'id', 'class', 'value'];

   private        $info       = null;
   private static $neededInfo = ['prefix', 'tag'];

   /* this function shall return all object-specific information defined in $neededInfo */
   abstract protected function getInfo();

   /**
    *
    * @param Printer\Configuration $config
    * @param array $html_attr
    * @throws PrinterException
    */
   function __construct( $config, $html_attr = [] )
   {
      /* parent constructor, preset html attr,
       * ignore content - this item either has no content anyway, or the content is to be handled in derived constructor
       */
      parent::__construct( '', $html_attr, $config->children()->content() );

      /* split the content of the parameter array in known parameters and html attributes: */
      foreach( $config as $key => $value )
      {
         if( in_array($key, static::$paramlist) )
         {
            $this->params[$key] = $value;
         }
         else
         {
            $this->attr[$key] = $value;
         }
      }

      /* get the object specific information */
      $this->info = $this->getInfo();
      /* check if all needed information was provided */
      foreach( self::$neededInfo as $key )
      {
         /* sanity check: values need to be set, but can be null */
         if( !array_key_exists( $key, $this->info ) )
         {
            throw new PrinterException("needed information tag '$key' is missing.\n");
         }
      }

      /* store the tag for html\element */
      $this->tag = $this->info['tag'];

      /* autogeneration of commonly used parameters */
      if( isset($this->params['name']) )
      {
         /* generate htmlparam id from name */
         if( isset($this->params['idmanager']) && isset($this->info['prefix']) && isset($this->params['name']) )
         {
            $this->attr['id'] = $this->params['idmanager']->createId($this->info['prefix'] . $this->params['name']);
         }
         /* set label=name */
         if( ! array_key_exists('label', $this->params) )
         {
            $this->params['label'] = $this->getName();
         }
      }
   }

   protected function getValue($key = 'values')
   {
      $returnvalue = null;
      $name = $this->getName();
      if( array_key_exists($key, $this->params) && array_key_exists($name, $this->params[$key]) )
      {
         $returnvalue = $this->params[$key][$name];
      }
      if( is_array($returnvalue) && (1 == count($returnvalue)) )
      {
         $returnvalue = $returnvalue[0];
      }
      return $returnvalue;
   }

   public function getId()
   {
      return $this->attr['id'];
   }

   public function getName()
   {
      $name = null;
      if( isset($this->params['name']) )
      {
         $name = $this->params['name'];
         if( substr($name, - 2) === '[]' ) $name = substr($name, 0, - 2); // remove array() at end of name
      }
      return $name;
   }
}

/******************************************************************************************************
 * BASE CONTAINER CLASS
 *****************************************************************************************************/
class Container extends Base
{
   protected static $inherit = array( 'values', 'invalid', 'errmsg', 'idmanager', 'fscollect');

   protected function getInfo()
   {
      return [ 'tag' => $this->params['tag'], 'prefix' => $this->params['tag'] ];
   }

   /**
    * @param \dbfe\Form\Printer\ConfigurationIf $config
    * @param array $htmldef
    * @throws PrinterException
    */
   function __construct(Printer\Configuration $config, array $htmldef)
   {
      /* detach content for separate processing */
      $children = $config->detach();

      /* call basic constructor */
      parent::__construct($config, $htmldef);

      /* now parse the content definitions if existing */
      foreach( $children as $sub )
      {
         if( $sub instanceof \dbfe\HtmlElementIf )
         {
            $this->push($sub);
         }
         elseif( $sub instanceof Printer\ConfigurationIf )
         {
            /* check if type is specified for subitem */
            if( !isset($sub['type']) )
            {
               print_r($sub);
               throw new PrinterException('type is missing in element description');
            }
            /* get corresponding class name */
            $class = '\\' . __NAMESPACE__ . '\\' . ucfirst(strtolower($sub['type']));
            /* check if this class exists */
            if( !class_exists($class, false) )
            {
               $class = '\\' . __NAMESPACE__ . '\\' . 'Element';
            }

            /* inherit defined params if they do not already exist in subelement */
            foreach( static::$inherit as $key )
            {
               $sub[$key] = $this->params[$key];
            }

            /* create and store object of subelement class */
            $this->push( new $class($sub, []) );
         }
         else
         {
            throw new PrinterException('sub element is non-object and non-array. Cannot handle it');
         }
      }
   }
}

/******************************************************************************************************
 * ROOT CLASS
 *****************************************************************************************************/
class Printer extends Container
{
   protected $htmlDef = [ 'method' => 'post' ];

   private static $FormValidatorKeys = [ 'data' => 'values', 'msg' => 'errmsg', 'is_valid' => 'invalid'];

   protected function getInfo()
   {
      return [ 'tag' => 'form', 'prefix' => 'Form'];
   }

   /**
    * @param Printer\Configuration $cfg
    */
   function __construct( Printer\Configuration $cfg )
   {
      /* create the ID manager */
      $cfg['idmanager'] = new IdManager();
      $cfg['fscollect'] = new StringCollector();

      /* initialize values/errmsg/valid if not given */
      foreach( self::$FormValidatorKeys as $fvKey => $key )
      {
         /* preset to empty array */
         if( !isset($cfg[$key]) ) $cfg[$key] = [];
         /* copy FormValidator content */
         if( isset($cfg['FormValidator']) )
         {
            $cfg[$key] = array_merge($cfg[$key], $cfg['FormValidator']->$fvKey);
         }
      }
      /* logic of IsValid/invalid has to be inverted ==> all unregistered fields are valid */
      foreach( $cfg['invalid'] as &$val )
      {
         $val = !$val;
      }

      /* remove FormValidator from data, it shall not be further inherited */
      unset($cfg['FormValidator']);

      /* preset the inherited parameters if they do not exist */
      foreach( static::$inherit as $key )
      {
         if( !isset( $cfg[$key] ) )
         {
            $cfg[$key] = null;
         }
      }

      /* construct the element */
      parent::__construct($cfg, $this->htmlDef);

      /* prepend the fieldset navigation menu, if there are at least as many fieldsets as defined via nav_threshold */
      if( !isset($cfg['nav_threshold']) ) // set a default value if not set
      {
         $cfg['nav_threshold'] = 5;
      }

      if( $cfg['nav_threshold'] > 0 ) // 0 -> disabled
      {
         $navcfg = $cfg['fscollect']->getList();

         if( count($navcfg) >= $cfg['nav_threshold'] )
         {
            $navmenu = new HtmlElement('ul', ['class' => 'nav' ] );
            foreach( $navcfg as $id => $caption )
            {
               $navmenu->push( ['li', [], [ ['a', [ 'href' => '#'.$id ], $caption ] ] ] );
            }
            $this->unshift($navmenu);

            /* set up explicit action (->without possible anchor link) */
            $this->attr['action'] = $_SERVER['REQUEST_URI'];
         }
      }
   }
}

/******************************************************************************************************
 * ATOMIC ELEMENT
 *****************************************************************************************************/
class Atomic extends Base
{
  protected static $prefixList = [
      'text'     => 'Input',
      'checkbox' => 'Check',
      'submit'   => 'Button',
      'reset'    => 'Button',
      'textarea' => 'Input',
      'file'     => 'File'   ];

   protected static $taglist = [ 'textarea' ];

   protected static $htmlparList = [
      'input'    => [ 'type', 'name'],
      'textarea' => ['name']
   ];

   protected static $htmlDef = [
      'input'    => [ 'value' => ''],
      'hidden'   => [ 'value' => ''],
      'password' => [],
      'textarea' => [ 'cols' => '30', 'rows' => '5' ],
      'file'     => []
   ];

   protected static $textContainer = [ 'textarea', 'p', 'td', 'th' ];

   function __construct( $data )
   {
      if( !($data instanceof ConfigurationIf) ) $data = new Configuration($data);

      /* preset the tag with default */
      if( !isset($data['tag']) )
      {
         $data['tag'] = isset($data['type']) && in_array($data['type'], static::$taglist) ? $data['type'] : 'input';
      }

      /* get list of default html params */
      $htmlDefs =  isset($data['type']) && isset(static::$htmlDef[$data['type']]) ? static::$htmlDef[$data['type']]
                :( isset($data['tag']) && isset(static::$htmlDef[$data['tag']])   ? static::$htmlDef[$data['tag']]
                :                                                                   [] );

      /* create the object */
      parent::__construct($data, $htmlDefs, $data->children()->content());

      /* copy needed params to html attr */
      if( isset(static::$htmlparList[$this->tag]) )
      {
         foreach( static::$htmlparList[$this->tag] as $key )
         {
            if( isset($this->params[$key]) )
            {
               $this->attr[$key] = $this->params[$key];
            }
         }
      }

      /* get the value of this field */
      $value = $this->getValue();

      /* set the value to the output object if there is one */
      if( $value !== null )
      {
         if( in_array($this->tag, static::$textContainer) )
         {
            $this->push($value);
         }
         else if( array_key_exists('value', $this->attr) )
         {
            $this->attr['value'] = $value;
         }

         /* make the object fixed if needed */
         if( @$data['fixed'] && $value )
         {
            $this->attr['disabled'] = true;
            $this->attr['class'] = ((@$this->attr['class']) ? $this->attr['class'] . " " : "") . "fixed";
         }
      }
      else
      {
         /* always add any content to text container elements */
         if( in_array($this->tag, static::$textContainer) )
         {
            $this->push('');
         }
      }
   }

   protected function getInfo()
   {
      if( isset($this->params['prefix']) )
      {
         $prefix = $this->params['prefix'];
      }
      else if( isset($this->params['type']) && array_key_exists($this->params['type'], static::$prefixList) )
      {
         $prefix = static::$prefixList[$this->params['type']];
      }
      else
      {
         $prefix = null;
      }
      return [ 'tag' => $this->params['tag'], 'prefix' => $prefix ];
   }

   static public function _extendStatic()
   {
      static::$paramlist[] = 'tag';
      static::$paramlist[] = 'prefix';
      static::$paramlist[] = 'fixed';
   }
}

Atomic::_extendStatic();

/******************************************************************************************************
 * ATOMIC CONTAINER (e.g. select)
 *****************************************************************************************************/
class AtomicContainer extends Atomic
{
  function __construct( $data )
   {
      /* first retrieve the defined content */
      $content  = $data['selection'] ?? [];
      unset($data['selection']);

      /* retrieve any optional "disabled options" list */
      $disabled = $data['disabled_keys'] ?? [];
      unset($data['disabled_keys']);

      /* the type has to be the tag itself here */
      $data['tag'] = $data['type'];
      /* create the element */
      parent::__construct($data);
      /* get the currently set value */
      $selected = $this->getValue();
      /* create the options */
      foreach( $content as $key => $value )
      {
         $options = [ 'value' => $key, 'content' => [ $value??'' ], 'tag' => 'option' ];
         if( strval($key) === strval($selected) )
         {
            $options['selected'] = true;
         }

         if( !(@$this->attr['disabled'] || in_array($key, $disabled)) || @$options['selected'] )
         {
            $this->push(new Atomic($options));
         }
      }
   }

   public static function _extendStatic()
   {
      static::$prefixList['select']  = 'Sel';
      static::$htmlDef['select']     = [ 'size' => '1' ];
      static::$htmlparList['select'] = [ 'name' ];
   }
}
AtomicContainer::_extendStatic();

/******************************************************************************************************
 * GENERIC FORM ELEMENT
 *****************************************************************************************************/
class Element extends Base
{
  protected static $inherit = [ 'values', 'invalid', 'errmsg', 'idmanager', 'fscollect', 'type', 'name', 'fixed' ];

   protected function getInfo()
   {
      return [ 'tag' => $this->params['tag'] ?? 'p', 'prefix' => 'Box' ];
   }

   function __construct( $data )
   {
      /* check if field contains a "selection" */
      $selection = [];
      if( isset($data['selection']) )
      {
         $selection = $data['selection'];
         unset($data['selection']);
      }

      /* create this element */
      parent::__construct($data, []);

      /* prepare parameters of input element */
      $class = '\\' . __NAMESPACE__ . '\\Atomic';
      $params = [];
      foreach( static::$inherit as $key )
      {
         if( isset($this->params[$key]) )
         {
            $params[$key] = $this->params[$key];
         }
      }

      /* copy the content */
      if( count($selection) > 0 )
      {
         $params['selection'] = $selection;
         /* this element has to be an atomic container */
         $class = '\\' . __NAMESPACE__ . '\\AtomicContainer';
      }
      /* create defined subelements */
      $input = new $class( array_merge($this->attr, $params) );
      $label = new Atomic( [ 'tag' => 'label', 'name' => $this->params['name'], 'for' => $input->getId(), 'content' => $this->params['label'] ] );
      /* reset the html params of this container element */
      $this->attr = [ 'id' => $this->getId(), 'class' => $this->params['type'] . (isset($this->attr['class']) ? ' ' . $this->attr['class'] : '') ];

      /* add new sub elements to Html\Element content */
      $this->push($label);
      /* add error message if invalid field */
      if( $this->getValue('invalid') )
      {
         $this->push( new Atomic( [ 'tag' => 'span', 'class' => 'error', 'content' => $this->getValue('errmsg') ] ) );
      }
      $this->push($input);

      /*
       * if this element is disabled, add its value
       * additionally as hidden element to preserve it
       * (browsers won't send values of disabled fields)
       */
      /**@var $input Element */
      if( isset($input->attr['disabled']) )
      {
         $hidden = new Hidden( [ 'name' => $params['name'], 'values' => $params['values']] );
         $this->push($hidden);
      }
   }

   /**
    *
    * {@inheritdoc}
    * @see \dbfe\HtmlElement::asHtml()
    */
   public function asHtml($indent = 0, $shift = 2)
   {
      if( !empty($this->content) )
      {
         return parent::asHtml($indent, $shift);
      }
   }
}

/******************************************************************************************************
 * FIELDSET
 *****************************************************************************************************/
class Fieldset extends Container
{

   protected function getInfo()
   {
      return [ 'tag' => 'fieldset', 'prefix' => 'Fs'];
   }

   function __construct($data)
   {
      /* create the object */
      parent::__construct($data, []);
      /* create legend */
      $this->unshift( new Atomic([ 'tag' => 'legend', 'content' => $this->params['label'] ] ) );
      /* register this fieldset */
      $this->params['fscollect']->add( $this->params['label'], $this->getId() );
   }
}

/******************************************************************************************************
 * div container
 *****************************************************************************************************/
class Div extends Container
{
   protected function getInfo()
   {
      return ['tag' => 'div', 'prefix' => 'div'];
   }

   function __construct( $data, $htmldef )
   {
      parent::__construct($data, $htmldef);
   }

   public function asHtml($indent = 0, $shift = 2)
   {
      $result = '';
      if( $this->attr['transparent']??false )
      {
         foreach( $this->content as $entry )
         {
            $result .= $entry->asHtml($indent, $shift);
         }
      }
      else
      {
         $result = parent::asHtml($indent, $shift);
      }
      return $result;
   }
}

/******************************************************************************************************
 * INPUT TYPE="HIDDEN"
 *****************************************************************************************************/
/* Has to be defined as own class, because the hidden elements shall not be
 * encapsulated within the <p><label /><input /></p> construct like all other atomic elements
 * Now the Container constructor directly uses this class when he finds a type="hidden" child
 */
class Hidden extends Atomic
{
   function __construct($data)
   {
      $data['type'] = 'hidden'; // force/preset type
      parent::__construct($data);
   }
}

/******************************************************************************************************
 * INPUT TYPE="SUBMIT/RESET"
 *****************************************************************************************************/
 /* include buttons directly (without <p><label/></p> environment) */
class Submit extends Atomic
{
   protected function getValue($key = 'values')
   {
      $result = null;
      /* the 'values' shall be set in the form directly */
      if( $key != 'values' )
      {
         $result = parent::getValue($key);
      }
      return $result;
   }
}

class Reset extends Submit
{
}

/******************************************************************************************************
 * LABEL
 *****************************************************************************************************/
/* type provided to just print out some text
 */
class Label extends Atomic
{
  protected function getInfo()
   {
      return [ 'tag' => $this->params['tag'] ?? 'p', 'prefix' => 'Lbl' ];
   }

   function __construct($data)
   {
      parent::__construct($data);
      if( isset($data['label']) )
      {
         $this->unshift( new Atomic([ 'content' => $data['label'] . ': ', 'tag' => 'span', 'class' => 'label'] ) );
      }
      if( isset($this->params['text']) )
      {
         $this->push($this->params['text']);
      }
   }

   static function _extendStatic()
   {
      static::$paramlist[] = 'text';
   }
}
Label::_extendStatic();

/******************************************************************************************************
 * INPUT TYPE="FILE"
 *****************************************************************************************************/
class File extends Element
{
   function __construct($data)
   {
      $values = [];
      $array = false;

      /* save back value setting. Its handled differently for File */
      if( isset($data['values']) )
      {
         $values = $data['values'];
         unset($data['values']);
      }

      $data['type'] = 'file'; // force/preset type

      parent::__construct($data);

      /* get and preevaluate the name */
      $name = $this->params['name'];
      if( substr($name, - 2) === '[]' )
      {
         $name = substr($name, 0, - 2);
         $array = true;
      }

      if( isset($values[$name]) && is_array($values[$name]) )
      {
         $values[$name] = $values[$name][0];
      }

      if( @$this->params['fixed'] && $values[$name] )
      {
         /* still print current value as hidden field */
         $this->content = [ new Hidden([ 'name' => $name . ($array ? '[]' : ''), 'value' => $values[$name]]) ];
      }
      else if( isset($values[$name]) )
      {
         /* output the file path as hidden element, to have everything needed in the post data */
         $this->push(new Hidden( [ 'name' => $name . ($array ? '[]' : ''), 'value' => $values[$name] ] ) );
      }

      foreach( $this->params['display'] ?? [] as $sub )
      {
         $this->push($sub);
      }
   }

   public static function _extendStatic()
   {
      static::$paramlist[] = 'display';
   }
}

File::_extendStatic();

/******************************************************************************************************
 * INPUT TYPE="Checkbox"
 *****************************************************************************************************/
class Checkbox extends Element
{
  function __construct($data)
   {
      /* value has to be handled differently: if 'value' is in values,
       * then we have to be checked
       */
      $name = (substr($data['name'], - 2) === '[]') ? substr($data['name'], 0, - 2) : $data['name'];

      if( isset($data['value']) && isset($data['values'][$name]) &&
         (is_array($data['values'][$name]) ? in_array($data['value'], $data['values'][$name]) : $data['value'] === $data['values'][$name]) )
      {
         $data['checked'] = 'checked';
      }

      $data['values'] = [];

      parent::__construct($data);
   }
}

/******************************************************************************************************
 * BUTTONS
 *****************************************************************************************************/
class Buttonbox extends Base
{
  protected function getInfo()
   {
      return [ 'tag' => 'div', 'prefix' => 'Box' ];
   }

   function __construct($data)
   {
      $buttons = $data['buttons'];
      unset($data['buttons']);

      parent::__construct($data, []);

      foreach( $buttons as $type => $value )
      {
         if( is_string($value) )
         {
            $this->push(new Atomic([ 'type' => $type, 'value' => $value]));
         }
         else if( is_array($value) )
         {
            foreach( $value as $name => $caption )
            {
               $this->push( new Atomic([ 'type' => $type, 'name' => $name, 'value' => $caption]) );
            }
         }
         else
         {
            throw new PrinterException('invalid value in ButtonBox');
         }
      }

      $this->skip_ws = true;
   }
}

/******************************************************************************************************
 * Array group
 *****************************************************************************************************/
class ArrayGroup extends Base
{
   /* list of inherited parameters */
   protected static $inherit = [ 'values', 'invalid', 'errmsg', 'idmanager', 'fscollect' ];

   /* list of element-specific fields which may have to be split */
   protected static $split   = [ 'value', 'text' ];

   /**
    * @param \dbfe\Form\Printer\Configuration $cfg
    * @throws PrinterException
    */
   function __construct( Printer\ConfigurationIf $cfg )
   {
      $options = [ 'addempty' => true ];
      /** @var mixed $option */
      foreach( $options as $key => &$option )
      {
         if( isset($cfg[$key]) )
         {
            $option = $cfg[$key];
            unset($cfg[$key]);
         }
      }

      /* detach configuration content and rebuild it while processing it */
      $children_list = $cfg->detach();

      /* preset needed parameters (to avoid "array key does not exist" warnings) */
      if( !isset($cfg['name']) ) $cfg['name'] = null;

      /* call parent constructor */
      parent::__construct($cfg, []);

      /* check the lengths of the value arrays to be equal
       *
       * recursively go through all sub elements to also check input elements
       * in deeper nesting
       */
      $names  = [];
      $length = 0;
      /** @var $check_names callable */
      $check_names = function (&$content) use (&$names, &$length, &$cfg, &$check_names) {
         foreach( $content as &$sub )
         {
            if( isset($sub['name']) )
            {
               $name = $sub['name'];
               if( substr($name, - 2) === '[]' ) $name = substr($name, 0, - 2); // remove [] at end of name
               $names[$sub['name']] = $name; // store it back for later

               if( $sub['type'] === 'checkbox' ) continue; /* no length alignment for checkboxes */

               /* check if this array has more entries */
               if( array_key_exists($name, $cfg['values']) && is_array($cfg['values'][$name]) && ($length < count($cfg['values'][$name])) )
               {
                  $length = count($cfg['values'][$name]);
               }
            }
            if( isset($sub['content']) )
            {
               $check_names($sub['content']);
            }
         }
      };
      $check_names($children_list->content());

      /* fill shorter parts up */
      foreach( $children_list->content() as &$sub )
      {
         if( $sub['type'] === 'checkbox' ) continue; /* no length alignment for checkboxes */
         if( empty($sub['name']) ) continue;

         $name = $names[$sub['name']];

         if( isset($cfg['values'][$name]) && is_array($cfg['values'][$name]) && (count($cfg['values'][$name]) < $length) )
         {
            $cfg['values'][$name] = array_pad($cfg['values'][$name], $length, '');
         }
      }

      /* prepare single element list */
      foreach( $children_list->content() as &$sub )
      {
         /* retrieve 'split' parameters */
         $splitdata = [];
         foreach( static::$split as $key )
         {
            if( isset($sub[$key]) )
            {
               $splitdata[$key] = $sub[$key];
               unset($sub[$key]);
            }
         }
         /* check if type is specified for subitem */
         if( !isset($sub['type']) )
         {
            throw new PrinterException('type is missing in element description');
         }
         /* get corresponding class name */
         $class = '\\' . __NAMESPACE__ . '\\' . ucfirst(strtolower($sub['type']));
         /* check if this class exists */
         if( !class_exists($class, false) )
         {
            $class = '\\' . __NAMESPACE__ . '\\' . 'Element';
         }
         $sub = [ 'data' => $sub, 'class' => $class, 'split' => $splitdata ];
      }

      /* create list of sub elements + one additional, empty entry if enabled */
      for( $idx = 0; $idx < ($length + ($options['addempty'] ? 1 : 0)); $idx ++ )
      {
         $row = [];
         foreach( $children_list->content() as &$sub )
         {
            /* split existing properties */
            foreach( static::$split as $key )
            {
               if( isset($sub['split'][$key]) )
               {
                  if( isset($sub['split'][$key]) && $idx < count($sub['split'][$key]) )
                  {
                     $sub['data'][$key] = $sub['split'][$key][$idx];
                  }
                  else
                  {
                     continue 2; // no value assigned anymore, do not create element
                  }
               }
            }

            /* inherit defined params if they do not already exist in subelement */
            foreach( static::$inherit as $key )
            {
               if( !is_array($cfg[$key]) )      /* this parameter is a scalar, and not split regarding the fields, just copy it */
               {
                  $sub['data'][$key] = $cfg[$key];
               }
               else
               {
                  /* split all data values (regardless whether they are actually used in this element to keep it simple) */
                  foreach( $names as $name )
                  {
                     if( !isset($cfg[$key][$name]) ) /* this field does not exists for this parameter (values, errmsg, etc.) */
                     {
                        $sub['data'][$key][$name] = null;
                     }
                     else if( !is_array($cfg[$key][$name])        /* this parameter is a scalar for this field */
                            ||($sub['data']['type'] === 'checkbox')  /* special handling for checkboxes */
                     )
                     {
                        $sub['data'][$key][$name] = $cfg[$key][$name];
                     }
                     else if( $idx >= count($cfg[$key][$name]) ) /* this is the additional empty field */
                     {
                        $sub['data'][$key][$name] = '';
                     }
                     else /* this parameter is an array for this field */
                     {
                        $sub['data'][$key][$name] = $cfg[$key][$name][$idx];
                     }
                  }
               }
            }

            /* create and store object of subelement class */
            $row[] = new $sub['class'](clone $sub['data'], []);
         }
         /* store this row as <li> element in the list */
         $this->push( new Atomic([ 'tag' => 'li', 'name' => $cfg['name'].'[]', 'prefix' => 'Entry'
                                 , 'idmanager' => $cfg['idmanager'], 'fscollect' => $cfg['fscollect']
                                 , 'content' => $row ]) );
      }
   }

   protected function getInfo()
   {
      return [ 'tag' => 'ul', 'prefix' => 'Group' ];
   }
}

/******************************************************************************************************
 * Table
 *****************************************************************************************************/
class Table extends ArrayGroup
{
   /**
    * @param Printer\Configuration $data
    */
   function __construct( Printer\Configuration $data )
   {
      /* extract the headers from the content ("label") */
      $headers = array_map( function( $cell )
      {
         return new \dbfe\HtmlElement('th', [], [ $cell['label'] ] );
      }, $data->children()->content() );
      $data['addempty'] = false;

      /* create the actual content as array group */
      parent::__construct($data);

      foreach( $this->content as $row )
      {
         $row->tag = 'tr';
      }
      $this->content = [ new \dbfe\HtmlElement( 'tbody', [], $this->content ) ];
      $this->unshift( new \dbfe\HtmlElement('thead', [], [ [ 'tr', [], $headers ] ]) );
   }

   protected function getInfo()
   {
      return ['tag' => 'table', 'prefix' => 'view'];
   }
}

class Cell extends Atomic
{
   function __construct( $data )
   {
      $data['tag'] = 'td';       // force/preset tag
      parent::__construct( $data );
   }
}

/******************************************************************************************************
 * ID Manager
 *****************************************************************************************************/
/* One object of this class is used by the whole Form\Printer structure to manage all used IDs
 * This is needed to ensure that every used ID is unique.
 */
class IdManager
{
  private $m_idList = [];

  function createId( $id )
  {
    $count = '';
    /* check if field is defined as array */
    if( substr( $id, -2 ) === '[]' )
    {
      $id    = substr( $id, 0, -2 ); // remove array() suffix
      $count = '0';                  // preset counter
    }
    /* remove illegal chars */
    $id = preg_replace( "/^[^A-Za-z]|[^A-Za-z0-9_:.-]/", "", $id);
    /* check if this id is already used */
    if( array_key_exists( $id, $this->m_idList ) )
    {
      /* yes: increment associated counter */
      $this->m_idList[$id] += 1;
      $count = $this->m_idList[$id];
    }
    else
    {
      /* no: register it */
      $this->m_idList[$id] = 0;
    }
    return $id.$count;
  }
}

class StringCollector
{
   private $m_list = [];

   public function add( string $str, $key = null ): void
   {
      if( is_null($key) )
      {
         $this->m_list[] = $str;
      }
      else
      {
         $this->m_list[$key] = $str;
      }
   }

   public function getList(): array
   {
      return $this->m_list;
   }
}
