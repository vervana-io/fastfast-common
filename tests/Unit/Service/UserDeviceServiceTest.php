<?php

namespace Tests\Unit\Service;

use FastFast\Common\Service\UserDeviceService;
use PHPUnit\Framework\TestCase;

class UserDeviceServiceTest extends TestCase
{
    public function test_disable_user_device_returns_zero_when_no_identifiers()
    {
        $svc = new UserDeviceService();
        $this->assertSame(0, $svc->disableUserDevice(1));
    }
}


