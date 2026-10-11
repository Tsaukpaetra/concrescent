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
        // 1. Look for the custom header from the active HTTP request
        // (PHP automatically prefixes headers with HTTP_ and uppercases them)
        $clientHeader = $_SERVER['HTTP_X_FRONTEND_BASE_URL'] ?? null;

        if ($clientHeader) {
            // Trim any trailing slashes from the header and leading slashes from the route
            // to prevent double-slash bugs (e.g., .../#//users/42)
            return rtrim($clientHeader, '/') . '/' . ltrim($route, '/');
        }

        // 2. Fallback to your legacy configuration if the header is missing
        $base = $this->frontend_host;
        if ($this->isHashMode) {
            $base = rtrim($base, '/') . '/#';
        }
        
        return rtrim($base, '/') . '/' . ltrim($route, '/');
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
        $route = ($badge['context_code'] == 'A' || $badge['context_code'] == 'S' || isset($badge['application_id'])) ? 'myBadges' : 'myApplications';
        return $this->routedURL($route.'?context_code='. $badge['context_code'] . '&id=' . $badge['id'] . '&uuid=' . $badge['uuid']);
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
        }
        return $result;
    }
}
