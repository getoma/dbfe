<?php

namespace getoma\dbfe\Table\Column;

use getoma\dbfe\Form\Generator\Field\FileField;
use getoma\dbfe\Form\Generator\Node\NodeInterface;
use getoma\dbfe\Util\FileHandler\FileHandlerIf;
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
      protected readonly FileContentType $content_type = FileContentType::Opaque,
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
      $colname = $this->getAffixedName();
      $cur = $data->get($colname);

      if( $this->support_delete && $cur )
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

   public function getFormGeneratorDefinition(): NodeInterface
   {
      return new FileField(
         name:         $this->getAffixedName(),
         fh:           $this->fh,
         content_type: $this->content_type,
         required:     $this->isRequired(),
         fixed:        $this->isFixed(),
         accept:       $this->fh->getAccept(),
         delete_name:  $this->support_delete? $this->getDeleteName() : '',
         attributes:   $this->formProp,
      );
   }

   /**
    */
   public function getValidatorConfig(bool $as_array = false, bool $optional = false): Validator
   {
      $result = parent::getValidatorConfig( $as_array );

      if( $this->support_delete )
      {
         $name = $this->getAffixedName() . '_del';
         $result = Validator::allOf( $result, Validator::key( $name, Validator::optional(Validator::intVal()->not(Validator::negative())), false ) );
      }

      return $result;
   }

   protected function getDeleteName(): string
   {
      return $this->getAffixedName() . '_del';
   }
}
