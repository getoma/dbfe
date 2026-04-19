<?php

namespace getoma\dbfe\Form\Validator\Filter;

/**
 * calls htmlspecialchars with predefined parameters
 */
class html extends Filter
{
   function __construct($params)
   {
      parent::__construct('htmlspecialchars', [ ENT_COMPAT | ENT_XHTML, 'UTF-8']);
   }
}
