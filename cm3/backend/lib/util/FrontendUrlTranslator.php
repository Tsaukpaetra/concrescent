<?php

namespace CM3_Lib\util;

use CM3_Lib\database\SearchTerm;

class FrontendUrlTranslator
{
    public function __construct(
        private string $frontend_host,
        private bool $isHashMode,
        private TokenGenerator $TokenGenerator,
    ) {
    }

    public function routedURL(string $route)
    {
        return $this->frontend_host . ($this->isHashMode ? '#/' : '') . $route;
    }

    public function GetPaymentReturn(string $cart_uuid = '')
    {
        return $this->routedURL('cart?checkout=confirm&cart_uuid=' . $cart_uuid);
    }
    public function GetPaymentCancel(string $cart_uuid = '')
    {
        return $this->routedURL('cart?checkout=cancel&cart_uuid=' . $cart_uuid);
    }
    public function GetBadgeLoad(array $badge)
    {
        return $this->routedURL('myBadges?context_code='. $badge['context_code'] . '&id=' . $badge['id'] . '&uuid=' . $badge['uuid']);
    }
    public function GetCartLoad(array $badge)
    {
        if (isset($badge['payment_id'])) {
            return $this->GetLoginConfirm($this->TokenGenerator->forLoginOnly(
                $badge["contact_id"],
                $badge["event_id"]
            ),'cart?id='. $badge['payment_id']);
        }
        //Dafuq we have a badge that has no payment_id?
        return '';
    }
    public function GetLoginConfirm(string $authString, string $returnTo = null)
    {
        $result = $this->routedURL('login?token=' . $authString);
        if (!empty($returnTo)) {
            $result .= '&returnTo=' .urlencode($returnTo);
            $result .= '&justgo=1';
        }
        return $result;
    }
}
