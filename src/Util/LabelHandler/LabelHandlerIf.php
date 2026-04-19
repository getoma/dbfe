<?php

namespace getoma\dbfe\Util\LabelHandler;

interface LabelHandlerIf
{
   /**
    * get the label for a specific key
    * @param string $key
    */
   function get( string $key, string $category = '' );
}
