<?php
namespace Canopy\Test\JQL;

use Canopy\JQL\Exceptions\JQLException;
use Canopy\JQL\Exceptions\JQLValidationException;
use Canopy\JQL\JQL;

class JQLTest extends JQLTestCase
{
    /* @var JQL $jql */
    protected $jql;

    public function setUp()
    {
        parent::setUp();
        $model = new Mammal();
        $this->jql = new JQL($model, 'mammals');

        $whitelist = [
            'mammals' => [
                'A' => ['eq'],
                'B' => ['eq', 'in'],
                'C' => ['eq'],
                'D' => ['eq', 'gt'],
                'E' => ['eq'],
                'F' => ['eq'],
                'G' => ['eq'],
                'field_1' => ['eq', 'gt'],
                'field_2' => ['lt', 'gt', 'eq', 'ne'],
                'field_3' => ['eq', 'gt', 'in'],
            ],
            'birds' => [
                'C' => ['eq'],
            ],
            'cats' => [
                'H' => ['eq'],
                'I' => ['eq'],
            ],
            'dogs' => [
                'F' => ['eq'],
                'G' => ['eq'],
                'J' => ['eq'],
            ],
            'humans' => [
                'field_2' => ['gt'],
            ],
        ];
        $this->jql->setApprovedOperators($whitelist);
    }

    public function testMainModelGetters()
    {
        $model = new Mammal();
        $jql = new JQL($model);
        $this->assertEquals($model, $jql->getMainModel());
        $this->assertEquals('Mammal', $jql->getMainModelName());
        $this->assertEquals('Mammal', $jql->getMainModelAlias());
    }

    public function testMainModelGettersWithCustomAlias()
    {
        $model = new Mammal();
        $alias = uniqid();
        $jql = new JQL($model, $alias);
        $this->assertEquals($model, $jql->getMainModel());
        $this->assertEquals('Mammal', $jql->getMainModelName());
        $this->assertEquals($alias, $jql->getMainModelAlias());
    }

    public function testApprovedOperatorsGetter()
    {
        $whitelist = [uniqid('table') => [uniqid('field') => ['eq']]];
        $this->jql->setApprovedOperators($whitelist);
        $this->assertEquals($whitelist, $this->jql->getApprovedOperators());
    }

    public function testBaseLinePulse()
    {
        $this->convertToFluentTest(
            'complex.json',
            "select * from `bobs` where `bobs`.`A` = ? and `bobs`.`B` = ? and (`bobs`.`field_2` > ? or `bobs`.`field_2` < ? or `bobs`.`field_3` in (?, ?, ?) or (`bobs`.`field_2` != ? or `bobs`.`C` = ?)) and `bobs`.`D` = ?"
        );
    }

    public function testInvalidFieldFormat()
    {
        $this->expectException(JQLValidationException::class);
        $this->expectExceptionMessage('Format must be');

        $json = $this->getJson('invalidFieldFormat.json');
        $this->jql->convertToFluent($json);

    }

    public function testInvalidModel()
    {
        $this->expectException(JQLValidationException::class);
        $this->expectExceptionMessage('reptiles.A');

        $json = $this->getJson('invalidModel.json');
        $this->jql->convertToFluent($json);
    }

    public function testInvalidField()
    {
        $this->expectException(JQLValidationException::class);
        $this->expectExceptionMessage('mammals.Z');

        $json = $this->getJson('invalidField.json');
        $this->jql->convertToFluent($json);
    }
    
    public function testInvalidOperator()
    {
        $this->expectException(JQLValidationException::class);
        $this->expectExceptionMessage('eqq: Not currently defined');

        $json = $this->getJson('invalidOperator.json');
        $this->jql->convertToFluent($json);

    }

    public function test_A_or_PB_and_CP()
    {
 //P == parentheses.
        $this->convertToFluentTest(
            'Aor-BandC.json',
            "select * from `bobs` where `bobs`.`field_1` > ? or (`bobs`.`field_2` > ? and `bobs`.`field_3` > ?)"
        );
    }

    public function test_A_and_PB_or_CP()
    {
 //P == parentheses.
        $this->convertToFluentTest(
            'Aand-BorC.json',
            "select * from `bobs` where `bobs`.`field_1` > ? and (`bobs`.`field_2` > ? or `bobs`.`field_3` > ?)"
        );
    }

    public function test_A_or_B_orPC_andDP()
    {
        $this->convertToFluentTest(
            'Aor-Bor-CandD.json',
            "select * from `bobs` where `bobs`.`field_1` > ? or `bobs`.`field_2` > ? or (`bobs`.`field_3` > ? and `bobs`.`D` > ?)"
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
        // A and B and (C or D or E or (F OR (field_1 and field2))) and field_3
        $this->convertToFluentTest(
            'AdvancedNested.json',
            "select * from `bobs` where `bobs`.`A` = ? and `bobs`.`B` = ? and (`bobs`.`C` = ? or `bobs`.`D` = ? or `bobs`.`E` = ? or (`bobs`.`F` = ? or `bobs`.`G` = ? or (`bobs`.`field_1` = ? and `bobs`.`field_2` = ?))) and `bobs`.`field_3` = ?"
        );
    }

    public function test_simple_join()
    {
        $this->convertToFluentTest(
            'SimpleJoin.json',
            "select * from `bobs` inner join `humans` on `humans`.`id` = `bobs`.`human_id` where `bobs`.`field_1` > ? and `humans`.`field_2` > ?"
        );
    }

    public function test_advanced_join()
    {
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
