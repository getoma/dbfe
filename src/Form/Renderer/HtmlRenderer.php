<?php declare(strict_types=1);

namespace getoma\dbfe\Form\Renderer;

use getoma\dbfe\Form\Generator\InputMask;
use getoma\dbfe\Table\Column\FileContentType;
use getoma\dbfe\Util\LabelHandler\LabelHandlerIf;

/**
 * default HTML renderer as provided with DBFE
 */
readonly class HtmlRenderer implements RendererIf
{
   public function __construct(
      protected readonly LabelHandlerIf $labelHandler,
      protected readonly int $nav_threshold = 3,
      protected readonly string $indent = '  ',
   )
   {
   }

   public function render( InputMask $mask ): string
   {
      $structure = $mask->toArray();

      $html = '';

      if( $this->nav_threshold )
      {
         $html .= $this->renderNav( $structure['children'] ?? [] );
      }

      $html .= match( $structure['type'] ?? null )
      {
         'group'       => $this->renderGroup( $structure ),
         'array-group' => $this->renderArrayGroup( $structure ),
         default       => $this->line( sprintf( '<p class="error">Cannot render structure of type %s</p>', $this->esc( $structure['type'] ?? '' ) ), 0 ),
      };

      return $html;
   }

   protected function renderNav( array $children ): string
   {
      $groups = array_filter( $children, static fn( array $c ): bool =>
         in_array( $c['type'] ?? null, ['group', 'array-group', 'table'], true )
         && ( !empty( $c['children'] ) || !empty( $c['rows'] ) )
      );

      if( count( $groups ) <= $this->nav_threshold )
      {
         return '';
      }

      $html = '';
      $html .= $this->line( '<nav>', 0 );
      $html .= $this->line( '<ul class="nav">', 1 );

      foreach( $groups as $child )
      {
         $html .= $this->line( sprintf( '<li><a href="#%s">%s</a></li>', $this->esc( $child['name'] ), $this->esc( $this->label( $child ) ) ), 2 );
      }

      $html .= $this->line( '</ul>', 1 );
      $html .= $this->line( '</nav>', 0 );

      return $html;
   }

   protected function hasFieldset( array $group, int $level ): bool
   {
      /* level 0 is the form itself
       * level 1 is the first layer of grouping, which we want to visualize with fieldsets
       * we do not generate nested fieldsets
       */
      return $level === 1;
   }

   protected function renderGroup( array $group, int $level = 0 ): string
   {
      $html = '';
      $hasFieldset = $this->hasFieldset($group, $level);

      if( $hasFieldset )
      {
         $html .= $this->line( sprintf( '<fieldset id="%s"><legend>%s</legend>', $this->esc( $group['name'] ), $this->esc( $this->label( $group ) ) ), $level );
      }

      $hasSelector = isset( $group['selector'] );
      if( $hasSelector )
      {
         $selectorIndent = $level + ( $hasFieldset ? 1 : 0 );
         $html .= $this->line( $this->renderCheckbox( $group['selector'], attr: ['class' => 'group-toggle'] ), $selectorIndent );
         $html .= $this->line( sprintf( '<div class="form-group" id="group-%s">', $this->esc( $group['name'] ) ), $selectorIndent );
      }

      $childLevel = $level + ( ( $hasFieldset || $hasSelector ) ? 1 : 0 );
      foreach( $group['children'] ?? [] as $child )
      {
         $html .= match( $child['type'] ?? null )
         {
            'group'       => $this->renderGroup( $child, $level + 1 ),
            'array-group' => $this->renderArrayGroup( $child, $level + 1 ),
            'table'       => $this->renderTable( $child, $childLevel ),
            'hidden'      => $this->line( $this->renderHidden( $child ), $childLevel ),
            'cell'        => $this->line( sprintf( '<p>%s</p>', $this->esc( $child['value'] ?? '' ) ), $childLevel ),
            default       => $this->line( sprintf( '<div class="form-element">%s</div>', $this->renderField( $child ) ), $childLevel ),
         };
      }

      if( $hasSelector )
      {
         $html .= $this->line( '</div>', $level + ( $hasFieldset ? 1 : 0 ) );
      }

      if( $hasFieldset )
      {
         $html .= $this->line( '</fieldset>', $level );
      }

      return $html;
   }

   protected function renderArrayGroup( array $group, int $level = 0 ): string
   {
      $html = '';
      $hasFieldset = $this->hasFieldset($group, $level);

      if( $hasFieldset )
      {
         $html .= $this->line( sprintf( '<fieldset id="%s"><legend>%s</legend>', $this->esc( $group['name'] ), $this->esc( $this->label( $group ) ) ), $level );
      }

      $tableIndent = $level + ( $hasFieldset ? 1 : 0 );
      $html .= $this->line( '<table class="form-table">', $tableIndent );
      $html .= $this->line( '<thead>', $tableIndent + 1 );
      $html .= $this->line( '<tr>', $tableIndent + 2 );
      foreach( $group['columns'] ?? [] as $column )
      {
         $html .= $this->line( sprintf( '<th scope="col">%s</th>', $this->esc( $this->label($column) ) ), $tableIndent + 3 );
      }
      $html .= $this->line( '</tr>', $tableIndent + 2 );
      $html .= $this->line( '</thead>', $tableIndent + 1 );
      $html .= $this->line( '<tbody>', $tableIndent + 1 );

      $rows            = $group['rows'] ?? [];
      $lastIndex       = count( $rows ) - 1;
      $isScaffolded    = $group['is_scaffolded'] ?? false;
      $hasDeleteColumn = $group['has_delete_column'] ?? false;

      foreach( $rows as $rowIndex => $row )
      {
         $isLastRow = ( $rowIndex === $lastIndex );
         $html .= $this->line( sprintf( '<tr%s>', ( $isLastRow && $isScaffolded ) ? ' class="scaffold"' : '' ), $tableIndent + 2 );

         foreach( $row as $cellIndex => $child )
         {
            $html .= $this->line( match( $child['type'] ?? null )
            {
               'hidden' => $cellIndex === 0
                  ? sprintf(
                     '<th scope="row"><span>%s %s</span>%s</th>',
                     $this->esc( $this->label( $child ) ),
                     $this->esc( $this->valueOf( $child ) ),
                     $this->renderHidden( $child ),
                  )
                  : sprintf( '<td>%s</td>', $this->renderHidden( $child ) ),
               'cell'   => sprintf( '<td>%s</td>', $this->esc( $child['value'] ?? '' ) ),
               default  => sprintf( '<td>%s</td>', $this->renderField( $child ) ),
            }, $tableIndent + 3 );
         }

         if( $isLastRow && $isScaffolded && $hasDeleteColumn )
         {
            $html .= $this->line( '<td></td>', $tableIndent + 3 );
         }

         $html .= $this->line( '</tr>', $tableIndent + 2 );
      }

      $html .= $this->line( '</tbody>', $tableIndent + 1 );
      $html .= $this->line( '</table>', $tableIndent );

      if( $hasFieldset )
      {
         $html .= $this->line( '</fieldset>', $level );
      }

      return $html;
   }

   protected function renderField( array $structure, array $attr = [] ): string
   {
      $html = '';

      if( $this->attributesOf( $structure )['disabled'] ?? false )
      {
         /* disabled inputs are not submitted by the browser, so mirror the value in a hidden field */
         $html .= $this->renderHidden( $structure, $attr );
      }

      return $html . match( $structure['type'] ?? null )
      {
         'boolean'  => $this->renderCheckbox( $structure, $attr ),
         'select'   => $this->renderSelect( $structure, $attr ),
         'textarea' => $this->renderTextarea( $structure, $attr ),
         'file'     => $this->renderFile( $structure, $attr ),
         'button'   => $this->renderButton( $structure, $attr ),
         default    => $this->renderInput( $structure, $attr ),
      };
   }

   protected function renderInput( array $structure, array $attr = [] ): string
   {
      $input = sprintf(
         '<input type="%s" name="%s" value="%s"%s>',
         $this->esc( $structure['type'] ?? 'text' ),
         $this->renderName( $structure ),
         $this->esc( $this->valueOf( $structure ) ),
         $this->renderAttributesFor( $structure, $attr ),
      );

      return $this->wrapLabeled( $this->esc( $this->label( $structure ) ), $input, $this->errorOf( $structure ) );
   }

   protected function renderCheckbox( array $structure, array $attr = [] ): string
   {
      $name = $this->renderName( $structure );

      /* add an hidden field with same name to enforce its existence in the sent data,
       * even if the checkbox is not checked */
      $hidden = sprintf('<input type="hidden" name="%s">', $name);

      $input = sprintf(
         '<input type="checkbox" name="%s"%s%s>',
         $name,
         $this->renderAttributesFor( $structure, $attr ),
         $this->valueOf( $structure ) ? ' checked' : '',
      );

      return sprintf(
         '<label>%s%s<span>%s%s</span></label>',
         $this->renderErrorSpan( $this->errorOf( $structure ) ),
         $hidden,
         $input,
         $this->esc( $this->label( $structure ) ),
      );
   }

   protected function renderTextarea( array $structure, array $attr = [] ): string
   {
      $control = sprintf(
         '<textarea name="%s"%s>%s</textarea>',
         $this->renderName( $structure ),
         $this->renderAttributesFor( $structure, $attr ),
         $this->esc( $this->valueOf( $structure ) ),
      );

      return $this->wrapLabeled( $this->esc( $this->label( $structure ) ), $control, $this->errorOf( $structure ) );
   }

   protected function renderHidden( array $structure, array $attr = [] ): string
   {
      return sprintf(
         '<input type="hidden" name="%s" value="%s">',
         $this->renderName( $structure ),
         $this->esc( $this->valueOf( $structure ) ),
      );
   }

   protected function renderButton( array $structure, array $attr = [] ): string
   {
      return sprintf(
         '<button type="%s" name="%s" value="%s"%s>%s</button>',
         $this->esc( $structure['button_type'] ?? 'submit' ),
         $this->renderName( $structure ),
         $this->esc( $this->valueOf( $structure ) ),
         $this->renderAttributesFor( $structure, $attr ),
         $this->esc( $this->label( $structure ) ),
      );
   }

   protected function renderSelect( array $structure, array $attr = [] ): string
   {
      $required = $this->attributesOf( $structure )['required'] ?? false;
      $value    = $this->valueOf( $structure );

      $options = ( !$required || empty( $value ) ) ? '<option value="">N/A</option>' : '';

      foreach( $structure['options'] ?? [] as $key => $option )
      {
         $selected = ( (string)$value === (string)$key ) ? ' selected' : '';
         $options .= sprintf( '<option value="%s"%s>%s</option>', $this->esc( $key ), $selected, $this->esc( $option ) );
      }

      $control = sprintf(
         '<select name="%s"%s>%s</select>',
         $this->renderName( $structure ),
         $this->renderAttributesFor( $structure, $attr ),
         $options,
      );

      return $this->wrapLabeled( $this->esc( $this->label( $structure ) ), $control, $this->errorOf( $structure ) );
   }

   protected function renderFile( array $structure, array $attr = [] ): string
   {
      $html = '';

      $storedFile = $structure['stored_file'] ?? null;
      if( $storedFile )
      {
         $html .= match( $structure['file_content_type'] ?? FileContentType::Opaque->name )
         {
            FileContentType::Image->name => sprintf( '<img src="%s" alt="%s" width="64">', $this->esc( $storedFile['url'] ), $this->esc( $storedFile['name'] ) ),
            default => sprintf( '<p><a href="%s">%s</a></p>', $this->esc( $storedFile['url'] ), $this->esc( $storedFile['name'] ) ),
         };
         $html .= $this->renderHidden($structure);
      }

      if( !($this->attributesOf( $structure )['disabled'] ?? false) )
      {
         $control = sprintf(
            '<input type="file" name="%s" value="%s"%s>',
            $this->renderName( $structure ),
            $this->esc( $this->valueOf( $structure ) ),
            $this->renderAttributesFor( $structure, $attr ),
         );

         $html .= $this->wrapLabeled( $this->esc( $this->label( $structure ) ), $control, $this->errorOf( $structure ) );
      }

      if( $structure['delete_name'] ?? null )
      {
         $html .= $this->renderCheckbox(
            [ 'name' => $structure['delete_name'],
              'attributes' => ['value' => $this->valueOf( $structure )],
              'field_id' => $structure['field_id'] ?? '',
            ]
         );
      }

      return $html;
   }

   protected function renderTable( array $structure, int $level = 0 ): string
   {
      $rows = $structure['rows'] ?? [];
      if( empty( $rows ) )
      {
         return '';
      }

      $html = '';
      $html .= $this->line( sprintf( '<table id="%s" class="view">', $this->esc( $structure['name'] ) ), $level );
      $html .= $this->line( sprintf( '<caption>%s</caption>', $this->esc( $this->label( $structure ) ) ), $level + 1 );
      $html .= $this->line( '<thead>', $level + 1 );
      $html .= $this->line( '<tr>', $level + 2 );
      foreach( $structure['columns'] ?? [] as $column )
      {
         $html .= $this->line( sprintf( '<th scope="col">%s</th>', $this->esc( $this->label($column) ) ), $level + 3 );
      }
      $html .= $this->line( '</tr>', $level + 2 );
      $html .= $this->line( '</thead>', $level + 1 );
      $html .= $this->line( '<tbody>', $level + 1 );

      foreach( $rows as $row )
      {
         $html .= $this->line( '<tr>', $level + 2 );
         foreach( $row as $cell )
         {
            /* unlike every other value in this class, table view cells intentionally carry pre-rendered raw HTML */
            $html .= $this->line( sprintf( '<td>%s</td>', $cell['value'] ?? '' ), $level + 3 );
         }
         $html .= $this->line( '</tr>', $level + 2 );
      }

      $html .= $this->line( '</tbody>', $level + 1 );
      $html .= $this->line( '</table>', $level );

      return $html;
   }

   protected function line( string $html, int $level ): string
   {
      return str_repeat( $this->indent, $level ) . $html . "\n";
   }

   protected function renderAttributes( array $attributes ): string
   {
      $html = '';
      foreach( $attributes as $key => $value )
      {
         if( $value === false )
         {
            continue;
         }

         $html .= ( $value === true )
            ? sprintf( ' %s', $this->esc( $key ) )
            : sprintf( ' %s="%s"', $this->esc( $key ), $this->esc( $value ) );
      }

      return $html;
   }

   protected function renderAttributesFor( array $node, array $extra = [] ): string
   {
      return $this->renderAttributes( array_merge( $this->attributesOf( $node ), $extra ) );
   }

   protected function wrapLabeled( string $labelHtml, string $controlHtml, ?string $error ): string
   {
      return sprintf( '<label><span>%s</span>%s%s</label>', $labelHtml, $controlHtml, $this->renderErrorSpan( $error ) );
   }

   protected function renderErrorSpan( ?string $error ): string
   {
      return $error? sprintf( '<span class="error">%s</span>', $this->esc( $error ) ) : '';
   }

   protected function attributesOf( array $node ): array
   {
      return $node['attributes'] ?? [];
   }

   protected function valueOf( array $node ): ?string
   {
      return is_null($node['value']??null) ? null : (string)$node['value'];
   }

   protected function errorOf( array $node ): ?string
   {
      return $node['error'] ?? null;
   }

   protected function renderName( array $node ): string
   {
      return $this->esc($node['name']) . ($node['field_id'] ?? '');
   }

   protected function label( array|string $structure ): string
   {
      // if label is explicitly set already, return it directly
      if( is_array($structure) && isset($structure['label']) ) return $structure['label'];
      // get the name of this field, and try to split it into category and actual name at the first dot
      $name = is_string($structure)? $structure : $structure['name'];
      [ $category, $label ] = explode( '-', $name, 2 ) + [ 1 => '' ];
      if( $label ) // if actual category/label split found, use it to retrieve the display label
      {
         return $this->labelHandler->get( $label, $category );
      }
      else // otherwise only use name with no category
      {
         return $this->labelHandler->get($name);
      }
   }

   protected function esc( mixed $value ): string
   {
      return htmlspecialchars( (string)$value );
   }
}
