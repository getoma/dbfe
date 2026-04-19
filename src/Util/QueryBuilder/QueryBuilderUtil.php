<?php

namespace getoma\dbfe\Util\QueryBuilder;

class QueryBuilderUtil
{
   static public function generateFilter( $filter )
   {
      if( count($filter) )
      {
         // generate "key=value" pairs as strings
         $whereList = array_map( function ($k, $v)
         {
            if( is_numeric($k) ) // numeric key -> filter condition is given completely
            {
               return $v;
            }
            else if( ($v === 'null') || $v === 'not null' )
            {
               return "$k is $v";
            }
            else if( is_null($v) )
            {
               return "$k is null";
            }
            else if( is_bool($v) )
            {
               return "$k=" . ($v+0);
            }
            else
            {
               return "$k=$v";
            }
         },
         array_keys( $filter ), $filter );

         // combine the where clause
         return join( ' and ', $whereList );
      }
      else
      {
         return '';
      }
   }
}
