<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette\Application\AbortException;
use Nette\Application\UI\Form;
use stdClass;
use Tracy\Debugger;

class AdvisePresenter extends BasePresenter
{
    public function renderDefault(): void
    {
    }


    public function renderAdvise(): void
    {
    }


    /**
     * @return Form
     */
    public function createComponentAdviceForm(): Form
    {
        $form = new Form();
        $form->addText('name', 'Jméno a příjmení:');
        $form->addEmail('email', 'E-mail:');
        $form->addTextArea('question', 'Dotaz:');
        $form->addSubmit('save', 'Odeslat');
        $form->onSuccess[] = [$this, 'saveAdviceForm'];
        return $form;
    }


    /**
     * @param Form $form
     * @param stdClass $values
     *
     * @throws AbortException
     */
    public function saveAdviceForm(Form $form, stdClass $values): void
    {
        $emailParams = [
            'from' => 'eshop@reproman.cz',
            'to' => $values->email,
            'subject' => 'Dotaz z webových stránek reproman.cz',
            'template' => __DIR__ . '/templates/Advise/emails/email-advice.latte',
            'obsah' => 'obsah',
            'params' => [
                'name' => $values->name,
                'email' => $values->email,
                'question' => $values->question,
            ],
        ];
        try {
            $this->sendEmail($emailParams);
            $this->flashMessage('Odeslano');
        } catch (\Exception $e) {
            $this->flashMessage('Nepodařilo se bohužel odeslat Váš email, zkuste to prosím později.');
            Debugger::log('Nepodařilo se odeslat e-mail: ' . $e->getMessage() . ' ze souboru ' . $e->getFile());
        }
        $this->redirect('this');
    }
}