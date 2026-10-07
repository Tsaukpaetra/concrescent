<?php

namespace CM3_Lib\Modules\Payment\cash;

use CM3_Lib\util\CurrentUserInfo;

class PayProcessor implements \CM3_Lib\Modules\Payment\PayProcessorInterface
{
    private array $config = array();
    public function __construct(
        //private CurrentUserInfo $CurrentUserInfo,
    ) {
    }
    private $orderData = array();
    private bool $isDisabled = false;

    public function Init(array $config)
    {
        $this->config = $config;
        $this->resetOrderData();
    }

    private function resetOrderData()
    {
        $this->orderData = array(
            'discount'  => 0.0,
            'subtotal'=>0.0,
            'tax'=>0.0,
            'total'     => 0.0,
            'items'     => array(),
            'stage' => 'init',
            'handler_id' => 0,
            'refund_history' => array()
        );
    }
    public function ProcessorOk(): bool
    {
        return !$this->isDisabled;
    }
    public function LoadOrder(string $data)
    {
        $this->orderData = json_decode($data, true);
    }
    public function SaveOrder(string &$data)
    {
        $data = json_encode($this->orderData);
    }
    public function SetOrderID(string $id)
    {
    }
    public function SetCustomerFacingID(string $id)
    {
    }
    public function SetOrderDescription(string $desc)
    {
    }
    public function SetReturnURLs(string $payComplete, string $payCancel)
    {
        //Not really applicable
    }
    public function GetDetails()
    {
        return $this->orderData;
    }

    public function ResetItems(): bool
    {
        //Have we already taken their money?
        if ($this->orderData['stage'] == 'COMPLETED') {
            return false;
        }
        $this->orderData['items'] = [];
        $this->orderData['subtotal']=0.0;
        $this->orderData['tax']=0.0;
        $this->orderData['total']=0.0;
        $this->orderData['discount']=0.0;
        return true;
    }
    public function AddItem(string $name, float $amount, int $count = 1, ?string $description = null, ?string $sku = null, ?float $discount = null, ?string $discountReason = null)
    {
        $this->orderData['items'][] = array(
            'name' => $name,
            'amount' => $amount,
            'count' => $count,
            'description'=>$description,
            'discount'=>$discount,
            'discountReason'=>$discountReason,
        );
        $this->orderData['subtotal'] += ($amount - $discount) * $count;
        $this->orderData['discount'] += $discount * $count;
        $this->UpdateTotal();
    }
    private function UpdateTotal(){
        //Update the tax and total
        $this->orderData['tax'] = $this->orderData['subtotal'] * $this->config['SalesTax'];
        $this->orderData['total'] = $this->orderData['subtotal'] + $this->orderData['tax'];

    }
    public function ConfirmOrder(): bool
    {
        $this->orderData['stage'] = 'APPROVED';
        return true;
    }
    public function CancelOrder(): bool
    {
        $this->orderData['stage'] = 'CANCELLED';
        return true;
    }
    public function GetTotal(): float
    {
        $this->UpdateTotal();
        return $this->orderData['total'];
    }
    public function GetTax(): float
    {
        $this->UpdateTotal();
        return $this->orderData['tax'];
    }
    public function RetrievePaymentRedirectURL(): string
    {
        return '';
    }
    public function CompleteOrder($data): bool
    {
        //We assume that physical cash has been handled.
        //TODO: Fix dependency injection?
        $this->orderData['handler_id'] = $data['handler_id'];//$this->CurrentUserInfo->GetContactId();
        $this->orderData['stage'] = 'COMPLETED';
        return true;
    }
    public function GetOrderStatus(): string
    {
        $status = $this->orderData['stage'] ?? 'UNKNOWN';
        switch ($status) {
            case 'CREATED': return 'Incomplete';
            case 'COMPLETED': return 'Completed';
            case 'APPROVED': return 'Incomplete'; //Still need to confirm with PayPal

        }
        return 'NotStarted';
    }
    /**
     * Calculates the tax and subtotal components of a given total amount.
     * This allows the upstream PaymentBuilder to accurately decrement both tax and transaction amounts during a refund.
     * 
     * @param float $totalAmount The amount being refunded (the total)
     * @return array ['subtotal' => float, 'tax' => float]
     */
    public function SplitTotal(float $totalAmount): array
    {
        $subtotal = 0.0;
        $tax = 0.0;

        if ($this->orderData['prep']['total'] > 0) {
            // Calculate the ratio of tax within the total
            // Ratio = tax / (subtotal + tax)
            $taxRatio = $this->orderData['prep']['tax'] / $this->orderData['prep']['total'];

            $tax = $totalAmount * $taxRatio;
            $subtotal = $totalAmount - $tax;
        }

        return [
            'subtotal' => $subtotal,
            'tax' => $tax
        ];
    }
    /**
     * Implementation of Refund for Cash.
     * Since this is cash, we simply record the manual refund in the history.
     */
    public function Refund(float $amount, ?string $reason = null): bool
    {
        $refundable = $this->GetRefundableAmount();
        if ($amount > $refundable) {
            throw new \Exception("Refund amount " . $amount . " exceeds available refundable amount " . $refundable);
        }

        // For cash, we don't call an API, we just log that a refund was performed.
        $this->orderData['refund_history'][] = array(
            'id' => uniqid('cash_ref_'),
            'amount' => $amount,
            'status' => 'COMPLETED',
            'date' => date('c'),
            'reason' => $reason ?? 'Manual cash refund'
        );

        return true;
    }

    /**
     * Returns how much money is currently available to be refunded for this transaction
     */
    public function GetRefundableAmount(string &$denyReason): float
    {
        if ($this->orderData['stage'] !== 'COMPLETED') {
            $denyReason = "Transaction is not complete and cannot be refunded.";
            return 0;
        }

        $totalOriginal = $this->orderData['total'];

        $totalRefunded = 0.0;
        if (isset($this->orderData['refund_history'])) {
            foreach ($this->orderData['refund_history'] as $history) {
                $totalRefunded += $history['amount'];
            }
        }

        $remaining = $totalOriginal - $totalRefunded;

        if ($remaining <= 0) {
            $denyReason = "Already refunded the max amount.";
        }

        return max(0.0, $remaining);
    }

     /**
     * Returns a list of previous refund actions associated with this order
     */
    public function GetRefundHistory(): array
    {
        return $this->orderData['refund_history'] ?? array();
    }
}

