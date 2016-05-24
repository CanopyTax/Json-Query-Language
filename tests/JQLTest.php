<?php namespace Test;

use Canopy\JQL\JQL;
use Illuminate\Database\Eloquent\Model;

class JQLTest extends JQLTestCase
{
	/* @var JQL $jql */
	protected $jql;

	public function setUp()
	{
		parent::setUp();
		$model = new Mamal();
		$this->jql = new JQL($model);
	}

	public function testBaseLinePulse()
	{
		$this->convertToFluentTest('complex.json', "select * from `bobs` where `bobs`.`is_business` = ? and `bobs`.`is_business` = ? and (`bobs`.`field2` > ? or `bobs`.`field2` < ? or `bobs`.`field3` = ? or (`bobs`.`field2` != ? or `bobs`.`city` = ?)) and `bobs`.`is_business` = ?");
	}

	public function test_A_or_PB_and_CP() { //P == parentheses.
		$this->convertToFluentTest('Aor-BandC.json', "select * from `bobs` where `bobs`.`fieldA` > ? or (`bobs`.`fieldB` > ? and `bobs`.`fieldC` > ?)");
	}

	public function test_A_and_PB_or_CP() { //P == parentheses.
		$this->convertToFluentTest('Aand-BorC.json', "select * from `bobs` where `bobs`.`fieldA` > ? and (`bobs`.`fieldB` > ? or `bobs`.`fieldC` > ?)");
	}

	public function test_A_or_B_orPC_andDP() {
		$this->convertToFluentTest('Aor-Bor-CandD.json', "select * from `bobs` where `bobs`.`fieldA` > ? or `bobs`.`fieldB` > ? or (`bobs`.`fieldC` > ? and `bobs`.`fieldD` > ?)");
	}

	public function test_AdvancedNested() {
		// A and B and (C or D or E or (F OR (H and I))) and J
		$this->convertToFluentTest('AdvancedNested.json', "select * from `bobs` where `bobs`.`A` = ? and `bobs`.`B` = ? and (`bobs`.`C` = ? or `bobs`.`D` = ? or `bobs`.`E` = ? or (`bobs`.`F` = ? or `bobs`.`G` = ? or (`bobs`.`H` = ? and `bobs`.`I` = ?))) and `bobs`.`J` = ?");
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


class Mamal extends Model
{
	public $table = 'bobs';
}
