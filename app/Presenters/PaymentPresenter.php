<?php

namespace App\Presenters;

use Latte\Engine;
use Nette;
use Pixidos\GPWebPay\Components\GPWebPayControl;
use Pixidos\GPWebPay\Components\GPWebPayControlFactory;
use Pixidos\GPWebPay\Data\Operation;
use Pixidos\GPWebPay\Data\RequestInterface;
use Pixidos\GPWebPay\Data\ResponseInterface;
use Pixidos\GPWebPay\Enum\Currency as CurrencyEnum;
use Pixidos\GPWebPay\Exceptions\GPWebPayException;
use Pixidos\GPWebPay\Factory\ResponseFactory;
use Pixidos\GPWebPay\Param\Amount;
use Pixidos\GPWebPay\Param\Currency;
use Pixidos\GPWebPay\Param\OrderNumber;
use Pixidos\GPWebPay\Param\ResponseUrl;
use Pixidos\GPWebPay\ResponseProviderInterface;
use stdClass;
use Tracy\Debugger;
use App\Model\EshopManager;

final class PaymentPresenter extends BasePresenter
{
    public int $orderId;
    public int $amount;
    public CurrencyEnum $currency;

    /** @var GPWebPayControlFactory */
    public GPWebPayControlFactory $webPayControlFactory;

    /** @var ResponseProviderInterface */
    private ResponseProviderInterface $responseProvider;

    /** @var ResponseFactory */
    private ResponseFactory $responseFactory;

    /** @var EshopManager */
    public EshopManager $eshopManager;

    public function __construct(GPWebPayControlFactory $webPayControlFactory,
                                ResponseProviderInterface $responseProvider,
                                ResponseFactory $responseFactory,
                                EshopManager $eshopManager)
    {
//        parent::__construct(EshopManager $eshopManager);
        $this->webPayControlFactory = $webPayControlFactory;
        $this->responseProvider = $responseProvider;
        $this->responseFactory = $responseFactory;
        $this->eshopManager = $eshopManager;
    }


    /**
     * @param object $summary
     *
     * @return int
     */
    public function deliveryPrice(object $summary): int
    {
        if ($summary['product'] === 'one') {
            if ($summary['delivery'] === 'address') {
                if ($summary['state'] === 'cz') {
                    // Cena za doručení na adresu v Cz.
                    $deliveryPrice = self::PACKETA_ADDRESS_CZ;
                } else {
                    // Cena za doručení na adresu v Sk.
                    $deliveryPrice = self::PACKETA_ADDRESS_SK;
                }
            } else {
                if ($summary['state'] === 'sk') {
                    $deliveryPrice = self::PACKETA_PLACE_SK;
                } else {
                    $deliveryPrice = self::PACKETA_PLACE_CZ;
//                    $this->template->deliveryPrice = self::PACKETA_PLACE_SK;
                }
            }
        } else {
            $deliveryPrice = 0;
        }
        return $deliveryPrice;
    }


    /**
     * @return void
     * @throws Nette\Application\AbortException
     */
    public function renderDefault(): void
    {
        $summary = $this->eshopManager->eshopOrder($this->orderSessionId());
        if ($summary !== null) {
            $this->template->summary = $summary;
        } else {
            $this->redirect('Eshop:nonepage');
        }
        $items = $this->priceDecomposition($summary['product']);
        $packeta = $this->packetaPrice('cz', 'address');
        $this->template->productPrice = [
            'one' => self::PRICE_ONE,
            'three' => self::PRICE_THREE,
            'six' => self::PRICE_SIX,
        ];

        $this->template->deliveryPrice = $this->deliveryPrice($summary);
    }


    /**
     * @return GPWebPayControl
     * @throws Nette\Application\UI\InvalidLinkException
     */
    protected function createComponentWebPayButton(): GPWebPayControl
    {
        $summary = $this->eshopManager->eshopOrder($this->orderSessionId());
        if ($summary->product === 'one') {
            $this->amount = self::PRICE_ONE + $this->deliveryPrice($summary);
        } elseif ($summary->product === 'two') {
            $this->amount = self::PRICE_THREE;
        } elseif ($summary->product === 'three') {
            $this->amount = self::PRICE_SIX;
        }

        // Nastavení měny
        if ($summary->state === 'cz') {
            $this->currency = CurrencyEnum::CZK();
        } elseif ($summary->state === 'sk') {
            // TODO - Pokud budou používat pro platby ze slovanska eura tak se to tady musí nastavit
            $this->currency = CurrencyEnum::CZK();
//            $currency = CurrencyEnum::EUR();
        }

        $rand = random_int(10, 100000);
        $this->orderId = $summary->orderId . 0 . $rand;
        $this->eshopManager->orderOrderIdGp($summary->orderId, $this->orderId);

        $operation = new Operation(
            new OrderNumber($this->orderId),
            new Amount($this->amount),
            new Currency($this->currency),
            'czk', // leave empty or null for default key
            new ResponseUrl($this->link('//Payment:processResponse')) // you can setup by config responseUrl:
        );

        $control = $this->webPayControlFactory->create($operation);

        # Run before redirect to webpay gateway
        $control->onCheckout[] = static function (GPWebPayControl $control, RequestInterface $request) {
            Debugger::barDump($request);
        };

        return $control;
    }

    /**
     * @return void
     * @throws Nette\Application\AbortException
     */
    public function actionProcessResponse(): void
    {
        $response = $this->responseFactory->create($this->getParameters());
        $this->responseProvider->addOnSuccess(
            static function (ResponseInterface $response) {
                //.. process success response
                Debugger::log('Prošlo to: ' . $response);
            }
        );

        $this->responseProvider->addOnError(
            static function (GPWebPayException $exception, ResponseInterface $response) {
                //.. process error response
                Debugger::log('Nepodarilo se to protoze: ' . $response->getResultText() . ', číslo objednávky: ' . $response->getOrderNumber() . ', PRCODE: ' . $response->getPrcode() . ', SRCODE: ' . $response->getSrcode() . ' error: ' . $response->hasError());
            }
        );

        $this->responseProvider->provide($response);

        // TODO - Smazat cookie pokud se už nemaže.
        // TODO - Nahradit emaily.
        if ($response->getResultText() === 'OK') {
            // Přesměrování na stránku s oznámením o úspěšné platbě.
            $this->redirect('Eshop:confirm');
        } else {
            $this->redirect('Eshop:nopayment');
        }

    }
}