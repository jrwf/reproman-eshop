<?php

declare(strict_types=1);

namespace App\Presenters;

use Latte\Engine;
use Mpdf\Mpdf as mPdf;
use Mpdf\MpdfException;
use Nette\Application\AbortException;
use Nette\Application\UI\Form;
use Nette\Mail\Message;
use Nette\Mail\SmtpMailer;
use stdClass;
use App\Model\EshopManager;
use Tracy\Debugger;

class EshopPresenter extends BasePresenter
{
    /** @var EshopManager */
    public EshopManager $eshopManager;

    /**
     * @param EshopManager $eshopManager
     */
    public function __construct(EshopManager $eshopManager)
    {
        parent::__construct($eshopManager);
        $this->eshopManager = $eshopManager;
    }


    /**
     * @return void
     */
    public function renderDefault(): void
    {
        $session = $this->getSession();
        $eshopForm = $session->getSection('packetaPlace');
        if ($this->orderSessionId()) {
            $order = $this->eshopManager->eshopOrder($this->orderSessionId());
            if (isset($order) && $order !== '') {
                $this->template->packetaPlace = $order->packetaPlace;
                $this->template->order = $order;
                $this->getComponent('eshopForm')->setDefaults($order->toArray());
            }
        } else {
//            $this->template->packetaPlace = '';
//            $this->template->order = '';
        }
        $this->template->devProd = $this->environment();
        $this->template->productPriceOne = self::PRICE_ONE;
        $this->template->productPriceThree = self::PRICE_THREE;
        $this->template->productPriceSix = self::PRICE_SIX;
    }


    /**
     * @return Form
     */
    public function createComponentEshopForm(): Form
    {
        $form = new Form();
        $productList = [
            'one' => 'Jeden měsíc',
            'two' => 'Dva měsíce',
            'three' => 'Tři měsíce',
        ];
        $form->addRadioList('product', 'Výběr produktu:', $productList)
//            ->setHtmlAttribute('onclick', 'myFunction(this.value)')
            ->setHtmlAttribute('onclick', 'getRadioValue()')
//            ->setDefaultValue('one')
            ->setRequired('Nemáte vybrán žádný produkt.');
        $form->addText('name', 'Jméno')
            ->setRequired('Musíte zadat jméno.');
        $form->addText('surname', 'Příjmení:')
            ->setRequired('Musíte zadat příjmení.');

        // Contact address
        $form->addText('contactStreet', 'Ulice:')
            ->setRequired('Musíte zadat název ulice.');
        $form->addText('houseNumber', 'č. p.')
            ->setRequired(true);
        $form->addText('contactCity', 'Město:')
            ->setRequired('Musíte zadat název města.');
        $form->addText('contactPsc', 'PSČ:')
            ->setRequired('Musíte zadat PSČ.');
//            ->addFilter(function ($value) {
//                return str_replace(' ', '', $value); // odstraníme mezery z PSČ
//            })
//            ->addRule($form::PATTERN, 'PSČ není ve tvaru pěti číslic', '\d{5}');
        $delivery = [
            'place' => 'Výdejní místo:',
            'address' => 'Na adresu',
        ];
        $form->addRadioList('delivery', 'Doručení:', $delivery)
            ->setRequired('Vyberte si způsob doručení.')
            ->addCondition($form::EQUAL, 'place')
            ->toggle('delivery-place')
            ->elseCondition($form::EQUAL, 'address')
            ->toggle('delivery-address');

        $state = [
            'cz' => 'Česká republika',
            'sk' => 'Slovenská republika',
        ];
        $form->addSelect('state', 'Stát', $state)
            ->setRequired('Musíte zadat název státu.')
            ->setDefaultValue('cz');

        // Checkbox pro billingAddress
        $form->addCheckbox('billingAddress', 'Fakturační adresa pokud je jiná než kontaktní:')
            ->setDefaultValue(false)
            ->setRequired(false)
            ->addCondition($form::EQUAL, true)
            ->toggle('billingStreet')
            ->toggle('billingCity')
            ->toggle('billingPsc');

        // Billing adress
        $form->addText('billingStreet', 'Ulice a č. p.:')
            ->setOption('id', 'billingStreet');
        $form->addText('billingCity', 'Město:')
            ->setOption('id', 'billingCity');
        // TODO - zkontrolovat formát;
        $form->addText('billingPsc', 'Psc:')
//            ->addFilter(function ($value) {
//                return str_replace(' ', '', $value); // odstraníme mezery z PSČ
//            })
//            ->addRule($form::PATTERN, 'PSČ není ve tvaru pěti číslic', '\d{5}')
            ->setOption('id', 'billingPsc');

        // TODO - zkontrolovat formát;
        $form->addText('phone', 'Telefon:')
            ->setHtmlType('tel');
        // TODO - zkontrolovat formát;
        $form->addEmail('email', 'E-mail:')
            ->setHtmlType('email');
        $form->addTextArea('note', 'Poznámka:');
        $form->addCheckbox('agreement', 'Souhlasím s obchodními podmínkami:')
            ->setRequired('Musíte potvrdit souhlas s obchodními podmínkami.');
        $form->addHidden('packetaId')
            ->setHtmlAttribute('id', 'packeta-point-id');
        $form->addHidden('packetaPlace')
            ->setHtmlAttribute('id', 'packeta-place');
        $form->addHidden('packetaAttribute')
            ->setHtmlAttribute('id', 'packeta-attribute');
        $form->addSubmit('save', 'Objednat a zaplatit')
            ->setHtmlAttribute('onclick', 'displayRadioValue()')
            ->setHtmlAttribute('class', 'button');
        $form->onValidate[] = [$this, 'validateEshopForm'];
        return $form;
    }


    /**
     * @param Form $form
     * @param stdClass $values
     *
     * @return Form
     */
    public function validateEshopForm(Form $form, \stdClass $values): Form
    {
        $session = $this->getSession();
        $eshopForm = $session->getSection('packetaPlace');
        $eshopFormValues = $eshopForm->set('formValue', 'formular');

        if ($values->packetaPlace === '') {
            $eshopForm->set('packetaPlace', 'nevyplnena');
        }

        if (($values->packetaId === '0' || $values->packetaId === '') && $values->delivery !== 'address') {
            $this->flashMessage('Nemáte vybráno výdejní místo');
        }

        if ($values->name === '') {
            $form['name']->addError('Musíte zadat jméno.');
        }

        if ($values->product === null) {
            $form['product']->addError('Musíte vybrat produkt.');
        }

        if ($values->surname === '') {
            $form['surname']->addError('Musíte zadat příjmení.');
        }

        // TODO - validovat telefonní čislo, počet znaků apd.
        if ($values->phone === '') {
            $form['phone']->addError('Musíte zadat číslo telefonu.');
        }

        // TODO - validovat email.
        if ($values->email === '') {
            $form['email']->addError('Musíte zadat Vaši e-mailovou adresu.');
        }

        if ($values->contactStreet === '') {
            $form['contactStreet']->addError('Musíte zadat ulici.');
        }

        if ($values->contactCity === '') {
            $form['contactCity']->addError('Musíte zadat město.');
        }

        if ($values->agreement === '') {
            $form['agreement']->addError('Musíte potvrdit souhlas s obchodními podmínkami.');
        }

        if ($values->contactPsc !== '') {
            // TODO - pročistit.
            trim($values->contactPsc);
            (int)$string = str_replace(' ', '', strip_tags($values->contactPsc));
            if (!is_int((int)$string)) {
                $form['contactPsc']->addError('Musi to byt číslo.');
            } elseif (strlen($string) != 5) {
                $form['contactPsc']->addError('PSČ musí mít pět čísel.');
            }
        } else {
            $form['contactPsc']->addError('Zadejte PSČ');
        }
        $form->onSuccess[] = [$this, 'saveEshopFormSucceeded'];
        return $form;
    }


    /**
     * @param Form $form
     * @param stdClass $values
     *
     * @throws AbortException
     */
    public function saveEshopFormSucceeded(Form $form, stdClass $values): void
    {
        $sessionId = $this->orderSessionId();

        // TODO - odstranit až dořeším zásilkovnu
        if ($values->packetaId === '') {
            $values->packetaId = 0;
        }

        // Vrací id podle vybrané země.
        $deliveryId = $this->priceDeliveryToAddress($values);

        $order = $this->eshopManager->eshopOrder($sessionId);
        if ($order === null) {
            $this->eshopManager->eshopInsert($values, $sessionId, $deliveryId);
        } else {
            $this->eshopManager->eshopUpdate($values, $sessionId, $deliveryId);
        }

        // TODO - doplnit hlášku.
        $this->redirect('Payment:default');
    }


    public function renderSummary(): void
    {
        if ($this->orderSessionId()) {
            $summary = $this->eshopManager->eshopOrder($this->orderSessionId());
            if ($summary !== null) {
                $this->template->summary = $summary;
            } else {
                $this->redirect('Eshop:nonepage');
            }
        }
        $this->template->productPriceOne = self::PRICE_ONE;
        $this->template->productPriceThree = self::PRICE_THREE;
        $this->template->productPriceSix = self::PRICE_SIX;
    }


    /**
     * @throws MpdfException
     * @throws AbortException
     */
    public function renderConfirm(): void
    {
        $showMessage = true;
        // Mění status objednávky na 1
        $this->eshopManager->eshopOrderPaid($this->orderSessionId());
        $order = $this->eshopManager->eshopOrderPaidResult($this->orderSessionId());
        if ($order === null) {
            $this->redirect('Eshop:nonepage');
        }

        // Nastavuje se cena.
        if ($order->product === 'one') {
            $priceWithVat = self::PRICE_ONE;
            $weight = 0.2;
        } elseif ($order->product === 'two') {
            $priceWithVat = self::PRICE_THREE;
            $weight = 0.6;
        } elseif ($order->product === 'three') {
            $priceWithVat = self::PRICE_SIX;
            $weight = 1;
        }

        // Odesílají se data do Packety
        $url = "https://www.zasilkovna.cz/api/rest";
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $headers = array(
            "Accept: application/json",
            "Content-Type: application/json",
        );
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        if ($this->environment() === 'dev') {
            $packetaApiPass = self::PACKETA_API_PASS_TEST;
        } elseif ($this->environment() === 'prod') {
            $packetaApiPass = self::PACKETA_API_PASS;
        }
        bdump($this->environment(), 'environment');
        bdump($packetaApiPass, 'packeta api');

        if ($order['delivery'] === 'place') {
            $data = "<createPacket>
                    <apiPassword>" . $packetaApiPass . "</apiPassword>
                    <packetAttributes>
                        <number>" . $order->orderId . "</number>
                        <name>" . $order->name . "</name>
                        <surname>" . $order->surname . "</surname>
                        <email>" . $order->email . "</email>
                        <addressId>" . $order->packetaId . "</addressId>
                        <value>" . $priceWithVat . "</value>
                        <eshop>reproman.cz</eshop>
                        <weight>" . $weight . "</weight>
                        <phone>" . $order->phone . "</phone>
                    </packetAttributes>
                </createPacket>";
        } elseif ($order['delivery'] === 'address') {
            // TODO - Nastavit address id a housenumber !!!!!
            $data = "<createPacket>
                    <apiPassword>" . $packetaApiPass . "</apiPassword>
                    <packetAttributes>
                        <number>" . $order->orderId . "</number>
                        <name>" . $order->name . "</name>
                        <surname>" . $order->surname . "</surname>
                        <email>" . $order->email . "</email>
                        <addressId>106</addressId>
                        <value>" . $priceWithVat . "</value>
                        <eshop>reproman.cz</eshop>
                        <weight>" . $weight . "</weight>
                        <street>" . $order->contactStreet . "</street>
                        <houseNumber>" . $order->houseNumber . "</houseNumber>
                        <city>" . $order->contactCity . "</city>
                        <zip>" . $order->contactPsc . "</zip>
                        <phone>" . $order->phone . "</phone>
                    </packetAttributes>
                </createPacket>";
        }
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        $result = curl_exec($curl);
        if (curl_errno($curl)) {
//            $this->flashMessage('Chyba: ' . curl_error($curl));
            Debugger::log('Nepodařilo se odeslat data do zásilkovny. ' . curl_error($curl));
        }
        $respons = json_encode($result);

        $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($http_status !== 200) {
            Debugger::log('Nepodařilo se odeslat data do zásilkovny. ' . $http_status);
        }
        curl_close($curl);
//
        if ($order !== null) {
            // Odeslat email pro zákazníka
            $template = $this->templateFactory->createTemplate();
            $template->setFile(__DIR__ . '/templates/Eshop/emails/customer-invoice.latte');
            $value = [
                'variableSymbol' => 333333,
            ];
            $item = $this->priceDecomposition($order['product']);
            $template->item = $this->priceDecomposition($order['product']);
            $template->packetaPrice = $this->packetaPrice($order['state'], $order['delivery']);
            $template->variable = $order;
            $objednavka = [
                'objednavka' => $order,
                'productPrice' => $this->productPrice($order['product']),
            ];
            $pdf = new mPdf(['tempDir' => __DIR__ . '/templates/Eshop/emails/tmp']);
            $pdf->WriteHTML($template);
            $content = $pdf->Output('', 'S');

            // Email pro administratora.
            $latte = new Engine();
            $email = new Message();
            $email->setFrom(self::FROM, 'Reproman | reproman.cz');
            $email->setSubject('www.reproman.cz - potvrzení objednávky');
            $email->addTo($order->email);
            $email->setHtmlBody($latte->renderToString(__DIR__ . '/templates/Eshop/emails/email-order-confirm.latte', $objednavka), 'images/');
            $email->addAttachment('files/pribalovy-letak.pdf');
            $email->addAttachment('files/vseobecne-obchodni-podminky-eshop-reproman-cz.pdf');
//            $email->addAttachment($this->getHttpRequest()->getUrl()->getBaseUrl() . 'files/pribalovy-letak.pdf');
//            $email->addAttachment($this->getHttpRequest()->getUrl()->getBaseUrl() . 'files/vseobecne-obchodni-podminky-eshop-reproman-cz.pdf');
            $email->addAttachment(__DIR__ . '/templates/Eshop/emails/faktura.pdf', $content);

            $mailer = new SmtpMailer([
                'host' => self::HOST,
                'username' => self::USERNAME,
                'password' => self::PASSWORD,
                'secure' => self::SECURE,
                'smtp' => self::SMTP,
            ]);
            try {
                $mailer->send($email);
            } catch (\Exception $e) {
                $this->flashMessage('Nepodařilo se odeslat email.');
                Debugger::log('Nepodařilo se odeslat email - ' . $e->getMessage() . ' - ' . $e->getLine());
            }

            // E-mail pro lékárnu
            if ($order->product === 'one') {
                $product = 'jeden měsíc';
            } elseif ($order->product === 'two') {
                $product = 'tři měsíce';
            } elseif ($order->product === 'three') {
                $product = 'šest měsíců';
            }

            // TODO - Smazat
/*            if ($this->serverName() === 'dev.reproman.cz' || $this->serverName() === 'localhost') {
                $adminLink = 'https://dev.reproman.cz/admin';
            } elseif ($this->serverName() === 'reproman.cz') {
                $adminLink = 'https://reproman.cz/admin';
            }*/

            $emailParams = [
                'from' => 'eshop@reproman.cz',
                'to' => [
                    $this->emailForReproman(),
                ],
                'subject' => 'www.reproman.cz - byla vytvořena nová objednávka.',
                'template' => __DIR__ . '/templates/Eshop/emails/email-order-notice.latte',
                'body' => 'Obsah zpravy',
                'params' => [
                    'objednavka' => $order,
                    'produkt' => $product,
                    'adminLink' => 'https://' . $this->serverName() . '/admin',
                ],
            ];
            try {
                $this->sendEmail($emailParams);
            } catch (\Exception $e) {
                $this->flashMessage('Nepodařilo se odeslat email.');
                Debugger::log('Nepodařilo se odeslat email - ' . $e->getMessage() . ' - ' . $e->getLine());
            }

            // Změna statusu.
//            $this->eshopManager->eshopStatus($order->orderId, 1);
            $session = $this->getSession();
            $session->destroy();
        } else {
            $showMessage = false;
        }
        $this->template->showMessage = $showMessage;
    }


    /**
     * Stránka se zobrazí pokud neprojde platba
     *
     * @return void
     */
    public function renderNopayment(): void
    {
    }


    /**
     * @return void
     */
    public function renderNonepage(): void
    {
    }

}