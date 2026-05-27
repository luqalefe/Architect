<?php

declare(strict_types=1);

namespace LaravelModulesArch\Tests\Feature\Relations;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LaravelModulesArch\Relations\NullSingularRelation;
use LaravelModulesArch\Tests\TestCase;

class NullSingularRelationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('null_singular_parents', function (Blueprint $table) {
            $table->id();
        });
    }

    public function test_get_results_returns_null(): void
    {
        $parent = NullSingularParent::create();

        $relation = new NullSingularRelation($parent);

        $this->assertNull($relation->getResults());
    }

    public function test_init_relation_sets_null_on_each_model(): void
    {
        $relation = new NullSingularRelation(new NullSingularParent);
        $models = [new NullSingularParent, new NullSingularParent];

        $relation->initRelation($models, 'partner');

        $this->assertNull($models[0]->getRelation('partner'));
        $this->assertNull($models[1]->getRelation('partner'));
    }

    public function test_match_returns_models_with_null_relation(): void
    {
        $relation = new NullSingularRelation(new NullSingularParent);
        $models = [new NullSingularParent];

        $result = $relation->match($models, new Collection, 'partner');

        $this->assertSame($models, $result);
        $this->assertNull($models[0]->getRelation('partner'));
    }

    public function test_extends_eloquent_relation_for_type_compatibility(): void
    {
        $this->assertInstanceOf(Relation::class, new NullSingularRelation(new NullSingularParent));
    }

    public function test_lazy_property_access_runs_no_queries_and_yields_null(): void
    {
        $parent = NullSingularParent::create();

        $queries = $this->countQueriesWhile(function () use ($parent): void {
            $partner = $parent->partner;

            $this->assertNull($partner);
        });

        $this->assertSame(0, $queries, 'NullSingularRelation must not execute any database query on lazy access.');
    }

    public function test_eager_loading_runs_only_the_parent_query_and_yields_null_per_row(): void
    {
        NullSingularParent::create();
        NullSingularParent::create();

        $queries = $this->countQueriesWhile(function (): void {
            $parents = NullSingularParent::with('partner')->get();

            $this->assertCount(2, $parents);
            foreach ($parents as $parent) {
                $this->assertNull($parent->partner);
            }
        });

        $this->assertSame(1, $queries, 'Eager loading a NullSingularRelation must only run the parent SELECT, no child query.');
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
 * @internal Test fixture model — wired with a NullSingularRelation `partner` relation.
 */
class NullSingularParent extends Model
{
    protected $table = 'null_singular_parents';

    public $timestamps = false;

    protected $guarded = [];

    public function partner(): NullSingularRelation
    {
        return new NullSingularRelation($this);
    }
}
