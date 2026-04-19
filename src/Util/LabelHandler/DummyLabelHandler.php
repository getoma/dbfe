<?php

namespace getoma\dbfe\Util\LabelHandler;

/**
 * default label handler automatically used by dbfe when no handler given
 */
class DummyLabelHandler implements LabelHandlerIf
{
   public function get(string $key, string $category = '')
   {
      return $key;
   }
}
