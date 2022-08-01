<?php

declare(strict_types=1);

namespace App\Model;

use Nette;
use Nette\Database\Table\ActiveRow;
use stdClass;

class EshopManager
{
    use Nette\SmartObject;

    /** @var Nette\Database\Explorer */
    public Nette\Database\Explorer $database;


    public function __construct(Nette\Database\Explorer $database)
    {
        $this->database = $database;
    }


    /**
     * @param stdClass $values
     * @param string $sessionId
     * @param int $deliveryId
     */
    public function eshopInsert(stdClass $values, string $sessionId, int $deliveryId): void
    {
        $this->database->table('orders')
            ->insert(
                [
                    'sessionId' => $sessionId,
                    'product' => $values->product,
                    'note' => $values->note,
                    'name' => $values->name,
                    'surname' => $values->surname,
                    'phone' => $values->phone,
                    'email' => $values->email,
                    'delivery' => $values->delivery,
                    'deliveryId' => $deliveryId,
                    'state' => $values->state,
                    'contactStreet' => $values->contactStreet,
                    'houseNumber' => $values->houseNumber,
                    'contactCity' => $values->contactCity,
                    'contactPsc' => $values->contactPsc,
                    'billingAddress' => $values->billingAddress,
                    'billingStreet' => $values->billingStreet,
                    'billingCity' => $values->billingCity,
                    'billingPsc' => $values->billingPsc,
                    'agreement' => $values->agreement,
                    'packetaId' => $values->packetaId,
                    'packetaAttribute' => $values->packetaAttribute,
                    'packetaPlace' => $values->packetaPlace,
                ]
            );
    }


    /**
     * @param string $sessionId
     *
     * @return ActiveRow|null
     */
    public function eshopOrder(string $sessionId): ?ActiveRow
    {
        return $this->database->table('orders')
            ->where('sessionId', $sessionId)
            ->fetch();
    }


    /**
     * @param string $sessionId
     *
     * @return ActiveRow|null
     */
    public function eshopOrderPaidResult(string $sessionId): ?ActiveRow
    {
        return $this->database->table('orders')
            ->where('sessionId', $sessionId)
            ->where('status', 1)
            ->fetch();
    }


    /**
     * @param string $sessionId
     *
     * @return void
     */
    public function eshopOrderPaid(string $sessionId): void
    {
        $this->database->table('orders')
            ->where('sessionId', $sessionId)
            ->update([
                'status' => 1
            ]);
    }


    /**
     * @param string $orderId
     *
     * @return ActiveRow|null
     */
    public function eshopOrderId(string $orderId): ?ActiveRow
    {
        return $this->database->table('orders')
            ->where('orderId', $orderId)
            ->fetch();
    }


    /**
     * @param stdClass $values
     * @param string $sessionId
     * @param int $deliveryId
     */
    public function eshopUpdate(stdClass $values, string $sessionId, int $deliveryId): void
    {
        $this->database->table('orders')
            ->where('sessionId', $sessionId)
            ->update([
                'product' => $values->product,
                'note' => $values->note,
                'name' => $values->name,
                'surname' => $values->surname,
                'phone' => $values->phone,
                'email' => $values->email,
                'delivery' => $values->delivery,
                'deliveryId' => $deliveryId,
                'state' => $values->state,
                'contactStreet' => $values->contactStreet,
                'houseNumber' => $values->houseNumber,
                'contactCity' => $values->contactCity,
                'contactPsc' => $values->contactPsc,
                'billingAddress' => $values->billingAddress,
                'billingStreet' => $values->billingStreet,
                'billingCity' => $values->billingCity,
                'billingPsc' => $values->billingPsc,
                'agreement' => $values->agreement,
                'packetaId' => $values->packetaId,
                'packetaAttribute' => $values->packetaAttribute,
                'packetaPlace' => $values->packetaPlace,
            ]);
    }


    /**
     * @param int $orderId
     * @param int $status
     */
    public function eshopStatus(int $orderId, int $status): void
    {
        $this->database->table('orders')
            ->where('orderId', $orderId)
            ->update([
                'status' => $status,
            ]);
    }


    public function orderOrderIdGp(int $orderId, int $orderIdGp)
    {
        $this->database->table('orders')
            ->where('orderId', $orderId)
            ->update([
                'orderIdGp' => $orderIdGp,
            ]);
    }
}