<?php
namespace Canopy\Test\JQL;

use Canopy\JQL\Exceptions\JQLException;
use Canopy\JQL\JQL;

class JQLTest extends JQLTestCase
{
    /* @var JQL $jql */
    protected $jql;

    public function setUp()
    {
        parent::setUp();
        $model = new Mammal();
        $this->jql = new JQL($model);
    }

    public function testBaseLinePulse()
    {
        $this->convertToFluentTest(
            'complex.json',
            "select * from `bobs` where `bobs`.`is_business` = ? and `bobs`.`is_business` = ? and (`bobs`.`field2` > ? or `bobs`.`field2` < ? or `bobs`.`field3` in (?, ?, ?) or (`bobs`.`field2` != ? or `bobs`.`city` = ?)) and `bobs`.`is_business` = ?"
        );
    }

    public function testInvalidField()
    {
        $this->expectException(JQLException::class);
        $this->expectExceptionMessage('Format must be');

        $json = $this->getJson('invalidField.json');
        $results = $this->jql->convertToFluent($json);

    }

    public function testInvalidOperator()
    {
        $this->expectException(JQLException::class);
        $this->expectExceptionMessage('eqq: Not currently defined');

        $json = $this->getJson('invalidOperator.json');
        $results = $this->jql->convertToFluent($json);

    }

    public function test_A_or_PB_and_CP()
    {
 //P == parentheses.
        $this->convertToFluentTest(
            'Aor-BandC.json',
            "select * from `bobs` where `bobs`.`fieldA` > ? or (`bobs`.`fieldB` > ? and `bobs`.`fieldC` > ?)"
        );
    }

    public function test_A_and_PB_or_CP()
    {
 //P == parentheses.
        $this->convertToFluentTest(
            'Aand-BorC.json',
            "select * from `bobs` where `bobs`.`fieldA` > ? and (`bobs`.`fieldB` > ? or `bobs`.`fieldC` > ?)"
        );
    }

    public function test_A_or_B_orPC_andDP()
    {
        $this->convertToFluentTest(
            'Aor-Bor-CandD.json',
            "select * from `bobs` where `bobs`.`fieldA` > ? or `bobs`.`fieldB` > ? or (`bobs`.`fieldC` > ? and `bobs`.`fieldD` > ?)"
        );
    }

    public function test_A_and_B_in()
    {
        $this->convertToFluentTest(
            'Aand-Bin.json',
            "select * from `bobs` where `bobs`.`A` = ? and `bobs`.`B` in (?, ?, ?)"
        );
    }

    public function test_AdvancedNested()
    {
        // A and B and (C or D or E or (F OR (H and I))) and J
        $this->convertToFluentTest(
            'AdvancedNested.json',
            "select * from `bobs` where `bobs`.`A` = ? and `bobs`.`B` = ? and (`bobs`.`C` = ? or `bobs`.`D` = ? or `bobs`.`E` = ? or (`bobs`.`F` = ? or `bobs`.`G` = ? or (`bobs`.`H` = ? and `bobs`.`I` = ?))) and `bobs`.`J` = ?"
        );
    }

    public function test_simple_join()
    {
        $this->convertToFluentTest(
            'SimpleJoin.json',
            "select * from `bobs` inner join `humans` on `humans`.`id` = `bobs`.`human_id` where `bobs`.`fieldA` > ? and `humans`.`fieldB` > ?"
        );
    }

    public function test_advanced_join()
    {
        $this->convertToFluentTest(
            'AdvancedJoin.json',
            "select * from `bobs` inner join `birds` on `birds`.`id` = `bobs`.`bird_id` inner join `dogs` on `dogs`.`id` = `bobs`.`dog_id` inner join `cats` on `cats`.`id` = `bobs`.`cat_id` where `bobs`.`A` = ? and `bobs`.`B` = ? and (`birds`.`C` = ? or `bobs`.`D` = ? or `bobs`.`E` = ? or (`dogs`.`F` = ? or `dogs`.`G` = ? or (`cats`.`H` = ? and `cats`.`I` = ?))) and `dogs`.`J` = ?"
        );
    }

    public function test_approved_models_to_join()
    {
        $this->jql->setApprovedModels(['Mammal', 'Bird', 'Dog', 'Cat']);
        $this->convertToFluentTest(
            'AdvancedJoin.json',
            "select * from `bobs` inner join `birds` on `birds`.`id` = `bobs`.`bird_id` inner join `dogs` on `dogs`.`id` = `bobs`.`dog_id` inner join `cats` on `cats`.`id` = `bobs`.`cat_id` where `bobs`.`A` = ? and `bobs`.`B` = ? and (`birds`.`C` = ? or `bobs`.`D` = ? or `bobs`.`E` = ? or (`dogs`.`F` = ? or `dogs`.`G` = ? or (`cats`.`H` = ? and `cats`.`I` = ?))) and `dogs`.`J` = ?"
        );
    }

    public function test_approved_models_to_join_throws_exception()
    {
        $this->jql->setApprovedModels(['Mammal', 'Bird', 'Dog', 'Cat']);
        $this->convertToFluentTest(
            'AdvancedJoin.json',
            "select * from `bobs` inner join `birds` on `birds`.`id` = `bobs`.`bird_id` inner join `dogs` on `dogs`.`id` = `bobs`.`dog_id` inner join `cats` on `cats`.`id` = `bobs`.`cat_id` where `bobs`.`A` = ? and `bobs`.`B` = ? and (`birds`.`C` = ? or `bobs`.`D` = ? or `bobs`.`E` = ? or (`dogs`.`F` = ? or `dogs`.`G` = ? or (`cats`.`H` = ? and `cats`.`I` = ?))) and `dogs`.`J` = ?"
        );
    }

    private function convertToFluentTest($filename, $expected)
    {
        $json = $this->getJson($filename);
        $results = $this->jql->convertToFluent($json);

        $this->assertSame($expected, $results->toSql());
    }

    private function getJson($filename)
    {
        $json = file_get_contents(__DIR__ . '/json/' . $filename);

        return $json;
    }
}
