<?php

namespace getoma\dbfe\Form\Validator;


class Field
{
   public $constraints = [];
   public $defaults    = null;
   public $needed      = false;
   public $filters     = [];
   public $array       = false;
   public $depgroup    = null;
   public $name        = null;
}
