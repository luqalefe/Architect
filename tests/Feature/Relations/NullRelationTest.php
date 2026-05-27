<?php

declare(strict_types=1);

namespace LaravelModulesArch\Tests\Feature\Relations;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LaravelModulesArch\Relations\NullRelation;
use LaravelModulesArch\Tests\TestCase;

class NullRelationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('null_relation_parents', function (Blueprint $table) {
            $table->id();
        });
    }

    public function test_get_results_returns_an_empty_collection(): void
    {
        $parent = NullRelationParent::create();

        $relation = new NullRelation($parent);

        $this->assertInstanceOf(Collection::class, $relation->getResults());
        $this->assertCount(0, $relation->getResults());
    }

    public function test_init_relation_sets_empty_collection_on_each_model(): void
    {
        $relation = new NullRelation(new NullRelationParent);
        $models = [new NullRelationParent, new NullRelationParent];

        $relation->initRelation($models, 'children');

        $this->assertCount(0, $models[0]->getRelation('children'));
        $this->assertCount(0, $models[1]->getRelation('children'));
    }

    public function test_match_returns_models_with_empty_relation(): void
    {
        $relation = new NullRelation(new NullRelationParent);
        $models = [new NullRelationParent];

        $result = $relation->match($models, new Collection, 'children');

        $this->assertSame($models, $result);
        $this->assertCount(0, $models[0]->getRelation('children'));
    }

    public function test_extends_eloquent_relation_for_type_compatibility(): void
    {
        $relation = new NullRelation(new NullRelationParent);

        $this->assertInstanceOf(Relation::class, $relation);
    }

    public function test_lazy_property_access_runs_no_queries(): void
    {
        $parent = NullRelationParent::create();

        $queries = $this->countQueriesWhile(function () use ($parent): void {
            /** @var Collection<int, Model> $children */
            $children = $parent->children;

            $this->assertCount(0, $children);
        });

        $this->assertSame(0, $queries, 'NullRelation must not execute any database query on lazy access.');
    }

    public function test_eager_loading_runs_only_the_parent_query(): void
    {
        NullRelationParent::create();
        NullRelationParent::create();

        $queries = $this->countQueriesWhile(function (): void {
            $parents = NullRelationParent::with('children')->get();

            $this->assertCount(2, $parents);
            foreach ($parents as $parent) {
                $this->assertCount(0, $parent->children);
            }
        });

        $this->assertSame(1, $queries, 'Eager loading a NullRelation must only run the parent SELECT, no child query.');
    }

    /**
     * @param  callable():void  $callback
     */
    private function countQueriesWhile(callable $callback): int
    {
        $count = 0;
        DB::listen(function () use (&$count): void {
            $count++;
        });

        $callback();

        return $count;
    }
}

/**
 * @internal Test fixture model — wired with a NullRelation `children` relation.
 */
class NullRelationParent extends Model
{
    protected $table = 'null_relation_parents';

    public $timestamps = false;

    protected $guarded = [];

    public function children(): NullRelation
    {
        return new NullRelation($this);
    }
}
