<?php

namespace getoma\dbfe\Table\Column;

use getoma\dbfe\Form\Printer\Configuration\ConfigurationList;
use getoma\dbfe\Util\FileHandler\FileHandlerIf;
use getoma\dbfe\Util\HtmlElement\HtmlElement;
use getoma\dbfe\Util\LabelHandler\LabelHandlerIf;
use getoma\dbfe\Util\ValidatedInput;

use Respect\Validation\Validator as Validator;

class FileHandlerColumn extends PlainColumn implements FileHandlerColumnIf
{
   /**
    * constructor
    */
   public function __construct(
      PlainColumn|array $structure,
      string $table,
      protected readonly FileHandlerIf $fh,
      protected readonly DispType $display_type = DispType::link,
      protected readonly bool $support_delete = true
   )
   {
      parent::__construct($structure, $table, false);
   }

   /**
    * perform the upload of a file
    */
   public function handleUpload(ValidatedInput $data, string|array|null $rowid): void
   {
      $colname = $this->getAfixedName();
      $cur = $data->get($colname);

      if( $this->support_delete && $data->has($colname) )
      {
         $del = $data->get($this->getDeleteName());

         if( is_array($del) )
         {
            $this->dropFiles( $del );
            $cur = array_diff($cur, $del);
            $data->store($colname, $cur );
         }
         else if( $del === $cur )
         {
            $this->dropFiles( [ $del ] );
            $cur = null;
            $data->store($colname, null);
         }
      }

      if( is_array($cur) && $this->isPrimaryKey() )
      {
         /* only adding of new files allowed in this case */
         $data->store($colname, array_merge($cur, $this->fh->upload( $colname, [], [] ) ));
      }
      else
      {
         $data->store($colname, $this->fh->upload( $colname, $cur, $rowid ));
      }
   }

   public function dropFiles( array $files ): void
   {
      foreach( $files as $del )
      {
         $this->fh->delete( $del );
      }
   }

   /**
    * get a form specification that can be used as input to Form\Printer
    */
   public function getFormDefinition(LabelHandlerIf $lblHdl, array $data = [], bool $as_array = false ): \getoma\dbfe\Form\Printer\Configuration\ConfigurationListIf
   {
      $dname     = $this->getAfixedName();
      $form_name = $this->getAfixedName($as_array);

      $result = new ConfigurationList(
         [ array_merge( [ 'type' => 'file', 'accept' => $this->fh->getAccept(), 'fixed' => $as_array
                        , 'name' => $form_name, 'label' => $lblHdl->get( $this->getName(), $this->tablename ) ]
                        , $this->formProp ) ] );

      if( isset($data[$dname]) )
      {
         $links = array_map( function($id)
         {
            return [ 'url'  => $id? $this->fh->getFileUrl($id) : null,
                     'name' => $id? $this->fh->getFileName($id) : null ];
         }, is_array($data[$dname])? $data[$dname] : [$data[$dname]] );

         if( $this->display_type == DispType::link )
         {
            $text = array_map( function($link)
            {
               return sprintf( '<a href="%s" title="%s">%s</a>', $link['url'], $link['name'], $link['name']);
            }, $links);

            $result->add( [ 'type' => 'label', 'tag' => 'p', 'name' => 'link_'.$form_name, 'class' => 'Label',
                            'text' => ($as_array?$text:$text[0]) ], 0 );
         }
         else if( $this->display_type == DispType::img )
         {
            $disp = array_map( function($link)
            {
               return [ new HtmlElement( 'img', [ 'src' => $link['url'], 'alt' => $link['name'] ] ) ];
            }, $links );

            $result[0]['display'] = $as_array? $disp : $disp[0];
         }
         else
         {
            /* no displaying requested */
         }
      }

      if( $this->support_delete && !empty($data[$dname][0]) )
      {
         $result[] = [ 'type' => 'checkbox', 'label' => 'delete', 'name' => $this->getDeleteName(), 'class' => 'delete_entry'
                     , 'value' => $as_array? $data[$dname] : $data[$dname][0] ];
      }

      return $result;
   }

   /**
    */
   public function getValidatorConfig(bool $as_array = false, bool $optional = false): Validator
   {
      $result = parent::getValidatorConfig( $as_array );

      if( $this->support_delete )
      {
         $name = $this->getAfixedName() . '_del';
         $result = Validator::allOf( $result, Validator::key( $name, Validator::intVal()->not(Validator::negative()), false ) );
      }

      return $result;
   }

   protected function getDeleteName(): string
   {
      return $this->getAfixedName() . '_del';
   }
}
