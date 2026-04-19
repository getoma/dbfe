<?php

namespace getoma\dbfe\Form\Validator\Filter;

/**
 * turns a german date into a mysql date
 */
class mysqldate implements FilterInterface
{
   public function execute($value)
   {
      $date = explode('.', $value);
      return sprintf('%04d-%02d-%02d', $date[2], $date[1], $date[0]);
   }
}
