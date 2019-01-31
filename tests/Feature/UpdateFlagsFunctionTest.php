<?php

namespace Tests\Feature;

use App\Ldaplibs\SettingsManager;
use Tests\TestCase;

class UpdateFlagsFunctionTest extends TestCase
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

    public function testGetSCIMUpdateFlags()
    {
        $settings = new SettingsManager();
        $SCIMUpdateFlag = $settings->getUpdateFlags('scim', 'toni18', 'AAA');
        self::assertTrue(0 === $SCIMUpdateFlag);
    }

    public function testGetCSVUpdateFlags()
    {
        $settings = new SettingsManager();
        $CSVUpdateFlag = $settings->getUpdateFlags('csv', 'toni18', 'AAA');
        self::assertTrue(0 === $CSVUpdateFlag);
    }

    public function testSetSCIMUpdateFlags(): void
    {
        $settings = new SettingsManager();
        $settings->setUpdateFlags('scim', 'sadye16', 'AAA', 1);
        self::assertTrue(1 === $settings->getUpdateFlags('csv', 'sadye16', 'AAA'));
    }

    public function testSetCSVUpdateFlags()
    {
        $settings = new SettingsManager();
        $settings->setUpdateFlags('csv', 'sadye16', 'AAA', 1);
        self::assertTrue(1 === $settings->getUpdateFlags('csv', 'sadye16', 'AAA'));
    }
}
