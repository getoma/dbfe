<?php

namespace getoma\dbfe\Form\Validator\Filter;

/**
 * general interface for usage as base
 * not all filter classes need the functionallity of the base class 'Filter'
 */
interface FilterInterface
{
   public function execute($value);
}
