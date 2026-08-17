<?php

namespace getoma\dbfe\Form\Renderer;

use getoma\dbfe\Form\Generator\InputMask;

interface RendererIf
{
   /**
    * render a complete input mask into a string
    */
   function render( InputMask $mask ): string;
}
