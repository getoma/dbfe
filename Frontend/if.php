<?php  namespace dbfe;

interface dbfeIf
{
   /**
    * shall return title/h1 of page
    * @return string
    */
   public function getTitle();
   
   /**
    * return (internal) name of page
    */
   public function getName();
   
   /**
    * shall return a global decline message, or null if no message exists
    * @return string
    */
   public function getErrorMessage();
   
   /**
    * process any input from $_REQUEST
    * returns true if input accepted, false if not accepted, null if there was no input
    * @return bool|null
    */
    public function input();
    
    /**
     * print page contents to stdout
     */
    public function output();
    
    /**
     * print http header to stdout
     * returns true if normal page output can be written, or false if no further output shall be printed
     * @return bool
     */
    public function printHeader();
    
    /**
     * check if a certain action is allowed
     */
    public function isAllowed($action);
}
