<?php

namespace getoma\dbfe\Form\Printer;

class StringCollector
{
   private $m_list = [];

   public function add( string $str, $key = null ): void
   {
      if( is_null($key) )
      {
         $this->m_list[] = $str;
      }
      else
      {
         $this->m_list[$key] = $str;
      }
   }

   public function getList(): array
   {
      return $this->m_list;
   }
}
