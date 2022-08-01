<?php

declare(strict_types=1);

namespace App\Presenters;

use Latte\Engine;
use Nette\Application\AbortException;
use Nette\Application\UI\Form;
use Nette\Mail\Message;
use Nette\Mail\SmtpMailer;
use stdClass;
use Tracy\Debugger;

class ContactPresenter extends BasePresenter
{
    public function renderContact(): void
    {
    }


    /**
     * @return Form
     */
    public function createComponentContactForm(): Form
    {
        $form = new Form();
        $form->addText('name', 'Vaše jméno:');
        $form->addText('phone', 'Telefon:');
//            ->setEmptyValue('+420');
        $form->addEmail('email', 'E-mail')
            ->setEmptyValue('@');
        $form->addTextArea('message', 'Vaše zpráva:')
            ->setHtmlAttribute('rows', 7);
        $form->addSubmit('save', 'Odeslat');
        $form->onSuccess[] = [$this, 'saveContactForm'];
        return $form;
    }


    /**
     * @param Form $form
     * @param stdClass $values
     */
    public function saveContactForm(Form $form, stdClass $values): void
    {
        $data = $form->getValues();

        // Latte
        $late = new Engine();
        $params = [
            'name' => $values->name,
            'email' => $values->email,
        ];

        // Email
        $email = new Message();
        $email->setFrom('eshop@reproman.cz');
        $email->setSubject('Zpráva z e-mailového formuláře.');
        $email->addTo($values->email);
        $email->setHtmlBody($late->renderToString(__DIR__ . '/templates/Contact/email-contact.latte', $params));

        // Mailer
        $mailer = new SmtpMailer([
            'host' => self::HOST,
            'username' => self::USERNAME,
            'password' => self::PASSWORD,
            'secure' => self::SECURE,
            'smtp' => self::SMTP,
        ]);

        // Odesílá se
        try {
            $mailer->send($email);
            $this->flashMessage('Vaše zpráva byla odeslána.');
        } catch (\Exception $e) {
            $this->flashMessage('Nepodařilo se to odeslat: ' . $e->getMessage());
            Debugger::log($e->getMessage());
        }
//        $this->redirect('this');
    }
}