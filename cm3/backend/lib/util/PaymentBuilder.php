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
    private array $cart_errors = array();
    private string $cart_uuid = "";
    private float $cart_payment_txn_amt = 0;//Our own sanity check against the cart
    private bool $AllowPay = true;
    private bool $RequiresApproval = false;
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

    public function resetCart($contact_id = null, $requested_by = '[self]')
    {
        $this->cart = array(
            'uuid' => '00000000-0000-0000-0000-000000000000',
            'event_id' => $this->CurrentUserInfo->GetEventId(),
            'contact_id' => $contact_id ?? $this->CurrentUserInfo->GetContactId(),
            'requested_by' => $requested_by,
            'items' => '[]',
            'payment_status' => 'NotReady',
            'payment_system' => 'Cash',
            'payment_details' => '',
            'payment_txn_amt' => -1,
            'date_created' =>'',
            'date_modified' =>'',
            'notes' =>'',
        );
        $this->cart_items = array();
        $this->cart_payment_txn_amt = 0;
        $this->AllowPay = true;
        $this->CanPay = true;
        $this->RequiresApproval = false;
        $this->pp = null;
        $this->stagedItems = array();
    }

    public function createCart($contact_id = null, $requested_by = '[self]')
    {
        $this->resetCart($contact_id,$requested_by );
        $createdcart = $this->payment->Create(array_diff_key($this->cart, array_flip(['uuid','date_created','date_modified'])));
        $this->cart = array_merge($this->cart, $createdcart, $this->payment->GetByID($createdcart['id'], array(
            'uuid','payment_details',
            'date_created' ,'date_modified' ,'notes'
        )));
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
            'date_created' ,'date_modified' ,'notes'
        ));
        return $this->loadCartFromPayment($cart, $expectedEventId, $expectedContactId);
    }

    public function loadCartFromPayment($paymentData, $expectedEventId = null, $expectedContactId = null)
    {
        //TODO: Check that we have necessary columns

        if ($paymentData === false) {
            $this->resetCart();
            return false;
        }

        //Check that the cart is in the right event, and right contact
        if (
            (!is_null($expectedEventId) && $paymentData['event_id'] != $expectedEventId)
            ||(!is_null($expectedContactId) && $paymentData['contact_id'] != $expectedContactId)
        ) {
            $this->resetCart();
            return false;
        }

        $this->cart = $paymentData;
        //Extract and remove the UUID, since we never want to try saving it back
        $this->cart_uuid = $paymentData['uuid'];
        unset($this->cart['uuid']);
        $this->cart_items = json_decode($paymentData['items'], true) ?? array();
        $this->cart_payment_txn_amt = 0;
        $this->AllowPay = true;
        $this->CanPay = true;
        $this->RequiresApproval = false;
        $this->pp = null;
        $this->stagedItems = array();

        //Tell the badge validator the cart id
        $this->badgevalidator->Set_Payment_Id($this->getCartId());

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

    public function SetIgnoreBadgeTypeAvailability(bool $ignoreBadgeTypeAvailability)
    {
        $this->badgevalidator->Set_IgnoreBadgeTypeAvailability($ignoreBadgeTypeAvailability);
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
            ($this->cart['payment_status'] == 'NotStarted'
            ||$this->cart['payment_status'] == 'Incomplete'
            ||$this->cart['payment_status'] == 'Cancelled'
            ||$this->cart['payment_status'] == 'ForbidPayment')
            &&$this->cartErrorCount()==0
        )
        ) {
            return false;
        }
        return $this->AllowPay;
    }
    public function getNotes()
    {
        return $this->cart['notes']??'';
    }
    public function setNotes(?string $notes)
    {
        $this->cart['notes'] = $notes;
    }
    public function getCanPay()
    {
        return $this->CanPay;
    }
    public function getRequiresApproval()
    {
        return $this->RequiresApproval;
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
            'uuid' => $this->cart_uuid,
            'canEdit' => $this->canEdit(),
            'RequiresApproval' => $this->getRequiresApproval(),
            'canPay' => $this->getCanPay(),
            'canCheckout' => $this->canCheckout(),
            'requested_by' => $this->cart['requested_by'],
            'payment_system' => $this->cart['payment_system'],
            'payment_txn_amt' =>$this->cart['payment_txn_amt'],
            'date_created' => $this->cart['date_created'],
            'date_modified' => $this->cart['date_modified'],
            'notes' => $this->cart['notes'] ??'',
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
        $isGroupApplication = !($item['context_code'] == 'A' || $item['context_code'] == 'S');
        //Ensure this badge is owned by the user (if we're not editing) and is good on the surface
        if (isset($item['id']) && $item['id'] > 0) {
            //Group apps are special
            if (!$isGroupApplication) {
                $bi = $this->badgeinfo->getSpecificBadge($item['id'], $item['context_code']);
            } else {
                $bi = $this->badgeinfo->getASpecificGroupApplication($item['id'] ?? 0, $item['context_code'], true);
            }
            //TODO: Determine if this badge is already in an active cart and abort
            //Preserve the current badge state, but only if it hasn't been preserved already
            if ($bi !== false && !isset($item['existing'])) {
                $item['existing'] = $bi;
            }
            //TODO: Make this actually work right
            // //Disallowed fields
            $disallowed = array();
            // $disallowed = array(
            //     'application_status',
            //     'editBadgePriorAddons',
            //     'editBadgePriorBadgeId',
            //     'existing',
            //     'id',
            //     'payment_badge_price',
            //     'payment_id',
            //     'payment_promo_amount',
            //     'payment_promo_code',
            //     'payment_promo_description',
            //     'payment_promo_price',
            //     'payment_promo_type',
            //     'payment_status',
            // )
            //If this isn't ours, ensure certain fields aren't tampered with
            if ($bi['contact_id'] != $this->cart['contact_id']) {
                $disallowed =array_merge($disallowed, array(
                    'notify_email',
                    'can_transfer',
                    'contact_id'
                ));
                array_walk($disallowed, function ($col) use ($item, $bi) {
                    if (isset($bi[$col])) {
                        $item[$col] = $bi[$col];
                    } else {
                        unset($item[$col]);
                    }
                });
                //And set the contact_id
                $item['contact_id'] = $bi['contact_id'] ?? $this->cart['contact_id'];
            } else {
                $item['contact_id'] = $this->cart['contact_id'];
            }
        } else {
            $item['contact_id'] = $this->cart['contact_id'];
        }

        //Check if there are subbadges
        if (isset($item['subbadges'])) {
            $existingids = [];
            if (isset($item['existing']['subbadges'])) {
                $existingids = array_column($item['existing'] ['subbadges'], 'id');
            }
            foreach ($item['subbadges'] as &$subbadge) {
                if (!in_array($subbadge['id']??0, $existingids)) {
                    $subbadge['created'] = true;
                }
            }
        }

        //If we're not an attendee, we'll need an Application Status field.
        //This is always "InProgress" unless they've already been accepted
        if ($item['context_code'] != 'A' && empty($item['application_status'])) {
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

    public function getCartErrors()
    {
        return $this->cart_errors;
    }
    public function cartErrorCount()
    {
        return array_reduce($this->cart_errors, function ($errorCount, $itemErrors) {
            return $errorCount + count($itemErrors);
        }, 0);
    }

    public function getCartTotal(bool $refresh = true)
    {
        if ($refresh) {
            $this->stageItems();
        }

        return $this->cart['payment_txn_amt'];
    }

    public function refreshCartMeta()
    {
        $this->canPay = true;

        $this->getCartTotal(true);

        foreach ($this->cart_items as $key => &$item) {
            //Fetch type info
            $bt = $this->badgeinfo->getBadgetType($item['context_code'] ?? 'A', $item['badge_type_id'] ?? 0);

            if ($this->banlist->is_banlisted($item)) {
                $this->AllowPay = false;
            }

            //If the badge type is invalid, short circuit because the rest of this loop does not make sense
            if ($bt === false) {
                $this->AllowPay = false;
                continue;
            }

            $item['badge_type_name'] = $bt['name'];

            //Check if this item is payable
            if (!empty($bt['payment_deferred']) && $bt['payment_deferred']) {
                $this->RequiresApproval = true;
                //Get the badge info depending on staff or not
                if ($item['context_code'] == 'S') {
                    $bi = $this->badgeinfo->getSpecificBadge($item['id'] ?? 0, $item['context_code']);
                } else {
                    $bi = $this->badgeinfo->getASpecificGroupApplication($item['id'] ?? 0, $item['context_code']);
                }

                if ($bi != null && isset($bi['application_status'])) {
                    //Check if it's currently in an approved state
                    switch ($bi['application_status']) {
                        case 'Onboarding':
                        case 'Active':
                        case 'PendingAcceptance':
                        case 'Accepted':
                            break;
                        case 'Rejected':
                        case 'Waitlisted':
                            $this->AllowPay = false;
                            // no break
                        default:
                            $this->CanPay = false;
                    }
                    //Sync down said status into the cart
                    $item['application_status'] = $bi['application_status'];
                } else {
                    $this->CanPay = false;
                }
            }
        }
        //If they're already attempting to pay, and this badge type is one that needs to be approved first,
        // skip some interfering checks

        if ($this->cart['payment_status'] == 'Incomplete' && $this->CanPay && $this->AllowPay && (!empty($bt['payment_deferred']) && $bt['payment_deferred'])) {
            $this->SetIgnoreBadgeTypeAvailability(true);
        }
        if ($this->cart['payment_status'] == 'AwaitingApproval' && $this->CanPay && $this->AllowPay) {
            //They must now meet the criteria to pay, switch them to NotStarted
            $this->cart['payment_status'] = 'NotStarted';
        }

        //Just run through and validate the items as they sit
        $this->cart_errors = array();
        foreach ($this->cart_items as $key => $badge) {
            $this->cart_errors[$key] = $this->badgevalidator->ValdateCartBadge($badge);
        }
        if ($this->cart['payment_status'] == 'NotStarted') {
            $this->cart['payment_status'] = count($this->cart_errors) ? 'NotStarted' : 'NotReady';
        }
    }

    private function stageItems()
    {
        //Reset the staged items
        $this->stagedItems = [];
        $this->cart_payment_txn_amt = 0;
        //TODO: craete the checked versions and use them instead of blind faith

        foreach ($this->cart_items as $key => &$cartitem) {
            if (!isset($cartitem['context_code'])) {
                $cartitem['context_code']='A'; //Frowny face
            }
            $this->badgepromoapplicator->TryApplyCode($cartitem, $cartitem['payment_promo_code'] ?? '');

            $bt = $this->badgeinfo->getBadgetType($cartitem['context_code'], $cartitem['badge_type_id'] ?? 0);
            if ($bt === false) {
                $this->AllowPay = false;
                continue;
            }
            $saveFormResponses = true;
            $badgeFreebies = 0;
            //If it's not an application, wire up the processor normally
            if ($cartitem['context_code'] == 'A' || $cartitem['context_code'] == 'S') {

                //Only add this as a line item if we're a new badge or upgrading (hence needing payment)
                if (!isset($cartitem['existing']) || 0 < $cartitem['payment_promo_price']) {
                    $this->stagedItems[] = array(
                    $bt['name'] . ' Badge',
                    $bt['price'],
                    1,
                    $bt['description'],
                    $this->CurrentUserInfo->GetEventId() . ':' . $cartitem['context_code'] . ':' . $cartitem['badge_type_id'],
                    max(0, $bt['price'] - ($cartitem['payment_promo_price'] ?? $cartitem['payment_badge_price'] ?? 99999)),
                    $cartitem['payment_promo_code'] ?? null
                );
                    //Add to the cart's amount...
                    $this->cart_payment_txn_amt += max(0, $cartitem['payment_promo_price'] ?? $cartitem['payment_badge_price'] ?? 99999);
                }
            } else {
                //Group applications are special

                //Base application price
                if (!isset($cartitem['existing']) || 0 < $cartitem['payment_promo_price']) {
                    $this->stagedItems[] = array(
                    $bt['name'],
                    $bt['price'],
                    1,
                    'Application fee for ' . $bt['name'],
                    $this->CurrentUserInfo->GetEventId() . ':' . $cartitem['context_code'] . ':S' . $cartitem['badge_type_id'],
                    max(0, $bt['price'] - ($cartitem['payment_promo_price'] ?? $cartitem['payment_badge_price'])),
                    null
                );
                    //Add to the cart's amount...
                    $this->cart_payment_txn_amt += max(0, $cartitem['payment_promo_price'] ?? $cartitem['payment_badge_price']);
                }
                //Get the current badge info (for the assignment count)
                $bi = $this->badgeinfo->getASpecificGroupApplication($cartitem['id'] ?? 0, $cartitem['context_code']);
                $cartitem['assignment_count_charging'] = [];
                //TODO: Validate that assignment_count wouldn't normally be passed through a non-admin cart
                $badge_assignment_count_current = $bi ? ($bi['assignment_count']?? 0 ) : $cartitem['assignment_count']?? 0 ;
                $badge_assignment_count_prior = $cartitem['existing']['assignment_count'] ?? 0;
                $cartitem['assignment_count_prior'] =$badge_assignment_count_prior ; 
                //die($badge_assignment_count_prior);
                
                for ($assignSpace=0; $assignSpace < ($badge_assignment_count_current); $assignSpace++) {
                    //Assignment space price
                    if ($badge_assignment_count_prior < $assignSpace) {
                        $this->stagedItems[] = array(
                        $bt['name'] . ' Assignment fee' . ($bt['base_assignment_count']> $assignSpace ? ' (Included /w base)' : ''),
                        $bt['base_assignment_count']> $assignSpace ? 0 : $bt['price_per_assignment'],
                        1,
                        'Assignment fee for ' . $bt['name'] . ', slot ' . ($assignSpace + 1) . ($bt['base_assignment_count']> $assignSpace ? ', (Included in base price)' : ''),
                        $this->CurrentUserInfo->GetEventId() . ':' . $cartitem['context_code'] . ':T' . $cartitem['badge_type_id'],
                        0, //Assignments can never be on promotion
                        null
                    );

                        //Add to the cart's amount...
                        $this->cart_payment_txn_amt += max(0, $bt['base_assignment_count']> $assignSpace ? 0 : $bt['price_per_assignment']);
                        $cartitem['assignment_count_charging'][] =['slot'=> $assignSpace + 1, 'price' => $bt['base_assignment_count']> $assignSpace ? 0 : $bt['price_per_assignment'], 'prepaid' => false ];
                    } else {
                        //Already paid for this slot, add the placeholder
                        $cartitem['assignment_count_charging'][] =['slot'=> $assignSpace + 1, 'price' => 0, 'prepaid' => true ];

                    }
                }
                //Also set the approved assignment count for the UI
                $cartitem['assignment_count'] = $badge_assignment_count_current;

                //Sort the subbadges to have the Created ones last
                usort($cartitem['subbadges'], function ($a, $b) {
                    return ($a['created'] ?? 0)- ($b['created'] ?? 0);
                });
                $sbadgeCount = 0;
                foreach ($cartitem['subbadges'] as &$badge) {
                    $badge_fee = (($badge['created'] ?? false) && $bt['base_applicant_count'] <= $sbadgeCount) ? $bt['price_per_applicant'] : 0;
                    if ($badge_fee > 0) {
                        $this->stagedItems[] = array(
                            $bt['name'] . ' Badge fee',
                            $badge_fee,
                            1,
                            'Badge fee for ' . $bt['name'],
                            $this->CurrentUserInfo->GetEventId() . ':' . $cartitem['context_code'] . ':B' . $cartitem['badge_type_id'],
                            0, //(this type of) badge can never be on promotion
                            null
                        );
                        $this->cart_payment_txn_amt += $badge_fee;
                    }
                    $badge['payment_price'] =''. $badge_fee;

                    $sbadgeCount++;
                }
            }


            //Check for addons
            if (isset($cartitem['addons'])) {
                $existingAddons = array_column(
                    $this->badgeinfo->GetAddons($cartitem['id']??0, $cartitem['context_code']),
                    'payment_status',
                    'addon_id'
                );
                $availableaddons = array_column($this->badgeinfo->GetAddonsAvailable($cartitem['badge_type_id'], $cartitem['context_code']), null, 'id');
                
                foreach ($cartitem['addons'] as $addon) {
                    if (!isset($addon['addon_id'])) {
                        continue;
                    }
                    if (isset($existingAddons[$addon['addon_id']]) && $existingAddons[$addon['addon_id']] == 'Completed') {
                        continue;
                    }
                    if (isset($availableaddons[$addon['addon_id']])) {
                        //Add it to the payment
                        $faddon = $availableaddons[$addon['addon_id']];
    
                        $this->stagedItems[] = array(
                            $faddon['name'],
                            $faddon['price'],
                            1,
                            $faddon['description'],
                            $this->CurrentUserInfo->GetEventId() . ':' . $cartitem['context_code'] . ',a:' . $addon['addon_id'],
                            max(0, $faddon['price'] - ($addon['payment_promo_price'] ?? $addon['payment_price'])),
                            $addon['payment_promo_code'] ?? null
                        );
    
                        //Prep Sanity check the cart's amount...
                        $this->cart_payment_txn_amt += max(0, $addon['payment_promo_price'] ?? $addon['payment_price']);
                    }
                }
            }
        }
        $this->cart['payment_txn_amt'] = $this->cart_payment_txn_amt;
        //$this->saveCart();
    }

    private function applyInFlightChanges()
    {

        //TODO: craete the checked versions and use them instead of blind faith

        foreach ($this->cart_items as $key => &$cartitem) {
            //Create/Update the badge
            $cartitem['payment_id'] = $this->cart['id'];
            $cartitem['payment_status'] = 'Incomplete';

            $badge_items = [];

            $bt = $this->badgeinfo->getBadgetType($cartitem['context_code'], $cartitem['badge_type_id']);
            $saveFormResponses = true;
            $badgeFreebies = 0;
            //If it's not an application, wire up the processor normally
            if ($cartitem['context_code'] == 'A' || $cartitem['context_code'] == 'S') {
                $badgeItems = [&$cartitem];


                //Check for bans
                if ($this->banlist->is_banlisted($cartitem)) {
                    $banlisted = true;
                    $canPay = false;
                    //TODO: Bubble a notify event
                    $errors[] = 'Banned:'.$key;
                }
                $this->createUpdateBadgeEntry($cartitem);

                //Save the form responses
                if (isset($cartitem['form_responses'])) {
                    $this->badgeinfo->SetFormResponses($cartitem['id'], $cartitem['context_code'], $cartitem['form_responses']);
                }
            } else {
                //Grou applications are special
                //Create/update the application submission
                $this->createUpdateApplicationSubmission($cartitem, $bt);

                $saveFormResponses = false;
                //Save the form responses
                if (isset($cartitem['form_responses'])) {
                    $this->badgeinfo->SetFormResponses($cartitem['id'], $cartitem['context_code'], $cartitem['form_responses']);
                }
            }


            //Check for addons
            if (isset($cartitem['addons'])) {
                $existingAddons = array_column(
                    $this->badgeinfo->GetAddons($cartitem['id'], $cartitem['context_code']),
                    'payment_status',
                    'addon_id'
                );
                $availableaddons = array_column($this->badgeinfo->GetAddonsAvailable($cartitem['badge_type_id'], $cartitem['context_code']), null, 'id');
                foreach ($cartitem['addons'] as $addon) {
                    if (isset($existingAddons[$addon['addon_id']]) && $existingAddons[$addon['addon_id']] == 'Completed') {
                        continue;
                    }
                    $addon[($cartitem['context_code'] == 'A' || $cartitem['context_code'] == 'S') ? 'attendee_id' : 'application_id'] = $cartitem['id'];
                    $addon['context_code'] = $cartitem['context_code'];
                    $addon['payment_id'] = $this->cart['id'];
                    $addon['payment_status'] = 'Incomplete';

                    $this->badgeinfo->AddUpdateBadgeAddonUnchecked($addon);
                }
            }
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

    public function is_approved_status(string $application_status)
    {
        return in_array($application_status, [
        'PendingAcceptance',
        'Accepted',
        'Onboarding', //Accepted, onboarding in progress
        'Active',     //Accepted, active staff
        ]);
    }
    public function is_submitted_status(string $application_status)
    {
        return in_array($application_status, [
            'Submitted',
            'Cancelled',
            'Rejected',
            'PendingAcceptance',
            'Accepted',
            'Onboarding', //Accepted, onboarding in progress
            'Active',     //Accepted, active staff
            'Terminated', //No longer welcome
            'Waitlisted',
            ]);
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
        if($this->cart['payment_status'] == 'InProgress')
        {
            return [];
        }
        $banlisted = false;
        $errors = array();
        $this->refreshCartMeta();
        $this->stageItems();

        $this->applyInFlightChanges();

        $this->getPayProcessor();
        // make sure the order is reset
        if (!$this->pp->ResetItems()) {
            throw new \Exception('Could not prep payment because the order on this transation is already completed?');
        }

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

    private function createUpdateApplicationSubmission(&$item, $bt)
    {

        //TODO: Temp hack to ensure there is a valid name_on_badgeOptions
        if (isset($item['name_on_badge']) && (empty($item['name_on_badge']) || $item['name_on_badge']=='')) {
            $item['name_on_badge'] = 'Real Name Only';
        }

        $bi = $this->badgeinfo->getASpecificGroupApplication($item['id'] ?? 0, $item['context_code']);
        if (isset($item['existing'])) {
            if (!empty($bt['payment_deferred']) && $bt['payment_deferred']
            &&  !$this->is_submitted_status($bi['application_status'])) {
                $item['application_status'] = 'Submitted';
            } else {
                //Take the application status of the active badge info
                $item['application_status'] = $bi['application_status'];
            }
            $this->badgeinfo->UpdateSpecificGroupApplicationUnchecked($item['id'], $item['context_code'], $item);
        } else {
            //Ensure the badge has an owner
            $item['contact_id'] =$item['contact_id'] ?? $this->CurrentUserInfo->GetContactId();
            //Ensure their application Status is "Submitted" if they're not allowed to pay yet
            //and not already accepted
            if ($bi === false) {
                if (!empty($bt['payment_deferred']) && $bt['payment_deferred']
                &&  !$this->is_submitted_status($item['application_status'])) {
                    $item['application_status'] = 'Submitted';
                }

                $newID = $this->badgeinfo->CreateSpecificGroupApplicationUnchecked($item);
                if ($newID !== false) {
                    $item['id'] = $newID['id'];
                }
            } else {
                //TODO: Badge exists? Should we do something special?
                // Maybe update it with whatever the cart said it should become?
                //Certainly keep any approval status so when we complete it will remain
                if (!$this->is_submitted_status($item['application_status'])) {
                    $item['application_status'] = $bi['application_status'];
                }
                $this->badgeinfo->UpdateSpecificGroupApplicationUnchecked($item['id'], $item['context_code'], $item);
            }
        }
    }
    private function createUpdateBadgeEntry(&$item)
    {

        //TODO: Temp hack to ensure there is a valid name_on_badgeOptions
        if (isset($item['name_on_badge']) && (empty($item['name_on_badge']) || $item['name_on_badge']=='')) {
            $item['name_on_badge'] = 'Real Name Only';
        }

        $bi = $this->badgeinfo->getSpecificBadge($item['id'] ?? 0, $item['context_code']);
        if (isset($item['existing'])) {
            $this->badgeinfo->UpdateSpecificBadgeUnchecked($item['id'], $item['context_code'], $item);
        } else {
            //Ensure the badge has an owner
            $item['contact_id'] =$item['contact_id'] ?? $this->CurrentUserInfo->GetContactId();
            //Ensure their application Status is "Submitted" if they're not allowed to pay yet
            //and not already accepted
            if ($bi === false) {
                if ($item['context_code'] != 'A' && !$this->is_submitted_status($item['application_status'])) {
                    $item['application_status'] = ($item['context_code'] == 'S') ? 'Submitted' : 'PendingAcceptance';
                }


                $newID = $this->badgeinfo->CreateSpecificBadgeUnchecked($item);
                if ($newID !== false) {
                    $item['id'] = $newID['id'];
                }
            } else {
                //TODO: Badge exists? Should we do something special?
                // Maybe update it with whatever the cart said it should become?
                //Certainly keep any approval status so when we complete it will remain
                if (!$this->is_submitted_status($item['application_status'] ??'')) {
                    $item['application_status'] = $bi['application_status'];
                }
                $this->badgeinfo->UpdateSpecificBadgeUnchecked($item['id'], $item['context_code'], $item);
            }
        }
    }

    public function confirmPrep()
    {
        //Make sure we're not AwaitingApproval
        if ($this->cart['payment_status'] == 'AwaitingApproval'
        || $this->cart['payment_status'] == 'ForbidPayment') {
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

        foreach ($this->cart_items as $key => &$cartitem) {
            //Update the badge
            if (!isset($cartitem['context_code'])) {
                $cartitem['context_code']='A';
            }

            //If it's not an application, wire up the processor normally
            if ($cartitem['context_code'] == 'A' || $cartitem['context_code'] == 'S') {
                $this->completeBadge($cartitem);
            } else {
                //Group applications are special
                //Create/update the application submission
                $this->completeGroupApplication($cartitem);
            }

            //Check for addons
            if (isset($cartitem['addons'])) {
                foreach ($cartitem['addons'] as &$addon) {
                    $addon['payment_id'] = $this->cart['id'];
                    $addon['payment_status'] = 'Completed';
                    switch ($cartitem['context_code']) {
                        case 'A':
                            $addon['attendee_id'] = $cartitem['id'];
                            $this->badgeinfo->AddUpdateABadgeAddonUnchecked($addon);
                            break;
                        case 'S':
                        //not supported (yet)
                        break;
                        default:
                            $addon['application_id'] = $cartitem['id'];
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

    private function completeGroupApplication(&$item)
    {
        $bi = $this->badgeinfo->getASpecificGroupApplication($item['id'], $item['context_code']);
        if ($bi !== false) {
            $item['payment_status'] = 'Completed';
            if ($bi['application_status'] == 'PendingAcceptance') {
                $item['application_status'] = 'Accepted';
            }
            $item['display_id'] = $bi['display_id'];
            $this->badgeinfo->UpdateSpecificGroupApplicationUnchecked($item['id'], $item['context_code'], $item);
        } else {
            throw new \Exception('Application not found?!?' . $item['context_code'] . $item['id']);
        }
    }

    private function completeBadge(&$item)
    {
        $bi = $this->badgeinfo->getSpecificBadge($item['id'], $item['context_code']);
        if ($bi !== false) {
            $item['payment_status'] = 'Completed';
            if ($bi['application_status'] == 'PendingAcceptance') {
                $item['application_status'] = 'Onboarding';
            }
            // die(print_r($item,true));
            $item['display_id'] = $bi['display_id'];

            $this->badgeinfo->UpdateSpecificBadgeUnchecked($item['id'], $item['context_code'], $item);
        } else {
            throw new \Exception('Badge not found?!?' . $item['context_code'] . $item['id']);
        }
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
        $this->cart['payment_status'] = 'Cancelled';
        $this->saveCart();
    }

    public function SendStatusEmail()
    {
        foreach ($this->cart_items as $item) {
            $to = $this->CurrentUserInfo->GetContactEmail($item['contact_id']);
            //Get the current info, not what's in the order
            $template = $this->cart['mail_template'] ?? ($item['context_code'] . '-payment-' .$this->cart['payment_status']);
            //If it's not an application, wire up the processor normally
            if ($item['context_code'] == 'A' || $item['context_code'] == 'S') {
                $badgeItems = [ $this->badgeinfo->getSpecificBadge($item['id'], $item['context_code'], true)];
            } else {
                $groupApp = $this->badgeinfo->getASpecificGroupApplication($item['id'] ?? 0, $item['context_code'], true);
                $this->Mail->SendTemplate($to, $template, $groupApp, null);
                // //Send the application status
                // $template = $cartitem['context_code'] . '-application-' .$cartitem['application_status'];
                //
                // try {
                //     //Attempt to send mail
                //     $to = $this->CurrentUserInfo->GetContactEmail($cartitem['contact_id']);
                //     if (!$this->Mail->SendTemplate($to, $template, $cartitem, $cartitem['notify_email'])) {
                //         $errors['sentUpdate'] =  false;
                //     }
                // } catch (\Exception $e) {
                //     //Oops, couldn't send. Oh well?
                //     $errors['sentUpdate'] = $e->getMessage();
                // }
                //Only if payment is complete should we send out the badge emails
                $badgeItems = [];
            }

            try {
                //Attempt to send mail(s)
                $anyFail = false;
                foreach ($badgeItems as $badge) {
                    $anyFail |= !$this->Mail->SendTemplate($to, $template, $badge, $badge['notify_email'] ?? null);
                }
                return !$anyFail;
            } catch (\Exception $e) {
                //Oops, couldn't send. Oh well?
                return false;
            }
        }
    }
}
