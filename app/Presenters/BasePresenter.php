<?php

declare(strict_types=1);

namespace App\Presenters;

use Latte\Engine;
use mysql_xdevapi\Exception;
use Nette\Application\UI\Presenter;
use Nette\Application\UI\ITemplateFactory;
use Nette\Application\UI\TemplateFactory;
use Nette\Mail\Message;
use Nette\Mail\SmtpMailer;
use Nette\Utils\Validators;
use stdClass;
use Tracy\Debugger;
use Mpdf\MpdfException;
use Mpdf\Mpdf as mPdf;
use App\Model\EshopManager;

class BasePresenter extends Presenter
{
    /** @var EshopManager */
    public EshopManager $eshopManager;

    /** @var ITemplateFactory @inject */
    public ITemplateFactory $templateFactory;

    // Packeta
    public const PACKETA_API_KEY = 'd6109a389741c45c';
    public const PACKETA_API_PASS = 'd6109a389741c45c202d9a6ab7e64af5';
    public const PACKETA_API_KEY_TEST = '265786c6eb895e98';
    public const PACKETA_API_PASS_TEST = '265786c6eb895e986cfcb4937577d485';
    public const PACKETA_PLACE_CZ = 70;
    public const PACKETA_ADDRESS_CZ = 110;
    public const PACKETA_PLACE_SK = 95;
    public const PACKETA_ADDRESS_SK = 120;
    public const PACKETA_VAT = 21;

    // Product
    public const PRICE_ONE = 399; // Cena za jeden produkt
    public const PRICE_THREE = 1197; // Cena za tři produkty
    public const PRICE_SIX = 1995; // Cena za šest produktů
    public const VAT = 15; // DPH

    // Odchozí email
    // reproman.cz
    public const HOST = 'smtp.websupport.sk';
    public const USERNAME = 'eshop@reproman.cz';
    public const PASSWORD = 'Zd04f^jy\w';
    public const SECURE = 'ssl';
    public const SMTP = true;
    public const FROM = 'eshop@reproman.cz';

    public const GMAIL_TEST_EMAIL = 'jiri.wolf@gmail.com';
    public const REPROMAN_ZM_TECH_EMAIL = 'reproman@zm-tech.cz';


    /**
     * @param EshopManager $eshopManager
     */
    public function __construct(EshopManager $eshopManager)
    {
        parent::__construct();
        $this->eshopManager = $eshopManager;
    }


    /**
     * @return void
     */
    public function startup()
    {
        $this->template->environment = $this->environment();
        if ($this->environment() === 'dev') {
            $this->template->packetaApiKey = self::PACKETA_API_KEY_TEST;
        } elseif ($this->environment() === 'prod') {
            $this->template->packetaApiKey = self::PACKETA_API_KEY;
        }
bdump($this->template->packetaApiKey, 'api key');
        $presenter = $this->presenter;
        // Hlavní strana
        if (($presenter->name === 'Homepage' && $presenter->action === 'home') ||
            ($presenter->name === 'Composition' && $presenter->action === 'components') ||
            ($presenter->name === 'Infertility' && $presenter->action === 'infertility') ||
            ($presenter->name === 'Research' && $presenter->action === 'research') ||
            ($presenter->name === 'Advise' && $presenter->action === 'advise') ||
            ($presenter->name === 'Contact' && $presenter->action === 'contact')) {
            $lang = 'en';
        } else {
            $lang = 'cz';
        }
        $this->template->lang = $lang;
        parent::startup();
    }



    /**
     * @return mixed
     */
    public function serverName()
    {
        return $_SERVER['SERVER_NAME'];
    }


    /**
     * @return string
     */
    public function environment(): string
    {
        $enviromnent = '';
        if ($this->serverName() === 'localhost' || $this->serverName() === 'dev.reproman.cz') {
            $enviromnent = 'dev';
        } elseif ($this->serverName() === 'reproman.cz') {
            $enviromnent = 'prod';
        }
        bdump($enviromnent, 'environment');
        return $enviromnent;
    }


    /**
     * @return string
     */
    public function emailForReproman(): string
    {
        $emailForReproman = '';
        if ($this->environment() === 'prod') {
            $emailForReproman = self::REPROMAN_ZM_TECH_EMAIL;
        } elseif ($this->environment() === 'dev') {
            $emailForReproman = self::GMAIL_TEST_EMAIL;
        }
        return $emailForReproman;
    }


    /**
     * @return string
     */
    public function orderSessionId(): string
    {
        $session = $this->getSession();
        $session->start();
        return $session->getId();
    }


    /**
     * Odesílání emailů.
     *
     * @param array $emailParams
     *
     * $emailParams = [
     *  'from' => 'eshop@reproman.cz',
     *  'to' => ['jiri.wolf@jw.cz', 'jiri.wolf@gmail.com'],
     *  'subject' => 'Predmet zpravy 8',
     *  'template' => __DIR__ . '/templates/Eshop/emails/email-order-confirm.latte',
     *  'body' => 'Obsah zpravy',
     *  'params' => [
     *  'objednavka' => 'nejaka objednavka',
     *  ],
     * ];
     *
     * @throws \Exception
     */
    public function sendEmail(array $emailParams): void
    {
        $latte = new Engine();
        $email = new Message();
        $email->setFrom($emailParams['from']);
        $email->setSubject($emailParams['subject']);
        if (is_array($emailParams['to'])) {
            foreach ($emailParams['to'] as $e) {
                if (Validators::isEmail($e)) {
                    $email->addTo($e);
                }
            }
        } else {
            $email->addTo($emailParams['to']);
        }
        $email->addBcc('jiri.wolf@gmail.com'); // Skrytá kopie
        if (file_exists($emailParams['template'])) {
            $email->setHtmlBody($latte->renderToString($emailParams['template'], $emailParams['params']), 'images/');
        }

        $mailer = new SmtpMailer([
            'host' => self::HOST,
            'username' => self::USERNAME,
            'password' => self::PASSWORD,
            'secure' => self::SECURE,
            'smtp' => self::SMTP,
        ]);

        $mailer->send($email);
    }


    /**
     * @param array $to
     * @param string $templateFile
     * @param array $templateParameters
     * @param array $attachments
     * @param string|null $from
     * @param bool $send
     *
     * @return Nette\Mail\Message
     */
    public function emailTemplate(array $to, string $templateFile, array $templateParameters = [], array $attachments = [], string $from = null, bool $send = true): Nette\Mail\Message
    {
        if (!is_array($to)) {
            $to = [$to];
        }
        if ($from === null) {
            $from = $this->context->parameters['mailFrom'];
        }

        if (file_exists($templateFile)) {
            $template = $this->createTemplate();
            $template->setFile($templateFile);
            $template->setParameters($templateParameters);
        } else {
            $template = $templateFile; // odeslat text
        }

        $mess = new Nette\Mail\Message;
        $mess->setFrom($from)
            ->setHtmlBody($template, $this->context->parameters['wwwDir']);

        foreach ($to as $e) {
            if (Nette\Utils\Validators::isEmail($e)) {
                $mess->addTo($e);
            }
        }

        foreach ($attachments as $name => $data) {
            $mess->addAttachment($name, $data);
        }

        //$mailer = new Nette\Mail\FallbackMailer([
        //	$this->context->getService('mail.mailer')
        //]);
        // $mailer = $this->context->getService('mail.mailer');

//        /** @var Aws\Ses\SesClient $mailer */
//        $mailer = $this->context->getService('aws')->createClient('email');

        if ($send) {
            //try {
            //	$mailer->send($mess);
            //} catch (Nette\Mail\SmtpException $e) {
            //	\Tracy\Debugger::log($e);
            //	return null;
            //}

            // ulozeni na s3
            $path = $this->context->parameters['s3Bucket']
                . '_log/email/'
                . (new \DateTime())->format('Y/m/d/His-v-')
                . Nette\Utils\Strings::webalize(implode('#', $to))
                . '.eml';
            file_put_contents($path, $mess->generateMessage());

            // odeslani
            $mailer->sendRawEmail([
                'RawMessage' => ['Data' => $mess->generateMessage()]
            ]);
        }

        return $mess;
    }


    public function invoice($sessionId)
    {
        $order = $this->eshopManager->eshopOrder($sessionId);
        return $order;
    }


    /**
     * @param string $product
     *
     * @return array
     */
    public function priceDecomposition(string $product): array
    {
        // Počet kusů
        $numberOfPieces = 0;
        if ($product === 'one') {
            $numberOfPieces = 1;
            // DPH za celé balení.
            $vatForAllPack = ((self::PRICE_ONE / (self::VAT + 100)) * self::VAT);
            // Cena za celé balení bez DPH.
            $priceForAllPackWithoutVat = self::PRICE_ONE - $vatForAllPack;
            // Cena za jedno balení bez DPH.
            $priceForOnePieceWithoutVat = $priceForAllPackWithoutVat / $numberOfPieces;
            // Cena celkem
            $priceFinal = self::PRICE_ONE;
        } elseif ($product === 'two') {
            $numberOfPieces = 3;
            // DPH za celé balení.
            $vatForAllPack = ((self::PRICE_THREE / (self::VAT + 100)) * self::VAT);
            // Cena za celé balení bez DPH.
            $priceForAllPackWithoutVat = self::PRICE_THREE - $vatForAllPack;
            // Cena za jedno balení bez DPH.
            $priceForOnePieceWithoutVat = $priceForAllPackWithoutVat / $numberOfPieces;
            // Cena celkem
            $priceFinal = self::PRICE_THREE;
        } elseif ($product === 'three') {
            $numberOfPieces = 6;
            // DPH za celé balení.
            $vatForAllPack = ((self::PRICE_SIX / (self::VAT + 100)) * self::VAT);
            // Cena za celé balení bez DPH.
            $priceForAllPackWithoutVat = self::PRICE_SIX - $vatForAllPack;
            // Cena za jedno balení bez DPH.
            $priceForOnePieceWithoutVat = $priceForAllPackWithoutVat / $numberOfPieces;
            // Cena celkem
            $priceFinal = self::PRICE_SIX;
        }
        return [
            // DPH
            'vatPrice' => self::VAT,
            // Počet kusů
            'numberOfPieces' => $numberOfPieces,
            // DPH pro celou částku.
            'vatForAllPack' => $vatForAllPack,
            // Cena bez DPH.
            'priceForAllPackWithoutVat' => $priceForAllPackWithoutVat,
            // Cena za jeden kus bez DPH.
            'priceForOnePieceWithoutVat' => $priceForOnePieceWithoutVat,
            // Celková částka
            'priceFinal' => $priceFinal,
        ];
    }


    /**
     * @param string $lang
     * @param string $where
     *
     * @return array
     */
    public function packetaPrice(string $lang, string $where): array
    {
        $packetaPriceFinalWithoutVat = 0;
        if ($lang === 'cz') {
            if ($where === 'address') {
                $packetaPriceFinal = self::PACKETA_ADDRESS_CZ;
            } elseif ($where === 'place') {
                $packetaPriceFinal = self::PACKETA_PLACE_CZ;
            }
        } elseif ($lang === 'sk') {
            if ($where === 'address') {
                $packetaPriceFinal = self::PACKETA_ADDRESS_SK;
            } elseif ($where === 'place') {
                $packetaPriceFinal = self::PACKETA_PLACE_SK;
            }
        }

        // DPH z celkové ceny za dopravu.
        $packetPriceVat = (($packetaPriceFinal / (self::PACKETA_VAT + 100)) * self::PACKETA_VAT);
        // Celková cena za dopravu bez DPH.
        $packetaPriceFinalWithoutVat = $packetaPriceFinal - $packetPriceVat;

        return [
            'packetaVat' => self::PACKETA_VAT,
            'packetaPriceVat' => $packetPriceVat,
            'packetaPriceFinalWithoutVat' => $packetaPriceFinalWithoutVat,
            'packetaPriceFinal' => $packetaPriceFinal,
        ];
    }


    /**
     * @param stdClass $values
     *
     * @return int
     *
     * Vrací id služby pro doručení na adresu podle zadané země,
     * tzn. cenu v Česku a cenu na Slovensku.
     */
    public function priceDeliveryToAddress(stdClass $values): int
    {
        $deliveryId = 0;
        if ($values->delivery === 'address') {
            if ($values->state === 'cz') {
                $deliveryId = 106;
            } elseif ($values->state === 'sk') {
                $deliveryId = 131;
            }
        }
        return $deliveryId;
    }


    /**
     * @param string $product
     *
     * @return int
     */
    public function productPrice(string $product): int
    {
        $productPrice = '';
        if ($product === 'one') {
            $productPrice = self::PRICE_ONE;
        } elseif ($product === 'two') {
            $productPrice = self::PRICE_THREE;
        } elseif ($product === 'three') {
            $productPrice = self::PRICE_SIX;
        }
        return $productPrice;
    }
}