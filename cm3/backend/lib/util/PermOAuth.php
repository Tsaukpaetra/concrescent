<?php

namespace CM3_Lib\util;

class PermOAuth extends Bitmask
{
    public const READ_ACCOUNT = 1;
    public const WRITE_ACCOUNT = 2;
    public const READ_BADGES = 4;
    public const READ_CART = 8;
    public const WRITE_CART = 16;
    //More to follow...
    public const NoScope = 0;
}
