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
		$this->convertToFluentTest('complex.json', "select * from `bobs` where `mamals`.`is_business` = ? and `mamals`.`is_business` = ? and (`mamals`.`field2` > ? or `tags`.`field2` < ? or `mamals`.`field3` = ? or (`mamals`.`field2` != ? or `mamals`.`city` = ?)) and `mamals`.`is_business` = ?");
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
		$this->convertToFluentTest('AdvancedNested.json', "select * from `bobs` where `contacts`.`A` = ? and `contacts`.`B` = ? and (`contacts`.`C` = ? or `contacts`.`D` = ? or `contacts`.`E` = ? or (`contacts`.`F` = ? or `contacts`.`G` = ? or (`contacts`.`H` = ? and `contacts`.`I` = ?))) and `contacts`.`J` = ?");
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
