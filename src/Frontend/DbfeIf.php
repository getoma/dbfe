<?php

namespace getoma\dbfe\Frontend;

interface DbfeIf
{
   /**
    * shall return title/h1 of page
    */
   public function getTitle(): string;

   /**
    * return (internal) name of page
    */
   public function getName(): string;

   /**
    * shall return a global decline message, or null if no message exists
    */
   public function getErrorMessage(): ?string;

   /**
    * process any input from $_REQUEST
    * returns true if input accepted, false if not accepted, null if there was no input
    * @return bool|null
    */
    public function input(): ?bool;

    /**
     * generate page content
     */
    public function output(): \getoma\dbfe\Util\HtmlElement\HtmlElementIf;

    /**
     * whether page should redirect
     * @return string|null path to redirect to
     */
    public function getRedirect(): ?string;

    /**
     * check if a certain action is allowed
     */
    public function isAllowed(string $action);
}
