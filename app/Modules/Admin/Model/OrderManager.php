<?php

declare(strict_types=1);

namespace App\Modules\Admin\Model;

use Nette;
use Nette\Database\Explorer;

class OrderManager
{
    use Nette\SmartObject;

    /** @var Explorer */
    public Explorer $database;

    public function __construct(Explorer $database)
    {
        $this->database = $database;
    }


    /**
     * @return array
     */
    public function orders(): array
    {
        return $this->database->table('orders')->fetchAll();
    }


    /**
     * @return Nette\Database\ResultSet
     */
    public function ordersTest(): Nette\Database\ResultSet
    {
        return $this->database->query('select * from orders');
//        return $this->database->query('select * from orders where status != 0');
    }


    /**
     * @param int $orderId
     *
     * @return Nette\Database\Row|null
     */
    public function order(int $orderId): ?Nette\Database\Row
    {
//        return $this->database->table('orders')->get($orderId);
        return $this->database->query('select * from orders where orderId = ?', $orderId)->fetch();
    }


    /**
     * @param int $orderId
     */
    public function orderDelete(int $orderId): void
    {
        $this->database->table('orders')
            ->where('orderId', $orderId)
            ->delete();
    }


    /**
     * @param int $orderId
     *
     * @return Nette\Database\Table\ActiveRow|null
     */
    public function orderStatus(int $orderId): ?Nette\Database\Table\ActiveRow
    {
        return $this->database->table('orders')->get($orderId);
    }



    /**
     * @param int $orderId
     * @param int $status
     */
    public function orderStatusUpdate(int $orderId, int $status): void
    {
        $this->database->table('orders')
            ->where('orderId', $orderId)
            ->update([
                'status' => $status,
            ]);
    }


}