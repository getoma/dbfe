<?php

namespace getoma\dbfe\Table\Column;

use getoma\dbfe\Form\Printer\Configuration\ConfigurationList;
use getoma\dbfe\Form\Printer\Configuration\Configuration;
use getoma\dbfe\Util\FileHandler\FileHandlerIf;
use getoma\dbfe\Util\HtmlElement\HtmlElement;
use getoma\dbfe\Util\LabelHandler\LabelHandlerIf;
use getoma\dbfe\Form\Validator\Constraint\FastConstructors as fvc;

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
    * @param array $data
    * @param string $rowid
    */
   public function handleUpload( array &$data, $rowid): void
   {
      $colname = $this->getAfixedName();

      if( $this->support_delete && !empty($data[$colname]) )
      {
         $del_cname = $this->getDeleteName();

         if( is_array($data[$del_cname]) )
         {
            $this->dropFiles( $data[$del_cname] );
            $data[$colname] = array_diff( $data[$colname], $data[$del_cname] );
         }
         else if( $data[$del_cname] === $data[$colname] )
         {
            $this->dropFiles( [ $data[$del_cname] ] );
            $data[$colname] = null;
         }
      }

      if( is_array($data[$colname]) && $this->isPrimaryKey() )
      {
         /* only adding of new files allowed in this case */
         $data[$colname] = array_merge($data[$colname], $this->fh->upload( $colname, [], [] ) );
      }
      else
      {
         $data[$colname] = $this->fh->upload( $colname, $data[$colname], $rowid );
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
    * {@inheritDoc}
    * @see \dbfe\PlainColumn::getValidatorConfig()
    */
   public function getValidatorConfig(bool $as_array = false): \getoma\dbfe\Form\Validator\Profile
   {
      $result = parent::getValidatorConfig( $as_array );

      if( isset($result) && $this->support_delete )
      {
         $name = $this->getAfixedName() . '_del';
         $result->optional[] = $name;
         $result->constraints[$name] = fvc::Integer(0);
      }

      return $result;
   }

   protected function getDeleteName(): string
   {
      return $this->getAfixedName() . '_del';
   }
}
