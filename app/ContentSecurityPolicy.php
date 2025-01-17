<?php

namespace App;

use Spatie\Csp\Directive;
use Spatie\Csp\Keyword;
use Spatie\Csp\Policies\Policy;

class ContentSecurityPolicy extends Policy
{
    public function configure()
    {
        $this
            ->addDirective(Directive::CONNECT, Keyword::SELF)
            ->addDirective(Directive::IMG, Keyword::SELF)
            ->addDirective(Directive::BASE, Keyword::SELF)
            ->addDirective(Directive::FORM_ACTION, Keyword::SELF);
    }
}
