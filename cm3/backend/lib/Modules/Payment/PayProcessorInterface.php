<?php

namespace CM3_Lib\Modules\Payment;

/**
 * Things every payment processor should be able to do
 */
interface PayProcessorInterface
{
    public function Init(array $config);
    public function ProcessorOk(): bool;
    //Load order state
    public function LoadOrder(string $data);
    //Save order state
    public function SaveOrder(string &$data);
    //Set internal ID if not provided by the processor
    public function SetOrderID(string $id);
    //Set customer-facing ID (i.e. on the receipt)
    public function SetCustomerFacingID(string $id);
    //Order description
    public function SetOrderDescription(string $desc);
    //Provide return URL for post-payment
    public function SetReturnURLs(string $payComplete, string $payCancel);
    //Details about the current order state
    public function GetDetails();
    //Clear the items list and order totals
    public function ResetItems(): bool;
    //Add an item to the order
    public function AddItem(string $name, float $amount, int $count = 1, ?string $description = null, ?string $sku = null, ?float $discount = null, ?string $discountReason = null);
    //Compile the order and make sure it's ready to be processed
    public function ConfirmOrder(): bool;
    //Close order if it's in-flight to prevent possible double-charging
    public function CancelOrder(): bool;
    //Get the total as it would be sent to the processor
    public function GetTotal(): float;
    //Get the tax portion of the total
    public function GetTax(): float;
    //If processor needs the user to visit someplace, what is the URL?
    public function RetrievePaymentRedirectURL(): string;
    //The user has come back with order completion information, let's do it!
    public function CompleteOrder($data): bool;
    //payment_status translation
    public function GetOrderStatus(): string;
    /**
     * Calculates the tax and subtotal components of a given total amount.
     * This allows the upstream PaymentBuilder to accurately decrement both tax and transaction amounts during a refund.
     * 
     * @param float $totalAmount The amount being refunded (the total)
     * @return array ['subtotal' => float, 'tax' => float]
     */
    public function SplitTotal(float $totalAmount): array;
    /**
     * Refund a specific amount from a transaction
     * @param float $amount
     * @param string|null $reason
     * @return bool
     * @throws \Exception with specific reason if refund fails
     */
    public function Refund(float $amount, ?string $reason = null): bool;

    /**
     * Returns how much money is currently available to be refunded for this transaction
     * @return float
     */
    public function GetRefundableAmount(string &$denyReason): float;

    /**
     * Returns a list of previous refund actions associated with this order
     * @return array List of arrays containing 'id', 'amount', 'status', 'date'
     */
    public function GetRefundHistory(): array;

}
