<?php

namespace App\Modules\Admin\Presenters;

use _PHPStan_76800bfb5\Nette\Utils\DateTime;
use Exception;
use Mpdf\Mpdf as mPdf;
use Mpdf\MpdfException;
use Nette;
use App\Model\EshopManager;
use App\Modules\Admin\Model\OrderManager;
use Nette\Application\UI\Form;
use stdClass;
use Tracy\Debugger;

class DashboardPresenter extends AdminPresenter
{
    /** @var OrderManager */
    public OrderManager $orderManager;

    /** @var EshopManager */
    public EshopManager $eshopManager;


    public function startup(): void
    {
        parent::startup();

        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect('Sign:in');
        }
    }


    /**
     * @param OrderManager $orderManager
     * @param EshopManager $eshopManager
     */
    public function __construct(OrderManager $orderManager, EshopManager $eshopManager)
    {
//        parent::__construct();
        $this->orderManager = $orderManager;
        $this->eshopManager = $eshopManager;
    }


    public function renderDefault(): void
    {
        $session = $this->getSession();
        $orderSession = $this->orderSessionId();

        $this->template->environment = $this->environment();

        $date = new DateTime();
        $ordersTest = $this->orderManager->ordersTest();
        foreach ($ordersTest as $itemTest) {
            $monthNumber = date_format($itemTest['created'], 'm');
            $year = date_format($itemTest['created'], 'Y');
            if ($monthNumber === '01') {
                $itemTest['mesic'] = 'Leden ' . $year;
            } elseif ($monthNumber === '02') {
                $itemTest['mesic'] = 'Ǔnor ' . $year;
            } elseif ($monthNumber === '03') {
                $itemTest['mesic'] = 'Březen ' . $year;
            } elseif ($monthNumber === '04') {
                $itemTest['mesic'] = 'Duben ' . $year;
            } elseif ($monthNumber === '05') {
                $itemTest['mesic'] = 'Květen ' . $year;
            } elseif ($monthNumber === '06') {
                $itemTest['mesic'] = 'Červen ' . $year;
            } elseif ($monthNumber === '07') {
                $itemTest['mesic'] = 'Červenec ' . $year;
            } elseif ($monthNumber === '08') {
                $itemTest['mesic'] = 'Srpen ' . $year;
            } elseif ($monthNumber === '09') {
                $itemTest['mesic'] = 'Září ' . $year;
            } elseif ($monthNumber === '10') {
                $itemTest['mesic'] = 'Říjen ' . $year;
            } elseif ($monthNumber === '11') {
                $itemTest['mesic'] = 'Listopad ' . $year;
            } elseif ($monthNumber === '12') {
                $itemTest['mesic'] = 'Prosinec ' . $year;
            } else {
                $itemTest['mesic'] = 'mesic ' . $year;
            }
            $objednavky[] = $itemTest;
        }
        if (isset($objednavky)) {
            $this->template->orders = $objednavky;
        }
        $this->template->productPrice = [
            'one' => self::PRICE_ONE,
            'three' => self::PRICE_THREE,
            'six' => self::PRICE_SIX,
        ];
//        $orders = $this->orderManager->orders();
        /*        foreach ($orders as $order => $item) {
                }
                $this->template->orders = $orders;*/
    }


    /**
     * @param int $orderId
     */
    public function actionDetail(int $orderId): void
    {
        $this->template->environment = $this->environment();
        $this->template->orderId = $orderId;
        $this->template->order = $this->orderManager->order($orderId);
        $this->template->productPrice = [
            'one' => self::PRICE_ONE,
            'three' => self::PRICE_THREE,
            'six' => self::PRICE_SIX,
        ];
    }


    /**
     * @param int $orderId
     */
    public function handleDelete(int $orderId): void
    {
        $this->orderManager->orderDelete($orderId);
    }


    public function createComponentStatusForm(): Form
    {
        $orderId = $this->getParameter('orderId');
        $orderStatus = $this->orderManager->orderStatus($orderId);
        $status = [
            0 => 'Nedokončená obje. (nezaplaceno)',
            1 => 'Objednáno a zaplaceno.',
            2 => 'Předáno dopravci.',
            3 => 'Doručeno.',
            4 => 'Storno',
        ];
        $form = new Form();
        $form->addSelect('status', 'Status', $status)
            ->setDefaultValue($orderStatus->status);
        $form->addSubmit('save', 'Uložit');
        $form->onSuccess[] = [$this, 'saveStatusForm'];
        return $form;
    }


    /**
     * @param Form $form
     * @param stdClass $values
     *
     * @throws Nette\Application\AbortException
     */
    public function saveStatusForm(Form $form, stdClass $values): void
    {
        (int)$orderId = $this->getParameter('orderId');
        $order = $this->orderManager->order($orderId);
        $this->orderManager->orderStatusUpdate($orderId, $values->status);

        // Email pro zákazníka. Oznámení o předání zásilky doručovací službě.
        $emailParams = [
            'from' => self::FROM,
            'to' => [$order->email],
            'subject' => 'www.reproman.cz - odeslání zásilky.',
            'template' => __DIR__ . '/templates/Dashboard/emails/customer-email.latte',
            'body' => 'Dobrý den, Vaše zásilka byla odeslána.',
            'params' => [
                'objednavka' => $order,
                'productPriceOne' => self::PRICE_ONE,
                'productPriceThree' => self::PRICE_THREE,
                'productPriceSix' => self::PRICE_SIX,
            ],
        ];

        // Odeslání emailu
        if ($values->status === 1) {
            try {
                $this->sendEmail($emailParams);
            } catch (Exception $e) {
                $this->flashMessage('Nepodařilo se odeslat email s upozorněním na odeslání zásilky.');
                Debugger::log('Nepodařilo se odeslat e-mail, ' . $e->getMessage() . ' na řádku ' . $e->getLine());
            }
            $this->flashMessage('Zákazníkovi byla odeslána zpráva o předání zboží dopravci.');
        }

        $this->redirect('this');
    }


    /**
     * @param int $orderId
     *
     * @return void
     * @throws MpdfException
     */
    public function renderInvoice(int $orderId): void
    {
        $server = $_SERVER['SERVER_NAME'];
        $order = $this->eshopManager->eshopOrderId($orderId);
        $this->template->variable = $order;
        $template = $this->templateFactory->createTemplate();
        $template->setFile('../app/Presenters/templates/Eshop/emails/customer-invoice.latte');
//        $template->setFile('/var/www/html/app/Presenters/templates/Eshop/emails/customer-invoice.latte');
//        $template->setFile(__DIR__ . '/templates/Dashboard/emails/customer-invoice.latte');
        $template->packetaPrice = $this->packetaPrice('cz', 'place');
        $template->item = $this->priceDecomposition($order['product']);
        $template->variable = $order;
//        $pdf = new mPdf(['tempDir' => '/var/www/reproman/temp/tmp']);
//        $pdf = new mPdf(['tempDir' => '/var/www/reproman/temp/mpdf/tmp']);
//        $pdf = new mPdf(['tempDir' => $this->getHttpRequest()->getUrl()->getBaseUrl() . 'temp/tmp']);
//        $pdf = new mPdf(['tempDir' => __DIR__ . '/templates/Dashboard/emails/tmp']);
        $pdf = new mPdf();
        $pdf->WriteHTML($template);
        $pdf->Output();
//        $this->template->productPriceOne = self::PRICE_ONE;
//        $this->template->productPriceThree = self::PRICE_THREE;
//        $this->template->productPriceSix = self::PRICE_SIX;
    }
}