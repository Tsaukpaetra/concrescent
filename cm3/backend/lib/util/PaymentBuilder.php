<?php

namespace CM3_Lib\util;

use Respect\Validation\Validator as v;

use CM3_Lib\models\banlist;
use CM3_Lib\models\payment;

use CM3_Lib\util\badgevalidator;
use CM3_Lib\util\badgepromoapplicator;

use CM3_Lib\database\TableValidator;
use CM3_Lib\database\View;
use CM3_Lib\database\Join;
use CM3_Lib\database\SearchTerm;
use CM3_Lib\database\SelectColumn;

use CM3_Lib\Factory\PaymentModuleFactory;
use CM3_Lib\Modules\Payment\PayProcessorInterface;
use CM3_Lib\Modules\Notification\Mail;

final class PaymentBuilder
{
    private array $cart = array();
    private array $cart_items = array();
    private string $cart_uuid = "";
    private float $cart_payment_txn_amt = 0;//Our own sanity check against the cart
    private bool $AllowPay = true;
    private bool $CanPay = true;
    private ?PayProcessorInterface $pp = null;
    private array $stagedItems = array();
    public function __construct(
        private badgeinfo $badgeinfo,
        private CurrentUserInfo $CurrentUserInfo,
        private PaymentModuleFactory $PaymentModuleFactory,
        private banlist $banlist,
        private payment $payment,
        private badgevalidator $badgevalidator,
        private badgepromoapplicator $badgepromoapplicator,
        private FrontendUrlTranslator $FrontendUrlTranslator,
        private Mail $Mail
    ) {
    }

    public function createCart($contact_id = null, $requested_by = '[self]')
    {
        $template = array(
            'event_id' => $this->CurrentUserInfo->GetEventId(),
            'contact_id' => $contact_id ?? $this->CurrentUserInfo->GetContactId(),
            'requested_by' => $requested_by,
            'items' => '[]',
            'payment_status' => 'NotReady',
            'payment_system' => 'Cash',
            'payment_txn_amt' => -1,
        );
        $createdcart = $this->payment->Create($template);
        $this->cart = array_merge($template, $createdcart, $this->payment->GetByID($createdcart['id'], array(
            'uuid','payment_details',
            'date_created' ,'date_modified' ,
        )));
        $this->cart_items = array();
        $cart_payment_txn_amt = 0;
        $this->AllowPay = true;
        $this->CanPay = true;
        $this->pp = null;
        $this->stagedItems = array();
    }

    public function loadCartFromBadge($context_code, $id)
    {
        $badge = $this->badgeinfo->GetSpecificBadge($id, $context_code, true);
        if ($badge===false) {
            return false;
        }
        //Fetch the associated payment
        return $this->loadCart($badge['payment_id']);
    }

    public function loadCart(int $cart_id, string $cart_uuid = null, $expectedEventId = null, $expectedContactId = null)
    {
        $cart = $this->payment->GetByIDorUUID($cart_id, $cart_uuid, array(
            'id', 'uuid', 'event_id','contact_id',
            'payment_status','payment_system','payment_txn_amt',
            'items','payment_details','requested_by' ,
            'date_created' ,'date_modified' ,
        ));

        if ($cart === false) {
            $this->cart = array();
            return false;
        }

        //Check that the cart is in the right event, and right contact
        if (
            (!is_null($expectedEventId) && $cart['event_id'] != $expectedEventId)
            ||(!is_null($expectedContactId) && $cart['contact_id'] != $expectedContactId)
        ) {
            $this->cart = array();
            return false;
        }

        $this->cart = $cart;
        //Extract and remove the UUID, since we never want to try saving it back
        $this->cart_uuid = $cart['uuid'];
        unset($this->cart['uuid']);
        $this->cart_items = json_decode($cart['items'], true) ?? array();

        //Load cart meta
        $this->refreshCartMeta();
        return true;
    }

    public function saveCart()
    {
        $this->cart['items'] = json_encode($this->cart_items);
        if (isset($this->pp)) {
            $this->cart['payment_details'] = '';
            $this->pp->SaveOrder($this->cart['payment_details']);
        }
        unset($this->cart['uuid']);

        //Save the current status
        $this->payment->Update($this->cart);
    }

    public function canEdit()
    {
        return
            $this->cart['payment_status'] == 'NotReady'
            ||$this->cart['payment_status'] == 'NotStarted'
            ||$this->cart['payment_status'] == 'Cancelled';
    }

    public function canCheckout()
    {
        //Check if we can alter this payment
        if (!(
            $this->cart['payment_status'] == 'NotStarted'
            ||$this->cart['payment_status'] == 'Incomplete'
            ||$this->cart['payment_status'] == 'Cancelled'
        )
        ) {
            return false;
        }
        return true;
    }
    public function getCanPay()
    {
        return $this->CanPay;
    }
    public function getCartId()
    {
        return $this->cart['id'] ?? null;
    }
    public function getCartEventId()
    {
        return $this->cart['event_id'] ?? null;
    }
    public function getCartContactId()
    {
        return $this->cart['contact_id'] ?? null;
    }
    public function getCartStatus()
    {
        return $this->cart['payment_status'] ?? null;
    }
    public function getCartExpandedState()
    {
        return array(
            'errors' => $this->getCartErrors(),
            'items' => $this->getCartItems(),
            'state' => $this->getCartStatus(),
            'id' => $this->getCartId(),
            'canpay' => $this->getCanPay(),
            'requested_by' => $this->cart['requested_by'],
            'payment_system' => $this->cart['payment_system'],
            'date_created' => $this->cart['date_created'],
            'date_modified' => $this->cart['date_modified'],
        );
    }
    public function setRequestedBy(string $name)
    {
        $this->cart['requested_by'] = $name;
    }

    public function setCartItems($items, $promocode = "", &$promoApplied = false)
    {
        if (!$this->canEdit()) {
            throw new \Exception('Cart state does not permit editing');
        }
        $errors = array();
        $this->cart_items = array();
        foreach ($items as $key => $badge) {
            $errors[$key] = $this->setCartItem($key, $badge, $promocode, $promoApplied);
        }
        //Do we have errors?
        $this->cart['payment_status'] = count($errors) ? 'NotStarted' : 'NotReady';

        //Did we try a promo code and fail?
        if (!$promoApplied && !empty($data['promocode'])) {
            $result['errors']['promo'] = 'Promo did not apply to any items in the cart';
        }
        $this->refreshCartMeta();
        $this->saveCart();
        return $errors;
    }

    public function setCartItem($cartIx, $item, $promocode = "", &$promoApplied = false)
    {
        if (!isset($item['context_code'])) {
            $item['context_code']='A';
        }
        //Ensure this badge is owned by the user (if we're not editing) and is good on the surface
        if (isset($item['id']) && $item['id'] > 0) {
            $bi = $this->badgeinfo->getSpecificBadge($item['id'], $item['context_code']);
            //TODO: Determine if this badge is already in an active cart and abort
            //Preserve the current badge state, but only if it hasn't been preserved already
            if ($bi !== false && !isset($item['existing'])) {
                $item['existing'] = $bi;
            }
            //If this isn't ours, ensure certain fields aren't tampered with
            if ($bi['contact_id'] != $this->cart['contact_id']) {
                $allowed = array(
                    'notify_email',
                    'can_transfer',
                    'contact_id'
                );
                array_walk($allowed, function ($col) use ($item, $bi) {
                    if (isset($bi[$col])) {
                        $item[$col] = $bi[$col];
                    } else {
                        unset($item[$col]);
                    }
                });
                //And set the contact_id
                $item['contact_id'] = $bi['contact_id'];
            } else {
                $item['contact_id'] = $this->cart['contact_id'];
            }
        } else {
            $item['contact_id'] = $this->cart['contact_id'];
        }

        //If we're not an attendee, we'll need an Application Status field.
        //This is always "InProgress
        if ($item['context_code'] != 'A') {
            $item['application_status'] = 'InProgress';
        }

        $errors = $this->badgevalidator->ValdateCartBadge($item);

        //Try to apply promo code, or otherwise update the price
        $promoApplied = $promoApplied | $this->badgepromoapplicator->TryApplyCode($item, $promocode);
        $this->cart_items[$cartIx] = $item;
        return $errors;
    }

    public function getCartItemByIx($cartIx)
    {
        return $this->cart_items[$cartIx];
    }

    public function findCartItemIxById($context_code, $id)
    {
        foreach ($this->cart_items as $key => $item) {
            if ($item['context_code'] == $context_code && $item['id'] == $id) {
                return $key;
            }
        }
        return false;
    }

    public function getCartItems()
    {
        return $this->cart_items;
    }

    public function getCartErrors(bool $updateReadyStatus = false)
    {
        //Just run through and validate the items as they sit
        $result = array();
        foreach ($this->cart_items as $key => $badge) {
            $result[$key] = $this->badgevalidator->ValdateCartBadge($badge);
        }
        if ($updateReadyStatus) {
            $this->cart['payment_status'] = count($result) ? 'NotStarted' : 'NotReady';
        }
        return $result;
    }

    public function getCartTotal(bool $refresh = true)
    {
        if ($refresh) {
            $cart_payment_txn_amt = 0;

            foreach ($this->cart_items as $key => &$item) {
                $this->badgepromoapplicator->TryApplyCode($item, $item['payment_promo_code'] ?? '');
                $cart_payment_txn_amt += max(0, $item['payment_promo_price'] ?? $item['payment_badge_price']);
                //Check for addons
                if (isset($item['addons'])) {
                    $existingAddons = array_column(
                        $this->badgeinfo->GetAttendeeAddons($item['id']),
                        'payment_status',
                        'addon_id'
                    );
                    $availableaddons = array_column($this->badgeinfo->GetAttendeeAddonsAvailable($item['badge_type_id']), null, 'id');
                    foreach ($item['addons'] as $addon) {
                        if (isset($existingAddons[$addon['addon_id']]) && $existingAddons[$addon['addon_id']] == 'Completed') {
                            continue;
                        }

                        //Prep Sanity check the cart's amount...
                        $cart_payment_txn_amt += max(0, $addon['payment_promo_price'] ?? $addon['payment_price']);
                    }
                }
            }
            $this->cart['payment_txn_amt'] = $cart_payment_txn_amt;
            $this->saveCart();
        }

        return $this->cart['payment_txn_amt'];
    }

    public function refreshCartMeta()
    {
        $this->canPay = true;

        foreach ($this->cart_items as $key => &$item) {
            //Fetch type info
            $bt = $this->badgeinfo->getBadgetType($item['context_code'] ?? 'A', $item['badge_type_id'] ?? 0);

            if ($this->banlist->is_banlisted($item)) {
                $this->AllowPay = false;
            }

            //Check if this item is payable
            if (!empty($bt['payment_deferred']) && $bt['payment_deferred']) {
                $bi = $this->badgeinfo->getSpecificBadge($item['id'] ?? 0, $item['context_code']);

                if ($bi != null) {
                    //Check if it's currently in an approved state
                    switch ($bi['application_status']) {
                        case 'Onboarding':
                        case 'Active':
                        case 'PendingAcceptance':
                        case 'Accepted':
                        case 'Onboatding':
                            break;
                        default:
                            $this->CanPay = false;
                    }
                } else {
                    $this->CanPay = false;
                }
            }
        }
        if ($this->cart['payment_status'] == 'AwaitingApproval' && $this->CanPay) {
            //They must now meet the criteria to pay, switch them to NotStarted
            $this->cart['payment_status'] = 'NotStarted';
        }
    }

    public function setPayProcessor(string $PayProcessor)
    {
        if ($this->cart['payment_system'] != $PayProcessor) {
            $this->cart['payment_system'] = $PayProcessor;
            $this->cart['payment_details'] ="";
            unset($this->pp);
            //Save the current status
            $this->payment->Update($this->cart);
        }
    }
    public function getPayProcessorName(): ?string
    {
        return $this->cart['payment_system'];
    }
    public function getPayProcessor(): PayProcessorInterface
    {
        if (!isset($this->pp)) {
            //try {
            $this->pp = $this->PaymentModuleFactory->Create($this->cart['payment_system']);
            if (!empty($this->cart['payment_details'])) {
                $this->pp->LoadOrder($this->cart['payment_details']);
            }
            //} catch (\Exception $e) {
            //}
        }
        return $this->pp;
    }

    public function SetAllowPay(bool $AllowPay)
    {
        $this->AllowPay = $AllowPay;
    }

    public function isFreeride()
    {
        return $this->cart['payment_txn_amt'] == 0;
    }

    public function resetPayment()
    {
        $this->stagedItems = array();
        $this->cart['payment_details'] = '';
        $this->cart['payment_status'] = 'NotReady';
    }

    //Note we expect all items to have been validated
    public function prepPayment()
    {
        //First do some pre-checks
        $banlisted = false;
        $errors = array();
        $this->refreshCartMeta();

        //TODO: craete the checked versions and use them instead of blind faith

        foreach ($this->cart_items as $key => &$item) {
            //Create/Update the badge
            $bt = $this->badgeinfo->getBadgetType($item['context_code'], $item['badge_type_id']);
            $bi = $this->badgeinfo->getSpecificBadge($item['id'] ?? 0, $item['context_code']);
            $item['payment_id'] = $this->cart['id'];
            $item['payment_status'] = 'Incomplete';
            if (isset($item['existing'])) {
                $this->badgeinfo->UpdateSpecificBadgeUnchecked($item['id'], $item['context_code'], $item);
            } else {
                //Ensure the badge has an owner
                $item['contact_id'] =$item['contact_id'] ?? $this->CurrentUserInfo->GetContactId();
                //Ensure their application Status is "Submitted" if they're not allowed to pay yet
                if ($bi === false) {
                    if ($item['context_code'] != 'A' && !empty($bt['payment_deferred']) && $bt['payment_deferred']) {
                        $item['application_status'] = 'Submitted';
                    }

                    //TODO: Temp hack to ensure there is a valid name_on_badgeOptions
                    if (isset($item['name_on_badge']) && empty($item['name_on_badge'])) {
                        $item['name_on_badge'] = 'Real Name Only';
                    }

                    $newID = $this->badgeinfo->CreateSpecificBadgeUnchecked($item);
                    if ($newID !== false) {
                        $item['id'] = $newID['id'];
                    }
                } else {
                    //TODO: Badge exists? Should we do something special?
                    // Maybe update it with whatever the cart said it should become?
                    //Certainly keep any approval status so when we complete it will remain
                    $item['application_status'] = $bi['application_status'];
                    //$this->badgeinfo->UpdateSpecificBadgeUnchecked($item['id'], $item['context_code'], $item);
                }
            }
            //Save the form responses
            if (isset($item['form_responses'])) {
                $this->badgeinfo->SetSpecificBadgeResponses($item['id'], $item['context_code'], $item['form_responses']);
            }

            //Check for bans
            if ($this->banlist->is_banlisted($item)) {
                $banlisted = true;
                $canpay = false;
                //TODO: Bubble a notify event
                $errors[] = 'Banned:'.$key;
            }
            //TODO: Process applicants too

            //Only add this as a line item if we're a new badge or upgrading (hence needing payment)
            if (!isset($item['existing']) || 0 < $item['payment_promo_price']) {
                $this->stagedItems[] = array(
                    $bt['name'],
                    $bt['price'],
                    1,
                    $bt['description'],
                    $this->CurrentUserInfo->GetEventId() . ':' . $item['context_code'] . ':' . $item['badge_type_id'],
                    max(0, $bt['price'] - ($item['payment_promo_price'] ?? $item['payment_badge_price'])),
                    $item['payment_promo_code'] ?? null
                );
            }
            //Prep Sanity check the cart's amount...
            $this->cart_payment_txn_amt += max(0, $item['payment_promo_price'] ?? $item['payment_badge_price']);

            //Check for addons
            if (isset($item['addons'])) {
                $existingAddons = array_column(
                    $this->badgeinfo->GetAttendeeAddons($item['id']),
                    'payment_status',
                    'addon_id'
                );
                $availableaddons = array_column($this->badgeinfo->GetAttendeeAddonsAvailable($item['badge_type_id']), null, 'id');
                foreach ($item['addons'] as $addon) {
                    if (isset($existingAddons[$addon['addon_id']]) && $existingAddons[$addon['addon_id']] == 'Completed') {
                        continue;
                    }
                    $addon['attendee_id'] = $item['id'];
                    $addon['payment_id'] = $this->cart['id'];
                    $addon['payment_status'] = 'Incomplete';

                    $this->badgeinfo->AddUpdateABadgeAddonUnchecked($addon);

                    //Add it to the payment
                    $faddon = $availableaddons[$addon['addon_id']];

                    $this->stagedItems[] = array(
                        $faddon['name'],
                        $faddon['price'],
                        1,
                        $faddon['description'],
                        $this->CurrentUserInfo->GetEventId() . ':' . $item['context_code'] . ',a:' . $addon['addon_id'],
                        max(0, $faddon['price'] - ($addon['payment_promo_price'] ?? $addon['payment_price'])),
                        $addon['payment_promo_code'] ?? null
                    );

                    //Prep Sanity check the cart's amount...
                    $this->cart_payment_txn_amt += max(0, $addon['payment_promo_price'] ?? $addon['payment_price']);
                }
            }
        }

        $this->getPayProcessor();

        $this->pp->SetReturnURLs(
            $this->FrontendUrlTranslator->GetPaymentReturn($this->cart_uuid),
            $this->FrontendUrlTranslator->GetPaymentCancel($this->cart_uuid)
        );
        foreach ($this->stagedItems as $sitem) {
            call_user_func_array(array($this->pp,'AddItem'), $sitem);
        }


        //Determine new cart status based on flags
        if (!$this->CanPay) {
            $this->cart['payment_status'] = 'AwaitingApproval';
        } else {
            $this->cart['payment_status'] = 'NotStarted';
        }

        //TODO: Real sanity check please
        $this->cart['payment_txn_amt'] = $this->cart_payment_txn_amt;

        $this->saveCart();

        //Report back the errors
        return $errors;
    }

    public function confirmPrep()
    {
        //Make sure we're not AwaitingApproval
        if ($this->cart['payment_status'] == 'AwaitingApproval') {
            return false;
        }

        //If a free-ride we don't do anything
        if ($this->isFreeride()) {
            $this->cart['payment_system']='Freeride';
            return true;
        }

        //Are we in-progress already?

        if ($this->cart['payment_status'] == 'Incomplete') {
            return true;
        } elseif ($this->cart['payment_status'] == 'NotStarted') {
            if ($this->AllowPay && $this->CanPay) {
                $this->getPayProcessor();
                if ($this->pp->ConfirmOrder()) {
                    $payment_details = $this->pp->GetDetails();
                    $this->cart['payment_status'] = 'Incomplete';
                    $this->saveCart();
                    return true;
                } else {
                    throw new \Exception('Failed to confirm order with provider.');
                }
            }
            $this->saveCart();
        }
        return false;
    }

    public function CompletePayment($completionData)
    {
        if (!$this->isFreeride()) {
            $this->getPayProcessor();
            if (!$this->pp->CompleteOrder($completionData)) {
                //Failed. Do we know why?
                $this->cart['payment_status'] = $this->pp->GetOrderStatus();
                $this->saveCart();
                return false;
            }
        }

        foreach ($this->cart_items as $key => &$item) {
            //Update the badge
            if (!isset($item['context_code'])) {
                $item['context_code']='A';
            }
            $bi = $this->badgeinfo->getSpecificBadge($item['id'], $item['context_code']);
            if ($bi !== false) {
                $item['payment_status'] = 'Completed';
                if ($bi['application_status'] == 'AwaitingApproval') {
                    $bi['application_status'] = 'Onboarding';
                }

                $this->badgeinfo->UpdateSpecificBadgeUnchecked($item['id'], $item['context_code'], $item);
                if (!isset($item['existing']) || (isset($item['existing']) && $item['existing']['display_id'] == null)) {
                    $this->badgeinfo->setNextDisplayIDSpecificBadge($item['id'], $item['context_code']);
                }
            } else {
                throw new \Exception('Badge not found?!?' . $item['context_code'] . $item['id']);
            }

            //Check for addons
            if (isset($item['addons'])) {
                foreach ($item['addons'] as &$addon) {
                    $addon['payment_id'] = $this->cart['id'];
                    $addon['payment_status'] = 'Completed';
                    switch ($item['context_code']) {
                        case 'A':
                            $addon['attendee_id'] = $item['id'];
                            $this->badgeinfo->AddUpdateABadgeAddonUnchecked($addon);
                            break;
                        case 'S':
                        //not supported (yet)
                        break;
                        default:
                            $addon['application_id'] = $item['id'];
                            $this->badgeinfo->AddUpdateGBadgeAddonUnchecked($addon);
                            break;
                    }
                }
            }
        }

        $this->cart['payment_status'] = 'Completed';
        $this->cart['payment_date'] = $this->payment->getDbNow();
        $this->saveCart();
        return true;
    }


    public function CancelPayment()
    {
        foreach ($this->cart_items as $key => &$item) {
            //Revert the badge
            $item['payment_status'] = 'Cancelled';
            if (isset($item['existing'])) {
                $this->badgeinfo->UpdateSpecificBadgeUnchecked($item['id'], $item['context_code'], $item['existing']);
            } else {
                $this->badgeinfo->UpdateSpecificBadgeUnchecked($item['id'], $item['context_code'], $item);
            }

            //Check for addons
            if (isset($item['addons'])) {
                foreach ($item['addons'] as &$addon) {
                    $addon['payment_id'] = $this->cart['id'];
                    $addon['payment_status'] = 'Cancelled';
                    switch ($item['context_code']) {
                        case 'A':
                            $addon['attendee_id'] = $item['id'];
                            $this->badgeinfo->AddUpdateABadgeAddonUnchecked($addon);
                            break;
                        case 'S':
                        //not supported (yet)
                        break;
                        default:
                            $addon['application_id'] = $item['id'];
                            $this->badgeinfo->AddUpdateGBadgeAddonUnchecked($addon);
                            break;
                    }
                }
            }
        }
        $this->getPayProcessor()->CancelOrder();
        $this->cart['payment_details'] = '';
        unset($this->pp);
    }

    public function SendStatusEmail()
    {
        foreach ($this->cart_items as $item) {
            $to = $this->CurrentUserInfo->GetContactEmail($item['contact_id']);
            //Get the current info, not what's in the order
            $badge = $this->badgeinfo->getSpecificBadge($item['id'], $item['context_code'], true);
            $template = $this->cart['mail_template'] ?? ($item['context_code'] . '-payment-' .$this->cart['payment_status']);
            try {
                //Attempt to send mail
                return $this->Mail->SendTemplate($to, $template, $badge, $badge['notify_email']);
            } catch (\Exception $e) {
                //Oops, couldn't send. Oh well?
                return false;
            }
        }
    }
}
