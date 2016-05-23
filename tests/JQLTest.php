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
		$model = new TestModel();
		$this->jql = new JQL($model);
	}

	public function testBaseLinePulse()
	{
		$this->convertToFluentTest('complex.json', "select * from `bobs` where `contacts`.`is_business` = ? and `contacts`.`is_business` = ? or (`contacts`.`field2` > ? and `tags`.`field2` < ? and `field3` = ? or (`field2` != ? and `city` = ?)) and `contacts`.`is_business` = ?");
	}

	public function test_A_or_PB_and_CP() { //P == parentheses.
		$this->convertToFluentTest('Aor-BandC.json', "select * from `bobs` where `contacts`.`fieldA` > ? or (`contacts`.`fieldB` > ? and `contacts`.`fieldC` > ?)");
	}

	public function test_A_and_PB_or_CP() { //P == parentheses.
		$this->convertToFluentTest('Aand-BorC.json', "select * from `bobs` where `contacts`.`fieldA` > ? and (`contacts`.`fieldB` > ? or `contacts`.`fieldC` > ?)");
	}
	
	public function test_A_or_B_orPC_andDP() {
		$this->convertToFluentTest('Aor-Bor-CandD.json', "select * from `bobs` where `contacts`.`fieldA` > ? or `contacts`.`fieldB` > ? or (`contacts`.`fieldC` > ? and `contacts`.`fieldD` > ?)");
	}

	public function test_AdvancedNested() {
		// A and B and (C or D or E or (F OR (H and I))) and J
		$this->convertToFluentTest('AdvancedNested.json', "select * from `bobs` where `contacts`.`is_business` = ? and `contacts`.`is_business` = ? and (`contacts`.`is_business` = ? or `contacts`.`is_business` = ? or `contacts`.`is_business` = ? or (`contacts`.`is_business` = ? or `contacts`.`is_business` = ? or (`contacts`.`is_business` = ? and `contacts`.`is_business` = ?))) and `contacts`.`is_business` = ?");
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


class TestModel extends Model
{
	public $table = 'bobs';
}
