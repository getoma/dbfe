<?php

namespace getoma\dbfe\Form\Printer;

/******************************************************************************************************
 * ID Manager
 *****************************************************************************************************/
/* One object of this class is used by the whole Form\Printer structure to manage all used IDs
 * This is needed to ensure that every used ID is unique.
 */
class IdManager
{
  private $m_idList = [];

  function createId( $id )
  {
    $count = '';
    /* check if field is defined as array */
    if( substr( $id, -2 ) === '[]' )
    {
      $id    = substr( $id, 0, -2 ); // remove array() suffix
      $count = '0';                  // preset counter
    }
    /* remove illegal chars */
    $id = preg_replace( "/^[^A-Za-z]|[^A-Za-z0-9_:.-]/", "", $id);
    /* check if this id is already used */
    if( array_key_exists( $id, $this->m_idList ) )
    {
      /* yes: increment associated counter */
      $this->m_idList[$id] += 1;
      $count = $this->m_idList[$id];
    }
    else
    {
      /* no: register it */
      $this->m_idList[$id] = 0;
    }
    return $id.$count;
  }
}
