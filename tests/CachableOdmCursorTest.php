<?php

use Pobl\Bongo\Model;
use Pobl\Bongo\Expression;
use Pobl\Bongo\CachableOdmCursor;
use Mockery as m;

class CachableOdmCursorTest extends \PHPUnit_Framework_TestCase
{

    protected $mongoMock = null;
    protected $testCollection = null;
    protected $testQuery = null;
    protected $odmCursor;
    protected $expression;

    public function setUp()
    {
        $this->mongoMock = m::mock('Connection');
        $this->testCollection = m::mock('Collection');
        $this->testQuery = m::mock('Query');
        $this->odmCursor = m::mock(new _stubOdmCursor);

        $this->mongoMock->test_model = $this->testCollection;
        $this->mongoMock->test_database = $this->mongoMock;
        
        $this->objA = new _stubModelForCachable;
        $this->objA->name = 'bob';

        $this->objB = new _stubModelForCachable;
        $this->objB->name = 'billy';

        $this->odmCursor
                ->shouldReceive('toArray')
                ->with(false)
                ->andReturn([
                    $this->objA,
                    $this->objB
        ]);
        
        $this->testQuery
                ->shouldReceive('query')
                ->andReturn(array());
        
        $this->testCollection
                ->shouldReceive('getQueryBuilder')
                ->with('_stubModelForCachable')
                ->andReturn($this->testQuery);

        _stubModelForCachable::$connection = $this->mongoMock;
        _stubModelForCachable::$returnToWhere = $this->odmCursor;
        
        $this->expression = new Expression();
        $this->expression->where('name', 'bob');
    }

    public function tearDown()
    {
        m::close();

        _stubModelForCachable::$connection = null;
    }

    public function testShouldGetCursor()
    {
        $this->odmCursor
                ->shouldReceive('getCursor')
                ->once()
                ->andReturn('theCursor');

        $cachableOdmCursor = new CachableOdmCursor($this->expression, '_stubModelForCachable');
        $result = $cachableOdmCursor->getCursor();

        $this->assertEquals('theCursor', $result);
    }

    public function testShouldCallOdmCursorMethod()
    {
        $this->odmCursor
                ->shouldReceive('randomMethod')
                ->once()
                ->with(1, 2, 3)
                ->andReturn(true);

        $cachableOdmCursor = new CachableOdmCursor($this->expression, '_stubModelForCachable');
        $result = $cachableOdmCursor->randomMethod(1, 2, 3);

        $this->assertTrue($result);
    }

    public function testShouldGetCurrent()
    {
        $cachableOdmCursor = new CachableOdmCursor($this->expression, '_stubModelForCachable');
        $result = $cachableOdmCursor->current();

        $this->assertInstanceOf('_stubModelForCachable', $result);
    }

    public function testShouldConvertToArrayCurrent()
    {
        $cachableOdmCursor = new CachableOdmCursor($this->expression, '_stubModelForCachable');
        $result = $cachableOdmCursor->toArray();
        $should_be = array($this->objA->attributes, $this->objB->attributes);

        $this->assertEquals($should_be, $result);
    }

    public function testShouldGetFirst()
    {
        $cachableOdmCursor = new CachableOdmCursor($this->expression, '_stubModelForCachable');
        $result = $cachableOdmCursor->first();

        $this->assertEquals($this->objA, $result);
    }

    public function testShouldRewind()
    {
        $this->odmCursor->shouldReceive('rewind');

        $cachableOdmCursor = new CachableOdmCursor($this->expression, '_stubModelForCachable');
        $cachableOdmCursor->rewind();
    }

    public function testGoNextAndGetKey()
    {
        $cachableOdmCursor = new CachableOdmCursor($this->expression, '_stubModelForCachable');

        $cachableOdmCursor->next();
        $cachableOdmCursor->next();
        $cachableOdmCursor->next();

        $this->assertEquals(3, $cachableOdmCursor->key());
    }

    public function testShouldSort()
    {
        $this->odmCursor->shouldReceive('sort');

        $cachableOdmCursor = new CachableOdmCursor($this->expression, '_stubModelForCachable');
        $result = $cachableOdmCursor->sort(['name']);
    }

    public function testToJson()
    {
        $cachableOdmCursor = new CachableOdmCursor($this->expression, '_stubModelForCachable');
        $result = $cachableOdmCursor->toJson();

        $shouldBe = json_encode(array($this->objA->attributes, $this->objB->attributes));

        $this->assertEquals($shouldBe, $result);
    }

}

class _stubModelForCachable extends Model
{

    protected $collection = 'test_model';
    protected $database = 'test_database';

    public static $returnToWhere;

    public static function where($query = array(), $fields = array(), $cachable = false)
    {
        return static::$returnToWhere;
    }

}

class _stubOdmCursor
{
    
}
