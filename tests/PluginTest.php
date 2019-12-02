<?php

namespace LoewenstarkRedirect\Tests;

use LoewenstarkRedirect\LoewenstarkRedirect as Plugin;
use Shopware\Components\Test\Plugin\TestCase;

class PluginTest extends TestCase
{
    protected static $ensureLoadedPlugins = [
        'LoewenstarkRedirect' => []
    ];

    public function testCanCreateInstance()
    {
        /** @var Plugin $plugin */
        $plugin = Shopware()->Container()->get('kernel')->getPlugins()['LoewenstarkRedirect'];

        $this->assertInstanceOf(Plugin::class, $plugin);
    }
}
