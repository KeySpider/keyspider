<?php

namespace Tests\Feature;

use App\Ldaplibs\SettingsManager;
use Tests\TestCase;

class GetFlagsTest extends TestCase
{
    public function testGetDeleteFlags()
    {
        $settings = new SettingsManager();
        $deleteFlags = $settings->getFlags()['deleteFlags'];
        $this->assertEquals(["AAA.015", "BBB.012", "CCC.011"], $deleteFlags);
    }

    public function testGetUpdateFlags()
    {
        $settings = new SettingsManager();
        $updateFlags = $settings->getFlags()['updateFlags'];
        $this->assertEquals(["AAA.016", "BBB.013", "CCC.012"], $updateFlags);
    }

}
