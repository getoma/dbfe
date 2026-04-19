<?php

namespace getoma\dbfe\Util\Exception;

/**
 * Exception class used for any error during file upload
 */
class UploadException extends \RuntimeException
{
   function __construct($message = null, $code = null, $previous = null)
   {
      if(!$message)
      {
         $errors = array_flip(get_defined_constants(true)['Core']);
         $message = $errors[$code] ?? 'unknown error';
      }
      parent::__construct( $message, $code, $previous );
   }
}
