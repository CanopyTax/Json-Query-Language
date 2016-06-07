<?php namespace CanopyTax\Test;

use CanopyTax\JQL\Exceptions\JQLDecodeException;
use CanopyTax\JQL\Exceptions\JQLException;
use CanopyTax\JQL\Exceptions\JQLValidationException;
use CanopyTax\JQL\JQL;

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
                'A' => ['eq', 'ne'],
                'B' => ['eq', 'in', 'nin'],
                'C' => ['eq'],
                'D' => ['eq', 'gt'],
                'E' => ['eq'],
                'F' => ['eq'],
                'G' => ['eq'],
                'field_1' => ['eq', 'gt'],
                'field_2' => ['lt', 'gt', 'eq', 'ne'],
                'field_3' => ['eq', 'gt', 'in'],
                'field_4' => ['eq', 'gt', 'in', 'ne'],
                'field_5' => ['between'],
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
            'magic' => [
                'A' => ['eq'],
            ],
        ];

        $tableMap = [
            'birds' => ['birds.id', 'bobs.bird_id'],
            'cats' => ['cats.bob_id', 'bobs.id'],
            'dogs' => ['dogs.id', 'bobs.dog_id'],
            'humans' => ['humans.id', 'bobs.human_id'],
            'ggs' => ['ggs.id', 'bobs_ggs.gg_id', 'bobs_ggs'],
            'bobs_ggs' => ['bobs_ggs.bob_id', 'bobs.id'],
        ];

        $fieldMap = [
            'mammals.A' => ['bobs', '`bobs`.`A`'],
            'mammals.B' => ['bobs', '`bobs`.`B`'],
            'mammals.C' => ['bobs', '`bobs`.`C`'],
            'mammals.D' => ['bobs', '`bobs`.`D`'],
            'mammals.E' => ['bobs', '`bobs`.`E`'],
            'mammals.F' => ['bobs', '`bobs`.`F`'],
            'mammals.G' => ['ggs', '`ggs`.`gg`'],
            'mammals.field_1' => ['bobs', '`bobs`.`field_1`'],
            'mammals.field_2' => ['bobs', '`bobs`.`field_2`'],
            'mammals.field_3' => ['bobs', '`bobs`.`field_3`'],
            'mammals.field_4' => ['bobs', 'to_char(to_timestamp((`bobs`.`field_4`)::NUMERIC / 1000), \'MM-DD\'))', 'to_char(to_timestamp({{value}} / 1000), \'MM-DD\')'],
            'mammals.field_5' => ['bobs', '`bobs`.`field_5`'],
        ];

        $this->jql->setApprovedOperators($whitelist)
            ->setTableMap($tableMap)
            ->setFieldMap($fieldMap);
    }

    public function testInvalidJson()
    {
        $this->expectException(JQLDecodeException::class);
        $this->expectExceptionCode(JSON_ERROR_SYNTAX);
        $model = new Mammal();
        $jql = new JQL($model);
        $jql->convertToFluent('{');
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

    public function testTableMapGetter()
    {
        $testData = [uniqid('table') => [uniqid()]];
        $this->jql->setTableMap($testData);
        $this->assertEquals($testData, $this->jql->getTableMap());
    }

    public function testFieldMapGetter()
    {
        $testData = [uniqid('table') => [uniqid()]];
        $this->jql->setFieldMap($testData);
        $this->assertEquals($testData, $this->jql->getFieldMap());
    }

    public function testBaseLinePulse()
    {
        $this->convertToFluentTest(
            'complex.json',
            "select * from `bobs`"
                ." where `bobs`.`A` = ? and `bobs`.`B` = ?"
                ." and (`bobs`.`field_2` > ? or `bobs`.`field_2` < ?"
                    ." or `bobs`.`field_3` in (?, ?, ?) or (`bobs`.`field_2` != ? or `bobs`.`C` = ?)"
                .") and `bobs`.`D` = ?"
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

    public function testInvalidFieldOperator()
    {
        $this->expectException(JQLValidationException::class);
        $this->expectExceptionMessage('mammals.A: Operator "in" not allowed');

        $json = $this->getJson('invalidFieldOperator.json');
        $this->jql->convertToFluent($json);
    }

    public function testInvalidOperator()
    {
        $this->expectException(JQLValidationException::class);
        $this->expectExceptionMessage('eqq: Not currently defined');

        $json = $this->getJson('invalidOperator.json');
        $this->jql->convertToFluent($json);
    }

    public function testUnjoinableTable()
    {
        $this->expectException(JQLException::class);
        $this->expectExceptionMessage('magic.A: Could not find way to join table');

        $json = $this->getJson('unjoinableModel.json');
        $this->jql->convertToFluent($json);
    }

    public function testCastValueInWhereClause()
    {
        $this->convertToFluentTest(
            'CastValue.json',
            "select * from `bobs` where to_char(to_timestamp((`bobs`.`field_4`)::NUMERIC / 1000), 'MM-DD')) = to_char(to_timestamp(? / 1000), 'MM-DD')",
            [1465316562797] // Bindings
        );
    }

    public function testCastValueInWhereClauseIsNull()
    {
        $this->convertToFluentTest(
            'CastValueEqNull.json',
            "select * from `bobs` where to_char(to_timestamp((`bobs`.`field_4`)::NUMERIC / 1000), 'MM-DD')) is null"
        );
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
            "select * from `bobs`"
                ." where `bobs`.`field_1` > ? or `bobs`.`field_2` > ? or (`bobs`.`field_3` > ? and `bobs`.`D` > ?)"
        );
    }

    public function testEqNull()
    {
        $this->convertToFluentTest(
            'EqNull.json',
            "select * from `bobs` where `bobs`.`A` is null"
        );
    }

    public function testNeNull()
    {
        $this->convertToFluentTest(
            'NeNull.json',
            "select * from `bobs` where `bobs`.`A` is not null"
        );
    }

    public function testJsonObjectDecoded()
    {
        $json = $this->getJson('EqNull.json');
        $decoded = json_decode($json);
        $results = $this->jql->convertToFluent($decoded);
        $this->assertSame("select * from `bobs` where `bobs`.`A` is null", $results->toSql());
    }

    public function testJsonArrayDecoded()
    {
        $this->expectException(JQLException::class);
        $this->expectExceptionMessage('JSON string or object was expected');
        $json = $this->getJson('EqNull.json');
        $decoded = json_decode($json, true);
        $this->jql->convertToFluent($decoded);
    }

    public function testNonJsonObjectDecodedJson()
    {
        $this->expectException(JQLException::class);
        $this->expectExceptionMessage('JSON string or object was expected');
        $this->jql->convertToFluent(42);
    }

    public function testJsonMissingJql()
    {
        $this->expectException(JQLException::class);
        $this->expectExceptionMessage('Missing jql property of JSON');
        $this->jql->convertToFluent(new \stdClass());
    }

    public function test_A_and_B_in()
    {
        $this->convertToFluentTest(
            'Aand-Bin.json',
            "select * from `bobs` where `bobs`.`A` = ? and `bobs`.`B` in (?, ?, ?)"
        );
    }

    public function testNin()
    {
        $this->convertToFluentTest(
            'Nin.json',
            "select * from `bobs` where `bobs`.`B` not in (?, ?, ?)"
        );
    }
    public function test_AdvancedNested()
    {
        // A and B and (C or D or E or (F OR (field_1 and field2))) and field_3
        $this->convertToFluentTest(
            'AdvancedNested.json',
            "select * from `bobs`"
                ." where `bobs`.`A` = ? and `bobs`.`B` = ?"
                ." and (`bobs`.`C` = ? or `bobs`.`D` = ? or `bobs`.`E` = ?"
                    ." or (`bobs`.`F` = ? or (`bobs`.`field_1` = ? and `bobs`.`field_2` = ?))"
                .") and `bobs`.`field_3` = ?"
        );
    }

    public function test_simple_join()
    {
        $this->convertToFluentTest(
            'SimpleJoin.json',
            "select * from `bobs`"
                ." inner join `humans` on `humans`.`id` = `bobs`.`human_id`"
                ." where `bobs`.`field_1` > ? and `humans`.`field_2` > ?"
        );
    }

    public function test_advanced_join()
    {
        $this->convertToFluentTest(
            'AdvancedJoin.json',
            "select * from `bobs`"
                ." inner join `bobs_ggs` on `bobs_ggs`.`bob_id` = `bobs`.`id`"
                ." inner join `ggs` on `ggs`.`id` = `bobs_ggs`.`gg_id`"
                ." inner join `birds` on `birds`.`id` = `bobs`.`bird_id`"
                ." inner join `dogs` on `dogs`.`id` = `bobs`.`dog_id`"
                ." inner join `cats` on `cats`.`bob_id` = `bobs`.`id`"
                ." where `bobs`.`A` = ? and `bobs`.`B` = ? and `ggs`.`gg` = ?"
                ." and (`birds`.`C` = ? or `bobs`.`D` = ? or `bobs`.`E` = ?"
                    ." or (`dogs`.`F` = ? or `dogs`.`G` = ? or (`cats`.`H` = ? and `cats`.`I` = ?))"
                .") and `dogs`.`J` = ?"
        );
    }

    private function convertToFluentTest($filename, $expected, array $bindings = [])
    {
        $json = $this->getJson($filename);
        $results = $this->jql->convertToFluent($json);

        $this->assertSame($expected, $results->toSql());

        if (!empty($bindings)) {
            $this->assertSame($bindings, $results->getBindings());
        }
    }

    private function getJson($filename)
    {
        $json = file_get_contents(__DIR__ . '/json/' . $filename);

        return $json;
    }
}
