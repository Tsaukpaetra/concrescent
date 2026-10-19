<?php

namespace CM3_Lib\util;

class PermOAuth extends Bitmask
{
    public const READ_ACCOUNT_BASIC = 1;
    public const READ_ACCOUNT_FULL = 2;
    public const WRITE_ACCOUNT = 4;
    public const READ_BADGES = 8;
    public const READ_CART = 16;
    public const WRITE_CART = 32;
    //More to follow...
    public const NoScope = 0;
}
