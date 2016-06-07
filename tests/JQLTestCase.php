<?php namespace CanopyTax\Test;

use PHPUnit_Framework_TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;

class JQLTestCase extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        parent::setUp();
            $capsule = new Capsule;
            $capsule->addConnection([
                'driver'    => 'mysql',
                'host'      => 'localhost',
                'database'  => 'database',
                'username'  => 'root',
                'password'  => 'password',
                'charset'   => 'utf8',
                'collation' => 'utf8_unicode_ci',
                'prefix'    => '',
            ]);
            $capsule->setAsGlobal();
            $capsule->bootEloquent();
    }
}
