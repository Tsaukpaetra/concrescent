<?php

namespace CM3_Lib\util;

use MessagePack\BufferUnpacker;
use MessagePack\Packer;
use MessagePack\Extension;

class OAuthPermissions implements Extension
{
    public PermOAuth $OAuthPerms;

    public function __construct()
    {
        $this->OAuthPerms = new PermOAuth(0);
    }

    public function getType(): int
    {
        // Arbitrary const for this project
        return 62;
    }

    public function pack(Packer $packer, mixed $value): ?string
    {
        //Just in case we get passed in a non-Permissions object
        if (!$value instanceof PermOAuth) {
            return null;
        }

        //First, pack the PermOAuth
        $result = $packer->pack($value->getValue());

        //Give back the result
        return $packer->packExt($this->getType(), $result);
    }

    public function unpackExt(BufferUnpacker $unpacker, int $extLength): PermOAuth
    {
        $result = new PermOAuth(0);
        //We'll always have an OAuthPerms if we've been packed at all
        $result->setValue($unpacker->unpack());
        //Give back our new Permissions!
        return $result;
    }

}
