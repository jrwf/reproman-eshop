<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;


final class HomepagePresenter extends BasePresenter
{
    public function renderDefault(): void
    {
        $session = $this->getSession();
    }


    public function renderHome()
    {
    }
}
