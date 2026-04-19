<?php

namespace getoma\dbfe\Form\Validator\Constraint;

/**
 * check if value looks like an email address
 */
class Email extends Constraint
{
   function __construct(string $name = null)
   {
      parent::__construct("/^\s*[a-z0-9!#$%&'*+\/=?^_`{|}~-]+(?:\.[a-z0-9!#$%&'*+\/=?^_`{|}~-]+)*@(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\s*$/i", $name);
   }
}
